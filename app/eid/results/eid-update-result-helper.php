<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "form_eid";
$tableName1 = "activity_log";
$tableName2 = "log_result_updates";


try {
  //Set sample received date
  if (isset($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) != "") {
    $sampleReceivedDate = explode(" ", (string) $_POST['sampleReceivedDate']);
    $_POST['sampleReceivedDate'] = DateUtility::isoDateFormat($sampleReceivedDate[0]) . " " . $sampleReceivedDate[1];
  } else {
    $_POST['sampleReceivedDate'] = null;
  }

  if (isset($_POST['sampleTestedDateTime']) && trim((string) $_POST['sampleTestedDateTime']) != "") {
    $sampleTestedDate = explode(" ", (string) $_POST['sampleTestedDateTime']);
    $_POST['sampleTestedDateTime'] = DateUtility::isoDateFormat($sampleTestedDate[0]) . " " . $sampleTestedDate[1];
  } else {
    $_POST['sampleTestedDateTime'] = null;
  }

  if (isset($_POST['approvedOnDateTime']) && trim((string) $_POST['approvedOnDateTime']) != "") {
    $approvedOnDateTime = explode(" ", (string) $_POST['approvedOnDateTime']);
    $_POST['approvedOnDateTime'] = DateUtility::isoDateFormat($approvedOnDateTime[0]) . " " . $approvedOnDateTime[1];
  } else {
    $_POST['approvedOnDateTime'] = null;
  }

  if (isset($_POST['reviewedOn']) && trim((string) $_POST['reviewedOn']) != "") {
    $reviewedOn = explode(" ", (string) $_POST['reviewedOn']);
    $_POST['reviewedOn'] = DateUtility::isoDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
  } else {
    $_POST['reviewedOn'] = null;
  }
  if (isset($_POST['resultDispatchedOn']) && trim((string) $_POST['resultDispatchedOn']) != "") {
    $resultDispatchedOn = explode(" ", (string) $_POST['resultDispatchedOn']);
    $_POST['resultDispatchedOn'] = DateUtility::isoDateFormat($resultDispatchedOn[0]) . " " . $resultDispatchedOn[1];
  } else {
    $_POST['resultDispatchedOn'] = null;
  }

  if (!empty($_POST['newRejectionReason'])) {
    $rejectionReasonQuery = "SELECT rejection_reason_id
                FROM r_eid_sample_rejection_reasons
                WHERE rejection_reason_name like ?";
    $rejectionResult = $db->rawQueryOne($rejectionReasonQuery, [$_POST['newRejectionReason']]);
    if (empty($rejectionResult)) {
      $data = array(
        'rejection_reason_name' => $_POST['newRejectionReason'],
        'rejection_type' => 'general',
        'rejection_reason_status' => 'active',
        'updated_datetime' => DateUtility::getCurrentDateTime()
      );
      $id = $db->insert('r_eid_sample_rejection_reasons', $data);
      $_POST['sampleRejectionReason'] = $id;
    } else {
      $_POST['sampleRejectionReason'] = $rejectionResult['rejection_reason_id'];
    }
  }

  $eidData = array(
    'sample_received_at_lab_datetime' => $_POST['sampleReceivedDate'],
    'eid_test_platform' => $_POST['eidPlatform'] ?? null,
    'import_machine_name' => $_POST['machineName'] ?? null,
    'sample_tested_datetime' => $_POST['sampleTestedDateTime'],
    'is_sample_rejected' => ($_POST['isSampleRejected'] ?? null),
    'lab_id' => $_POST['labId'] ?? null,
    'result' => $_POST['result'] ?? null,
    'tested_by' => (isset($_POST['testedBy']) && $_POST['testedBy'] != '') ? $_POST['testedBy'] : null,
    'lab_tech_comments' => (isset($_POST['labTechCmt']) && $_POST['labTechCmt'] != '') ? $_POST['labTechCmt'] : null,
    'result_approved_by' => (isset($_POST['approvedBy']) && $_POST['approvedBy'] != '') ? $_POST['approvedBy'] : null,
    'result_approved_datetime' => (isset($_POST['approvedOnDateTime']) && $_POST['approvedOnDateTime'] != '') ? $_POST['approvedOnDateTime'] : null,
    'revised_by' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? $_SESSION['userId'] : null,
    'revised_on' => (isset($_POST['revised']) && $_POST['revised'] == "yes") ? DateUtility::getCurrentDateTime() : null,
    'result_reviewed_by' => (isset($_POST['reviewedBy']) && $_POST['reviewedBy'] != "") ? $_POST['reviewedBy'] : null,
    'result_reviewed_datetime' => (isset($_POST['reviewedOn']) && $_POST['reviewedOn'] != "") ? $_POST['reviewedOn'] : null,
    'result_dispatched_datetime' => (isset($_POST['resultDispatchedOn']) && $_POST['resultDispatchedOn'] != "") ? $_POST['resultDispatchedOn'] : null,
    'reason_for_changing' => (!empty($_POST['reasonForChanging'])) ? $_POST['reasonForChanging'] : null,
    'result_status' => 8,
    'data_sync' => 0,
    'reason_for_sample_rejection' => $_POST['sampleRejectionReason'] ?? null,
    'rejection_on' => isset($_POST['rejectionDate']) ? DateUtility::isoDateFormat($_POST['rejectionDate']) : null,
    'last_modified_by' => $_SESSION['userId'],
    'result_printed_datetime' => null,
    'last_modified_datetime' => DateUtility::getCurrentDateTime()
  );

  $db->where('eid_id', $_POST['eidSampleId']);
  $getPrevResult = $db->getOne('form_eid');
  if ($getPrevResult['result'] != "" && $getPrevResult['result'] != $_POST['result']) {
    $eidData['result_modified'] = "yes";
  } else {
    $eidData['result_modified'] = "no";
  }


  if (isset($_POST['isSampleRejected']) && $_POST['isSampleRejected'] == 'yes') {
    $eidData['result'] = null;
    $eidData['result_status'] = SAMPLE_STATUS\REJECTED;
  }

  $formAttributes = [
		'applicationVersion' => $this->commonService->getSystemConfig('sc_version'),
		'ip_address' => $this->commonService->getClientIpAddress()
	];
	if (isset($_POST['freezer']) && $_POST['freezer'] != "" && $_POST['freezer'] != null) {
  
		$freezerCheck = $general->getDataFromOneFieldAndValue('lab_storage', 'storage_id', $_POST['freezer']);

		if (empty($freezerCheck)) {
			$storageId = $general->generateUUID();
			$freezerCode = $_POST['freezer'];
			$d = [
				'storage_id' => $storageId,
				'storage_code' => $freezerCode,
				'lab_id' => $_POST['labId'],
				'storage_status' => 'active'
			];
			$db->insert('lab_storage', $d);
		} else {
			$storageId = $_POST['freezer'];
			$condition = " storage_id = '$freezerCheck'";
			$freezerInfo = $general->getDataByTableAndFields('lab_storage', array('storage_code'), false, $condition);
			$freezerCode = $freezerInfo[0]['storage_code'];
		}

		$formAttributes['storage'] = [
			"storageId" => $storageId,
			"storageCode" => $freezerCode,
			"rack" => $_POST['rack'],
			"box" => $_POST['box'],
			"position" => $_POST['position'],
			"volume" => $_POST['volume']
		];
	}

	$formAttributes = $general->jsonToSetString(json_encode($formAttributes), 'form_attributes');
	$eidData['form_attributes'] = $db->func($formAttributes);

  //var_dump($eidData);die;

  $db->where('eid_id', $_POST['eidSampleId']);
  $id = $db->update($tableName, $eidData);
  error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());

  $_SESSION['alertMsg'] = _translate("EID result updated successfully");
  //Add event log
  $eventType = 'update-eid-result';
  $action = $_SESSION['userName'] . ' updated result for the sample id ' . $_POST['sampleCode'] . ' and child id ' . $_POST['childId'];
  $resource = 'eid-result';

  $general->activityLog($eventType, $action, $resource);

  $data = array(
    'user_id' => $_SESSION['userId'],
    'vl_sample_id' => $_POST['eidSampleId'],
    'test_type' => 'eid',
    'updated_datetime' => DateUtility::getCurrentDateTime()
  );
  $db->insert($tableName2, $data);

  header("Location:eid-manual-results.php");
} catch (Exception $exc) {
  LoggerUtility::log("error", $e->getMessage(), [
    'file' => __FILE__,
    'line' => __LINE__,
    'trace' => $e->getTraceAsString(),
  ]);
}
