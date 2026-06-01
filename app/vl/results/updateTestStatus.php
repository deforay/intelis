<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\TEST_FAILED;
use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\BulkResultStatusService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var BulkResultStatusService $bulkResultStatusService */
$bulkResultStatusService = ContainerRegistry::get(BulkResultStatusService::class);

$tableName = "form_vl";
try {


    // Sanitized values from $request object
    /** @var ServerRequestInterface $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $id = explode(",", (string) $_POST['id']);
    $counter = count($id);
    for ($i = 0; $i < $counter; $i++) {
        $db->where('vl_sample_id', $id[$i]);
        $vlRow = $db->getOne($tableName);

        $status = [];
        if (!empty($_POST['status'])) {
            $status = [
                'result_status' => $_POST['status'],
                'result_approved_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'data_sync' => 0
            ];
            // Preserve the historic auto-fill behavior on status updates for VL.
            // Skip for Cancelled samples since no testing/approval took place.
            if ($_POST['status'] != CANCELLED) {
                if (empty($vlRow['result_reviewed_by'])) {
                    $status['result_reviewed_by'] = $_SESSION['userId'];
                }
                if (empty($vlRow['result_approved_by'])) {
                    $status['result_approved_by'] = $_SESSION['userId'];
                }
            }
            if ($_POST['status'] == REJECTED) {
                $status['result_value_log'] = '';
                $status['result_value_absolute'] = '';
                $status['result_value_text'] = '';
                $status['result_value_absolute_decimal'] = '';
                $status['result'] = '';
                $status['is_sample_rejected'] = 'yes';
                $status['reason_for_sample_rejection'] = $_POST['rejectedReason'];
            } else {
                $status['is_sample_rejected'] = 'no';
                $status['reason_for_sample_rejection'] = null;
            }

            $vlService = ContainerRegistry::get(VlService::class);
            $status['vl_result_category'] = $vlService->getVLResultCategory($status['result_status'], $vlRow['result']);
            if ($status['vl_result_category'] == 'failed' || $status['vl_result_category'] == 'invalid') {
                $status['result_status'] = TEST_FAILED;
            } elseif ($status['vl_result_category'] == 'rejected') {
                $status['result_status'] = REJECTED;
            }

            $db->where('vl_sample_id', $id[$i]);
            $db->update($tableName, $status);
        }

        $userData = $bulkResultStatusService->getBulkUserData($vlRow, $_POST);
        if ($userData !== []) {
            $userData['last_modified_datetime'] = DateUtility::getCurrentDateTime();
            $userData['data_sync'] = 0;

            $db->where('vl_sample_id', $id[$i]);
            $db->update($tableName, $userData);
        }


        $result = $id[$i];

        $sampleCode = 'sample_code';
        if ($general->isSTSInstance()) {
            $sampleCode = 'remote_sample_code';
            $sampleCode = !empty($vlRow['remote_sample']) && $vlRow['remote_sample'] == 'yes' ? 'remote_sample_code' : 'sample_code';
        }

        $sampleId = (isset($vlRow[$sampleCode]) && !empty($vlRow[$sampleCode])) ? " " . _translate("Sample ID") . " " . $vlRow[$sampleCode] : '';
        $patientId = (isset($vlRow['patient_art_no']) && !empty($vlRow['patient_art_no'])) ? " " . _translate("Patient ID") . " " . $vlRow['patient_art_no'] : '';
        $concat = ($sampleId !== '' && $sampleId !== '0' && ($patientId !== '' && $patientId !== '0')) ? ' and' : '';
        //Add event logs
        $eventType = 'update-sample-status';
        $action = $_SESSION['userName'] . ' updated VL samples status for the ' . $sampleId . $concat . $patientId;
        $resource = 'vl-results';
        $general->activityLog($eventType, $action, $resource);
        echo $result;
    }
} catch (Throwable $e) {
    throw new SystemException(
        $e->getMessage(),
        $e->getCode(),
        $e
    );
}
