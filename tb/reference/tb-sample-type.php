<?php
$title = "TB Sample Type";
#require_once('../startup.php'); 
include_once(APPLICATION_PATH . '/header.php');

// if($sarr['sc_user_type']=='vluser'){
//   include('../remote/pullDataFromRemote.php');
// }
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><i><svg style=" width: 20px; " aria-hidden="true" focusable="false" data-prefix="fas" data-icon="viruses" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" class="svg-inline--fa fa-viruses fa-w-20">
          <path fill="currentColor" d="M624,352H611.88c-28.51,0-42.79-34.47-22.63-54.63l8.58-8.57a16,16,0,1,0-22.63-22.63l-8.57,8.58C546.47,294.91,512,280.63,512,252.12V240a16,16,0,0,0-32,0v12.12c0,28.51-34.47,42.79-54.63,22.63l-8.57-8.58a16,16,0,0,0-22.63,22.63l8.58,8.57c20.16,20.16,5.88,54.63-22.63,54.63H368a16,16,0,0,0,0,32h12.12c28.51,0,42.79,34.47,22.63,54.63l-8.58,8.57a16,16,0,1,0,22.63,22.63l8.57-8.58c20.16-20.16,54.63-5.88,54.63,22.63V496a16,16,0,0,0,32,0V483.88c0-28.51,34.47-42.79,54.63-22.63l8.57,8.58a16,16,0,1,0,22.63-22.63l-8.58-8.57C569.09,418.47,583.37,384,611.88,384H624a16,16,0,0,0,0-32ZM480,384a32,32,0,1,1,32-32A32,32,0,0,1,480,384ZM346.51,213.33h16.16a21.33,21.33,0,0,0,0-42.66H346.51c-38,0-57.05-46-30.17-72.84l11.43-11.44A21.33,21.33,0,0,0,297.6,56.23L286.17,67.66c-26.88,26.88-72.84,7.85-72.84-30.17V21.33a21.33,21.33,0,0,0-42.66,0V37.49c0,38-46,57.05-72.84,30.17L86.4,56.23A21.33,21.33,0,0,0,56.23,86.39L67.66,97.83c26.88,26.88,7.85,72.84-30.17,72.84H21.33a21.33,21.33,0,0,0,0,42.66H37.49c38,0,57.05,46,30.17,72.84L56.23,297.6A21.33,21.33,0,1,0,86.4,327.77l11.43-11.43c26.88-26.88,72.84-7.85,72.84,30.17v16.16a21.33,21.33,0,0,0,42.66,0V346.51c0-38,46-57.05,72.84-30.17l11.43,11.43a21.33,21.33,0,0,0,30.17-30.17l-11.43-11.43C289.46,259.29,308.49,213.33,346.51,213.33ZM160,192a32,32,0,1,1,32-32A32,32,0,0,1,160,192Zm80,32a16,16,0,1,1,16-16A16,16,0,0,1,240,224Z" class=""></path>
        </svg></i> TB Sample Type</h1>
    <ol class="breadcrumb">
      <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">TB Sample Type</li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;margin-left: 325px;" id="showhide" class="">
            <div class="row" style="background:#e0e0e0;padding: 15px;">
              <div class="col-md-12">
                <div class="col-md-4">
                  <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="0" id="iCol0" data-showhide="facility_code" class="showhideCheckBox" /> <label for="iCol0">Facility Code</label>
                </div>
                <div class="col-md-4">
                  <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="1" id="iCol1" data-showhide="facility_name" class="showhideCheckBox" /> <label for="iCol1">Facility Name</label>
                </div>
                <div class="col-md-4">
                  <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="2" id="iCol2" data-showhide="facility_type" class="showhideCheckBox" /> <label for="iCol2">Facility Type</label>
                </div>
                <div class="col-md-4">
                  <input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="3" id="iCol3" data-showhide="status" class="showhideCheckBox" /> <label for="iCol3">Status</label> <br>
                </div>
              </div>
            </div>
          </span>
          <div class="box-header with-border">
            <?php if (isset($_SESSION['privileges']) && in_array("tb-sample-type.php", $_SESSION['privileges']) && $sarr['sc_user_type'] != 'vluser') { ?>
              <a href="add-tb-sample-type.php" class="btn btn-primary pull-right"> <i class="fa fa-plus"></i> Add TB Sample Type</a>
            <?php } ?>
            <!--<button class="btn btn-primary pull-right" style="margin-right: 1%;" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>-->
          </div>
          <!-- /.box-header -->
          <div class="box-body">
            <table id="samTypDataTable" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Sample Name</th>
                  <th>Sample Status</th>
                  <?php if (isset($_SESSION['privileges']) && in_array("tb-sample-type.php", $_SESSION['privileges']) && $sarr['sc_user_type'] != 'vluser') { ?>
                    <th>Action</th>
                  <?php } ?>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="3" class="dataTables_empty">Loading data from server</td>
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
<script>
  var oTable = null;
  $(function() {
    //$("#example1").DataTable();
  });
  $(document).ready(function() {
    $.blockUI();
    oTable = $('#samTypDataTable').dataTable({
      "oLanguage": {
        "sLengthMenu": "_MENU_ records per page"
      },
      "bJQueryUI": false,
      "bAutoWidth": false,
      "bInfo": true,
      "bScrollCollapse": true,
      "bStateSave": true,
      "bRetrieve": true,
      "aoColumns": [{
          "sClass": "center"
        },
        {
          "sClass": "center"
        },
        <?php if (isset($_SESSION['privileges']) && in_array("tb-sample-type.php", $_SESSION['privileges']) && $sarr['sc_user_type'] != 'vluser') { ?> {
            "sClass": "center",
            "bSortable": false
          },
        <?php } ?>
      ],
      "aaSorting": [
        [0, "asc"]
      ],
      "bProcessing": true,
      "bServerSide": true,
      "sAjaxSource": "getTbSampleTypeDetails.php",
      "fnServerData": function(sSource, aoData, fnCallback) {
        $.ajax({
          "dataType": 'json',
          "type": "POST",
          "url": sSource,
          "data": aoData,
          "success": fnCallback
        });
      }
    });
    $.unblockUI();
  });
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>