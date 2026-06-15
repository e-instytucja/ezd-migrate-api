<?php
namespace App\Source\V1\Queries\Form;

use Illuminate\Support\Facades\DB;

class FormQuery {
    public function getMainDocumentFormValues($maindDocumentUid, $formPole = '')
    {
        $formPoleWhereSql = '';
        $params = [$maindDocumentUid];
        if (!empty($formPole)) {
            $params[] = $formPole;
            $formPoleWhereSql = ' AND form_dane_pole = ?';
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
            fs.form_struktura_opis,
            fd.form_dane_pole,
            fd.form_dane_wartosc
        FROM
            eurzad_form_struktura fs
        INNER JOIN
            eurzad_sprawa s ON fs.form_name = s.form_name
        LEFT JOIN
            eurzad_form_dane fd ON fd.sprawa_uid = s.sprawa_uid AND fd.form_dane_pole = fs.form_struktura_pole AND fd.form_dane_wartosc != ''
        WHERE
            s.sprawa_uid = ?{$formPoleWhereSql}
    )
    UNION
    (
        SELECT
            fd.form_dane_id,
            fs.form_struktura_typ,
            fs.form_struktura_pole,
            fs.form_struktura_opis,
            fd.form_dane_pole,
            fd.form_dane_wartosc
        FROM
            eurzad_form_dane fd
        INNER JOIN
            eurzad_sprawa s ON s.sprawa_uid = fd.sprawa_uid
        LEFT JOIN
            eurzad_form_struktura fs ON fs.form_name = s.form_name AND fd.form_dane_pole = fs.form_struktura_pole
        WHERE
            s.sprawa_uid = ? AND fd.form_dane_wartosc != '' {$formPoleWhereSql}
    )
) tmp
ORDER BY
    form_dane_id ASC
SQL;

        return collect(DB::select($query, $params))
            ->map(fn($item) => [
                'form_dane_id' => $item->form_dane_id ?? null,
                'struktura_typ' => $item->form_struktura_typ ?? null,
                'struktura_pole' => $item->form_struktura_pole ?? null,
                'struktura_opis' => $item->form_struktura_opis ?? null,
                'form_pole' => $item->form_dane_pole ?? null,
                'form_wartosc' => $item->form_dane_wartosc ?? null,
            ])
            ->values()
            ->toArray();
    }


    public function getDocumentFormValues($documentId, $formPole = '')
    {
        $formPoleWhereSql = '';
        $params = [$documentId];
        if (!empty($formPole)) {
            $params[] = $formPole;
            $formPoleWhereSql = ' AND klucz = ?';
        }
        $params = array_merge($params, $params);
        $query = <<<SQL
SELECT
    *
FROM (
    (
        SELECT
            fs.form_struktura_typ,
            fs.form_struktura_pole,
            fs.form_struktura_opis,
            fdp.form_pisma_dane_id,
            fdp.klucz,
            fdp.wartosc
        FROM
            eurzad_form_struktura fs
        INNER JOIN
            galaxia_processes gp ON fs.form_name = gp.normalized_name
        INNER JOIN 
            galaxia_instances gi ON (gi."pId" = gp."pId")
        INNER JOIN 
            eurzad_pismo p ON (p.instance_id = gi."instanceId" AND p.pismo_wersja = (
                SELECT MAX(pismo_wersja) FROM eurzad_pismo WHERE instance_id = gi."instanceId")
            )
        LEFT JOIN
            eurzad_form_pisma_dane fdp ON p.id = fdp.id AND fdp.klucz = fs.form_struktura_pole
        WHERE
            p.pismo_uid = ?{$formPoleWhereSql}
    )
    UNION
    (
        SELECT
            fs.form_struktura_typ,
            fs.form_struktura_pole,
            fs.form_struktura_opis,
            fdp.form_pisma_dane_id,
            fdp.klucz,
            fdp.wartosc
        FROM
            eurzad_form_pisma_dane fdp
        INNER JOIN 
            eurzad_pismo p ON (p.id = fdp.id AND p.pismo_wersja = (
                SELECT MAX(pismo_wersja) FROM eurzad_pismo WHERE instance_id = p.instance_id)
            )
        INNER JOIN 
            galaxia_instances gi ON (gi."instanceId" = p.instance_id)
        INNER JOIN 
            galaxia_processes gp ON (gp."pId" = gi."pId")
        LEFT JOIN
            eurzad_form_struktura fs ON fs.form_name = gp.normalized_name AND fdp.klucz = fs.form_struktura_pole
        WHERE
            p.pismo_uid = ?{$formPoleWhereSql}
    )
) tmp
SQL;

        return collect(DB::select($query, $params))
            ->map(fn($item) => [
                'form_dane_id' => $item->form_pisma_dane_id ?? null,
                'struktura_typ' => $item->form_struktura_typ ?? null,
                'struktura_pole' => $item->form_struktura_pole ?? null,
                'struktura_opis' => $item->form_struktura_opis ?? null,
                'form_pole' => $item->klucz ?? null,
                'form_wartosc' => $item->wartosc ?? null,
            ])
            ->values()
            ->toArray();
    }

    public function getAllValuesByKey(string $key, int $limit = 0, int $offset = 0): array
    {
        $query = DB::table('eurzad_form_dane')
            ->where('form_dane_pole', $key)
            ->where('form_dane_wartosc', '!=', '');

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
            ->where('form_dane_wartosc', '!=', '')
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
            ->where('form_dane_wartosc', '!=', '')
            ->count();

        $effectiveTotal = max(0, $total - max(0, $offset));
        if ($limit > 0) {
            $effectiveTotal = min($effectiveTotal, $limit);
        }

        return $effectiveTotal;
    }
}