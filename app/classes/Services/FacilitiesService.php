<?php

namespace App\Services;

use App\Utilities\DateUtility;
use App\Utilities\MemoUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

final class FacilitiesService
{
    private array $facilityTypeTableList = [
        1 => "health_facilities",
        2 => "testing_labs",
        3 => "health_facilities",
    ];

    protected string $table = 'facility_details';

    public function __construct(protected DatabaseService $db)
    {
    }

    public function getAllFacilities($facilityType = null, $onlyActive = true): mixed
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

    public function getFacilitiesForResultUpload($testType): mixed
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

    public function getFacilityName($facilityId)
    {
        if (!empty($facilityId)) {
            $this->db->where("facility_id", $facilityId);
        }
        return $this->db->getValue("facility_details", "facility_name");
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

    /**
     * Build a short, human-readable facility code from a facility name and make
     * it unique against existing facility_details.facility_code values
     * (the column carries a UNIQUE index, so callers must persist what this returns).
     *
     * The result is strictly uppercase Latin letters (A-Z) -- no digits, accents or
     * symbols -- so on STS the "-<code>" sample-code postfix never blurs into the
     * trailing numeric sequence. Heuristics, in order:
     *   1. Single word      -> first 3 letters                 ("Kinshasa" -> KIN)
     *   2. Multi-word       -> initial of each word, but short
     *      all-caps tokens are kept whole as acronyms          ("CH Monkole" -> CHM,
     *                                                            "National Reference Lab" -> NRL)
     *   3. Still under 3    -> padded from the first word       ("Saint Mary" -> SMA)
     *   4. Nothing usable   -> letters-only hash of the name    (non-latin / empty names)
     *
     * Capped at $maxLen (default 4). On collision a letter-only suffix is appended
     * (A..Z, then AA..ZZ) while keeping the total within $maxLen ("NRL" -> "NRLA").
     *
     * @param int|null $excludeFacilityId Skip this facility when checking uniqueness (for edits).
     */
    public function generateFacilityCode(string $facilityName, ?int $excludeFacilityId = null, int $maxLen = 4): string
    {
        $base = $this->buildFacilityCodeCandidate($facilityName, $maxLen);
        if ($base === '') {
            // Last-ditch: derive letters-only from a hash of the name (no digits/symbols).
            $base = substr(strtr(md5($facilityName), '0123456789abcdef', 'ABCDEFGHIJKLMNOP'), 0, $maxLen);
        }
        return $this->ensureUniqueFacilityCode($base, $excludeFacilityId, $maxLen);
    }

    /**
     * Normalise a user-entered or imported facility code to plain uppercase Latin
     * letters (A-Z). Accents are folded (É -> E), and anything that isn't a letter
     * (digits, spaces, punctuation) is dropped. Returns '' when nothing usable remains.
     */
    public function sanitizeFacilityCode(?string $code, int $maxLen = 32): string
    {
        $code = $this->foldLatinAccents((string) $code);
        $code = preg_replace('/[^A-Za-z]/', '', $code) ?? '';
        return substr(strtoupper($code), 0, $maxLen);
    }

    /**
     * Fold common Latin accents to ASCII so francophone/lusophone names (Hôpital,
     * Général, São) tokenise on word boundaries instead of on the accented letter.
     */
    private function foldLatinAccents(string $text): string
    {
        return strtr($text, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ]);
    }

    private function buildFacilityCodeCandidate(string $name, int $maxLen): string
    {
        $name = $this->foldLatinAccents(trim($name));
        if ($name === '') {
            return '';
        }

        // Split on anything that isn't a Latin letter (digits/symbols are not allowed in codes).
        $words = preg_split('/[^A-Za-z]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Drop common filler words, but never empty the list entirely.
        $stopWords = ['of', 'the', 'and', 'for', 'de', 'des', 'du', 'la', 'le', 'les'];
        $words = array_values(array_filter(
            $words,
            static fn($w) => !in_array(strtolower($w), $stopWords, true)
        )) ?: $words;

        if ($words === []) {
            return '';
        }

        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, 3));
        }

        $code = '';
        foreach ($words as $word) {
            // Keep short all-caps tokens whole (e.g. "CH", "NRL"); otherwise take the initial.
            $code .= (preg_match('/^[A-Z]{2,4}$/', $word) === 1) ? $word : substr($word, 0, 1);
            if (strlen($code) >= $maxLen) {
                break;
            }
        }
        $code = strtoupper($code);

        // Pad from the first word if the initials came out too short ("Saint Mary" -> "SM" -> "SMA").
        if (strlen($code) < 3) {
            $code = strtoupper(substr($code . substr($words[0], 1), 0, 3));
        }

        return substr($code, 0, $maxLen);
    }

    private function ensureUniqueFacilityCode(string $base, ?int $excludeFacilityId, int $maxLen): string
    {
        $base = substr($base, 0, $maxLen);
        if ($base !== '' && !$this->facilityCodeExists($base, $excludeFacilityId)) {
            return $base;
        }

        // On collision append a letter-only suffix (A..Z, then AA..ZZ) so the code stays
        // purely alphabetic, trimming the base so the whole code stays within $maxLen.
        $letters = range('A', 'Z');
        foreach ($letters as $l1) {
            $candidate = substr($base, 0, max(1, $maxLen - 1)) . $l1;
            if (!$this->facilityCodeExists($candidate, $excludeFacilityId)) {
                return $candidate;
            }
        }
        foreach ($letters as $l1) {
            foreach ($letters as $l2) {
                $candidate = substr($base, 0, max(1, $maxLen - 2)) . $l1 . $l2;
                if (!$this->facilityCodeExists($candidate, $excludeFacilityId)) {
                    return $candidate;
                }
            }
        }

        // Extremely unlikely safety net (letters-only hash of the base).
        return substr(strtr(md5($base . microtime()), '0123456789abcdef', 'ABCDEFGHIJKLMNOP'), 0, $maxLen);
    }

    private function facilityCodeExists(string $code, ?int $excludeFacilityId): bool
    {
        $sql = "SELECT 1 FROM facility_details WHERE facility_code = ?";
        $params = [$code];
        if (!empty($excludeFacilityId)) {
            $sql .= " AND facility_id != ?";
            $params[] = $excludeFacilityId;
        }
        $sql .= " LIMIT 1";
        return !empty($this->db->rawQueryOne($sql, $params));
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

    public function getUserFacilityMap($userId): mixed
    {
        if (empty($userId)) {
            return null;
        }

        // Self lookups can reuse the session-cached scope map.
        $isSelf = session_status() !== PHP_SESSION_NONE && $userId == ($_SESSION['userId'] ?? null);
        if ($isSelf && isset($_SESSION['facilityMap'])) {
            return $_SESSION['facilityMap'];
        }

        // Explicit, deterministic cache key so the mapping can be invalidated
        // by clearUserFacilityMapCache() the moment it is edited (otherwise a
        // saved change appears "not persisted" until the cache TTL lapses).
        $userfacilityMap = MemoUtility::memo(
            self::userFacilityMapCacheKey($userId),
            function () use ($userId) {
                $this->db->where("user_id", $userId);
                $response = $this->db->getValue("user_facility_map", "facility_id", null);
                if ($this->db->count > 0) {
                    // Normalize to a clean CSV of positive integer facility ids at the
                    // source so every consumer (login session, grid + manifest lab
                    // scoping) can interpolate it safely without re-sanitizing.
                    $facilityIds = array_values(array_filter(array_map('intval', (array) $response)));
                    return $facilityIds !== [] ? implode(",", $facilityIds) : null;
                }
                return null;
            }
        );

        if ($isSelf) {
            $_SESSION['facilityMap'] = $userfacilityMap;
        }

        return $userfacilityMap;
    }

    private static function userFacilityMapCacheKey($userId): string
    {
        return 'user_facility_map_' . hash('sha256', (string) $userId);
    }

    /**
     * Invalidate every cache layer for a user's facility map after it is
     * edited, so the next read reflects the DB immediately. Covers the
     * cross-request file cache, the in-request memo, and (for self-edits)
     * the session-cached scope map used for data isolation.
     */
    public function clearUserFacilityMapCache($userId): void
    {
        if (empty($userId)) {
            return;
        }

        MemoUtility::forget(self::userFacilityMapCacheKey($userId));

        if (session_status() !== PHP_SESSION_NONE && $userId == ($_SESSION['userId'] ?? null)) {
            unset($_SESSION['facilityMap']);
            // Re-derive fresh so lab-scoping that reads the session copy stays correct.
            $this->getUserFacilityMap($userId);
        }
    }


    public function getTestingLabFacilityMap($labId)
    {
        if (empty($labId)) {
            return null;
        }

        return MemoUtility::remember(function () use ($labId): string|array|null|int|float|false {
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
        $userId ??= null;
        if (isset($_SESSION['userId']) && $userId === null) {
            $userId ??= $_SESSION['userId'];
        }
        if (!$byPassFacilityMap && !empty($userId)) {
            $facilityMap = $this->getUserFacilityMap($userId);
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
            $condition = is_array($condition) ? $condition : [$condition];
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


    public function updateFacilitySyncTime($facilityIds, $currentDateTime = null): void
    {
        $currentDateTime ??= DateUtility::getCurrentDateTime();
        if (!empty($facilityIds)) {
            $facilityIds = array_unique(array_filter($facilityIds));
            $sql = 'UPDATE facility_details
                        SET facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.remoteRequestsSync", ?, "$.vlRemoteRequestsSync", ?)
                        WHERE facility_id IN (' . implode(",", $facilityIds) . ')';
            $this->db->rawQuery($sql, [$currentDateTime, $currentDateTime]);
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
        $userId ??= null;
        if (isset($_SESSION['userId']) && $userId === null) {
            $userId ??= $_SESSION['userId'];
        }
        if (!$byPassFacilityMap && !empty($userId)) {
            $facilityMap = $this->getUserFacilityMap($userId);
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
            $condition = is_array($condition) ? $condition : [$condition];
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

    public function getOrCreateProvince(string $provinceName, ?string $provinceCode = null): int
    {
        return MemoUtility::remember(function () use ($provinceName, $provinceCode) {
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
                $data = ['geo_name' => $provinceName, 'geo_status' => 'active', 'updated_datetime' => DateUtility::getCurrentDateTime()];
                $this->db->insert('geographical_divisions', $data);

                /** @var CommonService $general */
                $general = ContainerRegistry::get(CommonService::class);
                $general->activityLog('add-province', ($_SESSION['userName'] ?? '') . " added a new province $provinceName", 'geographical-divisions');

                return $this->db->getInsertId();
            }
        });
    }

    public function getOrCreateDistrict(?string $districtName, ?string $districtCode = null, ?int $provinceId = null): int
    {
        return MemoUtility::remember(function () use ($districtName, $districtCode, $provinceId) {
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
                $data = ['geo_name' => $districtName, 'geo_parent' => $provinceId, 'geo_status' => 'active', 'updated_datetime' => DateUtility::getCurrentDateTime()];
                $this->db->insert('geographical_divisions', $data);

                /** @var CommonService $general */
                $general = ContainerRegistry::get(CommonService::class);
                $general->activityLog('add-district', ($_SESSION['userName'] ?? '') . " added a new district $districtName", 'geographical-divisions');

                return $this->db->getInsertId();
            }
        });
    }

    public function getFacilitiesDropdown($testType, $facilityType, $provinceName = null, $districtRequested = null, $option = null, $comingFromUser = null): string
    {
        return MemoUtility::remember(function () use ($testType, $facilityType, $provinceName, $districtRequested, $option, $comingFromUser) {
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
        });
    }
}
