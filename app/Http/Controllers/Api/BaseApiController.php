<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Response\ApiResponseRenderer;
use App\Http\Response\Dto\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

abstract class BaseApiController extends Controller
{
    public function __construct(
        protected readonly ApiResponseRenderer $renderer,
    ) {}

    // -------------------------------------------------------------------------
    // Success helpers
    // -------------------------------------------------------------------------

    /**
     * Render a successful response.
     * If $data is null the response body will contain `"data": null`.
     */
    protected function renderResponse(
        Request $request,
        mixed $data,
        int $statusCode = 200,
        array $meta = [],
        ?string $message = null,
    ): Response {
        return $this->renderer->render(
            $request,
            ApiResponse::success($data, $statusCode, $meta, $message),
        );
    }

    /**
     * Render a 201 Created response with an optional Location header.
     */
    protected function renderCreated(
        Request $request,
        mixed $data = null,
        ?string $location = null,
        array $meta = [],
    ): Response {
        $response = $this->renderer->render(
            $request,
            ApiResponse::success($data, 201, $meta),
        );

        if ($location !== null) {
            $response->headers->set('Location', $location);
        }

        return $response;
    }

    /**
     * Render a 204 No Content response.
     * Note: most formatters will still return their envelope with `data: null`.
     */
    protected function renderEmpty(Request $request): Response
    {
        return $this->renderer->render($request, ApiResponse::empty());
    }

    // -------------------------------------------------------------------------
    // Error helpers
    // -------------------------------------------------------------------------

    protected function renderError(
        Request $request,
        string $errorCode,
        string $message,
        int $statusCode = 400,
        mixed $data = null,
    ): Response {
        return $this->renderer->render(
            $request,
            ApiResponse::error($errorCode, $message, $statusCode, $data),
        );
    }

    protected function renderNotFound(
        Request $request,
        string $message = 'Resource not found.',
    ): Response {
        return $this->renderError($request, 'not_found', $message, 404);
    }

    protected function renderUnauthorized(
        Request $request,
        string $message = 'Unauthorized.',
    ): Response {
        return $this->renderError($request, 'unauthorized', $message, 401);
    }

    protected function renderForbidden(
        Request $request,
        string $message = 'Forbidden.',
    ): Response {
        return $this->renderError($request, 'forbidden', $message, 403);
    }

    protected function renderUnprocessable(
        Request $request,
        string $message = 'Unprocessable entity.',
        mixed $data = null,
    ): Response {
        return $this->renderError($request, 'unprocessable_entity', $message, 422, $data);
    }

    protected function renderServerError(
        Request $request,
        string $message = 'Internal server error.',
    ): Response {
        return $this->renderError($request, 'server_error', $message, 500);
    }

}
