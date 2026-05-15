<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface SourceAdapterInterface
{
    /**
     * Zwraca pojedynczy rekord lub null jeśli nie istnieje.
     */
    public function find(int|string $id): ?array;

    /**
     * Zwraca wyniki wyszukiwania w formacie:
     * ['data' => array[], 'total' => int]
     */
    public function search(array $params): array;
}
