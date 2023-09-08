<?php

$title = _translate("Dashboard");

require_once APPLICATION_PATH . '/header.php';

?>
<style>
	.bluebox,
	.dashboard-stat2 {
		border: 1px solid #3598DC;
	}

	.searchVlRequestDataDiv .dashboard-stat2 {
		min-height: 120px;
	}

	.dashloader {
		width: 8px;
		height: 18px;
		display: block;
		margin: 60px auto;
		left: -20px;
		position: relative;

		border-radius: 4px;
		box-sizing: border-box;
		animation: animloader 1s linear infinite alternate;
	}

	@keyframes animloader {
		0% {
			box-shadow: 20px 0 rgba(0, 0, 0, 0.25), 40px 0 white, 60px 0 white;
		}

		50% {
			box-shadow: 20px 0 white, 40px 0 rgba(0, 0, 0, 0.25), 60px 0 white;
		}

		100% {
			box-shadow: 20px 0 white, 40px 0 white, 60px 0 rgba(0, 0, 0, 0.25);
		}
	}




	.input-mini {
		width: 100% !important;
	}

	.labAverageTatDiv {
		display: none;
	}

	.close {
		color: #960014 !important;
	}

	.sampleCountsDatatableDiv,
	.samplePieChartDiv {
		float: left;
		width: 100%;
	}
</style>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<div class="bs-example bs-example-tabs">
			<ul id="myTab" class="nav nav-tabs" style="font-size:1.4em;">
				<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true && array_intersect($_SESSION['modules'], array('vl'))) { ?>
					<li class="active"><a href="#vlDashboard" data-name="vl" data-toggle="tab" onclick="generateDashboard('vl');">
							<?= _translate("HIV Viral Load Tests"); ?>
						</a></li>
				<?php }
				if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true && array_intersect($_SESSION['modules'], array('eid'))) { ?>
					<li><a href="#eidDashboard" data-name="eid" data-toggle="tab" onclick="generateDashboard('eid');">
							<?= _translate("EID Tests"); ?>
						</a></li>
				<?php }
				if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true && array_intersect($_SESSION['modules'], array('covid19'))) { ?>
					<li><a href="#covid19Dashboard" data-name="covid19" data-toggle="tab" onclick="generateDashboard('covid19');">
							<?= _translate("Covid-19 Tests"); ?>
						</a></li>
				<?php }
				if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true && array_intersect($_SESSION['modules'], array('hepatitis'))) { ?>
					<li><a href="#hepatitisDashboard" data-toggle="tab" onclick="generateDashboard('hepatitis');">
							<?= _translate("Hepatitis Tests"); ?>
						</a></li>
				<?php }
				if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true && array_intersect($_SESSION['modules'], array('tb'))) { ?>
					<li><a href="#tbDashboard" data-toggle="tab" onclick="generateDashboard('tb');"><?= _translate("TB Tests"); ?></a></li>
				<?php }
				if (isset(SYSTEM_CONFIG['modules']['generic-tests']) && SYSTEM_CONFIG['modules']['generic-tests'] === true && array_intersect($_SESSION['modules'], array('generic-tests'))) { ?>
					<li><a href="#genericTestsDashboard" data-toggle="tab" onclick="generateDashboard('generic-tests');"><?= _translate("Other Lab Tests"); ?></a></li>
				<?php }
				if (isset(SYSTEM_CONFIG['recency']['vlsync']) && SYSTEM_CONFIG['recency']['vlsync'] === true) { ?>
					<li><a href="#recencyDashboard" data-name="recency" data-toggle="tab" onclick="generateDashboard('recency')">
							<?= _translate("Confirmation Tests for Recency"); ?>
						</a></li>
				<?php } ?>
			</ul>
			<div id="myTabContent" class="tab-content">

				<?php if (
					isset(SYSTEM_CONFIG['modules']['vl'])
					&& SYSTEM_CONFIG['modules']['vl'] === true && array_intersect($_SESSION['modules'], array('vl'))
				) { ?>
					<div class="tab-pane fade in active" id="vlDashboard">
						<!-- VL content -->
						<section class="content">
							<!-- Small boxes (Stat box) -->
							<div id="cont"> </div>
							<div id="contVl"> </div>
							<div class="row" style="padding-top:10px;padding-bottom:20px;">
								<div class="col-lg-7">
									<form autocomplete="off">
										<table aria-describedby="table" class="table searchTable" style="margin-left:1%;margin-top:0px;width: 98%;margin-bottom: 0px;">
											<tr>
												<th scope="row" style="vertical-align:middle;"><strong>
														<?= _translate("Date Range"); ?>&nbsp;:
													</strong></th>
												<td>
													<input type="text" id="vlSampleCollectionDate" name="vlSampleCollectionDate" class="form-control" placeholder="<?= _translate('Select Sample Collection daterange'); ?>" style="width:220px;background:#fff;" />
												</td>
												<td colspan="3">&nbsp;<input type="button" onclick="generateDashboard('vl');" value="<?= _translate('Search'); ?>" class="searchBtn btn btn-success btn-sm">
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSearchVlRequestData('vl');"><span>
															<?= _translate("Reset"); ?>
														</span></button>
												</td>
											</tr>
										</table>
									</form>
								</div>
							</div>
							<div class="row vl">
								<div class="searchVlRequestDataDiv" id="vlSampleResultDetails">

								</div>
								<div class="box-body sampleCountsDatatableDiv" id="vlNoOfSampleCount"></div>
								<div class="samplePieChartDiv" id="vlPieChartDiv"></div>
							</div>

							<!-- /.row -->
							<!-- Main row -->
							<!-- /.row (main row) -->

						</section>
						<!-- /. VL content -->
					</div>

				<?php } ?>

				<?php if (isset(SYSTEM_CONFIG['recency']['vlsync']) && SYSTEM_CONFIG['recency']['vlsync'] === true) { ?>
					<div class="tab-pane fade in" id="recencyDashboard">
						<!-- VL content -->
						<section class="content">
							<!-- Small boxes (Stat box) -->
							<div class="row" style="padding-top:10px;padding-bottom:20px;">
								<div class="col-lg-7">
									<form autocomplete="off">
										<table aria-describedby="table" class="table searchTable" style="margin-left:1%;margin-top:0px;width: 98%;margin-bottom: 0px;">
											<tr>
												<th scope="row" style="vertical-align:middle;"><strong>
														<?= _translate('Date Range'); ?>&nbsp;:
													</strong></th>
												<td>
													<input type="text" id="recencySampleCollectionDate" name="recencySampleCollectionDate" class="form-control" placeholder="<?= _translate('Select Collection Date'); ?>" style="width:220px;background:#fff;" />
												</td>
												<td colspan="3">&nbsp;<input type="button" onclick="generateDashboard('recency')" value="<?= _translate('Search'); ?>" class="searchBtn btn btn-success btn-sm">
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSearchVlRequestData('recency');"><span>
															<?= _translate('Reset'); ?>
														</span></button>
												</td>
											</tr>
										</table>
									</form>
								</div>
							</div>
							<div class="row recency">
								<div id="recencySampleResultDetails"></div>
								<div class="box-body sampleCountsDatatableDiv" id="recencyNoOfSampleCount"></div>
								<div class="samplePieChartDiv" id="recencyPieChartDiv"></div>
							</div>

							<!-- /.row -->
							<!-- Main row -->
							<!-- /.row (main row) -->

						</section>
						<!-- /. VL content -->
					</div>
				<?php } ?>
				<!-- EID START-->
				<?php if (
					isset(SYSTEM_CONFIG['modules']['eid']) &&
					SYSTEM_CONFIG['modules']['eid'] === true && array_intersect($_SESSION['modules'], array('eid'))
				) { ?>

					<div class="tab-pane fade in" id="eidDashboard">
						<!-- EID content -->
						<section class="content">
							<div id="contEid"> </div>
							<!-- Small boxes (Stat box) -->
							<div class="row" style="padding-top:10px;padding-bottom:20px;">
								<div class="col-lg-7">
									<form autocomplete="off">
										<table aria-describedby="table" class="table searchTable" style="margin-left:1%;margin-top:0px;width: 98%;margin-bottom: 0px;">
											<tr>
												<th scope="row" style="vertical-align:middle;"><strong>
														<?= _translate('Date Range'); ?>&nbsp;:
													</strong></th>
												<td>
													<input type="text" id="eidSampleCollectionDate" name="eidSampleCollectionDate" class="form-control" placeholder="<?= _translate('Select Sample Collection daterange'); ?>" style="width:220px;background:#fff;" />
												</td>
												<td colspan="3">&nbsp;<input type="button" onclick="generateDashboard('eid')" value="<?= _translate('Search'); ?>" class="searchBtn btn btn-success btn-sm">
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSearchVlRequestData('eid');"><span>
															<?= _translate('Reset'); ?>
														</span></button>
												</td>
											</tr>
										</table>
									</form>
								</div>
							</div>
							<div class="row eid">
								<div class="searchVlRequestDataDiv" id="eidSampleResultDetails"></div>
								<div class="box-body sampleCountsDatatableDiv" id="eidNoOfSampleCount"></div>
								<div class="samplePieChartDiv" id="eidPieChartDiv"></div>
							</div>

							<!-- /.row -->
							<!-- Main row -->
							<!-- /.row (main row) -->
						</section>
						<!-- /. EID content -->
					</div>

				<?php } ?>
				<!-- EID END -->
				<!-- COVID-19 START-->
				<?php if (
					isset(SYSTEM_CONFIG['modules']['covid19']) &&
					SYSTEM_CONFIG['modules']['covid19'] === true && array_intersect($_SESSION['modules'], array('covid19'))
				) { ?>

					<div class="tab-pane fade in" id="covid19Dashboard">
						<!-- COVID-19 content -->
						<section class="content">
							<div id="contCovid"> </div>
							<!-- Small boxes (Stat box) -->
							<div class="row" style="padding-top:10px;padding-bottom:20px;">
								<div class="col-lg-7">
									<form autocomplete="off">
										<table aria-describedby="table" class="table searchTable" style="margin-left:1%;margin-top:0px;width: 98%;margin-bottom: 0px;">
											<tr>
												<th scope="row" style="vertical-align:middle;"><strong>
														<?= _translate('Date Range'); ?>&nbsp;:
													</strong></th>
												<td>
													<input type="text" id="covid19SampleCollectionDate" name="covid19SampleCollectionDate" class="form-control" placeholder="<?= _translate('Select Sample Collection daterange'); ?>" style="width:220px;background:#fff;" />
												</td>
												<td colspan="3">&nbsp;<input type="button" onclick="generateDashboard('covid19')" value="<?= _translate('Search'); ?>" class="searchBtn btn btn-success btn-sm">
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSearchVlRequestData('covid19');"><span>
															<?= _translate('Reset'); ?>
														</span></button>
												</td>
											</tr>
										</table>
									</form>
								</div>
							</div>
							<div class="row covid19">
								<div class="searchVlRequestDataDiv" id="covid19SampleResultDetails"></div>
								<div class="box-body sampleCountsDatatableDiv" id="covid19NoOfSampleCount"></div>
								<div class="samplePieChartDiv" id="covid19PieChartDiv"></div>
							</div>

							<!-- /.row -->
							<!-- Main row -->
							<!-- /.row (main row) -->
						</section>
						<!-- /. COVID-19 content -->
					</div>

				<?php } ?>
				<!-- COVID-19 END -->

				<!-- Hepatitis START-->
				<?php if (
					isset(SYSTEM_CONFIG['modules']['hepatitis']) &&
					SYSTEM_CONFIG['modules']['hepatitis'] === true && array_intersect($_SESSION['modules'], array('hepatitis'))
				) { ?>

					<div class="tab-pane fade in" id="hepatitisDashboard">
						<!-- COVID-19 content -->
						<section class="content">
							<div id="contCovid"> </div>
							<!-- Small boxes (Stat box) -->
							<div class="row" style="padding-top:10px;padding-bottom:20px;">
								<div class="col-lg-7">
									<form autocomplete="off">
										<table aria-describedby="table" class="table searchTable" style="margin-left:1%;margin-top:0px;width: 98%;margin-bottom: 0px;">
											<tr>
												<th scope="row" style="vertical-align:middle;"><strong>
														<?= _translate('Date Range'); ?>&nbsp;:
													</strong></th>
												<td>
													<input type="text" id="hepatitisSampleCollectionDate" name="hepatitisSampleCollectionDate" class="form-control" placeholder="<?= _translate('Select Sample Collection daterange'); ?>" style="width:220px;background:#fff;" />
												</td>
												<td colspan="3">&nbsp;<input type="button" onclick="generateDashboard('hepatitis')" value="<?= _translate('Search'); ?>" class="searchBtn btn btn-success btn-sm">
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSearchVlRequestData('hepatitis');"><span>
															<?= _translate('Reset'); ?>
														</span></button>
												</td>
											</tr>
										</table>
									</form>
								</div>
							</div>
							<div class="row hepatitis">
								<div class="searchVlRequestDataDiv" id="hepatitisSampleResultDetails"></div>
								<div class="box-body sampleCountsDatatableDiv" id="hepatitisNoOfSampleCount"></div>
								<div class="samplePieChartDiv" id="hepatitisPieChartDiv"></div>
							</div>

							<!-- /.row -->
							<!-- Main row -->
							<!-- /.row (main row) -->
						</section>
						<!-- /. Hepatitis content -->
					</div>

				<?php } ?>
				<!-- Hepatitis END -->

				<!-- TB START-->
				<?php if (
					isset(SYSTEM_CONFIG['modules']['tb']) &&
					SYSTEM_CONFIG['modules']['tb'] === true && array_intersect($_SESSION['modules'], array('tb'))
				) { ?>

					<div class="tab-pane fade in" id="tbDashboard">
						<!-- TB content -->
						<section class="content">
							<div id="contCovid"> </div>
							<!-- Small boxes (Stat box) -->
							<div class="row" style="padding-top:10px;padding-bottom:20px;">
								<div class="col-lg-7">
									<form autocomplete="off">
										<table aria-describedby="table" class="table searchTable" style="margin-left:1%;margin-top:0px;width: 98%;margin-bottom: 0px;">
											<tr>
												<th scope="row" style="vertical-align:middle;"><strong>
														<?= _translate('Date Range'); ?>&nbsp;:
													</strong></th>
												<td>
													<input type="text" id="tbSampleCollectionDate" name="tbSampleCollectionDate" class="form-control" placeholder="<?= _translate('Select Sample Collection daterange'); ?>" style="width:220px;background:#fff;" />
												</td>
												<td colspan="3">&nbsp;<input type="button" onclick="generateDashboard('tb')" value="<?= _translate('Search'); ?>" class="searchBtn btn btn-success btn-sm">
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSearchVlRequestData('tb');"><span>
															<?= _translate('Reset'); ?>
														</span></button>
												</td>
											</tr>
										</table>
									</form>
								</div>
							</div>
							<div class="row tb">
								<div class="searchVlRequestDataDiv" id="tbSampleResultDetails"></div>
								<div class="box-body sampleCountsDatatableDiv" id="tbNoOfSampleCount"></div>
								<div class="samplePieChartDiv" id="tbPieChartDiv"></div>
							</div>

							<!-- /.row -->
							<!-- Main row -->
							<!-- /.row (main row) -->
						</section>
						<!-- /. TB content -->
					</div>

				<?php } ?>
				<!-- TB END -->

				<!-- OTHER LAB TESTS START-->
				<?php if (
					isset(SYSTEM_CONFIG['modules']['generic-tests']) &&
					SYSTEM_CONFIG['modules']['generic-tests'] === true && array_intersect($_SESSION['modules'], array('generic-tests'))
				) { ?>

					<div class="tab-pane fade in" id="genericTestsDashboard">
						<!-- OTHER LAB TESTS content -->
						<section class="content">
							<div id="contCovid"> </div>
							<!-- Small boxes (Stat box) -->
							<div class="row" style="padding-top:10px;padding-bottom:20px;">
								<div class="col-lg-7">
									<form autocomplete="off">
										<table aria-describedby="table" class="table searchTable" style="margin-left:1%;margin-top:0px;width: 98%;margin-bottom: 0px;">
											<tr>
												<th scope="row" style="vertical-align:middle;"><strong>
														<?= _translate('Date Range'); ?>&nbsp;:
													</strong></th>
												<td>
													<input type="text" id="genericTestsSampleCollectionDate" name="genericTestsSampleCollectionDate" id="genericTestsSampleCollectionDate" class="form-control" placeholder="<?= _translate('Select Sample Collection daterange'); ?>" style="width:220px;background:#fff;" />
												</td>
												<td colspan="3">&nbsp;<input type="button" onclick="generateDashboard('generic-tests')" value="<?= _translate('Search'); ?>" class="searchBtn btn btn-success btn-sm">
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSearchVlRequestData('generic-tests');"><span>
															<?= _translate('Reset'); ?>
														</span></button>
												</td>
											</tr>
										</table>
									</form>
								</div>
							</div>
							<div class="row generic-tests">
								<div class="searchVlRequestDataDiv" id="genericTestsSampleResultDetails"></div>
								<div class="box-body sampleCountsDatatableDiv" id="genericTestsNoOfSampleCount"></div>
								<div class="samplePieChartDiv" id="genericTestsPieChartDiv"></div>
							</div>

							<!-- /.row -->
							<!-- Main row -->
							<!-- /.row (main row) -->
						</section>
						<!-- /. OTHER LAB TESTS content -->
					</div>

				<?php } ?>
				<!-- OTHER LAB TESTS END -->

			</div>
		</div>
	</section>
</div>

<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script src="/assets/js/highcharts.js"></script>
<script src="/assets/js/exporting.js"></script>
<script src="/assets/js/accessibility.js"></script>


<script>
	$.fn.isInViewport = function() {
		var elementTop = $(this).offset().top;
		var elementBottom = elementTop + $(this).outerHeight();

		var viewportTop = $(window).scrollTop();
		var viewportBottom = viewportTop + $(window).height();

		return elementBottom > viewportTop && elementTop < viewportBottom;
	};

	let currentRequestType = null;
	let sampleCountsDatatableCounter = 0;
	let samplePieChartCounter = 0;
	let currentXHR = null;

	$(function() {


		$(".searchVlRequestDataDiv").html('<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 "> <div class="dashboard-stat2 bluebox" style="cursor:pointer;"> <span class="dashloader"></span></div> </div> <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 "> <div class="dashboard-stat2" style="cursor:pointer;"><span class="dashloader"></span> </div> </div> <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12 "> <div class="dashboard-stat2 " style="cursor:pointer;"> <span class="dashloader"></span></div> </div> <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12 "> <div class="dashboard-stat2 " style="cursor:pointer;"> <span class="dashloader"></span></div> </div> <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 "> <div class="dashboard-stat2 bluebox" style="cursor:pointer;"> <span class="dashloader"></span></div> </div>');
		$("#myTab li:first-child").addClass("active");
		$("#myTabContent div:first-child").addClass("active");
		// $("#myTabContent div:first-child table.searchTable .searchBtn").trigger("click");

		$('#vlSampleCollectionDate,#eidSampleCollectionDate,#covid19SampleCollectionDate,#recencySampleCollectionDate,#hepatitisSampleCollectionDate,#tbSampleCollectionDate,#genericTestsSampleCollectionDate').daterangepicker({
				locale: {
					cancelLabel: "<?= _translate("Clear"); ?>",
					format: 'DD-MMM-YYYY',
					separator: ' to ',
				},
				showDropdowns: true,
				alwaysShowCalendars: false,
				startDate: moment().subtract(28, 'days'),
				endDate: moment(),
				maxDate: moment(),
				ranges: {
					'Today': [moment(), moment()],
					'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
					'Last 7 Days': [moment().subtract(6, 'days'), moment()],
					'Last 30 Days': [moment().subtract(29, 'days'), moment()],
					'This Month': [moment().startOf('month'), moment().endOf('month')],
					'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
					'Last 30 Days': [moment().subtract(29, 'days'), moment()],
					'Last 90 Days': [moment().subtract(89, 'days'), moment()],
					'Last 120 Days': [moment().subtract(119, 'days'), moment()],
					'Last 180 Days': [moment().subtract(179, 'days'), moment()],
					'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')]
				}
			},
			function(start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});
		$("#myTab li:first-child > a").trigger("click");
	});

	function generateDashboard(requestType) {
		currentRequestType = requestType;
		sampleCountsDatatableCounter = 0;
		samplePieChartCounter = 0;

		$.when(
				searchVlRequestData(currentRequestType),
			)
			.done(function() {
				$.unblockUI();
				$(window).scroll();
			});


		$(window).on('beforeunload', function() {
			if (currentXHR !== null && currentXHR !== undefined) {
				currentXHR.abort();
			}
		});

		$(window).on('resize scroll', function() {

			if (sampleCountsDatatableCounter == 0) {
				if ($("." + currentRequestType + " .sampleCountsDatatableDiv").isInViewport()) {
					sampleCountsDatatableCounter++;
					// $.blockUI();
					$.when(
						getNoOfSampleCount(currentRequestType),
					).done(function() {
						getSamplesOverview(currentRequestType);
					}).done(function() {
						$.unblockUI();
					});
				}
			}
			// if (samplePieChartCounter == 0) {
			// 	if ($("." + currentRequestType + " .samplePieChartDiv").isInViewport()) {
			// 		samplePieChartCounter++;
			// 		getSamplesOverview(currentRequestType);
			// 	}
			// }
		});

		<?php if (!empty($arr['vl_monthly_target']) && $arr['vl_monthly_target'] == 'yes') { ?>
			if (requestType == 'vl') {
				getVlMonthlyTargetsReport();
				getVlSuppressionTargetReport();
			} else if (requestType == 'eid') {
				getEidMonthlyTargetsReport();
			} else if (requestType == 'covid19') {
				getCovid19MonthlyTargetsReport();
			} else if (requestType == 'hepatitis') {
				getHepatitisMonthlyTargetsReport();
			} else if (requestType == 'tb') {
				getTbMonthlyTargetsReport();
			}
		<?php } ?>

	}

	function searchVlRequestData(requestType) {
		//$.blockUI();
		if (requestType == 'vl') {
			currentXHR = $.post("/dashboard/getSampleResult.php", {
					sampleCollectionDate: $("#vlSampleCollectionDate").val(),
					type: 'vl'
				},
				function(data) {
					if (data != '') {
						$("#vlSampleResultDetails").html(data);
					}
				});
		} else if (requestType == 'recency') {
			currentXHR = $.post("/dashboard/getSampleResult.php", {
					sampleCollectionDate: $("#recencySampleCollectionDate").val(),
					type: 'recency'
				},
				function(data) {
					if (data != '') {
						$("#recencySampleResultDetails").html(data);
					}
				});
		} else if (requestType == 'eid') {
			currentXHR = $.post("/dashboard/getSampleResult.php", {
					sampleCollectionDate: $("#eidSampleCollectionDate").val(),
					type: 'eid'
				},
				function(data) {
					if (data != '') {
						$("#eidSampleResultDetails").html(data);
					}
				});
		} else if (requestType == 'covid19') {
			currentXHR = $.post("/dashboard/getSampleResult.php", {
					sampleCollectionDate: $("#covid19SampleCollectionDate").val(),
					type: 'covid19'
				},
				function(data) {
					if (data != '') {
						$("#covid19SampleResultDetails").html(data);
					}
				});
		} else if (requestType == 'hepatitis') {
			currentXHR = $.post("/dashboard/getSampleResult.php", {
					sampleCollectionDate: $("#hepatitisSampleCollectionDate").val(),
					type: 'hepatitis'
				},
				function(data) {
					if (data != '') {
						$("#hepatitisSampleResultDetails").html(data);
					}
				});
		} else if (requestType == 'tb') {
			currentXHR = $.post("/dashboard/getSampleResult.php", {
					sampleCollectionDate: $("#tbSampleCollectionDate").val(),
					type: 'tb'
				},
				function(data) {
					if (data != '') {
						$("#tbSampleResultDetails").html(data);
					}
				});
		} else if (requestType == 'generic-tests') {
			console.log($("#genericTestsSampleCollectionDate").val());
			currentXHR = $.post("/dashboard/getSampleResult.php", {
					sampleCollectionDate: $("#genericTestsSampleCollectionDate").val(),
					type: 'generic-tests'
				},
				function(data) {
					if (data != '') {
						$("#genericTestsSampleResultDetails").html(data);
					}
				});
		}

		return currentXHR;

	}

	function getNoOfSampleCount(requestType) {
		if (requestType == 'vl') {
			currentXHR = $.post("/dashboard/getSampleCount.php", {
					sampleCollectionDate: $("#vlSampleCollectionDate").val(),
					type: 'vl'
				},
				function(data) {
					if (data != '') {
						$("#vlNoOfSampleCount").html(data);
					}
				});
		} else if (requestType == 'recency') {
			currentXHR = $.post("/dashboard/getSampleCount.php", {
					sampleCollectionDate: $("#recencySampleCollectionDate").val(),
					type: 'recency'
				},
				function(data) {
					if (data != '') {
						$("#recencyNoOfSampleCount").html(data);
					}
				});
		} else if (requestType == 'eid') {
			currentXHR = $.post("/dashboard/getSampleCount.php", {
					sampleCollectionDate: $("#eidSampleCollectionDate").val(),
					type: 'eid'
				},
				function(data) {
					if (data != '') {
						$("#eidNoOfSampleCount").html(data);
					}
				});
		} else if (requestType == 'generic-tests') {
			currentXHR = $.post("/dashboard/getSampleCount.php", {
					sampleCollectionDate: $("#genericTestsSampleCollectionDate").val(),
					type: 'generic-tests'
				},
				function(data) {
					if (data != '') {
						$("#genericTestsNoOfSampleCount").html(data);
					}
				});
		}

		return currentXHR;
	}

	function getSamplesOverview(requestType) {
		if (requestType == 'vl') {
			currentXHR = $.post("/vl/program-management/getSampleStatus.php", {
					sampleCollectionDate: $("#vlSampleCollectionDate").val(),
					batchCode: '',
					facilityName: '',
					sampleType: '',
					type: 'vl'
				},
				function(data) {
					if ($.trim(data) != '') {
						$("#vlPieChartDiv").html(data);
						$(".labAverageTatDiv").css("display", "none");
					}
				});
		} else if (requestType == 'recency') {
			currentXHR = $.post("/vl/program-management/getSampleStatus.php", {
					sampleCollectionDate: $("#recencySampleCollectionDate").val(),
					batchCode: '',
					facilityName: '',
					sampleType: '',
					type: 'recency'
				},
				function(data) {
					if ($.trim(data) != '') {
						$("#recencyPieChartDiv").html(data);
						$(".labAverageTatDiv").css("display", "none");
					}
				});
		} else if (requestType == 'eid') {
			currentXHR = $.post("/eid/management/getSampleStatus.php", {
					sampleCollectionDate: $("#eidSampleCollectionDate").val(),
					batchCode: '',
					facilityName: '',
					sampleType: '',
					type: 'eid'
				},
				function(data) {
					if ($.trim(data) != '') {
						$("#eidPieChartDiv").html(data);
						$(".labAverageTatDiv").css("display", "none");
					}
				});
		} else if (requestType == 'covid19') {
			currentXHR = $.post("/covid-19/management/getSampleStatus.php", {
					sampleCollectionDate: $("#covid19SampleCollectionDate").val(),
					batchCode: '',
					facilityName: '',
					sampleType: '',
					type: 'covid19'
				},
				function(data) {
					if ($.trim(data) != '') {
						$("#covid19PieChartDiv").html(data);
						$(".labAverageTatDiv").css("display", "none");
					}
				});
		} else if (requestType == 'tb') {
			currentXHR = $.post("/tb/management/getSampleStatus.php", {
					sampleCollectionDate: $("#tbSampleCollectionDate").val(),
					batchCode: '',
					facilityName: '',
					sampleType: '',
					type: 'tb'
				},
				function(data) {
					if ($.trim(data) != '') {
						$("#tbPieChartDiv").html(data);
						$(".labAverageTatDiv").css("display", "none");
					}
				});
		} else if (requestType == 'generic-tests') {
			currentXHR = $.post("/generic-tests/program-management/get-sample-status.php", {
					sampleCollectionDate: $("#genericTestsSampleCollectionDate").val(),
					batchCode: '',
					facilityName: '',
					sampleType: '',
					type: 'generic-tests'
				},
				function(data) {
					if ($.trim(data) != '') {
						$("#genericTestsPieChartDiv").html(data);
						$(".labAverageTatDiv").css("display", "none");
					}
				});
		}

		return currentXHR;
	}

	function resetSearchVlRequestData(requestType) {
		$('#vlSampleCollectionDate,#eidSampleCollectionDate,#recencySampleCollectionDate,#tbSampleCollectionDate,#genericTestsSampleCollectionDate').daterangepicker({
			locale: {
				cancelLabel: "<?= _translate("Clear"); ?>",
				format: 'DD-MMM-YYYY',
				separator: ' to ',
			},
			showDropdowns: true,
			alwaysShowCalendars: false,
			startDate: moment().subtract(28, 'days'),
			endDate: moment(),
			maxDate: moment(),
			ranges: {
				'Today': [moment(), moment()],
				'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
				'Last 7 Days': [moment().subtract(6, 'days'), moment()],
				'Last 30 Days': [moment().subtract(29, 'days'), moment()],
				'This Month': [moment().startOf('month'), moment().endOf('month')],
				'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
				'Last 30 Days': [moment().subtract(29, 'days'), moment()],
				'Last 90 Days': [moment().subtract(89, 'days'), moment()],
				'Last 120 Days': [moment().subtract(119, 'days'), moment()],
				'Last 180 Days': [moment().subtract(179, 'days'), moment()],
				'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')]
			}
		}, function(start, end) {
			startDate = start.format('YYYY-MM-DD');
			endDate = end.format('YYYY-MM-DD');
		});
		generateDashboard(requestType);
	}

	function getEidMonthlyTargetsReport() {
		// $.blockUI();

		$.post("/eid/management/getEidMonthlyThresholdReport.php", {
				targetType: '1',
				sampleTestDate: $("#vlSampleCollectionDate").val(),
			},
			function(data) {
				var data = JSON.parse(data);
				// console.log(data['aaData'].length);
				if (data['aaData'].length > 0) {
					var div = '<div class="alert alert-danger alert-dismissible" role="alert" style="background-color: #ff909f !important">\
							<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="text-indent: 0px"><span aria-hidden="true" style="font-size: larger;font-weight: bolder;color: #000000;">&times;</span></button>\
							<span>' + data['aaData'].length + ' <?= addslashes(_translate("EID testing lab(s) did not meet the monthly test target")); ?>. </span><a href="/eid/management/eidTestingTargetReport.php" target="_blank"> <?= _translate("more"); ?> </a>\
							</div>';
					$("#contEid").html(div);
				}



			});
		$.unblockUI();
	}

	function getVlMonthlyTargetsReport() {
		// $.blockUI();

		$.post("/vl/program-management/getVlMonthlyThresholdReport.php", {
				targetType: '1',
				sampleTestDate: $("#vlSampleCollectionDate").val(),
			},
			function(data) {
				var data = JSON.parse(data);
				// console.log(data['aaData'].length);
				if (data['aaData'].length > 0) {
					var div = '<div class="alert alert-danger alert-dismissible" role="alert" style="background-color: #ff909f !important">\
							<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="text-indent: 0px"><span aria-hidden="true" style="font-size: larger;font-weight: bolder;color: #000000;">&times;</span></button>\
							<span>' + data['aaData'].length + ' <?= addslashes(_translate("VL testing lab(s) did not meet the monthly test target")); ?>. </span><a href="/vl/program-management/vlTestingTargetReport.php" target="_blank"> <?= _translate("more"); ?> </a>\
							</div>';
					$("#cont").html(div);
				}



			});
		$.unblockUI();
	}

	function getVlSuppressionTargetReport() {
		// $.blockUI();

		$.post("/vl/program-management/getSuppressedTargetReport.php", {
				targetType: '1',
				sampleTestDate: $("#vlSampleCollectionDate").val(),
			},
			function(data) {
				// console.log(data)
				if (data == 1) {
					var div = '<div class="alert alert-danger alert-dismissible" role="alert" style="background-color: #ff909f !important">\
							<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="text-indent: 0px"><span aria-hidden="true" style="font-size: larger;font-weight: bolder;color: #000000;">&times;</span></button>\
							<span> <?= addslashes(_translate("VL testing lab(s) did not meet suppression targets")); ?> </span><a href="/vl/program-management/vlSuppressedTargetReport.php" target="_blank"> <?= _translate("more"); ?> </a>\
							</div>';
					$("#contVl").html(div);
				}



			});
		$.unblockUI();
	}

	function getCovid19MonthlyTargetsReport() {
		// $.blockUI();

		$.post("/covid-19/management/getCovid19MonthlyThresholdReport.php", {
				targetType: '1',
				sampleTestDate: $("#vlSampleCollectionDate").val(),
			},
			function(data) {
				var data = JSON.parse(data);
				// console.log(data['aaData'].length);
				if (data['aaData'].length > 0) {
					var div = '<div class="alert alert-danger alert-dismissible" role="alert" style="background-color: #ff909f !important">\
							<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="text-indent: 0px"><span aria-hidden="true" style="font-size: larger;font-weight: bolder;color: #000000;">&times;</span></button>\
							<span >' + data['aaData'].length + ' <?= addslashes(_translate("Covid-19 testing lab(s) did not meet the monthly test target")); ?>.  </span><a href="/covid-19/management/covid19TestingTargetReport.php" target="_blank"> <?= _translate("more"); ?> </a>\
							</div>';
					$("#contCovid").html(div);
				}



			});
		$.unblockUI();
	}

	function getHepatitisMonthlyTargetsReport() {
		// $.blockUI();

		$.post("/hepatitis/management/get-hepatitis-monthly-threshold-report.php", {
				targetType: '1',
				sampleTestDate: $("#vlSampleCollectionDate").val(),
			},
			function(data) {
				var data = JSON.parse(data);
				// console.log(data['aaData'].length);
				if (data['aaData'].length > 0) {
					var div = '<div class="alert alert-danger alert-dismissible" role="alert" style="background-color: #ff909f !important">\
				<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="text-indent: 0px"><span aria-hidden="true" style="font-size: larger;font-weight: bolder;color: #000000;">&times;</span></button>\
				<span >' + data['aaData'].length + ' <?= addslashes(_translate("Hepatitis testing lab(s) did not meet the monthly test target")); ?>.  </span><a href="/hepatitis/management/hepatitis-testing-target-report.php" target="_blank"> <?= _translate("more"); ?> </a>\
				</div>';
					$("#contCovid").html(div);
				}
			});
		$.unblockUI();
	}

	function getTbMonthlyTargetsReport() {
		// $.blockUI();

		$.post("/tb/management/get-tb-monthly-threshold-report.php", {
				targetType: '1',
				sampleTestDate: $("#tbSampleCollectionDate").val(),
			},
			function(data) {
				var data = JSON.parse(data);
				// console.log(data['aaData'].length);
				if (data['aaData'].length > 0) {
					var div = '<div class="alert alert-danger alert-dismissible" role="alert" style="background-color: #ff909f !important">\
				<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="text-indent: 0px"><span aria-hidden="true" style="font-size: larger;font-weight: bolder;color: #000000;">&times;</span></button>\
				<span >' + data['aaData'].length + ' <?= addslashes(_translate("TB testing lab(s) did not meet the monthly test target")); ?>.  </span><a href="/hepatitis/management/hepatitis-testing-target-report.php" target="_blank"> <?= _translate("more"); ?> </a>\
				</div>';
					$("#contCovid").html(div);
				}



			});
		$.unblockUI();
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
