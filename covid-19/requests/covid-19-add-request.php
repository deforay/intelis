<?php
ob_start();
$title = "COVID-19 | Add New Request";
#require_once('../../startup.php');
include_once(APPLICATION_PATH . '/header.php');

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
// $general = new \Vlsm\Models\General();
$facilitiesDb = new \Vlsm\Models\Facilities();
$usersModel = new \Vlsm\Models\Users();
// $arr = $general->getGlobalConfig();
// $sarr = $general->getSystemConfig();

$facilityMap = $facilitiesDb->getFacilityMap($_SESSION['userId']);
$userResult = $usersModel->getActiveUsers($facilityMap);
$labTechniciansResults = array();
foreach ($userResult as $user) {
    $labTechniciansResults[$user['user_id']] = ucwords($user['user_name']);
}
$healthFacilities = $facilitiesDb->getHealthFacilities('covid19');
$testingLabs = $facilitiesDb->getTestingLabs('covid19');

$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_covid19_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_covid19_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);

$rejectionReason = "";
foreach ($rejectionTypeResult as $type) {
    $rejectionReason .= '<optgroup label="' . ucwords($type['rejection_type']) . '">';
    foreach ($rejectionResult as $reject) {
        if ($type['rejection_type'] == $reject['rejection_type']) {
            $rejectionReason .= '<option value="' . $reject['rejection_reason_id'] . '">' . ucwords($reject['rejection_reason_name']) . '</option>';
        }
    }
    $rejectionReason .= '</optgroup>';
}



$specimenTypeResult = $general->fetchDataFromTable('r_covid19_sample_type', "status = 'active'");


$fileArray = array(
    1 => 'forms/add-southsudan.php',
    2 => 'forms/add-zimbabwe.php',
    3 => 'forms/add-drc.php',
    4 => 'forms/add-zambia.php',
    5 => 'forms/add-png.php',
    6 => 'forms/add-who.php',
    7 => 'forms/add-rwanda.php',
    8 => 'forms/add-angola.php',
);

if (file_exists($fileArray[$arr['vl_form']])) {
    require_once($fileArray[$arr['vl_form']]);
} else {
    require_once('forms/add-who.php');
}


?>

<script>
    $(document).ready(function() {
        $('.date').datepicker({
            changeMonth: true,
            changeYear: true,
            onSelect: function() {
                $(this).change();
            },
            dateFormat: 'dd-M-yy',
            timeFormat: "hh:mm TT",
            maxDate: "Today",
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
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
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        $("#patientDob").datepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            maxDate: "Today",
            yearRange: <?php echo (date('Y') - 120); ?> + ":" + "<?php echo (date('Y')) ?>",
            onSelect: function(dateText, inst) {
                $("#sampleCollectionDate").datepicker("option", "minDate", $("#patientDob").datepicker("getDate"));
                $(this).change();
            }
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });


        $(document).on('focus', ".dateTime", function() {
            $(this).datetimepicker({
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
                yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y'); ?>"
            }).click(function() {
                $('.ui-datepicker-calendar').show();
            });
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
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
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
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        $('.date').mask('99-aaa-9999');
        $('.dateTime').mask('99-aaa-9999 99:99');

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
                // }else if(this.value == 'no'){
            } else if(this.value == 'no') {
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
            $.post("/covid-19/requests/checkPatientExist.php", {
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
                        <?php if ($sarr['sc_user_type'] == 'remoteuser' || $sarr['sc_user_type'] == 'standalone') { ?>
                            alert(alrt);
                            $("#" + id).val('');
                            <?php if ($arr['vl_form'] == '3') { ?>
                                $("#sampleCodeValue").html('').hide();
                            <?php }
                        } else { ?>
                            data = data.split("##");
                            document.location.href = "/covid-19/requests/covid-19-edit-request.php?id=" + data[0] + "&c=" + data[1];
                        <?php } ?>
                    }
                });
            $.unblockUI();
        }
    }

    function insertSampleCode(formId, covid19SampleId, sampleCode, sampleCodeKey, sampleCodeFormat, countryId, sampleCollectionDate, provinceCode = null, provinceId = null) {
        $.blockUI();
        $.post("/covid-19/requests/insert-sample.php", {
                sampleCode: $("#" + sampleCode).val(),
                sampleCodeKey: $("#" + sampleCodeKey).val(),
                sampleCodeFormat: $("#" + sampleCodeFormat).val(),
                countryId: countryId,
                sampleCollectionDate: $("#" + sampleCollectionDate).val(),
                provinceCode: provinceCode,
                provinceId: provinceId
            },
            function(data) {

                if (data > 0) {
                    $.unblockUI();
                    document.getElementById("covid19SampleId").value = data;
                    document.getElementById(formId).submit();
                } else {
                    $.unblockUI();
                    //$("#sampleCollectionDate").val('');
                    sampleCodeGeneration();
                    alert("We could not save this form. Please try saving again.");
                }
            });
    }

    function calculateAgeInYears() {
        var dateOfBirth = moment($("#patientDob").val(), "DD-MMM-YYYY");
        $("#patientAge").val(moment().diff(dateOfBirth, 'years'));
    }
</script>
<?php

include_once(APPLICATION_PATH . '/footer.php');
