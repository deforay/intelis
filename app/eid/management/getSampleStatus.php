<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$whereConditionArray = [];
$whereConditionArray[] = " vl.result_status != " . SAMPLE_STATUS\CANCELLED;
if (!empty($_SESSION['facilityMap'])) {
	$whereConditionArray[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")";
}

$whereCondition = implode(" AND ", $whereConditionArray);

$tsQuery = "SELECT * FROM `r_sample_status` ORDER BY `status_id`";
$tsResult = $db->rawQuery($tsQuery);


$sampleStatusColors = [];

$sampleStatusColors[1] = "#dda41b"; // HOLD
$sampleStatusColors[2] = "#9a1c64"; // LOST
$sampleStatusColors[3] = "grey"; // Sample Reordered
$sampleStatusColors[4] = "#d8424d"; // Rejected
$sampleStatusColors[5] = "black"; // Invalid
$sampleStatusColors[6] = "#e2d44b"; // Sample Received at lab
$sampleStatusColors[7] = "#639e11"; // Accepted
$sampleStatusColors[8] = "#7f22e8"; // Sent to Lab
$sampleStatusColors[9] = "#4BC0D9"; // Sample Registered at Health Center

$sWhere = [];

if ($whereCondition !== '' && $whereCondition !== '0') {
	$sWhere[] = $whereCondition;
}

$tQuery = "SELECT COUNT(eid_id) as total,status_id,status_name
                FROM form_eid as vl
                JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                JOIN facility_details as f ON vl.lab_id=f.facility_id
                LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id ";

//filter

if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
	$sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (!empty($_POST['sampleCollectionDate'])) {
	[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
	$sWhere[] = " DATE(vl.sample_collection_date) BETWEEN '$start_date' AND '$end_date'";
}
if (isset($_POST['sampleReceivedDateAtLab']) && trim((string) $_POST['sampleReceivedDateAtLab']) !== '') {
	[$labStartDate, $labEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedDateAtLab'] ?? '');
	$sWhere[] = " DATE(vl.sample_received_at_lab_datetime) BETWEEN '$labStartDate' AND '$labEndDate'";
}
if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) !== '') {
	[$testedStartDate, $testedEndDate] = DateUtility::convertDateRange($_POST['sampleTestedDate'] ?? '');
	$sWhere[] = " DATE(vl.sample_tested_datetime) BETWEEN '$testedStartDate' AND '$testedEndDate'";
}
if (!empty($_POST['labName'])) {
	$sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}
if ($sWhere !== []) {
	$tQuery .= " where " . implode(" AND ", $sWhere);
}
$tQuery .= " GROUP BY vl.result_status ORDER BY status_id";

// echo $tQuery;die;
$tResult = $db->rawQuery($tQuery);


//HVL and LVL Samples
$sWhere = [];
if ($whereCondition !== '' && $whereCondition !== '0') {
	$sWhere[] = $whereCondition;
}
$vlSuppressionQuery = "SELECT   COUNT(eid_id) as total,
		SUM(CASE
				WHEN (vl.result = 'positive') THEN 1
					ELSE 0
				END) AS positiveResult,
		(SUM(CASE
				WHEN (vl.result = 'negative') THEN 1
					ELSE 0
				END)) AS negativeResult,
		status_id,
		status_name

		FROM form_eid as vl INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status JOIN facility_details as f ON vl.lab_id=f.facility_id LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

$sWhere[] = " (vl.result!='' and vl.result is not null) ";

if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
	$sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (!empty($_POST['sampleCollectionDate'])) {
	[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
	$sWhere[] = " DATE(vl.sample_received_at_lab_datetime) BETWEEN '$start_date' AND '$end_date'";
}
if (isset($_POST['sampleReceivedDateAtLab']) && trim((string) $_POST['sampleReceivedDateAtLab']) !== '') {
	[$labStartDate, $labEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedDateAtLab'] ?? '');
	$sWhere[] = " DATE(vl.sample_received_at_lab_datetime) BETWEEN '$labStartDate' AND '$labEndDate'";
}
if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) !== '') {
	[$testedStartDate, $testedEndDate] = DateUtility::convertDateRange($_POST['sampleTestedDate'] ?? '');
	$sWhere[] = " DATE(vl.sample_tested_datetime) BETWEEN '$testedStartDate' AND '$testedEndDate'";
}
if (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) !== '') {
	$sWhere[] = ' s.sample_id = "' . $_POST['sampleType'] . '"';
}
if (!empty($_POST['labName'])) {
	$sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}
if ($sWhere !== []) {
	$vlSuppressionQuery .= " where " . implode(" AND ", $sWhere);
}
$vlSuppressionResult = $db->rawQueryOne($vlSuppressionQuery);

//get LAB TAT
$sWhere = [];
if ($whereCondition !== '' && $whereCondition !== '0') {
	$sWhere[] = $whereCondition;
}
if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) !== '') {
	[$tatStartDate, $tatEndDate] = DateUtility::convertDateRange($_POST['sampleTestedDate'] ?? '');
} else {
	$date = new DateTime();
	$tatEndDate = $date->format('Y-m-d');
	$date->modify('-1 year');
	$tatStartDate = $date->format('Y-m-d');
}

$tatSampleQuery = "SELECT
					COUNT(DISTINCT vl.unique_id) AS totalSamples,
					COUNT(DISTINCT CASE WHEN vl.sample_collection_date BETWEEN '$tatStartDate' AND '$tatEndDate' THEN vl.unique_id END) AS numberCollected,
					COUNT(DISTINCT CASE WHEN vl.sample_tested_datetime BETWEEN '$tatStartDate' AND '$tatEndDate' THEN vl.unique_id END) AS numberTested,
					COUNT(DISTINCT CASE WHEN vl.sample_received_at_lab_datetime BETWEEN '$tatStartDate' AND '$tatEndDate' THEN vl.unique_id END) AS numberReceived,
					DATE_FORMAT(DATE(vl.sample_tested_datetime), '%b-%Y') AS monthDate,

					ROUND(AVG(GREATEST(TIMESTAMPDIFF(DAY, vl.sample_collection_date, vl.sample_tested_datetime), 0)), 2) AS AvgCollectedTested,
					ROUND(AVG(GREATEST(TIMESTAMPDIFF(DAY, vl.sample_collection_date, vl.sample_received_at_lab_datetime), 0)), 2) AS AvgCollectedReceived,
					ROUND(AVG(GREATEST(TIMESTAMPDIFF(DAY, vl.sample_received_at_lab_datetime, vl.sample_tested_datetime), 0)), 2) AS AvgReceivedTested,
					ROUND(AVG(GREATEST(TIMESTAMPDIFF(DAY, vl.sample_collection_date, vl.result_printed_datetime), 0)), 2) AS AvgCollectedPrinted,
					ROUND(AVG(GREATEST(TIMESTAMPDIFF(DAY, vl.sample_tested_datetime, vl.result_printed_datetime), 0)), 2) AS AvgTestedPrinted,
					ROUND(AVG(GREATEST(TIMESTAMPDIFF(DAY, vl.result_printed_on_lis_datetime, vl.result_printed_on_sts_datetime), 0)), 2) AS AvgTestedPrintedFirstTime

				FROM `form_eid` AS vl
				INNER JOIN facility_details AS f ON vl.lab_id = f.facility_id
				LEFT JOIN r_vl_sample_type AS s ON s.sample_id = vl.specimen_type
				WHERE
					vl.result IS NOT NULL AND vl.result != '' AND
					DATE(vl.sample_tested_datetime) BETWEEN '$tatStartDate' AND '$tatEndDate'
";


if (!empty($_POST['labName'])) {
	$sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}

if ($sWhere !== []) {
	$tatSampleQuery .= " AND " . implode(" AND ", $sWhere);
}
$tatSampleQuery .= " GROUP BY monthDate ORDER BY sample_tested_datetime ";
//echo $tatSampleQuery;die;

$tatResult = $db->rawQuery($tatSampleQuery);
$j = 0;
foreach ($tatResult as $sRow) {
	if ($sRow["monthDate"] == null) {
		continue;
	}

	$result['totalSamples'][$j] = (isset($sRow["totalSamples"]) && $sRow["totalSamples"] > 0 && $sRow["totalSamples"] != null) ? $sRow["totalSamples"] : 'null';
	$result['numberCollected'][$j] = (isset($sRow["numberCollected"]) && $sRow["numberCollected"] > 0 && $sRow["numberCollected"] != null) ? $sRow["numberCollected"] : 'null';
	$result['numberTested'][$j] = (isset($sRow["numberTested"]) && $sRow["numberTested"] > 0 && $sRow["numberTested"] != null) ? $sRow["numberTested"] : 'null';
	$result['numberReceived'][$j] = (isset($sRow["numberReceived"]) && $sRow["numberReceived"] > 0 && $sRow["numberReceived"] != null) ? $sRow["numberReceived"] : 'null';
	$result['AvgTestedPrinted'][$j] = (isset($sRow["AvgTestedPrinted"]) && $sRow["AvgTestedPrinted"] > 0 && $sRow["AvgTestedPrinted"] != null) ? $sRow["AvgTestedPrinted"] : 'null';
	$result['sampleTestedDiff'][$j] = (isset($sRow["AvgCollectedTested"]) && $sRow["AvgCollectedTested"] > 0 && $sRow["AvgCollectedTested"] != null) ? round($sRow["AvgCollectedTested"], 2) : 'null';
	$result['sampleReceivedDiff'][$j] = (isset($sRow["AvgCollectedReceived"]) && $sRow["AvgCollectedReceived"] > 0 && $sRow["AvgCollectedReceived"] != null) ? round($sRow["AvgCollectedReceived"], 2) : 'null';
	$result['sampleReceivedTested'][$j] = (isset($sRow["AvgReceivedTested"]) && $sRow["AvgReceivedTested"] > 0 && $sRow["AvgReceivedTested"] != null) ? round($sRow["AvgReceivedTested"], 2) : 'null';
	$result['sampleReceivedPrinted'][$j] = (isset($sRow["AvgCollectedPrinted"]) && $sRow["AvgCollectedPrinted"] > 0 && $sRow["AvgCollectedPrinted"] != null) ? round($sRow["AvgCollectedPrinted"], 2) : 'null';
	$result['date'][$j] = $sRow["monthDate"];
	$j++;
}

?>
<div class="col-xs-12">
	<div class="box">
		<div class="box-body">
			<div id="eidSampleStatusOverviewContainer" style="float:left;width:100%; margin: 0 auto;"></div>
		</div>
	</div>
	<div class="box">
		<div class="box-body">
			<div id="eidSamplesOverview" style="float:right;width:100%;margin: 0 auto;"></div>
		</div>
	</div>
</div>
</div>
<div class="col-xs-12 labAverageTatDiv">
	<div class="box">
		<div class="box-body">
			<div id="eidLabAverageTat" style="padding:15px 0px 5px 0px;float:left;width:100%;"></div>
		</div>
	</div>
</div>
<script>
	<?php
	if (!empty($tResult)) {
		?>
		$('#eidSampleStatusOverviewContainer').highcharts({
			chart: {
				plotBackgroundColor: null,
				plotBorderWidth: null,
				plotShadow: false,
				type: 'pie'
			},
			title: {
				text: "<?php echo _translate("EID Samples Status Overview"); ?>"
			},
			credits: {
				enabled: false
			},
			tooltip: {
				pointFormat: "<?php echo _translate("EID Samples"); ?> :<strong>{point.y}</strong>"
			},
			plotOptions: {
				pie: {
					size: '100%',
					allowPointSelect: true,
					cursor: 'pointer',
					dataLabels: {
						enabled: true,
						useHTML: true,
						format: '<div style="padding-bottom:10px;"><strong>{point.name}</strong>: {point.y}</div>',
						style: {

							//crop:false,
							//overflow:'none',
							color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
						},
						distance: 10
					},
					showInLegend: true
				}
			},
			series: [{
				colorByPoint: false,
				point: {
					events: {
						click: function (e) {
							window.open(e.point.url, '_blank');
							e.preventDefault();
						}
					}
				},
				data: [
					<?php
					foreach ($tResult as $tRow) {
						?> {
							name: '<?php echo ($tRow['status_name']); ?>',
							y: <?php echo ($tRow['total']); ?>,
							color: '<?php echo $sampleStatusColors[$tRow['status_id']]; ?>',
							url: '../dashboard/vlTestResultStatus.php?id=<?php echo base64_encode((string) $tRow['status_id']); ?>&d=<?php echo base64_encode((string) $_POST['sampleCollectionDate']); ?>'
						},
						<?php
					}
					?>
				]
			}]
		});

		<?php

	}

	if (isset($vlSuppressionResult) && (isset($vlSuppressionResult['positiveResult']) || isset($vlSuppressionResult['negativeResult']))) {

		?>
		Highcharts.setOptions({
			colors: ['#FF0000', '#50B432']
		});
		$('#eidSamplesOverview').highcharts({
			chart: {
				plotBackgroundColor: null,
				plotBorderWidth: null,
				plotShadow: false,
				type: 'pie'
			},
			title: {
				text: "<?php echo _translate("EID Results"); ?>"
			},
			credits: {
				enabled: false
			},
			tooltip: {
				pointFormat: "<?php echo _translate("Samples"); ?> :<strong>{point.y}</strong>"
			},
			plotOptions: {
				pie: {
					size: '100%',
					allowPointSelect: true,
					cursor: 'pointer',
					dataLabels: {
						enabled: true,
						useHTML: true,
						format: '<div style="padding-bottom:10px;"><strong>{point.name}</strong>: {point.y}</div>',
						style: {
							color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
						},
						distance: 10
					},
					showInLegend: true
				}
			},
			series: [{
				colorByPoint: true,
				data: [{
					name: "<?php echo _translate("Positive"); ?>",
					y: <?php echo (isset($vlSuppressionResult['positiveResult']) && $vlSuppressionResult['positiveResult'] > 0) > 0 ? $vlSuppressionResult['positiveResult'] : 0; ?>
				},
				{
					name: "<?php echo _translate("Negative"); ?>",
					y: <?php echo (isset($vlSuppressionResult['negativeResult']) && $vlSuppressionResult['negativeResult'] > 0) > 0 ? $vlSuppressionResult['negativeResult'] : 0; ?>
				},
				]
			}]
		});
		<?php
	}
	if (!empty($result)) {
		?>
		$('#eidLabAverageTat').highcharts({
			chart: {
				type: 'line'
			},
			title: {
				text: "<?php echo _translate("EID Laboratory Turnaround Time"); ?>"
			},
			exporting: {
				chartOptions: {
					subtitle: {
						text: "<?php echo _translate("EID Laboratory Turnaround Time"); ?>",
					}
				}
			},
			credits: {
				enabled: false
			},
			xAxis: {
				//categories: ["21 Mar", "22 Mar", "23 Mar", "24 Mar", "25 Mar", "26 Mar", "27 Mar"]
				categories: [<?php
				if (!empty($result['date'])) {
					foreach ($result['date'] as $date) {
						echo "'" . $date . "',";
					}
				}
				?>]
			},
			yAxis: [{
				title: {
					text: "<?php echo _translate("Average TAT in Days"); ?>"
				},
				labels: {
					formatter: function () {
						return this.value;
					}
				}
			}, { // Secondary yAxis
				gridLineWidth: 0,
				title: {
					text: "<?php echo _translate("No. of Tests"); ?>"
				},
				labels: {
					format: '{value}'
				},
				opposite: true
			}],
			plotOptions: {
				line: {
					dataLabels: {
						enabled: true
					},
					cursor: 'pointer',
					point: {
						events: {
							click: function (e) {
								//doLabTATRedirect(e.point.category);
							}
						}
					}
				},
				series: {
					dataLabels: {
						enabled: true
					}
				}
			},

			series: [{
				type: 'column',
				name: "<?php echo _translate("No. of Samples Tested"); ?>",
				data: [<?php echo implode(",", $result['totalSamples']); ?>],
				color: '#7CB5ED',
				yAxis: 1
			},
				<?php
				if (isset($result['AvgTestedPrinted'])) {
					?> {
					connectNulls: false,
					showInLegend: true,
					name: "<?php echo _translate("Tested - Printed"); ?>",
					data: [<?php echo implode(",", $result['AvgTestedPrinted']); ?>],
					color: '#0f3f6e',
				},
					<?php
				}
				if (isset($result['sampleReceivedDiff'])) {
					?> {
					connectNulls: false,
					showInLegend: true,
					name: "<?php echo _translate("Collected - Received at Lab"); ?>",
					data: [<?php echo implode(",", $result['sampleReceivedDiff']); ?>],
					color: '#edb47c',
				},
					<?php
				}
				if (isset($result['sampleReceivedTested'])) {
					?> {
					connectNulls: false,
					showInLegend: true,
					name: "<?php echo _translate("Received - Tested"); ?>",
					data: [<?php echo implode(",", $result['sampleReceivedTested']); ?>],
					color: '#0f3f6e',
				},
					<?php
				}
				if (isset($result['sampleTestedDiff'])) {
					?> {
					connectNulls: false,
					showInLegend: true,
					name: "<?php echo _translate("Collected - Tested"); ?>",
					data: [<?php echo implode(",", $result['sampleTestedDiff']); ?>],
					color: '#ed7c7d',
				},
					<?php
				}
				if (isset($result['sampleReceivedPrinted'])) {
					?> {
					connectNulls: false,
					showInLegend: true,
					name: "<?php echo _translate("Collected - Printed"); ?>",
					data: [<?php echo implode(",", $result['sampleReceivedPrinted']); ?>],
					color: '#000',
				},
					<?php
				}
				?>
			],
		});
	<?php } ?>
</script>