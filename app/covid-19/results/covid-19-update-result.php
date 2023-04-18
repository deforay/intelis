<?php

use App\Models\Facilities;
use App\Models\Users;
use App\Utilities\DateUtils;

ob_start();
$title = _("Enter Covid-19 Result");

require_once(APPLICATION_PATH . '/header.php');



$facilitiesDb = new Facilities();
$usersModel = new Users();

$healthFacilities = $facilitiesDb->getHealthFacilities('covid19');
$testingLabs = $facilitiesDb->getTestingLabs('covid19');

$facilityMap = $facilitiesDb->getUserFacilityMap($_SESSION['userId']);
$userResult = $usersModel->getActiveUsers($facilityMap);
$labTechniciansResults = array();
foreach ($userResult as $user) {
	$labTechniciansResults[$user['user_id']] = ($user['user_name']);
}

$id = base64_decode($_GET['id']);

//get import config
$importQuery = "SELECT * FROM instruments WHERE `status` = 'active'";
$importResult = $db->query($importQuery);


$userQuery = "SELECT * FROM user_details WHERE `status` like 'active' ORDER BY user_name";
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

$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
$pdResult = $db->query($pdQuery);

$sQuery = "SELECT * FROM r_covid19_sample_type WHERE `status`='active'";
$specimenTypeResult = $db->query($sQuery);


$id = base64_decode($_GET['id']);
$covid19Query = "SELECT * FROM form_covid19 where covid19_id=?";
$covid19Info = $db->rawQueryOne($covid19Query, array($id));

$covid19TestQuery = "SELECT * FROM covid19_tests WHERE covid19_id=$id ORDER BY test_id ASC";
$covid19TestInfo = $db->rawQuery($covid19TestQuery);

$disable = "disabled = 'disabled'";
if (isset($vlQueryInfo['result_reviewed_datetime']) && trim($vlQueryInfo['result_reviewed_datetime']) != '' && $vlQueryInfo['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", $vlQueryInfo['result_reviewed_datetime']);
	$vlQueryInfo['result_reviewed_datetime'] = DateUtils::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['result_reviewed_datetime'] = '';
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
if ($arr['vl_form'] == 1) {
	require('forms/update-southsudan-result.php');
} else if ($arr['vl_form'] == 2) {
	require('forms/update-sierraleone-result.php');
} else if ($arr['vl_form'] == 3) {
	require('forms/update-drc-result.php');
} else if ($arr['vl_form'] == 4) {
	require('forms/update-zambia-result.php');
} else if ($arr['vl_form'] == 5) {
	require('forms/update-png-result.php');
} else if ($arr['vl_form'] == 6) {
	require('forms/update-who-result.php');
} else if ($arr['vl_form'] == 7) {
	require('forms/update-rwanda-result.php');
} else if ($arr['vl_form'] == 8) {
	require('forms/update-angola-result.php');
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

		//$('.date').mask('99-aaa-9999');
		//$('.dateTime').mask('99-aaa-9999 99:99');
		<?php if (isset($covid19Info['result']) && $covid19Info['result'] != "") { ?>
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
		<?php } ?>
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
			$(".result-optional").removeClass("isRequired");
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
require_once(APPLICATION_PATH . '/footer.php');