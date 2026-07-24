<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

readonly class KryteriaWyszukiwaniaSpraw
{
    public function __construct(
        public ApiKonfiguracja $konfiguracja,
        public TypFiltrSpraw   $filtry,
        public Paginacja       $paginacja,
        public SortowanieSpraw $sortowanie,
        public int             $dntas = 0,
        public ?AktaSprawyPaginacja $aktaPaginacja = null,
    ) {
    }

    public static function fromPayload(array $payload, int $dntas = 0): self
    {
        return new self(
            konfiguracja: ApiKonfiguracja::fromArray($payload['konfiguracja'] ?? []),
            filtry: TypFiltrSpraw::fromArray(is_array($payload['filtry'] ?? null) ? $payload['filtry'] : []),
            paginacja: Paginacja::fromPayload($payload),
            sortowanie: SortowanieSpraw::fromPayload($payload),
            dntas: self::normalizeDntas($dntas),
            aktaPaginacja: AktaSprawyPaginacja::fromPayload($payload),
        );
    }

    private static function normalizeDntas(int $dntas): int
    {
        return $dntas === 1 ? 1 : 0;
    }
}
