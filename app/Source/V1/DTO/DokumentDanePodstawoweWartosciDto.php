<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use App\Source\V1\Enum\TypDokument;
use App\Source\V1\Enum\TypFormularza;
use App\Source\V1\Enum\TypPowiazaniaDokumentu;
use JsonSerializable;

final readonly class DokumentDanePodstawoweWartosciDto implements JsonSerializable
{
    public function __construct(
        public ?string $nazwaProcesu,
        public ?int $idProcesu,
        public ?string $statusProcesu,
        public ?TypDokument $typDokumentu,
        public ?TypFormularza $typFormularza,
        public ?TypPowiazaniaDokumentu $typPowiazaniaDokumentu,
        public ?string $znakSprawy,
        public string|int|null $idDokumentu,
        public ?string $nrNaPismie,
        public ?int $wersja,
        public ?string $dataRejestracji,
        public ?string $dataUtworzenia,
        public ?string $dokumentTytul,
        public ?string $trescWniosku,
        public ?string $nrKsiegi,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'nazwaProcesu' => $this->nazwaProcesu,
            'idProcesu' => $this->idProcesu,
            'statusProcesu' => $this->statusProcesu,
            'typDokumentu' => $this->typDokumentu?->toApi(),
            'typFormularza' => $this->typFormularza?->toApi(),
            'typPowiazaniaDokumentu' => $this->typPowiazaniaDokumentu?->toApi(),
            'znakSprawy' => $this->znakSprawy,
            'idDokumentu' => $this->idDokumentu,
            'nrNaPismie' => $this->nrNaPismie,
            'wersja' => $this->wersja,
            'dataRejestracji' => $this->dataRejestracji,
            'dataUtworzenia' => $this->dataUtworzenia,
            'dokumentTytul' => $this->dokumentTytul,
            'trescWniosku' => $this->trescWniosku,
            'nrKsiegi' => $this->nrKsiegi,
        ];
    }
}
