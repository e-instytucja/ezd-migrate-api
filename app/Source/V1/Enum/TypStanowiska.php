<?php

namespace App\Source\V1\Enum;

/**
 * Class TypStanowiska
 *
 * @package App\Source\V1\Enum
 */
class TypStanowiska
{
    /**
     * Stanowisko kierownika/przełożonego dla pozostałych stanowisk we własnej komórce.
     * Zwykle ma prawo do zatwierdzania dokumentów stanowisk we własnej komórce.
     */
    const LIDER = 'Lider';
    /**
     * Stanowisko sekretariatu w komórce. Zwykle zajmuje się dekretacją pism na stanowiska we własnej  komórce.
     */
    const SEKRETARIAT = 'Sekretariat';
    /**
     * Stanowisko szeregowego pracownika komórki.
     */
    const POZOSTALE = 'Pozostałe';
    /**
     * Stanowisko łączące typ Lider oraz Sekretariat.
     */
    const LIDER_SEKRETARIAT = 'LiderSekretariat';
}
