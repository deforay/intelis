<?php

namespace App\Abstracts;

use COUNTRY;
use Exception;
use DateTimeImmutable;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\GeoLocationsService;

abstract class AbstractTestService
{
    protected DatabaseService $db;
    protected CommonService $commonService;
    protected GeoLocationsService $geoLocationsService;

    public function __construct(
        DatabaseService $db,
        CommonService $commonService,
        GeoLocationsService $geoLocationsService
    ) {
        $this->db = $db;
        $this->commonService = $commonService;
        $this->geoLocationsService = $geoLocationsService;
    }
    abstract public function getSampleCode($params);
    abstract public function insertSample($params, $returnSampleData = false);

    public function generateSampleCode($testTable, $params): bool|string
    {

        $sampleCodeGenerator = [];
        $formId = $this->commonService->getGlobalConfig('vl_form');
        $userType = $this->commonService->getSystemConfig('sc_user_type');

        $insertOperation = $params['insertOperation'] ?? false;

        $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;
        $provinceCode = $params['provinceCode'] ?? null;
        //$provinceId = $params['provinceId'] ?? null;
        $sampleCodeFormat = $params['sampleCodeFormat'] ?? 'MMYY';
        $prefix = $params['prefix'] ?? 'T';
        $testType = $params['testType'];
        $existingMaxId = $params['existingMaxId'] ?? null;

        if (empty($sampleCollectionDate) || DateUtility::isDateValid($sampleCollectionDate) === false) {
            $sampleCollectionDate = 'now';
        }
        $dateObj = new DateTimeImmutable($sampleCollectionDate);

        $currentYear = $year = $dateObj->format('y');
        $month = $dateObj->format('m');
        $day = $dateObj->format('d');

        $autoFormatedString = $year . $month . $day;

        $remotePrefix = '';
        $sampleCodeKeyCol = 'sample_code_key';
        $sampleCodeType = 'sample_code';
        if (!empty($userType) && $userType == 'remoteuser') {
            $remotePrefix = 'R';
            $sampleCodeKeyCol = 'remote_sample_code_key';
            $sampleCodeType = 'remote_sample_code';
        }

        try {
            $yearData = [];

            if (!empty($existingMaxId) && $existingMaxId > 0) {
                $maxId = $existingMaxId + 1;
            } else {
                // Determine the sequence number
                $sql = "SELECT * FROM sequence_counter
                        WHERE year = ? AND
                        test_type = ? AND
                        code_type = ?";

                if ($insertOperation) {
                    $sql .= " FOR UPDATE";
                }
                $yearData = $this->db->rawQueryOne($sql, [
                    $currentYear,
                    $testType,
                    $sampleCodeType
                ]);
                if (!empty($yearData)) {
                    $maxId = $yearData['max_sequence_number'] + 1;
                } else {
                    $maxId = 1;
                }
            }

            //$maxId = sprintf("%04d", (int) $maxId);

            $sampleCodeGenerator = [
                'sampleCode' => $remotePrefix . $prefix . $year . $maxId,
                'sampleCodeInText' => $remotePrefix . $prefix . $year . $maxId,
                'sampleCodeFormat' => $sampleCodeFormat,
                'sampleCodeKey' => $maxId,
                'maxId' => $maxId,
                'mnthYr' => $month . $year,
                'auto' => $year . $month . $day
            ];

            // PNG format has an additional R in prefix
            if ($formId == COUNTRY\PNG) {
                $remotePrefix = $remotePrefix . "R";
            }


            if ($sampleCodeFormat == 'auto') {
                $sampleCodeGenerator['sampleCode'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sampleCodeGenerator['maxId']);
                $sampleCodeGenerator['sampleCodeInText'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sampleCodeGenerator['maxId']);
                $sampleCodeGenerator['sampleCodeFormat'] = ($remotePrefix . $provinceCode . $autoFormatedString);
                $sampleCodeGenerator['sampleCodeKey'] = ($sampleCodeGenerator['maxId']);
            } elseif ($sampleCodeFormat == 'auto2') {
                $sampleCodeGenerator['sampleCode'] = $remotePrefix . $year . $provinceCode . $prefix . $sampleCodeGenerator['maxId'];
                $sampleCodeGenerator['sampleCodeInText'] = $remotePrefix . $year . $provinceCode . $prefix . $sampleCodeGenerator['maxId'];
                $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $provinceCode . $autoFormatedString;
                $sampleCodeGenerator['sampleCodeKey'] = $sampleCodeGenerator['maxId'];
            } elseif ($sampleCodeFormat == 'YY' || $sampleCodeFormat == 'MMYY') {
                $sampleCodeGenerator['sampleCode'] = $remotePrefix . $prefix . $sampleCodeGenerator['mnthYr'] . $sampleCodeGenerator['maxId'];
                $sampleCodeGenerator['sampleCodeInText'] = $remotePrefix . $prefix . $sampleCodeGenerator['mnthYr'] . $sampleCodeGenerator['maxId'];
                $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $prefix . $sampleCodeGenerator['mnthYr'];
                $sampleCodeGenerator['sampleCodeKey'] = ($sampleCodeGenerator['maxId']);
            }

            $checkQuery = "SELECT $sampleCodeType, $sampleCodeKeyCol
                        FROM $testTable
                        WHERE $sampleCodeType= ?";
            $checkResult = $this->db->rawQueryOne($checkQuery, [$sampleCodeGenerator['sampleCode']]);
            if (!empty($checkResult)) {
                error_log("DUP::: Sample ID ====== " . $sampleCodeGenerator['sampleCode']);
                error_log("DUP::: Sample Key Code ====== " . $maxId);
                $params['existingMaxId'] = $maxId;
                return $this->generateSampleCode($testTable, $params);
            }

            if ($insertOperation) {
                if ($maxId == 1) {
                    $this->db->insert('sequence_counter', [
                        'test_type' => $testType,
                        'year' => $currentYear,
                        'max_sequence_number' => $maxId,
                        'code_type' => $sampleCodeType
                    ]);
                } else {

                    $this->db->where('code_type', $sampleCodeType);
                    $this->db->where('year', $currentYear);
                    $this->db->where('test_type', $testType);
                    $this->db->update('sequence_counter', ['max_sequence_number' => $maxId]);
                }
            }
        } catch (Exception $e) {
            // Error handling logic
            error_log("Error in generateSampleCode: " . $e->getMessage());
        }

        return json_encode($sampleCodeGenerator);
    }
}
