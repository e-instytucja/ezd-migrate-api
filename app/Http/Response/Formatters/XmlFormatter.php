<?php

declare(strict_types=1);

namespace App\Http\Response\Formatters;

use App\Http\Response\Dto\ApiResponse;
use DOMDocument;
use DOMElement;
use Illuminate\Http\Response;

final class XmlFormatter extends AbstractFormatter
{
    public function format(ApiResponse $response): Response
    {
        $dom              = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('response');
        $dom->appendChild($root);

        $this->arrayToXml($this->normalize($response), $root, $dom);

        $body = $dom->saveXML();

        return $this->buildResponse($body, $response->statusCode, $this->mimeType());
    }

    public function mimeType(): string
    {
        return 'application/xml';
    }

    private function arrayToXml(mixed $data, DOMElement $parent, DOMDocument $dom): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $tagName = is_int($key)
                    ? 'item'
                    : $this->sanitizeTagName((string) $key);

                $child = $dom->createElement($tagName);
                $parent->appendChild($child);

                $this->arrayToXml($value, $child, $dom);
            }

            return;
        }

        $scalar = match (true) {
            is_bool($data) => $data ? 'true' : 'false',
            is_null($data) => '',
            default        => (string) $data,
        };

        $parent->appendChild($dom->createTextNode($scalar));
    }

    /**
     * XML element names must not start with a digit and must contain
     * only letters, digits, hyphens, underscores, and periods.
     */
    private function sanitizeTagName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $name) ?? '_';

        return preg_match('/^[0-9]/', $name) ? 'key_' . $name : ($name ?: '_');
    }
}
