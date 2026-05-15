<?php

namespace App\Source\V1\Enum;

/**
 * Class StatusUzytkownika
 *
 * @package App\Source\V1\Enum
 */
class StatusUzytkownika
{
    /**
     * Użytkownik jest aktywny w SIDAS EZD, pracuje.
     */
    const AKTYWNY = 'Aktywny';
    /**
     * Użytkownik został zablokowany w SIDAS EZD,
     * nie może się zalogować i pracować
     * (np. przy zastępstwach czasowych będąc zastępowanym,
     * po zablokowaniu przez administratora, po upływie daty ważności konta).
     */
    const ZABLOKOWANY = 'Zablokowany';
    /**
     * Użytkownik został skasowany w SIDAS EZD,
     * nie może się zalogować i pracować (informacja przechowywana w celach historycznych).
     */
    const SKASOWANY = 'Skasowany';
}
