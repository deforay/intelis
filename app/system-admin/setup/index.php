<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}
$adminCount = $db->rawQuery("SELECT * FROM system_admin as ud");
if (count($adminCount) != 0) {
  header("Location:/system-admin/login/login.php");
}

$path = '/assets/img/remote-bg.jpg';
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$myfile = fopen(APPLICATION_PATH . "/system-admin/secretKey.txt", "w+") or die("Unable to open file!");

$randomString = $general->generateRandomString();
fwrite($myfile, $randomString);
fclose($myfile);

?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['APP_LOCALE'] ?? 'en_US'; ?>">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo _("VLSM"); ?> | <?php echo _("New User Registration"); ?></title>
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
  <div class="container-fluid">
    <div id="loginbox" style="margin-top:140px;margin-bottom:70px;float:right;margin-right:509px;" class="mainbox col-md-3 col-sm-8 ">
      <div class="panel panel-default" style="opacity: 0.93;">
        <div class="panel-heading">
          <div class="panel-title"><?php echo _("Resgiter new System Admin"); ?></div>
        </div>

        <div style="padding-top:10px;" class="panel-body">
          <div style="display:none" id="login-alert" class="alert alert-danger col-sm-12"></div>
          <form id="registerForm" name="registerForm" class="form-horizontal" role="form" method="post" action="registerProcess.php" onsubmit="validateNow();return false;">
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-key"></em></span>
              <input type="text" class="form-control isRequired" id="secretKey" name="secretKey" placeholder="<?php echo _('Secret Key'); ?>" title="" />
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-user"></em></span>
              <input id="login-username" type="text" class="form-control isRequired" name="username" value="" placeholder="<?php echo _('User Name'); ?>" title="<?php echo _('Please enter the user name'); ?>">
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-envelope"></em></span>
              <input id="login-email" type="text" class="form-control isRequired" name="email" value="" placeholder="<?php echo _('Email Id'); ?>" title="<?php echo _('Please enter the email id'); ?>">
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-right-to-bracket"></em></span>
              <input id="login-id" type="text" class="form-control isRequired" name="loginid" value="" placeholder="<?php echo _('Login Id'); ?>" title="<?php echo _('Please enter the login id'); ?>">
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-lock"></em></span>
              <input type="password" class="form-control ppwd isRequired" id="confirmPassword" name="password" placeholder="<?php echo _('Password'); ?>" title="<?php echo _('Please enter the password'); ?>" />
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-lock"></em></span>
              <input type="password" class="form-control cpwd confirmPassword" id="confirmPassword" name="password" placeholder="<?php echo _('Confirm Password'); ?>" title="" />
            </div>
            <div style="margin-top:10px" class="form-group">
              <!-- Button -->
              <div class="col-sm-12 controls">
                <button class="btn btn-lg btn-primary btn-block" onclick="validateNow();return false;"><?php echo _("Submit"); ?></button>
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
        alert("<?= _("Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?>");
        $('.ppwd').focus();
      }
      return regex.test(pwd);
    }
    $(document).ready(function() {
      <?php
      if (isset($_SESSION['alertMsg']) && trim($_SESSION['alertMsg']) != "") {
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
