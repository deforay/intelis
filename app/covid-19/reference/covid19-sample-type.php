<?php
$title = _("Covid-19 Sample Type");
 
require_once APPLICATION_PATH . '/header.php';

// if($sarr['sc_user_type']=='vluser'){
//   include('../remote/pullDataFromRemote.php');
// }
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-virus-covid"></em> <?php echo _("Covid-19 Sample Type"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Covid-19 Sample Type"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;margin-left: 325px;" id="showhide" class="">
						<div class="row" style="background:#e0e0e0;padding: 15px;">
							<div class="col-md-12">
								<div class="col-md-4">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="0" id="iCol0" data-showhide="facility_code" class="showhideCheckBox" /> <label for="iCol0"><?php echo _("Facility Code"); ?></label>
								</div>
								<div class="col-md-4">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="1" id="iCol1" data-showhide="facility_name" class="showhideCheckBox" /> <label for="iCol1"><?php echo _("Facility Name"); ?></label>
								</div>
								<div class="col-md-4">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="2" id="iCol2" data-showhide="facility_type" class="showhideCheckBox" /> <label for="iCol2"><?php echo _("Facility Type"); ?></label>
								</div>
								<div class="col-md-4">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="3" id="iCol3" data-showhide="status" class="showhideCheckBox" /> <label for="iCol3"><?php echo _("Status"); ?></label> <br>
								</div>
							</div>
						</div>
					</span>
					<div class="box-header with-border">
						<?php if (isset($_SESSION['privileges']) && in_array("covid19-sample-type.php", $_SESSION['privileges']) && $sarr['sc_user_type'] != 'vluser') { ?>
							<a href="add-covid19-sample-type.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _("Add Covid-19 Sample Type"); ?></a>
						<?php } ?>
						<!--<button class="btn btn-primary pull-right" style="margin-right: 1%;" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>-->
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="samTypDataTable" class="table table-bordered table-striped" aria-hidden="true" >
							<thead>
								<tr>
									<th scope="row"><?php echo _("Sample Name"); ?></th>
									<th scope="row"><?php echo _("Sample Status"); ?></th>
									<?php if (isset($_SESSION['privileges']) && in_array("covid19-sample-type.php", $_SESSION['privileges']) && $sarr['sc_user_type'] != 'vluser') { ?>
										<!-- <th scope="row">Action</th> -->
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="3" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
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
		oTable = $('#samTypDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			"bStateSave": true,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": "center"
				},
				{
					"sClass": "center"
				}
			],
			"aaSorting": [
				[0, "asc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getCovid19SampleTypeDetails.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
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

	function updateStatus(obj, optVal) {
		if (obj.value != '') {
			conf = confirm("<?php echo _("Are you sure you want to change the status?"); ?>");
			if (conf) {
				$.post("update-covid19-sample-status.php", {
						status: obj.value,
						id: obj.id
					},
					function(data) {
						if (data != "") {
							oTable.fnDraw();
							alert("<?php echo _("Updated successfully."); ?>");
						}
					});
			} else {
				window.top.location = window.top.location;
			}
		}
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
