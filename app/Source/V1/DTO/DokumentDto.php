<?php
declare(strict_types=1);

namespace App\Source\V1\DTO;
final readonly class DokumentDto
{
    public function __construct(
        public ?string         $nazwaProcesu,
        public ?int            $idProcesu,
        public ?string         $statusProcesu,

        public ?int            $typ,
        public ?string         $znakSprawy,

        public string|int|null $idDokumentu,
        public ?string         $nrNaPismie,
        public ?int            $wersja,

        public ?string      $dataRejestracji,
        public ?string      $dataUtworzenia,

        public ?string      $dokumentTytul,
        public ?string      $trescWniosku,
        public ?string      $nrKsiegi,

        public ?int         $documentGroupType,

        public PracownikDto $wlasciciel,

        /** @var InteresantDto[] */
        public array        $interesanci,

        /** @var zalacznikDto[] */
        public array        $zalaczniki,

        /** @var historiaObieguDto[] */
        public ?array      $historiaObiegu,

        /** @var array<> */
        public ?array      $daneFormularza
    )
    {
    }
}