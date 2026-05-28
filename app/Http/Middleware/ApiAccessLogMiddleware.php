<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Functions;
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
        $durationMs = Functions::elapsedMs($startedAt);

        $route = $request->route();
        $endpoint = $route === null
            ? $request->path()
            : sprintf('%s [%s]', $route->getActionMethod(), $route->uri());

        $routeParams = $route?->parameters() ?? [];
        foreach ($routeParams as $key => $value) {
            if (in_array($key, ['token', 'uid', 'id'], true) && is_string($value) && strlen($value) > 8) {
                $routeParams[$key] = substr($value, 0, 8) . '***';
            }
        }

        $query = $request->query();
        if (count($query) > 5) {
            $query = array_slice($query, 0, 5) + ['_truncated' => true];
        }

        Log::info('[' . $durationMs . 'ms] ' . self::LOG_KEY, [
            'log_key' => self::LOG_KEY,
            'endpoint' => $endpoint,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'route_params' => $routeParams,
            'query' => $query,
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
