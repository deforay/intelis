<?php
$geoDb = new \Vlsm\Models\GeoLocations($db);
$generalDb = new \Vlsm\Models\General($db);
$text = '';
if (isset($_GET['q']) && $_GET['q'] != "" && !isset($_POST['pName'])) {
    $text = $_GET['q'];
    if ($text != "") {
        $cQuery = "SELECT DISTINCT patient_district FROM form_covid19 WHERE patient_district like '%" . $text . "%'";
    } else {
        $cQuery = "SELECT DISTINCT patient_district FROM form_covid19";
    }
    $cResult = $db->rawQuery($cQuery);
    $echoResult = array();
    if (count($cResult) > 0) {
        foreach ($cResult as $row) {
            $echoResult[] = array("id" => $row['patient_district'], "text" => ucwords($row['patient_district']));
        }
    } else {
        $echoResult[] = array("id" => $text, 'text' => $text);
    }

    $result = array("result" => $echoResult);
    echo json_encode($result);
} else if (isset($_POST) && $_POST['pName'] != "") {
    $cQuery = "SELECT DISTINCT patient_district FROM form_covid19 WHERE patient_province like '%" . $_POST['pName'] . "%'";
    $cResult = $db->rawQuery($cQuery);
    $option = array();
    if (count($cResult) > 0) {
        foreach ($cResult as $row) {
            $option[$row['patient_district']] = $row['patient_district'];
        }
        $option["other"] = "Other";
    }
    $options = $generalDb->generateSelectOptions($option, null, '-- Sélectionner --');
    echo $options;
}
