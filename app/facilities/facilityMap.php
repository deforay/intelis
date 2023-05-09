<?php
$title = "Facility Map";
 
require_once APPLICATION_PATH . '/header.php';
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-hospital"></em> Facility Map</h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
      <li class="active">Facility Map</li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <div class="box-header with-border">
            <?php if (isset($_SESSION['privileges']) && in_array("addVlFacilityMap.php", $_SESSION['privileges']) && (($_SESSION['instanceType'] == 'remoteuser' || ($sarr['sc_user_type'] == 'standalone')))) { ?>
              <a href="addVlFacilityMap.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> Add Facility Map</a>
            <?php } ?>
            <!--<button class="btn btn-primary pull-right" style="margin-right: 1%;" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>-->
          </div>
          <!-- /.box-header -->
          <div class="box-body">
            <table id="facilityMapDataTable" class="table table-bordered table-striped" aria-hidden="true" >
              <thead>
                <tr>
                  <th>Viral Load Lab</th>
                  <th>Health Centre Name</th>
                  <?php if (isset($_SESSION['privileges']) && in_array("editVlFacilityMap.php", $_SESSION['privileges']) && (($_SESSION['instanceType'] == 'remoteuser' || ($sarr['sc_user_type'] == 'standalone')))) { ?>
                    <th>Action</th>
                  <?php } ?>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="5" class="dataTables_empty">Loading data from server</td>
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

  $(document).ready(function() {
    $.blockUI();
    oTable = $('#facilityMapDataTable').dataTable({
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
          "sClass": "center",
          "bSortable": false
        },
        <?php if (isset($_SESSION['privileges']) && in_array("editVlFacilityMap.php", $_SESSION['privileges']) && (($_SESSION['instanceType'] == 'remoteuser' || ($sarr['sc_user_type'] == 'standalone')))) { ?> {
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
      "sAjaxSource": "getFacilityMapDetails.php",
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
require_once APPLICATION_PATH . '/footer.php';
