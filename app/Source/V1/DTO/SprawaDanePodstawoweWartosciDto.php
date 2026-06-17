<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

final readonly class SprawaDanePodstawoweWartosciDto
{
    public function __construct(
        public ?string $nazwaProcesu = null,
        public ?int $idProcesu = null,
        public ?string $statusPismaWiodacego = null,
        public ?string $dataRejestracji = null,
        public ?string $dataUtworzenia = null,
        public ?string $terminRealizacji = null,
        public ?string $tytulSprawy = null,
        public ?string $opisSprawy = null,
    ) {}
}
