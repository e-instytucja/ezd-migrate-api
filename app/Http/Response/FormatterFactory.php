<?php

declare(strict_types=1);

namespace App\Http\Response;

use App\Http\Response\Contracts\ResponseFormatterInterface;
use App\Http\Response\Exceptions\UnsupportedFormatException;
use App\Http\Response\Formatters\HtmlFormatter;
use App\Http\Response\Formatters\JsonFormatter;
use App\Http\Response\Formatters\XmlFormatter;

final class FormatterFactory
{
    /** @var array<string, class-string<ResponseFormatterInterface>> */
    private array $formatters = [
        'json' => JsonFormatter::class,
        'xml'  => XmlFormatter::class,
        'html' => HtmlFormatter::class,
    ];

    /**
     * Resolve and instantiate a formatter for the given format string.
     *
     * @throws UnsupportedFormatException
     */
    public function make(string $format): ResponseFormatterInterface
    {
        $format = strtolower(trim($format));

        if (!isset($this->formatters[$format])) {
            throw new UnsupportedFormatException($format, $this->supported());
        }

        return new ($this->formatters[$format])();
    }

    /**
     * Register a custom formatter at runtime (e.g. from a ServiceProvider).
     *
     * @param class-string<ResponseFormatterInterface> $formatterClass
     */
    public function register(string $format, string $formatterClass): void
    {
        $this->formatters[strtolower(trim($format))] = $formatterClass;
    }

    public function supports(string $format): bool
    {
        return isset($this->formatters[strtolower(trim($format))]);
    }

    /** @return list<string> */
    public function supported(): array
    {
        return array_keys($this->formatters);
    }
}
