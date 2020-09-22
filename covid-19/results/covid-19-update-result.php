<?php
ob_start();
$title = "Enter Covid-19 Result";
#require_once('../../startup.php');
include_once(APPLICATION_PATH . '/header.php');



$facilitiesDb = new \Vlsm\Models\Facilities($db);
$userDb = new \Vlsm\Models\Users($db);

$labTechnicians = $userDb->getActiveUserInfo();
$healthFacilities = $facilitiesDb->getHealthFacilities('covid19');
$testingLabs = $facilitiesDb->getTestingLabs('covid19');


$id = base64_decode($_GET['id']);

//get import config
$importQuery = "SELECT * FROM import_config WHERE `status` = 'active'";
$importResult = $db->query($importQuery);


$userQuery = "SELECT * FROM user_details WHERE `status` = 'active'";
$userResult = $db->rawQuery($userQuery);


//sample rejection reason
$rejectionQuery = "SELECT * FROM r_covid19_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);
//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_covid19_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
//sample status
$statusQuery = "SELECT * FROM r_sample_status WHERE `status` = 'active' AND status_id NOT IN(9,8,6)";
$statusResult = $db->rawQuery($statusQuery);

$pdQuery = "SELECT * FROM province_details";
$pdResult = $db->query($pdQuery);

$sQuery = "SELECT * FROM r_covid19_sample_type WHERE `status`='active'";
$specimenTypeResult = $db->query($sQuery);


$id = base64_decode($_GET['id']);
$covid19Query = "SELECT * FROM form_covid19 where covid19_id=$id";
$covid19Info = $db->rawQueryOne($covid19Query);

$covid19TestQuery = "SELECT * FROM covid19_tests WHERE covid19_id=$id ORDER BY test_id ASC";
$covid19TestInfo = $db->rawQuery($covid19TestQuery);

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
if ($arr['vl_form'] == 1) {
	require_once('forms/update-southsudan-result.php');
} else if ($arr['vl_form'] == 2) {
	require_once('forms/update-zimbabwe-result.php');
} else if ($arr['vl_form'] == 3) {
	require_once('forms/update-drc-result.php');
} else if ($arr['vl_form'] == 4) {
	require_once('forms/update-zambia-result.php');
} else if ($arr['vl_form'] == 5) {
	require_once('forms/update-png-result.php');
} else if ($arr['vl_form'] == 6) {
	require_once('forms/update-who-result.php');
} else if ($arr['vl_form'] == 7) {
	require_once('forms/update-rwanda-result.php');
} else if ($arr['vl_form'] == 8) {
	require_once('forms/update-angola-result.php');
}
?>

<script>
	$(document).ready(function() {
		$('#isSampleRejected').change(function(e) {
			changeReject(this.value);
		});
		$('#hasRecentTravelHistory').change(function(e) {
			changeHistory(this.value);
		});
		changeReject($('#isSampleRejected').val());
		changeReject($('#isSampleRejected').val());
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

		//$('.date').mask('99-aaa-9999');
		//$('.dateTime').mask('99-aaa-9999 99:99');
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

	function changeReject(val) {
		if (val == 'yes') {
			$('.show-rejection').show();
			$('.test-name-table-input').prop('disabled', true);
			$('.test-name-table').addClass('disabled');
			$('#sampleRejectionReason,#rejectionDate').addClass('isRequired');
			$('#sampleTestedDateTime,#result,.test-name-table-input').removeClass('isRequired');
			$('#result').prop('disabled', true);
			$('#sampleRejectionReason').prop('disabled', false);
		} else if (val == 'no') {
			$('#rejectionDate').val('');
			$('.show-rejection').hide();
			$('.test-name-table-input').prop('disabled', false);
			$('.test-name-table').removeClass('disabled');
			$('#sampleRejectionReason,#rejectionDate').removeClass('isRequired');
			$('#sampleTestedDateTime,#result,.test-name-table-input').addClass('isRequired');
			$('#result').prop('disabled', false);
			$('#sampleRejectionReason').prop('disabled', true);
		}
	}
</script>


<?php
include(APPLICATION_PATH . '/footer.php');
?>