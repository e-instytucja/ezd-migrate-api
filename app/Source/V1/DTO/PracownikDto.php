<?php
declare(strict_types=1);

namespace App\Source\V1\DTO;

final readonly class PracownikDto
{
    public function __construct(
        public ?int    $stanowiskoId = null,
        public ?string $stanowiskoSkrot = null,
        public ?string $stanowiskoNazwa = null,
        public ?string $komorkaSkrot = null,
        public ?string $komorkaNazwa = null,
        public ?string $imie = null,
        public ?string $nazwisko = null,
        public ?string $nazwisko2 = null,
        public ?string $nazwisko3 = null,
        public ?string $imieNazwisko = null,
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    public static function fromUugInfo(?object $uugInfo = null): self
    {
        return self::fromWorkstationRow($uugInfo);
    }

    public static function fromWorkstationRow(?object $row = null): self
    {
        if($row === null) {
            return new self();
        }
        return new self(
            stanowiskoId: isset($row->workstation_id) ? (int) $row->workstation_id : null,
            stanowiskoSkrot: $row->workstation_name ?? null,
            stanowiskoNazwa: $row->workstation_description ?? null,
            komorkaSkrot: $row->departament_name ?? null,
            komorkaNazwa: $row->departament_description ?? null,
            imie: $row->forename ?? null,
            nazwisko: $row->surname ?? null,
            nazwisko2: ($row->surname2 ?? '') !== '' ? $row->surname2 : null,
            nazwisko3: ($row->surname3 ?? '') !== '' ? $row->surname3 : null,
            imieNazwisko: self::buildImieNazwisko($row),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDocumentRow(array $row): self
    {
        return new self(
            stanowiskoId: isset($row['wlasciciel_stanowisko_id']) ? (int) $row['wlasciciel_stanowisko_id'] : null,
            stanowiskoSkrot: $row['wlasciciel_stanowisko_skrot'] ?? null,
            stanowiskoNazwa: $row['wlasciciel_stanowisko_nazwa'] ?? null,
            komorkaSkrot: $row['wlasciciel_komorka_skrot'] ?? null,
            komorkaNazwa: $row['wlasciciel_komorka_nazwa'] ?? null,
            imie: $row['wlasciciel_imie'] ?? null,
            nazwisko: $row['wlasciciel_nazwisko'] ?? null,
            nazwisko2: $row['wlasciciel_nazwisko2'] ?? null,
            nazwisko3: $row['wlasciciel_nazwisko3'] ?? null,
            imieNazwisko: $row['wlasciciel_imie_nazwisko'] ?? null,
        );
    }

    public static function labelFromWorkstationRow(object $row): string
    {
        return self::fromWorkstationRow($row)->formatWorkstationListLabel($row->login ?? null);
    }

    public function formatWorkstationListLabel(?string $login = null): string
    {
        return trim(sprintf(
            '%s %s [%s] {%s} (%s)',
            $this->imie ?? '',
            self::joinSurnames((object) [
                'surname' => $this->nazwisko,
                'surname2' => $this->nazwisko2,
                'surname3' => $this->nazwisko3,
            ]),
            $this->stanowiskoNazwa ?? '',
            $this->komorkaSkrot ?? '',
            $login ?? '',
        ));
    }

    public static function labelFromGroup(object $group): string
    {
        return sprintf(
            '%s (%s)',
            $group->departament_description ?? '',
            $group->departament_name ?? '',
        );
    }

    public function displayName(): string
    {
        return sprintf(
            '%s [%s] {%s}',
            $this->imieNazwisko ?? '',
            $this->stanowiskoNazwa ?? '',
            $this->komorkaSkrot ?? '',
        );
    }

    private static function buildImieNazwisko(object $user): ?string
    {
        $imieNazwisko = trim(implode(' ', array_filter(
            [$user->forename ?? null, $user->surname ?? null, $user->surname2 ?? null, $user->surname3 ?? null],
            fn ($v) => $v !== null && $v !== '',
        )));

        return $imieNazwisko !== '' ? $imieNazwisko : null;
    }

    private static function joinSurnames(object $user): string
    {
        $parts = array_filter(
            [$user->surname ?? null, $user->surname2 ?? null, $user->surname3 ?? null],
            fn ($v) => $v !== null && $v !== '',
        );

        return implode('-', $parts);
    }
}
