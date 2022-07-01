<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.css" />
<?php
$title = _("Audit Trail");
require_once(APPLICATION_PATH . '/header.php');

if(isset($_POST['testType']))
{
	$table_name = $_POST['testType'];
	$sample_code =$_POST['sampleCode'];
}
else {
	$table_name="audit_form_vl";
	$sample_code="VL062288148";
}
$columns_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'vlsm' AND table_name='$table_name'";
$col_result = $db->rawQuery($columns_sql);
function getDifference($arr1,$arr2)
{
    $diff = array_merge(array_diff_assoc($arr1,$arr2),array_diff_assoc($arr2,$arr1));
    return $diff;
}
?>
<style>
	.select2-selection__choice {
		color: black !important;
	}

	th {
		display: revert !important;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><i class="fa-solid fa-pen-to-square"></i> <?php echo _("Audit Trail"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa-solid fa-chart-pie"></i> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Audit Trail"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
				<form name="form1" action="audit-trail.php" method="post" id="searchForm">
					<table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:98%;">
						<tr>
							<td><b><?php echo _("Test Type"); ?>*&nbsp;:</b></td>
							<td>
							<select style="width:220px;" class="form-control" id="testType" name="testType" title="<?php echo _('Type of Test'); ?>">
							<option value="">--Choose Test Type--</option>
							<option <?php if(isset($_POST['testType']) && $_POST['testType']=="audit_form_vl") echo "selected='selected'"; ?> value="audit_form_vl">VL</option>
							<option <?php if(isset($_POST['testType']) && $_POST['testType']=="audit_form_eid") echo "selected='selected'"; ?> value="audit_form_eid">EID</option>
							<option <?php if(isset($_POST['testType']) && $_POST['testType']=="audit_form_covid19") echo "selected='selected'"; ?> value="audit_form_covid19">Covid-19</option>
							<option <?php if(isset($_POST['testType']) && $_POST['testType']=="audit_form_hepatitis") echo "selected='selected'"; ?> value="audit_form_hepatitis">Hepatitis</option>
							<option <?php if(isset($_POST['testType']) && $_POST['testType']=="audit_form_tb") echo "selected='selected'"; ?> value="audit_form_tb">TB</option>
							</select>
							</td>
							<td><b><?php echo _("Sample Code"); ?>&nbsp;:</b></td>
							<td>
								<input type="text" value="<?php if(isset($_POST['sampleCode'])) echo $_POST['sampleCode']; else echo ""; ?>" name="sampleCode" id="sampleCode" class="form-control" />
								<!--<select style="width:220px;" class="form-control" id="sampleCode" name="sampleCode" title="<?php echo _('Please select the Sample code'); ?>">
								</select>-->
							</td>
						</tr>
						<tr>
							<td></td>
							<td style=" display: contents; ">
								<button type="submit" value="Submit" class="btn btn-primary btn-sm"><span><?php echo _("Submit"); ?></span></button>
								<a href="/admin/monitoring/audit-trail.php" class="btn btn-danger btn-sm" style=" margin-left: 15px; "><span><?php echo _("Clear"); ?></span></button>
							</td>
						</tr>
					</table>
</form>
					<!-- /.box-header -->
					<div class="box-body">
					<table>
  <thead>
    <tr>
      <?php
      $columns_sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'vlsm' AND table_name='$table_name'";
      
      $result_column = $db->rawQuery($columns_sql);
      $col_arr = array();
     foreach($result_column as $col)
      {
        $col_arr[] = $col['COLUMN_NAME'];
        ?>
        <th>
          <?php //echo ucwords(str_replace('_',' ',$col['COLUMN_NAME'])); 
          echo $col['COLUMN_NAME'];
          ?>
          <?php } ?>
        </th>
    </tr>
  </thead>
  <tbody>

              <?php
              if(isset($_POST['sample_code']))
              		$code=$_POST['sample_code'];
              	else
              		$code="";

              $sql = "SELECT a.*, modifier.user_name as last_modified_by, creator.user_name as req_created_by from $table_name as a 
			  LEFT JOIN user_details as creator ON a.request_created_by = creator.user_id LEFT JOIN user_details as modifier ON a.last_modified_by = modifier.user_id 
			  WHERE sample_code = '$sample_code' OR remote_sample_code = '$sample_code'";
              $result = $db->rawQuery($sql);

              $posts=array();
              
              foreach($result as $row) {
                
                $posts[] = $row;
                
              }
            

         //  echo 'valu-'.count($posts);
            //echo '<pre>'; print_r($posts); die();
            for($i=0;$i<count($posts);$i++)
            {
              $k = ($i-1);
              $arr_diff = getDifference($posts[$i],$posts[$k]);
               
            ?>
            <tr>
            <?php
          
            for($j=0;$j<count($col_arr);$j++)
            {
            ?>
                  <td class="compare_col-<?php echo $i.'-'.$j; ?>">
                    <?php 
                     //  echo $i.'--'.($i-1);
                    if($i>0)
                    {
                     
                          if(!empty($arr_diff[$col_arr[$j]]) && $arr_diff[$col_arr[$j]]!=$posts[$i][$col_arr[$j]] && !empty($posts[$i][$col_arr[$j]]))
                          {
                          echo '<style type="text/css">
                            .compare_col-'.$i.'-'.$j.' {
                              background: orange;
                              color:black;
                            }
                            </style>';
                          }
                          else
                        {
                          echo '<style type="text/css">
                            .compare_col-'.$i.'-'.$j.' {
                              background: white;
                              color:black;
                            }
                            </style>';
                        }
                        
                  }                   
                    
                   echo $posts[$i][$col_arr[$j]];
                    ?>
                  </td>
            <?php }
            ?>
  </tr>
  <?php
}
  ?>
   
  </tbody>

</table>
					</div>
				</div>
				<!-- /.box -->
			</div>
			<!-- /.col -->
		</div>
		<!-- /.row -->
	</section>
	<!-- /.content -->
</div>
<script type="text/javascript" src="/assets/plugins/daterangepicker/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
/*	function getSampleCodeList(samplecode)
	{
		//loadVlRequestData();
		testType=$("#testType").val();
		$.post({
			type: "POST",
			url: '/admin/monitoring/get-sample-code-list.php',
			data: {table: testType, type:samplecode},
			success: function(data){
				obj = $.parseJSON(data);
				$("#sampleCode").html('');
				$.each( obj, function( key, value ) {
					$("#sampleCode").append("<option value="+value.sample_code+">"+value.sample_code+"</option>") ;
				});
			}
		});
	}*/


	/*	$("#sampleCode").autocomplete({
  source: function(request, response) {
    $.getJSON("get-sample-code-list.php", { testType: $('#testType').val(), code : $('#sampleCode').val() }, 
              response);
  },
  minLength: 2,
  select: function(event, ui){
    alert('hi');
  }
});
*/

	//	getSampleCodeList($("#testType").val());
		/*$("#sampleCode").select2({
			placeholder: "<?php echo _("Select Sample Code"); ?>"
		});
*/
	

	
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
?>
<style>
.box-body
{
	overflow:scroll;
}
.box-body td, .box-body th {
  border: 1px solid #999;
  padding: 20px;
}
 td{
  background: white;
}
.primary{
  background-color: brown;
  position: sticky;
}
.box-body > th {
  background: white;
  font-size:20px;
  color: black;
  border-radius: 0;
  top: 0;
  padding: 10px;
}
.box-body > tbody > tr:hover {
  background-color: #ffc107;
}

</style>
