<?php 
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
/** @var CommonService $commonService */
$general = ContainerRegistry::get(CommonService::class);
$labResults = $general->fetchDataFromTable('facility_details', 'facility_type = 2', array('facility_id', 'facility_name', 'facility_code'));
?>
<link rel="stylesheet" media="all" type="text/css" href="assets/css/jquery-ui.1.11.0.css" />
<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui.min.css" />
<link href="assets/css/jasny-bootstrap.min.css" rel="stylesheet" />
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
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='addInstance' id='addInstance' enctype="multipart/form-data" autocomplete="off" action="addInstanceHelper.php">
					<div class="row">
						<div class="col-xs-12">
							<div class="box">
								<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;">
									<tr>
										<td>
											<label class="radio-inline" style="margin-left:0;">
                                                <input type="radio" id="labTypeLis" class="labType" name="userType" value="lis" title="Please choose type of system" onchange="changeLabType('lis', 'sts');">
                                                <strong>LIS</strong>
                                            </label>
										</td>
										<td>
											<label class="radio-inline" style="margin-left:0;">
                                                <input type="radio" id="labTypeSts" class="labType" name="userType" value="sts" title="Please choose type of system" onchange="changeLabType('sts', 'lis');">
                                                <strong>STS</strong>
                                            </label>
										</td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td class="lis hide"><strong>Lab Name&nbsp;<span class="mandatory">*</span></strong> <br>
										</td>
										<td class="lis hide">
											<select name="labId" id="labId" title="Please select the lab name" class="form-control lis-input">
												<option value="">-- Select --</option>
												<?php foreach($labResults as $row){ ?>
													<option value="<?php echo $row['facility_id'];?>"><?php echo $row['facility_name'] . '(' . $row['facility_code'] . ')';?></option>
												<?php } ?>
											</select>
										</td>
										<td class="sts hide"><strong>Lab Name&nbsp;<span class="mandatory">*</span></strong> <br>
										<td class="sts hide">
											<input type="text" class="form-control sts-input isRequired" name="fName" id="fName" title="Please enter instance name" placeholder="Instance/Facility Name" />
										</td>
										<td class="sts hide">&nbsp;<strong>Lab ID&nbsp;</strong></td>
										<td class="sts hide">
											<input type="text" class="form-control" id="fCode" name="fCode" placeholder="Instance/Facility Code" title="Please enter facility code" />
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
										<td style="display:none">&nbsp;<strong>Logo Image&nbsp;</strong></td>
										<td style="display:none">
											<div class="fileinput fileinput-new" data-provides="fileinput">
												<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">

												</div>
												<div>
													<span class="btn btn-default btn-file"><span class="fileinput-new">Select image</span><span class="fileinput-exists">Change</span>
														<input type="file" id="logo" name="logo" title="Please select logo image">
													</span>
													<a href="#" class="btn btn-default fileinput-exists" data-dismiss="fileinput">Remove</a>
												</div>
											</div>
											<div class="box-body">
												<strong>Please make sure logo image size of:</strong> <code>80x80</code>
											</div>
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
<link rel="stylesheet" media="all" type="text/css" href="/assets/css/select2.live.min.css" />
<!-- DataTables -->
<script type="text/javascript">
	$(document).ready(function() {
		$('#labId').select2({
			width: '100%',
			placeholder: "Select Testing Lab"
		});
	});
	<?php if (isset($_SESSION['success']) && trim($_SESSION['success']) != "") { ?>
		window.parent.closeModal();
		window.parent.alert("<?php echo $_SESSION['alertMsg']; ?>");
		<?php $_SESSION['alertMsg'] = '';
		unset($_SESSION['alertMsg']);
		$_SESSION['success'] = '';
		unset($_SESSION['success']); ?>

	<?php } ?>

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'addInstance'
		});

		if (flag) {
			document.getElementById('addInstance').submit();
		}
	}

	function changeLabType(active, inactive){
		console.log(active + ' => '+inactive)
		$('.'+active).removeClass('hide');
		$('.'+active+'-input').addClass('isRequired');
		$('.'+inactive).addClass('hide');
		$('.'+inactive+'-input').removeClass('isRequired');
		$('.'+inactive+'-input,.'+active+'-input').val('').trigger('change');
	}
</script>