<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$artNo = $_POST['childIdNo'] ?? $_POST['artPatientNo'] ?? '';
$motherNo = $_POST['motherNo'] ?? '';
if (!empty($artNo) && $artNo != '') {

    $count = 0;
    $searchTerm = '%' . $artNo . '%';
    $pQuery = "SELECT count(*) as 'count'
            FROM form_eid
            WHERE child_id like ?
            OR child_name like ?
            OR child_surname like ?
            OR caretaker_phone_number like ?";
    $pResult = $db->rawQueryOne($pQuery, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    echo $pResult['count'];

} else if(!empty($motherNo) && $motherNo != '') {
    $count = 0;
    $searchTerm = '%' . $motherNo . '%';
    $pQuery = "SELECT count(*) as 'count'
            FROM form_eid
            WHERE mother_id like ?
            OR mother_name like ?
            OR mother_surname like ?
            OR caretaker_address like ?
            OR caretaker_phone_number like ?";
    $pResult = $db->rawQueryOne($pQuery, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    echo $pResult['count'];
} else {
    echo '0';
}