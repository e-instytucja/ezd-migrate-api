# Pytania otwarte i TODO

Living document — nierozstrzygnięte kwestie z Fazy 1 dokumentacji.
Kontekst SQL: `docs/queries/`, `docs/database.md`.

**Legenda kategorii:** Bug | Biznes | Techniczne | Martwy kod | ADR

---

## 1. Błędy / możliwe bugi w kodzie

### Q-12 — `utworzyl` zawsze z `eurzad_pismo_obieg`

| Pole | Wartość |
|------|---------|
| **Opis** | `DocumentService::mapToDokumentDto` — pole `utworzyl` zawsze z `documentQuery->getFirstRowFromHistory` (`eurzad_pismo_obieg`). Tymczasem `historiaObiegu` dla `!DokWychodzacy` idzie przez `CaseHistoryService` (`eurzad_obieg`). |
| **Priorytet** | **Wysoki** |
| **Ryzyko** | Błędny lub pusty autor dokumentu dla pism wiodących (typy 1, 3, 4); niespójność z historią obiegu. |
| **Sugerowane działanie** | Zweryfikować z domeną EZD; jeśli bug — rozgałęzić jak `historiaObiegu` (typ 2 → DocumentHistoryService, inne → CaseQuery/CaseHistoryService). |
| **Pliki** | `app/Source/V1/Services/Document/DocumentService.php`, `app/Source/V1/Queries/Document/DocumentQuery.php`, `app/Source/V1/Services/Case/HistoryService.php`, `app/Source/V1/Services/Document/HistoryService.php` |

---

### Q-13 — odwrócona logika `has_pozostali_interesanci`

| Pole | Wartość |
|------|---------|
| **Opis** | SQL: `has_pozostali_interesanci` = EXISTS gdy pole `interesanci` wypełnione → `true`. `SupliantService::hydrateSuppliantData` wywołuje `getAdditionalSuppliants` gdy `has_pozostali_interesanci === false`. |
| **Priorytet** | **Wysoki** |
| **Ryzyko** | Brak listy pozostałych interesantów gdy są w formularzu; niepotrzebne zapytania gdy ich nie ma. |
| **Sugerowane działanie** | Potwierdzić z autorem; prawdopodobna poprawka: warunek `=== true` (lub usunąć negację). |
| **Pliki** | `app/Source/V1/Services/Suppliant/SupliantService.php`, `app/Source/V1/Queries/Suppliant/SuppliantQuery.php`, `app/Source/V1/Queries/Case/CaseListQuery.php` (EXISTS), `app/Source/V1/Queries/Document/DocumentListQuery.php` (EXISTS) |

---

### Q-02 — `pokaz_udostepnione`: obecność klucza vs wartość bool

| Pole | Wartość |
|------|---------|
| **Opis** | `$filtry->pokazUdostepnione !== null` → `$includeShared = true`. Wysłanie `"pokaz_udostepnione": 0` włącza EXISTS w `galaxia_instance_users` tak samo jak `1`. |
| **Priorytet** | **Wysoki** |
| **Ryzyko** | Klienci wysyłający `false` oczekują wąskiego scope — dostają rozszerzony; odwrotnie: brak klucza ≠ „tylko właściciel z udostępnieniami wyłączonymi jawnie". |
| **Sugerowane działanie** | Ustalić kontrakt API z integratorami; jeśli bool — zmienić na `(bool) $filtry->pokazUdostepnione` zamiast `!== null`. |
| **Pliki** | `app/Source/V1/Queries/Case/CaseListQuery.php`, `app/Source/V1/Queries/Document/AbstractDocumentQuery.php`, `app/Source/V1/DTO/Request/TypFiltrDokument.php`, `app/Source/V1/DTO/Request/TypFiltrSpraw.php` |

---

### Q-01 — niespójny wybór „aktualnego" wiersza obiegu

| Pole | Wartość |
|------|---------|
| **Opis** | Różne wzorce: `max_status_sprawy_id > 0`, `status_sprawy_id > 0`, sort bez filtra max, brak ORDER BY w `getStatusSymbol`. |
| **Priorytet** | **Wysoki** |
| **Ryzyko** | Różne endpointy zwracają status/właściciela/instanceId z różnych wierszy obiegu; trudne do debugowania rozjazdy list vs szczegóły. |
| **Sugerowane działanie** | Konsultacja z ekspertem EZD; ujednolicić wzorzec w CaseQuery i ProcessQuery; udokumentować w `docs/queries/case-queries.md`. |
| **Pliki** | `app/Source/V1/Queries/Case/CaseListQuery.php`, `app/Source/V1/Queries/Case/CaseQuery.php`, `app/Source/V1/Queries/ProcessQuery.php`, `app/Source/V1/Queries/Document/DocumentListQuery.php` |

---

### Q-15 — `WorkstationQuery::getDepartament` — błędne wywołanie

| Pole | Wartość |
|------|---------|
| **Opis** | `getDepartament($workstationId)` wywołuje `getWorkstationsActive($workstationId)`, ale `getWorkstationsActive()` nie przyjmuje argumentów (PHP 8+ może rzucić `ArgumentCountError`). Metoda **nie jest używana** — używane jest `getDepartamentInfo`. |
| **Priorytet** | **Średni** (martwa, ale myląca) |
| **Ryzyko** | Przypadkowe użycie w przyszłości → runtime error; wprowadza w błąd czytających kod. |
| **Sugerowane działanie** | Usunąć `getDepartament` lub przekierować do `getDepartamentInfo`; grep przed usunięciem. |
| **Pliki** | `app/Source/V1/Queries/Structure/WorkstationQuery.php`, `app/Source/V1/Services/Form/FormDaneService.php` (używa `getDepartamentInfo`) |

---

### Q-03 — filtr `tresc_pisma` bez implementacji SQL

| Pole | Wartość |
|------|---------|
| **Opis** | `TypFiltrDokument::$trescPisma` parsowane z `filtry.tresc_pisma`, brak warunku w `AbstractDocumentQuery`. |
| **Priorytet** | **Średni** |
| **Ryzyko** | Klient wysyła filtr — API go ignoruje bez błędu; fałszywe poczucie filtrowania. |
| **Sugerowane działanie** | Zaimplementować (np. ILIKE na `fd_tresc_wniosku`) albo usunąć pole z DTO i udokumentować; ewentualnie zwracać 422 przy obecności klucza. |
| **Pliki** | `app/Source/V1/DTO/Request/TypFiltrDokument.php`, `app/Source/V1/Queries/Document/AbstractDocumentQuery.php`, `app/Source/V1/Queries/Document/DocumentListQuery.php` |

---

### Q-08 — `getProcessNames` — inny scope stanowisk

| Pole | Wartość |
|------|---------|
| **Opis** | Listy używają `buildWorkstationCondition` (właściciel ± EXISTS). `getProcessNames` JOIN `galaxia_instance_users` bez tej samej logiki. |
| **Priorytet** | **Średni** |
| **Ryzyko** | Słownik nazw procesów niezgodny z listą dokumentów dla tych samych filtrów; inne zachowanie przy `pokaz_udostepnione`. |
| **Sugerowane działanie** | Porównać wyniki na prod/stage; ujednolicić scope lub udokumentować różnicę jako zamierzoną. |
| **Pliki** | `app/Source/V1/Queries/Document/DocumentQuery.php`, `app/Source/V1/Queries/Document/AbstractDocumentQuery.php`, `app/Source/V1/Services/Document/DocumentService.php` |

---

### Q-07 — duplikat JOIN `eurzad_sprawa_przedluzanie`

| Pole | Wartość |
|------|---------|
| **Opis** | `pismoInnerJoinsSql`: aliasy `esp` i `sp` — ten sam JOIN dwukrotnie. |
| **Priorytet** | **Niski** |
| **Ryzyko** | Raczej wydajność/plan SQL niż błędne dane (ten sam klucz); możliwy duplikat wierszy gdy brak unikalności — **DO WYJAŚNIENIA**. |
| **Sugerowane działanie** | Usunąć redundantny JOIN; porównać EXPLAIN przed/po. |
| **Pliki** | `app/Source/V1/Queries/Document/DocumentListQuery.php` |

---

## 2. Niejasności biznesowe

### Q-06 — podwójny self-join `teczka_zawartosc` (typ UNION 4, zwroty)

| Pole | Wartość |
|------|---------|
| **Opis** | Łańcuch `etz2 → etz → et` dla `TypDokument::DokZpo`. Wniosek z SQL opisany w [queries/document-queries.md](queries/document-queries.md#potwierdzenia-odbioru--zwrotki-dokzpo); **brak potwierdzenia reguły biznesowej EZD** (czy `etz2.teczka_uid` zawsze wskazuje dokument nadrzędny). |
| **Priorytet** | **Średni** |
| **Ryzyko** | Błędne powiązanie zwrotu z teczką nadrzędną; trudna zmiana bez znajomości reguły EZD. |
| **Sugerowane działanie** | Zweryfikować na próbce danych z analitykiem EZD; ewentualnie doprecyzować sekcję w `document-queries.md`. |
| **Pliki** | `app/Source/V1/Queries/Document/DocumentListQuery.php` (`teczkaJoinsSql`, typ 4) |

---

### Q-11 — różnica biznesowa typów niewychodzących — **ZAMKNIĘTE (ponownie)**

| Pole | Wartość |
|------|---------|
| **Status** | Rozbito `DokPrzychodzacy` na 3 gałęzie UNION (`TypUnionDokumentu`): inicjujący / w sprawie / bez sprawy. `DokWewnetrzny` = `ef.form_typ = internal`. API: `TypPowiazaniaDokumentu`. |
| **Pliki** | `DocumentListQuery.php`, `AbstractDocumentQuery.php`, `TypDokument.php`, `TypPowiazaniaDokumentu.php`, `TypUnionDokumentu.php` |

---

### Q-10 — `getTeczkaSyg` bez filtra `dntas`

| Pole | Wartość |
|------|---------|
| **Opis** | Zakomentowany `->where('dntas', $dntas)`. Lookup tylko po `teczka_uid`. |
| **Priorytet** | **Niski** |
| **Ryzyko** | **ZAŁOŻENIE:** UID teczki globalnie unikalny — jeśli nie, kolizja Cases/DNTAS. |
| **Sugerowane działanie** | Sprawdzić unikalność `teczka_uid` w DB; przy kolizji przywrócić filtr `dntas`. |
| **Pliki** | `app/Source/V1/Queries/Case/CaseQuery.php`, miejsca wywołań (grep `getTeczkaSyg`) |

---

## 3. Niejasności techniczne

### Q-09 — `array_merge($bindings, $bindings)` w UNION

| Pole | Wartość |
|------|---------|
| **Opis** | Podwójny zestaw parametrów dla dwóch gałęzi UNION. Działa, brak komentarza autora. |
| **Priorytet** | **Niski** |
| **Ryzyko** | Przy edycji WHERE łatwo zepsuć liczbę bindów; regresja cicha (złe wyniki). |
| **Sugerowane działanie** | Dodać komentarz w kodzie (Faza 3) lub helper `duplicateBindingsForUnion(n)`; test manualny list procesów. |
| **Pliki** | `app/Source/V1/Queries/Form/FormQuery.php`, `app/Source/V1/Queries/Document/DocumentQuery.php` (`getProcessNames`) |

---

### Q-04 — `Document/QueryBuilder.php` — glob vs brak pliku

| Pole | Wartość |
|------|---------|
| **Opis** | Indeks repozytorium/IDE może wskazywać plik; odczyt z dysku: not found. Brak referencji w PHP. |
| **Priorytet** | **Niski** |
| **Ryzyko** | Mylący stan repo; agent/dev szuka nieistniejącej klasy. |
| **Sugerowane działanie** | `git status` / wyczyścić indeks; usunąć wpis z git jeśli phantom; potwierdzić brak referencji. |
| **Pliki** | `app/Source/V1/Queries/Document/QueryBuilder.php` (jeśli istnieje), `.git/index`, glob w IDE |

---

### Q-22 — cardinality relacji ER bez DDL

| Pole | Wartość |
|------|---------|
| **Opis** | Diagram w `docs/database.md` wnioskowany z JOIN-ów, nie z PK/FK. |
| **Priorytet** | **Niski** |
| **Ryzyko** | Błędne założenia przy modelowaniu nowych zapytań; nie wpływa na działające API. |
| **Sugerowane działanie** | Opcjonalnie: wyeksportować `\d` / FK z dumpa EZD do `docs/database.md`. |
| **Pliki** | `docs/database.md`, dump PostgreSQL (poza repo) |

---

### Q-23 — README vs migracje

| Pole | Wartość |
|------|---------|
| **Opis** | README może wspominać `php artisan migrate`; w repo jest wyłącznie migracja `api_cache` (nie zastępuje importu dumpa). |
| **Priorytet** | **Niski** |
| **Ryzyko** | Nowy developer uruchamia migrate na pustej bazie zamiast importu dumpa. |
| **Sugerowane działanie** | README: kolejność `import-db.sh` → `migrate` → opcjonalnie `setup-ezd-readonly-privileges.sh`. |
| **Pliki** | `README.md`, `scripts/import-db.sh`, `docs/database.md` |

---

### Q-21 — `FILES_URL` read-only zależne od mountu

| Pole | Wartość |
|------|---------|
| **Opis** | `:ro` tylko w `docker-compose.yml`; host bez flagi — brak gwarancji. |
| **Priorytet** | **Niski** (dokumentacja) |
| **Ryzyko** | Przypadkowy zapis plików poza Dockerem; nie dotyczy samego API PHP. |
| **Sugerowane działanie** | Utrzymać opis w docs; opcjonalnie readonly check w AttachmentService tylko do odczytu. |
| **Pliki** | `docker-compose.yml`, `app/Source/V1/Services/Attachment/AttachmentService.php`, `.env.example` |

---

## 4. Martwy kod / orphan code

### Q-05 — `ProcessQuery` bez konsumentów

| Pole | Wartość |
|------|---------|
| **Opis** | Klasa w `Queries/ProcessQuery.php`; brak importu w Services (grep). |
| **Priorytet** | **Niski** |
| **Ryzyko** | Duplikacja logiki z CaseQuery/DocumentQuery; martwy kod myli agentów. |
| **Sugerowane działanie** | Podpiąć do `ProcesService` jeśli planowany feature, albo usunąć; ADR jeśli zostaje „na później". |
| **Pliki** | `app/Source/V1/Queries/ProcessQuery.php`, `app/Source/V1/Services/Proces/ProcesService.php`, grep `ProcessQuery` |

---

### Q-17 — `SearchRequest` niepodpięty do tras

| Pole | Wartość |
|------|---------|
| **Opis** | `app/Http/Requests/Api/V1/SearchRequest.php` — brak użycia w `routes/api.php`. Max limit 100 vs `Paginacja` 200. |
| **Priorytet** | **Niski** |
| **Ryzyko** | Ktoś podłączy bez synchronizacji limitów; dwa kontrakty walidacji. |
| **Sugerowane działanie** | Usunąć albo podpiąć zastępując ręczne DTO; ujednolicić limity. |
| **Pliki** | `app/Http/Requests/Api/V1/SearchRequest.php`, `routes/api.php`, `app/Source/V1/DTO/Request/Paginacja.php` |

---

### Q-14 — `einstytucjaUserId` w DTO bez użycia

| Pole | Wartość |
|------|---------|
| **Opis** | Parsowane w `ApiKonfiguracja`, brak referencji w Services/Queries. |
| **Priorytet** | **Niski** |
| **Ryzyko** | Integratorzy wysyłają pole bez efektu; planowany feature niedokończony. |
| **Sugerowane działanie** | Wyjaśnić z product owner; zaimplementować scope lub usunąć z DTO/docs API. |
| **Pliki** | `app/Source/V1/DTO/Request/ApiKonfiguracja.php`, grep `einstytucjaUserId` |

---

## 5. Decyzje architektoniczne (ADR)

### Q-16 — migracja `documentId` → `instanceId`

| Pole | Wartość |
|------|---------|
| **Opis** | @TODO w `routes/api.php`: docelowo tylko numeric `instanceId`. Obecnie: `\d+` lub hex UID. |
| **Priorytet** | **Wysoki** (kontrakt API) |
| **Ryzyko** | Breaking change dla integratorów; równoległe semantyki `\d+` (instanceId vs rok w filtrze). |
| **Sugerowane działanie** | ADR: harmonogram migracji, wersjonowanie V2, okres przejściowy; zaktualizować `docs/api.md` (Faza 2). |
| **Pliki** | `routes/api.php`, `app/Source/V1/Queries/Document/AbstractDocumentQuery.php` (`oznaczenieCondition`), `app/Http/Controllers/Api/V1/DocumentsController.php` |

---

### Q-18 — brak autoryzacji API

| Pole | Wartość |
|------|---------|
| **Status** | **Rozwiązane** — shared secret `madkom-api-token` / `MADKOM_API_TOKEN` (wymagany) |
| **Opis** | Middleware `ApiTokenMiddleware` na `/api/v1/*`. Pusty env → **503** `configuration_error`. Błędny/brak nagłówka → **401**. Token z EZD (Migracje/Madkom/Konfiguracja). |
| **Priorytet** | — |
| **Pliki** | `app/Http/Middleware/ApiTokenMiddleware.php`, `bootstrap/app.php`, `config/app.php` |

---

### Q-20 — read-only jako konwencja vs gwarancja

| Pole | Wartość |
|------|---------|
| **Opis** | Brak INSERT/UPDATE w Queries; opcjonalna gwarancja przez `EzdDatabasePrivilegesGuard` + `ENFORCE_EZD_DB_READ_ONLY`. |
| **Priorytet** | **Niski** (częściowo rozwiązane) |
| **Ryzyko** | Przyszły kod może dodać zapis; integratorzy zakładają „tylko odczyt" bez formalnej gwarancji. |
| **Sugerowane działanie** | Prod: `scripts/setup-ezd-readonly-privileges.sh` + `ENFORCE_EZD_DB_READ_ONLY=true`; status: `GET /api/v1/system/db-privileges`. |
| **Pliki** | `app/Source/V1/Support/Database/EzdDatabasePrivilegesGuard.php`, `scripts/setup-ezd-readonly-privileges.sh`, `docs/database.md` |

---

### Q-19 — brak OpenAPI/Swagger

| Pole | Wartość |
|------|---------|
| **Opis** | Kontrakt API tylko w routes + DTO; brak maszynowo czytelnej specyfikacji. |
| **Priorytet** | **Średni** |
| **Ryzyko** | Rozjazd docs vs kod; wolniejszy onboarding integratorów i agentów AI. |
| **Sugerowane działanie** | ADR: OpenAPI vs ręczne `docs/api.md`; Faza 2 planu dokumentacji. |
| **Pliki** | `routes/api.php`, `docs/api.md` (do utworzenia) |

---

### Q-27 — kolumny tabel RPW (szczegóły) na dumpie EZD

| Pole | Wartość |
|------|---------|
| **Opis** | Szczegóły RPW (`RegistryAssignmentRpwQuery`): `eurzad_rejestr_obieg.rejestr_obieg_id`, `eurzad2_przesylka.nazwa`, `en_rpw` (`id`, `status`, `data_wyslania`) — brak DDL w repo; nazwy przyjęte z planu integracji EZD3. |
| **Priorytet** | **Średni** |
| **Ryzyko** | Błąd SQL przy braku kolumny na konkretnym dumpie; puste `historia_obiegu` / `przesylka_elektroniczna`. |
| **Sugerowane działanie** | Zweryfikować `\d eurzad_rejestr_obieg`, `eurzad2_przesylka`, `en_rpw` na dumpie; doprecyzować `docs/queries/registry-assignment-rpw-queries.md`. |
| **Pliki** | `app/Source/V1/Queries/Registry/RegistryAssignmentRpwQuery.php` |

---

## Indeks według priorytetu

| Priorytet | ID |
|-----------|-----|
| **Wysoki** | Q-01, Q-02, Q-12, Q-13, Q-16 |
| **Średni** | Q-03, Q-06, Q-08, Q-15, Q-20, Q-19, Q-27 |
| **Niski** | Q-04, Q-05, Q-07, Q-09, Q-10, Q-14, Q-17, Q-21, Q-22, Q-23 |

---

## TODO dokumentacji (poza ADR)

| ID | Temat | Faza |
|----|-------|------|
| Q-24 | `docs/api.md` | 2 |
| Q-25 | docs/queries dla Form, Structure, Attachment, Suppliant, Dictionary | 3 |
| Q-26 | PHPDoc w Queries | 3 |
