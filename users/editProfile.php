<?php
ob_start();
#require_once('../startup.php'); 
include_once(APPLICATION_PATH . '/header.php');
// if ($global['edit_profile'] == 'no') {
//   header("location:/dashboard/index.php");
// }
$id = $_SESSION['userId'];
$userQuery = "SELECT * from user_details where user_id='" . $id . "'";
$userInfo = $db->query($userQuery);
// $query = "SELECT * FROM roles where status='active'";
// $result = $db->rawQuery($query);
$userLoginhistory = "SELECT * FROM user_login_history ORDER BY history_id DESC LIMIT 25";
$data = $db->rawQuery($userLoginhistory);
?>

<link href="vendor/datatables-plugins/dataTables.bootstrap.css" rel="stylesheet">

<!-- DataTables Responsive CSS -->
<link href="vendor/datatables-responsive/dataTables.responsive.css" rel="stylesheet">
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1> <i class="fa fa-gears"></i> <?php echo _("Edit Profile");?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><i class="fa fa-dashboard"></i> <?php echo _("Home");?></a></li>
      <li class="active"><?php echo _("Users");?></li>
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
              <div class="col-md-6">
                <div class="form-group">
                  <label for="userName" class="col-lg-4 control-label"><?php echo _("User Name");?> <span class="mandatory">*</span></label>
                  <div class="col-lg-7">
                    <input type="text" class="form-control isRequired" id="userName" name="userName" placeholder="<?php echo _('User Name');?>" title="<?php echo _('Please enter user name');?>" value="<?php echo $userInfo[0]['user_name']; ?>" />
                    <input type="hidden" name="userId" id="userId" value="<?php echo base64_encode($userInfo[0]['user_id']); ?>" />
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="email" class="col-lg-4 control-label"><?php echo _("Email");?> </label>
                  <div class="col-lg-7">
                    <input type="text" class="form-control" id="email" name="email" placeholder="<?php echo _('Email');?>" title="<?php echo _('Please enter email');?>" value="<?php echo $userInfo[0]['email']; ?>" onblur="checkNameValidation('user_details','email',this,'<?php echo "user_id##" . $userInfo[0]['user_id']; ?>','<?php echo _("This email id that you entered already exists.Try another email id");?>',null)" />
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="phoneNo" class="col-lg-4 control-label"><?php echo _("Phone Number");?></label>
                  <div class="col-lg-7">
                    <input type="text" class="form-control" id="phoneNo" name="phoneNo" placeholder="<?php echo _('Phone Number');?>" title="<?php echo _('Please enter phone number');?>" value="<?php echo $userInfo[0]['phone_number']; ?>" />
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="password" class="col-lg-4 control-label"><?php echo _("Password");?> </label>
                  <div class="col-lg-7">
                    <input type="password" class="form-control ppwd" id="confirmPassword" name="password" placeholder="<?php echo _('Password');?>" title="<?php echo _('Please enter the password');?>" />
                    <code><?= _("Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?></code>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="confirmPassword" class="col-lg-4 control-label"><?php echo _("Confirm Password");?></label>
                  <div class="col-lg-7">
                    <input type="password" class="form-control cpwd confirmPassword" id="confirmPassword" name="password" placeholder="<?php echo _('Confirm Password');?>" title="" />
                  </div>
                </div>
              </div>
            </div>

          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit");?></a>
            <a href="/dashboard/index.php" class="btn btn-default"> <?php echo _("Cancel");?></a>
          </div>
          <!-- /.box-footer -->
        </form>
        <!-- /.row -->
      </div>
      <!-- <table class="table table-striped table-bordered table-hover" id="example">
        <thead>
          <tr>
            <th>Login Name</th>
            <th>Attempted Date Time</th>
            <th>IP Address</th>
            <th>Browser</th>
            <th>Operating System</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if (count($data) > 0) {
            foreach ($data as $project) {

          ?>
              <tr>
                <td><?php echo $project['login_id']; ?></td>
                <td><?php echo $project['login_attempted_datetime']; ?></td>
                <td><?php echo $project['ip_address']; ?></td>
                <td><?php echo $project['browser']; ?></td>
                <td><?php echo $project['operating_system']; ?></td>
                <td><?php echo $project['login_status']; ?></td>
              </tr>
            <?php
            }
            ?>
        </tbody>
      <?php } else {
            echo "No record found";
          } ?>

      </table> -->
    </div>
    <!-- /.box -->

  </section>
  <!-- /.content -->
</div>
<script src="vendor/jquery/jquery.min.js"></script>

<!-- Bootstrap Core JavaScript -->
<script src="vendor/bootstrap/js/bootstrap.min.js"></script>

<script src="vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="vendor/datatables-responsive/dataTables.responsive.js">
</script>
<!-- $(document).ready(function() {
$('#example').DataTable({
responsive: true
});
}); -->
<script type="text/javascript">
  $(document).ready(function() {
    $('#example').DataTable({
      responsive: true
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
      alert("<?= _("Password must be at least 8 characters long and must include AT LEAST one number, one alphabet and may have special characters.") ?>");
      $('.ppwd').focus();
    }
    return regex.test(pwd);
  }
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>