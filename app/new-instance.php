<?php

use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$remoteUrl = $general->getRemoteURL();

$labResults = $general->fetchDataFromTable('facility_details', 'facility_type = 2', array('facility_id', 'facility_name', 'facility_code'));
?>
<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui.min.css" />
<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jasny-bootstrap.min.css" />
<!-- Bootstrap 3.3.6 -->
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<link rel="stylesheet" media="all" type="text/css" href="/assets/css/select2.min.css" />
<link rel="stylesheet" media="all" type="text/css" href="/assets/css/select2.live.min.css" />
<link href="/assets/css/style.css" rel="stylesheet" />
<!-- Font Awesome -->
<link rel="stylesheet" href="/assets/css/font-awesome.min.css">
<!-- DataTables -->
<link rel="stylesheet" href="/assets/plugins/datatables/dataTables.bootstrap.css">
<link href="/assets/css/deforayModal.css" rel="stylesheet" />
<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
<script src="/assets/js/deforayModal.js"></script>
<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<script src="/assets/js/deforayValidation.js"></script>
<style>
	.select2-selection__placeholder {
		display: block;
	}

	#select2-labId-container {
		display: block;
		margin-top: 7px !important;
	}

	b {
		font-size: 12px;
	}

	.closeModal {
		display: none;
	}
</style>

<div class="content-wrapper" style="padding: 20px;">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h3>LIS Instance Setup</h3>
		<small>Please enter the information for this installation.</small>
	</section>
	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate("indicates required fields"); ?> &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='newInstance' id='newInstance' enctype="multipart/form-data" autocomplete="off" action="/new-instance-helper.php">
					<div class="row">
						<div class="col-xs-12">
							<div class="box">
								<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;">
									<tr>
										<td colspan="4">
											<select name="userType" id="userType" title="Please select the user type" class="form-control" onchange="changeLabType(this.value);" style=" background: aliceblue; ">
												<option value="">-- Select User Type --</option>
												<option value="lis">LIS with Remote Ordering Enabled</option>
												<option value="sts">Sample Tracking System(STS)</option>
												<option value="standalone">Standalone (no Remote Ordering)</option>
											</select>
										</td>
									</tr>
									<tr>
										<td class="lis hide"><strong>Lab Name&nbsp;<span class="mandatory">*</span></strong> <br>
										</td>
										<td class="lis hide" colspan="3">
											<select name="labId" id="labId" title="Please select the lab name" class="form-control lis-input">
												<option value="">-- Select --</option>
												<?php foreach ($labResults as $row) { ?>
													<option value="<?php echo $row['facility_id']; ?>"><?php echo $row['facility_name'] . '(' . $row['facility_code'] . ')'; ?></option>
												<?php } ?>
											</select>
										</td>
										<td class="sts hide"><strong>Lab Name&nbsp;<span class="mandatory">*</span></strong> <br>
										<td class="sts hide">
											<input type="text" class="form-control sts-input isRequired" name="facilityId" id="facilityId" title="Please enter instance name" placeholder="Instance/Facility Name" />
										</td>
										<td class="sts hide">&nbsp;<strong>Lab ID&nbsp;</strong></td>
										<td class="sts hide">
											<input type="text" class="form-control" id="facilityCode" name="facilityCode" placeholder="Instance/Facility Code" title="Please enter facility code" />
										</td>
									</tr>
									<tr style="display:none;">
										<td><strong>Lab Type&nbsp;<span class="mandatory">*</span></strong></td>
										<td>
											<select class="form-control isRequired" id="fType" name="fType" title="Please choose instance type">
												<option value="">-- Select --</option>
												<option value="Viral Load Lab">Viral Load Lab</option>
												<option value="Clinic/Lab">Clinic/Lab</option>
												<option value="Both" selected="selected">Both</option>
											</select>
										</td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div class="box-footer">
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save & Proceed</a>
					</div>
				</form>
				<hr />
			</div>
		</div>
	</section>
</div>
<script src="/assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="/assets/js/select2.min.js"></script>
<script type="text/javascript" src="/assets/js/jquery.blockUI.js"></script>
<link rel="stylesheet" media="all" type="text/css" href="/assets/css/select2.live.min.css" />
<!-- DataTables -->
<script type="text/javascript">
	$(document).ready(function() {
		$('#labId').select2({
			width: '100%',
			placeholder: "<?= _translate("Select Lab", escapeText: true); ?>"
		});
		<?php if (!empty($remoteUrl) && $general->isLISInstance() && empty($labResults)) { ?>

			window.parent.remoteSync = true;
			async function syncData() {
				$.blockUI({
					message: "<h3><?= _translate("Trying to sync Lab Details", escapeText: true); ?><br><?= _translate("Please wait..."); ?></h3>"
				});
				window.parent.syncRemoteData();
			}
			syncData().then(
				function(value) {
					location.reload();
				},
				function(error) {
					console.log(error);
				}
			);

		<?php } ?>
	});
	<?php if (isset($_SESSION['success']) && trim((string) $_SESSION['success']) != "") { ?>
		window.parent.closeModal();
		window.parent.alert("<?php echo $_SESSION['alertMsg']; ?>");
		<?php $_SESSION['alertMsg'] = '';
		unset($_SESSION['alertMsg']);
		$_SESSION['success'] = '';
		unset($_SESSION['success']); ?>

	<?php } ?>

	function validateNow() {
		if ($('#userType').val() == '') {
			alert('Please select the user type');
			$('#userType').focus();
		}
		flag = deforayValidator.init({
			formId: 'newInstance'
		});

		if (flag) {
			document.getElementById('newInstance').submit();
		}
	}

	function changeLabType(value) {
		if (value == 'lis' || value == 'standalone') {
			$('.lis').removeClass('hide');
			$('.lis-input').addClass('isRequired');
			$('.sts').addClass('hide');
			$('.sts-input').removeClass('isRequired');
			$('.sts-input,lis-input').val('').trigger('change');
		} else if (value == 'sts') {
			$('.sts').removeClass('hide');
			$('.sts-input').addClass('isRequired');
			$('.lis').addClass('hide');
			$('.lis-input').removeClass('isRequired');
			$('.lis-input,sts-input').val('').trigger('change');
		} else {
			$('.lis, .sts').addClass('hide');
			$('.lis-input,sts-input').removeClass('isRequired');
			$('.lis-input,sts-input').val('').trigger('change');
		}
	}
</script>
