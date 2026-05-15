<?php

namespace App\Source\V1\Enum;

/**
 * Class StatusPrzesylki
 *
 * @package App\Source\V1\Enum
 */
class StatusPrzesylki
{
    const NIE_WYSLANO                   = 'nie wyslano';
    const WYSLANO                       = 'wyslano';
    const ODEBRANO                      = 'odebrano';
    const ZWROCONO                      = 'zwrocono';
    const OCZEKUJE_NA_PRZEKAZANIE_DO_EN = 'oczekuje na przekazanie do EN';
    const PRZEKAZANO_DO_BUFORA_EN       = 'przekazano do bufora EN';
    //statusy z tabeli eurzad_slownik_status
    const NIE_WYSLANO_SYMBOL                   = 'PWAN';
    const WYSLANO_SYMBOL                       = 'PWAW';
    const ODEBRANO_SYMBOL                      = 'PWAO';
    const ZWROCONO_SYMBOL                      = 'PWAZ';
    const OCZEKUJE_NA_PRZEKAZANIE_DO_EN_SYMBOL = 'PWAN';
    const PRZEKAZANO_DO_BUFORA_EN_SYMBOL       = 'PWAN';
}
