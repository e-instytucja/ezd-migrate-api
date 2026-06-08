<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

readonly class TypFiltrDokument
{
    public function __construct(
        public ?string $teczkaUid = null,
        public ?string $idProcesu = null,
        public ?string $statusProcesu = null,
        public ?string $dataOd = null,
        public ?string $dataDo = null,
        public ?string $przesylka = null,
        public ?int $wlascicielStanowisko = null,
        public ?bool $pokazUdostepnione = null,
        public ?string $opisDokumentu = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            teczkaUid: self::nullableString($data['teczka_uid'] ?? null),
            idProcesu: self::nullableString($data['id_procesu'] ?? null),
            statusProcesu: self::nullableString($data['status_procesu'] ?? null),
            dataOd: self::nullableString($data['data_od'] ?? null),
            dataDo: self::nullableString($data['data_do'] ?? null),
            przesylka: self::nullableString($data['przesylka'] ?? null),
            wlascicielStanowisko: isset($data['wlasciciel_stanowisko']) ? (int) $data['wlasciciel_stanowisko'] : null,
            pokazUdostepnione: self::nullableBool($data['pokaz_udostepnione'] ?? null),
            opisDokumentu: self::nullableString($data['opis_dokumentu'] ?? null),
        );
    }

    public static function forTeczkaUid(string $teczkaUid): self
    {
        return new self(teczkaUid: $teczkaUid);
    }

    public function isScopedToTeczka(): bool
    {
        return $this->teczkaUid !== null;
    }

    public function requiresOpisJoin(): bool
    {
        return $this->opisDokumentu !== null;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (bool) (int) $value;
    }
}
