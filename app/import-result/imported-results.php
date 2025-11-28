<?php

use App\Services\UsersService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

require_once APPLICATION_PATH . '/header.php';


// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);


$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);

$userResult = $usersService->getActiveUsers();

$tQuery = "SELECT module, sample_review_by FROM temp_sample_import WHERE imported_by =? limit 1";

$tResult = $db->rawQueryOne($tQuery, [$_SESSION['userId']]);



$module = $_GET['t'];
$machine = base64_decode((string) $_GET['machine']);


$condition = " instrument_id = '$machine'";
$getMachineInfo = $general->getDataByTableAndFields('instruments', ['approved_by', 'reviewed_by'], false, $condition);

$approvedByAttr = json_decode((string) $getMachineInfo[0]['approved_by']);
$reviewedByAttr = json_decode((string) $getMachineInfo[0]['reviewed_by']);
$reviewedBy = $reviewedByAttr->$module;
$approvedBy = $approvedByAttr->$module;

if (!empty($tResult['sample_review_by'])) {
	$reviewBy = $tResult['sample_review_by'];
} elseif (!empty($reviewedBy)) {
	//$reviewBy = $_SESSION['userId'];
	$reviewBy = $reviewedBy;
} else {
	$reviewBy = $_SESSION['userId'];
}
$approveBy = empty($approvedBy) ? $_SESSION['userId'] : $approvedBy;

$arr = $general->getGlobalConfig();
$errorInImport = false;
if ($module == 'vl') {
	$rejectionTypeQuery = "SELECT DISTINCT rejection_type
							FROM r_vl_sample_rejection_reasons
							WHERE rejection_reason_status ='active'";
	$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
	//sample rejection reason
	$rejectionQuery = "SELECT *
							FROM r_vl_sample_rejection_reasons
							WHERE rejection_reason_status = 'active'";
	$rejectionResult = $db->rawQuery($rejectionQuery);
} elseif ($module == 'eid') {
	$rejectionTypeQuery = "SELECT DISTINCT rejection_type
								FROM r_eid_sample_rejection_reasons
								WHERE rejection_reason_status ='active'";
	$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
	//sample rejection reason
	$rejectionQuery = "SELECT *
						FROM r_eid_sample_rejection_reasons
						WHERE rejection_reason_status = 'active'";
	$rejectionResult = $db->rawQuery($rejectionQuery);
} elseif ($module == 'covid19' || $module == 'covid-19') {
	$rejectionTypeQuery = "SELECT DISTINCT rejection_type
								FROM r_covid19_sample_rejection_reasons
								WHERE rejection_reason_status ='active'";
	$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
	//sample rejection reason
	$rejectionQuery = "SELECT *
						FROM r_covid19_sample_rejection_reasons
						WHERE rejection_reason_status = 'active'";
	$rejectionResult = $db->rawQuery($rejectionQuery);
} elseif ($module == 'hepatitis') {
	$rejectionTypeQuery = "SELECT DISTINCT rejection_type
								FROM r_hepatitis_sample_rejection_reasons
								WHERE rejection_reason_status ='active'";
	$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
	//sample rejection reason
	$rejectionQuery = "SELECT *
						FROM r_hepatitis_sample_rejection_reasons
						WHERE rejection_reason_status = 'active'";
	$rejectionResult = $db->rawQuery($rejectionQuery);
} else {
	$errorInImport = true;
}


$rejectionReason = '<option value="">-- Select --</option>';
foreach ($rejectionTypeResult as $type) {
	$rejectionReason .= '<optgroup label="' . ($type['rejection_type']) . '">';
	foreach ($rejectionResult as $reject) {
		if ($type['rejection_type'] == $reject['rejection_type']) {
			$rejectionReason .= '<option value="' . $reject['rejection_reason_id'] . '">' . ($reject['rejection_reason_name']) . '</option>';
		}
	}
	$rejectionReason .= '</optgroup>';
}

?>
<style>
	.dataTables_wrapper {
		position: relative;
		clear: both;
		overflow-x: visible !important;
		overflow-y: visible !important;
		padding: 15px 0 !important;
	}

	.sampleType select {
		max-width: 100px;
		width: 100px !important
	}

	#rejectReasonDiv {
		border: 1px solid #ecf0f5;
		box-shadow: 3px 3px 15px #000;
		background-color: #ecf0f5;
		width: 50%;
		display: none;
		padding: 10px;
		border-radius: 10px;

	}

	.arrow-right {
		width: 0;
		height: 0;
		border-top: 15px solid transparent;
		border-bottom: 15px solid transparent;
		border-left: 15px solid #ecf0f5;
		position: absolute;
		left: 100%;
		top: 24px;
	}

	/* Date picker wrapper and input styling */
	.date-picker-wrapper {
		position: relative;
		display: inline-block;
		width: 150px;
		vertical-align: top;
	}

	.test-date-picker {
		width: 100% !important;
		height: 32px;
		font-size: 12px;
		padding: 6px 28px 6px 8px;
		border: 1px solid #ddd;
		border-radius: 4px;
		background-color: #fff;
		cursor: pointer;
		box-sizing: border-box;
		transition: border-color 0.2s ease;
	}

	.test-date-picker:hover {
		border-color: #337ab7;
	}

	.test-date-picker:focus {
		border-color: #337ab7;
		outline: none;
		box-shadow: 0 0 0 2px rgba(51, 122, 183, 0.2);
	}

	/* Placeholder styling */
	.test-date-picker::placeholder {
		color: #999;
		font-style: italic;
	}

	/* Clear button styling */
	.clear-date-btn {
		position: absolute;
		right: 4px;
		top: 50%;
		transform: translateY(-50%);
		width: 22px;
		height: 22px;
		border: none;
		background: #dc3545;
		color: white;
		font-size: 14px;
		font-weight: bold;
		cursor: pointer;
		border-radius: 3px;
		z-index: 10;
		line-height: 1;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: background-color 0.2s ease;
	}

	.clear-date-btn:hover {
		background: #c82333;
		transform: translateY(-50%) scale(1.05);
	}

	.clear-date-btn:active {
		transform: translateY(-50%) scale(0.95);
	}

	/* Style for missing test dates */
	.test-date-picker[data-missing="true"],
	.missing-test-date-flag[value="1"]~.test-date-picker {
		border-color: #dc3545;
		background-color: #fff5f5;
	}

	.test-date-picker[data-missing="true"]::placeholder {
		color: #dc3545;
		font-weight: 500;
	}

	/* Style for filled date fields */
	.test-date-picker[data-has-date="true"] {
		background-color: #f8f9fa;
		font-weight: 500;
		color: #495057;
	}

	/* Table cell alignment */
	td .date-picker-wrapper {
		margin: 0 auto;
	}

	/* Responsive adjustments */
	@media (max-width: 768px) {
		.date-picker-wrapper {
			width: 140px;
		}

		.test-date-picker {
			font-size: 11px;
			padding: 4px 24px 4px 6px;
			height: 28px;
		}

		.clear-date-btn {
			width: 18px;
			height: 18px;
			font-size: 12px;
		}
	}

	/* Loading state */
	.test-date-picker.loading {
		background-image: url('data:image/svg+xml;charset=utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="%23ddd" stroke-width="2"/><path d="M12 2a10 10 0 0 1 10 10" stroke="%23337ab7" stroke-width="2" stroke-linecap="round"/></svg>');
		background-repeat: no-repeat;
		background-position: calc(100% - 30px) center;
		background-size: 16px;
		animation: spin 1s linear infinite;
	}

	@keyframes spin {
		from {
			transform: rotate(0deg);
		}

		to {
			transform: rotate(360deg);
		}
	}

	/* DateRangePicker dropdown styling */
	.daterangepicker {
		border: 1px solid #ddd;
		border-radius: 6px;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
		padding: 0;
	}

	.daterangepicker .calendar-table {
		background: white;
	}

	.daterangepicker .calendar-table th,
	.daterangepicker .calendar-table td {
		font-size: 12px;
		padding: 6px;
	}

	.daterangepicker .ranges li {
		font-size: 12px;
		padding: 6px 12px;
	}

	.daterangepicker .drp-buttons {
		border-top: 1px solid #eee;
		padding: 8px;
	}

	.daterangepicker .btn {
		font-size: 12px;
		padding: 4px 12px;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1>
			<?= _translate("Imported Results"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Test Request</li>
		</ol>
	</section>
	<!-- for sample rejection -->
	<div id="rejectReasonDiv">
		<a href="javascript:void(0)" style="float:right;color:red;" title="close"
			onclick="hideReasonDiv('rejectReasonDiv')"><em class="fa-solid fa-xmark"></em></a>
		<div class="arrow-right"></div>
		<input type="hidden" name="statusDropDownId" id="statusDropDownId" />
		<h3 style="color:red;">
			<?= _translate("Choose Rejection Reason"); ?>
		</h3>
		<select name="rejectionReason" id="rejectionReason" class="form-control" title="Please choose reason"
			onchange="updateRejectionReasonStatus(this);">
			<?php echo $rejectionReason; ?>
		</select>

	</div>
	<!-- Main content -->

	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<?php if (!$errorInImport) { ?>

						<div class="box-header without-border">
							<div class="box-header with-border">
								<ul style="list-style: none;float: right;">
									<li style="float:left;margin-right:40px;"><em class="fa-solid fa-exclamation-circle"
											style="color:#e8000b;"></em>
										<?= _translate("Sample ID not from VLSM"); ?>
									</li>
									<li style="float:left;margin-right:40px;"><em class="fa-solid fa-exclamation-circle"
											style="color:#FFC300;"></em>
										<?= _translate("Result already exists for this sample"); ?>
									</li>
									<li style="float:left;margin-right:40px;"><em class="fa-solid fa-exclamation-circle"
											style="color:#337ab7;"></em>
										<?= _translate("Result for Sample ID from VLSM"); ?>
									</li>
									<li style="float:left;margin-right:20px;"><em class="fa-solid fa-exclamation-circle"
											style="color:#E0B0FF;"></em>
										<?= _translate("Control/Calibrator"); ?>
									</li>
								</ul>
							</div>
						</div>
						<!-- /.box-header -->
						<div class="box-body">
							<div class="col-md-6 col-sm-6">
								<input type="button" onclick="acceptAllSamples();"
									value="<?= _translate("Accept All Samples"); ?>" class="btn btn-success btn-sm">
								<br><strong class="text-danger">
									<?= _translate("Only accepts samples that do not have status field already selected"); ?>
								</strong>
							</div>
							<table aria-describedby="table" id="importedResultsTable"
								class="table table-bordered table-striped" aria-hidden="true">
								<thead>
									<tr>
										<th style="width: 23%;">
											<?= _translate("Sample ID"); ?>
										</th>
										<th style="width: 11%;">
											<?= _translate("Sample Collection Date"); ?>
										</th>
										<th style="width: 10%;">
											<?= _translate("Sample Test Date"); ?>
										</th>
										<th style="width: 10%;">
											<?= _translate("Clinic/Site Name"); ?>
										</th>
										<th style="width: 10%;">
											<?= _translate("Batch Code"); ?>
										</th>
										<th style="width: 10%;">
											<?= _translate("Lot Number"); ?>
										</th>
										<th style="width: 10%;">
											<?= _translate("Lot Expiry Date"); ?>
										</th>
										<th style="width: 10%;">
											<?= _translate("Rejection Reason"); ?>
										</th>
										<th style="max-width: 9%;">
											<?= _translate("Sample Type"); ?>
										</th>
										<th style="width: 9%;">
											<?= _translate("Result"); ?>
										</th>
										<th style="width: 9%;">
											<?= _translate("Status"); ?>
										</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td colspan="11" class="dataTables_empty">Loading data from server</td>
									</tr>
								</tbody>
							</table>
						</div>
						<table aria-describedby="table" class="table" aria-hidden="true"
							style="margin-left:1%;margin-top:30px;width: 100%;">
							<tr>
								<input type="hidden" name="checkedTests" id="checkedTests" />
								<input type="hidden" name="checkedTestsIdValue" id="checkedTestsIdValue" />
								<td style=" width: 30%; ">
									<strong>
										<?= _translate("Comments") ?>&nbsp;
									</strong>
									<textarea style="height: 34px;width: 100%;" class="form-control" id="comments"
										name="comments" placeholder="Comments"></textarea>
								</td>
								<td style=" width: 20%; ">
									<strong>
										<?= _translate("Tested By"); ?><span class="mandatory">*</span>&nbsp;
									</strong>
									<select name="testedBy" id="testedBy" class="select2 form-control"
										title="Please choose tested by" style="width: 100%;">
										<option value="">-- Select --</option>
										<?php
										foreach ($userResult as $uName) {
											?>
											<option value="<?php echo $uName['user_id']; ?>"><?= $uName['user_name']; ?>
											</option>
											<?php
										}
										?>
									</select>
								</td>
								<td style=" width: 20%; ">
									<strong>
										<?= _translate("Reviewed By"); ?><span class="mandatory">*</span>&nbsp;
									</strong>
									<!--<input type="text" name="reviewedBy" id="reviewedBy" class="form-control" title="Please enter Reviewed By" placeholder ="Reviewed By"/>-->
									<select name="reviewedBy" id="reviewedBy" class="form-control"
										title="Please choose reviewed by" style="width: 100%;">
										<option value="">-- Select --</option>
										<?php
										foreach ($userResult as $uName) {
											?>
											<option value="<?php echo $uName['user_id']; ?>" <?php echo ($uName['user_id'] == $reviewBy) ? "selected=selected" : ""; ?>>
												<?php echo ($uName['user_name']); ?>
											</option>
											<?php
										}
										?>
									</select>
								</td>
								<td style=" width: 20%; ">
									<strong>
										<?= _translate("Approved By"); ?><span class="mandatory">*</span>&nbsp;
									</strong>
									<!--<input type="text" name="approvedBy" id="approvedBy" class="form-control" title="Please enter Approved By" placeholder ="Approved By"/>-->
									<select name="approvedBy" id="approvedBy" class="form-control"
										title="Please choose approved by" style="width: 100%;">
										<option value="">-- Select --</option>
										<?php
										foreach ($userResult as $uName) {
											?>
											<option value="<?php echo $uName['user_id']; ?>" <?php echo ($uName['user_id'] == $approveBy) ? "selected=selected" : ""; ?>>
												<?php echo ($uName['user_name']); ?>
											</option>
											<?php
										}
										?>
									</select>
								</td>
								<td style=" width: 10%; ">
									<br>
									<input type="hidden" name="print" id="print" />
									<input type="hidden" name="module" id="module" value="<?= ($module); ?>" />
									<input type="button" onclick="submitTestStatus();" value="<?= _translate("Save"); ?>"
										class="btn btn-success btn-sm">
								</td>
							</tr>

						</table>
						<!-- /.box-body -->


					<?php } else { ?>
						<div class="box-body">
							<div class="col-md-12 col-sm-12">
								<br>
								<h3>
									<?= _translate("Either there were no records imported or you seem to have reached this page by mistake."); ?>
									<br><br>
									<?= _translate("Please contact technical support if you need assistance."); ?>
								</h3>
								<br>
								<input type="button" onclick="history.go(-1);" value="<?= _translate("Back"); ?>"
									class="btn btn-danger btn-sm">
							</div>
						</div>

					<?php } ?>
				</div>
				<!-- /.box -->
			</div>
			<!-- /.col -->
		</div>
		<!-- /.row -->
	</section>

	<!-- /.content -->
</div>

<script src="/assets/js/moment.min.js"></script>
<link rel="stylesheet" type="text/css" href="/assets/plugins/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>

<script type="text/javascript">
	function initializeDateTimePickers() {
		const systemDateFormat = '<?= $_SESSION['jsDateRangeFormat'] ?? 'DD-MMM-YYYY'; ?>';
		const systemDateTimeFormat = systemDateFormat + ' HH:mm';

		$('.test-date-picker').each(function () {
			const $input = $(this);
			const tempSampleId = $input.data('temp-sample-id');
			const customFormat = $input.data('date-format') || systemDateTimeFormat;
			const hasExistingDate = $input.val() !== '';

			// Skip if already initialized
			if ($input.hasClass('daterangepicker-initialized')) {
				return;
			}

			// Set data attributes for styling
			$input.attr('data-missing', hasExistingDate ? 'false' : 'true');
			$input.attr('data-has-date', hasExistingDate ? 'true' : 'false');

			// Create wrapper and clear button
			if (!$input.parent().hasClass('date-picker-wrapper')) {
				$input.wrap('<div class="date-picker-wrapper"></div>');

				const $clearBtn = $('<button type="button" class="clear-date-btn" title="' +
					'<?= _translate("Clear date", true); ?>' + '" aria-label="Clear date">×</button>');
				$input.parent().append($clearBtn);

				// Show/hide clear button based on whether there's a date
				if (hasExistingDate) {
					$clearBtn.show();
				} else {
					$clearBtn.hide();
				}
			}

			// Initialize daterangepicker
			$input.daterangepicker({
				singleDatePicker: true,
				timePicker: true,
				timePicker24Hour: true,
				timePickerIncrement: 30,
				locale: {
					format: customFormat,
					cancelLabel: '<?= _translate("Clear", true); ?>',
					applyLabel: '<?= _translate("Apply", true); ?>'
				},
				showDropdowns: true,
				autoApply: false,
				autoUpdateInput: false,
				opens: 'left',
				drops: 'down',
				startDate: hasExistingDate ? moment($input.val(), customFormat) : moment().hour(9).minute(0)
			});

			// Handle successful date selection
			$input.on('apply.daterangepicker', function (ev, picker) {
				const $this = $(this);
				const formattedDate = picker.startDate.format('YYYY-MM-DD HH:mm:ss');
				const displayDate = picker.startDate.format(customFormat);

				// Add loading state
				$this.addClass('loading');

				$this.val(displayDate);
				$this.attr('data-missing', 'false');
				$this.attr('data-has-date', 'true');
				$('#missingTestDateFlag' + tempSampleId).val('0');

				// Show clear button
				$this.siblings('.clear-date-btn').show();

				// Update server
				updateSampleTestDate(tempSampleId, formattedDate, $this, function () {
					// Remove loading state on completion
					$this.removeClass('loading');
				});

				// Only offer bulk update if this was previously empty
				if (!hasExistingDate) {
					offerToApplyToOtherMissingDates(displayDate, formattedDate, tempSampleId);
				}
			});

			// Handle cancel/clear from daterangepicker
			$input.on('cancel.daterangepicker', function (ev, picker) {
				const $this = $(this);

				$this.addClass('loading');
				$this.val('');
				$this.attr('data-missing', 'true');
				$this.attr('data-has-date', 'false');
				$('#missingTestDateFlag' + tempSampleId).val('1');
				$this.siblings('.clear-date-btn').hide();

				clearSampleTestDate(tempSampleId, $this, function () {
					$this.removeClass('loading');
				});
			});

			// Handle manual clear button click
			$input.siblings('.clear-date-btn').on('click', function (e) {
				e.preventDefault();
				e.stopPropagation();

				const $dateInput = $(this).siblings('.test-date-picker');

				$dateInput.addClass('loading');
				$dateInput.val('');
				$dateInput.attr('data-missing', 'true');
				$dateInput.attr('data-has-date', 'false');
				$('#missingTestDateFlag' + tempSampleId).val('1');
				$(this).hide();

				clearSampleTestDate(tempSampleId, $dateInput, function () {
					$dateInput.removeClass('loading');
				});
			});

			$input.addClass('daterangepicker-initialized');
		});
	}

	function offerToApplyToOtherMissingDates(displayDate, formattedDate, excludeTempSampleId) {
		let missingDatesCount = 0;
		let missingDateElements = [];

		$('.test-date-picker').each(function () {
			const tempSampleId = $(this).data('temp-sample-id');
			if (tempSampleId != excludeTempSampleId && $(this).val() === '') {
				missingDatesCount++;
				missingDateElements.push($(this));
			}
		});

		if (missingDatesCount > 0) {
			const baseMessage = "<?= _translate('Would you like to apply this same test date to the other samples that are missing test dates?', true); ?>";
			const detailsMessage = "<?= _translate('Test Date', true); ?>: " + displayDate + "\n" +
				"<?= _translate('Number of samples', true); ?>: " + missingDatesCount;
			const fullMessage = baseMessage + "\n\n" + detailsMessage;

			if (confirm(fullMessage)) {
				bulkUpdateSampleTestDates(missingDateElements, displayDate, formattedDate);
			}
		}
	}

	function bulkUpdateSampleTestDates(elements, displayDate, formattedDate) {
		const tempSampleIds = [];
		elements.forEach(function ($element) {
			tempSampleIds.push($element.data('temp-sample-id'));
		});

		if (tempSampleIds.length === 0) return;

		$.blockUI({
			message: '<h3><?= _translate("Updating test dates", true); ?>...<br><?= _translate("Please wait", true); ?>...</h3>'
		});

		$.post("/import-result/updateImportedSample.php", {
			bulkSampleTestDate: formattedDate,
			tempSampleIds: tempSampleIds.join(',')
		}, function (data) {
			$.unblockUI();

			if (data == "1") {
				elements.forEach(function ($element) {
					const tempSampleId = $element.data('temp-sample-id');
					$element.val(displayDate);
					$('#missingTestDateFlag' + tempSampleId).val('0');

					$element.css('border-color', '#5cb85c');
					setTimeout(function () {
						$element.css('border-color', '#ddd');
					}, 2000);
				});

				const successMsg = "<?= _translate('Successfully updated test dates for', true); ?> " + tempSampleIds.length + " <?= _translate('samples', true); ?>";
				showTemporaryMessage(successMsg, 'success');
			} else {
				alert("<?= _translate('Something went wrong! Please try again', true); ?>");
				oTable.fnDraw();
			}
		}).fail(function () {
			$.unblockUI();
			alert("<?= _translate('Network error. Please try again', true); ?>");
			oTable.fnDraw();
		});
	}

	function showTemporaryMessage(message, type) {
		const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
		const $alert = $('<div class="alert ' + alertClass + ' alert-dismissible" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;">' +
			'<button type="button" class="close" data-dismiss="alert">&times;</button>' +
			message + '</div>');

		$('body').append($alert);

		setTimeout(function () {
			$alert.fadeOut(500, function () {
				$(this).remove();
			});
		}, 3000);
	}

	function updateSampleTestDate(tempSampleId, testDate, $input, callback) {
		$.post("/import-result/updateImportedSample.php", {
			sampleTestDate: testDate,
			tempsampleId: tempSampleId
		}, function (data) {
			if (data == "1") {
				$('#missingTestDateFlag' + tempSampleId).val('0');

				// Success visual feedback
				$input.css('border-color', '#28a745');
				setTimeout(function () {
					$input.css('border-color', '');
				}, 1500);
			} else {
				alert("<?= _translate("Something went wrong! Please try again", true); ?>");
				$input.val('');
				$input.attr('data-missing', 'true');
				$input.attr('data-has-date', 'false');
			}

			if (callback) callback();
		}).fail(function () {
			alert("<?= _translate('Network error. Please try again', true); ?>");
			$input.val('');
			$input.attr('data-missing', 'true');
			$input.attr('data-has-date', 'false');

			if (callback) callback();
		});
	}

	// Updated clearSampleTestDate with callback
	function clearSampleTestDate(tempSampleId, $input, callback) {
		$.post("/import-result/updateImportedSample.php", {
			clearSampleTestDate: true,
			tempsampleId: tempSampleId
		}, function (data) {
			if (data == "1") {
				// Success visual feedback
				$input.css('border-color', '#ffc107');
				setTimeout(function () {
					$input.css('border-color', '');
				}, 1500);

				showTemporaryMessage("<?= _translate('Test date cleared', true); ?>", 'success');
			} else {
				alert("<?= _translate('Failed to clear test date', true); ?>");
			}

			if (callback) callback();
		}).fail(function () {
			alert("<?= _translate('Network error. Could not clear test date', true); ?>");

			if (callback) callback();
		});
	}

	// Modify the existing checkMissingSampleTestDate function
	function checkMissingSampleTestDate() {
		var missingTestDateFound = false;

		$('.missing-test-date-flag').each(function () {
			if ($(this).val() === '1') {
				missingTestDateFound = true;
				return false; // break loop
			}
		});

		if (missingTestDateFound) {
			alert("<?= _translate("Action Required: One or more samples do not have a test date. Please update all missing fields before submitting.", true); ?>");
			return false;
		}

		return true;
	}

	// Update the existing loadVlRequestData function's fnDrawCallback
	var oTable = null;

	function loadVlRequestData() {
		$.blockUI();
		oTable = $('#importedResultsTable').dataTable({
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			"bRetrieve": true,
			"aoColumns": [{
				"sClass": "center",
				"bSortable": false
			},
			{
				"sClass": "center",
				"bSortable": false
			},
			{
				"sClass": "center",
				"bSortable": false
			},
			{
				"sClass": "center",
				"bSortable": false
			},
			{
				"sClass": "center",
				"bSortable": false
			},
			{
				"sClass": "center",
				"bSortable": false
			},
			{
				"sClass": "center",
				"bSortable": false
			},
			{
				"sClass": "center",
				"bSortable": false
			},
			{
				"sClass": "center sampleType",
				"bSortable": false
			},
			{
				"sClass": "center",
				"bSortable": false
			},
			{
				"sClass": "center sampleType",
				"bSortable": false
			},
			],
			"iDisplayLength": 5000,
			"fnDrawCallback": function () {
				var oSettings = this.fnSettings();
				var iTotalRecords = oSettings.fnRecordsTotal();
				if (iTotalRecords == 0) {
					window.location.href = "/import-result/importedStatistics.php?t=<?= $module; ?>";
				}

				// Initialize datetime pickers after table draw
				setTimeout(function () {
					initializeDateTimePickers();
				}, 100);
			},
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/import-result/getImportedResults.php",
			"fnServerData": function (sSource, aoData, fnCallback) {
				aoData.push({
					"name": "module",
					"value": '<?= $module; ?>'
				});
				$.ajax({
					"dataType": 'json',
					"type": "POST",
					"url": sSource,
					"data": aoData,
					"success": fnCallback,
				});
			},
		});
		$.unblockUI();
	}

	var startDate = "";
	var endDate = "";
	var selectedTests = [];
	var selectedTestsIdValue = [];
	$(document).ready(function () {
		initializeDateTimePickers();
		$('#testedBy').select2({
			width: '100%',
			placeholder: "Select Tested By"
		});
		$('#reviewedBy').select2({
			width: '100%',
			placeholder: "Select Reviewed By"
		});
		$('#approvedBy').select2({
			width: '100%',
			placeholder: "Select Approved By"
		});
		loadVlRequestData();
	});


	function toggleTest(obj, sampleCode) {
		if (sampleCode == '') {
			alert("Please enter sample id");
			$("#" + obj.id).val('');
			return false;
		}
		if (obj.value == '4') {
			var confrm = confirm("<?= _translate("Do you wish to overwrite this result?"); ?>");
			if (confrm) {
				var pos = $("#" + obj.id).offset();
				$("#rejectReasonDiv").show();
				$("#rejectReasonDiv").css({
					top: Math.round(pos.top) - 30,
					position: 'absolute',
					'z-index': 1,
					right: '15%'
				});
				$("#statusDropDownId").val(obj.id);
				$(".content").css('pointer-events', 'none');
				//return false;
			} else {
				$("#" + obj.id).val('');
				return false;
			}
		} else {
			$("#rejectReasonName" + obj.id).html('');
			$("#rejectReasonDiv").hide();
		}

	}

	function updateRejectionReasonStatus(obj) {
		var rejectDropDown = $("#statusDropDownId").val();
		if (obj.value != '') {
			$("#rejectReasonName" + rejectDropDown).html(
				$("#" + obj.id + " option:selected").text() +
				'<input type="hidden" id="rejectedReasonId' + rejectDropDown + '" name="rejectedReasonId[]" value="' + obj.value + '"/><a href="javascript:void(0)" style="float:right;color:red;" title="cancel" onclick="showRejectedReasonList(' + rejectDropDown + ');"><em class="fa-solid fa-xmark"></em></a>'
			);
		} else {
			$("#rejectedReasonId" + rejectDropDown).val('');
		}
	}

	function showRejectedReasonList(postionId) {
		var pos = $("#" + postionId).offset();
		$("#rejectReasonDiv").show();
		$("#rejectReasonDiv").css({
			top: Math.round(pos.top) - 30,
			position: 'absolute',
			'z-index': 1,
			right: '15%'
		});
		$("#statusDropDownId").val(postionId);
		$(".content").css('pointer-events', 'none');
	}


	function submitTestStatus() {

		if (!checkMissingSampleTestDate()) {
			return false;
		}

		var idArray = [];
		var statusArray = [];
		var rejectReasonArray = [];
		var somethingmissing = false;

		$('[name="status[]"]').each(function () {
			if ($(this).val() == null || $(this).val() == '') {
				//console.log($(this));
				somethingmissing = true;
			}
			idArray.push($(this).attr('id'));
			statusArray.push($(this).val());
			rejectReasonArray.push($("#rejectedReasonId" + $(this).attr('id')).val());
		});

		id = idArray.join();
		status = statusArray.join();
		rejectReasonId = rejectReasonArray.join();
		comments = $("#comments").val();
		testBy = $("#testedBy").val();
		appBy = $("#approvedBy").val();
		reviewedBy = $("#reviewedBy").val();
		moduleName = $("#module").val();
		globalValue = '<?= $arr["user_review_approve"]; ?>';
		if (appBy == reviewedBy && (reviewedBy != '' && appBy != '') && globalValue == 'yes') {
			conf = confirm("<?= _translate("Same person is reviewing and approving result! Do you want to continue?", true); ?>");
			if (conf) { } else {
				return false;
			}
		} else if (appBy == reviewedBy && (reviewedBy != '' && appBy != '') && globalValue == 'no') {
			alert("<?= _translate("Same person is reviewing and approving result. This is not allowed as per your system configuration.", true); ?>");
			return false;
		}

		if (somethingmissing == true) {
			alert("<?= _translate("Please ensure that you have updated the status of all the Controls and Samples"); ?> ");
			$.unblockUI();
			return false;
		}

		if (appBy != '' && somethingmissing == false && testBy != "" && reviewedBy != "") {
			conf = confirm("<?= _translate("Are you sure you want to continue?", true); ?>");
			if (conf) {
				$.blockUI();
				$.post("/import-result/processImportedResults.php", {
					rejectReasonId: rejectReasonId,
					value: id,
					status: status,
					comments: comments,
					testBy: testBy,
					appBy: appBy,
					module: moduleName,
					reviewedBy: reviewedBy,
					format: "html"
				},
					function (data) {
						$.unblockUI();
						if ($("#print").val() == 'print') {
							convertSearchResultToPdf('');
						}
						if (data == 'importedStatistics.php') {
							window.location.href = "/import-result/importedStatistics.php?t=<?= $module; ?>";
						}
						oTable.fnDraw();
						selectedTests = [];
						selectedTestsIdValue = [];
						$("#checkedTests").val('');
						$("#checkedTestsIdValue").val('');
						$("#comments").val('');
					});

			} else {
				oTable.fnDraw();
			}
		} else {
			alert("<?= _translate("Please ensure you have updated the status and the approved by and reviewed by and tested by field", true); ?>");
			return false;
		}
	}

	function submitTestStatusAndPrint() {
		$("#print").val('print');
		submitTestStatus();
	}

	function updateStatus(value, status) {
		if (status != '') {
			conf = confirm("<?= _translate("Do you wish to change the status?", true); ?>");
			if (conf) {
				$.blockUI();
				$.post("/import-result/processImportedResults.php", {
					value: value,
					status: status,
					format: "html"
				},
					function (data) {
						convertSearchResultToPdf('');
						oTable.fnDraw();
						selectedTests = [];
						selectedTestsId = [];
						$("#checkedTests").val('');
						$(".countChecksPending").html(0);
					});
				$.unblockUI();
			} else {
				oTable.fnDraw();
			}
		} else {
			alert("<?= _translate("Please select result status", true); ?>");
		}
	}

	function updateSampleCode(obj, oldSampleCode, tempsampleId) {
		const $input = $(obj);
		const originalBorderColor = $input.css('border-color');

		$input.css('border-color', '#f39c12');

		$.post("/import-result/updateImportedSample.php", {
			sampleCode: obj.value,
			tempsampleId: tempsampleId
		},
			function (data) {
				if (data == "1") {
					$input.css('border-color', '#5cb85c');
					setTimeout(function () {
						$input.css('border-color', originalBorderColor);
					}, 2000);
				} else {
					$input.css('border-color', '#e74c3c');
					$input.val(oldSampleCode);
					showTemporaryMessage("<?= _translate('Failed to update sample code', true); ?>", 'error');
					setTimeout(function () {
						$input.css('border-color', originalBorderColor);
					}, 3000);
				}
			})
			.fail(function () {
				$input.css('border-color', '#e74c3c');
				$input.val(oldSampleCode);
				showTemporaryMessage("<?= _translate('Network error. Changes not saved', true); ?>", 'error');
				setTimeout(function () {
					$input.css('border-color', originalBorderColor);
				}, 3000);
			});
	}

	function updateBatchCode(obj, oldBatchCode, tempsampleId) {
		const $input = $(obj);
		const originalBorderColor = $input.css('border-color');

		$input.css('border-color', '#f39c12');

		$.post("/import-result/updateImportedSample.php", {
			batchCode: obj.value,
			tempsampleId: tempsampleId
		},
			function (data) {
				if (data == "1") {
					$input.css('border-color', '#5cb85c');
					setTimeout(function () {
						$input.css('border-color', originalBorderColor);
					}, 2000);
				} else {
					$input.css('border-color', '#e74c3c');
					$input.val(oldBatchCode);
					showTemporaryMessage("<?= _translate('Failed to update batch code', true); ?>", 'error');
					setTimeout(function () {
						$input.css('border-color', originalBorderColor);
					}, 3000);
				}
			})
			.fail(function () {
				$input.css('border-color', '#e74c3c');
				$input.val(oldBatchCode);
				showTemporaryMessage("<?= _translate('Network error. Changes not saved', true); ?>", 'error');
				setTimeout(function () {
					$input.css('border-color', originalBorderColor);
				}, 3000);
			});
	}

	function sampleToControl(obj, oldValue, tempsampleId) {
		const $select = $(obj);
		const originalBorderColor = $select.css('border-color');

		$select.css('border-color', '#f39c12');

		$.post("/import-result/updateImportedSample.php", {
			sampleType: obj.value,
			tempsampleId: tempsampleId
		},
			function (data) {
				if (data == "1") {
					$select.css('border-color', '#5cb85c');
					setTimeout(function () {
						$select.css('border-color', originalBorderColor);
					}, 2000);
				} else {
					$select.css('border-color', '#e74c3c');
					$select.val(oldValue);
					showTemporaryMessage("<?= _translate('Failed to update sample type', true); ?>", 'error');
					setTimeout(function () {
						$select.css('border-color', originalBorderColor);
					}, 3000);
				}
			})
			.fail(function () {
				$select.css('border-color', '#e74c3c');
				$select.val(oldValue);
				showTemporaryMessage("<?= _translate('Network error. Changes not saved', true); ?>", 'error');
				setTimeout(function () {
					$select.css('border-color', originalBorderColor);
				}, 3000);
			});
	}

	function sampleToControlAlert(number) {
		alert("<?= _translate("Maximum number of controls allowed as per the instrument configuration", true); ?> : " + number);
		oTable.fnDraw();
	}

	function hideReasonDiv(id) {
		$("#" + id).hide();
		$(".content").css('pointer-events', 'auto');
		if ($("#rejectionReason").val() == '') {
			$("#" + $("#statusDropDownId").val()).val('');
		}
	}

	function checkMissingSampleTestDate() {
		var missingTestDateFound = false;

		$('.missing-test-date-flag').each(function () {
			if ($(this).val() === '1') {
				missingTestDateFound = true;
				return false; // break loop
			}
		});

		if (missingTestDateFound) {
			alert("<?= _translate("Action Required: One or more samples do not have a test date. Please update all missing fields before submitting.", true); ?>");
			return false;
		}

		return true;
	}


	function acceptAllSamples() {

		if (!checkMissingSampleTestDate()) {
			return false;
		}
		conf = confirm("<?= _translate("Are you sure you want to mark all samples as Accepted?", true); ?>");
		if (conf) {
			$.blockUI();
			$.post("/import-result/updateAllSampleStatus.php", {},
				function (data) {
					oTable.fnDraw();
					$.unblockUI();
				});

		} else {
			oTable.fnDraw();
		}
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
