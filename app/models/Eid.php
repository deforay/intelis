<?php

namespace App\Models;

use App\Utilities\DateUtils;
use DateTimeImmutable;
use Exception;
use MysqliDb;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * General functions
 *
 * @author Amit
 */

class Eid
{

    protected $db = null;
    protected $table = 'form_eid';
    protected $shortCode = 'EID';

    public function __construct($db = null)
    {
        $this->db = !empty($db) ? $db : MysqliDb::getInstance();
    }

    public function generateEIDSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null)
    {

        $general = new General($this->db);
        $globalConfig = $general->getGlobalConfig();
        $vlsmSystemConfig = $general->getSystemConfig();

        if (DateUtils::verifyIfDateValid($sampleCollectionDate) === false) {
            $sampleCollectionDate = 'now';
        }
        $dateObj = new DateTimeImmutable($sampleCollectionDate);

        $year = $dateObj->format('y');
        $month = $dateObj->format('m');
        $day = $dateObj->format('d');

        $remotePrefix = '';
        $sampleCodeKeyCol = 'sample_code_key';
        $sampleCodeCol = 'sample_code';
        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $remotePrefix = 'R';
            $sampleCodeKeyCol = 'remote_sample_code_key';
            $sampleCodeCol = 'remote_sample_code';
        }
        // if (isset($user['access_type']) && !empty($user['access_type']) && $user['access_type'] != 'testing-lab') {
        //     $remotePrefix = 'R';
        //     $sampleCodeKeyCol = 'remote_sample_code_key';
        //     $sampleCodeCol = 'remote_sample_code';
        // }

        $mnthYr = $month . $year;
        // Checking if sample code format is empty then we set by default 'MMYY'
        $sampleCodeFormat = $globalConfig['eid_sample_code'] ?? 'MMYY';
        $prefixFromConfig = $globalConfig['eid_sample_code_prefix'] ?? '';

        if ($sampleCodeFormat == 'MMYY') {
            $mnthYr = $month . $year;
        } else if ($sampleCodeFormat == 'YY') {
            $mnthYr = $year;
        }

        $autoFormatedString = $year . $month . $day;


        if ($maxCodeKeyVal === null) {
            // If it is PNG form
            if ($globalConfig['vl_form'] == 5) {

                if (empty($provinceId) && !empty($provinceCode)) {
                    $geoLocations = new GeoLocations($this->db);
                    $provinceId = $geoLocations->getProvinceIDFromCode($provinceCode);
                }

                if (!empty($provinceId)) {
                    $this->db->where('province_id', $provinceId);
                }
            }

            $this->db->where('YEAR(sample_collection_date) = ?', array($dateObj->format('Y')));
            $maxCodeKeyVal = $this->db->getValue($this->table, "MAX($sampleCodeKeyCol)");
        }


        if (!empty($maxCodeKeyVal) && $maxCodeKeyVal > 0) {
            $maxId = $maxCodeKeyVal + 1;
        } else {
            $maxId = 1;
        }

        $maxId = sprintf("%04d", (int) $maxId);

        $sCodeKey = (array('maxId' => $maxId, 'mnthYr' => $mnthYr, 'auto' => $autoFormatedString));

        if ($globalConfig['vl_form'] == 5) {
            // PNG format has an additional R in prefix
            $remotePrefix = $remotePrefix . "R";
        }


        if ($sampleCodeFormat == 'auto') {
            $sCodeKey['sampleCode'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeInText'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeFormat'] = ($remotePrefix . $provinceCode . $autoFormatedString);
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        } else if ($sampleCodeFormat == 'auto2') {
            $sCodeKey['sampleCode'] = $remotePrefix . date('y', strtotime($sampleCollectionDate)) . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . date('y', strtotime($sampleCollectionDate)) . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $provinceCode . $autoFormatedString;
            $sCodeKey['sampleCodeKey'] = $sCodeKey['maxId'];
        } else if ($sampleCodeFormat == 'YY' || $sampleCodeFormat == 'MMYY') {
            $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'];
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        }

        $checkQuery = "SELECT $sampleCodeCol, $sampleCodeKeyCol FROM " . $this->table . " WHERE $sampleCodeCol='" . $sCodeKey['sampleCode'] . "'";
        $checkResult = $this->db->rawQueryOne($checkQuery);
        if ($checkResult !== null) {
            error_log("DUP::: Sample Code ====== " . $sCodeKey['sampleCode']);
            error_log("DUP::: Sample Key Code ====== " . $maxId);
            error_log('DUP::: ' . $this->db->getLastQuery());
            return $this->generateEIDSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $maxId, $user);
        }
        return json_encode($sCodeKey);
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

    public function getEidSampleTypes($updatedDateTime = null)
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

    public function generateExcelExport($params)
    {
        $general = new General();

        $eidModel = new Eid();
        $eidResults = $eidModel->getEidResults();

        //$sarr = $general->getSystemConfig();

        if (isset($_SESSION['eidRequestSearchResultQuery']) && trim($_SESSION['eidRequestSearchResultQuery']) != "") {

            $rResult = $this->db->rawQuery($_SESSION['eidRequestSearchResultQuery']);

            $excel = new Spreadsheet();
            $output = [];
            $sheet = $excel->getActiveSheet();

            $headings = array("S.No.", "Sample Code", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Testing Lab Name (Hub)", "Child ID", "Child Name", "Mother ID", "Child Date of Birth", "Child Age", "Child Gender", "Breastfeeding status", "PCR Test Performed Before", "Last PCR Test results", "Sample Collection Date", "Is Sample Rejected?", "Sample Tested On", "Result", "Sample Received On", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner");
            $colNo = 1;

            $styleArray = array(
                'font' => array(
                    'bold' => true,
                    'size' => 12,
                ),
                'alignment' => array(
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ),
                'borders' => array(
                    'outline' => array(
                        'style' => Border::BORDER_THIN,
                    ),
                )
            );

            $borderStyle = array(
                'alignment' => array(
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ),
                'borders' => array(
                    'outline' => array(
                        'style' => Border::BORDER_THIN,
                    ),
                )
            );

            $sheet->mergeCells('A1:AG1');
            $nameValue = '';
            foreach ($params as $key => $value) {
                if (trim($value) != '' && trim($value) != '-- Select --') {
                    $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
                }
            }
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . "1", html_entity_decode($nameValue));
            if ($params['withAlphaNum'] == 'yes') {
                foreach ($headings as $field => $value) {
                    $string = str_replace(' ', '', $value);
                    $value = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . "3", html_entity_decode($value));
                    $colNo++;
                }
            } else {
                foreach ($headings as $field => $value) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . "3", html_entity_decode($value));
                    $colNo++;
                }
            }
            $sheet->getStyle('A3:AG3')->applyFromArray($styleArray);

            $no = 1;
            foreach ($rResult as $aRow) {
                $row = [];
                //date of birth
                $dob = '';
                if ($aRow['child_dob'] != null && trim($aRow['child_dob']) != '' && $aRow['child_dob'] != '0000-00-00') {
                    $dob =  date("d-m-Y", strtotime($aRow['child_dob']));
                }
                //set gender
                $gender = '';
                if ($aRow['child_gender'] == 'male') {
                    $gender = 'M';
                } else if ($aRow['child_gender'] == 'female') {
                    $gender = 'F';
                } else if ($aRow['child_gender'] == 'not_recorded') {
                    $gender = 'Unreported';
                }
                //sample collecion date
                $sampleCollectionDate = '';
                if ($aRow['sample_collection_date'] != null && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
                    $expStr = explode(" ", $aRow['sample_collection_date']);
                    $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
                }

                $sampleTestedOn = '';
                if ($aRow['sample_tested_datetime'] != null && trim($aRow['sample_tested_datetime']) != '' && $aRow['sample_tested_datetime'] != '0000-00-00') {
                    $sampleTestedOn =  date("d-m-Y", strtotime($aRow['sample_tested_datetime']));
                }

                if ($aRow['sample_received_at_vl_lab_datetime'] != null && trim($aRow['sample_received_at_vl_lab_datetime']) != '' && $aRow['sample_received_at_vl_lab_datetime'] != '0000-00-00') {
                    $sampleReceivedOn =  date("d-m-Y", strtotime($aRow['sample_received_at_vl_lab_datetime']));
                }


                //set sample rejection
                $sampleRejection = 'No';
                if (trim($aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim($aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
                    $sampleRejection = 'Yes';
                }
                //result dispatched date
                $resultDispatchedDate = '';
                if ($aRow['result_printed_datetime'] != null && trim($aRow['result_printed_datetime']) != '' && $aRow['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
                    $expStr = explode(" ", $aRow['result_printed_datetime']);
                    $resultDispatchedDate =  date("d-m-Y", strtotime($expStr[0]));
                }

                //set result log value
                $logVal = '0.0';
                if ($aRow['result_value_log'] != null && trim($aRow['result_value_log']) != '') {
                    $logVal = round($aRow['result_value_log'], 1);
                } else if ($aRow['result_value_absolute'] != null && trim($aRow['result_value_absolute']) != '' && $aRow['result_value_absolute'] > 0) {
                    $logVal = round(log10((float)$aRow['result_value_absolute']), 1);
                }
                if ($_SESSION['instanceType'] == 'remoteuser') {
                    $sampleCode = 'remote_sample_code';
                } else {
                    $sampleCode = 'sample_code';
                }

                if ($aRow['patient_first_name'] != '') {
                    $patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_art_no']));
                } else {
                    $patientFname = '';
                }
                if ($aRow['patient_middle_name'] != '') {
                    $patientMname = ($general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['patient_art_no']));
                } else {
                    $patientMname = '';
                }
                if ($aRow['patient_last_name'] != '') {
                    $patientLname = ($general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_art_no']));
                } else {
                    $patientLname = '';
                }

                $row[] = $no;
                $row[] = $aRow[$sampleCode];
                $row[] = ($aRow['facility_name']);
                $row[] = $aRow['facility_code'];
                $row[] = ($aRow['facility_district']);
                $row[] = ($aRow['facility_state']);
                $row[] = ($aRow['labName']);
                $row[] = $aRow['child_id'];
                $row[] = $aRow['child_name'];
                $row[] = $aRow['mother_id'];
                $row[] = $dob;
                $row[] = ($aRow['child_age'] != null && trim($aRow['child_age']) != '' && $aRow['child_age'] > 0) ? $aRow['child_age'] : 0;
                $row[] = $gender;
                $row[] = ($aRow['has_infant_stopped_breastfeeding']);
                $row[] = ($aRow['pcr_test_performed_before']);
                $row[] = ($aRow['previous_pcr_result']);
                $row[] = $sampleCollectionDate;
                $row[] = $sampleRejection;
                $row[] = $sampleTestedOn;
                $row[] = $eidResults[$aRow['result']];
                $row[] = $sampleReceivedOn;
                $row[] = $resultDispatchedDate;
                $row[] = ($aRow['lab_tech_comments']);
                $row[] = (isset($aRow['funding_source_name']) && trim($aRow['funding_source_name']) != '') ? ($aRow['funding_source_name']) : '';
                $row[] = (isset($aRow['i_partner_name']) && trim($aRow['i_partner_name']) != '') ? ($aRow['i_partner_name']) : '';
                $output[] = $row;
                $no++;
            }

            $start = (count($output)) + 2;
            foreach ($output as $rowNo => $rowData) {
                $colNo = 1;
                foreach ($rowData as $field => $value) {
                    $rRowCount = $rowNo + 4;
                    $cellName = Coordinate::stringFromColumnIndex($colNo);
                    $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
                    $sheet->getStyle($cellName . $start)->applyFromArray($borderStyle);
                    $sheet->setCellValue($cellName . ($rowNo + 4), html_entity_decode($value));
                    $colNo++;
                }
            }
            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'VLSM-EID-Requested-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        }
    }

    public function insertSampleCode($params)
    {
        $general = new General();

        $globalConfig = $general->getGlobalConfig();
        $vlsmSystemConfig = $general->getSystemConfig();

        try {
            $provinceCode = (isset($params['provinceCode']) && !empty($params['provinceCode'])) ? $params['provinceCode'] : null;
            $provinceId = (isset($params['provinceId']) && !empty($params['provinceId'])) ? $params['provinceId'] : null;
            $sampleCollectionDate = (isset($params['sampleCollectionDate']) && !empty($params['sampleCollectionDate'])) ? $params['sampleCollectionDate'] : null;

            if (empty($sampleCollectionDate)) {
                echo 0;
                exit();
            }
            // PNG FORM CANNOT HAVE PROVINCE EMPTY
            if ($globalConfig['vl_form'] == 5 && empty($provinceId)) {
                echo 0;
                exit();
            }

            $rowData = false;

            $oldSampleCodeKey = $params['oldSampleCodeKey'] ?: null;
            $sampleJson = $this->generateEIDSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId, $oldSampleCodeKey);
            $sampleData = json_decode($sampleJson, true);
            $sampleDate = explode(" ", $params['sampleCollectionDate']);
            $sampleCollectionDate = DateUtils::isoDateFormat($sampleDate[0]) . " " . $sampleDate[1];

            if (!isset($params['countryId']) || empty($params['countryId'])) {
                $params['countryId'] = null;
            }


            $eidData = [];
            if (isset($params['api']) && $params['api'] = "yes") {
                $eidData = array(
                    'vlsm_country_id' => $params['formId'],
                    'sample_collection_date' => $sampleCollectionDate,
                    'vlsm_instance_id' => $params['instanceId'],
                    'province_id' => $provinceId,
                    'request_created_by' => null,
                    'request_created_datetime' => $this->db->now(),
                    'last_modified_by' => null,
                    'last_modified_datetime' => $this->db->now()
                );
            } else {
                $eidData = array(
                    'vlsm_country_id' => $params['countryId'],
                    'sample_collection_date' => $sampleCollectionDate,
                    'province_id' => $provinceId,
                    'vlsm_instance_id' => $_SESSION['instanceId'],
                    'request_created_by' => $_SESSION['userId'],
                    'request_created_datetime' => $this->db->now(),
                    'last_modified_by' => $_SESSION['userId'],
                    'last_modified_datetime' => $this->db->now()
                );
            }

            $oldSampleCodeKey = null;

            if ($vlsmSystemConfig['sc_user_type'] === 'remoteuser') {
                $eidData['remote_sample_code'] = $sampleData['sampleCode'];
                $eidData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                $eidData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                $eidData['remote_sample'] = 'yes';
                $eidData['result_status'] = 9;
                if ($_SESSION['accessType'] === 'testing-lab') {
                    $eidData['sample_code'] = $sampleData['sampleCode'];
                    $eidData['result_status'] = 6;
                }
            } else {
                $eidData['sample_code'] = $sampleData['sampleCode'];
                $eidData['sample_code_format'] = $sampleData['sampleCodeFormat'];
                $eidData['sample_code_key'] = $sampleData['sampleCodeKey'];
                $eidData['remote_sample'] = 'no';
                $eidData['result_status'] = 6;
            }

            $sQuery = "SELECT eid_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_eid ";
            if (isset($sampleData['sampleCode']) && !empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";

            $rowData = $this->db->rawQueryOne($sQuery);

            /* Update version in form attributes */
            $version = $general->getSystemConfig('sc_version');
            if (isset($version) && !empty($version)) {
                $ipaddress = '';
                if (isset($_SERVER['HTTP_CLIENT_IP'])) {
                    $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
                } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
                } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
                    $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
                } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
                    $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
                } else if (isset($_SERVER['HTTP_FORWARDED'])) {
                    $ipaddress = $_SERVER['HTTP_FORWARDED'];
                } else $ipaddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
                $formAttributes = array(
                    'applicationVersion'  => $version,
                    'ip_address'    => $ipaddress
                );
                $eidData['form_attributes'] = json_encode($formAttributes);
            }
            $id = 0;

            if ($rowData) {
                // $this->db = $this->db->where('eid_id', $rowData['eid_id']);
                // $id = $this->db->update("form_eid", $eidData);
                // $params['eidSampleId'] = $rowData['eid_id'];

                // If this sample code exists, let us regenerate
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSampleCode($params);
            } else {

                if (isset($params['api']) && $params['api'] = "yes") {
                    $id = $this->db->insert("form_eid", $eidData);
                    error_log($this->db->getLastError());
                    $params['eidSampleId'] = $id;
                } else {
                    if (isset($params['sampleCode']) && $params['sampleCode'] != '' && $params['sampleCollectionDate'] != null && $params['sampleCollectionDate'] != '') {
                        $eidData['unique_id'] = $general->generateUUID();
                        $id = $this->db->insert("form_eid", $eidData);
                        error_log($this->db->getLastError());
                    }
                }
            }
            if ($id > 0) {
                return $id;
            } else {
                return 0;
            }
        } catch (Exception $e) {
            error_log('Insert EID Sample : ' . $this->db->getLastErrno());
            error_log('Insert EID Sample : ' . $this->db->getLastError());
            error_log('Insert EID Sample : ' . $this->db->getLastQuery());
            error_log('Insert EID Sample : ' . $e->getMessage());
        }
    }
}
