<?php

require_once(__DIR__ . "/../startup.php");




$general = new \Vlsm\Models\General();

$lastUpdate = null;

try {
    $instanceQuery = "SELECT * FROM s_vlsm_instance";
    $instanceResult = $db->query($instanceQuery);
    if ($instanceResult) {
        $vlsmInstanceId = $instanceResult[0]['vlsm_instance_id'];
        if ($instanceResult[0]['eid_last_dash_sync'] == '' || $instanceResult[0]['eid_last_dash_sync'] == null) {
            $instanceUpdateOn = "";
        } else {
            $expDate = explode(" ", $instanceResult[0]['eid_last_dash_sync']);
            $instanceUpdateOn = $expDate[0];
        }


        $sQuery = "SELECT vl.*,
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
                    l_f.status as labFacilityStatus,
                    rsrr.rejection_reason_status 
                        FROM eid_form as vl 
                        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
                        LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id 
                        INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
                        LEFT JOIN facility_type as ft ON ft.facility_type_id=f.facility_type 
                        LEFT JOIN facility_type as lft ON lft.facility_type_id=l_f.facility_type 
                        LEFT JOIN r_eid_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection";

        if ($instanceUpdateOn != "") {
            $sQuery .= " WHERE DATE(vl.last_modified_datetime) > $instanceUpdateOn";
        }

        $sQuery .= " ORDER BY vl.last_modified_datetime ASC ";

        $sQuery .= " LIMIT 2500";

        $rResult = $db->rawQuery($sQuery);

        $lastUpdate = $rResult[count($rResult) - 1]['last_modified_datetime'];


        $output = array();
        foreach ($rResult as $key => $aRow) {
            if (!empty($aRow['remote_sample_code'])) {
                $aRow['sample_code'] = $aRow['remote_sample_code'] . '-' . $aRow['sample_code'];
            }
            $output[] = $aRow;
        }

        $currentDate = $general->getDateTime();
        $payload = array(
            'data' => $output,
            'datetime' => $currentDate
        );

        $filename = 'export-eid-result-' . $currentDate . '.json';
        $fp = fopen(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'w');
        fwrite($fp, json_encode($payload));
        fclose($fp);


        //global config
        $configQuery = "SELECT `value` FROM global_config WHERE name ='vldashboard_url'";
        $configResult = $db->query($configQuery);
        $vldashboardUrl = trim($configResult[0]['value']);
        $vldashboardUrl = rtrim($vldashboardUrl, "/");


        //$vldashboardUrl = "http://vldashboard";

        $apiUrl = $vldashboardUrl . "/api/vlsm-eid";
        //error_log($apiUrl);
        //$apiUrl.="?key_identity=XXX&key_credential=YYY";


        $data = [];
        $data['eidFile'] = new CURLFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename, 'application/json', $filename);

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

        $deResult = json_decode($result, true);

        if (isset($deResult['status']) && trim($deResult['status']) == 'success') {
            $data = array(
                'eid_last_dash_sync' => (!empty($lastUpdate) ? $lastUpdate : $general->getDateTime())
            );
            $db = $db->where('vlsm_instance_id', $vlsmInstanceId);
            $db->update('s_vlsm_instance', $data);
        }
        $general->removeDirectory(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
