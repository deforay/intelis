<?php



$tableName = "r_eid_sample_rejection_reasons";
$primaryKey = "rejection_reason_id";


$aColumns = ['rejection_reason_name', 'rejection_type', 'rejection_reason_code', 'rejection_reason_status'];

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;

// Sanitized values from $request object
/** @var \Psr\Http\Message\ServerRequestInterface $request */
$request = \App\Registries\AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$sOffset = $sLimit = null;
if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
    $sOffset = (int) $_POST['iDisplayStart'];
    $sLimit = (int) $_POST['iDisplayLength'];
}



$sOrder = (string) ($general->generateDataTablesSorting($_POST, $aColumns) ?? '');


$sWhere = (string) ($general->multipleColumnSearch($_POST['sSearch'] ?? null, $aColumns) ?? '');




$sQuery = "SELECT * FROM $tableName";

if ($sWhere !== '' && $sWhere !== '0') {
    $sWhere = ' WHERE ' . $sWhere;
    $sQuery = $sQuery . ' ' . $sWhere;
}

if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
//die($sQuery);
// echo $sQuery;
$rResult = $db->rawQuery($sQuery);
// print_r($rResult);
/* Data set length after filtering */

$iFilteredTotal = (int) ($db->rawQueryOne("SELECT COUNT(*) AS total FROM $tableName $sWhere")['total'] ?? 0);

/* Total data set length */
$aResultTotal = $db->rawQuery("select COUNT($primaryKey) as total FROM $tableName");
// $aResultTotal = $countResult->fetch_row();
//print_r($aResultTotal);
$iTotal = $aResultTotal[0]['total'];


$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

foreach ($rResult as $aRow) {
    $status = '<select class="form-control" name="status[]" id="' . $aRow['rejection_reason_id'] . '" title="' . _translate("Please select status") . '" onchange="updateStatus(this,\'' . $aRow['rejection_reason_status'] . '\')">
               <option value="active" ' . ($aRow['rejection_reason_status'] == "active" ? "selected=selected" : "") . '>' . _translate("Active") . '</option>
               <option value="inactive" ' . ($aRow['rejection_reason_status'] == "inactive" ? "selected=selected" : "") . '>' . _translate("Inactive") . '</option>
               </select><br><br>';
    $row = [];
    $row[] = htmlspecialchars((string) ($aRow['rejection_reason_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $row[] = htmlspecialchars((string) ($aRow['rejection_type'] ?? ''), ENT_QUOTES, 'UTF-8');
    $row[] = htmlspecialchars((string) ($aRow['rejection_reason_code'] ?? ''), ENT_QUOTES, 'UTF-8');
    if (_isAllowed("eid-sample-type.php") && $general->isLISInstance() === false) {
        $row[] = $status;
    } else {
        $row[] = htmlspecialchars((string) ($aRow['rejection_reason_status'] ?? ''), ENT_QUOTES, 'UTF-8');
    }
    $output['aaData'][] = $row;
}

echo json_encode($output);
