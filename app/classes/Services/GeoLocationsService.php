<?php

namespace App\Services;

use App\Utilities\DateUtility;
use App\Utilities\MemoUtility;
use App\Services\DatabaseService;

final class GeoLocationsService
{

    public function __construct(protected DatabaseService $db)
    {
    }

    public function getProvinces($isApi = "no", $onlyActive = true, $facilityMap = null)
    {
        return $this->fetchActiveGeolocations(null, 0, $isApi, $onlyActive, $facilityMap);
    }

    public function getDistricts($province, $isApi = "no", $onlyActive = true, $facilityMap = null)
    {
        return $this->fetchActiveGeolocations(null, $province, $isApi, $onlyActive, $facilityMap);
    }

    public function fetchActiveGeolocations($geoId = 0, $parent = 0, $api = "yes", $onlyActive = true, $facilityMap = null, $updatedDateTime = null, bool $orderByStatus = false): mixed
    {
        return MemoUtility::remember(function () use ($geoId, $parent, $api, $onlyActive, $facilityMap, $updatedDateTime, $orderByStatus) {

            $returnArr = [];
            if ($onlyActive) {
                $this->db->where('geo_status', 'active');
            }
            if (!empty($geoId) && $geoId > 0) {
                $this->db->where('geo_id', $geoId);
            }
            if (!empty($parent)) {
                if ($parent == 'all') {
                    $this->db->where('geo_parent != 0');
                } else {
                    $this->db->where('geo_parent', $parent);
                }
            } else {
                $this->db->where('geo_parent', 0);
            }
            // Province access mapping should only be applied when fetching provinces (geo_parent = 0)
            if (!empty($_SESSION['mappedProvinces']) && (empty($parent) || $parent === 0 || $parent === "0")) {
                $this->db->where('geo_id', $_SESSION['mappedProvinces']);
            }

            if ($updatedDateTime) {
                $this->db->where("updated_datetime >= '$updatedDateTime'");
            }

            if (!empty($facilityMap)) {
                $this->db->join("facility_details", "facility_state_id=geo_id", "INNER");
                $this->db->where("facility_id IN ($facilityMap)");
            }

            if ($orderByStatus) {
                $this->db->orderBy("(geo_status = 'active')", "DESC");
                $this->db->orderBy("geo_name", "ASC");
            } else {
                $this->db->orderBy("geo_name", "asc");
            }

            $response = $this->db->get("geographical_divisions");

            if ($api == 'yes') {
                foreach ($response as $row) {
                    $returnArr[$row['geo_id']] = ($row['geo_name']);
                }
            } else {
                $returnArr = $response;
            }
            return $returnArr;
        });
    }

    // get province id from the province table
    public function getProvinceIDFromCode($code)
    {
        if ($this->db == null) {
            return false;
        }

        $pQuery = "SELECT geo_id FROM geographical_divisions WHERE (geo_parent = 0) AND (geo_code like ?)";
        $pResult = $this->db->rawQueryOne($pQuery, [$code]);

        if ($pResult) {
            return $pResult['geo_id'];
        } else {
            return null;
        }
    }
    // get province id from the province table
    public function getProvinceCodeFromId(int $provinceId)
    {
        if ($this->db == null || $provinceId == 0) {
            return null;
        }

        $q = "SELECT geo_code FROM geographical_divisions
                    WHERE (geo_parent = 0) AND (geo_id like ?)";
        $result = $this->db->rawQueryOne($q, [$provinceId]);

        if ($result) {
            return $result['geo_code'];
        } else {
            return null;
        }
    }

    public function addGeoLocation($geoName, $parent = 0)
    {

        $data = ['geo_name'         => $geoName, 'geo_status'       => 'active', 'created_by'       => $_SESSION['userId'], 'created_on'       => DateUtility::getCurrentDateTime(), 'updated_datetime' => DateUtility::getCurrentDateTime(), 'data_sync'       => 0];
        if ($parent > 0) {
            $data['geo_parent'] = $parent;
        }
        /* Check if the name already there? */
        $this->db->where("geo_name", $geoName);
        $geo = $this->db->getOne('geographical_divisions');

        /* if yes then update or else insert and return Id */
        if (isset($geo) && $geo != "") {
            $this->db->where('geo_id', $geo['geo_id']);
            $this->db->update('geographical_divisions', $data);
            return $geo['geo_id'];
        } else {
            $this->db->insert('geographical_divisions', $data);
        }
        return $this->db->getInsertId();
    }

    public function getProvinceIdByName($geoName)
    {
        $this->db->where("geo_name", $geoName);
        return $this->db->getValue('geographical_divisions', "geo_id");
    }

    public function getByProvinceId($provinceId, $districts = true, $facilities = false, $labs = false): array
    {

        $response = [];
        if (is_array($provinceId)) {
            $provinceId = implode(',', $provinceId);
        }
        if ($districts === true) {
            $districtSql = "SELECT geo_id, geo_name from geographical_divisions WHERE geo_parent IN ($provinceId) AND geo_status='active' ORDER BY geo_name";
            $response['districts'] = $this->db->rawQuery($districtSql);
        }

        if ($facilities === true) {
            $facilitySql = "SELECT facility_id, facility_name, facility_code from facility_details WHERE facility_state_id IN ($provinceId) AND status='active' ORDER BY facility_name";
            $response['facilities'] = $this->db->rawQuery($facilitySql);
        }

        if ($labs === true) {
            $facilitySql = "SELECT facility_id, facility_name from facility_details WHERE facility_type = 2  AND facility_state_id IN ($provinceId) AND status='active' ORDER BY facility_name";
            $response['labs'] = $this->db->rawQuery($facilitySql);
        }

        return $response;
    }

    public function getByDistrictId($districtId, $facilities = true, $labs = false): array
    {

        $response = [];

        if ($facilities === true) {
            $facilitySql = "SELECT facility_id, facility_name, facility_code from facility_details WHERE facility_district_id = $districtId AND status='active' ORDER BY facility_name";
            $response['facilities'] = $this->db->rawQuery($facilitySql);
        }

        if ($labs === true) {
            $labSql = "SELECT facility_id, facility_name from facility_details WHERE facility_type = 2  AND facility_district_id = $districtId AND status='active' ORDER BY facility_name";
            $response['labs'] = $this->db->rawQuery($labSql);
        }

        return $response;
    }

    public function getDistrictDropdown($selectedProvince = null, $selectedDistrict = null, $option = null): string
    {
        if (!empty($selectedProvince)) {

            if (is_numeric($selectedProvince)) {
                $this->db->where("geo_parent", $selectedProvince);
            } else {
                $ids = $this->db->subQuery();
                $ids->where("geo_parent", 0);
                $ids->where("geo_name", $selectedProvince);
                $ids->get("geographical_divisions", null, "geo_id");
                $this->db->where("geo_parent", $ids, 'in');
            }

            $this->db->orderBy("geo_name", "ASC");

            $districtInfo = $this->db->setQueryOption('DISTINCT')
                ->get('geographical_divisions', null, 'geo_id, geo_name');
            $district = (string) $option;
            foreach ($districtInfo as $dRow) {
                $selected = '';
                if ($selectedDistrict == $dRow['geo_id']) {
                    $selected = "selected='selected'";
                }
                $district .= "<option $selected value='" . $dRow['geo_id'] . "'>" . ($dRow['geo_name']) . "</option>";
            }
            return $district;
        }

        if (!empty($_SESSION['facilityMap'])) {
            $this->db->where("f.facility_id IN (" . $_SESSION['facilityMap'] . ")");
        }
        $this->db->orderBy("f.facility_name", "ASC");
        $facilityInfo = $this->db->setQueryOption('DISTINCT')
            ->get('facility_details f', null, 'facility_district_id, facility_district');

        $district = (string) $option;
        foreach ($facilityInfo as $fRow) {
            $selected = '';
            if ($selectedDistrict == $fRow['facility_district']) {
                $selected = "selected='selected'";
            }
            $district .= "<option $selected value='" . $fRow['facility_district_id'] . "'>" . $fRow['facility_district'] . "</option>";
        }
        return $district;
    }

    public function getProvinceDropdown($selectedProvince = null, $option = null)
    {
        if (!empty($_SESSION['facilityMap'])) {
            $this->db->join("facility_details f", "f.facility_state_id=p.geo_id", "INNER");
            $this->db->where("f.facility_id IN (" . $_SESSION['facilityMap'] . ")");
        }

        $this->db->where("p.geo_parent = 0");
        $this->db->orderBy("p.geo_name", "ASC");
        $pdResult = $this->db->setQueryOption('DISTINCT')
            ->get('geographical_divisions p', null, 'geo_id,geo_name,geo_code');
        $state = $option;
        foreach ($pdResult as $pRow) {
            $selected = '';
            if ($selectedProvince == $pRow['geo_id']) {
                $selected = "selected='selected'";
            }
            $state .= "<option data-code='" . $pRow['geo_code'] . "' data-province-id='" . $pRow['geo_id'] . "' data-name='" . $pRow['geo_name'] . "' value='" . $pRow['geo_id'] . "##" . $pRow['geo_code'] . "' $selected>" . ($pRow['geo_name']) . "</option>";
        }
        return $state;
    }
}
