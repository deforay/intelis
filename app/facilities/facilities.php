<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;
use App\Services\SystemService;

$title = _("Facilities");
 
require_once(APPLICATION_PATH . '/header.php');
$fQuery = "SELECT * FROM facility_type";
$fResult = $db->rawQuery($fQuery);

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);

$activeTestModules = SystemService::getActiveTestModules();
// if($sarr['sc_user_type']=='vluser'){
//   include('../remote/pullDataFromRemote.php');
// }
$geoLocationDb = new GeoLocationsService();
$state = $geoLocationDb->getProvinces("yes");
?>
<style>
select { width:400px; !important }
.select2-element { width: 300px; }
</style>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-hospital"></em> <?php echo _("Facilities");?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home");?></a></li>
      <li class="active"><?php echo _("Facilities");?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <div class="row">
    <div class="col-xs-12">
				<div class="box">
					<table class="table" id="advanceFilter" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%; display:none;">
						<tbody><tr>
						<td><strong><?php echo _("Province/State"); ?>&nbsp;:</strong></td>
							<td>
              <select class="form-control select2-element" id="state" onchange="getDistrictByProvince(this.value)" name="state" title="<?php echo _('Please select Province/State'); ?>">
              <?= $general->generateSelectOptions($state, null, _("-- Select --")); ?>
								</select>
							</td>
							<td><strong><?php echo _("District/County"); ?> :</strong></td>
							<td>
              <select class="form-control select2-element" id="district" name="district" title="<?php echo _('Please select Province/State'); ?>">
                </select>
							</td>

						</tr>
						<tr>
							<td>&nbsp;<strong>Facility Type &nbsp;:</strong></td>
							<td>
              <select class="form-control isRequired select2-element" id="facilityType" name="facilityType" title="<?php echo _('Please select facility type'); ?>" onchange="<?php echo ($_SESSION['instanceType'] == 'remoteuser') ? 'getFacilityUser();' : ''; ?> getTestType(); showSignature(this.value);">
											<option value=""> <?php echo _("-- Select --"); ?> </option>
											<?php
											foreach ($fResult as $type) {
											?>
												<option value="<?php echo $type['facility_type_id']; ?>"><?php echo ($type['facility_type_name']); ?></option>
											<?php
											}
											?>
										</select>
							</td>
              <td>&nbsp;<strong>Test Type &nbsp;:</strong></td>
							<td>
              <select type="text" id="testType" name="testType" onchange="return checkFacilityType();" class="form-control select2-element" placeholder="<?php echo _('Please select the Test types'); ?>">
										<option value="">-- Choose Test Type--</option>
										<?php if (!empty($activeTestModules) && in_array('vl', $activeTestModules)) { ?>
											<option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'vl') ? "selected='selected'" : ""; ?> value="vl"><?php echo _("Viral Load"); ?></option>
										<?php }
										if (!empty($activeTestModules) && in_array('eid', $activeTestModules)) { ?>
											<option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'eid') ? "selected='selected'" : ""; ?> value="eid"><?php echo _("Early Infant Diagnosis"); ?></option>
										<?php }
										if (!empty($activeTestModules) && in_array('covid19', $activeTestModules)) { ?>
											<option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'covid19') ? "selected='selected'" : ""; ?> value="covid19"><?php echo _("Covid-19"); ?></option>
										<?php }
										if (!empty($activeTestModules) && in_array('hepatitis', $activeTestModules)) { ?>
											<option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'hepatitis') ? "selected='selected'" : ""; ?> value='hepatitis'><?php echo _("Hepatitis"); ?></option>
										<?php }
										if (!empty($activeTestModules) && in_array('tb', $activeTestModules)) { ?>
											<option <?php echo (isset($_POST['testType']) && $_POST['testType'] == 'tb') ? "selected='selected'" : ""; ?> value='tb'><?php echo _("TB"); ?></option>
										<?php } ?>
									</select>
							</td>
						</tr><tr>
							
							<td></td>
							<td></td>
						</tr>
						<tr>
							<td colspan="4">&nbsp;<input type="button" onclick="searchResultData(),searchVlTATData();" value="Search" class="btn btn-success btn-sm">
								&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
                &nbsp;<button class="btn btn-danger btn-sm" onclick="hideAdvanceSearch('advanceFilter','filter');"><span>Hide Advanced Search Options</span></button>
              </td>
						</tr>

					</tbody></table>
				</div>
			</div>
      <div class="col-xs-12">
        <div class="box">
          <span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;margin-left: 325px;" id="showhide" class="">
            <div class="row" style="background:#e0e0e0;padding: 15px;">
              <div class="col-md-12">
                <div class="col-md-4">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="0" id="iCol0" data-showhide="facility_code" class="showhideCheckBox" /> <label for="iCol0"><?php echo _("Facility Code");?></label>
                </div>
                <div class="col-md-4">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="1" id="iCol1" data-showhide="facility_name" class="showhideCheckBox" /> <label for="iCol1"><?php echo _("Facility Name");?></label>
                </div>
                <div class="col-md-4">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="2" id="iCol2" data-showhide="facility_type" class="showhideCheckBox" /> <label for="iCol2"><?php echo _("Facility Type");?></label>
                </div>
                <div class="col-md-4">
                  <input type="checkbox" onclick="fnShowHide(this.value);" value="3" id="iCol3" data-showhide="status" class="showhideCheckBox" /> <label for="iCol3"><?php echo _("Status");?></label> <br>
                </div>
              </div>
            </div>
          </span>
          <div class="box-header with-border">
            <?php if (isset($_SESSION['privileges']) && in_array("addFacility.php", $_SESSION['privileges']) && ($_SESSION['instanceType'] == 'remoteuser' || $sarr['sc_user_type'] == 'standalone')) { ?>
              <a href="addFacility.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _("Add Facility");?></a>
              <a href="mapTestType.php?type=testing-labs" class="btn btn-primary pull-right" style="margin-right: 10px;"> <em class="fa-solid fa-plus"></em> <?php echo _("Manage Testing Lab");?></a>
              <a href="mapTestType.php?type=health-facilities" class="btn btn-primary pull-right" style="margin-right: 10px;"> <em class="fa-solid fa-plus"></em> <?php echo _("Manage Health Facilities");?></a>
           
              <?php } ?>
              &nbsp;<button id="filter" class="btn btn-primary btn-sm pull-right" style="margin-right:5px;line-height: 2;" onclick="hideAdvanceSearch('filter','advanceFilter');"><span><?php echo _("Show Advanced Search Options"); ?></span></button>
            <!--<button class="btn btn-primary pull-right" style="margin-right: 1%;" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>-->
          </div>
          <!-- /.box-header -->
          <div class="box-body">
          <button class="btn btn-success pull-right" type="button" onclick="exportInexcel()"><em class="fa-solid fa-cloud-arrow-down"></em> Export to excel</button>
            <table id="facilityDataTable" class="table table-bordered table-striped" aria-hidden="true" >
              <thead>
                <tr>
                  <th><?php echo _("Facility Code");?></th>
                  <th><?php echo _("Facility Name");?></th>
                  <th><?php echo _("Facility Type");?></th>
                  <th><?php echo _("Status");?></th>
                  <th><?php echo _("Province");?></th>
                  <th><?php echo _("District");?></th>
                  <?php if (isset($_SESSION['privileges']) && in_array("editFacility.php", $_SESSION['privileges']) && ($_SESSION['instanceType'] == 'remoteuser' || $sarr['sc_user_type'] == 'standalone')) { ?>
                    <th><?php echo _("Action");?></th>
                  <?php } ?>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="6" class="dataTables_empty"><?php echo _("Loading data from server");?></td>
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
			placeholder: "<?php echo _("Select Province"); ?>"
		});
    $("#district").select2({
			placeholder: "<?php echo _("Select District"); ?>"
		});
    oTable = $('#facilityDataTable').dataTable({
      "oLanguage": {
        "sLengthMenu": "_MENU_ records per page"
      },
      "bJQueryUI": false,
      "bAutoWidth": false,
      "bInfo": true,
      "bScrollCollapse": true,
      "bStateSave": true,
      "bRetrieve": true,
      "aaSorting": [2, "asc"],
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
        <?php if (isset($_SESSION['privileges']) && in_array("editFacility.php", $_SESSION['privileges']) && ($_SESSION['instanceType'] == 'remoteuser' || $sarr['sc_user_type'] == 'standalone')) { ?> {
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

  function checkFacilityType()
  {
    fType = $("#facilityType").val();
    if(fType=="")
    {   alert("Please choose facility type first");
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
					alert("<?php echo _("Unable to generate excel"); ?>");
				} else {
					$.unblockUI();
					window.open('/download.php?f=' + data, '_blank');
				}
			});

	}

  function getDistrictByProvince(provinceId)
  {
    $("#district").html('');
    $.post("/common/get-by-province-id.php", {
      provinceId : provinceId,
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
require_once(APPLICATION_PATH . '/footer.php');
