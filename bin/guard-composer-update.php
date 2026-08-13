#!/usr/bin/env php
<?php

/**
 * bin/guard-composer-update.php — refuse `composer update` on a lab machine.
 *
 * Runs as composer's pre-update-cmd. Returning non-zero aborts the update
 * before anything is written.
 *
 * `composer update` resolves every dependency afresh, rewrites composer.lock,
 * and — unless --no-dev is passed, which nobody types by accident — installs the
 * dev toolchain. On a production instance that means three things at once: the
 * lock no longer matches the release the code came from, PHPUnit / PHPStan /
 * Rector / PHP_CodeSniffer land on a server that has no use for them, and
 * whatever the upstream packages released this morning is now running against a
 * lab's data without anyone having tested that combination. None of it is
 * announced, and the machine looks fine afterwards.
 *
 * It reached labs by an unhappy accident: /usr/local/bin/intelis used to be a
 * bare composer proxy, so on any machine that has not taken an upgrade since the
 * dispatcher shipped, `intelis update` — the command the guides now teach —
 * still means `composer update`.
 *
 * The guard cannot help those machines, whose composer.json predates it. It
 * stops the next one, and it stops the same command typed by hand, which is
 * every bit as damaging and rather more common.
 *
 * Allowed when either is true:
 *   - a .git directory sits at the project root, i.e. a developer's checkout,
 *     where updating dependencies is the entire point
 *   - INTELIS_ALLOW_COMPOSER_UPDATE=1 is set, for the rare deliberate case
 */

declare(strict_types=1);

$root = dirname(__DIR__);

if (is_dir($root . '/.git')) {
    exit(0);
}

if ((string) getenv('INTELIS_ALLOW_COMPOSER_UPDATE') === '1') {
    exit(0);
}

$tty    = function_exists('stream_isatty') && stream_isatty(STDERR);
$red    = $tty ? "\033[31m" : '';
$bold   = $tty ? "\033[1m" : '';
$reset  = $tty ? "\033[0m" : '';

fwrite(STDERR, <<<MESSAGE

  {$red}{$bold}Refusing to run `composer update` on this installation.{$reset}

  It would rewrite composer.lock to whatever the upstream packages released
  today, and install the development toolchain on a machine that serves a lab.
  Neither is announced, and the instance looks healthy afterwards.

  {$bold}To update InteLIS, which is almost certainly what was meant:{$reset}

      sudo intelis-update

  That fetches the current release, snapshots what is here so it can go back,
  and installs exactly the dependencies that release was built and tested with.

  If you genuinely meant to re-resolve dependencies here, say so explicitly:

      INTELIS_ALLOW_COMPOSER_UPDATE=1 composer update


MESSAGE);

exit(1);
