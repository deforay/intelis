<?php

use Laminas\Diactoros\ServerRequest;
use const COUNTRY\CAMEROON;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {

    // Sanitized values from $request object
    /** @var ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $tableName = "batch_details";
    $primaryKey = "batch_id";

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    /** @var UsersService $usersService */
    $usersService = ContainerRegistry::get(UsersService::class);

    $formTable = TestsService::getTestTableName($_POST['type']);
    $formId = (int) $general->getGlobalConfig('vl_form');

    // Check if test type is not set for any batch
    $db->where("IFNULL(test_type, '') = ''");
    $incompleteBatches = $db->getValue("batch_details", "batch_id", null);

    // Update test type for all batches in the current test type
    if (!empty($incompleteBatches)) {
        $incompleteBatches = implode(",", $incompleteBatches);
        $update = "UPDATE batch_details SET test_type = ? WHERE (test_type is NULL OR test_type = '') and batch_id in (SELECT DISTINCT sample_batch_id FROM $formTable WHERE sample_batch_id IN ($incompleteBatches))";
        $db->rawQuery($update, [$_POST['type']]);
    }

    $pdfLayout = $general->getGlobalConfig('batch_pdf_layout') ?? 'standard';

    $aColumns = ['b.batch_code', 'b.batch_code', 'b.lab_assigned_batch_code', null, "DATE_FORMAT(vl.sample_tested_datetime, '%d-%b-%Y')", "DATE_FORMAT(b.last_modified_datetime,'%d-%b-%Y %H:%i:%s')"];
    $orderColumns = ['b.batch_code', 'b.batch_code', 'b.lab_assigned_batch_code', null, 'last_tested_date', 'b.last_modified_datetime'];

    if ($formId != CAMEROON) {
        $aColumns = MiscUtility::removeMatchingElements($aColumns, ['b.lab_assigned_batch_code']);
        $orderColumns = MiscUtility::removeMatchingElements($orderColumns, ['b.lab_assigned_batch_code']);
    }

    $sOffset = $sLimit = null;
    if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
        $sOffset = $_POST['iDisplayStart'];
        $sLimit = $_POST['iDisplayLength'];
    }

    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

    $columnSearch = $general->multipleColumnSearch($_POST['sSearch'], $aColumns);

    $sWhere = [];
    if (!empty($columnSearch) && $columnSearch != '') {
        $sWhere[] = $columnSearch;
    }

    $sWhere[] = " b.test_type like '" . $_POST['type'] . "'";


    if (isset($_POST['testType']) && ($_POST['testType'] != "")) {
        $sWhere[] = " vl.test_type = '" . $_POST['testType'] . "'";
    }

    $testTypeCol = "";

    if (!empty($_POST['type']) && $_POST['type'] == 'generic-tests') {
        $testTypeCol = " vl.test_type, ";
    }

    if ($formTable === 'form_cd4') {
        $resultCondition = "(vl.cd4_result IS NOT NULL AND vl.cd4_result != '')";
    } else {
        $resultCondition = "(vl.result IS NOT NULL AND vl.result != '')";
    }

    $sQuery = "SELECT SUM(CASE
                    WHEN $resultCondition
                    OR IFNULL(vl.is_sample_rejected, 'no') = 'yes'
                    THEN 1 ELSE 0
                END) as `testcount`,
                MAX(vl.sample_tested_datetime) as last_tested_date,
                $testTypeCol
                b.request_created_datetime,
                b.last_modified_datetime,
                b.batch_code,
                b.lab_assigned_batch_code,
                b.batch_id,
                COUNT(vl.sample_code) AS total_samples
                FROM batch_details b
                LEFT JOIN $formTable vl ON vl.sample_batch_id = b.batch_id";

    if ($sWhere !== []) {
        $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
    }

    $sQuery .= ' GROUP BY b.batch_id';

    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
        $sQuery = "$sQuery ORDER BY $sOrder";
    }

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
    }

    [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

    $output = [
        "sEcho" => (int) $_POST['sEcho'],
        "iTotalRecords" => $resultCount,
        "iTotalDisplayRecords" => $resultCount,
        "aaData" => []
    ];
    $editBatch = $delete = $pdf = $editPosition = false;
    if (_isAllowed("/batch/edit-batch.php?type=" . $_POST['type'])) {
        $editBatch = true;
        $delete = true;
        $pdf = true;
        $editPosition = true;
    }
    if (!empty($_POST['type']) && $_POST['type'] == 'generic-tests') {
        $testTypeInfo = $general->getDataByTableAndFields("r_test_types", ["test_type_id", "test_standard_name", "test_loinc_code"], false, "test_status='active'");
        $testTypes = [];
        foreach ($testTypeInfo as $tests) {
            $testTypes[$tests['test_type_id']] = $tests['test_standard_name'];
        }
    }
    foreach ($rResult as $aRow) {
        $deleteBatch = '';
        $edit = '';
        $editPositionLink = '';
        if ($editBatch) {
            if (!empty($_POST['type']) && $_POST['type'] == 'generic-tests') {
                $edit = '<a href="edit-batch.php?type=' . $_POST['type'] . '&id=' . base64_encode((string) $aRow['batch_id']) . '&testType=' . base64_encode((string) $aRow['test_type']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>&nbsp;';
            } else {
                $edit = '<a href="edit-batch.php?type=' . $_POST['type'] . '&id=' . base64_encode((string) $aRow['batch_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>&nbsp;';
            }
        }
        if ($editPosition) {
            if (!empty($_POST['type']) && $_POST['type'] == 'generic-tests') {
                $editPositionLink = '<a href="edit-batch-position.php?type=' . $_POST['type'] . '&id=' . base64_encode((string) $aRow['batch_id']) . '&testType=' . base64_encode((string) $aRow['test_type']) . '" class="btn btn-default btn-xs" style="" title="' . _translate("Edit Position") . '"><em class="fa-solid fa-arrow-down-1-9"></em> ' . _translate("Edit Position") . '</a>';
            } else {
                $editPositionLink = '<a href="edit-batch-position.php?type=' . $_POST['type'] . '&id=' . base64_encode((string) $aRow['batch_id']) . '" class="btn btn-default btn-xs" style="" title="' . _translate("Edit Position") . '"><em class="fa-solid fa-arrow-down-1-9"></em> ' . _translate("Edit Position") . '</a>';
            }
        }
        if ($pdf) {
            $fullPdfUrl = '/batch/generate-batch-pdf.php';
            $compactPdfUrl = '/batch/generate-compact-batch-pdf.php';
            $batchId = MiscUtility::sqid((string) $aRow['batch_id']);

            if ($pdfLayout == 'compact') {
                $printBatchPdf = '';
            } else {
                $printBatchPdf = '<a href="' . $fullPdfUrl . '?type=' . $_POST['type'] . '&id=' . $batchId . '" target="_blank" rel="noopener" class="btn btn-info btn-xs" style="margin-right: 2px;" title="' . _translate("Batch PDF") . '"><em class="fa-solid fa-barcode"></em> ' . _translate("Batch PDF") . '</a>';
            }

            $printCompactPdf = '<a href="' . $compactPdfUrl . '?type=' . $_POST['type'] . '&id=' . $batchId . '" target="_blank" rel="noopener" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Compact Batch PDF") . '"><em class="fa-solid fa-table-columns"></em> ' . _translate("Compact Batch PDF") . '</a>';

            $printBarcode = trim($printBatchPdf . '&nbsp;' . $printCompactPdf);
        }

        if (($aRow['total_samples'] == 0 || $aRow['testcount'] == 0) && $delete) {
            $deleteBatch = '<a href="javascript:void(0);" class="btn btn-danger btn-xs" style="" title="' . _translate("Delete") . '" onclick="deleteBatchCode(\'' . base64_encode((string) $aRow['batch_id']) . '\',\'' . $aRow['batch_code'] . '\');"><em class="fa-solid fa-xmark"></em> ' . _translate("Delete") . '</a>';
        }

        $date = '';
        $lastDate = DateUtility::humanReadableDateFormat($aRow['last_tested_date'] ?? '');

        $row = [];
        $row[] = $aRow['batch_code'];
        if (!empty($_POST['type']) && $_POST['type'] == 'generic-tests') {
            $row[] = $testTypes[$aRow['test_type']];
        }
        if ($formId == CAMEROON) {
            $row[] = $aRow['lab_assigned_batch_code'];
        }
        $row[] = $aRow['total_samples'];
        $row[] = $aRow['testcount'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['last_tested_date'] ?? '', true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '', true);

        if ($editBatch || $editPosition || $pdf || $aRow['total_samples'] == 0 || $aRow['testcount'] == 0 || $delete) {
            $row[] = $edit . '&nbsp;' . $printBarcode . '&nbsp;' . $editPositionLink . '&nbsp;' . $deleteBatch;
        }

        $output['aaData'][] = $row;
    }
    echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
}
