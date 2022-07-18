<?php

require_once(__DIR__ . "/../startup.php");

$vlDb = new \Vlsm\Models\Vl();

$sql = "SELECT vl_sample_id,result_value_absolute_decimal, result_value_text, result, result_status
				
		FROM form_vl

		WHERE ((result_status = 4 OR result_status = 7) OR result is not null)
        
        AND vl_result_category is null
        ";

$result = $db->rawQuery($sql);

// var_dump(count($result));

foreach ($result as $aRow) {

    $vlResultCategory = $vlDb->getVLResultCategory($aRow['result_status'], $aRow['result']);

    if (!empty($vlResultCategory) && $vlResultCategory !== false) {
        
        $db->where('vl_sample_id', $aRow['vl_sample_id']);
        $res = $db->update("form_vl", array('vl_result_category' => $vlResultCategory));

    }
}
