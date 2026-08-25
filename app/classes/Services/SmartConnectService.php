<?php

namespace App\Services;

use InvalidArgumentException;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;

class SmartConnectService
{
    /**
     * The form tables this client pushes, and everything that differs between
     * them.
     *
     * This table is the reason there is one sync script instead of three. The
     * per-module copies it replaced drifted: a debugging `die()` sat in the VL
     * one, uploading nothing on every cron run, because nobody reads three
     * near-identical files side by side.
     *
     * The legacy paths are irregular (/api/vlsm, not /api/vlsm-vl), which is
     * why they are spelled out rather than derived from the module name.
     */
    private const MODULES = [
        'vl' => [
            'table' => 'form_vl',
            'watermark' => 'vl_last_dash_sync',
            'fileField' => 'vlFile',
            'v2Path' => '/api/v2/vl',
            'v1Path' => '/api/vlsm',
            'tracking' => 'smart-connect-vl-sync',
        ],
        'eid' => [
            'table' => 'form_eid',
            'watermark' => 'eid_last_dash_sync',
            'fileField' => 'eidFile',
            'v2Path' => '/api/v2/eid',
            'v1Path' => '/api/vlsm-eid',
            'tracking' => 'smart-connect-eid-sync',
        ],
        'covid19' => [
            'table' => 'form_covid19',
            'watermark' => 'covid19_last_dash_sync',
            'fileField' => 'covid19File',
            'v2Path' => '/api/v2/covid19',
            'v1Path' => '/api/vlsm-covid19',
            'tracking' => 'smart-connect-covid19-sync',
        ],
    ];

    /** @var DatabaseService */
    private $db;

    /** @var CommonService */
    private $general;

    /** @var ApiService */
    private $apiService;

    public function __construct(DatabaseService $db, CommonService $general, ApiService $apiService)
    {
        $this->db = $db;
        $this->general = $general;
        $this->apiService = $apiService;
    }

    /**
     * Right-trimmed vldashboard_url.
     */
    public function baseUrl(): string
    {
        return rtrim((string) $this->general->getGlobalConfig('vldashboard_url'), '/');
    }

    /**
     * Which API version this dashboard speaks: 2, 1, or null when it cannot be
     * reached at all.
     *
     * One plain unauthenticated GET per run, before any token is set — the
     * probe itself must never need the credential it is being run to decide
     * whether to send. 200 with a success envelope means the v2 seam is there;
     * 404 means the deployment predates it and still answers on the legacy
     * /api/* routes.
     *
     * Anything else (5xx, a proxy error page, no response) returns null and the
     * caller skips the run. Falling back to v1 on an unrecognised status would
     * mean posting a batch at a dashboard that is merely broken, not old.
     *
     * Note this can never return the 410 sunset: /api/v2/health is served by
     * the dashboard's Slim seam, which the legacy sunset middleware does not
     * touch. A 410 only ever comes back from a v1 POST, so that is where the
     * callers check for it.
     */
    public function probeVersion(): ?int
    {
        $url = $this->baseUrl() . '/api/v2/health';
        $result = $this->apiService->getHealth($url, true);
        $status = $result['httpStatusCode'] ?? null;
        $body = json_decode((string) ($result['body'] ?? ''), true);

        if ($status === 200 && (($body['status'] ?? null) === 'success')) {
            return 2;
        }

        if ($status === 404) {
            return 1;
        }

        LoggerUtility::logError('Smart Connect: health probe gave no usable answer', [
            'url' => $url,
            'status' => $status,
        ]);

        return null;
    }

    /**
     * Reads sc_api_token. Enrolls when empty.
     */
    public function token(): ?string
    {
        $token = $this->db->getValue('s_vlsm_instance', 'sc_api_token');

        if (!empty($token)) {
            return $token;
        }

        return $this->enroll();
    }

    /**
     * Posts to /api/v2/enroll. Stores the token.
     *
     * 401 means the key is wrong. 403 means the dashboard has no key set.
     * 422 means instance_uuid is empty.
     */
    public function enroll(): ?string
    {
        $payload = [
            'enrollment_key' => $this->general->getGlobalConfig('smart_connect_enrollment_key') ?: null,
            'instance_uuid' => $this->general->getInstanceId(),
            'lab_id' => $this->general->getSystemConfig('sc_testing_lab_id') ?: null,
            'facility_code' => $this->db->getValue('s_vlsm_instance', 'instance_facility_code'),
            'label' => $this->db->getValue('s_vlsm_instance', 'instance_facility_name'),
        ];

        // Fourth argument returns httpStatusCode alongside the body.
        $result = $this->apiService->post($this->baseUrl() . '/api/v2/enroll', $payload, false, true);

        if (($result['httpStatusCode'] ?? 0) !== 201) {
            LoggerUtility::logError('Smart Connect enrollment failed', [
                'status' => $result['httpStatusCode'] ?? null,
                'body' => $result['body'] ?? null,
            ]);
            return null;
        }

        $token = json_decode((string) $result['body'], true)['data']['token'] ?? null;

        if (!empty($token)) {
            $this->db->update('s_vlsm_instance', ['sc_api_token' => $token]);
        }

        return $token;
    }

    /**
     * Nulls sc_api_token.
     */
    public function forgetToken(): void
    {
        $this->db->update('s_vlsm_instance', ['sc_api_token' => null]);
    }

    /**
     * Upload one batch file, re-enrolling once if the dashboard rejects the
     * token.
     *
     * Every sync script needs the same three things around a v2 upload — a
     * token, a bearer header, and one recovery attempt when the stored token
     * has been revoked or reissued from the other end — so they live here
     * rather than being copied per module.
     *
     * One re-enrollment, one retry, never a loop: if a freshly minted token is
     * rejected too, the fault is at the dashboard and retrying would only mint
     * tokens against it.
     *
     * A null httpStatusCode means the request produced no HTTP response, which
     * includes the case where enrollment itself failed and nothing was sent.
     *
     * @param array<int, array{name: string, contents: mixed}> $params
     * @return array{httpStatusCode: int|null, body: string|null}
     */
    public function uploadFile(string $url, string $fileField, string $filePath, array $params, bool $useV2): array
    {
        if ($useV2) {
            $token = $this->token();

            if (empty($token)) {
                LoggerUtility::logError('Smart Connect: no API token and enrollment failed', ['url' => $url]);
                return ['httpStatusCode' => null, 'body' => null];
            }

            $this->apiService->setBearerToken($token);
        }

        $result = $this->apiService->postFile($url, $fileField, $filePath, $params, true, true);

        if ($useV2 && ($result['httpStatusCode'] ?? null) === 401) {
            $this->forgetToken();
            $token = $this->enroll();

            if (!empty($token)) {
                $this->apiService->setBearerToken($token);
                $result = $this->apiService->postFile($url, $fileField, $filePath, $params, true, true);
            }
        }

        return $result;
    }

    /** The module names syncModule() accepts. */
    public static function modules(): array
    {
        return array_keys(self::MODULES);
    }

    /**
     * Push one batch of pending rows for a single module.
     *
     * Outcomes: 'skipped' (no URL configured, or the dashboard gave no usable
     * answer), 'nothing' (no rows past the watermark), 'synced', 'failed'.
     *
     * One batch per call, not a drain-until-empty loop — cron calls this on a
     * fixed interval and a lab with a large backlog should spread it over runs
     * rather than hold a connection open for an hour.
     *
     * @return array{module: string, outcome: string, sent: int, httpStatus: int|null, watermark: string|null, message: string|null}
     */
    public function syncModule(string $module, int $batchSize = 5000): array
    {
        if (!isset(self::MODULES[$module])) {
            throw new InvalidArgumentException("Unknown Smart Connect module '$module'");
        }

        $config = self::MODULES[$module];

        $report = static fn(string $outcome, array $extra = []): array => array_merge([
            'module' => $module,
            'outcome' => $outcome,
            'sent' => 0,
            'httpStatus' => null,
            'watermark' => null,
            'message' => null,
        ], $extra);

        $baseUrl = $this->baseUrl();

        if (empty($baseUrl)) {
            return $report('skipped', ['message' => 'Smart Connect URL not set']);
        }

        $version = $this->probeVersion();

        if ($version === null) {
            return $report('skipped', ['message' => 'Dashboard gave no usable answer to the version probe']);
        }

        $useV2 = $version === 2;
        $url = $baseUrl . ($useV2 ? $config['v2Path'] : $config['v1Path']);

        $watermark = $this->db->getValue('s_vlsm_instance', $config['watermark']);

        if (!empty($watermark)) {
            $this->db->where('last_modified_datetime', $watermark, '>');
        }

        $this->db->orderBy('last_modified_datetime', 'ASC');
        $rows = $this->db->get($config['table'], $batchSize);

        if (empty($rows)) {
            return $report('nothing');
        }

        $filename = null;

        try {
            [$filename, $payload, $lastUpdate] = $this->writeBatch($rows, $watermark);
            $path = TEMP_PATH . DIRECTORY_SEPARATOR . $filename;

            $params = [
                ['name' => 'source', 'contents' => $this->general->isSTSInstance() ? 'STS' : 'LIS'],
                ['name' => 'labId', 'contents' => $this->general->getSystemConfig('sc_testing_lab_id') ?? null],
            ];

            // v2 always takes the v2 code path and ignores this; the legacy
            // endpoints still read it to pick a payload format.
            if (!$useV2) {
                $params[] = ['name' => 'api-version', 'contents' => 'v2'];
            }

            $response = $this->uploadFile($url, $config['fileField'], $path, $params, $useV2);
            $status = $response['httpStatusCode'] ?? null;

            // Payload too large for the dashboard's post_max_size: halve the
            // batch and retry once with a smaller file.
            if ($status === 413 && count($rows) > 1) {
                MiscUtility::deleteFile($path);

                $rows = array_slice($rows, 0, (int) ceil(count($rows) / 2));
                [$filename, $payload, $lastUpdate] = $this->writeBatch($rows, $watermark);
                $path = TEMP_PATH . DIRECTORY_SEPARATOR . $filename;

                $response = $this->uploadFile($url, $config['fileField'], $path, $params, $useV2);
                $status = $response['httpStatusCode'] ?? null;
            }

            // Only reachable on the legacy path, and only past the dashboard's
            // sunset date. Logged there; nothing to retry here.
            $this->isSunsetResponse($status, $url);

            $body = $response['body'] ?? null;
            $decoded = json_decode((string) $body, true);

            $this->general->addApiTracking(
                MiscUtility::generateULID(),
                'vlsm-system',
                count($rows),
                $config['tracking'],
                $module,
                $url,
                $payload,
                $body,
                'json'
            );

            $succeeded = isset($decoded['status']) && trim((string) $decoded['status']) === 'success';

            // A batch whose rows all have a NULL last_modified_datetime cannot
            // move the watermark. Stamping it with the current time instead
            // would push it past every row still waiting to sync.
            if ($succeeded && !empty($lastUpdate)) {
                $this->db->update('s_vlsm_instance', [$config['watermark'] => $lastUpdate]);
            }

            return $report($succeeded ? 'synced' : 'failed', [
                'sent' => count($rows),
                'httpStatus' => $status,
                'watermark' => $succeeded ? $lastUpdate : null,
                'message' => $succeeded ? null : ($decoded['message'] ?? 'Dashboard did not confirm the batch'),
            ]);
        } finally {
            // Covers the halving path, every early return, and any throw: the
            // batch file never outlives the call that made it.
            if (!empty($filename)) {
                MiscUtility::deleteFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
            }
        }
    }

    /**
     * The reference tables pushed by syncMetadata(), gated by enabled modules.
     *
     * Commented-out entries are deliberate: those tables exist locally but the
     * dashboard maintains its own copy, and pushing ours would overwrite it.
     *
     * Public so a CLI can size a progress bar before the sync starts.
     *
     * @return string[]
     */
    public function metadataTables(): array
    {
        $tables = [
            'facility_details',
            'geographical_divisions',
            'instrument_machines',
            'instruments',
        ];

        $perModule = [
            'vl' => [
                'r_vl_sample_type',
                'r_vl_test_reasons',
                'r_vl_art_regimen',
                'r_vl_sample_rejection_reasons',
            ],
            'eid' => [
                //'r_eid_results',
                'r_eid_sample_rejection_reasons',
                'r_eid_sample_type',
                //'r_eid_test_reasons',
            ],
            'covid19' => [
                'r_covid19_comorbidities',
                'r_covid19_sample_rejection_reasons',
                'r_covid19_sample_type',
                'r_covid19_symptoms',
                'r_covid19_test_reasons',
            ],
            'hepatitis' => [
                'r_hepatitis_sample_rejection_reasons',
                'r_hepatitis_sample_type',
                'r_hepatitis_results',
                'r_hepatitis_risk_factors',
                'r_hepatitis_test_reasons',
            ],
        ];

        foreach ($perModule as $module => $moduleTables) {
            if ((SYSTEM_CONFIG['modules'][$module] ?? false) === true) {
                $tables = [...$tables, ...$moduleTables];
            }
        }

        return $tables;
    }

    /**
     * Push local reference data (facilities, geography, instruments, per-module
     * lookup tables) up to the dashboard.
     *
     * A different shape from syncModule(): whole tables rather than a batch of
     * rows, one shared watermark rather than one per module, and no size
     * halving. It shares the transport and the version probe, not the loop.
     *
     * $force sends each table's CREATE statement too, so the dashboard drops and
     * rebuilds its copy. It also ignores the watermark, since a rebuilt table
     * needs every row back, not just the changed ones.
     *
     * $onProgress, if given, is called with each table name as it is collected,
     * so a CLI can draw a progress bar without this method knowing about one.
     *
     * @return array{outcome: string, tables: int, httpStatus: int|null, watermark: string|null, message: string|null}
     */
    public function syncMetadata(bool $force = false, ?callable $onProgress = null): array
    {
        $report = static fn(string $outcome, array $extra = []): array => array_merge([
            'outcome' => $outcome,
            'tables' => 0,
            'httpStatus' => null,
            'watermark' => null,
            'message' => null,
        ], $extra);

        $baseUrl = $this->baseUrl();

        if (empty($baseUrl)) {
            return $report('skipped', ['message' => 'Smart Connect URL not set']);
        }

        $version = $this->probeVersion();

        if ($version === null) {
            return $report('skipped', ['message' => 'Dashboard gave no usable answer to the version probe']);
        }

        $useV2 = $version === 2;
        $url = $baseUrl . ($useV2 ? '/api/v2/metadata' : '/api/vlsm-metadata');

        $tables = $this->metadataTables();
        $since = $force ? null : $this->db->getValue('s_vlsm_instance', 'last_vldash_sync');

        $data = ['forceSync' => $force];

        foreach ($tables as $table) {
            if ($force) {
                $created = $this->db->rawQueryOne("SHOW CREATE TABLE `$table`");
                $data[$table]['tableStructure'] = "SET FOREIGN_KEY_CHECKS=0;" . PHP_EOL
                    . "ALTER TABLE `$table` DISABLE KEYS ;" . PHP_EOL
                    . "DROP TABLE IF EXISTS `$table`;" . PHP_EOL
                    . $created['Create Table'] . ";" . PHP_EOL
                    . "ALTER TABLE `$table` ENABLE KEYS ;" . PHP_EOL
                    . "SET FOREIGN_KEY_CHECKS=1;" . PHP_EOL;
            }

            $data[$table]['lastModifiedTime'] = $this->general->getLastModifiedDateTime($table);

            if (!empty($since)) {
                $this->db->where('updated_datetime', $since, '>');
            }

            $this->db->orderBy('updated_datetime', 'ASC');
            $data[$table]['tableData'] = $this->db->get($table);

            if ($onProgress !== null) {
                $onProgress($table);
            }
        }

        $payload = [
            'timestamp' => empty($since) ? time() : strtotime((string) $since),
            'data' => $data,
        ];

        $filename = 'reference-data-' . DateUtility::getCurrentDateTime() . '.json';
        $path = TEMP_PATH . DIRECTORY_SEPARATOR . $filename;

        try {
            file_put_contents($path, json_encode($payload));

            $params = [
                ['name' => 'source', 'contents' => $this->general->isSTSInstance() ? 'STS' : 'LIS'],
                ['name' => 'labId', 'contents' => $this->general->getSystemConfig('sc_testing_lab_id')],
            ];

            if (!$useV2) {
                $params[] = ['name' => 'api-version', 'contents' => 'v2'];
            }

            $response = $this->uploadFile($url, 'referenceFile', $path, $params, $useV2);
            $status = $response['httpStatusCode'] ?? null;
            $body = $response['body'] ?? null;

            if ($this->isSunsetResponse($status, $url)) {
                return $report('failed', [
                    'tables' => count($tables),
                    'httpStatus' => $status,
                    'message' => 'The legacy metadata API has been retired and this dashboard does not serve /api/v2',
                ]);
            }

            if (empty($body)) {
                return $report('failed', [
                    'tables' => count($tables),
                    'httpStatus' => $status,
                    'message' => 'No response from Smart Connect',
                ]);
            }

            $decoded = json_decode((string) $body, true);

            // v2 puts the verdict in the HTTP status as well as the envelope, so
            // a 4xx that still carried a parseable body is a failure even when
            // the body does not say 'error'.
            $failed = json_last_error() !== JSON_ERROR_NONE
                || ($status !== null && $status >= 400)
                || (isset($decoded['status']) && $decoded['status'] === 'error');

            if ($failed) {
                return $report('failed', [
                    'tables' => count($tables),
                    'httpStatus' => $status,
                    'message' => $decoded['message'] ?? (string) $body,
                ]);
            }

            // A partial sync answers 200 with status 'success' and names the
            // tables it could not write. Advancing the watermark on that loses
            // them for good: the next run asks only for rows changed since, and
            // nothing changed, so the rows that never arrived are never sent
            // again. Treat it as a failure so the watermark holds and the next
            // run resends the same tables.
            $errors = $decoded['data']['errors'] ?? [];

            if (!empty($decoded['partial']) || !empty($errors)) {
                $names = is_array($errors) ? array_keys($errors) : [];

                LoggerUtility::logError('Smart Connect: metadata sync was partial', [
                    'url' => $url,
                    'errors' => $errors,
                ]);

                return $report('failed', [
                    'tables' => count($tables),
                    'httpStatus' => $status,
                    'message' => 'Dashboard accepted only part of the metadata'
                        . ($names === [] ? '' : ' (failed: ' . implode(', ', $names) . ')')
                        . '. Watermark held so the next run resends.',
                ]);
            }

            $this->general->addApiTracking(
                MiscUtility::generateULID(),
                'vlsm-system',
                count($tables),
                'smart-connect-metadata-sync',
                'common',
                $url,
                $payload,
                $body,
                'json'
            );

            // The highest updated_datetime across every table just sent, so the
            // next run asks only for what changed after it.
            $unionParts = array_map(
                static fn($table) => "SELECT MAX(updated_datetime) AS latest_update FROM `$table`",
                $tables
            );
            $latest = $this->db->rawQueryOne(
                'SELECT MAX(latest_update) AS latest_update FROM (' . implode(' UNION ALL ', $unionParts) . ') AS combined'
            );

            $watermark = $latest['latest_update'] ?? DateUtility::getCurrentDateTime();
            $this->db->update('s_vlsm_instance', ['last_vldash_sync' => $watermark]);

            return $report('synced', [
                'tables' => count($tables),
                'httpStatus' => $status,
                'watermark' => $watermark,
            ]);
        } finally {
            MiscUtility::deleteFile($path);
        }
    }

    /**
     * Write one slice of rows to a temp file for upload.
     *
     * @return array{0: string, 1: array, 2: string|null} filename, payload, highest timestamp seen
     */
    private function writeBatch(array $rows, ?string $watermark): array
    {
        $payload = [
            'timestamp' => empty($watermark) ? time() : strtotime((string) $watermark),
            'data' => $rows,
        ];

        // Only rows that carry a timestamp can move the watermark.
        $modified = array_filter(array_column($rows, 'last_modified_datetime'));
        $lastUpdate = $modified === [] ? null : max($modified);

        $filename = MiscUtility::generateRandomString(12) . time() . '.json';
        file_put_contents(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, json_encode($payload));

        return [$filename, $payload, $lastUpdate];
    }

    /**
     * True when a legacy POST came back 410 Gone, logging it on the way out.
     *
     * The dashboard retires its /api/* routes on a configured date, after which
     * every one of them answers 410. An install that sees this probed 404 on
     * /api/v2/health — so it is talking to a deployment that has both retired
     * v1 and not published v2, which no upgrade produces and no retry fixes.
     * It is logged loudly because the only repair is a human looking at the
     * dashboard.
     */
    public function isSunsetResponse(?int $status, string $url): bool
    {
        if ($status !== 410) {
            return false;
        }

        LoggerUtility::logError('Smart Connect: the legacy API is retired and this install is still calling it', [
            'url' => $url,
        ]);

        return true;
    }
}
