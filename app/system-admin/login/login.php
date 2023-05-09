<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['adminUserId'])) {
    header("Location:/system-admin/edit-config/index.php");
}
$adminCount = $db->rawQuery("SELECT * FROM system_admin as ud");
if (count($adminCount) == 0) {
    header("Location:/system-admin/setup/index.php");
}
$path = '/assets/img/remote-bg.jpg';
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['APP_LOCALE'] ?? 'en_US'; ?>">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo _("VLSM"); ?> | <?php echo _("Viral Load LIS"); ?> | <?php echo _("Admin Login"); ?></title>
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <!-- Bootstrap 3.3.6 -->
    <link rel="stylesheet" href="/assets/css/fonts.css">
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">

    <!-- Theme style -->
    <link rel="stylesheet" href="/assets/css/AdminLTE.min.css">
    <link href="/assets/css/deforayModal.css" rel="stylesheet" />
    <!-- iCheck -->

    <link rel="stylesheet" href="/assets/css/font-awesome.min.css">

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
        <div id="loginbox" style="margin-top:20px;margin-bottom:70px;float:right;margin-right:10px;" class="mainbox col-md-3 col-sm-8 ">
            <div class="panel panel-default" style="opacity: 0.93;">
                <div class="panel-heading">
                    <div class="panel-title"><?php echo _("System Administrator"); ?></div>
                </div>

                <div style="padding-top:10px;" class="panel-body">
                    <div style="display:none" id="login-alert" class="alert alert-danger col-sm-12"></div>
                    <form id="loginForm" name="loginForm" class="form-horizontal" role="form" method="post" action="adminLoginProcess.php" onsubmit="validateNow();return false;">
                        <div style="margin-bottom: 5px" class="input-group">
                            <span class="input-group-addon"><em class="fa-solid fa-user"></em></span>
                            <input id="login-username" type="text" class="form-control isRequired" name="username" value="" placeholder="<?php echo _('User Name'); ?>" title="<?php echo _('Please enter the user name'); ?>">
                        </div>
                        <div style="margin-bottom: 5px" class="input-group">
                            <span class="input-group-addon"><em class="fa-solid fa-lock"></em></span>
                            <input id="login-password" type="password" class="form-control isRequired" name="password" placeholder="<?php echo _('Password'); ?>" title="<?php echo _('Please enter the password'); ?>">
                        </div>
                        <div style="margin-top:10px" class="form-group">
                            <!-- Button -->
                            <div class="col-sm-12 controls">
                                <button class="btn btn-lg btn-success btn-block" onclick="validateNow();return false;"><?php echo _("Login"); ?></button>
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
        function validateNow() {
            flag = deforayValidator.init({
                formId: 'loginForm'
            });

            if (flag) {
                document.getElementById('loginForm').submit();
            }
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