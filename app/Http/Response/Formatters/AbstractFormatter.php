<?php

declare(strict_types=1);

namespace App\Http\Response\Formatters;

use App\Http\Response\Contracts\ResponseFormatterInterface;
use App\Http\Response\Dto\ApiResponse;
use Illuminate\Http\Response;

abstract class AbstractFormatter implements ResponseFormatterInterface
{
    /**
     * Normalises ApiResponse into a plain associative array
     * that every formatter can consume.
     */
    protected function normalize(ApiResponse $response): array
    {
        $payload = [
            'success'     => $response->success,
            'status_code' => $response->statusCode,
        ];

        if ($response->message !== null) {
            $payload['message'] = $response->message;
        }

        if (!$response->success && $response->errorCode !== null) {
            $payload['error'] = $response->errorCode;
        }

        if (!empty($response->meta)) {
            $payload['meta'] = $response->meta;
        }

        // Recursively cast objects/enums to plain arrays/scalars so every
        // formatter receives a uniform structure without type surprises.
        $payload['data'] = $response->data !== null
            ? json_decode(json_encode($response->data, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR)
            : null;

        return $payload;
    }

    protected function buildResponse(string $body, int $statusCode, string $contentType): Response
    {
        return new Response($body, $statusCode, [
            'Content-Type' => $contentType . '; charset=UTF-8',
        ]);
    }
}
