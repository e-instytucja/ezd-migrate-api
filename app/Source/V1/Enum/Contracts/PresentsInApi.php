<?php

declare(strict_types=1);

namespace App\Source\V1\Enum\Contracts;

interface PresentsInApi
{
    public function label(): string;

    /**
     * Wartość pola encji w show (np. danePodstawowe.values).
     *
     * @return array{name: string, label: string}
     */
    public function toApi(): array;

    /**
     * Opcja słownika / filtra (np. GET /documents/types) — kształt legacy dla Yii.
     *
     * @return array{id: string, label: string}
     */
    public function toFilterOption(): array;
}
