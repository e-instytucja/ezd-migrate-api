<?php

declare(strict_types=1);

namespace App\Shared;

final class QueryTimingCollector
{
    private float $totalMs = 0;

    private int $count = 0;

    /** @var list<array{sql: string, bindings: array<int|string, mixed>, time_ms: float}> */
    private array $queries = [];

    public function add(string $sql, array $bindings, float $timeMs): void
    {
        $this->totalMs += $timeMs;
        $this->count++;
        $this->queries[] = [
            'sql' => self::normalizeSql($sql),
            'bindings' => $bindings,
            'time_ms' => round($timeMs, 2),
        ];
    }

    /**
     * @return array{
     *     query_count: int,
     *     db_total_ms: float,
     *     queries?: list<array{sql: string, bindings: array<int|string, mixed>, time_ms: float}>
     * }
     */
    public function summary(bool $includeQueries = false): array
    {
        $summary = [
            'query_count' => $this->count,
            'db_total_ms' => round($this->totalMs, 2),
        ];

        if ($includeQueries) {
            $summary['queries'] = $this->queries;
        }

        return $summary;
    }

    public function reset(): void
    {
        $this->totalMs = 0;
        $this->count = 0;
        $this->queries = [];
    }

    public static function normalizeSql(string $sql): string
    {
        return preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
    }
}
