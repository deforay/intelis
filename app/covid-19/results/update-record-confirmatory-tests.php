<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;


$title = "Enter Covid-19 Result";

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$arr = $general->getGlobalConfig();

// get instruments
$importQuery = "SELECT * FROM instruments WHERE status = 'active'";
$importResult = $db->query($importQuery);

$fResult = $facilitiesService->getAllFacilities();

$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);

//get labs
$lResult = $facilitiesService->getAllFacilities(2);

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_covid19_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);
//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_covid19_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
//sample status
$statusQuery = "SELECT * FROM r_sample_status where status = 'active' AND status_id NOT IN(9,8,6)";
$statusResult = $db->rawQuery($statusQuery);

$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
$pdResult = $db->query($pdQuery);

$sQuery = "SELECT * from r_covid19_sample_type where status='active'";
$specimenTypeResult = $db->query($sQuery);


// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

$covid19Query = "SELECT * from form_covid19 where covid19_id=?";
$covid19Info = $db->rawQueryOne($covid19Query, [$id]);

$covid19TestQuery = "SELECT * from covid19_tests where covid19_id=$id ORDER BY test_id ASC";
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
$forms = [
	COUNTRY\SOUTH_SUDAN => 'forms/update-southsudan-result.php',
	COUNTRY\SIERRA_LEONE => 'forms/update-sierraleone-result.php',
	COUNTRY\DRC => 'forms/update-drc-result.php',
	COUNTRY\CAMEROON => 'forms/update-cameroon-result.php',
	COUNTRY\PNG => 'forms/update-png-result.php',
	COUNTRY\WHO => 'forms/update-who-result.php',
	COUNTRY\RWANDA => 'forms/update-rwanda-result.php'
];

if (isset($forms[$arr['vl_form']])) {
	require_once($forms[$arr['vl_form']]);
}
?>

<script>
	$(document).ready(function () {
		$('#isSampleRejected').change(function (e) {
			changeReject(this.value);
		});
		$('#hasRecentTravelHistory').change(function (e) {
			changeHistory(this.value);
		});





		$('#sampleReceivedDate').datetimepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
			timeFormat: "HH:mm",
			maxDate: "Today",
			onChangeMonthYear: function (year, month, widget) {
				setTimeout(function () {
					$('.ui-datepicker-calendar').show();
				});
			},
			onSelect: function (e) {
				$('#sampleTestedDateTime').val('');
				$('#sampleTestedDateTime').datetimepicker('option', 'minDate', e);
			},
			yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
		}).click(function () {
			$('.ui-datepicker-calendar').show();
		});

		//$('.date').mask('<?= $_SESSION['jsDateFormatMask'] ?? '99-aaa-9999' ?>');
		//$('.dateTime').mask('<?= $_SESSION['jsDateFormatMask'] ?? '99-aaa-9999' ?> 99:99');
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
			checkPostive();
		}
	}
</script>


<?php
require_once APPLICATION_PATH . '/footer.php';
