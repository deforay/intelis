<?php

namespace App\Services;

use Generator;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

final class BatchService
{
    protected ?DatabaseService $db;

    public function __construct(?DatabaseService $db)
    {
        $this->db = $db ?? ContainerRegistry::get(DatabaseService::class);
    }

    public function doesBatchCodeExist($code)
    {
        $this->db->where("batch_code", $code);
        return $this->db->getOne("batch_details");
    }

    public function createBatchCode(): array
    {
        $batchQuery = 'SELECT IFNULL(MAX(batch_code_key), 0) + 1 AS maxId
                        FROM batch_details as bd
                        WHERE DATE(bd.request_created_datetime) = CURRENT_DATE';
        $batchResult = $this->db->rawQueryOne($batchQuery);

        $batchCode = date('Ymd') . sprintf("%03s", $batchResult['maxId']);

        return [$batchResult['maxId'], $batchCode];
    }

    public function excelColumnRange($lower, $upper): Generator
    {
        ++$upper;
        for ($i = $lower; $i !== $upper; ++$i) {
            yield $i;
        }
    }

    public function getSortType($sortType): string
    {
        return match ($sortType) {
            'd', 'desc' => 'desc',
            default => 'asc',
        };
    }

    public function getOrderBy($sortBy, string $sortType): string
    {
        return match ($sortBy) {
                'lastModified' => 'last_modified_datetime',
            'requestCreated' => 'request_created_datetime',
            'labAssignedCode' => 'lab_assigned_code',
            default => 'sample_code',
        }
            . ' ' . $sortType;
    }

    public function getBatchInfo($id)
    {
        $batchQuery = "SELECT * FROM batch_details as b_d
                INNER JOIN instruments as i_c ON i_c.instrument_id=b_d.machine
                WHERE batch_id= ?";
        return $this->db->rawQueryOne($batchQuery, [$id]);
    }

    public function generateAlphaNumericRange(): array
    {
        $alphaNumeric = [];
        foreach (range('A', 'H') as $value) {
            foreach (range(1, 12) as $no) {
                $alphaNumeric[] = $value . $no;
            }
        }
        return $alphaNumeric;
    }

    public function formatLabel($value): string|array
    {
        $str = str_replace("_", " ", (string)$value);
        if (substr_count($str, 'in house') > 0) {
            return str_replace("in house", "In-House", $value);
        } elseif (substr_count($str, 'manufacturer controls') > 0) {
            return str_replace("manufacturer control", "Manufacturer Control", $value);
        } elseif (substr_count($str, 'calibrator') > 0) {
            return str_replace("calibrator", "Calibrator", $value);
        } else {
            return $str;
        }
    }

    /**
     * @return array<mixed, array<'noCalibrators'|'noHouseCtrl'|'noManufacturerCtrl', mixed>>
     */
    public function getConfigControl($machine): array
    {
        $configControlQuery = "SELECT * FROM instrument_controls WHERE instrument_id= ? ";
        $configControlInfo = $this->db->rawQuery($configControlQuery, [$machine]);
        $configControl = [];
        foreach ($configControlInfo as $info) {
            $configControl[$info['test_type']]['noHouseCtrl'] = $info['number_of_in_house_controls'];
            $configControl[$info['test_type']]['noManufacturerCtrl'] = $info['number_of_manufacturer_controls'];
            $configControl[$info['test_type']]['noCalibrators'] = $info['number_of_calibrators'];
        }
        return $configControl;
    }

    public function getBatchControlNames($batchInfo, $prevBatchControlNames)
    {
        if (!empty($batchInfo['control_names'])) {
            return json_decode((string)$batchInfo['control_names'], true);
        } else {
            return $prevBatchControlNames;
        }
    }

    public function getSamplesByBatchId($table, $primaryKeyColumn, $patientIdColumn, $id, $orderBy)
    {
        $samplesQry = "SELECT sample_code,$patientIdColumn,$primaryKeyColumn FROM $table WHERE sample_batch_id = ? ORDER BY $orderBy";
        return $this->db->rawQuery($samplesQry, [$id]);
    }

    public function getPreviousBatchControlNames($machine): mixed
    {
        $prevMachineControlQuery = "SELECT control_names from batch_details WHERE machine = ? AND control_names IS NOT NULL  ORDER BY batch_id DESC LIMIT 0,1";
        $prevMachineControlInfo = $this->db->rawQuery($prevMachineControlQuery, [$machine]);
        return json_decode((string)$prevMachineControlInfo[0]['control_names'], true);
    }

    public function generateDefaultContent(array $configControl, $primaryKeyColumn, $patientIdColumn, $orderBy, $id, $table, $testType): array
    {
        $content = '';
        $labelNewContent = '';
        $displayOrder = [];
        if (isset($configControl[$testType]['noHouseCtrl']) && $configControl[$testType]['noHouseCtrl'] > 0) {
            foreach (range(1, $configControl[$testType]['noHouseCtrl']) as $h) {
                $displayOrder[] = "in_house_controls_" . $h;
                $content .= '<li class="ui-state-default" id="in_house_controls_' . $h . '">In-House Control ' . $h . '</li>';
                $labelNewContent .= ' <tr><th>In-House Control ' . $h . ':</th><td> <input class="form-control" type="text" name="controls[in_house_controls_' . $h . ']" value="" placeholder="Enter label name"/></td></tr>';
            }
        }
        if (isset($configControl[$testType]['noManufacturerCtrl']) && $configControl[$testType]['noManufacturerCtrl'] > 0) {
            foreach (range(1, $configControl[$testType]['noManufacturerCtrl']) as $m) {
                $displayOrder[] = "manufacturer_controls_" . $m;
                $content .= '<li class="ui-state-default" id="manufacturer_controls_' . $m . '">Manufacturer Control ' . $m . '</li>';
                $labelNewContent .= ' <tr><th>Manufacturer Control ' . $m . ' :</th><td> <input class="form-control" type="text" name="controls[manufacturer_controls_' . $m . ']" value="" placeholder="Enter label name"/></td></tr>';
            }
        }
        if (isset($configControl[$testType]['noCalibrators']) && $configControl[$testType]['noCalibrators'] > 0) {
            foreach (range(1, $configControl[$testType]['noCalibrators']) as $c) {
                $displayOrder[] = "calibrators_" . $c;
                $content .= '<li class="ui-state-default" id="calibrators_' . $c . '">Calibrator ' . $c . '</li>';
                $labelNewContent .= ' <tr><th>Calibrator ' . $c . ' :</th><td> <input class="form-control" type="text" name="controls[calibrators_' . $c . ']" value="" placeholder="Enter label name"/></td></tr>';
            }
        }
        $samplesQuery = "SELECT $primaryKeyColumn ,$patientIdColumn, sample_code FROM $table WHERE sample_batch_id=? ORDER BY $orderBy";
        $samplesInfo = $this->db->rawQuery($samplesQuery, [$id]);
        foreach ($samplesInfo as $sample) {
            $displayOrder[] = "s_" . $sample[$primaryKeyColumn];
            $content .= '<li class="ui-state-default" id="s_' . $sample[$primaryKeyColumn] . '">' . $sample['sample_code'] . ' - ' . $sample[$patientIdColumn] . '</li>';
        }
        return ['content' => $content, 'labelNewContent' => $labelNewContent, 'displayOrder' => $displayOrder];
    }

    public function generateLabelOrderContent(array $batchInfo, $batchControlNames, $samplesResult, $samplesCount, $primaryKeyColumn, $patientIdColumn, $table, $testType): array
    {
        $labelNewContent = '';
        $displayOrder = [];
        $alphaNumeric = $this->generateAlphaNumericRange();
        $jsonToArray = json_decode((string)$batchInfo['label_order'], true);
        $batchControlNames ??= [];
        $content = '';
        $controls = '';
        if (!empty($jsonToArray) && (count($jsonToArray) != ($samplesCount + count($batchControlNames)))) {
            foreach ($samplesResult as $sample) {
                $displayOrder[] = "s_" . $sample[$primaryKeyColumn];
                $label = $sample['sample_code'] . " - " . $sample[$patientIdColumn];
                $content .= '<li class="ui-state-default" id="s_' . $sample[$primaryKeyColumn] . '">' . $label . '</li>';
            }
            if (!empty($batchControlNames) && count($batchControlNames) > 0) {
                foreach ($batchControlNames as $key => $value) {
                    $displayOrder[] = $value;
                    $clabel = str_replace("in house", "In-House", $value);
                    $clabel = str_replace("no of ", " ", $clabel);
                    $existingValue = $value ?? "";
                    $liLabel = $existingValue ?: $clabel;
                    $controls .= '<li class="ui-state-default" id="' . $key . '">' . $liLabel . '</li>';
                    $labelNewContent .= ' <tr><th>' . $liLabel . ' :</th><td> <input class="form-control" type="text" name="controls[' . $key . ']" value="' . $existingValue . '" placeholder="Enter label name"/></td></tr>';
                }
            } else {
                $labelControls = preg_grep("/^no_of_/i", $jsonToArray);
                foreach ($labelControls as $value) {
                    $displayOrder[] = $value;
                    $clabel = $this->formatLabel($value);
                    $controls .= '<li class="ui-state-default" id="' . $value . '">' . $clabel . '</li>';
                    $labelNewContent .= ' <tr><th>' . $clabel . ' :</th><td> <input class="form-control" type="text" name="controls[' . $clabel . ']" value="" placeholder="Enter label name"/></td></tr>';
                }
            }
        } else {
            $controls = '';
            foreach ($jsonToArray as $j => $jsonValue) {
                $index = ($batchInfo['position_type'] == 'alpha-numeric') ? $alphaNumeric[$j] : $j;
                $displayOrder[] = $jsonValue;
                $xplodJsonToArray = explode("_", (string)$jsonValue);

                if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] === "s") {
                    $sampleQuery = "SELECT sample_code, $patientIdColumn FROM $table WHERE  $primaryKeyColumn = ?";
                    $sampleResult = $this->db->rawQuery($sampleQuery, [$xplodJsonToArray[1]]);
                    $label = $sampleResult[0]['sample_code'] . " - " . $sampleResult[0][$patientIdColumn];
                    $content .= '<li class="ui-state-default" id="' . $jsonValue . '">' . $label . '</li>';
                } else {
                    $label = $this->formatLabel($jsonValue);
                    $existingValue = $batchControlNames[$jsonValue] ?? "";
                    $liLabel = $existingValue ?: $label;
                    $labelNewContent .= ' <tr><th>' . $jsonValue . ' :</th><td> <input class="form-control" type="text" name="controls[' . $jsonValue . ']" value="' . $existingValue . '" placeholder="Enter label name"/></td></tr>';
                    $controls .= '<li class="ui-state-default" id="' . $jsonValue . '">' . $liLabel . '</li>';
                }
            }
        }

        $content = $controls . $content;
        return ['content' => $content, 'labelNewContent' => $labelNewContent, 'displayOrder' => $displayOrder];
    }

    public function generateContent($samplesResult, $batchInfo, $batchControlNames, $configControl, $samplesCount, $table, $primaryKeyColumn, $patientIdColumn, $testType, $orderBy, $id): array
    {
        $content = '';
        $labelNewContent = '';
        $displayOrder = [];
        if (isset($batchInfo['label_order']) && trim((string)$batchInfo['label_order']) !== '') {
            $contentData = $this->generateLabelOrderContent($batchInfo, $batchControlNames, $samplesResult, $samplesCount, $primaryKeyColumn, $patientIdColumn, $table, $testType);
            $content = $contentData['content'];
            $labelNewContent = $contentData['labelNewContent'];
            $displayOrder = $contentData['displayOrder'];
        } else {
            $contentData = $this->generateDefaultContent($configControl, $primaryKeyColumn, $patientIdColumn, $orderBy, $id, $table, $testType);
            $content = $contentData['content'];
            $labelNewContent = $contentData['labelNewContent'];
            $displayOrder = $contentData['displayOrder'];
        }
        return ['content' => $content, 'labelNewContent' => $labelNewContent, 'displayOrder' => $displayOrder];
    }

    public function getLastInstumentForBatch($testType)
    {
        $getmachineQuery = "SELECT machine FROM batch_details WHERE test_type= ? ORDER BY `request_created_datetime` DESC limit 1";
        $machineData = $this->db->rawQueryOne($getmachineQuery, [$testType]);
        return $machineData['machine'];
    }
}
