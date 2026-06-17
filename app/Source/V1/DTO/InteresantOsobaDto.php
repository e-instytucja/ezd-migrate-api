<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

final readonly class InteresantOsobaDto
{
    public function __construct(
        public string $typ,
        public ?string $nazwa,
        public ?string $imie = null,
        public ?string $nazwisko = null,
        public ?string $pesel = null,
    ) {}
}
