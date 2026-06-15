<?php

namespace App\Source\V1\Services\Document;

use App\Shared\Functions;
use App\Source\V1\DTO\DokumentDto;
use App\Source\V1\DTO\HistoriaObieguDto;
use App\Source\V1\DTO\InteresantDto;
use App\Source\V1\DTO\PracownikDto;
use App\Source\V1\DTO\Request\KryteriaWyszukiwaniaDokumentow;
use App\Source\V1\Queries\Document\DocumentQuery;
use App\Source\V1\Queries\Document\DocumentListQuery;
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
        private readonly DocumentListQuery $documentListQuery,
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

        $zalaczniki = !empty($daneFormularza['pliki']['value']) ? $daneFormularza['pliki']['value'] : [];
        unset($daneFormularza['pliki']);
        $interesanci = !empty($daneFormularza['interesanci']['value']) ? $daneFormularza['interesanci']['value'] : [];
        unset($daneFormularza['interesanci']);

        return new DokumentDto(
            nazwaProcesu: $row['nazwa_procesu'] ?? null,
            idProcesu: isset($row['id_procesu']) ? (int) $row['id_procesu'] : null,
            statusProcesu: $row['status_procesu'] ?? null,
            typ: isset($row['typ']) ? (int) $row['typ'] : null,
            znakSprawy: $row['znak_sprawy'] ?? null,
            idDokumentu: $row['id_dokumentu'] ?? null,
            nrNaPismie: $row['nr_na_pismie'] ?? null,
            wersja: isset($row['wersja']) ? (int) $row['wersja'] : null,
            dataRejestracji: $row['data_rejestracji'] ?? null,
            dataUtworzenia: $row['data_utworzenia'] ?? null,
            dokumentTytul: $row['dokument_tytul'] ?? null,
            trescWniosku: $row['tresc_wniosku'] ?? null,
            nrKsiegi: ($row['nr_ksiegi'] ?? '') !== '' ? $row['nr_ksiegi'] : null,
            documentGroupType: isset($row['document_group_type']) ? (int) $row['document_group_type'] : null,
            wlasciciel: new PracownikDto(
                id: isset($row['wlasciciel_stanowisko_id']) ? (int) $row['wlasciciel_stanowisko_id'] : null,
                skrot: $row['wlasciciel_stanowisko_skrot'] ?? null,
                nazwa: $row['wlasciciel_stanowisko_nazwa'] ?? null,
                komorkaSkrot: $row['wlasciciel_komorka_skrot'] ?? null,
                komorkaNazwa: $row['wlasciciel_komorka_nazwa'] ?? null,
                imie: $row['wlasciciel_imie'] ?? null,
                nazwisko: $row['wlasciciel_nazwisko'] ?? null,
                nazwisko2: $row['wlasciciel_nazwisko2'] ?? null,
                nazwisko3: $row['wlasciciel_nazwisko3'] ?? null,
                imieNazwisko: $row['wlasciciel_imie_nazwisko'] ?? null,
            ),
            interesanci: $interesanci,
            zalaczniki: $zalaczniki,
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

        $documentList = $this->documentListQuery->getListByTeczkaUid($caseUID);

        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] DOCUMENT_LIST.ok', [
            'case_uid' => $caseUID,
            'count' => count($documentList),
        ]);

        return $documentList;
    }



}