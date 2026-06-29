# Testy API (PHPUnit) — iteracja 1

Testy **integracyjne Feature** — wywołania HTTP na read model API. PostgreSQL z danymi EZD jak w dev (po imporcie). Testy **nie modyfikują** bazy.

## KATEGORYCZNY ZAKAZ INGERENCJI W STRUKTURĘ BAZY

Patrz [`.cursor/rules/api-tests.mdc`](../.cursor/rules/api-tests.mdc).

## Zakres iteracji 1

| Warstwa | Pliki |
|---------|--------|
| Infrastruktura | `TestCase.php`, `ApiTestCase.php`, `Concerns/AssertsApiEnvelope.php` |
| Fixture | `Fixtures/api.php`, `Fixtures/registry_assignments.php` |
| Smoke (25 tras) | `Cases/`, `Dntas/`, `Documents/`, `Attachments/`, `Workstations/`, `Registry/RegistrySmokeTest.php` |
| Deep (show registry) | `Registry/RegistryAssignmentShowTest.php` |

Deep kontekstowe/globalne registry — **iteracja 2**.

## Smoke

Głównie `assertNotServerError` (status ≠ 500). Envelope JSON tylko wyjątkowo (np. jeden test list w Cases).

## Uruchomienie

```bash
docker compose exec app composer install
docker compose exec app ./vendor/bin/phpunit tests/Feature/Api
```

(`php artisan test` wymaga osobnej konfiguracji — używaj `phpunit`).

## Raport HTML w przeglądarce

```bash
docker compose exec app composer test:report
```

Potem otwórz: **http://localhost:8080/test-reports/** (lub `/test-reports/index.html`).

(JUnit XML: `storage/test-reports/junit.xml`.)

Opcjonalnie pokrycie kodu (wolniejsze, wymaga Xdebug w trybie coverage):

```bash
docker compose exec -e XDEBUG_MODE=coverage app composer test:coverage
```

Potem: **http://localhost:8080/test-reports/coverage/**

## Fixture

Identyfikatory na sztywno (`registry_assignment_id`, `attachment_uid` itd.) — dopasuj do swojej bazy przy innej instancji EZD.
