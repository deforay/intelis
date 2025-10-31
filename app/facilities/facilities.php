<?php

use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

$title = _translate("Facilities");

require_once APPLICATION_PATH . '/header.php';
$fQuery = "SELECT * FROM facility_type";
$fResult = $db->rawQuery($fQuery);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$activeModules = SystemService::getActiveModules();


/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);
$state = $geolocationService->getProvinces("yes");
?>
<style>
  .select2-element {
    width: 300px;
  }
</style>

<style>
  .action-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: flex-end;
  }
</style>


<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-hospital"></em> <?php echo _translate("Facilities"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
      <li class="active"><?php echo _translate("Facilities"); ?></li>
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
                  <td><strong><?= _translate("Province/State"); ?>&nbsp;:</strong></td>
                  <td>
                    <select class="form-control select2-element" id="state" onchange="getDistrictByProvince(this.value)" name="state" title="<?php echo _translate('Please select Province/State'); ?>">
                      <?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
                    </select>
                  </td>
                  <td><strong><?= _translate("District/County"); ?> :</strong></td>
                  <td>
                    <select class="form-control select2-element" id="district" name="district" title="<?php echo _translate('Please select Province/State'); ?>">
                    </select>
                  </td>

                </tr>
                <tr>
                  <td>&nbsp;<strong><?= _translate("Facility Type"); ?> &nbsp;:</strong></td>
                  <td>
                    <select class="form-control isRequired select2-element" id="facilityType" name="facilityType" title="<?php echo _translate('Please select facility type'); ?>" onchange="getTestType(); showSignature(this.value);">
                      <option value=""> <?php echo _translate("-- Select --"); ?> </option>
                      <?php
                      foreach ($fResult as $type) {
                      ?>
                        <option value="<?php echo $type['facility_type_id']; ?>"><?php echo ($type['facility_type_name']); ?></option>
                      <?php
                      }
                      ?>
                    </select>
                  </td>
                  <td>&nbsp;<strong><?= _translate("Test Type"); ?> &nbsp;:</strong></td>
                  <td>
                    <select id="testType" name="testType" onchange="return checkFacilityType();" class="form-control select2-element" placeholder="<?php echo _translate('Please select the Test types'); ?>">
                      <option value=""><?= _translate("-- Choose Test Type --"); ?> </option>
                      <?php if (!empty($activeModules) && in_array('vl', $activeModules)) { ?>
                        <option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'vl') ? "selected='selected'" : ""; ?> value="vl"><?php echo _translate("Viral Load"); ?></option>
                      <?php }
                      if (!empty($activeModules) && in_array('eid', $activeModules)) { ?>
                        <option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'eid') ? "selected='selected'" : ""; ?> value="eid"><?php echo _translate("Early Infant Diagnosis"); ?></option>
                      <?php }
                      if (!empty($activeModules) && in_array('covid19', $activeModules)) { ?>
                        <option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'covid19') ? "selected='selected'" : ""; ?> value="covid19"><?php echo _translate("Covid-19"); ?></option>
                      <?php }
                      if (!empty($activeModules) && in_array('hepatitis', $activeModules)) { ?>
                        <option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'hepatitis') ? "selected='selected'" : ""; ?> value='hepatitis'><?php echo _translate("Hepatitis"); ?></option>
                      <?php }
                      if (!empty($activeModules) && in_array('tb', $activeModules)) { ?>
                        <option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'tb') ? "selected='selected'" : ""; ?> value='tb'><?php echo _translate("TB"); ?></option>
                      <?php }
                      if (!empty($activeModules) && in_array('cd4', $activeModules)) { ?>
                        <option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'cd4') ? "selected='selected'" : ""; ?> value='cd4'><?php echo _translate("CD4"); ?></option>
                      <?php } ?>
                    </select>
                  </td>
                </tr>
                <tr>

                  <td>&nbsp;<strong><?= _translate("Show Only Active"); ?> &nbsp;:</strong></td>
                  <td>
                    <select class="form-control select2-element" id="activeFacility" name="activeFacility" title="<?php echo _translate('Please select Active Facility'); ?>">
                      <option value=""> <?php echo _translate("-- Select --"); ?> </option>
                      <option value="active"><?php echo _translate("Yes"); ?></option>
                      <option value="inactive"><?php echo _translate("No"); ?></option>
                    </select>
                  </td>
                  <td></td>
                  <td></td>
                </tr>
                <tr>
                  <td colspan="4">&nbsp;<input type="button" onclick="searchResultData(),searchVlTATData();" value="Search" class="btn btn-success btn-sm">
                    &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
                  </td>
                </tr>

              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-xs-12">
        <div class="box">
          <span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;margin-left: 325px;" id="showhide" class="">
            <div class="row" style="background:#e0e0e0;padding: 15px;">
              <div class="col-md-12">
                <div class="col-md-4">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="0" id="iCol0" data-showhide="facility_code" class="showhideCheckBox" /> <label for="iCol0"><?php echo _translate("Facility Code"); ?></label>
                </div>
                <div class="col-md-4">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="1" id="iCol1" data-showhide="facility_name" class="showhideCheckBox" /> <label for="iCol1"><?php echo _translate("Facility Name"); ?></label>
                </div>
                <div class="col-md-4">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="2" id="iCol2" data-showhide="facility_type" class="showhideCheckBox" /> <label for="iCol2"><?php echo _translate("Facility Type"); ?></label>
                </div>
                <div class="col-md-4">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="3" id="iCol3" data-showhide="status" class="showhideCheckBox" /> <label for="iCol3"><?php echo _translate("Status"); ?></label> <br>
                </div>
              </div>
            </div>
          </span>
          <div class="box-header with-border">
            <div class="action-bar">

              <!-- keep 1–2 primaries solid -->
              <button class="btn btn-success btn-sm btn-icon" onclick="exportInexcel()">
                <em class="fa fa-cloud-download"></em><span class="text"><?= _translate("Export") ?></span>
              </button>

              <button class="btn btn-ghost btn-sm btn-icon" data-toggle="collapse" data-target="#advanceFilter">
                <em class="fa fa-filter"></em><span class="text"><?= _translate("Advanced Search") ?></span>
              </button>
              <?php if (_isAllowed("addFacility.php") && ($general->isSTSInstance() || $general->isStandaloneInstance())) { ?>
                <!-- secondary actions stay visible, low-contrast, and will wrap -->
                <a href="mapTestType.php?type=health-facilities" class="btn btn-ghost btn-sm btn-icon">
                  <em class="fa fa-hospital"></em><span class="text"><?= _translate("Health Facilities") ?></span>
                </a>

                <a href="mapTestType.php?type=testing-labs" class="btn btn-ghost btn-sm btn-icon">
                  <em class="fa fa-flask"></em><span class="text"><?= _translate("Testing Lab") ?></span>
                </a>


                <a href="upload-facilities.php" class="btn btn-ghost btn-sm btn-icon">
                  <em class="fa fa-upload"></em><span class="text"><?= _translate("Bulk Upload") ?></span>
                </a>

                <a href="addFacility.php" class="btn btn-primary btn-sm btn-icon">
                  <em class="fa fa-plus-circle"></em><span class="text"><?= _translate("Add Facility") ?></span>
                </a>
              <?php } ?>
            </div>
          </div>

          <!-- /.box-header -->
          <div class="box-body">

            <table aria-describedby="table" id="facilityDataTable" class="table table-bordered table-striped" aria-hidden="true">
              <thead>
                <tr>
                  <th><?php echo _translate("Facility Code"); ?></th>
                  <th scope="row"><?php echo _translate("Facility Name"); ?></th>
                  <th><?php echo _translate("Facility Type"); ?></th>
                  <th scope="row"><?php echo _translate("Status"); ?></th>
                  <th><?php echo _translate("Province"); ?></th>
                  <th><?php echo _translate("District"); ?></th>
                  <?php if (_isAllowed("editFacility.php") && ($general->isSTSInstance() || $general->isStandaloneInstance())) { ?>
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

  $(document).ready(function() {

    $.blockUI();

    $("#state").select2({
      placeholder: "<?php echo _translate("Select Province"); ?>"
    });
    $("#district").select2({
      placeholder: "<?php echo _translate("Select District"); ?>"
    });
    oTable = $('#facilityDataTable').dataTable({
      "oLanguage": {
        "sLengthMenu": "_MENU_ <?= _translate("records per page", true); ?>"
      },
      "lengthMenu": [
        [10, 25, 50, 100, 200, 250, 500, -1],
        [10, 25, 50, 100, 200, 250, 500, "All"]
      ],

      "bJQueryUI": false,
      "bAutoWidth": false,
      "bInfo": true,
      "bScrollCollapse": true,
      "bStateSave": true,
      "bDestroy": true,
      "bRetrieve": true,
      "iDisplayLength": 25,
      "aaSorting": [
        [3, "asc"],
        [1, "asc"]
      ],
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
        {
          "sClass": "center"
        },
        <?php if (_isAllowed("editFacility.php") && ($general->isSTSInstance() || $general->isStandaloneInstance())) { ?> {
            "sClass": "center",
            "bSortable": false
          },
        <?php } ?>
      ],
      "bProcessing": true,
      "bServerSide": true,
      "sAjaxSource": "getFacilityDetails.php",
      "fnServerData": function(sSource, aoData, fnCallback) {
        aoData.push({
          "name": "state",
          "value": $("#state").val()
        });
        aoData.push({
          "name": "district",
          "value": $("#district").val()
        });
        aoData.push({
          "name": "facilityType",
          "value": $("#facilityType").val()
        });
        aoData.push({
          "name": "testType",
          "value": $("#testType").val()
        });
        aoData.push({
          "name": "activeFacility",
          "value": $("#activeFacility").val()
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
    $.unblockUI();
  });

  function searchResultData() {
    $.blockUI();
    oTable.fnDraw();
    $.unblockUI();
  }

  function loadVlRequestStateDistrict() {
    oTable.fnDraw();
  }

  function checkFacilityType() {
    fType = $("#facilityType").val();
    if (fType == "") {
      alert("<?= _translate("Please choose facility type first", true); ?>");
      $("#testType").val("");
      return false;
    }
    return true;
  }

  function exportInexcel() {

    $.blockUI();
    oTable.fnDraw();
    $.post("/facilities/facilityExportInExcel.php", {
        state: $("#state").val(),
        district: $("#district").val(),
        facilityType: $("#facilityType").val(),
        testType: $("#testType").val(),
      },
      function(data) {
        if (data == "" || data == null || data == undefined) {
          $.unblockUI();
          alert("<?= _translate("Unable to generate excel", true); ?>");
        } else {
          $.unblockUI();
          window.open('/download.php?f=' + data, '_blank');
        }
      });

  }

  function getDistrictByProvince(provinceId) {
    $("#district").html('');
    $.post("/common/get-by-province-id.php", {
        provinceId: provinceId,
        districts: true,
      },
      function(data) {
        Obj = $.parseJSON(data);
        $("#district").html(Obj['districts']);
      });
  }


  function hideAdvanceSearch(hideId, showId) {
    $("#" + hideId).hide();
    $("#" + showId).show();
  }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
