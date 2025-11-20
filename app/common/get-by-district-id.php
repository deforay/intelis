<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;


/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$list = [];
try {
    if (isset($_POST['districtId'])) {
        $districtId = $_POST['districtId'];
        $result = $geolocationService->getByDistrictId($districtId, true, true);

        //Get Facilities by district
        if (isset($_POST['facilities'])) {
            $facilityList = $result['facilities'];
            $option = "<option value=''>--Select--</option>";
            if (isset($_POST['facilityCode'])) {
                foreach ($facilityList as $facility) {
                    $option .= '<option value="' . $facility['facility_id'] . '">' . $facility['facility_name'] . ' - ' . $facility['facility_code'] . ' </option>';
                }
            } else {
                foreach ($facilityList as $facility) {
                    $option .= '<option value="' . $facility['facility_id'] . '">' . $facility['facility_name'] . '</option>';
                }
            }
            $list['facilities'] = $option;
        }

        //Get Labs by district
        if (isset($_POST['labs'])) {
            $labList = $result['labs'];
            $option = "<option value=''>" . _translate("--Select--") . "</option>";
            foreach ($labList as $lab) {
                $option .= '<option value="' . $lab['facility_id'] . '">' . $lab['facility_name'] . ' </option>';
            }
            $list['labs'] = $option;
        }
        echo JsonUtility::encodeUtf8Json($list);
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
}
