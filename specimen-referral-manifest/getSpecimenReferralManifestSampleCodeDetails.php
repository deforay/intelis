<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
#require_once('../startup.php');
include_once(APPLICATION_PATH . '/includes/MysqliDb.php');
include_once(APPLICATION_PATH . '/models/General.php');
$general = new \Vlsm\Models\General($db);
//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
  $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
//global config
$configQuery = "SELECT `value` FROM global_config WHERE name ='vl_form'";
$configResult = $db->query($configQuery);
$country = $configResult[0]['value'];

// $rpQuery="SELECT GROUP_CONCAT(DISTINCT rp.sample_id SEPARATOR ',') as sampleId FROM r_package_details_map as rp";
// $rpResult = $db->rawQuery($rpQuery);
if ($sarr['user_type'] == 'remoteuser') {
  $sCode = 'remote_sample_code';
  $vlfmQuery = "SELECT GROUP_CONCAT(DISTINCT vlfm.facility_id SEPARATOR ',') as facilityId FROM vl_user_facility_map as vlfm where vlfm.user_id='" . $_SESSION['userId'] . "'";
  $vlfmResult = $db->rawQuery($vlfmQuery);
} else if ($sarr['user_type'] == 'vluser' || $sarr['user_type'] == 'standalone') {
  $sCode = 'sample_code';
}

$module = $_POST['module'];

$query = "";
if ($module == 'vl') {
  $query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.vl_sample_id FROM vl_request_form as vl where (vl.sample_code IS NOT NULL OR vl.remote_sample_code IS NOT NULL) AND (vl.sample_package_id is null OR vl.sample_package_id='') AND (vl.sample_code is null OR vl.sample_code ='') AND vl.vlsm_country_id = $country";
} else if ($module == 'eid') {
  $query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.eid_id FROM eid_form as vl where (vl.sample_code IS NOT NULL OR vl.remote_sample_code IS NOT NULL) AND (vl.sample_package_id is null OR vl.sample_package_id='') AND (vl.sample_code is null OR vl.sample_code ='')  AND vl.vlsm_country_id = $country";
} else if ($module == 'C19') {
  $query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.covid19_id FROM form_covid19 as vl where (vl.sample_code IS NOT NULL OR vl.remote_sample_code IS NOT NULL) AND (vl.sample_package_id is null OR vl.sample_package_id='') AND (vl.sample_code is null OR vl.sample_code ='')  AND vl.vlsm_country_id = $country";
}


if (isset($vlfmResult[0]['facilityId'])) {
  $query .= " AND facility_id IN(" . $vlfmResult[0]['facilityId'] . ")";
}

$query .= " ORDER BY vl.request_created_datetime ASC";


$result = $db->rawQuery($query);

?>
<div class="col-md-8">
  <div class="form-group">
    <div class="col-md-12">
      <div class="col-md-12">
        <div style="width:60%;margin:0 auto;clear:both;">
          <a href="#" id="select-all-samplecode" style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<i class="icon-chevron-right"></i></a> <a href='#' id='deselect-all-samplecode' style="float:right" class="btn btn-danger btn-xs"><i class="icon-chevron-left"></i>&nbsp;Deselect All</a>
        </div><br /><br />
        <select id="sampleCode" name="sampleCode[]" multiple="multiple" class="search">
          <?php
          foreach ($result as $sample) {
            if ($sample[$sCode] != '') {
              if ($module == 'vl') {
                $sampleId  = $sample['vl_sample_id'];
                //$sampleCode  = $sample['vl_sample_id'];
              } else if ($module == 'eid') {
                $sampleId  = $sample['eid_id'];
              } else if ($module == 'C19') {
                $sampleId  = $sample['covid19_id'];
              }
          ?>
              <option value="<?php echo $sampleId; ?>"><?php echo ucwords($sample[$sCode]); ?></option>
          <?php
            }
          }
          ?>
        </select>
      </div>
    </div>
  </div>
</div>
<script>
  $(document).ready(function() {
    $('.search').multiSelect({
      selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample Code'>",
      selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample Code'>",
      afterInit: function(ms) {
        var that = this,
          $selectableSearch = that.$selectableUl.prev(),
          $selectionSearch = that.$selectionUl.prev(),
          selectableSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selectable:not(.ms-selected)',
          selectionSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selection.ms-selected';

        that.qs1 = $selectableSearch.quicksearch(selectableSearchString)
          .on('keydown', function(e) {
            if (e.which === 40) {
              that.$selectableUl.focus();
              return false;
            }
          });

        that.qs2 = $selectionSearch.quicksearch(selectionSearchString)
          .on('keydown', function(e) {
            if (e.which == 40) {
              that.$selectionUl.focus();
              return false;
            }
          });
      },
      afterSelect: function() {
        //button disabled/enabled
        if (this.qs2.cache().matchedResultsCount == noOfSamples) {
          alert("You have selected Maximum no. of sample " + this.qs2.cache().matchedResultsCount);
          $("#packageSubmit").attr("disabled", false);
          $("#packageSubmit").css("pointer-events", "auto");
        } else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
          $("#packageSubmit").attr("disabled", false);
          $("#packageSubmit").css("pointer-events", "auto");
        } else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
          alert("You have already selected Maximum no. of sample " + noOfSamples);
          $("#packageSubmit").attr("disabled", true);
          $("#packageSubmit").css("pointer-events", "none");
        }
        this.qs1.cache();
        this.qs2.cache();
      },
      afterDeselect: function() {
        //button disabled/enabled
        if (this.qs2.cache().matchedResultsCount == 0) {
          $("#packageSubmit").attr("disabled", true);
          $("#packageSubmit").css("pointer-events", "none");
        } else if (this.qs2.cache().matchedResultsCount == noOfSamples) {
          alert("You have selected Maximum no. of sample " + this.qs2.cache().matchedResultsCount);
          $("#packageSubmit").attr("disabled", false);
          $("#packageSubmit").css("pointer-events", "auto");
        } else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
          $("#packageSubmit").attr("disabled", false);
          $("#packageSubmit").css("pointer-events", "auto");
        } else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
          $("#packageSubmit").attr("disabled", true);
          $("#packageSubmit").css("pointer-events", "none");
        }
        this.qs1.cache();
        this.qs2.cache();
      }
    });
    $('#select-all-samplecode').click(function() {
      $('#sampleCode').multiSelect('select_all');
      return false;
    });
    $('#deselect-all-samplecode').click(function() {
      $('#sampleCode').multiSelect('deselect_all');
      $("#packageSubmit").attr("disabled", true);
      $("#packageSubmit").css("pointer-events", "none");
      return false;
    });
  });
</script>