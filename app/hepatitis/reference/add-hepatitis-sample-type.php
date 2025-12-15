<?php


require_once APPLICATION_PATH . '/header.php';
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-square-h"></em> <?php echo _translate("Add Hepatitis Sample Type"); ?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
      <li class="active"><?php echo _translate("Hepatitis Sample Type"); ?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">

    <div class="box box-default">
      <div class="box-header with-border">
        <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
          <?php echo _translate("indicates required fields"); ?> &nbsp;</div>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <!-- form start -->
        <form class="form-horizontal" method='post' name='addSampleForm' id='addSampleForm' autocomplete="off"
          enctype="multipart/form-data" action="save-hepatitis-sample-type-helper.php">
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="sampleName" class="col-lg-4 control-label"><?php echo _translate("Sample Name"); ?><span
                      class="mandatory">*</span></label>
                  <div class="col-lg-7">
                    <input type="text" class="form-control isRequired" id="sampleName" name="sampleName"
                      placeholder="<?php echo _translate('Sample Name'); ?>"
                      title="<?php echo _translate('Please enter Sample name'); ?>"
                      onblur='checkNameValidation("r_hepatitis_sample_type","sample_name",this,null,"<?php echo _translate("The Sample name that you entered already exists.Enter another name"); ?>",null)' />
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="sampleStatus"
                    class="col-lg-4 control-label"><?php echo _translate("Sample Status"); ?><span
                      class="mandatory">*</span></label>
                  <div class="col-lg-7">
                    <select class="form-control isRequired" id="sampleStatus" name="sampleStatus"
                      placeholder="<?php echo _translate('Sample Status'); ?>"
                      title="<?php echo _translate('Please enter Sample Status'); ?>">
                      <option value="active"><?php echo _translate("Active"); ?></option>
                      <option value="inactive"><?php echo _translate("Inactive"); ?></option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <br>

          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <a class="btn btn-primary" href="javascript:void(0);"
              onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
            <a href="hepatitis-sample-type.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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
      formId: 'addSampleForm'
    });

    if (flag) {
      $.blockUI();
      document.getElementById('addSampleForm').submit();
    }
  }

  function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
    let removeDots = obj.value.replace(/\./g, "");
    removeDots = removeDots.replace(/\,/g, "");
    //str=obj.value;
    removeDots = removeDots.replace(/\s{2,}/g, ' ');

    $.post("/includes/checkDuplicate.php", {
      tableName: tableName,
      fieldName: fieldName,
      value: removeDots.trim(),
      fnct: fnct,
      format: "html"
    },
      function (data) {
        if (data === '1') {
          alert(alrt);
          document.getElementById(obj.id).value = "";
        }
      });
  }
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
