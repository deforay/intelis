<?php

/**
 * General functions
 *
 * @author Amit
 */

namespace Vlsm\Models;

class App
{

    protected $db = null;

    public function __construct($db = null)
    {
        $this->db = !empty($db) ? $db : \MysqliDb::getInstance();
    }

    public function generateSelectOptions($options)
    {
        $i = 0;
        foreach ($options as $key => $show) {
            $response[$i]['value'] = $key;
            $response[$i]['show'] = $show;
            $i++;
        }
        return $response;
    }

    public function getAppHealthFacilities($testType = null, $user = null, $onlyActive = false, $facilityType = 0, $module = false, $activeModule = null)
    {
        $facilityDb = new \Vlsm\Models\Facilities($this->db);
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
                        pd.province_id, 
                        pd.province_name
                    FROM health_facilities AS hf 
                    INNER JOIN facility_details as f ON hf.facility_id=f.facility_id
                    INNER JOIN province_details as pd ON pd.province_name=f.facility_state";
        $where = "";
        if (!empty($user)) {
            $facilityMap = $facilityDb->getUserFacilityMap($user);
            if (!empty($facilityMap)) {
                if (isset($where) && trim($where) != "") {
                    $where .= " AND ";
                } else {
                    $where .= " WHERE ";
                }
                $where .= " f.facility_id IN (" . $facilityMap . ")";
            }
        }

        if (!$module && $facilityType == 1) {
            if (!empty($activeModule)) {
                if (isset($where) && trim($where) != "") {
                    $where .= " AND ";
                } else {
                    $where .= " WHERE ";
                }
                $where .= " hf.test_type IN (" . $activeModule . ")";
            }
        }

        if (!empty($testType)) {
            if (isset($where) && trim($where) != "") {
                $where .= " AND ";
            } else {
                $where .= " WHERE ";
            }
            $where .= " hf.test_type like '$testType'";
        }

        if ($onlyActive) {
            if (isset($where) && trim($where) != "") {
                $where .= " AND ";
            } else {
                $where .= " WHERE ";
            }
            $where .= " f.status like 'active'";
        }

        if ($facilityType > 0) {
            if (isset($where) && trim($where) != "") {
                $where .= " AND ";
            } else {
                $where .= " WHERE ";
            }
            $where .= " f.facility_type = '$facilityType'";
        }
        $where .= ' GROUP BY facility_name ORDER BY facility_name ASC ';
        $query .= $where;
        $result = $this->db->rawQuery($query);
        foreach ($result as $key => $row) {
            // $condition1 = " province_name like '" . $row['province_name'] . "%'";
            // $condition2 = " (facility_state like '" . $row['province_name'] . "%' OR facility_district_id like )";
            if ($module) {
                $response[$key]['value']        = $row['facility_id'];
                $response[$key]['show']         = $row['facility_name'] . ' (' . $row['facility_code'] . ')';
            } else {
                $response[$key]['facility_id']          = $row['facility_id'];
                $response[$key]['facility_name']        = $row['facility_name'];
                $response[$key]['facility_code']        = $row['facility_code'];
                $response[$key]['other_id']             = $row['other_id'];
                $response[$key]['facility_state_id']    = $row['facility_state_id'];
                $response[$key]['facility_state']       = $row['facility_state'];
                $response[$key]['facility_district_id'] = $row['facility_district_id'];
                $response[$key]['facility_district']    = $row['facility_district'];
                $response[$key]['facility_attributes']  = $row['facility_attributes'];
                $response[$key]['testing_points']       = $row['testing_points'];
                $response[$key]['status']               = $row['status'];
            }
            if (!$module && $facilityType == 1) {
                $response[$key]['test_type'] = $row['test_type'];
            }
            // $response[$key]['provinceDetails'] = $this->getSubFields('province_details', 'province_id', 'province_name', $condition1);
            // $response[$key]['districtDetails'] = $this->getSubFields('facility_details', 'facility_district', 'facility_district', $condition2);
        }
        return $response;
    }

    public function getTestingLabs($testType = null, $user = null, $onlyActive = false, $module = false)
    {
        $facilityDb = new \Vlsm\Models\Facilities($this->db);
        $query = "SELECT tl.test_type, f.facility_id, f.facility_name, f.facility_code, f.other_id, f.facility_state_id, f.facility_state, f.facility_district_id, f.facility_district, f.testing_points, f.status, pd.province_id, pd.province_name
                    from testing_labs AS tl 
                    INNER JOIN facility_details as f ON tl.facility_id=f.facility_id
                    LEFT JOIN province_details as pd ON pd.province_name=f.facility_state";
        $where = "";
        if (!empty($user)) {
            $facilityMap = $facilityDb->getUserFacilityMap($user);
            if (!empty($facilityMap)) {
                if (isset($where) && trim($where) != "") {
                    $where .= " AND ";
                } else {
                    $where .= " WHERE ";
                }
                $where .= " f.facility_id IN (" . $facilityMap . ")";
            }
        }

        if (!$module) {
            if (!empty($activeModule)) {
                if (isset($where) && trim($where) != "") {
                    $where .= " AND ";
                } else {
                    $where .= " WHERE ";
                }
                $where .= " tl.test_type IN (" . $activeModule . ")";
            }
        }

        if (!empty($testType)) {
            if (isset($where) && trim($where) != "") {
                $where .= " AND ";
            } else {
                $where .= " WHERE ";
            }
            $where .= " tl.test_type like '$testType'";
        }

        if ($onlyActive) {
            if (isset($where) && trim($where) != "") {
                $where .= " AND ";
            } else {
                $where .= " WHERE ";
            }
            $where .= " f.status like 'active'";
        }

        $where .= ' GROUP BY facility_name ORDER BY facility_name ASC';
        $query .= $where;
        $result = $this->db->rawQuery($query);
        foreach ($result as $key => $row) {
            // $condition1 = " province_name like '" . $row['province_name'] . "%'";
            // $condition2 = " facility_state like '" . $row['province_name'] . "%'";

            $response[$key]['value']        = $row['facility_id'];
            $response[$key]['show']         = $row['facility_name'] . ' (' . $row['facility_code'] . ')';
            $response[$key]['state']        = $row['facility_state'];
            $response[$key]['district']     = $row['facility_district'];
            if (!$module) {
                $response[$key]['test_type']                = $row['test_type'];
                $response[$key]['monthly_target']           = $row['monthly_target'];
                $response[$key]['suppressed_monthly_target'] = $row['suppressed_monthly_target'];
            }
            // $response[$key]['provinceDetails'] = $this->getSubFields('province_details', 'province_id', 'province_name', $condition1);
            // $response[$key]['districtDetails'] = $this->getSubFields('facility_details', 'facility_district', 'facility_district', $condition2);
        }
        return $response;
    }

    public function getProvinceDetails($user = null, $onlyActive = false)
    {
        $facilityDb = new \Vlsm\Models\Facilities($this->db);
        $query = "SELECT f.facility_id, f.facility_name, f.facility_code, pd.province_id, pd.province_name, f.facility_district, f.facility_type 
                    from province_details AS pd 
                    LEFT JOIN facility_details as f ON pd.province_name=f.facility_state";
        $where = "";
        if (!empty($user)) {
            $facilityMap = $facilityDb->getUserFacilityMap($user);
            if (!empty($facilityMap)) {
                if (isset($where) && trim($where) != "") {
                    $where .= " AND ";
                } else {
                    $where .= " WHERE ";
                }
                $where .= " f.facility_id IN (" . $facilityMap . ")";
            }
        }

        if ($onlyActive) {
            if (isset($where) && trim($where) != "") {
                $where .= " AND ";
            } else {
                $where .= " WHERE ";
            }
            $where .= " f.status like 'active'";
        }

        $where .= ' GROUP BY province_name ORDER BY province_name ASC';
        $query .= $where;
        $result = $this->db->rawQuery($query);
        foreach ($result as $key => $row) {
            $condition1 = " facility_state like '" . $row['province_name'] . "%'";

            $response[$key]['value']    = $row['province_id'];
            $response[$key]['show']     = $row['province_name'];
            // $response[$key]['district'] = $row['facility_district'];
            $response[$key]['districtDetails'] = $this->getSubFields('facility_details', 'facility_district', 'facility_district', $condition1);
        }
        return $response;
    }

    public function getDistrictDetails($user = null, $onlyActive = false)
    {
        $facilityDb = new \Vlsm\Models\Facilities($this->db);
        $query = "SELECT f.facility_id, f.facility_name, f.facility_code, pd.province_id, pd.province_name, f.facility_district
                    from province_details AS pd 
                    LEFT JOIN facility_details as f ON pd.province_name=f.facility_state";
        $where = "";
        if (!empty($user)) {
            $facilityMap = $facilityDb->getUserFacilityMap($user);
            if (!empty($facilityMap)) {
                if (isset($where) && trim($where) != "") {
                    $where .= " AND ";
                } else {
                    $where .= " WHERE ";
                }
                $where .= " f.facility_id IN (" . $facilityMap . ")";
            }
        }

        if ($onlyActive) {
            if (isset($where) && trim($where) != "") {
                $where .= " AND ";
            } else {
                $where .= " WHERE ";
            }
            $where .= " f.status like 'active'";
        }

        $where .= ' GROUP BY facility_district ORDER BY facility_district ASC';
        $query .= $where;
        // die($query);
        $result = $this->db->rawQuery($query);
        foreach ($result as $key => $row) {
            $condition1 = " facility_district like '" . $row['facility_district'] . "%'";
            $condition2 = " province_name like '" . $row['province_name'] . "%'";

            $response[$key]['value']        = $row['facility_district'];
            $response[$key]['show']         = $row['facility_district'];
            $response[$key]['facilityDetails'] = $this->getSubFields('facility_details', 'facility_id', 'facility_name', $condition1);
            $response[$key]['provinceDetails'] = $this->getSubFields('province_details', 'province_id', 'province_name', $condition2);
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
        $response = array();
        foreach ($result as $key => $row) {
            $response[$key]['value'] = $row[$primary];
            $response[$key]['show'] = $row[$name];
        }
        return $response;
    }

    public function getCovid19TestsByFormId($formId)
    {
        if (empty($formId)) {
            return null;
        }

        // Using this in sync requests/results
        if (is_array($formId)) {
            $sQuery = "SELECT * FROM covid19_tests WHERE `covid19_id` IN (" . implode(",", $formId) . ") ORDER BY test_id ASC";
        } else {
            $sQuery = "SELECT * FROM covid19_tests WHERE `covid19_id` = $formId ORDER BY test_id ASC";
        }
        return $this->db->rawQuery($sQuery);
    }

    public function addApiTracking($user, $records, $type, $testType, $url = null, $params = null, $format = null)
    {
        $general = new \Vlsm\Models\General($this->db);
        $data = array(
            'requested_by'          => $user ?: 'vlsm-system',
            'requested_on'          => $general->getCurrentDateTime(),
            'number_of_records'     => $records ?: 0,
            'request_type'          => $type ?: null,
            'test_type'             => $testType ?: null,
            'api_url'               => $url ?: null,
            'api_params'            => $params ?: null,
            'data_format'           => $format ?: null
        );
        if ($format == 'sync-api') {
            $data['facility_id'] = (isset($params['data'][0]['facilityId']) && count($params['data'][0]['facilityId']) > 0) ? $params['data'][0]['facilityId'] : null;
        }
        return $this->db->insert("track_api_requests", $data);
    }

    public function getTableDataUsingId($tablename, $fieldName, $value)
    {
        return $this->db->rawQueryOne("SELECT * FROM " . $tablename . " WHERE " . $fieldName . " = " . $value);
    }

    public function getCovid19TestsByC19Id($c19Id)
    {
        if (empty($c19Id)) {
            return null;
        }
        return $this->db->rawQuery("SELECT test_id as testId, covid19_id as covid19Id, facility_id as facilityId, test_name as testName, kit_lot_no as kitLotNo, kit_expiry_date as kitExpiryDate, tested_by as testedBy, sample_tested_datetime as testDate, testing_platform as testingPlatform, result as testResult FROM covid19_tests WHERE `covid19_id` = $c19Id ORDER BY test_id ASC");
    }
}
