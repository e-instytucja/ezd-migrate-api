<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

use App\Source\V1\Enum\TypDokument;
use App\Source\V1\Enum\TypFormularza;

readonly class TypFiltrDokument
{
    public function __construct(
        public ?string $documentId = null,
        public ?string $teczkaUid = null,
        public ?int    $rok = null,
        public ?TypDokument $typProcesu = null,
        public ?string $nazwaProcesu = null,
        public ?string $statusProcesu = null,
        public ?string $dataRejestracjiOd = null,
        public ?string $dataRejestracjiDo = null,
        public ?int    $wlascicielStanowisko = null,
        public ?bool   $pokazUdostepnione = null,
        public ?string $opisDokumentu = null,
        public ?string $trescPisma = null,
        public ?string $oznaczenie = null,
        public ?string $interesant = null,
        public ?TypFormularza $typFormularza = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            documentId: self::nullableString($data['documentId'] ?? null),
            teczkaUid: self::nullableString($data['teczka_uid'] ?? null),
            rok: isset($data['rok']) ? (int) $data['rok'] : null,
            typProcesu: self::parseTypProcesu($data['typ_procesu'] ?? null),
            nazwaProcesu: self::nullableString($data['nazwa_procesu'] ?? null),
            statusProcesu: self::nullableString($data['status_procesu'] ?? null),
            dataRejestracjiOd: self::nullableString($data['data_rejestracji_od'] ?? null),
            dataRejestracjiDo: self::nullableString($data['data_rejestracji_do'] ?? null),
            wlascicielStanowisko: isset($data['wlasciciel_stanowisko']) ? (int) $data['wlasciciel_stanowisko'] : null,
            pokazUdostepnione: self::nullableBool($data['pokaz_udostepnione'] ?? null),
            opisDokumentu: self::nullableString($data['opis_dokumentu'] ?? $data['dokument_tytul'] ?? null),
            trescPisma: self::nullableString($data['tresc_pisma'] ?? null),
            oznaczenie: self::coerceString($data['oznaczenie'] ?? $data['nr_na_pismie'] ?? null),
            interesant: self::nullableString($data['interesant'] ?? null),
            typFormularza: TypFormularza::tryFromFiltra($data['typ_formularza'] ?? null),
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

    public function requiresInteresantJoin(): bool
    {
        return $this->interesant !== null;
    }

    private static function parseTypProcesu(mixed $value): ?TypDokument
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException(
                'Nieprawidłowy filtr filtry.typ_procesu — oczekiwany string.',
            );
        }

        $typDokumentu = TypDokument::tryFrom($value);

        if ($typDokumentu === null) {
            $dozwolone = implode(', ', array_keys(TypDokument::mapaPoWartosci()));
            throw new \InvalidArgumentException(
                "Nieprawidłowy filtr filtry.typ_procesu: \"{$value}\". Dozwolone: {$dozwolone}.",
            );
        }

        return $typDokumentu;
    }

    private static function coerceString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return self::nullableString($value);
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
