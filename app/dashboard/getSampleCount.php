<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$facilityInfo = $facilitiesService->getAllFacilities();

$cDate = date('Y-m-d');
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-7 days'));

$systemType = $general->getSystemConfig('sc_user_type');
if (isset($_POST['type']) && trim($_POST['type']) == 'eid') {
    $table = "form_eid";
    $primaryKey = "eid_id";
    $unique = "Test2";
    $requestCountDataTable = "eidRequestCountDataTable";
    $samplesCollectionChart = "eidSamplesCollectionChart";
} elseif (isset($_POST['type']) && trim($_POST['type']) == 'vl') {
    $recencyWhere = " AND IFNULL(reason_for_vl_testing, 0)  != 9999";
    $table = "form_vl";
    $primaryKey = "vl_sample_id";
    $unique = "Test1";
    $requestCountDataTable = "vlRequestCountDataTable";
    $samplesCollectionChart = "vlSamplesCollectionChart";
} elseif (isset($_POST['type']) && trim($_POST['type']) == 'covid19') {
    $samplesCollectionChart = "covid19SamplesCollectionChart";
    $table = "form_covid19";
    $primaryKey = "covid19_id";
    $unique = "Test3";
} elseif (isset($_POST['type']) && trim($_POST['type']) == 'hepatitis') {
    $samplesCollectionChart = "hepatitisSamplesCollectionChart";
    $table = "form_hepatitis";
    $primaryKey = "hepatitis_id";
    $unique = "Test4";
} elseif (isset($_POST['type']) && trim($_POST['type']) == 'recency') {
    $samplesCollectionChart = "recencySamplesCollectionChart";
    $table = "form_vl";
    $primaryKey = "vl_sample_id";
    $unique = "Test5";

    // For VL Tab we do not want to show Recency Counts
    $recencyWhere = " AND reason_for_vl_testing = 9999";
    $requestCountDataTable = "recencyRequestCountDataTable";
} elseif (isset($_POST['type']) && trim($_POST['type']) == 'tb') {
    $table = "form_tb";
    $primaryKey = "tb_id";
    $unique = "Test6";
    $samplesCollectionChart = "tbSamplesCollectionChart";
} elseif (isset($_POST['type']) && trim($_POST['type']) == 'generic-tests') {
    $table = "form_generic";
    $primaryKey = "sample_id";
    $unique = "Test7";
    $samplesCollectionChart = "genericSamplesCollectionChart";
}


if ($systemType != 'remoteuser') {
    if (isset($_POST['type']) && trim($_POST['type']) == 'eid') {
        $whereCondition = " AND eid.result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
    } else {
        $whereCondition = " AND vl.result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
    }
} else {
    $whereCondition = "";
    //get user facility map ids
    if (!empty($_SESSION['facilityMap'])) {
        if (isset($_POST['type']) && trim($_POST['type']) == 'eid') {
            $whereCondition = " AND eid.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
        } else {
            $whereCondition = " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
        }
    }
}

$sampleCollectionDate = explode("to", $_POST['sampleCollectionDate'] ?? '');
$sampleCollectionDate = array_map('trim', $sampleCollectionDate);

$startDate = !empty($sampleCollectionDate[0]) ? DateUtility::isoDateFormat($sampleCollectionDate[0]) : null;
$endDate = !empty($sampleCollectionDate[1]) ? DateUtility::isoDateFormat($sampleCollectionDate[1]) : null;

if ($table == "form_eid") {
    $sQuery = "SELECT
		eid.facility_id,f.facility_code,f.facility_state,f.facility_district,f.facility_name,
		COUNT(*) AS totalCount,
		NULL AS reorderCount,
		SUM(CASE
			WHEN (result_status=9) THEN 1
				ELSE 0
			END) AS registerCount,
		SUM(CASE
			WHEN (result_status=8) THEN 1
				ELSE 0
			END) AS sentToLabCount,
		SUM(CASE
			WHEN (result_status=4) THEN 1
				ELSE 0
			END) AS rejectCount,
		SUM(CASE
			WHEN (result_status=6) THEN 1
				ELSE 0
			END) AS pendingCount,
		SUM(CASE
			WHEN (result_status=5) THEN 1
				ELSE 0
			END) AS invalidCount,
		SUM(CASE
			WHEN (result_status=7) THEN 1
				ELSE 0
			END) AS acceptCount,
		SUM(CASE
			WHEN (DATE(eid.result_printed_datetime) > '1970-01-01') THEN 1
				ELSE 0
			END) AS printCount
		FROM $table as eid JOIN facility_details as f ON f.facility_id=eid.facility_id
        WHERE DATE(eid.sample_collection_date) BETWEEN '$startDate' AND '$endDate'
        $whereCondition
        GROUP BY eid.facility_id ORDER BY totalCount DESC";
} else {
    $sQuery = "SELECT
                vl.facility_id,
                f.facility_code,
                f.facility_state,
                f.facility_district,
                f.facility_name,
                COUNT(*) AS totalCount,
                NULL AS reorderCount,
                SUM(CASE
                    WHEN (result_status=9) THEN 1
                        ELSE 0
                    END) AS registerCount,
                SUM(CASE
                    WHEN (result_status=8) THEN 1
                        ELSE 0
                    END) AS sentToLabCount,
                SUM(CASE
                    WHEN (result_status=4) THEN 1
                        ELSE 0
                    END) AS rejectCount,
                SUM(CASE
                    WHEN (result_status=6) THEN 1
                        ELSE 0
                    END) AS pendingCount,
                SUM(CASE
                    WHEN (result_status=5) THEN 1
                        ELSE 0
                    END) AS invalidCount,
                SUM(CASE
                    WHEN (result_status=7) THEN 1
                        ELSE 0
                    END) AS acceptCount,
                SUM(CASE
                    WHEN (DATE(vl.result_printed_datetime) > '1970-01-01') THEN 1
                        ELSE 0
                    END) AS printCount
                FROM $table as vl JOIN facility_details as f ON f.facility_id=vl.facility_id
                WHERE DATE(vl.sample_collection_date) BETWEEN '$startDate' AND '$endDate'
                $whereCondition
                $recencyWhere
                GROUP BY vl.facility_id ORDER BY totalCount DESC";
}

$tableResult = $db->rawQuery($sQuery);
?>

<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 ">
    <table aria-describedby="table" class="table collectionTable" cellpadding="1" cellspacing="3" style="margin-top:0px;width: 98%;margin-bottom: 0px;">
        <tr>
            <th style="vertical-align:middle;padding-left: 0px;"><strong>
                    <?php echo _translate("Collection Point"); ?>&nbsp;:
                </strong>
                <select id="facilityId<?php echo $unique; ?>" name="facilityId" class="form-control" multiple title="<?php echo _translate('Select facility name to filter'); ?>" style="width:220px;background:#fff;">
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
                <h3 class="font-purple-soft"></h3>
                <small class="font-purple-soft">
                    <?php echo _translate("SAMPLES REGISTERED BY COLLECTION POINT"); ?>
                </small><br>
            </div>
            <div class="icon">
                <em class="fa-solid fa-chart-pie"></em>
            </div>
        </div>
        <div id="collectionSite<?php echo $unique; ?>">
            <div id="<?php echo $samplesCollectionChart; ?>" style="min-height:250px;"></div>
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
                            <?php echo _translate("Samples Currently Registered at VL Lab"); ?>
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
                primaryKey: '<?php echo $primaryKey; ?>',
                facilityId: $('#facilityId<?php echo $unique; ?>').val(),
                cDate: <?php echo $cDate; ?>,
                sampleCollectionDate: '<?php echo htmlspecialchars($_POST['sampleCollectionDate']); ?>',
            },
            function(data) {
                $("#collectionSite<?php echo $unique; ?>").html(data);
            });
        $.unblockUI();
    }
    $(document).ready(function() {
        $('#facilityId<?php echo $unique; ?>').select2({
            width: '100%',
            placeholder: "<?= _translate("Select Collection Point(s)"); ?>"
        });
    });
    $(function() {
        var table = $("#<?php echo $requestCountDataTable; ?>").DataTable({
            "initComplete": function(settings, json) {
                var api = this.api();
                CalculateTableSummary(this, 'all');
            },
            "footerCallback": function(row, data, start, end, display) {
                var filter = $("#<?php echo $requestCountDataTable; ?>_filter .input-sm").val();
                if (filter != '') {
                    var page = 'current';
                } else {
                    var page = 'all';
                }
                var api = this.api(),
                    data;
                CalculateTableSummary(this, page);

            }
        });

    });

    function CalculateTableSummary(table, page) {
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
            console.log('Error in CalculateTableSummary');
            console.log(e)
        }
    }
    <?php
    if (isset($tableResult) && $tableResult > 0) { ?>
        $('#<?php echo $samplesCollectionChart; ?>').highcharts({
            chart: {
                type: 'column',
                height: 350
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
                                foreach ($tableResult as $tRow) {
                                    echo "'" . addslashes($tRow['facility_name']) . "',";
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
                        foreach ($tableResult as $tRow) {
                            echo ($tRow['totalCount']) . ",";
                        }
                        ?>]

            }],
            colors: ['#f36a5a']
        });
    <?php } ?>
</script>
