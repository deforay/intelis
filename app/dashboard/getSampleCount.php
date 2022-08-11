<?php

$general = new \Vlsm\Models\General(); 

$configFormQuery = "SELECT * FROM global_config WHERE name ='vl_form'";
$configFormResult = $db->rawQuery($configFormQuery);

$country = $configFormResult[0]['value'];
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-7 days'));

$u = $general->getSystemConfig('sc_user_type');
if (isset($_POST['type']) && trim($_POST['type']) == 'eid') {
    $table = "form_eid";
    $primaryKey = "eid_id";
    $requestCountDataTable = "eidRequestCountDataTable";
} else if (isset($_POST['type']) && trim($_POST['type']) == 'vl') {

    $recencyWhere = " AND reason_for_vl_testing != 9999";
    $table = "form_vl";
    $primaryKey = "vl_sample_id";
    $requestCountDataTable = "vlRequestCountDataTable";
}

// For VL Tab we do not want to show Recency Counts

else if (isset($_POST['type']) && trim($_POST['type']) == 'recency') {
    $recencyWhere = " AND reason_for_vl_testing = 9999";
    $table = "form_vl";
    $primaryKey = "vl_sample_id";
    $requestCountDataTable = "recencyRequestCountDataTable";
}



if ($u != 'remoteuser') {
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
        $start_date = $general->isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = $general->isoDateFormat(trim($s_c_date[1]));
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
		FROM " . $table . " as vl JOIN facility_details as f ON f.facility_id=vl.facility_id
		where  vl.vlsm_country_id =" . $country;
    $sQuery = $sQuery . ' AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
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
<div class="col-xs-12">
    <div class="box">
        <div class="box-body">
            <table id="<?php echo $requestCountDataTable; ?>" class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th><?php echo _("Facility Name");?></th>
                        <th class="sum"><?php echo _("Total Samples Registered");?></th>
                        <th class="sum"><?php echo _("Samples Currently Registered at HC");?></th>
                        <!-- <th class="sum">Samples Received/ Sent To Lab</th> -->
                        <th class="sum"><?php echo _("Samples Currently Registered at VL Lab");?><br><?php echo _("(Results not yet available)");?></th>
                        <th class="sum"><?php echo _("Samples with Accepted Results");?></th>
                        <th class="sum"><?php echo _("Samples Rejected");?></th>
                        <th class="sum"><?php echo _("Samples with Invalid or Failed Results");?></th>
                        <th class="sum"><?php echo _("Samples Reordered");?></th>
                        <th class="sum"><?php echo _("Results Printed");?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($tableResult) && count($tableResult) > 0) {
                        foreach ($tableResult as $tableRow) { ?>
                            <tr>
                                <td><?php echo ucwords($tableRow['facility_name']); ?></td>
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
                return;
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
</script>