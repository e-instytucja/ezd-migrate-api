<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

final readonly class InteresantKontaktDto
{
    public function __construct(
        public ?string $adresEpuap = null,
        public ?string $telefon = null,
        public ?string $kontakt = null,
        public ?string $adresWww = null,
        public ?string $odbiorElektroniczny = null,
    ) {}
}
