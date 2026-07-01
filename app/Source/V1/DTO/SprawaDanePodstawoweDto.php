<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use App\Shared\Functions;
use App\Source\V1\Enum\TypFormularza;
use JsonSerializable;

final readonly class SprawaDanePodstawoweDto implements JsonSerializable
{
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
//                terminRealizacji: null,
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
     */
    public static function fromCaseRow(array $row, object $titleAndDesc): self
    {
        $registerDate = $row['data_rejestracji_dokumentu'];
        $realizationTime = $row['czas_realizacji'];
        $terminRealizacji = null;

        if ($realizationTime >= 0) {
            $terminRealizacji = Functions::convertToISO8601(
                Functions::extendDateByDays($registerDate, $realizationTime),
            );
        }

        return self::fromValues(
            new SprawaDanePodstawoweWartosciDto(
                nazwaProcesu: $row['nazwa_procesu'],
                idProcesu: $row['id_procesu'],
                typFormularza: TypFormularza::tryFromWiersza($row['typ_formularza'] ?? null),
                statusPismaWiodacego: $row['status_procesu'],
                dataRejestracji: Functions::convertToISO8601($registerDate),
                dataUtworzenia: Functions::convertToISO8601($row['data_utworzenia_dokumentu']),
                terminRealizacji: $terminRealizacji,
                tytulSprawy: $titleAndDesc->tytul_sprawy,
                opisSprawy: $titleAndDesc->opis_sprawy,
            ),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function defaultLabels(): array
    {
        return [
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
