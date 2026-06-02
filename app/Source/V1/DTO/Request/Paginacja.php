<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

readonly class Paginacja
{
    public function __construct(
        public int $page,
        public int $limit,
        public int $offset,
    ) {
    }

    public static function fromPayload(array $payload, int $defaultLimit = 10, int $maxLimit = 200): self
    {
        $limit = max(1, min($maxLimit, (int) ($payload['limit'] ?? $defaultLimit)));
        $page  = max(1, (int) ($payload['page'] ?? 1));

        return new self(
            page: $page,
            limit: $limit,
            offset: ($page - 1) * $limit,
        );
    }
}
