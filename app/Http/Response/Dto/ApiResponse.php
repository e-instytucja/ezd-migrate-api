<?php

declare(strict_types=1);

namespace App\Http\Response\Dto;

final class ApiResponse
{
    public function __construct(
        public readonly mixed $data,
        public readonly int $statusCode = 200,
        public readonly ?string $message = null,
        public readonly array $meta = [],
        public readonly bool $success = true,
        public readonly ?string $errorCode = null,
    ) {}

    public static function success(
        mixed $data,
        int $statusCode = 200,
        array $meta = [],
        ?string $message = null,
    ): self {
        return new self(
            data: $data,
            statusCode: $statusCode,
            message: $message,
            meta: $meta,
            success: true,
        );
    }

    public static function error(
        string $errorCode,
        string $message,
        int $statusCode = 400,
        mixed $data = null,
    ): self {
        return new self(
            data: $data,
            statusCode: $statusCode,
            message: $message,
            meta: [],
            success: false,
            errorCode: $errorCode,
        );
    }

    public static function empty(int $statusCode = 204): self
    {
        return new self(data: null, statusCode: $statusCode, success: true);
    }
}
