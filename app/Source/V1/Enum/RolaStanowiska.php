<?php

namespace App\Source\V1\Enum;

/**
 * Class RolaStanowiska
 *
 * @package App\Source\V1\Enum
 */
class RolaStanowiska
{
    /**
     * Użytkownik piastuje to stanowisko na co dzień,
     * w ramach swoich zwykłych obowiązków służbowych.
     * To jest domyślne stanowisko użytkownika.
     */
    const PIASTUN_DOMYSLNE = 'Piastun domyślne';
    /**
     * Użytkownik piastuje to stanowisko na co dzień, w ramach swoich zwykłych obowiązków służbowych.
     */
    const PIASTUN = 'Piastun';
    /**
     * Użytkownik może pracować na tym stanowisku w ramach zastępstwa stałego.
     */
    const ZASTEPSTWO_STALE = 'Zastępstwo stałe';
    /**
     * Użytkownik może pracować na tym stanowisku tymczasowo w ramach zastępstwa czasowego.
     */
    const ZASTEPSTWO_CZASOWE = 'Zastępstwo czasowe';
    /**
     * Wskazanie użytkownika będącego domyślnym zastępującym dla danego stanowiska.
     */
    const DOMYSLNE_ZASTEPSTWO = 'Domyślne zastępstwo';
}
