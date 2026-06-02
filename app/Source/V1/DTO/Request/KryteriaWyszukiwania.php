<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

readonly class KryteriaWyszukiwania
{
    public function __construct(
        public ApiKonfiguracja $konfiguracja,
        public TypFiltrSpraw $filtry,
        public Paginacja $paginacja,
        public Sortowanie $sortowanie,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            konfiguracja: ApiKonfiguracja::fromArray($payload['konfiguracja'] ?? []),
            filtry: TypFiltrSpraw::fromArray(is_array($payload['filtry'] ?? null) ? $payload['filtry'] : []),
            paginacja: Paginacja::fromPayload($payload),
            sortowanie: Sortowanie::fromPayload($payload),
        );
    }
}
