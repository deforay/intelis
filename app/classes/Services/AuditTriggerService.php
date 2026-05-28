<?php

namespace App\Services;

use App\Registries\ContainerRegistry;

/**
 * Audit Trail v2 — trigger generator.
 *
 * Builds (but does not execute) the per-form `<form>_audit_ai/au/bd` triggers
 * that capture every insert/update/delete on a tracked form_* table into the
 * generic `audit_log` JSON table. Every trigger is derived entirely from the
 * live form schema (`information_schema`) — there is no hand-maintained column
 * list anywhere. "Update a trigger" reduces to "re-run the generator."
 *
 * MySQL 8 cannot capture a row generically in a trigger (no NEW.*, no row→JSON
 * builtin, no dynamic SQL in triggers), so the column list lives in JSON_OBJECT
 * args inside the trigger body — but it is machine-emitted from the current
 * schema, never written or maintained by a human.
 *
 * STATUS — STEP 1 of the v2 build (additive, non-destructive). This service
 * exists and works in isolation but NO live trigger is created/dropped from it
 * yet. The upgrade-flow wiring that calls `buildTriggersFor()` / executes the
 * SQL lands in a later step, and the cutover that drops the legacy
 * `<form>_data__ai/au/bd` triggers and switches capture to `audit_log` lands in
 * the cutover step. Full plan in the assistant's persistent memory at
 * `~/.claude/.../memory/project_audit_trail_v2.md` (off-repo by design).
 *
 * Why two trigger name conventions:
 *   - Legacy (kept here so the cutover knows what to drop):
 *       `<form>_data__ai`, `<form>_data__au`, `<form>_data__bd`
 *     emitted by the existing `bin/setup/fix-audit-tables.php`, writing into
 *     per-form columnar `audit_form_*` tables.
 *   - v2 (this generator):
 *       `<form>_audit_ai`, `<form>_audit_au`, `<form>_audit_bd`
 *     writing into the generic `audit_log` table with a JSON snapshot.
 *   Distinct names so v2 triggers can be created without colliding with the
 *   legacy ones (drop-legacy / create-new orchestrated by the cutover).
 */
final class AuditTriggerService
{
    /** The single staging table that all v2 triggers insert into. */
    public const string AUDIT_TABLE = 'audit_log';

    /** v2 trigger name template: <form>_audit_<suffix>. */
    public const array SUFFIXES = ['ai', 'au', 'bd'];

    /** Legacy trigger name template (for the cutover's drop step). */
    public const array LEGACY_SUFFIXES = ['ai', 'au', 'bd'];

    public function __construct(private DatabaseService $db) {}

    public static function instance(): self
    {
        return ContainerRegistry::get(self::class);
    }

    /**
     * (form_table, primary_key) pairs we audit. Scoped to the existing
     * TestsService test forms in v1 (a registry-driven generic version is
     * explicitly deferred — YAGNI). Form_vl appears twice in TestsService
     * (vl + recency) — deduped here so we don't try to create the same trigger
     * twice. Filtered to tables that actually exist on this instance (so a
     * minimal install without e.g. form_hepatitis is fine).
     *
     * @return list<array{table:string, pk:string}>
     */
    public function trackedForms(): array
    {
        $seen = [];
        $out = [];
        foreach (TestsService::getTestTypes() as $meta) {
            $table = $meta['tableName'] ?? null;
            $pk = $meta['primaryKey'] ?? null;
            if (!is_string($table) || !is_string($pk) || $table === '' || $pk === '' || isset($seen[$table])) {
                continue;
            }
            $seen[$table] = true;
            if ($this->tableExists($table)) {
                $out[] = ['table' => $table, 'pk' => $pk];
            }
        }
        return $out;
    }

    /** Whether the v2 staging table (`audit_log`) has been created (5.5.3 migration). */
    public function auditLogReady(): bool
    {
        return $this->tableExists(self::AUDIT_TABLE);
    }

    /** Ordered column names for a form table, read from live information_schema. */
    public function getFormColumns(string $formTable): array
    {
        $rows = $this->db->rawQuery(
            "SELECT COLUMN_NAME
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
              ORDER BY ORDINAL_POSITION",
            [$formTable]
        );
        return array_map(static fn($r) => (string) $r['COLUMN_NAME'], $rows ?: []);
    }

    public function newTriggerName(string $formTable, string $suffix): string
    {
        return "{$formTable}_audit_{$suffix}";
    }

    public function legacyTriggerName(string $formTable, string $suffix): string
    {
        return "{$formTable}_data__{$suffix}";
    }

    /**
     * Generate the DROP + CREATE TRIGGER statements for one form table — the
     * three v2 triggers (after-insert, after-update, before-delete), each
     * preceded by its own `DROP TRIGGER IF EXISTS` so re-running the generator
     * is idempotent. Statements are returned individually (one statement per
     * array element) so the caller controls execution (and so each CREATE
     * TRIGGER block is a single statement — the BEGIN…END body must be sent
     * intact, not split on `;`).
     *
     * @return list<string>
     */
    public function buildTriggersFor(string $formTable, string $pk): array
    {
        $cols = $this->getFormColumns($formTable);
        if ($cols === []) {
            return [];
        }

        $form  = $this->qIdent($formTable);
        $pkQ   = $this->qIdent($pk);
        $audit = $this->qIdent(self::AUDIT_TABLE);
        $formLit = $this->qLit($formTable);

        $defs = [
            ['suffix' => 'ai', 'timing' => 'AFTER INSERT',  'row' => 'NEW', 'action' => 'insert'],
            ['suffix' => 'au', 'timing' => 'AFTER UPDATE',  'row' => 'NEW', 'action' => 'update'],
            ['suffix' => 'bd', 'timing' => 'BEFORE DELETE', 'row' => 'OLD', 'action' => 'delete'],
        ];

        $statements = [];
        foreach ($defs as $d) {
            $trigQ      = $this->qIdent($this->newTriggerName($formTable, $d['suffix']));
            $actionLit  = $this->qLit($d['action']);
            $rowAlias   = $d['row'];

            // JSON_OBJECT('col_name', NEW.`col_name`, …)  — built from the live
            // form column list. Same column list for INSERT/UPDATE (NEW) and
            // DELETE (OLD); only the row alias differs.
            $pairs = [];
            foreach ($cols as $col) {
                $pairs[] = '    ' . $this->qLit($col) . ', ' . $rowAlias . '.' . $this->qIdent($col);
            }
            $jsonObject = implode(",\n", $pairs);

            $statements[] = "DROP TRIGGER IF EXISTS {$trigQ}";

            // The BEGIN…END body intentionally contains inner semicolons; the
            // caller must dispatch this as a SINGLE statement (e.g. via
            // mysqli::query) rather than splitting on `;`. No DELIMITER tricks
            // are needed at the API layer — only the mysql CLI requires those.
            $statements[] = <<<SQL
CREATE TRIGGER {$trigQ} {$d['timing']} ON {$form}
FOR EACH ROW
BEGIN
  DECLARE next_rev INT;
  SELECT COALESCE(MAX(`revision`),0)+1 INTO next_rev
    FROM {$audit}
   WHERE `form_table` = {$formLit}
     AND `record_id`  = {$rowAlias}.{$pkQ};
  INSERT INTO {$audit}
    (`form_table`, `record_id`, `revision`, `action`, `dt_datetime`, `row_data`)
  VALUES (
    {$formLit},
    {$rowAlias}.{$pkQ},
    next_rev,
    {$actionLit},
    NOW(),
    JSON_OBJECT(
{$jsonObject}
    )
  );
END
SQL;
        }

        return $statements;
    }

    /**
     * DROP statements for the legacy `<form>_data__*` triggers. The cutover
     * step uses this to retire the old columnar-audit triggers in favor of
     * the v2 ones. Idempotent (`IF EXISTS`).
     *
     * @return list<string>
     */
    public function buildDropLegacyTriggers(string $formTable): array
    {
        $out = [];
        foreach (self::LEGACY_SUFFIXES as $suffix) {
            $out[] = 'DROP TRIGGER IF EXISTS ' . $this->qIdent($this->legacyTriggerName($formTable, $suffix));
        }
        return $out;
    }

    /**
     * DROP statements for v2 triggers (useful for the upgrade-time "drop before
     * migrations" step and for clean re-runs of the generator).
     *
     * @return list<string>
     */
    public function buildDropTriggersFor(string $formTable): array
    {
        $out = [];
        foreach (self::SUFFIXES as $suffix) {
            $out[] = 'DROP TRIGGER IF EXISTS ' . $this->qIdent($this->newTriggerName($formTable, $suffix));
        }
        return $out;
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->db->rawQueryValue(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$table]
        );
    }

    private function qIdent(string $id): string
    {
        return '`' . str_replace('`', '``', $id) . '`';
    }

    private function qLit(string $s): string
    {
        return "'" . str_replace("'", "''", $s) . "'";
    }
}
