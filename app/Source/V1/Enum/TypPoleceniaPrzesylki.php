<?php

namespace App\Source\V1\Enum;

/**
 * Class TypPoleceniaPrzesylki
 *
 * @package App\Source\V1\Enum
 */
class TypPoleceniaPrzesylki
{
    /**
     * Powiązanie wskazanego parametrem ID_DOKUMENTU dokumentu z utworzonym jednocześnie wpisem w CRPW (przesyłką),
     * skierowaną do interesanta wskazanego parametrem ADRESAT.
     * W tym wypadku podanie parametrów ID_DOKUMENTU oraz ADRESAT jest obowiązkowe,
     * a część parametrów ze struktury parametru PRZESYLKA jest ignorowanych
     * i zastępowanych danymi z SIDAS EZD opisującymi wskazany dokument.
     * Oznacza skierowanie do wysyłki dokumentu egzystującego w SIDAS EZD do istniejącego w SIDAS EZD adresata
     * i utworzenie odpowiedniej przesyłki w CRPW.
     */
    const WYSLIJ_DOKUMENT = 'wyslij dokument';
    /**
     * Utworzenie wpisu w CRPW (przesyłki) na podstawie wszystkich wartości w strukturze parametru PRZESYLKA.
     */
    const WYSLIJ_PRZESYLKE = 'wyslij przesylke';
}
