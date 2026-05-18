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
     * @var string
     */
    public string $id_uzytkownika;
    /**
     * @var string
     */
    public string $imie;
    /**
     * Wszystkie człony nazwiska połączone ze sobą znakiem "-"
     *
     * @var string
     */
    public string $nazwisko;
    /**
     * @var string
     */
    public int $id_stanowiska;
    /**
     * @var string
     */
    public string $nazwa_stanowiska;

    public string $login;

    public string $nazwa_komorki;
    public string $skrot_komorki;
}
