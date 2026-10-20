<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Services\BatchService;
use App\Services\TbPoolService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\SecurityService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
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
    $labelOrder = '';
    if (isset($_POST['sortOrders']) && trim((string) $_POST['sortOrders']) !== '') {

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
            $counter = count($xplodSortOrders);
            for ($o = 0; $o < $counter; $o++) {
                $orderArray[$alphaNumeric[$o]] = $xplodSortOrders[$o];
            }
        } else {
            $counter = count($xplodSortOrders);
            for ($o = 0; $o < $counter; $o++) {
                $orderArray[$o] = $xplodSortOrders[$o];
            }
        }

        if ($orderArray !== []) {
            $counter = count($orderArray);
            for ($a = 0; $a < $counter; $a++) {
                if ($_POST !== [] && array_key_exists($orderArray[$a], $_POST)) {
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

        // A TB batch can pool its samples for GeneXpert, while its pools are not
        // fixed. A fixed batch keeps its pool size as well as its pools.
        $batchInfo = $db->rawQueryOne("SELECT * FROM batch_details WHERE batch_id = ?", [(int) $_POST['batchId']]) ?: [];
        $poolSize = null;
        /** @var TbPoolService $tbPoolService */
        $tbPoolService = ContainerRegistry::get(TbPoolService::class);
        if (($_POST['type'] ?? '') === 'tb' && isset($_POST['poolSize']) && !$tbPoolService->isBatchLocked($batchInfo)) {
            $poolSize = (int) $_POST['poolSize'];
            $batchAttributes['pool_size'] = $poolSize;
        }

        if ($batchAttributes !== []) {
            // The sort is replaced as posted. Everything else the batch holds, such as
            // its pool size and when its pools were printed, is kept.
            $existingAttributes = json_decode((string) ($batchInfo['batch_attributes'] ?? ''), true);
            if (is_array($existingAttributes)) {
                $batchAttributes += array_diff_key($existingAttributes, ['sort_by' => true, 'sort_type' => true]);
            }
            $data['batch_attributes'] = json_encode($batchAttributes, true);
        }
        $db->where('batch_id', $_POST['batchId']);
        $db->update($tableName, $data);
        $_SESSION['alertMsg'] = _translate("Batch Samples Position saved successfully.", true);

        if (($_POST['type'] ?? '') === 'tb' && isset($_POST['poolSize']) && $poolSize === null) {
            $_SESSION['alertMsg'] = _translate("Batch Samples Position saved. The pools were not changed because a worksheet with them was printed or a pool has a result.", true);
        } elseif ($poolSize !== null) {
            $pooled = $tbPoolService->assignPools(
                (int) $_POST['batchId'],
                (string) $batchInfo['batch_code'],
                TbPoolService::poolsPrintedAt($batchInfo),
                TbPoolService::sampleIdsInOrder($xplodSortOrders),
                $poolSize
            );
            if (!$pooled) {
                $_SESSION['alertMsg'] = _translate("Batch Samples Position saved. The pools were not changed because a worksheet with them was printed or a pool has a result.", true);
            }
        }
    }
    MiscUtility::redirect("batches.php?type=" . $_POST['type']);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    throw $e;
}
