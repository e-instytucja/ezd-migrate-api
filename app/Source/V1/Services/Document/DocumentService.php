<?php

namespace App\Source\V1\Services\Document;

use App\Shared\Functions;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaDokumentow;
use App\Source\V1\DTO\TypPozycjaDokumentu;
use App\Source\V1\Enum\RodzajPracownika;
use App\Source\V1\Enum\TypDokumentu;
use App\Source\V1\Queries\Case\CaseQuery;
use App\Source\V1\Queries\Document\DocumentQuery;
use App\Source\V1\Queries\Document\DocumentListQuery;
use App\Source\V1\Queries\Form\FormQuery;
use App\Source\V1\Services\Attachment\AttachmentService;
use App\Source\V1\Services\Structure\EmployeeService;
use App\Source\V1\Services\Suppliant\SupliantService;
use Exception;
use Illuminate\Support\Facades\Log;

class DocumentService
{



    public function __construct(
        private readonly DocumentQuery $documentQuery,
        private readonly DocumentListQuery $documentListQuery,
        private readonly CaseQuery $caseQuery,
        private readonly EmployeeService $employeeService,
        private readonly FormQuery $formQuery,
        private readonly SupliantService $supliantService,
        private readonly AttachmentService $attachmentService
    )
    {
    }

    /**
     * @throws \JsonException
     */
    public function getList(KryteriaWyszukiwaniaDokumentow $kryteriaWyszukiwania): array
    {
        Log::notice('DOCUMENT_LIST.start', [
            'offset' => $kryteriaWyszukiwania->paginacja->offset,
            'limit' => $kryteriaWyszukiwania->paginacja->limit,
            'page' => $kryteriaWyszukiwania->paginacja->page,
            'sort_field' => $kryteriaWyszukiwania->sortowanie->field,
            'sort_direction' => $kryteriaWyszukiwania->sortowanie->direction,
            'dntas' => $kryteriaWyszukiwania->dntas,
        ]);
        $startedAt = Functions::startTimer();

        $count = $this->documentListQuery->getListCount($kryteriaWyszukiwania);
        if (empty($count)) {
            Log::info('DOCUMENT_LIST.empty', [
                'offset' => $kryteriaWyszukiwania->paginacja->offset,
                'limit' => $kryteriaWyszukiwania->paginacja->limit,
            ]);
            return [
                'data' => [],
                'count' => $count,
            ];
        }
        $list = $this->documentListQuery->getList($kryteriaWyszukiwania);
        foreach ($list as &$row) {
            $row['zalaczniki_details'] = !empty($row['zalaczniki'])
                ? $this->attachmentService->getAttachmentsDetails($row['zalaczniki'])
                : [];
            $this->supliantService->hydrateSuppliantData($row, $row['id_dokumentu']);
        }
        unset($row);

        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] DOCUMENT_LIST.ok', [
            'count' => $count,
            'returned' => count($list),
        ]);

        return [
            'data' => $list,
            'count' => $count,
        ];
    }

    public function getDocumentsListByCaseUID(string $caseUID, int $dntas = 0): array
    {
        Log::notice('DOCUMENT_LIST.start', ['case_uid' => $caseUID, 'dntas' => $dntas]);
        $startedAt = Functions::startTimer();

        $data = $this->documentListQuery->getListByTeczkaUid($caseUID, $dntas);
        $documentList = $this->hydrateDataToObjects(
            $this->fillDocumentsWithRemainingData(
                $data
            )
        );

        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] DOCUMENT_LIST.ok', [
            'case_uid' => $caseUID,
            'count' => count($documentList),
        ]);

        return $documentList;
    }

    private function fillDocumentsWithRemainingData($documentList)
    {
//        $rejestrObieg = new rejestrObieg();
        foreach ($documentList as &$document) {
            $dateTime = $this->getDocumentDateTime($document['id_dokumentu'], $document['typ']);
            $document['data_i_czas'] = Functions::convertToISO8601($dateTime);
//            $document['przesylka'] =
//                $this->documentElements->getDeliveryType($document['id_dokumentu'], $document['typ']);

//            if ($rejestrObieg->sprawdzZwrot($document['id_dokumentu'])) {
//                $document['przesylka'] = RodzajPrzesylki::ZWROTKA;
//            }

            $document['wlasciciel'] = $this->employeeService->getEmployee(
                RodzajPracownika::WLASCICIEL,
                $document['id_dokumentu'],
                $document['typ']
            );

        }

        return $documentList;
    }

    public function getDocumentDateTime($documentId, $processType): string
    {
        switch ($processType) {
            case TypDokumentu::DOKUMENT:
                $pismoData = $this->documentQuery->getLastInsertedToPismo($documentId, 'pismo_uid');
                if (empty($pismoData) || !isset($pismoData->pismo_createdate)) {
                    Log::error('DOCUMENT_DATETIME.error', ['document_id' => $documentId, 'process_type' => $processType, 'error' => 'missing_pismo_date']);
                    throw new Exception(
                        "Brak daty ostatniego dokumentu '{$documentId}'"
                    );
                }
                $date = $pismoData->pismo_createdate;
                break;
            case TypDokumentu::AUTHENTICATION:
            case TypDokumentu::PISMO:
//                $formName = $this->caseQuery->getFormNameByMainDocumentUid($documentId);
//                $formValues = $this->formService->getFormValues($documentId, $formName);
                $value = $this->formQuery->getValueFromFormDane('data', $documentId);
                if (empty($value)) {
                    $date = $this->caseQuery->getSprawaPrzedluzanie($documentId, 'sprawa_createdate');
                    if ($date === false) {
                        Log::error('DOCUMENT_DATETIME.error', ['document_id' => $documentId, 'process_type' => $processType, 'error' => 'missing_registration_date']);
                        throw new Exception(
                            "Brak daty zarejestrowania pisma '{$documentId}'"
                        );
                    }
                } else {
                    $date = $value;
                }


                $tmpDate = $this->caseQuery->getSprawaCreateDate($documentId);
                $date = ($tmpDate) ? $tmpDate : $date;

                break;
            default:
                Log::error('DOCUMENT_DATETIME.error', ['document_id' => $documentId, 'process_type' => $processType, 'error' => 'unsupported_process_type']);
                throw new Exception("ID: '{$documentId}'");
        }

        return $date;
    }

    /**
     * @param $rawDocuments
     * @return TypPozycjaDokumentu[]
     */
    private function hydrateDataToObjects($rawDocuments): array
    {
        $documents = [];
        foreach ($rawDocuments as $rawDocument) {
            $document = new TypPozycjaDokumentu();
            $document->id_dokumentu = $rawDocument['id_dokumentu'];
            $document->nazwa_procesu = $rawDocument['nazwa_procesu'];
            $document->id_procesu = $rawDocument['id_procesu'];
            $document->status_procesu = $rawDocument['status_procesu'];
            $document->data_i_czas = $rawDocument['data_i_czas'];
            $document->wersja = $rawDocument['wersja'];
            $document->przesylka = $rawDocument['przesylka']??'';
            $document->wlasciciel = $rawDocument['wlasciciel'];
            $document->blad = $rawDocument['blad']??'';

            $documents[] = $document;
        }
        return $documents;
    }



}