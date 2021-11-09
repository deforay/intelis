<?php

/**
 * GeoLocations functions
 *
 * @author Thana
 */

namespace Vlsm\Models;

class GeoLocations
{

    protected $db = null;

    public function __construct($db = null)
    {
        $this->db = !empty($db) ? $db : \MysqliDb::getInstance();
    }

    public function getProvinces($isApi = "no", $onlyActive = true, $facilityMap = null)
    {
        return $this->fetchActiveGeolocations(null, 0, $isApi, $onlyActive, $facilityMap);
    }

    public function getDistricts($province, $isApi = "no", $onlyActive = true, $facilityMap = null)
    {
        return $this->fetchActiveGeolocations(null, $province, $isApi, $onlyActive, $facilityMap);
    }

    public function fetchActiveGeolocations($geoId = 0, $parent = 0, $api = "yes", $onlyActive = true, $facilityMap = null)
    {
        $returnArr = array();
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

        if (!empty($facilityMap)) {
            $this->db->join("facility_details", "facility_state_id=geo_id", "INNER");
            $this->db->where("facility_id IN (" . $facilityMap . ")");
        }

        $this->db->orderBy("geo_name", "asc");

        $response = $this->db->get("geographical_divisions");

        if ($api == 'yes') {
            foreach ($response as $row) {
                $returnArr[$row['geo_id']] = ucwords($row['geo_name']);
            }
        } else {
            $returnArr = $response;
        }
        return $returnArr;
    }

    function addGeoLocation($geoName, $parent = 0)
    {
        $general = new \Vlsm\Models\General($this->db);

        $data = array(
            'geo_name'         => $geoName,
            'geo_status'       => 'active',
            'created_by'       => $_SESSION['userId'],
            'created_on'       => $general->getDateTime(),
            'updated_datetime' => $general->getDateTime(),
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
}
