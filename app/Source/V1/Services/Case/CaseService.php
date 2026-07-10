<?php

namespace App\Source\V1\Services\Case;

use App\Shared\Functions;
use App\Source\V1\DTO\SprawaDanePodstawoweDto;
use App\Source\V1\DTO\PracownikDto;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaSpraw;
use App\Source\V1\DTO\SprawaDto;
use App\Source\V1\DTO\SprawaZnakDto;
use App\Source\V1\Queries\Case\CaseListQuery;
use App\Source\V1\Queries\Case\CaseQuery;
use App\Source\V1\Queries\Form\FormQuery;
use App\Source\V1\Queries\Structure\UugQuery;
use App\Source\V1\Queries\Structure\WorkstationQuery;
use App\Source\V1\Services\Attachment\AttachmentService;
use App\Source\V1\Services\Case\HistoryService as CaseHistoryService;
use App\Source\V1\Services\Document\DocumentService;
use App\Source\V1\Services\Form\FormService;
use App\Source\V1\Services\Suppliant\SupliantService;
use Exception;
use Illuminate\Support\Facades\Log;

class CaseService
{

    public function __construct(
        private readonly CaseQuery          $caseQuery,
        private readonly CaseListQuery      $caseListQuery,
        private readonly DocumentService    $documentService,
        private readonly FormService        $formService,
        private readonly CaseHistoryService $caseHistoryService,
        private readonly SupliantService    $supliantService,
        private readonly FormQuery              $formQuery,
        private readonly AttachmentService      $attachmentService,
        private readonly WorkstationQuery   $workstationQuery,
        private readonly UugQuery           $uugQuery
    )
    {
    }

    /**
     * @throws Exception
     * @throws \ReflectionException
     */
    public function getCaseDetails(KryteriaWyszukiwaniaSpraw $kryteriaWyszukiwania, int $dntas = 0): SprawaDto
    {
        Log::notice('CASE_DETAILS.start', ['kryteriaWyszukiwania' => json_encode($kryteriaWyszukiwania), 'dntas' => $dntas]);
        $startedAt = Functions::startTimer();

        $caseUid = $kryteriaWyszukiwania->filtry->sprawaUid;
        $caseRow = $this->caseListQuery->getList($kryteriaWyszukiwania)[0];

        $sprawa = $this->mapToSprawaDto($caseRow, $caseUid, $dntas);

        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] CASE_DETAILS.ok', [
            'case_uid' => $caseUid,
            'main_document_uid' => $caseRow['main_document_uid'],
            'process_id' => $sprawa->danePodstawowe->values->idProcesu,
            'documents_count' => count($sprawa->aktaSprawy ?? []),
        ]);

        return $sprawa;
    }

    /**
     * @param array<string, mixed> $row
     * @throws Exception
     */
    private function mapToSprawaDto(array $row, string $caseUid, int $dntas): SprawaDto
    {
        $mainDocumentUid = $row['main_document_uid'];
        $normalizedProcessName = $row['nazwa_procesu_znormalizowana'];

        $titleAndDesc = $this->caseQuery->getTitleAndDescription($caseUid, $dntas);
        $wlasciciel = PracownikDto::fromWorkstationRow(
            $this->workstationQuery->getWorkstationInfo($row['wlasciciel_stanowisko_id']),
        );
        $historyRow = $this->caseQuery->getFirstRowFromHistory($mainDocumentUid);
        $utworzyl = PracownikDto::fromWorkstationRow(
            $this->uugQuery->getInfo($historyRow->uugid_from),
        );

        $daneFormularza = $this->formService->getFormMainDocumentValues($mainDocumentUid, $normalizedProcessName);

        $sprawa = new SprawaDto();
        $sprawa->znakSprawy = SprawaZnakDto::fromCaseRow(
            $row,
            $this->caseQuery->getAllFromTeczkaBySprawaUid($caseUid, $dntas),
            $caseUid,
            $wlasciciel->stanowiskoNazwa,
        );
        $sprawa->danePodstawowe = SprawaDanePodstawoweDto::fromCaseRow($row, $titleAndDesc);
        $sprawa->wlasciciel = $wlasciciel;
        $sprawa->utworzyl = $utworzyl;
        $sprawa->aktaSprawy = $dntas
            ? []
            : $this->documentService->getDocumentsListByCaseUID($caseUid);
        $sprawa->daneFormularza = $daneFormularza;
        $sprawa->interesanci = $daneFormularza->extractInteresanci();
        $sprawa->zalaczniki = $daneFormularza->extractZalaczniki();
        $sprawa->historiaObiegu = $this->caseHistoryService->getHistory($mainDocumentUid);

        return $sprawa;
    }

    public function getList(KryteriaWyszukiwaniaSpraw $kryteriaWyszukiwania): array
    {
        Log::notice('CASE_LIST.start', [
            'offset' => $kryteriaWyszukiwania->paginacja->offset,
            'limit' => $kryteriaWyszukiwania->paginacja->limit,
            'page' => $kryteriaWyszukiwania->paginacja->page,
            'sort_field' => $kryteriaWyszukiwania->sortowanie->field,
            'sort_direction' => $kryteriaWyszukiwania->sortowanie->direction,
            'dntas' => $kryteriaWyszukiwania->dntas,
        ]);
        $startedAt = Functions::startTimer();
        $logSql = (bool) config('app.log_sql_queries');
        /** @var array<string, float> $phases */
        $phases = [];

        $tCount = Functions::startTimer();
        $count = $this->caseListQuery->getListCount($kryteriaWyszukiwania);
        if ($logSql) {
            $phases['count_ms'] = round((microtime(true) - $tCount) * 1000, 2);
            Log::info('CASE_LIST.phase', [
                'phase' => 'count',
                'elapsed_ms' => $phases['count_ms'],
                'offset' => $kryteriaWyszukiwania->paginacja->offset,
                'limit' => $kryteriaWyszukiwania->paginacja->limit,
            ]);
        }
        if (empty($count)) {
            Log::info('CASE_LIST.empty', array_filter([
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
        $list = $this->caseListQuery->getList($kryteriaWyszukiwania);
        if ($logSql) {
            $phases['list_ms'] = round((microtime(true) - $tList) * 1000, 2);
            Log::info('CASE_LIST.phase', [
                'phase' => 'list',
                'elapsed_ms' => $phases['list_ms'],
                'returned_count' => count($list),
            ]);
        }
        $tHydrate = Functions::startTimer();
        foreach ($list as &$row) {
            $row['zalaczniki_details'] = !empty($row['zalaczniki'])
                ? $this->attachmentService->getAttachmentsDetails($row['zalaczniki'])
                : [];

            $this->supliantService->hydrateSuppliantData($row, $row['main_document_uid']);

        }
        unset($row);
        if ($logSql) {
            $phases['hydrate_ms'] = round((microtime(true) - $tHydrate) * 1000, 2);
            Log::info('CASE_LIST.phase', [
                'phase' => 'hydrate',
                'elapsed_ms' => $phases['hydrate_ms'],
                'returned_count' => count($list),
            ]);
        }
        Log::info('[' . Functions::elapsedMs($startedAt) . '] CASE_LIST.ok', array_filter([
            'total_count' => $count,
            'returned_count' => count($list),
            'offset' => $kryteriaWyszukiwania->paginacja->offset,
            'limit' => $kryteriaWyszukiwania->paginacja->limit,
            'phases' => $logSql ? $phases : null,
        ]));

        return [
            'data' => $list,
            'count' => $count,
        ];
    }

    /**
     * @return array<int, array{status: string, opis: string}>
     */
    public function getStatuses(int $dntas = 0): array
    {
        return $this->caseQuery->getStatuses($dntas);
    }

    public function streamMainDocumentAttachmentsAuditCandidates(int $limit = 0, int $offset = 0): \Generator
    {
        yield from $this->formQuery->streamAllValuesByKey('pliki', $limit, $offset);
    }

    public function countMainDocumentAttachmentsAuditCandidates(int $limit = 0, int $offset = 0): int
    {
        return $this->formQuery->countAllValuesByKey('pliki', $limit, $offset);
    }

}
