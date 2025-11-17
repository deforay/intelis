<?php

use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\UsersService;
use App\Services\CommonService;


$title = "COVID-19 | Add New Request";

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
$usersService = ContainerRegistry::get(UsersService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$labTechnicians = $usersService->getActiveUsers($_SESSION['facilityMap']);

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

/* To get testing platform names */
$testPlatformResult = $general->getTestingPlatforms('covid19');
foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name'] . '##' . $row['instrument_id']] = $row['machine_name'];
}

$fileArray = [COUNTRY\SOUTH_SUDAN => 'forms/quick-add-southsudan.php', COUNTRY\SIERRA_LEONE => 'forms/quick-add-sierraleone.php', COUNTRY\DRC => 'forms/quick-add-drc.php', COUNTRY\CAMEROON => 'forms/quick-add-cameroon.php', COUNTRY\PNG => 'forms/quick-add-png.php', COUNTRY\WHO => 'forms/quick-add-who.php', COUNTRY\RWANDA => 'forms/quick-add-rwanda.php'];

require_once($fileArray[$arr['vl_form']]);

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
                $('.test-name-table-input').prop('disabled', true);
                $('.test-name-table').addClass('disabled');
                $('#sampleRejectionReason,#rejectionDate').addClass('isRequired');
                $('#sampleTestedDateTime,#result,.test-name-table-input').removeClass('isRequired');
                $('#result').prop('disabled', true);
                $('#sampleRejectionReason').prop('disabled', false);
                // }else if(this.value == 'no'){
            } else {
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
                if (data > 0) {
                    $.unblockUI();
                    document.getElementById("covid19SampleId").value = data;
                    document.getElementById(formId).submit();
                } else {
                    $.unblockUI();
                    //$("#sampleCollectionDate").val('');
                    generateSampleCode();
                    alert("<?= _translate("We could not save this form. Please try saving again.", true); ?>");
                }
            });
    }

    function calculateAgeInYears() {
        var dateOfBirth = moment($("#patientDob").val(), '<?= $_SESSION['jsDateRangeFormat'] ?? 'DD-MMM-YYYY'; ?>');
        $("#patientAge").val(moment().diff(dateOfBirth, 'years'));
    }
</script>
<?php require_once APPLICATION_PATH . '/footer.php';
