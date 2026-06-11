<?php

namespace App\Source\V1\Services\Document;

use App\Shared\Functions;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaSpraw;
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

    public function getTypes(): array
    {
        return [
            ['id' => DocumentListQuery::DOKUMENTY_W_SPRAWIE, 'label' => 'Dokumenty w sprawie'],
            ['id' => DocumentListQuery::PISMA_INICJUJACE_W_SPRAWIE, 'label' => 'Pisma inicjujące'],
            ['id' => DocumentListQuery::PISMA_INICJUJACE_WIODACE, 'label' => 'Pisma wiodące w sprawie'],
            ['id' => DocumentListQuery::PISMA_POTWIERDZENIE_ODBIORU, 'label' => 'potwierdzenia odbioru'],
        ];
    }

    public function getStatuses()
    {
        return $this->documentQuery->getStatuses();
    }



    public function getProcessNames(KryteriaWyszukiwaniaDokumentow $kryteriaWyszukiwania)
    {
        return $this->documentQuery->getProcessNames($kryteriaWyszukiwania);
    }

    public function getDocumentsListByCaseUID(string $caseUID): array
    {
        Log::notice('DOCUMENT_LIST.start', ['case_uid' => $caseUID]);
        $startedAt = Functions::startTimer();

        $documentList = $this->documentListQuery->getListByTeczkaUid($caseUID);

        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] DOCUMENT_LIST.ok', [
            'case_uid' => $caseUID,
            'count' => count($documentList),
        ]);
        $data = json_encode($documentList, JSON_THROW_ON_ERROR);

        return $documentList;
    }



}