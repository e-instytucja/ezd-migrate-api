-- Uprawnienia read-only EZD + schemat api_cache dla roli aplikacji.
-- Zmienne psql (wymagane): app_user, mv_schema
-- Uruchamiaj jako superuser / właściciel bazy (nie jako app_user).

\set ON_ERROR_STOP on

\if :{?app_user}
\else
\echo 'Brak zmiennej psql: app_user' >&2
\quit 1
\endif

\if :{?mv_schema}
\else
\echo 'Brak zmiennej psql: mv_schema' >&2
\quit 1
\endif

\echo '=== setup-ezd-readonly-privileges ==='
\echo 'app_user:' :'app_user'
\echo 'mv_schema:' :'mv_schema'

DROP MATERIALIZED VIEW IF EXISTS public.api_case_list CASCADE;
DROP MATERIALIZED VIEW IF EXISTS public.api_document_list CASCADE;

DO $setup$
DECLARE
    v_app_user text := :'app_user';
    v_mv_schema text := :'mv_schema';
    r record;
BEGIN
    EXECUTE format('CREATE SCHEMA IF NOT EXISTS %I AUTHORIZATION %I', v_mv_schema, v_app_user);
    EXECUTE format('GRANT USAGE, CREATE ON SCHEMA %I TO %I', v_mv_schema, v_app_user);

    EXECUTE format('REVOKE CREATE ON SCHEMA public FROM %I', v_app_user);
    EXECUTE format('GRANT USAGE ON SCHEMA public TO %I', v_app_user);
    EXECUTE format('GRANT SELECT ON ALL TABLES IN SCHEMA public TO %I', v_app_user);
    EXECUTE format('GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO %I', v_app_user);

    FOR r IN
        SELECT tablename
        FROM pg_tables
        WHERE schemaname = 'public'
          AND tablename ~ '^(eurzad_|galaxia_|users_|front_office_)'
    LOOP
        EXECUTE format(
            'REVOKE INSERT, UPDATE, DELETE, TRUNCATE ON TABLE public.%I FROM %I',
            r.tablename,
            v_app_user
        );
    END LOOP;
END
$setup$;

\echo '=== weryfikacja (oczekiwane: f / f / f / f / f / t) ==='
SELECT
    has_table_privilege(:'app_user', 'public.eurzad_teczka', 'INSERT') AS ezd_insert,
    has_table_privilege(:'app_user', 'public.eurzad_teczka', 'UPDATE') AS ezd_update,
    has_schema_privilege(:'app_user', 'public', 'CREATE') AS public_create,
    has_schema_privilege(:'app_user', :'mv_schema', 'CREATE') AS api_cache_create;

\echo '=== koniec ==='
