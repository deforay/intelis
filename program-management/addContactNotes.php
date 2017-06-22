  <link rel="stylesheet" media="all" type="text/css" href="../assets/css/jquery-ui.1.11.0.css" />
  <!-- Bootstrap 3.3.6 -->
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="../assets/css/font-awesome.min.4.5.0.css">
   <!-- DataTables -->
  <link rel="stylesheet" href=".././assets/plugins/datatables/dataTables.bootstrap.css">
  <link href="../assets/css/deforayModal.css" rel="stylesheet" />
  <script type="text/javascript" src="../assets/js/jquery.min.2.0.2.js"></script>
  <script type="text/javascript" src="../assets/js/jquery-ui.1.11.0.js"></script>
  <script src="../assets/js/deforayModal.js"></script>
<?php
ob_start();
include('../includes/MysqliDb.php');
include('../General.php');
$id=base64_decode($_GET['id']);
$general=new Deforay_Commons_General();
$contactInfo="SELECT * from contact_notes_details where treament_contact_id=$id";
$contact=$db->query($contactInfo);
//get patient info
$vlInfo="SELECT sample_code,patient_first_name,patient_last_name,patient_art_no,sample_collection_date from vl_request_form where vl_sample_id=$id";
$vlResult=$db->query($vlInfo);

if(isset($vlResult[0]['sample_collection_date']) && trim($vlResult[0]['sample_collection_date'])!= '' && $vlResult[0]['sample_collection_date']!= '0000-00-00 00:00:00'){
        $xplodDate = explode(" ",$vlResult[0]['sample_collection_date']);
        $vlResult[0]['sample_collection_date'] = $general->humanDateFormat($xplodDate[0]);
    }else{
        $vlResult[0]['sample_collection_date'] = '';
    }
$general=new Deforay_Commons_General();
?>
<div class="content-wrapper" style="padding: 20px;">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h4>Add Contact Notes</h4>
    </section>

    <!-- Main content -->
    <section class="content">
      <!-- SELECT2 EXAMPLE -->
      <div class="box box-default">
        <div class="box-header with-border">
          <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
        </div>
        <!-- /.box-header -->
        <div class="box-body">
          <!-- form start -->
            <div class="form-horizontal" id="contactNotes">
              <div class="box-body">
                  <table class="table">
                    <tr>
                      <td><b>Sample Code:<small><?php echo $vlResult[0]['sample_code'];?></small></b></td>
                      <td><b>Contacted Date:<small><?php echo $vlResult[0]['sample_collection_date'] ;?></small></b></td>
                      <td><b>Patient Name:<small><?php echo $vlResult[0]['patient_first_name']." ".$vlResult[0]['patient_last_name'];?></small></b></td>
                      <td><b>Patient Code:<small><?php echo $vlResult[0]['patient_art_no'];?></small></b></td>
                    </tr>
                  </table>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="address" class="col-lg-4 control-label">Contact Notes<span class="mandatory">*</span></label>
                        <div class="col-lg-7">
                        <textarea class="form-control isRequired" name="notes" id="notes" title="Please enter contact notes" placeholder="Enter Contact Notes"></textarea>
                        <input type="hidden" name="treamentId" id="treamentId" value="<?php echo $id;?>"/>
                        </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                        <label for="address" class="col-lg-4 control-label">Contacted On<span class="mandatory">*</span></label>
                        <div class="col-lg-7">
                        <input type="text" class="form-control date readonly" readonly='readonly' id="date" name="date" placeholder="Contacted On" title="Enter Contacted on"/>
                        </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="box-footer">
                <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
                <a href="javascript:void(0)" class="btn btn-default"  onclick="window.parent.closeModal()"> Cancel</a>
              </div>
            </div>
            <hr/>
            
            <!--histroy of contact notes-->
            <div class="col-md-12"><h4><a id="history" href="javascript:void(0);" style="text-decoration: none;" onclick="formToggler('+');">Show History <i class="fa fa-plus"></i></a></h4></div>
                  <div class="row" id="showHistory" style="display: none;">
                    <div class="col-xs-12">
                      <div class="box">
                        <!-- /.box-header -->
                        <div class="box-body">
                            <h3>Contact Notes History</h3>
                          <table id="contactNotesDetails" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                              <th>Contact Notes</th>
                              <th>Contacted On</th>
                              <th>Added On</th>
                            </tr>
                            </thead>
                            <tbody>
                                <?php
                                if(count($contact)>0){
                                    foreach($contact as $notes){
                                        $date = explode(" ",$notes['added_on']);
                                        $collectDate = $general->humanDateFormat($notes['collected_on']);
                                        $humanDate = $general->humanDateFormat($date[0]);
                                        ?>
                                        <tr>
                                        <td><?php echo $notes['contact_notes'];?></td>
                                        <td><?php echo $collectDate;?></td>
                                        <td><?php echo $humanDate." ".$date[1];?></td>
                                        </tr>
                                        <?php
                                    }
                                }else{
                                ?>
                                <tr>
                                    <td colspan="3" class="dataTables_empty">Loading data from server</td>
                                </tr>
                              <?php } ?>
                            </tbody>
                          </table>
                        </div>
                        <!-- /.box-body -->
                      </div>
                      <!-- /.box -->
                    </div>
                    <!-- /.col -->
                  </div>
        </div>
      </div>
    </section>
</div>
<script src="assets/js/bootstrap.min.js"></script>
  <!-- DataTables -->
  <script src=".././assets/plugins/datatables/jquery.dataTables.min.js"></script>
  <script src=".././assets/plugins/datatables/dataTables.bootstrap.min.js"></script>
 <script type="text/javascript">
var oTable = null;
  $(document).ready(function() {
    $('.date').datepicker({
      changeMonth: true,
      changeYear: true,
      dateFormat: 'dd-M-yy',
      timeFormat: "hh:mm TT",
      yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
     });
    <?php if(count($contact)>0){ ?>
      oTable = $('#contactNotesDetails').dataTable({"aaSorting": [[ 1, "desc" ]]});
     <?php } ?>
  });
  
  function validateNow(){
    notes = $("#notes").val();
    dateVal = $("#date").val();
    if(notes!='' && dateVal!=''){
      $.post("addContactNotesHelper.php", {
	  notes: $("#notes").val(),dateVal: $("#date").val(),treamentId: $("#treamentId").val(),
	},
      function(data){
	  if(data>0){
              alert("Notes Added Successfully");
              $("#notes").val("");
              $("#date").val("");
              window.location.reload();
	  }
      });
    }else{
        alert("Please enter Notes and Date");
    }
  }
  
  function formToggler(symbol){
      if(symbol == "+"){
          $("#showHistory").slideToggle();
          $("#history").html('Hide History <i class="fa fa-minus"></i>');
          $("#history").attr("onclick", "formToggler('-')");
      }else{
        $("#showHistory").slideToggle();
        $("#history").html('Show History <i class="fa fa-plus"></i>');
        $("#history").attr("onclick", "formToggler('-')");
      }
  }
</script>
