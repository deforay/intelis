<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$artNo = $_GET['artNo'];
$testType = $_GET['testType'];

$pQuery = "SELECT * FROM form_generic as g inner join facility_details as fd ON fd.facility_id=g.facility_id where g.test_type=$testType AND(patient_id like '%" . $artNo . "%' OR patient_first_name like '%" . $artNo . "%' OR patient_middle_name like '%" . $artNo . "%' OR patient_last_name like '%" . $artNo . "%') ORDER BY sample_tested_datetime DESC, sample_collection_date DESC LIMIT 25";
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
		<h4 class="pull-left bg-primary" style="width:100%;padding:8px;font-weight:normal;">Results matching your search - <?php echo $artNo; ?></h4>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<!-- /.box-header -->
					<div class="box-body">
						<table id="patientModalDataTable" class="table table-bordered table-striped" aria-hidden="true" >
							<thead>
								<tr>
									<th style="width:10%;">Select</th>
									<th>Patient ID</th>
									<th>Patient Name</th>
									<th>Age</th>
									<th>Gender</th>
									<th>Facility</th>
									<th>Date and Time</th>
									<th>Tested Date and Time</th>
									<th>Result</th>
								</tr>
							</thead>
							<tbody>
								<?php
								$artNoList = [];
								foreach ($pResult as $patient) {
									$value = $patient['patient_id'] . strtolower($patient['patient_first_name']) . strtolower($patient['patient_last_name']) . $patient['patient_age_in_years'] . strtolower($patient['patient_gender']) . strtolower($patient['facility_name']);
									if (!in_array($value, $artNoList)) {
										$artNoList[] = $value;
										//$patientDetails = $patient['patient_first_name'] . "##" . $patient['patient_last_name'] . "##" . $patient['patient_gender'] . "##" . \App\Utilities\DateUtility::humanReadableDateFormat($patient['patient_dob']) . "##" . $patient['patient_age_in_years'] . "##" . $patient['patient_age_in_months'] . "##" . $patient['is_patient_pregnant'] . "##" . $patient['is_patient_breastfeeding'] . "##" . $patient['patient_mobile_number'] . "##" . $patient['consent_to_receive_sms'] . "##" . \App\Utilities\DateUtility::humanReadableDateFormat($patient['treatment_initiated_date']) . "##" . $patient['current_regimen'] . "##" . \App\Utilities\DateUtility::humanReadableDateFormat($patient['last_viral_load_date']) . "##" . $patient['last_viral_load_result'] . "##" . $patient['number_of_enhanced_sessions'] . "##" . $patient['patient_id'] . "##" . $patient['is_patient_new'] . "##" .$patient['sample_tested_datetime']; 
										$patientDetails = json_encode(array(
											"name"=>$patient['patient_first_name'] . " " . $patient['patient_last_name'],
											"gender" => $patient['patient_gender'],
											"dob" => DateUtility::humanReadableDateFormat($patient['patient_dob']),
											"age_in_years" => $patient['patient_age_in_years'],
											"age_in_months" => $patient['patient_age_in_months'],
											"is_pregnant" => $patient['is_patient_pregnant'],
											"is_breastfeeding" => $patient['is_patient_breastfeeding'],
											"mobile" => $patient['patient_mobile_number'],
											"consent_to_receive_sms" =>$patient['consent_to_receive_sms'],
											"treatment_initiated_date" => DateUtility::humanReadableDateFormat($patient['treatment_initiated_date']),
											"current_regimen" => $patient['current_regimen'],
											"last_viral_load_date" => DateUtility::humanReadableDateFormat($patient['last_viral_load_date']),
											"last_viral_load_result" => $patient['last_viral_load_result'],
											"number_of_enhanced_sessions" => $patient['number_of_enhanced_sessions'],
											"patient_id" => $patient['patient_id'],
											"is_patient_new" => $patient['is_patient_new'],
											"sample_tested_datetime" => DateUtility::humanReadableDateFormat($patient['sample_tested_datetime']),
											"result" => $patient['result'],
										));
										
										?>
										
										<tr>
											<td><input type="radio" id="patient<?php echo $patient['sample_id']; ?>" name="patient" value='<?php echo $patientDetails; ?>' onclick="getPatientDetails(this.value);"></td>
											<td><?php echo $patient['patient_id']; ?></td>
											<td><?php echo ($patient['patient_first_name']) . " " . $patient['patient_last_name']; ?></td>
											<td><?php echo $patient['patient_age_in_years']; ?></td>
											<td><?php echo (str_replace("_", " ", $patient['patient_gender'])); ?></td>
											<td><?php echo ($patient['facility_name']); ?></td>
											<td><?php echo date("d-M-Y h:i:s a", strtotime($patient['request_created_datetime'])); ?></td>
											<td><?php echo date("d-M-Y h:i:s a", strtotime($patient['sample_tested_datetime'])); ?></td>
											<td><?php echo $patient['result']; ?></td>
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
	<iframe id="dFrame" src="" style="border:none;" scrolling="yes" marginwidth="0" marginheight="0" frameborder="0" vspace="0" hspace="0"><?= _("Unable to load this page or resource"); ?></iframe>
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