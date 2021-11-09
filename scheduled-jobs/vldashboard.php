<?php

// ini_set('memory_limit', -1);

require_once(__DIR__ . "/../startup.php");




$general = new \Vlsm\Models\General();
$lastUpdate = null;
try {
    $instanceQuery = "SELECT * FROM s_vlsm_instance";
    $instanceResult = $db->query($instanceQuery);
    if ($instanceResult) {
        $vlsmInstanceId = $instanceResult[0]['vlsm_instance_id'];
        if ($instanceResult[0]['vl_last_dash_sync'] == '' || $instanceResult[0]['vl_last_dash_sync'] == null) {
            $instanceUpdateOn = "";
        } else {
            $expDate = explode(" ", $instanceResult[0]['vl_last_dash_sync']);
            $instanceUpdateOn = $expDate[0];
        }


        $sQuery = "SELECT vl.*,s.sample_name,s.status as sample_type_status,
                    ts.*,f.facility_name,l_f.facility_name as labName,
                    f.facility_code,f.facility_state,f.facility_district,
                    f.facility_mobile_numbers,f.address,f.facility_hub_name,
                    f.contact_person,f.report_email,f.country,f.longitude,
                    f.latitude,f.facility_type,f.status as facility_status,
                    ft.facility_type_name,lft.facility_type_name as labFacilityTypeName,
                    l_f.facility_name as labName,l_f.facility_code as labCode,
                    l_f.facility_state as labState,l_f.facility_district as labDistrict,
                    l_f.facility_mobile_numbers as labPhone,l_f.address as labAddress,
                    l_f.facility_hub_name as labHub,l_f.contact_person as labContactPerson,
                    l_f.report_email as labReportMail,l_f.country as labCountry,
                    l_f.longitude as labLongitude,l_f.latitude as labLatitude,
                    l_f.facility_type as labFacilityType,
                    l_f.status as labFacilityStatus,tr.test_reason_name,
                    tr.test_reason_status,rsrr.rejection_reason_name,
                    rsrr.rejection_reason_status 
                        FROM vl_request_form as vl 
                        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
                        LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id 
                        LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type 
                        INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
                        LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_vl_testing 
                        LEFT JOIN facility_type as ft ON ft.facility_type_id=f.facility_type 
                        LEFT JOIN facility_type as lft ON lft.facility_type_id=l_f.facility_type 
                        LEFT JOIN r_vl_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection
                        
                        WHERE (sample_code is not null AND sample_code !='')
                        
                        ";

        if ($instanceUpdateOn != "") {
            $sQuery .= " AND DATE(vl.last_modified_datetime) > '$instanceUpdateOn'";
        }

        $sQuery .= " ORDER BY vl.last_modified_datetime ASC ";

        //echo $instanceUpdateOn;

        

        $sQuery .= " LIMIT 1000 ";

        //echo $sQuery;

        $rResult = $db->rawQuery($sQuery);

        $lastUpdate = $rResult[count($rResult)-1]['last_modified_datetime'];

        

        $output = array();

        foreach ($rResult as $aRow) {
            $row = array();

            $VLAnalysisResult = $aRow['result_value_absolute'];
            if ($aRow['result_value_text'] == 'Target not Detected' || $aRow['result_value_text'] == 'Target Not Detected' || strtolower($aRow['result_value_text']) == 'tnd') {
                $VLAnalysisResult = 20;
            } else if ($aRow['result_value_text'] == '< 20' || $aRow['result_value_text'] == '<20') {
                $VLAnalysisResult = 20;
            } else if ($aRow['result_value_text'] == '< 40' || $aRow['result_value_text'] == '<40') {
                $VLAnalysisResult = 40;
            } else if ($aRow['result_value_text'] == 'Nivel de detecÁao baixo' || $aRow['result_value_text'] == 'NÌvel de detecÁ„o baixo') {
                $VLAnalysisResult = 20;
            } else if ($aRow['result_value_text'] == 'Suppressed') {
                $VLAnalysisResult = 500;
            } else if ($aRow['result_value_text'] == 'Not Suppressed') {
                $VLAnalysisResult = 1500;
            } else if ($aRow['result_value_text'] == 'Negative' || $aRow['result_value_text'] == 'NEGAT') {
                $VLAnalysisResult = 20;
            } else if ($aRow['result_value_text'] == 'Positive') {
                $VLAnalysisResult = 1500;
            } else if ($aRow['result_value_text'] == 'Indeterminado') {
                $VLAnalysisResult = "";
            }

            if ($VLAnalysisResult == null || $VLAnalysisResult == 'NULL' || $VLAnalysisResult == '') {
                $DashVL_Abs = 0;
                $DashVL_AnalysisResult = '';
            } else if ($VLAnalysisResult < 1000) {
                $DashVL_AnalysisResult = 'Suppressed';
                $DashVL_Abs = $VLAnalysisResult;
            } else if ($VLAnalysisResult >= 1000) {
                $DashVL_AnalysisResult = 'Not Suppressed';
                $DashVL_Abs = $VLAnalysisResult;
            }
            if (!empty($aRow['remote_sample_code'])) {
                $row['sample_code']      = $aRow['remote_sample_code'];
                if (!empty($aRow['sample_code'])) {
                    $row['sample_code']      .= '-' . $aRow['sample_code'];
                }
            } else {
                $row['sample_code']      = $aRow['sample_code'];
            }
            $row['vlsm_instance_id']     = $aRow['vlsm_instance_id'];
            $row['patient_gender']       = $aRow['patient_gender'];
            $row['patient_age_in_years'] = $aRow['patient_age_in_years'];
            $row['facility_name']        = ($aRow['facility_name']);
            $row['facility_code']        = ($aRow['facility_code']);
            $row['facility_state']       = ($aRow['facility_state']);
            $row['facility_district']    = ($aRow['facility_district']);
            $row['facility_mobile_numbers'] = ($aRow['facility_mobile_numbers']);
            $row['address']              = ($aRow['address']);
            $row['facility_hub_name']    = ($aRow['facility_hub_name']);
            $row['contact_person']       = ($aRow['contact_person']);
            $row['report_email']         = ($aRow['report_email']);
            $row['country']              = ($aRow['country']);
            $row['longitude']            = ($aRow['longitude']);
            $row['latitude']             = ($aRow['latitude']);
            $row['facility_status']      = ($aRow['facility_status']);
            $row['facility_type_name']   = ($aRow['facility_type_name']);
            $row['sample_name']          = $aRow['sample_name'];
            $row['sample_type_status']   = $aRow['sample_type_status'];
            $row['sample_collection_date']                  = $aRow['sample_collection_date'];
            $row['labName'] = ($aRow['labName']);
            $row['labCode'] = ($aRow['labCode']);
            $row['labState'] = ($aRow['labState']);
            $row['labDistrict'] = ($aRow['labDistrict']);
            $row['labPhone'] = $aRow['labPhone'];
            $row['labAddress'] = $aRow['labAddress'];
            $row['labHub'] = $aRow['labHub'];
            $row['labContactPerson'] = ($aRow['labContactPerson']);
            $row['labReportMail'] = ($aRow['labReportMail']);
            $row['labCountry'] = ($aRow['labCountry']);
            $row['labLongitude'] = ($aRow['labLongitude']);
            $row['labLatitude'] = ($aRow['labLatitude']);
            $row['labFacilityStatus'] = ($aRow['labFacilityStatus']);
            $row['labFacilityTypeName'] = ($aRow['labFacilityTypeName']);
            $row['sample_tested_datetime'] = $aRow['sample_tested_datetime'];
            $row['result_value_log'] = $aRow['result_value_log'];
            $row['result_value_absolute'] = $aRow['result_value_absolute'];
            $row['result_value_text'] = $aRow['result_value_text'];
            $row['result_value_absolute_decimal'] = $aRow['result_value_absolute_decimal'];
            $row['result'] = $aRow['result'];
            $row['test_reason_name'] = ($aRow['test_reason_name']);
            $row['test_reason_status'] = ($aRow['test_reason_status']);
            $row['status_name'] = ($aRow['status_name']);
            $row['sample_received_at_vl_lab_datetime']  = $aRow['sample_received_at_vl_lab_datetime'];
            $row['line_of_treatment'] = $aRow['line_of_treatment'];
            $row['is_sample_rejected'] = $aRow['is_sample_rejected'];
            $row['rejection_reason_name'] = $aRow['rejection_reason_name'];
            $row['rejection_reason_status'] = $aRow['rejection_reason_status'];
            $row['is_patient_pregnant'] = (isset($aRow['is_patient_pregnant']) && $aRow['is_patient_pregnant'] != null && $aRow['is_patient_pregnant'] != '') ? $aRow['is_patient_pregnant'] : 'unreported';
            $row['is_patient_breastfeeding'] = (isset($aRow['is_patient_breastfeeding']) && $aRow['is_patient_breastfeeding'] != null && $aRow['is_patient_breastfeeding'] != '') ? $aRow['is_patient_breastfeeding'] : 'unreported';
            $row['patient_art_no'] = $aRow['patient_art_no'];
            $row['date_of_initiation_of_current_regimen'] = $aRow['date_of_initiation_of_current_regimen'];
            $row['arv_adherance_percentage'] = $aRow['arv_adherance_percentage'];
            $row['is_adherance_poor'] = $aRow['is_adherance_poor'];
            $row['result_approved_datetime'] = $aRow['result_approved_datetime'];
            $row['DashVL_Abs'] = $DashVL_Abs;
            $row['DashVL_AnalysisResult'] = $DashVL_AnalysisResult;
            $row['current_regimen'] = $aRow['current_regimen'];
            $row['sample_registered_at_lab'] = $aRow['sample_registered_at_lab'];
            $output[] = $row;
        }

        $currentDate = $general->getDateTime();
        $payload = array(
            'data' => $output,
            'datetime' => $currentDate
        );


        $filename = 'export-vl-result-' . $currentDate . '.json';
        $fp = fopen(__DIR__ . "/../temporary" . DIRECTORY_SEPARATOR . $filename, 'w');
        fwrite($fp, json_encode($payload));
        fclose($fp);


        //global config
        $configQuery = "SELECT `value` FROM global_config WHERE name ='vldashboard_url'";
        $configResult = $db->query($configQuery);
        $vldashboardUrl = trim($configResult[0]['value']);
        $vldashboardUrl = rtrim($vldashboardUrl, "/");


        //$vldashboardUrl = "http://vldashboard";

        $apiUrl = $vldashboardUrl . "/api/vlsm";
        //error_log($apiUrl);
        //$apiUrl.="?key_identity=XXX&key_credential=YYY";


        $data = [];
        $data['api-version'] = 'v1';
        $data['vlFile'] = new CURLFile(__DIR__ . "/../temporary" . DIRECTORY_SEPARATOR . $filename, 'application/json', $filename);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => ['Content-Type: multipart/form-data']
        ];

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        curl_close($ch);

        //var_dump($result);
        $deResult = json_decode($result, true);

        if (isset($deResult['status']) && trim($deResult['status']) == 'success') {
            $data = array(
                'vl_last_dash_sync' => (!empty($lastUpdate) ? $lastUpdate : $general->getDateTime())
            );
            // $data = array(
            //     'vl_last_dash_sync' => $rResult[count($rResult) -1]['last_modified_datetime']
            // );
            $db = $db->where('vlsm_instance_id', $vlsmInstanceId);
            $db->update('s_vlsm_instance', $data);
            /* echo "<pre>";
            print_r($deResult); */
        }
        $general->removeDirectory(__DIR__ . "/../temporary" . DIRECTORY_SEPARATOR . $filename);
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}