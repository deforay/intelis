<?php

use App\Services\SampleStatusDetailsService;
use App\Utilities\SampleStatusUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\TbService;
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
 * One filter set for the whole page. Each chart used to build its own subset
 * of these conditions, so changing a filter moved some charts and left others
 * describing a different population of samples.
 */
/*
 * The drilldown behind each status slice reads the same conditions, so the
 * samples it lists are the ones the slice counted.
 */
/** @var SampleStatusDetailsService $statusDetails */
$statusDetails = ContainerRegistry::get(SampleStatusDetailsService::class);
[$filters, $params] = $statusDetails->conditions('tb', $_POST);

$whereCondition = implode(" AND ", $filters);
$joins = "JOIN r_sample_status AS status ON status.status_id = sample.result_status
        LEFT JOIN batch_details AS batch ON batch.batch_id = sample.sample_batch_id";

$tsQuery = "SELECT * FROM `r_sample_status` ORDER BY `status_id`";
$tsResult = $db->rawQuery($tsQuery);

$tQuery = "SELECT COUNT(sample.tb_id) as total, status.status_id, status.status_name
        FROM form_tb AS sample
        $joins
        WHERE $whereCondition
        GROUP BY sample.result_status
        ORDER BY status.status_id";

$tResult = $db->rawQuery($tQuery, $params);

// TB final results are free-text values, so aggregate them by label instead of
// forcing them into a positive/negative split like EID.
$tbResultsDistributionQuery = "SELECT
        TRIM(sample.result) AS resultName,
        COUNT(sample.tb_id) AS total
        FROM form_tb AS sample
        $joins
        WHERE $whereCondition
            AND NULLIF(TRIM(sample.result), '') IS NOT NULL
        GROUP BY TRIM(sample.result)
        ORDER BY total DESC";

$tbResultsDistribution = $db->rawQuery($tbResultsDistributionQuery, $params);

// Laboratory turnaround time, monthly, on the same filters as the charts above.
/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);
$tat = $tbService->getTurnaroundTimeSeries(
    conditions: $filters,
    params: $params,
    joins: $joins
);

?>
<div class="col-xs-12">
	<div class="box">
		<div class="box-body">
			<div id="tbSampleStatusOverviewContainer" style="float:left;width:100%; margin: 0 auto;"></div>
		</div>
	</div>
	<div class="box">
		<div class="box-body">
			<div id="tbSamplesOverview" style="float:right;width:100%;margin: 0 auto;"></div>
		</div>
	</div>
</div>
</div>
<div class="col-xs-12 labAverageTatDiv">
	<div class="box">
		<div class="box-body">
			<div id="tbLabAverageTat" style="padding:15px 0px 5px 0px;float:left;width:100%;"></div>
		</div>
	</div>
</div>
<script>
	<?php
	if (!empty($tResult)) {
		?>
		$('#tbSampleStatusOverviewContainer').highcharts({
			chart: {
				plotBackgroundColor: null,
				plotBorderWidth: null,
				plotShadow: false,
				type: 'pie'
			},
			title: {
				text: "<?= _jsTranslate("TB Samples Status Overview"); ?>"
			},
			credits: {
				enabled: false
			},
			tooltip: {
				pointFormat: "<?= _jsTranslate("TB Samples"); ?>: <strong>{point.y}</strong>"
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
							url: <?= json_encode(SampleStatusDetailsService::pageUrl('tb', (int) $tRow['status_id'], $_POST), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
						},
						<?php
					}
					?>
				]
			}]
		});

		<?php

	}

	if (!empty($tbResultsDistribution)) {

		?>
		$('#tbSamplesOverview').highcharts({
			chart: {
				type: 'bar'
			},
			title: {
				text: "<?= _jsTranslate("TB Final Result Counts"); ?>"
			},
			credits: {
				enabled: false
			},
			xAxis: {
				categories: [
					<?php
					foreach ($tbResultsDistribution as $resultRow) {
						echo "'" . htmlspecialchars((string) $resultRow['resultName'], ENT_QUOTES) . "',";
					}
					?>
				],
				title: {
					text: null
				}
			},
			yAxis: {
				min: 0,
				title: {
					text: "<?= _jsTranslate("Number of Samples"); ?>",
					align: 'high'
				}
			},
			tooltip: {
				pointFormat: "<?= _jsTranslate("Samples"); ?>: <strong>{point.y}</strong>"
			},
			plotOptions: {
				series: {
					dataLabels: {
						enabled: true,
						format: '{point.y}'
					}
				}
			},
			series: [{
				showInLegend: false,
				name: "<?= _jsTranslate("Samples"); ?>",
				color: '#7CB5ED',
				data: [
				<?php
				foreach ($tbResultsDistribution as $resultRow) {
					echo (int) $resultRow['total'] . ',';
				}
				?>
				]
			}]
		});
		<?php
	}
	if (!empty($tat['months'])) {
		?>
		$('#tbLabAverageTat').highcharts({
			chart: {
				type: 'line'
			},
			title: {
				text: "<?= _jsTranslate("TB Laboratory Turnaround Time"); ?>"
			},
			exporting: {
				chartOptions: {
					subtitle: {
						text: "<?= _jsTranslate("TB Laboratory Turnaround Time"); ?>",
					}
				}
			},
			credits: {
				enabled: false
			},
			xAxis: {
				//categories: ["21 Mar", "22 Mar", "23 Mar", "24 Mar", "25 Mar", "26 Mar", "27 Mar"]
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
