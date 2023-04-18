<?php
ob_start();
 
require_once(APPLICATION_PATH . '/header.php');
$otherConfigQuery = "SELECT * from other_config WHERE type='result'";
$otherConfigResult = $db->query($otherConfigQuery);
$arr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($otherConfigResult); $i++) {
  $arr[$otherConfigResult[$i]['name']] = $otherConfigResult[$i]['value'];
}

$resultArr = array();
//Set selected field
if (isset($arr['rs_field']) && trim($arr['rs_field']) != '') {
  $explodField = explode(",", $arr['rs_field']);
  for ($f = 0; $f < count($explodField); $f++) {
    $resultArr[] = $explodField[$f];
  }
}
?>
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
  .ms-container {
    width: 100%;
  }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-sharp fa-solid fa-gears"></em> <?php echo _("Edit Test Result Email Configuration");?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home");?></a></li>
      <li><a href="testResultEmailConfig.php"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Manage Test Result Email/SMS Config");?></a></li>
      <li class="active"><?php echo _("Edit Test Result Email Configuration");?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    
    <div class="box box-default">
      <!--<div class="box-header with-border">
          <div class="pull-right" style="font-size:15px;"> </div>
        </div>-->
      <!-- /.box-header -->
      <div class="box-body">
        <!-- form start -->
        <form class="form-horizontal" name="editTestResultEmailConfigForm" id="editTestResultEmailConfigForm" method="post" autocomplete="off" action="editTestResultEmailConfigHelper.php">
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="rs_email" class="col-lg-3 control-label"><?php echo _("Email Id");?> <code><?php echo _("(Gmail only)");?></code>*</label>
                  <div class="col-lg-9">
                    <input type="text" class="form-control isEmail isRequired" id="rs_email" name="rs_email" placeholder="<?php echo _('eg.hello.vl@gmail.com');?>" title="<?php echo _('Please enter email');?>" value="<?php echo $arr['rs_email']; ?>">
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="rs_password" class="col-lg-3 control-label"><?php echo _("Password");?> *</label>
                  <div class="col-lg-9">
                    <input type="password" class="form-control isRequired" id="rs_password" name="rs_password" placeholder="<?php echo _('Password');?>" title="<?php echo _('Please enter password');?>" value="<?php echo $arr['rs_password']; ?>">
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="rs_field" class="col-lg-3 control-label"><?php echo _("Choose Fields");?> *</label>
                  <div class="col-lg-9">
                    <div style="width:100%;margin:0 auto;clear:both;">
                      <a href="#" id="select-all-field" style="float:left" class="btn btn-info btn-xs"><?php echo _("Select All");?>&nbsp;&nbsp;<em class="fa-solid fa-chevron-right"></em></a> <a href="#" id="deselect-all-field" style="float:right" class="btn btn-danger btn-xs"><em class="fa-solid fa-chevron-left"></em>&nbsp;<?php echo _("Deselect All");?></a>
                    </div><br /><br />
                    <select id="rs_field" name="rs_field[]" multiple="multiple" class="search isRequired" title="Please select email fields">
                      <option value="Sample ID" <?php echo (in_array("Sample ID", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Sample ID");?></option>
                      <option value="Urgency" <?php echo (in_array("Urgency", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Urgency");?></option>
                      <option value="Province" <?php echo (in_array("Province", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Province/State");?></option>
                      <option value="District Name" <?php echo (in_array("District Name", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("District/County");?></option>
                      <option value="Clinic Name" <?php echo (in_array("Clinic Name", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Clinic Name");?></option>
                      <option value="Clinician Name" <?php echo (in_array("Clinician Name", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Clinician Name");?></option>
                      <option value="Sample Collection Date" <?php echo (in_array("Sample Collection Date", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Sample Collection Date");?></option>
                      <option value="Sample Received Date" <?php echo (in_array("Sample Received Date", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Sample Received Date");?></option>
                      <option value="Collected by (Initials)" <?php echo (in_array("Collected by (Initials)", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Collected by (Initials)");?></option>
                      <option value="Gender" <?php echo (in_array("Gender", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Gender");?></option>
                      <option value="Date Of Birth" <?php echo (in_array("Date Of Birth", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Date Of Birth");?></option>
                      <option value="Age in years" <?php echo (in_array("Age in years", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Age in years");?></option>
                      <option value="Age in months" <?php echo (in_array("Age in months", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Age in months");?></option>
                      <option value="Is Patient Pregnant?" <?php echo (in_array("Is Patient Pregnant?", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Is Patient Pregnant");?>?</option>
                      <option value="Is Patient Breastfeeding?" <?php echo (in_array("Is Patient Breastfeeding?", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Is Patient Breastfeeding");?>?</option>
                      <option value="Patient ID/ART/TRACNET" <?php echo (in_array("Patient ID/ART/TRACNET", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Patient ID/ART/TRACNET");?></option>
                      <option value="Date Of ART Initiation" <?php echo (in_array("Date Of ART Initiation", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Date Of ART Initiation");?></option>
                      <option value="ART Regimen" <?php echo (in_array("ART Regimen", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("ART Regimen");?></option>
                      <option value="Patient consent to SMS Notification?" <?php echo (in_array("Patient consent to SMS Notification?", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Patient consent to SMS Notification");?>?</option>
                      <option value="Patient Mobile Number" <?php echo (in_array("Patient Mobile Number", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Patient Mobile Number");?></option>
                      <option value="Date of Last VL Test" <?php echo (in_array("Date of Last VL Test", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Date of Last VL Test");?></option>
                      <option value="Result Of Last Viral Load" <?php echo (in_array("Result Of Last Viral Load", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Result Of Last Viral Load");?></option>
                      <option value="Viral Load Log" <?php echo (in_array("Viral Load Log", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Viral Load Log");?></option>
                      <option value="Reason For VL Test" <?php echo (in_array("Reason For VL Test", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Reason For VL Test");?></option>
                      <option value="Lab Name" <?php echo (in_array("Lab Name", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Lab Name");?></option>
                      <option value="Lab ID" <?php echo (in_array("Lab ID", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Lab ID");?></option>
                      <option value="VL Testing Platform" <?php echo (in_array("VL Testing Platform", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("VL Testing Platform");?></option>
                      <option value="Specimen type" <?php echo (in_array("Specimen type", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Specimen type");?></option>
                      <option value="Sample Testing Date" <?php echo (in_array("Sample Testing Date", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Sample Testing Date");?></option>
                      <option value="Viral Load Result(copiesl/ml)" <?php echo (in_array("Viral Load Result(copiesl/ml)", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Viral Load Result(copiesl/ml)");?></option>
                      <option value="Log Value" <?php echo (in_array("Log Value", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Log Value");?></option>
                      <option value="Is Sample Rejected" <?php echo (in_array("Is Sample Rejected", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Is Sample Rejected");?></option>
                      <option value="Rejection Reason" <?php echo (in_array("Rejection Reason", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Rejection Reason");?></option>
                      <option value="Reviewed By" <?php echo (in_array("Reviewed By", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Reviewed By");?></option>
                      <option value="Approved By" <?php echo (in_array("Approved By", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Approved By");?></option>
                      <option value="Lab Tech. Comments" <?php echo (in_array("Lab Tech. Comments", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Lab Tech. Comments");?></option>
                      <option value="Status" <?php echo (in_array("Status", $resultArr) ? "selected='selected'" : ""); ?>><?php echo _("Status");?></option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit");?></a>
            <a href="testResultEmailConfig.php" class="btn btn-default"> <?php echo _("Cancel");?></a>
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
<script src="/assets/js/jquery.multi-select.js"></script>
<script src="/assets/js/jquery.quicksearch.js"></script>
<script type="text/javascript">
  $(document).ready(function() {
    $('.search').multiSelect({
      selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='<?php echo _("Enter Field Name");?>'>",
      selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='<?php echo _("Enter Field Name");?>'>",
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
        this.qs1.cache();
        this.qs2.cache();
      },
      afterDeselect: function() {
        this.qs1.cache();
        this.qs2.cache();
      }
    });

    $('#select-all-field').click(function() {
      $('#rs_field').multiSelect('select_all');
      return false;
    });
    $('#deselect-all-field').click(function() {
      $('#rs_field').multiSelect('deselect_all');
      return false;
    });
  });

  $('#rs_email').on('change', function() {
    if (/@gmail\.com$/.test(this.value)) {
      //Perfect
    } else {
      alert('Please enter your gmail account');
      $('#rs_email').val('');
    }
  })

  function validateNow() {
    flag = deforayValidator.init({
      formId: 'editTestResultEmailConfigForm'
    });

    if (flag) {
      $.blockUI();
      document.getElementById('editTestResultEmailConfigForm').submit();
    }
  }
</script>

<?php
require_once(APPLICATION_PATH . '/footer.php');
