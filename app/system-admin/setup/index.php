<?php

use App\Services\CommonService;
use App\Helpers\PassphraseHelper;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);


$countSql = "SELECT COUNT(*) as totalCount FROM system_admin";
$adminCount = (int) $db->rawQueryOne($countSql)['totalCount'];
if ($adminCount > 0) {
  header("Location:/system-admin/login/login.php");
}

$path = '/assets/img/remote-bg.jpg';

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
try {
  $myfile = fopen(CORE\SYSADMIN_SECRET_KEY_FILE, "w+");
  if ($myfile === false) {
    throw new SystemException("Unable to create secret key file. Please check permissions on the directory.", 500);
  }
} catch (Throwable $e) {
  throw new SystemException($e->getMessage(), $e->getCode(), $e);
}

$randomString = PassphraseHelper::generate();
fwrite($myfile, $randomString);
fclose($myfile);

?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['APP_LOCALE'] ?? 'en_US'; ?>">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>InteLIS | <?php echo _translate("New User Registration"); ?></title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <!-- Bootstrap 3.3.6 -->
  <link rel="stylesheet" href="/assets/css/fonts.css">
  <link rel="stylesheet" href="/assets/css/bootstrap.min.css">

  <!-- Theme style -->
  <link rel="stylesheet" href="/assets/css/AdminLTE.min.css">
  <link href="/assets/css/deforayModal.css" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/font-awesome.min.css">
  <!-- iCheck -->
  <style>
    body {
      background: #F6F6F6;
      background: #000;

      background: url("<?php echo $path; ?>") center;
      background-size: cover;
      background-repeat: no-repeat;
    }
  </style>

  <script type="text/javascript" src="/assets/js/jquery.min.js"></script>
</head>

<body class="">
  <div class="container-fluid" style="margin-top:10em;">
    <div id="loginbox" style="margin-left:35%;padding:0;" class="mainbox col-md-4 col-sm-8 ">
      <div class="panel panel-default" style="opacity: 0.93;">
        <div class="panel-heading">
          <div class="panel-title"><?= _translate("Register new System Admin"); ?></div>
        </div>

        <div style="padding-top:10px;" class="panel-body">
          <div style="display:none" id="login-alert" class="alert alert-danger col-sm-12"></div>
          <form id="registerForm" name="registerForm" class="form-horizontal" autocomplete="no" method="post"
            action="/system-admin/setup/registerProcess.php" onsubmit="validateNow();return false;">
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-key"></em></span>
              <input type="text" class="form-control isRequired" id="secretKey" name="secretKey"
                placeholder="<?= _translate('Secret Key'); ?>" title="" autocomplete="no" />
            </div>
            <!-- <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-user"></em></span>
              <input id="login-username" type="text" class="form-control isRequired" name="username" value="" placeholder="<?= _translate('User Name'); ?>" title="<?php echo _translate('Please enter the user name'); ?>" autocomplete="no">
            </div> -->
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-envelope"></em></span>
              <input id="login-email" type="text" class="form-control isRequired" name="email" value=""
                placeholder="<?= _translate('Email ID'); ?>"
                title="<?php echo _translate('Please enter the email id'); ?>">
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-right-to-bracket"></em></span>
              <input id="login-id" type="text" class="form-control isRequired" name="loginid" value=""
                placeholder="<?= _translate('Login ID'); ?>"
                title="<?php echo _translate('Please enter the login id'); ?>">
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-lock"></em></span>
              <input type="password" class="form-control ppwd isRequired" id="confirmPassword" name="password"
                placeholder="<?php echo _translate('Password'); ?>"
                title="<?php echo _translate('Please enter the password'); ?>" />
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-lock"></em></span>
              <input type="password" class="form-control cpwd confirmPassword" id="confirmPassword" name="password"
                placeholder="<?php echo _translate('Confirm Password'); ?>" title="" />
            </div>
            <div style="margin-top:10px" class="form-group">
              <!-- Button -->
              <div class="col-sm-12 controls">
                <button class="btn btn-lg btn-primary btn-block"
                  onclick="validateNow();return false;"><?= _translate("Submit"); ?></button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <script src="/assets/js/deforayValidation.js"></script>
  <script src="/assets/js/jquery.blockUI.js"></script>
  <script type="text/javascript">
    pwdflag = true;

    function validateNow() {
      flag = deforayValidator.init({
        formId: 'registerForm'
      });

      if (flag) {
        if ($('.ppwd').val() != '') {
          pwdflag = checkPasswordLength();
        }
        if (pwdflag) {
          $.blockUI();
          document.getElementById('registerForm').submit();
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
    $(document).ready(function () {
      <?php
      if (isset($_SESSION['alertMsg']) && trim((string) $_SESSION['alertMsg']) !== "") {
        ?>
        alert("<?php echo $_SESSION['alertMsg']; ?>");
        <?php
        $_SESSION['alertMsg'] = '';
        unset($_SESSION['alertMsg']);
      }
      ?>
    });
  </script>
</body>

</html>