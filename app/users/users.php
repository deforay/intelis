<?php

use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$title = _translate("Users");

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// A cloud-LIS lab operator only manages non-admin, non-API testing-lab users, so
// the role filter shows just those roles (matches the add/edit form). The list
// itself is lab-scoped in getUserDetails.php. No-op for everyone else.
$roleScope = $general->isCloudLisNonAdmin()
     ? " AND access_type='testing-lab' AND role_id != 1 AND (role_code IS NULL OR role_code != 'API') "
     : "";
$roles = $db->rawQuery("SELECT role_id, role_name FROM roles WHERE status='active' $roleScope GROUP BY role_code ORDER BY role_name ASC");
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-user"></em> <?php echo _translate("Users"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
      <li class="active"><?php echo _translate("Users"); ?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-xs-12">
        <div class="box">
          <div id="advanceFilter" class="collapse" style="margin-left:1%;margin-top:20px;width:98%;">
            <table class="table" aria-describedby="table" aria-hidden="true">
              <tbody>
                <tr>
                  <td><strong><?= _translate("Role"); ?>&nbsp;:</strong></td>
                  <td>
                    <select class="form-control select2-element" id="role" name="role"
                      title="<?php echo _translate('Please select Role'); ?>">
                      <option value=""><?php echo _translate("-- Select --"); ?></option>
                      <?php foreach ($roles as $role) { ?>
                        <option value="<?php echo $role['role_id']; ?>"><?php echo $role['role_name']; ?></option>
                      <?php } ?>
                    </select>
                  </td>
                  <td><strong><?= _translate("Status"); ?>&nbsp;:</strong></td>
                  <td>
                    <select class="form-control select2-element" id="status" name="status"
                      title="<?php echo _translate('Please select Status'); ?>">
                      <option value=""><?php echo _translate("-- Select --"); ?></option>
                      <option value="active"><?php echo _translate("Active"); ?></option>
                      <option value="inactive"><?php echo _translate("Inactive"); ?></option>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td colspan="4">&nbsp;<input type="button" onclick="searchResultData();" value="<?= _translate("Search"); ?>"
                      class="btn btn-success btn-sm">
                    <button class="btn btn-danger btn-sm"
                      onclick="document.location.href = document.location"><span><?= _translate("Reset"); ?></span></button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-xs-12">


        <div class="box">

          <span
            style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;margin-left: 450px;"
            id="showhide" class="">
            <div class="row" style="background:#e0e0e0;padding: 15px;">
              <div class="col-md-12">
                <div class="col-md-4">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="0" id="iCol0"
                    data-showhide="user_name" class="showhideCheckBox" /> <label
                    for="iCol0"><?php echo _translate("User Name"); ?></label>
                </div>
                <div class="col-md-3">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="1" id="iCol1" data-showhide="email"
                    class="showhideCheckBox" /> <label for="iCol1"><?php echo _translate("Email"); ?></label>
                </div>
                <div class="col-md-3">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="2" id="iCol2"
                    data-showhide="role_name" class="showhideCheckBox" /> <label
                    for="iCol2"><?php echo _translate("Role"); ?></label>
                </div>
                <div class="col-md-3">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="3" id="iCol3" data-showhide="status"
                    class="showhideCheckBox" /> <label for="iCol3"><?php echo _translate("Status"); ?></label> <br>
                </div>
              </div>
            </div>
          </span>
          <div class="box-header with-border">

            <?php if (_isAllowed("/users/addUser.php")) { ?>
              <a href="addUser.php" class="btn btn-primary pull-right" style="margin-left: 1%;"> <em class="fa-solid fa-plus"></em>
                <?php echo _translate("Add User"); ?></a>
            <?php } ?>
            <button class="btn btn-ghost btn-icon pull-right" data-toggle="collapse" data-target="#advanceFilter"
              aria-expanded="false" aria-controls="advanceFilter">
              <em class="fa fa-filter"></em><span class="text"><?= _translate("Advanced Search") ?></span>
            </button>
            <!--<button class="btn btn-primary pull-right" style="margin-right: 1%;" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>-->
          </div>

          <!-- /.box-header -->
          <div class="box-body">
            <table aria-describedby="table" id="userDataTable" class="table table-bordered table-striped"
              aria-hidden="true">
              <thead>
                <tr>
                  <th><?php echo _translate("User Name"); ?></th>
                  <th><?php echo _translate("Login ID"); ?></th>
                  <th><?php echo _translate("Email"); ?></th>
                  <th><?php echo _translate("Role"); ?></th>
                  <th scope="row"><?php echo _translate("Status"); ?></th>
                  <?php if (_isAllowed("/users/editUser.php")) { ?>
                    <th><?php echo _translate("Action"); ?></th>
                  <?php } ?>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="6" class="dataTables_empty"><?php echo _translate("Loading data from server"); ?></td>
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
  $(function () {

  });

  $(document).ready(function () {

    var $btn = $('[data-target="#advanceFilter"]');
    $('#advanceFilter').on('shown.bs.collapse', function () {
      $btn.find('.text').text('<?= _translate("Hide Filters") ?>');
    })
      .on('hidden.bs.collapse', function () {
        $btn.find('.text').text('<?= _translate("Advanced Search") ?>');
      });

    $("#role").select2({
      placeholder: "<?php echo _translate("Select Role"); ?>"
    });
    $("#status").select2({
      placeholder: "<?php echo _translate("Select Status"); ?>"
    });

    oTable = $('#userDataTable').dataTable({
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
        <?php if (_isAllowed("/users/editUser.php")) { ?> {
          "sClass": "center",
          "bSortable": false
        },
        <?php } ?>
      ],
      "aaSorting": [
        [4, "asc"],
        [0, "asc"]
      ],
      "bProcessing": true,
      "bServerSide": true,
      "sAjaxSource": "/users/getUserDetails.php",
      "fnServerData": function (sSource, aoData, fnCallback) {
        aoData.push({
          "name": "role",
          "value": $("#role").val()
        });
        aoData.push({
          "name": "status",
          "value": $("#status").val()
        });
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

  function searchResultData() {
    $.blockUI();
    oTable.fnDraw();
    $.unblockUI();
  }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
