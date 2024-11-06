<?php

namespace App\Services;

use App\Utilities\MiscUtility;
use COUNTRY;
use Throwable;
use SAMPLE_STATUS;
use App\Utilities\DateUtility;
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
        '< 839',
        '<839',
        '-1.00',
        '< titer min',
        'negative',
        'negat'
    ];
    protected int $suppressionLimit = 1000;
    public string $testType = 'vl';


    public function getSampleCode($params)
    {
        if (empty($params['sampleCollectionDate'])) {
            throw new SystemException("Sample Collection Date is required");
        } else {
            $globalConfig = $this->commonService->getGlobalConfig();
            $params['sampleCodeFormat'] = $globalConfig['sample_code'] ?? 'MMYY';
            $params['prefix'] ??= $globalConfig['sample_code_prefix'] ?? $this->shortCode;

            try {
                return $this->generateSampleCode($this->table, $params);
            } catch (Throwable $e) {
                LoggerUtility::log('error', 'Unable to generate Sample ID : ' . $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), [
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
        } catch (Throwable $e) {
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
        return once(function () use ($resultStatus, $finalResult) {
            $vlResultCategory = null;
            $orignalResultValue = $finalResult;
            $patterns = [
                '/c\/?p(?:ml|m|opies)?/i',
                '/copies/i',
                '/hiv-?1\s*(?:not)?\s*detected/i'
            ];

            $finalResult = preg_replace($patterns, '', (string) $finalResult);
            $finalResult = trim($finalResult);

            if (empty($finalResult) || $finalResult == '') {
                $vlResultCategory = null;
            } elseif (in_array(strtolower($finalResult), ['fail', 'failed', 'failure', 'error', 'err'])) {
                $vlResultCategory = 'failed';
            } elseif (in_array($resultStatus, [1, 2, 3, 10])) {
                $vlResultCategory = null;
            } elseif ($resultStatus == 4) {
                $vlResultCategory = 'rejected';
            } elseif ($resultStatus == 5) {
                $vlResultCategory = 'invalid';
            } else {

                if (is_numeric($finalResult)) {
                    $interpretedResult =  floatval($finalResult);
                } elseif (preg_match('/^([<>])\s*(\d+(\.\d+)?(E[+-]?\d+)?)$/i', $finalResult, $matches)) {
                    if (isset($matches[2]) && is_numeric($matches[2])) {
                        $interpretedResult =  floatval($matches[2]);
                    }
                } else {
                    if (in_array(strtolower((string) $orignalResultValue), $this->suppressedArray)) {
                        $interpretedResult = 10;
                    } else {
                        $interpretedResult = (float) filter_var($finalResult, FILTER_SANITIZE_NUMBER_FLOAT);
                    }
                }

                if ($interpretedResult < $this->suppressionLimit) {
                    $vlResultCategory = 'suppressed';
                } elseif ($interpretedResult >= $this->suppressionLimit) {
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

        if ($resultStatus == SAMPLE_STATUS\REJECTED || $params['isSampleRejected'] == 'yes') {
            $isRejected = 'yes';
            $finalResult = $params['vlResult'] = $params['vlLog'] = null;
            $resultStatus = SAMPLE_STATUS\REJECTED;
        } elseif (!empty($params['vlResult'])) {
            $resultStatus = SAMPLE_STATUS\PENDING_APPROVAL; // Awaiting Approval
            //Result is saved as entered
            $finalResult = $params['vlResult'];

            if (in_array(strtolower((string) $params['vlResult']), ['fail', 'failed', 'failure', 'error', 'err', 'invalid'])) {
                $hivDetection = null;
                $resultStatus = SAMPLE_STATUS\TEST_FAILED; // Invalid/Failed
                //$finalResult = $params['vlResult'];
            } elseif (in_array(strtolower((string) $params['vlResult']), ['noresult', 'no result', 'no'])) {
                $hivDetection = null;
                $resultStatus = SAMPLE_STATUS\NO_RESULT; // No Result
            } else {

                $interpretedResults = $this->interpretViralLoadResult($params['vlResult']);

                $logVal = $interpretedResults['logVal'] ?? null;
                $absDecimalVal = $interpretedResults['absDecimalVal'] ?? null;
                $absVal = $interpretedResults['absVal'] ?? null;
                $txtVal = $interpretedResults['txtVal'] ?? null;
            }
        } elseif (!empty($params['vlLog']) && is_numeric($params['vlLog'])) {
            $resultStatus = SAMPLE_STATUS\PENDING_APPROVAL; // Awaiting Approval
            $finalResult = pow(10, $params['vlLog']);
        }

        $hivDetection = $hivDetection ?? '';
        $finalResult = trim($hivDetection . ' ' . $finalResult);

        if (
            !empty($params['api']) &&
            $params['api'] == 'yes' &&
            $resultStatus == SAMPLE_STATUS\PENDING_APPROVAL &&
            !empty($finalResult) &&
            $this->commonService->getGlobalConfig('vl_auto_approve_api_results') == 'yes'
        ) {
            $resultStatus = SAMPLE_STATUS\ACCEPTED;
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
        return once(function () use ($result, $unit, $defaultLowVlResultText) {

            $vlResultType = $this->checkViralLoadValueType($result);

            if ($vlResultType == 'empty') {
                return null;
            }

            $finalResult = $vlResult = trim(htmlspecialchars_decode((string) $result));
            $vlResult = str_ireplace(['c/ml', 'cp/ml', 'copies/ml', 'cop/ml', 'copies'], '', $vlResult);
            $vlResult = str_ireplace('-', '', $vlResult);
            $vlResult = trim(str_ireplace(['hiv1 detected', 'hiv1 notdetected'], '', $vlResult));



            if ($vlResult == "-1.00") {
                $finalResult = $vlResult = "Not Detected";
            }

            if (in_array($finalResult, ['fail', 'failed', 'failure', 'error', 'err'])) {
                return [
                    'logVal' => null,
                    'result' => null,
                    'absDecimalVal' => null,
                    'absVal' => null,
                    'txtVal' => $finalResult,
                    'resultStatus' => SAMPLE_STATUS\TEST_FAILED
                ];
            } elseif ($vlResultType == 'numeric') {
                //passing only number
                return $this->interpretViralLoadNumericResult($vlResult, $unit);
            } else {
                //Passing orginal result value for text results
                return $this->interpretViralLoadTextResult($finalResult, $unit, $defaultLowVlResultText);
            }
        });
    }

    public function interpretViralLoadTextResult($result, $unit = null, $defaultLowVlResultText = null): ?array
    {

        // If result is blank, then return null
        if (empty(trim((string) $result))) {
            return null;
        }

        // If result is numeric, then process it as a number
        if ($this->checkViralLoadValueType($result) == 'numeric') {
            return $this->interpretViralLoadNumericResult($result, $unit);
        }

        $interpretAndConvertResult = $this->commonService->getGlobalConfig('vl_interpret_and_convert_results');
        $interpretAndConvertResult = !empty($interpretAndConvertResult) && $interpretAndConvertResult === 'yes';

        $resultStatus = null;

        // Some machines and some countries prefer a default text result
        $vlDefaultTextResult = !empty(trim((string) $defaultLowVlResultText)) && trim((string) $defaultLowVlResultText) != "" ? $defaultLowVlResultText : "Target Not Detected";

        $vlResult = $logVal = $txtVal = $absDecimalVal = $absVal = null;

        $originalResultValue = $result;

        if ($result == '-1.00') {
            $result = "Target Not Detected";
        }

        $strToLowerresult = strtolower((string) $result);
        switch ($strToLowerresult) {
            case 'bdl':
            case '< 839':
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
        if ($interpretAndConvertResult) {
            $originalResultValue = $vlResult;
        }

        return [
            'logVal' => $logVal,
            'result' => $originalResultValue,
            'absDecimalVal' => $absDecimalVal,
            'absVal' => $absVal,
            'txtVal' => $txtVal,
            'resultStatus' => $resultStatus,
        ];
    }

    public function interpretViralLoadNumericResult(string $result, ?string $unit = null): ?array
    {
        $result = trim($result);
        if (empty($result)) {
            return null; // Return early if the result is empty
        }

        // Check the type of the value and process non-numeric types as text results
        if ($this->checkViralLoadValueType($result) == 'text') {
            return $this->interpretViralLoadTextResult($result, $unit);
        }

        $resultStatus = $vlResult = $logVal = $txtVal = $absDecimalVal = $absVal = null;
        $originalResultValue = $result;
        $interpretAndConvertResult = $this->commonService->getGlobalConfig('vl_interpret_and_convert_results') === 'yes';

        // Handling inequality operators (< and >), and scientific notation in the result
        if (preg_match('/^([<>])\s*(\d+(\.\d+)?(E[+-]?\d+)?)$/i', $result, $matches)) {
            $operator = $matches[1];
            $numericValue = floatval($matches[2]);

            if (!empty($unit) && str_contains($unit, 'Log')) {
                $logVal = $numericValue;
                $absDecimalVal = round(pow(10, $logVal), 2);
            } else {
                $absDecimalVal = $numericValue;
                $logVal = log10($absDecimalVal);
            }

            $absVal = $absDecimalVal;
            $vlResult = "$operator $absDecimalVal";
        } elseif (is_numeric($result)) {
            // Handle all numeric results here, whether they need logarithmic conversion.
            if (!empty($unit) && str_contains($unit, 'Log')) {
                // Assume the numeric result is a log value needing conversion to absolute count.
                $logVal = (float)$result;
                $absDecimalVal = round(pow(10, $logVal), 2);
                $vlResult = $absVal = $absDecimalVal;
            } else {
                // It's a simple numeric result, not requiring conversion from log scale.
                $absDecimalVal = floatval($result);
                $logVal = round(log10($absDecimalVal), 2);
                $absVal = $absDecimalVal;
                $vlResult = $absDecimalVal;
            }
        } else {
            $vlResult = $absVal = $absDecimalVal = floatval($result);
            $logVal = round(log10($absDecimalVal), 2);
        }

        // Use the converted or original value based on configuration
        $resultToUse = $interpretAndConvertResult ? $vlResult : $originalResultValue;

        return [
            'logVal' => $logVal,
            'result' => $resultToUse,
            'absDecimalVal' => $absDecimalVal,
            'absVal' => $absVal,
            'txtVal' => $txtVal,
            'resultStatus' => $resultStatus
        ];
    }

    public function insertSample($params, $returnSampleData = false): int | array
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
                ($formId == COUNTRY\PNG && empty($provinceId))
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
                'result_modified'  => 'no',
                'is_result_sms_sent'  => 'no',
                'manual_result_entry' => 'yes',
                'locked' => 'no'
            ];

            if ($this->commonService->isSTSInstance()) {
                $tesRequestData['remote_sample'] = 'yes';
                $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
                if ($accessType === 'testing-lab') {
                    $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
                }
            } else {
                $tesRequestData['remote_sample'] = 'no';
                $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
            }

            $formAttributes = [
                'applicationVersion' => $this->commonService->getAppVersion(),
                'ip_address' => $this->commonService->getClientIpAddress()
            ];
            $tesRequestData['form_attributes'] = json_encode($formAttributes);

            $this->db->insert($this->table, $tesRequestData);

            $id = $this->db->getInsertId();
            if ($this->db->getLastErrno() > 0) {
                throw new SystemException($this->db->getLastErrno() . " | " .  $this->db->getLastError());
            }
            // Commit the transaction after the successful insert
            $this->db->commitTransaction();
        } catch (Throwable $e) {
            // Rollback the current transaction to release locks and undo changes
            $this->db->rollbackTransaction();

            //if ($this->db->getLastErrno() > 0) {
            LoggerUtility::log('error', $this->db->getLastErrno() . ":" . $this->db->getLastError());
            LoggerUtility::log('error', $this->db->getLastQuery());
            //}

            LoggerUtility::log('error', 'Insert VL Sample : ' . $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage(), [
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

    public function getReasonForFailure($option = true, $updatedDateTime = null)
    {
        $result = [];
        $this->db->where('status', 'active');
        if ($updatedDateTime) {
            $this->db->where('updated_datetime >= "' . $updatedDateTime . '"');
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

    public function getVlResults($instrumentId = null)
    {
        // Build the query condition for instrument availability
        if (!empty($instrumentId)) {
            // Safely binding the parameter to avoid SQL injection
            $instrumentCondition = $this->db->escape($instrumentId);

            // Using 'one' instead of 'all' if checking for at least one occurrence is sufficient
            $this->db->where("(JSON_SEARCH(available_for_instruments, 'all', '$instrumentCondition') IS NOT NULL)
                        OR available_for_instruments IS NULL
                        OR available_for_instruments REGEXP '^\\[\\s*\\]$'");
        }

        // Add additional conditions
        $this->db->where('status', 'active');
        return $this->db->get('r_vl_results');
    }


    public function getVlReasonsForTesting(): array
    {
        return $this->db->rawQuery("SELECT test_reason_id,test_reason_name
                                            FROM r_vl_test_reasons
                                                WHERE `test_reason_status` LIKE 'active'
                                                AND (parent_reason IS NULL OR parent_reason = 0)");
    }

    public function checkViralLoadValueType($input)
    {
        // Check if it's null or empty
        if (is_null($input) || trim((string) $input) == '') {
            return 'empty';
        }

        // Explicitly handle "< 839" as text
        if ($input === '< 839') {
            return 'text'; // Treat this specific case as text
        }

        // Check if it is a numeric value, including scientific notation
        if (is_numeric($input)) {
            return 'numeric';
        } elseif (preg_match('/^([<>])\s*(\d+(\.\d+)?(E[+-]?\d+)?)$/i', $input, $matches)) {
            // Ensure the numeric value after < or > is handled correctly
            if (isset($matches[2]) && is_numeric($matches[2])) {
                return 'numeric'; // The part after < or > is numeric
            }
        }

        // If not null, not empty, and not numeric, it's text
        return 'text';
    }

    public function extractViralLoadValue($input, $returnWithOperator = true): ?string
    {
        // Trim the input to remove leading/trailing whitespace
        $input = trim((string) $input);

        if (is_numeric($input)) {
            return floatval($input);
        } elseif (preg_match('/^([<>])\s*(\d+(\.\d+)?(E[+-]?\d+)?)$/i', $input, $matches)) {
            // Ensure the numeric value after < or > is handled correctly
            if ($returnWithOperator) {
                $operator = $matches[1] ?? '';
            } else {
                $operator = '';
            }
            if (isset($matches[2]) && is_numeric($matches[2])) {
                return trim("$operator " . floatval($matches[2])); // The part after < or > is numeric
            }
        }

        return null;
    }

    public function getLabStorage($labId = null, $onlyActive = true)
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
