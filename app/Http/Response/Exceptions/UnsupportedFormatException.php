<?php

declare(strict_types=1);

namespace App\Http\Response\Exceptions;

use RuntimeException;

final class UnsupportedFormatException extends RuntimeException
{
    public function __construct(string $format, array $supported = [])
    {
        $hint = empty($supported)
            ? ''
            : sprintf(' Supported formats: %s.', implode(', ', $supported));

        parent::__construct(
            "Unsupported response format: '{$format}'.{$hint}",
            406,
        );
    }
}
