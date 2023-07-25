<?php

use App\Services\VlService;
use App\Services\UsersService;
use App\Services\FacilitiesService;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;


$title = "VL | Add New Request";

require_once APPLICATION_PATH . '/header.php';

$labFieldDisabled = '';

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var CommonService $commonService */
$general = ContainerRegistry::get(CommonService::class);


$healthFacilities = $facilitiesService->getHealthFacilities('vl');
$testingLabs = $facilitiesService->getTestingLabs('vl');

//get import config
$condition = "status = 'active'";
$importResult = $general->fetchDataFromTable('instruments', $condition);

$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$reasonForFailure = $vlService->getReasonForFailure();
$userInfo = [];
foreach ($userResult as $user) {
    $userInfo[$user['user_id']] = ($user['user_name']);
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


//get vltest reason details
$testReason = $general->fetchDataFromTable('r_vl_test_reasons');
$pdResult = $general->fetchDataFromTable('geographical_divisions', "geo_parent = 0 AND geo_status='active'");
//get suspected treatment failure at
$suspectedTreatmentFailureAtQuery = "SELECT DISTINCT vl_sample_suspected_treatment_failure_at FROM form_vl where vlsm_country_id='" . $arr['vl_form'] . "'";
$suspectedTreatmentFailureAtResult = $db->rawQuery($suspectedTreatmentFailureAtQuery);

//get vl test reason list
$vlTestReasonResult = $vlService->getVlReasonsForTesting();

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

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
$fileArray = array(
    1 => 'forms/add-southsudan.php',
    2 => 'forms/add-sierraleone.php',
    3 => 'forms/add-drc.php',
    4 => 'forms/add-cameroon.php',
    5 => 'forms/add-png.php',
    6 => 'forms/add-who.php',
    7 => 'forms/add-rwanda.php'
);

require($fileArray[$arr['vl_form']]);

?>

<script type="text/javascript" src="/assets/js/datalist-css.min.js"></script>

<?php
// Common JS functions in a PHP file
// Why PHP? Because we can use PHP variables in the JS code
require_once APPLICATION_PATH . "/vl/vl.js.php";
?>
<script>
    function insertSampleCode(formId, vlSampleId, sampleCode, sampleCodeKey, sampleCodeFormat, countryId, sampleCollectionDate, provinceCode = null, provinceId = null) {
        $.blockUI();
        $.post("/vl/requests/insert-sample.php", {
                sampleCode: $("#" + sampleCode).val(),
                sampleCodeKey: $("#" + sampleCodeKey).val(),
                sampleCodeFormat: $("#" + sampleCodeFormat).val(),
                countryId: countryId,
                sampleCollectionDate: $("#" + sampleCollectionDate).val(),
                provinceCode: provinceCode,
                provinceId: provinceId
            },
            function(data) {
                console.log(data);
                if (data > 0) {
                    $.unblockUI();
                    document.getElementById("vlSampleId").value = data;
                    document.getElementById(formId).submit();
                } else {
                    $.unblockUI();
                    //$("#sampleCollectionDate").val('');
                    generateSampleCode();
                    alert("<?= _("Could not save this form. Please try again."); ?>");
                }
            });
    }
</script>
<?php include APPLICATION_PATH . '/footer.php';
