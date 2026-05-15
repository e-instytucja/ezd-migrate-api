<?php

namespace App\Source\V1\Enum;

/**
 * Class StatusStanowiska
 *
 * @package App\Source\V1\Enum
 */
class StatusStanowiska
{
    /**
     * Stanowisko jest aktywne w SIDAS EZD.
     * Użytkownik może pracować w jego kontekście.
     */
    const AKTYWNY = 'Aktywny';
    /**
     * Stanowisko jest nieaktywne w SIDAS EZD.
     * Użytkownik nie może pracować w jego kontekście.
     */
    const NIEAKTYWNY = 'Nieaktywny';
    /**
     * Stanowisko zostało skasowane w SIDAS EZD (informacja przechowywana w celach historycznych).
     * Użytkownik nie może pracować w jego kontekście.
     */
    const SKASOWANY = 'Skasowany';
}
