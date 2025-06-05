<?php

namespace App\Services\STS;

use SAMPLE_STATUS;
use JsonMachine\Items;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;
use App\Abstracts\AbstractTestService;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

final class ResultsService
{
    protected CommonService $commonService;
    protected DatabaseService $db;
    protected string $testType;
    protected string $tableName;
    protected string $primaryKeyName;

    /** @var AbstractTestService $testTypeService */
    protected $testTypeService;
    protected $usersService;
    protected $testRequestsService;
    protected $fieldsToRemoveForAcceptedResults = [];
    protected $unwantedColumns = [];

    public function __construct(DatabaseService $db, CommonService $commonService, UsersService $usersService, TestRequestsService $testRequestsService)
    {
        $this->db = $db ?? ContainerRegistry::get(DatabaseService::class);
        $this->commonService = $commonService;
        $this->usersService = $usersService;
        $this->testRequestsService = $testRequestsService;
    }


    private function setTestType($testType)
    {
        $this->testType = $testType;
        $this->tableName = TestsService::getTestTableName($testType);
        $this->primaryKeyName = TestsService::getTestPrimaryKeyColumn($testType);
        $serviceClass = TestsService::getTestServiceClass($testType);

        $this->testTypeService = ContainerRegistry::get($serviceClass);
    }

    public function receiveResults($testType, $jsonResponse, $isSilent = false)
    {

        $this->setTestType($testType);

        //remove unwanted columns
        $unwantedColumns = [
            $this->primaryKeyName,
            'sample_package_id',
            'sample_package_code',
            'request_created_by',
            'result_printed_on_sts_datetime',
            'result_printed_datetime'
        ];

        // Create an array with all column names set to null
        $localDbFieldArray = $this->commonService->getTableFieldsAsArray($this->tableName, $unwantedColumns);


        $sampleCodes = $facilityIds = [];
        $labId = null;


        if (!empty($jsonResponse) && $jsonResponse != '[]' && JsonUtility::isJSON($jsonResponse)) {

            $resultData = [];
            $options = [
                'decoder' => new ExtJsonDecoder(true)
            ];
            $parsedData = Items::fromString($jsonResponse, $options);
            foreach ($parsedData as $name => $data) {
                if ($name === 'labId') {
                    $labId = $data;
                } elseif ($name === 'results') {
                    $resultData = $data;
                }
            }

            $counter = 0;
            foreach ($resultData as $key => $dataFromLIS) {
                try {
                    if (empty($dataFromLIS)) {
                        continue;
                    }
                    $this->db->beginTransaction();
                    $id = false;
                    $counter++;
                    if ($testType == "covid19" || $testType == "generic-tests") {
                        $originalLISRecord = $dataFromLIS['form_data'] ?? [];
                    } else {
                        $originalLISRecord = $dataFromLIS ?? [];
                    }

                    if (empty($originalLISRecord)) {
                        continue;
                    }

                    // nullify empty strings
                    $originalLISRecord = MiscUtility::arrayEmptyStringsToNull($originalLISRecord);

                    // Overwrite the values in $localDbFieldArray with the values in $originalLISRecord
                    // basically we are making sure that we only use columns that are present in the $localDbFieldArray
                    // which is from local db and not using the ones in the $originalLISRecord
                    $resultFromLab = MiscUtility::updateMatchingKeysOnly($localDbFieldArray, $originalLISRecord);

                    if (isset($originalLISRecord['approved_by_name']) && !empty($originalLISRecord['approved_by_name'])) {

                        $resultFromLab['result_approved_by'] = $this->usersService->getOrCreateUser($originalLISRecord['approved_by_name']);
                        //$resultFromLab['result_approved_datetime'] ??= DateUtility::getCurrentDateTime();
                    }

                    //data_sync = 1 means data sync done. data_sync = 0 means sync is not yet done.
                    $resultFromLab['data_sync'] = 1;
                    $resultFromLab['last_modified_datetime'] = DateUtility::getCurrentDateTime();

                    if ($resultFromLab['result_status'] != SAMPLE_STATUS\ACCEPTED && $resultFromLab['result_status'] != SAMPLE_STATUS\REJECTED) {
                        $keysToRemove = [
                            'result',
                            'is_sample_rejected',
                            'reason_for_sample_rejection'
                        ];
                        $resultFromLab = MiscUtility::excludeKeys($resultFromLab, $keysToRemove);
                    }

                    $localRecord = $this->testRequestsService->findMatchingLocalRecord($resultFromLab, $this->tableName, $this->primaryKeyName);

                    $formAttributes = JsonUtility::jsonToSetString($resultFromLab['form_attributes'], 'form_attributes');
                    $resultFromLab['form_attributes'] = !empty($formAttributes) ? $this->db->func($formAttributes) : null;

                    if (!empty($localRecord)) {

                        if (MiscUtility::isArrayEqual($resultFromLab, $localRecord, ['last_modified_datetime', 'form_attributes'])) {
                            $primaryKeyValue = $localRecord[$this->primaryKeyName];
                            continue;
                        }

                        if ($isSilent) {
                            unset($resultFromLab['last_modified_datetime']);
                        }

                        $this->db->where($this->primaryKeyName, $localRecord[$this->primaryKeyName]);
                        $id = $this->db->update($this->tableName, $resultFromLab);
                        $primaryKeyValue = $localRecord[$this->primaryKeyName];
                    } else {
                        $id = $this->db->insert($this->tableName, $resultFromLab);
                        $primaryKeyValue = $this->db->getInsertId();
                    }
                    if ($testType == "covid19") {

                        // Insert covid19_tests
                        $testsData = $dataFromLIS[$key]['data_from_tests'] ?? [];

                        $this->db->where($this->primaryKeyName, $primaryKeyValue);
                        $this->db->delete("covid19_tests");
                        foreach ($testsData as $tRow) {
                            $covid19TestData = [
                                "covid19_id"                => $primaryKeyValue,
                                "facility_id"               => $tRow['facility_id'],
                                "test_name"                 => $tRow['test_name'],
                                "tested_by"                 => $tRow['tested_by'],
                                "sample_tested_datetime"    => $tRow['sample_tested_datetime'],
                                "testing_platform"          => $tRow['testing_platform'],
                                "instrument_id"             => $tRow['instrument_id'],
                                "kit_lot_no"                => $tRow['kit_lot_no'],
                                "kit_expiry_date"           => $tRow['kit_expiry_date'],
                                "result"                    => $tRow['result'],
                                "updated_datetime"          => $tRow['updated_datetime']
                            ];
                            $this->db->insert("covid19_tests", $covid19TestData);
                        }
                    } elseif ($testType == "generic-tests") {
                        // Insert generic_test_results
                        $testsData = $dataFromLIS['data_from_tests'] ?? [];

                        $this->db->where('generic_id', $primaryKeyValue);
                        $this->db->delete("generic_test_results");
                        foreach ($testsData as $tRow) {
                            $customTestData = [
                                "generic_id" => $primaryKeyValue,
                                "test_name" => $tRow['test_name'],
                                "facility_id" => $tRow['facility_id'],
                                "sample_tested_datetime" => $tRow['sample_tested_datetime'],
                                "testing_platform" => $tRow['testing_platform'],
                                "result" => $tRow['result'],
                                "updated_datetime" => DateUtility::getCurrentDateTime()
                            ];
                            $this->db->insert("generic_test_results", $customTestData);
                        }
                    }

                    if ($id === true && isset($resultFromLab['sample_code'])) {
                        array_push($sampleCodes, $resultFromLab['sample_code']);
                        array_push($facilityIds, $resultFromLab['facility_id']);
                    }
                    $this->db->commitTransaction();
                } catch (\Throwable $e) {
                    $this->db->rollbackTransaction();
                    LoggerUtility::logError($e->getMessage(), [
                        'line' => $e->getLine(),
                        'file' => $e->getFile(),
                        'local_unique_id' => $localRecord['unique_id'] ?? null,
                        'local_sample_code' => $localRecord['sample_code'] ?? null,
                        'local_remote_sample_code' => $localRecord['remote_sample_code'] ?? null,
                        'local_lab_id' => $localRecord['lab_id'] ?? null,
                        'local_facility_id' => $localRecord['facility_id'] ?? null,
                        'received_unique_id' => $dataFromLIS['unique_id'] ?? null,
                        'received_sample_code' => $dataFromLIS['sample_code'] ?? null,
                        'received_remote_sample_code' => $dataFromLIS['remote_sample_code'] ?? null,
                        'received_facility_id' => $dataFromLIS['facility_id'] ?? null,
                        'test_type' => $this->testType,
                        'lab_id' => $labId,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }

        return $sampleCodes;
    }
}
