<?php
$general = new \Vlsm\Models\General();
$tableName = "audit_form_vl";
$primaryKey = "revision";



if (session_status() == PHP_SESSION_NONE) {
     session_start();
}
//echo '<pre>'; print_r($_POST); 


/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$columns_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'vlsm' AND table_name='$auditTable'";
$col_result = $db->rawQuery($columns_sql);

$aColumns = $col_result;
$orderColumns = $col_result;

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;
/*
* Paging
*/
$sLimit = "";
if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
     $sOffset = $_POST['iDisplayStart'];
     $sLimit = $_POST['iDisplayLength'];
}

/*
* Ordering
*/
/*
$sOrder = "";
if (isset($_POST['iSortCol_0'])) {
     $sOrder = "";
     for ($i = 0; $i < intval($_POST['iSortingCols']); $i++) {
          if ($_POST['bSortable_' . intval($_POST['iSortCol_' . $i])] == "true") {
               $sOrder .= $orderColumns[intval($_POST['iSortCol_' . $i])] . "
               " . ($_POST['sSortDir_' . $i]) . ", ";
          }
     }
     $sOrder = substr_replace($sOrder, "", -2);
}
*/
/*
* Filtering
* NOTE this does not match the built-in DataTables filtering which does it
* word by word on any field. It's possible to do here, but concerned about efficiency
* on very large tables, and MySQL's regex functionality is very limited
*/

$sWhere = array();
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
     $searchArray = explode(" ", $_POST['sSearch']);
     $sWhereSub = "";
     foreach ($searchArray as $search) {
          $sWhereSub .= " (";
          $colSize = count($aColumns);

          for ($i = 0; $i < $colSize; $i++) {
               if ($i < $colSize - 1) {
                    $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
               } else {
                    $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
               }
          }
          $sWhereSub .= ")";
     }
     $sWhere[] = $sWhereSub;
}

/* Individual column filtering */
for ($i = 0; $i < count($aColumns); $i++) {
     if (isset($_POST['bSearchable_' . $i]) && $_POST['bSearchable_' . $i] == "true" && $_POST['sSearch_' . $i] != '') {
          $sWhere[] = $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
     }
}

/*
* SQL queries
* Get data to display
*/
$aWhere = '';
$sQuery = '';
if(isset($_POST['table_name']))
{
     $auditTable = $_POST['table_name'];
}
else
{
     $auditTable = "audit_form_vl";
}

if(isset($_POST['sample_code']))
{
     $sampleCode = $_POST['sample_code'];
}
else
{
     $sampleCode = 'VL062288148';
}

$sQuery = "SELECT * FROM $auditTable WHERE sample_code='$sampleCode'";

echo $sQuery;
$start_date = '';
$end_date = '';


//echo $sQuery;die;
$rResult = $db->rawQuery($sQuery);

/* Data set length after filtering */
$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];

/*
* Output
*/
$output = array(
     "sEcho" => intval($_POST['sEcho']),
     "iTotalRecords" => $iTotal,
     "iTotalDisplayRecords" => $iFilteredTotal,
     "aaData" => array()
);
     foreach ($rResult as $key => $aRow) {
          $row = array();
          for($i=0;$i<count($col_result);$i++)
          {
               $row[] = $aRow[$col_result[$i]['COLUMN_NAME']];
          }
     
          $output['aaData'][] = $row;
     }

echo json_encode($output);
