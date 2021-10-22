<?php

require_once(__DIR__ . "/../startup.php");

$vlDb = new \Vlsm\Models\Vl($db);

$sql = "SELECT vl_sample_id,result_value_absolute_decimal, result_value_text, result
				
		FROM vl_request_form

		WHERE (result_status = 4 OR result_status = 7)
        
        AND vl_result_category is null";

$result = $db->rawQuery($sql);

foreach ($result as $aRow) {

    $vlResultCategory = $vlDb->getVLResultCategory($aRow['result_status'], $aRow['result']);

    if (!empty($vlResultCategory) && $vlResultCategory !== false) {
        $db->where('vl_sample_id', $aRow['vl_sample_id']);
        $db->update("vl_request_form", $vlResultCategory);
    }
}
