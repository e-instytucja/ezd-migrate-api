<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

readonly class AktaSprawyPaginacja
{
    public function __construct(
        public int $page,
        public int $limit,
        public int $offset,
    ) {
    }

    public static function fromPayload(array $payload): ?self
    {
        if (!isset($payload['aktaSprawy']) || !is_array($payload['aktaSprawy'])) {
            return null;
        }

        $akta = $payload['aktaSprawy'];
        $limit = max(10, min(100, (int) ($akta['limit'] ?? 20)));
        $page = max(1, (int) ($akta['page'] ?? 1));

        return new self(
            page: $page,
            limit: $limit,
            offset: ($page - 1) * $limit,
        );
    }
}
