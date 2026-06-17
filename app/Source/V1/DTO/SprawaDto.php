<?php

namespace App\Source\V1\DTO;

/**
 * Class SprawaDto
 *
 */
class SprawaDto
{
    public SprawaZnakDto $znakSprawy;

    public SprawaDanePodstawoweDto $danePodstawowe;

    public ?PracownikDto $wlasciciel = null;

    public ?PracownikDto $utworzyl = null;

    public $aktaSprawy = [];

    public DaneFormularzaDto $daneFormularza;

    public InteresanciDto $interesanci;

    /** @var ZalacznikDto[] */
    public array $zalaczniki = [];

    /** @var HistoriaObieguDto[] */
    public array $historiaObiegu = [];

    public array $stronySprawy;

    public function __construct()
    {
        $this->znakSprawy = SprawaZnakDto::empty();
        $this->danePodstawowe = SprawaDanePodstawoweDto::empty();
        $this->daneFormularza = new DaneFormularzaDto();
        $this->interesanci = InteresanciDto::empty();
    }
}
