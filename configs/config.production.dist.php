<?php

$systemConfig = [];

// System Locale
$systemConfig['locale'] = 'en_US';

// STS URL
$systemConfig['remoteURL'] = '';

// Enable/Disable Modules
// true => Enabled
// false => Disabled
$systemConfig['modules']['vl'] = true;
$systemConfig['modules']['eid'] = true;
$systemConfig['modules']['covid19'] = true;
$systemConfig['modules']['hepatitis'] = false;
$systemConfig['modules']['tb'] = false;
$systemConfig['modules']['cd4'] = false;
$systemConfig['modules']['generic-tests'] = false;

$systemConfig['instance-name'] = '';

// Database Settings
$systemConfig['database']['host']       = '';
$systemConfig['database']['username']   = '';
$systemConfig['database']['password']   = '';
$systemConfig['database']['db']         = 'vlsm';
$systemConfig['database']['port']       = 3306;
$systemConfig['database']['charset']    = 'utf8mb4';

$systemConfig['tryCrypt'] = 'PUT-A-RANDOM-STRING-HERE';


$systemConfig['interfacing'] = [];

// Enable/Disable Interfacing
// true => Enabled
// false => Disabled
$systemConfig['interfacing']['enabled'] = false;

// Interfacing Database Details (not needed if above feature set to false)
$systemConfig['interfacing']['database']['host'] = '';
$systemConfig['interfacing']['database']['username'] = '';
$systemConfig['interfacing']['database']['password'] = '';
$systemConfig['interfacing']['database']['db'] = 'interfacing';
$systemConfig['interfacing']['database']['port'] = 3306;
$systemConfig['interfacing']['database']['charset'] = 'utf8mb4';

$systemConfig['interfacing']['sqlite3Path'] = '';


$systemConfig['recency'] = [];

// Enable/Disable Cross Login with Recency
// true => Enabled
// false => Disabled
$systemConfig['recency']['crosslogin'] = false;

// Domain URL of the Recency Web Application
$systemConfig['recency']['url'] = '';


// Enable/Disable Recency Viral Load tests sync
// true => Enabled
// false => Disabled
$systemConfig['recency']['vlsync'] = false;


// This Salt should match the Salt on Recency Web app
$systemConfig['recency']['crossloginSalt'] = "VALID LIBSODIUM KEY";


$systemConfig['system'] = [
    'debug_mode' => false, // set to true to enable debug mode
    'cache_di' => true, // set to true to enable DI Container caching
];


// Smart Connect enrollment key
//
// One key per Smart Connect deployment, shared by every laboratory in it. Read it
// from the Smart Connect server with `php bin/generate-enrollment-key.php --show`.
//
// It is used once, on the POST to /api/v2/enroll, to prove this installation is
// allowed to enroll. The per-lab token that comes back is what authenticates every
// later request; this key is never sent as a Bearer credential.
//
// It stays in the file config on purpose. It is not stored in the database and not
// in global_config, so an STS cannot push it out to the fleet and a database dump
// does not carry it.
$systemConfig['smart_connect']['enrollment_key'] = '';


// Security settings
$systemConfig['security'] = [
    // Enforce CSRF token validation on state-changing requests (POST/PUT/PATCH/DELETE).
    // true  => reject requests that do not carry a valid CSRF token (recommended)
    // false => legacy mode: only reject requests that send an *incorrect* token;
    //          requests with no token are allowed through. Use this to roll back
    //          quickly if a token-less form is discovered in production.
    'csrf_enforce' => true,

    // How long a logged-in user can sit idle before being sent back to the login
    // page, in seconds. The window slides: every request the user makes resets it,
    // so this only ever fires on a genuinely unattended screen.
    // Capped at session.gc_maxlifetime (php.ini, 28800 on a scripted install)
    // because PHP deletes the session file at that age regardless.
    'session_idle_timeout' => 14400, // 4 hours
];

return $systemConfig;
