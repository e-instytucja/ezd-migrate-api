<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Form;

use App\Shared\Structure;
use App\Source\V1\DTO\InteresantDto;
use App\Source\V1\Queries\Structure\GroupQuery;
use App\Source\V1\Queries\Structure\WorkstationQuery;
use App\Source\V1\Services\Attachment\AttachmentService;
use App\Source\V1\Services\Dictionary\DictionaryService;
use App\Source\V1\Services\Suppliant\SupliantService;

final class FormDaneService
{

    public function __construct(
        private readonly GroupQuery $groupQuery,
        private readonly WorkstationQuery $workstationQuery,
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
    public function przetworzDane(array $daneZBazy): array
    {

        $wynik = [];

        foreach ($daneZBazy as $wiersz) {
            if($wiersz['struktura_typ'] !== null) {
                $this->przetworzWierszZeStruktura($wiersz, $wynik);
            }
            else {
                $this->przetworzWierszBezStruktury($wiersz, $wynik);
            }
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
            $typ = $wiersz['struktura_typ'] ?? null;

            if ($typ === 'interesanci' || $typ === 'petent_uid') {
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
        array &$wynik,
    ): void {


    }

    /**
     * @param array<string, mixed> $wiersz
     * @param array<string, mixed> $wynik
     */
    private function przetworzWierszZeStruktura(
        array $wiersz,
        array &$wynik,
    ): void {
        $kluczPola = $wiersz['struktura_pole'];
        $wartoscPola = $wiersz['form_wartosc'];
        $typPola = $wiersz['struktura_typ'];
        $opisPola = $wiersz['struktura_opis'];

        if(!empty($wartoscPola)) {

            switch ($typPola) {
                case 'dokument_tytul':
                    $wartoscPola = json_decode($wartoscPola, true)['textarea'] ?? null;
                    break;

                case 'interesanci':
                    $wartoscPola = $this->pobierzInteresantaDoFormularza(
                        $wartoscPola,
                        $wiersz['form_dane_id'],
                        false,
                    );
                    break;


                case 'attachment':
                    $wartoscPola = $this->attachmentService->getAttachmentsDetails(
                        (string) $wartoscPola
                    );
                    break;

                // identyfikatory oddzielone "#" (np. "123#99#46")
                // są to identyfikatory stanowisk lub komórek
                case 'dekretacja_wydzial':
                    $wartoscPola = $this->getDekretacjaWydzial($wartoscPola);
                    break;

                case 'slownik':
                    $wartoscPola = $this->dictionaryService->getDictionaryValue(
                        (int) $wartoscPola
                    );
                    break;

                case 'referat':
                    $departament = $this->workstationQuery->getDepartamentInfo(
                        (string) $wartoscPola
                    );

                    $wartoscPola = $departament['groupName'] ?? null;
                    break;

                // insertdate, textinput, streszczenie, textarea, insertdatetime,
                // select1, radio, sposob_dostarczenia_tylko_lista,
                // tresc_wniosku, odwzorowanie, ilosc_dni
                default:
                    $wartoscPola = str_replace('&#34;', '"', (string) $wartoscPola);
                    break;
            }
        }

        if ($kluczPola === 'interesanci') {
            if (!isset($wynik['interesanci'])) {
                $wynik['interesanci'] = [
                    'label' => $opisPola,
                    'value' => [],
                ];
            }

            $wynik['interesanci']['value'][] = $wartoscPola;

            return;
        }

        $wynik[$kluczPola] = [
            'label' => $opisPola,
            'value' => $wartoscPola,
        ];
    }

    /**
     * @param array<string, mixed> $wiersz
     * @param array<string, mixed> $wynik
     */
    private function przetworzWierszBezStruktury(array $wiersz, array &$wynik): void
    {
        if(empty($wiersz['form_wartosc'])) {
            return;
        }
        if (
            ($wiersz['form_pole'] ?? null) === 'petent_uid'
        ) {
            $daneInteresanta = $this->pobierzInteresantaDoFormularza(
                $wiersz['form_wartosc'],
                $wiersz['form_dane_id'],
                true,
            );
            if(!isset($wynik['interesanci'])) {
                $wynik['interesanci'] = [
                    'label' => $wiersz['struktura_opis'],
                ];
            }
            $wynik['interesanci']['value'][] = $daneInteresanta;
            return;
        }
        $wynik[$wiersz['struktura_pole']] = [
            'label' => $wiersz['struktura_opis'],
            'value' => $wiersz['form_wartosc'],
        ];
    }

    private function getDekretacjaWydzial(
        string $wartoscPola
    )
    {
        $ids = explode('#', $wartoscPola);
        $rows = [];
        foreach ($ids as $id) {
            $workstationData = $this->workstationQuery->getWorkstationInfo((int)$id);
            if (!empty($workstationData)) {
                $rows[] = Structure::concatWorkstationData($workstationData);
            } else {
                $groupData = $this->groupQuery->getDepartamentInfo((int)$id);
                if (!empty($groupData)) {
                    $rows[] = Structure::concatGroupData($groupData);
                }
            }
        }
        return implode('<br>', $rows);
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

        $departament = $zapytanie->getDepartamentInfo($wartosc);
        $wynik[$kluczPola] = $wartosc . '[' . $departament['groupName'] . ']';
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
