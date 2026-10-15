<?php

require_once APPLICATION_PATH . '/header.php';

?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1> <em class="fa-solid fa-key"></em> <?php echo _translate("Change Password"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
      <li class="active"><?php echo _translate("Users"); ?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">

    <div class="box box-default">
      <div class="box-header with-border">
        <div class="pull-right" style="font-size:15px;">
     
        <span class="mandatory">*</span> <?= _translate("indicates required fields"); ?> &nbsp;</div>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <!-- form start -->
        <form class="form-horizontal" method='post' name='changePasswordForm' id='changePasswordForm' autocomplete="off" action="change-password-helper.php">
          <div class="box-body">
           
            <div class="row">
             <div class="col-md-6">
                <div class="form-group">
                  <label for="currentPassword" class="col-lg-4 control-label"><?php echo _translate("Current Password"); ?></label>
                  <div class="col-lg-8">
                    <input type="password" class="form-control" id="currentPassword" name="currentPassword" placeholder="<?php echo _translate('Current Password'); ?>" title="" />
                    <div id="currentPasswordError" style="color: red;"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="newPassword" class="col-lg-4 control-label"><?php echo _translate("Password"); ?></label>
                  <div class="col-lg-8">
                    <div class="input-group">
                      <input type="password" class="form-control" id="newPassword" name="newPassword" placeholder="<?php echo _translate('Password'); ?>" title="<?php echo _translate('Please enter the password'); ?>" />
                      <span class="input-group-btn">
                        <button class="btn btn-default" type="button" id="generatePassword" onclick="passwordType();" title="Generate Password">
                          <i class="fa fa-random"></i> <?= _translate("Generate"); ?>
                        </button>

                      </span>
                    </div>
                    <small class="form-text text-muted">
                      <?= _translate("Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?>
                    </small>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
             <div class="col-md-6">
                <div class="form-group">
                  <label for="confirmPassword" class="col-lg-4 control-label"><?php echo _translate("Confirm Password"); ?></label>
                  <div class="col-lg-8">
                    <input type="password" class="form-control ppwd" id="confirmPassword" name="confirmPassword" placeholder="<?php echo _translate('Confirm Password'); ?>" title="" />
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
            <a href="/dashboard/index.php" class="btn btn-default"> <?= _translate("Cancel"); ?></a>
          </div>
          <!-- /.box-footer -->
        </form>
        <!-- /.row -->
      </div>
  

  </section>
  <!-- /.content -->
</div>

<script type="text/javascript">
  pwdflag = true;

  function validateNow() {
    flag = deforayValidator.init({
      formId: 'changePasswordForm'
    });

    if (flag && passwordFieldsValid()) {
      pwdflag = checkPasswordLength();
      if (pwdflag) {
        $.blockUI();
        document.getElementById('changePasswordForm').submit();
      }
    }
  }

  // The current password is checked on the server when the form is submitted
  function passwordFieldsValid() {
    $('#currentPasswordError').text('');
    if (!$('#currentPassword').val()) {
      $('#currentPasswordError').text("<?= _translate("Please enter your current password.", true); ?>");
      $('#currentPassword').focus();
      return false;
    }
    if (!$('#newPassword').val()) {
      alert("<?= _translate("Please enter a new password.", true); ?>");
      return false;
    }
    if ($('#newPassword').val() !== $('#confirmPassword').val()) {
      alert("<?= _translate("New password and confirm password do not match.", true); ?>");
      return false;
    }
    return true;
  }

  function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
    var removeDots = obj.value.replace(/\,/g, "");
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

  function checkPasswordLength() {
    var pwd = $('#confirmPassword').val();
    var regex = /^(?=.*[0-9])(?=.*[a-zA-Z])([a-zA-Z0-9!@#\$%\^\&*\)\(+=. _-]+){8,}$/;
    if (regex.test(pwd) == false) {
      alert("<?= _translate("Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.", true) ?>");
      $('.ppwd').focus();
    }
    return regex.test(pwd);
  }

  async function passwordType() {
    document.getElementById('newPassword').type = "text";
    document.getElementById('confirmPassword').type = "text";
    const data = await $.post("/includes/generate-password.php", {
      size: 32
    });
    $("#newPassword").val(data);
    $("#confirmPassword").val(data);
    try {
      const success = await Utilities.copyToClipboard(data);
      if (success) {
        toast.success("<?= _translate("Password generated and copied to clipboard", true); ?>");
      } else {
        console.log('Failed to copy text');
      }
    } catch (error) {
      console.log(error);
    }
  }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
