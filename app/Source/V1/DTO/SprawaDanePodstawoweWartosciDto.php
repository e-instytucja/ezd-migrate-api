<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use App\Source\V1\Enum\TypFormularza;
use JsonSerializable;

final readonly class SprawaDanePodstawoweWartosciDto implements JsonSerializable
{
    public function __construct(
        public ?string $idSprawy = null,
        public ?string $nazwaProcesu = null,
        public ?int $idProcesu = null,
        public ?TypFormularza $typFormularza = null,
        public ?string $statusPismaWiodacego = null,
        public ?string $dataRejestracji = null,
        public ?string $dataUtworzenia = null,
        public ?string $dataWszczecia = null,
        public string $terminRealizacji = '',
        public ?string $tytulSprawy = null,
        public ?string $opisSprawy = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'idSprawy' => $this->idSprawy,
            'nazwaProcesu' => $this->nazwaProcesu,
            'idProcesu' => $this->idProcesu,
            'typFormularza' => $this->typFormularza?->toApi(),
            'statusPismaWiodacego' => $this->statusPismaWiodacego,
            'dataRejestracji' => $this->dataRejestracji,
            'dataUtworzenia' => $this->dataUtworzenia,
            'dataWszczecia' => $this->dataWszczecia,
            'terminRealizacji' => $this->terminRealizacji,
            'tytulSprawy' => $this->tytulSprawy,
            'opisSprawy' => $this->opisSprawy,
        ];
    }
}
