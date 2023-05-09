<?php

  

// 
require_once APPLICATION_PATH . '/header.php';
$tableName = "batch_details";
try {
    $labelOrder = '';
    if (isset($_POST['sortOrders']) && trim($_POST['sortOrders']) != '') {
        $xplodSortOrders = explode(",", $_POST['sortOrders']);
        $orderArray = [];
        for ($o = 0; $o < count($xplodSortOrders); $o++) {
            $orderArray[$o] = $xplodSortOrders[$o];
        }
        $labelOrder = json_encode($orderArray, JSON_FORCE_OBJECT);
        $data = array('label_order' => $labelOrder);
        $db = $db->where('batch_id', $_POST['batchId']);
        $db->update($tableName, $data);
        $_SESSION['alertMsg'] = "Batch position saved";
        header("Location:tb-batches.php");
    } else {
        header("Location:tb-batches.php");
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
