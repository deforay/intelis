<?php
$title = "Instruments";

require_once APPLICATION_PATH . '/header.php';
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-gears"></em>
      <?php echo _translate("Instruments"); ?>
    </h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
          <?php echo _translate("Home"); ?>
        </a></li>
      <li class="active">
        <?php echo _translate("Instruments"); ?>
      </li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <div class="box-header with-border">
            <a href="add-instrument.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em>
              <?php echo _translate("Add Instrument"); ?>
            </a>
          </div>
          <!-- /.box-header -->
          <div class="box-body">
            <table aria-describedby="table" id="importConfigDataTable" class="table table-bordered table-striped"
              aria-hidden="true">
              <thead>
                <tr>
                  <th>
                    <?php echo _translate("Instrument Name"); ?>
                  </th>
                  <th>
                    <?php echo _translate("Status"); ?>
                  </th>
                  <th>
                    <?php echo _translate("Action"); ?>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="12" class="dataTables_empty">
                    <?php echo _translate("Loading data from server"); ?>
                  </td>
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
  $(document).ready(function () {
    oTable = $('#importConfigDataTable').dataTable({
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
      "sAjaxSource": "get-instruments.php",
      "fnServerData": function (sSource, aoData, fnCallback) {
        $.ajax({
          "dataType": 'json',
          "type": "POST",
          "url": sSource,
          "data": aoData,
          "success": fnCallback
        });
      }
    });
  });
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
