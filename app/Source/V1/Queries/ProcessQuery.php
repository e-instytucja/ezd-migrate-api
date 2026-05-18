<?php
namespace App\Source\V1\Queries;

use Illuminate\Support\Facades\DB;

class ProcessQuery {

    /**
     * Pobiera wartość kolumny z tablicy po sprawa_uid
     *
     * @param        $mainDocumentUid
     * @param string $column
     * @param string $table
     *
     * @return bool|int
     */
    public function getBySprawaUid($mainDocumentUid, $column = 'pId'): bool|int
    {
        $getInstanceIdSql = <<<SQL
SELECT
    "instanceId"
FROM
    eurzad_obieg
WHERE
    sprawa_uid = ?
ORDER BY
    status_sprawy_id DESC
LIMIT 1
SQL;
        $sql = "SELECT p.\"{$column}\" FROM galaxia_instances p WHERE p.\"instanceId\" = ({$getInstanceIdSql})";
        $ret = DB::selectOne($sql, [$mainDocumentUid]);

        $retColumn = $ret?->{$column};

        if (empty($retColumn)) {
            throw new \Exception(
                "Brak danych dla dokumentu {$mainDocumentUid}"
            );
        }

        return $retColumn;
    }

    public function getProcesNameByPID($processId)
    {
        $name = DB::table('galaxia_processes')
            ->where('pId', $processId)
            ->value('name');
        if(empty($name)) {
            throw new \Exception("Brak danych dla procesu {$processId}");
        }
        return $name;
    }

    public function getNormalizedProcesNameByPID($processId)
    {
        $name = DB::table('galaxia_processes')
            ->where('pId', $processId)
            ->value('normalized_name');
        if(empty($name)) {
            throw new \Exception("Brak danych dla procesu {$processId}");
        }
        return $name;
    }
}