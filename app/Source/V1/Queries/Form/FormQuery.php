<?php
namespace App\Source\V1\Queries\Form;

use Illuminate\Support\Facades\DB;

class FormQuery {
    public function getValuesFromFormDane($documentId, $pole = '')
    {
        $formDanePole = '';
        $params = [$documentId];
        if (!empty($pole)) {
            $params[] = $pole;
            $formDanePole = ' AND form_dane_pole = ?';
        }
        $params = array_merge($params, $params);
        $query = <<<SQL
SELECT
    *
FROM (
    (
        SELECT
            fd.form_dane_id,
            fs.form_struktura_typ,
            fs.form_struktura_pole,
            fd.form_dane_pole,
            fd.form_dane_wartosc
        FROM
            eurzad_form_struktura fs
        INNER JOIN
            eurzad_sprawa s ON fs.form_name = s.form_name
        LEFT JOIN
            eurzad_form_dane fd ON fd.sprawa_uid = s.sprawa_uid AND fd.form_dane_pole = fs.form_struktura_pole
        WHERE
            s.sprawa_uid = ?{$formDanePole}
    )
    UNION
    (
        SELECT
            fd.form_dane_id,
            fs.form_struktura_typ,
            fs.form_struktura_pole,
            fd.form_dane_pole,
            fd.form_dane_wartosc
        FROM
            eurzad_form_dane fd
        INNER JOIN
            eurzad_sprawa s ON s.sprawa_uid = fd.sprawa_uid
        LEFT JOIN
            eurzad_form_struktura fs ON fs.form_name = s.form_name AND fd.form_dane_pole = fs.form_struktura_pole
        WHERE
            s.sprawa_uid = ?{$formDanePole}
    )
) tmp
ORDER BY
    form_dane_id ASC
SQL;
        $data = collect(DB::select($query, $params))
            ->map(fn($item) => (array) $item)
            ->toArray();
        return $data;
    }

    public function getValuesFromFormPismaDane($documentId, $pole = '')
    {
        $formDanePole = '';
        $params = [$documentId];
        if (!empty($pole)) {
            $params[] = $pole;
            $formDanePole = ' AND fdp.klucz = ?';
        }
        $params = array_merge($params, $params);
        $query = <<<SQL
SELECT
    *
FROM (
    (
        SELECT
            fd.form_dane_id,
            fs.form_struktura_typ,
            fs.form_struktura_pole,
            fd.klucz,
            fd.wartosc
        FROM
            eurzad_form_struktura fs
        INNER JOIN
            galaxia_processes gp ON gp ON fs.form_name = gp.normalized_name
        INNER JOIN 
            galaxia_instances gi ON (gi."pId" = gp."pId")
        INNER JOIN 
            eurzad_pismo p ON (p.instance_id = gi."instanceId" AND p.pismo_wersja = (
                SELECT MAX(pismo_wersja) FROM eurzad_pismo WHERE instance_id = gi."instanceId")
            )
        LEFT JOIN
            eurzad_form_pisma_dane fdp ON p.id = fdp.id AND fdp.form_dane_pole = fs.form_struktura_pole
        WHERE
            p.pismo_uid = ?{$formDanePole}
    )
    UNION
    (
        SELECT
            fd.form_dane_id,
            fs.form_struktura_typ,
            fs.form_struktura_pole,
            fd.form_dane_pole,
            fd.form_dane_wartosc
        FROM
            eurzad_form_pisma_dane fdp
        INNER JOIN 
            eurzad_pismo p ON (p.id = fdp.id AND p.pismo_wersja = (
                SELECT MAX(pismo_wersja) FROM eurzad_pismo WHERE instance_id = p."instanceId")
            )
        INNER JOIN 
            galaxia_instances gi ON (gi."instanceId" = p."instanceId")
        INNER JOIN 
            galaxia_processes gp ON (gp."pId" = gi."pId")
        LEFT JOIN
            eurzad_form_struktura fs ON fs.form_name = gp.normalized_name AND fd.form_dane_pole = fs.form_struktura_pole
        WHERE
            p.pismo_uid = ?{$formDanePole}
    )
) tmp
ORDER BY
    form_dane_id ASC
SQL;
        $data = collect(DB::select($query, $params))
            ->map(fn($item) => (array) $item)
            ->toArray();
        return $data;
    }

    public function getFormStructure(string $formName): array
    {
        $params = [$formName];
        $query = <<<SQL
SELECT
    form_struktura_pole AS pole,
    form_struktura_typ AS typ,
    form_struktura_required AS required,
    form_struktura_pattern AS pattern,
    form_struktura_function AS function,
    form_struktura_opis AS opis,
    form_struktura_default AS form_default,
    form_struktura_options,
    form_struktura_typ_danych AS data_type,
    form_struktura_size AS field_size,
    form_struktura_dt_default AS dt_default,
    form_struktura_dt_manualy AS dt_manualy,
    form_struktura_dt_set AS dt_set
FROM
    eurzad_form_struktura
WHERE
    form_name = ?
ORDER BY
    form_struktura_id
SQL;
        return collect(DB::select($query, $params))
            ->map(fn($item) => (array) $item)
            ->toArray();
    }



    public function getValueFromFormDane($key, $documentId) {
        return DB::table('eurzad_form_dane')
            ->where('sprawa_uid', $documentId)
            ->where('form_dane_pole', $key)
            ->value('form_dane_wartosc');
    }

    public function getAllValuesByKey(string $key, int $limit = 0, int $offset = 0): array
    {
        $query = DB::table('eurzad_form_dane')
            ->where('form_dane_pole', $key);

        if ($offset > 0) {
            $query->offset($offset);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query
            ->get()
            ->map(fn($item) => (array) $item)
            ->toArray();
    }

    public function streamAllValuesByKey(string $key, int $limit = 0, int $offset = 0): \Generator
    {
        $query = DB::table('eurzad_form_dane')
            ->where('form_dane_pole', $key)
            ->orderBy('form_dane_id');

        if ($offset > 0) {
            $query->offset($offset);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        foreach ($query->cursor() as $item) {
            yield (array) $item;
        }
    }

    public function countAllValuesByKey(string $key, int $limit = 0, int $offset = 0): int
    {
        $total = (int) DB::table('eurzad_form_dane')
            ->where('form_dane_pole', $key)
            ->count();

        $effectiveTotal = max(0, $total - max(0, $offset));
        if ($limit > 0) {
            $effectiveTotal = min($effectiveTotal, $limit);
        }

        return $effectiveTotal;
    }
}