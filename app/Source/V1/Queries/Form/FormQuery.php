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
}