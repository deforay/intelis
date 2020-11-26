<?php
ob_start();
$title = "Hepatitis | Edit Request";
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
</style>



<?php

$id = base64_decode($_GET['id']);
$labFieldDisabled = '';


$facilitiesDb = new \Vlsm\Models\Facilities($db);
$userDb = new \Vlsm\Models\Users($db);
$hepatitisDb = new \Vlsm\Models\Hepatitis($db);

$hepatitisResults = $hepatitisDb->getHepatitisResults();
$testReasonResults = $hepatitisDb->getHepatitisReasonsForTesting();
$healthFacilities = $facilitiesDb->getHealthFacilities('hepatitis');
$testingLabs = $facilitiesDb->getTestingLabs('hepatitis');

// Comorbidity
$comorbidityData = array();
$comorbidityQuery = "SELECT DISTINCT comorbidity_id, comorbidity_name FROM r_hepatitis_comorbidities WHERE comorbidity_status ='active'";
$comorbidityResult = $db->rawQuery($comorbidityQuery);
foreach($comorbidityResult as $comorbidity){
    $comorbidityData[$comorbidity['comorbidity_id']] = ucwords($comorbidity['comorbidity_name']);
}
$comorbidityInfo = $hepatitisDb->getComorbidityByHepatitisId($id);

// Risk Factors
$riskFactorsData = array();
$riskFactorsQuery = "SELECT DISTINCT riskfactor_id, riskfactor_name FROM r_hepatitis_rick_factors WHERE riskfactor_status ='active'";
$riskFactorsResult = $db->rawQuery($riskFactorsQuery);
foreach($riskFactorsResult as $riskFactors){
    $riskFactorsData[$riskFactors['riskfactor_id']] = ucwords($riskFactors['riskfactor_name']);
}
$riskFactorsInfo = $hepatitisDb->getRiskFactorsByHepatitisId($id);

//$id = ($_GET['id']);
$hepatitisQuery = "SELECT * from form_hepatitis where hepatitis_id=$id";
$hepatitisInfo = $db->rawQueryOne($hepatitisQuery);

if ($arr['hepatitis_sample_code'] == 'auto' || $arr['hepatitis_sample_code'] == 'auto2' || $arr['hepatitis_sample_code'] == 'alphanumeric') {
    $sampleClass = '';
    $maxLength = '';
    if ($arr['hepatitis_max_length'] != '' && $arr['hepatitis_sample_code'] == 'alphanumeric') {
        $maxLength = $arr['hepatitis_max_length'];
        $maxLength = "maxlength=" . $maxLength;
    }
} else {
    $sampleClass = 'checkNum';
    $maxLength = '';
    if ($arr['hepatitis_max_length'] != '') {
        $maxLength = $arr['hepatitis_max_length'];
        $maxLength = "maxlength=" . $maxLength;
    }
}


if (isset($hepatitisInfo['sample_collection_date']) && trim($hepatitisInfo['sample_collection_date']) != '' && $hepatitisInfo['sample_collection_date'] != '0000-00-00 00:00:00') {
    $sampleCollectionDate = $hepatitisInfo['sample_collection_date'];
    $expStr = explode(" ", $hepatitisInfo['sample_collection_date']);
    $hepatitisInfo['sample_collection_date'] = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
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
    $rejectionReason .= '<optgroup label="' . ucwords($type['rejection_type']) . '">';
    foreach ($rejectionResult as $reject) {
        if ($type['rejection_type'] == $reject['rejection_type']) {
            $selected = (isset($hepatitisInfo['reason_for_sample_rejection']) && $hepatitisInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id'])?"selected='selected'":"";
            $rejectionReason .= '<option value="' . $reject['rejection_reason_id'] . '" '.$selected.'>' . ucwords($reject['rejection_reason_name']) . '</option>';
        }
    }
    $rejectionReason .= '</optgroup>';
}
// speciment Type
$specimenResult = array();
$specimenTypeResult = $general->fetchDataFromTable('r_hepatitis_sample_type', "status = 'active'");
foreach ($specimenTypeResult as $name) {
    $specimenResult[$name['sample_id']] = ucwords($name['sample_name']); 
}
// Import machine config
$testPlatformResult = $general->getTestingPlatforms('hepatitis');
foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name']] = $row['machine_name'];
}
$fileArray = array(
    1 => 'forms/edit-southsudan.php',
    2 => 'forms/edit-zimbabwe.php',
    3 => 'forms/edit-drc.php',
    4 => 'forms/edit-zambia.php',
    5 => 'forms/edit-png.php',
    6 => 'forms/edit-who.php',
    7 => 'forms/edit-rwanda.php',
    8 => 'forms/edit-angola.php',
);

if (file_exists($fileArray[$arr['vl_form']])) {
    require_once($fileArray[$arr['vl_form']]);
} else {
    require_once('forms/edit-who.php');
}

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
                        <?php if (isset($sarr['user_type']) && ($sarr['user_type'] == 'remoteuser' || $sarr['user_type'] == 'standalone')) { ?>
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
            timeFormat: "hh:mm TT",
            maxDate: "Today",
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
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
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
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
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
            $('#sampleTestedDateTime').addClass('isRequired');
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
            testType: 'eid'
        },
        function(data) {
            $('#machineName').html('');
            if (data != "") {
                $('#machineName').append(data);
            }
        });
    }
</script>
<?php include_once(APPLICATION_PATH . '/footer.php');
