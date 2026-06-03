<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use App\Http\Response\ApiResponseRenderer;
use App\Http\Response\Dto\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    public function __construct(
        private readonly ApiResponseRenderer $renderer,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->is('api/*');
    }

    public function render(Request $request, Throwable $e): Response
    {
        if ($e instanceof NotFoundHttpException) {
            return $this->renderError(
                $request,
                'not_found',
                $e->getMessage() !== '' ? $e->getMessage() : 'The requested resource was not found.',
                404,
            );
        }

        report($e);

        if ($e instanceof HttpException) {
            return $this->renderError(
                $request,
                'http_error',
                $e->getMessage() !== '' ? $e->getMessage() : 'HTTP error.',
                $e->getStatusCode(),
            );
        }

        if ($e instanceof RuntimeException) {
            return $this->renderError($request, 'not_found', $e->getMessage(), 404);
        }

        if ($e instanceof Exception) {
            return $this->renderError($request, 'request_failed', $e->getMessage(), 422);
        }

        return $this->renderError($request, 'server_error', 'Internal server error.', 500);
    }

    private function renderError(
        Request $request,
        string $errorCode,
        string $message,
        int $statusCode,
    ): Response {
        return $this->renderer->render(
            $request,
            ApiResponse::error($errorCode, $message, $statusCode),
        );
    }
}
