<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

final readonly class InteresantInstytucjaDto
{
    public function __construct(
        public string $typ,
        public ?string $nazwa,
        public ?string $pesel = null,
        public ?string $instytucja = null,
        public ?string $nip = null,
        public ?string $regon = null,
        public ?string $krs = null,
    ) {}
}
