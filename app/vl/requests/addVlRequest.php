<?php

use App\Services\VlService;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$title = _translate("VL | Add New Request");

require_once APPLICATION_PATH . '/header.php';

$labFieldDisabled = '';

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$formId = (int) $general->getGlobalConfig('vl_form');


$healthFacilities = $facilitiesService->getHealthFacilities('vl');
$testingLabs = $facilitiesService->getTestingLabs('vl');

$healthFacilitiesAllColumns = $facilitiesService->getHealthFacilities('vl', false, true);

$storageList = $vlService->getLabStorage();
// get instruments
$condition = "status = 'active'";
$importResult = $general->fetchDataFromTable('instruments', $condition);

$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$reasonForFailure = $vlService->getReasonForFailure();
$userInfo = [];
foreach ($userResult as $user) {
    $userInfo[$user['user_id']] = $user['user_name'];
}

//sample rejection reason
$condition = "rejection_reason_status ='active'";
$rejectionResult = $general->fetchDataFromTable('r_vl_sample_rejection_reasons', $condition);

//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_vl_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//get active sample types
$condition = "status = 'active'";
$sResult = $general->fetchDataFromTable('r_vl_sample_type', $condition);

//Recommended corrective actions
$condition = "status ='active' AND test_type='vl'";
$correctiveActions = $general->fetchDataFromTable('r_recommended_corrective_actions', $condition);

//get vltest reason details
$testReason = $general->fetchDataFromTable('r_vl_test_reasons');
$pdResult = $general->fetchDataFromTable('geographical_divisions', "geo_parent = 0 AND geo_status='active'");

//get vl test reason list
$vlTestReasonResult = $vlService->getVlReasonsForTesting();

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$artRegimenQuery = "SELECT DISTINCT headings FROM r_vl_art_regimen";
$artRegimenResult = $db->rawQuery($artRegimenQuery);
$aQuery = "SELECT * FROM r_vl_art_regimen where art_status ='active'";
$aResult = $db->query($aQuery);

$minPatientIdLength = 0;
if (isset($arr['vl_min_patient_id_length']) && $arr['vl_min_patient_id_length'] != "") {
    $minPatientIdLength = $arr['vl_min_patient_id_length'];
}
?>
<style>
    .ui_tpicker_second_label {
        display: none !important;
    }

    .ui_tpicker_second_slider {
        display: none !important;
    }

    .ui_tpicker_millisec_label {
        display: none !important;
    }

    .ui_tpicker_millisec_slider {
        display: none !important;
    }

    .ui_tpicker_microsec_label {
        display: none !important;
    }

    .ui_tpicker_microsec_slider {
        display: none !important;
    }

    .ui_tpicker_timezone_label {
        display: none !important;
    }

    .ui_tpicker_timezone {
        display: none !important;
    }

    .ui_tpicker_time_input {
        width: 100%;
    }
</style>
<?php

$fileArray = [
    COUNTRY\SOUTH_SUDAN => 'forms/add-southsudan.php',
    COUNTRY\SIERRA_LEONE => 'forms/add-sierraleone.php',
    COUNTRY\DRC => 'forms/add-drc.php',
    COUNTRY\CAMEROON => 'forms/add-cameroon.php',
    COUNTRY\PNG => 'forms/add-png.php',
    COUNTRY\WHO => 'forms/add-who.php',
    COUNTRY\RWANDA => 'forms/add-rwanda.php',
    COUNTRY\BURKINA_FASO => 'forms/add-burkina-faso.php'
];

require_once($fileArray[$formId]);

?>

<script type="text/javascript"
    src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/datalist-css.min.js") ?>"></script>

<?php
// Common JS functions in a PHP file
// Why PHP? Because we can use PHP variables in the JS code
require_once WEB_ROOT . "/assets/js/test-specific/vl.js.php";
?>
<script>
    function insertSampleCode(formId, vlSampleId = null, sampleCode = null, sampleCodeKey = null, sampleCodeFormat = null, countryId = null, sampleCollectionDate = null, provinceCode = null, provinceId = null) {
        $.blockUI();
        let formData = $("#" + formId).serialize();
        if (provinceCode != null && provinceCode != '') {
            formData += "&provinceCode=" + encodeURIComponent(provinceCode);
        }
        formData += "&provinceId=" + encodeURIComponent(provinceId);
        formData += "&countryId=" + encodeURIComponent(countryId);

        $.post("/vl/requests/insert-sample.php", formData,
            function (data) {
                if (data > 0) {
                    $.unblockUI();
                    document.getElementById("vlSampleId").value = data;
                    document.getElementById(formId).submit();
                } else {
                    $.unblockUI();
                    generateSampleCode();
                    alert("<?= _translate("Could not save this form. Please try again."); ?>");
                }
            });
    }
</script>
<?php

require_once APPLICATION_PATH . '/footer.php';
