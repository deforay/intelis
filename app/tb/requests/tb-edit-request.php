<?php

use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\UsersService;
use App\Utilities\DateUtility;


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

/* Get Active users for approved / reviewed / examined by */
$facilityMap = $facilitiesService->getUserFacilityMap($_SESSION['userId']);
$userResult = $usersService->getActiveUsers($facilityMap);
$userInfo = [];
foreach ($userResult as $user) {
    $userInfo[$user['user_id']] = ($user['user_name']);
}

$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_tb_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_tb_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);


$id = base64_decode($_GET['id']);
$tbQuery = "SELECT * from form_tb where tb_id=?";
$tbInfo = $db->rawQueryOne($tbQuery, array($id));
if (!$tbInfo) {
    header("Location:/tb/requests/tb-requests.php");
}
$testRequsted = [];
if (isset($tbInfo['tests_requested']) && $tbInfo['tests_requested'] != "") {
    $testRequsted = json_decode($tbInfo['tests_requested']);
}
$testQuery = "SELECT * from tb_tests where tb_id=? ORDER BY tb_test_id ASC";
$tbTestInfo = $db->rawQuery($testQuery, array($id));

$specimenTypeResult = $general->fetchDataFromTable('r_tb_sample_type', "status = 'active'");

if ($arr['tb_sample_code'] == 'auto' || $arr['tb_sample_code'] == 'auto2' || $arr['tb_sample_code'] == 'alphanumeric') {
    $sampleClass = '';
    $maxLength = '';
    if ($arr['tb_max_length'] != '' && $arr['tb_sample_code'] == 'alphanumeric') {
        $maxLength = $arr['tb_max_length'];
        $maxLength = "maxlength=" . $maxLength;
    }
} else {
    $sampleClass = '';
    $maxLength = '';
    if ($arr['tb_max_length'] != '') {
        $maxLength = $arr['tb_max_length'];
        $maxLength = "maxlength=" . $maxLength;
    }
}


if (isset($tbInfo['request_created_datetime']) && trim($tbInfo['request_created_datetime']) != '' && $tbInfo['request_created_datetime'] != '0000-00-00 00:00:00') {
    $requestedDate = $tbInfo['request_created_datetime'];
    $expStr = explode(" ", $tbInfo['request_created_datetime']);
    $tbInfo['request_created_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $requestedDate = '';
    $tbInfo['request_created_datetime'] = '';
}

if (isset($tbInfo['sample_collection_date']) && trim($tbInfo['sample_collection_date']) != '' && $tbInfo['sample_collection_date'] != '0000-00-00 00:00:00') {
    $sampleCollectionDate = $tbInfo['sample_collection_date'];
    $expStr = explode(" ", $tbInfo['sample_collection_date']);
    $tbInfo['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $sampleCollectionDate = '';
    $tbInfo['sample_collection_date'] = '';
}

if (isset($tbInfo['sample_received_at_lab_datetime']) && trim($tbInfo['sample_received_at_lab_datetime']) != '' && $tbInfo['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
    $sampleReceivedDate = $tbInfo['sample_received_at_lab_datetime'];
    $expStr = explode(" ", $tbInfo['sample_received_at_lab_datetime']);
    $tbInfo['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $sampleReceivedDate = '';
    $tbInfo['sample_received_at_lab_datetime'] = '';
}

if (isset($tbInfo['sample_tested_datetime']) && trim($tbInfo['sample_tested_datetime']) != '' && $tbInfo['sample_tested_datetime'] != '0000-00-00 00:00:00') {
    $sampleTestedDateTime = explode(" ", $tbInfo['sample_tested_datetime']);
    $tbInfo['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($sampleTestedDateTime[0]) . " " . $sampleTestedDateTime[1];
} else {
    $tbInfo['sample_tested_datetime'] = '';
}

if (isset($tbInfo['sample_dispatched_datetime']) && trim($tbInfo['sample_dispatched_datetime']) != '' && $tbInfo['sample_tested_datetime'] != '0000-00-00 00:00:00') {
    $sampleTestedDateTime = explode(" ", $tbInfo['sample_dispatched_datetime']);
    $tbInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($sampleTestedDateTime[0]) . " " . $sampleTestedDateTime[1];
} else {
    $tbInfo['sample_dispatched_datetime'] = '';
}

if (isset($tbInfo['result_reviewed_datetime']) && trim($tbInfo['result_reviewed_datetime']) != '' && $tbInfo['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
    $reviewedOn = explode(" ", $tbInfo['result_reviewed_datetime']);
    $tbInfo['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($reviewedOn[0]) . " " . $reviewedOn[1];
} else {
    $tbInfo['result_reviewed_datetime'] = '';
}

if (isset($tbInfo['result_approved_datetime']) && trim($tbInfo['result_approved_datetime']) != '' && $tbInfo['result_approved_datetime'] != '0000-00-00 00:00:00') {
    $approvedOn = explode(" ", $tbInfo['result_approved_datetime']);
    $tbInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat($approvedOn[0]) . " " . $approvedOn[1];
} else {
    $tbInfo['result_approved_datetime'] = '';
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
            $.post("/tb/requests/check-sample-duplicate.php", {
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
                            document.location.href = " /tb/requests/tb-edit-request.php?id=" + data[0] + "&c=" + data[1];
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

        $('.date-time').datetimepicker({
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
            onSelect: function(e) {},
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


    function calculateAgeInYears() {
        var dateOfBirth = moment($("#patientDob").val(), "DD-MMM-YYYY");
        $("#patientAge").val(moment().diff(dateOfBirth, 'years'));
    }
</script>
<?php require_once APPLICATION_PATH . '/footer.php';
