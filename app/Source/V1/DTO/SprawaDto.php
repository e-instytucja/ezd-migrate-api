<?php

namespace App\Source\V1\DTO;

/**
 * Class SprawaDto
 *
 */
class SprawaDto
{
    public string $znakSprawy = '';
    public ?string $terminRealizacji = null;
    public znakSprawyDto $znakSprawySzczegoly;
    public string $nazwaProcesu = '';
    public string $idProcesu = '';
    public string $statusPismaWiodacego = '';
    public string $dataRejestracji = '';
    public string $dataUtworzenia = '';
    public ?PracownikDto $wlasciciel = null;
    public ?PracownikDto $utworzyl = null;
    public $aktaSprawy = [];
    public string $opisSprawy;
    public string $tytulSprawy;
    public $daneFormularza;

    public $historiaObiegu;
    public array $stronySprawy;

    /**
     * SprawaDto constructor.
     */
    public function __construct()
    {
        $this->znakSprawySzczegoly = new znakSprawyDto();
    }
}
