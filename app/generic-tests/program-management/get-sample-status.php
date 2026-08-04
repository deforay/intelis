<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\GenericTestsService;
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
$filters = [];
$params = [];

if (!empty($_SESSION['facilityMap'])) {
    $filters[] = " sample.facility_id IN (" . $_SESSION['facilityMap'] . ")";
}
if ($labScope = $general->labScopeWhere('sample')) {
    $filters[] = $labScope;
}
if (!$general->isSTSInstance()) {
    $filters[] = ' sample.result_status != ' . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
}


if (!empty($_POST['batchCode'])) {
    $filters[] = ' batch.batch_code = ?';
    $params[] = (string) $_POST['batchCode'];
}
if (!empty($_POST['sampleCollectionDate'])) {
    [$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate']);
    if ($startDate !== '' && $endDate !== '') {
        $filters[] = " DATE(sample.sample_collection_date) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    }
}
if (!empty($_POST['sampleReceivedDateAtLab'])) {
    [$labStartDate, $labEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedDateAtLab']);
    if ($labStartDate !== '' && $labEndDate !== '') {
        $filters[] = " DATE(sample.sample_received_at_lab_datetime) BETWEEN ? AND ?";
        $params[] = $labStartDate;
        $params[] = $labEndDate;
    }
}
if (!empty($_POST['sampleTestedDate'])) {
    [$testedStartDate, $testedEndDate] = DateUtility::convertDateRange($_POST['sampleTestedDate']);
    if ($testedStartDate !== '' && $testedEndDate !== '') {
        $filters[] = " DATE(sample.sample_tested_datetime) BETWEEN ? AND ?";
        $params[] = $testedStartDate;
        $params[] = $testedEndDate;
    }
}
if (!empty($_POST['sampleType'])) {
    // This used to read s.sample_id against queries that never joined the
    // sample type table, so picking a sample type failed with an SQL error.
    $filters[] = ' sample.specimen_type = ?';
    $params[] = (int) $_POST['sampleType'];
}
if (!empty($_POST['labName'])) {
    // The test reason chart filtered vl.lab_id against a query that aliased
    // the form table as c, so picking a testing lab failed with an SQL error.
    $filters[] = ' sample.lab_id = ?';
    $params[] = (int) $_POST['labName'];
}

$whereCondition = implode(" AND ", $filters);
$joins = "JOIN r_sample_status AS status ON status.status_id = sample.result_status
        LEFT JOIN batch_details AS batch ON batch.batch_id = sample.sample_batch_id";

$tsQuery = "SELECT * FROM `r_sample_status` ORDER BY `status_id`";
$tsResult = $db->rawQuery($tsQuery);

$sampleStatusOverviewContainer = "genericSampleStatusOverviewContainer";
$samplesVlOverview = "genericSmplesVlOverview";
$labAverageTat = "genericLabAverageTat";

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

$tQuery = "SELECT COUNT(sample.sample_id) as total, status.status_id, status.status_name
        FROM form_generic AS sample
        $joins
        WHERE $whereCondition
        GROUP BY sample.result_status
        ORDER BY status.status_id";

$tResult = $db->rawQuery($tQuery, $params);


// Laboratory turnaround time, monthly, on the same filters as the charts above.
/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);
$tat = $genericTestsService->getTurnaroundTimeSeries(
    conditions: $filters,
    params: $params,
    joins: $joins
);

?>
<div class="col-xs-12">
    <div class="box">
        <div class="box-body">
            <div id="<?php echo $sampleStatusOverviewContainer; ?>" style="float:left;width:100%; margin: 0 auto;">
            </div>
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
    if (isset($tResult) && count($tResult) > 0) {
        $total = 0; ?>
        var _value = [
            <?php foreach ($tResult as $tRow) {
                $total += $tRow['total']; ?> {
                    name: '<?= $tRow['status_name']; ?>',
                    y: <?= $tRow['total']; ?>,
                    color: '<?= $sampleStatusColors[$tRow['status_id']]; ?>',
                    url: '/dashboard/vlTestResultStatus.php?id=<?php echo base64_encode((string) $tRow['status_id']); ?>&d=<?php echo base64_encode((string) $_POST['sampleCollectionDate']); ?>'
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

    <?php }
    if (!empty($tat['months'])) { ?>
        $('#<?php echo $labAverageTat; ?>').highcharts({
            chart: {
                type: 'line'
            },
            title: {
                text: "<?php echo _translate("Laboratory Turnaround Time"); ?>"
            },
            exporting: {
                chartOptions: {
                    subtitle: {
                        text: "<?php echo _translate("Laboratory Turnaround Time"); ?>",
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
        });
    <?php } ?>
</script>