<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();

$artNo = $_GET['artNo'];

$pQuery = "SELECT * FROM form_eid as vl inner join facility_details as fd ON fd.facility_id=vl.facility_id  Left JOIN geographical_divisions as gd ON fd.facility_state_id=gd.geo_id where (child_id like '%" . $artNo . "%' OR child_name like '%" . $artNo . "%' OR child_surname like '%" . $artNo . "%' OR 	caretaker_phone_number like '%" . $artNo . "%') ORDER BY sample_tested_datetime DESC, sample_collection_date DESC LIMIT 25";
$pResult = $db->rawQuery($pQuery);

?>
<link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui.min.css" />
<!-- Bootstrap 3.3.6 -->
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<!-- Font Awesome -->
<link rel="stylesheet" href="/assets/css/font-awesome.min.css">
<!-- DataTables -->
<link rel="stylesheet" href="/assets/plugins/datatables/dataTables.bootstrap.css">
<link href="/assets/css/deforayModal.css" rel="stylesheet" />
<style>
	.content-wrapper {
		padding: 2%;
	}

	.center {
		text-align: center;
	}

	body {
		overflow-x: hidden;
		/*overflow-y: hidden;*/
	}

	td {
		font-size: 13px;
		font-weight: 500;
	}

	th {
		font-size: 15px;
	}
</style>
<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
<script type="text/javascript" src="/assets/js/jquery-ui.min.js"></script>
<script src="/assets/js/deforayModal.js"></script>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h4 class="pull-left bg-primary" style="width:100%;padding:8px;font-weight:normal;">Results matching your search - <?= htmlspecialchars($artNo); ?></h4>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="patientModalDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th style="width:10%;">Select</th>
									<th>Infant Code/ID</th>
									<th>Infant Name</th>
									<th>Age</th>
									<th>Gender</th>
									<th>Facility</th>
									<th>Date and Time</th>
								</tr>
							</thead>
							<tbody>
								<?php
								$artNoList = [];
								foreach ($pResult as $patient) {
									$value = $patient['child_id'] . strtolower($patient['child_name']) . strtolower($patient['child_surname']) . $patient['mother_age_in_years'] . strtolower($patient['child_gender']) . strtolower($patient['facility_name']);
									if (!in_array($value, $artNoList)) {
										$artNoList[] = $value;
										//$patientDetails = $patient['child_name'] . "##" . $patient['child_surname'] . "##" . $patient['child_gender'] . "##" . \App\Utilities\DateUtility::humanReadableDateFormat($patient['child_dob']) . "##" . $patient['child_age'] . "##" . $patient['caretaker_phone_number'] .  "##" . $patient['child_id'] .  "##" . $patient['mother_id'] .  "##" . $patient['caretaker_address'] .  "##" . $patient['mother_name'] .  "##" . \App\Utilities\DateUtility::humanReadableDateFormat($patient['mother_dob']) .  "##" . $patient['mother_marital_status'];

										$patientDetails = json_encode(array(
											"name" => $patient['child_name'] . " " . $patient['child_surname'],
											"gender" => $patient['child_gender'],
											"dob" => DateUtility::humanReadableDateFormat($patient['child_dob']),
											"age" => $patient['child_age'],
											"caretaker_no" => $patient['caretaker_phone_number'],
											"child_id" => $patient['child_id'],
											"mother_id" => $patient['mother_id'],
											"caretaker_address" => $patient['caretaker_address'],
											"mother_name" => $patient['mother_name'],
											"mother_dob" => DateUtility::humanReadableDateFormat($patient['mother_dob']),
											"mother_marital_status" => $patient['mother_marital_status'],
										));
								?>
										<tr>
											<td><input type="radio" id="patient<?php echo $patient['vl_sample_id']; ?>" name="patient" value='<?php echo $patientDetails; ?>' onclick="getPatientDetails(this.value);"></td>
											<td><?php echo $patient['child_id']; ?></td>
											<td><?php echo ($patient['child_name']) . " " . $patient['child_surname']; ?></td>
											<td><?php echo $patient['child_age']; ?></td>
											<td><?php echo (str_replace("_", " ", $patient['child_gender'])); ?></td>
											<td><?php echo ($patient['facility_name']); ?></td>
											<td><?php echo date("d-M-Y h:i:s a", strtotime($patient['request_created_datetime'])); ?></td>
										</tr>
								<?php
									}
								}
								?>
							</tbody>
						</table>
					</div>
					<!-- /.box-body -->
				</div>
				<!-- /.box -->
			</div>
			<!-- /.col -->
		</div>
		<!-- /.row -->
	</section>
	<!-- /.content -->
</div>
<div id="dDiv" class="dialog">
	<div style="text-align:center"><span onclick="closeModal();" style="float:right;clear:both;" class="closeModal"></span></div>
	<iframe id="dFrame" src="" title="LIS Content" style="border:none;" scrolling="yes" marginwidth="0" marginheight="0" frameborder="0" vspace="0" hspace="0"><?= _("Unable to load this page or resource"); ?></iframe>
</div>
<!-- Bootstrap 3.3.6 -->
<script src="/assets/js/bootstrap.min.js"></script>
<!-- DataTables -->
<script src="/assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/assets/plugins/datatables/dataTables.bootstrap.min.js"></script>
<script>
	$(document).ready(function() {
		$('#patientModalDataTable').DataTable({
			"aaSorting": [1, 'asc']
		});
	});

	function getPatientDetails(pDetails) {
		parent.closeModal();
		window.parent.setPatientDetails(pDetails);
	}
</script>
