<?php

use App\Models\Covid19;
use App\Models\Facilities;
use App\Models\General;
use App\Models\Users;



require_once(APPLICATION_PATH . '/header.php');
$generalDb = new General();
$covid19Db = new Covid19();
$facilityDb = new Facilities();
$userDb = new Users();
$code = $covid19Db->generateCovid19QcCode();
$testingLabs = $facilityDb->getTestingLabs("covid19");
$users = $userDb->getAllUsers(null, null, "drop-down");

$testKitInfo = $db->rawQuery("SELECT * from r_covid19_qc_testkits");
$testKitsList = [];
foreach ($testKitInfo as $kits) {
    $testKitsList[base64_encode($kits['testkit_id'])] = $kits['testkit_name'];
}
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
$chkUserFcMapQry = "SELECT user_id FROM user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
$chkUserFcMapResult = $db->query($chkUserFcMapQry);
if ($chkUserFcMapResult) {
    $pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
}
$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
    $province .= "<option data-code='" . $provinceName['geo_code'] . "' data-province-id='" . $provinceName['geo_id'] . "' data-name='" . $provinceName['geo_name'] . "' value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-virus-covid"></em> <?php echo _("Add Covid-19 QC Test Kit"); ?></h1>
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
                <form class="form-horizontal" method='post' name='addQcTestKits' id='addQcTestKits' autocomplete="off" enctype="multipart/form-data" action="save-covid-19-qc-data.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="qcCode" class="col-lg-4 control-label"><?php echo _("QC Code"); ?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" value="<?php echo $code['code']; ?>" class="form-control isRequired" id="qcCode" name="qcCode" placeholder="<?php echo _('QC Code'); ?>" title="<?php echo _('Please enter QC Code'); ?>" readonly />
                                        <input type="hidden" value="<?php echo $code['key']; ?>" id="qcKey" name="qcKey" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testKit" class="col-lg-4 control-label"><?php echo _("Test Kit"); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control select2 isRequired" id="testKit" name="testKit" title="<?php echo _('Please select test kit'); ?>" onchange="getKitLabels(this.value);">
                                            <?= $generalDb->generateSelectOptions($testKitsList, null, "--Select--"); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="lotNo" class="col-lg-4 control-label"><?php echo _("Lot number"); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="lotNo" name="lotNo" placeholder="<?php echo _('Lot number'); ?>" title="<?php echo _('Please enter lot no'); ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="expiryDate" class="col-lg-4 control-label"><?php echo _("Expiry Date"); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control date isRequired" id="expiryDate" name="expiryDate" placeholder="<?php echo _('Expiry date'); ?>" title="<?php echo _('Please enter expiry date'); ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="province" class="col-lg-4 control-label"><?php echo _("State / Province"); ?></label>
                                    <div class="col-lg-7">
                                        <select class="form-control select2" name="province" id="province" title="Please choose State / province" onchange="getDistrictDetails(this);" style="width:100%;">
                                            <?php echo $province; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="district" class="col-lg-4 control-label"><?php echo _("District / County"); ?></label>
                                    <div class="col-lg-7">
                                        <select class="form-control select2" name="district" id="district" title="Please choose district / county" style="width:100%;" onchange="getLabsDistrictWise(this);">
                                            <option value=""> -- Select -- </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="labName" class="col-lg-4 control-label"><?php echo _("Testing Lab"); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control select2 isRequired" id="labName" name="labName" title="<?php echo _('Please select lab name'); ?>" onchange="getTestingPoints();">
                                            <?= $generalDb->generateSelectOptions($testingLabs, null, "--Select--"); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testingPoint" class="col-lg-4 control-label"><?php echo _("Testing Point"); ?></label>
                                    <div class="col-lg-7">
                                        <select class="form-control select2" id="testingPoint" name="testingPoint" title="<?php echo _('Please select testing point'); ?>">
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testerName" class="col-lg-4 control-label"><?php echo _("Tester Name"); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control select2 isRequired" id="testerName" name="testerName" title="<?php echo _('Please select tester name'); ?>">
                                            <?= $generalDb->generateSelectOptions($users, null, "--Select--"); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="receivedOn" class="col-lg-4 control-label"><?php echo _("Received On"); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control date-time isRequired" id="receivedOn" name="receivedOn" placeholder="<?php echo _('Received on'); ?>" title="<?php echo _('Please enter received on'); ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testedOn" class="col-lg-4 control-label"><?php echo _("Tested On"); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control date-time isRequired" id="testedOn" name="testedOn" placeholder="<?php echo _('Tested on'); ?>" title="<?php echo _('Please enter tested on'); ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br>
                    </div>
                    <table id="qcTestTableRoot" border="0" class="table table-striped table-bordered table-condensed" aria-hidden="true" style="width:100%;display:none;">
                        <thead>
                            <tr>
                                <th style="text-align:center;"><?php echo _("QC Test Label"); ?></th>
                                <th style="text-align:center;"><?php echo _("Test Result"); ?></th>
                            </tr>
                        </thead>
                        <tbody id="qcTestTable">

                        </tbody>
                    </table>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit"); ?></a>
                        <a href="covid-19-qc-data.php" class="btn btn-default"> <?php echo _("Cancel"); ?></a>
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
    $('#labName').select2({
        width: '100%',
        placeholder: "Select testing lab name"
    });

    $('#testerName').select2({
        width: '100%',
        placeholder: "Select Tester name"
    });

    $('#testKit').select2({
        width: '100%',
        placeholder: "Select Test Kit name"
    });

    $('#province').select2({
        width: '100%',
        placeholder: "Select province name"
    });

    $('#district').select2({
        width: '100%',
        placeholder: "Select district name"
    });

    $(document).ready(function() {
        $('.date').datepicker({
            changeMonth: true,
            changeYear: true,
            onSelect: function() {
                $(this).change();
            },
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        $('.date-time').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            onSelect: function(e) {},
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        $('.date').mask('99-aaa-9999');
        $('.dateTime').mask('99-aaa-9999 99:99');
    });

    function validateNow() {

        flag = deforayValidator.init({
            formId: 'addQcTestKits'
        });

        if (flag) {
            $.blockUI();
            document.getElementById('addQcTestKits').submit();
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

    function checkLabelName(obj) {
        machineObj = document.getElementsByName("qcTestLable[]");
        for (m = 0; m < machineObj.length; m++) {
            if (obj.value != '' && obj.id != machineObj[m].id && obj.value == machineObj[m].value) {
                alert('Duplicate value not allowed');
                $('#' + obj.id).val('');
            }
        }
    }

    function getKitLabels(value) {
        if (value != "") {

            $.post("/covid-19/results/get-kit-labels.php", {
                    kitId: value,
                    format: "html"
                },
                function(data) {
                    if (data != "") {
                        $("#qcTestTable").html(data)
                        $("#qcTestTableRoot").show();
                    }
                });
        } else {
            $("#qcTestTableRoot").hide();
        }
    }

    function getTestingPoints() {
        var labId = $("#labName").val();
        var selectedTestingPoint = null;
        if (labId) {
            $.post("/includes/getTestingPoints.php", {
                    labId: labId,
                    selectedTestingPoint: selectedTestingPoint
                },
                function(data) {
                    if (data != "") {
                        $("#testingPoint").html(data);
                    } else {
                        $("#testingPoint").html('');
                    }
                });
        }
    }

    function getDistrictDetails(obj) {

        // $.blockUI();
        var pName = $("#province").val();
        if ($.trim(pName) != '') {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    pName: pName,
                    fType: 2,
                    testType: 'covid19'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#labName").html(details[0]);
                        $("#district").html(details[1]);
                    }
                });
        } else if (pName == '') {
            $("#province").html("<?php echo $province; ?>");
            $("#labName").html("<?= $generalDb->generateSelectOptions($testingLabs, null, "--Select--"); ?>");
            $("#labName").select2("val", "");
            $("#district").html("<option value=''> -- Select -- </option>");
        }
        $.unblockUI();
    }

    function getLabsDistrictWise(obj) {
        // $.blockUI();
        var dName = $("#district").val();
        if (dName != '') {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    dName: dName,
                    fType: 2,
                    testType: 'covid19'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#labName").html(details[1]);
                    }
                });
        } else {
            $("#labName").html("<?= $generalDb->generateSelectOptions($testingLabs, null, "--Select--"); ?>");
        }
        $.unblockUI();
    }
</script>

<?php
require_once(APPLICATION_PATH . '/footer.php');
