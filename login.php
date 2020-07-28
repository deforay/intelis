<?php
session_start();
if (isset($_SESSION['userId'])) {
  header("location:dashboard/index.php");
}
#require_once('../startup.php');
include_once(APPLICATION_PATH . '/includes/MysqliDb.php');
$globalConfigQuery = "SELECT * from global_config where name='logo'";
$configResult = $db->query($globalConfigQuery);
//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
  $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
$shortName = 'Sample Management';
$systemType = "Lab Sample Management Module";
if ($sarr['user_type'] == 'remoteuser') {
  $shortName = 'Sample Tracking';
  $systemType = "Remote Sample Tracking Module";
  $path = 'assets/img/remote-bg.jpg';
} else {
  $path = 'assets/img/bg.jpg';
}
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo $shortName; ?> | Login</title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">


  <?php if (isset($sarr['user_type']) && $sarr['user_type'] == 'remoteuser') { ?>
    <link rel="apple-touch-icon" sizes="180x180" href="/vlsts-icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/vlsts-icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/vlsts-icons/favicon-16x16.png">
    <link rel="manifest" href="/vlsts-icons/site.webmanifest">
  <?php } else { ?>
    <link rel="apple-touch-icon" sizes="180x180" href="/vlsm-icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/vlsm-icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/vlsm-icons/favicon-16x16.png">
    <link rel="manifest" href="/vlsm-icons/site.webmanifest">
  <?php } ?>


  <!-- Bootstrap 3.3.6 -->
  <link rel="stylesheet" href="/assets/css/fonts.css">
  <link rel="stylesheet" href="/assets/css/bootstrap.min.css">

  <!-- Theme style -->
  <link rel="stylesheet" href="/assets/css/AdminLTE.min.css">
  <link href="/assets/css/deforayModal.css" rel="stylesheet" />
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
  <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
  <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
  <!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
  <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->
  <script type="text/javascript" src="assets/js/jquery.min.js"></script>
</head>

<body class="">
  <div class="container-fluid">
    <?php
    $filePath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'login-logos';


    if (isset($configResult[0]['value']) && trim($configResult[0]['value']) != "" && file_exists('uploads' . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $configResult[0]['value'])) {
    ?>
      <div style="margin-top:15px;float:left;">
        <img src="/uploads/logo/<?php echo $configResult[0]['value']; ?>" alt="Logo image" style="max-width:120px;">
      </div>
    <?php
    }

    if (is_dir($filePath) && count(scandir($filePath)) > 2) {
      $dir = scandir($filePath);
      $loginLogoFiles = array();
      foreach ($dir as $fileName) {
        if ($fileName != '.' && $fileName != '..') {
          $loginLogoFiles[] = $fileName;
        }
      }
    ?>
      <div style="margin-top:15px;float:left;">
        <?php foreach ($loginLogoFiles as $fileName) { ?>
          &nbsp;<img src="/uploads/login-logos/<?php echo $fileName; ?>" alt="Logo image" style="max-width:80px;">
        <?php }  ?>
      </div>
    <?php
    }

    ?>
    <div id="loginbox" style="margin-top:20px;margin-bottom:70px;float:right;margin-right:10px;" class="mainbox col-md-3 col-sm-8 ">
      <div class="panel panel-default" style="opacity: 0.93;">
        <div class="panel-heading">
          <div class="panel-title"><?php echo $systemType; ?></div>
        </div>

        <div style="padding-top:10px;" class="panel-body">
          <div style="display:none" id="login-alert" class="alert alert-danger col-sm-12"></div>
          <form id="loginForm" name="loginForm" class="form-horizontal" role="form" method="post" action="loginProcess.php" onsubmit="validateNow();return false;">
            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
              <input id="login-username" type="text" class="form-control isRequired" name="username" value="" placeholder="User Name" title="Please enter the user name">
            </div>

            <div style="margin-bottom: 5px" class="input-group">
              <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
              <input id="login-password" type="password" class="form-control isRequired" name="password" placeholder="Password" title="Please enter the password">
            </div>

            <div style="margin-top:10px" class="form-group">
              <!-- Button -->
              <div class="col-sm-12 controls">
                <button class="btn btn-lg btn-success btn-block" onclick="validateNow();return false;">Login</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <div style="padding:1% 2%;width:100%;position:absolute;bottom:1.5%;color:#fff;background:rgba(0,0,0,0);">
    <!-- <span>
        <a id="download-form" href="#" style="color:#fff;text-decoration:underline;">Download VL Form</a>
        <select id="country" name="country" class="form-control" style="width:200px;display:none;">
            <option value=""> -- Select Country -- </option>
            <option value="6">South Sudan</option>
            <option value="5">Rwanda</option>
            <option value="3">DRC</option>
            <option value="4">Zambia</option>
        </select>
        <a id="download" href="#" style="color:#fff;text-decoration:underline;display:none;"><h5>Click here to Download</h5></a>
        </span> -->
    <span class="pull-right" style="font-weight:bold;">v <?php echo VERSION; ?></span>
  </div>
  <script src="/assets/js/deforayValidation.js"></script>
  <script src="/assets/js/jquery.blockUI.js"></script>
  <script type="text/javascript">
    function validateNow() {
      flag = deforayValidator.init({
        formId: 'loginForm'
      });

      if (flag) {
        document.getElementById('loginForm').submit();
      }
    }

    $(document).ready(function() {
      <?php if ($recencyConfig['crosslogin']) { ?>
        if (sessionStorage.getItem("crosslogin") == "true") {
          <?php $_SESSION['logged'] = false; ?>
          sessionStorage.setItem("crosslogin", "false");
          $('<iframe src="<?php echo rtrim($recencyConfig['url'], "/") . '/logout'; ?>" frameborder="0" scrolling="no" id="myFrame" style="display:none;"></iframe>').appendTo('body');
        }
      <?php }
      if (isset($_SESSION['alertMsg']) && trim($_SESSION['alertMsg']) != "") { ?>
        alert('<?php echo $_SESSION['alertMsg']; ?>');
      <?php $_SESSION['alertMsg'] = '';
        unset($_SESSION['alertMsg']);
      } ?>
    });

    $('#download-form').click(function() {
      $('#download-form').hide(400);
      $('#country').show(400);
    });

    $('#country').change(function() {
      if ($('#country').val() != '') {
        $('#download').show(400);
        if ($('#country').val() == 3) {
          $('#download').attr('onclick', 'downloadVLForm("drc")');
        } else if ($('#country').val() == 4) {
          $('#download').attr('onclick', 'downloadVLForm("zambia")');
        } else if ($('#country').val() == 5) {
          $('#download').attr('onclick', 'downloadVLForm("rwanda")');
        } else if ($('#country').val() == 6) {
          $('#download').attr('onclick', 'downloadVLForm("south-sudan")');
        } else {
          $('#download').removeAttr('onclick');
        }
      } else {
        $('#download').hide(400);
        $('#download').removeAttr('onclick');
      }
    });

    function downloadVLForm(country) {
      $.blockUI();
      var downloadURL = '#';
      if (country == 'drc') {
        $.unblockUI();
        window.open('/uploads/vl-drc-form.pdf', '_blank');
      } else if (country == 'zambia') {
        $.unblockUI();
        window.open('/uploads/vl-zambia-form.pdf', '_blank');
      } else if (country == 'rwanda') {
        $.unblockUI();
        window.open('/uploads/vl-rwanda-form.pdf', '_blank');
      } else if (country == 'south-sudan') {
        $.unblockUI();
        window.open('/uploads/vl-south-sudan-form.pdf', '_blank');
      }
    }
  </script>
</body>

</html>