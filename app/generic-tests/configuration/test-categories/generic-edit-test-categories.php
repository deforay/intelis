<?php

use App\Registries\AppRegistry;

$title = _translate("Test Categories");

require_once APPLICATION_PATH . '/header.php';

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

$tQuery = "SELECT * from r_generic_test_categories where test_category_id=$id";
$testCategoryInfo = $db->query($tQuery);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-gears"></em> <?php echo _translate("Edit Test Categories"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Test Categories"); ?></li>
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
                <form class="form-horizontal" method='post' name='addSampleTypeForm' id='addSampleTypeForm'
                    autocomplete="off" action="save-test-categories-helper.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testCategory"
                                        class="col-lg-4 control-label"><?php echo _translate("Test Category"); ?><span
                                            class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="testCategory"
                                            name="testCategory"
                                            placeholder="<?php echo _translate('Test Categories'); ?>"
                                            title="<?php echo _translate('Please enter test category'); ?>"
                                            onblur="checkNameValidation('r_generic_test_categories','test_category_name',this,'<?php echo "test_category_id##" . $testCategoryInfo[0]['test_category_id']; ?>','<?php echo _translate("This test category that you entered already exists.Try another name"); ?>',null)"
                                            value="<?php echo $testCategoryInfo[0]['test_category_name']; ?>" />
                                        <input type="hidden" name="testCategoryId" id="testCategoryId"
                                            value="<?php echo base64_encode((string) $testCategoryInfo[0]['test_category_id']); ?>" />
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testCategorytatus"
                                        class="col-lg-4 control-label"><?php echo _translate("Status"); ?><span
                                            class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control isRequired" id="testCategoryStatus"
                                            name="testCategoryStatus"
                                            title="<?php echo _translate('Please select status'); ?>">
                                            <option value=""><?php echo _translate("--Select--"); ?></option>
                                            <option value="active" <?php echo ($testCategoryInfo[0]['test_category_status'] == 'active') ? "selected='selected'" : "" ?>><?php echo _translate("Active"); ?>
                                            </option>
                                            <option value="inactive" <?php echo ($testCategoryInfo[0]['test_category_status'] == 'inactive') ? "selected='selected'" : "" ?>><?php echo _translate("Inactive"); ?>
                                            </option>
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
                        <a href="generic-test-categories.php" class="btn btn-default">
                            <?php echo _translate("Cancel"); ?></a>
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
