<?php

namespace App\Source\V1\Enum;

/**
 * Class TypStatusZastepstwa
 *
 * @package App\Source\V1\Enum
 */
class TypStatusZastepstwa
{
    const ANULOWANE                  = 'Anulowane';
    const OCZEKUJE_NA_ZATWIERDZENIE  = 'Oczekuje na zatwierdzenie';
    const ODRZUCONE                  = 'Odrzucone';
    const TRWA                       = 'Trwa';
    const ZAKONCZONE                 = 'Zakończone';
    const ZATWIERDZONE_NIEROZPOCZETE = 'Zatwierdzone. Nierozpoczęte';
}
