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
    $pQuery = "SELECT count(*) as 'count'
            FROM form_eid
            WHERE child_id like '%$artNo%'
            OR child_name like '%$artNo%'
            OR child_surname like '%$artNo%'
            OR caretaker_phone_number like '%$artNo%'";
    $pResult = $db->rawQueryOne($pQuery);
    echo $pResult['count'];

} else if(!empty($motherNo) && $motherNo != '') {
    $count = 0;
    $pQuery = "SELECT count(*) as 'count'
            FROM form_eid
            WHERE mother_id like '%$motherNo%'
            OR mother_name like '%$motherNo%'
            OR mother_surname like '%$motherNo%'
            OR caretaker_address like '%$motherNo%'
            OR caretaker_phone_number like '%$motherNo%'";
    $pResult = $db->rawQueryOne($pQuery);
    echo $pResult['count'];
} else {
    echo '0';
}