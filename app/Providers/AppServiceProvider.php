<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\QueryTimingCollector;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register as singletons so the formatter registry is shared across
        // the entire request lifecycle and custom formatters registered in one
        // place (e.g. another ServiceProvider) are visible everywhere.
        $this->app->singleton(\App\Http\Response\FormatterFactory::class);
        $this->app->singleton(\App\Http\Response\ApiResponseRenderer::class);
        $this->app->singleton(QueryTimingCollector::class);
    }

    public function boot(): void
    {
        if (!config('app.log_sql_queries')) {
            return;
        }

        DB::listen(function (QueryExecuted $query): void {
            $collector = app(QueryTimingCollector::class);
            $collector->add($query->sql, $query->bindings, $query->time);

            $slowMs = (float) config('app.log_sql_slow_ms', 100);
            if ($query->time >= $slowMs) {
                Log::notice('SQL.slow', [
                    'time_ms' => round($query->time, 2),
                    'sql' => QueryTimingCollector::normalizeSql($query->sql),
                    'bindings' => $query->bindings,
                ]);
            }
        });
    }
}
