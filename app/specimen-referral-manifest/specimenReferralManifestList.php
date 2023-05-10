<?php
$title = "Specimen Referral Manifest";

require_once APPLICATION_PATH . '/header.php';
?>
<style>
	.center {
		text-align: center;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-gears"></em> Specimen Referral Manifest</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Manage Specimen Referral Manifest</li>
		</ol>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<?php if (isset($_SESSION['privileges']) && in_array("move-manifest.php", $_SESSION['privileges'])) { ?>
							<a href="move-manifest.php?t=<?php echo htmlspecialchars($_GET['t']); ?>" class="btn btn-primary pull-right" style=" margin-left: 10px; "> <em class="fa-solid fa-angles-right"></em> <?= _("Move Manifest");?></a>
						<?php } if (isset($_SESSION['privileges']) && in_array("addSpecimenReferralManifest.php", $_SESSION['privileges'])) { ?>
							<a href="addSpecimenReferralManifest.php?t=<?php echo htmlspecialchars($_GET['t']); ?>" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _("Add Specimen Referral Manifest");?></a>
						<?php } ?>
						<!--<button class="btn btn-primary pull-right" style="margin-right: 1%;" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>-->
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="specimenReferralManifestDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<!-- <th>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" id="checkPackageData" onclick="checkAllPackageRows(this);"/></th> -->
									<th><?= _("Manifest Code"); ?></th>
									<th><?= _("Test Type"); ?></th>
									<th><?= _("Testing Lab"); ?></th>
									<th><?= _("Number of Samples"); ?></th>
									<th><?= _("Manifest Created On"); ?></th>
									<?php if (isset($_SESSION['privileges']) && in_array("editSpecimenReferralManifest.php", $_SESSION['privileges'])) { ?>
										<th><?= _("Action"); ?></th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="6" class="dataTables_empty">Loading data from server</td>
								</tr>
							</tbody>
							<input type="hidden" name="checkedPackages" id="checkedPackages" />
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
	$(function() {});
	$(document).ready(function() {
		$.blockUI();
		oTable = $('#specimenReferralManifestDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			"iDisplayLength": 100,
			//"bStateSave" : true,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": ""
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center",
					"bSortable": false
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				<?php if (isset($_SESSION['privileges']) && in_array("editSpecimenReferralManifest.php", $_SESSION['privileges'])) { ?> {
						"sClass": "center",
						"bSortable": false
					},
				<?php } ?>
			],
			"aaSorting": [
				[4, "desc"]
			],
			"fnDrawCallback": function() {
				// var checkBoxes = document.getElementsByName("chkPackage[]");
				// len = checkBoxes.length;
				// for (c = 0; c < len; c++) {
				//   if (jQuery.inArray(checkBoxes[c].id, selectedPackages) != -1) {
				//     checkBoxes[c].setAttribute("checked", true);
				//   }
				// }
			},
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getSpecimenReferralManifestCodeDetails.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "module",
					"value": "<?= htmlspecialchars(base64_decode($_GET['t'])); ?>"
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

	function generateManifestPDF(pId, frmSrc) {
		var ids = $("#checkedPackages").val();
		var module = '<?= htmlspecialchars(base64_decode($_GET['t'])); ?>';
		if (module == 'vl') {
			manifestFileName = "generateVLManifest.php";
		} else if (module == 'eid') {
			manifestFileName = "generateEIDManifest.php";
		} else if (module == 'covid19') {
			manifestFileName = "generateCovid19Manifest.php";
		} else if (module == 'hepatitis') {
			manifestFileName = "generateHepatitisManifest.php";
		}else if (module == 'tb') {
			manifestFileName = "generateTBManifest.php";
		}else if (module == 'generic-tests') {
			manifestFileName = "generateGenericManifest.php";
		}
		//alert(manifestFileName);
		$.post(manifestFileName, {
				id: pId,
				ids: ids,
				frmSrc: frmSrc
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					alert('Unable to generate manifest PDF');
				} else {
					window.open('/temporary/sample-manifests/' + data, '_blank');
				}

			});
	}

	selectedPackages = [];

	function checkPackage(obj) {
		if ($(obj).is(':checked')) {
			selectedPackages.push(obj.value);
		} else {
			selectedPackages.splice($.inArray(obj.value, selectedPackages), 1);
		}
		$("#checkedPackages").val(selectedPackages.join());
		if (selectedPackages.length == 0) {
			$('#checkPackageData').prop('checked', false);
		}
		$('.selectedRows').html(selectedPackages.length + ' Row(s) Selected');
		if (selectedPackages.length > 0) {
			$('.printBarcode').show();
		} else {
			$('.printBarcode').hide();
		}
	}

	function checkAllPackageRows(obj) {
		// if ($(obj).is(':checked')) {
		//   $(".chkPackage").each(function() {
		//     if ($.inArray(this.value, selectedPackages) == -1) {
		//       $(this).prop('checked', true);
		//       selectedPackages.push(this.value);
		//     }
		//   });
		// } else {
		//   $(".chkPackage").each(function() {
		//     $(this).prop('checked', false);
		//     selectedPackages.splice($.inArray(this.value, selectedPackages), 1);
		//   });
		// }
		// $("#checkedPackages").val(selectedPackages.join());
		// $('.selectedRows').html(selectedPackages.length + ' Row(s) Selected');
		// if (selectedPackages.length > 0) {
		//   $('.printBarcode').show();
		// } else {
		//   $('.printBarcode').hide();
		// }
	}

	// var count_elem = document.getElementById('specimenReferralManifestDataTable');
	// var div = document.createElement('div');
	// div.innerHTML = '<span class="selectedRows" style="font-weight:bold;">0 Row(s) Selected</span>&nbsp;&nbsp;&nbsp;&nbsp;<a class="btn btn-info btn-xs printBarcode" href="javascript:void(0);" onclick="generateManifestPDF(\' \',\'pk2\');" style="display:none;margin-bottom: 1vh;"><em class="fa-solid fa-barcode"></em> Print Barcode</a>';
	// count_elem.parentNode.insertBefore(div, count_elem);
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
