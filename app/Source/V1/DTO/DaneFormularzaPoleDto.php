<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

final readonly class DaneFormularzaPoleDto
{
    public function __construct(
        public string $label,
        public mixed $value,
    ) {}
}
