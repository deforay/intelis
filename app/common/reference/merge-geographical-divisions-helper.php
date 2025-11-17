<?php

use Laminas\Diactoros\ServerRequest;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if ($general->isSTSInstance() === false) {
    $_SESSION['alertMsg'] = _translate("Merging geographical divisions is not allowed on this instance.");
    header("Location: geographical-divisions-details.php");
    exit;
}

try {
    $transactionStarted = false;
    $mergeType = $_POST['mergeType'] ?? '';
    $currentDateTime = DateUtility::getCurrentDateTime();

    if ($mergeType === 'province') {
        $selectedProvinces = array_unique(array_filter(array_map('intval', $_POST['selectedProvinces'] ?? [])));
        $primaryProvince = (int) ($_POST['primaryProvince'] ?? 0);

        if (count($selectedProvinces) < 2 || empty($primaryProvince)) {
            $_SESSION['alertMsg'] = _translate("Please select at least two provinces and choose the primary province.");
            header("Location: merge-geographical-divisions.php");
            exit;
        }

        if (!in_array($primaryProvince, $selectedProvinces, true)) {
            $_SESSION['alertMsg'] = _translate("Primary province must be included in the selected list.");
            header("Location: merge-geographical-divisions.php");
            exit;
        }

        $primaryProvinceInfo = $db->rawQueryOne("SELECT geo_id, geo_name FROM geographical_divisions WHERE geo_id = ?", [$primaryProvince]);
        if (empty($primaryProvinceInfo)) {
            $_SESSION['alertMsg'] = _translate("Primary province not found.");
            header("Location: merge-geographical-divisions.php");
            exit;
        }

        $otherProvinceIds = array_values(array_diff($selectedProvinces, [$primaryProvince]));

        if ($otherProvinceIds === []) {
            $_SESSION['alertMsg'] = _translate("Please select at least one additional province to merge.");
            header("Location: merge-geographical-divisions.php");
            exit;
        }

        $db->beginTransaction();
        $transactionStarted = true;

        $existingTargetDistricts = $db->rawQuery(
            "SELECT geo_id, LOWER(TRIM(geo_name)) AS name_key
                FROM geographical_divisions
                WHERE geo_parent = ?
                ORDER BY geo_name",
            [$primaryProvince]
        );
        $targetDistrictMap = [];
        foreach ($existingTargetDistricts as $row) {
            $targetDistrictMap[$row['name_key']] = (int) $row['geo_id'];
        }

        foreach ($otherProvinceIds as $otherProvinceId) {
            $otherDistricts = $db->rawQuery(
                "SELECT geo_id, geo_name, geo_status
                    FROM geographical_divisions
                    WHERE geo_parent = ?",
                [$otherProvinceId]
            );

            foreach ($otherDistricts as $district) {
                $nameKey = strtolower(trim((string) ($district['geo_name'] ?? '')));
                if (isset($targetDistrictMap[$nameKey])) {
                    $targetDistrictId = $targetDistrictMap[$nameKey];

                    $db->where('facility_district_id', (int) $district['geo_id']);
                    $db->update('facility_details', [
                        'facility_district_id' => $targetDistrictId,
                        'facility_district' => $district['geo_name'],
                        'facility_state_id' => $primaryProvince,
                        'facility_state' => $primaryProvinceInfo['geo_name'],
                        'updated_datetime' => $currentDateTime
                    ]);

                    $db->where('geo_id', (int) $district['geo_id']);
                    $db->update('geographical_divisions', [
                        'geo_status' => 'inactive',
                        'updated_datetime' => $currentDateTime
                    ]);
                } else {
                    $db->where('geo_id', (int) $district['geo_id']);
                    $db->update('geographical_divisions', [
                        'geo_parent' => $primaryProvince,
                        'updated_datetime' => $currentDateTime
                    ]);
                    $targetDistrictMap[$nameKey] = (int) $district['geo_id'];
                }
            }
        }

        $db->where('facility_state_id', $otherProvinceIds, 'IN');
        $db->update('facility_details', [
            'facility_state_id' => $primaryProvince,
            'facility_state' => $primaryProvinceInfo['geo_name'],
            'updated_datetime' => $currentDateTime
        ]);

        $db->where('geo_id', $otherProvinceIds, 'IN');
        $db->update('geographical_divisions', [
            'geo_status' => 'inactive',
            'updated_datetime' => $currentDateTime
        ]);

        $db->where('geo_id', $primaryProvince);
        $db->update('geographical_divisions', [
            'geo_status' => 'active',
            'updated_datetime' => $currentDateTime
        ]);

        $db->commitTransaction();
        $transactionStarted = false;

        $_SESSION['alertMsg'] = _translate("Selected provinces merged successfully.");
        $general->activityLog('merge-provinces', ($_SESSION['userName'] ?? '') . ' merged provinces into ' . $primaryProvinceInfo['geo_name'], 'geographical-divisions');
    } elseif ($mergeType === 'district') {
        $selectedDistricts = array_unique(array_filter(array_map('intval', $_POST['selectedDistricts'] ?? [])));
        $primaryDistrict = (int) ($_POST['primaryDistrict'] ?? 0);

        if (count($selectedDistricts) < 2 || empty($primaryDistrict)) {
            $_SESSION['alertMsg'] = _translate("Please select at least two districts and choose the primary district.");
            header("Location: merge-geographical-divisions.php");
            exit;
        }

        if (!in_array($primaryDistrict, $selectedDistricts, true)) {
            $_SESSION['alertMsg'] = _translate("Primary district must be included in the selected list.");
            header("Location: merge-geographical-divisions.php");
            exit;
        }

        $primaryDistrictInfo = $db->rawQueryOne(
            "SELECT d.geo_id,
                    d.geo_name,
                    CAST(NULLIF(d.geo_parent, '') AS UNSIGNED) AS province_id,
                    p.geo_name AS province_name
                FROM geographical_divisions d
                LEFT JOIN geographical_divisions p ON p.geo_id = CAST(NULLIF(d.geo_parent, '') AS UNSIGNED)
                WHERE d.geo_id = ?",
            [$primaryDistrict]
        );
        if (empty($primaryDistrictInfo) || empty($primaryDistrictInfo['province_id'])) {
            $_SESSION['alertMsg'] = _translate("Primary district not found or has no parent province.");
            header("Location: merge-geographical-divisions.php");
            exit;
        }

        $otherDistrictIds = array_values(array_diff($selectedDistricts, [$primaryDistrict]));
        if ($otherDistrictIds === []) {
            $_SESSION['alertMsg'] = _translate("Please select at least one additional district to merge.");
            header("Location: merge-geographical-divisions.php");
            exit;
        }

        $db->beginTransaction();
        $transactionStarted = true;

        $db->where('facility_district_id', $otherDistrictIds, 'IN');
        $db->update('facility_details', [
            'facility_district_id' => $primaryDistrict,
            'facility_district' => $primaryDistrictInfo['geo_name'],
            'facility_state_id' => $primaryDistrictInfo['province_id'],
            'facility_state' => $primaryDistrictInfo['province_name'],
            'updated_datetime' => $currentDateTime
        ]);

        $db->where('geo_id', $otherDistrictIds, 'IN');
        $db->update('geographical_divisions', [
            'geo_status' => 'inactive',
            'updated_datetime' => $currentDateTime
        ]);

        $db->where('geo_id', $primaryDistrict);
        $db->update('geographical_divisions', [
            'geo_status' => 'active',
            'updated_datetime' => $currentDateTime
        ]);

        $db->commitTransaction();
        $transactionStarted = false;

        $_SESSION['alertMsg'] = _translate("Selected districts merged successfully.");
        $general->activityLog('merge-districts', ($_SESSION['userName'] ?? '') . ' merged districts into ' . $primaryDistrictInfo['geo_name'], 'geographical-divisions');
    } else {
        $_SESSION['alertMsg'] = _translate("Invalid merge request.");
    }

    header("Location: geographical-divisions-details.php");
} catch (Throwable $e) {
    if (!empty($transactionStarted)) {
        $db->rollbackTransaction();
    }
    LoggerUtility::log("error", $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    $_SESSION['alertMsg'] = _translate("An error occurred while merging divisions. Please try again.");
    header("Location: merge-geographical-divisions.php");
}
