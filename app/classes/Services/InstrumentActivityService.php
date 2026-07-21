<?php

declare(strict_types=1);

namespace App\Services;

use App\Utilities\DateUtility;

/**
 * Stores the activity the Interface Tool records about its instruments.
 *
 * Events arrive two ways and both land here: bin/interface.php reads them out of
 * the tool's local database, and an installation can post them over the API. The
 * same event may legitimately arrive twice, so storing is idempotent on event_uid.
 *
 * The lab is always supplied by the caller from a trusted source -- the system
 * config for the importer, the credential for the API -- and never read from the
 * event itself, which carries whatever the tool was configured with.
 */
final class InstrumentActivityService
{
    public const VIA_API = 'api';
    public const VIA_IMPORTER = 'importer';

    public function __construct(private readonly DatabaseService $db)
    {
    }

    /**
     * Stores a batch of events and reports what happened to each one.
     *
     * @param array<int, array<string, mixed>> $events
     * @return array{stored: int, duplicates: int, skipped: int, storedUids: list<string>}
     */
    public function store(
        array $events,
        int $labId,
        string $receivedVia = self::VIA_API,
        ?string $installationId = null
    ): array {
        $stored = 0;
        $duplicates = 0;
        $skipped = 0;
        $storedUids = [];

        foreach ($events as $event) {
            $row = $this->normalize($event, $labId, $receivedVia, $installationId);
            if ($row === null) {
                $skipped++;
                continue;
            }

            // A repeated delivery is expected rather than exceptional: the importer and
            // the API can both carry the same event, and a client that times out will
            // resend. The unique key on event_uid settles it in the database.
            $this->db->connection('default')->rawQuery(
                'INSERT IGNORE INTO instrument_activity_log
                    (event_uid, lab_id, installation_id, received_via, event_type, event_category,
                     occurred_at, instrument_id, machine_type, protocol, connection_mode, test_type,
                     outcome, failure_code, event_count, app_version, received_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_values($row)
            );

            // INSERT IGNORE reports zero affected rows when the event_uid already exists,
            // which is how a repeat delivery is told apart from a genuine insert.
            if ((int) $this->db->connection('default')->count > 0) {
                $stored++;
                $storedUids[] = $row['event_uid'];
            } else {
                $duplicates++;
            }
        }

        return [
            'stored' => $stored,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
            'storedUids' => $storedUids,
        ];
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>|null null when the event lacks what identifies it
     */
    private function normalize(
        array $event,
        int $labId,
        string $receivedVia,
        ?string $installationId
    ): ?array {
        $eventUid = trim((string) ($event['event_id'] ?? $event['event_uid'] ?? ''));
        $eventType = trim((string) ($event['event_type'] ?? ''));
        // getDateTime returns a formatted string, not a date object.
        $occurredAt = DateUtility::getDateTime((string) ($event['occurred_at'] ?? ''));

        if ($eventUid === '' || $eventType === '' || $occurredAt === null) {
            return null;
        }

        return [
            'event_uid' => mb_substr($eventUid, 0, 36),
            'lab_id' => $labId,
            'installation_id' => $installationId,
            'received_via' => $receivedVia,
            'event_type' => mb_substr($eventType, 0, 64),
            'event_category' => mb_substr((string) ($event['event_category'] ?? 'unknown'), 0, 32),
            'occurred_at' => $occurredAt,
            'instrument_id' => $this->nullableText($event['instrument_id'] ?? null, 128),
            'machine_type' => $this->nullableText($event['machine_type'] ?? null, 128),
            'protocol' => $this->nullableText($event['protocol'] ?? null, 32),
            'connection_mode' => $this->nullableText($event['connection_mode'] ?? null, 32),
            'test_type' => $this->nullableText($event['test_type'] ?? null, 128),
            'outcome' => mb_substr((string) ($event['outcome'] ?? 'success'), 0, 32),
            'failure_code' => $this->nullableText($event['failure_code'] ?? null, 64),
            'event_count' => max(1, (int) ($event['event_count'] ?? 1)),
            'app_version' => $this->nullableText($event['app_version'] ?? null, 32),
            'received_at' => DateUtility::getCurrentDateTime(),
        ];
    }

    private function nullableText(mixed $value, int $length): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
