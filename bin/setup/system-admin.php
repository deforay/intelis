<?php

//bin/setup/system-admin.php

use App\Services\UsersService;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;

require_once __DIR__ . "/../../bootstrap.php";

ini_set('memory_limit', -1);
set_time_limit(0);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$cliMode = PHP_SAPI === 'cli';

if (!$cliMode) {
    echo "This script can only be run from command line." . PHP_EOL;
    exit(CLI\ERROR);
}

/**
 * Function to read user input from command line
 */
function readUserInput($prompt = ''): string
{
    echo $prompt;
    $handle = fopen("php://stdin", "r");
    $input = trim(fgets($handle));
    fclose($handle);
    return $input;
}

/**
 * Function to read password input (without echo)
 */
function readPassword($prompt = ''): string
{
    echo $prompt;

    // Try to disable echo for password input
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows
        $password = rtrim(shell_exec('powershell -Command "$Password = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($Password))"'), "\n");
    } else {
        // Unix/Linux/Mac
        system('stty -echo');
        $password = trim(fgets(STDIN));
        system('stty echo');
        echo PHP_EOL; // New line after password input
    }

    return $password;
}

/**
 * Function to validate password strength
 */
function validatePassword($password): ?string
{
    if (strlen((string) $password) < 8) {
        return "Password must be at least 8 characters long.";
    }

    if (!preg_match('/[A-Z]/', (string) $password)) {
        return "Password must contain at least one uppercase letter.";
    }

    if (!preg_match('/[a-z]/', (string) $password)) {
        return "Password must contain at least one lowercase letter.";
    }

    if (!preg_match('/\d/', (string) $password)) {
        return "Password must contain at least one number.";
    }

    return null; // Valid password
}

echo "=== System Administrator Setup ===" . PHP_EOL;
echo PHP_EOL;

// Step 1: Get login ID
$loginId = '';
do {
    $loginId = readUserInput("Enter admin login ID: ");
    $loginId = trim((string) $loginId);

    if ($loginId === '' || $loginId === '0') {
        echo "Login ID cannot be empty. Please try again." . PHP_EOL;
        continue;
    }

    if (strlen($loginId) < 3) {
        echo "Login ID must be at least 3 characters long. Please try again." . PHP_EOL;
        continue;
    }

    break;
} while (true);

// Step 2: Check if login ID already exists
$existingAdmin = $db->rawQueryOne(
    "SELECT system_admin_login FROM system_admin WHERE system_admin_login = ?",
    [$loginId]
);

if ($existingAdmin) {
    echo PHP_EOL;
    MiscUtility::safeCliEcho("Admin with login ID '" . $loginId . "' already exists." . PHP_EOL);

    $resetPassword = readUserInput("Do you want to reset the password? (y/n) [n]: ");
    $resetPassword = strtolower(trim((string) $resetPassword));

    if ($resetPassword !== 'y' && $resetPassword !== 'yes') {
        echo "Operation cancelled." . PHP_EOL;
        exit(CLI\OK);
    }

    $isUpdate = true;
} else {
    MiscUtility::safeCliEcho("Creating new admin: " . $loginId . PHP_EOL);
    $isUpdate = false;
}

// Step 3: Get password with validation
echo PHP_EOL;
$password = '';
do {
    $password = readPassword("Enter password: ");

    if (empty($password)) {
        echo "Password cannot be empty. Please try again." . PHP_EOL;
        continue;
    }

    $validationError = validatePassword($password);
    if ($validationError) {
        echo $validationError . " Please try again." . PHP_EOL;
        continue;
    }

    break;
} while (true);

// Step 4: Confirm password
$confirmPassword = '';
do {
    $confirmPassword = readPassword("Confirm password: ");

    if ($password !== $confirmPassword) {
        echo "Passwords do not match. Please try again." . PHP_EOL;
        continue;
    }

    break;
} while (true);

// Step 5: Save to database
echo PHP_EOL;
echo "Saving admin credentials..." . PHP_EOL;

try {
    $hashedPassword = $usersService->passwordHash($password);

    if ($isUpdate) {
        // Update existing admin password
        $data = ['system_admin_password' => $hashedPassword];
        $db->where('system_admin_login', $loginId);
        $result = $db->update('system_admin', $data);

        if ($result) {
            MiscUtility::safeCliEcho("✓ Admin password updated successfully for: " . $loginId . PHP_EOL);
        } else {
            MiscUtility::safeCliEcho("✗ Failed to update admin password." . PHP_EOL);
            exit(CLI\ERROR);
        }
    } else {
        // Create new admin
        $insertData = [
            'system_admin_login' => $loginId,
            'system_admin_password' => $hashedPassword
        ];
        $result = $db->insert('system_admin', $insertData);

        if ($result) {
            MiscUtility::safeCliEcho("✓ Admin created successfully: " . $loginId . PHP_EOL);
        } else {
            MiscUtility::safeCliEcho("✗ Failed to create admin account." . PHP_EOL);
            exit(CLI\ERROR);
        }
    }
} catch (Exception $e) {
    LoggerUtility::logError(
        "Error creating/updating system admin: " . $e->getMessage(),
        [
            'login' => $loginId,
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString(),
        ]
    );
    echo "✗ Error saving admin credentials. Please check logs for details." . PHP_EOL;
    exit(CLI\ERROR);
}

echo PHP_EOL;
echo "=== System Administrator Setup Complete ===" . PHP_EOL;
echo PHP_EOL;
MiscUtility::safeCliEcho("Admin login: " . $loginId . PHP_EOL);
echo "You can now log in to the system administration panel." . PHP_EOL;
