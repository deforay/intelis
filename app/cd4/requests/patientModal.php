<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$artNo = urldecode((string) $_GET['artNo']);

$db->join("facility_details fd", "fd.facility_id=vl.facility_id", "LEFT");
$db->where("patient_art_no LIKE ?", ["%$artNo%"]);
$db->orWhere("patient_first_name LIKE ?", ["%$artNo%"]);
$db->orWhere("patient_middle_name LIKE ?", ["%$artNo%"]);
$db->orWhere("patient_last_name LIKE ?", ["%$artNo%"]);
$db->orderBy("sample_tested_datetime");
$db->orderBy("sample_collection_date");

$pResult = $db->get("form_cd4 vl", 25, "fd.facility_id,
            fd.facility_name,
			cd4_id,
            patient_first_name,
            patient_last_name,
            patient_age_in_years,
            patient_dob,
            patient_gender,
            patient_age_in_months,
            is_patient_pregnant,
            is_patient_breastfeeding,
			pregnancy_trimester,
            patient_mobile_number,
            consent_to_receive_sms,
            treatment_initiated_date,
            current_regimen,
            last_cd4_date,
            last_cd4_result,
            patient_art_no,
			is_encrypted,
            is_patient_new,
            sample_tested_datetime,
            cd4_result,
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
										<?= _translate("Select"); ?>
									</th>
									<th style="width:10%;">
										<?= _translate("Patient ID"); ?>
									</th>
									<th style="width:10%;">
										<?= _translate("Patient Name"); ?>
									</th>
									<th style="width:10%;">
										<?= _translate("Age"); ?>
									</th>
									<th style="width:10%;">
										<?= _translate("Sex"); ?>
									</th>
									<th style="width:10%;">
										<?= _translate("Facility Name"); ?>
									</th>
									<th style="width:10%;">
										<?= _translate("Date and Time"); ?>
									</th>
									<th style="width:10%;">
										<?= _translate("Previous Tested Date"); ?>
									</th>
									<th style="width:10%;">
										<?= _translate("Previous Result"); ?>
									</th>
								</tr>
							</thead>
							<tbody>
								<?php
								$artNoList = [];
								foreach ($pResult as $patient) {
									$value = $patient['patient_art_no'] . strtolower((string) $patient['patient_first_name']) . strtolower((string) $patient['patient_last_name']) . $patient['patient_age_in_years'] . strtolower((string) $patient['patient_gender']) . strtolower((string) $patient['facility_name']);
									//if (!in_array($value, $artNoList)) {
									$artNoList[] = $value;
									$patientDetails = json_encode(
										["name" => $patient['patient_first_name'] . " " . $patient['patient_last_name'], "gender" => $patient['patient_gender'], "dob" => DateUtility::humanReadableDateFormat($patient['patient_dob']), "age_in_years" => $patient['patient_age_in_years'], "age_in_months" => $patient['patient_age_in_months'], "is_pregnant" => $patient['is_patient_pregnant'], "is_breastfeeding" => $patient['is_patient_breastfeeding'], "trimester" => $patient['pregnancy_trimester'], "mobile" => $patient['patient_mobile_number'], "consent_to_receive_sms" => $patient['consent_to_receive_sms'], "treatment_initiated_date" => DateUtility::humanReadableDateFormat($patient['treatment_initiated_date']), "current_regimen" => $patient['current_regimen'], "last_cd4_date" => DateUtility::humanReadableDateFormat($patient['last_cd4_date']), "last_cd4_result" => $patient['last_cd4_result'], "patient_art_no" => $patient['patient_art_no'], "is_encrypted" => $patient['is_encrypted'], "is_patient_new" => $patient['is_patient_new'], "sample_tested_datetime" => DateUtility::humanReadableDateFormat($patient['sample_tested_datetime']), "cd4_result" => $patient['cd4_result']]
									);

									?>

									<tr>
										<td>
											<input type="radio" id="patient<?php echo $patient['vl_sample_id']; ?>"
												name="patient" value='<?= $patientDetails; ?>'
												onclick="getPatientDetails(this.value);">
										</td>
										<td>
											<?php echo $patient['patient_art_no']; ?>
										</td>
										<td>
											<?php echo ($patient['patient_first_name']) . " " . $patient['patient_last_name']; ?>
										</td>
										<td>
											<?php echo $patient['patient_age_in_years']; ?>
										</td>
										<td>
											<?= str_replace("_", " ", (string) $patient['patient_gender']); ?>
										</td>
										<td>
											<?= $patient['facility_name']; ?>
										</td>
										<td>
											<?= DateUtility::humanReadableDateFormat($patient['request_created_datetime'], true); ?>
										</td>
										<td>
											<?php echo DateUtility::humanReadableDateFormat($patient['sample_tested_datetime']); ?>
										</td>
										<td>
											<?php echo $patient['cd4_result']; ?>
										</td>
									</tr>
									<?php
									//}
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
		window.parent.closeModal();
		window.parent.setPatientDetails(pDetails);
	}
</script>