<?php

use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$title = _translate("Roles");

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$activeModules = SystemService::getActiveModules();

$mode = " AND (p.show_mode like 'always')";
if ($general->isSTSInstance()) {
    $mode = " AND (p.show_mode like 'sts' or p.show_mode like 'always')";
} elseif ($general->isLISInstance()) {
    $mode = " AND (p.show_mode like 'lis' or p.show_mode like 'always')";
}

$permissions = [];
if ($activeModules !== []) {
    $permQuery = "SELECT p.privilege_id,
                CONCAT(r.display_name, ' - ', p.display_name) as permission_name
                FROM privileges as p
                INNER JOIN resources as r ON r.resource_id = p.resource_id
                WHERE r.module IN ('" . implode("','", $activeModules) . "') $mode
                ORDER BY r.display_name ASC, p.display_order ASC";
    $permissions = $db->rawQuery($permQuery);
}
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-user"></em> <?php echo _translate("Roles"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
      <li class="active"><?php echo _translate("Roles"); ?></li>
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
                  <td><strong><?= _translate("Access Type"); ?>&nbsp;:</strong></td>
                  <td>
                    <select class="form-control select2-element" id="accessType" name="accessType"
                      title="<?php echo _translate('Please select Access Type'); ?>">
                      <option value=""><?php echo _translate("-- Select --"); ?></option>
                      <option value="testing-lab"><?php echo _translate("Testing Lab"); ?></option>
                      <option value="collection-site"><?php echo _translate("Collection Site"); ?></option>
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
                  <td><strong><?= _translate("Permission"); ?>&nbsp;:</strong></td>
                  <td colspan="3">
                    <select class="form-control select2-element" id="permission" name="permission"
                      style="width:100%;" title="<?php echo _translate('Filter roles having this permission enabled'); ?>">
                      <option value=""><?php echo _translate("-- Select Permission --"); ?></option>
                      <?php foreach ($permissions as $permission) { ?>
                        <option value="<?php echo $permission['privilege_id']; ?>">
                          <?php echo _translate($permission['permission_name']); ?></option>
                      <?php } ?>
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
          <div class="box-header with-border">
            <?php if (_isAllowed("/roles/addRole.php")) { ?>
              <a href="addRole.php" class="btn btn-primary pull-right" style="margin-left: 1%;"> <em class="fa-solid fa-plus"></em>
                <?php echo _translate("Add Role"); ?></a>
            <?php } ?>
            <button class="btn btn-ghost btn-icon pull-right" data-toggle="collapse" data-target="#advanceFilter"
              aria-expanded="false" aria-controls="advanceFilter">
              <em class="fa fa-filter"></em><span class="text"><?= _translate("Advanced Search") ?></span>
            </button>
          </div>
          <!-- /.box-header -->
          <div class="box-body">
            <table aria-describedby="table" id="roleDataTable" class="table table-bordered table-striped"
              aria-hidden="true">
              <thead>
                <tr>
                  <th><?php echo _translate("Role Name"); ?></th>
                  <th><?php echo _translate("Role Code"); ?></th>
                  <th scope="row"><?php echo _translate("Status"); ?></th>
                  <?php if (_isAllowed("/roles/editRole.php")) { ?>
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

  $(document).ready(function () {

    var $btn = $('[data-target="#advanceFilter"]');
    $('#advanceFilter').on('shown.bs.collapse', function () {
      $btn.find('.text').text('<?= _translate("Hide Filters") ?>');
    })
      .on('hidden.bs.collapse', function () {
        $btn.find('.text').text('<?= _translate("Advanced Search") ?>');
      });

    $("#accessType").select2({
      placeholder: "<?php echo _translate("Select Access Type"); ?>"
    });
    $("#status").select2({
      placeholder: "<?php echo _translate("Select Status"); ?>"
    });
    $("#permission").select2({
      placeholder: "<?php echo _translate("Select Permission"); ?>",
      allowClear: true
    });

    oTable = $('#roleDataTable').dataTable({
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
        <?php if (_isAllowed("/roles/editRole.php")) { ?> {
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
      "sAjaxSource": "/roles/getRoleDetails.php",
      "fnServerData": function (sSource, aoData, fnCallback) {
        aoData.push({
          "name": "accessType",
          "value": $("#accessType").val()
        });
        aoData.push({
          "name": "status",
          "value": $("#status").val()
        });
        aoData.push({
          "name": "permission",
          "value": $("#permission").val()
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
