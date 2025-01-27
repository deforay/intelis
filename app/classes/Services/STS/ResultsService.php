<?php

namespace App\Services\STS;
use JsonMachine\Items;
use SAMPLE_STATUS;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Abstracts\AbstractTestService;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use App\Utilities\LoggerUtility;
use App\Utilities\QueryLoggerUtility;
use App\Exceptions\SystemException;



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

    protected $fieldsToRemoveForAcceptedResults = [];
    protected $unwantedColumns = [];

    public function __construct(DatabaseService $db, CommonService $commonService, UsersService $usersService)
    {
        $this->db = $db ?? ContainerRegistry::get(DatabaseService::class);
        $this->commonService = $commonService;
        $this->usersService = $usersService;
    }

    private function setTestType($testType)
    {
        $this->testType = $testType;
        $this->tableName = TestsService::getTestTableName($testType);
        $this->primaryKeyName = TestsService::getTestPrimaryKeyColumn($testType);
        $serviceClass = TestsService::getTestServiceClass($testType);

        $this->testTypeService = ContainerRegistry::get($serviceClass);
    }

    public function setFieldsToRemoveForAcceptedResults($fieldsToRemove)
    {
        $this->fieldsToRemoveForAcceptedResults = $fieldsToRemove;
    }

    public function setUnwantedColumns($unwantedColumns)
    {
        $this->unwantedColumns = $unwantedColumns;
    }

    public function getApproverName($approverId)
    {
        return $this->usersService->getOrCreateUser($approverId);
    }

    public function getResults($testType, $jsonResponse)
    {

        $this->setTestType($testType);

             //remove unwanted columns
       $unwantedColumns = [
        $this->primaryKeyName,
        'sample_package_id',
        'sample_package_code',
     //   'result_printed_datetime',
        'request_created_by'
    ];

    // Create an array with all column names set to null
    $emptyLabArray = $this->commonService->getTableFieldsAsArray($this->tableName, $unwantedColumns);


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
        foreach ($resultData as $key => $resultRow) {

                $counter++;
              
                $resultRow = MiscUtility::arrayEmptyStringsToNull($resultRow);
                // Overwrite the values in $emptyLabArray with the values in $resultRow
                $lab = MiscUtility::updateFromArray($emptyLabArray, $resultRow);

                if (isset($resultRow['approved_by_name']) && !empty($resultRow['approved_by_name'])) {

                    $lab['result_approved_by'] = $this->usersService->getOrCreateUser($resultRow['approved_by_name']);
                    $lab['result_approved_datetime'] ??= DateUtility::getCurrentDateTime();
                }

                //data_sync = 1 means data sync done. data_sync = 0 means sync is not yet done.
                $lab['data_sync'] = 1;
                $lab['last_modified_datetime'] = DateUtility::getCurrentDateTime();


                if ($lab['result_status'] != SAMPLE_STATUS\ACCEPTED && $lab['result_status'] != SAMPLE_STATUS\REJECTED) {
                  
                    $lab = MiscUtility::removeFromAssociativeArray($lab, $unwantedColumns);
                }

            if($testType == "covid19" || $testType == "generic-tests"){
                $formData = $resultRow['form_data'] ?? [];
                if (empty($formData)) {
                    continue;
                }
    
                // Overwrite the values in $emptyLabArray with the values in $formData
                $lab = MiscUtility::updateFromArray($emptyLabArray, $formData);
    
                if (isset($lab['approved_by_name']) && $lab['approved_by_name'] != '') {
    
                    $lab['result_approved_by'] = $this->usersService->getOrCreateUser($lab['approved_by_name']);
                    $lab['result_approved_datetime'] ??= DateUtility::getCurrentDateTime();
                    // we dont need this now
                    //unset($lab['approved_by_name']);
                }
    
                $lab['data_sync'] = 1; //data_sync = 1 means data sync done. data_sync = 0 means sync is not yet done.
                $lab['last_modified_datetime'] = DateUtility::getCurrentDateTime();
    
    
                if ($lab['result_status'] != SAMPLE_STATUS\ACCEPTED && $lab['result_status'] != SAMPLE_STATUS\REJECTED) {
                    $keysToRemove = [
                        'result',
                        'is_sample_rejected',
                        'reason_for_sample_rejection'
                    ];
                    $lab = MiscUtility::removeFromAssociativeArray($lab, $keysToRemove);
                }
            }

                $sResult = $this->runQuery($lab);
              
                $formAttributes = JsonUtility::jsonToSetString($lab['form_attributes'], 'form_attributes');
                $lab['form_attributes'] = !empty($formAttributes) ? $this->db->func($formAttributes) : null;

                if (!empty($sResult)) {
                    $this->db->where($this->primaryKeyName, $sResult[$this->primaryKeyName]);
                    $id = $this->db->update($this->tableName, $lab);
                    $primaryKeyValue = $sResult[$this->primaryKeyName];

                } else {
                    $id = $this->db->insert($this->tableName, $lab);
                    $primaryKeyValue = $this->db->getInsertId();
                }
                if($testType == "covid19"){

                    // Insert covid19_tests
                    $testsData = $resultRow[$key]['data_from_tests'] ?? [];

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
                }
                if($testType == "generic-tests"){
                    // Insert generic_test_results
                $testsData = $resultRow['data_from_tests'] ?? [];

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

                if ($id === true && isset($lab['sample_code'])) {
                    array_push($sampleCodes, $lab['sample_code']);
                    array_push($facilityIds, $lab['facility_id']);
                }

        }
    }

    return $sampleCodes;

    }

    private function runQuery($lab)
    {
        [$conditions, $params] = $this->buildCondition($lab);

        $sResult = [];
        if (!empty($conditions)) {
            $sQuery = "SELECT $this->primaryKeyName FROM $this->tableName WHERE " . implode(' OR ', $conditions) . " FOR UPDATE";
            $sResult = $this->db->rawQueryOne($sQuery, $params);
        }

        return $sResult;
    }

    private function buildCondition($lab)
    {
        // Checking if Remote Sample ID is set, if not set we will check if Sample ID is set
        $conditions = [];
        $params = [];

        if (!empty($lab['unique_id'])) {
            $conditions[] = "unique_id = ?";
            $params[] = $lab['unique_id'];
        } elseif (!empty($lab['remote_sample_code'])) {
            $conditions[] = "remote_sample_code = ?";
            $params[] = $lab['remote_sample_code'];
        } elseif (!empty($lab['sample_code'])) {
            if (!empty($lab['lab_id'])) {
                $conditions[] = "sample_code = ? AND lab_id = ?";
                $params[] = $lab['sample_code'];
                $params[] = $lab['lab_id'];
            } elseif (!empty($lab['facility_id'])) {
                $conditions[] = "sample_code = ? AND facility_id = ?";
                $params[] = $lab['sample_code'];
                $params[] = $lab['facility_id'];
            }
        }

        return [$conditions, $params];
    }

}
