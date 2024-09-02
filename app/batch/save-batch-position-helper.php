<?php

use App\Services\BatchService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var BatchService $batchService */
$batchService = ContainerRegistry::get(BatchService::class);


$tableName = "batch_details";
try {
    // echo '<pre>'; print_r($_POST); die;
    $labelOrder = '';
    if (isset($_POST['sortOrders']) && trim((string) $_POST['sortOrders']) != '') {

        $namesArr = $_POST['controls'];

        foreach ($namesArr as $key => $value) {
            if ($value == "") {
                $namesArr[$key] = ucwords(str_replace('no of ', '', str_replace('_', ' ', $key)));
            }
        }

        //Saving names of controls
        $controlNames = json_encode($namesArr, JSON_FORCE_OBJECT);

        $xplodSortOrders = explode(",", (string) $_POST['sortOrders']);
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

        if (!empty($orderArray)) {
            for ($a = 0; $a < count($orderArray); $a++) {
                if (!empty($_POST) && array_key_exists($orderArray[$a], $_POST)) {
                    $orderArray[$a] = $_POST[$orderArray[$a]];
                }
            }
        }
        $labelOrder = json_encode($orderArray, JSON_FORCE_OBJECT);
        $data = [
            'label_order' => $labelOrder,
            'control_names' => $controlNames,
            'last_modified_by' => $_SESSION['userId'],
            'last_modified_datetime' => DateUtility::getCurrentDateTime()
        ];

        $batchAttributes = [];
        if (!empty($_POST['sortBy'])) {
            $batchAttributes['sort_by'] = $_POST['sortBy'];
        }
        if (!empty($_POST['sortType'])) {
            $batchAttributes['sort_type'] = $_POST['sortType'];
        }

        if (!empty($batchAttributes)) {
            $data['batch_attributes'] = json_encode($batchAttributes, true);
        }
        $db->where('batch_id', $_POST['batchId']);
        $db->update($tableName, $data);
        $_SESSION['alertMsg'] = _translate("Batch Samples Position saved successfully.", true);
    }
    header("Location:batches.php?type=" . $_POST['type']);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
