#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * bin/lib/help.php — zero-dependency `--help` helper.
 *
 * Scripts call `bin_help_if_requested(__FILE__)` BEFORE bootstrap so `--help`
 * keeps working even when the DB / env / vendor is broken. Prints the leading
 * /** ... *\/ docblock and exits 0.
 *
 * Usage in a script:
 *
 *   require __DIR__ . '/lib/help.php';
 *   bin_help_if_requested(__FILE__);
 *   require_once __DIR__ . '/../bootstrap.php';
 */

function bin_help_if_requested(string $file, ?array $argv = null): void
{
    $argv ??= $GLOBALS['argv'] ?? [];
    foreach ($argv as $a) {
        if ($a === '--help' || $a === '-h') {
            echo bin_extract_docblock($file) . "\n";
            exit(0);
        }
    }
}

function bin_extract_docblock(string $file): string
{
    $src = @file_get_contents($file);
    if ($src === false) {
        return basename($file) . ' — (no docblock)';
    }

    if (preg_match('#/\*\*(.*?)\*/#s', $src, $m)) {
        $body = $m[1];
        $lines = preg_split('/\r?\n/', $body);
        $out = [];
        foreach ($lines as $line) {
            $out[] = preg_replace('/^\s*\*\s?/', '', $line);
        }
        return trim(implode("\n", $out));
    }

    return basename($file) . ' — (no docblock)';
}
