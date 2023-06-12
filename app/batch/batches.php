<?php
// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();


$showPatientName = false;
if (isset($_GET['type']) && $_GET['type'] == 'vl') {
	$_title = "Viral Load";
    $refTable = "form_vl";
    $refPrimaryColumn = "vl_sample_id";
    $patientIdColumn = 'patient_art_no';
    $patientFirstName = 'patient_first_name';
    $patientLastName = 'patient_last_name';
    $worksheetName = 'Viral Load Test Worksheet';
} elseif (isset($_GET['type']) && $_GET['type'] == 'eid') {
	$_title = "Early Infant Diagnosis";
    $refTable = "form_eid";
    $refPrimaryColumn = "eid_id";
    $patientIdColumn = 'child_id';
    $patientFirstName = 'child_name';
    $patientLastName = 'child_surname';
    $worksheetName = 'EID Test Worksheet';
} elseif (isset($_GET['type']) && $_GET['type'] == 'covid19') {
	$_title = "Covid-19";
    $refTable = "form_covid19";
    $refPrimaryColumn = "covid19_id";
    $patientIdColumn = 'patient_id';
    $patientFirstName = 'patient_name';
    $patientLastName = 'patient_surname';
    $worksheetName = 'Covid-19 Test Worksheet';
} elseif (isset($_GET['type']) && $_GET['type'] == 'hepatitis') {
	$_title = "Hepatitis";
    $refTable = "form_hepatitis";
    $refPrimaryColumn = "hepatitis_id";
    $patientIdColumn = 'patient_id';
    $patientFirstName = 'patient_name';
    $patientLastName = 'patient_surname';
    $worksheetName = 'Hepatitis Test Worksheet';
    $showPatientName = true;
} elseif (isset($_GET['type']) && $_GET['type'] == 'tb') {
	$_title = "TB";
    $refTable = "form_tb";
    $refPrimaryColumn = "tb_id";
    $patientIdColumn = 'patient_id';
    $patientFirstName = 'patient_name';
    $patientLastName = 'patient_surname';
    $worksheetName = 'TB Test Worksheet';
    $showPatientName = true;
} elseif (isset($_GET['type']) && $_GET['type'] == 'generic-tests') {
	$_title = "Lab Tests";
    $refTable = "form_generic";
    $refPrimaryColumn = "sample_id";
    $patientIdColumn = 'patient_id';
    $patientFirstName = 'patient_first_name';
    $patientLastName = 'patient_last_name';
    $worksheetName = 'Lab Test Worksheet';
    $showPatientName = true;
}
$title = _( $_title . " | Batches");

require_once APPLICATION_PATH . '/header.php';

?>


<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _("Manage ".$_title." Batches"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Manage Batches"); ?></li>
		</ol>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<?php if (isset($_SESSION['privileges']) && in_array("/batch/add-batch.php?type=".$_GET['type'], $_SESSION['privileges'])) { ?>
							<a href="add-batch.php?type=<?php echo $_GET['type'];?>" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _("Create New Batch"); ?></a>
						<?php } ?>
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="batchCodeDataTable" class="table table-bordered table-striped" aria-hidden="true" >
							<thead>
								<tr>
									<th scope="col"><?php echo _("Batch Code"); ?></th>
									<th scope="col"><?php echo _("No. of Samples"); ?></th>
									<th scope="col"><?php echo _("No. of Samples Tested"); ?></th>
									<th scope="col"><?php echo _("Last Tested Date"); ?></th>
									<th scope="col"><?php echo _("Created On"); ?></th>
									<?php if (isset($_SESSION['privileges']) && in_array("/batch/edit-batch.php?type=".$_GET['type'], $_SESSION['privileges'])) { ?>
										<th scope="col"><?php echo _("Action"); ?></th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="6" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
								</tr>
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
<script>
	var oTable = null;
	$(document).ready(function() {
		$.blockUI();
		oTable = $('#batchCodeDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": "center"
				},
				{
					"sClass": "center",
					"bSortable": false
				},
				{
					"sClass": "center",
					"bSortable": false
				},
				{
					"sClass": "center",
					"bSortable": false
				},
				{
					"sClass": "center"
				},
				<?php if (isset($_SESSION['privileges']) && in_array("/batch/edit-batch.php?type=".$_GET['type'], $_SESSION['privileges'])) { ?> {
						"sClass": "center",
						"bSortable": false
					},
				<?php } ?>
			],
			"aaSorting": [
				[4, "desc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "get-batches.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "type",
					"value": "<?php echo $_GET['type'];?>"
				});
				$.ajax({
					"dataType": 'json',
					"type": "POST",
					"url": sSource,
					"data": aoData,
					"success": fnCallback
				});
			}

		});
		$.unblockUI();
	});

	

	function deleteBatchCode(bId, batchCode) {
		var conf = confirm("<?php echo _("Are you sure you want to delete Batch"); ?> : " + batchCode + "?\n<?php echo _("This action cannot be undone."); ?>");
		if (conf) {
			$.post("delete-batch.php", {
					id: bId,
					type: '<?php echo $_GET['type'];?>'
				},
				function(data) {
					if (data == 1) {
						alert("<?php echo _("Batch deleted"); ?>");
					} else {
						alert("<?php echo _("Something went wrong. Please try again!"); ?>");
					}
					oTable.fnDraw();
				});
		}
	}
</script>


<?php

require_once APPLICATION_PATH . '/footer.php';
