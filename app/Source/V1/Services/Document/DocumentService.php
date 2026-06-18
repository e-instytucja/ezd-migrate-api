<?php

namespace App\Source\V1\Services\Document;

use App\Shared\Functions;
use App\Source\V1\DTO\DokumentDanePodstawoweDto;
use App\Source\V1\DTO\DokumentDto;
use App\Source\V1\DTO\PracownikDto;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaDokumentow;
use App\Source\V1\Queries\Case\CaseQuery;
use App\Source\V1\Queries\Document\DocumentQuery;
use App\Source\V1\Queries\Document\DocumentListQuery;
use App\Source\V1\Queries\Structure\UugQuery;
use App\Source\V1\Services\Attachment\AttachmentService;
use App\Source\V1\Services\Form\FormService;
use App\Source\V1\Services\Suppliant\SupliantService;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Source\V1\Services\Document\HistoryService as DocumentHistoryService;
use App\Source\V1\Services\Case\HistoryService as CaseHistoryService;

class DocumentService
{



    public function __construct(
        private readonly DocumentQuery $documentQuery,
        private readonly CaseQuery $caseQuery,
        private readonly DocumentListQuery $documentListQuery,
        private readonly UugQuery $uugQuery,
        private readonly SupliantService $supliantService,
        private readonly AttachmentService $attachmentService,
        private readonly DocumentHistoryService $documentHistoryService,
        private readonly CaseHistoryService $caseHistoryService,
        private readonly FormService $formService
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

    public function getDocumentDetails(
        KryteriaWyszukiwaniaDokumentow $kryteriaWyszukiwania
    ): DokumentDto {
        $documentDetails = $this->documentListQuery->getList($kryteriaWyszukiwania);
        if (count($documentDetails) === 0) {
            throw new Exception('Document not found');
        }

        $row = $documentDetails[0];
        $this->supliantService->hydrateSuppliantData($row, $row['id_dokumentu']);

        return $this->mapToDokumentDto($row);
    }

    /**
     * @param array<string, mixed> $row
     * @throws \JsonException
     */
    private function mapToDokumentDto(array $row): DokumentDto
    {
        $historiaObieguRaw = $row['document_group_type'] === DocumentListQuery::DOKUMENTY_W_SPRAWIE
            ? $this->documentHistoryService->getHistory($row['id_dokumentu'])
            : $this->caseHistoryService->getHistory($row['id_dokumentu']);



        $daneFormularza = $row['document_group_type'] === DocumentListQuery::DOKUMENTY_W_SPRAWIE
            ? $this->formService->getFormDocumentValues($row['id_dokumentu'], $row['nazwa_znormalizowana_procesu'])
            : $this->formService->getFormMainDocumentValues($row['id_dokumentu'], $row['nazwa_znormalizowana_procesu']);

        $wlasciciel = PracownikDto::fromDocumentRow($row);
        if($row['typ'] === DocumentQuery::DOKUMENTY_W_SPRAWIE) {
            $historyRow = $this->documentQuery->getFirstRowFromHistory($row['id_dokumentu']);
        }
        else {
            $historyRow = $this->caseQuery->getFirstRowFromHistory($row['id_dokumentu']);
        }
        $utworzyl = PracownikDto::fromWorkstationRow(
            $this->uugQuery->getInfo($historyRow->uugid_from),
        );

        return new DokumentDto(
            danePodstawowe: DokumentDanePodstawoweDto::fromDocumentRow($row),
            wlasciciel: $wlasciciel,
            utworzyl: $utworzyl,
            interesanci: $daneFormularza->extractInteresanci(),
            zalaczniki: $daneFormularza->extractZalaczniki(),
            historiaObiegu: $historiaObieguRaw,
            daneFormularza: $daneFormularza,
        );
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
        $documentList = $this->documentListQuery->getList(KryteriaWyszukiwaniaDokumentow::forTeczkaUid($caseUID));
        foreach ($documentList as &$document) {
            $this->supliantService->hydrateSuppliantData($document, $document['id_dokumentu']);
        }
        unset($document);


        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] DOCUMENT_LIST.ok', [
            'case_uid' => $caseUID,
            'count' => count($documentList),
        ]);

        return $documentList;
    }



}