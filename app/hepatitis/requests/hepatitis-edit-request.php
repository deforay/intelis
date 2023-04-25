<?php

use App\Services\FacilitiesService;
use App\Services\HepatitisService;
use App\Services\UserService;
use App\Utilities\DateUtils;


$title = "Hepatitis | Edit Request";

require_once(APPLICATION_PATH . '/header.php');
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

$id = base64_decode($_GET['id']);
$labFieldDisabled = '';


$facilitiesDb = new FacilitiesService();
$userDb = new UserService();
$hepatitisDb = new HepatitisService();

$hepatitisResults = $hepatitisDb->getHepatitisResults();
$testReasonResults = $hepatitisDb->getHepatitisReasonsForTesting();
$healthFacilities = $facilitiesDb->getHealthFacilities('hepatitis');
$testingLabs = $facilitiesDb->getTestingLabs('hepatitis');
$facilityMap = $facilitiesDb->getUserFacilityMap($_SESSION['userId']);
$userResult = $userDb->getActiveUsers($facilityMap);
$labTechniciansResults = [];
foreach ($userResult as $user) {
    $labTechniciansResults[$user['user_id']] = ($user['user_name']);
}

// Comorbidity
$comorbidityData = $hepatitisDb->getHepatitisComorbidities();
$comorbidityInfo = $hepatitisDb->getComorbidityByHepatitisId($id);

// Risk Factors
$riskFactorsData = $hepatitisDb->getHepatitisRiskFactors();
$riskFactorsInfo = $hepatitisDb->getRiskFactorsByHepatitisId($id);


//$id = ($_GET['id']);
$hepatitisQuery = "SELECT * FROM form_hepatitis where hepatitis_id=?";
$hepatitisInfo = $db->rawQueryOne($hepatitisQuery, array($id));

if ($arr['hepatitis_sample_code'] == 'auto' || $arr['hepatitis_sample_code'] == 'auto2' || $arr['hepatitis_sample_code'] == 'alphanumeric') {
    $sampleClass = '';
    $maxLength = '';
    if ($arr['hepatitis_max_length'] != '' && $arr['hepatitis_sample_code'] == 'alphanumeric') {
        $maxLength = $arr['hepatitis_max_length'];
        $maxLength = "maxlength=" . $maxLength;
    }
} else {
    $sampleClass = '';
    $maxLength = '';
    if ($arr['hepatitis_max_length'] != '') {
        $maxLength = $arr['hepatitis_max_length'];
        $maxLength = "maxlength=" . $maxLength;
    }
}


if (isset($hepatitisInfo['sample_collection_date']) && trim($hepatitisInfo['sample_collection_date']) != '' && $hepatitisInfo['sample_collection_date'] != '0000-00-00 00:00:00') {
    $sampleCollectionDate = $hepatitisInfo['sample_collection_date'];
    $expStr = explode(" ", $hepatitisInfo['sample_collection_date']);
    $hepatitisInfo['sample_collection_date'] = DateUtils::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
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
$specimenResult = $hepatitisDb->getHepatitisSampleTypes();

// Import machine config
$testPlatformResult = $general->getTestingPlatforms('hepatitis');
$testPlatformList = [];
foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name']] = $row['machine_name'];
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

        $('#sampleTestedDateTime').datetimepicker({
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
        var dateOfBirth = moment($("#patientDob").val(), "DD-MMM-YYYY");
        $("#patientAge").val(moment().diff(dateOfBirth, 'years'));
    }

    function getMachine(value) {
        $.post("/import-configs/get-config-machine-by-config.php", {
                configName: value,
                machine: <?php echo !empty($hepatitisInfo['import_machine_name']) ? $hepatitisInfo['import_machine_name']  : '""'; ?>,
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
<?php require_once(APPLICATION_PATH . '/footer.php');
