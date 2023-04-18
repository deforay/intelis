<?php
ob_start();
 
require_once(APPLICATION_PATH . '/header.php');

$vlfmQuery = "SELECT GROUP_CONCAT(DISTINCT vlfm.vl_lab_id SEPARATOR ',') as vlLabId FROM testing_lab_health_facilities_map as vlfm";
$vlfmResult = $db->rawQuery($vlfmQuery);
$fQuery = "SELECT * FROM facility_details where facility_type=2";
if (isset($vlfmResult[0]['vlLabId'])) {
  $fQuery = $fQuery . " AND facility_id NOT IN(" . $vlfmResult[0]['vlLabId'] . ")";
}
$fResult = $db->rawQuery($fQuery);
$hcQuery = "SELECT * FROM facility_details where facility_type!=2";
$hcResult = $db->rawQuery($hcQuery);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-hospital"></em> Add Facility Map </h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
      <li class=""><a href="facilityMap.php">Facility Map</a></li>
      <li class="active">Add Facility Map</li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">

    <div class="box box-default">
      <div class="box-header with-border">
        <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <!-- form start -->
        <form class="form-horizontal" method='post' name='addFacilityMapForm' id='addFacilityMapForm' autocomplete="off" action="addFacilityMapHelper.php">
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="vlLab" class="col-lg-4 control-label">Viral Load Lab <span class="mandatory">*</span> </label>
                  <div class="col-lg-7">
                    <select class="form-control isRequired" id="vlLab" name="vlLab" title="Please select vl lab">
                      <option value=""> -- Select -- </option>
                      <?php
                      foreach ($fResult as $lab) {
                      ?>
                        <option value="<?php echo $lab['facility_id']; ?>"><?php echo ($lab['facility_name']); ?></option>
                      <?php
                      }
                      ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-xs-5">
                <select name="from[]" id="search" class="form-control" size="8" multiple="multiple">
                  <?php
                  foreach ($hcResult as $facility) {
                  ?>
                    <option value="<?php echo $facility['facility_id']; ?>"><?php echo ($facility['facility_name']); ?></option>
                  <?php
                  }
                  ?>
                </select>
              </div>

              <div class="col-xs-2">
                <button type="button" id="search_rightAll" class="btn btn-block"><em class="fa-solid fa-forward"></em></button>
                <button type="button" id="search_rightSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-right"></em></button>
                <button type="button" id="search_leftSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-left"></em></button>
                <button type="button" id="search_leftAll" class="btn btn-block"><em class="fa-solid fa-backward"></em></button>
              </div>

              <div class="col-xs-5">
                <select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple"></select>
              </div>
            </div>
          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <input type="hidden" class="isRequired" name="facilityTo" id="facilityTo" title="Please choose atleast one facility" />
            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
            <a href="facilityMap.php" class="btn btn-default"> Cancel</a>
          </div>
          <!-- /.box-footer -->
        </form>
        <!-- /.row -->
      </div>
    </div>
    <!-- /.box -->
  </section>
  <!-- /.content -->
</div>
<script type="text/javascript" src="/assets/js/multiselect.min.js"></script>

<script type="text/javascript">
  jQuery(document).ready(function($) {
    $('#search').multiselect({
      search: {
        left: '<input type="text" name="q" class="form-control" placeholder="Search..." />',
        right: '<input type="text" name="q" class="form-control" placeholder="Search..." />',
      },
      fireSearch: function(value) {
        return value.length > 3;
      }
    });
  });

  function validateNow() {
    var selVal = [];
    $('#search_to option').each(function(i, selected) {
      selVal[i] = $(selected).val();
    });
    $("#facilityTo").val(selVal);
    flag = deforayValidator.init({
      formId: 'addFacilityMapForm'
    });

    if (flag) {
      $.blockUI();
      document.getElementById('addFacilityMapForm').submit();
    }
  }

  function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
    var removeDots = obj.value.replace(/\./g, "");
    var removeDots = removeDots.replace(/\,/g, "");
    //str=obj.value;
    removeDots = removeDots.replace(/\s{2,}/g, ' ');

    $.post("/includes/checkDuplicate.php", {
        tableName: tableName,
        fieldName: fieldName,
        value: removeDots.trim(),
        fnct: fnct,
        format: "html"
      },
      function(data) {
        if (data === '1') {
          alert(alrt);
          document.getElementById(obj.id).value = "";
        }
      });
  }
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
