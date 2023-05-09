<?php
ob_start();
$title = _("Testing Reason");

require_once APPLICATION_PATH . '/header.php';

$id = base64_decode($_GET['id']);
$tQuery = "SELECT * from r_generic_test_reasons where test_reason_id=$id";
$testingReasonInfo = $db->query($tQuery);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-gears"></em> <?php echo _("Edit Testing Reason");?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home");?></a></li>
            <li class="active"><?php echo _("Testing Reason");?></li>
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
                <form class="form-horizontal" method='post' name='addSampleTypeForm' id='addSampleTypeForm' autocomplete="off" action="save-testing-reason-helper.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testReason" class="col-lg-4 control-label"><?php echo _("Test Reason");?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="testReason" name="testReason" placeholder="<?php echo _('Testing Reason');?>" title="<?php echo _('Please enter test reason');?>" onblur="checkNameValidation('r_generic_test_reasons','test_reason',this,'<?php echo "test_reason_id##" . $testingReasonInfo[0]['test_reason_id']; ?>','<?php echo _("This test reason that you entered already exists.Try another name");?>',null)" value="<?php echo $testingReasonInfo[0]['test_reason']; ?>"/>
                                        <input type="hidden" name="testReasonId" id="testReasonId" value="<?php echo base64_encode($testingReasonInfo[0]['test_reason_id']); ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testReasonCode" class="col-lg-4 control-label"><?php echo _("Test Reason Code");?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="testReasonCode" name="testReasonCode" placeholder="<?php echo _('Test Reason Code');?>" title="<?php echo _('Please enter test reason code');?>" onblur="checkNameValidation('r_generic_test_reasons','test_reason_code',this,'<?php echo "test_reason_id##" . $testingReasonInfo[0]['test_reason_id']; ?>','<?php echo _("This test reason code that you entered already exists.Try another code");?>',null)" value="<?php echo $testingReasonInfo[0]['test_reason_code']; ?>"/>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testReasonStatus" class="col-lg-4 control-label"><?php echo _("Status");?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control isRequired" id="testReasonStatus" name="testReasonStatus" title="<?php echo _('Please select status');?>">
											<option value=""><?php echo _("--Select--");?></option>
											<option value="active" <?php echo ($testingReasonInfo[0]['test_reason_status'] == 'active') ? "selected='selected'" : "" ?>><?php echo _("Active");?></option>
											<option value="inactive" <?php echo ($testingReasonInfo[0]['test_reason_status'] == 'inactive') ? "selected='selected'" : "" ?>><?php echo _("Inactive");?></option>
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
                        <a href="generic-testing-reason.php" class="btn btn-default"> <?php echo _("Cancel");?></a>
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
            formId: 'addSampleTypeForm'
        });

        if (flag) {
            $.blockUI();
            document.getElementById('addSampleTypeForm').submit();
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
require_once APPLICATION_PATH . '/footer.php';
