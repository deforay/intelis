<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $s_c_date = explode("to", $_POST['sampleCollectionDate']);
    //print_r($s_c_date);die;
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $lastSevenDay = DateUtility::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $cDate = DateUtility::isoDateFormat(trim($s_c_date[1]));
    }
}

$configFormQuery = "SELECT * FROM global_config WHERE name ='vl_form'";
$configFormResult = $db->rawQuery($configFormQuery);
$facilityId = [];
//get collection data
$table = $_POST['table'];
$primaryKey = $_POST['primaryKey'];
foreach ($_POST['facilityId'] as $facility) {
    $facilities[] = '"' . $facility . '"';
}
$collectionQuery = "SELECT COUNT($primaryKey) as total, facility_name FROM " . $table . " as vl JOIN facility_details as f ON f.facility_id=vl.facility_id WHERE vlsm_country_id = '" . $configFormResult[0]['value'] . "' AND DATE(vl.sample_collection_date) <= '" . $cDate . "' AND DATE(vl.sample_collection_date)>= '" . $lastSevenDay . "'";
if (sizeof($facilities) > 0) {
    $collectionQuery .= " AND f.facility_name IN (" . implode(",", $facilities) . ")";
}
$collectionQuery .= "  GROUP BY f.facility_id ORDER BY total DESC";
// die($collectionQuery);
$collectionResult = $db->rawQuery($collectionQuery); //collection result
$collectionTotal = 0;
if (sizeof($collectionResult) > 0) {
    foreach ($collectionResult as $total) {
        $collectionTotal = $collectionTotal + $total['total'];
    }
}
?>
<div id="collection" width="210" height="150" style="min-height:150px;"></div>
<script>
    $('.facilityCounterup').html('0');
    <?php if ($collectionTotal > 0) { ?>
        $('.facilityCounterup').html('<?php echo $collectionTotal; ?>')
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
                                    echo "'" . ($tRow['facility_name']) . "',";
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
                            echo ($tRow['total']) . ",";
                        }
                        ?>]

            }],
            colors: ['#f36a5a']
        });
    <?php } ?>
</script>