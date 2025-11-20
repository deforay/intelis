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

$pQuery = "SELECT * FROM form_covid19 as vl inner join facility_details as fd ON fd.facility_id=vl.facility_id  Left JOIN geographical_divisions as gd ON fd.facility_state_id=gd.geo_id where (patient_id like '%" . $artNo . "%' OR patient_name like '%" . $artNo . "%' OR patient_surname like '%" . $artNo . "%') ORDER BY sample_tested_datetime DESC, sample_collection_date DESC LIMIT 25";
$pResult = $db->rawQuery($pQuery);

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
								</tr>
							</thead>
							<tbody>
								<?php
								$artNoList = [];
								foreach ($pResult as $patient) {
									$value = $patient['patient_id'] . strtolower((string) $patient['patient_name']) . strtolower((string) $patient['patient_surname']) . $patient['patient_age_in_years'] . strtolower((string) $patient['patient_gender']) . strtolower((string) $patient['facility_name']);
									//if (!in_array($value, $artNoList)) {
									$artNoList[] = $value;

									$patientDetails = json_encode(
										["firstname" => ($patient['patient_name']), "lastname" => ($patient['patient_surname']), "gender" => $patient['patient_gender'], "dob" => DateUtility::humanReadableDateFormat($patient['patient_dob']), "age" => $patient['patient_age'], "is_patient_pregnant" => $patient['is_patient_pregnant'], "is_patient_breastfeeding" => $patient['is_patient_breastfeeding'], "patient_phone_number" => $patient['patient_phone_number'], "patient_id" => $patient['patient_id'], "patient_passport_number" => $patient['patient_passport_number'], "patient_address" => $patient['patient_address'], "patient_nationality" => $patient['patient_nationality'], "patient_city" => $patient['patient_city'], "patient_province" => $patient['patient_province'], "patient_district" => $patient['patient_district'], "geo_code" => $patient['geo_code'], "geo_name" => $patient['geo_name'], "province_id" => $patient['province_id'], "patient_zone" => $patient['patient_zone'], "external_sample_code" => $patient['external_sample_code']]
									);
									?>

									<tr>
										<td><input type="radio" id="patient<?php echo $patient['covid19_id']; ?>"
												name="patient" value='<?php echo $patientDetails; ?>'
												onclick="getPatientDetails(this.value);"></td>
										<td>
											<?= $patient['patient_id']; ?>
										</td>
										<td>
											<?= ($patient['patient_name']) . " " . $patient['patient_surname']; ?>
										</td>
										<td>
											<?= $patient['patient_age']; ?>
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