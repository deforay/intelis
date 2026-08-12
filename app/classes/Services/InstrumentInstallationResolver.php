<?php

declare(strict_types=1);

namespace App\Services;

use App\Utilities\LoggerUtility;
use Throwable;

/**
 * Works out which installation a batch of instrument telemetry belongs to.
 *
 * The Interface Tool stamps every event and summary with a `source_installation_id`
 * of its own making -- `interface-<uuid>`, up to 128 characters. That is not what the
 * activity and usage tables hold: their `installation_id` is the 36-character identity
 * this server assigns when an installation is activated. Only `interface_installations`
 * joins the two, so a source identifier has to be looked up rather than copied across.
 * Copying it would overflow CHAR(36) and match nothing it was later joined against.
 *
 * Only the API learns its installation outright, from the credential. The importer and
 * the relay have to work it out, which is what this is for.
 */
final class InstrumentInstallationResolver
{
    /** The `installation_id` columns are CHAR(36), so nothing else can be one. */
    private const INSTALLATION_ID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    /** The shape activation stores a source under; see MySqlInterfaceInstallationRepository. */
    private const SOURCE_INSTALLATION_ID = '/^[A-Za-z0-9._:-]{8,128}$/D';

    /**
     * Sources already looked up, as "lab:source" => identity or null for one not
     * registered. bin/interface.php stores usage summaries one at a time so that a
     * summary that cannot be stored costs only itself, which would otherwise mean a
     * lookup per summary for a batch that is nearly always the same one or two
     * installations throughout.
     *
     * Misses are held as well as hits, or an unregistered tool -- the case that
     * repeats most -- would query every time. The cost is that an installation
     * activated part way through a run is not seen until the next one, no different
     * from a summary that arrives before its tool is activated at all.
     *
     * @var array<string, string|null>
     */
    private array $seen = [];

    public function __construct(private readonly DatabaseService $db)
    {
    }

    /**
     * Names the installation behind each row, keyed as the rows were given.
     *
     * Resolved for the batch rather than row by row: an interfacing database shared by
     * several tools yields a batch spanning several installations, and one query answers
     * for all of them.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string|null>
     */
    public function resolve(array $rows, int $labId): array
    {
        $sources = [];
        foreach ($rows as $row) {
            $source = $this->sourceOf($row);
            if ($source !== null) {
                $sources[$source] = true;
            }
        }
        $sources = array_keys($sources);

        $unseen = array_values(array_filter(
            $sources,
            fn(string $source): bool => !array_key_exists($this->key($labId, $source), $this->seen)
        ));

        if ($unseen !== []) {
            $found = $this->lookup($unseen, $labId);
            foreach ($unseen as $source) {
                $this->seen[$this->key($labId, $source)] = $found[$source] ?? null;
            }
        }

        $known = [];
        foreach ($sources as $source) {
            $identity = $this->seen[$this->key($labId, $source)] ?? null;
            if ($identity !== null) {
                $known[$source] = $identity;
            }
        }

        return $this->map($rows, $known);
    }

    private function key(int $labId, string $source): string
    {
        return $labId . ':' . $source;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $known source identifier => installation identity
     * @return array<int, string|null>
     */
    private function map(array $rows, array $known): array
    {
        $resolved = [];

        foreach ($rows as $key => $row) {
            // A relayed row was resolved once already, by the LIS that first stored it,
            // and carries this server's own kind of identifier. It is kept as it stands:
            // re-resolving would need an installations table, and that one is never
            // relayed, so the alternative to keeping it is losing it.
            $relayed = $row['installation_id'] ?? null;
            if (is_scalar($relayed) && preg_match(self::INSTALLATION_ID, trim((string) $relayed)) === 1) {
                $resolved[$key] = trim((string) $relayed);
                continue;
            }

            // Left unnamed rather than falling back to the tool's own identifier: an
            // installation this server never activated has no identity here, and putting
            // the source identifier in its place would fit neither the column nor a join.
            $source = $this->sourceOf($row);
            $resolved[$key] = $source === null ? null : ($known[$source] ?? null);
        }

        return $resolved;
    }

    /**
     * @param list<string> $sources
     * @return array<string, string>
     */
    private function lookup(array $sources, int $labId): array
    {
        $placeholders = implode(',', array_fill(0, count($sources), '?'));

        try {
            // Held to the lab the caller vouched for, so an installation registered to
            // one lab cannot pick up another lab's events by matching on the source alone.
            $rows = $this->db->connection('default')->rawQuery(
                "SELECT source_installation_id, installation_id
                   FROM interface_installations
                  WHERE facility_id = ? AND source_installation_id IN ($placeholders)",
                [$labId, ...$sources]
            ) ?: [];
        } catch (Throwable $error) {
            // Knowing which installation an event came from is worth less than the event.
            // Nothing here existed before the Interface API did, and bin/interface.php
            // treats a throw from this whole block as "activity not imported" -- so a
            // server whose installations table is missing or unreadable would stop
            // importing telemetry altogether over a detail it can manage without.
            LoggerUtility::logInfo('Instrument installations could not be resolved: ' . $error->getMessage());
            return [];
        }

        return array_column($rows, 'installation_id', 'source_installation_id');
    }

    /** @param array<string, mixed> $row */
    private function sourceOf(array $row): ?string
    {
        $source = $row['source_installation_id'] ?? null;
        if (!is_scalar($source)) {
            return null;
        }

        // Anything outside the shape activation accepts was never stored by activation
        // either, so it cannot match and is not worth sending to the database.
        $source = trim((string) $source);
        return preg_match(self::SOURCE_INSTALLATION_ID, $source) === 1 ? $source : null;
    }
}
