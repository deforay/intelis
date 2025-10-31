<?php

use App\Services\TestsService;
use App\Services\UsersService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());


if (empty($_GET['type'])) {
	throw new SystemException(_translate('Invalid test type') . ' - ' . ($_GET['type'] ?? _translate("Unspecified")), 500);
}

$testName = TestsService::getTestName($_GET['type']);

$title = _translate("Testing Batches") . " | " . $testName;

require_once APPLICATION_PATH . '/header.php';


/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);


$testTypeQuery = "SELECT * FROM r_test_types WHERE test_status='active' ORDER BY test_standard_name ASC";
$testTypeResult = $db->rawQuery($testTypeQuery);


$formId = (int) $general->getGlobalConfig('vl_form');

?>


<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> <?= $title; ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Manage Batches"); ?></li>
		</ol>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<?php if (!empty($_GET['type']) && $_GET['type'] == 'generic-tests') { ?>
						<div class="box-header with-border">
							<div class="row">
								<div class="col-xs-6 col-md-6">
									<div class="form-group" style="margin-left:30px; margin-top:30px;">
										<label for="testType">Test Type</label>
										<select class="form-control" name="testType" id="testType" title="Please choose test type" style="width:100%;" onchange="getBatchForm(this)">
											<option value=""> -- Select -- </option>
											<?php foreach ($testTypeResult as $testType) { ?>
												<option value="<?php echo $testType['test_type_id']; ?>"><?php echo $testType['test_standard_name'] . ' (' . $testType['test_loinc_code'] . ')' ?></option>
											<?php } ?>
										</select>

									</div>
								</div>
								<div class="col-xs-6 col-md-6">
									<div class="box-header with-border">
										<?php if (_isAllowed("/batch/add-batch.php?type=" . $_GET['type'])) { ?>
											<a href="add-batch.php?type=<?php echo $_GET['type']; ?>" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Create New Batch"); ?></a>
										<?php } ?>
									</div>
								</div>
							</div>
						</div>
					<?php } else { ?>
						<div class="box-header with-border">
							<?php if (_isAllowed("/batch/add-batch.php?type=" . $_GET['type'])) { ?>
								<a href="add-batch.php?type=<?php echo $_GET['type']; ?>" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Create New Batch"); ?></a>
							<?php } ?>
						</div>
					<?php } ?>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="batchCodeDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th scope="col"><?= _translate("Batch Code"); ?></th>
									<?php if (!empty($_GET['type']) && $_GET['type'] == 'generic-tests') { ?>
										<th scope="col"><?= _translate("Test Type"); ?></th>
									<?php }
									if ($formId == COUNTRY\CAMEROON) { ?>
										<th scope="col"><?= _translate("Lab Assigned Batch Code"); ?></th>
									<?php } ?>
									<th scope="col"><?= _translate("No. of Samples"); ?></th>
									<th scope="col"><?= _translate("No. of Samples Tested"); ?></th>
									<th scope="col"><?= _translate("Tested Date"); ?></th>
									<th scope="col"><?= _translate("Last Modified On"); ?></th>
									<?php if (_isAllowed("/batch/edit-batch.php?type=" . $_GET['type'])) { ?>
										<th scope="col"><?= _translate("Action"); ?></th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="5" class="dataTables_empty"><?= _translate("Loading data from server"); ?></td>
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
		$("#testType").select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Test Type"); ?>"
		});
		$.blockUI();
		oTable = $('#batchCodeDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ <?= _translate("records per page", true); ?>"
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
				<?php if (!empty($_GET['type']) && $_GET['type'] == 'generic-tests') { ?> {
						"sClass": "center",
						"bSortable": false
					},
				<?php } ?> <?php
							if ($formId == COUNTRY\CAMEROON) {
								echo '{
						"sClass": "center",
					},';
							}
							?> {
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
				}
				<?php
				if (_isAllowed("/batch/edit-batch.php?type=" . $_GET['type'])) {
					echo ',{"sClass": "center", "bSortable": false}';
				} ?>
			],
			"aaSorting": [
				[<?= ($formId == COUNTRY\CAMEROON) ? 5 : 4; ?>, "desc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/batch/get-batches.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "type",
					"value": "<?php echo $_GET['type']; ?>"
				});
				aoData.push({
					"name": "testType",
					"value": $('#testType').val()
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
		var conf = confirm("<?php echo _translate("Are you sure you want to delete Batch"); ?> : " + batchCode + "?\n<?php echo _translate("This action cannot be undone."); ?>");
		if (conf) {
			$.post("/batch/delete-batch.php", {
					id: bId,
					type: '<?php echo $_GET['type']; ?>'
				},
				function(data) {
					let toastMessage = "";
					let toastColor = "";
					if (data == 1) {
						toastMessage = "<?= _translate("Batch deleted successfully", true); ?>";
						toastColor = 'green';
					} else {
						toastMessage = "<?= _translate("Something went wrong. Please try again later.", true); ?>";
						toastColor = 'red';
					}

					if (toastMessage) {
						toast.info(toastMessage);
					}
					oTable.fnDraw();
				});
		}
	}

	function getBatchForm(obj) {
		if (obj.value != "") {
			oTable.fnDraw();
		}
	}
</script>


<?php

require_once APPLICATION_PATH . '/footer.php';
