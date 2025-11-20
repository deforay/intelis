<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {

    $sWhere = [];
    if (isset($_POST['cType']) && trim((string) $_POST['cType']) !== '') {
        $sWhere[] = ' vl.control_type = "' . $_POST['cType'] . '" ';
    }
    if (!empty($_POST['sampleTestDate'])) {
        [$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleTestDate'] ?? '');
        $sWhere[] = " DATE(vl.sample_tested_datetime) BETWEEN '$startDate' AND '$endDate' ";
    }

    $whereConditions = '';
    if ($sWhere !== []) {
        $whereConditions = "WHERE " . implode(" AND ", $sWhere);
    }

    $tsQuery = "SELECT DATE_FORMAT(sample_tested_datetime,'%d-%M-%Y') AS sample_tested_datetime,
                SUM(CASE WHEN (result_value_absolute != '' AND result_value_absolute IS NOT NULL) THEN result_value_absolute
                        ELSE 0
                    END) AS sumTotal,
                COUNT(CASE WHEN (result_value_absolute != '' AND result_value_absolute IS NOT NULL) THEN 1
                        ELSE 0
                END) AS countTotal
            FROM vl_imported_controls as vl
            $whereConditions
            GROUP BY DATE_FORMAT(sample_tested_datetime,'%d-%M-%Y')";
    $totalControlResult = $db->rawQuery($tsQuery);

    $sQuery = "SELECT control_id,
                SUM(CASE WHEN (result_value_absolute != '' AND result_value_absolute IS NOT NULL)
                        THEN result_value_absolute ELSE 0 END)
                AS sumTotal
                FROM vl_imported_controls as vl
                $whereConditions GROUP BY control_id";
    $controlResult = $db->rawQuery($sQuery);

    $sumTotal = array_column($controlResult, 'sumTotal');

    if ($sumTotal !== []) {
        $mean = array_sum($sumTotal) / count($sumTotal);

        $sd_square = fn($x): float|int => ($x - $mean) ** 2;

        $sd = (count($sumTotal) > 1) ? sqrt(array_sum(array_map($sd_square, $sumTotal)) / (count($sumTotal) - 1)) : 0;
    } else {
        $mean = 0;
        $sd = 0;
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'exception' => $e,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stacktrace' => $e->getTraceAsString()
    ]);
}
?>
<div id="container" style="height: 400px"></div>
<script>
    $(function () {
        Highcharts.chart('container', {
            title: {
                text: "<?php echo _translate("Control Result", true); ?>"
            },
            subtitle: {
                text: "<?php echo _translate("Mean", true); ?>:<?= round($mean, 2); ?>   <?= _translate("SD", true); ?>:<?php echo round($sd, 2); ?>"
            },
            xAxis: {
                categories: [<?php
                foreach ($totalControlResult as $category) {
                    echo "'" . $category['sample_tested_datetime'] . "',";
                }
                ?>]
            },

            yAxis: {
                min: <?= round($mean - ($sd * 3), 2); ?>,
                max: <?= round($mean + ($sd * 3), 2); ?>,
                title: {
                    text: "<?= _translate("Control Result", true); ?>"
                },

                plotLines: [{
                    value: <?= round($mean + ($sd * 3), 2); ?>,
                    color: 'green',
                    dashStyle: 'shortdash',
                    width: 2,
                    label: {
                        text: <?= round($mean + ($sd * 3), 2); ?>
                    }
                },
                {
                    value: <?= round($mean + ($sd * 2), 2); ?>,
                    color: 'orange',
                    dashStyle: 'shortdash',
                    width: 2,
                    label: {
                        text: <?= round($mean + ($sd * 2), 2); ?>
                    }
                },
                {
                    value: <?= round($mean + ($sd), 2); ?>,
                    color: 'red',
                    dashStyle: 'shortdash',
                    width: 2,
                    label: {
                        text: <?php echo round($mean + ($sd), 2); ?>
                    }
                }, {
                    value: '<?= round($mean); ?>',
                    color: 'yellow',
                    dashStyle: 'shortdash',
                    width: 2,
                    label: {
                        text: <?= round($mean); ?>
                    }
                }, {
                    value: '<?= round($mean - ($sd), 2); ?>',
                    color: 'red',
                    dashStyle: 'shortdash',
                    width: 2,
                    label: {
                        text: <?= round($mean - ($sd), 2); ?>
                    }
                }, {
                    value: '<?= round($mean - ($sd * 2), 2); ?>',
                    color: 'orange',
                    dashStyle: 'shortdash',
                    width: 2,
                    label: {
                        text: <?= round($mean - ($sd * 2), 2); ?>
                    }
                }, {
                    value: '<?= round($mean - ($sd * 3), 2); ?>',
                    color: 'green',
                    dashStyle: 'shortdash',
                    width: 2,
                    label: {
                        text: <?= round($mean - ($sd * 3), 2); ?>
                    }
                }
                ]
            },


            series: [{
                data: [<?php
                foreach ($totalControlResult as $total) {
                    echo round($total['sumTotal'] / $total['countTotal'], 2) . ",";
                }
                ?>]
            }]
        });
    });
</script>