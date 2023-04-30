<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;

$geoDb = new GeoLocationsService($db);

/** @var GeoLocationsService $geoDb */
$geoDb = \App\Registries\ContainerRegistry::get(GeoLocationsService::class);

/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);

$text = '';
if (isset($_GET['type']) && $_GET['type'] == 'district') {
    $field = "patient_district";
} else if (isset($_GET['type']) && $_GET['type'] == 'province') {
    $field = "patient_province";
} else if (isset($_GET['type']) && $_GET['type'] == 'zone') {
    $field = "patient_zone";
}

if (!isset($_POST['pName']) && !isset($_POST['zName'])) {
    $text = $_GET['q'];

    if ($text != "") {
        $cQuery = "SELECT DISTINCT $field FROM form_covid19 WHERE $field like '%" . $text . "%' AND $field is not null";
    } else if ($_GET['zName'] != "") {
        $cQuery = "SELECT DISTINCT $field FROM form_covid19 WHERE patient_zone like '%" . $_GET['zName'] . "%' AND $field is not null";
    } else if ($_GET['pName'] != "") {
        $cQuery = "SELECT DISTINCT $field FROM form_covid19 WHERE patient_province like '%" . $_GET['pName'] . "%' AND $field is not null";
    } else {
        $cQuery = "SELECT DISTINCT $field FROM form_covid19 WHERE $field is not null";
    }
    $cResult = $db->rawQuery($cQuery);
    $echoResult = [];
    if (count($cResult) > 0) {
        foreach ($cResult as $row) {
            $echoResult[] = array("id" => $row[$field], "text" => ($row[$field]));
        }
    } else {
        $echoResult[] = array("id" => $text, 'text' => $text);
    }

    $result = array("result" => $echoResult);
    echo json_encode($result);
} else if (isset($_POST['pName']) && $_POST['pName'] != "") {
    $cQuery = "SELECT DISTINCT patient_zone FROM form_covid19 WHERE patient_province like '%" . $_POST['pName'] . "%' AND patient_zone is not null";
    $cResult = $db->rawQuery($cQuery);
    $option = [];
    if (count($cResult) > 0) {
        foreach ($cResult as $row) {
            $option[$row['patient_zone']] = $row['patient_zone'];
        }
        $option["other"] = "Other";
    }
    $options = $general->generateSelectOptions($option, null, '-- Sélectionner --');
    echo $options;
} else if (isset($_POST['zName']) && $_POST['zName'] != "") {
    $cQuery = "SELECT DISTINCT patient_district FROM form_covid19 WHERE patient_zone like '%" . $_POST['zName'] . "%' AND patient_district is not null";
    $cResult = $db->rawQuery($cQuery);
    $option = [];
    if (count($cResult) > 0) {
        foreach ($cResult as $row) {
            $option[$row['patient_district']] = $row['patient_district'];
        }
        $option["other"] = "Other";
    }
    $options = $general->generateSelectOptions($option, null, '-- Sélectionner --');
    echo $options;
}
