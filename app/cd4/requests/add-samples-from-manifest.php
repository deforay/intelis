<?php

require_once APPLICATION_PATH . '/header.php';

use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$remoteURL = $general->getRemoteURL();

$title = _translate("Add Samples from Manifest");

?>
<style>
	.select2-selection__choice {
		color: black !important;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-plus"></em>
			<?php echo _translate("Add Samples from Manifest"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("Test Request"); ?>
			</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;display: block;">
						<tr>
							<td style="width:20%;vertical-align:middle;"><strong>
									<?php echo _translate("Enter Sample Manifest Code"); ?> :
								</strong></td>
							<td style="width:70%;vertical-align:middle;">
								<input type="text" id="manifestCode" name="manifestCode" class="form-control" placeholder="<?php echo _translate('Sample Manifest Code'); ?>" title="<?php echo _translate('Please enter the sample manifest code'); ?>" style="background:#fff;" />
								<input type="hidden" id="sampleId" name="sampleId" />
							</td>
							<td style="width:10%;">
								<button class="btn btn-primary btn-sm pull-right" style="margin-right:5px;" onclick="getSamplesForManifest();return false;"><span>
										<?php echo _translate("Submit"); ?>
									</span></button>
							</td>
						</tr>
						<tr class="activateSample" style="display:none;">
							<th scope="row" style="width:20%;vertical-align:middle;">
								<?php echo _translate("Sample Received at Testing Lab"); ?> :
							</th>
							<td style="width:70%;vertical-align:middle;"><input type="text" name="sampleReceivedOn" id="sampleReceivedOn" class="form-control dateTime" placeholder="Sample Received at Testing Lab" title="Please select when the samples were received at the Testing Lab" readonly />
							</td>

							<td style="width:10%;">
								<a class="btn btn-success btn-sm pull-right" style="margin-right:5px;" href="javascript:void(0);" onclick="activateSamplesFromManifest();"><em class="fa-solid fa-check"></em>&nbsp;<?= _translate("Activate Samples"); ?></a>
							</td>
						</tr>
					</table>

					<div class="container-fluid">
						<span class="pull-right sts-server-reachable">
							<span class="fa-solid fa-circle is-remote-server-reachable" style="font-size:1em;display:none;"></span>
							<span class="sts-server-reachable-span">

							</span>
						</span>
					</div>
					<!-- /.box-header -->
					<div class="box-body table-responsive">
						<table aria-describedby="table" id="manifestDataTable" class="table table-bordered table-striped table-vcenter">
							<thead>
								<tr>
									<th>
										<?php echo _translate("Sample ID"); ?>
									</th>
									<?php if (!$general->isStandaloneInstance()) { ?>
										<th>
											<?php echo _translate("Remote Sample ID"); ?>
										</th>
									<?php } ?>
									<th>
										<?php echo _translate("Sample Collection Date"); ?>
									</th>
									<th>
										<?php echo _translate("Batch Code"); ?>
									</th>
									<th>
										<?php echo _translate("Unique ART No"); ?>
									</th>
									<th>
										<?php echo _translate("Patient's Name"); ?>
									</th>
									<th scope="row">
										<?php echo _translate("Facility Name"); ?>
									</th>
									<th>
										<?php echo _translate("Province/State"); ?>
									</th>
									<th>
										<?php echo _translate("District/County"); ?>
									</th>
									<th>
										<?php echo _translate("Sample Type"); ?>
									</th>
									<th>
										<?php echo _translate("Result"); ?>
									</th>
									<th>
										<?php echo _translate("Last Modified Date"); ?>
									</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="13" class="dataTables_empty" style="text-align:center;">
										<?php echo _translate("Please enter the manifest code then submit!", true); ?>
									</td>
								</tr>
							</tbody>
						</table>
					</div>

				</div>
				<!-- /.box -->

			</div>
			<!-- /.col -->
		</div>
		<!-- /.row -->
	</section>
	<!-- /.content -->
</div>
<script type="text/javascript" src="/assets/plugins/daterangepicker/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>

<script type="text/javascript">
	var oTable = null;

	function loadRequestData() {
		$.blockUI();
		if (oTable) {
			$("#manifestDataTable").dataTable().fnDestroy();
		}

		oTable = $('#manifestDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bDestroy": true,
			"bStateSave": true,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": "center"
				},
				<?php if (!$general->isStandaloneInstance()) { ?> {
						"sClass": "center"
					},
				<?php } ?> {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}
			],
			"aaSorting": [
				[<?php echo ($general->isSTSInstance() || $general->isLISInstance()) ? 11 : 10 ?>, "desc"]
			],
			"fnDrawCallback": function() {
				var checkBoxes = document.getElementsByName("chk[]");
				len = checkBoxes.length;
				for (c = 0; c < len; c++) {
					if (jQuery.inArray(checkBoxes[c].id, selectedTestsId) != -1) {
						checkBoxes[c].setAttribute("checked", true);
					}
				}
			},
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/cd4/requests/getManifestInGridHelper.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "manifestCode",
					"value": $("#manifestCode").val()
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
	}

	function getSamplesForManifest() {
		if ($("#manifestCode").val() != "") {
			$.blockUI();

			$.post("/tasks/remote/requests-receiver.php", {
					manifestCode: $("#manifestCode").val(),
					testType: 'cd4'
				},
				function(data) {
					$.unblockUI();
					let parsed;
					try {
						parsed = JSON.parse(data);
						if (
							parsed &&
							typeof parsed === 'object' &&
							!Array.isArray(parsed) &&
							Object.keys(parsed).length === 0
						) {
							toast.error("<?= _translate("No samples found in the manifest", true); ?>");
						} else {
							$('.activateSample').show();
							$('#sampleId').val(data);
							loadRequestData();
						}
					} catch (e) {
						toast.error("<?= _translate("Some error occurred while processing the manifest", true); ?>");
					}
				});
		} else {
			alert("<?php echo _translate("Please enter the Sample Manifest Code", true); ?>");
		}
	}

	function activateSamplesFromManifest() {
		if ($("#sampleReceivedOn").val() == "") {
			alert("<?= _translate("Please select when the samples were received at the Testing Lab", true); ?>");
			return false;
		}
		$.blockUI();
		$.post("/cd4/requests/activate-samples-from-manifest.php", {
				testType: 'cd4',
				manifestCode: $("#manifestCode").val(),
				sampleId: $("#sampleId").val(),
				sampleReceivedOn: $("#sampleReceivedOn").val()
			},
			function(data) {
				if (data > 0) {
					alert("<?php echo _translate("Samples from this Manifest have been activated", true); ?>");
				}
				$('.activateSample').hide();
				oTable.fnDraw();
				$.unblockUI();
			});
	}
</script>
<?php

require_once APPLICATION_PATH . '/footer.php';
