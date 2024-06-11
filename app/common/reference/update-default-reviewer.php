<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {

    // Sanitized values from $request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    if($_POST['testType'] == 'vl'){
        $tableName = "form_vl";
    }else if($_POST['testType'] == 'eid'){
        $tableName = "form_eid";
    }else if($_POST['testType'] == 'covid19'){
        $tableName = "form_covid19";
    }else if($_POST['testType'] == 'hepatitis'){
        $tableName = "form_hepatitis";
    }else if($_POST['testType'] == 'tb'){
        $tableName = "form_tb";
    }else if($_POST['testType'] == 'cd4'){
        $tableName = "form_cd4";
    }else if($_POST['testType'] == 'generic-tests'){
        $tableName = "form_generic";
    }

    $data = array(
        'result_reviewed_by'  => $_POST['defaultReviewer']
    );

    $db->where("(result_reviewed_by IS NULL OR result_reviewed_by = '')");
    if($_POST['testType'] == 'cd4'){
        $db->where('cd4_result', NULL, 'IS NOT');
    }else{
        $db->where('result', NULL, 'IS NOT');
    }
    $db->update($tableName, $data);

} catch (Exception $exc) {
    error_log($exc->getMessage());
}
