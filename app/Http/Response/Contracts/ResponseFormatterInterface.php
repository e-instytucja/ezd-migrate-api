<?php

declare(strict_types=1);

namespace App\Http\Response\Contracts;

use App\Http\Response\Dto\ApiResponse;
use Illuminate\Http\Response;

interface ResponseFormatterInterface
{
    public function format(ApiResponse $response): Response;

    public function mimeType(): string;
}
