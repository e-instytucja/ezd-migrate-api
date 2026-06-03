<?php

namespace App\Source\V1\DTO;

use DateTime;
use App\Source\V1\Enum\RodzajPrzesylki;

/**
 * Class TypPozycjaDokumentu
 *
 * @package Docflow\ESBService\Proxy\Type
 */
class TypPozycjaDokumentu
{
    /**
     * Identyfikator dokumentu w SIDAS EZD (instancji procesu)
     *
     * @var string
     */
    public $id_dokumentu = '';
    /**
     * Nazwa procesu
     *
     * @var string
     */
    public $nazwa_procesu = '';
    /**
     * Identyfikator procesu w SIDAS EZD
     *
     * @var string
     */
    public $id_procesu = '';
    /**
     * Status dokumentu w SIDAS EZD
     *
     * @var string
     */
    public $status_procesu = '';
    /**
     * Data i czas wszczęcia postępowania lub wprowadzenia do systemu.
     * To data powstania/utworzenia tej wersji dokumentu, która jest listowana.
     * W przypadku dokumentu zarejestrowanego w CRPP to będzie data wpływu (czyli wszczęcia postępowania).
     * W przypadku sprawy wszczętej ze stanowiska (bez wpisu w CRPP) to będzie data powstania/utworzenia.
     * W przypadku dokumentów wystawianych w sprawie to będzie systemowa data powstania/utworzenia wersji.
     *
     * @var DateTime
     */
    public $data_i_czas = '';
    /**
     * @var integer
     */
    public $wersja;
    /**
     * Rodzaj przesyłki
     *
     * @type RodzajPrzesylki
     * @var string
     */
    public string $przesylka = '';
    /**
     * Wskazanie właściciela (stanowiska merytorycznego) dokumentu, w strukturze
     *
     */
    public TypPracownik $wlasciciel;
    /**
     * Parametr wskazujacy czy przy wczesniejszej probie pobrania wystepowaly problemy
     *
     * @var boolean
     */
    public $blad = false;
}
