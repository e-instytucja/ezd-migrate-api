<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Form;

use App\Source\V1\DTO\InteresantDto;
use App\Source\V1\Queries\Structure\UugQuery;
use App\Source\V1\Queries\Structure\WorkstationQuery;
use App\Source\V1\Services\Attachment\AttachmentService;
use App\Source\V1\Services\Dictionary\DictionaryService;
use App\Source\V1\Services\Suppliant\SupliantService;

final class FormDaneService
{
    public function __construct(
        private readonly WorkstationQuery $workstationQuery,
        private readonly UugQuery $uugQuery,
        private readonly AttachmentService $attachmentService,
        private readonly DictionaryService $dictionaryService,
        private readonly SupliantService $suppliantService,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $daneZBazy
     * @param array<string, array<string, mixed>> $strukturaFormularza
     *
     * @return array<string, mixed>
     */
    public function przetworzDane(array $daneZBazy, array $strukturaFormularza): array
    {
        $liczbaInteresantow = $this->policzInteresantow($daneZBazy);
        $wynik = [];

        foreach ($daneZBazy as $wiersz) {
            $this->przetworzWiersz($wiersz, $strukturaFormularza, $liczbaInteresantow, $wynik);
        }

        return $wynik;
    }

    /**
     * @param array<int, array<string, mixed>> $daneZBazy
     */
    private function policzInteresantow(array $daneZBazy): int
    {
        $liczba = 0;

        foreach ($daneZBazy as $wiersz) {
            if (($wiersz['struktura_typ'] ?? null) === 'interesanci') {
                $liczba++;
            }
        }

        return $liczba;
    }

    /**
     * @param array<string, mixed> $wiersz
     * @param array<string, array<string, mixed>> $strukturaFormularza
     * @param array<string, mixed> $wynik
     */
    private function przetworzWiersz(
        array $wiersz,
        array $strukturaFormularza,
        int $liczbaInteresantow,
        array &$wynik,
    ): void {
        if (
            ($wiersz['struktura_pole'] ?? null) !== null
            && isset($strukturaFormularza[$wiersz['struktura_pole']]['struktura_typ'])
        ) {
            $this->przetworzWierszZeStruktura(
                $wiersz,
                $strukturaFormularza[$wiersz['struktura_pole']]['struktura_typ'],
                $liczbaInteresantow,
                $wynik,
            );

            return;
        }

        if (!empty($wiersz['form_wartosc'])) {
            $this->przetworzWierszBezStruktury($wiersz, $wynik);
        }
    }

    /**
     * @param array<string, mixed> $wiersz
     * @param array<string, mixed> $wynik
     */
    private function przetworzWierszZeStruktura(
        array $wiersz,
        string $typPola,
        int $liczbaInteresantow,
        array &$wynik,
    ): void {
        $kluczPola = $wiersz['struktura_pole'];
        $wartosc = $wiersz['form_wartosc'];

        match ($typPola) {
            'dokument_tytul' => $wynik[$kluczPola] = json_decode($wartosc, true)['textarea'] ?? null,
            'interesanci' => $this->dodajInteresanta($wiersz, $liczbaInteresantow, $wynik),
            'attachment' => $wynik[$kluczPola] = $this->attachmentService->getAttachmentsDetails((string) $wartosc),
            'dekretacja_wydzial' => $this->uzupelnijStanowisko($wiersz, $wynik, $this->workstationQuery),
            'slownik' => $wynik[$kluczPola] = $this->dictionaryService->getDictionaryValue((int) $wartosc),
            'stanowisko_uzytkownik' => $this->uzupelnijStanowisko($wiersz, $wynik, $this->uugQuery),
            'referat' => $wynik[$kluczPola] = $this->workstationQuery->getDepartamentInfo((string) $wartosc)['groupName'],
//            insertdate, textinput, streszczenie, textarea, insertdatetime, select1, radio, sposob_dostarczenia_tylko_lista,tresc_wniosku, odwzorowanie, ilosc_dni - Chojnice - typy pól które obsługuje default
            default => $wynik[$kluczPola] = str_replace('&#34;', '"', (string) $wartosc),
        };
    }

    /**
     * @param array<string, mixed> $wiersz
     * @param array<string, mixed> $wynik
     */
    private function przetworzWierszBezStruktury(array $wiersz, array &$wynik): void
    {
        if (
            ($wiersz['form_pole'] ?? null) === 'petent_uid'
            && ($wiersz['struktura_typ'] ?? null) !== 'interesanci'
        ) {
            $wynik['interesanci'][] = $this->pobierzInteresantaDoFormularza(
                $wiersz['form_wartosc'],
                $wiersz['form_dane_id'],
                true,
            );

            return;
        }

        $wynik[$wiersz['struktura_pole']] = $wiersz['form_wartosc'];
    }

    /**
     * @param array<string, mixed> $wiersz
     * @param array<string, mixed> $wynik
     */
    private function dodajInteresanta(array $wiersz, int $liczbaInteresantow, array &$wynik): void
    {
        if ($liczbaInteresantow <= 1) {
            $wynik[$wiersz['struktura_pole']] = null;
        }

        if (!empty($wiersz['form_wartosc'])) {
            $wynik[$wiersz['form_pole']][] = $this->pobierzInteresantaDoFormularza(
                $wiersz['form_wartosc'],
                $wiersz['form_dane_id'],
                false,
            );
        }
    }

    /**
     * @param array<string, mixed> $wiersz
     * @param array<string, mixed> $wynik
     */
    private function uzupelnijStanowisko(
        array $wiersz,
        array &$wynik,
        WorkstationQuery|UugQuery $zapytanie,
    ): void {
        $kluczPola = $wiersz['struktura_pole'];
        $wartosc = (string) $wiersz['form_wartosc'];

        $wynik[$kluczPola] = $wartosc . '[' . $zapytanie->getDepartamentInfo($wartosc)['groupName'] . ']';
    }

    private function pobierzInteresantaDoFormularza(
        mixed $idInteresanta,
        mixed $idDanychFormularza,
        bool $czyGlowny,
    ): InteresantDto {
        $interesantDane = $this->suppliantService->getSupliantById($idInteresanta);
        $interesantRola = $this->suppliantService->getPetentRoleById($idDanychFormularza);
        $interesantTyp = ($interesantDane['typ_osoby'] ?? null) === 'firma'
            ? 'instytucja'
            : 'osoba';

        return new InteresantDto(
            nazwa: $interesantDane['view_podmiot'],
            adres: $interesantDane['adres_metadane']['adres_korespondencyjny'],
            adresEpuap: $interesantDane['front_office_petent_id'],
            meta: [
                'glowny' => $czyGlowny,
                'role' => $interesantRola,
                'interesant_type' => $interesantTyp,
            ],
        );
    }
}
