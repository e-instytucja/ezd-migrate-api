<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

readonly class KryteriaWyszukiwaniaDokumentow
{
    public function __construct(
        public ApiKonfiguracja $konfiguracja,
        public TypFiltrDokument $filtry,
        public Paginacja $paginacja,
        public Sortowanie $sortowanie,
        public int $dntas = 0,
    ) {
    }

    public static function fromPayload(array $payload, int $dntas = 0): self
    {
        return new self(
            konfiguracja: ApiKonfiguracja::fromArray($payload['konfiguracja'] ?? []),
            filtry: TypFiltrDokument::fromArray(is_array($payload['filtry'] ?? null) ? $payload['filtry'] : []),
            paginacja: Paginacja::fromPayload($payload),
            sortowanie: Sortowanie::fromPayload($payload),
            dntas: self::normalizeDntas($dntas),
        );
    }

    public static function forTeczkaUid(string $teczkaUid, int $dntas = 0): self
    {
        return new self(
            konfiguracja: new ApiKonfiguracja(),
            filtry: TypFiltrDokument::forTeczkaUid($teczkaUid),
            paginacja: new Paginacja(page: 1, limit: 10000, offset: 0),
            sortowanie: new Sortowanie('data_wszczecia', 'desc'),
            dntas: self::normalizeDntas($dntas),
        );
    }

    private static function normalizeDntas(int $dntas): int
    {
        return $dntas === 1 ? 1 : 0;
    }
}
