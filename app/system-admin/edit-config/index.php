<?php

use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = _("Edit Configuration");

require_once(APPLICATION_PATH . '/system-admin/admin-header.php');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$arr = $general->getGlobalConfig();

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

//get labs
$fResult = $facilitiesService->getAllFacilities(2);
?>
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-sharp fa-solid fa-gears"></em> <?php echo _("Edit System Configuration"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="index.php"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
      <li class="active"><?php echo _("Manage System Config"); ?></li>
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
        <form class="form-horizontal" method='post' name='editSystemConfigForm' id='editSystemConfigForm' enctype="multipart/form-data" autocomplete="off" action="systemConfigHelper.php">
          <div class="box-body">
            <div class="panel panel-default">
              <div class="panel-heading">
                <h3 class="panel-title"><?php echo _("Instance Settings"); ?></h3>
              </div>
              <div class="panel-body">
                <div class="row">
                  <div class="col-md-7">
                    <div class="form-group">
                      <label for="sc_user_type" class="col-lg-4 control-label"><?php echo _("Instance Type"); ?> <span class="mandatory">*</span></label>
                      <div class="col-lg-8">
                        <select type="text" class="form-control" id="sc_user_type" name="sc_user_type" placeholder="<?php echo _('Instance Type'); ?>" title="<?php echo _('Please choose instance type'); ?>" onchange="enableLab();">
                          <option value="standalone" <?php echo ('standalone' == $arr['sc_user_type']) ? "selected='selected'" : "" ?>><?php echo _("Standalone"); ?></option>
                          <option value="vluser" <?php echo ('vluser' == $arr['sc_user_type']) ? "selected='selected'" : "" ?>><?php echo _("Lab Instance"); ?></option>
                          <option value="remoteuser" <?php echo ('remoteuser' == $arr['sc_user_type']) ? "selected='selected'" : "" ?>><?php echo _("Remote Instance"); ?></option>
                        </select>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-7 labName" style="<?php echo ($arr['sc_user_type'] == 'vluser') ? 'display:show' : 'display:none'; ?>">
                    <div class="form-group">
                      <label for="sc_testing_lab_id" class="col-lg-4 control-label"><?php echo _("Lab Name"); ?></label>
                      <div class="col-lg-8">
                        <select class="form-control" name="sc_testing_lab_id" id="sc_testing_lab_id" title="<?php echo _('Please select the lab name'); ?>">
                          <option value=""><?php echo _("-- Select --"); ?></option>
                          <?php foreach ($fResult as $labName) { ?>
                            <option value="<?php echo $labName['facility_id']; ?>" <?php echo ($labName['facility_id'] == $arr['sc_testing_lab_id']) ? "selected='selected'" : "" ?>><?php echo $labName['facility_name']; ?></option>
                          <?php } ?>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="panel panel-default">
              <div class="panel-heading">
                <h3 class="panel-title"><?php echo _("SMTP Settings"); ?></h3>
              </div>
              <div class="panel-body">
                <div class="row">
                  <div class="col-md-7">
                    <div class="form-group">
                      <label for="sup_email" class="col-lg-4 control-label">Email </label>
                      <div class="col-lg-8">
                        <input type="text" class="form-control isEmail" id="sup_email" name="sup_email" placeholder="Email" title="Please enter email" value="<?php echo $arr['sup_email']; ?>">
                      </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-7">
                    <div class="form-group">
                      <label for="sup_password" class="col-lg-4 control-label">Password </label>
                      <div class="col-lg-8">
                        <input type="text" class="form-control" id="sup_password" name="sup_password" placeholder="Password" title="Please enter password" value="<?php echo $arr['sup_password']; ?>">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
          <div class="box-footer">
            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit"); ?></a>
            <a href="index.php" class="btn btn-default"> <?php echo _("Reload"); ?></a>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>
<script>
  $(document).ready(function() {
    enableLab();
  });

  function enableLab() {
    if ($("#sc_user_type").val() == 'vluser') {
      $(".labName").show();
      $("#sc_testing_lab_id").addClass("isRequired").css('pointer-events', '');
    } else {
      $(".labName").hide();
      $("#sc_testing_lab_id").removeClass("isRequired").css('pointer-events', 'none').val('');
    }
  }

  function validateNow() {
    flag = deforayValidator.init({
      formId: 'editSystemConfigForm'
    });
    if (flag) {
      $.blockUI();
      document.getElementById('editSystemConfigForm').submit();
    }
  }
</script>
<?php
require_once(APPLICATION_PATH . '/system-admin/admin-footer.php');
