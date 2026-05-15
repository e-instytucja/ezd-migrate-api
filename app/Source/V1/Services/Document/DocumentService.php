<?php

namespace App\Source\V1\Services\Document;

use App\Shared\Functions;
use App\Source\V1\DTO\TypPozycjaDokumentu;
use App\Source\V1\Enum\DocumentQueryContext;
use App\Source\V1\Enum\TypDokumentu;
use App\Source\V1\Queries\CaseQuery;
use App\Source\V1\Queries\Document\DocumentQuery;
use App\Source\V1\Queries\Document\QueryBuilder;
use App\Source\V1\Services\Form\FormService;
use Exception;
use Illuminate\Support\Facades\DB;
use stdClass;

class DocumentService
{



    public function __construct(
        private readonly DocumentQuery $documentQuery,
        private QueryBuilder $documentListQueryBuilder,
        private FormService $formService,
        private readonly CaseQuery $caseQuery,
    )
    {
    }

    public function getLastRowFromHistory(
        $documentUid,
        array $statuses = [],
        string $sortDirection = 'DESC'
    ): stdClass
    {
        return $this->documentQuery->getRowFromHistory(
            $documentUid,
            $statuses,
            'DESC'
        );
    }

    public function getFirstRowFromHistory(
        $documentUid,
        array $statuses = []
    ): stdClass
    {
        return $this->documentQuery->getRowFromHistory(
            $documentUid,
            $statuses,
            'ASC'
        );
    }

    public function getDocumentsListByCaseUID($caseUID)
    {
        $sql = $this->prepareSQLForDataFromCase($caseUID);
        $data = (array)DB::Select($sql['query'], $sql['params']);

        $documentList = $this->hydrateDataToObjects(
            $this->fillDocumentsWithRemainingData(
                $data
            )
        );

        return $documentList;
    }

    private function fillDocumentsWithRemainingData($documentList)
    {
        $rejestrObieg = new rejestrObieg();
        foreach ($documentList as &$document) {
            $document['data_i_czas'] = Functions::convertToISO8601(
                $this->getDocumentDateTime($document['id_dokumentu'], $document['typ'])
            );
            $document['przesylka'] =
                $this->documentElements->getDeliveryType($document['id_dokumentu'], $document['typ']);

            if ($rejestrObieg->sprawdzZwrot($document['id_dokumentu'])) {
                $document['przesylka'] = RodzajPrzesylki::ZWROTKA;
            }

            $employee = new Employee();
            $document['wlasciciel'] = $employee->getEmployee(
                RodzajPracownika::WLASCICIEL,
                $document['id_dokumentu'],
                $document['typ']
            );

        }

        return $documentList;
    }

    public function getDocumentDateTime($documentId, $processType)
    {
        switch ($processType) {
            case TypDokumentu::DOKUMENT:
                $pismoData = $this->documentQuery->getLastInsertedToPismo($documentId, 'pismo_uid');
                if (empty($pismoData) || !isset($pismoData->pismo_createdate)) {
                    throw new Exception(
                        "Brak daty ostatniego pisma '{$documentId}'"
                    );
                }
                $date = $pismoData->pismo_createdate;
                break;
            case TypDokumentu::AUTHENTICATION:
            case TypDokumentu::PISMO:
                $formName = $this->caseQuery->getFormNameByMainDocumentUid($documentId);
                $formValues = $this->formService->getFormValues($documentId, $formName);
                $sprawy = new sprawy();
                $data = $sprawy->getDataFromSprawa($documentId, '', '', '', $formValues);
                if (!count($data) || !isset($data['data'])) {
                    $date = sprawaPrzedluzanie::getInstance()->getDataZarejestrowania($documentId);
                    if ($date === false) {
                        throw new Exception(
                            "Brak daty zarejestrowania pisma '{$documentId}'"
                        );
                    }
                } else {
                    $date = $data['data'];
                }


                $tmpDate = $sprawy->getSprawaCreateDate($documentId);
                $date = ($tmpDate) ? $tmpDate : $date;

                break;
            default:
                throw new WebserviceException("ID: '{$documentId}'", ErrorCode::PROCESS_INCORRECT_TYPE);
        }

        return $date;
    }

    private function hydrateDataToObjects($rawDocuments): TypPozycjaDokumentu
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
            $document->przesylka = $rawDocument['przesylka'];
            $document->wlasciciel = $rawDocument['wlasciciel'];
            $document->blad = $rawDocument['blad'];

            $documents[] = $document;
        }
        return $documents;
    }

    private function prepareSQLForDataFromCase($caseUID)
    {
        $sql = [
            'query'  =>
                '(' . $this->documentListQueryBuilder->buildSQLQuery(
                    DocumentQueryContext::CASE_UID,
                    TypDokumentu::PISMO
                ) . ')
                UNION
                (' .
                $this->documentListQueryBuilder->buildSQLQuery(
                    DocumentQueryContext::CASE_UID,
                    TypDokumentu::DOKUMENT
                ) . ')
                UNION
                (' .
                $this->documentListQueryBuilder->buildSQLQuery(
                    DocumentQueryContext::CASE_UID_MAIN_DOCUMENT_ATTACHED_TO_CASE,
                    TypDokumentu::PISMO
                ) . ')
                UNION
                (' .
                $this->documentListQueryBuilder->buildSQLQuery(
                    DocumentQueryContext::CASE_UID_MAIN_DOCUMENT_ATTACHED_TO_DOCUMENT,
                    TypDokumentu::PISMO
                ) . ')' .
                $this->documentListQueryBuilder->addDocumentGroupOrder(),
            'params' => [],
        ];

        // prepare params
        $sql['params'] = [$caseUID, $caseUID, $caseUID, $caseUID];

        return $sql;
    }

}