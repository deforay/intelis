<?php

declare(strict_types=1);

// A bootstrap both defines constants and requires files by definition, which is the one
// thing PSR1's side-effects rule asks a file not to do. The rule is right about ordinary
// files and cannot be satisfied by this one.
// phpcs:disable PSR1.Files.SideEffects

require_once dirname(__DIR__) . '/vendor/autoload.php';

// The application's constants -- sample statuses, country identifiers -- are plain
// namespaced consts rather than class constants, so the autoloader never sees them and
// any code path referencing one fatals in a unit test. Loading them here means a test
// can drive real service code instead of steering around it.
//
// constants.php resolves CORE\SYSADMIN_SECRET_KEY_FILE against VAR_PATH at load time, so
// that has to exist first. It points at a throwaway directory: nothing under test writes
// there, and a test that did would be reaching further than a unit test should.
// LegacyRequestHandler resolves a page against APPLICATION_PATH, and several
// utilities resolve paths against ROOT_PATH. bootstrap.php defines both; a test
// driving that code needs them to mean the same thing.
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!defined('APPLICATION_PATH')) {
    define('APPLICATION_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
}

if (!defined('VAR_PATH')) {
    define('VAR_PATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'intelis-tests-var');
}
if (!is_dir(VAR_PATH)) {
    mkdir(VAR_PATH, 0777, true);
}

// Derived from VAR_PATH in bootstrap.php. Anything that logs or caches on a failure
// path -- DatabaseService does both -- resolves these at call time, so a test that
// drives real service code needs them to exist.
if (!defined('LOG_PATH')) {
    define('LOG_PATH', VAR_PATH . DIRECTORY_SEPARATOR . 'logs');
}
if (!defined('CACHE_PATH')) {
    define('CACHE_PATH', VAR_PATH . DIRECTORY_SEPARATOR . 'cache');
}
foreach ([LOG_PATH, CACHE_PATH] as $path) {
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

// download.php serves out of exactly two roots and DownloadTokenUtility refuses to sign
// anything outside them, so a test that exercises a grant needs these to be real
// directories it can drop a file into.
if (!defined('WEB_ROOT')) {
    define('WEB_ROOT', VAR_PATH . DIRECTORY_SEPARATOR . 'public');
}
if (!defined('TEMP_PATH')) {
    define('TEMP_PATH', WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary');
}
if (!defined('VAR_TEMP_PATH')) {
    define('VAR_TEMP_PATH', VAR_PATH . DIRECTORY_SEPARATOR . 'temporary');
}
foreach ([TEMP_PATH, VAR_TEMP_PATH] as $path) {
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

require_once dirname(__DIR__) . '/app/system/constants.php';

// The app's translation helpers live in app/system/functions.php, which pulls in the
// whole framework on load. A unit test only needs _translate() to hand back the string
// it was given, so it is stubbed here rather than dragging the framework into the suite.
// Guarded, so if the real one is ever loaded first it wins.
if (!function_exists('_translate')) {
    function _translate(?string $text, bool|string $escapeTextOrContext = false): string
    {
        return (string) $text;
    }
}

// Same reasoning for the request helpers the swept endpoints call. The real
// _sanitizeInput() runs HTML Purifier; a test drives endpoints with plain values,
// so a passthrough keeps the framework out of the suite. _rawInput() mirrors the
// real lookup order: the registered request's parsed body, then $_POST.
if (!function_exists('_sanitizeInput')) {
    function _sanitizeInput(mixed $input, bool $nullifyEmptyStrings = false): mixed
    {
        return $input;
    }
}

// The AJAX guard the endpoints call. The real one asks UsersService, which the
// suite does not boot; this keeps the one rule a test relies on -- superadmin
// (role 1) always passes, anyone else needs the privilege in their session --
// so an endpoint under test still refuses a caller without the page's privilege.
if (!function_exists('_requirePrivilege')) {
    function _requirePrivilege(string $page, ?string $message = null): void
    {
        if ((int) ($_SESSION['roleId'] ?? 0) === 1 || isset($_SESSION['privileges'][$page])) {
            return;
        }
        throw new \App\Exceptions\SystemException(
            $message ?? 'You do not have permission to perform this action.',
            403
        );
    }
}

if (!function_exists('_rawInput')) {
    function _rawInput(string $key, mixed $default = null): mixed
    {
        try {
            $request = \App\Registries\AppRegistry::get('request');
        } catch (\Throwable) {
            $request = null;
        }
        $body = ($request instanceof \Psr\Http\Message\ServerRequestInterface)
            ? $request->getParsedBody()
            : null;

        if (!is_array($body)) {
            $body = $_POST ?? [];
        }

        return $body[$key] ?? $default;
    }
}

// The repository invalidates file-cache tags after reference writes; the real
// helper needs the container-built FileCacheUtility, which the suite does not
// boot. This stub records the tags instead, so a test can assert that a write
// actually invalidates what its entity declares.
if (!function_exists('_invalidateFileCacheByTags')) {
    function _invalidateFileCacheByTags($tags): void
    {
        $GLOBALS['__invalidatedCacheTags'][] = is_array($tags) ? $tags : [$tags];
    }
}
