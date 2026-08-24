#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * bin/setup/regenerate-audit-triggers.php
 *
 * Manage the audit triggers that record every form_* insert/update/delete
 * into the `audit_log` staging table.
 *
 *   IMPORTANT: this command only manages TRIGGERS. It does NOT alter, rebuild
 *   or touch any table or row of data — purely DDL on triggers.
 *
 * Usage:
 *   php bin/setup/regenerate-audit-triggers.php                  # dry-run (print SQL)
 *   php bin/setup/regenerate-audit-triggers.php form_vl          # dry-run, one form
 *   php bin/setup/regenerate-audit-triggers.php --apply install  # (re)install triggers
 *   php bin/setup/regenerate-audit-triggers.php --apply drop-all # drop all audit triggers
 *
 * Use -v / -vv for more detail. Default output is intentionally minimal —
 * `composer post-install` / `composer post-update` calls this on every deploy
 * and operators don't want a wall of text.
 */

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use App\Registries\ContainerRegistry;
use App\Services\AuditTriggerService;
use App\Services\DatabaseService;

require_once __DIR__ . '/../../bootstrap.php';

#[AsCommand(
    name: 'intelis:audit-triggers',
    description: 'Manage the audit triggers (TRIGGERS only — does not touch tables or data).'
)]
final class AuditTriggersCommand extends Command
{
    private const MODE_INSTALL  = 'install';
    private const MODE_DROP_ALL = 'drop-all';

    /** How long a trigger DDL waits for its metadata lock before giving up. */
    private const LOCK_WAIT_SECONDS = 30;

    /** MySQL: "Lock wait timeout exceeded; try restarting transaction". */
    private const ER_LOCK_WAIT_TIMEOUT = 1205;

    /** How many times a table blocked on a lock is retried before it fails. */
    private const LOCK_RETRIES = 3;

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'form',
                InputArgument::OPTIONAL,
                "Limit to one form_* table (e.g. 'form_vl'); applies to dry-run."
            )
            ->addOption(
                'apply',
                null,
                InputOption::VALUE_REQUIRED,
                "'install' (idempotent re-create) or 'drop-all'. Omit to dry-run."
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $mode = $input->getOption('apply');
        $only = $input->getArgument('form');

        if ($mode !== null && !in_array($mode, [self::MODE_INSTALL, self::MODE_DROP_ALL], true)) {
            $io->error("--apply must be 'install' or 'drop-all'.");
            return Command::FAILURE;
        }

        /** @var AuditTriggerService $svc */
        $svc = ContainerRegistry::get(AuditTriggerService::class);
        /** @var DatabaseService $db */
        $db = ContainerRegistry::get(DatabaseService::class);
        $mysqli = $db->mysqli();

        $forms = $this->resolveForms($svc, $only, $io);
        if ($forms === null) {
            return Command::FAILURE;
        }
        if ($forms === []) {
            return Command::SUCCESS;
        }

        if ($mode === self::MODE_INSTALL && !$svc->auditLogReady()) {
            $io->error('audit_log is not present yet — run pending migrations first.');
            return Command::FAILURE;
        }

        if ($mode === null) {
            return $this->dryRun($output, $svc, $forms);
        }
        // A run scoped to one table stays scoped to it; the orphan sweep is a
        // whole-schema operation and would be a surprise from a targeted run.
        return $this->apply($output, $svc, $mysqli, $forms, $mode, $only === null);
    }

    /**
     * @return list<array{table:string, pk:string}>|null  null = error already reported
     */
    private function resolveForms(AuditTriggerService $svc, ?string $only, SymfonyStyle $io): ?array
    {
        $forms = $svc->trackedTables();
        if ($only !== null) {
            $forms = array_values(array_filter($forms, static fn(array $f): bool => $f['table'] === $only));
            if ($forms === []) {
                $io->error("No tracked table matches '{$only}'.");
                return null;
            }
        }
        if ($forms === []) {
            $io->warning('No tracked tables found on this instance.');
        }
        return $forms;
    }

    /**
     * Dry-run: emit the SQL. Developers running this explicitly want to see
     * everything, so we don't truncate.
     *
     * @param list<array{table:string, pk:string}> $forms
     */
    private function dryRun(OutputInterface $output, AuditTriggerService $svc, array $forms): int
    {
        $output->writeln('-- dry-run: nothing applied. Re-run with `--apply install` to execute.');
        foreach ($forms as $f) {
            $output->writeln('');
            $output->writeln("-- {$f['table']}");
            $installSql = [...$svc->buildDropLegacyTriggers($f['table']), ...$svc->buildTriggersFor($f['table'], $f['pk'])];
            foreach ($installSql as $sql) {
                $output->writeln($sql . ';');
                $output->writeln('');
            }
        }
        return Command::SUCCESS;
    }

    /**
     * Apply install or drop-all. Minimal output on success; details on -v;
     * clear per-table errors on failure.
     *
     * @param list<array{table:string, pk:string}> $forms
     */
    private function apply(
        OutputInterface $output,
        AuditTriggerService $svc,
        \mysqli $mysqli,
        array $forms,
        string $mode,
        bool $sweepOrphans = true
    ): int {
        $successful = [];
        $failed     = [];

        // Bound the metadata-lock wait. MySQL's default lock_wait_timeout is
        // 31536000 seconds -- a year -- so a DDL that cannot get its lock does
        // not fail, it hangs, printing nothing, and every later query against
        // the same table queues behind it because locks are granted in request
        // order. That turned one slow background query on a busy instance into
        // an apparently frozen `composer post-update` and a form table no one
        // could read. Failing after 30 seconds says which table is blocked and
        // leaves the instance running.
        try {
            $mysqli->query('SET SESSION lock_wait_timeout = ' . self::LOCK_WAIT_SECONDS);
        } catch (\mysqli_sql_exception $e) {
            $output->writeln('<comment>Could not set lock_wait_timeout (' . $e->getMessage() . '); continuing with the server default.</comment>');
        }

        foreach ($forms as $f) {
            // Install always drops the legacy `_data__` triggers first, so a bare
            // `--apply install` self-heals a table whose legacy triggers were
            // never retired by the v2 cutover (e.g. user_details). Idempotent:
            // DROP IF EXISTS is a no-op where they're already gone.
            $statements = $mode === self::MODE_INSTALL
                ? [...$svc->buildDropLegacyTriggers($f['table']), ...$svc->buildTriggersFor($f['table'], $f['pk'])]
                : [...$svc->buildDropLegacyTriggers($f['table']), ...$svc->buildDropTriggersFor($f['table'])];

            // Named before the statement runs, not after. This is the only
            // thing an operator watching a stalled upgrade has to go on, and a
            // summary printed at the end is no use while it is still blocked.
            if ($output->isVerbose()) {
                $output->writeln("  {$f['table']}...");
            }

            // Retried, because a lock held by a query that is merely slow should
            // not abort an upgrade -- only one held by something genuinely stuck
            // should. Replaying a table from the top is safe: every statement is
            // a DROP ... IF EXISTS, or a CREATE preceded by its own drop.
            $err = null;
            for ($attempt = 1; $attempt <= self::LOCK_RETRIES; $attempt++) {
                // mysqli reports errors by exception here (MYSQLI_REPORT_ERROR |
                // MYSQLI_REPORT_STRICT, the default since PHP 8.1), so a failed
                // DDL never comes back as a false return -- checking one would
                // let the exception escape and abort post-update outright.
                $err   = null;
                $errno = 0;
                try {
                    foreach ($statements as $sql) {
                        $mysqli->query($sql);
                    }
                } catch (\mysqli_sql_exception $e) {
                    $err   = $e->getMessage();
                    $errno = $e->getCode();
                }

                if ($err === null || $errno !== self::ER_LOCK_WAIT_TIMEOUT) {
                    break;
                }

                if ($attempt < self::LOCK_RETRIES) {
                    $output->writeln(
                        "<comment>{$f['table']}: still waiting on a lock after "
                        . self::LOCK_WAIT_SECONDS . "s (attempt {$attempt} of "
                        . self::LOCK_RETRIES . "); retrying.</comment>"
                    );
                    continue;
                }

                $err .= ' -- another connection has held a lock on ' . $f['table']
                    . ' for over ' . (self::LOCK_WAIT_SECONDS * self::LOCK_RETRIES)
                    . 's. Find the long-running query or open transaction'
                    . ' (SHOW FULL PROCESSLIST) and re-run this once it has finished.';
            }

            if ($err !== null) {
                $failed[] = ['table' => $f['table'], 'error' => $err];
                continue;
            }
            $successful[] = $f['table'];
        }

        // Orphan sweep. The loop above only ever visits trackedTables(), so a
        // legacy trigger on anything else survives every run — which is why an
        // instance can report leftover `_data__` triggers immediately after a
        // successful `--apply install`. Asking the schema what is actually there
        // catches those, and needs no list to be kept up to date.
        $orphansDropped = 0;

        if ($sweepOrphans) {
            foreach ($svc->findLegacyTriggers() as $legacy) {
                try {
                    $mysqli->query($svc->buildDropLegacyTriggerByName($legacy['trigger']));
                    $orphansDropped++;
                } catch (\mysqli_sql_exception $e) {
                    $failed[] = ['table' => $legacy['trigger'], 'error' => $e->getMessage()];
                }
            }
        }

        $verb = $mode === self::MODE_INSTALL ? 'installed' : 'cleared';

        if ($failed === []) {
            $tableCount = count($successful);
            $summary    = $mode === self::MODE_INSTALL
                ? "{$tableCount} form table(s), " . ($tableCount * 3) . " triggers"
                : "{$tableCount} form table(s)";
            $output->writeln("Audit triggers {$verb} ({$summary}).");
            $output->writeln("  " . implode(', ', $successful));
            if ($orphansDropped > 0) {
                $output->writeln("Also dropped {$orphansDropped} orphaned legacy trigger(s) on untracked tables.");
            }
            return Command::SUCCESS;
        }

        $output->writeln("<error>Audit trigger {$verb} reported errors:</error>");
        foreach ($failed as $f) {
            $output->writeln("  {$f['table']}: {$f['error']}");
        }
        if ($successful !== []) {
            $output->writeln("(succeeded: " . implode(', ', $successful) . ")");
        }
        return Command::FAILURE;
    }
}

$application = new Application();
$application->addCommand(new AuditTriggersCommand());
$application->setDefaultCommand('intelis:audit-triggers', true);
$application->run();
