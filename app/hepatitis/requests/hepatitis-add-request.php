<?php

use App\Services\UsersService;
use App\Services\HepatitisService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;


$title = "Hepatitis | Add New Request";

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

    .table td,
    .table th {
        vertical-align: middle !important;
    }
</style>



<?php



/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);
$usersService = ContainerRegistry::get(UsersService::class);

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

// Risk Factors
$riskFactorsData = $hepatitisService->getHepatitisRiskFactors();

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
            $rejectionReason .= '<option value="' . $reject['rejection_reason_id'] . '">' . ($reject['rejection_reason_name']) . '</option>';
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

$minPatientIdLength = 0;
if (isset($arr['hepatitis_min_patient_id_length']) && $arr['hepatitis_min_patient_id_length'] != "") {
    $minPatientIdLength = $arr['hepatitis_min_patient_id_length'];
}

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();


$fileArray = [COUNTRY\SOUTH_SUDAN => 'forms/add-southsudan.php', COUNTRY\SIERRA_LEONE => 'forms/add-sierraleone.php', COUNTRY\DRC => 'forms/add-drc.php', COUNTRY\CAMEROON => 'forms/add-cameroon.php', COUNTRY\PNG => 'forms/add-png.php', COUNTRY\WHO => 'forms/add-who.php', COUNTRY\RWANDA => 'forms/add-rwanda.php'];

require_once $fileArray[$arr['vl_form']];
?>


<?php
// Common JS functions in a PHP file
// Why PHP? Because we can use PHP variables in the JS code
require_once WEB_ROOT . "/assets/js/test-specific/hepatitis.js.php";

?>
<script>
    $(document).ready(function() {


        $(document).on('focus', ".dateTime", function() {
            $(this).datetimepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
                timeFormat: "HH:mm",
                maxDate: "Today",
                onChangeMonthYear: function(year, month, widget) {
                    setTimeout(function() {
                        $('.ui-datepicker-calendar').show();
                    });
                },
                yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y'); ?>"
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });
        });


        $('#isSampleRejected').change(function(e) {
            if (this.value == 'yes') {
                $('.show-rejection').show();
                $('.rejected-input').prop('disabled', true);
                $('.rejected').addClass('disabled');
                $('#sampleRejectionReason,#rejectionDate').addClass('isRequired');
                $('#sampleTestedDateTime,').removeClass('isRequired');
                $('#result').prop('disabled', true);
                $('#sampleRejectionReason').prop('disabled', false);
            } else {
                $('#rejectionDate').val('');
                $('.show-rejection').hide();
                $('.rejected-input').prop('disabled', false);
                $('.rejected').removeClass('disabled');
                $('#sampleRejectionReason,#rejectionDate,.rejected-input').removeClass('isRequired');
                $('#sampleTestedDateTime,').addClass('isRequired');
                $('#result').prop('disabled', false);
                $('#sampleRejectionReason').prop('disabled', true);
            }
        });
    });

    function checkSampleNameValidation(tableName, fieldName, id, fnct, alrt) {
        if ($.trim($("#" + id).val()) != '') {
            $.blockUI();
            $.post("/hepatitis/requests/check-sample-duplicate.php", {
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

    function insertSampleCode(formId, hepatitisTestType = null, hepatitisSampleId = null, sampleCode = null, sampleCodeKey = null, sampleCodeFormat = null, countryId = null, sampleCollectionDate = null, provinceCode = null, provinceId = null) {
        $.blockUI();
        let formData = $("#" + formId).serialize();
        formData += "&provinceCode=" + encodeURIComponent(provinceCode);
        formData += "&provinceId=" + encodeURIComponent(provinceId);
        formData += "&countryId=" + encodeURIComponent(countryId);
        formData += "&prefix=" + encodeURIComponent($("#" + hepatitisTestType).val());
        $.post("/hepatitis/requests/insert-sample.php", formData,
            function(data) {
                if (data > 0) {
                    $.unblockUI();
                    document.getElementById("hepatitisSampleId").value = data;
                    document.getElementById(formId).submit();
                } else {
                    $.unblockUI();
                    //$("#sampleCollectionDate").val('');
                    generateSampleCode();
                    alert("<?= _translate("We could not save this form. Please try saving again.", true); ?>");
                }
            });

        $("#hepatitisPlatform").on("change", function() {
            if (this.value != "") {
                getMachine(this.value);
            }
        });
    }

    function calculateAgeInYears() {
        var dateOfBirth = moment($("#patientDob").val(), '<?= $_SESSION['jsDateRangeFormat'] ?? 'DD-MMM-YYYY'; ?>');
        $("#patientAge").val(moment().diff(dateOfBirth, 'years'));
    }

    function getMachine(value) {
        $.post("/instruments/get-machine-names-by-instrument.php", {
                instrumentId: value,
                machine: '',
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
