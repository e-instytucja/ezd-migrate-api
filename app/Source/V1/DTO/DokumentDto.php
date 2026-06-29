<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

final readonly class DokumentDto
{
    public function __construct(
        public DokumentDanePodstawoweDto $danePodstawowe,

        public PracownikDto $wlasciciel,

        public PracownikDto $utworzyl,

        public InteresanciDto $interesanci,

        /** @var ZalacznikDto[] */
        public array $zalaczniki,

        /** @var HistoriaObieguDto[] */
        public ?array $historiaObiegu,

        public ?DaneFormularzaDto $daneFormularza,

        /** @var RejestrPrzypisaniaDto */
        public RejestrPrzypisaniaDto $rejestry,

        /** @var RejestrRpwPrzypisaniaDto */
        public RejestrRpwPrzypisaniaDto $wysylki,
    ) {}
}
