<?php

namespace App\Services;

use Override;
use Normalizer;
use const COUNTRY\PNG;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use COUNTRY;
use Throwable;
use SAMPLE_STATUS;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Abstracts\AbstractTestService;

final class EidService extends AbstractTestService
{
    public string $testType = 'eid';


    #[Override]
    public function getSampleCode($params)
    {
        if (empty($params['sampleCollectionDate'])) {
            throw new SystemException("Sample Collection Date is required to generate Sample ID", 400);
        } else {
            $globalConfig = $this->commonService->getGlobalConfig();
            $params['sampleCodeFormat'] = $globalConfig['eid_sample_code'] ?? 'MMYY';
            $params['prefix'] ??= $globalConfig['eid_sample_code_prefix'] ?? $this->shortCode;
            $params['postfix'] ??= $this->labPostfix($params);

            try {
                return $this->generateSampleCode($this->table, $params);
            } catch (Throwable $e) {
                LoggerUtility::logError('Unable to generate Sample ID : ' . $e->getMessage(), [
                    'exception' => $e,
                    'file' => $e->getFile(), // File where the error occurred
                    'line' => $e->getLine(), // Line number of the error
                    'stacktrace' => $e->getTraceAsString()
                ]);
                return json_encode([]);
            }
        }
    }
    public function getEidResults($updatedDateTime = null): array
    {
        $query = "SELECT * FROM r_eid_results WHERE status='active' ";
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= '$updatedDateTime' ";
        }
        $query .= " ORDER BY result_id";
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['result_id']] = $row['result'];
        }
        return $response;
    }

    /**
     * Instruments report the qualitative EID result as free text in the language the
     * machine is configured in, so "HIV-1 NOT DETECTED", "HIV-1 NON DÉTECTÉ" and
     * "hiv-1 non detecte" all have to land on the same r_eid_results key.
     *
     * A single result can also carry several targets separated by a pipe
     * ("HIV-1 Detected | HIV-2 Not Detected"); any detected target makes the sample
     * positive. Returns null when nothing matches, so each caller keeps its own
     * fallback for unrecognised text.
     */
    public static function interpretEidResult(?string $result): ?string
    {
        $negativeKeywords = [
            'not detected', 'notdetected', 'non detecte', 'nondetecte',
            'negative', 'negatif', 'non reactive', 'non reactif',
        ];
        $positiveKeywords = [
            'detected', 'detecte', 'positive', 'positif',
            'reactive', 'reactif', 'passed',
        ];

        $normalized = self::normalizeResultText($result);
        if ($normalized === '') {
            return null;
        }

        $isPositive = false;
        $isNegative = false;
        foreach (explode('|', $normalized) as $part) {
            $hasNegative = array_any($negativeKeywords, fn($keyword) => str_contains($part, $keyword));
            $isNegative = $isNegative || $hasNegative;
            // A negative keyword always wins within its own target, because every
            // positive keyword is a substring of the matching negative one.
            if (!$hasNegative && array_any($positiveKeywords, fn($keyword) => str_contains($part, $keyword))) {
                $isPositive = true;
            }
        }

        if ($isPositive) {
            return 'positive';
        }

        return $isNegative ? 'negative' : null;
    }

    /**
     * Lowercases, strips accents and flattens punctuation so the keyword lists above
     * only ever have to carry the plain ASCII form of each word.
     */
    private static function normalizeResultText(?string $result): string
    {
        $text = trim((string) MiscUtility::cleanString($result));
        if ($text === '') {
            return '';
        }

        $text = mb_strtolower($text, 'UTF-8');

        if (class_exists('Normalizer')) {
            $decomposed = Normalizer::normalize($text, Normalizer::FORM_D);
            if ($decomposed !== false) {
                $text = preg_replace('/\p{Mn}/u', '', $decomposed) ?? $text;
            }
        }

        // Keep the pipe, it separates targets. Everything else becomes a space so
        // "non-reactive" and "non réactif" collapse onto the same keyword.
        $text = preg_replace('/[^a-z0-9|]+/', ' ', $text) ?? $text;

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    public function getEidSampleTypes($updatedDateTime = null): array
    {
        $query = "SELECT * FROM r_eid_sample_type where status='active' ";
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= '$updatedDateTime' ";
        }
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['sample_id']] = $row['sample_name'];
        }
        return $response;
    }

    #[Override]
    public function insertSample($params, $returnSampleData = false)
    {
        try {

            // Start a new transaction (this starts a new transaction if not already started)
            // see the beginTransaction() function implementation to understand how this works
            $this->db->beginTransaction();

            $formId = (int) $this->commonService->getGlobalConfig('vl_form');

            $provinceCode = $params['provinceCode'] ?? null;
            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            // PNG FORM (formId = 5) CANNOT HAVE PROVINCE EMPTY
            // Sample Collection Date Cannot be Empty
            if (empty($sampleCollectionDate) || DateUtility::isDateValid($sampleCollectionDate) === false || ($formId == PNG && empty($provinceId))) {
                return 0;
            }

            $uniqueId = $params['uniqueId'] ?? MiscUtility::generateULID();
            // Session first: an interactive user's own role is authoritative. The API/sync
            // path (save-request.php) is token-authenticated with no session, so it falls
            // through to the per-sample accessType passed in $params.
            $accessType = $_SESSION['accessType'] ?? $params['accessType'] ?? null;

            // Insert into the Code Generation Queue
            $this->testRequestsService->addToSampleCodeQueue(
                $uniqueId,
                $this->testType,
                DateUtility::isoDateFormat($sampleCollectionDate, true),
                $params['provinceCode'] ?? null,
                $params['sampleCodeFormat'] ?? null,
                $params['prefix'] ?? $this->shortCode,
                $accessType,
                (int) ($params['labId'] ?? 0) ?: null
            );

            $id = 0;
            $tesRequestData = [
                'vlsm_country_id' => $formId,
                'sample_reordered' => $params['sampleReordered'] ?? 'no',
                'unique_id' => $uniqueId,
                'facility_id' => $params['facilityId'] ?? null,
                'lab_id' => $params['labId'] ?? null,
                'app_sample_code' => $params['appSampleCode'] ?? null,
                'sample_collection_date' => DateUtility::isoDateFormat($sampleCollectionDate, true),
                'vlsm_instance_id' => $_SESSION['instanceId'] ?? $this->commonService->getInstanceId() ?? null,
                'province_id' => _castVariable($provinceId, 'int'),
                'request_created_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'request_created_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime()
            ];

            if ($this->commonService->isSTSInstance()) {
                $tesRequestData['remote_sample'] = 'yes';
                // Only collection-site samples stay at the clinic; every other role
                // (testing-lab, or an unset/legacy access_type) works the lab side.
                $tesRequestData['result_status'] = ($accessType === 'collection-site')
                    ? RECEIVED_AT_CLINIC
                    : RECEIVED_AT_TESTING_LAB;
            } else {
                $tesRequestData['remote_sample'] = 'no';
                $tesRequestData['result_status'] = RECEIVED_AT_TESTING_LAB;
            }

            $formAttributes = [
                'applicationVersion' => $this->commonService->getAppVersion(),
                'ip_address' => $this->commonService->getClientIpAddress()
            ];
            $tesRequestData['form_attributes'] = json_encode($formAttributes);

            $this->db->insert($this->table, $tesRequestData);
            $id = $this->db->getInsertId();
            // Commit the transaction after the successful insert
            $this->db->commitTransaction();
        } catch (Throwable $e) {
            // Rollback the current transaction to release locks and undo changes
            $this->db->rollbackTransaction();

            LoggerUtility::logError($this->db->getLastErrno() . ":" . $this->db->getLastError());
            LoggerUtility::logError($this->db->getLastQuery());

            LoggerUtility::logError('Insert EID Sample : ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(), // File where the error occurred
                'line' => $e->getLine(), // Line number of the error
                'stacktrace' => $e->getTraceAsString()
            ]);
            $id = 0;
        }

        if ($returnSampleData === true) {
            return [
                'id' => max($id, 0),
                'uniqueId' => $uniqueId
            ];
        } else {
            return max($id, 0);
        }
    }
}
