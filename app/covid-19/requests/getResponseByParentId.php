<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$sampleData = [];
$sampleQuery = 'SELECT * FROM r_covid19_test_reasons where parent_reason = "' . $_POST['responseParent'] . '"';
$sampleResult = $db->query($sampleQuery);

if (isset($_POST['covid19Id']) && $_POST['covid19Id'] != '') {
    $results = $db->rawQuery("SELECT * FROM covid19_reasons_for_testing WHERE `covid19_id` = " . $_POST['covid19Id']);
    foreach ($results as $row) {
        $response[$row['reasons_id']] = $row['reasons_detected'];
    }
}
if (!empty($sampleResult)) {
    $index = 1;
    foreach ($sampleResult as $key => $sampleRow) {
        $yes = (isset($response[$sampleRow['test_reason_id']]) && $response[$sampleRow['test_reason_id']] == 'yes') ? "selected='selected'" : '';
        $no = (isset($response[$sampleRow['test_reason_id']]) && $response[$sampleRow['test_reason_id']] == 'no') ? "selected='selected'" : '';
        $unknown = (isset($response[$sampleRow['test_reason_id']]) && $response[$sampleRow['test_reason_id']] == 'unknown') ? "selected='selected'" : '';

        echo '<tr class="responseRow' . $_POST['responseParent'] . '" id="' . $_POST['responseParent'] . '">
                <th style="width:50%;padding-left:25px;">' . ($sampleRow['test_reason_name']) . '</th>
                <td style="width:50%;">
                    <input name="responseId[]" type="hidden" value="' . $sampleRow['test_reason_id'] . '">
                    <select name="responseDetected[]" class="reason-input form-control" title="Veuillez choisir la valeur pour ' . $sampleRow['test_reason_name'] . '" style="width:80%" onchange="checkSubResponse(this,' . $sampleRow['test_reason_id'] . ',' . $index . ')">
                        <option value="">-- Select --</option>
                        <option value="yes" ' . $yes . '> Oui </option>
                        <option value="no" ' . $no . '> Non </option>
                        <option value="unknown" ' . $unknown . '> Inconnu </option>
                    </select>
                </td>
            </tr>';
    }
} else {
    echo "";
}
