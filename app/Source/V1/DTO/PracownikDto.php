<?php
declare(strict_types=1);

namespace App\Source\V1\DTO;

final readonly class PracownikDto
{
    public function __construct(
        public ?int $id,
        public ?string $skrot,
        public ?string $nazwa,

        public ?string $komorkaSkrot,
        public ?string $komorkaNazwa,

        public ?string $imie,
        public ?string $nazwisko,
        public ?string $nazwisko2,
        public ?string $nazwisko3,

        public ?string $imieNazwisko,
    ) {}
}