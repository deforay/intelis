<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\UsersService;

require_once APPLICATION_PATH . '/header.php';


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);


$globalConfig = $general->getGlobalConfig();

$localeLists = $general->getLocaleList((int)($globalConfig['vl_form'] ?? 0));


$userInfo = $usersService->getUserByID($_SESSION['userId']);

$db->orderBy("login_attempted_datetime");
$db->where("login_id", $_SESSION['loginId']);
$db->orWhere('user_id', $_SESSION['userId']);
$data = $db->get("user_login_history", 25);

//echo $totpService->isEnabled(). ' --------- '.$totpService->isActive($_SESSION['userId']); die;
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1> <em class="fa-solid fa-gears"></em> <?php echo _translate("Edit Profile"); ?></h1>
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
                  <label for="confirmPassword" class="col-lg-4 control-label"><?php echo _translate("Current Password"); ?></label>
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
                  <label for="password" class="col-lg-4 control-label"><?php echo _translate("Password"); ?></label>
                  <div class="col-lg-7">
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

<!-- $(document).ready(function() {
$('#example').DataTable({
responsive: true
});
}); -->
<script type="text/javascript">
  $(document).ready(function() {
    $('#loginAttempts').DataTable({
      responsive: true,
      ordering: false
    });
  });
  pwdflag = true;

  function validateNow() {
    flag = deforayValidator.init({
      formId: 'changePasswordForm'
    });

    if (flag) {
      currentPwd = password_verify();
      alert(currentPwd);
      if ($('.ppwd').val() != '') {
        pwdflag = checkPasswordLength();
      }
      if (pwdflag && currentPwd == true) {
        $.blockUI();
        document.getElementById('changePasswordForm').submit();
      }
    }
  }

  function password_verify()
  {
     const currentPassword = $('#currentPassword').val(); 
     const newPassword = $('#newPassword').val(); 
     const confirmPassword = $('#confirmPassword').val(); 
     $('#currentPasswordError').text('');
      if (!currentPassword) {
          $('#currentPasswordError').text('Please enter your current password.'); 
          return false; 
        }
      if (!newPassword) { 
        alert('Please enter a new password.'); 
        return false; 
      }
      if (newPassword !== confirmPassword) { 
        alert('New password and confirm password do not match.');  
        return false; 
      } 
       $.ajax({
          url: '/includes/verify-current-password.php', 
          type: 'POST', 
          dataType: 'json',
           data: { currentPassword: currentPassword }, 
           success: function (response) { 
            if (response.success) { // Current password is correct. // Now submit the actual change-password form.
             return true; 
            } 
            else 
              { $('#currentPasswordError').text( response.message || 'Current password is incorrect.' ); 
            $('#currentPassword').focus(); return false; } }, 
            error: function () { 
              $('#currentPasswordError').text( 'Unable to validate current password. Please try again.' ); return false;} 
            });
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
    document.getElementById('password').type = "text";
    document.getElementById('confirmPassword').type = "text";
    const data = await $.post("/includes/generate-password.php", {
      size: 32
    });
    $("#password").val(data);
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
