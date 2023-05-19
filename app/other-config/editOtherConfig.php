<?php


require_once APPLICATION_PATH . '/header.php';

$otherConfigQuery = "SELECT * from other_config";
$otherConfigResult = $db->query($otherConfigQuery);
$arr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($otherConfigResult); $i++) {
  $arr[$otherConfigResult[$i]['name']] = $otherConfigResult[$i]['value'];
}
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-sharp fa-solid fa-gears"></em> Edit Email/SMS Configuration</h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
      <li class="active">Manage Email/SMS Config</li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">

    <div class="box box-default">
      <!--<div class="box-header with-border">
          <div class="pull-right" style="font-size:15px;"> </div>
        </div>-->
      <!-- /.box-header -->
      <div class="box-body">
        <!-- form start -->
        <form class="form-horizontal" method='post' name='editOtherConfigForm' id='editOtherConfigForm' autocomplete="off" action="otherConfigHelper.php">
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="email" class="col-lg-3 control-label">Email </label>
                  <div class="col-lg-9">
                    <input type="text" class="form-control" id="email" name="email" placeholder="Email" title="Please enter email" value="<?php echo $arr['email']; ?>">
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="password" class="col-lg-3 control-label">Password </label>
                  <div class="col-lg-9">
                    <input type="text" class="form-control" id="password" name="password" placeholder="Password" title="Please enter password" value="<?php echo $arr['password']; ?>">
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
            <a href="otherConfig.php" class="btn btn-default"> Cancel</a>
          </div>
          <!-- /.box-footer -->
        </form>
        <!-- /.row -->
      </div>

    </div>
    <!-- /.box -->

  </section>
  <!-- /.content -->
</div>
<script type="text/javascript">
  function validateNow() {
    flag = deforayValidator.init({
      formId: 'editOtherConfigForm'
    });

    if (flag) {
      $.blockUI();
      document.getElementById('editOtherConfigForm').submit();
    }
  }
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
