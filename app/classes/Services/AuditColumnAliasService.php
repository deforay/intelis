<?php

namespace App\Services;

use Throwable;
use App\Registries\ContainerRegistry;

/**
 * Audit Trail v2 — read-time column rename resolver.
 *
 * Loads the `audit_column_aliases` table once per request and resolves any
 * historical column name to its **current** name on the relevant form table,
 * following chains (A → B → C) safely. Used at the audit-trail READ layer so
 * a renamed column's pre-rename revisions display under the new name without
 * ever rewriting stored audit data (the bulk lives in compressed CSV files
 * that would be prohibitively expensive to rewrite per rename).
 *
 * STATUS — STEP 2a of the v2 build. The alias table is created (step 1) but
 * has no rows yet; nothing in this commit changes user-visible behavior. Once
 * a rename migration registers an alias here, historical revisions automatically
 * align under the current column name on the next read.
 *
 * Design notes:
 *  - Aliases are scoped per form_table (PRIMARY KEY in the schema).
 *  - Resolution follows the chain to the current name. Cycle-safe: if a buggy
 *    alias creates A → B → A, resolution stops on the first revisit and returns
 *    the last unique step (it does not loop). This is defensive — chains in
 *    practice should be linear (a column is renamed forward through history).
 *  - Reserved audit columns (action/revision/dt_datetime) are never aliased
 *    because the alias table doesn't carry rows for them.
 *  - The table may not exist on older instances that haven't run the 5.5.3
 *    migration; failures to load are treated as an empty map so legacy reads
 *    never break.
 */
final class AuditColumnAliasService
{
    /** @var array<string, array<string,string>>  form_table => (old_name => new_name) */
    private array $directMap = [];
    private bool $loaded = false;

    public function __construct(private DatabaseService $db) {}

    public static function instance(): self
    {
        return ContainerRegistry::get(self::class);
    }

    /**
     * Resolve a (possibly historical) column name to its CURRENT name on the
     * given form table. Returns the input unchanged when there's no mapping
     * (the common case: alias table empty / column never renamed).
     */
    public function resolveCurrentName(string $formTable, string $name): string
    {
        $this->load();
        $map = $this->directMap[$formTable] ?? null;
        if (!$map) {
            return $name;
        }

        $seen = [$name => true];
        $current = $name;
        while (isset($map[$current])) {
            $next = $map[$current];
            if (isset($seen[$next])) {
                // Cycle defense — should never happen with sane data.
                break;
            }
            $seen[$next] = true;
            $current = $next;
        }
        return $current;
    }

    /**
     * Resolve a whole header row in one call. Convenience for CSV readers
     * where every cell maps positionally to a header.
     *
     * @param string[] $names
     * @return string[]
     */
    public function resolveMany(string $formTable, array $names): array
    {
        $this->load();
        $map = $this->directMap[$formTable] ?? null;
        if (!$map) {
            return $names;
        }
        $out = [];
        foreach ($names as $n) {
            $out[] = $this->resolveCurrentName($formTable, (string) $n);
        }
        return $out;
    }

    /** Raw direct map for one form_table — for callers that need both names. */
    public function getMapFor(string $formTable): array
    {
        $this->load();
        return $this->directMap[$formTable] ?? [];
    }

    /** Force-reload (e.g. after a rename migration registered a new alias mid-request). */
    public function reload(): void
    {
        $this->loaded = false;
        $this->directMap = [];
        $this->load();
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        try {
            $rows = $this->db->rawQuery(
                "SELECT form_table, old_name, new_name FROM audit_column_aliases"
            );
            foreach ($rows ?: [] as $r) {
                $form = (string) ($r['form_table'] ?? '');
                $old  = (string) ($r['old_name']   ?? '');
                $new  = (string) ($r['new_name']   ?? '');
                if ($form === '' || $old === '' || $new === '' || $old === $new) {
                    continue;
                }
                $this->directMap[$form][$old] = $new;
            }
        } catch (Throwable) {
            // audit_column_aliases not present yet on this instance (pre-5.5.3
            // migration). Treat as empty map; legacy reads continue to work.
            $this->directMap = [];
        }
        $this->loaded = true;
    }
}
