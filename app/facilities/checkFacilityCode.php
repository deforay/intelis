<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$raw = trim((string) ($_POST['code'] ?? ''));
$facilityId = !empty($_POST['facilityId']) ? base64_decode((string) $_POST['facilityId']) : null;

// Normalise exactly the way the save path does, so the live check can never
// disagree with what the server will actually store.
$normalized = $facilitiesService->sanitizeFacilityCode($raw);

$status = 'ok';     // ok | empty | invalid | taken
$available = true;

if ($raw === '') {
    // Code is optional, so an empty box is not an error.
    $status = 'empty';
} elseif ($normalized === '') {
    // Something was typed, but nothing usable (letters/digits) remained.
    $status = 'invalid';
    $available = false;
} else {
    // Case-insensitive uniqueness (matches the UNIQUE index collation),
    // excluding the facility currently being edited.
    $params = [$normalized];
    $exclude = '';
    if (!empty($facilityId)) {
        $exclude = ' AND facility_id != ?';
        $params[] = $facilityId;
    }
    $hit = $db->rawQuery("SELECT 1 FROM facility_details WHERE facility_code = ?$exclude LIMIT 1", $params);
    if (!empty($hit)) {
        $status = 'taken';
        $available = false;
    }
}

echo json_encode([
    'raw'        => $raw,
    'normalized' => $normalized,
    'status'     => $status,
    'available'  => $available,
]);
