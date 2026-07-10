<?php

namespace App\Source\V1\Services\Document;

use App\Shared\Functions;
use App\Source\V1\DTO\DokumentDanePodstawoweDto;
use App\Source\V1\DTO\DokumentDto;
use App\Source\V1\DTO\PracownikDto;
use App\Source\V1\Enum\TypDokument;
use App\Source\V1\Enum\TypFormularza;
use App\Source\V1\Enum\TypPowiazaniaDokumentu;
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
use App\Source\V1\Services\Registry\RegistryAssignmentRpwService;
use App\Source\V1\Services\Registry\RegistryAssignmentService;

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
        private readonly FormService $formService,
        private readonly RegistryAssignmentService $registryAssignmentService,
        private readonly RegistryAssignmentRpwService $registryAssignmentRpwService,
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
        $logSql = (bool) config('app.log_sql_queries');
        /** @var array<string, float> $phases */
        $phases = [];

        $tCount = Functions::startTimer();
        $count = $this->documentListQuery->getListCount($kryteriaWyszukiwania);
        if ($logSql) {
            $phases['count_ms'] = round((microtime(true) - $tCount) * 1000, 2);
            Log::info('DOCUMENT_LIST.phase', [
                'phase' => 'count',
                'elapsed_ms' => $phases['count_ms'],
                'offset' => $kryteriaWyszukiwania->paginacja->offset,
                'limit' => $kryteriaWyszukiwania->paginacja->limit,
            ]);
        }
        if (empty($count)) {
            Log::info('DOCUMENT_LIST.empty', array_filter([
                'offset' => $kryteriaWyszukiwania->paginacja->offset,
                'limit' => $kryteriaWyszukiwania->paginacja->limit,
                'phases' => $logSql ? $phases : null,
            ]));
            return [
                'data' => [],
                'count' => $count,
            ];
        }
        $tList = Functions::startTimer();
        $list = $this->documentListQuery->getList($kryteriaWyszukiwania);
        if ($logSql) {
            $phases['list_ms'] = round((microtime(true) - $tList) * 1000, 2);
            Log::info('DOCUMENT_LIST.phase', [
                'phase' => 'list',
                'elapsed_ms' => $phases['list_ms'],
                'returned_count' => count($list),
            ]);
        }
        $tHydrate = Functions::startTimer();
        foreach ($list as &$row) {
            $this->hydrateDocumentListRowEnums($row);
            $row['zalaczniki_details'] = !empty($row['zalaczniki'])
                ? $this->attachmentService->getAttachmentsDetails($row['zalaczniki'])
                : [];
            $this->supliantService->hydrateSuppliantData($row, $row['id_dokumentu']);
        }
        unset($row);
        if ($logSql) {
            $phases['hydrate_ms'] = round((microtime(true) - $tHydrate) * 1000, 2);
            Log::info('DOCUMENT_LIST.phase', [
                'phase' => 'hydrate',
                'elapsed_ms' => $phases['hydrate_ms'],
                'returned_count' => count($list),
            ]);
        }

        Log::info('[' . Functions::elapsedMs($startedAt) . '] DOCUMENT_LIST.ok', array_filter([
            'count' => $count,
            'returned' => count($list),
            'phases' => $logSql ? $phases : null,
        ]));

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
        $danePodstawowe = DokumentDanePodstawoweDto::fromDocumentRow($row);
        $typDokumentu = $danePodstawowe->values->typDokumentu
            ?? throw new Exception('Brak typ_dokumentu w wierszu dokumentu');

        $historiaObieguRaw = $typDokumentu->isWychodzacy()
            ? $this->documentHistoryService->getHistory($row['id_dokumentu'])
            : $this->caseHistoryService->getHistory($row['id_dokumentu']);

        $daneFormularza = $typDokumentu->isWychodzacy()
            ? $this->formService->getFormDocumentValues($row['id_dokumentu'], $row['nazwa_znormalizowana_procesu'])
            : $this->formService->getFormMainDocumentValues($row['id_dokumentu'], $row['nazwa_znormalizowana_procesu']);

        $wlasciciel = PracownikDto::fromDocumentRow($row);
        if ($typDokumentu->isWychodzacy()) {
            $historyRow = $this->documentQuery->getFirstRowFromHistory($row['id_dokumentu']);
        } else {
            $historyRow = $this->caseQuery->getFirstRowFromHistory($row['id_dokumentu']);
        }
        $utworzyl = PracownikDto::fromWorkstationRow(
            $this->uugQuery->getInfo($historyRow->uugid_from),
        );

        $documentId = (string) $row['id_dokumentu'];

        return new DokumentDto(
            danePodstawowe: $danePodstawowe,
            wlasciciel: $wlasciciel,
            utworzyl: $utworzyl,
            interesanci: $daneFormularza->extractInteresanci(),
            zalaczniki: $daneFormularza->extractZalaczniki(),
            historiaObiegu: $historiaObieguRaw,
            daneFormularza: $daneFormularza,
            rejestry: $this->registryAssignmentService->getByDocumentId($documentId),
            wysylki: $this->registryAssignmentRpwService->getByDocumentId($documentId),
        );
    }
    public function getTypes(): array
    {
        return array_map(
            static fn (TypDokument $typDokumentu) => $typDokumentu->toFilterOption(),
            TypDokument::wszystkie(),
        );
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
            $this->hydrateDocumentListRowEnums($document);
            $this->supliantService->hydrateSuppliantData($document, $document['id_dokumentu']);
        }
        unset($document);


        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] DOCUMENT_LIST.ok', [
            'case_uid' => $caseUID,
            'count' => count($documentList),
        ]);

        return $documentList;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateDocumentListRowEnums(array &$row): void
    {
        $row['typ_dokumentu'] = TypDokument::tryFromWiersza($row['typ_dokumentu'] ?? null)?->toApi();
        $row['typ_formularza'] = TypFormularza::tryFromWiersza($row['typ_formularza'] ?? null)?->toApi();
        $row['typ_powiazania_dokumentu'] = TypPowiazaniaDokumentu::tryFromWiersza($row['typ_powiazania_dokumentu'] ?? null)?->toApi();
    }



}