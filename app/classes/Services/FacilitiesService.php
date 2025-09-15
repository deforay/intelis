<?php

namespace App\Services;

use App\Utilities\DateUtility;
use App\Utilities\MemoUtility;
use App\Services\DatabaseService;

final class FacilitiesService
{
    protected $db;
    private $facilityTypeTableList = [
        1 => "health_facilities",
        2 => "testing_labs",
        3 => "health_facilities",
    ];

    protected string $table = 'facility_details';

    public function __construct(DatabaseService $db)
    {
        $this->db = $db;
    }

    public function getAllFacilities($facilityType = null, $onlyActive = true)
    {
        return MemoUtility::remember(function () use ($facilityType, $onlyActive) {

            $this->db->orderBy("facility_name", "asc");

            if (!empty($facilityType)) {
                $this->db->where("facility_type", $facilityType);
            }

            if ($onlyActive) {
                $this->db->where('status', 'active');
            }

            return $this->db->get("facility_details");
        });
    }

    public function getFacilitiesForResultUpload($testType)
    {
        return MemoUtility::remember(function () use ($testType) {

        $fQuery = 'SELECT * FROM facility_details as f
            INNER JOIN testing_labs as t ON t.facility_id=f.facility_id
            WHERE t.test_type = ?
                AND f.facility_type=2
                AND (f.facility_attributes->>"$.allow_results_file_upload" = "yes"
                    OR f.facility_attributes->>"$.allow_results_file_upload" IS NULL)
            ORDER BY f.facility_name ASC';
        return $this->db->rawQuery($fQuery, [$testType]);
        });
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
        $this->db->join("geographical_divisions g", "g.geo_id=f.facility_state_id", "INNER");
        return $this->db->get("facility_details f");
    }

    public function getFacilityById($facilityId)
    {
        if (!empty($facilityId)) {
            $this->db->where("facility_id", $facilityId);
        }
        return $this->db->getOne("facility_details");
    }

    public function getFacilityByAttribute($attributeName, $attributeValue)
    {
        $fQuery = "SELECT * FROM facility_details as f
                    WHERE f.facility_attributes->>\"$.$attributeName\" = ?
                    AND f.facility_attributes->>\"$.$attributeName\" is NOT NULL";

        return $this->db->rawQueryOne($fQuery, [$attributeValue]);
    }

    public function getTestingPoints($facilityId)
    {

        if (empty($facilityId)) {
            return null;
        }

        return MemoUtility::remember(function () use ($facilityId) {

            $response = null;
            $this->db->where("facility_id", $facilityId);
            $testingPointsJson = $this->db->getValue($this->table, 'testing_points');
            if ($testingPointsJson) {
                $response = json_decode($testingPointsJson, true);
            }
            return $response;
        });
    }

    // $facilityType = 1 for getting all mapped health facilities
    // $facilityType = 2 for getting all mapped testing labs
    // $facilityType = null for getting all mapped facilities
    public function getUserFacilityMap($userId, $facilityType = null)
    {

        return MemoUtility::remember(function () use ($userId, $facilityType) {

            if (empty($userId)) {
                return null;
            }

            /* if (!empty($facilityType)) {
            $this->db->join("facility_details f", "map.facility_id=f.facility_id", "INNER");
            $this->db->joinWhere("facility_details f", "f.facility_type", $facilityType);
            } */
            $userfacilityMap = null;
            $this->db->where("user_id", $userId);
            $response = $this->db->getValue("user_facility_map", "facility_id", null);
            if ($this->db->count > 0) {
                $userfacilityMap = implode(",", $response);
            }
            return $userfacilityMap;
        });
    }


    public function getTestingLabFacilityMap($labId)
    {
        if (empty($labId)) {
            return null;
        }

        return MemoUtility::remember(function () use ($labId) {
            $this->db->where("vl_lab_id", $labId);
            $fMapResult = $this->db->getValue('testing_lab_health_facilities_map', 'facility_id', null);

            if (!empty($fMapResult)) {
                //$fMapResult = array_map('current', $fMapResult);
                $fMapResult = implode(",", $fMapResult);
            }

            return $fMapResult;
        });
    }



    // $testType = vl, eid, covid19 or any other tests that might be there.
    // Default $testType is null and returns all facilities
    // $byPassFacilityMap = true -> bypass facility map check, false -> do not bypass facility map check
    // $condition = WHERE condition (for eg. "facility_state = 1")
    // $allColumns = (false -> only facility_id and facility_name, true -> all columns)
    // $onlyActive = true/false
    public function getHealthFacilities($testType = null, $byPassFacilityMap = false, $allColumns = false, $condition = [], $onlyActive = true, $userId = null)
    {

        $userId = $userId ?? $_SESSION['userId'] ?? null;
        if (!$byPassFacilityMap && !empty($userId)) {
            $facilityMap = $_SESSION['facilityMap'] ?? $this->getUserFacilityMap($userId);
            if (!empty($facilityMap)) {
                $this->db->where("`facility_id` IN ($facilityMap)");
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
            $condition = !is_array($condition) ? [$condition] : $condition;
            foreach ($condition as $cond) {
                $this->db->where($cond);
            }
        }


        $this->db->orderBy("facility_name", "asc");

        if ($allColumns) {
            return $this->db->get("facility_details");
        } else {

            $response = [];

            $results = $this->db->get("facility_details", null, "facility_id,facility_name");

            foreach ($results as $row) {
                $response[$row['facility_id']] = $row['facility_name'];
            }
            return $response;
        }
    }


    public function updateFacilitySyncTime($facilityIds, $currentDateTime = null)
    {
        $currentDateTime = $currentDateTime ?? DateUtility::getCurrentDateTime();
        if (!empty($facilityIds)) {
            $facilityIds = array_unique(array_filter($facilityIds));
            $sql = 'UPDATE facility_details
                        SET facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.remoteRequestsSync", ?, "$.vlRemoteRequestsSync", ?)
                        WHERE facility_id IN (' . implode(",", $facilityIds) . ')';
            $this->db->rawQuery($sql, array($currentDateTime, $currentDateTime));
        }
    }


    // $testType = vl, eid, covid19 or any other tests that might be there.
    // Default $testType is null and returns all facilities with type=2 (testing site)
    // $byPassFacilityMap = true -> bypass faciliy map check, false -> do not bypass facility map check
    // For testing labs we usually want to show all so we bypass = true by default
    // $condition = WHERE condition (for eg. "facility_state = 1")
    // $allColumns = (false -> only facility_id and facility_name, true -> all columns)
    // $onlyActive = true/false
    public function getTestingLabs($testType = null, $byPassFacilityMap = true, $allColumns = false, $condition = [], $onlyActive = true, $userId = null)
    {
        $userId = $userId ?: $_SESSION['userId'] ?: null;
        if (!$byPassFacilityMap && !empty($userId)) {
            $facilityMap = $this->getUserFacilityMap($userId, 2);
            if (!empty($facilityMap)) {
                $this->db->where("`facility_id` IN ($facilityMap)");
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
            $condition = !is_array($condition) ? [$condition] : $condition;
            foreach ($condition as $cond) {
                $this->db->where($cond);
            }
        }

        $this->db->where('facility_type = 2');
        $this->db->orderBy("facility_name", "asc");

        if ($allColumns) {
            return $this->db->get("facility_details");
        } else {
            $response = [];
            $results = $this->db->get("facility_details", null, "facility_id,facility_name");
            foreach ($results as $row) {
                $response[$row['facility_id']] = $row['facility_name'];
            }
            return $response;
        }
    }

    public function getOrCreateProvince(string $provinceName, string $provinceCode = null): int
    {
        // check if there is a province matching the input params, if yes then return province id
        $this->db->where("geo_name ='$provinceName'");
        if ($provinceCode != "") {
            $this->db->where("geo_code ='$provinceCode'");
        }
        $provinceInfo = $this->db->getOne('geographical_divisions');

        if (isset($provinceInfo['geo_id']) && $provinceInfo['geo_id'] != "") {
            return $provinceInfo['geo_id'];
        } else {
            // if not then insert and return the new province id
            $data = array(
                'geo_name' => $provinceName,
                'geo_status' => 'active',
                'updated_datetime' => DateUtility::getCurrentDateTime(),
            );
            $this->db->insert('geographical_divisions', $data);
            return $this->db->getInsertId();
        }
    }

    public function getOrCreateDistrict(?string $districtName, ?string $districtCode = null, ?int $provinceId = null): int
    {
        // check if there is a district matching the input params, if yes then return province id
        $this->db->where("geo_name ='$districtName' AND geo_parent = $provinceId");
        if ($districtCode != "") {
            $this->db->where("geo_code ='$districtCode'");
        }
        $districtInfo = $this->db->getOne('geographical_divisions');

        if (isset($districtInfo['geo_id']) && $districtInfo['geo_id'] != "") {
            return $districtInfo['geo_id'];
        } else {
            // if not then insert and return the new province id
            $data = array(
                'geo_name' => $districtName,
                'geo_parent' => $provinceId,
                'geo_status' => 'active',
                'updated_datetime' => DateUtility::getCurrentDateTime(),
            );
            $this->db->insert('geographical_divisions', $data);
            return $this->db->getInsertId();
        }
    }
    public function getFacilitiesDropdown($testType, $facilityType, $provinceName = null, $districtRequested = null, $option = null, $comingFromUser = null): string
    {

        $facilityTypeTable = $this->facilityTypeTableList[$facilityType];

        $this->db->where("f.status", 'active');
        $this->db->orderBy("f.facility_name", "ASC");

        if (!empty($provinceName)) {
            if (is_numeric($provinceName)) {
                $this->db->where("f.facility_state_id", $provinceName);
            } else {
                $this->db->where("f.facility_state", $provinceName);
            }
        }

        if (!empty($districtRequested)) {
            if (is_numeric($districtRequested)) {
                $this->db->where("f.facility_district_id", $districtRequested);
            } else {
                $this->db->where("f.facility_district", $districtRequested);
            }
        }
        //$db->where("f.facility_type", $facilityTypeRequested);
        $this->db->join("user_details u", "u.user_id=f.contact_person", "LEFT");
        $this->db->join("$facilityTypeTable h", "h.facility_id=f.facility_id", "INNER");
        $this->db->joinWhere("$facilityTypeTable h", "h.test_type", $testType);

        if (!empty($_SESSION['facilityMap'])) {
            $this->db->where("f.facility_id IN (" . $_SESSION['facilityMap'] . ")");
        }

        $facilityInfo = $this->db->get('facility_details f', null, 'f.* , u.user_name as contact_person');
        $facility = '';
        if ($facilityInfo) {
            if (!isset($comingFromUser)) {
                $facility .= $option;
            }
            foreach ($facilityInfo as $fDetails) {
                $fcode = (isset($fDetails['facility_code']) && $fDetails['facility_code'] != "") ? ' - ' . $fDetails['facility_code'] : '';

                $facility .= "<option data-code='" . $fDetails['facility_code'] . "' data-emails='" . $fDetails['facility_emails'] . "' data-mobile-nos='" . $fDetails['facility_mobile_numbers'] . "' data-contact-person='" . ($fDetails['contact_person']) . "' value='" . $fDetails['facility_id'] . "'>" . (htmlspecialchars((string) $fDetails['facility_name'])) . $fcode . "</option>";
            }
        } else {
            $facility .= $option;
        }
        return $facility;
    }
}
