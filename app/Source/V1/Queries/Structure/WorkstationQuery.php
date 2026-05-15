<?php
namespace App\Source\V1\Queries\Structure;

use Illuminate\Support\Facades\DB;

class WorkstationQuery {
    public function getUugId($workstationId)
    {
        $uugId = DB::table('users_usergroups')
            ->where('group_id', $workstationId)
            ->where('status', 'A')
            ->where('typ', 'Z')
            ->value('id');
        if(empty($uugId)) {
            throw new \Exception("Brak danych dla pracownika {$workstationId}");
        }
        return $uugId;
    }
}