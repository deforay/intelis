<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
$title = _translate("Lab Storage");

require_once APPLICATION_PATH . '/header.php';

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$labNameList = $facilitiesService->getTestingLabs();
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

$sQuery = "SELECT * from lab_storage where storage_id=?";
$sInfo = $db->rawQueryOne($sQuery, [$id]);


?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-gears"></em> <?php echo _translate("Edit Lab Freezer/Storage"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Lab Freezer/Storage"); ?></li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">

        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _translate("indicates required fields"); ?> &nbsp;</div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->
                <form class="form-horizontal" method='post' name='storageSrcNameForm' id='storageSrcNameForm' autocomplete="off" enctype="multipart/form-data" action="/common/reference/save-lab-storage-helper.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="storageCode" class="col-lg-4 control-label"><?php echo _translate("Freezer/Storage Code"); ?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="storageCode" name="storageCode" value="<?= $sInfo['storage_code']; ?>" placeholder="<?php echo _translate('Storage Code'); ?>" title="<?php echo _translate('Please enter Storage Code'); ?>" onblur='checkNameValidation("lab_storage","storage_code",this,null,"<?php echo _translate("The Storage Code that you entered already exists.Enter another Storage Code"); ?>",null)' />
                                    </div>
                                </div>
                            </div>
                            <!-- <div class="col-md-6">
                                <div class="form-group">
                                    <label for="storageCode" class="col-lg-4 control-label"><?php echo _translate("Testing Lab"); ?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control select2 isRequired" id="labId" name="labId" title="Please select the testing lab">
                                            <?php echo $general->generateSelectOptions($labNameList, $sInfo['lab_id'], '--Select--'); ?>
                                        </select>

                                    </div>
                                </div>
                            </div> -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="storageStatus" class="col-lg-4 control-label"><?php echo _translate("Freezer/Storage Status"); ?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control isRequired" id="storageStatus" name="storageStatus" title="<?php echo _translate('Please select status'); ?>">
                                            <option value=""><?php echo _translate("--Select--"); ?></option>
                                            <option value="active" <?php echo ($sInfo['storage_status'] == 'active') ? 'selected="selected"' : ''; ?>><?php echo _translate("Active"); ?></option>
                                            <option value="inactive" <?php echo ($sInfo['storage_status'] == 'inactive') ? 'selected="selected"' : ''; ?>><?php echo _translate("Inactive"); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br>
                    </div>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <input type="hidden" name="storageId" name="storageId" value="<?php echo $_GET['id']; ?>">
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
                        <a href="/common/reference/lab-storage.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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

<script nonce="<?= $_SESSION['nonce']; ?>" type="text/javascript">
    $(document).ready(function() {

        $(".select2").select2({
            width: '100%',
            placeholder: '<?php echo _translate("Select the options"); ?>'
        });
    });

    function validateNow() {

        flag = deforayValidator.init({
            formId: 'storageSrcNameForm'
        });

        if (flag) {
            $.blockUI();
            document.getElementById('storageSrcNameForm').submit();
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
