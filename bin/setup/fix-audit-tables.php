#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * bin/setup/fix-audit-tables.php
 *
 * Non-destructive audit table sync with Symfony Console output/progress.
 * - Derives form tables & PKs from TestsService (no hardcoding)
 * - Creates audit tables if missing: audit_<formTable>
 * - Aligns engine/collation (InnoDB, utf8mb4_0900_ai_ci)
 * - Ensures audit cols: action, revision, dt_datetime
 * - ADD/MODIFY/DROP columns in audit to match form (keeps data)
 * - Rebuilds triggers with per-key MAX(revision)+1
 * - Detects crashed audit tables and recreates them
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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\TestsService;
use App\Utilities\MiscUtility;

require_once __DIR__ . '/../../bootstrap.php';

#[AsCommand(
    name: 'intelis:fix-audit-tables',
    description: 'Sync audit_form_* tables with form_* (non-destructive) and rebuild triggers.'
)]
final class FixAuditTablesCommand extends Command
{
    private const string CHARSET = 'utf8mb4';
    private const string COLLATE_MYSQL8 = 'utf8mb4_0900_ai_ci';
    private const string COLLATE_LEGACY = 'utf8mb4_unicode_ci';
    private const string ENGINE = 'InnoDB';
    private const array RESERVED_AUDIT_COLS = ['action', 'revision', 'dt_datetime'];

    /** @var DatabaseService */
    private $db;
    /** @var \mysqli */
    private $mysqli;
    private string $dbName;
    private string $collation;

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Comma-separated form_* or audit_* tables to process.')
            ->addOption('no-drop-extras', null, InputOption::VALUE_NONE, 'Keep columns that exist only in audit.')
            ->addOption('rebuild-triggers-only', null, InputOption::VALUE_NONE, 'Only (re)create triggers.')
            ->addOption('skip-triggers', null, InputOption::VALUE_NONE, 'Skip trigger (re)creation.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print SQL only, do not execute.');
    }

    #[\Override]
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->db = ContainerRegistry::get(DatabaseService::class);
        if (!$this->db->isConnected()) {
            throw new RuntimeException('Database connection failed.');
        }
        $this->mysqli = $this->db->mysqli();
        $this->dbName = (string) (SYSTEM_CONFIG['database']['db'] ?? '');
        $this->collation = $this->db->isMySQL8OrHigher()
            ? self::COLLATE_MYSQL8
            : self::COLLATE_LEGACY;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $dropExtras = !$input->getOption('no-drop-extras');
        $rebuildTriggersOnly = (bool) $input->getOption('rebuild-triggers-only');
        $skipTriggers = (bool) $input->getOption('skip-triggers');

        $io->title('Audit table sync (non-destructive)');

        $tableMap = $this->buildTableMapFromTestsService();

        if ($only = $input->getOption('only')) {
            $wanted = array_map('trim', explode(',', (string) $only));
            $tableMap = array_filter($tableMap, function ($info, $form) use ($wanted): bool {
                [$audit] = $info;
                return in_array($form, $wanted, true) || in_array($audit, $wanted, true);
            }, ARRAY_FILTER_USE_BOTH);
        }

        if ($tableMap === []) {
            $io->warning('No matching form tables found to process.');
            return Command::SUCCESS;
        }

        $bar = MiscUtility::spinnerStart(
            count($tableMap),
            'Syncing audit tables…',
            '█',
            '░',
            '█',
            'cyan'
        );

        foreach ($tableMap as $form => [$audit, $pk]) {
            /**
             * Flow for each form table (ref: sql/audit-triggers.sql):
             * 1. Check if audit table exists
             * 2. If not exists -> create table with columns + primary key (pk, revision)
             *    (no sync needed - CREATE TABLE LIKE copies structure from form table)
             * 3. If exists -> sync columns, engine, collation to match form table
             * 4. Recreate triggers (always, to ensure they exist and are current)
             */
            $sqlBatch = [];
            $actions = [];
            try {
                if ($this->tableExists($audit) && $this->isTableCrashed($audit)) {
                    MiscUtility::spinnerPausePrint($bar, fn() => $io->warning("$audit is marked as crashed; dropping and recreating."));
                    $actions[] = 'recreated (crashed)';
                    $sqlBatch = $this->recreateAuditTable($form, $audit, $pk);
                    if ($dryRun) {
                        $this->printSqlBatch($io, $form, $audit, $sqlBatch);
                    } else {
                        $this->executeSqlBatch($sqlBatch);
                    }
                    $sqlBatch = [];
                }

                if (!$dryRun) {
                    $this->mysqli->begin_transaction();
                }

                // Phase 1: Schema sync (create table if missing, sync columns if exists)
                if (!$rebuildTriggersOnly) {
                    $auditExists = $this->tableExists($audit);
                    $schemaSql = [
                        ...$this->ensureAuditTableExists($form, $audit, $pk),
                        ...($auditExists ? $this->removeAutoIncrementFromAudit($audit) : []),
                        ...($auditExists ? $this->alignEngineAndCollation($audit) : []),
                        ...($auditExists ? $this->ensureAuditColumnsAndPK($audit, $pk) : []),
                        ...($auditExists ? $this->syncColumnsToMatchForm($form, $audit, $dropExtras, $pk) : [])
                    ];
                    if ($schemaSql !== []) {
                        $sqlBatch = [...$sqlBatch, ...$schemaSql];
                        $actions[] = 'schema synced';
                    }

                    // Execute schema changes first
                    if ($dryRun) {
                        if ($sqlBatch !== []) {
                            $this->printSqlBatch($io, $form, $audit, $sqlBatch);
                        }
                    } else {
                        if ($sqlBatch !== []) {
                            $this->executeSqlBatch($sqlBatch);
                        }
                    }
                    $sqlBatch = [];
                }

                // Phase 2: Triggers (only after audit table confirmed to exist)
                if (!$skipTriggers && $this->tableExists($audit)) {
                    $triggerSql = $this->rebuildTriggers($form, $audit, $pk);
                    if ($triggerSql !== []) {
                        $sqlBatch = [...$sqlBatch, ...$triggerSql];
                        $actions[] = 'triggers rebuilt';
                    }

                    if ($dryRun) {
                        if ($sqlBatch !== []) {
                            $this->printSqlBatch($io, $form, $audit, $sqlBatch);
                        }
                    } else {
                        if ($sqlBatch !== []) {
                            $this->executeSqlBatch($sqlBatch);
                        }
                    }
                }

                if (!$dryRun) {
                    $this->mysqli->commit();
                }

                if ($actions !== []) {
                    $actionsText = implode(', ', $actions);
                    MiscUtility::spinnerPausePrint($bar, function () use ($output, $audit, $actionsText): void {
                        $output->writeln("  $audit: $actionsText");
                    });
                }
                MiscUtility::spinnerAdvance($bar, 1);
            } catch (\Throwable $e) {
                if (!$dryRun) {
                    $this->mysqli->rollback();
                }
                MiscUtility::spinnerPausePrint($bar, fn() => $io->error($e->getMessage()));
                MiscUtility::spinnerAdvance($bar, 1);
            }
        }

        MiscUtility::spinnerFinish($bar);
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
            $pk = $meta['primaryKey'] ?? null;
            if (!$form || !$pk) {
                continue;
            }
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
        foreach ($rows as $r)
            $out[$r['COLUMN_NAME']] = true;
        return $out;
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->db->rawQueryValue(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?",
            [$this->dbName, $table]
        );
    }

    private function isTableCrashed(string $table): bool
    {
        // Use QUICK check to avoid slow full table scan on large InnoDB tables
        $res = $this->mysqli->query("CHECK TABLE `{$this->dbName}`.`$table` QUICK");
        if (!$res) {
            return false;
        }
        while ($row = $res->fetch_assoc()) {
            $msgType = strtolower((string) ($row['Msg_type'] ?? ''));
            $msgText = strtolower((string) ($row['Msg_text'] ?? ''));
            if ($msgType === 'error' || str_contains($msgText, 'crash') || str_contains($msgText, 'corrupt')) {
                $res->free();
                return true;
            }
        }
        $res->free();
        return false;
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
        if (!$res) {
            throw new RuntimeException("SHOW CREATE TABLE {$this->dbName}.{$table} failed: " . $this->mysqli->error);
        }
        $row = $res->fetch_assoc();
        $res->free();
        foreach ($row as $k => $v) {
            if ($k !== 'Table' && $k !== 'View') {
                return $v;
            }
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
    private function generateRemoveAutoIncrementSql(string $sourceTable, string $targetTable): array
    {
        $sql = [];
        $sourceCols = $this->parseColumnDDLs($this->showCreate($sourceTable));
        foreach ($sourceCols as $col => $ddl) {
            if (stripos($ddl, 'AUTO_INCREMENT') === false) {
                continue;
            }
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$targetTable` MODIFY COLUMN " . $this->stripAutoIncrementFromDDL($ddl);
        }
        return $sql;
    }

    /** @return string[] */
    private function removeAutoIncrementFromAudit(string $audit): array
    {
        $sql = [];
        $auditCols = $this->parseColumnDDLs($this->showCreate($audit));
        foreach ($auditCols as $col => $ddl) {
            if (stripos($ddl, 'AUTO_INCREMENT') === false) {
                continue;
            }
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` MODIFY COLUMN " . $this->stripAutoIncrementFromDDL($ddl);
        }
        return $sql;
    }

    /** @return string[] */
    private function createAuditTableSql(
        string $form,
        string $audit,
        string $pk,
        bool $dropFirst,
        bool $useAuditDdl
    ): array
    {
        $sql = [];
        if ($dropFirst) {
            $sql[] = "DROP TABLE IF EXISTS `{$this->dbName}`.`$audit`";
        }

        // Create and align
        $sql[] = "CREATE TABLE `{$this->dbName}`.`$audit` LIKE `{$this->dbName}`.`$form`";
        $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ENGINE=" . self::ENGINE;
        $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` CONVERT TO CHARACTER SET " . self::CHARSET . " COLLATE " . $this->collation;

        // Remove AUTO_INCREMENT from any copied columns BEFORE touching PKs
        $sql = [...$sql, ...$this->generateRemoveAutoIncrementSql($form, $audit)];

        // Add audit columns
        $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN `action` VARCHAR(8) NOT NULL DEFAULT 'insert' FIRST";
        $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN `revision` INT NOT NULL AFTER `action`";
        $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`";

        // Always rebuild PK - table is new/recreated via CREATE LIKE, so PK needs changing to ($pk, revision)
        $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` DROP PRIMARY KEY";
        $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD PRIMARY KEY (`$pk`,`revision`)";
        $sql = [...$sql, ...$this->dropUniqueIndexesFromAudit($audit)];

        return $sql;
    }

    /** @return string[] */
    private function ensureAuditTableExists(string $form, string $audit, string $pk): array
    {
        $sql = [];
        if ($this->tableExists($audit)) {
            return $sql;
        }

        $sql = [...$sql, ...$this->createAuditTableSql($form, $audit, $pk, false, false)];

        return $sql;
    }

    /** @return string[] */
    private function recreateAuditTable(string $form, string $audit, string $pk): array
    {
        return $this->createAuditTableSql($form, $audit, $pk, true, false);
    }


    /** @return string[] */
    private function alignEngineAndCollation(string $audit): array
    {
        $sql = [];

        // Only alter if engine/collation don't already match
        $info = $this->db->rawQuery(
            "SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [$this->dbName, $audit]
        );

        if (!empty($info[0])) {
            $currentEngine = $info[0]['ENGINE'] ?? '';
            $currentCollation = $info[0]['TABLE_COLLATION'] ?? '';

            if (strcasecmp($currentEngine, self::ENGINE) !== 0) {
                $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ENGINE=" . self::ENGINE;
            }
            if (strcasecmp($currentCollation, $this->collation) !== 0) {
                $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` CONVERT TO CHARACTER SET " . self::CHARSET . " COLLATE " . $this->collation;
            }
        }

        return $sql;
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
        if (!isset($have['action'])) {
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN `action` VARCHAR(8) NOT NULL DEFAULT 'insert' FIRST";
        }
        if (!isset($have['revision'])) {
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN `revision` INT NOT NULL AFTER `action`";
        }
        if (!isset($have['dt_datetime'])) {
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`";
        }

        // Strip AUTO_INCREMENT on any column in audit (audit tables should never auto-increment)
        $sql = [...$sql, ...$this->removeAutoIncrementFromAudit($audit)];

        // Only rebuild PK if it isn't exactly (<pk>, revision)
        $currentPk = $this->getPrimaryKeyColumns($audit);
        if ($currentPk !== [$pk, 'revision']) {
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` DROP PRIMARY KEY";
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD PRIMARY KEY (`$pk`,`revision`)";
        }

        // WHY: Audit rows keep historical revisions for the same business keys.
        // Any copied UNIQUE keys from form_* tables will break trigger inserts.
        $sql = [...$sql, ...$this->dropUniqueIndexesFromAudit($audit)];

        return $sql;
    }



    /** @return string[] */
    private function syncColumnsToMatchForm(string $form, string $audit, bool $dropExtras, string $pk): array
    {
        $sql = [];
        $formCreate = $this->showCreate($form);
        $auditCreate = $this->showCreate($audit);

        $formCols = $this->parseColumnDDLs($formCreate);
        $auditCols = $this->parseColumnDDLs($auditCreate);

        // ADD missing (strip AUTO_INCREMENT, always strip for PK)
        foreach ($formCols as $col => $ddl) {
            if (in_array($col, self::RESERVED_AUDIT_COLS, true)) {
                continue;
            }
            if (!array_key_exists($col, $auditCols)) {
                $addDDL = ($col === $pk || stripos($ddl, 'AUTO_INCREMENT') !== false)
                    ? $this->stripAutoIncrementFromDDL($ddl)
                    : $ddl;
                $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` ADD COLUMN $addDDL";
            }
        }

        // MODIFY mismatches (compare with AI stripped for pk)
        foreach ($formCols as $col => $ddl) {
            if (in_array($col, self::RESERVED_AUDIT_COLS, true)) {
                continue;
            }
            if (!array_key_exists($col, $auditCols)) {
                continue;
            }

            $lhs = ($col === $pk || stripos($ddl, 'AUTO_INCREMENT') !== false)
                ? $this->stripAutoIncrementFromDDL($ddl)
                : $ddl;           // desired
            $rhs = (stripos($auditCols[$col], 'AUTO_INCREMENT') !== false)
                ? $this->stripAutoIncrementFromDDL($auditCols[$col])
                : $auditCols[$col]; // current

            if ($lhs !== $rhs) {
                $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` MODIFY COLUMN $lhs";
            }
        }

        if ($dropExtras) {
            foreach (array_keys($auditCols) as $col) {
                if (in_array($col, self::RESERVED_AUDIT_COLS, true)) {
                    continue;
                }
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
        // Always rebuild triggers to ensure they're up-to-date

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
                // Check each result for errors
                do {
                    if ($this->mysqli->error) {
                        throw new RuntimeException('Trigger statement failed: ' . $this->mysqli->error);
                    }
                } while ($this->mysqli->more_results() && $this->mysqli->next_result());
            } elseif (!$this->mysqli->query($sql)) {
                throw new RuntimeException('SQL failed: ' . $this->mysqli->error . ' (' . $sql . ')');
            }
        }
    }

    private function printSqlBatch(SymfonyStyle $io, string $form, string $audit, array $sqlBatch): void
    {
        $io->section("$audit ⇐ $form");
        foreach ($sqlBatch as $sql) {
            $io->writeln($sql);
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
        return implode(";\n", array_map(static fn($s): string => trim((string) $s, " \n;"), $buf)) . ";";
    }

    /** @return string[] */
    private function dropUniqueIndexesFromAudit(string $audit): array
    {
        $rows = $this->db->rawQuery(
            "SELECT DISTINCT INDEX_NAME
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND NON_UNIQUE = 0
                AND INDEX_NAME <> 'PRIMARY'",
            [$this->dbName, $audit]
        );

        if (empty($rows)) {
            return [];
        }

        $sql = [];
        foreach ($rows as $row) {
            $indexName = (string) ($row['INDEX_NAME'] ?? '');
            if ($indexName === '') {
                continue;
            }
            $sql[] = "ALTER TABLE `{$this->dbName}`.`$audit` DROP INDEX `{$indexName}`";
        }
        return $sql;
    }
}

/** Run the single-file console app */
$application = new Application('InteLIS Tools', '1.0');
$application->addCommand(new FixAuditTablesCommand());
$application->setDefaultCommand('intelis:fix-audit-tables', true);
$application->run();
