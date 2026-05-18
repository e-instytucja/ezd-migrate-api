<?php

declare(strict_types=1);

namespace App\Http\Response\Formatters;

use App\Http\Response\Dto\ApiResponse;
use Illuminate\Http\Response;

final class JsonFormatter extends AbstractFormatter
{
    public function format(ApiResponse $response): Response
    {
        $body = json_encode(
            $this->normalize($response),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return $this->buildResponse($body, $response->statusCode, $this->mimeType());
    }

    public function mimeType(): string
    {
        return 'application/json';
    }
}
