<?php

declare(strict_types=1);

namespace App\Source\V1\DTO\Request;

readonly class KryteriaPrzypisanRejestrowRpw
{
    /**
     * @param string[] $registryTypes
     */
    public function __construct(
        public ApiKonfiguracja $konfiguracja,
        public Paginacja $paginacja,
        public bool $isGlobal,
        public ?string $pismoUid = null,
        public ?string $documentId = null,
        public ?string $caseUid = null,
        public ?string $registryUid = null,
        public array $registryTypes = [],
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
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
            createdFrom: self::nullableString($filtry['createdFrom'] ?? $filtry['created_from'] ?? null),
            createdTo: self::nullableString($filtry['createdTo'] ?? $filtry['created_to'] ?? null),
        );
    }

    public static function fromPayload(array $payload): self
    {
        $filtry = is_array($payload['filtry'] ?? null) ? $payload['filtry'] : [];

        return new self(
            konfiguracja: new ApiKonfiguracja(),
            paginacja: new Paginacja(page: 1, limit: 10000, offset: 0),
            isGlobal: false,
            registryUid: self::nullableString($filtry['registryUid'] ?? $filtry['registry_uid'] ?? null),
            registryTypes: self::parseStringList($filtry['registryTypes'] ?? $filtry['registry_types'] ?? []),
            createdFrom: self::nullableString($filtry['createdFrom'] ?? $filtry['created_from'] ?? null),
            createdTo: self::nullableString($filtry['createdTo'] ?? $filtry['created_to'] ?? null),
        );
    }

    public static function forPismoUid(string $pismoUid, array $payload = []): self
    {
        $kryteria = self::fromPayload($payload);

        return new self(
            konfiguracja: $kryteria->konfiguracja,
            paginacja: $kryteria->paginacja,
            isGlobal: false,
            pismoUid: $pismoUid,
            registryUid: $kryteria->registryUid,
            registryTypes: $kryteria->registryTypes,
            createdFrom: $kryteria->createdFrom,
            createdTo: $kryteria->createdTo,
        );
    }

    public function withPismoUid(?string $pismoUid): self
    {
        return new self(
            konfiguracja: $this->konfiguracja,
            paginacja: $this->paginacja,
            isGlobal: $this->isGlobal,
            pismoUid: $pismoUid,
            documentId: $this->documentId,
            caseUid: $this->caseUid,
            registryUid: $this->registryUid,
            registryTypes: $this->registryTypes,
            createdFrom: $this->createdFrom,
            createdTo: $this->createdTo,
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

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
