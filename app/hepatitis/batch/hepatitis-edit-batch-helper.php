<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



$tableName1 = "batch_details";
$tableName2 = "form_hepatitis";
try {
	if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != "") {
		$id = intval($_POST['batchId']);
		$data = array(
			'batch_code' => $_POST['batchCode'],
			'machine' => $_POST['machine']
		);
		$db = $db->where('batch_id', $id);
		$db->update($tableName1, $data);
		if ($id > 0) {
			$value = array('sample_batch_id' => null);
			$db = $db->where('sample_batch_id', $id);
			$db->update($tableName2, $value);
			$xplodResultSample = [];
			if (isset($_POST['selectedSample']) && trim($_POST['selectedSample']) != "") {
				$xplodResultSample = explode(",", $_POST['selectedSample']);
			}
			$sample = [];
			//Mergeing disabled samples into existing samples
			if (isset($_POST['sampleCode']) && count($_POST['sampleCode']) > 0) {
				if (count($xplodResultSample) > 0) {
					$sample = array_unique(array_merge($_POST['sampleCode'], $xplodResultSample));
				} else {
					$sample = $_POST['sampleCode'];
				}
			} elseif (count($xplodResultSample) > 0) {
				$sample = $xplodResultSample;
			}

			for ($j = 0; $j < count($sample); $j++) {
				$value = array('sample_batch_id' => $id);
				$db = $db->where('hepatitis_id', $sample[$j]);
				$db->update($tableName2, $value);
			}
			//Update batch controls position, If samples has changed
			$displaySampleOrderArray = [];
			$batchQuery = "SELECT * from batch_details as b_d INNER JOIN instruments as i_c ON i_c.config_id=b_d.machine where batch_id=$id";
			$batchInfo = $db->query($batchQuery);
			if (isset($batchInfo) && count($batchInfo) > 0) {
				if (isset($batchInfo[0]['label_order']) && trim($batchInfo[0]['label_order']) != '') {
					//Get display sample only
					$samplesQuery = "SELECT hepatitis_id,sample_code from form_hepatitis where sample_batch_id=$id ORDER BY sample_code ASC";
					$samplesInfo = $db->query($samplesQuery);
					foreach ($samplesInfo as $sample) {
						$displaySampleOrderArray[] = $sample['hepatitis_id'];
					}
					//Set label order
					$jsonToArray = json_decode($batchInfo[0]['label_order'], true);
					$displaySampleArray = [];
					for ($j = 0; $j < count($jsonToArray); $j++) {
						$xplodJsonToArray = explode("_", $jsonToArray[$j]);
						if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] == "s") {
							if (in_array($xplodJsonToArray[1], $displaySampleOrderArray)) {
								$displayOrder[] = $jsonToArray[$j];
								$displaySampleArray[] = $xplodJsonToArray[1];
							}
						} else {
							$displayOrder[] = $jsonToArray[$j];
						}
					}
					$remainSampleNewArray = array_values(array_diff($displaySampleOrderArray, $displaySampleArray));
					//For new samples
					for ($ns = 0; $ns < count($remainSampleNewArray); $ns++) {
						$displayOrder[] = 's_' . $remainSampleNewArray[$ns];
					}
					$orderArray = [];
					for ($o = 0; $o < count($displayOrder); $o++) {
						$orderArray[$o] = $displayOrder[$o];
					}
					$labelOrder = json_encode($orderArray, JSON_FORCE_OBJECT);
					//Update label order
					$data = array('label_order' => $labelOrder);
					$db = $db->where('batch_id', $id);
					$db->update($tableName1, $data);
				}
			}
			$_SESSION['alertMsg'] = "Batch updated successfully";
		}
	}
	header("Location:/hepatitis/batch/hepatitis-batches.php");
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
