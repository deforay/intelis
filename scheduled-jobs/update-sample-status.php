<?php

require_once(__DIR__ . "/../startup.php");
$general = new \Vlsm\Models\General();
/* Define the module required fields and tables */
$primaryKey = array("vl" => "vl_sample_id", "eid" => "eid_id", "covid19" => "covid19_id", "hepatitis" => "hepatitis_id", "tb" => "tb_id");
$tableName = array("vl" => "vl_request_form", "eid" => "eid_form", "covid19" => "form_covid19", "hepatitis" => "form_hepatitis", "tb" => "form_tb");

$updateStatus = array();
foreach ($systemConfig['modules'] as $module => $status) {
    if ($status) {
        $expiryDays = $general->getGlobalConfig($module . '_sample_expiry_after_days');
        $updateStatus['sample-expiry'][$module] = array();
        $updateStatus['lock-expiry'][$module] = array();
        if ($expiryDays > 0) {
            /* Update sample expiry days status */
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
                        $updateStatus['sample-expiry'][$module][] = $testRow[$primaryKey[$module]];
                    }
                }
            }
        }
        $lockExpiryDays = $general->getGlobalConfig($module . '_sample_lock_after_days');
        if ($lockExpiryDays != null) {
            /* Updated sample lock expiry days */
            $db->where("(result_status = 7 OR result_status = 4)");
            $db->where("locked NOT LIKE 'yes'");
            $db->where("(DATEDIFF(CURRENT_DATE, `last_modified_datetime`)) > " . $lockExpiryDays);
            $lockResult = $db->get($tableName[$module]);
            if (sizeof($lockResult) > 0) {
                $id = 0;
                foreach ($lockResult as $lockRow) {
                    $db->where($primaryKey[$module], $lockRow[$primaryKey[$module]]);
                    $id = $db->update($tableName[$module], array("locked" => "yes"));
                    if ($id > 0) {
                        $updateStatus['lock-expiry'][$module][] = $lockRow[$primaryKey[$module]];
                    }
                }
            }
        }
    }
}
echo json_encode(array("status" => "success", "updated-records" => json_encode($updateStatus), "message" => "Updated"));
