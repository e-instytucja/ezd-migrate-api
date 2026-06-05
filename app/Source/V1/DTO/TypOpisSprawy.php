<?php

namespace App\Source\V1\DTO;

use DateTime;

/**
 * Class TypOpisSprawy
 *
 */
class TypOpisSprawy
{
    /**
     * Znak sprawy w SIDAS EZD
     *
     * @var string
     */
    public $znak = '';
    public $oznaczenie_dntas = '';
    /**
     * Znak sprawy do której przynależy dokument pismo. Podawany tylko, jeśli dokument/pismo zostało dołączone do
     * sprawy lub pismo utworzyło sprawę.
     */
    public TypZnakSprawy $znak_szczegolowy;
    /**
     * Nazwa procesu dokumentu w SIDAS EZD, które zainicjowało sprawę
     *
     * @var string
     */
    public $nazwa_procesu = '';
    /**
     * Identyfikator procesu dokumentu w SIDAS EZD, które zainicjowało sprawę
     *
     * @var string
     */
    public $id_procesu = '';
    /**
     * Status sprawy w SIDAS EZD
     *
     * @var string
     */
    public $status_procesu = '';
    /**
     * Data i czas zarejestrowania/powstania sprawy w SIDAS EZD
     *
     * @var DateTime
     */
    public $rejestracja = '';
    /**
     * Wskazanie właściciela (stanowiska merytorycznego) sprawy, w strukturze TYP_PRACOWNIK.
     */
    public TypPracownik $wlasciciel;
    /**
     * Wskazanie twórcy sprawy (stanowiska rejestrującego sprawę), w strukturze TYP_PRACOWNIK.
     */
    public TypPracownik $utworzyl;
    /**
     * Lista dokumentów/pism zawierających się w sprawie (w tym pismo inicjujące sprawę),
     * w strukturze TYP_POZYCJA_DOKUMENTU zdefiniowanej przy metodzie ”pobierzDokumentyLista”.
     * Kolejność pozycji na liście odpowiada numerom porządkowym dokumentów/pism w sprawie.
     * Pismo inicjujące sprawę zawsze na pierwszej pozycji.
     *
     * @var TypPozycjaDokumentu[]
     */
    public $dokumenty = [];
    /**
     * Wskazanie daty do której sprawa ma zostać zakończona.
     * Jeśli czas na rozpatrzenie sprawy zdefiniowano wskazaniem konkretnej daty, to ten parametr przekazuje tą datę.
     * Jeśli czas na rozpatrzenie sprawy zdefiniowano wskazaniem liczby dni na rozpatrzenie, to ten parametr przekazuje
     * datę jaka wynika z bieżącej pozostałej liczby dni na rozpatrzenie.
     * Jeśli nie wskazano konkretnej daty albo liczby dni na rozpatrzenie (np. wskazano nieograniczony czas na
     * rozpatrzenie), to ten parametr jest pusty.
     *
     * @var DateTime
     */
    public $termin = null;
    /**
     * Opis sprawy
     *
     * @var string
     */
    public $opis;
    /**
     * Tytuł sprawy
     *
     * @var string
     */
    public $tytul;
    /**
     * Lista stron sprawy wskazanych w SIDAS EZD,w strukturze TYP_POZYCJA_INTERESANTA zdefiniowanej w metodzie
     * pobierzDokumentOpis. W parametrze ROLA struktury obsługiwane wartości to: ”Główny”, ”Do wiadomości”.
     *
     */
    public $dane_formularza;

    public $historia_obiegu;
    public array $strony;
    /**
     * Lista stanowisk którym udostępniono sprawę (pracowników pracujących wspólnie nad sprawą wraz z właścicielem),
     * w strukturze TYP_PRACOWNIK zdefiniowanej przy metodzie ”pobierzDokumentOpis”.
     *
     * @var TypPracownik[]
     */
    public array $udostepniona;
    /**
     * Parametr wskazujacy czy przy wczesniejszej probie pobrania wystepowaly problemy
     *
     * @var boolean
     */
    public $blad = false;

    /**
     * TypOpisSprawy constructor.
     */
    public function __construct()
    {
        $this->znak_szczegolowy = new TypZnakSprawy();
        $this->wlasciciel = new TypPracownik();
        $this->utworzyl = new TypPracownik();
    }
}
