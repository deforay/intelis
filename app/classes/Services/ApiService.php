<?php

/**
 * General functions
 *
 * @author Amit
 */

namespace App\Services;

use MysqliDb;
use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;

class ApiService
{

    protected ?MysqliDb $db = null;

    public function __construct($db = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
    }

    public function generateSelectOptions($options)
    {
        $i = 0;
        $response = [];
        foreach ($options as $key => $show) {
            $response[$i] = [];
            $response[$i]['value'] = $key;
            $response[$i]['show'] = $show;
            $i++;
        }
        return $response;
    }

    public function getAppHealthFacilities($testType = null, $user = null, $onlyActive = false, $facilityType = 0, $module = false, $activeModule = null, $updatedDateTime = null)
    {
        /** @var FacilitiesService $facilitiesService */
        $facilitiesService = ContainerRegistry::get(FacilitiesService::class);

        $query = "SELECT hf.test_type,
                        f.facility_id,
                        f.facility_name,
                        f.facility_code, f.other_id,
                        f.facility_state_id,
                        f.facility_state,
                        f.facility_district_id,
                        f.facility_district,
                        f.testing_points,
                        f.facility_attributes,
                        f.status,
                        gd.geo_id as province_id,
                        gd.geo_name as province_name
                    FROM health_facilities AS hf
                    INNER JOIN facility_details as f ON hf.facility_id=f.facility_id
                    INNER JOIN geographical_divisions as gd ON gd.geo_id=f.facility_state_id";
        $where = [];
        if (!empty($user)) {
            $facilityMap = $facilitiesService->getUserFacilityMap($user);
            if (!empty($facilityMap)) {
                $where[] = " f.facility_id IN (" . $facilityMap . ")";
            }
        }

        if (!$module && $facilityType == 1) {
            if (!empty($activeModule)) {
                $where[] = " hf.test_type IN (" . $activeModule . ")";
            }
        }

        if (!empty($testType)) {
            $where[] = " hf.test_type like '$testType'";
        }

        if ($onlyActive) {
            $where[] = " f.status like 'active'";
        }

        if ($facilityType > 0) {
            $where[] = " f.facility_type = '$facilityType'";
        }
        if ($updatedDateTime) {
            $where[] = " f.updated_datetime >= '$updatedDateTime'";
        }
        $whereStr = "";
        if (!empty($where)) {
            $whereStr = " WHERE " . implode(" AND ", $where);
        }
        $query .= $whereStr . ' GROUP BY facility_name ORDER BY facility_name ASC ';
        $result = $this->db->rawQuery($query);
        foreach ($result as $key => $row) {
            // $condition1 = " province_name like '" . $row['province_name'] . "%'";
            // $condition2 = " (facility_state like '" . $row['province_name'] . "%' OR facility_district_id like )";
            if ($module) {
                $response[$key]['value'] = $row['facility_id'];
                $response[$key]['show'] = $row['facility_name'] . ' (' . $row['facility_code'] . ')';
            } else {
                $response[$key]['facility_id'] = $row['facility_id'];
                $response[$key]['facility_name'] = $row['facility_name'];
                $response[$key]['facility_code'] = $row['facility_code'];
                $response[$key]['other_id'] = $row['other_id'];
                $response[$key]['facility_state_id'] = $row['facility_state_id'];
                $response[$key]['facility_state'] = $row['facility_state'];
                $response[$key]['facility_district_id'] = $row['facility_district_id'];
                $response[$key]['facility_district'] = $row['facility_district'];
                $response[$key]['facility_attributes'] = $row['facility_attributes'];
                $response[$key]['testing_points'] = $row['testing_points'];
                $response[$key]['status'] = $row['status'];
            }
            if (!$module && $facilityType == 1) {
                $response[$key]['test_type'] = $row['test_type'];
            }
            // $response[$key]['provinceDetails'] = $this->getSubFields('province_details', 'province_id', 'province_name', $condition1);
            // $response[$key]['districtDetails'] = $this->getSubFields('facility_details', 'facility_district', 'facility_district', $condition2);
        }
        return $response;
    }

    public function getTestingLabs($testType = null, $user = null, $onlyActive = false, $module = false, $activeModule = null, $updatedDateTime = null)
    {
        /** @var FacilitiesService $facilitiesService */
        $facilitiesService = ContainerRegistry::get(FacilitiesService::class);

        $query = "SELECT tl.test_type, f.facility_id, f.facility_name, f.facility_code, f.other_id, f.facility_state_id, f.facility_state, f.facility_district_id, f.facility_district, f.testing_points, f.status, gd.geo_id, gd.geo_name
                    from testing_labs AS tl
                    INNER JOIN facility_details as f ON tl.facility_id=f.facility_id
                    LEFT JOIN geographical_divisions as gd ON gd.geo_id=f.facility_state_id";
        $where = [];
        if (!empty($user)) {
            $facilityMap = $facilitiesService->getUserFacilityMap($user);
            if (!empty($facilityMap)) {
                $where[] = " f.facility_id IN (" . $facilityMap . ")";
            }
        }

        if (!$module) {
            if (!empty($activeModule)) {
                $where[] = " tl.test_type IN (" . $activeModule . ")";
            }
        }

        if (!empty($testType)) {
            $where[] = " tl.test_type like '$testType'";
        }

        if ($onlyActive) {
            $where[] = " f.status like 'active'";
        }

        if ($updatedDateTime) {
            $where[] = " f.updated_datetime >= '$updatedDateTime'";
        }
        $whereStr = "";
        if (!empty($where)) {
            $whereStr = " WHERE " . implode(" AND ", $where);
        }
        $query .= $whereStr . ' GROUP BY facility_name ORDER BY facility_name ASC';
        // die($query);
        $result = $this->db->rawQuery($query);
        $response = [];
        foreach ($result as $key => $row) {
            $response[$key] = [];
            $response[$key]['value'] = $row['facility_id'];
            $response[$key]['show'] = $row['facility_name'] . ' (' . $row['facility_code'] . ')';
            $response[$key]['state'] = $row['facility_state'];
            $response[$key]['district'] = $row['facility_district'];
            if (!$module) {
                $response[$key]['test_type'] = $row['test_type'];
                $response[$key]['monthly_target'] = $row['monthly_target'] ?? 0;
                $response[$key]['suppressed_monthly_target'] = $row['suppressed_monthly_target'] ?? 0;
            }
        }
        return $response;
    }

    public function getProvinceDetails($user = null, $onlyActive = false, $updatedDateTime = null)
    {
        /** @var FacilitiesService $facilitiesService */
        $facilitiesService = ContainerRegistry::get(FacilitiesService::class);

        $query = "SELECT f.facility_id, f.facility_name, f.facility_code, gd.geo_id, gd.geo_name, f.facility_district, f.facility_type
                    from geographical_divisions AS gd
                    LEFT JOIN facility_details as f ON gd.geo_id=f.facility_state_id";
        $where = [];
        if (!empty($user)) {
            $facilityMap = $facilitiesService->getUserFacilityMap($user);
            if (!empty($facilityMap)) {
                $where[] = " f.facility_id IN (" . $facilityMap . ")";
            }
        }

        if ($onlyActive) {
            $where[] = " f.status like 'active'";
        }

        if ($updatedDateTime) {
            $where[] = " gd.updated_datetime >= '$updatedDateTime'";
        }
        $whereStr = "";
        if (!empty($where)) {
            $whereStr = " WHERE " . implode(" AND ", $where);
        }
        $query .= $whereStr . ' GROUP BY geo_name ORDER BY geo_name ASC';
        $result = $this->db->rawQuery($query);
        foreach ($result as $key => $row) {
            $condition1 = " facility_state like '" . $row['geo_name'] . "%'";

            $response[$key]['value'] = $row['geo_id'];
            $response[$key]['show'] = $row['geo_name'];
            // $response[$key]['district'] = $row['facility_district'];
            $response[$key]['districtDetails'] = $this->getSubFields('facility_details', 'facility_district', 'facility_district', $condition1);
        }
        return $response;
    }

    public function getDistrictDetails($user = null, $onlyActive = false, $updatedDateTime = null)
    {
        /** @var FacilitiesService $facilitiesService */
        $facilitiesService = ContainerRegistry::get(FacilitiesService::class);

        $query = "SELECT f.facility_id, f.facility_name, f.facility_code, gd.geo_id, gd.geo_name, f.facility_district
                    from geographical_divisions AS gd
                    LEFT JOIN facility_details as f ON gd.geo_id=f.facility_state_id";
        $where = [];
        if (!empty($user)) {
            $facilityMap = $facilitiesService->getUserFacilityMap($user);
            if (!empty($facilityMap)) {
                $where[] = " f.facility_id IN (" . $facilityMap . ")";
            }
        }

        if ($onlyActive) {
            $where[] = " f.status like 'active'";
        }

        if ($updatedDateTime) {
            $where[] = " gd.updated_datetime >= '$updatedDateTime'";
        }
        $whereStr = "";
        if (!empty($where)) {
            $whereStr = " WHERE " . implode(" AND ", $where);
        }
        $query .= $whereStr . ' GROUP BY facility_district ORDER BY facility_district ASC';
        // die($query);
        $result = $this->db->rawQuery($query);
        $response = [];
        foreach ($result as $key => $row) {
            $condition1 = " facility_district like '" . $row['facility_district'] . "%'";
            $condition2 = " geo_name like '" . $row['geo_name'] . "%'";

            $response[$key]['value'] = $row['facility_district'];
            $response[$key]['show'] = $row['facility_district'];
            $response[$key]['facilityDetails'] = $this->getSubFields('facility_details', 'facility_id', 'facility_name', $condition1);
            $response[$key]['provinceDetails'] = $this->getSubFields('geographical_divisions', 'geo_id', 'geo_name', $condition2);
            /* $response[$key]['facilityId']   = $row['facility_id'];
            $response[$key]['facilityName'] = $row['facility_name'].' ('.$row['facility_code'].')';
            $response[$key]['provinceId']   = $row['province_id'];
            $response[$key]['province']     = $row['province_name']; */
        }
        return $response;
    }

    public function getSubFields($tableName, $primary, $name, $condition)
    {
        $query = "SELECT $primary, $name from $tableName where $condition group by $name";
        $result = $this->db->rawQuery($query);
        $response = [];
        foreach ($result as $key => $row) {
            $response[$key]['value'] = $row[$primary];
            $response[$key]['show'] = $row[$name];
        }
        return $response;
    }

    public function addApiTracking($user, $records, $type, $testType, $url = null, $params = null, $format = null)
    {
        $data = array(
            'requested_by' => $user ?: 'vlsm-system',
            'requested_on' => DateUtility::getCurrentDateTime(),
            'number_of_records' => $records ?: 0,
            'request_type' => $type ?: null,
            'test_type' => $testType ?: null,
            'api_url' => $url ?: null,
            'api_params' => $params ?: null,
            'data_format' => $format ?: null
        );
        if ($format == 'sync-api') {
            $data['facility_id'] = (isset($params['data'][0]['facilityId']) && !empty($params['data'][0]['facilityId'])) ? $params['data'][0]['facilityId'] : null;
        }
        return $this->db->insert("track_api_requests", $data);
    }

    public function getTableDataUsingId($tablename, $fieldName, $value)
    {
        return $this->db->rawQueryOne("SELECT * FROM " . $tablename . " WHERE " . $fieldName . " = " . $value);
    }

    public function getLastRequestForPatientID($testType, $patientId)
    {

        $sQuery = "";
        if ($testType == 'vl') {
            $sQuery = "SELECT DATE_FORMAT(DATE(request_created_datetime),'%d-%b-%Y') as request_created_datetime,DATE_FORMAT(DATE(sample_collection_date),'%d-%b-%Y') as sample_collection_date, (SELECT count(*) FROM `form_vl` WHERE `patient_art_no`='" . $patientId . "') as no_of_req_time,
                        (SELECT count(*) FROM `form_vl` WHERE `patient_art_no`='" . $patientId . "' AND sample_tested_datetime IS NOT NULL AND sample_tested_datetime!='0000-00-00 00:00:00') as no_of_tested_time from form_vl
                        WHERE `patient_art_no`='" . $patientId . "' ORDER by DATE(request_created_datetime) DESC limit 1";
        } elseif ($testType == 'eid') {
            $sQuery = "SELECT DATE_FORMAT(DATE(request_created_datetime),'%d-%b-%Y') as request_created_datetime,DATE_FORMAT(DATE(sample_collection_date),'%d-%b-%Y') as sample_collection_date, (SELECT count(*) FROM `form_eid` WHERE `child_id`='" . $patientId . "') as no_of_req_time,
                        (SELECT count(*) FROM `form_eid` WHERE `child_id`='" . $patientId . "' AND sample_tested_datetime IS NOT NULL AND sample_tested_datetime!='0000-00-00 00:00:00') as no_of_tested_time from form_eid
                        WHERE `child_id`='" . $patientId . "' ORDER by DATE(request_created_datetime) DESC limit 1";
        } elseif ($testType == 'covid19') {
            $sQuery = "SELECT DATE_FORMAT(DATE(request_created_datetime),'%d-%b-%Y') as request_created_datetime,DATE_FORMAT(DATE(sample_collection_date),'%d-%b-%Y') as sample_collection_date, (SELECT count(*) FROM `form_covid19` WHERE `patient_id`='" . $patientId . "') as no_of_req_time,
                        (SELECT count(*) FROM `form_covid19` WHERE `patient_id`='" . $patientId . "' AND sample_tested_datetime IS NOT NULL AND sample_tested_datetime!='0000-00-00 00:00:00') as no_of_tested_time from form_covid19
                        WHERE `patient_id`='" . $patientId . "' ORDER by DATE(request_created_datetime) DESC limit 1";
        } elseif ($testType == 'hepatitis') {
            $sQuery = "SELECT DATE_FORMAT(DATE(request_created_datetime),'%d-%b-%Y') as request_created_datetime,DATE_FORMAT(DATE(sample_collection_date),'%d-%b-%Y') as sample_collection_date, (SELECT count(*) FROM `form_hepatitis` WHERE `patient_id`='" . $patientId . "') as no_of_req_time,
                        (SELECT count(*) FROM `form_hepatitis` WHERE `patient_id`='" . $patientId . "' AND sample_tested_datetime IS NOT NULL AND sample_tested_datetime!='0000-00-00 00:00:00') as no_of_tested_time from form_hepatitis
                        WHERE `patient_id`='" . $patientId . "' ORDER by DATE(request_created_datetime) DESC limit 1";
        }
        return $this->db->rawQueryOne($sQuery);
    }
}
