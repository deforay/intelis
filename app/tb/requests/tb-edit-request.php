<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use App\Exceptions\SystemException;


$title = "TB | Edit Request";

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


$labFieldDisabled = '';



/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$healthFacilities = $facilitiesService->getHealthFacilities('tb');
$testingLabs = $facilitiesService->getTestingLabs('tb');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/* Get Active users for approved / reviewed / examined by */
$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$userInfo = [];
foreach ($userResult as $user) {
    $userInfo[$user['user_id']] = ($user['user_name']);
}

$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_tb_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_tb_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);


// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

$tbQuery = "SELECT * from form_tb where tb_id=?";
$tbInfo = $db->rawQueryOne($tbQuery, [$id]);

if (!$tbInfo) {
    header("Location:/tb/requests/tb-requests.php");
}
$testRequsted = [];
if (isset($tbInfo['tests_requested']) && $tbInfo['tests_requested'] != "") {
    $testRequsted = json_decode((string) $tbInfo['tests_requested']);
}
$testQuery = "SELECT * from tb_tests where tb_id=? ORDER BY tb_test_id ASC";
$tbTestInfo = $db->rawQuery($testQuery, [$id]);
$specimenTypeResult = $general->fetchDataFromTable('r_tb_sample_type', "status = 'active'");

$userId =  $_SESSION['userId'];
$checkNonAdminUser = $general->isNonAdmin($userId);

if (isset($tbInfo['request_created_datetime']) && trim((string) $tbInfo['request_created_datetime']) !== '' && $tbInfo['request_created_datetime'] != '0000-00-00 00:00:00') {
    $requestedDate = $tbInfo['request_created_datetime'];
    $expStr = explode(" ", (string) $tbInfo['request_created_datetime']);
    $tbInfo['request_created_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $requestedDate = '';
    $tbInfo['request_created_datetime'] = '';
}

if (isset($tbInfo['sample_collection_date']) && trim((string) $tbInfo['sample_collection_date']) !== '' && $tbInfo['sample_collection_date'] != '0000-00-00 00:00:00') {
    $sampleCollectionDate = $tbInfo['sample_collection_date'];
    $expStr = explode(" ", (string) $tbInfo['sample_collection_date']);
    $tbInfo['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $sampleCollectionDate = '';
    $tbInfo['sample_collection_date'] = '';
}

if (isset($tbInfo['sample_received_at_lab_datetime']) && trim((string) $tbInfo['sample_received_at_lab_datetime']) !== '' && $tbInfo['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
    $sampleReceivedDate = $tbInfo['sample_received_at_lab_datetime'];
    $expStr = explode(" ", (string) $tbInfo['sample_received_at_lab_datetime']);
    $tbInfo['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $sampleReceivedDate = '';
    $tbInfo['sample_received_at_lab_datetime'] = '';
}

if (isset($tbInfo['sample_tested_datetime']) && trim((string) $tbInfo['sample_tested_datetime']) !== '' && $tbInfo['sample_tested_datetime'] != '0000-00-00 00:00:00') {
    $sampleTestedDateTime = explode(" ", (string) $tbInfo['sample_tested_datetime']);
    $tbInfo['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($sampleTestedDateTime[0]) . " " . $sampleTestedDateTime[1];
} else {
    $tbInfo['sample_tested_datetime'] = '';
}

if (isset($tbInfo['sample_dispatched_datetime']) && trim((string) $tbInfo['sample_dispatched_datetime']) !== '' && $tbInfo['sample_tested_datetime'] !== '0000-00-00 00:00:00') {
    $sampleTestedDateTime = explode(" ", (string) $tbInfo['sample_dispatched_datetime']);
    $tbInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($sampleTestedDateTime[0]) . " " . $sampleTestedDateTime[1];
} else {
    $tbInfo['sample_dispatched_datetime'] = '';
}

if (isset($tbInfo['result_reviewed_datetime']) && trim((string) $tbInfo['result_reviewed_datetime']) !== '' && $tbInfo['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
    $reviewedOn = explode(" ", (string) $tbInfo['result_reviewed_datetime']);
    $tbInfo['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
} else {
    $tbInfo['result_reviewed_datetime'] = '';
}

if (isset($tbInfo['result_date']) && trim((string) $tbInfo['result_date']) !== '' && $tbInfo['result_date'] != '0000-00-00 00:00:00') {
    $reviewedOn = explode(" ", (string) $tbInfo['result_date']);
    $tbInfo['result_date'] = DateUtility::humanReadableDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
} else {
    $tbInfo['result_date'] = '';
}

if (isset($tbInfo['xpert_result_date']) && trim((string) $tbInfo['xpert_result_date']) !== '' && $tbInfo['xpert_result_date'] != '0000-00-00 00:00:00') {
    $reviewedOn = explode(" ", (string) $tbInfo['xpert_result_date']);
    $tbInfo['xpert_result_date'] = DateUtility::humanReadableDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
} else {
    $tbInfo['xpert_result_date'] = '';
}

if (isset($tbInfo['tblam_result_date']) && trim((string) $tbInfo['tblam_result_date']) !== '' && $tbInfo['tblam_result_date'] != '0000-00-00 00:00:00') {
    $reviewedOn = explode(" ", (string) $tbInfo['tblam_result_date']);
    $tbInfo['tblam_result_date'] = DateUtility::humanReadableDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
} else {
    $tbInfo['tblam_result_date'] = '';
}

if (isset($tbInfo['culture_result_date']) && trim((string) $tbInfo['culture_result_date']) !== '' && $tbInfo['culture_result_date'] != '0000-00-00 00:00:00') {
    $reviewedOn = explode(" ", (string) $tbInfo['culture_result_date']);
    $tbInfo['culture_result_date'] = DateUtility::humanReadableDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
} else {
    $tbInfo['culture_result_date'] = '';
}

if (isset($tbInfo['identification_result_date']) && trim((string) $tbInfo['identification_result_date']) !== '' && $tbInfo['identification_result_date'] != '0000-00-00 00:00:00') {
    $reviewedOn = explode(" ", (string) $tbInfo['identification_result_date']);
    $tbInfo['identification_result_date'] = DateUtility::humanReadableDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
} else {
    $tbInfo['identification_result_date'] = '';
}

if (isset($tbInfo['drug_mgit_result_date']) && trim((string) $tbInfo['drug_mgit_result_date']) !== '' && $tbInfo['drug_mgit_result_date'] != '0000-00-00 00:00:00') {
    $reviewedOn = explode(" ", (string) $tbInfo['drug_mgit_result_date']);
    $tbInfo['drug_mgit_result_date'] = DateUtility::humanReadableDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
} else {
    $tbInfo['drug_mgit_result_date'] = '';
}

if (isset($tbInfo['drug_lpa_result_date']) && trim((string) $tbInfo['drug_lpa_result_date']) !== '' && $tbInfo['drug_lpa_result_date'] != '0000-00-00 00:00:00') {
    $reviewedOn = explode(" ", (string) $tbInfo['drug_lpa_result_date']);
    $tbInfo['drug_lpa_result_date'] = DateUtility::humanReadableDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
} else {
    $tbInfo['drug_lpa_result_date'] = '';
}

if (isset($tbInfo['result_approved_datetime']) && trim((string) $tbInfo['result_approved_datetime']) !== '' && $tbInfo['result_approved_datetime'] != '0000-00-00 00:00:00') {
    $approvedOn = explode(" ", (string) $tbInfo['result_approved_datetime']);
    $tbInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat($approvedOn[0]) . " " . $approvedOn[1];
} else {
    $tbInfo['result_approved_datetime'] = '';
}
//Recommended corrective actions
$condition = "status ='active' AND test_type='tb'";
$correctiveActions = $general->fetchDataFromTable('r_recommended_corrective_actions', $condition);

if (!empty($arr['display_encrypt_pii_option']) && $arr['display_encrypt_pii_option'] == "yes" && !empty($tbInfo['is_encrypted']) && $tbInfo['is_encrypted'] == 'yes') {
    $key = (string) $general->getGlobalConfig('key');
    $tbInfo['patient_id'] = $general->crypto('decrypt', $tbInfo['patient_id'], $key);
    if ($tbInfo['patient_name'] != '') {
        $tbInfo['patient_name'] = $general->crypto('decrypt', $tbInfo['patient_name'], $key);
    }
    if ($tbInfo['patient_surname'] != '') {
        $tbInfo['patient_surname'] = $general->crypto('decrypt', $tbInfo['patient_surname'], $key);
    }
}

$minPatientIdLength = 0;
if (isset($arr['tb_min_patient_id_length']) && $arr['tb_min_patient_id_length'] != "") {
    $minPatientIdLength = $arr['tb_min_patient_id_length'];
}
// Import machine config
$testPlatformResult = $general->getTestingPlatforms('hepatitis');
$testPlatformList = [];
foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name'] . '##' . $row['instrument_id']] = $row['machine_name'];
}

if ($tbInfo['locked'] == 'yes') {
    if ($checkNonAdminUser == 1) {
        $fileArray = [
            COUNTRY\SOUTH_SUDAN => 'forms/edit-southsudan.php',
            COUNTRY\SIERRA_LEONE => 'forms/edit-sierraleone.php',
            COUNTRY\DRC => 'forms/edit-drc.php',
            COUNTRY\CAMEROON => 'forms/edit-cameroon.php',
            COUNTRY\PNG => 'forms/edit-png.php',
            COUNTRY\WHO => 'forms/edit-who.php',
            COUNTRY\RWANDA => 'forms/edit-rwanda.php',
            COUNTRY\BURKINA_FASO => 'forms/edit-burkina-faso.php'
        ];

        require_once($fileArray[$arr['vl_form']]);
    } else {
        http_response_code(403);
        throw new SystemException('Invalid URL', 403);
    }
} else {
    if (_isAllowed("/eid/requests/eid-edit-request.php")) {
        $fileArray = [
            COUNTRY\SOUTH_SUDAN => 'forms/edit-southsudan.php',
            COUNTRY\SIERRA_LEONE => 'forms/edit-sierraleone.php',
            COUNTRY\DRC => 'forms/edit-drc.php',
            COUNTRY\CAMEROON => 'forms/edit-cameroon.php',
            COUNTRY\PNG => 'forms/edit-png.php',
            COUNTRY\WHO => 'forms/edit-who.php',
            COUNTRY\RWANDA => 'forms/edit-rwanda.php',
            COUNTRY\BURKINA_FASO => 'forms/edit-burkina-faso.php'
        ];

        require_once($fileArray[$arr['vl_form']]);
    } else {
        http_response_code(403);
        throw new SystemException('Invalid URL', 403);
    }
}
?>

<script>
    function checkSampleNameValidation(tableName, fieldName, id, fnct, alrt) {
        if ($.trim($("#" + id).val()) != '') {
            $.blockUI();
            $.post("/tb/requests/check-sample-duplicate.php", {
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

    $(document).ready(function() {


        $('#isSampleRejected').change(function(e) {
            changeReject(this.value);
        });
        $('#hasRecentTravelHistory').change(function(e) {
            changeHistory(this.value);
        });
        changeReject($('#isSampleRejected').val());
        changeHistory($('#hasRecentTravelHistory').val());

        $('.result-focus').change(function(e) {
            var status = false;
            $(".result-focus").each(function(index) {
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
            $.post("/tb/requests/search-patients.php", {
                    artPatientNo: $("#artPatientNo").val()
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

    function changeReject(val) {
        if (val == 'yes') {
            $('.show-rejection').show();
            $('.test-name-table-input').prop('disabled', true);
            $('.test-name-table').addClass('disabled');
            // $('#sampleRejectionReason,#rejectionDate').addClass('isRequired');
            $('#sampleTestedDateTime,#result,.test-name-table-input').removeClass('isRequired');
            $('#result').prop('disabled', true);
            $('#sampleRejectionReason').prop('disabled', false);
        } else if (val == 'no') {
            $('#rejectionDate').val('');
            $('.show-rejection').hide();
            $('.test-name-table-input').prop('disabled', false);
            $('.test-name-table').removeClass('disabled');
            $('#sampleRejectionReason,#rejectionDate').removeClass('isRequired');
            // $('#sampleTestedDateTime,#result,.test-name-table-input').addClass('isRequired');
            $('#result').prop('disabled', false);
            $('#sampleRejectionReason').prop('disabled', true);
        }
        <?php if (isset($arr['tb_positive_confirmatory_tests_required_by_central_lab']) && $arr['tb_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            checkPostive();
        <?php } ?>
    }
</script>
<?php require_once APPLICATION_PATH . '/footer.php';
