<?php

/**
 * GeoLocations functions
 *
 * @author Thana
 */

namespace App\Models;

use App\Utilities\DateUtils;
use MysqliDb;

class GeoLocations
{

    protected $db = null;

    public function __construct($db = null)
    {
        $this->db = !empty($db) ? $db : MysqliDb::getInstance();
    }

    public function getProvinces($isApi = "no", $onlyActive = true, $facilityMap = null)
    {
        return $this->fetchActiveGeolocations(null, 0, $isApi, $onlyActive, $facilityMap);
    }

    public function getDistricts($province, $isApi = "no", $onlyActive = true, $facilityMap = null)
    {
        return $this->fetchActiveGeolocations(null, $province, $isApi, $onlyActive, $facilityMap);
    }

    public function fetchActiveGeolocations($geoId = 0, $parent = 0, $api = "yes", $onlyActive = true, $facilityMap = null, $updatedDateTime = null)
    {
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
        if(isset($_SESSION['mappedProvinces']) && !empty($_SESSION['mappedProvinces'])){
            $this->db->where('geo_id', $_SESSION['mappedProvinces']);
        }

        if ($updatedDateTime) {
            $this->db->where("updated_datetime >= '$updatedDateTime'");
        }

        if (!empty($facilityMap)) {
            $this->db->join("facility_details", "facility_state_id=geo_id", "INNER");
            $this->db->where("facility_id IN (" . $facilityMap . ")");
        }

        $this->db->orderBy("geo_name", "asc");

        $response = $this->db->get("geographical_divisions");

        if ($api == 'yes') {
            foreach ($response as $row) {
                $returnArr[$row['geo_id']] = ($row['geo_name']);
            }
        } else {
            $returnArr = $response;
        }
        return $returnArr;
    }

    // get province id from the province table
    public function getProvinceIDFromCode($code)
    {
        if ($this->db == null) {
            return false;
        }

        $pQuery = "SELECT * FROM geographical_divisions WHERE (geo_parent = 0) AND (geo_code like ?)";
        $pResult = $this->db->rawQueryOne($pQuery, array($code));

        if ($pResult) {
            return $pResult['geo_id'];
        } else {
            return null;
        }
    }

    function addGeoLocation($geoName, $parent = 0)
    {
        $general = new General($this->db);

        $data = array(
            'geo_name'         => $geoName,
            'geo_status'       => 'active',
            'created_by'       => $_SESSION['userId'],
            'created_on'       => DateUtils::getCurrentDateTime(),
            'updated_datetime' => DateUtils::getCurrentDateTime(),
            'data_sync'       => 0
        );
        if ($parent > 0) {
            $data['geo_parent'] = $parent;
        }
        /* Check if the name already there? */
        $this->db->where("geo_name", $geoName);
        $geo = $this->db->getOne('geographical_divisions');

        /* if yes then update or else insert and return Id */
        if (isset($geo) && $geo != "") {
            $db = $this->db->where('geo_id', $geo['geo_id']);
            $db->update('geographical_divisions', $data);
            return $geo['geo_id'];
        } else {
            $this->db->insert('geographical_divisions', $data);
        }
        return $this->db->getInsertId();
    }

    public function getByName($geoName)
    {
        $this->db->where("geo_name", $geoName);
        return $this->db->getOne('geographical_divisions', array("geo_id", "geo_name"));
    }

    public function getById($geoId)
    {
        $this->db->where("geo_id", $geoId);
        return $this->db->getOne('geographical_divisions', array("geo_id", "geo_name"));
    }

    public function getByProvinceId($provinceId, $districts = true, $facilities = false, $labs = false){

        $response = [];
    
        if($districts === true){
            $districtSql = "SELECT geo_id, geo_name from geographical_divisions WHERE geo_parent = $provinceId AND geo_status='active'";
            $response['districts'] = $this->db->rawQuery($districtSql);
        }
      
        if($facilities === true){
            $facilitySql = "SELECT facility_id, facility_name, facility_code from facility_details WHERE (facility_type = 1 or facility_type = 3) AND facility_state_id = $provinceId AND status='active'";
            $response['facilities'] = $this->db->rawQuery($facilitySql);
        }
      
       if($labs === true){
            $facilitySql = "SELECT facility_id, facility_name from facility_details WHERE facility_type = 2  AND facility_state_id = $provinceId AND status='active'";
            $response['labs'] = $this->db->rawQuery($facilitySql);
        }
        
        return $response;
    }

    public function getByDistrictId($districtId, $facilities = true, $labs = false){

        $response = [];
      
        if($facilities === true){
            $facilitySql = "SELECT facility_id, facility_name, facility_code from facility_details WHERE (facility_type = 1 or facility_type = 3) AND facility_district_id = $districtId AND status='active'";
            $response['facilities'] = $this->db->rawQuery($facilitySql);
        }
      
        if($labs === true){
            $labSql = "SELECT facility_id, facility_name from facility_details WHERE facility_type = 2  AND facility_district_id = $districtId AND status='active'";
            $response['labs'] = $this->db->rawQuery($labSql);
        }
      
        return $response;
    }
}
