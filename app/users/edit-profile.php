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
        <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate("indicates required fields"); ?> &nbsp;</div>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <!-- form start -->
        <form class="form-horizontal" method='post' name='userEditForm' id='userEditForm' autocomplete="off" action="edit-profile-helper.php">
          <div class="box-body">
            <div class="row">
              <div class="col-md-12">
                <div class="form-group">
                  <label for="userName" class="col-lg-2 control-label"><?php echo _translate("Your Full Name"); ?> <span class="mandatory">*</span></label>
                  <div class="col-lg-10">
                    <input type="text" class="form-control isRequired" id="userName" name="userName" placeholder="<?php echo _translate('Your Full Name'); ?>" title="<?php echo _translate('Please enter user name'); ?>" value="<?php echo $userInfo['user_name']; ?>" />
                    <input type="hidden" name="userId" id="userId" value="<?php echo base64_encode((string) $userInfo['user_id']); ?>" />
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="locale" class="col-lg-4 control-label"><?php echo _translate("Preferred Language"); ?> <span class="mandatory">*</span> </label>
                  <div class="col-lg-8">
                    <select class="form-control isRequired" name="userLocale" id="userLocale" title="<?php echo _translate('Please select your Locale'); ?>">
                      <option value=""><?= _translate("-- Select --"); ?></option>
                      <?php
                      $selectedLocale = $userInfo['user_locale'] ?? $globalConfig['app_locale'] ?? 'en_US';
                      foreach ($localeLists as $locale => $localeName) { ?>
                        <option value="<?php echo $locale; ?>" <?php echo ($selectedLocale == $locale) ? 'selected="selected"' : ''; ?>><?= $localeName; ?></option>
                      <?php } ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="email" class="col-lg-4 control-label"><?php echo _translate("Official Email"); ?> </label>
                  <div class="col-lg-8">
                    <input type="text" class="form-control" id="email" name="email" placeholder="<?php echo _translate('Official Email'); ?>" title="<?php echo _translate('Please enter email'); ?>" value="<?php echo $userInfo['email']; ?>" onblur="checkNameValidation('user_details','email',this,'<?php echo "user_id##" . $userInfo['user_id']; ?>','<?php echo _translate("This email id that you entered already exists.Try another email id"); ?>',null)" />
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="phoneNo" class="col-lg-4 control-label"><?php echo _translate("Phone Number"); ?></label>
                  <div class="col-lg-8">
                    <input type="text" class="form-control phone-number" id="phoneNo" name="phoneNo" placeholder="<?php echo _translate('Phone Number'); ?>" title="<?php echo _translate('Please enter phone number'); ?>" value="<?php echo $userInfo['phone_number']; ?>" />
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
                      <input type="password" class="form-control ppwd" id="password" name="password" placeholder="<?php echo _translate('Password'); ?>" title="<?php echo _translate('Please enter the password'); ?>" />
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
              <div class="col-md-6">
                <div class="form-group">
                  <label for="confirmPassword" class="col-lg-4 control-label"><?php echo _translate("Confirm Password"); ?></label>
                  <div class="col-lg-8">
                    <input type="password" class="form-control cpwd confirmPassword" id="confirmPassword" name="password" placeholder="<?php echo _translate('Confirm Password'); ?>" title="" />
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
      <div class="box-body">
        <h4 style="font-weight:bold;border-bottom:1px solid #ccc;padding-bottom:10px;"><?= _translate("Recent Login Attempts"); ?></h4>
        <table aria-describedby="table" class="table table-striped table-bordered table-hover" id="loginAttempts">
          <thead>
            <tr>

              <th><?= _translate("Login Attempt Date and Time"); ?></th>
              <th><?= _translate("Login ID"); ?></th>
              <th><?= _translate("IP Address"); ?></th>
              <th><?= _translate("Browser"); ?></th>
              <th><?= _translate("Operating System"); ?></th>
              <th><?= _translate("Login Status"); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php
            if (count($data) > 0) {
              foreach ($data as $project) {

            ?>
                <tr>

                  <td><?php echo DateUtility::humanReadableDateFormat($project['login_attempted_datetime']); ?></td>
                  <td><?php echo $project['login_id']; ?></td>
                  <td><?php echo $project['ip_address']; ?></td>
                  <td><?php echo $project['browser']; ?></td>
                  <td><?php echo $project['operating_system']; ?></td>
                  <td><?php echo $project['login_status']; ?></td>
                </tr>
              <?php
              }
              ?>

            <?php } else {
            ?>

              <tr>
                <td class="center" colspan="6"><?= _translate("No record found"); ?></td>
              </tr>
            <?php
            } ?>
          </tbody>
        </table>
      </div>
    </div>
    <!-- /.box -->

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
      formId: 'userEditForm'
    });

    if (flag) {
      if ($('.ppwd').val() != '') {
        pwdflag = checkPasswordLength();
      }
      if (pwdflag) {
        $.blockUI();
        document.getElementById('userEditForm').submit();
      }
    }
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
