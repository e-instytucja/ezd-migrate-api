<?php

declare(strict_types=1);

namespace App\Http\Response;

use App\Http\Response\Dto\ApiResponse;
use App\Http\Response\Exceptions\UnsupportedFormatException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ApiResponseRenderer
{
    public const string DEFAULT_FORMAT = 'json';

    public const string QUERY_PARAM = 'format';

    public function __construct(private readonly FormatterFactory $factory) {}

    /**
     * Render an ApiResponse using the format resolved from the request.
     *
     * Falls back to JSON with 406 when the requested format is not supported.
     */
    public function render(Request $request, ApiResponse $response): Response
    {
        $format = $this->resolveFormat($request);

        try {
            return $this->factory->make($format)->format($response);
        } catch (UnsupportedFormatException $e) {
            return $this->factory
                ->make(self::DEFAULT_FORMAT)
                ->format(
                    ApiResponse::error(
                        errorCode: 'unsupported_format',
                        message: sprintf(
                            "Format '%s' is not supported. Supported formats: %s.",
                            $format,
                            implode(', ', $this->factory->supported()),
                        ),
                        statusCode: 406,
                    ),
                );
        }
    }

    private function resolveFormat(Request $request): string
    {
        $raw = $request->query(self::QUERY_PARAM, self::DEFAULT_FORMAT);

        return strtolower(trim((string) $raw));
    }
}
