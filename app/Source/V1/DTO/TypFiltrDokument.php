<?php

namespace App\Source\V1\DTO;

use DateTime;

/**
 * Class TypFiltrDokument
 *
 */
class TypFiltrDokument
{
    /**
     * Identyfikator procesu dokumentu/pisma w SIDAS EZD.
     *
     * @var string
     */
    public $id_procesu;
    /**
     * Status dokumentu/pisma w SIDAS EZD.
     *
     * @var string
     */
    public $status_procesu;
    /**
     * Dolna granica przedziału (domkniętego) dat wszczęcia postępowania lub wprowadzenia do systemu dokumentu/pisma  w
     * SIDAS EZD - data najstarszego dokumentu.
     *
     * @var DateTime
     */
    public $data_od;
    /**
     * Górna granica przedziału (domkniętego) dat wszczęcia postępowania lub wprowadzenia do systemu dokumentu/pisma  w
     * SIDAS EZD - data najmłodszego dokumentu.
     *
     * @var DateTime
     */
    public $data_do;
    /**
     * przychodzaca – pisma przychodzące
     * wychodzaca – dokumenty wychodzące
     * wewnetrzny – dokumenty/pisma wewnętrzne
     * zwrotkazwrot – zwrotne potwierdzeni odbioru lub zwrot wysłanej przesyłki
     *
     * @var string
     */
    public $przesylka;
    /**
     * Wskazanie właściciela (stanowiska merytorycznego) dokumentu/pisma, w strukturze TYP_PRACOWNIK.
     *
     */
    public $wlasciciel;
    /**
     * Wystąpienie błędu przy wywołaniu metody pobierzSpraweOpis lub zgłoszenie metodą pobierzSprawaBlad wobec
     * filtrowanych spraw. false – zwrócona lista dokumentów/pism będzie ogranicza do tych gdzie brak błędu true –
     * zwrócona lista dokumentów/pism będzie ogranicza do tych gdzie błąd wystąpił
     *
     * @var bool
     */
    public $blad;
}
