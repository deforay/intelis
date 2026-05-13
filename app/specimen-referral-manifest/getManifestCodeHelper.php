<?php

// Autocomplete helper for manifest codes from the `specimen_manifests` table.
// Accepts optional `module` (test type, e.g. vl/eid/covid19) and `manifestType`
// (e.g. collection/referral) to scope the suggestions.

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$search = trim((string) ($_POST['search'] ?? ''));
$module = trim((string) ($_POST['module'] ?? ''));
$manifestType = trim((string) ($_POST['manifestType'] ?? ''));

$db->where('manifest_code', '%' . $search . '%', 'LIKE');

if ($module !== '') {
    $db->where('module', $module);
}
if ($manifestType !== '') {
    $db->where('manifest_type', $manifestType);
}

$db->orderBy('last_modified_datetime', 'DESC');
$results = $db->get('specimen_manifests', 25, ['manifest_code']);

$options = array_values(array_filter(array_map(
    static fn($row) => $row['manifest_code'] ?? null,
    $results ?? []
)));

echo json_encode($options);
