<?php
namespace App\Source\V1\Services\Suppliant;

use App\Shared\Functions;
use App\Source\V1\Queries\Suppliant\SuppliantQuery;
use Illuminate\Support\Facades\DB;

class SupliantService {

    public function __construct(
        private readonly SuppliantQuery $suppliantQuery
    )
    {}

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