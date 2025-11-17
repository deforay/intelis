<?php

use App\Services\DatabaseService;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = _translate("COVID-19 | Add New Request");
require_once APPLICATION_PATH . '/header.php';


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$arr = $general->getGlobalConfig();

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

    .table td,
    .table th {
        vertical-align: middle !important;
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

$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$labTechniciansResults = [];
foreach ($userResult as $user) {
    $labTechniciansResults[$user['user_id']] = ($user['user_name']);
}
$healthFacilities = $facilitiesService->getHealthFacilities('covid19');
$testingLabs = $facilitiesService->getTestingLabs('covid19');

$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_covid19_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_covid19_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);

$rejectionReason = "";
foreach ($rejectionTypeResult as $type) {
    $rejectionReason .= '<optgroup label="' . ($type['rejection_type']) . '">';
    foreach ($rejectionResult as $reject) {
        if ($type['rejection_type'] == $reject['rejection_type']) {
            $rejectionReason .= '<option value="' . $reject['rejection_reason_id'] . '">' . ($reject['rejection_reason_name']) . '</option>';
        }
    }
    $rejectionReason .= '</optgroup>';
}
$specimenTypeResult = $general->fetchDataFromTable('r_covid19_sample_type', "status = 'active'");
$countryResult = $general->fetchDataFromTable('r_countries');
$countyData = [];
if (isset($countryResult) && count($countryResult) > 0) {
    foreach ($countryResult as $country) {
        $countyData[$country['id']] = $country['iso_name'];
    }
}

//Recommended corrective actions
$condition = "status ='active' AND test_type='covid19'";
$correctiveActions = $general->fetchDataFromTable('r_recommended_corrective_actions', $condition);

$minPatientIdLength = 0;
if (isset($arr['covid19_min_patient_id_length']) && $arr['covid19_min_patient_id_length'] != "") {
    $minPatientIdLength = $arr['covid19_min_patient_id_length'];
}

/* To get testing platform names */
$testPlatformResult = $general->getTestingPlatforms('covid19');
foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name'] . '##' . $row['instrument_id']] = $row['machine_name'];
}

$fileArray = [COUNTRY\SOUTH_SUDAN => 'forms/add-southsudan.php', COUNTRY\SIERRA_LEONE => 'forms/add-sierraleone.php', COUNTRY\DRC => 'forms/add-drc.php', COUNTRY\CAMEROON => 'forms/add-cameroon.php', COUNTRY\PNG => 'forms/add-png.php', COUNTRY\WHO => 'forms/add-who.php', COUNTRY\RWANDA => 'forms/add-rwanda.php'];

require_once($fileArray[$arr['vl_form']]);

?>

<script>
    $(document).ready(function() {


        $('#isSampleRejected').change(function(e) {
            if (this.value == 'yes') {
                $('.show-rejection').show();
                $('.test-name-table-input').prop('disabled', true);
                $('.test-name-table').addClass('disabled');
                $('#sampleRejectionReason,#rejectionDate').addClass('isRequired');
                $('#sampleTestedDateTime,#result,.test-name-table-input').removeClass('isRequired');
                $('#result').prop('disabled', true);
                $('#sampleRejectionReason').prop('disabled', false);
                $('#sampleTestedDateTime,#result,.test-name-table-input').val('');
                $(".result-optional").removeClass("isRequired");
            } else if (this.value == 'no') {
                $('#sampleRejectionReason').val('');
                $('#rejectionDate').val('');
                $('.show-rejection').hide();
                $('.test-name-table-input').prop('disabled', false);
                $('.test-name-table').removeClass('disabled');
                $('#sampleRejectionReason,#rejectionDate').removeClass('isRequired');
                $('#sampleTestedDateTime,#result,.test-name-table-input').addClass('isRequired');
                $('#result').prop('disabled', false);
                $('#sampleRejectionReason').prop('disabled', true);
                checkPostive();
            }
            if (this.value == '') {
                $('#sampleRejectionReason').val('');
                $('#rejectionDate').val('');
                $('.show-rejection').hide();
                $('#sampleRejectionReason,#rejectionDate').removeClass('isRequired');
                $('#sampleTestedDateTime,#result,.test-name-table-input').removeClass('isRequired');
                $('#sampleRejectionReason').prop('disabled', true);
                $('#sampleTestedDateTime,#result,.test-name-table-input').val('');
            }
        });
        $('#hasRecentTravelHistory').change(function(e) {
            if (this.value == 'no' || this.value == 'unknown') {
                $('.historyfield').hide();
                $('#countryName,#returnDate').removeClass('isRequired');
            } else if (this.value == 'yes') {
                $('.historyfield').show();
                $('#countryName,#returnDate').addClass('isRequired');
            }
        });
    });

    function showPatientList() {
        $("#showEmptyResult").hide();
        if ($.trim($("#artPatientNo").val()) != '') {
            $.post("/covid-19/requests/search-patients.php", {
                    artPatientNo: $.trim($("#artPatientNo").val())
                },
                function(data) {
                    if (data >= '1') {
                        showModal('patientModal.php?artNo=' + $.trim($("#artPatientNo").val()), 900, 520);
                    } else {
                        $("#showEmptyResult").show();
                    }
                });
        }
    }

    function checkSampleNameValidation(tableName, fieldName, id, fnct, alrt) {
        if ($.trim($("#" + id).val()) != '') {
            $.blockUI();
            $.post("/covid-19/requests/check-sample-duplicate.php", {
                    tableName: tableName,
                    fieldName: fieldName,
                    value: $("#" + id).val(),
                    fnct: fnct,
                    format: "html"
                },
                function(data) {
                    if (data != 0) {
                    }
                });
            $.unblockUI();
        }
    }

    function insertSampleCode(formId, covid19SampleId = null, sampleCode = null, sampleCodeKey = null, sampleCodeFormat = null, countryId = null, sampleCollectionDate = null, provinceCode = null, provinceId = null) {
        $.blockUI();
        let formData = $("#" + formId).serialize();
        formData += "&provinceCode=" + encodeURIComponent(provinceCode);
        formData += "&provinceId=" + encodeURIComponent(provinceId);
        formData += "&countryId=" + encodeURIComponent(countryId);
        $.post("/covid-19/requests/insert-sample.php", formData,
            function(data) {
                //console.log(data);
                if (data > 0) {
                    $.unblockUI();
                    document.getElementById("covid19SampleId").value = data;
                    document.getElementById(formId).submit();
                } else {
                    $.unblockUI();
                    //$("#sampleCollectionDate").val('');
                    generateSampleCode();
                    $(".submitButton").show();
                    alert("<?= _translate("We could not save this form. Please try saving again.", true); ?>");
                }
            });
    }
</script>
<?php

require_once APPLICATION_PATH . '/footer.php';
