<?php

declare(strict_types=1);

/**
 * Stałe identyfikatory (odczyt z bazy EZD jak w dev). Bez zapisu do DB w testach.
 */
return [
    'workstation_ids' => [142],
    'workstation_ids_rpw' => [180],
    'case_uid' => '632acd3d57cbb',
    'case_document_id' => '202902',
    'dntas_case_uid' => '5cf0ea366d6fa',
    'dntas_document_id' => '104',
    'document_przychodzacy_id' => '100075',
    'document_rpw_id' => '100288',
    'document_rpw_sprawa_id' => '100247',
    'attachment_uid' => '6311cc3caa81e',
    // Opcjonalnie: file_id z epuap_download_file (dev DB) — test showEpuap
    'epuap_file_id' => '',
    'invalid_document_id' => '999999999',
    'unknown_case_uid' => '0000000000000',
    'malformed_case_uid' => 'not-a-valid-uid',
    'invalid_registry_assignment_id' => '999999999',
];
