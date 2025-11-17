<?php

include(__DIR__ . '/../admin-header.php');
$id = $_SESSION['adminUserId'];
$userQuery = "SELECT * from system_admin where system_admin_id='" . $id . "'";
$userInfo = $db->query($userQuery);
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1> <em class="fa-solid fa-gears"></em> <?php echo _translate("Edit Password"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="/system-admin/edit-config/index.php"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
      <li class="active"><?php echo _translate("Manage Password"); ?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">

    <div class="box box-default">
      <div class="box-header with-border">
        <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _translate("indicates required fields"); ?> &nbsp;</div>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <!-- form start -->
        <form class="form-horizontal" method='post' name='resetEditForm' id='resetEditForm' autocomplete="off" action="resetPasswordProcess.php">
          <input type="hidden" name="userId" id="userId" value="<?php echo base64_encode((string) $userInfo[0]['system_admin_id']); ?>" />
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="password" class="col-lg-4 control-label"><?php echo _translate("Password"); ?> <span class="mandatory">*</span></label>
                  <div class="col-lg-7">
                    <input type="password" class="form-control ppwd isRequired" id="confirmPassword" name="password" placeholder="<?php echo _translate('Password'); ?>" title="<?php echo _translate('Please enter the password'); ?>" />
                    <code><?= _translate("Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?></code>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="confirmPassword" class="col-lg-4 control-label"><?php echo _translate("Confirm Password"); ?> <span class="mandatory">*</span></label>
                  <div class="col-lg-7">
                    <input type="password" class="form-control cpwd confirmPassword" id="confirmPassword" name="password" placeholder="<?php echo _translate('Confirm Password'); ?>" title="" />
                  </div>
                </div>
              </div>
            </div>

          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
            <a href="/system-admin/edit-config/index.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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


<script type="text/javascript">
  $(document).ready(function() {});
  pwdflag = true;

  function validateNow() {
    flag = deforayValidator.init({
      formId: 'resetEditForm'
    });

    if (flag) {
      if ($('.ppwd').val() != '') {
        pwdflag = checkPasswordLength();
      }
      if (pwdflag) {
        $.blockUI();
        document.getElementById('resetEditForm').submit();
      }
    }
  }

  function checkPasswordLength() {
    var pwd = $('#confirmPassword').val();
    var regex = /^(?=.*[0-9])(?=.*[a-zA-Z])([a-zA-Z0-9!@#\$%\^\&*\)\(+=. _-]+){8,}$/;
    if (regex.test(pwd) == false) {
      alert("<?= _translate("Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?>");
      $('.ppwd').focus();
    }
    return regex.test(pwd);
  }
</script>
<?php
include(__DIR__ . '/../admin-footer.php');
