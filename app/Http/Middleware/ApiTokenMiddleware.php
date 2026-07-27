<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Response\ApiResponseRenderer;
use App\Http\Response\Dto\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiTokenMiddleware
{
    public const HEADER = 'madkom-api-token';

    public function __construct(
        private readonly ApiResponseRenderer $renderer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('app.madkom_api_token', '');

        if ($expected === '') {
            return $this->renderer->render(
                $request,
                ApiResponse::error(
                    'configuration_error',
                    'Brak konfiguracji dostępu do API. Ustaw zmienną środowiskową MADKOM_API_TOKEN i zrestartuj usługę aplikacji Madkom API.',
                    503,
                ),
            );
        }

        $provided = (string) $request->header(self::HEADER, '');

        if ($provided === '' || !hash_equals($expected, $provided)) {
            return $this->renderer->render(
                $request,
                ApiResponse::error(
                    'unauthorized',
                    'Brak autoryzacji. Podaj prawidłowy token w nagłówku madkom-api-token.',
                    401,
                ),
            );
        }

        return $next($request);
    }
}
