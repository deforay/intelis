<?php
$title = "EID | Sample Rejection Report";
#require_once('../../startup.php'); 
include_once(APPLICATION_PATH.'/header.php');

$tsQuery="SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);

$facilitiesDb = new \Vlsm\Models\Facilities($db);
$facilityList = $facilitiesDb->getAllFacilities('grouped');
$lResult = $facilityList['labs'];
$cResult = $facilityList['facilities'];
?>
  <style>
    .select2-selection__choice{
      color:black !important;
    }
  </style>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1><i class="fa fa-book"></i> Sample Rejection Report</h1>
      <ol class="breadcrumb">
        <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Rejection Result</li>
      </ol>
    </section>

     <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-xs-12">
          <div class="box">
	    <table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:98%;">
		<tr>
		    <td><b>Sample Collection Date&nbsp;:</b></td>
		    <td>
		      <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="width:220px;background:#fff;"/>
		    </td>
		    <td>&nbsp;<b>Lab &nbsp;:</b></td>
		    <td>
		      <select class="form-control" id="labName" name="labName" title="Please select lab name" style="width:220px;">
		         <option value=""> -- Select -- </option>
				  <?php
				  foreach($lResult as $name){
				  ?>
					<option value="<?php echo $name['facility_id'];?>"><?php echo ucwords($name['facility_name']);?></option>
				  <?php
				  }
				  ?>
		      </select>
		    </td>
		</tr>
		<tr>
		
		    <td>&nbsp;<b>Clinic Name &nbsp;:</b></td>
		    <td>
			  <select class="form-control" id="clinicName" name="clinicName" title="Please select clinic name" multiple="multiple" style="width:220px;">
		         <option value=""> -- Select -- </option>
				  <?php
				  foreach($cResult as $name){
				  ?>
					<option value="<?php echo $name['facility_id'];?>"><?php echo ucwords($name['facility_name']);?></option>
				  <?php
				  }
				  ?>
		      </select>
		    </td>
            <td></td>
		    <td></td>
				    
		</tr>
		<tr>
		  <td colspan="4">&nbsp;<input type="button" onclick="searchResultData();" value="Search" class="btn btn-success btn-sm">
		    &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
		  </td>
		</tr>
		
	    </table>
            <!-- /.box-header -->
            <div class="box-body" id="pieChartDiv">
              
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
  <script type="text/javascript" src="/assets/plugins/daterangepicker/moment.min.js"></script>
  <script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
  <script src="/assets/js/highcharts.js"></script>
  <script>
  $(function () {
    $("#clinicName").select2({placeholder:"Select Clinics"});
    $('#sampleCollectionDate').daterangepicker({
            format: 'DD-MMM-YYYY',
	    separator: ' to ',
            startDate: moment().subtract('days', 365),
            endDate: moment(),
            maxDate: moment(),
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1,'days'), moment().subtract(1,'days')],
                'Last 7 Days': [moment().subtract(6,'days'), moment()],
                'Last 30 Days': [moment().subtract(29,'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1,'month').startOf('month'), moment().subtract(1,'month').endOf('month')]
            }
        },
        function(start, end) {
            startDate = start.format('YYYY-MM-DD');
            endDate = end.format('YYYY-MM-DD');
      });
     searchResultData();
  });
  function searchResultData()
  {
    $.blockUI();
    $.post("/covid-19/management/get-rejected-samples.php",{sampleCollectionDate:$("#sampleCollectionDate").val(),labName:$("#labName").val(),clinicName:$("#clinicName").val()},
      function(data){
	  if(data!=''){
	    $("#pieChartDiv").html(data);
	  }
      });
    $.unblockUI();
  }
  function exportInexcel() {
    $.blockUI();
    $.post("/covid-19/management/generate-rejected-samples-export.php",{sampleCollectionDate:$("#sampleCollectionDate").val(),lab_name:$("#labName").val(),clinic_name:$("#clinicName").val()},
    function(data){
	  if(data == "" || data == null || data == undefined){
			$.unblockUI();
				alert('Unable to generate excel.');
			}else{
				$.unblockUI();
				location.href = '/temporary/'+data;
			}
    });
  }
</script>
 <?php
 include(APPLICATION_PATH.'/footer.php');
 ?>
