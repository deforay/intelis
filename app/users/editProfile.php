<?php

use App\Models\General;
use App\Utilities\DateUtils;

ob_start();
require_once(APPLICATION_PATH . '/header.php');

$db = MysqliDb::getInstance();
$general = new General();

$db->where("user_id", $_SESSION['userId']);
$userInfo = $db->getOne("user_details");

$db->orderBy("login_attempted_datetime", "DESC");
$db->where("login_id", $_SESSION['loginId']);
$db->orWhere('user_id', $_SESSION['userId']);
$data = $db->get("user_login_history", 25);


?>

<link href="vendor/datatables-plugins/dataTables.bootstrap.css" rel="stylesheet">

<!-- DataTables Responsive CSS -->
<link href="vendor/datatables-responsive/dataTables.responsive.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1> <em class="fa-solid fa-gears"></em> <?php echo _("Edit Profile"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
      <li class="active"><?php echo _("Users"); ?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">

    <div class="box box-default">
      <div class="box-header with-border">
        <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <!-- form start -->
        <form class="form-horizontal" method='post' name='userEditForm' id='userEditForm' autocomplete="off" action="editProfileHelper.php">
          <div class="box-body">
            <div class="row">
              <div class="col-md-12">
                <div class="form-group">
                  <label for="userName" class="col-lg-2 control-label"><?php echo _("Your Full Name"); ?> <span class="mandatory">*</span></label>
                  <div class="col-lg-10">
                    <input type="text" class="form-control isRequired" id="userName" name="userName" placeholder="<?php echo _('Your Full Name'); ?>" title="<?php echo _('Please enter user name'); ?>" value="<?php echo $userInfo['user_name']; ?>" />
                    <input type="hidden" name="userId" id="userId" value="<?php echo base64_encode($userInfo['user_id']); ?>" />
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="email" class="col-lg-4 control-label"><?php echo _("Official Email"); ?> </label>
                  <div class="col-lg-8">
                    <input type="text" class="form-control" id="email" name="email" placeholder="<?php echo _('Official Email'); ?>" title="<?php echo _('Please enter email'); ?>" value="<?php echo $userInfo['email']; ?>" onblur="checkNameValidation('user_details','email',this,'<?php echo "user_id##" . $userInfo['user_id']; ?>','<?php echo _("This email id that you entered already exists.Try another email id"); ?>',null)" />
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="phoneNo" class="col-lg-4 control-label"><?php echo _("Phone Number"); ?></label>
                  <div class="col-lg-8">
                    <input type="text" class="form-control" id="phoneNo" name="phoneNo" placeholder="<?php echo _('Phone Number'); ?>" title="<?php echo _('Please enter phone number'); ?>" value="<?php echo $userInfo['phone_number']; ?>" />
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="password" class="col-lg-4 control-label"><?php echo _("Password"); ?> </label>
                  <div class="col-lg-8">
                    <input type="password" class="form-control ppwd" id="password" name="password" placeholder="<?php echo _('Password'); ?>" title="<?php echo _('Please enter the password'); ?>" maxlength="16" /><br>
                    <button type="button" id="generatePassword" onclick="passwordType();" class="btn btn-default"><b>Generate Random Password</b></button><br>
                    <code><?= _("Password must be at least 12 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?></code>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="confirmPassword" class="col-lg-4 control-label"><?php echo _("Confirm Password"); ?></label>
                  <div class="col-lg-8">
                    <input type="password" class="form-control cpwd confirmPassword" id="confirmPassword" name="password" placeholder="<?php echo _('Confirm Password'); ?>" title="" maxlength="16" />
                  </div>
                </div>
              </div>
            </div>

          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit"); ?></a>
            <a href="/dashboard/index.php" class="btn btn-default"> <?php echo _("Cancel"); ?></a>
          </div>
          <!-- /.box-footer -->
        </form>
        <!-- /.row -->
      </div>
      <div class="box-body">
        <h4 style="font-weight:bold;border-bottom:1px solid #ccc;padding-bottom:10px;">Recent Login Attempts</h4>
        <table class="table table-striped table-bordered table-hover" id="loginAttempts">
          <thead>
            <tr>

              <th>Login Attempt Date and Time</th>
              <th>Login ID</th>
              <th>IP Address</th>
              <th>Browser</th>
              <th>Operating System</th>
              <th>Login Status</th>
            </tr>
          </thead>
          <tbody>
            <?php
            if (count($data) > 0) {
              foreach ($data as $project) {

            ?>
                <tr>

                  <td><?php echo DateUtils::humanReadableDateFormat($project['login_attempted_datetime']); ?></td>
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
                <td class="center" colspan="6">No record found</td>
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
<script src="vendor/jquery/jquery.min.js"></script>

<!-- Bootstrap Core JavaScript -->
<script src="vendor/bootstrap/js/bootstrap.min.js"></script>

<script src="vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="vendor/datatables-responsive/dataTables.responsive.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
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
    var regex = /^(?=.*[0-9])(?=.*[a-zA-Z])([a-zA-Z0-9!@#\$%\^\&*\)\(+=. _-]+){12,}$/;
    if (regex.test(pwd) == false) {
      alert("<?= _("Password must be at least 12 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?>");
      $('.ppwd').focus();
    }
    return regex.test(pwd);
  }

  function passwordType() {
    document.getElementById('password').type = "text";
    document.getElementById('confirmPassword').type = "text";
    $.post("/includes/generate-password.php", {
        size: 32
      },
      function(data) {
        // alert(data);
        $("#password").val(data);
        $("#confirmPassword").val(data);
        var cpy = copyToClipboard(document.getElementById("confirmPassword"));
        if (cpy == true) {
          // alert("Password copied to clipboard!");
          Toastify({
            text: "Random password generated and copied to clipboard",
            duration: 3000,
          }).showToast();
        }
      });
  }

  function copyToClipboard(elem) {
    // create hidden text element, if it doesn't already exist
    var targetId = "_hiddenCopyText_";
    var isInput = elem.tagName === "INPUT" || elem.tagName === "TEXTAREA";
    var origSelectionStart, origSelectionEnd;
    if (isInput) {
      // can just use the original source element for the selection and copy
      target = elem;
      origSelectionStart = elem.selectionStart;
      origSelectionEnd = elem.selectionEnd;
    } else {
      // must use a temporary form element for the selection and copy
      target = document.getElementById(targetId);
      if (!target) {
        var target = document.createElement("textarea");
        target.style.position = "absolute";
        target.style.left = "-9999px";
        target.style.top = "0";
        target.id = targetId;
        document.body.appendChild(target);
      }
      target.textContent = elem.textContent;
    }
    // select the content
    var currentFocus = document.activeElement;
    target.focus();
    target.setSelectionRange(0, target.value.length);

    // copy the selection
    var succeed;
    try {
      succeed = document.execCommand("copy");
    } catch (e) {
      succeed = false;
    }
    // restore original focus
    if (currentFocus && typeof currentFocus.focus === "function") {
      currentFocus.focus();
    }

    if (isInput) {
      // restore prior selection
      elem.setSelectionRange(origSelectionStart, origSelectionEnd);
    } else {
      // clear temporary content
      target.textContent = "";
    }
    return succeed;
  }
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
