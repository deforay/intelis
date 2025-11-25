<?php

namespace App\Services;

use Override;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\TEST_FAILED;
use const SAMPLE_STATUS\NO_RESULT;
use const SAMPLE_STATUS\ACCEPTED;
use const COUNTRY\SOUTH_SUDAN;
use const COUNTRY\PNG;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use COUNTRY;
use Throwable;
use SAMPLE_STATUS;
use App\Utilities\DateUtility;
use App\Utilities\MemoUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Abstracts\AbstractTestService;

final class VlService extends AbstractTestService
{
    // keep in lowercase to make them easier to compare
    protected array $suppressedArray = [
        'hiv-1 not detected',
        'hiv-1 target not detected',
        'hiv-1 < titer min',
        'target not detected',
        'tnd',
        'not detected',
        'below detection limit',
        'below detection level',
        'bdl',
        'suppressed',
        '< 20',
        '<20',
        '< 40',
        '<40',
        //'< 839',
        //'<839',
        '-1.00',
        '< titer min',
        'negative',
        'negat'
    ];

    protected array $failureCases = [
        'fail',
        'failed',
        'failure',
        'error',
        'err',
        'invalid'
    ];

    public array $copiesPatterns = [
        'c/ml',
        'cp/ml',
        'copies/ml',
        'copies / ml',
        'copies /ml',
        'copies/ ml',
        '{copies}/ml',
        'cop/ml',
        'copies',
        'c0pies/ml',
        'cpml'
    ];

    protected int $suppressionLimit = 1000;
    public string $testType = 'vl';

    public function getSuppressionLimit(): int
    {

        $suppressionLimit = $this->commonService->getGlobalConfig('viral_load_threshold_limit');
        if ($suppressionLimit !== '' && $suppressionLimit !== '0' && $suppressionLimit !== [] && is_numeric($suppressionLimit)) {
            return (int) $suppressionLimit;
        }
        return $this->suppressionLimit;
    }

    /**
     * Safely converts a string with commas to float
     */
    private function parseNumericValue(string $value): float
    {
        // Remove thousand separators (commas) and spaces
        $cleaned = str_replace([',', ' '], '', trim($value));

        // Use is_numeric() to validate, then cast to float
        if (is_numeric($cleaned)) {
            return (float) $cleaned;
        }

        return 0.0;
    }

    /**
     * Preprocesses viral load input by removing patterns and units
     * @param string $input The input to preprocess
     * @return string The preprocessed input
     */
    private function preprocessViralLoadInput(string $input): string
    {

        $input = trim($input);

        // Only decode if HTML entities are detected
        if (str_contains($input, '&')) {
            $input = html_entity_decode(htmlspecialchars_decode($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Remove copy number units like cp/mL, copies/mL, etc.
        $input = str_ireplace($this->copiesPatterns, '', strtolower($input));

        // Explicitly remove (Ct 38.24) or similar values
        $input = preg_replace('/\(Ct\s*[0-9.]+\)/i', '', $input);

        // Replace common text patterns
        $textPatterns = [
            'hiv-1' => 'hiv1',
            'hiv-2' => 'hiv2',
            'hiv-' => 'hiv',
            'not-' => 'not',
            'hcv-' => 'hcv',
            'hcv-rna' => 'hcv'
        ];
        $input = str_ireplace(array_keys($textPatterns), array_values($textPatterns), $input);

        // Remove full phrases like 'HIV1 not detected'
        $phrasePatterns = [
            'hiv1 detected',
            'hiv2 detected',
            'hiv1 notdetected',
            'hiv2 notdetected',
            'hiv1 not detected',
            'hiv2 not detected',
            'hcv detected'
        ];
        $input = str_ireplace($phrasePatterns, '', $input);

        return trim($input);
    }

    #[Override]
    public function getSampleCode($params)
    {
        if (empty($params['sampleCollectionDate'])) {
            throw new SystemException("Sample Collection Date is required to generate Sample ID", 400);
        } else {
            $globalConfig = $this->commonService->getGlobalConfig();
            $params['sampleCodeFormat'] = $globalConfig['sample_code'] ?? 'MMYY';
            $params['prefix'] ??= $globalConfig['sample_code_prefix'] ?? $this->shortCode;
            $params['postfix'] ??= '';

            try {
                return $this->generateSampleCode($this->table, $params);
            } catch (Throwable $e) {
                LoggerUtility::logError('Unable to generate Sample ID : ' . $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), [
                    'exception' => $e,
                    'file' => $e->getFile(), // File where the error occurred
                    'line' => $e->getLine(), // Line number of the error
                    'stacktrace' => $e->getTraceAsString()
                ]);
                return json_encode([]);
            }
        }
    }

    public function getVlSampleTypesByName($name = "")
    {
        $where = "";
        if (!empty($name)) {
            $where = " AND sample_name LIKE '$name%'";
        }
        $query = "SELECT * FROM r_vl_sample_type WHERE `status` like 'active' $where";
        try {
            return $this->db->rawQuery($query);
        } catch (Throwable) {
            return [];
        }
    }

    public function getVlSampleTypes($updatedDateTime = null): array
    {
        $query = "SELECT * FROM r_vl_sample_type where status='active'";
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

    public function getVLResultCategory($resultStatus, $finalResult): ?string
    {
        return MemoUtility::remember(function () use ($resultStatus, $finalResult): ?string {
            $vlResultCategory = null;
            $orignalResultValue = $finalResult;
            $patterns = [
                '/c\/?p(?:ml|m|opies)?/i',
                '/copies/i'
            ];


            $finalResult = preg_replace($patterns, '', (string) $finalResult);
            $finalResult = trim((string) $finalResult);


            if ($resultStatus == 4) {
                $vlResultCategory = 'rejected';
            } elseif ($resultStatus == 5) {
                $vlResultCategory = 'invalid';
            } elseif ($finalResult === null || $finalResult === '') {
                $vlResultCategory = null;
            } elseif (in_array(strtolower($finalResult), $this->failureCases)) {
                $vlResultCategory = 'failed';
            } elseif (in_array($resultStatus, [1, 2, 3, 10])) {
                $vlResultCategory = null;
            } else {
                if (is_numeric($finalResult)) {
                    $interpretedResult = $this->parseNumericValue($finalResult);
                } elseif (preg_match('/^([<>])\s*(\d+(\.\d+)?(E[+-]?\d+)?)$/i', $finalResult, $matches)) {
                    if (isset($matches[2]) && is_numeric($matches[2])) {
                        $interpretedResult = $this->parseNumericValue($matches[2]);
                    }
                } elseif (in_array(strtolower((string) $orignalResultValue), $this->suppressedArray)) {
                    $interpretedResult = 10;
                } elseif (preg_match('/\d+(\.\d+)?[eE][+-]?\d+/', $finalResult, $matches)) {
                    // Extract scientific notation if present
                    $interpretedResult = $this->parseNumericValue($matches[0]);
                } else {
                    $interpretedResult = (float) filter_var($finalResult, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_SCIENTIFIC);
                }

                if ($interpretedResult < $this->getSuppressionLimit()) {
                    $vlResultCategory = 'suppressed';
                } elseif ($interpretedResult >= $this->getSuppressionLimit()) {
                    $vlResultCategory = 'not suppressed';
                }
            }

            return $vlResultCategory;
        });
    }

    public function processViralLoadResultFromForm(array $params): array
    {
        $isRejected = 'no';
        $params['vlResult'] ??= $params['result'] ?? null;
        $params['vlLog'] ??= $params['result_value_log'] ?? null;
        $finalResult = $params['vlResult'];
        $absDecimalVal = $absVal = $logVal = $txtVal = null;
        $hivDetection = $params['hivDetection'] ?? null;
        $resultStatus = $params['result_status'] ?? null;
        $params['isSampleRejected'] ??= null;

        if ($resultStatus == REJECTED || $params['isSampleRejected'] == 'yes') {
            $isRejected = 'yes';
            $finalResult = $params['vlResult'] = $params['vlLog'] = null;
            $resultStatus = REJECTED;
        } elseif (!empty($params['vlResult'])) {
            $resultStatus = PENDING_APPROVAL; // Awaiting Approval
            //Result is saved as entered
            $finalResult = $params['vlResult'];

            if (in_array(strtolower((string) $params['vlResult']), $this->failureCases)) {
                $hivDetection = null;
                $resultStatus = TEST_FAILED; // Invalid/Failed
                //$finalResult = $params['vlResult'];
            } elseif (in_array(strtolower((string) $params['vlResult']), ['noresult', 'no result', 'no'])) {
                $hivDetection = null;
                $resultStatus = NO_RESULT; // No Result
            } else {

                $interpretedResults = $this->interpretViralLoadResult($params['vlResult']);

                $logVal = $interpretedResults['logVal'] ?? null;
                $absDecimalVal = $interpretedResults['absDecimalVal'] ?? null;
                $absVal = $interpretedResults['absVal'] ?? null;
                $txtVal = $interpretedResults['txtVal'] ?? null;
            }
        } elseif (!empty($params['vlLog']) && is_numeric($params['vlLog'])) {
            $resultStatus = PENDING_APPROVAL; // Awaiting Approval
            $finalResult = 10 ** $params['vlLog'];
        }

        $hivDetection ??= '';
        $finalResult = trim("$hivDetection $finalResult");

        if (
            !empty($params['api']) &&
            $params['api'] == 'yes' &&
            $resultStatus == PENDING_APPROVAL &&
            ($finalResult !== '' && $finalResult !== '0') &&
            $this->commonService->getGlobalConfig('vl_auto_approve_api_results') == 'yes'
        ) {
            $resultStatus = ACCEPTED;
        }


        $response = [
            'isRejected' => $isRejected,
            'finalResult' => $finalResult,
            'absDecimalVal' => $absDecimalVal,
            'absVal' => $absVal,
            'logVal' => $logVal,
            'txtVal' => $txtVal,
            'hivDetection' => $hivDetection,
            'resultStatus' => $resultStatus,
        ];
        return MiscUtility::arrayEmptyStringsToNull($response);
    }

    public function interpretViralLoadResult($result, $unit = null, $defaultLowVlResultText = null): ?array
    {
        return MemoUtility::remember(function () use ($result, $unit, $defaultLowVlResultText): ?array {

            // Remove copy number units like cp/mL, copies/mL, etc.
            $result = str_ireplace($this->copiesPatterns, '', $result);

            $interpretedData = [];
            $vlResultType = $this->checkViralLoadValueType($result);

            if ($vlResultType == 'empty') {
                return null;
            }

            $originalResult = $result;

            // Special case for -1.00
            if ($result == "-1.00") {
                $result = "Not Detected";
            }

            // Check for failure cases
            if (in_array(strtolower($originalResult), $this->failureCases, true)) {
                $interpretedData = [
                    'logVal' => null,
                    'result' => $originalResult,
                    'absDecimalVal' => null,
                    'absVal' => null,
                    'txtVal' => $originalResult,
                    'finalResult' => $originalResult,
                    'originalResult' => $originalResult,
                    'resultStatus' => TEST_FAILED
                ];
            } elseif ($vlResultType == 'numeric') {
                $interpretedData = $this->interpretViralLoadNumericResult($result, $unit);
            } else {
                $interpretedData = $this->interpretViralLoadTextResult($result, $unit, $defaultLowVlResultText);
            }

            return $interpretedData;
        });
    }

    public function countrySpecificInterpretations($vlResult): mixed
    {
        return MemoUtility::remember(function () use ($vlResult) {
            $formId = (int) $this->commonService->getGlobalConfig('vl_form');
            if ($formId === SOUTH_SUDAN) {
                return match ($vlResult) {
                    'bdl', 'target not detected', 'not detected', 'tnd' => 'Below Detection Limit',
                    default => $vlResult,
                };
            }
            return $vlResult;
        });
    }

    public function interpretViralLoadTextResult($result, ?string $unit = null, $defaultLowVlResultText = null): ?array
    {

        // If result is blank, then return null
        if (in_array(trim((string) $result), ['', '0'], true)) {
            return null;
        }

        // If result is numeric, then process it as a number
        if ($this->checkViralLoadValueType($result) == 'numeric') {
            return $this->interpretViralLoadNumericResult($result, $unit);
        }

        $interpretAndConvertResult = $this->commonService->getGlobalConfig('vl_interpret_and_convert_results');
        $interpretAndConvertResult = $interpretAndConvertResult !== '' && $interpretAndConvertResult !== '0' && $interpretAndConvertResult !== [] && $interpretAndConvertResult === 'yes';

        $resultStatus = null;

        // Some machines and some countries prefer a default text result
        $vlDefaultTextResult = !in_array(trim((string) $defaultLowVlResultText), ['', '0'], true) && trim((string) $defaultLowVlResultText) !== "" ? $defaultLowVlResultText : "Target Not Detected";

        $vlResult = $logVal = $txtVal = $absDecimalVal = $absVal = null;

        $originalResultValue = $result;

        if ($result == '-1.00') {
            $result = "Target Not Detected";
        }

        $strToLowerresult = strtolower((string) $result);
        switch ($strToLowerresult) {
            case 'bdl':
                //case '< 839':
                $vlResult = $txtVal = 'Below Detection Limit';
                break;
            case 'target not detected':
            case 'not detected':
            case 'tnd':
                $vlResult = $txtVal = $vlDefaultTextResult;
                break;
            case '< 2.00E+1':
            case '< titer min':
                $absDecimalVal = 20;
                $txtVal = $vlResult = $absVal = "< 20";
                break;
            case '> titer max"':
                $absDecimalVal = 10000000;
                $txtVal = $vlResult = $absVal = "> 1000000";
                break;
            case '< inf':
                $absDecimalVal = 839;
                $vlResult = $absVal = 839;
                $logVal = 2.92;
                $txtVal = null;
                break;
            default:
                $vlResult = $txtVal = $result;
                break;
        }

        $vlResult = $this->countrySpecificInterpretations($vlResult);
        $resultToUse = $interpretAndConvertResult ? $vlResult : $originalResultValue;

        return [
            'logVal' => $logVal,
            'result' => $resultToUse,
            'absDecimalVal' => $absDecimalVal,
            'absVal' => $absVal,
            'txtVal' => $txtVal,
            'processedResult' => $vlResult,
            'originalResult' => $originalResultValue,
            'resultStatus' => $resultStatus
        ];
    }

    public function interpretViralLoadNumericResult(string $result, ?string $unit = null): ?array
    {
        $result = trim($result);
        if ($result === '' || $result === '0') {
            return null;
        }

        if ($this->checkViralLoadValueType($result) == 'text') {
            return $this->interpretViralLoadTextResult($result, $unit);
        }

        $resultStatus = $vlResult = $logVal = $txtVal = $absDecimalVal = $absVal = null;
        $originalResultValue = trim("$result $unit");
        $interpretAndConvertResult = $this->commonService->getGlobalConfig('vl_interpret_and_convert_results') === 'yes';

        $extracted = $this->extractViralLoadValue($result, true);

        $numericValue = null;
        $operator = '';
        if ($extracted !== null && preg_match('/^([<>])?\s*(\d+(\.\d+)?)/', $extracted, $matches)) {
            $operator = $matches[1] ?? '';
            $numericValue = $this->parseNumericValue($matches[2]);

            $absDecimalVal = 0;
            if ($numericValue !== null) {
                if ($unit !== null && $unit !== '' && $unit !== '0' && str_contains($unit, 'Log')) {
                    $logVal = $numericValue;
                    $absDecimalVal = round(10 ** $logVal);
                } elseif ($unit !== null && $unit !== '' && $unit !== '0') {
                    [$absDecimalVal, $unit] = $this->processResultAndUnit($numericValue, $unit);
                } else {
                    $absDecimalVal = $numericValue;
                }
            }

            $absVal = $absDecimalVal;
            $vlResult = $operator !== '' && $operator !== '0' ? "$operator $absDecimalVal" : $absDecimalVal;
        } else {
            // Fallback parsing (rare)
            $absDecimalVal = $this->parseNumericValue($result);
            $absVal = $absDecimalVal;
            $vlResult = $absDecimalVal;
        }

        if (empty($logVal) && is_numeric($absDecimalVal) && $absDecimalVal > 0) {
            $logVal = round(log10($absDecimalVal), 2);
        }

        $resultToUse = $interpretAndConvertResult ? $vlResult : $originalResultValue;

        return [
            'logVal' => $logVal,
            'result' => $resultToUse,
            'absDecimalVal' => $absDecimalVal,
            'absVal' => $absVal,
            'txtVal' => $txtVal,
            'processedResult' => $vlResult,
            'originalResult' => $originalResultValue,
            'resultStatus' => $resultStatus
        ];
    }


    private function processResultAndUnit(string $result, ?string $unit): array
    {
        // Ensure result is converted to a float
        $processedResult = is_numeric($result) ? $this->parseNumericValue($result) : 0;
        $unit = str_ireplace(['×10^', 'x10^'], '10*', $unit);
        $processedUnit = $unit;

        // Check if the unit contains scientific notation (e.g., "10*2", "E-1")
        if (!empty($unit)) {
            // Extract the scientific notation part (e.g., "10*-1" or "E-1")
            if (preg_match('/10\*\s*(-?\d+)|E([+-]?\d+)/i', $unit, $matches)) {
                $exponent = isset($matches[1]) && $matches[1] !== ''
                    ? (float) $matches[1]
                    : (float) $matches[2];

                $processedResult *= 10 ** $exponent; // Apply the multiplier
                $processedUnit = preg_replace('/10\*\s*-?\d+|E[+-]?\d+/i', '', $unit); // Clean the unit
            }

            // Remove any non-printable or unexpected characters from the unit
            $processedUnit = preg_replace('/[^a-zA-Z0-9\s\/().%]/', '', (string) $processedUnit);

            // Trim extra spaces and clean up the unit string
            $processedUnit = trim((string) $processedUnit);
        }

        return [strval($processedResult), $processedUnit];
    }


    #[Override]
    public function insertSample($params, $returnSampleData = false): int|array
    {
        try {
            // Start a new transaction (this starts a new transaction if not already started)
            // see the beginTransaction() function implementation to understand how this works
            $this->db->beginTransaction();

            $formId = (int) $this->commonService->getGlobalConfig('vl_form');

            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            // PNG FORM (formId = 5) CANNOT HAVE PROVINCE EMPTY
            // Sample Collection Date Cannot be Empty
            if (
                empty($sampleCollectionDate) ||
                DateUtility::isDateValid($sampleCollectionDate) === false ||
                ($formId == PNG && empty($provinceId))
            ) {
                return 0;
            }

            $uniqueId = $params['uniqueId'] ?? MiscUtility::generateULID();
            $accessType = $params['accessType'] ?? $_SESSION['accessType'] ?? null;

            // Insert into the Code Generation Queue
            $this->testRequestsService->addToSampleCodeQueue(
                $uniqueId,
                $this->testType,
                DateUtility::isoDateFormat($sampleCollectionDate, true),
                $params['provinceCode'] ?? null,
                $params['sampleCodeFormat'] ?? null,
                $params['prefix'] ?? $this->shortCode,
                $accessType
            );

            $id = 0;
            $tesRequestData = [
                'vlsm_country_id' => $formId,
                'sample_reordered' => $params['sampleReordered'] ?? 'no',
                'unique_id' => $uniqueId,
                'facility_id' => $params['facilityId'] ?? null,
                'lab_id' => $params['labId'] ?? null,
                'patient_art_no' => $params['artNo'] ?? null,
                'specimen_type' => $params['specimenType'] ?? null,
                'app_sample_code' => $params['appSampleCode'] ?? null,
                'sample_collection_date' => DateUtility::isoDateFormat($sampleCollectionDate, true),
                'vlsm_instance_id' => $_SESSION['instanceId'] ?? $this->commonService->getInstanceId() ?? null,
                'province_id' => _castVariable($provinceId, 'int'),
                'request_created_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'form_attributes' => $params['formAttributes'] ?? "{}",
                'request_created_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
                'result_modified' => 'no',
                'is_result_sms_sent' => 'no',
                'manual_result_entry' => 'yes',
                'locked' => 'no'
            ];

            if ($this->commonService->isSTSInstance()) {
                $tesRequestData['remote_sample'] = 'yes';
                $tesRequestData['result_status'] = RECEIVED_AT_CLINIC;
                if ($accessType === 'testing-lab') {
                    $tesRequestData['result_status'] = RECEIVED_AT_TESTING_LAB;
                }
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
            if ($this->db->getLastErrno() > 0) {
                throw new SystemException($this->db->getLastErrno() . " | " . $this->db->getLastError());
            }
            // Commit the transaction after the successful insert
            $this->db->commitTransaction();
        } catch (Throwable $e) {
            // Rollback the current transaction to release locks and undo changes
            $this->db->rollbackTransaction();

            LoggerUtility::logError('Error Inserting VL Sample : ' . $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(), // File where the error occurred
                'line' => $e->getLine(), // Line number of the error
                'stacktrace' => $e->getTraceAsString(),
                'last_db_error' => "DB ERROR : " . $this->db->getLastErrno() . ":" . $this->db->getLastError(),
                'last_db_query' => $this->db->getLastQuery()
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

    public function getReasonForFailure($option = true, $updatedDateTime = null)
    {
        $result = [];
        $this->db->where('status', 'active');
        if ($updatedDateTime) {
            $this->db->where("updated_datetime >= '$updatedDateTime'");
        }
        $results = $this->db->get('r_vl_test_failure_reasons');
        if ($option) {
            foreach ($results as $row) {
                $result[$row['failure_id']] = $row['failure_reason'];
            }
            return $result;
        } else {
            return $results;
        }
    }

    public function getVlResults($instrumentId = null): void
    {
        _getFromFileCache('vl_results_for_instrument', function () use ($instrumentId) {

            if (!empty($instrumentId)) {
                $instrumentCondition = $this->db->escape($instrumentId);

                // Using 'one' instead of 'all' if checking for at least one occurrence is sufficient
                $this->db->where("(JSON_SEARCH(available_for_instruments, 'all', '$instrumentCondition') IS NOT NULL)
                        OR available_for_instruments IS NULL
                        OR available_for_instruments REGEXP '^\\[\\s*\\]$'");
            }

            $this->db->where('status', 'active');
            return $this->db->get('r_vl_results');
        }, ['r_vl_results']);
    }


    public function getVlReasonsForTesting(): array
    {
        return $this->db->rawQuery("SELECT test_reason_id,test_reason_name
                                            FROM r_vl_test_reasons
                                                WHERE `test_reason_status` LIKE 'active'
                                                AND (parent_reason IS NULL OR parent_reason = 0)");
    }

    /**
     * Determines the type of viral load value (empty, numeric, or text).
     *
     * This function analyzes input to categorize it as empty, numeric, or text.
     * It handles various number formats including scientific notation, commas,
     * and numbers with operators (<, >). It also processes custom text patterns
     * and specific hardcoded cases.
     *
     * @param mixed $input The viral load value to analyze
     * @param array $customTextPatterns Optional array of exact strings to treat as text (e.g., ['< 500', '> 999'])
     * @return string Returns 'empty', 'numeric', or 'text'
     *
     * Numeric formats supported:
     * - Regular numbers: 123, 123.45
     * - Numbers with commas: 1,234,567.89
     * - Scientific notation: 5.69E03, 1.23E-02
     * - Numbers with operators: < 1000, > 500
     *
     * Processing steps:
     * 1. Checks for null/empty input
     * 2. Checks against custom text patterns
     * 3. Handles hardcoded special case "< 839"
     * 4. Removes units and text patterns
     * 5. Removes detection phrases
     * 6. Validates remaining string as numeric
     */
    public function checkViralLoadValueType(mixed $input, $customTextPatterns = []): mixed
    {
        return MemoUtility::remember(function () use ($input, $customTextPatterns): string {
            // Check if it's null or empty first
            if (is_null($input) || trim((string) $input) === '') {
                return 'empty';
            }
            // Remove copy number units like cp/mL, copies/mL, etc.
            $input = str_ireplace($this->copiesPatterns, '', strtolower((string) $input));

            // Check for any custom text patterns (exact match)
            if (!empty($customTextPatterns) && in_array($input, $customTextPatterns, true)) {
                return 'text';
            }

            // // Hardcoded cases (can be expanded via customTextPatterns)
            // $hardcodedTextCases = ['< 839'];
            // if (in_array($input, $hardcodedTextCases, true)) {
            //     return 'text';
            // }

            $processed = $this->preprocessViralLoadInput($input);

            // Check if it's a pure number (including scientific notation and commas)
            if (preg_match('/^-?\d+(?:,\d{3})*(?:\.\d+)?(?:[eE][-+]?\d+)?$/', $processed)) {
                return 'numeric';
            }
            // Check if it's a number with < or > operator
            elseif (preg_match('/^[<>]\s*\d+(?:,\d{3})*(?:\.\d+)?(?:[eE][-+]?\d+)?$/', $processed)) {
                return 'numeric';
            }

            return 'text';
        });
    }

    public function extractViralLoadValue(string $input, $returnWithOperator = true): ?string
    {
        return MemoUtility::remember(function () use ($input, $returnWithOperator): float|string|null {
            $processed = $this->preprocessViralLoadInput($input);

            if (is_numeric($processed)) {
                return $this->parseNumericValue($processed);
            }

            // Check for numbers with comma separators (e.g., "1,116" or "1,234.56")
            if (preg_match('/^(\d+(?:,\d{3})*(?:\.\d+)?(?:[eE][-+]?\d+)?)$/i', $processed, $matches)) {
                return $this->parseNumericValue($matches[1]);
            }

            // More lenient: allows trailing units or (Ct ...) with < or > operators
            if (preg_match('/([\<\>])\s*(\d+(?:,\d{3})*(?:\.\d+)?(?:[eE][-+]?\d+)?)/i', $processed, $matches)) {
                $operator = $returnWithOperator ? $matches[1] : '';
                return trim("$operator " . $this->parseNumericValue($matches[2]));
            }

            return null;
        });
    }

    /**
     * @return non-falsy-string[]
     */
    public function getLabStorage($labId = null, $onlyActive = true): array
    {

        if ($onlyActive) {
            $this->db->where('status', 'active');
        }
        if ($labId) {
            $this->db->where('lab_id', $labId);
        }
        $this->db->join("facility_details f", "f.facility_id=s.lab_id", "INNER");

        $response = [];
        $results = $this->db->get("lab_storage s");
        foreach ($results as $row) {
            $response[$row['storage_id']] = $row['storage_code'] . " - " . $row['facility_name'];
        }
        return $response;
    }
}
