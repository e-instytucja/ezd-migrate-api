<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response\ApiResponseRenderer;
use App\Http\Response\Dto\ApiResponse;
use App\Source\V1\Support\Database\EzdDatabasePrivilegesGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EzdDatabasePrivilegesMiddleware
{
    public function __construct(
        private readonly EzdDatabasePrivilegesGuard $privilegesGuard,
        private readonly ApiResponseRenderer $renderer,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('app.enforce_ezd_db_read_only', false)) {
            return $next($request);
        }

        if ($this->privilegesGuard->isCompliant()) {
            return $next($request);
        }

        $audit = $this->privilegesGuard->audit();

        return $this->renderer->render(
            $request,
            ApiResponse::error(
                'configuration_error',
                'User bazy danych ma uprawnienia zapisu do danych EZD lub CREATE na public. '
                . 'Oczekiwany model: SELECT na public, CREATE tylko na '
                . $audit['mv_schema']
                . '. Naruszenia: '
                . implode(', ', $audit['violations']),
                503,
            ),
        );
    }
}
