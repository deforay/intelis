<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {

    if (!$general->isSTSInstance()) {
        $whereCondition = " AND result_status!= " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . " ";
    } else {
        $whereCondition = "";
        if (!empty($_SESSION['facilityMap'])) {
            $whereCondition = " AND tb.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
        }
    }

    if (!empty($_POST['sampleCollectionDate'])) {
        [$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
    } else {
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');
    }
    /* State filter */
    if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
        $whereCondition .= " AND f.facility_state_id = '" . $_POST['state'] . "' ";
    }

    /* District filters */
    if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
        $whereCondition .= " AND f.facility_district_id = '" . $_POST['district'] . "' ";
    }
    /* Facility filter */
    if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
        $whereCondition .=  ' AND f.facility_id IN (' . $_POST['facilityName'] . ') ';
    }

    $sQuery = "SELECT
                tb.facility_id,
                f.facility_code,
                f.facility_state,
                f.facility_district,
                f.facility_name,
                COUNT(*) AS totalCount,
                SUM(CASE
                    WHEN (result_status=6) THEN 1
                        ELSE 0
                    END) AS registerCount,
                SUM(CASE
                    WHEN (result_status=11) THEN 1
                        ELSE 0
                    END) AS noResultCount,
                SUM(CASE
                    WHEN (result_status=7) THEN 1
                        ELSE 0
                    END) AS acceptCount,
                SUM(CASE
                    WHEN (result_status=4) THEN 1
                        ELSE 0
                    END) AS rejectCount

                FROM form_tb as tb JOIN facility_details as f ON f.facility_id=tb.facility_id
                WHERE DATE(tb.sample_collection_date) BETWEEN '$startDate' AND '$endDate'
                $whereCondition
                GROUP BY tb.facility_id ORDER BY totalCount DESC";
    $sampleTestingResult = $db->rawQuery($sQuery);
} catch (Exception $e) {
    LoggerUtility::log("error", $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}

?>
<script>
    Highcharts.chart('container', {
        chart: {
            type: 'column'
        },
        title: {
            text: "<?= _translate("Samples Testing Report"); ?>",
            align: 'left'
        },
        exporting: {
            sourceWidth: 1200,
            sourceHeight: 600
        },
        credits: {
            enabled: false
        },
        xAxis: {
            categories: [
                <?php
                foreach ($sampleTestingResult as $row) {
                    echo '"' . ($row['facility_name']) . '",';
                }
                ?>
            ]
        },
        yAxis: {
            allowDecimals: false,
            min: 0,
            title: {
                text: "<?= _translate("No. of Samples"); ?>"
            },
            stackLabels: {
                enabled: true
            }
        },
        legend: {
            align: 'left',
            x: 70,
            verticalAlign: 'top',
            y: 70,
            floating: true,
            backgroundColor: Highcharts.defaultOptions.legend.backgroundColor || 'white',
            borderColor: '#CCC',
            borderWidth: 1,
            shadow: false
        },
        tooltip: {
            headerFormat: '<b>{point.key}</b><br/>',
            pointFormat: '{series.name}: {point.y}<br/>Total: {point.stackTotal}'
        },
        plotOptions: {
            column: {
                stacking: 'normal',
                dataLabels: {
                    enabled: true
                }
            }
        },
        series: [{
            name: 'Samples collected',
            data: [
                <?php
                foreach ($sampleTestingResult as $row) {
                    echo ($row['registerCount']) . ',';
                }
                ?>
            ]
        }, {
            name: 'Samples Not tested',
            data: [
                <?php
                foreach ($sampleTestingResult as $row) {
                    echo ($row['noResultCount']) . ',';
                }
                ?>
            ]
        }, {
            name: 'Samples Tested',
            data: [
                <?php
                foreach ($sampleTestingResult as $row) {
                    echo ($row['acceptCount']) . ',';
                }
                ?>
            ]
        }, {
            name: 'Samples Rejected',
            data: [
                <?php
                foreach ($sampleTestingResult as $row) {
                    echo ($row['rejectCount']) . ',';
                }
                ?>
            ]
        }]
    });
</script>
