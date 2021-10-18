<?php

require_once(__DIR__ . "/../startup.php");

$suppressionLimit = 1000;

$suppressedArray = array(
    'target not detected',
    'tnd',
    'not detected',
    'below detection limit',
    'below detection level',
    'bdl',
    'suppressed',
    'negative',
    'negat'
);

$sql = "SELECT vl_sample_id,result_value_absolute_decimal, result_value_text, result
				
		FROM vl_request_form

		WHERE vl_result_category is null";


$result = $db->rawQuery($sql);
foreach ($result as $aRow) {

    $dataForUpdate = array();
    if ($aRow['result'] == NULL || empty($aRow['result'])) {

        $dataForUpdate['vl_result_category'] = 'NO-RESULT';
    } else if (is_numeric($aRow['result']) && $aRow['result'] > 0 && $aRow['result'] == round($aRow['result'], 0)) {
        $aRow['result'] = (float)filter_var($aRow['result'], FILTER_SANITIZE_NUMBER_FLOAT);

        if ($aRow['result'] < $suppressionLimit) {
            $dataForUpdate['vl_result_category'] = 'Suppressed';
        } else if ($aRow['result'] >= $suppressionLimit) {
            $dataForUpdate['vl_result_category'] = 'Not Suppressed';
        }
    } else {

        $textResult = NULL;

        if (in_array(strtolower($aRow['result']), $suppressedArray) || in_array(strtolower($aRow['result_value_text']), $suppressedArray)) {
            $textResult = 20;
        } else {
            $textResult = (float)filter_var($aRow['result_value_text'], FILTER_SANITIZE_NUMBER_FLOAT);
        }

        if ($textResult == 'NULL' || empty($textResult)) {
            $dataForUpdate['vl_result_category'] = 'NO-RESULT';
        } else if ($textResult < $suppressionLimit) {
            $dataForUpdate['vl_result_category'] = 'Suppressed';
        } else if ($textResult >= $suppressionLimit) {
            $dataForUpdate['vl_result_category'] = 'Not Suppressed';
        }
    }

    $db->where('vl_sample_id', $aRow['vl_sample_id']);
    $ucount = $db->update("vl_request_form", $dataForUpdate);
}
