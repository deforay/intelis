<?php

use App\Registries\AppRegistry;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$name = (string) ($_POST['name'] ?? '');
$stateId = $_POST['stateId'] ?? null;
$districtId = $_POST['districtId'] ?? null;
$facilityId = !empty($_POST['facilityId']) ? (int) base64_decode((string) $_POST['facilityId']) : null;

// A facility name has to be unique only within its province and district.
// A new ("other") province or district has no facilities yet, so nothing to check.
$taken = false;
if ($stateId !== 'other' && $districtId !== 'other') {
    $taken = $facilitiesService->isFacilityNameTakenInArea($name, $stateId, $districtId, $facilityId);
}

header('Content-Type: application/json');
echo json_encode(['taken' => $taken]);
