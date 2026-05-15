<?php

namespace App\Source\V1\Enum;

/**
 * Rodzaj przesyłki:
 * - przychodzaca – dla dokumentów przychodzących
 * - wychodzaca – dla dokumentów wychodzących
 * - wewnetrzny – dla dokumentów wewnętrznych
 */

/**
 * Class RodzajPrzesylki
 *
 * @package App\Source\V1\Enum
 */
class RodzajPrzesylki
{
    const PRZYCHODZACA = 'przychodzaca';
    const WYCHODZACA   = 'wychodzaca';
    const WEWNETRZNY   = 'wewnetrzny';
    const ZWROTKA      = 'zwrotkazwrot';
}
