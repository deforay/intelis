<?php
ob_start();
#require_once('../startup.php');
include_once(APPLICATION_PATH . '/header.php');
$rejQuery = "SELECT * from r_tb_test_reasons WHERE test_reason_status ='active'";
$rejInfo = $db->query($rejQuery);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><i class="fa fa-heartbeat"></i> Add TB Test Reasons</h1>
    <ol class="breadcrumb">
      <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">TB Test Reasons</li>
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
        <form class="form-horizontal" method='post' name='addTstForm' id='addTstForm' autocomplete="off" enctype="multipart/form-data" action="add-test-reasons-helper.php">
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="testReasonName" class="col-lg-4 control-label">Test Reason Name <span class="mandatory">*</span></label>
                  <div class="col-lg-7">
                    <input type="text" class="form-control isRequired" id="testReasonName" name="testReasonName" placeholder="Test Reason Name" title="Please enter Test Reason name" onblur="checkNameValidation('r_tb_test_reasons','test_reason_name',this,null,'The Test Reason name that you entered already exists.Enter another name',null)" />
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="parentReason" class="col-lg-4 control-label">Parent Reason</label>
                  <div class="col-lg-7">
                    <select class="form-control isRequired" id="parentReason" name="parentReason" placeholder="Parent Reason" title="Please enter Parent Reason">
                      <option value=""> -- Select -- </option>
                      <?php
                      foreach ($rejInfo as $type) {
                      ?>
                        <option value="<?php echo $type['test_reason_id']; ?>"><?php echo ucwords($type['test_reason_name']); ?></option>
                      <?php
                      }
                      ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="testReasonStatus" class="col-lg-4 control-label">Test Reason Status</label>
                  <div class="col-lg-7">
                    <select class="form-control isRequired" id="testReasonStatus" name="testReasonStatus" placeholder="Test Reason Status" title="Please select Test Reason Status">
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <br>

          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
            <a href="tb-test-reasons.php" class="btn btn-default"> Cancel</a>
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
      formId: 'addTstForm'
    });

    if (flag) {
      $.blockUI();
      document.getElementById('addTstForm').submit();
    }
  }

  function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
    var removeDots = obj.value.replace(/\./g, "");
    var removeDots = removeDots.replace(/\,/g, "");
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
</script>

<?php
include(APPLICATION_PATH . '/footer.php');
?>