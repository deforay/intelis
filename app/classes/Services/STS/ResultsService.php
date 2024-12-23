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
use App\Services\Covid19Service;
use App\Services\DatabaseService;
use App\Services\HepatitisService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use App\Abstracts\AbstractTestService;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use App\Utilities\LoggerUtility;


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
            'result_printed_datetime',
            'request_created_by'
        ];
    
        $options = [
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);

        foreach ($parsedData as $name => $data) {
            if ($name === 'labId') {
                $labId = $data;
            } elseif ($name === 'result') {
                $resultData = $data;
            }
        }

        $emptyLabArray = $this->commonService->getTableFieldsAsArray($this->tableName, $unwantedColumns);

        $sampleCodes = $facilityIds = [];

        $counter = 0;
        foreach ($resultData as $key => $resultRow) {

            $counter++;
            $resultRow = MiscUtility::arrayEmptyStringsToNull($resultRow);
            // Overwrite the values in $emptyLabArray with the values in $resultRow
            $lab = MiscUtility::updateFromArray($emptyLabArray, $resultRow);

            if (isset($resultRow['approved_by_name']) && !empty($resultRow['approved_by_name'])) {

                $lab['result_approved_by'] = $this->usersService->getOrCreateUser($resultRow['approved_by_name']);
                $lab['result_approved_datetime'] ??= DateUtility::getCurrentDateTime();
                // we dont need this now
                //unset($resultRow['approved_by_name']);
            }

            //data_sync = 1 means data sync done. data_sync = 0 means sync is not yet done.
            $lab['data_sync'] = 1;
            $lab['last_modified_datetime'] = DateUtility::getCurrentDateTime();


            if ($lab['result_status'] != SAMPLE_STATUS\ACCEPTED && $lab['result_status'] != SAMPLE_STATUS\REJECTED) {
                $keysToRemove = [
                    'result',
                    'result_value_log',
                    'result_value_absolute',
                    'result_value_text',
                    'result_value_absolute_decimal',
                    'is_sample_rejected',
                    'reason_for_sample_rejection'
                ];
                $lab = MiscUtility::removeFromAssociativeArray($lab, $unwantedColumns);
            }

                $sResult = $this->runQuery($lab);

                $formAttributes = JsonUtility::jsonToSetString($lab['form_attributes'], 'form_attributes');
                $lab['form_attributes'] = !empty($formAttributes) ? $this->db->func($formAttributes) : null;

                if (!empty($sResult)) {
                    $this->db->where($this->primaryKeyName, $sResult[$this->primaryKeyName]);
                    $id = $this->db->update($this->tableName, $lab);
                } else {
                    $id = $this->db->insert($this->tableName, $lab);
                }

                if ($id === true && isset($lab['sample_code'])) {
                    $sampleCodes[] = $lab['sample_code'];
                    $facilityIds[] = $lab['facility_id'];
                }

        }

        return [$sampleCodes, $facilityIds];
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

    private function returnResults($id, array $lab): array
    {
        $sampleCodes = $facilityIds = [];

        if ($id === true && isset($lab['sample_code'])) {
            $sampleCodes[] = $lab['sample_code'];
            $facilityIds[] = $lab['facility_id'];
        }
     
          
        return [
            'sampleCodes' => $sampleCodes,
            'facilityIds' => $facilityIds
        ];
    }
}
