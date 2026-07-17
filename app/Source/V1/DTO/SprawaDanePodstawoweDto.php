<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use App\Shared\Functions;
use App\Source\V1\Enum\TypFormularza;
use Exception;
use JsonSerializable;

final readonly class SprawaDanePodstawoweDto implements JsonSerializable
{
    private const STATUSY_ZAKONCZONE = ['Z', 'ZS', 'ZA', 'T'];

    public function __construct(
        public SprawaDanePodstawoweWartosciDto $values,
        /** @var array<string, string> */
        public array                           $labels,
        public ?string                         $sectionLabel = null,
    ) {}

    public static function empty(): self
    {
        return new self(
            values: new SprawaDanePodstawoweWartosciDto(
//                nazwaProcesu: '',
//                idProcesu: null,
//                statusPismaWiodacego: '',
//                dataRejestracji: '',
//                dataUtworzenia: '',
//                terminRealizacji: '',
//                tytulSprawy: '',
//                opisSprawy: '',
            ),
            labels: self::defaultLabels(),
            sectionLabel: 'Dane podstawowe',
        );
    }

    public static function fromValues(
        SprawaDanePodstawoweWartosciDto $values,
        ?string                         $sectionLabel = 'Dane podstawowe',
    ): self {
        return new self($values, self::defaultLabels(), $sectionLabel);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws Exception
     */
    public static function fromCaseRow(array $row, object $titleAndDesc): self
    {
        return self::fromValues(
            new SprawaDanePodstawoweWartosciDto(
                idSprawy: $row['id_sprawy'] ?? null,
                nazwaProcesu: $row['nazwa_procesu'],
                idProcesu: $row['id_procesu'],
                typFormularza: TypFormularza::tryFromWiersza($row['typ_formularza'] ?? null),
                statusPismaWiodacego: $row['status_procesu'],
                dataRejestracji: Functions::convertToISO8601($row['data_rejestracji_dokumentu']),
                dataUtworzenia: Functions::convertToISO8601($row['data_utworzenia_dokumentu']),
                terminRealizacji: self::resolveTerminRealizacji($row),
                tytulSprawy: $titleAndDesc->tytul_sprawy,
                opisSprawy: $titleAndDesc->opis_sprawy,
            ),
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws Exception
     */
    private static function resolveTerminRealizacji(array $row): string
    {
        $czasRealizacji = (int) $row['czas_realizacji'];
        $dataRejestracji = $row['data_rejestracji_dokumentu'];
        $sprawaFinishdate = $row['sprawa_finishdate'] ?? null;
        $status = isset($row['status']) ? (string) $row['status'] : null;

        if ($czasRealizacji >= 0) {
            return Functions::convertToISO8601(
                Functions::extendDateByDays($dataRejestracji, $czasRealizacji),
            );
        }

        if (!empty($sprawaFinishdate)) {
            return Functions::convertToISO8601($sprawaFinishdate);
        }

        if (self::isSprawaNiezakonczona($status)) {
            throw new Exception('brak czasu realizacji dla sprawy niezakończonej');
        }

        throw new Exception('brak czasu realizacji dla sprawy zakończonej');
    }

    private static function isSprawaNiezakonczona(?string $status): bool
    {
        return $status === null || !in_array($status, self::STATUSY_ZAKONCZONE, true);
    }

    /**
     * @return array<string, string>
     */
    public static function defaultLabels(): array
    {
        return [
            'idSprawy' => 'Identyfikator sprawy',
            'nazwaProcesu' => 'Nazwa procesu',
            'idProcesu' => 'Identyfikator procesu',
            'typFormularza' => 'Typ formularza',
            'statusPismaWiodacego' => 'Status pisma wiodącego',
            'dataRejestracji' => 'Data rejestracji',
            'dataUtworzenia' => 'Data utworzenia',
            'terminRealizacji' => 'Termin realizacji',
            'tytulSprawy' => 'Tytuł sprawy',
            'opisSprawy' => 'Opis sprawy',
        ];
    }

    /**
     * @return array{sectionLabel: ?string, labels: array<string, string>, values: SprawaDanePodstawoweWartosciDto}
     */
    public function jsonSerialize(): array
    {
        return [
            'sectionLabel' => $this->sectionLabel,
            'labels' => $this->labels,
            'values' => $this->values,
        ];
    }
}
