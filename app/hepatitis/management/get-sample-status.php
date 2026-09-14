<?php

use App\Services\SampleStatusDetailsService;
use App\Utilities\MiscUtility;
use App\Utilities\SampleStatusUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\HepatitisService;
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
[$filters, $params] = $statusDetails->conditions('hepatitis', $_POST);

$whereCondition = implode(" AND ", $filters);
$joins = "JOIN r_sample_status AS status ON status.status_id = sample.result_status
        LEFT JOIN batch_details AS batch ON batch.batch_id = sample.sample_batch_id";

$tsQuery = "SELECT * FROM `r_sample_status` ORDER BY `status_id`";
$tsResult = $db->rawQuery($tsQuery);

$tQuery = "SELECT COUNT(sample.hepatitis_id) as total, status.status_id, status.status_name
        FROM form_hepatitis AS sample
        $joins
        WHERE $whereCondition
        GROUP BY sample.result_status
        ORDER BY status.status_id";

$tResult = $db->rawQuery($tQuery, $params);

// Hepatitis reports HCV and HBV positivity separately.
$vlSuppressionQuery = "SELECT COUNT(sample.hepatitis_id) as total,
        SUM(CASE WHEN sample.hcv_vl_count = 'positive' THEN 1 ELSE 0 END) AS positiveResult,
        SUM(CASE WHEN sample.hcv_vl_count = 'negative' THEN 1 ELSE 0 END) AS negativeResult,
        SUM(CASE WHEN sample.hbv_vl_count = 'positive' THEN 1 ELSE 0 END) AS hbvpositiveResult,
        SUM(CASE WHEN sample.hbv_vl_count = 'negative' THEN 1 ELSE 0 END) AS hbvnegativeResult
        FROM form_hepatitis AS sample
        $joins
        WHERE $whereCondition
            AND IFNULL(sample.hcv_vl_count, '') != ''";

$vlSuppressionResult = $db->rawQueryOne($vlSuppressionQuery, $params);

// Laboratory turnaround time, monthly, on the same filters as the charts above.
/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);
$tat = $hepatitisService->getTurnaroundTimeSeries(
    conditions: $filters,
    params: $params,
    joins: $joins
);

// Reason for testing distribution
$testReasonQuery = "SELECT COUNT(sample.sample_code) AS total, reason.test_reason_name
        FROM form_hepatitis AS sample
        INNER JOIN r_hepatitis_test_reasons AS reason ON sample.reason_for_hepatitis_test = reason.test_reason_id
        LEFT JOIN batch_details AS batch ON batch.batch_id = sample.sample_batch_id
        WHERE $whereCondition
            AND sample.reason_for_hepatitis_test IS NOT NULL
        GROUP BY reason.test_reason_name";

$testReasonResult = $db->rawQuery($testReasonQuery, $params);

?>
<div class="col-xs-12">
    <div class="box">
        <div class="box-body">
            <div id="hepatitisSampleStatusOverviewContainer" style="float:left;width:100%; margin: 0 auto;"></div>
        </div>
    </div>
    <div class="box">
        <div class="box-body">
            <div id="hepatitisTestReasonContainer" style="float:left;width:100%; margin: 0 auto;"></div>
        </div>
    </div>
    <div class="box">
        <div class="box-body">
            <div id="hepatitisSamplesOverview" style="float:right;width:100%;margin: 0 auto;"></div>
        </div>
    </div>
    <div class="box">
        <div class="box-body">
            <div id="hepatitisHbvSamplesOverview" style="float:right;width:100%;margin: 0 auto;"></div>
        </div>
    </div>
</div>
</div>
<div class="col-xs-12 labAverageTatDiv">
    <div class="box">
        <div class="box-body">
            <div id="hepatitisLabAverageTat" style="padding:15px 0px 5px 0px;float:left;width:100%;"></div>
        </div>
    </div>
</div>
<script>
    <?php
    if (!empty($tResult)) {
        ?>
        $('#hepatitisSampleStatusOverviewContainer').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?= _jsTranslate("Hepatitis Samples Status Overview"); ?>"
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: "<?= _jsTranslate("Hepatitis Samples"); ?>: <strong>{point.y}</strong>"
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
                            //console.log(e.point.url);
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
                            url: <?= json_encode(SampleStatusDetailsService::pageUrl('hepatitis', (int) $tRow['status_id'], $_POST), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
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
        $('#hepatitisSamplesOverview').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?= _jsTranslate("Hepatitis HCV VL Results"); ?>"
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

    if (isset($vlSuppressionResult) && (isset($vlSuppressionResult['hbvpositiveResult']) || isset($vlSuppressionResult['hbvnegativeResult']))) {

        ?>
        Highcharts.setOptions({
            colors: ['#FF0000', '#50B432']
        });
        $('#hepatitisHbvSamplesOverview').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?= _jsTranslate("Hepatitis HBV VL Results"); ?>"
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
                    y: <?php echo (isset($vlSuppressionResult['hbvpositiveResult']) && $vlSuppressionResult['hbvpositiveResult'] > 0) > 0 ? $vlSuppressionResult['hbvpositiveResult'] : 0; ?>
                },
                {
                    name: "<?= _jsTranslate("Negative"); ?>",
                    y: <?php echo (isset($vlSuppressionResult['hbvnegativeResult']) && $vlSuppressionResult['hbvnegativeResult'] > 0) > 0 ? $vlSuppressionResult['hbvnegativeResult'] : 0; ?>
                },
                ]
            }]
        });
        <?php
    }
    if (!empty($tat['months'])) {
        ?>
        $('#hepatitisLabAverageTat').highcharts({
            chart: {
                type: 'line'
            },
            title: {
                text: "<?= _jsTranslate("Hepatitis Laboratory Turnaround Time"); ?>"
            },
            exporting: {
                chartOptions: {
                    subtitle: {
                        text: "<?= _jsTranslate("Hepatitis Laboratory Turnaround Time"); ?>",
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
    <?php }
    if (!empty($testReasonResult)) { ?>
        $('#hepatitisTestReasonContainer').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?= _jsTranslate("Hepatitis Test Reasons"); ?>"
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: "<?= _jsTranslate("Test Reasons"); ?>: <strong>{point.y}</strong>"
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
                data: [
                    <?php
                    foreach ($testReasonResult as $tRow) {
                        ?> {
                            name: <?= json_encode((string) $tRow['test_reason_name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                            y: <?= ($tRow['total']); ?>,
                            color: '#<?php echo MiscUtility::randomHexColor() ?>',
                        },
                        <?php
                    }
                    ?>
                ]
            }]
        });
    <?php } ?>
</script>