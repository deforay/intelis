<?php

use App\Services\CommonService;



$general = new CommonService();
$tableName = "batch_details";
try {
    $labelOrder = '';
    if (isset($_POST['sortOrders']) && trim($_POST['sortOrders']) != '') {
        $xplodSortOrders = explode(",", $_POST['sortOrders']);
        $orderArray = [];
        if (isset($_POST['positions']) && $_POST['positions'] == 'alpha-numeric') {
            foreach ($general->excelColumnRange('A', 'H') as $value) {
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
        /* echo "<pre>";
        print_r($orderArray);
        die; */
        $labelOrder = json_encode($orderArray, JSON_FORCE_OBJECT);
        $data = array('label_order' => $labelOrder);
        $db = $db->where('batch_id', $_POST['batchId']);
        $db->update($tableName, $data);
        $_SESSION['alertMsg'] = "Batch Position saved successfully";
        header("Location:batchcode.php");
    } else {
        header("Location:batchcode.php");
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
