<?php

require_once APPLICATION_PATH . '/header.php';
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-gears"></em> Email/SMS Configuration</h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
      <li class="active">Email/SMS Configuration</li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <div class="box-header with-border">
            <a href="editOtherConfig.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-pen-to-square"></em></em> Edit Other Config</a>
            <a href="editResultEmailConfig.php" class="btn btn-warning pull-right" style="margin-right:10px;"> <em class="fa-solid fa-pen-to-square"></em></em> Edit Result Email Config</a>
            <a href="editRequestEmailConfig.php" class="btn btn-default pull-right" style="margin-right:10px;"> <em class="fa-solid fa-pen-to-square"></em></em> Edit Request Email Config</a>
          </div>
          <!-- /.box-header -->
          <div class="box-body">
            <table aria-describedby="table" id="otherConfigDataTable" class="table table-bordered table-striped" aria-hidden="true">
              <thead>
                <tr>
                  <th>Config Name</th>
                  <th>Value</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="2" class="dataTables_empty">Loading data from server</td>
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
    oTable = $('#otherConfigDataTable').dataTable({
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
        }
      ],
      "aaSorting": [
        [0, "asc"]
      ],
      "bProcessing": true,
      "bServerSide": true,
      "sAjaxSource": "getOtherConfigDetails.php",
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
