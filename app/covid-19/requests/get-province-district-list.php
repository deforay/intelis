<?php

use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
$_GET = _sanitizeInput($request->getQueryParams());

/** @var GeoLocationsService $geoDb */
$geoDb = ContainerRegistry::get(GeoLocationsService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$text = '';
if (isset($_GET['type']) && $_GET['type'] == 'district') {
    $field = "patient_district";
} elseif (isset($_GET['type']) && $_GET['type'] == 'province') {
    $field = "patient_province";
} elseif (isset($_GET['type']) && $_GET['type'] == 'zone') {
    $field = "patient_zone";
}

if (!isset($_POST['pName']) && !isset($_POST['zName'])) {
    $text = $_GET['q'];

    if ($text != "") {
        $cQuery = "SELECT DISTINCT $field FROM form_covid19 WHERE $field like '%" . $text . "%' AND $field is not null";
    } elseif ($_GET['zName'] != "") {
        $cQuery = "SELECT DISTINCT $field FROM form_covid19 WHERE patient_zone like '%" . $_GET['zName'] . "%' AND $field is not null";
    } elseif ($_GET['pName'] != "") {
        $cQuery = "SELECT DISTINCT $field FROM form_covid19 WHERE patient_province like '%" . $_GET['pName'] . "%' AND $field is not null";
    } else {
        $cQuery = "SELECT DISTINCT $field FROM form_covid19 WHERE $field is not null";
    }
    $cResult = $db->rawQuery($cQuery);
    $echoResult = [];
    if (!empty($cResult)) {
        foreach ($cResult as $row) {
            $echoResult[] = ["id" => $row[$field], "text" => ($row[$field])];
        }
    } else {
        $echoResult[] = ["id" => $text, 'text' => $text];
    }

    $result = ["result" => $echoResult];
    echo json_encode($result);
} elseif (isset($_POST['pName']) && $_POST['pName'] != "") {
    $cQuery = "SELECT DISTINCT patient_zone FROM form_covid19 WHERE patient_province like '%" . $_POST['pName'] . "%' AND patient_zone is not null";
    $cResult = $db->rawQuery($cQuery);
    $option = [];
    if (!empty($cResult)) {
        foreach ($cResult as $row) {
            $option[$row['patient_zone']] = $row['patient_zone'];
        }
        $option["other"] = "Other";
    }
    echo $general->generateSelectOptions($option, null, '-- Sélectionner --');
} elseif (isset($_POST['zName']) && $_POST['zName'] != "") {
    $cQuery = "SELECT DISTINCT patient_district FROM form_covid19 WHERE patient_zone like '%" . $_POST['zName'] . "%' AND patient_district is not null";
    $cResult = $db->rawQuery($cQuery);
    $option = [];
    if (!empty($cResult)) {
        foreach ($cResult as $row) {
            $option[$row['patient_district']] = $row['patient_district'];
        }
        $option["other"] = "Other";
    }
    echo $general->generateSelectOptions($option, null, '-- Sélectionner --');
}
