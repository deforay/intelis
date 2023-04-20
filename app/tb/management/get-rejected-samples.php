<?php

use App\Models\Facilities;
use App\Models\General;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
ob_start();
  


$general = new General();

$facilitiesDb = new Facilities();
$facilityMap = $facilitiesDb->getUserFacilityMap($_SESSION['userId']);

$formId = $general->getGlobalConfig('vl_form');

$tResult = array();
//$rjResult = array();

    //get value by rejection reason id
    $vlQuery = "select count(*) as `total`, vl.reason_for_sample_rejection,sr.rejection_reason_name,sr.rejection_type,sr.rejection_reason_code,fd.facility_name, lab.facility_name as `labname`
                FROM form_tb as vl
                INNER JOIN r_tb_sample_rejection_reasons as sr ON sr.rejection_reason_id=vl.reason_for_sample_rejection
                INNER JOIN facility_details as fd ON fd.facility_id=vl.facility_id
                INNER JOIN facility_details as lab ON lab.facility_id=vl.lab_id";
    $sWhere[]= ' vl.is_sample_rejected = "yes" AND reason_for_sample_rejection!="" AND reason_for_sample_rejection IS NOT NULL';
    if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
        $start_date = '';
        $end_date = '';
        $sWhere = array();
        $s_c_date = explode("to", $_POST['sampleCollectionDate']);
        //print_r($s_c_date);die;
        if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
            $start_date = DateUtils::isoDateFormat(trim($s_c_date[0]));
        }
        if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
            $end_date = DateUtils::isoDateFormat(trim($s_c_date[1]));
        }
        $sWhere[]= ' DATE(vl.sample_collection_date) <= "' . $end_date . '" AND DATE(vl.sample_collection_date) >= "' . $start_date . '"';
    }
    if (isset($_POST['sampleType']) && trim($_POST['sampleType']) != '') {
        $sWhere[]= ' s.sample_id = "' . $_POST['sampleType'] . '"';
    }
    if (isset($_POST['labName']) && trim($_POST['labName']) != '') {
        $sWhere[]= ' vl.lab_id = "' . $_POST['labName'] . '"';
    }
    if (isset($_POST['clinicName']) && is_array($_POST['clinicName']) && count($_POST['clinicName']) > 0) {
        $sWhere[]= " vl.facility_id IN (" . implode(',', $_POST['clinicName']) . ")";
    }
    if (!empty($facilityMap)) {
        $sWhere[]= " vl.facility_id IN ($facilityMap)";
    }

    if(isset($sWhere) && count($sWhere) > 0)
    {
        $sWhere = " where ". implode(' AND ',$sWhere);
    }
    else
    {
        $sWhere="";
    }

    $vlQuery = $vlQuery . $sWhere . " group by vl.reason_for_sample_rejection,vl.lab_id,vl.facility_id";
    //vl.vlsm_country_id = "' . $formId . '" AND
    $_SESSION['rejectedSamples'] = $vlQuery;
    $tableResult = $db->rawQuery($vlQuery);

    foreach ($tableResult as $tableRow) {
        if (!isset($tResult[$tableRow['rejection_reason_name']])) {
            $tResult[$tableRow['rejection_reason_name']] = array('total' => null, 'category' => null);
        }
        $tResult[$tableRow['rejection_reason_name']]['total'] += $tableRow['total'];
        $tResult[$tableRow['rejection_reason_name']]['category'] = $tableRow['rejection_type'];

        //$rjResult[$tableRow['rejection_type']]  += $tableRow['total'];
    }


if (isset($tResult) && count($tResult) > 0) {
?>
    <div id="container" style="width: 100%; height: 500px; margin: 20px auto;"></div>
    <!-- <div id="rejectedType" style="width: 100%; height: 400px; margin: 20px auto;margin-top:50px;"></div> -->
<?php }
if (isset($tableResult) && count($tableResult) > 0) { ?>
    <div class="pull-right">
        <button class="btn btn-success" type="button" onclick="exportInexcel()"><em class="fa-solid fa-cloud-arrow-down"></em> <?php echo _("Export Excel");?></button>
    </div>
<?php } ?>
<table id="tbRequestDataTable" class="table table-bordered table-striped table-hover">
    <thead>
        <tr>
            <th><?php echo _("Lab Name");?></th>
            <th><?php echo _("Facility Name");?></th>
            <th><?php echo _("Rejection Reason");?></th>
            <th><?php echo _("Reason Category");?></th>
            <th><?php echo _("No. of Samples");?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        if (isset($tableResult) && count($tableResult) > 0) {
            foreach ($tableResult as $tableRow) {
        ?>
                <tr>
                    <td><?php echo ($tableRow['labname']); ?></td>
                    <td><?php echo ($tableRow['facility_name']); ?></td>
                    <td><?php echo ($tableRow['rejection_reason_name']); ?></td>
                    <td><?php echo strtoupper($tableRow['rejection_type']); ?></td>
                    <td><?php echo $tableRow['total']; ?></td>
                </tr>
        <?php
            }
        }
        ?>
    </tbody>
</table>
<script>
    $(function() {
        $("#tbRequestDataTable").DataTable();
    });
    <?php
    if (isset($tResult) && count($tResult) > 0) { ?>
        $('#container').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?php echo _("Sample Rejection Reasons");?>"
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: '{point.number}: <strong>{point.y}</strong>'
            },
            plotOptions: {
                pie: {
                    allowPointSelect: true,
                    cursor: 'pointer',
                    dataLabels: {
                        enabled: true,
                        format: '<strong>{point.name}</strong>: {point.y}',
                        style: {
                            color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                        }
                    }
                }
            },
            series: [{
                colorByPoint: true,
                point: {
                    events: {
                        click: function(e) {
                            e.preventDefault();
                        }
                    }
                },
                data: [
                    <?php
                    foreach ($tResult as $reasonName => $values) {
                    ?> {
                            name: '<?php echo $reasonName; ?>',
                            y: <?php echo ($values['total']); ?>,
                            number: '<?php echo ($values['category']); ?>'
                        },
                    <?php
                    }
                    ?>
                ]
            }]
        });
    <?php }

    if (isset($rjResult) && count($rjResult) > 0) { ?>
        $('#rejectedType').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?php echo _("Sample Rejection by Categories");?>"
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: '{point.name}: <strong>{point.y}</strong>'
            },
            plotOptions: {
                pie: {
                    allowPointSelect: true,
                    cursor: 'pointer',
                    dataLabels: {
                        enabled: true,
                        format: '<strong>{point.name}</strong>: {point.y}',
                        style: {
                            color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                        }
                    }
                }
            },
            series: [{
                colorByPoint: true,
                point: {
                    events: {
                        click: function(e) {
                            e.preventDefault();
                        }
                    }
                },
                data: [
                    <?php
                    foreach ($rjResult as $key => $total) {
                    ?> {
                            name: '<?php echo ($key); ?>',
                            y: <?php echo ($total); ?>
                        },
                    <?php
                    }
                    ?>
                ]
            }]
        });
    <?php } ?>
</script>