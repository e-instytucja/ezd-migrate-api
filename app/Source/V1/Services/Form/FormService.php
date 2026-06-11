<?php
namespace App\Source\V1\Services\Form;

use App\Shared\Functions;

use App\Source\V1\DTO\TypPozycjaInteresanta;
use App\Source\V1\Queries\Form\FormQuery;
use App\Source\V1\Queries\Structure\UugQuery;
use App\Source\V1\Queries\Structure\WorkstationQuery;
use App\Source\V1\Services\Attachment\AttachmentService;
use App\Source\V1\Services\Dictionary\DictionaryService;
use App\Source\V1\Services\Suppliant\SupliantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FormService {



    public function __construct(
        private readonly FormQuery         $formQuery,
        private readonly WorkstationQuery  $workstationQuery,
        private readonly UugQuery          $uugQuery,
        private readonly AttachmentService $attachmentService,
        private readonly DictionaryService $dictionaryService,
        private readonly SupliantService    $SuppliantService
    )
    {

    }

    /**
     * @throws \JsonException
     */
    public function getFormValues($mainDocumentUid, $formName)
    {
        Log::notice('FORM_VALUES.start', ['main_document_uid' => $mainDocumentUid, 'form_name' => $formName]);
        $startedAt = Functions::startTimer();

        $formFromDb = $this->formQuery->getValuesFromFormDane($mainDocumentUid);
        $formStruct = $this->formStruct($formName);
        $clientsCount = 0;
        foreach ($formFromDb as $val) {
            if ($val['struktura_typ'] === 'interesanci') {
                $clientsCount++;
            }
        }
        $ret = [];
        foreach ($formFromDb as $val) {
            if (isset($formStruct[$val['form_pole']]['typ']) && $val['struktura_pole'] != null) {
                switch ($formStruct[$val['struktura_pole']]['typ']) {
                    case 'cpv':
                        $ret[$val["struktura_pole"]] = explode('#', $val["wartosc"]);
                        break;
                    case 'checkbox':
                    case 'multiselect1':
                        $val["wartosc"] = htmlspecialchars_decode(str_replace('&#34;', '"', $val["wartosc"]));
                        $ret[$val["struktura_pole"]] = json_decode($val["wartosc"]);
                        break;
                    case 'interesanci':
                        if ($clientsCount <= 1) {
                            $ret[$val["struktura_pole"]] = null;
                        }
                        if (!empty($val["wartosc"])) {
                            $ret[$val["form_pole"]][] = $this->getSuppliantToForm(
                                $val["wartosc"],
                                $val['form_dane_id'],
                                false
                            );
                        }
                        break;
                    case 'stanowiska':
                        $ret[$val["struktura_pole"]] = $val["wartosc"];
                        $departamentInfo = $this->workstationQuery->getDepartamentInfo($val["wartosc"]);
                        $ret[$val["struktura_pole"] . '_symbol_kom'] = $departamentInfo['groupName'];
                        break;
                    case 'stanowisko_uzytkownik':
                        $ret[$val["struktura_pole"]] = $val["wartosc"];
                        $departamentInfo = $this->uugQuery->getDepartamentInfo($val["wartosc"]);
                        $ret[$val["struktura_pole"] . '_symbol_kom'] = $departamentInfo['groupName'];
                        break;
                    case 'dokument_tytul':
                        $ret[$val["struktura_pole"]] = $val["wartosc"];
                        break;
                    case 'referat':
                        $departamentInfo = $this->workstationQuery->getDepartamentInfo($val["wartosc"]);
                        $ret[$val["struktura_pole"]] = $departamentInfo['groupName'];
                        break;
                    case 'attachment':
                        $ret[$val["struktura_pole"]] = $this->attachmentService->getAttachmentsDetails($val["wartosc"]);
                        break;
                    case 'slownik':
                        $ret[$val["struktura_pole"]] = $this->dictionaryService->getDictionaryValue($val["wartosc"]);
                        break;
                    default:
                        $val["wartosc"] = str_replace('&#34;', '"', $val["wartosc"]);
                        $ret[$val["struktura_pole"]] = $val["wartosc"];
                        break;
                }
            } elseif (!empty($val['wartosc'])) {
                if ($val["form_pole"] === 'petent_uid' && $val['struktura_typ'] !== 'interesanci') {
                    $ret['interesanci'][] = $this->getSuppliantToForm(
                        $val["wartosc"],
                        $val['form_dane_id'],
                        true
                    );
                } else {
                    $ret[$val["struktura_pole"]] = $val["wartosc"];
                }
            }
        }
        Log::info('[' . Functions::elapsedMs($startedAt) . 'ms] FORM_VALUES.ok', [
            'main_document_uid' => $mainDocumentUid,
            'form_name' => $formName,
            'fields_count' => count($ret),
        ]);

        return $ret;
    }

    private function getSuppliantToForm($suppliantId, $formDaneId, $isMain)
    {
        $suppliantData = $this->SuppliantService->getSupliantById($suppliantId);
        $suppliantRole = $this->SuppliantService->getPetentRoleById($formDaneId);
        return new TypPozycjaInteresanta(
            interesantDane:  $suppliantData,
            interesantRole:  $suppliantRole,
            interesantGlowny: $isMain
        );
    }

    public function formStruct(string $formName)
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
                "value"         => '',
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
//                        $dictionary = dictionaries::getInstance()->getDictionary(
//                            $row['form_struktura_options'],
//                            't',
//                            true
//                        );
//
//                        if ($dictionary['data']['symbol'] == 'dokument_jezyk') {
//                            $default_value = dictionaries::getInstance()->getDictionaryContentId(
//                                $row['form_struktura_options'],
//                                'pol'
//                            );
//                        } else {
//                            if ($dictionary['data']['symbol'] == 'dokument_dostep') {
//                                //dla pola "Dostęp" domyślną wartością ma być 1 wartość ze słownika
//                                $default_value = dictionaries::getInstance()->getDictionaryContentId(
//                                    $row['form_struktura_options']
//                                );
//                            } else {
//                                $default_value = dictionaries::getInstance()->getDictionaryContentId(
//                                    $row['form_struktura_options'],
//                                    $retarray[$row["pole"]]['default_value']
//                                );
//                            }
//                        }

//                        if (!empty($default_value)) {
//                            $retarray[$row["pole"]]['default_value'] = $default_value;
//                        }
//                        $retarray[$row["pole"]]['options'] = $dictionary['data']['content'];
                        $retarray[$row["pole"]]['value'] = (array)json_decode($row['form_default']);
                        $retarray[$row["pole"]]['value'] = isset($retarray[$row["pole"]]['value'][0])
                            ? $retarray[$row["pole"]]['value'][0] : '';
//                        $retarray[$row["pole"]]['symbol_slownika'] = $dictionary['data']['symbol'];
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
//                    case 'grafika':
//                        $arrGrafika = [];
//                        $arrGrafikaTmp = (array)json_decode($row['form_struktura_options']);
//                        if (!empty($arrGrafikaTmp['base64_file']) && !empty($arrGrafikaTmp['filename'])) {
//                            $arrGrafikaTmp['filepath'] = $this->parseBase64FileToPath(
//                                $arrGrafikaTmp['base64_file'],
//                                $arrGrafikaTmp['filename']
//                            );
//                            $arrGrafika = $arrGrafikaTmp;
//                        }
//                        $retarray[$row["pole"]]['options'] = $arrGrafika;
//                        unset($arrGrafika);
//                        unset($arrGrafikaTmp);
//                        break;
                    default:
                        $retarray[$row["pole"]]['options'] = explode("#", $row['form_struktura_options']);
                        break;
                }
            }
        }

        //sortowanie wg form_parts
        $arrayTemp = $retarray;
        $retarray = [];
        $parts = (array) DB::Select(
            'SELECT form_parts_pola FROM eurzad_form_parts WHERE form_name = ? ORDER BY form_parts_lp',
            [$formName]
        );
        foreach($parts as $row) {
            foreach (explode(';', $row->form_parts_pola) as $field) {
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