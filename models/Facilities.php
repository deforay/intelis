<?php

namespace Vlsm\Models;

class Facilities
{

    protected $db = null;
    protected $table = 'facility_details';

    public function __construct($db = null)
    {
        $this->db = !empty($db) ? $db : \MysqliDb::getInstance();
    }

    public function getAllFacilities($facilityType = null, $onlyActive = true)
    {

        $this->db->orderBy("facility_name", "asc");

        if (!empty($facilityType)) {
            $this->db->where("facility_type", $facilityType);
        }

        if ($onlyActive) {
            $this->db->where('status', 'active');
        }

        return $this->db->get("facility_details");
    }


    public function searchOrAdd($facilityType, $facilityName = null, $facilityOtherId = null)
    {

        $this->db->orderBy("facility_name", "asc");

        if (!empty($facilityOtherId)) {
            $this->db->where("other_id", $facilityOtherId);
        }
        if (!empty($facilityName)) {
            $this->db->where("facility_name", $facilityName);
        }

        if ($facilityType) {
            $this->db->where('facility_type', $facilityType);
        }

        return $this->db->getOne("facility_details");
    }

    public function getFacilityByName($facilityName)
    {
        if (!empty($facilityName)) {
            $this->db->where("facility_name", $facilityName);
        }
        $this->db->join("province_details p", "p.province_name=f.facility_state", "INNER");
        return $this->db->get("facility_details f");
    }

    public function getFacilityById($facilityId)
    {
        if (!empty($facilityId)) {
            $this->db->where("facility_id", $facilityId);
        }
        return $this->db->getOne("facility_details");
    }

    public function getTestingPoints($facilityId)
    {

        if (empty($facilityId)) return null;

        $response = null;
        $this->db->where("facility_id", $facilityId);
        $testingPointsJson = $this->db->getValue($this->table, 'testing_points');
        if ($testingPointsJson) {
            $response = json_decode($testingPointsJson, true);
        }
        return $response;
    }

    // $facilityType = 1 for getting all mapped health facilities
    // $facilityType = 2 for getting all mapped testing labs
    // $facilityType = null for getting all mapped facilities
    public function getFacilityMap($userId, $facilityType = null)
    {
        if (empty($userId)) return null;

        /* if (!empty($facilityType)) {
            $this->db->join("facility_details f", "map.facility_id=f.facility_id", "INNER");
            $this->db->joinWhere("facility_details f", "f.facility_type", $facilityType);
        } */

        $this->db->where("user_id", $userId);
        $response = $this->db->getValue("vl_user_facility_map", "facility_id",  null);
        if ($this->db->count > 0) {
            return implode(",", $response);
        } else {
            return null;
        }
    }



    // $testType = vl, eid, covid19 or any other tests that might be there. 
    // Default $testType is null and returns all facilities
    // $byPassFacilityMap = true -> bypass facility map check, false -> do not bypass facility map check
    // $condition = WHERE condition (for eg. "facility_state = 1")
    // $allColumns = (false -> only facility_id and facility_name, true -> all columns)
    // $onlyActive = true/false
    public function getHealthFacilities($testType = null, $byPassFacilityMap = false, $allColumns = false, $condition = null, $onlyActive = true)
    {

        if (!$byPassFacilityMap && !empty($_SESSION['userId'])) {
            $facilityMap = $this->getFacilityMap($_SESSION['userId']);
            if (!empty($facilityMap)) {
                $this->db->where("`facility_id` IN (" . $facilityMap . ")");
            }
        }

        if (!empty($testType)) {
            // subquery
            $healthFacilities = $this->db->subQuery();
            // we want to fetch facilities that have test type is not specified as well as this specific test type
            $healthFacilities->where("test_type is null or test_type like '$testType'");
            $healthFacilities->get("health_facilities", null, "facility_id");

            $this->db->where("facility_id", $healthFacilities, 'IN');
        }

        if ($onlyActive) {
            $this->db->where('status', 'active');
        }

        if (!empty($condition)) {
            $this->db->where($condition);
        }

        $this->db->orderBy("facility_name", "asc");

        if ($allColumns) {
            return $this->db->get("facility_details");
        } else {

            $response = array();
            $cols = array("facility_id", "facility_name");

            $results = $this->db->get("facility_details", null, $cols);

            foreach ($results as $row) {
                $response[$row['facility_id']] = $row['facility_name'];
            }
            return $response;
        }
    }


    // $testType = vl, eid, covid19 or any other tests that might be there. 
    // Default $testType is null and returns all facilities with type=2 (testing site)
    // $byPassFacilityMap = true -> bypass faciliy map check, false -> do not bypass facility map check
    // For testing labs we usually want to show all so we bypass = true by default
    // $condition = WHERE condition (for eg. "facility_state = 1")
    // $allColumns = (false -> only facility_id and facility_name, true -> all columns)
    // $onlyActive = true/false
    public function getTestingLabs($testType = null, $byPassFacilityMap = true, $allColumns = false, $condition = null, $onlyActive = true)
    {

        if (!$byPassFacilityMap && !empty($_SESSION['userId'])) {
            $facilityMap = $this->getFacilityMap($_SESSION['userId'], 2);
            if (!empty($facilityMap)) {
                $this->db->where("`facility_id` IN (" . $facilityMap . ")");
            }
        }

        if (!empty($testType)) {
            // subquery
            $testingLabs = $this->db->subQuery();
            // we want to fetch facilities that have test type is not specified as well as this specific test type
            $testingLabs->where("test_type is null or test_type like '$testType'");
            $testingLabs->get("testing_labs", null, "facility_id");

            $this->db->where("facility_id", $testingLabs, 'IN');
        }

        if ($onlyActive) {
            $this->db->where('status', 'active');
        }

        if (!empty($condition)) {
            $this->db->where($condition);
        }


        $this->db->where('facility_type = 2');
        $this->db->orderBy("facility_name", "asc");

        if ($allColumns) {
            return $this->db->get("facility_details");
        } else {
            $response = array();
            $cols = array("facility_id", "facility_name");
            $results = $this->db->get("facility_details", null, $cols);
            foreach ($results as $row) {
                $response[$row['facility_id']] = $row['facility_name'];
            }
            return $response;
        }
    }
}
