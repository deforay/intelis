<?php

$title = _("Geographical Divisions");

require_once APPLICATION_PATH . '/header.php';

// Sanitize values before using them below
$_GET = array_map('htmlspecialchars', $_GET);
$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;

if (!isset($id) || $id == "") {
    $_SESSION['alertMsg'] = _("Something went wrong in Geographical Divisions edit page");
    header("Location:geographical-divisions-details.php");
}

$geoQuery = "SELECT * from geographical_divisions WHERE geo_status ='active'";
$geoParentInfo = $db->query($geoQuery);
$geoArray = [];
foreach ($geoParentInfo as $type) {
    $geoArray[$type['geo_id']] = ($type['geo_name']);
}
$query = "SELECT * from geographical_divisions where geo_id = $id";
$geoInfo = $db->rawQueryOne($query);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-gears"></em> <?php echo _("Edit Geographical Divisions"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
            <li class="active"><?php echo _("Edit Geographical Divisions"); ?></li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">

        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _("indicates required field"); ?> &nbsp;</div>
            </div>
            <form class="form-horizontal" method='post' name='geographicalDivisionsDetails' id='geographicalDivisionsDetails' autocomplete="off" enctype="multipart/form-data" action="save-geographical-divisions-helper.php">
                <!-- /.box-header -->
                <div class="box-body">
                    <!-- form start -->
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="geoName" class="col-lg-4 control-label"><?php echo _("Geographical Division Name"); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" value="<?php echo $geoInfo['geo_name']; ?>" id="geoName" name="geoName" placeholder="<?php echo _('Geo Division Name'); ?>" title="<?php echo _('Please enter Geographical Division name'); ?>" onblur="checkNameValidation('geographical_divisions','geo_name',this,'<?php echo 'geo_id##' . htmlspecialchars($id); ?>','<?php echo _("The Geographical Division name that you entered already exists.Enter another name"); ?>',null)" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="geoCode" class="col-lg-4 control-label"><?php echo _("Geographical Division Code"); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" value="<?php echo $geoInfo['geo_code']; ?>" id="geoCode" name="geoCode" placeholder="<?php echo _('Geographical Divisions code'); ?>" title="<?php echo _('Please enter Geographical Division code'); ?>" onblur="checkNameValidation('geographical_divisions','geo_code',this,'<?php echo 'geo_id##' . htmlspecialchars($id); ?>','<?php echo _("The Geographical Division code that you entered already exists.Enter another code"); ?>',null)" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="geoParent" class="col-lg-4 control-label"><?php echo _("Parent Geographical Division"); ?></label>
                                    <div class="col-lg-7">
                                        <select class="form-control" id="geoParent" name="geoParent" placeholder="<?php echo _('Parent Division'); ?>" title="<?php echo _('Please select Parent division'); ?>">
                                            <?= $general->generateSelectOptions($geoArray, $geoInfo['geo_parent'], _("-- Select --")); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="geoStatus" class="col-lg-4 control-label"><?php echo _("Status"); ?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control isRequired" id="geoStatus" name="geoStatus" title="<?php echo _('Please select status'); ?>">
                                            <option value=""><?php echo _("--Select--"); ?></option>
                                            <option value="active" <?php echo ($geoInfo['geo_status'] == "active" ? 'selected' : ''); ?>><?php echo _("Active"); ?></option>
                                            <option value="inactive" <?php echo ($geoInfo['geo_status'] == "inactive" ? 'selected' : ''); ?>><?php echo _("Inactive"); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <input type="hidden" name="geoId" name="geoId" value="<?php echo htmlspecialchars($_GET['id']); ?>">
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit"); ?></a>
                        <a href="geographical-divisions-details.php" class="btn btn-default"> <?php echo _("Cancel"); ?></a>
                    </div>
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
            formId: 'geographicalDivisionsDetails'
        });

        if (flag) {
            $.blockUI();
            document.getElementById('geographicalDivisionsDetails').submit();
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
