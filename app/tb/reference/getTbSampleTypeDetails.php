<?php



$tableName = "r_tb_sample_type";
$primaryKey = "sample_id";

$aColumns = ['sample_name', 'status', 'status'];

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




$sQuery = "SELECT * FROM r_tb_sample_type";

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

$iFilteredTotal = (int) ($db->rawQueryOne("SELECT COUNT(*) AS total FROM r_tb_sample_type $sWhere")['total'] ?? 0);

/* Total data set length */
$aResultTotal = $db->rawQuery("select COUNT(sample_id) as total FROM r_tb_sample_type");
// $aResultTotal = $countResult->fetch_row();
//print_r($aResultTotal);
$iTotal = $aResultTotal[0]['total'];


$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

foreach ($rResult as $aRow) {
    $status = '<select class="form-control" name="status[]" id="' . $aRow['sample_id'] . '" title="' . _translate("Please select status") . '" onchange="updateStatus(this,\'' . $aRow['status'] . '\')">
               <option value="active" ' . ($aRow['status'] == "active" ? "selected=selected" : "") . '>' . _translate("Active") . '</option>
               <option value="inactive" ' . ($aRow['status'] == "inactive" ? "selected=selected" : "") . '>' . _translate("Inactive") . '</option>
               </select><br><br>';
    $row = [];
    $row[] = htmlspecialchars((string) ($aRow['sample_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $row[] = _isAllowed("tb-sample-type.php") && $general->isLISInstance() === false ? $status : htmlspecialchars((string) ($aRow['status'] ?? ''), ENT_QUOTES, 'UTF-8');
    $output['aaData'][] = $row;
}

echo json_encode($output);
