<?php

use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\UsersService;
use App\Utilities\DateUtility;


$title = _("COVID-19 | Edit Request");

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
$rejectionQuery = "SELECT * FROM r_covid19_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);


// Sanitize values before using them below
$_GET = array_map('htmlspecialchars', $_GET);
$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;

//$id = ($_GET['id']);
$covid19Query = "SELECT * from form_covid19 where covid19_id=?";
$covid19Info = $db->rawQueryOne($covid19Query, array($id));

$covid19TestQuery = "SELECT * from covid19_tests where covid19_id=? ORDER BY test_id ASC";
$covid19TestInfo = $db->rawQuery($covid19TestQuery, array($id));

//var_dump($covid19TestInfo);die;

// echo "<pre>"; var_dump($covid19Info);die;

$specimenTypeResult = $general->fetchDataFromTable('r_covid19_sample_type', "status = 'active'");


if ($arr['covid19_sample_code'] == 'auto' || $arr['covid19_sample_code'] == 'auto2' || $arr['covid19_sample_code'] == 'alphanumeric') {
    $sampleClass = '';
    $maxLength = '';
    if ($arr['covid19_max_length'] != '' && $arr['covid19_sample_code'] == 'alphanumeric') {
        $maxLength = $arr['covid19_max_length'];
        $maxLength = "maxlength=" . $maxLength;
    }
} else {
    $sampleClass = '';
    $maxLength = '';
    if ($arr['covid19_max_length'] != '') {
        $maxLength = $arr['covid19_max_length'];
        $maxLength = "maxlength=" . $maxLength;
    }
}


if (isset($covid19Info['sample_collection_date']) && trim($covid19Info['sample_collection_date']) != '' && $covid19Info['sample_collection_date'] != '0000-00-00 00:00:00') {
    $sampleCollectionDate = $covid19Info['sample_collection_date'];
    $expStr = explode(" ", $covid19Info['sample_collection_date']);
    $covid19Info['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $sampleCollectionDate = '';
    $covid19Info['sample_collection_date'] = '';
}

if (isset($covid19Info['result_reviewed_datetime']) && trim($covid19Info['result_reviewed_datetime']) != '' && $covid19Info['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
    $reviewedOn = explode(" ", $covid19Info['result_reviewed_datetime']);
    $covid19Info['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
} else {
    $covid19Info['result_reviewed_datetime'] = '';
}
$fileArray = array(
    1 => 'forms/edit-southsudan.php',
    2 => 'forms/edit-sierraleone.php',
    3 => 'forms/edit-drc.php',
    4 => 'forms/edit-zambia.php',
    5 => 'forms/edit-png.php',
    6 => 'forms/edit-who.php',
    7 => 'forms/edit-rwanda.php',
    8 => 'forms/edit-angola.php',
);

require($fileArray[$arr['vl_form']]);

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
                function(data) {
                    if (data != 0) {
                        <?php if (isset($sarr['sc_user_type']) && ($sarr['sc_user_type'] == 'remoteuser' || $sarr['sc_user_type'] == 'standalone')) { ?>
                            alert(alrt);
                            $("#" + id).val('');
                        <?php } else { ?>
                            data = data.split("##");
                            document.location.href = " /covid-19/requests/covid-19-edit-request.php?id=" + data[0] + "&c=" + data[1];
                        <?php } ?>
                    }
                });
            $.unblockUI();
        }
    }

    $(document).ready(function() {
        $('.date').datepicker({
            changeMonth: true,
            changeYear: true,
            onSelect: function() {
                $(this).change();
            },
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            maxDate: "Today",
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });


        $("#patientDob").datepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            maxDate: "Today",
            yearRange: <?php echo (date('Y') - 120); ?> + ":" + "<?= date('Y') ?>",
            onSelect: function(dateText, inst) {
                $("#sampleCollectionDate").datepicker("option", "minDate", $("#patientDob").datepicker("getDate"));
                $(this).change();
            }
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });


        $('.dateTime').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        $('#sampleCollectionDate').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            onSelect: function(e) {
                $('#sampleReceivedDate').val('');
                $('#sampleReceivedDate').datetimepicker('option', 'minDate', e);
            },
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        $('#sampleReceivedDate').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            onSelect: function(e) {
                $('#sampleTestedDateTime').val('');
                $('#sampleTestedDateTime').datetimepicker('option', 'minDate', e);
            },
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        //$('.date').mask('99-aaa-9999');
        //$('.dateTime').mask('99-aaa-9999 99:99');

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


    function calculateAgeInYears() {
        var dateOfBirth = moment($("#patientDob").val(), "DD-MMM-YYYY");
        $("#patientAge").val(moment().diff(dateOfBirth, 'years'));
    }
</script>
<?php require_once APPLICATION_PATH . '/footer.php'; ?>
