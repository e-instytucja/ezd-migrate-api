<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Functions;
use App\Shared\QueryTimingCollector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiAccessLogMiddleware
{
    private const LOG_KEY = 'API_ACCESS';

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = Functions::startTimer();
        $response = $next($request);

        $route = $request->route();
        $endpoint = $route === null
            ? $request->path()
            : sprintf('%s [%s]', $route->getActionMethod(), $route->uri());

        $routeParams = $route?->parameters() ?? [];
        $payload = $request->all();

        $logContext = [
            'log_key' => self::LOG_KEY,
            'endpoint' => $endpoint,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'route_params' => $routeParams,
            'payload' => $payload,
            'status' => $response->getStatusCode(),
        ];

        if (config('app.log_sql_queries')) {
            $requestMs = (microtime(true) - $startedAt) * 1000;
            $summary = app(QueryTimingCollector::class)->summary(
                (bool) config('app.log_sql_queries_detail'),
            );

            $logContext['query_count'] = $summary['query_count'];
            $logContext['db_total_ms'] = $summary['db_total_ms'];
            $logContext['php_overhead_ms'] = round(max(0, $requestMs - $summary['db_total_ms']), 2);

            if (isset($summary['queries'])) {
                $logContext['queries'] = $summary['queries'];
            }
        }

        Log::info('[' . Functions::elapsedMs($startedAt) . '] ' . self::LOG_KEY, $logContext);

        return $response;
    }
}
