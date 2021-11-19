<?php

require_once(__DIR__ . "/../startup.php");
$general = new \Vlsm\Models\General();
/* Define the module required fields and tables */
$primaryKey = array("vl" => "vl_sample_id", "eid" => "eid_id", "covid19" => "covid19_id", "hepatitis" => "hepatitis_id", "tb" => "tb_id");
$tableName = array("vl" => "vl_request_form", "eid" => "eid_form", "covid19" => "form_covid19", "hepatitis" => "form_hepatitis", "tb" => "form_tb");
$availableModule = array("vl", "eid", "covid19", "hepatitis", "tb");

$updateStatus = array();
foreach ($systemConfig['modules'] as $module => $status) {
    if ($status) {
        $expiryDays = $general->getGlobalConfig($module . '_sample_expiry_after_days');
        if (in_array($module, $availableModule)) {
            $updateStatus[$module] = array();
            if ($expiryDays > 0) {
                $db->where("sample_tested_datetime IS NULL");
                $db->where("result_status NOT LIKE 10");
                $db->where("(DATEDIFF(CURRENT_DATE, `sample_collection_date`)) > " . $expiryDays);
                $result = $db->get($tableName[$module]);
                if (sizeof($result) > 0) {
                    $id = 0;
                    foreach ($result as $testRow) {
                        $db->where($primaryKey[$module], $testRow[$primaryKey[$module]]);
                        $id = $db->update($tableName[$module], array("result_status" => 10));
                        if ($id > 0) {
                            $updateStatus[$module][] = $testRow[$primaryKey[$module]];
                        }
                    }
                }
            }
        }
    }
}
echo json_encode(array("status" => "success", "updated-records" => json_encode($updateStatus), "message" => "Updated"));
