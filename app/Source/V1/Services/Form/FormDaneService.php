<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Form;

use App\Source\V1\DTO\DaneFormularzaDto;
use App\Source\V1\DTO\InteresantDto;
use App\Source\V1\DTO\DaneFormularzaPoleDto;
use App\Source\V1\DTO\PracownikDto;
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
     */
    public function przetworzDane(array $daneZBazy): DaneFormularzaDto
    {
        $wynik = new DaneFormularzaDto();

        foreach ($daneZBazy as $wiersz) {
            if ($wiersz['struktura_typ'] !== null) {
                $this->przetworzWierszZeStruktura($wiersz, $wynik);
            } else {
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
     */
    private function przetworzWierszZeStruktura(
        array $wiersz,
        DaneFormularzaDto $wynik,
    ): void {
        $kluczPola = $wiersz['struktura_pole'];
        $wartoscPola = $wiersz['form_wartosc'];
        $typPola = $wiersz['struktura_typ'];
        $opisPola = $wiersz['struktura_opis'];

        if (!empty($wartoscPola)) {

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
            if (!$wynik->hasPole('interesanci')) {
                $wynik->addPole('interesanci', new DaneFormularzaPoleDto(
                    label: $opisPola,
                    value: [],
                ));
            }

            $wynik->appendToPoleValue('interesanci', $wartoscPola);

            return;
        }

        $wynik->addPole($kluczPola, new DaneFormularzaPoleDto(
            label: $opisPola,
            value: $wartoscPola,
        ));
    }

    /**
     * @param array<string, mixed> $wiersz
     */
    private function przetworzWierszBezStruktury(array $wiersz, DaneFormularzaDto $wynik): void
    {
        if (empty($wiersz['form_wartosc'])) {
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
            if (!$wynik->hasPole('interesanci')) {
                $wynik->addPole('interesanci', new DaneFormularzaPoleDto(
                    label: $wiersz['struktura_opis'] ?? '',
                    value: [],
                ));
            }
            $wynik->appendToPoleValue('interesanci', $daneInteresanta);

            return;
        }
        $wynik->addPole($wiersz['struktura_pole'], new DaneFormularzaPoleDto(
            label: $wiersz['struktura_opis'] ?? '',
            value: $wiersz['form_wartosc'],
        ));
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
                $rows[] = PracownikDto::labelFromWorkstationRow($workstationData);
            } else {
                $groupData = $this->groupQuery->getDepartamentInfo((int)$id);
                if (!empty($groupData)) {
                    $rows[] = PracownikDto::labelFromGroup($groupData);
                }
            }
        }
        return implode('<br>', $rows);
    }

    private function pobierzInteresantaDoFormularza(
        mixed $idInteresanta,
        mixed $idDanychFormularza,
        bool $czyGlowny,
    ): InteresantDto {
        $interesantDane = $this->suppliantService->getSupliantById($idInteresanta);
        $interesantRola = $this->suppliantService->getPetentRoleById($idDanychFormularza);

        return $this->suppliantService->mapToInteresantDto(
            row: $interesantDane,
            uid: (string) $idInteresanta,
            glowny: $czyGlowny,
            role: $interesantRola,
        );
    }
}
