<?php

$title = _translate("Geographical Divisions");

require_once APPLICATION_PATH . '/header.php';


use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$geoQuery = "SELECT geo_id, geo_name FROM geographical_divisions WHERE geo_status ='active' AND geo_parent = 0 ORDER BY geo_name";
$geoParentInfo = $db->query($geoQuery);
$geoArray = [];
foreach ($geoParentInfo as $type) {
    $geoArray[$type['geo_id']] = ($type['geo_name']);
}
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-gears"></em> <?php echo _translate("Add Geographical Divisions"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Add Geographical Divisions"); ?></li>
        </ol>
    </section>

    <section class="content">

        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
                    <?php echo _translate("indicates required fields"); ?> &nbsp;</div>
            </div>
            <form class="form-horizontal" method='post' name='geographicalDivisionsDetails'
                id='geographicalDivisionsDetails' autocomplete="off" enctype="multipart/form-data"
                action="save-geographical-divisions-helper.php">
                <!-- /.box-header -->
                <div class="box-body">
                    <!-- form start -->
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="geoName"
                                        class="col-lg-4 control-label"><?php echo _translate("Geographical Division Name"); ?>
                                        <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="geoName" name="geoName"
                                            placeholder="<?php echo _translate('Geographical Division Name'); ?>"
                                            title="<?php echo _translate('Please enter Geographical Division name'); ?>"
                                            onblur='checkNameValidation("geographical_divisions","geo_name",this,null,"<?php echo _translate("The Geographical Division name that you entered already exists. Please enter another name"); ?>",null)' />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="geoCode"
                                        class="col-lg-4 control-label"><?php echo _translate("Geographical Division Code"); ?>
                                        <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="geoCode" name="geoCode"
                                            placeholder="<?php echo _translate('Geographical Division code'); ?>"
                                            title="<?php echo _translate('Please enter Geographical Division code'); ?>"
                                            onblur='checkNameValidation("geographical_divisions","geo_code",this,null,"<?php echo _translate("The Geographical Division code that you entered already exists. Please enter another code"); ?>",null)' />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="geoParent"
                                        class="col-lg-4 control-label"><?php echo _translate("Parent Geographical Division"); ?></label>
                                    <div class="col-lg-7">
                                        <select class="form-control select2-element" id="geoParent" name="geoParent"
                                            placeholder="<?php echo _translate('Parent Division'); ?>"
                                            title="<?php echo _translate('Please select Parent Division'); ?>">
                                            <?= $general->generateSelectOptions($geoArray, null, _translate("-- Select --")); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="geoStatus"
                                        class="col-lg-4 control-label"><?php echo _translate("Status"); ?><span
                                            class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control isRequired" id="geoStatus" name="geoStatus"
                                            title="<?php echo _translate('Please select status'); ?>">
                                            <option value=""><?php echo _translate("--Select--"); ?></option>
                                            <option value="active"><?php echo _translate("Active"); ?></option>
                                            <option value="inactive"><?php echo _translate("Inactive"); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <br>
                </div>
                <!-- /.box-body -->
                <div class="box-footer">
                    <input type="hidden" name="provinceId" name="provinceId" value="<?php echo $_GET['id']; ?>">
                    <a class="btn btn-primary" href="javascript:void(0);"
                        onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
                    <a href="geographical-divisions-details.php" class="btn btn-default">
                        <?php echo _translate("Cancel"); ?></a>
                </div>
                <!-- /.box-footer -->
            </form>
            <!-- /.row -->
        </div>
</div>
<!-- /.box -->

<!--</section>-->
<!--<!-- /.content -->-->
<!--</div>-->

<script type="text/javascript">
    $(document).ready(function () {
        $('#geoParent').select2({
            width: '100%',
            allowClear: true,
            placeholder: "<?php echo _translate('-- Select --'); ?>"
        });
    });

    function validateNow() {

        flag = deforayValidator.init({
            formId: 'geographicalDivisionsDetails'
        });

        if (flag) {
            $.blockUI();
            document.getElementById('geographicalDivisionsDetails').submit();
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
