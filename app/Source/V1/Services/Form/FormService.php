<?php
namespace App\Source\V1\Services\Form;

use App\Source\V1\Queries\Form\FormQuery;

class FormService {
    public function __construct(
        private readonly FormQuery $formQuery
    )
    {

    }

    public function getFormValues($documentId, $formName)
    {
        $formFromDb = $this->formQuery->getValuesFromFormDane($documentId);
        $formStruct = $this->formStruct($formName);
        $clientsCount = 0;
        foreach ($formFromDb as $val) {
            if ($val->form_struktura_typ == 'interesanci') {
                $clientsCount++;
            }
        }
        $ret = [];
        foreach ($formFromDb as $val) {
            if (isset($formStruct[$val['form_dane_pole']]['typ']) && $val['form_struktura_pole'] != null) {
                switch ($formStruct[$val['form_struktura_pole']]['typ']) {
                    case 'cpv':
                        $ret[$val["form_struktura_pole"]] = explode('#', $val["form_dane_wartosc"]);
                        break;
                    case 'checkbox':
                    case 'multiselect1':
                        $val["form_dane_wartosc"] = str_replace('&#34;', '"', $val["form_dane_wartosc"]);
                        $val["form_dane_wartosc"] = htmlspecialchars_decode($val['form_dane_wartosc']);
                        $ret[$val["form_struktura_pole"]] = json_decode($val["form_dane_wartosc"]);
                        break;
                    case 'interesanci':
                        if ($clientsCount <= 1) {
                            $ret[$val["form_struktura_pole"]] = null;
                        }
                        if (empty($val["form_dane_wartosc"])) {
                            continue;
                        }
                        $ret[$val["form_struktura_pole"]][] = $val["form_dane_wartosc"];
                        $ret['petent_role'][$val["form_dane_wartosc"]] = petentRoles::getInstance()->getPetentRoleById(
                            $val['form_dane_id'],
                            'form_dane_id'
                        );
                        break;
                    case 'stanowiska':
                        $ret[$val["form_struktura_pole"]] = $val["form_dane_wartosc"];
                        $workstationClass = new \Madkom\Objects\EZD\Workstation\Workstation($val["form_dane_wartosc"]);
                        $workstationParent = $workstationClass->getParent();
                        $workstationName = $workstationParent->getName();
                        $ret[$val["form_struktura_pole"] . '_symbol_kom'] = is_null($workstationName)
                            ? false : $workstationName;
                        break;
                    case 'stanowisko_uzytkownik':
                        $ret[$val["form_struktura_pole"]] = $val["form_dane_wartosc"];
                        $ret[$val["form_struktura_pole"] . '_symbol_kom'] = UsersGroupsIdAll::getInstance()
                            ->getBasicData(
                                $val["form_dane_wartosc"],
                                'workstation_group_name'
                            );
                        break;
                    case 'dokument_tytul':
                        $ret[$val["form_struktura_pole"]] = $val["form_dane_wartosc"];
                        break;
                    case 'referat':
                        $workstationClass = new \Madkom\Objects\EZD\Workstation\Workstation($val["form_dane_wartosc"]);
                        $workstationParent = $workstationClass->getParent();
                        $workstationName = $workstationParent->getName();
                        $ret[$val["form_struktura_pole"]] = is_null($workstationName) ? false : $workstationName;
                        break;
                    default:
                        $val["form_dane_wartosc"] = str_replace('&#34;', '"', $val["form_dane_wartosc"]);
                        $ret[$val["form_struktura_pole"]] = $val["form_dane_wartosc"];
                        break;
                }
            } elseif (!empty($val['form_dane_wartosc'])) {
                if ($val["form_dane_pole"] == 'petent_uid' && $val['form_struktura_typ'] != 'interesanci') {
                    $ret['petent_role'][$val["form_dane_wartosc"]] = petentRoles::getInstance()->getPetentRoleById(
                        $val['form_dane_id'],
                        'form_dane_id'
                    );
                    $ret[$val["form_dane_pole"]] = $val["form_dane_wartosc"];
                } else {
                    $ret[$val["form_struktura_pole"]] = $val["form_dane_wartosc"];
                }
            }
        }

        if ($getEmptyValues === true) {
            foreach ($formStruct as $key => $val) {
                if (!isset($ret[$key])) {
                    if (isset($_GET['iid']) && isset($_GET['activityId']) && isset($val['default_value'])) {
                        $mapper = new ValueMapper(
                            [
                                'instanceID' => $_GET['iid'],
                                'activityID' => $_GET['activityId'],
                            ]
                        );

                        $val['default_value'] = $mapper->mapVariablesToValues($val['default_value']);
                    }

                    $ret[$key] = $val['default_value'];
                }
            }
        }

        return $ret;
    }

    public function formStruct($formName)
    {
        $formStructura = $this->formQuery->getFormStructure($formName);
        $retarray = [];
        foreach($formStructura as $row) {
            $retarray[$row["pole"]] = [
                "required"      => $row["required"],
                "pattern"       => $row["pattern"],
                "function"      => $row["function"],
                "typ"           => $row["typ"],
                "opis"          => $row["opis"],
                "value"         => ($this->formEdit) ? null : $row["form_default"],
                "default_value" => $row["form_default"],
                "data_type"     => $row["data_type"],
                "field_size"    => $row["field_size"],
                "dt_default"    => $row["dt_default"],
                "dt_manualy"    => $row["dt_manualy"],
                "dt_set"        => $row["dt_set"],
                "readonly"      => false,
            ];

            if (!empty($row['form_struktura_options'])) {
                switch ($row['typ']) {
                    case 'multiselect1':
                        $row['form_struktura_options'] = htmlentities($row['form_struktura_options'], ENT_NOQUOTES);
                        $row['form_struktura_options'] = str_replace('\\\\', '\\', $row['form_struktura_options']);
                        $retarray[$row["pole"]]['options'] = (array)json_decode($row['form_struktura_options']);
                        $retarray[$row["pole"]]['value'] = (array)json_decode($row['form_default']);
                        break;
                    case 'checkbox':
                        $retarray[$row["pole"]]['options'] = explode("#", $row['form_struktura_options']);
                        $retarray[$row["pole"]]['value'] = (array)json_decode($row['form_default']);
                        break;
                    case 'slownik':
                        $dictionary = dictionaries::getInstance()->getDictionary(
                            $row['form_struktura_options'],
                            't',
                            true
                        );

                        if ($dictionary['data']['symbol'] == 'dokument_jezyk') {
                            $default_value = dictionaries::getInstance()->getDictionaryContentId(
                                $row['form_struktura_options'],
                                'pol'
                            );
                        } else {
                            if ($dictionary['data']['symbol'] == 'dokument_dostep') {
                                //dla pola "Dostęp" domyślną wartością ma być 1 wartość ze słownika
                                $default_value = dictionaries::getInstance()->getDictionaryContentId(
                                    $row['form_struktura_options']
                                );
                            } else {
                                $default_value = dictionaries::getInstance()->getDictionaryContentId(
                                    $row['form_struktura_options'],
                                    $retarray[$row["pole"]]['default_value']
                                );
                            }
                        }

                        if (!empty($default_value)) {
                            $retarray[$row["pole"]]['default_value'] = $default_value;
                        }
                        $retarray[$row["pole"]]['options'] = $dictionary['data']['content'];
                        $retarray[$row["pole"]]['value'] = (array)json_decode($row['form_default']);
                        $retarray[$row["pole"]]['value'] = isset($retarray[$row["pole"]]['value'][0])
                            ? $retarray[$row["pole"]]['value'][0] : '';
                        $retarray[$row["pole"]]['symbol_slownika'] = $dictionary['data']['symbol'];
                        break;
                    case 'select1':
                        $retarray[$row["pole"]]['value'] = (array)json_decode($row['form_default']);
                        $retarray[$row["pole"]]['value'] = isset($retarray[$row["pole"]]['value'][0])
                            ? $retarray[$row["pole"]]['value'][0] : '';
                        $retarray[$row["pole"]]['options'] = explode("#", $row['form_struktura_options']);

                        $retarray[$row["pole"]]['default_value'] = str_replace(
                            '["',
                            '',
                            $retarray[$row["pole"]]['default_value']
                        );
                        $retarray[$row["pole"]]['default_value'] = str_replace(
                            '"]',
                            '',
                            $retarray[$row["pole"]]['default_value']
                        );
                        break;
                    case 'grafika':
                        $arrGrafika = [];
                        $arrGrafikaTmp = (array)json_decode($row['form_struktura_options']);
                        if (!empty($arrGrafikaTmp['base64_file']) && !empty($arrGrafikaTmp['filename'])) {
                            $arrGrafikaTmp['filepath'] = $this->parseBase64FileToPath(
                                $arrGrafikaTmp['base64_file'],
                                $arrGrafikaTmp['filename']
                            );
                            $arrGrafika = $arrGrafikaTmp;
                        }
                        $retarray[$row["pole"]]['options'] = $arrGrafika;
                        unset($arrGrafika);
                        unset($arrGrafikaTmp);
                        break;
                    default:
                        $retarray[$row["pole"]]['options'] = explode("#", $row['form_struktura_options']);
                        break;
                }
            }
        }

        //sortowanie wg form_parts
        $arrayTemp = $retarray;
        $retarray = [];
        $parts = $this->db->Execute(
            'SELECT form_parts_pola FROM eurzad_form_parts WHERE form_name = ? ORDER BY form_parts_lp',
            [$this->formName]
        );
        while (($row = $parts->FetchRow())) {
            foreach (explode(';', $row['form_parts_pola']) as $field) {
                if (isset($arrayTemp[$field])) {
                    $retarray[$field] = $arrayTemp[$field];
                    unset($arrayTemp[$field]);
                }
            }
        }
        $retarray = array_merge($arrayTemp, $retarray);
        return $retarray;
    }
}