<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


if (!empty($_POST['sampleCollectionDate'])) {
    [$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
} else {
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = date('Y-m-d');
}

$facilityId = [];
//get collection data
$table = $_POST['table'];
foreach ($_POST['facilityId'] as $facility) {
    $facilities[] = '"' . $facility . '"';
}
$collectionQuery = "SELECT COUNT(vl.unique_id) as total, facility_name
                    FROM $table as vl
                    JOIN facility_details as f ON f.facility_id=vl.facility_id
                    WHERE DATE(vl.sample_collection_date) BETWEEN '$startDate' AND '$endDate'";
if (count($facilities) > 0) {
    $collectionQuery .= " AND f.facility_name IN (" . implode(",", $facilities) . ")";
}
$collectionQuery .= "  GROUP BY f.facility_id ORDER BY total DESC";
// die($collectionQuery);
$collectionResult = $db->rawQuery($collectionQuery); //collection result
$collectionTotal = 0;
if (count($collectionResult) > 0) {
    foreach ($collectionResult as $total) {
        $collectionTotal += $total['total'];
    }
}
?>
<div id="collection" width="210" height="150" style="min-height:150px;"></div>
<script>
    $('.facilityCounterup').html('0');
    <?php if ($collectionTotal > 0) { ?>
        $('.facilityCounterup').html('<?= htmlspecialchars($collectionTotal); ?>')
        $('#collection').highcharts({
            chart: {
                type: 'column',
                height: 150
            },
            title: {
                text: ''
            },
            subtitle: {
                text: ''
            },
            credits: {
                enabled: false
            },
            xAxis: {
                categories: [<?php
                                foreach ($collectionResult as $tRow) {
                                    echo "'" . htmlspecialchars((string) $tRow['facility_name']) . "',";
                                }
                                ?>],
                crosshair: true,
                scrollbar: {
                    enabled: true
                },
            },
            yAxis: {
                min: 0,
                title: {
                    text: null
                }
            },
            tooltip: {
                headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
                pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                    '<td style="padding:0"><strong>{point.y}</strong></td></tr>',
                footerFormat: '</table>',
                shared: true,
                useHTML: true
            },
            plotOptions: {
                column: {
                    pointPadding: 0.2,
                    borderWidth: 0,
                    cursor: 'pointer',
                }
            },
            series: [{
                showInLegend: false,
                name: 'Samples',
                data: [<?php
                        foreach ($collectionResult as $tRow) {
                            echo htmlspecialchars((string) $tRow['total']) . ",";
                        }
                        ?>]

            }],
            colors: ['#f36a5a']
        });
    <?php } ?>
</script>
