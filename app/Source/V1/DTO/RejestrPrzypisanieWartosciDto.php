<?php

declare(strict_types=1);

namespace App\Source\V1\DTO;

use JsonSerializable;

final readonly class RejestrPrzypisanieWartosciDto implements JsonSerializable
{
    public function __construct(
        public int $registryAssignmentId,
        public string $registryAssignmentUid,
        public string $documentId,
        public ?string $registryAssignmentNumber,
        public ?string $registryAssignmentType,
        public string $registryUid,
        public ?string $registryType,
        public ?string $registryDescription,
        public ?string $createdAt,
        public ?string $leadCaseUid,
        public ?string $processName,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            registryAssignmentId: (int) $row['registry_assignment_id'],
            registryAssignmentUid: (string) $row['registry_assignment_uid'],
            documentId: (string) $row['document_id'],
            registryAssignmentNumber: $row['registry_assignment_number'] ?? null,
            registryAssignmentType: $row['registry_assignment_type'] ?? null,
            registryUid: (string) $row['registry_uid'],
            registryType: $row['registry_type'] ?? null,
            registryDescription: $row['registry_description'] ?? null,
            createdAt: $row['created_at'] ?? null,
            leadCaseUid: $row['lead_case_uid'] ?? null,
            processName: $row['process_name'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'registry_assignment_id' => $this->registryAssignmentId,
            'registry_assignment_uid' => $this->registryAssignmentUid,
            'document_id' => $this->documentId,
            'registry_assignment_number' => $this->registryAssignmentNumber,
            'registry_assignment_type' => $this->registryAssignmentType,
            'registry_uid' => $this->registryUid,
            'registry_type' => $this->registryType,
            'registry_description' => $this->registryDescription,
            'created_at' => $this->createdAt,
            'lead_case_uid' => $this->leadCaseUid,
            'process_name' => $this->processName,
        ];
    }
}
