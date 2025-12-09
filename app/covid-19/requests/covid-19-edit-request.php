<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Exceptions\SystemException;


$title = _translate("COVID-19 | Edit Request");

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

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


$labFieldDisabled = '';

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$healthFacilities = $facilitiesService->getHealthFacilities('covid19');
$testingLabs = $facilitiesService->getTestingLabs('covid19');

$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$labTechniciansResults = [];
foreach ($userResult as $user) {
    $labTechniciansResults[$user['user_id']] = ($user['user_name']);
}

$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_covid19_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_covid19_sample_rejection_reasons WHERE rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);


// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

//$id = ($_GET['id']);
$covid19Query = "SELECT * from form_covid19 WHERE covid19_id=?";
$covid19Info = $db->rawQueryOne($covid19Query, [$id]);

$covid19TestQuery = "SELECT * from covid19_tests WHERE covid19_id=? ORDER BY test_id ASC";
$covid19TestInfo = $db->rawQuery($covid19TestQuery, [$id]);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$userId =  $_SESSION['userId'];
$checkNonAdminUser = $general->isNonAdmin($userId);

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$specimenTypeResult = $general->fetchDataFromTable('r_covid19_sample_type', "status = 'active'");

$arr = $general->getGlobalConfig();


$sampleCollectionDate = $covid19Info['sample_collection_date'];
$covid19Info['sample_collection_date'] = DateUtility::humanReadableDateFormat($covid19Info['sample_collection_date'] ?? '', true);
$covid19Info['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($covid19Info['result_reviewed_datetime'] ?? '', true);
$covid19Info['result_approved_datetime'] = DateUtility::humanReadableDateFormat($covid19Info['result_approved_datetime'] ?? '', true);

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

if (!empty($arr['display_encrypt_pii_option']) && $arr['display_encrypt_pii_option'] == "yes" && !empty($covid19Info['is_encrypted']) && $covid19Info['is_encrypted'] == 'yes') {
    $key = (string) $general->getGlobalConfig('key');
    $covid19Info['patient_id'] = $general->crypto('decrypt', $covid19Info['patient_id'], $key);
    if ($covid19Info['patient_name'] != '') {
        $covid19Info['patient_name'] = $general->crypto('decrypt', $covid19Info['patient_name'], $key);
    }
    if ($covid19Info['patient_surname'] != '') {
        $covid19Info['patient_surname'] = $general->crypto('decrypt', $covid19Info['patient_surname'], $key);
    }
}
$covid19Info['patient_dob'] = DateUtility::humanReadableDateFormat($covid19Info['patient_dob'] ?? '');

$minPatientIdLength = 0;
if (isset($arr['covid19_min_patient_id_length']) && $arr['covid19_min_patient_id_length'] != "") {
    $minPatientIdLength = $arr['covid19_min_patient_id_length'];
}

/* To get testing platform names */
$testPlatformResult = $general->getTestingPlatforms('covid19');
foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name'] . '##' . $row['instrument_id']] = $row['machine_name'];
}

if ($covid19Info['locked'] == 'yes') {
    if($checkNonAdminUser == 1){
        $fileArray = [COUNTRY\SOUTH_SUDAN => 'forms/edit-southsudan.php', COUNTRY\SIERRA_LEONE => 'forms/edit-sierraleone.php', COUNTRY\DRC => 'forms/edit-drc.php', COUNTRY\CAMEROON => 'forms/edit-cameroon.php', COUNTRY\PNG => 'forms/edit-png.php', COUNTRY\WHO => 'forms/edit-who.php', COUNTRY\RWANDA => 'forms/edit-rwanda.php'];
        require_once($fileArray[$arr['vl_form']]);
    }
    else{
        http_response_code(403);
        throw new SystemException('Invalid URL', 403);
    }
}
else{
        if(_isAllowed("/covid-19/requests/covid-19-edit-request.php"))
        {
            $fileArray = [COUNTRY\SOUTH_SUDAN => 'forms/edit-southsudan.php', COUNTRY\SIERRA_LEONE => 'forms/edit-sierraleone.php', COUNTRY\DRC => 'forms/edit-drc.php', COUNTRY\CAMEROON => 'forms/edit-cameroon.php', COUNTRY\PNG => 'forms/edit-png.php', COUNTRY\WHO => 'forms/edit-who.php', COUNTRY\RWANDA => 'forms/edit-rwanda.php'];
            require_once($fileArray[$arr['vl_form']]);

        }
        else{
            http_response_code(403);
            throw new SystemException('Invalid URL', 403);
        }

}

?>

<script>
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
                function (data) {
                    if (data != 0) {

                    }
                });
            $.unblockUI();
        }
    }

    $(document).ready(function () {




        $('#isSampleRejected').change(function (e) {
            changeReject(this.value);
        });
        $('#hasRecentTravelHistory').change(function (e) {
            changeHistory(this.value);
        });
        changeReject($('#isSampleRejected').val());
        changeHistory($('#hasRecentTravelHistory').val());

        $('.result-focus').change(function (e) {
            var status = false;
            $(".result-focus").each(function (index) {
                if ($(this).val() != "") {
                    status = true;
                }
            });
            if (status) {
                $('.change-reason').show();
                $('#reasonForChanging').addClass('isRequired');
            } else {
                $('.change-reason').hide();
                $('#reasonForChanging').removeClass('isRequired');
            }
        });
    });

    function changeHistory(val) {
        if (val == 'no' || val == 'unknown') {
            $('.historyfield').hide();
            $('#countryName,#returnDate').removeClass('isRequired');
        } else if (val == 'yes') {
            $('.historyfield').show();
            $('#countryName,#returnDate').addClass('isRequired');
        }
    }

    function showPatientList() {
        $("#showEmptyResult").hide();
        if ($.trim($("#artPatientNo").val()) != '') {
            $.post("/covid-19/requests/search-patients.php", {
                artPatientNo: $.trim($("#artPatientNo").val())
            },
                function (data) {
                    if (data >= '1') {
                        showModal('patientModal.php?artNo=' + $.trim($("#artPatientNo").val()), 900, 520);
                    } else {
                        $("#showEmptyResult").show();
                    }
                });
        }
    }

    function changeReject(val) {
        if (val == 'yes') {
            $('.show-rejection').show();
            $('.test-name-table-input').prop('disabled', true);
            $('.test-name-table').addClass('disabled');
            $('#sampleRejectionReason,#rejectionDate').addClass('isRequired');
            $('#sampleTestedDateTime,#result,.test-name-table-input').removeClass('isRequired');
            $('#result').prop('disabled', true);
            $('#sampleRejectionReason').prop('disabled', false);
            $('#sampleTestedDateTime,#result,.test-name-table-input').val('');
            $(".result-optional").removeClass("isRequired");
        } else if (val == 'no') {
            $('#sampleRejectionReason').val('');
            $('#rejectionDate').val('');
            $('.show-rejection').hide();
            $('.test-name-table-input').prop('disabled', false);
            $('.test-name-table').removeClass('disabled');
            $('#sampleRejectionReason,#rejectionDate').removeClass('isRequired');
            $('#sampleTestedDateTime,#result,.test-name-table-input').addClass('isRequired');
            $('#result').prop('disabled', false);
            $('#sampleRejectionReason').prop('disabled', true);
        }
        if (val == '') {
            $('#sampleRejectionReason').val('');
            $('#rejectionDate').val('');
            $('.show-rejection').hide();
            $('#sampleRejectionReason,#rejectionDate').removeClass('isRequired');
            $('#sampleTestedDateTime,#result,.test-name-table-input').removeClass('isRequired');
            $('#sampleRejectionReason').prop('disabled', true);
            $('#sampleTestedDateTime,#result,.test-name-table-input').val('');
        }
        <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            checkPostive();
        <?php } ?>
    }
</script>
<?php require_once APPLICATION_PATH . '/footer.php'; ?>