<?php

// Command handler: ping
//
// No-op self-test command. Just echoes back some diagnostic info about
// the LIS side so operators can verify:
//   - queue endpoint accepted the command
//   - courier picked it up
//   - dispatcher routed it
//   - status report made the round trip to STS
//
// Zero side effects. Safe to run at any time on any lab.
//
// Expected params: optional `note` (echoed back as-is, trimmed + capped).

use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var array $params */

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$note = isset($params['note']) ? mb_substr(trim((string) $params['note']), 0, 200) : '';

return [
    'status' => 'completed',
    'echoedAt' => date('c'),
    'version' => defined('VERSION') ? VERSION : null,
    'phpVersion' => PHP_VERSION,
    'instanceId' => $general->getInstanceId(),
    'labId' => $general->getSystemConfig('sc_testing_lab_id'),
    'note' => $note,
];
