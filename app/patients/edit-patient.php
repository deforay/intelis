<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;
use App\Utilities\DateUtility;


$title = _translate("Patients");

require_once APPLICATION_PATH . '/header.php';

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

$state = $geolocationService->getProvinces("yes", true, $_SESSION['facilityMap']);

$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

$patientQuery = "SELECT * FROM patients WHERE patient_id=?";
$patientQueryInfo = $db->rawQueryOne($patientQuery, [$id]);
$patientQueryInfo['patient_dob'] = DateUtility::humanReadableDateFormat($patientQueryInfo['patient_dob'] ?? '');

if (!empty($patientQueryInfo['is_encrypted']) && $patientQueryInfo['is_encrypted'] == 'yes') {
    $key = (string) $general->getGlobalConfig('key');
    $patientQueryInfo['patient_code'] = $general->crypto('decrypt', $patientQueryInfo['patient_code'], $key);
    $patientQueryInfo['patient_first_name'] = $general->crypto('decrypt', $patientQueryInfo['patient_first_name'], $key);
    $patientQueryInfo['patient_middle_name'] = $general->crypto('decrypt', $patientQueryInfo['patient_middle_name'], $key);
    $patientQueryInfo['patient_last_name'] = $general->crypto('decrypt', $patientQueryInfo['patient_last_name'], $key);
}
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _translate("Edit Patient"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Patients"); ?></li>
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
                <form class="form-horizontal" method='post' name='patientForm' id='patientForm' autocomplete="off" enctype="multipart/form-data" action="save-patient-helper.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6 encryptPIIContainer">
                                <div class="form-group">
                                    <label class="col-lg-5 control-label" for="encryptPII"><?= _translate('Patient is from Defence Forces (Patient Name and Patient ID will not be synced between LIS and STS)'); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-6">
                                        <select name="encryptPII" id="encryptPII" class="form-control" title="<?= _translate('Encrypt Patient Identifying Information'); ?>">
                                            <option value=""><?= _translate('--Select--'); ?></option>
                                            <option value="no" <?php echo ($patientQueryInfo['is_encrypted'] == "no") ? "selected='selected'" : ""; ?>><?= _translate('No'); ?></option>
                                            <option value="yes" <?php echo ($patientQueryInfo['is_encrypted'] == "yes") ? "selected='selected'" : ""; ?>><?= _translate('Yes'); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="ageInYears" class="col-lg-4 control-label"><?= _translate('Province'); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control isRequired" name="province" id="province" title="<?= _translate('Please choose a province'); ?>" style="width:100%;" onchange="getByProvince(this.value)">
                                            <?= $general->generateSelectOptions($state, $patientQueryInfo['patient_province'], _translate("-- Select --")); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="ageInYears" class="col-lg-4 control-label"><?= _translate('District'); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select class="form-control isRequired" name="district" id="district" title="<?= _translate('Please choose a district'); ?>" style="width:100%;">
                                            <option value=""> <?= _translate('-- Select --'); ?> </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="patientCodePrefix" class="col-lg-4 control-label"><?php echo _translate("Patient Code Prefix"); ?><span class="mandatory">*</span></label>
                                        <div class="col-lg-7">
                                            <input type="text" value="<?= $patientQueryInfo['patient_code_prefix']; ?>" class="form-control isRequired" id="patientCodePrefix" name="patientCodePrefix" placeholder="<?php echo _translate('Patient Code Prefix'); ?>" title="<?php echo _translate('Please enter Patient Code Prefix'); ?>" />
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="patientCodeKey" class="col-lg-4 control-label"><?php echo _translate("Patient Code Key"); ?><span class="mandatory">*</span></label>
                                        <div class="col-lg-7">
                                            <input type="text" value="<?= $patientQueryInfo['patient_code_key']; ?>" class="form-control isRequired" id="patientCodeKey" name="patientCodeKey" placeholder="<?php echo _translate('Patient Code Key'); ?>" title="<?php echo _translate('Please enter Patient Code Key'); ?>" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="patientCode" class="col-lg-4 control-label"><?php echo _translate("Patient Code"); ?><span class="mandatory">*</span></label>
                                        <div class="col-lg-7">
                                            <input type="text" value="<?= $patientQueryInfo['patient_code']; ?>" class="form-control isRequired" id="patientCode" name="patientCode" placeholder="<?php echo _translate('Patient Code'); ?>" title="<?php echo _translate('Please enter Patient Code'); ?>" />
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="patientFirstName" class="col-lg-4 control-label"><?php echo _translate("Patient First Name"); ?><span class="mandatory">*</span></label>
                                        <div class="col-lg-7">
                                            <input type="text" value="<?= $patientQueryInfo['patient_first_name']; ?>" class="form-control isRequired" id="patientFirstName" name="patientFirstName" placeholder="<?php echo _translate('Patient First Name'); ?>" title="<?php echo _translate('Please enter Patient First Name'); ?>" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="patientMiddleName" class="col-lg-4 control-label"><?php echo _translate("Patient Middle Name"); ?></label>
                                        <div class="col-lg-7">
                                            <input type="text" value="<?= $patientQueryInfo['patient_middle_name']; ?>" class="form-control" id="patientMiddleName" name="patientMiddleName" placeholder="<?php echo _translate('Patient Middle Name'); ?>" title="<?php echo _translate('Please enter Patient Code'); ?>" />
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="patientLastName" class="col-lg-4 control-label"><?php echo _translate("Patient Last Name"); ?></label>
                                        <div class="col-lg-7">
                                            <input type="text" value="<?= $patientQueryInfo['patient_last_name']; ?>" class="form-control" id="patientLastName" name="patientLastName" placeholder="<?php echo _translate('Patient Last Name'); ?>" title="<?php echo _translate('Please enter Patient Last Name'); ?>" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="gender" class="col-lg-4 control-label"><?= _translate('Sex'); ?><span class="mandatory">*</span></label>
                                        <div class="col-lg-7">
                                            <label class="radio-inline control-label" style="margin-left:0px;">
                                                <input type="radio" class="isRequired" id="genderMale" name="gender" value="male" title="Please choose sex" <?php echo ($patientQueryInfo['patient_gender'] == 'male') ? "checked='checked'" : "" ?>>Male
                                            </label>
                                            <label class="radio-inline control-label" style="margin-left:0px;">
                                                <input type="radio" class="isRequired" id="genderFemale" name="gender" value="female" title="Please choose sex" <?php echo ($patientQueryInfo['patient_gender'] == 'female') ? "checked='checked'" : "" ?>>Female
                                            </label>
                                            <label class="radio-inline control-label" style="margin-left:0px;">
                                                <input type="radio" class="isRequired" id="genderUnreported" name="gender" value="unreported" title="Please choose sex" <?php echo ($patientQueryInfo['patient_gender'] == 'unreported') ? "checked='checked'" : "" ?>>Unreported
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="dob" class="col-lg-4 control-label"><?php echo _translate("Patient DOB"); ?><span class="mandatory">*</span></label>
                                        <div class="col-lg-7">
                                            <input type="text" value="<?= $patientQueryInfo['patient_dob']; ?>" name="dob" id="dob" class="form-control date isRequired" placeholder="Enter Patient DOB" title="Enter Patient DOB" onchange="getAge();" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ageInYears" class="col-lg-4 control-label"><?= _translate('If DOB unknown, Age in Year(s)'); ?> </label>
                                        <div class="col-lg-7">
                                            <input type="text" value="<?= $patientQueryInfo['patient_age_in_years']; ?>" name="ageInYears" id="ageInYears" class="form-control forceNumeric" maxlength="2" placeholder="<?= _translate('Age in Year(s)'); ?>" title="<?= _translate('Enter age in years'); ?>" />
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ageInMonths" class="col-lg-4 control-label"><?= _translate('If Age < 1, Age in Month(s)'); ?> </label>
                                        <div class="col-lg-7">
                                            <input type="text" value="<?= $patientQueryInfo['patient_age_in_months']; ?>" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="<?= _translate('Age in Month(s)'); ?>" title="<?= _translate('Enter age in months'); ?>" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row femaleSection" style="display:none;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="patientPregnant" class="col-lg-4 control-label"><?= _translate('Is Patient Pregnant?'); ?> <span class="mandatory">*</span></label>
                                        <label class="radio-inline">
                                            <input type="radio" class="" id="pregYes" name="patientPregnant" value="yes" title="<?= _translate('Please check if patient is pregnant'); ?>"> <?= _translate('Yes'); ?>
                                        </label>
                                        <label class="radio-inline">
                                            <input type="radio" class="" id="pregNo" name="patientPregnant" value="no"> <?= _translate('No'); ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="breastfeeding" class="col-lg-4 control-label"><?= _translate('Is Patient Breastfeeding?'); ?> <span class="mandatory">*</span></label>
                                        <label class="radio-inline">
                                            <input type="radio" class="" id="breastfeedingYes" name="breastfeeding" value="yes" title="<?= _translate('Please check if patient is breastfeeding'); ?>"> <?= _translate('Yes'); ?>
                                        </label>
                                        <label class="radio-inline">
                                            <input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no"> <?= _translate('No'); ?>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="patientPhoneNumber" class="col-lg-4 control-label"><?= _translate('Phone Number'); ?> </label>
                                        <div class="col-lg-7">
                                            <input type="text" value="<?= $patientQueryInfo['patient_phone_number']; ?>" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control phone-number" placeholder="<?= _translate('Enter Phone Number'); ?>" maxlength="<?= $maxPhoneLength; ?>" title="<?= _translate('Enter phone number'); ?>" />
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="patientAddress" class="col-lg-4 control-label"><?= _translate('Address'); ?> </label>
                                        <div class="col-lg-7">
                                            <textarea class="form-control" value="<?= $patientQueryInfo['patient_address']; ?>" id="patientAddress" name="patientAddress" placeholder="<?= _translate('Address'); ?>" title="<?= _translate('Case Address'); ?>" style="width:100%;" onchange=""><?= $patientQueryInfo['patient_address']; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="patientStatus" class="col-lg-4 control-label"><?= _translate('Status'); ?> <span class="mandatory">*</span></label>
                                            <div class="col-lg-7">
                                                <select class="form-control isRequired" id="patientStatus" name="patientStatus" title="<?php echo _translate('Please select patient status'); ?>">
                                                    <option value=""><?php echo _translate("--Select--"); ?></option>
                                                    <option value="active" <?php echo ($patientQueryInfo['status'] == "active") ? "selected='selected'" : ""; ?>><?php echo _translate("Active"); ?></option>
                                                    <option value="inactive" <?php echo ($patientQueryInfo['status'] == "inactive") ? "selected='selected'" : ""; ?>><?php echo _translate("Inactive"); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <br>
                            </div>
                            <!-- /.box-body -->
                            <div class="box-footer">
                                <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
                                <a href="view-patients.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
                            </div>
                            <input type="hidden" name="patientId" id="patientId" value="<?= ($patientQueryInfo['patient_id']); ?>" />

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
        getByProvince(<?php echo $patientQueryInfo['patient_province']; ?>);
        $('#province').select2({
            placeholder: "<?= _translate('Select Province'); ?>"
        });

        $('#district').select2({
            placeholder: "<?= _translate('Select Distrct'); ?>"
        });
        $("input:radio[name=gender]").click(function() {
            if ($(this).val() == 'male' || $(this).val() == 'unreported') {
                $('.femaleSection').hide();
                $('input[name="breastfeeding"]').prop('checked', false);
                $('input[name="patientPregnant"]').prop('checked', false);
                $('#breastfeedingYes').removeClass('isRequired');
                $('#pregYes').removeClass('isRequired');
            } else if ($(this).val() == 'female') {
                $('.femaleSection').show();
                $('#breastfeedingYes').addClass('isRequired');
                $('#pregYes').addClass('isRequired');
            }
        });

    });

    function validateNow() {

        flag = deforayValidator.init({
            formId: 'patientForm'
        });

        if (flag) {
            $.blockUI();
            document.getElementById('patientForm').submit();
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

    function getfacilityDetails(obj) {

        $.blockUI();

        var pName = $("#province").val();

        if ($.trim(pName) != '') {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    pName: pName,
                    testType: 'vl'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#district").html(details[1]);
                    }
                });
        } else if (pName == '') {
            provinceName = true;
            $("#province").html("<?php echo $province; ?>");
            $("#district").html("<option value=''> -- Select -- </option>");
        }
        $.unblockUI();
    }

    function getByProvince(provinceId) {
        $("#district").html('');
        $.post("/common/get-by-province-id.php", {
                provinceId: provinceId,
                districts: '<?php echo $patientQueryInfo['patient_district']; ?>',
            },
            function(data) {
                Obj = $.parseJSON(data);
                $("#district").html(Obj['districts']);
            });
    }
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
