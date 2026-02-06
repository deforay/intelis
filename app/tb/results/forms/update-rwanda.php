<?php
// imported in tb-add-request.php based on country in global config

use App\Services\TbService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Utilities\JsonUtility;


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Nationality
$nationalityQry = "SELECT * FROM `r_countries` ORDER BY `iso_name` ASC";
$nationalityResult = $db->query($nationalityQry);

foreach ($nationalityResult as $nrow) {
    $nationalityList[$nrow['id']] = ($nrow['iso_name']) . ' (' . $nrow['iso3'] . ')';
}

$pResult = $general->fetchDataFromTable('geographical_divisions', "geo_parent = 0 AND geo_status='active'");

// Getting the list of Provinces, Districts and Facilities
/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);
$tbResults = $tbService->getTbResults();
$tbXPertResults = $tbService->getTbResults('x-pert');
$tbLamResults = $tbService->getTbResults('lam');
$specimenTypeResult = $tbService->getTbSampleTypes();
$tbReasonsForTesting = $tbService->getTbReasonsForTesting();

$rKey = '';
$sKey = '';
$sFormat = '';
if ($_SESSION['accessType'] == 'collection-site') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    $rKey = 'R';
} else {
    $sampleCodeKey = 'sample_code_key';
    $sampleCode = 'sample_code';
    $rKey = '';
}

$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);
$facility = $general->generateSelectOptions($healthFacilities, $tbInfo['facility_id'], '-- Select --');
$microscope = ["No AFB" => "No AFB", "1+" => "1+", "2+" => "2+", "3+" => "3+"];

$typeOfPatient = (!empty($tbInfo['patient_type'])) ? (array)json_decode((string) $tbInfo['patient_type']) : [];
$reasonForTbTest = (!empty($tbInfo['reason_for_tb_test'])) ? (array)json_decode((string) $tbInfo['reason_for_tb_test']) : [];
//$testTypeRequested = (!empty($tbInfo['tests_requested'])) ? (array)json_decode((string) $tbInfo['tests_requested']) : [];
$testTypeRequested = !empty($tbInfo['tests_requested']) ? explode(',', (string) $tbInfo['tests_requested']) : [];
$tbInfo['purpose_of_test'] = !empty($tbInfo['purpose_of_test']) ? explode(',', (string) $tbInfo['purpose_of_test']) : [];
// Auto-select lab for LIS instances
$isLisInstance = $general->isLISInstance();
$currentLabId = null;
if ($isLisInstance) {
    $currentLabId = $general->getSystemConfig('sc_testing_lab_id');
}
?>

<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _translate("TB LABORATORY TEST RESULTS FORM"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Update Result"); ?></li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;">
                    <span class="mandatory">*</span> <?= _translate("indicates required fields"); ?>
                </div>
            </div>

            <div class="box-body">
                <!-- FACILITY INFORMATION -->
                <div class="box box-default disabledForm">
                    <div class="box-body">
                        <div class="box-header with-border sectionHeader" style="display: flex;">
                            <div class="col-md-7">
                                <h3 class="box-title"><?php echo _translate("FACILITY INFORMATION"); ?></h3>
                            </div>
                            <div class="col-md-5" style="display: flex;">
                                <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
                                    <span style="width: 20%;"><label class="label-control"
                                            for="sampleCode"><?php echo _translate("Sample ID"); ?></label></span>
                                    <span style="width: 80%;"
                                        id="sampleCodeInText"><?php echo (isset($tbInfo['remote_sample_code']) && $tbInfo['remote_sample_code'] != "") ? $tbInfo['remote_sample_code'] : $tbInfo['sample_code']; ?></span>
                                    <input type="hidden" id="sampleCode" name="sampleCode" />
                                <?php } else { ?>
                                    <span style="width: 20%;"><label class="label-control"
                                            for="sampleCode"><?php echo _translate("Sample ID"); ?><span
                                                class="mandatory">*</span></label></span>
                                    <input style="width: 80%;" value="<?php echo $tbInfo['sample_code']; ?>" type="text"
                                        class="form-control isRequired" id="sampleCode" name="sampleCode" readonly
                                        placeholder="Sample ID"
                                        title="<?php echo _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>"
                                        onchange="checkSampleNameValidation('form_tb','<?php echo $sampleCode; ?>',this.id,null,'The Sample ID that you entered already exists. Please try another Sample ID',null)" />
                                <?php } ?>
                            </div>
                        </div>

                        <div class="box-header with-border">
                            <h3 class="box-title" style="font-size:1em;">
                                <?php echo _translate("Enter requesting Clinician/Nurse details"); ?>
                            </h3>
                        </div>

                        <table class="table" style="width:100%">
                            <tr>
                                <td style="width: 33.33%;">
                                    <label class="label-control"
                                        for="province"><?php echo _translate("Province"); ?><span
                                            class="mandatory">*</span></label>
                                    <select class="form-control select2 isRequired" name="province" id="province"
                                        title="<?php echo _translate("Please choose State"); ?>"
                                        onchange="getfacilityDetails(this);">
                                        <?php echo $province; ?>
                                    </select>
                                </td>
                                <td style="width: 33.33%;">
                                    <label class="label-control"
                                        for="district"><?php echo _translate("District"); ?><span
                                            class="mandatory">*</span></label>
                                    <select class="form-control select2 isRequired" name="district" id="district"
                                        title="<?php echo _translate("Please choose County"); ?>"
                                        onchange="getfacilityDistrictwise(this);">
                                        <option value=""> -- <?php echo _translate("Select"); ?> -- </option>
                                    </select>
                                </td>
                                <td style="width: 33.33%;">
                                    <label class="label-control"
                                        for="facilityId"><?php echo _translate("Health Facility Name"); ?><span
                                            class="mandatory">*</span></label>
                                    <select class="form-control isRequired" name="facilityId" id="facilityId"
                                        title="<?php echo _translate("Please choose facility"); ?>"
                                        onchange="getfacilityProvinceDetails(this);">
                                        <?php echo $facility; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td style="width: 33.33%;">
                                    <label class="label-control"
                                        for="affiliatedDistrictHospital"><?php echo _translate("Affiliated District Hospital"); ?></label>
                                    <input type="text" class="form-control" id="affiliatedDistrictHospital"
                                        name="affiliatedDistrictHospital"
                                        value="<?php echo $tbInfo['affiliated_district_hospital'] ?? ''; ?>"
                                        placeholder="<?php echo _translate("Enter affiliated district hospital"); ?>"
                                        title="<?php echo _translate("Please enter affiliated district hospital"); ?>" />
                                </td>
                                <td style="width: 33.33%;">
                                    <label class="label-control"
                                        for="affiliatedLabId"><?php echo _translate("Affiliated TB Testing Site"); ?></label>
                                    <select name="affiliatedLabId" id="affiliatedLabId"
                                        class="form-control select2 isRequired"
                                        title="<?php echo _translate("Please select affiliated laboratory"); ?>">
                                        <?= $general->generateSelectOptions($testingLabs, $tbInfo['affiliated_lab_id'], '-- Select --'); ?>
                                    </select>
                                </td>
                                <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
                                    <td style="width: 33.33%;">
                                        <label class="label-control"
                                            for="labId"><?php echo _translate("Testing Laboratory"); ?><span
                                                class="mandatory">*</span></label>
                                        <select name="labId" id="labId" class="form-control select2 isRequired"
                                            title="<?php echo _translate("Please select Testing Laboratory"); ?>">
                                            <?= $general->generateSelectOptions($testingLabs, $tbInfo['lab_id'], '-- Select --'); ?>
                                        </select>
                                    </td>
                                <?php } else { ?>
                                    <td style="width: 33.33%;"></td>
                                <?php } ?>
                            </tr>
                        </table>

                        <!-- PATIENT DETAILS -->
                        <div class="box-header with-border sectionHeader">
                            <h3 class="box-title"><?php echo _translate("PATIENT DETAILS"); ?>:</h3>
                        </div>
                        <div class="box-header with-border">
                            <h3 class="box-title" style="font-size:1em;">
                                <?php echo _translate("Enter patient identification, contact details, and type of patient"); ?>
                            </h3>
                            <a style="margin-top:-0.35%;margin-left:10px;" href="javascript:void(0);"
                                class="btn btn-default pull-right btn-sm" onclick="showPatientList();"><em
                                    class="fa-solid fa-magnifying-glass"></em><?php echo _translate("Search"); ?></a>
                            <span id="showEmptyResult"
                                style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;<?php echo _translate("No Patient Found"); ?></strong></span>
                            <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo"
                                class="pull-right"
                                placeholder="<?php echo _translate("Enter Patient ID or Patient Name"); ?>"
                                title="<?php echo _translate("Enter ART number or patient name"); ?>" />
                        </div>

                        <table class="table" style="width:100%">
                            <tr class="encryptPIIContainer">
                                <td style="width: 33.33%;">
                                    <label for="encryptPII"><?= _translate('Encrypt PII'); ?></label>
                                    <select name="encryptPII" id="encryptPII" class="form-control"
                                        title="<?= _translate('Encrypt Patient Identifying Information'); ?>">
                                        <option value=""><?= _translate('--Select--'); ?></option>
                                        <option value="no" <?php echo ($tbInfo['is_encrypted'] == "no") ? "selected='selected'" : ""; ?>><?= _translate('No'); ?></option>
                                        <option value="yes" <?php echo ($tbInfo['is_encrypted'] == "yes") ? "selected='selected'" : ""; ?>><?= _translate('Yes'); ?></option>
                                    </select>
                                </td>
                                <td style="width: 33.33%;"></td>
                                <td style="width: 33.33%;"></td>
                            </tr>
                            <tr>
                                <td style="width: 33.33%;">
                                    <label for="trackerNo"><?= _translate('e-TB tracker No.'); ?></label>
                                    <input type="text" value="<?php echo $tbInfo['etb_tracker_number']; ?>"
                                        class="form-control" id="trackerNo" name="trackerNo"
                                        placeholder="<?php echo _translate("Enter the e-TB tracker number"); ?>"
                                        title="<?php echo _translate("Please enter the e-TB tracker number"); ?>" />
                                </td>
                                <td style="width: 33.33%;">
                                    <label for="dob"><?= _translate('Date of Birth'); ?></label>
                                    <input type="text"
                                        value="<?php echo DateUtility::humanReadableDateFormat($tbInfo['patient_dob']); ?>"
                                        class="form-control date" id="dob" name="dob"
                                        placeholder="<?php echo _translate("Date of Birth"); ?>"
                                        title="<?php echo _translate("Please enter Date of birth"); ?>"
                                        onchange="calculateAgeInYears('dob', 'patientAge');" />
                                </td>
                                <td style="width: 33.33%;">
                                    <label for="patientAge"><?= _translate('Age (years)'); ?></label>
                                    <input type="number" value="<?php echo $tbInfo['patient_age']; ?>" max="150"
                                        maxlength="3" class="form-control" id="patientAge" name="patientAge"
                                        placeholder="<?php echo _translate("Age (in years)"); ?>"
                                        title="<?php echo _translate("Patient Age"); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <td style="width: 33.33%;">
                                    <label for="patientId"><?php echo _translate("Patient ID"); ?></label>
                                    <input type="text" value="<?php echo $tbInfo['patient_id']; ?>"
                                        class="form-control patientId" id="patientId" name="patientId"
                                        placeholder="Patient Identification"
                                        title="<?php echo _translate("Please enter Patient ID"); ?>" />
                                </td>
                                <td style="width: 33.33%;">
                                    <label for="patientGender"><?php echo _translate("Sex"); ?><span
                                            class="mandatory">*</span></label>
                                    <select class="form-control isRequired" name="patientGender" id="patientGender"
                                        title="<?php echo _translate("Please choose sex"); ?>">
                                        <option value=''> -- <?php echo _translate("Select"); ?> -- </option>
                                        <option value='male' <?php echo (isset($tbInfo['patient_gender']) && !empty($tbInfo['patient_gender']) && $tbInfo['patient_gender'] == 'male') ? 'selected="selected"' : ''; ?>> <?php echo _translate("Male"); ?> </option>
                                        <option value='female' <?php echo (isset($tbInfo['patient_gender']) && !empty($tbInfo['patient_gender']) && $tbInfo['patient_gender'] == 'female') ? 'selected="selected"' : ''; ?>> <?php echo _translate("Female"); ?>
                                        </option>
                                        <option value='other' <?php echo (isset($tbInfo['patient_gender']) && !empty($tbInfo['patient_gender']) && $tbInfo['patient_gender'] == 'other') ? 'selected="selected"' : ''; ?>> <?php echo _translate("Other"); ?> </option>
                                    </select>
                                </td>
                                <td style="width: 33.33%;">
                                    <label for="firstName"><?php echo _translate("First Name"); ?><span
                                            class="mandatory">*</span></label>
                                    <input type="text" value="<?php echo $tbInfo['patient_name']; ?>"
                                        class="form-control isRequired" id="firstName" name="firstName"
                                        placeholder="<?php echo _translate("First Name"); ?>"
                                        title="<?php echo _translate("Please enter First name"); ?>" />
                                </td>
                            </tr>
                            <tr>
                                <td style="width: 33.33%;">
                                    <label for="lastName"><?php echo _translate("Surname"); ?></label>
                                    <input type="text" value="<?php echo $tbInfo['patient_surname']; ?>"
                                        class="form-control" id="lastName" name="lastName"
                                        placeholder="<?php echo _translate("Last name"); ?>"
                                        title="<?php echo _translate("Please enter Last name"); ?>" />
                                </td>
                                <td style="width: 33.33%;">
                                    <label for="patientPhoneNumber"><?php echo _translate("Phone contact"); ?>:</label>
                                    <input type="text" value="<?php echo $tbInfo['patient_phone']; ?>"
                                        class="form-control checkNum" id="patientPhoneNumber" name="patientPhoneNumber"
                                        placeholder="<?php echo _translate("Phone Number"); ?>"
                                        title="<?php echo _translate("Please enter phone number"); ?>" />
                                </td>
                                <td style="width: 33.33%;">
                                    <label for="typeOfPatient"><?php echo _translate("Case Type"); ?><span
                                            class="mandatory">*</span></label>
                                    <select class="select2 form-control isRequired" multiple name="typeOfPatient[]"
                                        id="typeOfPatient"
                                        title="<?php echo _translate("Please select the case type"); ?>"
                                        onchange="showOther(this.value,'typeOfPatientOther');">
                                        <option value=''> -- <?php echo _translate("Select"); ?> -- </option>
                                        <option value='new' <?php echo ((is_array($typeOfPatient) && in_array("new", $typeOfPatient)) || $typeOfPatient == "new") ? "selected='selected'" : ""; ?>>
                                            New </option>
                                        <option value='loss-to-follow-up' <?php echo ((is_array($typeOfPatient) && in_array("loss-to-follow-up", $typeOfPatient)) || $typeOfPatient == "loss-to-follow-up") ? "selected='selected'" : ""; ?>>
                                            Loss to Follow Up </option>
                                        <option value='treatment-failure' <?php echo ((is_array($typeOfPatient) && in_array("treatment-failure", $typeOfPatient)) || $typeOfPatient == "treatment-failure") ? "selected='selected'" : ""; ?>>
                                            Treatment Failure </option>
                                        <option value='relapse' <?php echo ((is_array($typeOfPatient) && in_array("relapse", $typeOfPatient)) || $typeOfPatient == "relapse") ? "selected='selected'" : ""; ?>>
                                            Relapse </option>
                                        <!-- <option value='other' <?php echo ((is_array($typeOfPatient) && in_array("other", $typeOfPatient)) || $typeOfPatient == "other") ? "selected='selected'" : ""; ?>> Other </option> -->
                                    </select>
                                    <input type="text" class="form-control typeOfPatientOther" id="typeOfPatientOther"
                                        name="typeOfPatientOther"
                                        placeholder="<?php echo _translate("Enter case type if others"); ?>"
                                        title="<?php echo _translate("Please enter case type if others"); ?>"
                                        style="display: none;" />
                                </td>
                            </tr>
                        </table>

                        <!-- TREATMENT AND RISK FACTORS INFORMATION -->
                        <div class="box-header with-border sectionHeader">
                            <h3 class="box-title"><?php echo _translate("TREATMENT AND RISK FACTORS INFORMATION"); ?>
                            </h3>
                        </div>
                        <div class="box-header with-border">
                            <h3 class="box-title" style="font-size:1em;">
                                <?php echo _translate("Please complete full information on treatment history, TB regimen and risk factors"); ?>
                            </h3>
                        </div>

                        <table class="table" style="width:100%">
                            <tr>
                                <td style="width: 33.33%;">
                                    <label
                                        for="isPatientInitiatedTreatment"><?php echo _translate("Is Patient initiated on TB treatment?"); ?>:</label>
                                    <select name="isPatientInitiatedTreatment" id="isPatientInitiatedTreatment"
                                        class="form-control isRequired" title="Please choose treatment status">
                                        <option value=''>-- <?php echo _translate("Select"); ?> --</option>
                                        <option value='no' <?php echo (isset($tbInfo['is_patient_initiated_on_tb_treatment']) && !empty($tbInfo['is_patient_initiated_on_tb_treatment']) && $tbInfo['is_patient_initiated_on_tb_treatment'] == 'no') ? 'selected="selected"' : ''; ?>><?php echo _translate("No"); ?></option>
                                        <option value='yes' <?php echo (isset($tbInfo['is_patient_initiated_on_tb_treatment']) && !empty($tbInfo['is_patient_initiated_on_tb_treatment']) && $tbInfo['is_patient_initiated_on_tb_treatment'] == 'yes') ? 'selected="selected"' : ''; ?>><?php echo _translate("Yes"); ?></option>
                                    </select>
                                </td>
                                <td style="width: 33.33%;">
                                    <label class="label-control"
                                        for="riskFactors"><?php echo _translate("Risk Factors"); ?></label>
                                    <select id="riskFactors" name="riskFactors" class="form-control"
                                        title="Please select the any one of risk factors"
                                        onchange="(this.value == 'Others') ? $('#riskFactorsOther').show() : $('#riskFactorsOther').hide();">
                                        <option value="">Select risk factor...</option>
                                        <option value="TB Contact" <?php echo (isset($tbInfo['risk_factors']) && !empty($tbInfo['risk_factors']) && $tbInfo['risk_factors'] == 'TB Contact') ? 'selected="selected"' : ''; ?>>TB Contact</option>
                                        <option value="PLHIV" <?php echo (isset($tbInfo['risk_factors']) && !empty($tbInfo['risk_factors']) && $tbInfo['risk_factors'] == 'PLHIV') ? 'selected="selected"' : ''; ?>>PLHIV</option>
                                        <option value="Healthcare provider" <?php echo (isset($tbInfo['risk_factors']) && !empty($tbInfo['risk_factors']) && $tbInfo['risk_factors'] == 'Healthcare provider') ? 'selected="selected"' : ''; ?>>Healthcare provider</option>
                                        <option value="CHW" <?php echo (isset($tbInfo['risk_factors']) && !empty($tbInfo['risk_factors']) && $tbInfo['risk_factors'] == 'CHW') ? 'selected="selected"' : ''; ?>>CHW</option>
                                        <option value="Prisoner inmate" <?php echo (isset($tbInfo['risk_factors']) && !empty($tbInfo['risk_factors']) && $tbInfo['risk_factors'] == 'Prisoner inmate') ? 'selected="selected"' : ''; ?>>Prisoner/inmate</option>
                                        <option value="Tobacco smoking" <?php echo (isset($tbInfo['risk_factors']) && !empty($tbInfo['risk_factors']) && $tbInfo['risk_factors'] == 'Tobacco smoking') ? 'selected="selected"' : ''; ?>>Tobacco smoking</option>
                                        <option value="Crowded habitant" <?php echo (isset($tbInfo['risk_factors']) && !empty($tbInfo['risk_factors']) && $tbInfo['risk_factors'] == 'Crowded habitant') ? 'selected="selected"' : ''; ?>>Crowded habitant</option>
                                        <option value="Diabetic" <?php echo (isset($tbInfo['risk_factors']) && !empty($tbInfo['risk_factors']) && $tbInfo['risk_factors'] == 'Diabetic') ? 'selected="selected"' : ''; ?>>Diabetic</option>
                                        <option value="Miner" <?php echo (isset($tbInfo['risk_factors']) && !empty($tbInfo['risk_factors']) && $tbInfo['risk_factors'] == 'Miner') ? 'selected="selected"' : ''; ?>>Miner</option>
                                        <option value="Refugee camp" <?php echo (isset($tbInfo['risk_factors']) && !empty($tbInfo['risk_factors']) && $tbInfo['risk_factors'] == 'Refugee camp') ? 'selected="selected"' : ''; ?>>Refugee camp</option>
                                        <option value="No information provided" <?php echo (isset($tbInfo['risk_factors']) && !empty($tbInfo['risk_factors']) && $tbInfo['risk_factors'] == 'No information provided') ? 'selected="selected"' : ''; ?>>No information provided</option>
                                        <option value="Others" <?php echo (isset($tbInfo['risk_factors']) && !empty($tbInfo['risk_factors']) && $tbInfo['risk_factors'] == 'Others') ? 'selected="selected"' : ''; ?>>Others</option>
                                    </select>
                                    <input
                                        style="<?php echo (isset($tbInfo['risk_factor_other']) && !empty($tbInfo['risk_factor_other'])) ? "" : "display: none"; ?>"
                                        value="<?php echo $tbInfo['risk_factor_other'] ?? ''; ?>" type="text"
                                        id="riskFactorsOther" name="riskFactorsOther" class="form-control"
                                        placeholder="Enter the other risk factor"
                                        title="Please enter the other risk factor" />
                                </td>
                                <td style="width: 33.33%;"></td>
                            </tr>
                            <tr class="treatmentSelected"
                                style="<?php echo (isset($tbInfo['is_patient_initiated_on_tb_treatment']) && !empty($tbInfo['is_patient_initiated_on_tb_treatment']) && $tbInfo['is_patient_initiated_on_tb_treatment'] != 'yes') ? "display: none;" : ""; ?>">
                                <td style="width: 33.33%;">
                                    <label class="label-control"
                                        for="treatmentDate"><?php echo _translate("Date of treatment Initiation"); ?><span
                                            class="mandatory">*</span></label>
                                    <input type="text"
                                        value="<?php echo DateUtility::humanReadableDateFormat($tbInfo['date_of_treatment_initiation']) ?? ''; ?>"
                                        name="treatmentDate" id="treatmentDate"
                                        class="treatmentSelectedInput form-control date"
                                        title="Please choose treatment date" />
                                </td>
                                <td style="width: 33.33%;">
                                    <label for="currentRegimen"
                                        class="label-control"><?php echo _translate("Current regimen"); ?><span
                                            class="mandatory">*</span></label>
                                    <input type="text" value="<?php echo $tbInfo['current_regimen'] ?? ''; ?>"
                                        class="form-control treatmentSelectedInput" id="currentRegimen"
                                        name="currentRegimen"
                                        placeholder="<?php echo _translate('Enter the current regimen'); ?>"
                                        title="<?php echo _translate('Please enter current regimen'); ?>">
                                </td>
                                <td style="width: 33.33%;">
                                    <label class="label-control"
                                        for="regimenDate"><?php echo _translate("Date of Initiation of Current Regimen"); ?><span
                                            class="mandatory">*</span></label>
                                    <input type="text"
                                        value="<?php echo DateUtility::humanReadableDateFormat($tbInfo['date_of_initiation_of_current_regimen']) ?? ''; ?>"
                                        name="regimenDate" id="regimenDate"
                                        class="treatmentSelectedInput form-control date"
                                        title="Please choose date of current regimen" />
                                </td>
                            </tr>
                        </table>

                        <!-- PURPOSE AND TEST(S) REQUESTED -->
                        <div class="box-header with-border sectionHeader">
                            <h3 class="box-title">
                                <?php echo _translate("PURPOSE AND TEST(S) REQUESTED FOR TB DIAGNOSTICS"); ?>
                            </h3>
                        </div>
                        <div class="box-header with-border">
                            <h3 class="box-title" style="font-size:1em;">
                                <?php echo _translate("Please select all that apply"); ?>
                            </h3>
                        </div>

                        <table class="table" style="width:100%">
                            <tr style="border: 1px solid #8080804f;">
                                <td style="width: 50%;">
                                    <label class="label-control"
                                        for="purposeOfTbTest"><?php echo _translate("Purpose of TB test(s)"); ?><span
                                            class="mandatory">*</span></label>
                                    <select id="purposeOfTbTest" name="purposeOfTbTest[]" multiple
                                        class="form-control isRequired"
                                        title="Please select the any one of purpose of test">
                                        <option value="">Select purpose of TB test...</option>
                                        <option value="Initial TB diagnosis" <?php echo (isset($tbInfo['purpose_of_test']) && !empty($tbInfo['purpose_of_test']) && in_array('Initial TB diagnosis', (array) $tbInfo['purpose_of_test'])) ? 'selected="selected"' : ''; ?>>Initial TB diagnosis</option>
                                        <option value="DS-TB Treatment Follow-Up" <?php echo (isset($tbInfo['purpose_of_test']) && !empty($tbInfo['purpose_of_test']) && in_array('DS-TB Treatment Follow-Up', (array) $tbInfo['purpose_of_test'])) ? 'selected="selected"' : ''; ?>>DS-TB Treatment Follow-Up</option>
                                        <option value="C2" <?php echo (isset($tbInfo['purpose_of_test']) && !empty($tbInfo['purpose_of_test']) && in_array('C2', (array) $tbInfo['purpose_of_test'])) ? 'selected="selected"' : ''; ?>>C2
                                        </option>
                                        <option value="C5" <?php echo (isset($tbInfo['purpose_of_test']) && !empty($tbInfo['purpose_of_test']) && in_array('C5', (array) $tbInfo['purpose_of_test'])) ? 'selected="selected"' : ''; ?>>C5
                                        </option>
                                        <option value="End of TB treatment" <?php echo (isset($tbInfo['purpose_of_test']) && !empty($tbInfo['purpose_of_test']) && in_array('End of TB treatment', (array) $tbInfo['purpose_of_test'])) ? 'selected="selected"' : ''; ?>>End of TB treatment</option>
                                        <option value="DR-TB Patient Baseline tests" <?php echo (isset($tbInfo['purpose_of_test']) && !empty($tbInfo['purpose_of_test']) && in_array('DR-TB Patient Baseline tests', (array) $tbInfo['purpose_of_test'])) ? 'selected="selected"' : ''; ?>>DR-TB Patient
                                            Baseline tests</option>
                                        <option value="DR-TB patient Follow up" <?php echo (isset($tbInfo['purpose_of_test']) && !empty($tbInfo['purpose_of_test']) && in_array('DR-TB patient Follow up', (array) $tbInfo['purpose_of_test'])) ? 'selected="selected"' : ''; ?>>DR-TB patient Follow up</option>
                                    </select>
                                </td>
                                <td style="width: 50%;">
                                    <label class="label-control"
                                        for="tbTestsRequested"><?php echo _translate("TB test(s) requested"); ?></label>
                                    <select id="tbTestsRequested" multiple name="tbTestsRequested[]"
                                        class="form-control" title="Please select the TB test(s) requested">
                                            <option value="">Select TB test(s) requested...</option>
                                            <option value="LED microscopy" <?php echo in_array('LED microscopy', $testTypeRequested) ? 'selected="selected"' : ''; ?>>LED microscopy
                                            </option>
                                            <option value="TB LAM test" <?php echo in_array('TB LAM test', $testTypeRequested) ? 'selected="selected"' : ''; ?>>TB LAM test
                                            </option>
                                            <option value="MTB/ RIF Ultra" <?php echo in_array('MTB/ RIF Ultra', $testTypeRequested) ? 'selected="selected"' : ''; ?>>MTB/ RIF Ultra
                                            </option>
                                            <option value="MTB/ XDR (if RIF detected)" <?php echo in_array('MTB/ XDR (if RIF detected)', $testTypeRequested) ? 'selected="selected"' : ''; ?>>MTB/
                                                XDR (if RIF detected)</option>
                                            <option value="TB culture and Drug susceptibility test (DST)" <?php echo in_array('TB culture and Drug susceptibility test (DST)', $testTypeRequested) ? 'selected="selected"' : ''; ?>>TB culture and
                                                Drug
                                                susceptibility test (DST)</option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <!-- SPECIMEN INFORMATION -->
                        <div class="box-header with-border sectionHeader">
                            <h3 class="box-title"><?php echo _translate("SPECIMEN INFORMATION"); ?></h3>
                        </div>
                        <div class="box-header with-border">
                            <h3 class="box-title" style="font-size:1em;">
                                <?php echo _translate("Please complete full information and circle/tick as appropriate"); ?>
                            </h3>
                        </div>

                        <table class="table">
                            <tr>
                                <td style="width: 33.33%;">
                                    <label class="label-control"
                                        for="sampleCollectionDate"><?php echo _translate("Date Specimen Collected"); ?><span
                                            class="mandatory">*</span></label>
                                    <input class="form-control isRequired date-time"
                                        value="<?php echo $tbInfo['sample_collection_date']; ?>" type="text"
                                        name="sampleCollectionDate" id="sampleCollectionDate"
                                        placeholder="<?php echo _translate("Sample Collection Date"); ?>"
                                        onchange="generateSampleCode(); checkCollectionDate(this.value);" />
                                    <span class="expiredCollectionDate" style="color:red; display:none;"></span>
                                </td>
                                <td style="width: 33.33%;">
                                    <label class="label-control"
                                        for="specimenType"><?php echo _translate("Specimen Type"); ?><span
                                            class="mandatory">*</span></label>
                                    <select name="specimenType" id="specimenType"
                                        class="form-control isRequired select2"
                                        title="<?php echo _translate("Please choose specimen type"); ?>" multiple
                                        onchange="showOther(this.value,'specimenTypeOther')">
                                        <?php echo $general->generateSelectOptions($specimenTypeResult, $tbInfo['specimen_type'], '-- Select --'); ?>
                                        <option value='other' <?php echo ($tbInfo['specimen_type'] == 'other') ? "selected='selected'" : ""; ?>> <?php echo _translate("Other"); ?> </option>
                                    </select>
                                    <input type="text" class="form-control specimenTypeOther" id="specimenTypeOther"
                                        name="specimenTypeOther"
                                        placeholder="<?php echo _translate("Enter specimen type of others"); ?>"
                                        title="<?php echo _translate("Please enter the specimen type if others"); ?>"
                                        style="display: none;" />
                                </td>
                                <td style="width: 33.33%;">
                                    <label class="label-control"
                                        for="reOrderedCorrectiveAction"><?php echo _translate("Is Specimen re-ordered as part of Corrective Action?"); ?></label>
                                    <select class="form-control" name="reOrderedCorrectiveAction"
                                        id="reOrderedCorrectiveAction"
                                        title="<?php echo _translate("Is specimen re-ordered as part of corrective action"); ?>">
                                        <option value="">--<?php echo _translate("Select"); ?>--</option>
                                        <option value="no" <?php echo (isset($tbInfo['is_specimen_reordered']) && !empty($tbInfo['is_specimen_reordered']) && $tbInfo['is_specimen_reordered'] == "no") ? 'selected="selected"' : ''; ?>>
                                            <?php echo _translate("No"); ?>
                                        </option>
                                        <option value="yes" <?php echo (isset($tbInfo['is_specimen_reordered']) && !empty($tbInfo['is_specimen_reordered']) && $tbInfo['is_specimen_reordered'] == "yes") ? 'selected="selected"' : ''; ?>>
                                            <?php echo _translate("Yes"); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php if (_isAllowed('/tb/results/tb-update-result.php') || $_SESSION['accessType'] != 'collection-site') { ?>
                    <form class="form-horizontal" method="post" name="editTbRequestForm" id="editTbRequestForm"
                        autocomplete="off" action="tb-update-result-helper.php">
                        <!-- TEST RESULTS INFORMATION -->
                        <div class="box box-primary">
                            <div class="box-body">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><?php echo _translate("TEST RESULTS INFORMATION"); ?></h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">
                                        <?php echo _translate("Please complete full information and appropriate results with reference to TB test(s) requested above"); ?>
                                    </h3>
                                </div>

                                <div id="testSections">
                                    <?php if (isset($tbTestInfo) && !empty($tbTestInfo)) {
                                        $n = 1;
                                        foreach ($tbTestInfo as $key => $test) { ?>
                                            <div class="test-section" data-count="<?php echo $n; ?>">
                                                <div class="section-header"><strong>Test #<span
                                                            class="section-number"><?php echo $n; ?></span></strong></div>
                                                <table class="table" style="width:100%; margin-top: 15px;">
                                                    <tr>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="labId<?php echo $n; ?>"><?php echo _translate("Testing Lab"); ?><span
                                                                    class="mandatory">*</span></label>
                                                            <select name="testResult[labId][]" id="labId<?php echo $n; ?>"
                                                                class="isRequired form-control select2"
                                                                title="<?php echo _translate("Please select testing laboratory"); ?>">
                                                                <?= $general->generateSelectOptions($testingLabs, $test['lab_id'] ?: $currentLabId, '-- Select lab --'); ?>
                                                            </select>
                                                        </td>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="sampleReceivedDate<?php echo $n; ?>"><?php echo _translate("Date specimen received at TB testing site"); ?><span
                                                                    class="mandatory">*</span></label>
                                                            <input type="text" class="date-time isRequired form-control"
                                                                value="<?php echo DateUtility::humanReadableDateFormat($test['sample_received_at_lab_datetime'], true); ?>"
                                                                id="sampleReceivedDate<?php echo $n; ?>"
                                                                name="testResult[sampleReceivedDate][]"
                                                                placeholder="<?= _translate("Please enter date"); ?>"
                                                                title="<?php echo _translate("Please enter sample receipt date"); ?>" />
                                                        </td>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="isSampleRejected<?php echo $n; ?>"><?php echo _translate("Is Sample Rejected?"); ?></label>
                                                            <select class="form-control sample-rejection-select"
                                                                name="testResult[isSampleRejected][]"
                                                                id="isSampleRejected<?php echo $n; ?>"
                                                                title="<?php echo _translate("Please select if sample was rejected"); ?>"
                                                                onchange="$('.reasonForChange<?php echo $n; ?>').show();">
                                                                <option value=''> -- <?php echo _translate("Select"); ?> --
                                                                </option>
                                                                <option value="yes" <?php echo (isset($test['is_sample_rejected']) && !empty($test['is_sample_rejected']) && $test['is_sample_rejected'] == 'yes') ? 'selected="selected"' : ''; ?>> <?php echo _translate("Yes"); ?> </option>
                                                                <option value="no" <?php echo (isset($test['is_sample_rejected']) && !empty($test['is_sample_rejected']) && $test['is_sample_rejected'] == 'no') ? 'selected="selected"' : ''; ?>> <?php echo _translate("No"); ?> </option>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                    <tr class="rejection-date-field"
                                                        style="<?php echo (isset($test['is_sample_rejected']) && !empty($test['is_sample_rejected']) && $test['is_sample_rejected'] != 'yes') ? 'display:none;' : ''; ?>">
                                                        <td style="width: 33.33%;" class="rejection-reason-field">
                                                            <label class="label-control"
                                                                for="sampleRejectionReason<?php echo $n; ?>"><?php echo _translate("Reason for Rejection"); ?><span
                                                                    class="mandatory">*</span></label>
                                                            <select class="form-control rejection-reason-select"
                                                                name="testResult[sampleRejectionReason][]"
                                                                id="sampleRejectionReason<?php echo $n; ?>"
                                                                title="<?php echo _translate("Please select the reason for rejection"); ?>">
                                                                <option value=''> -- <?php echo _translate("Select"); ?> --
                                                                </option>
                                                                <option value="">-- Select --</option>
                                                                <?php foreach ($rejectionTypeResult as $type) { ?>
                                                                    <optgroup
                                                                        label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
                                                                        <?php
                                                                        foreach ($rejectionResult as $reject) {
                                                                            if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                                                <option value="<?php echo $reject['rejection_reason_id']; ?>"
                                                                                    <?php echo ($test['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>>
                                                                                    <?= $reject['rejection_reason_name']; ?>
                                                                                </option>
                                                                        <?php }
                                                                        } ?>
                                                                    </optgroup>
                                                                <?php }
                                                                if ($test['reason_for_sample_rejection'] == 9999) {
                                                                    echo '<option value="9999" selected="selected">Unspecified</option>';
                                                                } ?>
                                                            </select>
                                                        </td>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="rejectionDate<?php echo $n; ?>"><?php echo _translate("Rejection Date"); ?><span
                                                                    class="mandatory">*</span></label>
                                                            <input class="form-control date rejection-date"
                                                                value="<?php echo DateUtility::humanReadableDateFormat($test['rejection_on']); ?>"
                                                                type="text" name="testResult[rejectionDate][]"
                                                                id="rejectionDate<?php echo $n; ?>"
                                                                placeholder="<?php echo _translate("Select rejection date"); ?>"
                                                                title="<?php echo _translate("Please select the rejection date"); ?>" />
                                                        </td>
                                                        <td style="width: 33.33%;"></td>
                                                    </tr>
                                                    <tr>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="specimenType<?php echo $n; ?>"><?php echo _translate("Specimen Type"); ?></label>
                                                            <select name="testResult[specimenType][]"
                                                                id="specimenType<?php echo $n; ?>" class="form-control"
                                                                title="<?php echo _translate("Please choose specimen type"); ?>">
                                                                <?php echo $general->generateSelectOptions($specimenTypeResult, $test['specimen_type'], '-- Select --'); ?>
                                                            </select>
                                                        </td>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="testType<?php echo $n; ?>"><?php echo _translate("Test Type"); ?></label>
                                                            <select class="form-control test-type-select"
                                                                name="testResult[testType][]" id="testType<?php echo $n; ?>"
                                                                title="<?php echo _translate("Please select the test type"); ?>"
                                                                onchange="$('.reasonForChange<?php echo $n; ?>').show();">
                                                                <option value=""><?php echo _translate("Select test type"); ?>
                                                                </option>
                                                                <option value="Smear Microscopy" <?php echo ($test['test_type'] == 'Smear Microscopy') ? 'selected="selected"' : ''; ?>>Smear Microscopy</option>
                                                                <option value="TB LAM test" <?php echo ($test['test_type'] == 'TB LAM test') ? 'selected="selected"' : ''; ?>>TB LAM test</option>
                                                                <option value="MTB/ RIF Ultra" <?php echo ($test['test_type'] == 'MTB/ RIF Ultra') ? 'selected="selected"' : ''; ?>>MTB/ RIF Ultra
                                                                </option>
                                                                <option value="MTB/ XDR (if RIF detected)" <?php echo ($test['test_type'] == 'MTB/ XDR (if RIF detected)') ? 'selected="selected"' : ''; ?>>MTB/ XDR (if RIF detected)
                                                                </option>
                                                                <option value="TB culture and Drug susceptibility test (DST)" <?php echo ($test['test_type'] == 'TB culture and Drug susceptibility test (DST)') ? 'selected="selected"' : ''; ?>>TB culture and
                                                                    Drug susceptibility test (DST)</option>
                                                            </select>
                                                        </td>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="testResult<?php echo $n; ?>"><?php echo _translate("Test Result"); ?></label>
                                                            <select class="form-control test-result-select"
                                                                name="testResult[testResult][]" id="testResult<?php echo $n; ?>"
                                                                title="<?php echo _translate("Please select the test result"); ?>"
                                                                onchange="$('.reasonForChange<?php echo $n; ?>').show();">
                                                                <option value=""><?php echo _translate("Select test result"); ?>
                                                                </option>
                                                                <?php if (isset($test['test_result']) && !empty($test['test_result'])) { ?>
                                                                    <option value="<?php echo $test['test_result']; ?>"
                                                                        selected="selected">
                                                                        <?php echo $test['test_result']; ?>
                                                                    </option>
                                                                <?php } ?>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="comments<?php echo $n; ?>"><?php echo _translate("Comments"); ?></label>
                                                            <textarea class="form-control" id="comments<?php echo $n; ?>"
                                                                name="testResult[comments][]"
                                                                placeholder="<?= _translate("Please enter comments"); ?>"
                                                                title="<?php echo _translate("Please enter comments"); ?>"><?php echo $test['comments']; ?></textarea>
                                                        </td>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="testedBy<?php echo $n; ?>"><?php echo _translate("Tested By"); ?></label>
                                                            <select name="testResult[testedBy][]" id="testedBy<?php echo $n; ?>"
                                                                class="form-control"
                                                                title="<?php echo _translate("Please choose tested by"); ?>">
                                                                <?= $general->generateSelectOptions($userInfo, $test['tested_by'], '-- Select --'); ?>
                                                            </select>
                                                        </td>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="sampleTestedDateTime<?php echo $n; ?>"><?php echo _translate("Tested On"); ?></label>
                                                            <input type="text"
                                                                value="<?php echo DateUtility::humanReadableDateFormat($test['sample_tested_datetime'], true); ?>"
                                                                class="date-time form-control"
                                                                id="sampleTestedDateTime<?php echo $n; ?>"
                                                                name="testResult[sampleTestedDateTime][]"
                                                                placeholder="<?= _translate("Please enter date"); ?>"
                                                                title="<?php echo _translate("Please enter tested date"); ?>" />
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="reviewedBy<?php echo $n; ?>"><?php echo _translate("Reviewed By"); ?></label>
                                                            <select name="testResult[reviewedBy][]" id="reviewedBy<?php echo $n; ?>"
                                                                class="form-control"
                                                                title="<?php echo _translate("Please choose reviewed by"); ?>">
                                                                <?= $general->generateSelectOptions($userInfo, $test['result_reviewed_by'], '-- Select --'); ?>
                                                            </select>
                                                        </td>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="reviewedOn<?php echo $n; ?>"><?php echo _translate("Reviewed On"); ?></label>
                                                            <input type="text"
                                                                value="<?php echo DateUtility::humanReadableDateFormat($test['result_reviewed_datetime'], true); ?>"
                                                                name="testResult[reviewedOn][]" id="reviewedOn<?php echo $n; ?>"
                                                                class="date-time disabled-field form-control"
                                                                placeholder="<?php echo _translate("Reviewed On"); ?>"
                                                                title="<?php echo _translate("Please enter reviewed date"); ?>" />
                                                        </td>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="approvedBy<?php echo $n; ?>"><?php echo _translate("Approved By"); ?></label>
                                                            <select name="testResult[approvedBy][]" id="approvedBy<?php echo $n; ?>"
                                                                class="form-control"
                                                                title="<?php echo _translate("Please choose approved by"); ?>">
                                                                <?= $general->generateSelectOptions($userInfo, $test['result_reviewed_by'], '-- Select --'); ?>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="approvedOn<?php echo $n; ?>"><?php echo _translate("Approved On"); ?></label>
                                                            <input type="text"
                                                                value="<?php echo DateUtility::humanReadableDateFormat($test['result_approved_datetime'], true); ?>"
                                                                name="testResult[approvedOn][]" id="approvedOn<?php echo $n; ?>"
                                                                class="date-time form-control"
                                                                placeholder="<?php echo _translate("Approved On"); ?>"
                                                                title="<?php echo _translate("Please enter approved date"); ?>" />
                                                        </td>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="revisedBy<?php echo $n; ?>"><?php echo _translate("Revised By"); ?></label>
                                                            <select name="testResult[revisedBy][]" id="revisedBy<?php echo $n; ?>"
                                                                class="form-control"
                                                                title="<?php echo _translate("Please choose revised by"); ?>">
                                                                <?= $general->generateSelectOptions($userInfo, $test['revised_by'], '-- Select --'); ?>
                                                            </select>
                                                        </td>
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="revisedOn<?php echo $n; ?>"><?php echo _translate("Revised On"); ?></label>
                                                            <input
                                                                value="<?php echo DateUtility::humanReadableDateFormat($test['revised_on'], true); ?>"
                                                                type="text" name="testResult[revisedOn][]"
                                                                id="revisedOn<?php echo $n; ?>" class="date-time form-control"
                                                                placeholder="<?php echo _translate("Revised On"); ?>"
                                                                title="<?php echo _translate("Please enter revised date"); ?>" />
                                                        </td>
                                                    </tr>
                                                    <tr style="display: none;" class="reasonForChange<?php echo $n; ?>">
                                                        <td style="width: 33.33%;">
                                                            <label class="label-control"
                                                                for="reasonForChange<?php echo $n; ?>"><?php echo _translate('Reason for result change'); ?></label>
                                                            <textarea class="form-control" name="testResult[reasonForChange][]"
                                                                id="reasonForChange<?php echo $n; ?>"
                                                                placeholder="Enter the reason for result change"
                                                                title="Please enter the reason for result change"></textarea>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        <?php $n += 1;
                                        } ?>
                                    <?php } else { ?>
                                        <!-- Initial test section -->
                                        <div class="test-section" data-count="1">
                                            <div class="section-header"><strong>Test #<span
                                                        class="section-number">1</span></strong></div>
                                            <table class="table" style="width:100%; margin-top: 15px;">
                                                <tr>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="labId1"><?php echo _translate("Testing Lab"); ?><span
                                                                class="mandatory">*</span></label>
                                                        <select name="testResult[labId][]" id="labId1"
                                                            class="isRequired form-control select2"
                                                            title="<?php echo _translate("Please select testing laboratory"); ?>">
                                                            <?= $general->generateSelectOptions($testingLabs, $currentLabId, '-- Select lab --'); ?>
                                                        </select>
                                                    </td>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="sampleReceivedDate1"><?php echo _translate("Date specimen received at TB testing site"); ?><span
                                                                class="mandatory">*</span></label>
                                                        <input type="text" class="date-time isRequired form-control"
                                                            id="sampleReceivedDate1" name="testResult[sampleReceivedDate][]"
                                                            placeholder="<?= _translate("Please enter date"); ?>"
                                                            title="<?php echo _translate("Please enter sample receipt date"); ?>" />
                                                    </td>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="isSampleRejected1"><?php echo _translate("Is Sample Rejected?"); ?></label>
                                                        <select class="form-control sample-rejection-select"
                                                            name="testResult[isSampleRejected][]" id="isSampleRejected1"
                                                            title="<?php echo _translate("Please select if sample was rejected"); ?>">
                                                            <option value=''> -- <?php echo _translate("Select"); ?> --
                                                            </option>
                                                            <option value="yes"> <?php echo _translate("Yes"); ?> </option>
                                                            <option value="no"> <?php echo _translate("No"); ?> </option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr class="rejection-date-field" style="display:none;">
                                                    <td style="width: 33.33%;" class="rejection-reason-field">
                                                        <label class="label-control"
                                                            for="sampleRejectionReason1"><?php echo _translate("Reason for Rejection"); ?><span
                                                                class="mandatory">*</span></label>
                                                        <select class="form-control rejection-reason-select"
                                                            name="testResult[sampleRejectionReason][]"
                                                            id="sampleRejectionReason1"
                                                            title="<?php echo _translate("Please select the reason for rejection"); ?>">
                                                            <option value=''> -- <?php echo _translate("Select"); ?> --
                                                            </option>
                                                            <?php echo $rejectionReason; ?>
                                                        </select>
                                                    </td>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="rejectionDate1"><?php echo _translate("Rejection Date"); ?><span
                                                                class="mandatory">*</span></label>
                                                        <input class="form-control date rejection-date" type="text"
                                                            name="testResult[rejectionDate][]" id="rejectionDate1"
                                                            placeholder="<?php echo _translate("Select rejection date"); ?>"
                                                            title="<?php echo _translate("Please select the rejection date"); ?>" />
                                                    </td>
                                                    <td style="width: 33.33%;"></td>
                                                </tr>
                                                <tr>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="specimenType1"><?php echo _translate("Specimen Type"); ?></label>
                                                        <select name="testResult[specimenType][]" id="specimenType1"
                                                            class="form-control"
                                                            title="<?php echo _translate("Please choose specimen type"); ?>">
                                                            <?php echo $general->generateSelectOptions($specimenTypeResult, null, '-- Select --'); ?>
                                                        </select>
                                                    </td>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="testType1"><?php echo _translate("Test Type"); ?></label>
                                                        <select class="form-control test-type-select"
                                                            name="testResult[testType][]" id="testType1"
                                                            title="<?php echo _translate("Please select the test type"); ?>">
                                                            <option value=""><?php echo _translate("Select test type"); ?>
                                                            </option>
                                                            <option value="Smear Microscopy">Smear Microscopy</option>
                                                            <option value="TB LAM test">TB LAM test</option>
                                                            <option value="MTB/ RIF Ultra">MTB/ RIF Ultra</option>
                                                            <option value="MTB/ XDR (if RIF detected)">MTB/ XDR (if RIF
                                                                detected)</option>
                                                            <option value="TB culture and Drug susceptibility test (DST)">TB
                                                                culture and Drug susceptibility test (DST)</option>
                                                        </select>
                                                    </td>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="testResult1"><?php echo _translate("Test Result"); ?></label>
                                                        <select class="form-control test-result-select"
                                                            name="testResult[testResult][]" id="testResult1"
                                                            title="<?php echo _translate("Please select the test result"); ?>">
                                                            <option value=""><?php echo _translate("Select test result"); ?>
                                                            </option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="comments1"><?php echo _translate("Comments"); ?></label>
                                                        <textarea class="form-control" id="comments1"
                                                            name="testResult[comments][]"
                                                            placeholder="<?= _translate("Please enter comments"); ?>"
                                                            title="<?php echo _translate("Please enter comments"); ?>"></textarea>
                                                    </td>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="testedBy1"><?php echo _translate("Tested By"); ?></label>
                                                        <select name="testResult[testedBy][]" id="testedBy1"
                                                            class="form-control"
                                                            title="<?php echo _translate("Please choose tested by"); ?>">
                                                            <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                        </select>
                                                    </td>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="sampleTestedDateTime1"><?php echo _translate("Tested On"); ?></label>
                                                        <input type="text" class="date-time form-control"
                                                            id="sampleTestedDateTime1" name="testResult[sampleTestedDateTime][]"
                                                            placeholder="<?= _translate("Please enter date"); ?>"
                                                            title="<?php echo _translate("Please enter tested date"); ?>" />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="reviewedBy1"><?php echo _translate("Reviewed By"); ?></label>
                                                        <select name="testResult[reviewedBy][]" id="reviewedBy1"
                                                            class="form-control"
                                                            title="<?php echo _translate("Please choose reviewed by"); ?>">
                                                            <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                        </select>
                                                    </td>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="reviewedOn1"><?php echo _translate("Reviewed On"); ?></label>
                                                        <input type="text" name="testResult[reviewedOn][]" id="reviewedOn1"
                                                            class="date-time disabled-field form-control"
                                                            placeholder="<?php echo _translate("Reviewed On"); ?>"
                                                            title="<?php echo _translate("Please enter reviewed date"); ?>" />
                                                    </td>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="approvedBy1"><?php echo _translate("Approved By"); ?></label>
                                                        <select name="testResult[approvedBy][]" id="approvedBy1"
                                                            class="form-control"
                                                            title="<?php echo _translate("Please choose approved by"); ?>">
                                                            <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="approvedOn1"><?php echo _translate("Approved On"); ?></label>
                                                        <input type="text" name="testResult[approvedOn][]" id="approvedOn1"
                                                            class="date-time form-control"
                                                            placeholder="<?php echo _translate("Approved On"); ?>"
                                                            title="<?php echo _translate("Please enter approved date"); ?>" />
                                                    </td>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="revisedBy1"><?php echo _translate("Revised By"); ?></label>
                                                        <select name="testResult[revisedBy][]" id="revisedBy1"
                                                            class="form-control"
                                                            title="<?php echo _translate("Please choose revised by"); ?>">
                                                            <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                        </select>
                                                    </td>
                                                    <td style="width: 33.33%;">
                                                        <label class="label-control"
                                                            for="revisedOn1"><?php echo _translate("Revised On"); ?></label>
                                                        <input type="text" name="testResult[revisedOn][]" id="revisedOn1"
                                                            class="date-time form-control"
                                                            placeholder="<?php echo _translate("Enter the revised on date"); ?>"
                                                            title="<?php echo _translate("Please enter the revised on date"); ?>" />
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    <?php } ?>
                                </div>
                                <div class="controls" style="margin-top: 20px;">
                                    <button type="button" class="btn btn-success" onclick="addTestSection()">+
                                        <?php echo _translate("Add Test"); ?></button>
                                    <button type="button" id="removeTestBtn" class="btn btn-danger" style="display: none;"
                                        onclick="removeTestSection()">-
                                        <?php echo _translate("Remove Test"); ?></button>
                                </div>
                                <br>
                                <div class="row pr-5 fnal-result" <?php echo $referralResultDisplay; ?>>
                                    <div class="col-md-6">
                                        <label class="label-control" for="isResultFinalized"><?php echo _translate("Do you want to enter the Final Interpretation?"); ?></label>
                                        <select class="form-control" name="isResultFinalized" id="isResultFinalized" title="<?php echo _translate("Is result finalized"); ?>" onchange="finalResultChange();">
                                            <option value="no" <?php echo (isset($tbInfo['is_result_finalized']) && !empty($tbInfo['is_result_finalized']) && $tbInfo['is_result_finalized'] == "no") ? 'selected="selected"' : ''; ?>><?php echo _translate("No"); ?></option>
                                            <option value="yes" <?php echo (isset($tbInfo['is_result_finalized']) && !empty($tbInfo['is_result_finalized']) && $tbInfo['is_result_finalized'] == "yes") ? 'selected="selected"' : ''; ?>><?php echo _translate("Yes"); ?></option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 finalResult" <?php echo (isset($tbInfo['is_result_finalized']) && $tbInfo['is_result_finalized'] == "yes") ? '' : 'style="display:none;"'; ?>>
                                        <label class="label-control"
                                            for="finalResult"><?php echo _translate("Final Interpretation"); ?></label>
                                        <div class="resultInputContainer">
                                            <input type="text" list="possibleFinalResults" class="form-control"
                                                id="finalResult" name="finalResult"
                                                value="<?php echo $tbInfo['result'] ?? ''; ?>"
                                                placeholder="<?php echo _translate('Select or Type Final Interpretation'); ?>"
                                                title="<?php echo _translate('Please enter the final interpretation'); ?>"
                                                onchange="if(confirmFinalInterpretation(this)) { (this.value != '') ? $('.refer-inputs').hide(): $('.refer-inputs').show(); }" />
                                            <datalist id="possibleFinalResults">
                                                <?php foreach ($tbResults as $resultValue) { ?>
                                                    <option value="<?php echo $resultValue; ?>"><?php echo $resultValue; ?>
                                                    </option>
                                                <?php } ?>
                                            </datalist>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Footer -->
                        <div class="box-footer">
                            <?php if ($arr['tb_sample_code'] == 'auto' || $arr['tb_sample_code'] == 'YY' || $arr['tb_sample_code'] == 'MMYY') { ?>
                                <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat"
                                    value="<?php echo $sFormat; ?>" />
                                <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                            <?php } ?>
                            <a class="btn btn-primary btn-disabled" href="javascript:void(0);"
                                onclick="validateNow();return false;"><?php echo _translate("Save"); ?></a>
                            <input type="hidden" name="formId" id="formId" value="7" />
                            <input type="hidden" name="tbSampleId" id="tbSampleId" value="<?php echo $id; ?>" />
                            <a href="/tb/requests/tb-requests.php"
                                class="btn btn-default"><?php echo _translate("Cancel"); ?></a>
                        </div>
                    </form>
                <?php } ?>
            </div>
        </div>
    </section>
</div>

<style>
    .test-section {
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 5px;
        border: 1px solid #ddd;
    }

    .test-section:nth-child(odd) {
        background-color: #f9f9f9;
    }

    .test-section:nth-child(even) {
        background-color: #ffffff;
    }

    .test-section .section-header {
        font-size: 1.1em;
        color: #3c8dbc;
        margin-bottom: 10px;
        padding-bottom: 5px;
        border-bottom: 2px solid #3c8dbc;
    }
</style>

<script type="text/javascript">
    let provinceName = true;
    let facilityName = true;
    let testCount = $('.test-section').length || 1;

    // Test result options for each test type
    const testResultOptions = {
        "Smear Microscopy": [
            "Negative",
            "Scanty",
            "AFB Positive 1+",
            "AFB Positive 2+",
            "AFB Positive 3+"
        ],
        "TB LAM test": [
            "TB-LAM Negative",
            "TB-LAM Positive",
            "TB-LAM Invalid"
        ],
        "MTB/ RIF Ultra": [
            "MTB not detected",
            "MTB detected TRACE/RIF indeterminate",
            "MTB Detected Very Low/RIF not detected",
            "MTB Detected Very Low/RIF detected",
            "MTB Detected Low/RIF not detected",
            "MTB Detected Low/RIF detected",
            "MTB Detected Medium/RIF Not Detected",
            "MTB Detected Medium/RIF Detected",
            "MTB Detected High/RIF Not Detected",
            "MTB Detected High/RIF Detected"
        ],
        "MTB/ XDR (if RIF detected)": [
            "XDR not detected",
            "XDR detected",
            "No result/ invalid",
            "INH Resistance Detected",
            "INH Resistance Not Detected",
            "INH Resistance Indeterminate",
            "FLQ Resistance Detected",
            "FLQ Resistance Not Detected",
            "FLQ Resistance Indeterminate",
            "KAN Resistance Detected",
            "KAN Resistance Not Detected",
            "KAN Resistance Indeterminate",
            "CAP Resistance Detected",
            "CAP Resistance Not Detected",
            "CAP Resistance Indeterminate",
            "ETH Resistance Detected",
            "ETH Resistance Not Detected",
            "ETH Resistance Indeterminate"
        ],
        "TB culture and Drug susceptibility test (DST)": [
            "TB culture Negative",
            "TB culture Contaminated",
            "TB culture Positive with DST profile"
        ]
    };

    // Update test results dropdown based on selected test type
    function updateTestResults(rowNumber) {
        const testTypeSelect = document.getElementById(`testType${rowNumber}`);
        const testResultSelect = document.getElementById(`testResult${rowNumber}`);

        if (!testTypeSelect || !testResultSelect) {
            console.log(`Elements not found for row ${rowNumber}`);
            return;
        }

        const selectedTestType = testTypeSelect.value;

        // Clear existing options
        testResultSelect.innerHTML = '<option value=""><?php echo _translate("Select test result"); ?></option>';

        // Populate based on selected test type
        if (selectedTestType && testResultOptions[selectedTestType]) {
            testResultOptions[selectedTestType].forEach(option => {
                const optionElement = document.createElement('option');
                optionElement.value = option;
                optionElement.textContent = option;
                testResultSelect.appendChild(optionElement);
            });
        }
    }

    // Initialize date pickers for a section
    function initializeDatePickers(section) {
        // Regular date picker
        $(section).find('.date:not(.hasDatepicker)').datepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            maxDate: "Today",
            yearRange: <?= DateUtility::getYearMinus(100); ?> + ":" + "<?= date('Y') ?>"
        });

        // DateTime picker  
        $(section).find('.date-time:not(.hasDateTimePicker)').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            maxDate: "Today",
            yearRange: <?= DateUtility::getYearMinus(100); ?> + ":" + "<?= date('Y') ?>"
        });
    }

    // Initialize event handlers for a test section
    function initializeTestSection(section, sectionNumber) {
        const $section = $(section);

        // Initialize Select2 for dropdowns
        $section.find('.select2').each(function() {
            const $this = $(this);
            $this.removeClass('select2-hidden-accessible');
            $this.select2({
                placeholder: "Select option",
                width: '100%'
            });
        });

        // Sample rejection change handler
        $section.find('.sample-rejection-select').off('change.testSection').on('change.testSection', function() {
            const $row = $(this).closest('.test-section');
            if ($(this).val() === 'yes') {
                $row.find('.rejection-reason-field, .rejection-date-field').show();
                $row.find('.rejection-reason-select, .rejection-date').addClass('isRequired');
            } else {
                $row.find('.rejection-reason-field, .rejection-date-field').hide();
                $row.find('.rejection-reason-select, .rejection-date').removeClass('isRequired').val('');
            }
        });

        // Test type change handler - FIXED: Use proper event delegation
        $section.find('.test-type-select').off('change.testSection').on('change.testSection', function() {
            const sectionNum = $(this).closest('.test-section').attr('data-count');
            updateTestResults(sectionNum);
        });

        // Initialize date pickers
        initializeDatePickers(section);
    }

    // Add new test section
    function addTestSection() {
        testCount++;
        const container = document.getElementById('testSections');
        const firstSection = container.querySelector('.test-section');
        const newSection = firstSection.cloneNode(true);

        // Update section attributes
        newSection.setAttribute('data-count', testCount);
        newSection.querySelector('.section-number').textContent = testCount;

        // Update all IDs and names
        updateElementIds(newSection, testCount);

        // Clear form values
        clearSectionValues(newSection);
        // Remove stale Select2 container spans from cloned section
        $(newSection).find('.select2-container').remove();

        // Hide conditional fields
        $(newSection).find('.rejection-reason-field, .rejection-date-field').hide();
        $(newSection).find('.reasonForChange' + (testCount - 1)).hide();

        container.appendChild(newSection);

        // Initialize the new section
        initializeTestSection(newSection, testCount);
        updateRemoveButtonVisibility();
    }

    // Remove test section
    function removeTestSection() {
        if (testCount > 1) {
            const container = document.getElementById('testSections');
            const lastSection = container.querySelector('.test-section:last-child');
            if (lastSection) {
                lastSection.remove();
                testCount--;
                updateRemoveButtonVisibility();
            }
        }
    }

    // Update Remove button visibility based on test count
    function updateRemoveButtonVisibility() {
        const removeBtn = document.getElementById('removeTestBtn');
        if (removeBtn) {
            removeBtn.style.display = (testCount > 1) ? '' : 'none';
        }
    }

    // Update element IDs and names for new section
    function updateElementIds(section, count) {
        // Update labels
        $(section).find('label[for]').each(function() {
            const oldFor = $(this).attr('for');
            if (oldFor && /\d+$/.test(oldFor)) {
                const newFor = oldFor.replace(/\d+$/, count);
                $(this).attr('for', newFor);
            }
        });

        // Update form elements
        $(section).find('input[id], select[id], textarea[id]').each(function() {
            const oldId = $(this).attr('id');
            if (oldId && /\d+$/.test(oldId)) {
                const newId = oldId.replace(/\d+$/, count);
                $(this).attr('id', newId);

                // Remove datepicker classes to allow re-initialization
                $(this).removeClass('hasDatepicker hasDateTimePicker');
            }
        });

        // Update reason for change class
        $(section).find('.reasonForChange1, .reasonForChange2, .reasonForChange3').removeClass().addClass('reasonForChange' + count);
    }

    // Clear all values in a section
    function clearSectionValues(section) {
        $(section).find('input, select, textarea').each(function() {
            if (this.type === 'checkbox' || this.type === 'radio') {
                this.checked = false;
            } else if (this.tagName === 'SELECT') {
                this.selectedIndex = 0;
            } else {
                this.value = '';
            }
        });
    }

    // Utility functions
    function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
        var removeDots = obj.value.replace(/\./g, "").replace(/\,/g, "").replace(/\s{2,}/g, ' ');

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

        if (pName != '' && provinceName && facilityName) {
            facilityName = false;
        }

        if ($.trim(pName) != '') {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    pName: pName,
                    testType: 'tb'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#facilityId").html(details[0]);
                        $("#district").html(details[1]);
                    }
                });
            generateSampleCode();
        } else if (pName == '') {
            provinceName = true;
            facilityName = true;
            $("#province").html("<?php echo $province; ?>");
            $("#facilityId").html("<?php echo $facility; ?>");
            $("#facilityId").select2("val", "");
            $("#district").html("<option value=''> -- Select -- </option>");
        }
        $.unblockUI();
    }

    function getfacilityDistrictwise(obj) {
        $.blockUI();
        var dName = $("#district").val();
        var cName = $("#facilityId").val();

        if (dName != '') {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    dName: dName,
                    cliName: cName,
                    testType: 'tb'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#facilityId").html(details[0]);
                    }
                });
        } else {
            $("#facilityId").html("<option value=''> -- Select -- </option>");
        }
        $.unblockUI();
    }

    function getfacilityProvinceDetails(obj) {
        $.blockUI();
        var cName = $("#facilityId").val();

        if (cName != '' && provinceName && facilityName) {
            provinceName = false;
        }

        if (cName != '' && facilityName) {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    cName: cName,
                    testType: 'tb'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#province").html(details[0]);
                        $("#district").html(details[1]);
                        $("#clinicianName").val(details[2]);
                    }
                });
        } else if (cName == '') {
            provinceName = true;
            facilityName = true;
            $("#province").html("<?php echo $province; ?>");
            $("#facilityId").html("<?php echo $facility; ?>");
        }
        $.unblockUI();
    }

    function setPatientDetails(pDetails) {
        patientArray = JSON.parse(pDetails);
        $("#firstName").val(patientArray['firstname']);
        $("#lastName").val(patientArray['lastname']);
        $("#patientGender").val(patientArray['gender']);
        $("#patientAge").val(patientArray['age']);
        $("#dob").val(patientArray['dob']);
        $("#patientId").val(patientArray['patient_id']);
    }

    function generateSampleCode() {
        var pName = $("#province").val();
        var sDate = $("#sampleCollectionDate").val();
        var provinceCode = $("#province").find(":selected").attr("data-code");

        if (pName != '' && sDate != '') {
            $.post("/tb/requests/generate-sample-code.php", {
                    sampleCollectionDate: sDate,
                    provinceCode: provinceCode
                },
                function(data) {
                    var sCodeKey = JSON.parse(data);
                    $("#sampleCode").val(sCodeKey.sampleCode);
                    $("#sampleCodeInText").html(sCodeKey.sampleCodeInText);
                    $("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
                    $("#sampleCodeKey").val(sCodeKey.sampleCodeKey);
                });
        }
    }

    function validateNow() {
        if ($('#isResultAuthorized').val() != "yes") {
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
        }

        flag = deforayValidator.init({
            formId: 'editTbRequestForm'
        });

        if (flag) {
            document.getElementById('editTbRequestForm').submit();
        }
    }

    function showOther(obj, othersId) {
        if (obj == 'other') {
            $('.' + othersId).show();
        } else {
            $('.' + othersId).hide();
        }
    }

    function checkSubReason(obj, show, opUncheck, hide) {
        $('.reason-checkbox').prop("checked", false);
        if (opUncheck == "followup-uncheck") {
            $('#followUp').val("");
            $("#xPertMTMResult").prop('disabled', false);
        } else {
            $("#xPertMTMResult").prop('disabled', true);
        }
        $('.' + opUncheck).prop("checked", false);
        if ($(obj).prop("checked", true)) {
            $('.' + show).show(300);
            $('.' + show).removeClass(hide);
            $('.' + hide).hide(300);
            $('.' + show).addClass(hide);
        }
    }

    // Document ready function
    $(document).ready(function() {
        // Initialize Select2 for main form elements
        $("#facilityId, #province, #district").select2({
            placeholder: "<?php echo _translate('Select option'); ?>",
            width: '100%'
        });

        $('#typeOfPatient').select2({
            placeholder: "<?php echo _translate('Select case type'); ?>",
            width: '100%'
        });

        $('#specimenType').select2({
            placeholder: "<?php echo _translate('Select specimen type'); ?>",
            width: '100%'
        });

        // Initialize Purpose of TB Test Select2
        $('#purposeOfTbTest').select2({
            placeholder: "Select purpose of test"
        });

        // Initialize TB Tests Requested Select2
        $('#tbTestsRequested').select2({
            placeholder: "Select TB test(s) requested"
        });

        // Update Remove button visibility on page load
        updateRemoveButtonVisibility();

        <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
            $('#labId').select2({
                placeholder: "<?php echo _translate('Select testing lab'); ?>"
            });
        <?php } ?>

        // Initialize all existing test sections
        $('.test-section').each(function(index) {
            const sectionNumber = $(this).attr('data-count') || (index + 1);
            initializeTestSection(this, sectionNumber);

            // Initialize test results for existing sections
            const testTypeSelect = $(this).find('.test-type-select');
            if (testTypeSelect.length && testTypeSelect.val()) {
                // updateTestResults(sectionNumber);
            }
        });

        // Treatment initiation change handler
        $('#isPatientInitiatedTreatment').on('change', function() {
            if (this.value === 'yes') {
                $('.treatmentSelected').show();
                $('.treatmentSelectedInput').addClass('isRequired');
            } else {
                $('.treatmentSelected').hide();
                $('.treatmentSelectedInput').removeClass('isRequired').val('');
            }
        });

        // Lab and facility change handlers
        $("#labId, #facilityId, #sampleCollectionDate").on('change', function() {
            if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleDispatchedDate").val() == "") {
                $('#sampleDispatchedDate').datetimepicker("setDate", new Date($('#sampleCollectionDate').datetimepicker('getDate')));
            }
        });

        <?php if (isset($arr['tb_positive_confirmatory_tests_required_by_central_lab']) && $arr['tb_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            $(document).on('change', '.test-result, #result', function(e) {
                checkPostive();
            });
        <?php } ?>

        $("#labId").change(function(e) {
            if ($(this).val() != "") {
                $.post("/tb/requests/get-attributes-data.php", {
                        id: this.value,
                    },
                    function(data) {
                        if (data != "" && data != false) {
                            _data = jQuery.parseJSON(data);
                            $(".platform").hide();
                            $.each(_data, function(index, value) {
                                $("." + value).show();
                            });
                        }
                    });
            }
        });

        getfacilityProvinceDetails($('facilityId'));
        $('.disabledForm input, .disabledForm select , .disabledForm textarea').attr('disabled', true);
        $('.disabledForm input, .disabledForm select , .disabledForm textarea').removeClass("isRequired");
    });

    function confirmFinalInterpretation(input) {
        if (input.value !== '' && input.value !== input.dataset.previousValue) {
            if (!confirm('<?php echo _translate("Tests with Final Interpretation cannot be referred to other labs. Are you sure you want to continue?"); ?>')) {
                input.value = input.dataset.previousValue || '';
                (input.value != '') ? $('.refer-inputs').hide(): $('.refer-inputs').show();
                return false;
            }
        }
        input.dataset.previousValue = input.value;
        return true;
    }

    // Store initial value on focus
    document.getElementById('finalResult')?.addEventListener('focus', function() {
        this.dataset.previousValue = this.value;
    });

    function finalResultChange() {
        $('.finalResult').toggle();
        $('#finalResult').val('').trigger('change');
    }
</script>
<script type="text/javascript"
    src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/datalist-css.min.js") ?>"></script>