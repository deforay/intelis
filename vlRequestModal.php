<?php
include('./includes/MysqliDb.php');
$sQuery="SELECT * FROM r_sample_type";
$sResult = $db->rawQuery($sQuery);
$fQuery="SELECT * FROM facility_details where status='active'";
$fResult = $db->rawQuery($fQuery);
?>
  <link rel="stylesheet" media="all" type="text/css" href="assets/css/jquery-ui.1.11.0.css" />
  <!-- Bootstrap 3.3.6 -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="assets/css/font-awesome.min.4.5.0.css">
   <!-- DataTables -->
  <link rel="stylesheet" href="./assets/plugins/datatables/dataTables.bootstrap.css">
  <link href="assets/plugins/daterangepicker/daterangepicker.css" rel="stylesheet" /> 
   <style>
    .content-wrapper{
        padding:2%;
    }
	.center{text-align:center;}
   </style> 
  <script type="text/javascript" src="assets/js/jquery.min.2.0.2.js"></script>
  <script type="text/javascript" src="assets/js/jquery-ui.1.11.0.js"></script>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h3 style="margin:0;">Search Patients</h3>
    </section>
     <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-xs-12">
          <div class="box">
	    <table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:10px;width: 98%;">
		<tr>
		    <td style=""><b>Sample Collection Date&nbsp;:</b></td>
		    <td>
		      <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="width:220px;background:#fff;"/>
		    </td>
		    <td>&nbsp;<b>Batch Code&nbsp;:</b></td>
		    <td>
			<input type="text" id="batchCode" name="batchCode" class="form-control" placeholder="Enter Batch Code"/>
		    </td>
		    </tr>
		<tr>
		    <td>&nbsp;<b>Sample Type&nbsp;:</b></td>
		    <td>
		      <select style="width:220px;" class="form-control" id="sampleType" name="sampleType" title="Please select sample type">
		      <option value="">-- Select --</option>
			<?php
			foreach($sResult as $type){
			 ?>
			 <option value="<?php echo $type['sample_id'];?>"><?php echo ucwords($type['sample_name']);?></option>
			 <?php
			}
			?>
		      </select>
		    </td>
		
		    <td>&nbsp;<b>Facility Name & Code&nbsp;:</b></td>
		    <td>
		      <select class="form-control" id="facilityName" name="facilityName" title="Please select facility name">
		      <option value="">-- Select --</option>
			<?php
			foreach($fResult as $name){
			 ?>
			 <option value="<?php echo $name['facility_id'];?>"><?php echo ucwords($name['facility_name']."-".$name['facility_code']);?></option>
			 <?php
			}
			?>
		      </select>
		    </td>
		</tr>
		<tr>
		  <td colspan="3">&nbsp;<input type="button" onclick="searchVlRequestData();" value="Search" class="btn btn-success btn-sm">
		    &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
		    
		    </td>
		</tr>
		
	    </table>
            <!-- /.box-header -->
            <div class="box-body">
              <table id="vlRequestDataTable" class="table table-bordered table-striped">
                <thead>
                <tr>
		  <th>Select</th>
		  <th>Sample Code</th>
                  <th>Sample Collection Date</th>
                  <th>Batch Code</th>
                  <th>Unique ART No</th>
                  <th>Patient's Name</th>
		  <th>Facility Name</th>
                  <th>Sample Type</th>
                </tr>
                </thead>
                <tbody>
                  <tr>
                    <td colspan="8" class="dataTables_empty">Loading data from server</td>
                </tr>
                </tbody>
              </table>
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
    <!-- Bootstrap 3.3.6 -->
  <script src="assets/js/bootstrap.min.js"></script>
  <!-- DataTables -->
  <script src="./assets/plugins/datatables/jquery.dataTables.min.js"></script>
  <script src="./assets/plugins/datatables/dataTables.bootstrap.min.js"></script>
  <script type="text/javascript" src="assets/plugins/daterangepicker/moment.min.js"></script>
  <script type="text/javascript" src="assets/plugins/daterangepicker/daterangepicker.js"></script>
  <script type="text/javascript">
   var startDate = "";
   var endDate = "";
  $(document).ready(function() {
     $('#sampleCollectionDate').daterangepicker({
            format: 'DD-MMM-YYYY',
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
     $('#sampleCollectionDate').val("");
     loadVlRequestData();
  } );
  
  var oTable = null;
  function loadVlRequestData(){
     oTable = $('#vlRequestDataTable').dataTable({
            "oLanguage": {
                "sLengthMenu": "_MENU_ records per page"
            },
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "bScrollCollapse": true,
            
            "bRetrieve": true,                        
            "aoColumns": [
		{"sClass":"center","bSortable":false},
                {"sClass":"center"},
                {"sClass":"center"},
                {"sClass":"center"},
                {"sClass":"center"},
                {"sClass":"center"},
                {"sClass":"center"},
                {"sClass":"center"}
            ],
            "aaSorting": [[ 2, "desc" ]],
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "getVlRequestModalDetails.php",
            "fnServerData": function ( sSource, aoData, fnCallback ) {
	      aoData.push({"name": "batchCode", "value": $("#batchCode").val()});
	      aoData.push({"name": "sampleCollectionDate", "value": $("#sampleCollectionDate").val()});
	      aoData.push({"name": "facilityName", "value": $("#facilityName").val()});
	      aoData.push({"name": "sampleType", "value": $("#sampleType").val()});
              $.ajax({
                  "dataType": 'json',
                  "type": "POST",
                  "url": sSource,
                  "data": aoData,
                  "success": fnCallback
              });
            }
        });
  }
  
  function searchVlRequestData(){
    oTable.fnDraw();
  }
  
  function getPatient(ptDetails){
    parent.closeModal();
    window.parent.setPatientDetails(ptDetails);
  }
</script>
