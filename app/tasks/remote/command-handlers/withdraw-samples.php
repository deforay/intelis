<?php

// Command handler: withdraw-samples
//
// Queued by the STS when Move Manifest gives this lab's samples to another lab.
// Cancels this lab's copies, except any it has tested since its last sync, which
// are reported back untouched. See SampleWithdrawalService.
//
// Expected params:
//   - testType      (required) one of: vl, eid, covid19, hepatitis, tb, cd4, generic-tests
//   - movedToLabId  (required) the lab the samples now belong to
//   - uniqueIds     (required) the samples to cancel
//   - manifestCodes (optional) for the record only
//
// Returns an array that becomes the `result` field on the status row back at STS.

use App\Registries\ContainerRegistry;
use App\Services\SampleWithdrawalService;

/** @var array $params */
/** @var array $command */

$testType = (string) ($params['testType'] ?? '');
$movedToLabId = (int) ($params['movedToLabId'] ?? 0);
$uniqueIds = is_array($params['uniqueIds'] ?? null) ? $params['uniqueIds'] : [];

$validModules = ['vl', 'eid', 'covid19', 'hepatitis', 'tb', 'cd4', 'generic-tests'];
if (!in_array($testType, $validModules, true)) {
    return ['status' => 'failed', 'error' => 'Invalid testType: ' . $testType];
}
if ($movedToLabId <= 0 || $uniqueIds === []) {
    return ['status' => 'failed', 'error' => 'movedToLabId and uniqueIds are required'];
}

/** @var SampleWithdrawalService $withdrawals */
$withdrawals = ContainerRegistry::get(SampleWithdrawalService::class);
$outcome = $withdrawals->withdraw($testType, $uniqueIds, $movedToLabId);

return [
    'status' => 'completed',
    'manifestCodes' => is_array($params['manifestCodes'] ?? null) ? $params['manifestCodes'] : [],
] + $outcome;
