<?php
namespace App\Source\V1\Services\Suppliant;

use Illuminate\Support\Facades\DB;

class SupliantService {

    public function getPetentRoleById($formDaneId): array
    {
        $roles = DB::table('eurzad_petent_roles')
            ->where('form_dane_id', $formDaneId)
            ->pluck('role_name')
            ->toArray();

        return !empty($roles)
            ? $roles
            : ['Twórca'];
    }

    public function getSupliantById($suppliantId)
    {
        $data = DB::table('eurzad_petent_search')
            ->where('main_petent_uid', $suppliantId)
            ->value('view_all');
        $data = json_decode($data, true);
        return $data[$suppliantId];
    }
}