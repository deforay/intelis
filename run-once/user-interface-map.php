<?php

use App\Registries\ContainerRegistry;
use App\Services\UserService;

require_once(__DIR__ . '/../bootstrap.php');

/** @var MysqliDb $db */
$db = \App\Registries\ContainerRegistry::get('db');


/** @var UserService $usersService */
$usersService = \App\Registries\ContainerRegistry::get(UserService::class);


$sql = "SELECT u.user_id, u.user_name, i.user_id as interface_user_id, i.user_name as interface_user_name    
        FROM user_details u 
        INNER JOIN user_details i ON (JSON_CONTAINS(LOWER(u.interface_user_name), JSON_QUOTE(LOWER(i.user_name)), '$'))";


$testTables = array(
    "form_vl",
    "form_eid",
    "form_covid19",
    "form_hepatitis",
    "form_tb",
);


$columnsToUpdate = array(
    "tested_by",
    "result_approved_by",
    "result_reviewed_by",
);

$result = $db->rawQuery($sql);

foreach ($result as $row) {

    foreach ($testTables as $table) {
        foreach ($columnsToUpdate as $column) {
            $db->where($column, $row['interface_user_id']);
            $db->update($table, array($column => $row['user_id']));
        }
    }

    $db->where('user_id', $row['interface_user_id']);
    $db->delete("user_details");
}
