# Attachment queries

Katalog: `app/Source/V1/Queries/Attachment/AttachmentQuery.php`  
Serwis: `app/Source/V1/Services/Attachment/AttachmentService.php`  
Resolver ścieżek ePUAP: `app/Source/V1/Services/Attachment/AttachmentPathResolver.php`

## `getAttachmentRows(array $attachmentUids)`

Lista załączników po UID (pole `pliki` w formularzu).

```sql
SELECT * FROM eurzad_zalacznik
WHERE zalacznik_uid IN (?)
```

## `getAttachmentRow(string $zalacznikUid)`

Pojedynczy wiersz metadanych załącznika (m.in. ścieżka na dysku).

```sql
SELECT * FROM eurzad_zalacznik
WHERE zalacznik_uid = ?
LIMIT 1
```

## `getEpuapDownloadFileRowByFileId(string $fileId)`

Wyszukanie dużego pliku ePUAP po samym `file_id`.

```sql
SELECT * FROM epuap_download_file
WHERE file_id = ?
ORDER BY id ASC
LIMIT 1
```

`file_id` pochodzi z parametru URL ePUAP (`DownloadServlet?fileId=...`).  
`zalacznik_uid` jest pobierany z tego wiersza (nie z URL API).

Jeśli `countEpuapDownloadFileRowsByFileId` > 1 → 404 (niejednoznaczny `file_id`).

## Endpoint `GET|POST /api/v1/attachments/epuap/{fileId}`

1. `getEpuapDownloadFileRowByFileId` — brak wiersza → 404
2. `getAttachmentRow(zalacznik_uid z wiersza)` — brak → 404
3. `AttachmentPathResolver::resolve` — ścieżka względem `FILES_URL`
4. Plik na dysku → binary stream (200)
5. Brak pliku na dysku → 409 JSON envelope

## Endpoint `GET|POST /api/v1/attachments/epuap/{zalacznikUid}/{fileId}`

Kontrakt dla klienta EZD (Madkom). Ta sama logika co trasa jednoparametrowa — **`zalacznikUid` z URL jest ignorowany**; lookup nadal po `fileId` w `epuap_download_file`.

Opcjonalna walidacja pary `file_id` + `zalacznik_uid` — tymczasowo wyłączona (zakomentowana w serwisie/query).

Wzorce ścieżek (względem `FILES_URL`): patrz `AttachmentPathResolver` — logika jak EZD3 `Attachment::getFilePathByData`.
