<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
// Get locale directory list
$localeLists = $general->getLocaleList('all');

$formQuery = "SELECT * FROM s_available_country_forms ORDER by form_name ASC";
$formResult = $db->query($formQuery);

$globalConfigQuery = "SELECT * from global_config";
$configResult = $db->query($globalConfigQuery);
$arr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
  $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}

$db->where("login_id", NULL, 'IS NOT');
$count = $db->getValue("user_details", "count(*)");
if ($count != 0) {
  header("Location:/login/login.php");
}

$shortName = _translate('Lab Sample Management System');

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
  <title><?= _translate("Register Admin User"); ?> | VLSM</title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <meta name="viewport" content="width=1024">

  <?php
  $iconType = !empty($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'remoteuser' ? 'vlsts' : 'vlsm';
  ?>

  <link rel="apple-touch-icon" sizes="180x180" href="/assets/<?= $iconType; ?>-icons/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/<?= $iconType; ?>-icons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/assets/<?= $iconType; ?>-icons/favicon-16x16.png">
  <link rel="manifest" href="/assets/<?= $iconType; ?>-icons/site.webmanifest">


  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/fonts.css" />
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui.min.css" />
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui-timepicker-addon.css" />
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/bootstrap.min.css">
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/font-awesome.min.css">
  <link rel="stylesheet" media="all" type="text/css" href="/assets/plugins/datatables/dataTables.bootstrap.css">
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/AdminLTE.min.css">
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/skins/_all-skins.min.css">
  <link rel="stylesheet" media="all" type="text/css" href="/assets/plugins/daterangepicker/daterangepicker.css" />
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/select2.min.css" />
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/deforayModal.css" />
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery.fastconfirm.css" />
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/components-rounded.min.css">
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/select2.live.min.css" />
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/style.css?v=<?= filemtime(WEB_ROOT . "/assets/css/style.css") ?>" />

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
          <div class="panel-title"><?= _translate("Register Admin User"); ?></div>
        </div>

        <div style="padding-top:10px;" class="panel-body">
          <div style="display:none" id="login-alert" class="alert alert-danger col-sm-12"></div>
          <form id="registerForm" name="registerForm" class="form-horizontal" role="form" method="post" action="/setup/registerProcess.php" onsubmit="validateNow();return false;">

            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-flag"></em></span>
              <select class="form-control isRequired readPage select2" name="vl_form" id="vl_form" title="<?php echo _translate('Please select the viral load form'); ?>">
                <option value=""></option>
                <?php
                foreach ($formResult as $val) {
                ?>
                  <option value="<?php echo $val['vlsm_country_id']; ?>" <?php echo ($val['vlsm_country_id'] == $arr['vl_form']) ? "selected='selected'" : "" ?>><?php echo $val['form_name']; ?></option>
                <?php
                }
                ?>
              </select>
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-clock"></em></span>
              <select class="form-control readPage select2 isRequired" id="default_time_zone" name="default_time_zone" placeholder="<?php echo _translate('Timezone'); ?>" title="<?php echo _translate('Please choose Timezone'); ?>">
                <option value=""></option>
                <?php
                $timezone_identifiers = DateTimeZone::listIdentifiers();

                foreach ($timezone_identifiers as $value) {
                ?>
                  <option <?= ($arr['default_time_zone'] == $value ? 'selected=selected' : ''); ?> value='<?= $value; ?>'> <?= $value; ?></option>;
                <?php
                }

                ?>
              </select>
            </div>

            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-language"></em></span>
              <select class="form-control isRequired readPage" name="app_locale" id="app_locale" title="<?php echo _translate('Please select the System Locale'); ?>">
                <option value=""><?= _translate("-- Choose System Language --"); ?></option>
                <?php foreach ($localeLists as $locale => $localeName) { ?>
                  <option value="<?php echo $locale; ?>" <?php echo (isset($arr['app_locale']) && $arr['app_locale'] == $locale) ? 'selected="selected"' : ''; ?>><?= $localeName; ?></option>
                <?php } ?>
              </select>
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-envelope"></em></span>
              <input id="login-email" type="text" class="form-control isRequired" name="email" value="" placeholder="<?= _translate("Email ID"); ?>" title="Please enter your email id">
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-user"></em></span>
              <input id="login-username" type="text" class="form-control isRequired" name="userName" value="" placeholder="<?= _translate("Full Name"); ?>" title="Please enter your name">
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-right-to-bracket"></em></span>
              <input id="login-id" type="text" class="form-control isRequired" name="loginId" value="" placeholder="<?= _translate("Login ID"); ?>" title="Please enter your login id">
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-lock"></em></span>
              <input type="password" class="form-control ppwd isRequired" id="confirmPassword" name="password" placeholder="<?= _translate("Password"); ?>" title="Please enter your password" />
            </div>
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><em class="fa-solid fa-lock"></em></span>
              <input type="password" class="form-control cpwd confirmPassword" id="confirmPassword" name="password" placeholder="<?= _translate("Confirm Password"); ?>" title="" />
            </div>
            <div style="margin-top:10px" class="form-group">
              <!-- Button -->
              <div class="col-sm-12 controls">
                <button class="btn btn-lg btn-primary btn-block" onclick="validateNow();return false;"><?= _translate("Create User"); ?></button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <script type="text/javascript" src="/assets/js/select2.min.js"></script>
  <script src="/assets/js/deforayValidation.js"></script>
  <script src="/assets/js/jquery.blockUI.js"></script>
  <script type="text/javascript">
    let pwdflag = true;

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
      let pwd = $('#confirmPassword').val();
      let regex = /^(?=.*[0-9])(?=.*[a-zA-Z])([a-zA-Z0-9!@#\$%\^\&*\)\(+=. _-]+){8,}$/;
      if (regex.test(pwd) == false) {
        alert("<?= _translate("Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?>");
        $('.ppwd').focus();
      }
      return regex.test(pwd);
    }
    $(document).ready(function() {
      <?php
      if (isset($_SESSION['alertMsg']) && trim((string) $_SESSION['alertMsg']) != "") {
      ?>
        alert("<?php echo $_SESSION['alertMsg']; ?>");
      <?php
        $_SESSION['alertMsg'] = '';
        unset($_SESSION['alertMsg']);
      }
      ?>

      $('#vl_form').select2({
        placeholder: "<?= _translate("-- Choose Country of Installation --", true); ?>",
      });
      $('#default_time_zone').select2({
        placeholder: "<?= _translate("-- Select Timezone --", true); ?>",
      });
    });
  </script>
</body>

</html>
