<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

final readonly class InteresantKontekstDto
{
    public function __construct(
        public string $uid,
        public bool $glowny,
        /** @var string[] */
        public array $role,
    ) {}
}
