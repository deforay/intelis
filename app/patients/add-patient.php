<?php

$title = _translate("Patients");

require_once APPLICATION_PATH . '/header.php';
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-user"></em> <?php echo _translate("Add Patient"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Patients"); ?></li> 
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _translate("indicates required field"); ?> &nbsp;</div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->
                <form class="form-horizontal" method='post' name='patientForm' id='patientForm' autocomplete="off" enctype="multipart/form-data" action="save-patient-helper.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="patientCodePrefix" class="col-lg-4 control-label"><?php echo _translate("Patient Code Prefix"); ?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="patientCodePrefix" name="patientCodePrefix" placeholder="<?php echo _translate('Patient Code Prefix'); ?>" title="<?php echo _translate('Please enter Patient Code Prefix'); ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="patientCodeKey" class="col-lg-4 control-label"><?php echo _translate("Patient Code Key"); ?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="patientCodeKey" name="patientCodeKey" placeholder="<?php echo _translate('Patient Code Key'); ?>" title="<?php echo _translate('Please enter Patient Code Key'); ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="patientCode" class="col-lg-4 control-label"><?php echo _translate("Patient Code"); ?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="patientCode" name="patientCode" placeholder="<?php echo _translate('Patient Code'); ?>" title="<?php echo _translate('Please enter Patient Code'); ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="patientFirstName" class="col-lg-4 control-label"><?php echo _translate("Patient First Name"); ?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="patientFirstName" name="patientFirstName" placeholder="<?php echo _translate('Patient First Name'); ?>" title="<?php echo _translate('Please enter Patient First Name'); ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="patientMiddleName" class="col-lg-4 control-label"><?php echo _translate("Patient Middle Name"); ?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="patientMiddleName" name="patientMiddleName" placeholder="<?php echo _translate('Patient Middle Name'); ?>" title="<?php echo _translate('Please enter Patient Code'); ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="patientLastName" class="col-lg-4 control-label"><?php echo _translate("Patient Last Name"); ?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="patientLastName" name="patientLastName" placeholder="<?php echo _translate('Patient Last Name'); ?>" title="<?php echo _translate('Please enter Patient Last Name'); ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="gender" class="col-lg-4 control-label">Gender<span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <label class="radio-inline control-label" style="margin-left:0px;">
                                            <input type="radio" class="isRequired" id="genderMale" name="gender" value="male" title="Please check gender">Male
                                        </label>
                                        <label class="radio-inline control-label" style="margin-left:0px;">
                                            <input type="radio" class="isRequired" id="genderFemale" name="gender" value="female" title="Please check gender">Female
                                        </label>
                                        <label class="radio-inline control-label" style="margin-left:0px;">
                                            <input type="radio" class="isRequired" id="genderNotRecorded" name="gender" value="not_recorded" title="Please check gender">Not Recorded
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="patientDob" class="col-lg-4 control-label"><?php echo _translate("Patient DOB"); ?><span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                    <input type="text" name="patientDob" id="patientDob" class="form-control date isRequired" placeholder="Enter Patient DOB" title="Enter Patient DOB" onchange="getAge();checkARTInitiationDate();" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br>
                    </div>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
                        <a href="funding-sources.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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
            formId: 'fundingSrcNameForm'
        });

        if (flag) {
            $.blockUI();
            document.getElementById('fundingSrcNameForm').submit();
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
