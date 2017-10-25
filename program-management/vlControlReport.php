<?php
$title = "VLSM | VL Control Report";
include('../header.php');
$sQuery="SELECT * FROM r_sample_controls where r_sample_control_name!='s'";
$sResult = $db->rawQuery($sQuery);
?>
<style>
  .select2-selection__choice{
    color:#000000 !important;
  }
  .center{
    text-align:center;
  }
</style>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1><i class="fa fa-edit"></i> VL Control Report</h1>
      <ol class="breadcrumb">
        <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">VL Control Report</li>
      </ol>
    </section>
     <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-xs-12">
          <div class="box">
						<table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:80%;">
							<tr>
								<td><b>Sample Tested Date&nbsp;</b><span class="mandatory">*</span></td><td><input type="text" id="sampleTestDate" name="sampleTestDate" class="form-control" placeholder="Select Tested Date" readonly style="width:220px;background:#fff;"/></td>
								<td><b>Control Type&nbsp;</b><span class="mandatory">*</span></td>
								<td>
									<select id="cType" name="cType" class="form-control" title="Choose control type">
										<option value="">-- Select --</option>
										<?php
										foreach($sResult as $control)
										{
											?>
											<option value="<?php echo $control['r_sample_control_name'];?>"><?php echo $control['r_sample_control_name'];?></option>
											<?php
										}
										?>
									</select>
								</td>
							</tr>
							<tr>
								<td colspan="6">&nbsp;<input type="button" onclick="loadControlChart();" value="Search" class="btn btn-success btn-sm">
								&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>		    
								</td>
							</tr>
						</table>
            <!-- /.box-header -->
            <div class="box-body" style="margin-top:-30px;" id="chart">
              
            </div>
            <!-- /.box-body -->
          </div>
          <!-- /.box -->
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
    </section>
    <!-- /.content -->
  </div>
  <script type="text/javascript" src="../assets/plugins/daterangepicker/moment.min.js"></script>
  <script type="text/javascript" src="../assets/plugins/daterangepicker/daterangepicker.js"></script>
	<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="http://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
  <script type="text/javascript">
   var startDate = "";
   var endDate = "";
  $(document).ready(function() {
     $('#sampleTestDate').daterangepicker({
            format: 'DD-MMM-YYYY',
						separator: ' to ',
            startDate: moment().subtract('days', 29),
            endDate: moment(),
            maxDate: moment(),
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract('days', 1), moment().subtract('days', 1)],
                'Last 7 Days': [moment().subtract('days', 6), moment()],
                'Last 30 Days': [moment().subtract('days', 29), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract('month', 1).startOf('month'), moment().subtract('month', 1).endOf('month')]
            }
        },
        function(start, end) {
            startDate = start.format('YYYY-MM-DD');
            endDate = end.format('YYYY-MM-DD');
      });
     $('#sampleTestDate').val("");
		 //loadControlChart();
  });

  function loadControlChart(){
		if($("#sampleTestDate").val()!='' && $("#cType").val()!=''){
			$.blockUI();
			$.post("../program-management/getControlChart.php",{sampleTestDate:$("#sampleTestDate").val(),cType:$("#cType").val()},
				function(data){
					$("#chart").html(data);
				});
			$.unblockUI();
		}else{
			alert("Please choose Sample Test Date Range and Control Type to generate the report");
		}
  }
</script>
 <?php
 include('../footer.php');
 ?>