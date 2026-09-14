<?php

use App\Utilities\SampleStatusUtility;
use App\Services\SampleStatusDetailsService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\VlService;
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

$isRecency = isset($_POST['type']) && trim((string) $_POST['type']) === 'recency';
$testType = $isRecency ? 'recency' : 'vl';
if ($isRecency) {
    $sampleStatusOverviewContainer = "recencySampleStatusOverviewContainer";
    $samplesVlOverview = "recencySmplesVlOverview";
    $samplesResultview = "recencySampleResultView";
    $labAverageTat = "recencyLabAverageTat";
} else {
    $sampleStatusOverviewContainer = "vlSampleStatusOverviewContainer";
    $samplesVlOverview = "vlSmplesVlOverview";
    $samplesResultview = "vlSampleResultView";
    $labAverageTat = "vlLabAverageTat";
}

/*
 * One filter set for the whole page. The status pie, the suppression pie, the
 * turnaround time chart and the drilldown behind each status slice all read it
 * from SampleStatusDetailsService, so a slice and the samples listed under it
 * always describe the same population.
 */
/** @var SampleStatusDetailsService $statusDetails */
$statusDetails = ContainerRegistry::get(SampleStatusDetailsService::class);
[$filters, $params] = $statusDetails->conditions($testType, $_POST);

$whereCondition = implode(" AND ", $filters);

$table = "form_vl";
$highVL = "High Viral Load";
$lowVL = "Low Viral Load";
$suppression = "VL Suppression";

$tsQuery = "SELECT * FROM `r_sample_status` ORDER BY `status_id`";
$tsResult = $db->rawQuery($tsQuery);

$tQuery = "SELECT COUNT(sample.vl_sample_id) as total,
                sample.result_status,
                status.status_id,
                status.status_name
            FROM $table as sample
            LEFT JOIN r_sample_status as status ON status.status_id = sample.result_status
            LEFT JOIN batch_details as batch ON batch.batch_id = sample.sample_batch_id
            WHERE $whereCondition
            GROUP BY sample.result_status
            ORDER BY status.status_id";

$tResult = $db->rawQuery($tQuery, $params);

$vlSuppressionQuery = "SELECT COUNT(sample.vl_sample_id) as total,
        SUM(CASE WHEN IFNULL(sample.vl_result_category, '') like 'not suppressed' THEN 1 ELSE 0 END) AS highVL,
        SUM(CASE WHEN IFNULL(sample.vl_result_category, '') like 'suppressed' THEN 1 ELSE 0 END) AS lowVL
        FROM $table as sample
        LEFT JOIN batch_details as batch ON batch.batch_id = sample.sample_batch_id
        WHERE $whereCondition
            AND IFNULL(sample.result_status, 0) = " . SAMPLE_STATUS\ACCEPTED;

$vlSuppressionResult = $db->rawQueryOne($vlSuppressionQuery, $params);





// Laboratory turnaround time, monthly. Uses the same filters as the charts
// above so the whole page describes one population of samples.
/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);
$tat = $vlService->getTurnaroundTimeSeries(
    conditions: $filters,
    params: $params,
    joins: "LEFT JOIN batch_details AS batch ON batch.batch_id = sample.sample_batch_id"
);

?>
<div class="col-xs-12">
    <div class="box">
        <div class="box-body">
            <div id="<?php echo $sampleStatusOverviewContainer; ?>" style="float:left;width:100%; margin: 0 auto;">
            </div>
        </div>
    </div>
    <div class="box">
        <div class="box-body">
            <div id="<?php echo $samplesVlOverview; ?>" style="float:right;width:100%;margin: 0 auto;"></div>
        </div>
    </div>
    
</div>
</div>
<div class="col-xs-12 labAverageTatDiv">
    <div class="box">
        <div class="box-body">
            <div id="<?php echo $labAverageTat; ?>" style="padding:15px 0px 5px 0px;float:left;width:100%;"></div>
        </div>
    </div>
</div>
<script>
    <?php
    if (!empty($tResult)) {
        $total = 0;
        ?>
        var _value = [
            <?php foreach ($tResult as $tRow) {
                $total += $tRow['total']; ?> {
                    name: <?= json_encode(_translate((string) $tRow['status_name']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                    y: <?= (int) $tRow['total']; ?>,
                    color: '<?= SampleStatusUtility::chartColor((int) $tRow['status_id']); ?>',
                    url: <?= json_encode(SampleStatusDetailsService::pageUrl($testType, (int) $tRow['status_id'], $_POST), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
                },
            <?php } ?>
        ];
        $('#<?php echo $sampleStatusOverviewContainer; ?>').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?php echo _translate("Samples Status Overview (N = " . $total . ")"); ?>"
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
                data: _value
            }]
        });

        <?php

    }

    if (isset($vlSuppressionResult) && (isset($vlSuppressionResult['highVL']) || isset($vlSuppressionResult['lowVL']))) {

        ?>
        Highcharts.setOptions({
            colors: ['#FF0000', '#50B432']
        });
        $('#<?php echo $samplesVlOverview; ?>').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?php echo _translate("VL Suppression (N = " . ($vlSuppressionResult['highVL'] + $vlSuppressionResult['lowVL']) . ")"); ?>"
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
                    name: '<?php echo $highVL; ?>',
                    y: <?php echo (isset($vlSuppressionResult['highVL']) && $vlSuppressionResult['highVL'] > 0) > 0 ? $vlSuppressionResult['highVL'] : 0; ?>
                },
                {
                    name: '<?php echo $lowVL; ?>',
                    y: <?php echo (isset($vlSuppressionResult['lowVL']) && $vlSuppressionResult['lowVL'] > 0) > 0 ? $vlSuppressionResult['lowVL'] : 0; ?>
                },
                ]
            }]
        });
        <?php
    }
    ?>
    $('#<?php echo $samplesResultview; ?>').hide();
    <?php
    if (!empty($tat['months'])) {
        ?>
        $('#<?php echo $labAverageTat; ?>').highcharts({
            chart: {
                type: 'line'
            },
            title: {
                text: "<?php echo _translate("Laboratory Turnaround Time", escapeTextOrContext: true); ?>"
            },
            exporting: {
                chartOptions: {
                    subtitle: {
                        text: "<?php echo _translate("Laboratory Turnaround Time", escapeTextOrContext: true); ?>",
                    }
                },
                sourceWidth: 1200,
                sourceHeight: 600
            },
            credits: {
                enabled: false
            },
            xAxis: {
                categories: <?php echo json_encode($tat['months']); ?>
            },
            yAxis: [{
                title: {
                    text: "<?php echo _translate("Average TAT in Days", escapeTextOrContext: true); ?>"
                },
                labels: {
                    formatter: function () {
                        return this.value;
                    }
                }
            }, { // Secondary yAxis
                gridLineWidth: 0,
                title: {
                    text: "<?php echo _translate("No. of Tests", escapeTextOrContext: true); ?>"
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
                name: "<?php echo _translate("No. of Samples Tested", escapeTextOrContext: true); ?>",
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
            exporting: {
                sourceWidth: 1200,
                sourceHeight: 600,
                scale: 10
            }
        });
    <?php } ?>
</script>
