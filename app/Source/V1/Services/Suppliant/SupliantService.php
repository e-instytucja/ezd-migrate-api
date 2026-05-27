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

        array_walk_recursive($suppliants, static function (&$value): void {
            if (is_string($value) && $value !== '') {
                $value = Functions::normalizeText($value);
            }
        });

        return $suppliants;
    }
}