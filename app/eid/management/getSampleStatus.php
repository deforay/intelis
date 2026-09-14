<?php

use App\Services\SampleStatusDetailsService;
use App\Utilities\SampleStatusUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\EidService;
use App\Registries\ContainerRegistry;
use App\Utilities\TurnaroundTimeUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/*
 * One filter set for the whole page. The status pie, the positivity pie and
 * the turnaround time chart used to build their own subsets of these
 * conditions, so changing a filter moved some charts and left others
 * describing a different population.
 */
/*
 * The drilldown behind each status slice reads the same conditions, so the
 * samples it lists are the ones the slice counted.
 */
/** @var SampleStatusDetailsService $statusDetails */
$statusDetails = ContainerRegistry::get(SampleStatusDetailsService::class);
[$filters, $params] = $statusDetails->conditions('eid', $_POST);

$whereCondition = implode(" AND ", $filters);
$joins = "JOIN r_sample_status AS status ON status.status_id = sample.result_status
		LEFT JOIN batch_details AS batch ON batch.batch_id = sample.sample_batch_id";

$tsQuery = "SELECT * FROM `r_sample_status` ORDER BY `status_id`";
$tsResult = $db->rawQuery($tsQuery);

$tQuery = "SELECT COUNT(sample.eid_id) as total, status.status_id, status.status_name
			FROM form_eid AS sample
			$joins
			WHERE $whereCondition
			GROUP BY sample.result_status
			ORDER BY status.status_id";

$tResult = $db->rawQuery($tQuery, $params);

// Positive / negative split
$vlSuppressionQuery = "SELECT COUNT(sample.eid_id) as total,
		SUM(CASE WHEN sample.result = 'positive' THEN 1 ELSE 0 END) AS positiveResult,
		SUM(CASE WHEN sample.result = 'negative' THEN 1 ELSE 0 END) AS negativeResult
		FROM form_eid AS sample
		$joins
		WHERE $whereCondition
			AND IFNULL(sample.result, '') != ''";

$vlSuppressionResult = $db->rawQueryOne($vlSuppressionQuery, $params);

// Laboratory turnaround time, monthly, on the same filters as the charts above.
/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$tat = $eidService->getTurnaroundTimeSeries(
	conditions: $filters,
	params: $params,
	joins: $joins
);

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
				text: "<?= _jsTranslate("EID Samples Status Overview"); ?>"
			},
			credits: {
				enabled: false
			},
			tooltip: {
				pointFormat: "<?= _jsTranslate("EID Samples"); ?>: <strong>{point.y}</strong>"
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
							name: <?= json_encode(_translate((string) $tRow['status_name']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
							y: <?= (int) $tRow['total']; ?>,
							color: '<?= SampleStatusUtility::chartColor((int) $tRow['status_id']); ?>',
							url: <?= json_encode(SampleStatusDetailsService::pageUrl('eid', (int) $tRow['status_id'], $_POST), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
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
				text: "<?= _jsTranslate("EID Results"); ?>"
			},
			credits: {
				enabled: false
			},
			tooltip: {
				pointFormat: "<?= _jsTranslate("Samples"); ?>: <strong>{point.y}</strong>"
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
					name: "<?= _jsTranslate("Positive"); ?>",
					y: <?php echo (isset($vlSuppressionResult['positiveResult']) && $vlSuppressionResult['positiveResult'] > 0) > 0 ? $vlSuppressionResult['positiveResult'] : 0; ?>
				},
				{
					name: "<?= _jsTranslate("Negative"); ?>",
					y: <?php echo (isset($vlSuppressionResult['negativeResult']) && $vlSuppressionResult['negativeResult'] > 0) > 0 ? $vlSuppressionResult['negativeResult'] : 0; ?>
				},
				]
			}]
		});
		<?php
	}
	if (!empty($tat['months'])) {
		?>
		$('#eidLabAverageTat').highcharts({
			chart: {
				type: 'line'
			},
			title: {
				text: "<?= _jsTranslate("EID Laboratory Turnaround Time"); ?>"
			},
			exporting: {
				chartOptions: {
					subtitle: {
						text: "<?= _jsTranslate("EID Laboratory Turnaround Time"); ?>",
					}
				}
			},
			credits: {
				enabled: false
			},
			xAxis: {
				categories: <?php echo json_encode($tat['months']); ?>
			},
			yAxis: [{
				title: {
					text: "<?= _jsTranslate("Average TAT in Days"); ?>"
				},
				labels: {
					formatter: function () {
						return this.value;
					}
				}
			}, { // Secondary yAxis
				gridLineWidth: 0,
				title: {
					text: "<?= _jsTranslate("No. of Tests"); ?>"
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
				name: "<?= _jsTranslate("No. of Samples Tested"); ?>",
				data: [<?php echo implode(",", $tat['samplesTested']); ?>],
				color: '#7CB5ED',
				yAxis: 1
			},
				<?php foreach (TurnaroundTimeUtility::chartSeries($tat) as $tatSeries) { ?> {
				connectNulls: false,
				showInLegend: true,
				name: "<?php echo $tatSeries['name']; ?>",
				data: [<?php echo $tatSeries['data']; ?>],
				color: '<?php echo $tatSeries['color']; ?>',
			},
				<?php } ?>
			],
		});
	<?php } ?>
</script>