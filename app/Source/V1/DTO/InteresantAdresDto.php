<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

final readonly class InteresantAdresDto
{
    public function __construct(
        public ?string $ulica = null,
        public ?string $numerDomu = null,
        public ?string $numerLokalu = null,
        public ?string $kodPocztowy = null,
        public ?string $miasto = null,
        public ?string $poczta = null,
        public ?string $kraj = null,
        public ?string $pelny = null,
    ) {}
}
