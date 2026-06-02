<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

readonly class ApiKonfiguracja
{
    /**
     * @param int[] $madkomWorkstationIds
     */
    public function __construct(
        public array $madkomWorkstationIds = [],
        public ?int $einstytucjaUserId = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            madkomWorkstationIds: self::parseWorkstationIds($data['madkomWorkstationIds'] ?? null),
            einstytucjaUserId: isset($data['einstytucjaUserId']) ? (int) $data['einstytucjaUserId'] : null,
        );
    }

    /**
     * @return int[]
     */
    private static function parseWorkstationIds(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $value))));
    }
}
