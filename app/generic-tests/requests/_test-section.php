<?php

/**
 * Custom Test multi-test "Test Results Information" section (TB-style).
 *
 * One sample can carry several test results, each at the SAME or a DIFFERENT lab,
 * each with its own receipt / rejection / tested / reviewed / approved chain --
 * mirroring tb_tests. Each card maps to one generic_test_results row.
 *
 * "Test Type" options are the test's configured TEST METHODS ($genericMethodOptions,
 * the assays). Each method resolves to its Result Group ($genericMethodGroups, keyed
 * by method name), and that group decides "Test Result": a qualitative dropdown of
 * the group's answers, or a numeric value + unit (group result_type 'quantitative').
 * Picking a Test Type rebuilds the result control live (gtOnTypeChange). A test can
 * have several groups (e.g. Ebola RT-PCR vs Antigen); many methods can share a group.
 * A stored value not in the configured list is still shown as a selected option, so
 * old data never blanks.
 *
 * Backward compatible: existing rows ($genericTestInfo) render as cards; a row's
 * per-test column (lab/tested/reviewed/approved) that is NULL -- i.e. entered
 * before release 5.5.10 -- is backfilled from the parent form_generic.
 *
 * Expects: $general, $testingLabs, $userInfo, $rejectionResult, $rejectionTypeResult,
 * $genericTestInfo (rows), $genericResultInfo (parent), $genericMethodOptions,
 * $genericMethodGroups, $genericDefaultGroup, $genericResultUnitOptions.
 */

use App\Utilities\DateUtility;

$gtEsc = static fn($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

$gtParent = is_array($genericResultInfo ?? null) ? $genericResultInfo : [];
$gtRows = (isset($genericTestInfo) && is_array($genericTestInfo) && !empty($genericTestInfo)) ? $genericTestInfo : [[]];
$gtMethodOptions = is_array($genericMethodOptions ?? null) ? $genericMethodOptions : [];
$gtMethodGroups = is_array($genericMethodGroups ?? null) ? $genericMethodGroups : [];
$gtDefaultGroup = is_array($genericDefaultGroup ?? null) ? $genericDefaultGroup : ['result_type' => 'qualitative', 'results' => []];
$gtUnits = is_array($genericResultUnitOptions ?? null) ? $genericResultUnitOptions : [];

/** Render one test card. $row may be empty (blank card). */
$gtRenderCard = function (int $n, array $row) use ($general, $testingLabs, $userInfo, $rejectionResult, $rejectionTypeResult, $gtParent, $gtMethodOptions, $gtMethodGroups, $gtDefaultGroup, $gtUnits, $gtEsc) {
	// Backfill per-test fields from the parent for rows that predate per-test columns.
	$labId        = $row['lab_id'] ?? null ?: ($gtParent['lab_id'] ?? null);
	$received     = !empty($row['sample_received_at_lab_datetime']) ? $row['sample_received_at_lab_datetime'] : ($gtParent['sample_received_at_lab_datetime'] ?? '');
	$rejected     = $row['is_sample_rejected'] ?? '';
	$rejReason    = $row['reason_for_sample_rejection'] ?? '';
	$rejOn        = $row['rejection_on'] ?? '';
	$testType     = $row['test_name'] ?? ($row['sub_test_name'] ?? '');   // the method/assay
	// The chosen method resolves to its result group, which decides the result control.
	$gtGroup      = $gtMethodGroups[$testType] ?? $gtDefaultGroup;
	$gtGroupType  = (($gtGroup['result_type'] ?? 'qualitative') === 'quantitative') ? 'quantitative' : 'qualitative';
	$gtGroupRes   = is_array($gtGroup['results'] ?? null) ? $gtGroup['results'] : [];
	$testResult   = $row['result'] ?? ($row['final_result'] ?? '');
	$comments     = $row['comments'] ?? '';
	$testedBy     = $row['tested_by'] ?? null ?: ($gtParent['tested_by'] ?? null);
	$testedOn     = $row['sample_tested_datetime'] ?? '';
	$reviewedBy   = $row['result_reviewed_by'] ?? null ?: ($gtParent['result_reviewed_by'] ?? null);
	$reviewedOn   = $row['result_reviewed_datetime'] ?? '';
	$approvedBy   = $row['result_approved_by'] ?? null ?: ($gtParent['result_approved_by'] ?? null);
	$approvedOn   = $row['result_approved_datetime'] ?? '';
	$testId       = $row['test_id'] ?? '';
	$isRejected   = ($rejected === 'yes');
	$fmt          = static fn($v, $time = true) => $v ? DateUtility::humanReadableDateFormat($v, $time) : '';
	?>
	<div class="test-section" data-count="<?= $n; ?>">
		<div class="section-header">
			<strong><?= _translate("Test #"); ?><span class="section-number"><?= $n; ?></span></strong>
			<span class="mandatory"> &mdash; <?= _translate("All fields are required except Comments"); ?></span>
		</div>
		<input type="hidden" name="testResult[testId][]" value="<?= $gtEsc($testId); ?>" />
		<table class="table" style="width:100%;">
			<tr>
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Testing Lab"); ?></label>
					<select name="testResult[labId][]" id="labId<?= $n; ?>" class="form-control select2 isRequired gtLab"
						title="<?= _translate("Please select testing laboratory"); ?>">
						<?= $general->generateSelectOptions($testingLabs, $labId, '-- ' . _translate("Select lab") . ' --'); ?>
					</select>
				</td>
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Date specimen received at testing lab"); ?></label>
					<input type="text" class="date-time isRequired form-control" id="sampleReceivedDate<?= $n; ?>"
						name="testResult[sampleReceivedDate][]" value="<?= $gtEsc($fmt($received)); ?>"
						placeholder="<?= _translate("Please enter date"); ?>" />
				</td>
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Is Sample Rejected?"); ?></label>
					<select class="form-control isRequired gtRejected" name="testResult[isSampleRejected][]"
						id="isSampleRejected<?= $n; ?>" onchange="gtToggleRejection(this);">
						<option value=""> -- <?= _translate("Select"); ?> -- </option>
						<option value="yes" <?= $isRejected ? 'selected' : ''; ?>><?= _translate("Yes"); ?></option>
						<option value="no" <?= ($rejected === 'no') ? 'selected' : ''; ?>><?= _translate("No"); ?></option>
					</select>
				</td>
			</tr>
			<tr class="gtRejectionRow" style="<?= $isRejected ? '' : 'display:none;'; ?>">
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Reason for Rejection"); ?></label>
					<select class="form-control gtRejectionReason" name="testResult[sampleRejectionReason][]" id="sampleRejectionReason<?= $n; ?>">
						<option value=""> -- <?= _translate("Select"); ?> --</option>
						<?php foreach ($rejectionTypeResult as $type) { ?>
							<optgroup label="<?= $gtEsc(strtoupper((string) $type['rejection_type'])); ?>">
								<?php foreach ($rejectionResult as $reject) {
									if ($type['rejection_type'] === $reject['rejection_type']) { ?>
										<option value="<?= $gtEsc($reject['rejection_reason_id']); ?>" <?= ((string) $rejReason === (string) $reject['rejection_reason_id']) ? 'selected' : ''; ?>>
											<?= $gtEsc($reject['rejection_reason_name']); ?>
										</option>
									<?php }
								} ?>
							</optgroup>
						<?php } ?>
					</select>
				</td>
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Rejection Date"); ?></label>
					<input class="form-control date" type="text" name="testResult[rejectionDate][]" id="rejectionDate<?= $n; ?>"
						value="<?= $gtEsc($fmt($rejOn, false)); ?>" placeholder="<?= _translate("Select rejection date"); ?>" />
				</td>
				<td style="width:33.33%;"></td>
			</tr>
			<tr class="gtResultRow" style="<?= $isRejected ? 'display:none;' : ''; ?>">
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Test Type"); ?></label>
					<select class="form-control isRequired gtType" name="testResult[testType][]" id="testType<?= $n; ?>" onchange="gtOnTypeChange(this);">
						<option value=""><?= _translate("Select test type"); ?></option>
						<?php
						$gtTypeFound = false;
						foreach ($gtMethodOptions as $m) {
							$mName = (string) ($m['name'] ?? '');
							$sel = ((string) $testType === $mName);
							$gtTypeFound = $gtTypeFound || $sel; ?>
							<option value="<?= $gtEsc($mName); ?>" <?= $sel ? 'selected' : ''; ?>><?= $gtEsc($mName); ?></option>
						<?php }
						// Keep a stored value that is no longer in the configured method list.
						if (!$gtTypeFound && trim((string) $testType) !== '') { ?>
							<option value="<?= $gtEsc($testType); ?>" selected><?= $gtEsc($testType); ?></option>
						<?php } ?>
					</select>
				</td>
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Test Result"); ?></label>
					<span class="gtResultControl">
						<?php if ($gtGroupType === 'quantitative') { ?>
							<input type="number" step="any" class="form-control isRequired" name="testResult[testResult][]"
								id="testResult<?= $n; ?>" value="<?= $gtEsc($testResult); ?>"
								placeholder="<?= _htmlTranslate("Enter numeric result"); ?>" />
							<select class="form-control" name="testResult[resultUnit][]" id="resultUnit<?= $n; ?>" style="margin-top:6px;">
								<option value=""><?= _htmlTranslate("Unit"); ?></option>
								<?php foreach ($gtUnits as $u) { ?>
									<option value="<?= $gtEsc($u['id']); ?>" <?= ((string) ($row['result_unit'] ?? '') === (string) $u['id']) ? 'selected' : ''; ?>><?= $gtEsc($u['name']); ?></option>
								<?php } ?>
							</select>
						<?php } else {
							$gtResFound = false; ?>
							<select class="form-control isRequired" name="testResult[testResult][]" id="testResult<?= $n; ?>">
								<option value=""><?= _htmlTranslate("Select test result"); ?></option>
								<?php foreach ($gtGroupRes as $rv) {
									$sel = ((string) $testResult === (string) $rv);
									$gtResFound = $gtResFound || $sel; ?>
									<option value="<?= $gtEsc($rv); ?>" <?= $sel ? 'selected' : ''; ?>><?= $gtEsc($rv); ?></option>
								<?php }
								if (!$gtResFound && trim((string) $testResult) !== '') { ?>
									<option value="<?= $gtEsc($testResult); ?>" selected><?= $gtEsc($testResult); ?></option>
								<?php } ?>
							</select>
							<?php // Keep resultUnit[] index-aligned across cards even when this one has no unit. ?>
							<input type="hidden" name="testResult[resultUnit][]" value="" />
						<?php } ?>
					</span>
				</td>
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Comments"); ?></label>
					<textarea class="form-control" name="testResult[comments][]" id="comments<?= $n; ?>"
						placeholder="<?= _translate("Please enter comments"); ?>"><?= $gtEsc($comments); ?></textarea>
				</td>
			</tr>
			<tr class="gtWorkflowRow" style="<?= $isRejected ? 'display:none;' : ''; ?>">
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Tested By"); ?></label>
					<select name="testResult[testedBy][]" id="testedBy<?= $n; ?>" class="form-control select2 isRequired">
						<?= $general->generateSelectOptions($userInfo, $testedBy, '-- ' . _translate("Select") . ' --'); ?>
					</select>
				</td>
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Tested On"); ?></label>
					<input type="text" class="date-time form-control isRequired" id="sampleTestedDateTime<?= $n; ?>"
						name="testResult[sampleTestedDateTime][]" value="<?= $gtEsc($fmt($testedOn)); ?>"
						placeholder="<?= _translate("Please enter date"); ?>" />
				</td>
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Reviewed By"); ?></label>
					<select name="testResult[reviewedBy][]" id="reviewedBy<?= $n; ?>" class="form-control select2 isRequired">
						<?= $general->generateSelectOptions($userInfo, $reviewedBy, '-- ' . _translate("Select") . ' --'); ?>
					</select>
				</td>
			</tr>
			<tr class="gtWorkflowRow" style="<?= $isRejected ? 'display:none;' : ''; ?>">
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Reviewed On"); ?></label>
					<input type="text" class="date-time form-control isRequired" id="reviewedOn<?= $n; ?>"
						name="testResult[reviewedOn][]" value="<?= $gtEsc($fmt($reviewedOn)); ?>"
						placeholder="<?= _translate("Reviewed On"); ?>" />
				</td>
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Approved By"); ?></label>
					<select name="testResult[approvedBy][]" id="approvedBy<?= $n; ?>" class="form-control select2 isRequired">
						<?= $general->generateSelectOptions($userInfo, $approvedBy, '-- ' . _translate("Select") . ' --'); ?>
					</select>
				</td>
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Approved On"); ?></label>
					<input type="text" class="date-time form-control isRequired" id="approvedOn<?= $n; ?>"
						name="testResult[approvedOn][]" value="<?= $gtEsc($fmt($approvedOn)); ?>"
						placeholder="<?= _translate("Approved On"); ?>" />
				</td>
			</tr>
		</table>
	</div>
	<?php
};
?>

<style>
	#testSections .test-section {
		padding: 15px;
		margin-bottom: 12px;
		border-radius: 5px;
		border: 1px solid #ddd;
	}

	#testSections .test-section:nth-child(odd) {
		background-color: #f9f9f9;
	}

	#testSections .test-section:nth-child(even) {
		background-color: #ffffff;
	}

	#testSections .test-section .section-header {
		font-size: 1.1em;
		color: #3c8dbc;
		margin-bottom: 10px;
		padding-bottom: 5px;
		border-bottom: 2px solid #3c8dbc;
	}

	#testSections .test-section .section-header strong {
		color: #3c8dbc;
	}
</style>

<?php // No box framing here: this section is rendered INSIDE the result page's
// Laboratory Information box, so its own box-primary would be a redundant
// nested box. Keep the id (JS hook) and box-body padding only. ?>
<div id="genericTestSectionBox">
	<div class="box-body">
		<div class="box-header with-border">
			<h3 class="box-title"><?= _translate("TEST RESULTS INFORMATION"); ?></h3>
		</div>
		<div class="box-header with-border">
			<h3 class="box-title" style="font-size:1em;">
				<?= _translate("Record each test performed on this sample. A test can be done at this lab or referred to another lab; use Add Test for each result. Enter the Final Interpretation only once you are done -- it locks further tests and referral."); ?>
			</h3>
		</div>

		<div id="testSections">
			<?php $n = 1;
			foreach ($gtRows as $row) {
				$gtRenderCard($n, is_array($row) ? $row : []);
				$n++;
			} ?>
		</div>

		<div style="margin:6px 0 18px;">
			<button type="button" id="gtAddTestBtn" class="btn btn-success" onclick="gtAddTest();">
				<em class="fa-solid fa-plus"></em> <?= _translate("Add Test"); ?>
			</button>
			<button type="button" id="gtRemoveTestBtn" class="btn btn-danger" style="display:none;" onclick="gtRemoveTest();">
				<em class="fa-solid fa-minus"></em> <?= _translate("Remove Test"); ?>
			</button>
		</div>

		<div class="box-header with-border">
			<h3 class="box-title"><?= _translate("FINAL INTERPRETATION"); ?></h3>
		</div>
		<table class="table" style="width:100%;">
			<tr>
				<td style="width:33.33%;">
					<label class="label-control"><?= _translate("Enter the Final Interpretation?"); ?></label>
					<select class="form-control" id="isResultFinalized" name="isResultFinalized" onchange="gtToggleFinal();">
						<option value="no"><?= _translate("No"); ?></option>
						<option value="yes" <?= (trim((string) ($gtParent['result'] ?? '')) !== '') ? 'selected' : ''; ?>><?= _translate("Yes"); ?></option>
					</select>
				</td>
				<td style="width:66.66%;">
					<label class="label-control"><?= _translate("Final Interpretation"); ?>
						<small class="text-muted">(<?= _translate("locks Add Test and referral once saved"); ?>)</small></label>
					<input type="text" class="form-control" id="finalResult" name="finalResult"
						value="<?= $gtEsc($gtParent['result'] ?? ''); ?>"
						placeholder="<?= _translate("Enter the final interpretation"); ?>"
						style="<?= (trim((string) ($gtParent['result'] ?? '')) !== '') ? '' : 'display:none;'; ?>" />
				</td>
			</tr>
		</table>
	</div>
</div>

<script type="text/javascript">
	var gtTestCount = <?= count($gtRows); ?>;
	// method name -> { result_type, results[] }; picking a Test Type shows its group's result options.
	var gtMethodGroups = <?= json_encode($gtMethodGroups, JSON_UNESCAPED_UNICODE) ?: '{}'; ?>;
	var gtDefaultGroup = <?= json_encode($gtDefaultGroup, JSON_UNESCAPED_UNICODE) ?: '{"result_type":"qualitative","results":[]}'; ?>;
	var gtUnits = <?= json_encode($gtUnits, JSON_UNESCAPED_UNICODE) ?: '[]'; ?>;
	var gtI18n = {
		unit: "<?= _jsTranslate("Unit"); ?>",
		selectResult: "<?= _jsTranslate("Select test result"); ?>",
		numeric: "<?= _jsTranslate("Enter numeric result"); ?>"
	};

	// Build the Test Result control for a method's result group -- a qualitative
	// dropdown or a numeric input + unit. Built with DOM nodes (not innerHTML) so
	// configured result/unit values can't inject markup.
	function gtBuildResultControl(group, n, currentVal) {
		var nodes = [];
		if (group && group.result_type === 'quantitative') {
			var $num = $('<input>', {
				type: 'number', step: 'any', 'class': 'form-control isRequired',
				name: 'testResult[testResult][]', id: 'testResult' + n,
				value: (currentVal == null ? '' : currentVal), placeholder: gtI18n.numeric
			});
			var $unit = $('<select>', { 'class': 'form-control', name: 'testResult[resultUnit][]', id: 'resultUnit' + n })
				.css('margin-top', '6px');
			$unit.append($('<option>').val('').text(gtI18n.unit));
			gtUnits.forEach(function (u) { $unit.append($('<option>').val(u.id).text(u.name)); });
			nodes.push($num, $unit);
		} else {
			var $sel = $('<select>', { 'class': 'form-control isRequired', name: 'testResult[testResult][]', id: 'testResult' + n });
			$sel.append($('<option>').val('').text(gtI18n.selectResult));
			var found = false;
			(((group && group.results) || [])).forEach(function (rv) {
				var $o = $('<option>').val(rv).text(rv);
				if (String(currentVal) === String(rv)) { $o.prop('selected', true); found = true; }
				$sel.append($o);
			});
			if (!found && currentVal != null && String(currentVal).trim() !== '') {
				$sel.append($('<option>').val(currentVal).text(currentVal).prop('selected', true));
			}
			// Keep resultUnit[] index-aligned across cards even when this one has no unit.
			nodes.push($sel, $('<input>', { type: 'hidden', name: 'testResult[resultUnit][]', value: '' }));
		}
		return nodes;
	}

	// Swap the Test Result control to match the chosen Test Type (method -> group).
	function gtOnTypeChange(typeSel) {
		var $card = $(typeSel).closest('.test-section');
		var n = $card.attr('data-count');
		var group = gtMethodGroups[$(typeSel).val()] || gtDefaultGroup;
		var $control = $card.find('.gtResultControl');
		$control.empty();
		gtBuildResultControl(group, n, '').forEach(function (node) { $control.append(node); });
	}

	function gtToggleRejection(sel) {
		var $card = $(sel).closest('.test-section');
		var rejected = sel.value === 'yes';
		$card.find('.gtRejectionRow').toggle(rejected);
		$card.find('.gtResultRow, .gtWorkflowRow').toggle(!rejected);
	}

	function gtToggleFinal() {
		var on = $('#isResultFinalized').val() === 'yes';
		$('#finalResult').toggle(on);
		// Final interpretation locks adding more tests / referral.
		$('#gtAddTestBtn').prop('disabled', on);
		$('.referSampleBtn, #referToLab, .genericReferralBtn').prop('disabled', on).toggleClass('disabled', on);
	}

	function gtAddTest() {
		if ($('#isResultFinalized').val() === 'yes') { return; }
		gtTestCount++;
		var $first = $('#testSections .test-section').first();
		var $clone = $first.clone();
		$clone.attr('data-count', gtTestCount);
		$clone.find('.section-number').text(gtTestCount);
		// Renumber ids; clear values; keep array names intact.
		$clone.find('[id]').each(function () { this.id = this.id.replace(/\d+$/, '') + gtTestCount; });
		$clone.find('input[type="text"], input[type="number"], textarea').val('');
		$clone.find('input[type="hidden"][name="testResult[testId][]"]').val('');
		$clone.find('select').prop('selectedIndex', 0);
		$clone.find('.select2-container').remove();
		$clone.find('.gtRejectionRow').hide();
		$clone.find('.gtResultRow, .gtWorkflowRow').show();
		$('#testSections').append($clone);
		gtInitTestSectionPlugins($clone);
		// Test Type was reset to blank -- rebuild the Test Result control to the default group.
		gtOnTypeChange($clone.find('.gtType')[0]);
		$('#gtRemoveTestBtn').show();
	}

	function gtRemoveTest() {
		var $sections = $('#testSections .test-section');
		if ($sections.length > 1) {
			$sections.last().remove();
			gtTestCount--;
		}
		if ($('#testSections .test-section').length <= 1) { $('#gtRemoveTestBtn').hide(); }
	}

	// (Re)initialise pickers for a freshly cloned card. A clone inherits the
	// original's already-initialised state: the select2 markup and the jQuery-UI
	// date-picker flags (hasDatepicker / hasDateTimePicker). Those flags make the
	// global initializers skip the field, so the date pickers never bind on added
	// rows. Mirror the TB Rwanda multi-test form: strip the stale flags, then run
	// the canonical global initializers (initDatePicker / initDateTimePicker from
	// dates.js.php), which (re)bind any .date / .date-time not yet carrying a flag.
	function gtInitTestSectionPlugins($scope) {
		try {
			$scope.find('.select2').each(function () {
				$(this).removeClass('select2-hidden-accessible').select2({ width: '100%' });
			});
		} catch (e) { }
		try {
			$scope.find('.date, .date-time').removeClass('hasDatepicker hasDateTimePicker');
			if (typeof initDatePicker === 'function') { initDatePicker(); }
			if (typeof initDateTimePicker === 'function') { initDateTimePicker(); }
		} catch (e) { }
	}

	$(function () {
		// Results are now entered per Test card -- hide the legacy single-result widgets.
		$('#resultSection, .subTestFields').hide();
		// The result page disables non-result sections on ready (.disabledForm). The
		// Test Section IS the result entry area, so defer past those handlers and keep it editable.
		setTimeout(function () {
			$('#genericTestSectionBox').find('input, select, textarea').prop('disabled', false);
			// These sample-level result fields are now captured PER Test card (or no longer
			// used in the multi-test flow). Hide them, drop their required flags, and disable
			// them so they don't submit / block validation. Kept: lab comments, reason for
			// testing, reason-for-changes. Focal person / hub received / testing platform /
			// result dispatch are removed for now (result dispatch may return after the final
			// interpretation step).
			['#isSampleRejected', '#sampleTestingDateAtLab', '#sampleReceivedDate',
				'#reviewedBy', '#reviewedOn', '#testedBy', '#approvedBy', '#approvedOn',
				'#vlFocalPerson', '#vlFocalPersonPhoneNumber', '#sampleReceivedAtHubOn',
				'#testPlatform', '#resultDispatchedOn'
			].forEach(function (sel) {
				$(sel).removeClass('isRequired').prop('disabled', true)
					.closest('.col-md-6, .form-group').hide();
			});
			$('.review-approve-span').hide();
			// Server-rendered cards aren't covered by the page's select2 init; do it here.
			$('#testSections').find('select.select2').each(function () {
				if (!$(this).hasClass('select2-hidden-accessible')) {
					$(this).select2({ width: '100%' });
				}
			});
			gtToggleFinal();
		}, 0);
		if ($('#testSections .test-section').length > 1) { $('#gtRemoveTestBtn').show(); }
	});
</script>
