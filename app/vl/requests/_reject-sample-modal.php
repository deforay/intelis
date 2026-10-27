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

// Only this lab's users: reject-sample.php refuses any other approver
$rejectApproverOptions = $general->generateSelectOptions($usersService->getActiveLabScopedUsers(), null, _translate("Choose who approved"));
?>
<link rel="stylesheet" href="/assets/css/tom-select.css" />
<style>
	#rejectSampleModal .modal-title {
		font-weight: 600;
	}

	#rejectSampleModal .reject-sample-code {
		margin-left: 6px;
		font-weight: normal;
		color: #a94442;
	}

	#rejectSampleModal .reject-sample-hint {
		margin: 0 0 15px;
		color: #777;
	}

	#rejectSampleModal label {
		font-weight: 600;
	}

	/* TomSelect copies .form-control onto its wrapper, which would draw a second box */
	#rejectSampleModal .ts-wrapper.form-control {
		padding: 0;
		height: auto;
		border: 0;
		box-shadow: none;
	}

	/* Same height, padding and corners as the date inputs beside it */
	#rejectSampleModal .ts-control {
		min-height: 34px;
		padding: 6px 12px;
		border-color: #d2d6de;
		border-radius: 0;
		font-size: 14px;
	}

	#rejectSampleModal .ts-dropdown {
		border-radius: 0;
	}

	#rejectSampleModal .ts-dropdown .optgroup-header {
		font-weight: 600;
		color: #777;
	}

	/* The date pickers open above the dialog, not behind it */
	.ui-datepicker {
		z-index: 1060 !important;
	}
</style>
<div class="modal fade" id="rejectSampleModal" tabindex="-1" role="dialog" aria-labelledby="rejectSampleModalLabel" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<form id="rejectSampleForm" autocomplete="off">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="<?= _htmlTranslate('Close'); ?>"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="rejectSampleModalLabel">
						<em class="fa-solid fa-ban text-danger"></em> <?= _translate("Reject Sample"); ?>
						<span class="reject-sample-code" id="rejectSampleCode"></span>
					</h4>
				</div>
				<div class="modal-body">
					<p class="reject-sample-hint"><?= _translate("The sample is marked Rejected and any test details recorded for it are cleared."); ?></p>
					<div class="alert alert-danger" id="rejectSampleError" role="alert" style="display:none;"></div>
					<input type="hidden" name="id" id="rejectSampleId" />
					<div class="form-group">
						<label for="rejectRejectionReason"><?= _translate("Rejection Reason"); ?> <span class="mandatory">*</span></label>
						<select name="rejectionReason" id="rejectRejectionReason" class="form-control" title="<?= _htmlTranslate("Please choose the rejection reason"); ?>">
							<option value=""><?= _translate("Search or choose a reason"); ?></option>
							<?php foreach ($rejectionReasonsByType as $type => $reasons) { ?>
								<optgroup label="<?= htmlspecialchars(ucwords($type)); ?>">
									<?php foreach ($reasons as $reason) { ?>
										<option value="<?= (int) $reason['rejection_reason_id']; ?>"><?= htmlspecialchars((string) $reason['rejection_reason_name']); ?></option>
									<?php } ?>
								</optgroup>
							<?php } ?>
						</select>
					</div>
					<div class="row">
						<div class="col-sm-4 form-group">
							<label for="rejectRejectionDate"><?= _translate("Rejected On"); ?> <span class="mandatory">*</span></label>
							<input type="text" name="rejectionDate" id="rejectRejectionDate" class="form-control date" placeholder="<?= _htmlTranslate("Select Rejection Date"); ?>" title="<?= _htmlTranslate("Please select Sample Rejection Date"); ?>" />
						</div>
						<div class="col-sm-4 form-group">
							<label for="rejectApprovedBy"><?= _translate("Rejection Approved By"); ?> <span class="mandatory">*</span></label>
							<select name="approvedBy" id="rejectApprovedBy" class="form-control" title="<?= _htmlTranslate("Please choose approved by"); ?>">
								<?= $rejectApproverOptions; ?>
							</select>
						</div>
						<div class="col-sm-4 form-group">
							<label for="rejectApprovedOn"><?= _translate("Rejection Approved On"); ?> <span class="mandatory">*</span></label>
							<input type="text" name="approvedOnDateTime" id="rejectApprovedOn" class="form-control dateTime" placeholder="<?= _htmlTranslate("Approved On"); ?>" title="<?= _htmlTranslate("Please enter approved on"); ?>" />
						</div>
					</div>
					<div class="form-group" style="margin-bottom:0;">
						<label for="rejectLabComments"><?= _translate("Lab Tech. Comments"); ?> <small class="text-muted">(<?= _translate("optional"); ?>)</small></label>
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
<script src="/assets/js/tom-select.complete.min.js"></script>
<script type="text/javascript">
	(function () {
		// The list stays inside the dialog: Bootstrap's focus trap closes one rendered on body
		var tsOptions = {
			maxOptions: null,
			allowEmptyOption: false
		};
		var tsReason = new TomSelect('#rejectRejectionReason', tsOptions);
		var tsApprover = new TomSelect('#rejectApprovedBy', tsOptions);

		function showError(message) {
			$('#rejectSampleError').text(message).show();
		}

		$(document).on('click', '.reject-sample', function () {
			$('#rejectSampleForm')[0].reset();
			$('#rejectSampleError').hide().text('');
			$('#rejectSampleId').val($(this).data('id'));
			$('#rejectSampleCode').text($(this).data('code'));
			// Every field is entered by hand: the dates and approver are part of the record
			tsReason.clear(true);
			tsApprover.clear(true);
			$('#rejectRejectionDate, #rejectApprovedOn').val('');
			$('#rejectSampleModal').modal('show');
		});

		$('#rejectSampleModal').on('shown.bs.modal', function () {
			tsReason.focus();
		}).on('hide.bs.modal', function () {
			tsReason.close();
			tsApprover.close();
		});

		$('#rejectSampleForm').on('submit', function (e) {
			e.preventDefault();
			$('#rejectSampleError').hide();
			var required = ['#rejectRejectionReason', '#rejectRejectionDate', '#rejectApprovedBy', '#rejectApprovedOn'];
			for (var i = 0; i < required.length; i++) {
				if ($.trim($(required[i]).val()) === '') {
					showError($(required[i]).attr('title'));
					return;
				}
			}
			var $submit = $('#rejectSampleSubmit').prop('disabled', true);
			$.post('/vl/requests/reject-sample.php', $(this).serialize())
				.done(function (response) {
					if (response.status === 'success') {
						$('#rejectSampleModal').modal('hide');
						oTable.fnDraw();
					} else {
						showError(response.message);
					}
				})
				.fail(function () {
					showError(<?= json_encode(_translate("Please try again later")); ?>);
				})
				.always(function () {
					$submit.prop('disabled', false);
				});
		});
	})();
</script>
