<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use JsonSerializable;

final readonly class RejestrRpwWysylkaDto implements JsonSerializable
{
    public function __construct(
        public ?string $dataWyslania,
        public ?string $nrNadawczy,
        public ?RejestrRpwFormaDoreczeniaDto $formaDoreczenia,
        public ?RejestrRpwPrzesylkaElektronicznaDto $przesylkaElektroniczna,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'data_wyslania' => $this->dataWyslania,
            'nr_nadawczy' => $this->nrNadawczy,
            'forma_doreczenia' => $this->formaDoreczenia,
            'przesylka_elektroniczna' => $this->przesylkaElektroniczna,
        ];
    }
}
