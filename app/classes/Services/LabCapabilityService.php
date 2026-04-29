<?php

namespace App\Services;

use App\Registries\ContainerRegistry;

// Reads per-lab capabilities reported by the courier on each pending-commands
// poll. Capabilities live in facility_details.facility_attributes JSON under:
//   - capabilities         (object: { commandPlane, version, supports[] })
//   - capabilitiesSeenAt   (datetime — when the courier last reported)
//
// A lab is considered to "speak the command plane" only when both:
//   1) capabilitiesSeenAt is fresher than $staleMinutes (default 24h), and
//   2) capabilities.commandPlane === true.
//
// Older couriers that don't send the field are correctly treated as "no plane"
// because capabilitiesSeenAt stays null.
final class LabCapabilityService
{
    private const DEFAULT_STALE_MINUTES = 1440; // 24 hours

    public function __construct(private DatabaseService $db) {}

    public static function instance(): self
    {
        return ContainerRegistry::get(self::class);
    }

    /** @return array{capabilities: ?array, capabilitiesSeenAt: ?string} */
    public function read(int $labId): array
    {
        $row = $this->db->rawQueryOne(
            "SELECT facility_attributes->>'$.capabilities'       AS caps,
                    facility_attributes->>'$.capabilitiesSeenAt' AS seenAt
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
        $seenAt = !empty($row['seenAt']) && $row['seenAt'] !== 'null'
            ? (string) $row['seenAt']
            : null;

        return ['capabilities' => $caps, 'capabilitiesSeenAt' => $seenAt];
    }

    public function supportsCommandPlane(int $labId, int $staleMinutes = self::DEFAULT_STALE_MINUTES): bool
    {
        $info = $this->read($labId);
        return $this->isFresh($info['capabilitiesSeenAt'], $staleMinutes)
            && !empty($info['capabilities']['commandPlane']);
    }

    public function supportsCommand(int $labId, string $command, int $staleMinutes = self::DEFAULT_STALE_MINUTES): bool
    {
        $info = $this->read($labId);
        if (!$this->isFresh($info['capabilitiesSeenAt'], $staleMinutes)) {
            return false;
        }
        if (empty($info['capabilities']['commandPlane'])) {
            return false;
        }
        $supports = $info['capabilities']['supports'] ?? null;
        return is_array($supports) && in_array($command, $supports, true);
    }

    private function isFresh(?string $iso, int $staleMinutes): bool
    {
        if (empty($iso)) {
            return false;
        }
        $ts = strtotime($iso);
        if ($ts === false) {
            return false;
        }
        return (time() - $ts) <= ($staleMinutes * 60);
    }
}
