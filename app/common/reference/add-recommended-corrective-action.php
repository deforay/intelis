<?php

use App\Registries\AppRegistry;

$title = _translate("Recommended Corrective Action");

require_once APPLICATION_PATH . '/header.php';

$testType = 'vl';


// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

if (!empty($_GET['testType'])) {
    $testType = $_GET['testType'];
}
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-gears"></em> <?php echo _translate("Add Recommended Corrective Action"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Recommended Corrective Action"); ?></li>
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
                <form class="form-horizontal" method='post' name='correctiveActionForm' id='correctiveActionForm'
                    autocomplete="off" enctype="multipart/form-data"
                    action="save-recommended-corrective-action-helper.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="correctiveAction"
                                        class="col-lg-4 control-label"><?php echo _translate("Recommended Corrective Action Name"); ?><span
                                            class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="correctiveAction"
                                            name="correctiveAction"
                                            placeholder="<?php echo _translate('Recommended Corrective Action Name'); ?>"
                                            title="<?php echo _translate('Please enter Recommended Corrective Action'); ?>"
                                            onblur='checkNameValidation("r_recommended_corrective_actions","recommended_corrective_action_name",this,'
                                            <?php echo "test_type##" . $testType; ?>',"<?php echo _translate("The Corrective action that you entered already exists.Enter another Corrective action"); ?>",null)' />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="correctiveActionStatus"
                                        class="col-lg-4 control-label"><?php echo _translate("Status"); ?><span
                                            class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control isRequired" id="correctiveActionStatus"
                                            name="correctiveActionStatus"
                                            title="<?php echo _translate('Please select corrective Action Status'); ?>">
                                            <option value=""><?php echo _translate("--Select--"); ?></option>
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
                        <a href="recommended-corrective-actions.php?testType=<?= $testType; ?>" class="btn btn-default">
                            <?php echo _translate("Cancel"); ?></a>
                    </div>
                    <input type="hidden" class="form-control" id="testType" name="testType" value="<?= $testType; ?>" />


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
            formId: 'correctiveActionForm'
        });

        if (flag) {
            $.blockUI();
            document.getElementById('correctiveActionForm').submit();
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
            testType: '<?= $testType; ?>',
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
