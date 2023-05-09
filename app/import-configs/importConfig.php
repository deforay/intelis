<?php
$title = "Instruments";
 
require_once APPLICATION_PATH . '/header.php';
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-gears"></em> <?php echo _("Instruments");?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home");?></a></li>
      <li class="active"><?php echo _("Instruments");?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <div class="box-header with-border">
            <a href="addImportConfig.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _("Add Instrument");?></a>
          </div>
          <!-- /.box-header -->
          <div class="box-body">
            <table id="importConfigDataTable" class="table table-bordered table-striped" aria-hidden="true" >
              <thead>
                <tr>
                  <th><?php echo _("Instrument Name");?></th>
                  <th><?php echo _("Status");?></th>
                  <th><?php echo _("Action");?></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="12" class="dataTables_empty"><?php echo _("Loading data from server");?></td>
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
<script type="text/javascript">
  var oTable = null;
  $(document).ready(function() {
    $.blockUI();
    oTable = $('#importConfigDataTable').dataTable({
      "oLanguage": {
        "sLengthMenu": "_MENU_ records per page"
      },
      "bJQueryUI": false,
      "bAutoWidth": false,
      "bInfo": true,
      "bScrollCollapse": true,

      "bRetrieve": true,
      "aoColumns": [{
          "sClass": "center"
        },
        {
          "sClass": "center"
        },
        {
          "sClass": "center",
          "bSortable": false
        }
      ],
      "aaSorting": [
        [0, "asc"]
      ],
      "bProcessing": true,
      "bServerSide": true,
      "sAjaxSource": "getImportConfigDetails.php",
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
?>