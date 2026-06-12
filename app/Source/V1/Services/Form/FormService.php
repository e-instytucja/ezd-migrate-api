<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Form;

use App\Shared\Functions;
use App\Source\V1\Queries\Form\FormQuery;
use Illuminate\Support\Facades\Log;

class FormService
{
    public function __construct(
        private readonly FormQuery $formQuery,
        private readonly FormDaneService $formDaneService,
    ) {
    }

    public function getFormDocumentValues($documentUid, $normalizedProcessName)
    {
        Log::notice('FORM_DOKUMENT_VALUES.start', ['document_uid' => $documentUid, 'form_name' => $normalizedProcessName]);
        $startedAt = Functions::startTimer();

        $daneZBazy = $this->formQuery->getDocumentFormValues($documentUid);
        $strukturaFormularza = $this->pobierzStruktureFormularza($normalizedProcessName);
        $daneFormularza = $this->formDaneService->przetworzDane($daneZBazy, $strukturaFormularza);

        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] FORM_VALUES.ok', [
            'main_document_uid' => $documentUid,
            'form_name' => $normalizedProcessName,
            'fields_count' => count($daneFormularza),
        ]);

        return $daneFormularza;
    }

    /**
     * @throws \JsonException
     */
    public function getFormMainDocumentValues($mainDocumentUid, $normalizedProcessName)
    {
        Log::notice('FORM_MAIN_DOCUMENT_VALUES.start', ['main_document_uid' => $mainDocumentUid, 'form_name' => $normalizedProcessName]);
        $startedAt = Functions::startTimer();

        $daneZBazy = $this->formQuery->getMainDocumentFormValues($mainDocumentUid);
        $strukturaFormularza = $this->pobierzStruktureFormularza($normalizedProcessName);
        $daneFormularza = $this->formDaneService->przetworzDane($daneZBazy, $strukturaFormularza);

        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] FORM_VALUES.ok', [
            'main_document_uid' => $mainDocumentUid,
            'form_name' => $normalizedProcessName,
            'fields_count' => count($daneFormularza),
        ]);

        return $daneFormularza;
    }

    public function pobierzStruktureFormularza(string $formName): array
    {
        $struktura = $this->pobierzStrukturePoPolach($formName);
        $uporzadkowanaStruktura = [];

        foreach ($this->formQuery->getKolejnoscPolFormularza($formName) as $grupaPol) {
            foreach (explode(';', $grupaPol) as $pole) {
                if (!array_key_exists($pole, $struktura)) {
                    continue;
                }

                $uporzadkowanaStruktura[$pole] = $struktura[$pole];
                unset($struktura[$pole]);
            }
        }

        return array_merge($uporzadkowanaStruktura, $struktura);
    }

    private function pobierzStrukturePoPolach(string $formName): array
    {
        $struktura = [];

        foreach ($this->formQuery->getStruktureFormularza($formName) as $wiersz) {
            $struktura[$wiersz['struktura_pole']] = [
                'struktura_typ' => $wiersz['struktura_typ'],
                'struktura_opis' => $wiersz['struktura_opis'],
                'struktura_pole' => $wiersz['struktura_pole'],
            ];
        }

        return $struktura;
    }
}
