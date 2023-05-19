<?php

use App\Registries\ContainerRegistry;

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');



$db->where("login_id", NULL, 'IS NOT');
$count = $db->getValue("user_details", "count(*)");
if ($count != 0) {
  header("Location:/login/login.php");
}

$shortName = _('Lab Sample Management System');

if ($_SESSION['instanceType'] == 'remoteuser') {
  $shortName = 'Sample Tracking';
  $systemType = "Remote Sample Tracking Module";
  $path = '/assets/img/remote-bg.jpg';
} else {
  $path = '/assets/img/bg.jpg';
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['APP_LOCALE'] ?? 'en_US'; ?>">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?= _("Register Admin User"); ?> | VLSM</title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

  <?php if (!empty($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'remoteuser') { ?>
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/vlsts-icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/vlsts-icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/vlsts-icons/favicon-16x16.png">
    <link rel="manifest" href="/assets/vlsts-icons/site.webmanifest">
  <?php } else { ?>
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/vlsm-icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/vlsm-icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/vlsm-icons/favicon-16x16.png">
    <link rel="manifest" href="/assets/vlsm-icons/site.webmanifest">
  <?php } ?>


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

    a {
      cursor: pointer;
    }
  </style>

  <script type="text/javascript" src="/assets/js/jquery.min.js"></script>
</head>

<body class="">
  <div class="container-fluid">
    <div id="loginbox" style="margin-top:80px;float:right;" class="mainbox col-md-4 col-sm-8 ">
      <div class="panel panel-default" style="opacity: 0.98;">
        <div class="panel-heading">
          <div class="panel-title"><?= _("Register Admin User"); ?></div>
        </div>

        <div style="padding-top:10px;" class="panel-body">
          <div style="display:none" id="login-alert" class="alert alert-danger col-sm-12"></div>
          <form id="registerForm" name="registerForm" class="form-horizontal" role="form" method="post" action="/setup/registerProcess.php" onsubmit="validateNow();return false;">
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-user"></em></span>
              <input id="login-username" type="text" class="form-control isRequired" name="userName" value="" placeholder="<?= _("User Name"); ?>" title="Please enter your name">
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-envelope"></em></span>
              <input id="login-email" type="text" class="form-control isRequired" name="email" value="" placeholder="<?= _("Email ID"); ?>" title="Please enter your email id">
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-right-to-bracket"></em></span>
              <input id="login-id" type="text" class="form-control isRequired" name="loginId" value="" placeholder="<?= _("Login ID"); ?>" title="Please enter your login id">
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-lock"></em></span>
              <input type="password" class="form-control ppwd isRequired" id="confirmPassword" name="password" placeholder="<?= _("Password"); ?>" title="Please enter your password" />
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-lock"></em></span>
              <input type="password" class="form-control cpwd confirmPassword" id="confirmPassword" name="password" placeholder="<?= _("Confirm Password"); ?>" title="" />
            </div>
            <div style="margin-top:10px" class="form-group">
              <!-- Button -->
              <div class="col-sm-12 controls">
                <button class="btn btn-lg btn-primary btn-block" onclick="validateNow();return false;"><?= _("Create User"); ?></button>
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
