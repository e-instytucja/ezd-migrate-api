<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

final readonly class DokumentDanePodstawoweWartosciDto
{
    public function __construct(
        public ?string $nazwaProcesu,
        public ?int $idProcesu,
        public ?string $statusProcesu,
        public ?int $typDokumentu,
        public ?string $znakSprawy,
        public string|int|null $idDokumentu,
        public ?string $nrNaPismie,
        public ?int $wersja,
        public ?string $dataRejestracji,
        public ?string $dataUtworzenia,
        public ?string $dokumentTytul,
        public ?string $trescWniosku,
        public ?string $nrKsiegi,
        public ?int $documentGroupType,
    ) {}
}
