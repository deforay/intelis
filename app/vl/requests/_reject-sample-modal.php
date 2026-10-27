<?php

// The Reject Sample dialog shared by the VL Requests and Enter VL Result pages.
// A row's Reject button (class reject-sample, data-id, data-code) opens it, and
// it posts to reject-sample.php. The including page provides oTable.

use App\Services\VlService;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

if (!VlService::canRejectFromListing()) {
	return;
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$rejectionReasonsByType = [];
$rejectionReasons = $db->rawQuery("SELECT rejection_reason_id, rejection_reason_name, rejection_type
									FROM r_vl_sample_rejection_reasons
									WHERE rejection_reason_status = 'active'
									ORDER BY rejection_type, rejection_reason_name");
foreach ($rejectionReasons as $reason) {
	$rejectionReasonsByType[(string) $reason['rejection_type']][] = $reason;
}

$rejectApprovers = [];
foreach ($usersService->getActiveUsers($_SESSION['facilityMap'] ?? null) as $user) {
	$rejectApprovers[$user['user_id']] = $user['user_name'];
}
$rejectApproverOptions = $general->generateSelectOptions($rejectApprovers, $_SESSION['userId'] ?? null, _translate("-- Select --"));
?>
<div class="modal fade" id="rejectSampleModal" tabindex="-1" role="dialog" aria-labelledby="rejectSampleModalLabel" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form id="rejectSampleForm" autocomplete="off">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="<?= _htmlTranslate('Close'); ?>"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="rejectSampleModalLabel"><?= _translate("Reject Sample"); ?> <span id="rejectSampleCode"></span></h4>
				</div>
				<div class="modal-body">
					<input type="hidden" name="id" id="rejectSampleId" />
					<div class="form-group">
						<label for="rejectRejectionReason"><?= _translate("Rejection Reason"); ?> <span class="mandatory">*</span></label>
						<select name="rejectionReason" id="rejectRejectionReason" class="form-control" title="<?= _htmlTranslate("Please choose the rejection reason"); ?>">
							<option value=""><?= _translate("-- Select --"); ?></option>
							<?php foreach ($rejectionReasonsByType as $type => $reasons) { ?>
								<optgroup label="<?= htmlspecialchars(strtoupper($type)); ?>">
									<?php foreach ($reasons as $reason) { ?>
										<option value="<?= (int) $reason['rejection_reason_id']; ?>"><?= htmlspecialchars((string) $reason['rejection_reason_name']); ?></option>
									<?php } ?>
								</optgroup>
							<?php } ?>
						</select>
					</div>
					<div class="form-group">
						<label for="rejectRejectionDate"><?= _translate("Rejected On"); ?> <span class="mandatory">*</span></label>
						<input type="text" name="rejectionDate" id="rejectRejectionDate" class="form-control date" placeholder="<?= _htmlTranslate("Select Rejection Date"); ?>" title="<?= _htmlTranslate("Please select Sample Rejection Date"); ?>" />
					</div>
					<div class="form-group">
						<label for="rejectApprovedBy"><?= _translate("Rejection Approved By"); ?> <span class="mandatory">*</span></label>
						<select name="approvedBy" id="rejectApprovedBy" class="form-control" title="<?= _htmlTranslate("Please choose approved by"); ?>">
							<?= $rejectApproverOptions; ?>
						</select>
					</div>
					<div class="form-group">
						<label for="rejectApprovedOn"><?= _translate("Rejection Approved On"); ?> <span class="mandatory">*</span></label>
						<input type="text" name="approvedOnDateTime" id="rejectApprovedOn" class="form-control dateTime" placeholder="<?= _htmlTranslate("Approved On"); ?>" title="<?= _htmlTranslate("Please enter approved on"); ?>" />
					</div>
					<div class="form-group">
						<label for="rejectLabComments"><?= _translate("Lab Tech. Comments"); ?></label>
						<textarea name="labComments" id="rejectLabComments" class="form-control" rows="2"></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal"><?= _translate("Cancel"); ?></button>
					<button type="submit" class="btn btn-danger" id="rejectSampleSubmit"><em class="fa-solid fa-ban"></em> <?= _translate("Reject Sample"); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
<style>
	/* The date pickers open above the dialog, not behind it */
	.ui-datepicker {
		z-index: 1060 !important;
	}
</style>
<script type="text/javascript">
	$(document).on('click', '.reject-sample', function () {
		var $form = $('#rejectSampleForm');
		$form[0].reset();
		$('#rejectSampleId').val($(this).data('id'));
		$('#rejectSampleCode').text($(this).data('code'));
		$('#rejectRejectionDate').datepicker('setDate', new Date());
		$('#rejectApprovedOn').datetimepicker('setDate', new Date());
		$('#rejectApprovedBy').val(<?= json_encode((string) ($_SESSION['userId'] ?? '')); ?>);
		$('#rejectSampleModal').modal('show');
	});

	$('#rejectSampleForm').on('submit', function (e) {
		e.preventDefault();
		var required = ['#rejectRejectionReason', '#rejectRejectionDate', '#rejectApprovedBy', '#rejectApprovedOn'];
		for (var i = 0; i < required.length; i++) {
			if ($.trim($(required[i]).val()) === '') {
				alert($(required[i]).attr('title'));
				$(required[i]).focus();
				return;
			}
		}
		var $submit = $('#rejectSampleSubmit').prop('disabled', true);
		$.post('/vl/requests/reject-sample.php', $(this).serialize())
			.done(function (response) {
				alert(response.message);
				if (response.status === 'success') {
					$('#rejectSampleModal').modal('hide');
					oTable.fnDraw();
				}
			})
			.fail(function () {
				alert(<?= json_encode(_translate("Please try again later")); ?>);
			})
			.always(function () {
				$submit.prop('disabled', false);
			});
	});
</script>
