<?php

declare(strict_types=1);

namespace App\Services\InterfaceApi;

use App\Exceptions\InterfaceApiException;
use App\Services\DatabaseService;

/**
 * Describes the facility an Interface Tool installation is bound to: its identity,
 * the instruments configured for it, and what this server can do.
 *
 * The facility always comes from the authenticated installation, never from the
 * request, so an installation can only ever see its own lab.
 */
final readonly class InterfaceConnectionService
{
    public const ACTIVATION_MAX_ATTEMPTS = 10;
    public const ACTIVATION_WINDOW_SECONDS = 60;
    public const ACTIVATION_MAX_BODY_BYTES = 16384;

    public function __construct(private DatabaseService $db)
    {
    }

    /**
     * @param array<string, mixed> $installation
     * @return array<string, mixed>
     */
    public function getConnection(array $installation): array
    {
        $scopes = $installation['credential_scopes'] ?? [];

        return $this->facilityConnection((int) $installation['facility_id']) + [
            'capabilities' => [
                'apiVersion' => 'v1',
                'adapter' => [
                    'id' => 'intelis',
                    'managed' => true,
                ],
                'operations' => [
                    'connectionRead' => true,
                    'resultsWrite' => false,
                    'telemetryWrite' => false,
                ],
                'credentialScopes' => array_values(is_array($scopes) ? $scopes : []),
            ],
            'limits' => [
                'activation' => [
                    'maxAttempts' => self::ACTIVATION_MAX_ATTEMPTS,
                    'windowSeconds' => self::ACTIVATION_WINDOW_SECONDS,
                    'maxBodyBytes' => self::ACTIVATION_MAX_BODY_BYTES,
                ],
            ],
        ];
    }

    /**
     * Only the columns the Interface Tool needs are selected. Facility coordinates,
     * file names and the like stay on the server.
     *
     * @return array<string, mixed>
     */
    private function facilityConnection(int $facilityId): array
    {
        $facility = $this->db->rawQueryOne(
            "SELECT facility_id, facility_code, facility_name, test_type
               FROM facility_details
              WHERE facility_id = ? AND status = 'active'
              LIMIT 1",
            [$facilityId]
        );
        if (empty($facility)) {
            throw new InterfaceApiException('facility_unavailable', 'The installation facility is unavailable.', 404);
        }

        $rows = $this->db->rawQuery(
            "SELECT i.instrument_id, i.machine_name, i.supported_tests,
                    im.config_machine_id, im.config_machine_name AS alias
               FROM instruments i
               LEFT JOIN instrument_machines im ON im.instrument_id = i.instrument_id
              WHERE i.lab_id = ? AND i.status = 'active'
              ORDER BY i.machine_name, im.config_machine_name",
            [$facilityId]
        ) ?: [];

        $instruments = [];
        $supportedTests = $this->decodeTests($facility['test_type'] ?? null);
        foreach ($rows as $row) {
            $instrumentId = (string) $row['instrument_id'];
            if (!isset($instruments[$instrumentId])) {
                $tests = $this->decodeTests($row['supported_tests'] ?? null);
                $supportedTests = array_values(array_unique(array_merge($supportedTests, $tests)));
                $instruments[$instrumentId] = [
                    'id' => $instrumentId,
                    'name' => (string) ($row['machine_name'] ?? ''),
                    'supportedTests' => $tests,
                    'aliases' => [],
                    'machines' => [],
                ];
            }

            $alias = trim((string) ($row['alias'] ?? ''));
            if ($alias !== '' && !in_array($alias, $instruments[$instrumentId]['aliases'], true)) {
                $instruments[$instrumentId]['aliases'][] = $alias;
            }
            $machineId = (int) ($row['config_machine_id'] ?? 0);
            if ($machineId > 0 && $alias !== '') {
                $instruments[$instrumentId]['machines'][] = [
                    'id' => $machineId,
                    'name' => $alias,
                ];
            }
        }

        sort($supportedTests);
        return [
            'facility' => [
                'id' => (int) $facility['facility_id'],
                'code' => (string) ($facility['facility_code'] ?? ''),
                'name' => (string) ($facility['facility_name'] ?? ''),
            ],
            'supportedTests' => $supportedTests,
            'instruments' => array_values($instruments),
        ];
    }

    /** @return list<string> */
    private function decodeTests(mixed $value): array
    {
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        } elseif (is_string($value)) {
            $value = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        $tests = array_map('strval', array_filter($value, static fn(mixed $test): bool => is_scalar($test)));
        $tests = array_values(array_unique(array_filter(array_map('trim', $tests))));
        sort($tests);
        return $tests;
    }
}
