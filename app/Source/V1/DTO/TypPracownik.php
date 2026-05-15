<?php

namespace App\Source\V1\DTO;

/**
 * Class TypPracownik
 *
 * @package App\Source\V1\DTO
 */
class TypPracownik
{
    /**
     * Login użytkownika w SIDAS EZD - string
     *
     * @var string
     */
    public $id_uzytkownika = '';
    /**
     * @var string
     */
    public $imie;
    /**
     * Wszystkie człony nazwiska połączone ze sobą znakiem "-"
     *
     * @var string
     */
    public $nazwisko;
    /**
     * @var string
     */
    public $id_stanowiska;
    /**
     * @var string
     */
    public $nazwa_stanowiska;
}
