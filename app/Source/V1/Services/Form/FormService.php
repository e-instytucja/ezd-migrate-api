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

    public function getFormDocumentValues(string $documentUid, string $normalizedProcessName)
    {
        Log::notice('FORM_DOKUMENT_VALUES.start', ['document_uid' => $documentUid, 'form_name' => $normalizedProcessName]);
        $startedAt = Functions::startTimer();

        $daneZBazy = $this->formQuery->getDocumentFormValues($documentUid);
        $daneFormularza = $this->formDaneService->przetworzDane($daneZBazy);

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
    public function getFormMainDocumentValues(string $mainDocumentUid, string $normalizedProcessName)
    {
        Log::notice('FORM_MAIN_DOCUMENT_VALUES.start', ['main_document_uid' => $mainDocumentUid, 'form_name' => $normalizedProcessName]);
        $startedAt = Functions::startTimer();

        $daneZBazy = $this->formQuery->getMainDocumentFormValues($mainDocumentUid);
        $daneFormularza = $this->formDaneService->przetworzDane($daneZBazy);

        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] FORM_VALUES.ok', [
            'main_document_uid' => $mainDocumentUid,
            'form_name' => $normalizedProcessName,
            'fields_count' => count($daneFormularza),
        ]);

        return $daneFormularza;
    }


}
