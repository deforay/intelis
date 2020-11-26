<?php
ob_start();
$title = "Enter Hepatitis Result";
#require_once('../../startup.php');
include_once(APPLICATION_PATH . '/header.php');



$facilitiesDb = new \Vlsm\Models\Facilities($db);
$userDb = new \Vlsm\Models\Users($db);
$hepatitisDb = new \Vlsm\Models\Hepatitis($db);

$hepatitisResults = $hepatitisDb->getHepatitisResults();
$testReasonResults = $hepatitisDb->getHepatitisReasonsForTesting();
$healthFacilities = $facilitiesDb->getHealthFacilities('hepatitis');
$testingLabs = $facilitiesDb->getTestingLabs('hepatitis');


$id = base64_decode($_GET['id']);

//get import config
$importQuery = "SELECT * FROM import_config WHERE `status` = 'active'";
$importResult = $db->query($importQuery);

$userQuery = "SELECT * FROM user_details WHERE `status` = 'active'";
$userResult = $db->rawQuery($userQuery);

$pdQuery = "SELECT * FROM province_details";
$pdResult = $db->query($pdQuery);

$id = base64_decode($_GET['id']);

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

$hepatitisQuery = "SELECT * FROM form_hepatitis where hepatitis_id=$id";
$hepatitisInfo = $db->rawQueryOne($hepatitisQuery);

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
$specimenResult = array();
$specimenTypeResult = $general->fetchDataFromTable('r_hepatitis_sample_type', "status = 'active'");
foreach ($specimenTypeResult as $name) {
    $specimenResult[$name['sample_id']] = ucwords($name['sample_name']); 
}
$disable = "disabled = 'disabled'";
?>
<style>
	.disabledForm {
		background: #efefef;
	}

	:disabled,
	.disabledForm .input-group-addon {
		background: none !important;
		border: none !important;
	}

	.ui_tpicker_second_label {
		display: none !important;
	}

	.ui_tpicker_second_slider {
		display: none !important;
	}

	.ui_tpicker_millisec_label {
		display: none !important;
	}

	.ui_tpicker_millisec_slider {
		display: none !important;
	}

	.ui_tpicker_microsec_label {
		display: none !important;
	}

	.ui_tpicker_microsec_slider {
		display: none !important;
	}

	.ui_tpicker_timezone_label {
		display: none !important;
	}

	.ui_tpicker_timezone {
		display: none !important;
	}

	.ui_tpicker_time_input {
		width: 100%;
	}
</style>
<?php

$fileArray = array(
    1 => 'forms/update-southsudan-result.php',
    2 => 'forms/update-zimbabwe-result.php',
    3 => 'forms/update-drc-result.php',
    4 => 'forms/update-zambia-result.php',
    5 => 'forms/update-png-result.php',
    6 => 'forms/update-who-result.php',
    7 => 'forms/update-rwanda-result.php',
    8 => 'forms/update-angola-result.php',
);

if (file_exists($fileArray[$arr['vl_form']])) {
    require_once($fileArray[$arr['vl_form']]);
} else {
    require_once('forms/update-who-result.php');
}
?>

<script>
	changeReject($('#isSampleRejected').val());

	$(document).ready(function() {
		$('.date').datepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "hh:mm TT",
			maxDate: "Today",
			yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
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
<?php
include(APPLICATION_PATH . '/footer.php');
?>