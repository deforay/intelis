<?php

use App\Services\CD4Service;
use App\Services\UsersService;
use App\Services\FacilitiesService;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;


$title = "CD4 | Add New Request";

require_once APPLICATION_PATH . '/header.php';

$labFieldDisabled = '';

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var Cd4Service $cd4Service */
$cd4Service = ContainerRegistry::get(CD4Service::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$formId = (int) $general->getGlobalConfig('vl_form');


$healthFacilities = $facilitiesService->getHealthFacilities('cd4');
$testingLabs = $facilitiesService->getTestingLabs('cd4');

$healthFacilitiesAllColumns = $facilitiesService->getHealthFacilities('cd4', false, true);


// get instruments
$condition = "status = 'active'";
$importResult = $general->fetchDataFromTable('instruments', $condition);

$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$userInfo = [];
foreach ($userResult as $user) {
    $userInfo[$user['user_id']] = ($user['user_name']);
}

//sample rejection reason
$condition = "rejection_reason_status ='active'";
$rejectionResult = $general->fetchDataFromTable('r_cd4_sample_rejection_reasons', $condition);

//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_cd4_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//get active sample types
$condition = "status = 'active'";
$sResult = $general->fetchDataFromTable('r_cd4_sample_types', $condition);


//get cd4test reason details
$testReason = $general->fetchDataFromTable('r_cd4_test_reasons');
$pdResult = $general->fetchDataFromTable('geographical_divisions', "geo_parent = 0 AND geo_status='active'");

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$artRegimenQuery = "SELECT DISTINCT headings FROM r_vl_art_regimen";
$artRegimenResult = $db->rawQuery($artRegimenQuery);
$aQuery = "SELECT * FROM r_vl_art_regimen where art_status ='active'";
$aResult = $db->query($aQuery);

$minPatientIdLength = 0;
if (isset($arr['cd4_min_patient_id_length']) && $arr['cd4_min_patient_id_length'] != "") {
    $minPatientIdLength = $arr['cd4_min_patient_id_length'];
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
    COUNTRY\RWANDA => 'forms/add-rwanda.php'
];

require_once($fileArray[$formId]);

?>

<script type="text/javascript" src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/datalist-css.min.js") ?>"></script>

<?php
// Common JS functions in a PHP file
// Why PHP? Because we can use PHP variables in the JS code
require_once APPLICATION_PATH . "/vl/vl.js.php";
?>
<script>
    function insertSampleCode(formId) {
        $.blockUI();
        let formData = $("#" + formId).serialize();


        $.post("/cd4/requests/insert-sample.php", formData,
            function(data) {
                if (data > 0) {
                    $.unblockUI();
                    document.getElementById("cd4SampleId").value = data;
                    document.getElementById(formId).submit();
                } else {
                    $.unblockUI();
                    generateSampleCode();
                    alert("<?= _translate("Could not save this form. Please try again."); ?>");
                }
            });
    }
</script>
<?php include APPLICATION_PATH . '/footer.php';
