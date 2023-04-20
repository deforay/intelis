<?php

require_once(APPLICATION_PATH . '/header.php');
$rejReaons = $general->getRejectionReasons('eid');
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><em class="fa-solid fa-child"></em> <?php echo _("Add EID Sample Rejection Reasons");?></h1>
    <ol class="breadcrumb">
      <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home");?></a></li>
      <li class="active"><?php echo _("EID Sample Rejection Reasons");?></li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">

    <div class="box box-default">
      <div class="box-header with-border">
        <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _("indicates required field");?> &nbsp;</div>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <!-- form start -->
        <form class="form-horizontal" method='post' name='addSampleRejcForm' id='addSampleRejcForm' autocomplete="off" enctype="multipart/form-data" action="save-eid-sample-rejection-reasons-helper.php">
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="rejectionReasonName" class="col-lg-4 control-label"><?php echo _("Rejection Reason Name");?><span class="mandatory">*</span></label>
                  <div class="col-lg-7">
                    <input type="text" class="form-control isRequired" id="rejectionReasonName" name="rejectionReasonName" placeholder="<?php echo _('Rejection Reason Name');?>" title="<?php echo _('Please enter Rejection Reason name');?>" onblur='checkNameValidation("r_eid_sample_rejection_reasons","rejection_reason_name",this,null,"<?php echo _("The Rejection Reason name that you entered already exists.Enter another Rejection Reason name");?>",null)' />
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="rejectionType" class="col-lg-4 control-label"><?php echo _("Rejection Type");?> <span class="mandatory">*</span></label>
                  <div class="col-lg-7">
                    <select class="form-control isRequired select2" id="rejectionType" name="rejectionType" placeholder="<?php echo _('Rejection Type');?>" title="<?php echo _('Please enter Rejection Type');?>">
                      <?= $general->generateSelectOptions($rejReaons, null, _("-- Select --")); ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="rejectionReasonCode" class="col-lg-4 control-label"><?php echo _("Rejection Reason Code");?><span class="mandatory">*</span></label>
                  <div class="col-lg-7">
                    <input type="text" class="form-control isRequired" id="rejectionReasonCode" name="rejectionReasonCode" placeholder="<?php echo _('Rejection Reason Code');?>" title="<?php echo _('Please enter Rejection Reason Code');?>" onblur='checkNameValidation("r_eid_sample_rejection_reasons","rejection_reason_code",this,null,"<?php echo _("The Rejection Reason code that you entered already exists.Enter another Rejection Reason code");?>",null)' />
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="rejectionReasonStatus" class="col-lg-4 control-label"><?php echo _("Rejection Reason Status");?><span class="mandatory">*</span></label>
                  <div class="col-lg-7">
                    <select class="form-control isRequired" id="rejectionReasonStatus" name="rejectionReasonStatus" placeholder="<?php echo _('Rejection Reason Status');?>" title="<?php echo _('Please enter Rejection Reason Status');?>">
                      <option value="active"><?php echo _("Active");?></option>
                      <option value="inactive"><?php echo _("Inactive");?></option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
            <br>

          </div>
          <!-- /.box-body -->
          <div class="box-footer">
            <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit");?></a>
            <a href="eid-sample-rejection-reasons.php" class="btn btn-default"> <?php echo _("Cancel");?></a>
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
  $(document).ready(function() {
    $(".select2").select2();
    $(".select2").select2({
      tags: true
    });
  });

  function validateNow() {

    flag = deforayValidator.init({
      formId: 'addSampleRejcForm'
    });

    if (flag) {
      $.blockUI();
      document.getElementById('addSampleRejcForm').submit();
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

  function addNewRejectionType(id) {
    checkValue = $("#" + id + " option:selected").html();
    if (checkValue != '') {
      $.post("/includes/addNewField.php", {
          value: checkValue,
          mode: 'addNewRejectionType'
        },
        function(data) {
          console.log(data)
        });
    }
  }
</script>

<?php
require_once(APPLICATION_PATH . '/footer.php');
