<?php

namespace App\Source\V1\Enum;

/**
 * Class TypPoleceniaDokumentu
 *
 * @package App\Source\V1\Enum
 */
class TypPoleceniaDokumentu
{
    /**
     * Dodaj pismo – Tworzy instancję wskazanego procesu pisma,
     * jeśli żądano nadania nr z CRPP dodatkowo rejestruje pismo.
     * Status to ”Do przyjęcia”
     */
    const DODAJ_PISMO = 'Dodaj pismo';
    /**
     * Dodaj pismo i nową sprawę – Jak wyżej.
     * Dodatkowo na podstawie pisma tworzy instancję wskazanego procesu pisma i na jej podstawie inicjuje sprawę.
     * Jeśli w ZNAK_SPRAWY_NOWY nie wskazano NUMER, znak jest generowany jako kolejny możliwy,
     * a jeśli wskazano, to następuje próba założenia sprawy o wskazanym znaku (zwraca błąd przy niepowodzeniu).
     * Status to ”Rozpatrywane”
     */
    const DODAJ_PISMO_I_NOWA_SPRAWE = 'Dodaj pismo i nowa sprawe';
    /**
     * Dodaj pismo do sprawy – Tworzy instancję wskazanego procesu pisma
     * i próbuje dołączyć ją do sprawy o wskazanym znaku (zwraca błąd przy niepowodzeniu).
     * Status to ”Dołączone”
     */
    const DODAJ_PISMO_DO_SPRAWY = 'Dodaj pismo do sprawy';
    /**
     * Dodaj dokument do sprawy – Tworzy instancję wskazanego procesu dokumentu
     * i próbuje dołączyć ją do sprawy o wskazanym znaku (zwraca błąd przy niepowodzeniu)
     */
    const DODAJ_DOKUMENT_DO_SPRAWY = 'Dodaj dokument do sprawy';
    /**
     * Dodaj dokument i nowa sprawe – Tworzy instancję wskazanego w parametrze ID_PROCESU_SPRAWY procesu pisma
     * i na jej podstawie inicjuje sprawę. Jeśli w ZNAK_SPRAWY_NOWY nie wskazano NUMER,
     * znak jest generowany jako kolejny możliwy, a jeśli wskazano, to następuje próba
     * założenia sprawy o wskazanym znaku. Status sprawy to Rozpatrywane.
     * Tworzy instancję wskazanego procesu dokumentu i próbuje dołączyć ją do utworzonej sprawy.
     * Status dokumentu to Zatwierdzony. Zwraca błąd przy niepowodzeniu.
     */
    const DODAJ_DOKUMENT_I_NOWA_SPRAWE = 'Dodaj dokument i nowa sprawe';
}
