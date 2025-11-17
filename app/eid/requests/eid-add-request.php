<?php

use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = "EID | Add New Request";

require_once APPLICATION_PATH . '/header.php';
?>
<style>
    .ui_tpicker_second_label,
    .ui_tpicker_second_slider,
    .ui_tpicker_millisec_label,
    .ui_tpicker_millisec_slider,
    .ui_tpicker_microsec_label,
    .ui_tpicker_microsec_slider,
    .ui_tpicker_timezone_label,
    .ui_tpicker_timezone {
        display: none !important;
    }

    .ui_tpicker_time_input {
        width: 100%;
    }
</style>

<?php

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$healthFacilities = $facilitiesService->getHealthFacilities('eid');

$healthFacilitiesAllColumns = $facilitiesService->getHealthFacilities('eid', false, true);

$testingLabs = $facilitiesService->getTestingLabs('eid');
$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$userInfo = [];
foreach ($userResult as $user) {
    $userInfo[$user['user_id']] = ($user['user_name']);
}

$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_eid_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//sample rejection reason
$rejectionReason = "";
$rejectionQuery = "SELECT * FROM r_eid_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);


foreach ($rejectionTypeResult as $type) {
    $rejectionReason .= '<optgroup label="' . ($type['rejection_type']) . '">';
    foreach ($rejectionResult as $reject) {
        if ($type['rejection_type'] == $reject['rejection_type']) {
            $rejectionReason .= '<option value="' . $reject['rejection_reason_id'] . '">' . ($reject['rejection_reason_name']) . '</option>';
        }
    }
    $rejectionReason .= '</optgroup>';
}


$testPlatformResult = $general->getTestingPlatforms('eid');
foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name'] . '##' . $row['instrument_id']] = $row['machine_name'];
}

$iResultQuery = "select * from  instrument_machines";
$iResult = $db->rawQuery($iResultQuery);
$machine = [];
foreach ($iResult as $val) {
    $machine[$val['config_machine_id']] = $val['config_machine_name'];
}

/*$testPlatformResult = $general->getTestingPlatforms('eid');
foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name']] = $row['machine_name'];
}*/

$minPatientIdLength = 0;
if (isset($arr['eid_min_patient_id_length']) && $arr['eid_min_patient_id_length'] != "") {
    $minPatientIdLength = $arr['eid_min_patient_id_length'];
}

$sampleResult = $general->fetchDataFromTable('r_eid_sample_type', "status = 'active'");
//Recommended corrective actions
$condition = "status ='active' AND test_type='eid'";
$correctiveActions = $general->fetchDataFromTable('r_recommended_corrective_actions', $condition);

$fileArray = [COUNTRY\SOUTH_SUDAN => 'forms/add-southsudan.php', COUNTRY\SIERRA_LEONE => 'forms/add-sierraleone.php', COUNTRY\DRC => 'forms/add-drc.php', COUNTRY\CAMEROON => 'forms/add-cameroon.php', COUNTRY\PNG => 'forms/add-png.php', COUNTRY\WHO => 'forms/add-who.php', COUNTRY\RWANDA => 'forms/add-rwanda.php', COUNTRY\BURKINA_FASO => 'forms/add-burkina-faso.php'];
require_once($fileArray[$arr['vl_form']]);

?>
<?php
// Common JS functions in a PHP file
// Why PHP? Because we can use PHP variables in the JS code
require_once APPLICATION_PATH . "/eid/eid.js.php";
?>
<script>
    function updateSampleResult() {
        if ($('#isSampleRejected').val() == "yes") {
            $('.rejected').show();
            $('#sampleRejectionReason').addClass('isRequired');
            $('#rejectionDate').addClass('isRequired');
            $('#sampleTestedDateTime,#result').val('');
            $('#sampleTestedDateTime,#result').removeClass('isRequired');
            $(".result-optional").removeClass("isRequired");
        } else if ($('#isSampleRejected').val() == "no") {

            $('.rejected').hide();
            $('#sampleRejectionReason').val('');
            $('#sampleRejectionReason').removeClass('isRequired');
            $('#rejectionDate').removeClass('isRequired');
            $('#sampleTestedDateTime').addClass('isRequired');
            $('#result').addClass('isRequired');
        } else {
            $('.rejected').hide();
            $('#sampleRejectionReason').val('');
            $('#sampleRejectionReason').removeClass('isRequired');
            $('#sampleTestedDateTime').removeClass('isRequired');
            $('#result').removeClass('isRequired');
        }

        if ($('#result').val() == "") {
            $('#sampleTestedDateTime').removeClass('isRequired');
            $('#result').removeClass('isRequired');
        } else {
            $('#sampleTestedDateTime').addClass('isRequired');
            $('#result').addClass('isRequired');
        }
    }

    $(document).ready(function() {

        $("#isSampleRejected,#result").on("change", function() {
            updateSampleResult();
        });

    });


    function insertSampleCode(formId, eidSampleId = null, sampleCode = null, sampleCodeKey = null, sampleCodeFormat = null, countryId = null, sampleCollectionDate = null, provinceCode = null, provinceId = null) {
        $.blockUI();
        let formData = $("#" + formId).serialize();
        formData += "&provinceCode=" + encodeURIComponent(provinceCode);
        formData += "&provinceId=" + encodeURIComponent(provinceId);
        formData += "&countryId=" + encodeURIComponent(countryId);
        $.post("/eid/requests/insert-sample.php", formData,
            function(data) {
                if (data > 0) {
                    $.unblockUI();
                    document.getElementById("eidSampleId").value = data;
                    document.getElementById(formId).submit();
                } else {
                    $.unblockUI();
                    //$("#sampleCollectionDate").val('');
                    generateSampleCode();
                    alert("<?= _translate("We could not save this form. Please try saving again.", true); ?>");
                }
            });
    }
</script>



<?php

require_once APPLICATION_PATH . '/footer.php';
