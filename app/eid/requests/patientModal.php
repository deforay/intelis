<?php

use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();

$artNo = urldecode($_GET['artNo']);

$db->join("facility_details fd", "fd.facility_id=vl.facility_id", "LEFT");
$db->where("child_id LIKE ?", ["%$artNo%"]);
$db->orWhere("child_name LIKE ?", ["%$artNo%"]);
$db->orWhere("child_surname LIKE ?", ["%$artNo%"]);
$db->orderBy("sample_tested_datetime");
$db->orderBy("sample_collection_date");

$pResult = $db->get("form_eid vl", 25, "fd.facility_id,
            fd.facility_name,
            eid_id,
			child_name,
			child_surname,
			child_gender,
			child_dob,
			child_age,
			caretaker_phone_number,
			child_id,
			mother_id,
			caretaker_address,
			mother_name,
			mother_dob,
			mother_marital_status,
			request_created_datetime");

?>
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/plugins/datatables/dataTables.bootstrap.css">
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

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h4 class="pull-left bg-primary" style="width:100%;padding:8px;font-weight:normal;">Results matching your search
			-
			<?= ($artNo); ?>
		</h4>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="patientModalDataTable"
							class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th style="width:10%;">
										<?= _("Select"); ?>
									</th>
									<th>
										<?= _("Infant Code/ID"); ?>
									</th>
									<th>
										<?= _("Infant Name"); ?>
									</th>
									<th style="width:10%;">
										<?= _("Age"); ?>
									</th>
									<th style="width:10%;">
										<?= _("Gender"); ?>
									</th>
									<th style="width:10%;">
										<?= _("Facility Name"); ?>
									</th>
									<th style="width:10%;">
										<?= _("Date and Time"); ?>
									</th>
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
								
										$patientDetails = json_encode(
											array(
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
											)
										);
										?>
										<tr>
											<td><input type="radio" id="patient<?php echo $patient['eid_id']; ?>" name="patient"
													value='<?php echo $patientDetails; ?>'
													onclick="getPatientDetails(this.value);"></td>
											<td>
												<?php echo $patient['child_id']; ?>
											</td>
											<td>
												<?php echo ($patient['child_name']) . " " . $patient['child_surname']; ?>
											</td>
											<td>
												<?php echo $patient['child_age']; ?>
											</td>
											<td>
												<?php echo (str_replace("_", " ", $patient['child_gender'])); ?>
											</td>
											<td>
												<?= $patient['facility_name']; ?>
											</td>
											<td>
												<?= DateUtility::humanReadableDateFormat($patient['request_created_datetime'], true); ?>
											</td>
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

<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
<script src="/assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="/assets/plugins/datatables/dataTables.bootstrap.min.js"></script>
<script>
	$(document).ready(function () {
		$('#patientModalDataTable').DataTable({
			"aaSorting": [
				[1, 'asc'],
				[6, 'desc']
			]

		});
	});

	function getPatientDetails(pDetails) {
		parent.closeModal();
		window.parent.setPatientDetails(pDetails);
	}
</script>
