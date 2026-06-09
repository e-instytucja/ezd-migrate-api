<?php

declare(strict_types=1);

namespace App\Source\V1\Services\Suppliant;

use App\Shared\Functions;
use App\Source\V1\Queries\Suppliant\SuppliantQuery;

class SupliantService
{
    public function __construct(
        private readonly SuppliantQuery $suppliantQuery,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function hydrateSuppliantData(array &$row, $documentUid): void
    {
        if (isset($row['interesant'])) {
            $row['interesant'] = Functions::normalizeText($row['interesant']);
            $row['interesant_adres'] = Functions::normalizeText($row['interesant_adres']);
            $row['interesant_type'] = ($row['interesant_type'] ?? null) === 'firma'
                ? 'instytucja'
                : 'osoba';
            $row['interesant_meta'] = [
                'interesant_type' => $row['interesant_type'],
            ];
        }

        $row['pozostali_interesanci'] = [];
        $row['pozostali_interesanci_tooltip_count'] = 0;
        $row['pozostali_interesanci_tooltip'] = '';

        if ($row['has_pozostali_interesanci'] === false) {
            $row['pozostali_interesanci'] = $this->getAdditionalSuppliants($documentUid);
            $row['pozostali_interesanci_tooltip_count'] = count($row['pozostali_interesanci']);
            $row['pozostali_interesanci_tooltip'] = implode(', ', array_column(
                $row['pozostali_interesanci'],
                'interesant',
            ));
        }
    }

    public function getAdditionalSuppliants($mainDocumentUid): array
    {
        $suppliants = $this->suppliantQuery->getAdditionalSuppliants($mainDocumentUid);

        return $this->normalizeSuppliants($suppliants);
    }

    public function getSupliantById($suppliantUid)
    {
        $suppliant = $this->suppliantQuery->getSupliantById($suppliantUid);

        return $this->normalizeSuppliants($suppliant);
    }

    public function getPetentRoleById($suppliantUid)
    {
        return $this->suppliantQuery->getPetentRoleById($suppliantUid);
    }

    private function normalizeSuppliants($data): array
    {
        $data = json_decode(json_encode($data), true);

        if (!is_array($data)) {
            return [];
        }

        array_walk_recursive($data, static function (&$value): void {
            if (is_string($value) && $value !== '') {
                $value = Functions::normalizeText($value);
            }
        });

        return $data;
    }
}