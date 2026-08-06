<?php

return [

    'name' => env('APP_NAME', 'SidasEzdApi'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'timezone' => env('APP_TIMEZONE', 'Europe/Warsaw'),

    'locale' => env('APP_LOCALE', 'pl'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'pl_PL'),

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    'maintenance' => ['driver' => 'file'],

    'log_sql_queries' => (bool) env('LOG_SQL_QUERIES', false),
    'log_sql_slow_ms' => (float) env('LOG_SQL_SLOW_MS', 100),
    'log_sql_queries_detail' => (bool) env('LOG_SQL_QUERIES_DETAIL', false),

    /*
    |--------------------------------------------------------------------------
    | Materialized views (listy API)
    |--------------------------------------------------------------------------
    |
    | false = live SQL (CaseListQuery, DocumentListQuery, …)
    | true  = materialized views (CaseListQueryMV, DocumentListQueryMV, …)
    |
    | Wymaga: php artisan materialized-views:refresh przed USE_MATERIALIZED_VIEWS=true
    |
    */
    'use_materialized_views' => (bool) env('USE_MATERIALIZED_VIEWS', false),

    /*
    |--------------------------------------------------------------------------
    | Materialized views schema (PostgreSQL)
    |--------------------------------------------------------------------------
    |
    | Schemat dla api_case_list / api_document_list (DDL tylko artisan).
    | Wymaga: php artisan migrate (CREATE SCHEMA) przed materialized-views:refresh.
    |
    */
    'materialized_views_schema' => env('DB_MV_SCHEMA', 'api_cache'),

    /*
    |--------------------------------------------------------------------------
    | EZD database read-only enforcement
    |--------------------------------------------------------------------------
    |
    | true = HTTP 503 gdy DB_USERNAME ma zapis do danych EZD lub CREATE na public.
    | Weryfikacja: GET /api/v1/system/db-privileges
    | Setup prod: scripts/setup-ezd-readonly-privileges.sh (po migrate)
    |
    */
    'enforce_ezd_db_read_only' => (bool) env('ENFORCE_EZD_DB_READ_ONLY', false),

    'ezd_privileges_probe_table' => env('EZD_PRIVILEGES_PROBE_TABLE', 'public.eurzad_teczka'),

    /*
    |--------------------------------------------------------------------------
    | Madkom API token (shared secret z EZD)
    |--------------------------------------------------------------------------
    |
    | Wymagany. Pusty = HTTP 503 configuration_error na /api/v1/*.
    | Nagłówek HTTP: madkom-api-token
    |
    */
    'madkom_api_token' => (string) env('MADKOM_API_TOKEN', ''),

];
