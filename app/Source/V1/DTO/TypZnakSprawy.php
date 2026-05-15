<?php

namespace App\Source\V1\DTO;

/**
 * Class TypZnakSprawy
 *
 * @package App\Source\V1\DTO;
 */
class TypZnakSprawy
{
    /**
     * Symbol komórki, format: tylko wielkie litery i myślnik (minus) otoczony literami.
     * Jeśli brak - symbol komórki jest pobierany na podstawie stanowiska ze struktury WLASCICIEL.
     *
     * @var string
     */
    public $komorka_symbol;
    /**
     * Symbol JRWA.
     * format: tylko cyfry.
     *
     * @var string
     */
    public $jrwa_symbol = '';
    /**
     * Numer zbioru podrzędnego (podteczki).
     * Jeśli brak wskazania - sprawa trafi do zbioru głównego, a nie podrzędnego (podteczki).
     * Jeśli jest wskazanie, a w SIDAS EZD brak zbioru podrzędnego (podteczki) o wskazanym numerze –
     * nastąpi sprawdzenie czy istnieje możliwość wydzielenia zbioru podrzędnego (podteczki).
     * Jeśli tak – Zbiór podrzędny zostaje wydzielony a sprawa utworzona. Jeśli nie – SIDAS EZD zwraca komunikat błędu.
     *
     * @var integer
     */
    public $zbior_nr;
    /**
     * Opis zbioru podrzędnego (podteczki).
     * format: dowolny ciąg znaków.
     *
     * @var string
     */
    public $zbior_opis;
    /**
     * Numer sprawy w zbiorze podstawowym albo podrzędnym.
     * Jeśli brak - sprawa otrzyma kolejny wolny numer w zbiorze w którym ma być tworzona.
     *
     * @var integer
     */
    public $numer;
    /**
     * Rocznik w którym sprawa się rozpoczęła.
     * Jeśli brak – sprawa zostanie utworzona w roczniku DATA _WPLYWU, a jeśli jej brak to w roczniku DATA_UTWORZENIA.
     *
     * @var string
     */
    public $rocznik;
}
