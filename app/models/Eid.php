<?php

namespace Vlsm\Models;

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
        $this->db = !empty($db) ? $db : \MysqliDb::getInstance();
    }

    public function generateEIDSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null)
    {

        $general = new \Vlsm\Models\General($this->db);
        $globalConfig = $general->getGlobalConfig();
        $vlsmSystemConfig = $general->getSystemConfig();
        $sampleID = '';


        $remotePrefix = '';
        $sampleCodeKeyCol = 'sample_code_key';
        $sampleCodeCol = 'sample_code';
        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $remotePrefix = 'R';
            $sampleCodeKeyCol = 'remote_sample_code_key';
            $sampleCodeCol = 'remote_sample_code';
        }
        if (isset($user['access_type']) && !empty($user['access_type']) && $user['access_type'] != 'testing-lab') {
            $remotePrefix = 'R';
            $sampleCodeKeyCol = 'remote_sample_code_key';
            $sampleCodeCol = 'remote_sample_code';
        }
        $sampleColDateTimeArray = explode(" ", $sampleCollectionDate);
        $sampleCollectionDate = $general->dateFormat($sampleColDateTimeArray[0]);
        $sampleColDateArray = explode("-", $sampleCollectionDate);
        $samColDate = substr($sampleColDateArray[0], -2);
        $start_date = $sampleColDateArray[0] . '-01-01';
        $end_date = $sampleColDateArray[0] . '-12-31';
        $mnthYr = $samColDate[0];
        // Checking if sample code format is empty then we set by default 'MMYY'
        $sampleCodeFormat = isset($globalConfig['eid_sample_code']) ? $globalConfig['eid_sample_code'] : 'MMYY';
        $prefixFromConfig = isset($globalConfig['eid_sample_code_prefix']) ? $globalConfig['eid_sample_code_prefix'] : '';

        if ($sampleCodeFormat == 'MMYY') {
            $mnthYr = $sampleColDateArray[1] . $samColDate;
        } else if ($sampleCodeFormat == 'YY') {
            $mnthYr = $samColDate;
        }

        $autoFormatedString = $samColDate . $sampleColDateArray[1] . $sampleColDateArray[2];


        if ($maxCodeKeyVal == null) {
            // If it is PNG form
            if ($globalConfig['vl_form'] == 5) {

                if (empty($provinceId) && !empty($provinceCode)) {
                    $provinceId = $general->getProvinceIDFromCode($provinceCode);
                }

                if (!empty($provinceId)) {
                    $this->db->where('province_id', $provinceId);
                }
            }

            $this->db->where('DATE(sample_collection_date)', array($start_date, $end_date), 'BETWEEN');
            $this->db->where($sampleCodeCol, NULL, 'IS NOT');
            $this->db->orderBy($sampleCodeKeyCol, "DESC");
            $svlResult = $this->db->getOne($this->table, array($sampleCodeKeyCol));
            if ($svlResult) {
                $maxCodeKeyVal = $svlResult[$sampleCodeKeyCol];
            } else {
                $maxCodeKeyVal = null;
            }
        }


        if (!empty($maxCodeKeyVal)) {
            $maxId = $maxCodeKeyVal + 1;
            $strparam = strlen($maxId);
            $zeros = (isset($sampleCodeFormat) && trim($sampleCodeFormat) == 'auto2') ? substr("0000", $strparam) : substr("000", $strparam);
            $maxId = $zeros . $maxId;
        } else {
            $maxId = (isset($sampleCodeFormat) && trim($sampleCodeFormat) == 'auto2') ? '0001' : '001';
        }

        //error_log($maxCodeKeyVal);

        $sCodeKey = (array('maxId' => $maxId, 'mnthYr' => $mnthYr, 'auto' => $autoFormatedString));



        if ($globalConfig['vl_form'] == 5) {
            // PNG format has an additional R in prefix
            $remotePrefix = $remotePrefix . "R";
            //$sampleCodeFormat = 'auto2';
        }


        if ($sampleCodeFormat == 'auto') {
            //$pNameVal = explode("##", $provinceCode);
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

        $checkQuery = "SELECT $sampleCodeCol, $sampleCodeKeyCol FROM " . $this->table . " where $sampleCodeCol='" . $sCodeKey['sampleCode'] . "'";
        $checkResult = $this->db->rawQueryOne($checkQuery);
        if ($checkResult !== null) {
            return $this->generateEIDSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, null, $user);
        }
        return json_encode($sCodeKey);
    }


    public function getEidResults()
    {
        $results = $this->db->rawQuery("SELECT * FROM r_eid_results where status='active' ORDER BY result_id DESC");
        $response = array();
        foreach ($results as $row) {
            $response[$row['result_id']] = $row['result'];
        }
        return $response;
    }

    public function getEidSampleTypes()
    {
        $results = $this->db->rawQuery("SELECT * FROM r_eid_sample_type where status='active'");
        $response = array();
        foreach ($results as $row) {
            $response[$row['sample_id']] = $row['sample_name'];
        }
        return $response;
    }

    public function generateExcelExport($params)
    {
        $general = new \Vlsm\Models\General();
        $eidResults = $general->getEidResults();

        //system config
        $systemConfigQuery = "SELECT * from system_config";
        $systemConfigResult = $this->db->query($systemConfigQuery);
        $sarr = array();
        // now we create an associative array so that we can easily create view variables
        for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
            $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
        }
        if (isset($_SESSION['eidRequestSearchResultQuery']) && trim($_SESSION['eidRequestSearchResultQuery']) != "") {

            $rResult = $this->db->rawQuery($_SESSION['eidRequestSearchResultQuery']);

            $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $output = array();
            $sheet = $excel->getActiveSheet();

            $headings = array("S.No.", "Sample Code", "Health Facility Name", "Health Facility Code", "District/County", "Province/State", "Testing Lab Name (Hub)", "Child ID", "Child Name", "Mother ID", "Child Date of Birth", "Child Age", "Child Gender", "Breastfeeding status", "PCR Test Performed Before", "Last PCR Test results", "Sample Collection Date", "Is Sample Rejected?", "Sample Tested On", "Result", "Sample Received On", "Date Result Dispatched", "Comments", "Funding Source", "Implementing Partner");
            $colNo = 1;

            $styleArray = array(
                'font' => array(
                    'bold' => true,
                    'size' => 12,
                ),
                'alignment' => array(
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ),
                'borders' => array(
                    'outline' => array(
                        'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ),
                )
            );

            $borderStyle = array(
                'alignment' => array(
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ),
                'borders' => array(
                    'outline' => array(
                        'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
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
            $sheet->getCellByColumnAndRow($colNo, 1)->setValueExplicit(html_entity_decode($nameValue), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            if ($params['withAlphaNum'] == 'yes') {
                foreach ($headings as $field => $value) {
                    $string = str_replace(' ', '', $value);
                    $value = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
                    $sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $colNo++;
                }
            } else {
                foreach ($headings as $field => $value) {
                    $sheet->getCellByColumnAndRow($colNo, 3)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $colNo++;
                }
            }
            $sheet->getStyle('A3:AG3')->applyFromArray($styleArray);

            $no = 1;
            foreach ($rResult as $aRow) {
                $row = array();
                //date of birth
                $dob = '';
                if ($aRow['child_dob'] != NULL && trim($aRow['child_dob']) != '' && $aRow['child_dob'] != '0000-00-00') {
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
                if ($aRow['sample_collection_date'] != NULL && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
                    $expStr = explode(" ", $aRow['sample_collection_date']);
                    $sampleCollectionDate =  date("d-m-Y", strtotime($expStr[0]));
                }

                $sampleTestedOn = '';
                if ($aRow['sample_tested_datetime'] != NULL && trim($aRow['sample_tested_datetime']) != '' && $aRow['sample_tested_datetime'] != '0000-00-00') {
                    $sampleTestedOn =  date("d-m-Y", strtotime($aRow['sample_tested_datetime']));
                }

                if ($aRow['sample_received_at_vl_lab_datetime'] != NULL && trim($aRow['sample_received_at_vl_lab_datetime']) != '' && $aRow['sample_received_at_vl_lab_datetime'] != '0000-00-00') {
                    $sampleReceivedOn =  date("d-m-Y", strtotime($aRow['sample_received_at_vl_lab_datetime']));
                }


                //set sample rejection
                $sampleRejection = 'No';
                if (trim($aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != NULL && trim($aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
                    $sampleRejection = 'Yes';
                }
                //result dispatched date
                $resultDispatchedDate = '';
                if ($aRow['result_printed_datetime'] != NULL && trim($aRow['result_printed_datetime']) != '' && $aRow['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
                    $expStr = explode(" ", $aRow['result_printed_datetime']);
                    $resultDispatchedDate =  date("d-m-Y", strtotime($expStr[0]));
                }

                //set result log value
                $logVal = '0.0';
                if ($aRow['result_value_log'] != NULL && trim($aRow['result_value_log']) != '') {
                    $logVal = round($aRow['result_value_log'], 1);
                } else if ($aRow['result_value_absolute'] != NULL && trim($aRow['result_value_absolute']) != '' && $aRow['result_value_absolute'] > 0) {
                    $logVal = round(log10((float)$aRow['result_value_absolute']), 1);
                }
                if ($_SESSION['instanceType'] == 'remoteuser') {
                    $sampleCode = 'remote_sample_code';
                } else {
                    $sampleCode = 'sample_code';
                }

                if ($aRow['patient_first_name'] != '') {
                    $patientFname = ucwords($general->crypto('decrypt', $aRow['patient_first_name'], $aRow['patient_art_no']));
                } else {
                    $patientFname = '';
                }
                if ($aRow['patient_middle_name'] != '') {
                    $patientMname = ucwords($general->crypto('decrypt', $aRow['patient_middle_name'], $aRow['patient_art_no']));
                } else {
                    $patientMname = '';
                }
                if ($aRow['patient_last_name'] != '') {
                    $patientLname = ucwords($general->crypto('decrypt', $aRow['patient_last_name'], $aRow['patient_art_no']));
                } else {
                    $patientLname = '';
                }

                $row[] = $no;
                $row[] = $aRow[$sampleCode];
                $row[] = ucwords($aRow['facility_name']);
                $row[] = $aRow['facility_code'];
                $row[] = ucwords($aRow['facility_district']);
                $row[] = ucwords($aRow['facility_state']);
                $row[] = ucwords($aRow['labName']);
                $row[] = $aRow['child_id'];
                $row[] = $aRow['child_name'];
                $row[] = $aRow['mother_id'];
                $row[] = $dob;
                $row[] = ($aRow['child_age'] != NULL && trim($aRow['child_age']) != '' && $aRow['child_age'] > 0) ? $aRow['child_age'] : 0;
                $row[] = $gender;
                $row[] = ucwords($aRow['has_infant_stopped_breastfeeding']);
                $row[] = ucwords($aRow['pcr_test_performed_before']);
                $row[] = ucwords($aRow['previous_pcr_result']);
                $row[] = $sampleCollectionDate;
                $row[] = $sampleRejection;
                $row[] = $sampleTestedOn;
                $row[] = $eidResults[$aRow['result']];
                $row[] = $sampleReceivedOn;
                $row[] = $resultDispatchedDate;
                $row[] = ucfirst($aRow['lab_tech_comments']);
                $row[] = (isset($aRow['funding_source_name']) && trim($aRow['funding_source_name']) != '') ? ucwords($aRow['funding_source_name']) : '';
                $row[] = (isset($aRow['i_partner_name']) && trim($aRow['i_partner_name']) != '') ? ucwords($aRow['i_partner_name']) : '';
                $output[] = $row;
                $no++;
            }

            $start = (count($output)) + 2;
            foreach ($output as $rowNo => $rowData) {
                $colNo = 1;
                foreach ($rowData as $field => $value) {
                    $rRowCount = $rowNo + 4;
                    $cellName = $sheet->getCellByColumnAndRow($colNo, $rRowCount)->getColumn();
                    $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
                    $sheet->getStyle($cellName . $start)->applyFromArray($borderStyle);
                    $sheet->getDefaultRowDimension($colNo)->setRowHeight(18);
                    $sheet->getColumnDimensionByColumn($colNo)->setWidth(20);
                    $sheet->getCellByColumnAndRow($colNo, $rowNo + 4)->setValueExplicit(html_entity_decode($value), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $colNo++;
                }
            }
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'VLSM-EID-Requested-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        }
    }

    public function insertSampleCode($params)
    {
        $general = new \Vlsm\Models\General();

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
            if ($globalConfig['vl_form'] == 5) {
                if (empty($provinceId)) {
                    echo 0;
                    exit();
                }
            }

            $rowData = false;

            $sampleJson = $this->generateEIDSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId);
            $sampleData = json_decode($sampleJson, true);
            $sampleDate = explode(" ", $params['sampleCollectionDate']);
            $sampleCollectionDate = $general->dateFormat($sampleDate[0]) . " " . $sampleDate[1];

            if (!isset($params['countryId']) || empty($params['countryId'])) {
                $params['countryId'] = null;
            }


            $eidData = array();
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

            if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
                $eidData['remote_sample_code'] = $sampleData['sampleCode'];
                $eidData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                $eidData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                $eidData['remote_sample'] = 'yes';
                $eidData['result_status'] = 9;
                if ($_SESSION['accessType'] == 'testing-lab') {
                    $eidData['sample_code'] = $sampleData['sampleCode'];
                    $eidData['sample_code_format'] = $sampleData['sampleCodeFormat'];
                    $eidData['sample_code_key'] = $sampleData['sampleCodeKey'];
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
            $id = 0;
            if ($rowData) {
                // $this->db = $this->db->where('eid_id', $rowData['eid_id']);
                // $id = $this->db->update("form_eid", $eidData);
                // $params['eidSampleId'] = $rowData['eid_id'];

                // If this sample code exists, let us regenerate
                return $this->insertSampleCode($params);
            } else {

                if (isset($params['api']) && $params['api'] = "yes") {
                    $id = $this->db->insert("form_eid", $eidData);
                    $params['eidSampleId'] = $id;
                } else {
                    if (isset($params['sampleCode']) && $params['sampleCode'] != '' && $params['sampleCollectionDate'] != null && $params['sampleCollectionDate'] != '') {
                        $eidData['unique_id'] = $general->generateRandomString(32);
                        $id = $this->db->insert("form_eid", $eidData);
                    }
                }
            }
            if ($id > 0) {
                return $id;
            } else {
                return 0;
            }
        } catch (\Exception $e) {
            error_log('Insert EID Sample : ' . $this->db->getLastError());
            error_log('Insert EID Sample : ' . $e->getMessage());
        }
    }
}
