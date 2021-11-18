<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$logoName = "<img src='/assets/img/flask.png' style='margin-top:-5px;max-width:22px;'> <span style=''>VLSM</span>";
$smallLogoName = "<img src='/assets/img/flask.png'>";
$systemType = "Viral Load Sample Management";
$skin = "skin-blue";
if (!isset($_SESSION['adminUserId'])) {
  header("location:/login/login.php");
}
?>
<!DOCTYPE html>
<html lang="en-US">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title><?php echo (isset($title) && $title != null && $title != "") ? $title : "VLSM | Viral Load LIS" ?></title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/fonts.css" />

  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui.1.11.0.css" />
  <link rel="stylesheet" media="all" type="text/css" href="/assets/css/jquery-ui-timepicker-addon.css" />

  <!-- Bootstrap 3.3.6 -->
  <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="/assets/css/font-awesome.min.4.5.0.css">

  <!-- Ionicons -->
  <!--<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">-->
  <!-- DataTables -->
  <link rel="stylesheet" href="/assets/plugins/datatables/dataTables.bootstrap.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="/assets/css/AdminLTE.min.css">
  <!-- AdminLTE Skins. Choose a skin from the css/skins
       folder instead of downloading all of them to reduce the load. -->
  <link rel="stylesheet" href="/assets/css/skins/_all-skins.min.css">
  <!-- iCheck -->

  <link href="/assets/plugins/daterangepicker/daterangepicker.css" rel="stylesheet" />

  <link href="/assets/css/select2.min.css" rel="stylesheet" />
  <link href="/assets/css/style.css" rel="stylesheet" />
  <link href="/assets/css/deforayModal.css" rel="stylesheet" />
  <link href="/assets/css/jquery.fastconfirm.css" rel="stylesheet" />

  <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
  <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
  <!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
  <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->
  <!-- jQuery 2.2.3 -->

  <script type="text/javascript" src="/assets/js/jquery.min.js"></script>

  <!-- Latest compiled and minified JavaScript -->

  <script type="text/javascript" src="/assets/js/jquery-ui.1.11.0.js"></script>
  <script src="/assets/js/deforayModal.js"></script>
  <script src="/assets/js/jquery.fastconfirm.js"></script>
  <!--<script type="text/javascript" src="assets/js/jquery-ui-sliderAccess.js"></script>-->
  <style>
    .dataTables_wrapper {
      position: relative;
      clear: both;
      overflow-x: scroll !important;
      overflow-y: visible !important;
      padding: 15px 0 !important;
    }

    .select2-selection__choice__remove {
      color: red !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
      background-color: #00c0ef;
      border-color: #00acd6;
      color: #fff !important;
      font-family: helvetica, arial, sans-serif;
    }
  </style>
</head>

<body class="hold-transition <?php echo $skin; ?> sidebar-mini">
  <div class="wrapper">
    <header class="main-header">
      <!-- Logo -->
      <a href="#" class="logo">
        <!-- mini logo for sidebar mini 50x50 pixels -->
        <span class="logo-mini"><b><?php echo $smallLogoName; ?></b></span>
        <!-- logo for regular state and mobile devices -->
        <span class="logo-lg" style="font-weight:bold;"><?php echo $logoName; ?></span>
      </a>
      <!-- Header Navbar: style can be found in header.less -->
      <nav class="navbar navbar-static-top">
        <!-- Sidebar toggle button-->
        <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
          <span class="sr-only">Toggle navigation</span>
        </a>
        <ul class="nav navbar-nav">
          <li>
            <a href="javascript:void(0);return false;"><span style="text-transform: uppercase;font-weight:600;"><?php echo $systemType; ?></span></a>
          </li>
        </ul>
        <div class="navbar-custom-menu">
          <ul class="nav navbar-nav">
            <li class="dropdown user user-menu">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                <img src="<?php echo DOMAIN; ?>/assets/img/default-user.png" class="user-image" alt="User Image">
                <span class="hidden-xs"><?php if (isset($_SESSION['adminUserName'])) {
                                          echo $_SESSION['adminUserName'];
                                        } ?></span>
              </a>
              <ul class="dropdown-menu">
                <!-- Menu Footer-->
                <li class="user-footer">
									<a href="/system-admin/edit-config/resetPassword.php" class="">Change Password</a>
								</li>
                <li class="user-footer">
                  <a href="/system-admin/login/logout.php" class="">Sign out</a>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </nav>
    </header>
    <!-- Left side column. contains the logo and sidebar -->
    <aside class="main-sidebar">
      <!-- sidebar: style can be found in sidebar.less -->
      <section class="sidebar">
        <!-- sidebar menu: : style can be found in sidebar.less -->
        <!-- Sidebar user panel -->
        <ul class="sidebar-menu">
          <li class="treeview manage">
            <a href="#">
              <i class="fa fa-gears"></i>
              <span>Admin</span>
              <span class="pull-right-container">
                <i class="fa fa-angle-left pull-right"></i>
              </span>
            </a>
            <ul class="treeview-menu">
              <li class="allMenu systemConfigmenu">
                <a href="/system-admin/edit-config/index.php"><i class="fa fa-circle-o"></i> System Configuration</a>
              </li>
            </ul>
            <ul class="treeview-menu">
              <li class="allMenu systemConfigmenu">
                <a href="/system-admin/instance-overview/index.php"><i class="fa fa-circle-o"></i>Instance Overview</a>
              </li>
            </ul>
            <ul class="treeview-menu">
              <li class="allMenu systemConfigmenu">
                <a href="/system-admin/api-stats/index.php"><i class="fa fa-circle-o"></i>Api-Stats</a>
              </li>
            </ul>
            <ul class="treeview-menu">
              <li class="allMenu systemConfigmenu">
                <a href="/system-admin/user-login-history/index.php"><i class="fa fa-circle-o"></i>User Login History</a>
              </li>
            </ul>
          </li>
          <!---->
        </ul>
      </section>
      <!-- /.sidebar -->
    </aside>
    <!-- content-wrapper -->
    <div id="dDiv" class="dialog">
      <div style="text-align:center"><span onclick="closeModal();" style="float:right;clear:both;" class="closeModal"></span></div>
      <iframe id="dFrame" src="" style="border:none;" scrolling="yes" marginwidth="0" marginheight="0" frameborder="0" vspace="0" hspace="0">some problem</iframe>
    </div>