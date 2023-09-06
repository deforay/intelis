<?php

use App\Services\BatchService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');
/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


/** @var BatchService $batchService */
$batchService = ContainerRegistry::get(BatchService::class);

$tableName = "batch_details";
try {
    $labelOrder = '';
    if (isset($_POST['sortOrders']) && trim($_POST['sortOrders']) != '') {
        $xplodSortOrders = explode(",", $_POST['sortOrders']);
        $orderArray = [];
        if (isset($_POST['positions']) && $_POST['positions'] == 'alpha-numeric') {
            foreach ($batchService->excelColumnRange('A', 'H') as $value) {
                foreach (range(1, 12) as $no) {
                    $alphaNumeric[] = $value . $no;
                }
            }
            for ($o = 0; $o < count($xplodSortOrders); $o++) {
                $orderArray[$alphaNumeric[$o]] = $xplodSortOrders[$o];
            }
        } else {
            for ($o = 0; $o < count($xplodSortOrders); $o++) {
                $orderArray[$o] = $xplodSortOrders[$o];
            }
        }
        $labelOrder = json_encode($orderArray, JSON_FORCE_OBJECT);
        $data = [
            'label_order' => $labelOrder,
            'last_modified_by' => $_SESSION['userId'],
            'last_modified_datetime' => DateUtility::getCurrentDateTime()
        ];
        $db = $db->where('batch_id', $_POST['batchId']);
        $db->update($tableName, $data);
        $_SESSION['alertMsg'] = _translate("Samples position in batch saved");
    }
    header("Location:batches.php?type=" . $_POST['type']);
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
