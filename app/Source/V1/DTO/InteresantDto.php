<?php
declare(strict_types=1);

namespace App\Source\V1\DTO;

final readonly class InteresantDto
{
    public function __construct(
        public ?string $nazwa,
        public ?string $adres,
        public ?string $adresEpuap,

        /** @var array<string, mixed> */
        public array $meta = [],
    ) {}
}