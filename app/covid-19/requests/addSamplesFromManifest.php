<?php
$title = _translate("Add Samples from Manifest");

require_once APPLICATION_PATH . '/header.php';


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
				<?php echo _translate("Covid-19 Test Request"); ?>
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
							<td style="width:50%;vertical-align:middle;"><strong>
									<?php echo _translate("Enter Sample Manifest Code"); ?> :
								</strong></td>
							<td style="width:50%;vertical-align:middle;">
								<input type="text" id="samplePackageCode" name="samplePackageCode" class="form-control" placeholder="<?php echo _translate('Sample manifest code'); ?>" title="<?php echo _translate('Please enter the sample manifest code'); ?>" style="background:#fff;" />
								<input type="hidden" id="sampleId" name="sampleId" />
							</td>
							<td style="width:20%;vertical-align:middle;">
								<button class="btn btn-primary btn-sm pull-right" style="margin-right:5px;" onclick="getSampleCode();return false;"><span>
										<?php echo _translate("Submit"); ?>
									</span></button>
							</td>
						</tr>
						<tr class="activateSample" style="display:none;">
							<th scope="row" style="width:50%;vertical-align:middle;">
								<?php echo _translate("Sample Received at Testing Lab"); ?> :
							</th>
							<td style="width:50%;vertical-align:middle;"><input type="text" name="sampleReceivedOn" id="sampleReceivedOn" class="form-control dateTime" placeholder="Sample Received at Testing Lab" title="Please select when the samples were received at the Testing Lab" readonly />
							</td>
							<td style="width:20%;" colspan="3">
								<a class="btn btn-success btn-sm pull-right activateSample" style="display:none;margin-right:5px;" href="javascript:void(0);" onclick="activateSamplesFromManifest();"><em class="fa-solid fa-square-check"></em>
									<?php echo _translate("Activate Samples"); ?>
								</a>
							</td>
						</tr>
					</table>
					<!-- /.box-header -->
					<div class="box-body table-responsive">
						<table aria-describedby="table" id="covid19ManifestDataTable" class="table table-bordered table-striped table-vcenter" aria-hidden="true">
							<thead>
								<tr>
									<th>
										<?php echo _translate("Sample ID"); ?>
									</th>
									<?php if ($_SESSION['instance']['type'] != 'standalone') { ?>
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
									<th scope="row">
										<?php echo _translate("Facility Name"); ?>
									</th>
									<th>
										<?php echo _translate("Patient ID"); ?>
									</th>
									<th>
										<?php echo _translate("Patient Name"); ?>
									</th>
									<th>
										<?php echo _translate("Province/State"); ?>
									</th>
									<th>
										<?php echo _translate("District/County"); ?>
									</th>
									<th>
										<?php echo _translate("Result"); ?>
									</th>
									<th>
										<?php echo _translate("Last Modified On"); ?>
									</th>
									<th scope="row">
										<?php echo _translate("Status"); ?>
									</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="14" class="dataTables_empty" style="text-align:center;">
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

<?php
if (isset($global['bar_code_printing']) && $global['bar_code_printing'] != "off") {
	if ($global['bar_code_printing'] == 'dymo-labelwriter-450') {
?>
		<script src="/assets/js/DYMO.Label.Framework.js"></script>
		<script src="/uploads/barcode-formats/dymo-format.js"></script>
		<script src="/assets/js/dymo-print.js"></script>
	<?php
	} else if ($global['bar_code_printing'] == 'zebra-printer') {
	?>
		<script src="/assets/js/zebra-browserprint.js"></script>
		<script src="/uploads/barcode-formats/zebra-format.js"></script>
		<script src="/assets/js/zebra-print.js"></script>
<?php
	}
}
?>

<script type="text/javascript">
	var oTable = null;

	function loadCovid19RequestData() {
		$.blockUI();
		if (oTable) {
			$("#covid19ManifestDataTable").dataTable().fnDestroy();
		}
		oTable = $('#covid19ManifestDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			"bStateSave": true,
			//"bDestroy": true,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": "center"
				},
				<?php if ($_SESSION['instance']['type'] != 'standalone') { ?> {
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
				[<?php echo ($general->isSTSInstance() || $general->isLISInstance()) ? 9 : 8 ?>, "desc"]
			],
			"fnDrawCallback": function() {},
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/covid-19/requests/getManifestInGridHelper.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "samplePackageCode",
					"value": $("#samplePackageCode").val()
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

	function getSampleCode() {
		if ($("#samplePackageCode").val() != "") {
			$.blockUI();
			loadCovid19RequestData();
			$.post("/covid-19/requests/getRemoteManifestHelper.php", {
					samplePackageCode: $("#samplePackageCode").val()
				},
				function(data) {
					$.unblockUI();
					if (data != "") {
						$('.activateSample').show();
						$('#sampleId').val(data);
					} else {
						<?php if ($general->isLISInstance()) { ?>
							forceSyncRequestsByManifestCode($("#samplePackageCode").val(), 'covid19');
						<?php } ?>
					}
				});
		} else {
			alert("<?php echo _translate("Please enter the Sample Manifest Code", true); ?>");
		}
	}

	/* Remote Syn only package code matches */
	<?php if ($general->isLISInstance()) { ?>
		var remoteUrl = '<?php echo SYSTEM_CONFIG['remoteURL']; ?>';

		function forceSyncRequestsByManifestCode(manifestCode, forceSyncModule) {
			$.blockUI({
				message: "<h3><?php echo _translate("Trying to sync manifest", true); ?><br><?php echo _translate("Please wait", true); ?>...</h3>"
			});

			if (remoteSync && remoteUrl != null && remoteUrl != '') {
				var jqxhr = $.ajax({
						url: "/scheduled-jobs/remote/requests-receiver.php?manifestCode=" + manifestCode + "&forceSyncModule=" + forceSyncModule,
					})
					.done(function(data) {
						////console.log(data);
						//alert( "success" );
					})
					.fail(function() {
						$.unblockUI();
						// alert("Unable to do STS Sync. Please contact technical team for assistance.");
					})
					.always(function() {
						$.unblockUI();
						$.post("/covid-19/requests/getRemoteManifestHelper.php", {
								samplePackageCode: $("#samplePackageCode").val()
							},
							function(data) {
								$.unblockUI();
								if (data != "") {
									$('.activateSample').show();
									$('#sampleId').val(data);
									oTable.fnDraw();
								}
							});
					});
			}
		}
	<?php } ?>

	function activateSamplesFromManifest() {
		if ($("#sampleReceivedOn").val() == "") {
			alert("<?= _translate("Please select when the samples were received at the Testing Lab", true); ?>");
			return false;
		}
		$.blockUI();
		$.post("/covid-19/requests/activate-samples-from-manifest.php", {
				sampleId: $("#sampleId").val(),
				sampleReceivedOn: $("#sampleReceivedOn").val()
			},
			function(data) {
				if (data > 0) {
					alert("<?php echo _translate("Samples from this Manifest have been activated", true); ?>");
					$('.activateSample').hide();
				}
				oTable.fnDraw();
				$.unblockUI();
			});
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
