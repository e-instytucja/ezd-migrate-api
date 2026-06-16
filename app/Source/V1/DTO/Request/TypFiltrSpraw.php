<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

readonly class TypFiltrSpraw extends \App\Source\V1\DTO\Request\TypFiltrDokument
{
    public function __construct(
        public ?string $sprawaUid = null,
        public ?int $rok = null,
        public ?string $znak = null,
        public ?string $oznaczenieDntas = null,
        public ?string $statusProcesu = null,
        public ?int $wlascicielStanowisko = null,
        public ?string $tytulSprawy = null,
        public ?string $interesant = null,
        public ?bool $pokazUdostepnione = null,
        public ?string $dataWszczeciaOd = null,
        public ?string $dataWszczeciaDo = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sprawaUid: self::parseSprawaUid($data),
            rok: isset($data['rok']) ? (int) $data['rok'] : null,
            znak: self::nullableString($data['znak'] ?? null),
            oznaczenieDntas: self::nullableString($data['oznaczenie_dntas'] ?? null),
            statusProcesu: self::nullableString($data['status_procesu'] ?? null),
            wlascicielStanowisko: isset($data['wlasciciel_stanowisko']) ? (int) $data['wlasciciel_stanowisko'] : null,
            tytulSprawy: self::nullableString($data['tytu_sprawy'] ?? null),
            interesant: self::nullableString($data['interesant'] ?? null),
            pokazUdostepnione: self::nullableBool($data['pokaz_udostepnione'] ?? null),
            dataWszczeciaOd: self::nullableString($data['data_wszczecia_od'] ?? null),
            dataWszczeciaDo: self::nullableString($data['data_wszczecia_do'] ?? null),
        );
    }

    public function requiresInteresantJoin(): bool
    {
        return $this->interesant !== null;
    }

    private static function parseSprawaUid(array $data): ?string
    {
        $value = $data['sprawa_uid'] ?? $data['sprawaUid'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || !preg_match('/^[a-f0-9]{13}$/', $value)) {
            return null;
        }

        return $value;
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
