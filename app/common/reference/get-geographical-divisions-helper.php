<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\UsersService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

    $db->beginReadOnlyTransaction();


    $tableName = "geographical_divisions";
    $primaryKey = "geo_id";

    $aColumns = array('geo_name', 'geo_code', 'geo_status');

    /* Indexed column (used for fast and accurate table cardinality) */
    $sIndexColumn = $primaryKey;

    $sTable = $tableName;

    $sOffset = $sLimit = null;
    if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
        $sOffset = $_POST['iDisplayStart'];
        $sLimit = $_POST['iDisplayLength'];
    }



    $sOrder = "";
    if (isset($_POST['iSortCol_0'])) {
        $sOrder = "";
        for ($i = 0; $i < (int) $_POST['iSortingCols']; $i++) {
            if ($_POST['bSortable_' . (int) $_POST['iSortCol_' . $i]] == "true") {
                $sOrder .= $aColumns[(int) $_POST['iSortCol_' . $i]] . "
				 	" . ($_POST['sSortDir_' . $i]) . ", ";
            }
        }
        $sOrder = substr_replace($sOrder, "", -2);
    }



    $sWhere = "";
    if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
        $searchArray = explode(" ", (string) $_POST['sSearch']);
        $sWhereSub = "";
        foreach ($searchArray as $search) {
            if ($sWhereSub == "") {
                $sWhereSub .= "(";
            } else {
                $sWhereSub .= " AND (";
            }
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
        $sWhere .= $sWhereSub;
    }




    $sQuery = "SELECT * FROM $tableName";

    if (!empty($sWhere)) {
        $sWhere = ' WHERE ' . $sWhere;
        $sQuery = $sQuery . ' ' . $sWhere;
    }

    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
        $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
    }

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
    }
    $rResult = $db->rawQuery($sQuery);
    /* Data set length after filtering */

    $aResultFilterTotal = $db->rawQuery("SELECT * FROM $tableName $sWhere order by $sOrder");
    $iFilteredTotal = count($aResultFilterTotal);

    /* Total data set length */
    $aResultTotal = $db->rawQuery("select COUNT($primaryKey) as total FROM $tableName");
    // $aResultTotal = $countResult->fetch_row();
    $iTotal = $aResultTotal[0]['total'];

    /* Output  */
    $output = array(
        "sEcho" => (int) $_POST['sEcho'],
        "iTotalRecords" => $iTotal,
        "iTotalDisplayRecords" => $iFilteredTotal,
        "aaData" => []
    );

    foreach ($rResult as $aRow) {
        $row = [];
        $row[] = ($aRow['geo_name']);
        $row[] = $aRow['geo_code'];
        $row[] = ($aRow['geo_status']);
        if (_isAllowed("edit-geographical-divisions.php") && $general->isLISInstance() === false) {
            $row[] = '<a href="edit-geographical-divisions.php?id=' . base64_encode((string) $aRow['geo_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
        }
        $output['aaData'][] = $row;
    }

    echo JsonUtility::encodeUtf8Json($output);

    $db->commitTransaction();
} catch (Exception $exc) {
    LoggerUtility::log('error', $exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
