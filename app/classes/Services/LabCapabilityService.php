<?php

namespace App\Services;

use App\Registries\ContainerRegistry;

// Reads per-lab capabilities reported by the courier on each pending-commands
// poll. Capabilities live in facility_details.facility_attributes JSON under:
//   - capabilities         (object: { commandPlane, version, supports[] })
//   - capabilitiesSeenAt    (datetime — when the courier last reported caps)
//   - commandPlaneSeenAt    (datetime — when the lab last polled the command
//                            plane endpoint, written on EVERY authenticated
//                            poll regardless of payload shape)
//
// How much we trust a lab for remote commands is graded into three tiers
// (see evaluate()):
//   - 'full'  : capabilitiesSeenAt is fresh AND capabilities.commandPlane=true.
//               We offer every verb the courier advertised in supports[],
//               including upgrade/root commands (only advertised when the
//               lab's runner has allow_remote_upgrade on).
//   - 'basic' : no fresh capability report, but commandPlaneSeenAt is fresh —
//               i.e. the lab is actively polling the command plane on a
//               courier that predates capability reporting. We offer only the
//               safe non-root verbs (BASIC_COMMANDS).
//   - 'none'  : neither signal is fresh -> queueing is disabled.
//
// This lets the existing fleet (couriers that pull+execute commands since
// 5.4.3 but don't self-report capabilities) keep working, while reserving the
// upgrade/root commands for labs that explicitly advertise they can run them.
final class LabCapabilityService
{
    private const DEFAULT_STALE_MINUTES = 1440; // 24 hours

    // Safe, non-root verbs handled in-process by the LIS courier. Mirrors the
    // courier's $inProcessHandlers map in app/tasks/remote/pending-commands.php.
    // These are the only commands offered to a 'basic'-tier lab. Root/upgrade
    // verbs are deliberately excluded — they require an explicit capability
    // report (and allow_remote_upgrade on the lab's runner).
    public const BASIC_COMMANDS = [
        'ping',
        'resend-results',
        'resend-requests',
        'metadata-resync',
        'refresh-cache',
        'rotate-token',
    ];

    public function __construct(private DatabaseService $db) {}

    public static function instance(): self
    {
        return ContainerRegistry::get(self::class);
    }

    /** @return array{capabilities: ?array, capabilitiesSeenAt: ?string, commandPlaneSeenAt: ?string, instanceId: ?string, instanceSeenAt: ?string, previousInstanceId: ?string, instanceChangedAt: ?string, instanceChangeCount: int} */
    public function read(int $labId): array
    {
        $row = $this->db->rawQueryOne(
            "SELECT facility_attributes->>'$.capabilities'          AS caps,
                    facility_attributes->>'$.capabilitiesSeenAt'    AS seenAt,
                    facility_attributes->>'$.commandPlaneSeenAt'    AS planeSeenAt,
                    facility_attributes->>'$.instanceId'            AS instanceId,
                    facility_attributes->>'$.instanceSeenAt'        AS instanceSeenAt,
                    facility_attributes->>'$.previousInstanceId'    AS previousInstanceId,
                    facility_attributes->>'$.instanceChangedAt'     AS instanceChangedAt,
                    facility_attributes->>'$.instanceChangeCount'   AS instanceChangeCount
             FROM facility_details
             WHERE facility_id = ?",
            [$labId]
        );

        $caps = null;
        if (!empty($row['caps']) && $row['caps'] !== 'null') {
            $decoded = json_decode((string) $row['caps'], true);
            if (is_array($decoded)) {
                $caps = $decoded;
            }
        }
        $clean = static fn($v) => !empty($v) && $v !== 'null' ? (string) $v : null;

        return [
            'capabilities' => $caps,
            'capabilitiesSeenAt' => $clean($row['seenAt'] ?? null),
            'commandPlaneSeenAt' => $clean($row['planeSeenAt'] ?? null),
            'instanceId' => $clean($row['instanceId'] ?? null),
            'instanceSeenAt' => $clean($row['instanceSeenAt'] ?? null),
            'previousInstanceId' => $clean($row['previousInstanceId'] ?? null),
            'instanceChangedAt' => $clean($row['instanceChangedAt'] ?? null),
            'instanceChangeCount' => (int) ($clean($row['instanceChangeCount'] ?? null) ?? 0),
        ];
    }

    /**
     * How to read a lab's instance history, in one place so the sync-status row,
     * the queue dialog and anything added later agree.
     *
     * Two installations of one lab are indistinguishable to the command plane:
     * the sts_token is a single column on facility_details, so both authenticate
     * identically, and every query keys on lab_id. A command goes to whichever
     * polls first and the other never learns it existed.
     *
     * A single change is ordinary — a rebuilt machine, a restore onto new
     * hardware, a migration. Repeated changes are the case worth acting on:
     * two live instances taking turns, each overwriting the other's report.
     * The distinction is the count, not the fact of a change.
     *
     * @param array $attrs A row from read()
     * @return array{state: string, message: ?string}
     *         state: 'single' | 'changed' | 'contested' | 'unknown'
     */
    public static function instanceState(array $attrs, int $contestedThreshold = 2): array
    {
        $instanceId = $attrs['instanceId'] ?? null;
        $changeCount = (int) ($attrs['instanceChangeCount'] ?? 0);

        if (empty($instanceId)) {
            return [
                'state' => 'unknown',
                'message' => null,
            ];
        }

        if ($changeCount >= $contestedThreshold) {
            return [
                'state' => 'contested',
                'message' => sprintf(
                    'This lab has reported %d different installations. Two may be running at once, '
                        . 'in which case a queued command goes to whichever polls first and the other never sees it.',
                    $changeCount + 1
                ),
            ];
        }

        if ($changeCount > 0) {
            return [
                'state' => 'changed',
                'message' => 'The installation reporting for this lab has changed once, '
                    . 'which is expected after a rebuild, a restore, or a move to new hardware.',
            ];
        }

        return [
            'state' => 'single',
            'message' => null,
        ];
    }

    /**
     * Pure tier computation from raw attribute values so callers that already
     * have the row in hand (e.g. the sync-status bulk query) can grade a lab
     * without triggering another DB read. Single source of truth for the
     * trust tiers shared by the UI and the server-side queue/replay gates.
     *
     * @param ?array $caps Decoded capabilities object, or null.
     * @return array{tier: string, supports: string[]}
     */
    public static function evaluate(
        ?array $caps,
        ?string $capabilitiesSeenAt,
        ?string $commandPlaneSeenAt,
        int $staleMinutes = self::DEFAULT_STALE_MINUTES
    ): array {
        $isFresh = static function (?string $iso) use ($staleMinutes): bool {
            if (empty($iso)) {
                return false;
            }
            $ts = strtotime($iso);
            return $ts !== false && (time() - $ts) <= ($staleMinutes * 60);
        };

        // Full trust: explicit, fresh capability report.
        if ($isFresh($capabilitiesSeenAt) && !empty($caps['commandPlane'])) {
            $supports = [];
            if (is_array($caps['supports'] ?? null)) {
                $supports = array_values(array_filter(
                    $caps['supports'],
                    static fn($v) => is_string($v) && $v !== ''
                ));
            }
            return ['tier' => 'full', 'supports' => $supports];
        }

        // Basic trust: lab is polling the command plane but on a courier that
        // predates capability reporting. Offer only the safe non-root verbs.
        if ($isFresh($commandPlaneSeenAt)) {
            return ['tier' => 'basic', 'supports' => self::BASIC_COMMANDS];
        }

        return ['tier' => 'none', 'supports' => []];
    }

    public function supportsCommandPlane(int $labId, int $staleMinutes = self::DEFAULT_STALE_MINUTES): bool
    {
        $info = $this->read($labId);
        $ev = self::evaluate(
            $info['capabilities'],
            $info['capabilitiesSeenAt'],
            $info['commandPlaneSeenAt'],
            $staleMinutes
        );
        return $ev['tier'] !== 'none';
    }

    public function supportsCommand(int $labId, string $command, int $staleMinutes = self::DEFAULT_STALE_MINUTES): bool
    {
        $info = $this->read($labId);
        $ev = self::evaluate(
            $info['capabilities'],
            $info['capabilitiesSeenAt'],
            $info['commandPlaneSeenAt'],
            $staleMinutes
        );
        return $ev['tier'] !== 'none' && in_array($command, $ev['supports'], true);
    }
}
