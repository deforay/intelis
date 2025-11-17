<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\HepatitisService;
use App\Services\UsersService;
use App\Utilities\DateUtility;


$title = "Hepatitis | Edit Request";

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

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

$labFieldDisabled = '';



/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$usersService = ContainerRegistry::get(UsersService::class);

/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);

$hepatitisResults = $hepatitisService->getHepatitisResults();
$testReasonResults = $hepatitisService->getHepatitisReasonsForTesting();
$healthFacilities = $facilitiesService->getHealthFacilities('hepatitis');
$testingLabs = $facilitiesService->getTestingLabs('hepatitis');

$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$labTechniciansResults = [];
foreach ($userResult as $user) {
    $labTechniciansResults[$user['user_id']] = ($user['user_name']);
}

// Comorbidity
$comorbidityData = $hepatitisService->getHepatitisComorbidities();
$comorbidityInfo = $hepatitisService->getComorbidityByHepatitisId($id);

// Risk Factors
$riskFactorsData = $hepatitisService->getHepatitisRiskFactors();
$riskFactorsInfo = $hepatitisService->getRiskFactorsByHepatitisId($id);


//$id = ($_GET['id']);
$hepatitisQuery = "SELECT * FROM form_hepatitis where hepatitis_id=?";
$hepatitisInfo = $db->rawQueryOne($hepatitisQuery, [$id]);


if (isset($hepatitisInfo['sample_collection_date']) && trim((string) $hepatitisInfo['sample_collection_date']) !== '' && $hepatitisInfo['sample_collection_date'] != '0000-00-00 00:00:00') {
    $sampleCollectionDate = $hepatitisInfo['sample_collection_date'];
    $expStr = explode(" ", (string) $hepatitisInfo['sample_collection_date']);
    $hepatitisInfo['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $sampleCollectionDate = '';
    $hepatitisInfo['sample_collection_date'] = '';
}

//sample rejection reason
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_hepatitis_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

$rejectionQuery = "SELECT * FROM r_hepatitis_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);

$rejectionReason = "";
foreach ($rejectionTypeResult as $type) {
    $rejectionReason .= '<optgroup label="' . ($type['rejection_type']) . '">';
    foreach ($rejectionResult as $reject) {
        if ($type['rejection_type'] == $reject['rejection_type']) {
            $selected = (isset($hepatitisInfo['reason_for_sample_rejection']) && $hepatitisInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? "selected='selected'" : "";
            $rejectionReason .= '<option value="' . $reject['rejection_reason_id'] . '" ' . $selected . '>' . ($reject['rejection_reason_name']) . '</option>';
        }
    }
    $rejectionReason .= '</optgroup>';
}
// Specimen Type
$specimenResult = $hepatitisService->getHepatitisSampleTypes();

// Import machine config
$testPlatformResult = $general->getTestingPlatforms('hepatitis');
$testPlatformList = [];
foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name'] . '##' . $row['instrument_id']] = $row['machine_name'];
}

if (!empty($arr['display_encrypt_pii_option']) && $arr['display_encrypt_pii_option'] == "yes" && !empty($hepatitisInfo['is_encrypted']) && $hepatitisInfo['is_encrypted'] == 'yes') {
    $key = (string) $general->getGlobalConfig('key');
    $hepatitisInfo['patient_id'] = $general->crypto('decrypt', $hepatitisInfo['patient_id'], $key);
    if ($hepatitisInfo['patient_name'] != '') {
        $hepatitisInfo['patient_name'] = $general->crypto('decrypt', $hepatitisInfo['patient_name'], $key);
    }
    if ($hepatitisInfo['patient_surname'] != '') {
        $hepatitisInfo['patient_surname'] = $general->crypto('decrypt', $hepatitisInfo['patient_surname'], $key);
    }
}

$minPatientIdLength = 0;
if (isset($arr['hepatitis_min_patient_id_length']) && $arr['hepatitis_min_patient_id_length'] != "") {
    $minPatientIdLength = $arr['hepatitis_min_patient_id_length'];
}

$fileArray = [COUNTRY\SOUTH_SUDAN => 'forms/edit-southsudan.php', COUNTRY\SIERRA_LEONE => 'forms/edit-sierraleone.php', COUNTRY\DRC => 'forms/edit-drc.php', COUNTRY\CAMEROON => 'forms/edit-cameroon.php', COUNTRY\PNG => 'forms/edit-png.php', COUNTRY\WHO => 'forms/edit-who.php', COUNTRY\RWANDA => 'forms/edit-rwanda.php'];

require_once $fileArray[$arr['vl_form']];

?>


<?php
// Common JS functions in a PHP file
// Why PHP? Because we can use PHP variables in the JS code
require_once WEB_ROOT . "/assets/js/test-specific/hepatitis.js.php";

?>

<script>
    changeReject($('#isSampleRejected').val());

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

    $(document).ready(function() {



        $('#isSampleRejected').change(function(e) {
            changeReject(this.value);
        });

        $("#hepatitisPlatform").on("change", function() {
            if (this.value != "") {
                getMachine(this.value);
            }
        });
        getMachine($("#hepatitisPlatform").val());

        $('.result-focus').change(function(e) {
            var status = false;
            $(".result-focus").each(function(index) {
                if ($(this).val() != "") {
                    status = true;
                }
            });
            if (status) {
                $('.change-reason').show();
                $('#reasonForResultChanges').addClass('isRequired');
            } else {
                $('.change-reason').hide();
                $('#reasonForResultChanges').removeClass('isRequired');
            }
        });
    });

    function changeReject(val) {
        if (val == 'yes') {
            $('.show-rejection').show();
            $('.rejected-input').prop('disabled', true);
            $('.rejected').addClass('disabled');
            $('#sampleRejectionReason,#rejectionDate').addClass('isRequired');
            $('#sampleTestedDateTime').removeClass('isRequired');
            $('#result').prop('disabled', true);
            $('#sampleRejectionReason').prop('disabled', false);
        } else {
            $('#rejectionDate').val('');
            $('.show-rejection').hide();
            $('.rejected-input').prop('disabled', false);
            $('.rejected').removeClass('disabled');
            $('#sampleRejectionReason,#rejectionDate,.rejected-input').removeClass('isRequired');
            // $('#sampleTestedDateTime').addClass('isRequired');
            $('#result').prop('disabled', false);
            $('#sampleRejectionReason').prop('disabled', true);
        }
    }

    function calculateAgeInYears() {
        var dateOfBirth = moment($("#patientDob").val(), '<?= $_SESSION['jsDateRangeFormat'] ?? 'DD-MMM-YYYY'; ?>');
        $("#patientAge").val(moment().diff(dateOfBirth, 'years'));
    }

    function getMachine(value) {
        $.post("/instruments/get-machine-names-by-instrument.php", {
                instrumentId: value,
                machine: <?php echo empty($hepatitisInfo['import_machine_name']) ? '""' : $hepatitisInfo['import_machine_name']; ?>,
                testType: 'hepatitis'
            },
            function(data) {
                $('#machineName').html('');
                if (data != "") {
                    $('#machineName').append(data);
                }
            });
    }
</script>
<?php require_once APPLICATION_PATH . '/footer.php';
