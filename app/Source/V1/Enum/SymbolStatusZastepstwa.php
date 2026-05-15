<?php

namespace App\Source\V1\Enum;

/**
 * Class SymbolStatusZastepstwa
 *
 * @package App\Source\V1\Enum
 */
class SymbolStatusZastepstwa
{
    const ANULOWANE                  = 'A';
    const OCZEKUJE_NA_ZATWIERDZENIE  = 'D';
    const ODRZUCONE                  = 'O';
    const TRWA                       = 'T';
    const ZAKONCZONE                 = 'K';
    const ZATWIERDZONE_NIEROZPOCZETE = 'N';
}
