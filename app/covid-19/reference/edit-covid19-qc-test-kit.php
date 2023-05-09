<?php

use App\Services\Covid19Service;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;



require_once APPLICATION_PATH . '/header.php';
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);
$covid19Results = $covid19Service->getCovid19Results();

$id = base64_decode($_GET['id']);
$resultQuery = "SELECT * from r_covid19_qc_testkits where testkit_id = '" . $id . "' ";
$resultInfo = $db->rawQueryOne($resultQuery);
$subResult = json_decode($resultInfo['labels_and_expected_results'], true);

?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-virus-covid"></em> <?php echo _("Edit Covid-19 QC Test Kit"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
            <li class="active"><?php echo _("Covid-19 QC Test Kit"); ?></li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _("indicates required field"); ?> &nbsp;</div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->
                <form class="form-horizontal" method='post' name='editQcTestKits' id='editQcTestKits' autocomplete="off" enctype="multipart/form-data" action="save-covid19-qc-test-kits-helper.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testKitName" class="col-lg-4 control-label"><?php echo _("Test Kit Name"); ?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" value="<?php echo $resultInfo['testkit_name']; ?>" class=" form-control isRequired" id="testKitName" name="testKitName" placeholder="<?php echo _('Test Kit Name'); ?>" title="<?php echo _('Please enter Test Kit name'); ?>" onblur='checkNameValidation("r_covid19_qc_testkits", "testkit_name" , this , ' <?php echo "testkit_id##" . $id; ?>', "<?php echo _("The test kit name that you entered already exists. Enter another name"); ?>" , null)' readonly />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testKitStatus" class="col-lg-4 control-label"><?php echo _("Status"); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control isRequired" id="testKitStatus" name="testKitStatus" placeholder="<?php echo _('Status'); ?>" title="<?php echo _('Please select Status'); ?>">
                                            <option value="">-- Select --</option>
                                            <option value="active" <?php echo (isset($resultInfo['status']) && $resultInfo['status'] == 'active') ? "selected='selected'" : ""; ?>><?php echo _("Active"); ?></option>
                                            <option value="inactive" <?php echo (isset($resultInfo['status']) && $resultInfo['status'] == 'inactive') ? "selected='selected'" : ""; ?>><?php echo _("Inactive"); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br>
                    </div>
                    <table aria-describedby="table" border="0" class="table table-striped table-bordered table-condensed" aria-hidden="true" style="width:100%;">
                        <thead>
                            <tr>
                                <th style="text-align:center;"><?php echo _("QC Test Label"); ?> <span class="mandatory">*</span></th>
                                <th style="text-align:center;"><?php echo _("Expected Result"); ?> <span class="mandatory">*</span></th>
                                <th style="text-align:center;"><?php echo _("Action"); ?></th>
                            </tr>
                        </thead>
                        <tbody id="qcTestTable">
                            <?php
                            if (isset($resultInfo['labels_and_expected_results']) && !empty($resultInfo['labels_and_expected_results'])) {
                                foreach ($subResult['label'] as $key => $row) { ?>
                                    <tr>
                                        <td>
                                            <input type="text" value="<?php echo $subResult['label'][$key]; ?>" name=" qcTestLable[]" id="qcTestLable<?php echo ($key + 1); ?>" class="form-control isRequired" placeholder='<?php echo _("QC Test Label"); ?>' title='<?php echo _("Please enter QC test label"); ?>' onblur="checkLabelName(this);" />
                                        </td>
                                        <td>
                                            <select id="expectedResult<?php echo ($key + 1); ?>" name="expectedResult[]" class="isRequired form-control" title="Please enter the expected results">
                                                <?= $general->generateSelectOptions($covid19Results, $subResult['expected'][$key], "--Select--"); ?>
                                            </select>
                                        </td>
                                        <td align="center" style="vertical-align:middle;">
                                            <a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
                                        </td>
                                    </tr>
                                <?php }
                            } else { ?>
                                <tr>
                                    <td>
                                        <input type="text" name=" qcTestLable[]" id="qcTestLable1" class="form-control isRequired" placeholder='<?php echo _("QC Test Label"); ?>' title='<?php echo _("Please enter QC test label"); ?>' onblur="checkLabelName(this);" />
                                    </td>
                                    <td>
                                        <select id="expectedResult1" name="expectedResult[]" class="isRequired form-control" title="Please enter the expected results">
                                            <?= $general->generateSelectOptions($covid19Results, null, "--Select--"); ?>
                                        </select>
                                    </td>
                                    <td align="center" style="vertical-align:middle;">
                                        <a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <input type="hidden" name="qcTestId" id="qcTestId" value="<?php echo base64_encode($resultInfo['testkit_id']); ?>">
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit"); ?></a>
                        <a href="covid19-qc-test-kits.php" class="btn btn-default"> <?php echo _("Cancel"); ?></a>
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
    tableRowId = 2;

    function validateNow() {

        flag = deforayValidator.init({
            formId: 'editQcTestKits'
        });

        if (flag) {
            $.blockUI();
            document.getElementById('editQcTestKits').submit();
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

    function insRow() {
        rl = document.getElementById("qcTestTable").rows.length;
        var a = document.getElementById("qcTestTable").insertRow(rl);
        a.setAttribute("style", "display:none");
        var b = a.insertCell(0);
        var c = a.insertCell(1);
        var d = a.insertCell(2);
        d.setAttribute("align", "center");
        d.setAttribute("style", "vertical-align:middle");

        b.innerHTML = '<input type="text" name="qcTestLable[]" id="qcTestLable' + tableRowId + '" class="isRequired form-control" placeholder="<?php echo _('QC Test Label'); ?>" title="<?php echo _('Please enter qc test label'); ?>" onblur="checkLabelName(this);"/ >';
        c.innerHTML = '<select id="expectedResult' + tableRowId + '" name="expectedResult[]" class="isRequired form-control" title="Please enter the expected results"><option value="">--Select--</option><?php foreach ($covid19Results as $key => $row) { ?><option value="<?php echo $key; ?>"><?php echo $row; ?></option><?php } ?></select>';
        d.innerHTML = '<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>';
        $(a).fadeIn(800);
        tableRowId++;
    }

    function removeAttributeRow(el) {
        $(el).fadeOut("slow", function() {
            el.parentNode.removeChild(el);
            rl = document.getElementById("qcTestTable").rows.length;
            if (rl == 0) {
                insRow();
            }
        });
    }

    function checkLabelName(obj) {
        machineObj = document.getElementsByName("qcTestLable[]");
        for (m = 0; m < machineObj.length; m++) {
            if (obj.value != '' && obj.id != machineObj[m].id && obj.value == machineObj[m].value) {
                alert('Duplicate value not allowed');
                $('#' + obj.id).val('');
            }
        }
    }
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
