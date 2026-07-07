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
        return $this->apply($output, $svc, $mysqli, $forms, $mode);
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
        string $mode
    ): int {
        $successful = [];
        $failed     = [];

        foreach ($forms as $f) {
            // Install always drops the legacy `_data__` triggers first, so a bare
            // `--apply install` self-heals a table whose legacy triggers were
            // never retired by the v2 cutover (e.g. user_details). Idempotent:
            // DROP IF EXISTS is a no-op where they're already gone.
            $statements = $mode === self::MODE_INSTALL
                ? [...$svc->buildDropLegacyTriggers($f['table']), ...$svc->buildTriggersFor($f['table'], $f['pk'])]
                : [...$svc->buildDropLegacyTriggers($f['table']), ...$svc->buildDropTriggersFor($f['table'])];

            $err = null;
            foreach ($statements as $sql) {
                if (!$mysqli->query($sql)) {
                    $err = $mysqli->error;
                    break;
                }
            }

            if ($err !== null) {
                $failed[] = ['table' => $f['table'], 'error' => $err];
                continue;
            }
            $successful[] = $f['table'];
        }

        $verb = $mode === self::MODE_INSTALL ? 'installed' : 'cleared';

        if ($failed === []) {
            $tableCount = count($successful);
            $summary    = $mode === self::MODE_INSTALL
                ? "{$tableCount} form table(s), " . ($tableCount * 3) . " triggers"
                : "{$tableCount} form table(s)";
            $output->writeln("Audit triggers {$verb} ({$summary}).");
            $output->writeln("  " . implode(', ', $successful));
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
