<?php

use App\Http\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\ApiAccessLogMiddleware;
use App\Http\Middleware\ApiTokenMiddleware;
use App\Http\Middleware\EzdDatabasePrivilegesMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(append: [
            ApiTokenMiddleware::class,
            EzdDatabasePrivilegesMiddleware::class,
            ApiAccessLogMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            $handler = app(ApiExceptionRenderer::class);

            if (!$handler->supports($request)) {
                return null;
            }

            return $handler->render($request, $e);
        });
    })
    ->create();
