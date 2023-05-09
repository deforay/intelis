<?php
$title = _("Roles");

require_once APPLICATION_PATH . '/header.php';
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-user"></em> <?php echo _("Roles"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
      <li class="active"><?php echo _("Roles"); ?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">


        <div class="box">
          <div class="box-header with-border">
            <?php if (isset($_SESSION['privileges']) && in_array("addRole.php", $_SESSION['privileges'])) { ?>
              <a href="addRole.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _("Add Role"); ?></a>
            <?php } ?>
          </div>
          <!-- /.box-header -->
          <div class="box-body">
            <table id="roleDataTable" class="table table-bordered table-striped" aria-hidden="true">
              <thead>
                <tr>
                  <th><?php echo _("Role Name"); ?></th>
                  <th><?php echo _("Role Code"); ?></th>
                  <th scope="row"><?php echo _("Status"); ?></th>
                  <?php if (isset($_SESSION['privileges']) && in_array("editRole.php", $_SESSION['privileges'])) { ?>
                    <th><?php echo _("Action"); ?></th>
                  <?php } ?>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="6" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
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
    oTable = $('#roleDataTable').dataTable({
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
          "sClass": "center"
        },
        <?php if (isset($_SESSION['privileges']) && in_array("editRole.php", $_SESSION['privileges'])) { ?> {
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
      "sAjaxSource": "getRoleDetails.php",
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
