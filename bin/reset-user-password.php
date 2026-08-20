#!/usr/bin/env php
<?php

// bin/reset-user-password.php
// Reset a user's password (interactive picker when no login is given).
//
// Usage:
//   php bin/reset-user-password.php
//   php bin/reset-user-password.php --login amit
//   php bin/reset-user-password.php --login amit --generate
//   php bin/reset-user-password.php --login amit --password 'S3cretPass'
//   php bin/reset-user-password.php --login amit --generate --activate --force-reset

declare(strict_types=1);

use App\Utilities\MiscUtility;
use App\Utilities\DateUtility;
use App\Utilities\CliPickerUtility;
use App\Services\UsersService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use Hackzilla\PasswordGenerator\Generator\RequirementPasswordGenerator;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(CLI\ERROR);
}

// Same rule the UI enforces on the reset-password and add/edit user forms
const PASSWORD_RULE = '/^(?=.*[0-9])(?=.*[a-zA-Z])([a-zA-Z0-9!@#\$%\^\&*\)\(+=. _-]+){8,}$/';
const PASSWORD_RULE_TEXT = 'Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.';

$options = getopt("", ["login:", "password:", "generate", "activate", "force-reset", "help"]);

if (isset($options['help'])) {
    echo "Reset a user's password." . PHP_EOL . PHP_EOL;
    echo "Usage:" . PHP_EOL;
    echo "  php bin/reset-user-password.php [--login <login_id>] [--password <pwd> | --generate] [--activate] [--force-reset]" . PHP_EOL . PHP_EOL;
    echo "Options:" . PHP_EOL;
    echo "  --login <login_id>  Skip the picker and target this user" . PHP_EOL;
    echo "                      (the picker lists active users only)" . PHP_EOL;
    echo "  --password <pwd>    Set this password (otherwise prompted)" . PHP_EOL;
    echo "  --generate          Generate a random password and print it" . PHP_EOL;
    echo "  --activate          Also set the user status to active; required to" . PHP_EOL;
    echo "                      target an inactive user with --login" . PHP_EOL;
    echo "  --force-reset       Require the user to change the password at next login" . PHP_EOL;
    exit(CLI\OK);
}

function readUserInput(string $prompt = ''): ?string
{
    echo $prompt;
    $line = fgets(STDIN);
    return $line === false ? null : trim($line);
}

function readHiddenInput(string $prompt): ?string
{
    echo $prompt;
    system('stty -echo 2>/dev/null');
    $line = fgets(STDIN);
    system('stty echo 2>/dev/null');
    echo PHP_EOL;
    return $line === false ? null : trim($line);
}

function generatePassword(): string
{
    // Same profile as /includes/generate-password.php
    $generator = new RequirementPasswordGenerator();
    $generator
        ->setLength(16)
        ->setOptionValue(ComputerPasswordGenerator::OPTION_UPPER_CASE, true)
        ->setOptionValue(ComputerPasswordGenerator::OPTION_LOWER_CASE, true)
        ->setOptionValue(ComputerPasswordGenerator::OPTION_NUMBERS, true)
        ->setOptionValue(ComputerPasswordGenerator::OPTION_SYMBOLS, false)
        ->setMinimumCount(ComputerPasswordGenerator::OPTION_UPPER_CASE, 2)
        ->setMinimumCount(ComputerPasswordGenerator::OPTION_LOWER_CASE, 2)
        ->setMinimumCount(ComputerPasswordGenerator::OPTION_NUMBERS, 4);
    return $generator->generatePassword();
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

// ---- Pick the user -----------------------------------------------------

// Login requires status = 'active', so an inactive account is not something a
// password alone can fix. The picker leaves them out entirely; --login can
// still reach one, but only together with --activate.
$userQuery = "SELECT u.user_id, u.login_id, u.user_name, u.status, r.role_name
                FROM user_details u
                LEFT JOIN roles r ON r.role_id = u.role_id
                WHERE u.status = 'active'
                ORDER BY u.login_id";

if (!empty($options['login'])) {
    $requestedLogin = trim((string) $options['login']);
    $user = $db->rawQueryOne(
        "SELECT u.user_id, u.login_id, u.user_name, u.status, r.role_name
            FROM user_details u
            LEFT JOIN roles r ON r.role_id = u.role_id
            WHERE u.login_id = ?",
        [$requestedLogin]
    );
    if (empty($user)) {
        echo "Error: No user found with login ID '$requestedLogin'." . PHP_EOL;
        exit(CLI\ERROR);
    }
    if ($user['status'] !== 'active' && !isset($options['activate'])) {
        echo "Error: '$requestedLogin' is {$user['status']} and cannot sign in." . PHP_EOL;
        echo "Add --activate to reset the password and activate the account." . PHP_EOL;
        exit(CLI\ERROR);
    }
} else {
    $users = $db->rawQuery($userQuery);
    if (empty($users)) {
        echo "Error: No active users found." . PHP_EOL;
        exit(CLI\ERROR);
    }
    $user = CliPickerUtility::pick(
        $users,
        ['login_id', 'user_name', 'role_name'],
        'Select active user',
        'Login ID | Name | Role'
    );
    if ($user === null) {
        echo "No user selected. Aborting." . PHP_EOL;
        exit(CLI\OK);
    }
}

$loginId = trim((string) $user['login_id']);
// Some rows (remote-order facilities, for instance) carry no login ID at all.
// Naming such a user by it gives "Reset password for ''?", and the password
// itself is inert because there is nothing to sign in with.
$label = $loginId !== '' ? $loginId : trim((string) $user['user_name']);

echo PHP_EOL;
echo "  Login ID: " . ($loginId !== '' ? $loginId : '(none)') . PHP_EOL;
echo "  Name:     {$user['user_name']}" . PHP_EOL;
echo "  Role:     " . ($user['role_name'] ?? '-') . PHP_EOL;
echo "  Status:   {$user['status']}" . PHP_EOL;
echo PHP_EOL;

$newLoginId = null;
if ($loginId === '') {
    echo "This user has no login ID, so a password on its own leaves them unable" . PHP_EOL;
    echo "to sign in. Set one now, or press Enter to leave the account untouched." . PHP_EOL . PHP_EOL;

    while (true) {
        $entered = readUserInput("New login ID: ");
        if ($entered === null || trim($entered) === '') {
            echo "Aborting. No changes made." . PHP_EOL;
            exit(CLI\OK);
        }
        $entered = strtolower(trim($entered));

        // Same rule the add/edit user forms enforce
        if (!preg_match('/^[a-z0-9_-]+$/', $entered)) {
            echo "Login ID can only contain lowercase letters, numbers, hyphens (-) and underscores (_)." . PHP_EOL;
            continue;
        }

        // login_id carries a UNIQUE index; catching the collision here beats
        // letting the update fail after the password has already been decided.
        $taken = $db->rawQueryOne(
            "SELECT user_id FROM user_details WHERE login_id = ? AND user_id != ?",
            [$entered, $user['user_id']]
        );
        if (!empty($taken)) {
            echo "'$entered' is already taken by another user. Try another." . PHP_EOL;
            continue;
        }

        $newLoginId = $entered;
        $loginId = $entered;
        $label = $entered;
        break;
    }
    echo PHP_EOL;
}

// ---- Determine the new password ----------------------------------------

$generated = false;
if (!empty($options['password'])) {
    $password = (string) $options['password'];
    if (!preg_match(PASSWORD_RULE, $password)) {
        echo "Error: " . PASSWORD_RULE_TEXT . PHP_EOL;
        exit(CLI\ERROR);
    }
} elseif (isset($options['generate'])) {
    $password = generatePassword();
    $generated = true;
} else {
    $useGenerated = null;
    while ($useGenerated === null) {
        $answer = readUserInput("Generate a strong password automatically? [Y/n]: ");
        if ($answer === null) {
            echo "Aborting." . PHP_EOL;
            exit(CLI\OK);
        }
        $answer = strtolower(trim($answer));
        if ($answer === '' || $answer === 'y' || $answer === 'yes') {
            $useGenerated = true;
        } elseif ($answer === 'n' || $answer === 'no') {
            $useGenerated = false;
        } else {
            echo "Please answer y to generate one, or n to type your own." . PHP_EOL;
        }
    }

    if ($useGenerated === false) {
        do {
            $password = readHiddenInput("New password: ");
            if ($password === null || $password === '') {
                echo "Aborting." . PHP_EOL;
                exit(CLI\OK);
            }
            if (!preg_match(PASSWORD_RULE, $password)) {
                echo PASSWORD_RULE_TEXT . PHP_EOL;
                continue;
            }
            $confirm = readHiddenInput("Confirm password: ");
            if ($password !== $confirm) {
                echo "Passwords do not match. Try again." . PHP_EOL;
                continue;
            }
            break;
        } while (true);
    } else {
        $password = generatePassword();
        $generated = true;
    }
}

// ---- Confirm and apply -------------------------------------------------

// Fully-specified invocations stay non-interactive for scripting
$interactive = empty($options['login']) || (empty($options['password']) && !isset($options['generate']));
if ($interactive) {
    $answer = strtolower((string) (readUserInput("Reset password for '{$label}'? [y/N]: ") ?? ''));
    if ($answer !== 'y' && $answer !== 'yes') {
        echo "Aborting. No changes made." . PHP_EOL;
        exit(CLI\OK);
    }
}

$data = [
    'password' => $usersService->passwordHash($password),
    'updated_datetime' => DateUtility::getCurrentDateTime(),
];
if ($newLoginId !== null) {
    $data['login_id'] = $newLoginId;
}
if (isset($options['activate'])) {
    $data['status'] = 'active';
}
if (isset($options['force-reset'])) {
    $data['force_password_reset'] = 1;
}

$db->where('user_id', $user['user_id']);
$db->update('user_details', $data);

if ($db->getLastErrno() > 0) {
    echo "Error resetting password: " . $db->getLastError() . PHP_EOL;
    exit(CLI\ERROR);
}

MiscUtility::consoleSuccess('Password reset successfully.');
echo "  Login ID: " . ($loginId !== '' ? $loginId : '(none)') . ($newLoginId !== null ? '  (newly set)' : '') . PHP_EOL;
echo "  Name:     {$user['user_name']}" . PHP_EOL;
if (isset($options['activate'])) {
    echo "  Status:   active" . PHP_EOL;
}
if ($generated) {
    echo "  Password: $password" . PHP_EOL;
}
if (isset($options['force-reset'])) {
    echo PHP_EOL . "The user must change this password at next login." . PHP_EOL;
}
