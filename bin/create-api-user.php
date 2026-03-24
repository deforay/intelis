#!/usr/bin/env php
<?php

// bin/create-api-user.php
// Creates an API-only user or resets the token for an existing one.
//
// Usage:
//   php bin/create-api-user.php --name "Interface Bot" --login interface-bot
//   php bin/create-api-user.php --name "Interface Bot" --login interface-bot --facility 1
//   php bin/create-api-user.php --login interface-bot --reset-token

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(CLI\ERROR);
}

use App\Utilities\MiscUtility;
use App\Services\ApiService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$options = getopt("", ["name:", "login:", "facility:", "reset-token"]);
$resetToken = isset($options['reset-token']);

if (empty($options['login']) || (!$resetToken && empty($options['name']))) {
    echo "Usage:" . PHP_EOL;
    echo "  Create:      php bin/create-api-user.php --name \"Display Name\" --login <login_id> [--facility <facility_id>]" . PHP_EOL;
    echo "  Reset token: php bin/create-api-user.php --login <login_id> --reset-token" . PHP_EOL;
    exit(CLI\ERROR);
}

$loginId = strtolower(trim($options['login']));
$userName = trim($options['name']);
$facilityId = !empty($options['facility']) ? (int) $options['facility'] : null;

if (!preg_match('/^[a-z0-9_-]+$/', $loginId)) {
    echo "Error: Login ID must contain only lowercase letters, numbers, hyphens, and underscores." . PHP_EOL;
    exit(CLI\ERROR);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$existing = $db->rawQueryOne("SELECT user_id, user_name, api_token FROM user_details WHERE login_id = ?", [$loginId]);

if ($resetToken) {
    // --- Reset token for existing user ---
    if (!$existing) {
        echo "Error: No user found with login ID '$loginId'." . PHP_EOL;
        exit(CLI\ERROR);
    }

    $apiToken = ApiService::generateAuthToken();
    $now = date('Y-m-d H:i:s');

    $db->rawQuery("UPDATE user_details SET api_token = ?, api_token_generated_datetime = ?, updated_datetime = ? WHERE login_id = ?", [
        $apiToken, $now, $now, $loginId
    ]);

    if ($db->getLastErrno() > 0) {
        echo "Error resetting token: " . $db->getLastError() . PHP_EOL;
        exit(CLI\ERROR);
    }

    echo "API token reset successfully." . PHP_EOL;
    echo PHP_EOL;
    echo "  User ID:   {$existing['user_id']}" . PHP_EOL;
    echo "  Name:      {$existing['user_name']}" . PHP_EOL;
    echo "  Login ID:  $loginId" . PHP_EOL;
    echo "  API Token: $apiToken" . PHP_EOL;
    echo PHP_EOL;
    echo "Use this token in the Authorization header:" . PHP_EOL;
    echo "  Authorization: Bearer $apiToken" . PHP_EOL;
} else {
    // --- Create new user ---
    if ($existing) {
        echo "Error: A user with login ID '$loginId' already exists (user_id: {$existing['user_id']})." . PHP_EOL;
        exit(CLI\ERROR);
    }

    $userId = MiscUtility::generateUUID();
    $apiToken = ApiService::generateAuthToken();
    $now = date('Y-m-d H:i:s');

    $data = [
        'user_id' => $userId,
        'user_name' => $userName,
        'login_id' => $loginId,
        'role_id' => 4, // API User role
        'status' => 'active',
        'app_access' => 'yes',
        'api_token' => $apiToken,
        'api_token_generated_datetime' => $now,
        'api_token_exipiration_days' => 0,
        'force_password_reset' => 0,
        'updated_datetime' => $now,
    ];

    $db->insert('user_details', $data);

    if ($db->getLastErrno() > 0) {
        echo "Error creating user: " . $db->getLastError() . PHP_EOL;
        exit(CLI\ERROR);
    }

    if ($facilityId !== null) {
        $db->insert('user_facility_map', [
            'user_id' => $userId,
            'facility_id' => $facilityId,
        ]);
    }

    echo "API user created successfully." . PHP_EOL;
    echo PHP_EOL;
    echo "  User ID:   $userId" . PHP_EOL;
    echo "  Name:      $userName" . PHP_EOL;
    echo "  Login ID:  $loginId" . PHP_EOL;
    echo "  API Token: $apiToken" . PHP_EOL;
    echo PHP_EOL;
    echo "Use this token in the Authorization header:" . PHP_EOL;
    echo "  Authorization: Bearer $apiToken" . PHP_EOL;
}
