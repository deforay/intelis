<?php
ob_start();
include('../includes/MysqliDb.php');
include('../General.php');
$general=new Deforay_Commons_General();
$configFormQuery="SELECT * FROM global_config WHERE name ='vl_form'";
$configFormResult = $db->rawQuery($configFormQuery);
//date
$start_date = '';
$end_date = '';
if(isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate'])!= ''){
   $s_c_date = explode("to", $_POST['sampleTestDate']);
   //print_r($s_c_date);die;
   if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
     $start_date = $general->dateFormat(trim($s_c_date[0]));
   }
   if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
     $end_date = $general->dateFormat(trim($s_c_date[1]));
   }
}

$tsQuery="SELECT DATE_FORMAT(sample_tested_datetime,'%d-%M-%Y') AS sample_tested_datetime,

		SUM(CASE
			WHEN (result_value_absolute != '' AND result_value_absolute IS NOT NULL) THEN result_value_absolute
		             ELSE 0
		           END) AS sumTotal,
        COUNT(CASE
			WHEN (result_value_absolute != '' AND result_value_absolute IS NOT NULL) THEN 1
		             ELSE 0
		           END) AS countTotal
        FROM vl_imported_controls as vl where vlsm_country_id='".$configFormResult[0]['value']."'";

	$sWhere = '';
   if(isset($_POST['cType']) && trim($_POST['cType'])!= ''){
      $sWhere.= ' AND vl.control_type = "'.$_POST['cType'].'"';
   }
   if(isset($_POST['sampleTestDate']) && trim($_POST['sampleTestDate'])!= ''){
      $sWhere.= ' AND DATE(vl.sample_tested_datetime) >= "'.$start_date.'" AND DATE(vl.sample_tested_datetime) <= "'.$end_date.'"';
   }
	$tsQuery = $tsQuery.' '.$sWhere." group by DATE_FORMAT(sample_tested_datetime,'%d-%M-%Y')";
	$totalControlResult = $db->rawQuery($tsQuery);

	$sQuery="SELECT SUM(CASE WHEN (result_value_absolute != '' AND result_value_absolute IS NOT NULL) THEN result_value_absolute ELSE 0 END) AS sumTotal FROM vl_imported_controls as vl where vlsm_country_id='".$configFormResult[0]['value']."'";
	$sQuery = $sQuery.' '.$sWhere."  group by control_id";
	$controlResult = $db->rawQuery($sQuery);
	$array = array_map('current', $controlResult);
	$mean = (isset($array) && count($array) > 0) ?  (array_sum($array)/count($array)) : 0;
	function sd_square($x, $mean) { return pow($x - $mean,2); }
	$sd = (isset($array) && count($array) > 0) ?  (sqrt(array_sum(array_map("sd_square", $array, array_fill(0,count($array), (array_sum($array) / count($array)) ) ) ) / (count($array)-1) )) : 0;
	?>
<div id="container" style="height: 400px"></div>
<script>
	$(function () {
	Highcharts.chart('container', {
    title: {
        text: 'Control Result'
    },
	subtitle: {
        text: 'Mean:<?php echo round($mean,2);?>   SD:<?php echo round($sd,2);?>'
    },
    xAxis: {
        categories: [<?php
			foreach($totalControlResult as $category){
				echo "'".$category['sample_tested_datetime']."',";
			}
			?>]
    },

	 yAxis: {
		min: <?php echo round($mean - ($sd*3),2);?>, max: <?php echo round($mean + ($sd*3),2);?>,
            title: {
                text: 'Control Result'
            },
				
            plotLines: [{
                value: <?php echo round($mean + ($sd*3),2);?>,
                color: 'green',
                dashStyle: 'shortdash',
                width: 2,
                label: {
                    text: <?php echo round($mean + ($sd*3),2);?>
                }
					},
					{
					value: <?php echo round($mean + ($sd*2),2);?>,
                color: 'orange',
                dashStyle: 'shortdash',
                width: 2,
                label: {
                    text: <?php echo round($mean + ($sd*2),2);?>
                }
					},
					{
					value: <?php echo round($mean + ($sd),2);?>,
                color: 'red',
                dashStyle: 'shortdash',
                width: 2,
                label: {
                    text: <?php echo round($mean + ($sd),2);?>
                }
					}, {
                value: '<?php echo round($mean);?>',
                color: 'yellow',
                dashStyle: 'shortdash',
                width: 2,
                label: {
                    text: <?php echo round($mean);?>
                }
            },{
                value: '<?php echo round($mean - ($sd),2);?>',
                color: 'red',
                dashStyle: 'shortdash',
                width: 2,
                label: {
                    text: <?php echo round($mean - ($sd),2);?>
                }
            },{
                value: '<?php echo round($mean - ($sd*2),2);?>',
                color: 'orange',
                dashStyle: 'shortdash',
                width: 2,
                label: {
                    text: <?php echo round($mean - ($sd*2),2);?>
                }
            },{
                value: '<?php echo round($mean - ($sd*3),2);?>',
                color: 'green',
                dashStyle: 'shortdash',
                width: 2,
                label: {
                    text: <?php echo round($mean - ($sd*3),2);?>
                }
            }]
        },
    

    series: [{
        data: [<?php
			foreach($totalControlResult as $total){
				echo round($total['sumTotal']/$total['countTotal'],2).",";
			}
			?>]
    }]
});
            });
</script>

