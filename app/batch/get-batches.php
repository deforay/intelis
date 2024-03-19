<?php

use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {

    $db->beginReadOnlyTransaction();

    // Sanitized values from $request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());

    $tableName = "batch_details";
    $primaryKey = "batch_id";

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    /** @var UsersService $usersService */
    $usersService = ContainerRegistry::get(UsersService::class);

    $refTable = TestsService::getTestTableName($_POST['type']);

    $formConfigQuery = "SELECT * from global_config where name='batch_pdf_layout'";
    $configResult = $db->query($formConfigQuery);
    $pdfLayout = '';
    if(!empty($configResult)){
        $pdfLayout = $configResult[0]['value'];
    }

    $aColumns = ['b.batch_code', 'b.batch_code', null, "DATE_FORMAT(vl.sample_tested_datetime, '%d-%b-%Y')", "DATE_FORMAT(b.last_modified_datetime,'%d-%b-%Y %H:%i:%s')"];
    $orderColumns = ['b.batch_code', 'b.batch_code', null, 'last_tested_date', 'b.last_modified_datetime'];

    $sOffset = $sLimit = null;
    if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
        $sOffset = $_POST['iDisplayStart'];
        $sLimit = $_POST['iDisplayLength'];
    }

    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

    $sWhere = $general->multipleColumnSearch($_POST['sSearch'], $aColumns);

    $sWhere[] = " b.test_type like '" . $_POST['type'] . "'";


    if (isset($_POST['testType']) && ($_POST['testType'] != "")) {
        $sWhere[] = " vl.test_type = '" . $_POST['testType'] . "'";
    }

    $testTypeCol = "";

    if (!empty($_POST['type']) && $_POST['type'] == 'generic-tests') {
        $testTypeCol = " vl.test_type, ";
    }

    $sQuery = "SELECT SUM(CASE WHEN vl.sample_tested_datetime is not null THEN 1 ELSE 0 END) as `testcount`,
                MAX(vl.sample_tested_datetime) as last_tested_date,
                $testTypeCol
                b.request_created_datetime,
                b.last_modified_datetime,
                b.batch_code,
                b.batch_id,
                COUNT(vl.sample_code) AS total_samples
                FROM batch_details b
                INNER JOIN $refTable vl ON vl.sample_batch_id = b.batch_id";

    if (!empty($sWhere)) {
        $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
    }

    $sQuery = $sQuery . ' GROUP BY b.batch_id';

    if (!empty($sOrder)) {
        $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
        $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
    }

    [$rResult, $resultCount] = $general->getQueryResultAndCount(sql: $sQuery, params: null, limit: $sLimit, offset: $sOffset, returnGenerator: true);

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
        $testTypeInfo = $general->getDataByTableAndFields("r_test_types", array("test_type_id", "test_standard_name", "test_loinc_code"), false, "test_status='active'");
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
                $editPositionLink = '<a href="edit-batch-position.php?type=' . $_POST['type'] . '&id=' . base64_encode((string) $aRow['batch_id']) . '&testType=' . base64_encode((string) $aRow['test_type']) . '" class="btn btn-default btn-xs" style="margin-right: 2px;margin-top:6px;" title="' . _translate("Edit Position") . '"><em class="fa-solid fa-arrow-down-1-9"></em> ' . _translate("Edit Position") . '</a>';
            } else {
                $editPositionLink = '<a href="edit-batch-position.php?type=' . $_POST['type'] . '&id=' . base64_encode((string) $aRow['batch_id']) . '" class="btn btn-default btn-xs" style="margin-right: 2px;margin-top:6px;" title="' . _translate("Edit Position") . '"><em class="fa-solid fa-arrow-down-1-9"></em> ' . _translate("Edit Position") . '</a>';
            }
        }
        if ($pdf) {
            if(!empty($pdfLayout) && $pdfLayout == 'compact'){
                $printBarcode = '<a href="/batch/generate-compact-batch-pdf.php?type=' . $_POST['type'] . '&id=' . base64_encode((string) $aRow['batch_id']) . '" target="_blank"  rel="noopener" class="btn btn-info btn-xs" style="margin-right: 2px;" title="' . _translate("Print Batch PDF") . '"><em class="fa-solid fa-barcode"></em> ' . _translate("Print Batch PDF") . '</a>';
            }else{
                $printBarcode = '<a href="/batch/generate-batch-pdf.php?type=' . $_POST['type'] . '&id=' . base64_encode((string) $aRow['batch_id']) . '" target="_blank"  rel="noopener" class="btn btn-info btn-xs" style="margin-right: 2px;" title="' . _translate("Print Batch PDF") . '"><em class="fa-solid fa-barcode"></em> ' . _translate("Print Batch PDF") . '</a>';
            }
        }

        if (($aRow['total_samples'] == 0 || $aRow['testcount'] == 0) && $delete) {
            $deleteBatch = '<a href="javascript:void(0);" class="btn btn-danger btn-xs" style="margin-right: 2px;margin-top:6px;" title="' . _translate("Delete") . '" onclick="deleteBatchCode(\'' . base64_encode((string) $aRow['batch_id']) . '\',\'' . $aRow['batch_code'] . '\');"><em class="fa-solid fa-xmark"></em> ' . _translate("Delete") . '</a>';
        }

        $date = '';
        $lastDate = DateUtility::humanReadableDateFormat($aRow['last_tested_date'] ?? '');

        $row = [];
        $row[] = $aRow['batch_code'];
        if (!empty($_POST['type']) && $_POST['type'] == 'generic-tests') {
            $row[] = $testTypes[$aRow['test_type']];
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
    echo MiscUtility::convertToUtf8AndEncode($output);

    $db->commitTransaction();
} catch (Exception $exc) {
    LoggerUtility::log('error', $exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
