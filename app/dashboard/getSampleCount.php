<?php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$facilityInfo = $facilitiesService->getAllFacilities();

$testType = (string) $_POST['type'];
$table = TestsService::getTestTableName($testType);

$recencyWhere = "";
if ($testType == 'eid') {
    $requestCountDataTable = "eidRequestCountDataTable";
    $samplesCollectionChart = "eidSamplesCollectionChart";
} elseif ($testType == 'vl') {
    $recencyWhere = " AND IFNULL(reason_for_vl_testing, 0)  != 9999";
    $requestCountDataTable = "vlRequestCountDataTable";
    $samplesCollectionChart = "vlSamplesCollectionChart";
} elseif ($testType == 'covid19') {
    $samplesCollectionChart = "covid19SamplesCollectionChart";
} elseif ($testType == 'hepatitis') {
    $requestCountDataTable = "hepatitisRequestCountDataTable";
    $samplesCollectionChart = "hepatitisSamplesCollectionChart";
} elseif ($testType == 'recency') {
    $samplesCollectionChart = "recencySamplesCollectionChart";

    // For VL Tab we do not want to show Recency Counts
    $recencyWhere = " AND reason_for_vl_testing = 9999";
    $requestCountDataTable = "recencyRequestCountDataTable";
} elseif ($testType == 'tb') {
    $requestCountDataTable = "tbRequestCountDataTable";
    $samplesCollectionChart = "tbSamplesCollectionChart";
} elseif ($testType == 'generic-tests') {
    $requestCountDataTable = "genericRequestCountDataTable";
    $samplesCollectionChart = "genericSamplesCollectionChart";
} elseif ($testType == 'cd4') {
    $requestCountDataTable = "cd4RequestCountDataTable";
    $samplesCollectionChart = "cd4SamplesCollectionChart";
}

$whereConditionArray = [];
$whereConditionArray[] = " vl.result_status != " . SAMPLE_STATUS\CANCELLED;
if (!$general->isSTSInstance()) {
    $whereConditionArray[] = " vl.result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
} else {
    if (!empty($_SESSION['facilityMap'])) {
        $whereConditionArray[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
    }
}

$whereCondition = implode(' AND ', $whereConditionArray);

if (!empty($_POST['sampleCollectionDate'])) {
    $selectedRange = (string) $_POST['sampleCollectionDate'];
    [$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
} else {
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $selectedRange = date('d-M-Y', strtotime($startDate)) . ' to ' . date('d-M-Y', strtotime($endDate));
}

$sQuery = "SELECT
    vl.facility_id,
    f.facility_code,
    f.facility_state,
    f.facility_district,
    f.facility_name,
    COUNT(*) AS totalCount,
    NULL AS reorderCount,
    SUM(result_status = 9) AS registerCount,
    SUM(result_status = 8) AS sentToLabCount,
    SUM(result_status = 4) AS rejectCount,
    SUM(result_status = 6) AS pendingCount,
    SUM(result_status = 5) AS invalidCount,
    SUM(result_status = 7) AS acceptCount,
    SUM(vl.result_printed_datetime IS NOT NULL) AS printCount
    FROM $table AS vl
    JOIN facility_details AS f ON f.facility_id = vl.facility_id
    WHERE DATE(vl.sample_collection_date) BETWEEN '$startDate' AND '$endDate'
    AND $whereCondition
    $recencyWhere
    GROUP BY vl.facility_id
    ORDER BY totalCount DESC";

$tableResult = $db->rawQuery($sQuery);
?>

<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 ">
    <table aria-describedby="table" class="table collectionTable" cellpadding="1" cellspacing="3" style="margin-top:0px;width: 98%;margin-bottom: 0px;">
        <tr>
            <th style="vertical-align:middle;padding-left: 0px;"><strong>
                    <?php echo _translate("Collection Point"); ?>&nbsp;:
                </strong>
                <select id="facilityId<?php echo $testType; ?>" name="facilityId" class="form-control" multiple title="<?php echo _translate('Select facility name to filter'); ?>" style="width:220px;background:#fff;">
                    <?php foreach ($facilityInfo as $facility) { ?>
                        <option vlaue="<?php echo $facility['facility_id']; ?>"><?php echo $facility['facility_name']; ?>
                        </option>
                    <?php } ?>
                </select>
            </th>
            <td colspan="3" style=" display: grid; ">&nbsp;<input type="button" onclick="fetchByFacility();" value="<?= _translate('Search'); ?>" class="searchBtn btn btn-success btn-sm">
            </td>
        </tr>
    </table>
    <div class="dashboard-stat2 " style="cursor:pointer;">
        <div class="display">
            <div class="number">
                <div class="number">
                    <h4 class="font-purple-soft" style="font-weight:600;">
                        <?php echo _translate("SAMPLES REGISTERED BY COLLECTION POINT"); ?>
                    </h4>
                    <small class="font-purple-soft" style="font-size:0.75em;">
                        <?php echo _translate("In Selected Range") . " : " . $selectedRange; ?>
                    </small>
                </div>
            </div>
            <div class="icon">
                <em class="fa-solid fa-chart-simple"></em>
            </div>
        </div>
        <div id="collectionSite<?php echo $testType; ?>">
            <div id="<?php echo $samplesCollectionChart; ?>" style="min-height:300px;"></div>
        </div>
    </div>
</div>
<div class="col-xs-12">
    <div class="box">
        <div class="box-body">
            <table aria-describedby="table" id="<?php echo $requestCountDataTable; ?>" class="table table-bordered table-striped table-hover requestCountDataTable">
                <thead>
                    <tr>
                        <th scope="row">
                            <?php echo _translate("Facility Name"); ?>
                        </th>
                        <th class="sum">
                            <?php echo _translate("Total Samples Registered"); ?>
                        </th>
                        <th class="sum">
                            <?php echo _translate("Samples Currently Registered at HC"); ?>
                        </th>
                        <th class="sum">
                            <?php echo _translate("Samples Currently Registered at Testing Lab"); ?>
                            <br>
                            <?php echo _translate("(Results not yet available)"); ?>
                        </th>
                        <th class="sum">
                            <?php echo _translate("Samples with Accepted Results"); ?>
                        </th>
                        <th class="sum">
                            <?php echo _translate("Samples Rejected"); ?>
                        </th>
                        <th class="sum">
                            <?php echo _translate("Samples with Invalid or Failed Results"); ?>
                        </th>
                        <th class="sum">
                            <?php echo _translate("Samples Reordered"); ?>
                        </th>
                        <th class="sum">
                            <?php echo _translate("Results Printed"); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($tableResult)) {
                        foreach ($tableResult as $tableRow) { ?>
                            <tr>
                                <td>
                                    <?= $tableRow['facility_name'] ?? 0; ?>
                                </td>
                                <td>
                                    <?= $tableRow['totalCount'] ?? 0; ?>
                                </td>
                                <td>
                                    <?= $tableRow['registerCount'] ?? 0; ?>
                                </td>
                                <td>
                                    <?= $tableRow['pendingCount'] ?? 0; ?>
                                </td>
                                <td>
                                    <?= $tableRow['acceptCount'] ?? 0; ?>
                                </td>
                                <td>
                                    <?= $tableRow['rejectCount'] ?? 0; ?>
                                </td>
                                <td>
                                    <?= $tableRow['invalidCount'] ?? 0; ?>
                                </td>
                                <td>
                                    <?= $tableRow['reorderCount'] ?? 0; ?>
                                </td>
                                <td>
                                    <?= $tableRow['printCount'] ?? 0; ?>
                                </td>
                            </tr>
                    <?php
                        }
                    } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<script>
    function fetchByFacility() {
        $.blockUI();
        $.post("/dashboard/get-collection-samples.php", {
                table: '<?php echo $table; ?>',
                facilityId: $('#facilityId<?php echo $testType; ?>').val(),
                sampleCollectionDate: '<?php echo htmlspecialchars((string) $_POST['sampleCollectionDate']); ?>',
            },
            function(data) {
                $("#collectionSite<?php echo $testType; ?>").html(data);
            });
        $.unblockUI();
    }
    $(document).ready(function() {
        $('#facilityId<?php echo $testType; ?>').select2({
            width: '100%',
            placeholder: "<?= _translate("Select Collection Point(s)"); ?>"
        });
    });
    $(function() {
        var table = $("#<?php echo $requestCountDataTable; ?>").DataTable({
            "initComplete": function(settings, json) {
                let api = this.api();
                calculateTableSummary(this, 'all');
            },
            "sorting": [1, "desc"],
            "footerCallback": function(row, data, start, end, display) {
                let filter = $("#<?php echo $requestCountDataTable; ?>_filter .input-sm").val();
                let page = 'all';
                if (filter != '') {
                    page = 'current';
                }
                var api = this.api(),
                    data;
                calculateTableSummary(this, page);

            }
        });

    });

    function calculateTableSummary(table, page) {
        try {

            var intVal = function(i) {
                return typeof i === 'string' ?
                    i.replace(/[\$,]/g, '') * 1 :
                    typeof i === 'number' ?
                    i : 0;
            };


            var api = table.api();
            api.columns(".sum").eq(0).each(function(index) {
                var column = api.column(index, {
                    page: page
                });

                var sum = column
                    .data()
                    .reduce(function(a, b) {
                        return intVal(a) + intVal(b);
                    }, 0);


                $(api.column(index).footer()).html(sum);

            });
        } catch (e) {
            console.log('Error in calculateTableSummary');
            console.log(e)
        }
    }
    <?php
    if (!empty($tableResult)) { ?>
        $('#<?php echo $samplesCollectionChart; ?>').highcharts({
            chart: {
                type: 'column',
                height: 400
            },
            title: {
                text: ''
            },
            exporting: {
                filename: "samples-registered-by-collection-point",
                sourceWidth: 1200,
                sourceHeight: 600
            },
            subtitle: {
                text: ''
            },
            credits: {
                enabled: false
            },
            xAxis: {
                categories: [<?= "'" . implode("','", array_map('htmlspecialchars', array_column($tableResult, 'facility_name'))) . "'"; ?>],
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
                data: [<?= implode(",", array_column($tableResult, 'totalCount')); ?>]

            }],
            colors: ['#f36a5a']
        });
    <?php } ?>
</script>