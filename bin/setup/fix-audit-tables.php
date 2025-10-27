#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * bin/setup/fix-audit-tables.php
 *
 * Non-destructive audit table sync with Symfony Console output/progress.
 * - Derives form tables & PKs from TestsService (no hardcoding)
 * - Creates audit tables if missing: audit_<formTable>
 * - Aligns engine/collation (MyISAM, utf8mb4_0900_ai_ci)
 * - Ensures audit cols: action, revision, dt_datetime
 * - ADD/MODIFY/DROP columns in audit to match form (keeps data)
 * - Rebuilds triggers with per-key MAX(revision)+1
 *
 * Options:
 *   --only=form_vl,form_eid      Limit to specific form_* or audit_* tables
 *   --no-drop-extras             Do not drop columns that exist only in audit
 *   --rebuild-triggers-only      Skip schema sync; only (re)create triggers
 *   --skip-triggers              Do schema sync; skip triggers
 *   --dry-run                    Print SQL; don’t execute
 *
 * Verbosity:
 *   -v / -vv for more logs
 */

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\TestsService;

require_once __DIR__ . '/../../bootstrap.php';

#[AsCommand(
    name: 'intelis:fix-audit-tables',
    description: 'Sync audit_form_* tables with form_* (non-destructive) and rebuild triggers.'
)]
final class FixAuditTablesCommand extends Command
{
    private const CHARSET = 'utf8mb4';
    private const COLLATE = 'utf8mb4_0900_ai_ci';
    private const RESERVED_AUDIT_COLS = ['action', 'revision', 'dt_datetime'];

    /** @var DatabaseService */
    private $db;
    /** @var \mysqli */
    private $mysqli;
    private string $dbName;

    protected function configure(): void
    {
        $this
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Comma-separated form_* or audit_* tables to process.')
            ->addOption('no-drop-extras', null, InputOption::VALUE_NONE, 'Keep columns that exist only in audit.')
            ->addOption('rebuild-triggers-only', null, InputOption::VALUE_NONE, 'Only (re)create triggers.')
            ->addOption('skip-triggers', null, InputOption::VALUE_NONE, 'Skip trigger (re)creation.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print SQL only, don’t execute.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->db = ContainerRegistry::get(DatabaseService::class);
        if (!$this->db->isConnected()) {
            throw new RuntimeException('Database connection failed.');
        }
        $this->mysqli = $this->db->mysqli();
        $this->dbName = (string) (SYSTEM_CONFIG['database']['db'] ?? '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun             = (bool) $input->getOption('dry-run');
        $dropExtras         = !$input->getOption('no-drop-extras');
        $rebuildTriggersOnly = (bool) $input->getOption('rebuild-triggers-only');
        $skipTriggers       = (bool) $input->getOption('skip-triggers');

        $io->title('Audit table sync (non-destructive)');

        $tableMap = $this->buildTableMapFromTestsService();

        if ($only = $input->getOption('only')) {
            $wanted = array_map('trim', explode(',', $only));
            $tableMap = array_filter($tableMap, function ($info, $form) use ($wanted) {
                [$audit] = $info;
                return in_array($form, $wanted, true) || in_array($audit, $wanted, true);
            }, ARRAY_FILTER_USE_BOTH);
        }

        if (empty($tableMap)) {
            $io->warning('No matching form tables found to process.');
            return Command::SUCCESS;
        }

        $bar = new ProgressBar($output, count($tableMap));
        $bar->setFormat(' [%bar%] %percent%%  %current%/%max%  %message%');
        $bar->start();

        foreach ($tableMap as $form => [$audit, $pk]) {
            $bar->setMessage("Syncing $audit ⇐ $form");
            $bar->advance();

            $sqlBatch = [];
            try {
                if (!$dryRun) {
                    $this->mysqli->begin_transaction();
                }

                if (!$rebuildTriggersOnly) {
                    $sqlBatch = array_merge($sqlBatch, $this->ensureAuditTableExists($form, $audit, $pk));
                    $sqlBatch = array_merge($sqlBatch, $this->alignEngineAndCollation($audit));
                    $sqlBatch = array_merge($sqlBatch, $this->ensureAuditColumnsAndPK($audit, $pk));
                    $sqlBatch = array_merge($sqlBatch, $this->syncColumnsToMatchForm($form, $audit, $dropExtras, $pk));
                }

                if (!$skipTriggers) {
                    $sqlBatch = array_merge($sqlBatch, $this->rebuildTriggers($form, $audit, $pk));
                }

                if ($dryRun) {
                    $this->printSqlBatch($io, $form, $audit, $sqlBatch);
                } else {
                    $this->executeSqlBatch($sqlBatch);
                    $this->mysqli->commit();
                }
            } catch (\Throwable $e) {
                if (!$dryRun) {
                    $this->mysqli->rollback();
                }
                $io->error($e->getMessage());
            }
        }

        $bar->finish();
        $output->writeln('');
        $io->success('All requested audit tables checked and updated.');
        return Command::SUCCESS;
    }

    /** ---------- Helpers ---------- */

    /** @return array<string, array{0:string,1:string}> form_table => [audit_table, pk] */
    private function buildTableMapFromTestsService(): array
    {
        $types = TestsService::getTestTypes();
        $tmp = [];
        foreach ($types as $meta) {
            $form = $meta['tableName'] ?? null;
            $pk   = $meta['primaryKey'] ?? null;
            if (!$form || !$pk) continue;
            $exists = $this->db->rawQueryValue(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?",
                [$this->dbName, $form]
            );
            if ($exists) {
                $tmp[$form] = ['audit_' . $form, $pk];
            }
        }
        return $tmp;
    }

    private function listColumns(string $table): array
    {
        $rows = $this->db->rawQuery("SELECT COLUMN_NAME
              FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=? AND TABLE_NAME=?
             ORDER BY ORDINAL_POSITION", [$this->dbName, $table]);
        $out = [];
        foreach ($rows as $r) $out[$r['COLUMN_NAME']] = true;
        return $out;
    }

    /** Extract a single column's full DDL (backticked) from SHOW CREATE output. */
    private function getColumnDDL(string $table, string $column): ?string
    {
        $ddlMap = $this->parseColumnDDLs($this->showCreate($table));
        return $ddlMap[$column] ?? null;
    }

    /** Remove AUTO_INCREMENT token from a column DDL line. */
    private function stripAutoIncrementFromDDL(string $colDDL): string
    {
        // Example input: `id` int(11) NOT NULL AUTO_INCREMENT
        // Remove the AUTO_INCREMENT word (case-insensitive), keeping spacing sane.
        $ddl = preg_replace('/\s+AUTO_INCREMENT\b/i', '', $colDDL);
        return trim($ddl ?? $colDDL);
    }

    private function showCreate(string $table): string
    {
        $res = $this->mysqli->query("SHOW CREATE TABLE `{$this->dbName}`.`{$table}`");
        if (!$res) throw new RuntimeException("SHOW CREATE TABLE {$this->dbName}.{$table} failed: " . $this->mysqli->error);
        $row = $res->fetch_assoc();
        $res->free();
        foreach ($row as $k => $v) {
            if ($k !== 'Table' && $k !== 'View') return $v;
        }
        throw new RuntimeException("Unexpected SHOW CREATE TABLE result for {$this->dbName}.{$table}");
    }

    /** @return array<string, string> colName => full backticked DDL (trimmed trailing comma) */
    private function parseColumnDDLs(string $createSql): array
    {
        $cols = [];
        foreach (explode("\n", $createSql) as $line) {
            $t = trim($line);
            if (preg_match('/^`([^`]+)`\s+(.+)$/', $t, $m)) {
                $cols[$m[1]] = rtrim($t, ',');
            }
        }
        return $cols;
    }

    /** @return string[] */
    private function ensureAuditTableExists(string $form, string $audit, string $pk): array
    {
        $sql = [];
        $exists = $this->db->rawQueryValue(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?",
            [$this->dbName, $audit]
        );
        if ($exists) return $sql;

        // Create and align
        $sql[] = "CREATE TABLE `{$this->dbName}`.`$audit` LIKE `{$this->dbName}`.`$form`";
        $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ENGINE=MyISAM";
        $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` CONVERT TO CHARACTER SET " . self::CHARSET . " COLLATE " . self::COLLATE;

        // Remove AUTO_INCREMENT from copied pk BEFORE touching PKs
        $auditPkDDL = $this->getColumnDDL($audit, $pk) ?? $this->getColumnDDL($form, $pk);
        if ($auditPkDDL && stripos($auditPkDDL, 'AUTO_INCREMENT') !== false) {
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` MODIFY COLUMN " . $this->stripAutoIncrementFromDDL($auditPkDDL);
        }

        // Add audit columns
        $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN `action` VARCHAR(8) NOT NULL DEFAULT 'insert' FIRST";
        $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN `revision` INT NOT NULL AFTER `action`";
        $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`";

        // Switch PK only if not already (<pk>, revision) — on brand-new LIKE it will be just <pk>
        $currentPk = $this->getPrimaryKeyColumns($audit);
        if ($currentPk !== [$pk, 'revision']) {
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` DROP PRIMARY KEY";
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD PRIMARY KEY (`$pk`,`revision`)";
        }

        return $sql;
    }



    /** @return string[] */
    private function alignEngineAndCollation(string $audit): array
    {
        return [
            "ALTER TABLE `{$this->dbName}`.`$audit` ENGINE=MyISAM",
            "ALTER TABLE `{$this->dbName}`.`$audit` CONVERT TO CHARACTER SET " . self::CHARSET . " COLLATE " . self::COLLATE,
        ];
    }

    private function getPrimaryKeyColumns(string $table): array
    {
        $rows = $this->db->rawQuery(
            "SELECT k.COLUMN_NAME
           FROM information_schema.TABLE_CONSTRAINTS t
           JOIN information_schema.KEY_COLUMN_USAGE k
             ON k.CONSTRAINT_NAME = t.CONSTRAINT_NAME
            AND k.TABLE_SCHEMA = t.TABLE_SCHEMA
            AND k.TABLE_NAME = t.TABLE_NAME
          WHERE t.TABLE_SCHEMA = ?
            AND t.TABLE_NAME = ?
            AND t.CONSTRAINT_TYPE = 'PRIMARY KEY'
          ORDER BY k.ORDINAL_POSITION",
            [$this->dbName, $table]
        );
        return array_map(static fn($r) => $r['COLUMN_NAME'], $rows ?? []);
    }




    /** @return string[] */
    private function ensureAuditColumnsAndPK(string $audit, string $pk): array
    {
        $sql = [];

        // Ensure audit columns
        $have = $this->listColumns($audit);
        if (!isset($have['action']))      $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN `action` VARCHAR(8) NOT NULL DEFAULT 'insert' FIRST";
        if (!isset($have['revision']))    $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN `revision` INT NOT NULL AFTER `action`";
        if (!isset($have['dt_datetime'])) $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`";

        // Strip AUTO_INCREMENT on pk if present (safe even when PK already composite)
        $auditPkDDL = $this->getColumnDDL($audit, $pk);
        if ($auditPkDDL && stripos($auditPkDDL, 'AUTO_INCREMENT') !== false) {
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` MODIFY COLUMN " . $this->stripAutoIncrementFromDDL($auditPkDDL);
        }

        // Only rebuild PK if it isn't exactly (<pk>, revision)
        $currentPk = $this->getPrimaryKeyColumns($audit);
        if ($currentPk !== [$pk, 'revision']) {
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` DROP PRIMARY KEY";
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD PRIMARY KEY (`$pk`,`revision`)";
        }

        return $sql;
    }



    /** @return string[] */
    private function syncColumnsToMatchForm(string $form, string $audit, bool $dropExtras, string $pk): array
    {
        $sql = [];
        $formCreate  = $this->showCreate($form);
        $auditCreate = $this->showCreate($audit);

        $formCols  = $this->parseColumnDDLs($formCreate);
        $auditCols = $this->parseColumnDDLs($auditCreate);

        // ADD missing (strip AI if it's the pk)
        foreach ($formCols as $col => $ddl) {
            if (in_array($col, self::RESERVED_AUDIT_COLS, true)) continue;
            if (!array_key_exists($col, $auditCols)) {
                $addDDL = ($col === $pk) ? $this->stripAutoIncrementFromDDL($ddl) : $ddl;
                $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN $addDDL";
            }
        }

        // MODIFY mismatches (compare with AI stripped for pk)
        foreach ($formCols as $col => $ddl) {
            if (in_array($col, self::RESERVED_AUDIT_COLS, true)) continue;
            if (!array_key_exists($col, $auditCols)) continue;

            $lhs = ($col === $pk) ? $this->stripAutoIncrementFromDDL($ddl) : $ddl;           // desired
            $rhs = ($col === $pk) ? $this->stripAutoIncrementFromDDL($auditCols[$col]) : $auditCols[$col]; // current

            if ($lhs !== $rhs) {
                $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` MODIFY COLUMN $lhs";
            }
        }

        if ($dropExtras) {
            foreach ($auditCols as $col => $_) {
                if (in_array($col, self::RESERVED_AUDIT_COLS, true)) continue;
                if (!array_key_exists($col, $formCols)) {
                    $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` DROP COLUMN `$col`";
                }
            }
        }

        return $sql;
    }



    /** @return string[] */
    private function rebuildTriggers(string $form, string $audit, string $pk): array
    {
        $mk = function (string $suffix, string $timing, string $rowRef, string $action) use ($form, $audit, $pk): string {
            $pkRef = ($rowRef === 'OLD') ? "OLD.`$pk`" : "NEW.`$pk`";
            return <<<SQL
DROP TRIGGER IF EXISTS `{$form}_data__{$suffix}`;
CREATE TRIGGER `{$form}_data__{$suffix}` $timing ON `{$this->dbName}`.`$form`
FOR EACH ROW
BEGIN
  DECLARE next_rev INT;
  SELECT COALESCE(MAX(`revision`),0)+1 INTO next_rev
    FROM `{$this->dbName}`.`$audit` WHERE `$pk` = $pkRef;

  INSERT INTO `{$this->dbName}`.`$audit`
    SELECT '$action', next_rev, NOW(), d.*
      FROM `{$this->dbName}`.`$form` AS d
     WHERE d.`$pk` = $pkRef;
END
SQL;
        };

        $block = "DELIMITER $$\n" .
            $mk('ai', 'AFTER INSERT', 'NEW', 'insert') . " $$\n" .
            $mk('au', 'AFTER UPDATE', 'NEW', 'update') . " $$\n" .
            $mk('bd', 'BEFORE DELETE', 'OLD', 'delete') . " $$\n" .
            "DELIMITER ;";
        return [$block];
    }

    /** @param string[] $sqlBatch */
    private function executeSqlBatch(array $sqlBatch): void
    {
        foreach ($sqlBatch as $sql) {
            if (str_starts_with($sql, 'DELIMITER')) {
                $normalized = $this->normalizeDelimiterBlock($sql);
                if (!$this->mysqli->multi_query($normalized)) {
                    throw new RuntimeException('Trigger rebuild failed: ' . $this->mysqli->error);
                }
                while ($this->mysqli->more_results() && $this->mysqli->next_result()) { /* drain */
                }
            } else {
                if (!$this->mysqli->query($sql)) {
                    throw new RuntimeException('SQL failed: ' . $this->mysqli->error . ' (' . $sql . ')');
                }
            }
        }
    }

    private function printSqlBatch(SymfonyStyle $io, string $form, string $audit, array $sqlBatch): void
    {
        $io->section("$audit ⇐ $form");
        foreach ($sqlBatch as $sql) {
            if (str_starts_with($sql, 'DELIMITER')) {
                $io->writeln('<comment>-- trigger block --</comment>');
            } else {
                $io->writeln($sql . ';');
            }
        }
        $io->newLine();
    }

    private function normalizeDelimiterBlock(string $block): string
    {
        $block = str_replace(["\r\n", "\r"], "\n", $block);
        $lines = explode("\n", $block);
        $buf = [];
        $curr = [];
        $inBlock = false;

        foreach ($lines as $ln) {
            $t = trim($ln);
            if ($t === 'DELIMITER $$') {
                $inBlock = true;
                continue;
            }
            if ($t === 'DELIMITER ;') {
                $inBlock = false;
                continue;
            }
            if ($inBlock && str_ends_with($t, '$$')) {
                $curr[] = rtrim($t, '$$');
                $buf[] = implode("\n", $curr);
                $curr = [];
            } else {
                $curr[] = $ln;
            }
        }
        return implode(";\n", array_map(static fn($s) => trim($s, " \n;"), $buf)) . ";";
    }
}

/** Run the single-file console app */
$application = new Application('InteLIS Tools', '1.0');
$application->add(new FixAuditTablesCommand());
$application->setDefaultCommand('intelis:fix-audit-tables', true);
$application->run();
