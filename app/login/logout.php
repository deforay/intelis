<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\SecurityService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Add event log before clearing session
$userName = $_SESSION['userName'] ?? 'Unknown';
$general->activityLog('log-out', "$userName logged out", 'user');

SecurityService::resetSession();

// Redirect to login page
header("Location: /login/login.php");
exit();
