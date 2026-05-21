<?php
namespace App\Source\V1\Queries\Suppliant;
use Illuminate\Support\Facades\DB;

class SuppliantQuery
{
    public function getAdditionalSuppliants($mainDocumentUid): array
    {
        $suppliants = DB::table('eurzad_form_dane as fd')
            ->join('eurzad_petent_search as ps', 'ps.main_petent_uid', '=', 'fd.form_dane_wartosc')
            ->select([
                'fd.form_dane_wartosc as interesant_uid',
                'ps.view_podmiot as interesant',
                'ps.view_adres_korespondencyjny as interesant_adres',
                'ps.main_petent_uid',
            ])
            ->where('fd.sprawa_uid', $mainDocumentUid)
            ->where('fd.form_dane_pole', 'interesanci')
            ->get()
            ->map(fn($item) => (array) $item)
            ->toArray();

        return $suppliants;
    }
}