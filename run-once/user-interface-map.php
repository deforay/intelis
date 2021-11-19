<?php

require_once(__DIR__ . "/../startup.php");

$db = \MysqliDb::getInstance();

$usersDb = new \Vlsm\Models\Users();


$sql = "SELECT u.user_id, u.user_name, i.user_id as interface_user_id, i.user_name as interface_user_name    
        FROM user_details u 
        INNER JOIN user_details i ON (JSON_CONTAINS(LOWER(u.interface_user_name), JSON_QUOTE(LOWER(i.user_name)), '$'))";
        

$result = $db->rawQuery($sql);

foreach ($result as $row) {

    $db->where('tested_by', $row['interface_user_id']);
    $db->update("vl_request_form", array('tested_by' => $row['user_id']));

    $db->where('result_approved_by', $row['interface_user_id']);
    $db->update("vl_request_form", array('result_approved_by' => $row['user_id']));

    $db->where('result_reviewed_by', $row['interface_user_id']);
    $db->update("vl_request_form", array('result_reviewed_by' => $row['user_id']));

    $db->where('tested_by', $row['interface_user_id']);
    $db->update("eid_form", array('tested_by' => $row['user_id']));

    $db->where('result_approved_by', $row['interface_user_id']);
    $db->update("eid_form", array('result_approved_by' => $row['user_id']));

    $db->where('result_reviewed_by', $row['interface_user_id']);
    $db->update("eid_form", array('result_reviewed_by' => $row['user_id']));

    $db->where('tested_by', $row['interface_user_id']);
    $db->update("form_covid19", array('tested_by' => $row['user_id']));

    $db->where('result_approved_by', $row['interface_user_id']);
    $db->update("form_covid19", array('result_approved_by' => $row['user_id']));

    $db->where('result_reviewed_by', $row['interface_user_id']);
    $db->update("form_covid19", array('result_reviewed_by' => $row['user_id']));

    $db->where('tested_by', $row['interface_user_id']);
    $db->update("form_hepatitis", array('tested_by' => $row['user_id']));

    $db->where('result_approved_by', $row['interface_user_id']);
    $db->update("form_hepatitis", array('result_approved_by' => $row['user_id']));

    $db->where('result_reviewed_by', $row['interface_user_id']);
    $db->update("form_hepatitis", array('result_reviewed_by' => $row['user_id']));

    $db->where('user_id', $row['interface_user_id']);
    $db->delete("user_details");

}
