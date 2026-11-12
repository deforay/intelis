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

    /**
     * @param bool $applyUserMapping Limit provinces to the ones the logged-in user's
     *        mapped facilities are in. Pass false on admin screens (Add/Edit Facility),
     *        which must list every province: otherwise a facility outside the user's
     *        provinces opens with no province selected and cannot be saved properly.
     */
    public function fetchActiveGeolocations($geoId = 0, $parent = 0, $api = "yes", $onlyActive = true, $facilityMap = null, $updatedDateTime = null, bool $orderByStatus = false, bool $applyUserMapping = true): mixed
    {
        // Province access mapping applies only when fetching provinces (geo_parent = 0).
        // It is worked out here, outside the memoised closure, so it is part of the
        // cache key: the cache is shared across requests, and keying it without the
        // mapping served one user's province list to every other user.
        $mappedProvinceIds = [];
        if ($applyUserMapping && !empty($_SESSION['mappedProvinces']) && (empty($parent) || $parent === 0 || $parent === "0")) {
            // mappedProvinces is a comma-separated string; binding it to `=` makes
            // MySQL cast "12,4,31" to 12, so only the first province would match.
            $mappedProvinceIds = array_values(array_filter(array_map('intval', explode(',', (string) $_SESSION['mappedProvinces']))));
        }

        return MemoUtility::remember(function () use ($geoId, $parent, $api, $onlyActive, $facilityMap, $updatedDateTime, $orderByStatus, $mappedProvinceIds) {

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
            if (!empty($mappedProvinceIds)) {
                $this->db->where('geo_id', $mappedProvinceIds, 'IN');
            }

            if ($updatedDateTime) {
                $this->db->where("updated_datetime >= " . $this->db->quote($updatedDateTime));
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

    // Fetch a single geolocation row by id, regardless of parent or status.
    // Useful when a saved district/province must be displayed even if its
    // hierarchy link (geo_parent) is broken or it is inactive.
    public function getGeoLocationById($geoId): ?array
    {
        if ($this->db == null || empty($geoId)) {
            return null;
        }
        $row = $this->db->rawQueryOne(
            "SELECT geo_id, geo_name, geo_parent, geo_status FROM geographical_divisions WHERE geo_id = ?",
            [$geoId]
        );
        return $row ?: null;
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

    /**
     * The id of the province ($parent = 0) or district ($parent = province id)
     * with this name, adding it when there is none.
     *
     * The match is on name AND parent, like the (geo_name, geo_parent) unique key.
     * Matching on the name alone used to pick up a same-named district of another
     * province (or a district when a province was meant) and rewrite its parent,
     * moving it and every facility in it to the wrong province.
     */
    public function addGeoLocation($geoName, $parent = 0)
    {
        $geoName = trim(preg_replace('/\s+/u', ' ', (string) $geoName) ?? (string) $geoName);
        $parent = (is_numeric($parent) && (int) $parent > 0) ? (int) $parent : 0;
        if ($geoName === '') {
            return null;
        }

        $geo = $this->db->rawQueryOne(
            "SELECT geo_id, geo_status FROM geographical_divisions WHERE TRIM(geo_name) = ? AND geo_parent = ? LIMIT 1",
            [$geoName, (string) $parent]
        );
        $now = DateUtility::getCurrentDateTime();

        if (!empty($geo)) {
            // Someone chose "Other" and typed a name that is already listed: reuse it.
            if ($geo['geo_status'] !== 'active') {
                $this->db->where('geo_id', $geo['geo_id']);
                $this->db->update('geographical_divisions', ['geo_status' => 'active', 'updated_datetime' => $now, 'data_sync' => 0]);
            }
            return $geo['geo_id'];
        }

        $this->db->insert('geographical_divisions', [
            'geo_name'         => $geoName,
            'geo_parent'       => (string) $parent,
            'geo_status'       => 'active',
            'created_by'       => $_SESSION['userId'] ?? null,
            'created_on'       => $now,
            'updated_datetime' => $now,
            'data_sync'        => 0,
        ]);
        return $this->db->getInsertId();
    }

    /**
     * Resolve the province and district a facility form posted into ids and the
     * names that go with them.
     *
     * Each one is either an id from the dropdown, "other" with a typed new name,
     * or (when no provinces exist yet) just a typed name. The stored names are
     * always taken from geographical_divisions, never from the dropdown's text,
     * so the name and the id cannot disagree. Without an id, $fallbackState and
     * $fallbackDistrict (a legacy facility's free-text values) are kept.
     *
     * @return array{stateId: ?int, state: ?string, districtId: ?int, district: ?string, error: ?string}
     */
    public function resolveFacilityLocation($stateId, ?string $provinceNew, $districtId, ?string $districtNew, ?string $fallbackState = null, ?string $fallbackDistrict = null): array
    {
        $out = ['stateId' => null, 'state' => null, 'districtId' => null, 'district' => null, 'error' => null];
        $provinceNew = trim((string) $provinceNew);
        $districtNew = trim((string) $districtNew);

        if ($stateId === 'other' || (empty($stateId) && $provinceNew !== '')) {
            if ($provinceNew === '') {
                $out['error'] = _translate('Please enter the new Province/State.');
                return $out;
            }
            $stateId = $this->addGeoLocation($provinceNew);
        }
        $province = is_numeric($stateId) ? $this->getGeoLocationById($stateId) : null;
        if ($province !== null) {
            $out['stateId'] = (int) $province['geo_id'];
            $out['state'] = trim((string) $province['geo_name']);
        } else {
            $out['state'] = ($fallbackState !== null && trim($fallbackState) !== '') ? trim($fallbackState) : null;
        }

        if ($districtId === 'other' || (empty($districtId) && $districtNew !== '')) {
            if ($districtNew === '') {
                $out['error'] = _translate('Please enter the new District/County.');
                return $out;
            }
            if ($out['stateId'] === null) {
                $out['error'] = _translate('Please choose the Province/State before adding a new District/County.');
                return $out;
            }
            $districtId = $this->addGeoLocation($districtNew, $out['stateId']);
        }
        $district = is_numeric($districtId) ? $this->getGeoLocationById($districtId) : null;
        if ($district !== null) {
            if ($out['stateId'] !== null && (int) $district['geo_parent'] !== $out['stateId']) {
                $out['error'] = _translate('The selected District/County does not belong to the selected Province/State.');
                return $out;
            }
            $out['districtId'] = (int) $district['geo_id'];
            $out['district'] = trim((string) $district['geo_name']);
        } else {
            $out['district'] = ($fallbackDistrict !== null && trim($fallbackDistrict) !== '') ? trim($fallbackDistrict) : null;
        }

        return $out;
    }

    /**
     * The geographical divisions with this exact name. The name usually comes
     * from a stored facility or sample row, so it is bound: a stored name with an
     * apostrophe used to break the edit forms that looked it up.
     *
     * @return list<array<string, mixed>>
     */
    public function findByName(?string $geoName): array
    {
        return $this->db->rawQuery('SELECT * FROM geographical_divisions WHERE geo_name = ?', [(string) $geoName]);
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
            $districtSql = "SELECT geo_id, geo_name from geographical_divisions WHERE geo_parent IN (" . $this->db->inIntList($provinceId) . ") AND geo_status='active' ORDER BY geo_name";
            $response['districts'] = $this->db->rawQuery($districtSql);
        }

        if ($facilities === true) {
            $facilitySql = "SELECT facility_id, facility_name, facility_code from facility_details WHERE facility_state_id IN (" . $this->db->inIntList($provinceId) . ") AND status='active' ORDER BY facility_name";
            $response['facilities'] = $this->db->rawQuery($facilitySql);
        }

        if ($labs === true) {
            $facilitySql = "SELECT facility_id, facility_name from facility_details WHERE facility_type = 2  AND facility_state_id IN (" . $this->db->inIntList($provinceId) . ") AND status='active' ORDER BY facility_name";
            $response['labs'] = $this->db->rawQuery($facilitySql);
        }

        return $response;
    }

    public function getByDistrictId($districtId, $facilities = true, $labs = false): array
    {

        $response = [];

        if ($facilities === true) {
            $facilitySql = "SELECT facility_id, facility_name, facility_code from facility_details WHERE facility_district_id = " . (int) $districtId . " AND status='active' ORDER BY facility_name";
            $response['facilities'] = $this->db->rawQuery($facilitySql);
        }

        if ($labs === true) {
            $labSql = "SELECT facility_id, facility_name from facility_details WHERE facility_type = 2  AND facility_district_id = " . (int) $districtId . " AND status='active' ORDER BY facility_name";
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
