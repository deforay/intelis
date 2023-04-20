<?php

use App\Models\Facilities;
use App\Models\General;
use App\Models\Hepatitis;
use App\Models\Users;


$title = "Enter Hepatitis Result";

require_once(APPLICATION_PATH . '/header.php');



$general = new General();
$facilitiesDb = new Facilities();
$userDb = new Users();
$hepatitisDb = new Hepatitis();

$hepatitisResults = $hepatitisDb->getHepatitisResults();
$testReasonResults = $hepatitisDb->getHepatitisReasonsForTesting();
$healthFacilities = $facilitiesDb->getHealthFacilities('hepatitis');
$testingLabs = $facilitiesDb->getTestingLabs('hepatitis');


$id = base64_decode($_GET['id']);

//get import config
$importQuery = "SELECT * FROM instruments WHERE `status` = 'active'";
$importResult = $db->query($importQuery);

$userQuery = "SELECT * FROM user_details WHERE `status` like 'active' ORDER BY user_name";
$userResult = $db->rawQuery($userQuery);

$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
$pdResult = $db->query($pdQuery);

$id = base64_decode($_GET['id']);

// Comorbidity
$comorbidityData = $hepatitisDb->getHepatitisComorbidities();
$comorbidityInfo = $hepatitisDb->getComorbidityByHepatitisId($id);

// Risk Factors
$riskFactorsData = $hepatitisDb->getHepatitisRiskFactors();
$riskFactorsInfo = $hepatitisDb->getRiskFactorsByHepatitisId($id);

$hepatitisQuery = "SELECT * FROM form_hepatitis where hepatitis_id=?";
$hepatitisInfo = $db->rawQueryOne($hepatitisQuery, array($id));

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

$disable = "disabled = 'disabled'";


// Import machine config
$testPlatformResult = $general->getTestingPlatforms('hepatitis');
foreach ($testPlatformResult as $row) {
	$testPlatformList[$row['machine_name']] = $row['machine_name'];
}

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
	7 => 'forms/update-rwanda-result.php'
);

require($fileArray[$arr['vl_form']]);

?>

<script>
	changeReject($('#isSampleRejected').val());

	$(document).ready(function() {
		$('.date').datepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			maxDate: "Today",
			yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
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
			$('#sampleTestedDateTime').addClass('isRequired');
			$('#result').prop('disabled', false);
			$('#sampleRejectionReason').prop('disabled', true);
		}
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
<?php
require_once(APPLICATION_PATH . '/footer.php');
