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
		<div class="bs bs-tabs">
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
				if (isset(SYSTEM_CONFIG['modules']['cd4']) && SYSTEM_CONFIG['modules']['cd4'] === true && array_intersect($_SESSION['modules'], array('cd4'))) { ?>
					<li><a href="#cd4Dashboard" data-toggle="tab" onclick="generateDashboard('cd4');"><?= _translate("CD4 Tests"); ?></a></li>
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
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSampleResultData('vl');"><span>
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
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSampleResultData('recency');"><span>
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
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSampleResultData('eid');"><span>
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
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSampleResultData('covid19');"><span>
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
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSampleResultData('hepatitis');"><span>
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
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSampleResultData('tb');"><span>
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


				<!-- CD4 START-->
				<?php if (
					isset(SYSTEM_CONFIG['modules']['cd4']) &&
					SYSTEM_CONFIG['modules']['cd4'] === true && array_intersect($_SESSION['modules'], array('cd4'))
				) { ?>

					<div class="tab-pane fade in" id="cd4Dashboard">
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
													<input type="text" id="cd4SampleCollectionDate" name="cd4SampleCollectionDate" class="form-control" placeholder="<?= _translate('Select Sample Collection daterange'); ?>" style="width:220px;background:#fff;" />
												</td>
												<td colspan="3">&nbsp;<input type="button" onclick="generateDashboard('cd4')" value="<?= _translate('Search'); ?>" class="searchBtn btn btn-success btn-sm">
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSampleResultData('cd4');"><span>
															<?= _translate('Reset'); ?>
														</span></button>
												</td>
											</tr>
										</table>
									</form>
								</div>
							</div>
							<div class="row cd4">
								<div class="searchVlRequestDataDiv" id="cd4SampleResultDetails"></div>
								<div class="box-body sampleCountsDatatableDiv" id="cd4NoOfSampleCount"></div>
								<div class="samplePieChartDiv" id="cd4PieChartDiv"></div>
							</div>

							<!-- /.row -->
							<!-- Main row -->
							<!-- /.row (main row) -->
						</section>
						<!-- /. CD4 content -->
					</div>

				<?php } ?>
				<!-- CD4 END -->



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
													&nbsp;<button class="btn btn-danger btn-sm" onclick="resetSampleResultData('generic-tests');"><span>
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
	let currentRequests = [];
	let isGeneratingDashboard = false;

	// Function to abort all current requests
	function abortAllRequests() {
		currentRequests.forEach(xhr => {
			if (xhr && xhr.readyState !== 4) {
				xhr.abort();
			}
		});
		currentRequests = [];
		isGeneratingDashboard = false;
		$.unblockUI();
	}

	$(function() {
		// Abort requests on page unload
		$(window).on('beforeunload', abortAllRequests);

		// Allow navigation away even while dashboard is loading
		$(document).on('click', 'a:not([data-toggle])', function(e) {
			// If user clicks a link while dashboard is generating, abort and allow navigation
			if (isGeneratingDashboard && !$(this).hasClass('searchBtn')) {
				abortAllRequests();
				// Allow the link to proceed
			}
		});

		// Handle sidebar menu clicks during loading
		$('.sidebar-menu').on('click', 'a', function(e) {
			if (isGeneratingDashboard) {
				abortAllRequests();
			}
		});

		$(".searchVlRequestDataDiv").html('<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 "> <div class="dashboard-stat2 bluebox" style="cursor:pointer;"> <span class="dashloader"></span></div> </div> <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 "> <div class="dashboard-stat2" style="cursor:pointer;"><span class="dashloader"></span> </div> </div> <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12 "> <div class="dashboard-stat2 " style="cursor:pointer;"> <span class="dashloader"></span></div> </div> <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12 "> <div class="dashboard-stat2 " style="cursor:pointer;"> <span class="dashloader"></span></div> </div> <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 "> <div class="dashboard-stat2 bluebox" style="cursor:pointer;"> <span class="dashloader"></span></div> </div>');
		$("#myTab li:first-child").addClass("active");
		$("#myTabContent div:first-child").addClass("active");

		$('#vlSampleCollectionDate,#eidSampleCollectionDate,#covid19SampleCollectionDate,#recencySampleCollectionDate,#hepatitisSampleCollectionDate,#tbSampleCollectionDate,#cd4SampleCollectionDate,#genericTestsSampleCollectionDate').daterangepicker({
				locale: {
					cancelLabel: "<?= _translate("Clear", true); ?>",
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
					'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
					'Previous Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
					'Current Year To Date': [moment().startOf('year'), moment()]
				}
			},
			function(start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});

		// Load first tab immediately but make it non-blocking
		// Use setTimeout with 0ms to break out of the synchronous execution chain
		setTimeout(function() {
			$("#myTab li:first-child > a").trigger("click");
		}, 0);
	});

	function generateDashboard(requestType) {
		// Prevent multiple simultaneous generations of the SAME dashboard
		if (isGeneratingDashboard && currentRequestType === requestType) {
			console.log('Dashboard generation already in progress for ' + requestType);
			return;
		}

		// If switching to a different dashboard, abort previous requests
		if (isGeneratingDashboard && currentRequestType !== requestType) {
			abortAllRequests();
		}

		isGeneratingDashboard = true;
		currentRequestType = requestType;
		sampleCountsDatatableCounter = 0;
		samplePieChartCounter = 0;

		// Fetch the first data asynchronously using jQuery's .done() and .fail() instead of .then() and .catch()
		fetchSampleResultData(currentRequestType)
			.done(function() {
				// Small delay to allow DOM to update and remain responsive
				setTimeout(function() {
					$.unblockUI();
					// Trigger scroll to check viewport immediately
					$(window).trigger('scroll');
					isGeneratingDashboard = false;
				}, 0);
			})
			.fail(function(error) {
				// Handle abort or other errors
				if (error.statusText !== 'abort') {
					console.error('Dashboard generation error:', error);
				}
				isGeneratingDashboard = false;
			});

		// Lazy load charts when they come into viewport
		// Remove any existing scroll handlers first to prevent duplicates
		$(window).off('resize.dashboard scroll.dashboard');

		$(window).on('resize.dashboard scroll.dashboard', function() {
			if (sampleCountsDatatableCounter == 0) {
				if ($("." + currentRequestType + " .sampleCountsDatatableDiv").isInViewport()) {
					sampleCountsDatatableCounter++;

					// Load charts in parallel using $.when
					$.when(
						getSampleCountsForDashboard(currentRequestType),
						getSamplesOverview(currentRequestType)
					).done(function() {
						$.unblockUI();
					}).fail(function(error) {
						if (error.statusText !== 'abort') {
							console.error('Chart loading error:', error);
						}
						$.unblockUI();
					});
				}
			}
		});

		<?php if (!empty($arr['vl_monthly_target']) && $arr['vl_monthly_target'] == 'yes') { ?>
			// Load target reports asynchronously in the background
			// These shouldn't block anything
			setTimeout(function() {
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
				} else if (requestType == 'cd4') {
					getCd4MonthlyTargetsReport();
				}
			}, 50);
		<?php } ?>
	}

	function fetchSampleResultData(requestType) {
		let xhr;
		if (requestType == 'vl') {
			xhr = $.ajax({
				url: "/dashboard/getSampleResult.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#vlSampleCollectionDate").val(),
					type: 'vl'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#vlSampleResultDetails").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching VL sample result data:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'recency') {
			xhr = $.ajax({
				url: "/dashboard/getSampleResult.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#recencySampleCollectionDate").val(),
					type: 'recency'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#recencySampleResultDetails").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching recency sample result data:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'eid') {
			xhr = $.ajax({
				url: "/dashboard/getSampleResult.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#eidSampleCollectionDate").val(),
					type: 'eid'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#eidSampleResultDetails").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching EID sample result data:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'covid19') {
			xhr = $.ajax({
				url: "/dashboard/getSampleResult.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#covid19SampleCollectionDate").val(),
					type: 'covid19'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#covid19SampleResultDetails").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching COVID-19 sample result data:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'hepatitis') {
			xhr = $.ajax({
				url: "/dashboard/getSampleResult.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#hepatitisSampleCollectionDate").val(),
					type: 'hepatitis'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#hepatitisSampleResultDetails").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching hepatitis sample result data:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'tb') {
			xhr = $.ajax({
				url: "/dashboard/getSampleResult.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#tbSampleCollectionDate").val(),
					type: 'tb'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#tbSampleResultDetails").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching TB sample result data:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'cd4') {
			xhr = $.ajax({
				url: "/dashboard/getSampleResult.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#cd4SampleCollectionDate").val(),
					type: 'cd4'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#cd4SampleResultDetails").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching CD4 sample result data:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'generic-tests') {
			xhr = $.ajax({
				url: "/dashboard/getSampleResult.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#genericTestsSampleCollectionDate").val(),
					type: 'generic-tests'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#genericTestsSampleResultDetails").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching generic tests sample result data:', textStatus, errorThrown);
					}
				}
			});
		}

		currentRequests.push(xhr);
		return xhr;
	}

	function getSampleCountsForDashboard(requestType) {
		let xhr;
		if (requestType == 'vl') {
			xhr = $.ajax({
				url: "/dashboard/getSampleCount.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#vlSampleCollectionDate").val(),
					type: 'vl'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#vlNoOfSampleCount").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching VL sample counts:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'recency') {
			xhr = $.ajax({
				url: "/dashboard/getSampleCount.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#recencySampleCollectionDate").val(),
					type: 'recency'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#recencyNoOfSampleCount").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching recency sample counts:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'eid') {
			xhr = $.ajax({
				url: "/dashboard/getSampleCount.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#eidSampleCollectionDate").val(),
					type: 'eid'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#eidNoOfSampleCount").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching EID sample counts:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'cd4') {
			xhr = $.ajax({
				url: "/dashboard/getSampleCount.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#cd4SampleCollectionDate").val(),
					type: 'cd4'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#cd4NoOfSampleCount").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching CD4 sample counts:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'hepatitis') {
			xhr = $.ajax({
				url: "/dashboard/getSampleCount.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#hepatitisSampleCollectionDate").val(),
					type: 'hepatitis'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#hepatitisNoOfSampleCount").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching hepatitis sample counts:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'generic-tests') {
			xhr = $.ajax({
				url: "/dashboard/getSampleCount.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#genericTestsSampleCollectionDate").val(),
					type: 'generic-tests'
				},
				async: true,
				success: function(data) {
					if (data != '') {
						$("#genericTestsNoOfSampleCount").html(data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching generic tests sample counts:', textStatus, errorThrown);
					}
				}
			});
		}

		currentRequests.push(xhr);
		return xhr;
	}

	function getSamplesOverview(requestType) {
		let xhr;
		if (requestType == 'vl') {
			xhr = $.ajax({
				url: "/vl/program-management/getSampleStatus.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#vlSampleCollectionDate").val(),
					batchCode: '',
					facilityName: '',
					sampleType: '',
					type: 'vl'
				},
				async: true,
				success: function(data) {
					if ($.trim(data) != '') {
						$("#vlPieChartDiv").html(data);
						$(".labAverageTatDiv").css("display", "none");
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching VL samples overview:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'recency') {
			xhr = $.ajax({
				url: "/vl/program-management/getSampleStatus.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#recencySampleCollectionDate").val(),
					batchCode: '',
					facilityName: '',
					sampleType: '',
					type: 'recency'
				},
				async: true,
				success: function(data) {
					if ($.trim(data) != '') {
						$("#recencyPieChartDiv").html(data);
						$(".labAverageTatDiv").css("display", "none");
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching recency samples overview:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'eid') {
			xhr = $.ajax({
				url: "/eid/management/getSampleStatus.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#eidSampleCollectionDate").val(),
					batchCode: '',
					facilityName: '',
					sampleType: '',
					type: 'eid'
				},
				async: true,
				success: function(data) {
					if ($.trim(data) != '') {
						$("#eidPieChartDiv").html(data);
						$(".labAverageTatDiv").css("display", "none");
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching EID samples overview:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'covid19') {
			xhr = $.ajax({
				url: "/covid-19/management/getSampleStatus.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#covid19SampleCollectionDate").val(),
					batchCode: '',
					facilityName: '',
					sampleType: '',
					type: 'covid19'
				},
				async: true,
				success: function(data) {
					if ($.trim(data) != '') {
						$("#covid19PieChartDiv").html(data);
						$(".labAverageTatDiv").css("display", "none");
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching COVID-19 samples overview:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'tb') {
			xhr = $.ajax({
				url: "/tb/management/getSampleStatus.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#tbSampleCollectionDate").val(),
					batchCode: '',
					facilityName: '',
					sampleType: '',
					type: 'tb'
				},
				async: true,
				success: function(data) {
					if ($.trim(data) != '') {
						$("#tbPieChartDiv").html(data);
						$(".labAverageTatDiv").css("display", "none");
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching TB samples overview:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'cd4') {
			xhr = $.ajax({
				url: "/cd4/management/get-sample-status.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#cd4SampleCollectionDate").val(),
					batchCode: '',
					facilityName: '',
					sampleType: '',
					type: 'cd4'
				},
				async: true,
				success: function(data) {
					if ($.trim(data) != '') {
						$("#cd4PieChartDiv").html(data);
						$(".labAverageTatDiv").css("display", "none");
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching CD4 samples overview:', textStatus, errorThrown);
					}
				}
			});
		} else if (requestType == 'generic-tests') {
			xhr = $.ajax({
				url: "/generic-tests/program-management/get-sample-status.php",
				type: 'POST',
				data: {
					sampleCollectionDate: $("#genericTestsSampleCollectionDate").val(),
					batchCode: '',
					facilityName: '',
					sampleType: '',
					type: 'generic-tests'
				},
				async: true,
				success: function(data) {
					if ($.trim(data) != '') {
						$("#genericTestsPieChartDiv").html(data);
						$(".labAverageTatDiv").css("display", "none");
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					if (textStatus !== 'abort') {
						console.error('Error fetching generic tests samples overview:', textStatus, errorThrown);
					}
				}
			});
		}

		currentRequests.push(xhr);
		return xhr;
	}

	function resetSampleResultData(requestType) {
		// Abort any in-progress requests
		abortAllRequests();

		// Reset date picker
		$('#vlSampleCollectionDate,#eidSampleCollectionDate,#covid19SampleCollectionDate,#recencySampleCollectionDate,#hepatitisSampleCollectionDate,#tbSampleCollectionDate,#cd4SampleCollectionDate,#genericTestsSampleCollectionDate').daterangepicker({
			locale: {
				cancelLabel: "<?= _translate("Clear", true); ?>",
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
				'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
				'Previous Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
				'Current Year To Date': [moment().startOf('year'), moment()]
			}
		}, function(start, end) {
			startDate = start.format('YYYY-MM-DD');
			endDate = end.format('YYYY-MM-DD');
		});

		generateDashboard(requestType);
	}

	function getEidMonthlyTargetsReport() {
		let xhr = $.ajax({
			url: "/eid/management/getEidMonthlyThresholdReport.php",
			type: 'POST',
			data: {
				targetType: '1',
				sampleTestDate: $("#vlSampleCollectionDate").val(),
			},
			async: true,
			success: function(data) {
				var dataObj = JSON.parse(data);
				if (dataObj['aaData'].length > 0) {
					var div = '<div class="alert alert-danger alert-dismissible" role="alert" style="background-color: #ff909f !important">\
							<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="text-indent: 0px"><span aria-hidden="true" style="font-size: larger;font-weight: bolder;color: #000000;">&times;</span></button>\
							<span>' + dataObj['aaData'].length + ' <?= _translate("EID testing lab(s) did not meet the monthly test target", true); ?>. </span><a href="/eid/management/eidTestingTargetReport.php" target="_blank"> <?= _translate("more"); ?> </a>\
							</div>';
					$("#contEid").html(div);
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				if (textStatus !== 'abort') {
					console.error('Error fetching EID monthly targets:', textStatus, errorThrown);
				}
			}
		});
		currentRequests.push(xhr);
		return xhr;
	}

	function getVlMonthlyTargetsReport() {
		let xhr = $.ajax({
			url: "/vl/program-management/getVlMonthlyThresholdReport.php",
			type: 'POST',
			data: {
				targetType: '1',
				sampleTestDate: $("#vlSampleCollectionDate").val(),
			},
			async: true,
			success: function(data) {
				var dataObj = JSON.parse(data);
				if (dataObj['aaData'].length > 0) {
					var div = '<div class="alert alert-danger alert-dismissible" role="alert" style="background-color: #ff909f !important">\
							<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="text-indent: 0px"><span aria-hidden="true" style="font-size: larger;font-weight: bolder;color: #000000;">&times;</span></button>\
							<span>' + dataObj['aaData'].length + ' <?= _translate("VL testing lab(s) did not meet the monthly test target", true); ?>. </span><a href="/vl/program-management/vlTestingTargetReport.php" target="_blank"> <?= _translate("more"); ?> </a>\
							</div>';
					$("#cont").html(div);
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				if (textStatus !== 'abort') {
					console.error('Error fetching VL monthly targets:', textStatus, errorThrown);
				}
			}
		});
		currentRequests.push(xhr);
		return xhr;
	}

	function getVlSuppressionTargetReport() {
		let xhr = $.ajax({
			url: "/vl/program-management/getSuppressedTargetReport.php",
			type: 'POST',
			data: {
				targetType: '1',
				sampleTestDate: $("#vlSampleCollectionDate").val(),
			},
			async: true,
			success: function(data) {
				if (data == 1) {
					var div = '<div class="alert alert-danger alert-dismissible" role="alert" style="background-color: #ff909f !important">\
							<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="text-indent: 0px"><span aria-hidden="true" style="font-size: larger;font-weight: bolder;color: #000000;">&times;</span></button>\
							<span> <?= _translate("VL testing lab(s) did not meet suppression targets", true); ?> </span><a href="/vl/program-management/vlSuppressedTargetReport.php" target="_blank"> <?= _translate("more"); ?> </a>\
							</div>';
					$("#contVl").html(div);
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				if (textStatus !== 'abort') {
					console.error('Error fetching VL suppression targets:', textStatus, errorThrown);
				}
			}
		});
		currentRequests.push(xhr);
		return xhr;
	}

	function getCovid19MonthlyTargetsReport() {
		let xhr = $.ajax({
			url: "/covid-19/management/getCovid19MonthlyThresholdReport.php",
			type: 'POST',
			data: {
				targetType: '1',
				sampleTestDate: $("#vlSampleCollectionDate").val(),
			},
			async: true,
			success: function(data) {
				var dataObj = JSON.parse(data);
				if (dataObj['aaData'].length > 0) {
					var div = '<div class="alert alert-danger alert-dismissible" role="alert" style="background-color: #ff909f !important">\
							<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="text-indent: 0px"><span aria-hidden="true" style="font-size: larger;font-weight: bolder;color: #000000;">&times;</span></button>\
							<span >' + dataObj['aaData'].length + ' <?= _translate("Covid-19 testing lab(s) did not meet the monthly test target", true); ?>.  </span><a href="/covid-19/management/covid19TestingTargetReport.php" target="_blank"> <?= _translate("more"); ?> </a>\
							</div>';
					$("#contCovid").html(div);
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				if (textStatus !== 'abort') {
					console.error('Error fetching COVID-19 monthly targets:', textStatus, errorThrown);
				}
			}
		});
		currentRequests.push(xhr);
		return xhr;
	}

	function getHepatitisMonthlyTargetsReport() {
		let xhr = $.ajax({
			url: "/hepatitis/management/get-hepatitis-monthly-threshold-report.php",
			type: 'POST',
			data: {
				targetType: '1',
				sampleTestDate: $("#vlSampleCollectionDate").val(),
			},
			async: true,
			success: function(data) {
				var dataObj = JSON.parse(data);
				if (dataObj['aaData'].length > 0) {
					var div = '<div class="alert alert-danger alert-dismissible" role="alert" style="background-color: #ff909f !important">\
				<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="text-indent: 0px"><span aria-hidden="true" style="font-size: larger;font-weight: bolder;color: #000000;">&times;</span></button>\
				<span >' + dataObj['aaData'].length + ' <?= _translate("Hepatitis testing lab(s) did not meet the monthly test target", true); ?>.  </span><a href="/hepatitis/management/hepatitis-testing-target-report.php" target="_blank"> <?= _translate("more"); ?> </a>\
				</div>';
					$("#contCovid").html(div);
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				if (textStatus !== 'abort') {
					console.error('Error fetching Hepatitis monthly targets:', textStatus, errorThrown);
				}
			}
		});
		currentRequests.push(xhr);
		return xhr;
	}

	function getTbMonthlyTargetsReport() {
		let xhr = $.ajax({
			url: "/tb/management/get-tb-monthly-threshold-report.php",
			type: 'POST',
			data: {
				targetType: '1',
				sampleTestDate: $("#tbSampleCollectionDate").val(),
			},
			async: true,
			success: function(data) {
				var dataObj = JSON.parse(data);
				if (dataObj['aaData'].length > 0) {
					var div = '<div class="alert alert-danger alert-dismissible" role="alert" style="background-color: #ff909f !important">\
				<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="text-indent: 0px"><span aria-hidden="true" style="font-size: larger;font-weight: bolder;color: #000000;">&times;</span></button>\
				<span >' + dataObj['aaData'].length + ' <?= _translate("TB testing lab(s) did not meet the monthly test target", true); ?>.  </span><a href="/hepatitis/management/hepatitis-testing-target-report.php" target="_blank"> <?= _translate("more"); ?> </a>\
				</div>';
					$("#contCovid").html(div);
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				if (textStatus !== 'abort') {
					console.error('Error fetching TB monthly targets:', textStatus, errorThrown);
				}
			}
		});
		currentRequests.push(xhr);
		return xhr;
	}

	function getCd4MonthlyTargetsReport() {
		let xhr = $.ajax({
			url: "/cd4/management/get-cd4-monthly-threshold-report.php",
			type: 'POST',
			data: {
				targetType: '1',
				sampleTestDate: $("#vlSampleCollectionDate").val(),
			},
			async: true,
			success: function(data) {
				var dataObj = JSON.parse(data);
				if (dataObj['aaData'].length > 0) {
					var div = '<div class="alert alert-danger alert-dismissible" role="alert" style="background-color: #ff909f !important">\
				<button type="button" class="close" data-dismiss="alert" aria-label="Close" style="text-indent: 0px"><span aria-hidden="true" style="font-size: larger;font-weight: bolder;color: #000000;">&times;</span></button>\
				<span >' + dataObj['aaData'].length + ' <?= _translate("CD4 testing lab(s) did not meet the monthly test target", true); ?>.  </span><a href="/hepatitis/management/hepatitis-testing-target-report.php" target="_blank"> <?= _translate("more"); ?> </a>\
				</div>';
					$("#contCovid").html(div);
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				if (textStatus !== 'abort') {
					console.error('Error fetching CD4 monthly targets:', textStatus, errorThrown);
				}
			}
		});
		currentRequests.push(xhr);
		return xhr;
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
