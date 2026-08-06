<?php

declare(strict_types=1);

namespace App\Source\V1\Support\MaterializedViews;

use InvalidArgumentException;

/**
 * Kwalifikacja nazw materialized views (schema + view) dla DDL i SELECT.
 */
final class MaterializedViewNaming
{
    private const IDENTIFIER_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public static function schema(): string
    {
        $schema = (string) config('app.materialized_views_schema', 'api_cache');
        self::assertIdentifier($schema, 'schema');

        return $schema;
    }

    public static function qualified(string $viewName): string
    {
        self::assertIdentifier($viewName, 'view');

        return self::schema() . '.' . $viewName;
    }

    private static function assertIdentifier(string $name, string $kind): void
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(
                "Invalid materialized view {$kind}: {$name}",
            );
        }
    }
}
