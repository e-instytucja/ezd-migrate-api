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
    | Case list data source
    |--------------------------------------------------------------------------
    |
    | legacy = CaseListQuery (joins na eurzad_*)
    | mv     = CaseListQueryMV (materialized view api_case_list)
    |
    | Wymaga: php artisan cases:refresh-list-mv przed CASE_LIST_SOURCE=mv
    |
    */
    'case_list_source' => env('CASE_LIST_SOURCE', 'legacy'),

];
