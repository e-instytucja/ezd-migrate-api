<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use JsonSerializable;

final readonly class RejestrRpwPrzypisanieSzczegolyDto implements JsonSerializable
{
    /**
     * @param array<string, string> $labels
     */
    public function __construct(
        public RejestrRpwPrzypisanieSzczegolyWartosciDto $values,
        public array $labels,
        public ?string $sectionLabel = 'Szczegóły przypisania RPW',
    ) {
    }

    public static function fromPodstawa(
        RejestrRpwPrzypisanieWartosciDto $podstawa,
        ?RejestrRpwWysylkaDto $wysylka = null,
        ?InteresantDto $adresat = null,
        array $historiaObiegu = [],
        ?string $sectionLabel = null,
    ): self {
        return new self(
            values: RejestrRpwPrzypisanieSzczegolyWartosciDto::fromPodstawa(
                $podstawa,
                $wysylka,
                $adresat,
                $historiaObiegu,
            ),
            labels: self::defaultLabels(),
            sectionLabel: $sectionLabel,
        );
    }

    /**
     * @return array<string, string>
     */
    public static function defaultLabels(): array
    {
        $labels = [
            'id_przypisania_rejestru' => 'ID przypisania',
            'uid_przypisania_rejestru' => 'UID przypisania',
            'id_dokumentu' => 'ID dokumentu',
            'numer_przypisania' => 'Numer',
            'typ_przypisania' => 'Typ przypisania',
            'uid_rejestru' => 'UID rejestru',
            'typ_rejestru' => 'Typ rejestru',
            'opis_rejestru' => 'Opis rejestru',
            'data_utworzenia' => 'Data utworzenia',
            'uid_przesylki_nadrzednej' => 'UID przesyłki nadrzędnej',
            'nazwa_procesu' => 'Nazwa procesu',
            'wysylka' => 'Wysyłka',
            'wysylka.data_wyslania' => 'Data wysłania',
            'wysylka.nr_nadawczy' => 'Numer nadawczy',
            'wysylka.forma_doreczenia' => 'Forma doręczenia',
            'wysylka.forma_doreczenia.klucz' => 'Klucz formy doręczenia',
            'wysylka.forma_doreczenia.nazwa' => 'Nazwa formy doręczenia',
            'wysylka.przesylka_elektroniczna' => 'Przesyłka elektroniczna',
            'wysylka.przesylka_elektroniczna.rpw_shipment_id' => 'ID przesyłki RPW',
            'wysylka.przesylka_elektroniczna.status' => 'Status przesyłki',
            'wysylka.przesylka_elektroniczna.data_wyslania' => 'Data wysłania (EN)',
            'adresat' => 'Adresat',
            'historia_obiegu' => 'Historia obiegu',
        ];

        foreach (InteresanciDto::defaultLabels() as $key => $label) {
            $labels['adresat.' . $key] = $label;
        }

        return $labels;
    }

    /**
     * @return array{sectionLabel: ?string, labels: array<string, string>, values: RejestrRpwPrzypisanieSzczegolyWartosciDto}
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
