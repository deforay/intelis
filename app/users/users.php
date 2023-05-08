<?php
$title = _("Users");

require_once(APPLICATION_PATH . '/header.php');
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-user"></em> <?php echo _("Users"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
      <li class="active"><?php echo _("Users"); ?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">


        <div class="box">

          <span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;margin-left: 450px;" id="showhide" class="">
            <div class="row" style="background:#e0e0e0;padding: 15px;">
              <div class="col-md-12">
                <div class="col-md-4">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="0" id="iCol0" data-showhide="user_name" class="showhideCheckBox" /> <label for="iCol0"><?php echo _("User Name"); ?></label>
                </div>
                <div class="col-md-3">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="1" id="iCol1" data-showhide="email" class="showhideCheckBox" /> <label for="iCol1"><?php echo _("Email"); ?></label>
                </div>
                <div class="col-md-3">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="2" id="iCol2" data-showhide="role_name" class="showhideCheckBox" /> <label for="iCol2"><?php echo _("Role"); ?></label>
                </div>
                <div class="col-md-3">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="3" id="iCol3" data-showhide="status" class="showhideCheckBox" /> <label for="iCol3"><?php echo _("Status"); ?></label> <br>
                </div>
              </div>
            </div>
          </span>
          <div class="box-header with-border">

            <?php if (isset($_SESSION['privileges']) && in_array("addUser.php", $_SESSION['privileges'])) { ?>
              <a href="addUser.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _("Add User"); ?></a>
            <?php } ?>
            <!--<button class="btn btn-primary pull-right" style="margin-right: 1%;" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>-->
          </div>

          <!-- /.box-header -->
          <div class="box-body">
            <table id="userDataTable" class="table table-bordered table-striped" aria-hidden="true">
              <thead>
                <tr>
                  <th><?php echo _("User Name"); ?></th>
                  <th><?php echo _("Login ID"); ?></th>
                  <th><?php echo _("Email"); ?></th>
                  <th><?php echo _("Role"); ?></th>
                  <th scope="row"><?php echo _("Status"); ?></th>
                  <?php if (isset($_SESSION['privileges']) && in_array("editUser.php", $_SESSION['privileges'])) { ?>
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
  $(function() {

  });

  $(document).ready(function() {
    $.blockUI();
    oTable = $('#userDataTable').dataTable({
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
        {
          "sClass": "center"
        },
        {
          "sClass": "center"
        },
        {
          "sClass": "center"
        },
        <?php if (isset($_SESSION['privileges']) && in_array("editUser.php", $_SESSION['privileges'])) { ?> {
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
      "sAjaxSource": "getUserDetails.php",
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
require_once(APPLICATION_PATH . '/footer.php');
