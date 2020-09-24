<?php
ob_start();
$type = (!empty($_GET['type'])) ? $_GET['type'] : $_GET['type'];
if ($type == 'health-facilities') {
	$title = "Manage Health Facilities";
} else if ($type == 'testing-labs') {
	$title = "Manage Testing Labs";
}
#require_once('../startup.php');
include_once(APPLICATION_PATH . '/header.php');



?>
<link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" />
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
	.select2-selection__choice {
		color: #000000 !important;
	}

	.boxWidth,
	.eid_boxWidth {
		width: 10%;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1 class="fa fa-gears"> <?php echo $title; ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
			<li class="active"> <?php echo $title; ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='facilityTestMapForm' id='facilityTestMapForm' enctype="multipart/form-data" autocomplete="off" action="mapTestTypeHelper.php">
					<div class="box-body">
						<div class="panel panel-default">
							<div class="panel-heading">
								<h3 class="panel-title"> <?php echo $title; ?></h3>
							</div>
							<div class="panel-body">
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="testType" class="col-lg-4 control-label">Test Type</label>
											<div class="col-lg-8">
												<select type="text" class="form-control" id="testType" name="testType" title="Choose one test type" onchange="selectedTestType(this.value);">
													<option value="">--Select--</option>
													<option value="vl">Viral Load</option>
													<option value="eid">Early Infant Diagnosis</option>
													<option value="covid19">Covid-19</option>
												</select>
											</div>
										</div>
									</div>
								</div>

								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="facilities" class="col-lg-4 control-label">Select the <?php echo str_replace("Manage", "", $title); ?> for test type </label>
											<div class="col-lg-8">
												<div class="form-group">
													<div class="col-md-12">
														<div class="row">
															<div class="col-md-12" style="text-align:justify;">
																<!-- <code>If any of the selected fields are incomplete, the Result PDF appears with a <strong>DRAFT</strong> watermark. Leave right block blank (Deselect All) to disable this.</code> -->
															</div>
														</div>
														<div style="width:100%;margin:10px auto;clear:both;">
															<a href="#" id="select-all-field" style="float:left;" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<i class="icon-chevron-right"></i></a> <a href="#" id="deselect-all-field" style="float:right;" class="btn btn-danger btn-xs"><i class="icon-chevron-left"></i>&nbsp;Deselect All</a>
														</div><br /><br />
														<select id="facilities" name="facilities[]" multiple="multiple" class="search">
															<?php foreach ($facilityResult as $facility) {
																echo '<option value="' . $facility['facility_id'] . '">' . $facility['facility_name'] . '</option>';
															} ?>
														</select>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<!-- /.box-body -->
						<div class="box-footer">
							<input type="hidden" name="facilityType" class="form-control" id="facilityType" value="<?php echo $type; ?>" />
							<input type="hidden" name="mappedFacilities" id="mappedFacilities" value="" />
							<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
							<a href="facilities.php" class="btn btn-default"> Cancel</a>
						</div>
						<!-- /.box-footer -->
					</div>
				</form>
				<!-- /.row -->
			</div>

		</div>
		<!-- /.box -->

	</section>
	<!-- /.content -->
</div>
<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<script src="/assets/js/jquery.multi-select.js"></script>
<script src="/assets/js/jquery.quicksearch.js"></script>
<script type="text/javascript">
	$(document).ready(function() {
		init();

		$('#select-all-field').click(function() {
			$('#facilities').multiSelect('select_all');
			return false;
		});
		$('#deselect-all-field').click(function() {
			$('#facilities').multiSelect('deselect_all');
			return false;
		});
	});

	function validateNow() {

		let mappedFacilities = JSON.stringify($("#facilities").val());
		$("#mappedFacilities").val(mappedFacilities);
		$("#facilities").val(""); // THIS IS IMPORTANT. TO REDUCE NUMBER OF PHP VARIABLES		
		flag = deforayValidator.init({
			formId: 'facilityTestMapForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('facilityTestMapForm').submit();
		}
	}

	function selectedTestType(val) {
		$.blockUI({
			message: '<h3>Trying to get mapped facilities <br>Please wait...</h3>'
		});
		$.post("getTestTypeFacilitiesHelper.php", {
				facilityType: $('#facilityType').val(),
				testType: $('#testType').val()
			},
			function(toAppend) {
				if (toAppend != "") {
					if (toAppend != null && toAppend != undefined) {
						$('.search').html(toAppend)
						$('.search').multiSelect('refresh');
						$.unblockUI();
					}
				}
			});
	}

	function init() {

		$('.search').multiSelect({
			selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Field Name'>",
			selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Field Name'>",
			afterInit: function(ms) {
				var that = this,
					$selectableSearch = that.$selectableUl.prev(),
					$selectionSearch = that.$selectionUl.prev(),
					selectableSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selectable:not(.ms-selected)',
					selectionSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selection.ms-selected';

				that.qs1 = $selectableSearch.quicksearch(selectableSearchString)
					.on('keydown', function(e) {
						if (e.which === 40) {
							that.$selectableUl.focus();
							return false;
						}
					});

				that.qs2 = $selectionSearch.quicksearch(selectionSearchString)
					.on('keydown', function(e) {
						if (e.which == 40) {
							that.$selectionUl.focus();
							return false;
						}
					});
			},
			afterSelect: function() {
				this.qs1.cache();
				this.qs2.cache();
			},
			afterDeselect: function() {
				this.qs1.cache();
				this.qs2.cache();
			}
		});
	}
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>