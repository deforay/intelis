<?php

use App\Services\CommonService;

$general = new CommonService();
$sampleData = [];
$symptomsQuery = 'SELECT * FROM r_covid19_symptoms where parent_symptom = "' . $_POST['symptomParent'] . '"';
$covid19Symptoms = $db->query($symptomsQuery);

$disabled = (isset($_POST['from']) && $_POST['from'] == "update-result") ? "disabled" : "";
$symptomsArray = [];
if (isset($_POST['covid19Id']) && $_POST['covid19Id'] != '') {
    $results = $db->query("SELECT * FROM covid19_patient_symptoms WHERE `covid19_id` = " . $_POST['covid19Id'] . " ORDER BY symptom_id ASC");
    foreach ($results as $key => $val) {
        $symptomsArray[$val['symptom_id']] = json_decode($val['symptom_details'], true);
        $symptomsParentArray[] = $val['symptom_id'];
    }
}
if (count($covid19Symptoms) > 0) {
    $index = 1;
    foreach ($covid19Symptoms as $key => $symptoms) {
        $checked = (in_array($_POST['symptomParent'], $symptomsParentArray) && in_array($symptoms['symptom_id'], $symptomsArray[$_POST['symptomParent']])) ? "checked" : '';

        $subSymptoms = '<tr class="symptomRow' . $_POST['symptomParent'] . ' hide-symptoms" id="' . $_POST['symptomParent'] . '">
                <td colspan="2" style="padding-left: 70px;display: flex;">';
        if ($symptoms['symptom_id'] == 16 || trim($symptoms['symptom_name']) == 'Nombre de selles par /24h') {
            $subSymptoms .= '<label class="radio-inline" for="symptomDetails' . $symptoms['symptom_id'] . '" style="padding-left:17px !important;margin-left:0;">' . ($symptoms['symptom_name']) . '</label>
                                    <input type="text" value="' . end($symptomsArray[$_POST['symptomParent']]) . '" class="form-control reason-checkbox symptoms-checkbox" id="symptomDetails' . $symptoms['symptom_id'] . '" name="symptomDetails[' . $_POST['symptomParent'] . '][]" placeholder="' . $symptoms['symptom_name'] . '" title="' . $symptoms['symptom_name'] . '" ' . $disabled . ' style=" width: 25%; margin-left: 10px; ">';
        } else {
            $subSymptoms .= '<label class="radio-inline" style="width:4%;margin-left:0;">
                                        <input type="checkbox" class="reason-checkbox symptoms-checkbox" id="symptomDetails' . $symptoms['symptom_id'] . '" name="symptomDetails[' . $_POST['symptomParent'] . '][]" value="' . $symptoms['symptom_id'] . '" title="' . $symptoms['symptom_name'] . '" onclick="checkSubSymptoms(this,' . $symptoms['symptom_id'] . ',' . $index . ', "sub");" ' . $checked . ' ' . $disabled . '>
                                    </label>
                                    <label class="radio-inline" for="symptomDetails' . $symptoms['symptom_id'] . '" style="padding-left:17px !important;margin-left:0;">' . ($symptoms['symptom_name']) . '</label>';
        }
        $subSymptoms .= '</td>
            </tr>';
        echo $subSymptoms;
    }
} else {
    echo "";
}
/* 
<th style="width:50%;padding-left:25px;">'.($sampleRow['symptom_name']).'</th>
<td style="width:50%;">
    <input name="symptomId[]" type="hidden" value="'.$sampleRow['symptom_id'].'">
    <select name="symptomDetected[]" class="symptom-input form-control" title="'. $sampleRow['symptom_name'].'" style="width:80%" onchange="checkSubSymptoms(this,'.$sampleRow['symptom_id'].','.$index.');">
        <option value="">-- Select --</option>
        <option value="yes" '.$yes.'> Oui </option>
        <option value="no" '.$no.'> Non </option>
        <option value="unknown" '.$unknown.'> Inconnu </option>
    </select>
</td> */