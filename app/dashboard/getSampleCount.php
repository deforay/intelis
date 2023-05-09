<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$facilityInfo = $facilitiesService->getAllFacilities();
$configFormQuery = "SELECT * FROM global_config WHERE name ='vl_form'";
$configFormResult = $db->rawQuery($configFormQuery);
$country = $configFormResult[0]['value'];
$cDate = date('Y-m-d');
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-7 days'));

$systemType = $general->getSystemConfig('sc_user_type');
if (isset($_POST['type']) && trim($_POST['type']) == 'eid') {
    $table = "form_eid";
    $primaryKey = "eid_id";
    $unique = "Test2";
    $requestCountDataTable = "eidRequestCountDataTable";
    $samplesCollectionChart = "eidSamplesCollectionChart";
} else if (isset($_POST['type']) && trim($_POST['type']) == 'vl') {
    $recencyWhere = " AND reason_for_vl_testing != 9999";
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
}


if ($systemType != 'remoteuser') {
    if (isset($_POST['type']) && trim($_POST['type']) == 'eid') {
        $whereCondition = " AND eid.result_status != 9 ";
    } else {
        $whereCondition = " AND vl.result_status != 9 ";
    }
} else {
    $whereCondition = "";
    //get user facility map ids
    $userfacilityMapQuery = "SELECT GROUP_CONCAT(DISTINCT facility_id ORDER BY facility_id SEPARATOR ',') as facility_id FROM user_facility_map where user_id='" . $_SESSION['userId'] . "'";
    $userfacilityMapresult = $db->rawQuery($userfacilityMapQuery);
    if ($userfacilityMapresult[0]['facility_id'] != null && $userfacilityMapresult[0]['facility_id'] != '') {
        $userfacilityMapresult[0]['facility_id'] = rtrim($userfacilityMapresult[0]['facility_id'], ",");
        if (isset($_POST['type']) && trim($_POST['type']) == 'eid') {
            $whereCondition = " AND eid.facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ") ";
        } else {
            $whereCondition = " AND vl.facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ") ";
        }
    }
}

if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $s_c_date = explode("to", $_POST['sampleCollectionDate']);
    //print_r($s_c_date);die;
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
    }
}
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
			WHEN (result_printed_datetime not like '' AND result_printed_datetime is not NULL AND DATE(result_printed_datetime) NOT LIKE '0000-00-00 00:00:00') THEN 1
				ELSE 0
			END) AS printCount
		FROM " . $table . " as eid JOIN facility_details as f ON f.facility_id=eid.facility_id";
    $sQuery = $sQuery . ' WHERE DATE(eid.sample_collection_date) >= "' . $start_date . '" AND DATE(eid.sample_collection_date) <= "' . $end_date . '"';
    $sQuery = $sQuery . $whereCondition;
    $sQuery = $sQuery . ' GROUP BY eid.facility_id';
} else {
    $sQuery = "SELECT
		vl.facility_id,f.facility_code,f.facility_state,f.facility_district,f.facility_name,
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
			WHEN (result_printed_datetime not like '' AND result_printed_datetime is not NULL AND DATE(result_printed_datetime) NOT LIKE '0000-00-00 00:00:00') THEN 1
				ELSE 0
			END) AS printCount
		FROM " . $table . " as vl JOIN facility_details as f ON f.facility_id=vl.facility_id";
    $sQuery = $sQuery . ' where DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
    $sQuery = $sQuery . $whereCondition . $recencyWhere;
    $sQuery = $sQuery . ' GROUP BY vl.facility_id';
}
//echo $sQuery; die;
$tableResult = $db->rawQuery($sQuery);
?>

<style>
    #<?php echo $requestCountDataTable; ?>thead th {
        vertical-align: middle;
    }
</style>
<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 ">
    <table aria-describedby="table" class="table collectionTable" cellpadding="1" cellspacing="3" style="margin-top:0px;width: 98%;margin-bottom: 0px;">
        <tr>
            <th style="vertical-align:middle;padding-left: 0px;"><strong><?php echo _("Collection Point"); ?>&nbsp;:</strong>
                <select id="facilityId<?php echo $unique; ?>" name="facilityId" class="form-control" multiple title="<?php echo _('Select facility name to filter'); ?>" style="width:220px;background:#fff;">
                    <?php foreach ($facilityInfo as $facility) { ?>
                        <option vlaue="<?php echo $facility['facility_id']; ?>"><?php echo $facility['facility_name']; ?></option>
                    <?php } ?>
                </select>
            </th>
            <td colspan="3" style=" display: grid; ">&nbsp;<input type="button" onclick="fetchByFacility();" value="<?php echo _('Search'); ?>" class="searchBtn btn btn-success btn-sm">
            </td>
        </tr>
    </table>
    <div class="dashboard-stat2 " style="cursor:pointer;">
        <div class="display">
            <div class="number">
                <h3 class="font-purple-soft">

                </h3>
                <small class="font-purple-soft"><?php echo _("SAMPLES REGISTERED BY COLLECTION POINT"); ?></small><br>
                <!-- <small class="font-purple-soft" style="font-size:0.75em;">(LAST 6 MONTHS)</small> -->
            </div>
            <div class="icon">
                <em class="fa-solid fa-chart-pie"></em>
            </div>
        </div>
        <div id="collectionSite<?php echo $unique; ?>">
            <div id="<?php echo $samplesCollectionChart; ?>" width="210" height="150" style="min-height:150px;"></div>
        </div>
    </div>
</div>
<div class="col-xs-12">
    <div class="box">
        <div class="box-body">
            <table aria-describedby="table" id="<?php echo $requestCountDataTable; ?>" class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th scope="row"><?php echo _("Facility Name"); ?></th>
                        <th class="sum"><?php echo _("Total Samples Registered"); ?></th>
                        <th class="sum"><?php echo _("Samples Currently Registered at HC"); ?></th>
                        <!-- <th class="sum">Samples Received/ Sent To Lab</th> -->
                        <th class="sum"><?php echo _("Samples Currently Registered at VL Lab"); ?><br><?php echo _("(Results not yet available)"); ?></th>
                        <th class="sum"><?php echo _("Samples with Accepted Results"); ?></th>
                        <th class="sum"><?php echo _("Samples Rejected"); ?></th>
                        <th class="sum"><?php echo _("Samples with Invalid or Failed Results"); ?></th>
                        <th class="sum"><?php echo _("Samples Reordered"); ?></th>
                        <th class="sum"><?php echo _("Results Printed"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($tableResult) && count($tableResult) > 0) {
                        foreach ($tableResult as $tableRow) { ?>
                            <tr>
                                <td><?php echo ($tableRow['facility_name']); ?></td>
                                <td><?php echo $tableRow['totalCount']; ?></td>
                                <td><?php echo $tableRow['registerCount']; ?></td>
                                <td><?php echo $tableRow['pendingCount']; ?></td>
                                <td><?php echo $tableRow['acceptCount']; ?></td>
                                <td><?php echo $tableRow['rejectCount']; ?></td>
                                <td><?php echo $tableRow['invalidCount']; ?></td>
                                <td><?php echo $tableRow['reorderCount']; ?></td>
                                <td><?php echo $tableRow['printCount']; ?></td>
                            </tr>
                    <?php
                        }
                    } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <!-- <td></td> -->
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
            placeholder: "<?= _("Select Collection Point(s)"); ?>"
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
                        //return parseInt(a, 10) + parseInt(b, 10);
                        return intVal(a) + intVal(b);
                    }, 0);


                //console.log(sum);

                $(api.column(index).footer()).html(sum);
                // $( api.column( 1 ).footer() ).html(registerCount);
                // $( api.column( 2 ).footer() ).html(reorderCount);
                // $( api.column( 3 ).footer() ).html(sentToLabCount);
                // $( api.column( 4 ).footer() ).html(rejectCount);
                // $( api.column( 5 ).footer() ).html(pendingCount);
                // $( api.column( 6 ).footer() ).html(invalidCount);
                // $( api.column( 7 ).footer() ).html(acceptCount);
                // $( api.column( 8 ).footer() ).html(printCount);

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