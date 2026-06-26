<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

readonly class KryteriaPrzypisanRejestrow
{
    /**
     * @param string[] $registryTypes
     * @param string[] $documentIds
     */
    public function __construct(
        public ApiKonfiguracja $konfiguracja,
        public Paginacja $paginacja,
        public bool $isGlobal,
        public ?string $documentId = null,
        public ?string $caseUid = null,
        public ?string $registryUid = null,
        public array $registryTypes = [],
        public bool $withCopies = true,
        public array $documentIds = [],
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public ?int $year = null,
        public ?string $numberFrom = null,
        public ?string $numberTo = null,
    ) {
    }

    public static function fromGlobalPayload(array $payload): self
    {
        $filtry = is_array($payload['filtry'] ?? null) ? $payload['filtry'] : [];

        return new self(
            konfiguracja: ApiKonfiguracja::fromArray($payload['konfiguracja'] ?? []),
            paginacja: Paginacja::fromPayload($payload),
            isGlobal: true,
            documentId: self::nullableString($filtry['documentId'] ?? $filtry['document_id'] ?? null),
            caseUid: self::nullableString($filtry['caseUid'] ?? $filtry['case_uid'] ?? null),
            registryUid: self::nullableString($filtry['registryUid'] ?? $filtry['registry_uid'] ?? null),
            registryTypes: self::parseStringList($filtry['registryTypes'] ?? $filtry['registry_types'] ?? []),
            withCopies: self::parseBool($filtry['withCopies'] ?? $filtry['with_copies'] ?? true, true),
            createdFrom: self::nullableString($filtry['createdFrom'] ?? $filtry['created_from'] ?? null),
            createdTo: self::nullableString($filtry['createdTo'] ?? $filtry['created_to'] ?? null),
            year: self::nullableInt($filtry['year'] ?? $filtry['rok'] ?? null),
            numberFrom: self::nullableString($filtry['numberFrom'] ?? $filtry['number_from'] ?? null),
            numberTo: self::nullableString($filtry['numberTo'] ?? $filtry['number_to'] ?? null),
        );
    }

    public static function fromPayload(array $payload): self
    {
        $filtry = is_array($payload['filtry'] ?? null) ? $payload['filtry'] : [];

        return new self(
            konfiguracja: new ApiKonfiguracja(),
            paginacja: new Paginacja(page: 1, limit: 10000, offset: 0),
            isGlobal: false,
            documentId: self::nullableString($filtry['documentId'] ?? $filtry['document_id'] ?? null),
            caseUid: self::nullableString($filtry['caseUid'] ?? $filtry['case_uid'] ?? null),
            registryUid: self::nullableString($filtry['registryUid'] ?? $filtry['registry_uid'] ?? null),
            registryTypes: self::parseStringList($filtry['registryTypes'] ?? $filtry['registry_types'] ?? []),
            withCopies: self::parseBool($filtry['withCopies'] ?? $filtry['with_copies'] ?? true, true),
            createdFrom: self::nullableString($filtry['createdFrom'] ?? $filtry['created_from'] ?? null),
            createdTo: self::nullableString($filtry['createdTo'] ?? $filtry['created_to'] ?? null),
            year: self::nullableInt($filtry['year'] ?? $filtry['rok'] ?? null),
            numberFrom: self::nullableString($filtry['numberFrom'] ?? $filtry['number_from'] ?? null),
            numberTo: self::nullableString($filtry['numberTo'] ?? $filtry['number_to'] ?? null),
        );
    }

    public static function forDocumentId(string $documentId, array $payload = []): self
    {
        $kryteria = self::fromPayload($payload);

        return new self(
            konfiguracja: $kryteria->konfiguracja,
            paginacja: $kryteria->paginacja,
            isGlobal: false,
            documentId: $documentId,
            caseUid: $kryteria->caseUid,
            registryUid: $kryteria->registryUid,
            registryTypes: $kryteria->registryTypes,
            withCopies: $kryteria->withCopies,
            documentIds: $kryteria->documentIds,
            createdFrom: $kryteria->createdFrom,
            createdTo: $kryteria->createdTo,
            year: $kryteria->year,
            numberFrom: $kryteria->numberFrom,
            numberTo: $kryteria->numberTo,
        );
    }

    public static function forCaseUid(string $caseUid, array $payload = []): self
    {
        $kryteria = self::fromPayload($payload);

        return new self(
            konfiguracja: $kryteria->konfiguracja,
            paginacja: $kryteria->paginacja,
            isGlobal: false,
            documentId: $kryteria->documentId,
            caseUid: $caseUid,
            registryUid: $kryteria->registryUid,
            registryTypes: $kryteria->registryTypes,
            withCopies: $kryteria->withCopies,
            documentIds: $kryteria->documentIds,
            createdFrom: $kryteria->createdFrom,
            createdTo: $kryteria->createdTo,
            year: $kryteria->year,
            numberFrom: $kryteria->numberFrom,
            numberTo: $kryteria->numberTo,
        );
    }

    public function withDocumentIds(array $documentIds): self
    {
        return new self(
            konfiguracja: $this->konfiguracja,
            paginacja: $this->paginacja,
            isGlobal: $this->isGlobal,
            documentId: $this->documentId,
            caseUid: $this->caseUid,
            registryUid: $this->registryUid,
            registryTypes: $this->registryTypes,
            withCopies: $this->withCopies,
            documentIds: $documentIds,
            createdFrom: $this->createdFrom,
            createdTo: $this->createdTo,
            year: $this->year,
            numberFrom: $this->numberFrom,
            numberTo: $this->numberTo,
        );
    }

    /**
     * @return string[]
     */
    private static function parseStringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $value,
        )));
    }

    private static function parseBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'tak' => true,
            '0', 'false', 'no', 'n', 'nie' => false,
            default => $default,
        };
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
