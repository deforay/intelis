<?php
// imported in tb-add-request.php based on country in global config

use App\Registries\ContainerRegistry;
use App\Services\TbService;


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
$facility = $general->generateSelectOptions($healthFacilities, null, '-- Select --');
$microscope = array("No AFB" => "No AFB", "1+" => "1+", "2+" => "2+", "3+" => "3+");
?>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _translate("TB LABORATORY TEST REQUEST FORM"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Add New Request"); ?></li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="box box-default">
            <div class="box-header with-border">

                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate("indicates required fields"); ?> &nbsp;</div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->
                <form class="form-horizontal" method="post" name="addTbRequestForm" id="addTbRequestForm" autocomplete="off" action="tb-add-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border sectionHeader" style=" display: flex; ">
                                    <div class="col-md-7">
                                        <h3 class="box-title"><?php echo _translate("FACILITY INFORMATION"); ?></h3>
                                    </div>
                                    <div class="col-md-5" style=" display: flex; ">
                                        <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
                                            <span style="width: 20%;"><label class="label-control" for="sampleCode"><?php echo _translate("Sample ID"); ?> </label></span>
                                            <span style="width: 80%;" id="sampleCodeInText" style="width:80%;border-bottom:1px solid #333;"></span>
                                            <input type="hidden" id="sampleCode" name="sampleCode" />
                                        <?php } else { ?>
                                            <span style="width: 20%;"><label class="label-control" for="sampleCode"><?php echo _translate("Sample ID"); ?> </label><span class="mandatory">*</span></span>
                                            <inpu style="width: 80%;" t type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" readonly="readonly" placeholder="Sample ID" title="<?php echo _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>" style="width:80%;" onchange="checkSampleNameValidation('form_tb','<?php echo $sampleCode; ?>',this.id,null,'The Sample ID that you entered already exists. Please try another Sample ID',null)" />
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;"><?php echo _translate("To be filled by requesting Clinician/Nurse"); ?></h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <td style="width: 50%;"><label class="label-control" for="facilityId"><?php echo _translate("Health Facility Name"); ?> </label><span class="mandatory">*</span>
                                            <select class="form-control isRequired " name="facilityId" id="facilityId" title="<?php echo _translate("Please choose facility"); ?>" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
                                                <?php echo $facility; ?>
                                            </select>
                                        </td>
                                        <td style="width: 50%;"><label class="label-control" for="district"><?php echo _translate("Health Facility County"); ?> </label><span class="mandatory">*</span>
                                            <select class="form-control select2 isRequired" name="district" id="district" title="<?php echo _translate("Please choose County"); ?>" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                                                <option value=""> -- <?php echo _translate("Select"); ?> -- </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="width: 50%;"><label class="label-control" for="province"><?php echo _translate("Health Facility State"); ?> </label><span class="mandatory">*</span>
                                            <select class="form-control select2 isRequired" name="province" id="province" title="<?php echo _translate("Please choose State"); ?>" onchange="getfacilityDetails(this);" style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <td style="width: 50%;"><label class="label-control" for="testNumber"><?php echo _translate("Afflicated TB Testing Site"); ?></label>
                                            <input type="text" name="testNumber" id="testNumber" placeholder="Enter the afflicated TB testing site" class="form-control" title="<?php echo _translate("Please select afflicated TB testing site"); ?>" style="width:100%;" />
                                        </td>
                                    </tr>
                                    <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
                                        <tr>
                                            <td style="width: 50%;"><label class="label-control" for="labId"><?php echo _translate("Testing Laboratory"); ?> <span class="mandatory">*</span></label>
                                                <select name="labId" id="labId" class="form-control select2 isRequired" title="<?php echo _translate("Please select Testing Testing Laboratory"); ?>" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </table>
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title"><?php echo _translate("PATIENT DETAILS"); ?>:</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;"><?php echo _translate("Complete full information on patient identification, contact and type of patient by TB program definition"); ?></h3>
                                    <a style="margin-top:-0.35%;margin-left:10px;" href="javascript:void(0);" class="btn btn-default pull-right btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em><?php echo _translate("Search"); ?></a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;<?php echo _translate("No Patient Found"); ?></strong></span>
                                    <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="pull-right" placeholder="<?php echo _translate("Enter Patient ID or Patient Name"); ?>" title="<?php echo _translate("Enter art number or patient name"); ?>" />&nbsp;&nbsp;
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr class="encryptPIIContainer">
                                        <td style="width: 50%;" style="width:15% !important"><label for="childId"><?= _translate('Encrypt PII'); ?> </label>
                                            <select name="encryptPII" id="encryptPII" class="form-control" title="<?= _translate('Encrypt Patient Identifying Information'); ?>">
                                                <option value=""><?= _translate('--Select--'); ?></option>
                                                <option value="no" selected='selected'><?= _translate('No'); ?></option>
                                                <option value="yes"><?= _translate('Yes'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="width: 40%;">
                                            <label for="trackerNo"><?= _translate('e-TB tracker No.'); ?></label>
                                            <input type="text" class="form-control" id="trackerNo" name="trackerNo" placeholder="<?php echo _translate("Enter the e-TB tracker number"); ?>" title="<?php echo _translate("Please enter the e-TB tracker number"); ?>" style="width:100%;" />
                                        </td>

                                        <td style="width: 60%;display: ruby-text;margin-top: 40px;">
                                            <div style="width:50%">
                                                <label for="dob"><?= _translate('Date of Birth'); ?></label>
                                                <input type="text" class="form-control date" id="dob" name="dob" placeholder="<?php echo _translate("Date of Birth"); ?>" title="<?php echo _translate("Please enter Date of birth"); ?>" style="width:100%;" onchange="calculateAgeInYears('dob', 'patientAge');" />
                                            </div>
                                            <div style="width:50%;">
                                                <label for="childId"><?= _translate('Age (years)'); ?></label>
                                                <input type="number" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="patientAge" name="patientAge" placeholder="<?php echo _translate("Age (in years)"); ?>" title="<?php echo _translate("Patient Age"); ?>" style="width:100%;" onchange="" />
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="width: 50%;">
                                            <label for="patientId"><?php echo _translate("Patient ID"); ?></label>
                                            <input type="text" class="form-control patientId" id="patientId" name="patientId" placeholder="Patient Identification" title="<?php echo _translate("Please enter Patient ID"); ?>" style="width:100%;" onchange="" />
                                        </td>
                                        <td style="width: 50%;">
                                            <label for="patientGender"><?php echo _translate("Sex"); ?> <span class="mandatory">*</span> </label>
                                            <select class="form-control isRequired" name="patientGender" id="patientGender" title="<?php echo _translate("Please choose sex"); ?>">
                                                <option value=''> -- <?php echo _translate("Select"); ?> -- </option>
                                                <option value='male'> <?php echo _translate("Male"); ?> </option>
                                                <option value='female'> <?php echo _translate("Female"); ?> </option>
                                                <option value='other'> <?php echo _translate("Other"); ?> </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="width: 50%;">
                                            <label for="firstName"><?php echo _translate("First Name"); ?> <span class="mandatory">*</span> </label>
                                            <input type="text" class="form-control isRequired" id="firstName" name="firstName" placeholder="<?php echo _translate("First Name"); ?>" title="<?php echo _translate("Please enter First name"); ?>" style="width:100%;" onchange="" />
                                        </td>
                                        <td style="width: 50%;">
                                            <label for="lastName"><?php echo _translate("Surname"); ?> </label>
                                            <input type="text" class="form-control " id="lastName" name="lastName" placeholder="<?php echo _translate("Last name"); ?>" title="<?php echo _translate("Please enter Last name"); ?>" style="width:100%;" onchange="" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="width: 50%;">
                                            <label for="patientPhoneNumber"><?php echo _translate("Phone contact"); ?>: </label>
                                            <input type="text" class="form-control checkNum" id="patientPhoneNumber" name="patientPhoneNumber" placeholder="<?php echo _translate("Last name"); ?>" title="<?php echo _translate("Please enter Last name"); ?>" style="width:100%;" />
                                        </td>
                                        <td style="width: 50%;">
                                            <label for="typeOfPatient"><?php echo _translate("Type of patient"); ?><span class="mandatory">*</span></label><br>
                                            <select class="select2 form-control isRequired" name="typeOfPatient[]" id="typeOfPatient" title="<?php echo _translate("Please select the type of patient"); ?>" onchange="showOther(this.value,'typeOfPatientOther');" multiple>
                                                <option value=''> -- <?php echo _translate("Select"); ?> -- </option>
                                                <option value='new'> New </option>
                                                <option value='loss-to-follow-up'> Loss to Follow Up </option>
                                                <option value='treatment-failure'> Treatment Failure </option>
                                                <option value='relapse'> Relapse </option>
                                                <option value='other'> <?php echo _translate("Other"); ?> </option>
                                            </select>
                                            <input type="text" class="form-control typeOfPatientOther" id="typeOfPatientOther" name="typeOfPatientOther" placeholder="<?php echo _translate("Enter type of patient if others"); ?>" title="<?php echo _translate("Please enter type of patient if others"); ?>" style="display: none;" />
                                        </td>
                                    </tr>
                                </table>

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title"><?php echo _translate("TREATMENT AND RISK FACTORS INFORMATION"); ?></h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;"><?php echo _translate("Please complete full information on treatment history, TB regimen and risk factors"); ?></h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <td style="width: 50%;">
                                            <label for="patientPhoneNumber"><?php echo _translate("Is patient initiated on TB treatment?"); ?>: </label>
                                            <select name="isPatientInitiatedTreatment" id="isPatientInitiatedTreatment" class="form-control isRequired" title="Please choose specimen type" style="width:100%">
                                                <option value=''>-- <?php echo _translate("Select"); ?> --</option>
                                                <option value='no'><?php echo _translate("No"); ?></option>
                                                <option value='yes'><?php echo _translate("Yes"); ?></option>
                                            </select>
                                        </td>
                                        <td class="treatmentSelected" style="width: 50%; display: none;">
                                            <label class="label-control" for="treatmentDate"><?php echo _translate("Date of treatment Initiation"); ?><span class="mandatory">*</span></label>
                                            <input type="text" name="treatmentDate" id="treatmentDate" class="treatmentSelectedInput form-control date" readonly title="Please choose specimen type" style="width:100%" />
                                        </td>
                                    </tr>
                                    <tr class="treatmentSelected" style="display: none;">
                                        <td style="width: 50%;">
                                            <label for="currentRegimen" class="label-control"><?php echo _translate("Current regimen"); ?><span class="mandatory">*</span></label>
                                            <input type="text" class="form-control treatmentSelectedInput" id="currentRegimen" name="currentRegimen" placeholder="<?php echo _translate('Enter the current regimen'); ?>" title="<?php echo _translate('Please enter Risk Factor name'); ?>">
                                        </td>
                                        <td style="width: 50%;">
                                            <label class="label-control" for="regimenDate"><?php echo _translate("Date of Initiation of Current Regimen"); ?><span class="mandatory">*</span></label>
                                            <input type="text" name="regimenDate" id="regimenDate" class="treatmentSelectedInput form-control date" readonly title="Please choose date of current regimen" style="width:100%" />
                                        </td>
                                    </tr>
                                    <tr class="treatmentSelected" style="display: none;">
                                        <td colspan="4">
                                            <label class="label-control" for="regimenDate"><?php echo _translate("Risk Factors"); ?><span class="mandatory">*</span></label></br>
                                            <ul style=" display: inline-flex; list-style: none; padding: 0px; ">
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="diagnosis-check reason-checkbox" id="tBContact" name="riskFactors[TB Contact]" value="yes">
                                                    </label>
                                                    <label class="radio-inline" for="tBContact" style="padding-left:17px !important;margin-left:0;">TB Contact</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="diagnosis-check reason-checkbox" id="PLHIV" name="riskFactors[PLHIV]" value="yes">
                                                    </label>
                                                    <label class="radio-inline" for="PLHIV" style="padding-left:17px !important;margin-left:0;">PLHIV</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="diagnosis-check reason-checkbox" id="healthcareProvider" name="riskFactors[Healthcare provider]" value="yes">
                                                    </label>
                                                    <label class="radio-inline" for="healthcareProvider" style="padding-left:17px !important;margin-left:0;">Healthcare provider</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="diagnosis-check reason-checkbox" id="CHW" name="riskFactors[CHW]" value="yes">
                                                    </label>
                                                    <label class="radio-inline" for="CHW" style="padding-left:17px !important;margin-left:0;">CHW</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="diagnosis-check reason-checkbox" id="prisonerInmate" name="riskFactors[Prisoner inmate]" value="yes">
                                                    </label>
                                                    <label class="radio-inline" for="prisonerInmate" style="padding-left:17px !important;margin-left:0;">Prisoner/ inmate</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="diagnosis-check reason-checkbox" id="tobaccoSmoking" name="riskFactors[Tobacco smoking]" value="yes">
                                                    </label>
                                                    <label class="radio-inline" for="tobaccoSmoking" style="padding-left:17px !important;margin-left:0;">Tobacco smoking</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="diagnosis-check reason-checkbox" id="crowdedHabitant" name="riskFactors[Crowded habitant]" value="yes">
                                                    </label>
                                                    <label class="radio-inline" for="crowdedHabitant" style="padding-left:17px !important;margin-left:0;">Crowded habitant</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="diagnosis-check reason-checkbox" id="diabetic" name="riskFactors[Diabetic]" value="yes">
                                                    </label>
                                                    <label class="radio-inline" for="diabetic" style="padding-left:17px !important;margin-left:0;">Diabetic</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="diagnosis-check reason-checkbox" id="miner" name="riskFactors[Miner]" value="yes">
                                                    </label>
                                                    <label class="radio-inline" for="miner" style="padding-left:17px !important;margin-left:0;">Miner</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="diagnosis-check reason-checkbox" id="refugeeCamp" name="riskFactors[Refugee camp]" value="yes">
                                                    </label>
                                                    <label class="radio-inline" for="refugeeCamp" style="padding-left:17px !important;margin-left:0;">Refugee camp</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="diagnosis-check reason-checkbox" id="others" name="riskFactors[Others]" value="yes">
                                                    </label>
                                                    <label class="radio-inline" for="others" style="padding-left:17px !important;margin-left:0;">Others</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="diagnosis-check reason-checkbox" id="none" name="riskFactors[No information provided]" value="yes">
                                                    </label>
                                                    <label class="radio-inline" for="none" style="padding-left:17px !important;margin-left:0;"><?php echo _translate("No information provided"); ?></label>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                </table>
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title"><?php echo _translate("PURPOSE AND TEST(S) REQUESTED FOR TB DIAGNOSTICS"); ?></h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;"><?php echo _translate("Please tick/ circle all as applicable below"); ?></h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr style=" border: 1px solid #8080804f; ">
                                        <td>
                                            <label class="radio-inline" style="margin-left:0;">
                                                <input type="radio" class="isRequired diagnosis-check" id="reasonForTbTest1" name="requestPurpose[purpose]" value="diagnosis" title="<?php echo _translate("Select reason for examination"); ?>" onchange="checkSubReason(this,'diagnosis','followup-uncheck', 'purpose-hide-reasons');">
                                                <strong><?php echo _translate("Purpose of TB test(s)"); ?></strong>
                                            </label>
                                        </td>
                                        <td style="float: left;text-align: center;" colspan="3">
                                            <div class="diagnosis purpose-hide-reasons" style="display: none;">
                                                <ul style=" display: inline-flex; list-style: none; padding: 0px; ">
                                                    <li>
                                                        <label class="radio-inline" style="width:4%;margin-left:0;">
                                                            <input type="checkbox" class="diagnosis-check reason-checkbox" id="initialTbDiagnosis" name="purposeOfTbTest[Initial TB diagnosis]" value="yes">
                                                        </label>
                                                        <label class="radio-inline" for="initialTbDiagnosis" style="padding-left:17px !important;margin-left:0;">Initial TB diagnosis</label>
                                                    </li>
                                                    <li>
                                                        <label class="radio-inline" style="width:4%;margin-left:0;">
                                                            <input type="checkbox" class="diagnosis-check reason-checkbox" id="DSTBTreatmentFollowUp" name="purposeOfTbTest[DS-TB Treatment Follow-Up]" value="yes">
                                                        </label>
                                                        <label class="radio-inline" for="DSTBTreatmentFollowUp" style="padding-left:17px !important;margin-left:0;">DS-TB Treatment Follow-Up</label>
                                                    </li>
                                                    <li>
                                                        <label class="radio-inline" style="width:4%;margin-left:0;">
                                                            <input type="checkbox" class="diagnosis-check reason-checkbox" id="C2" name="purposeOfTbTest[C2]" value="yes">
                                                        </label>
                                                        <label class="radio-inline" for="C2" style="padding-left:17px !important;margin-left:0;">C2</label>
                                                    </li>
                                                    <li>
                                                        <label class="radio-inline" style="width:4%;margin-left:0;">
                                                            <input type="checkbox" class="diagnosis-check reason-checkbox" id="C5" name="purposeOfTbTest[C5]" value="yes">
                                                        </label>
                                                        <label class="radio-inline" for="C5" style="padding-left:17px !important;margin-left:0;">C5</label>
                                                    </li>
                                                    <li>
                                                        <label class="radio-inline" style="width:4%;margin-left:0;">
                                                            <input type="checkbox" class="diagnosis-check reason-checkbox" id="endofTBtreatment" name="purposeOfTbTest[End of TB treatment]" value="yes">
                                                        </label>
                                                        <label class="radio-inline" for="endofTBtreatment" style="padding-left:17px !important;margin-left:0;">End of TB treatment</label>
                                                    </li>
                                                    <li>
                                                        <label class="radio-inline" style="width:4%;margin-left:0;">
                                                            <input type="checkbox" class="diagnosis-check reason-checkbox" id="DRTBPatientBaselineTests" name="purposeOfTbTest[DR-TB Patient Baseline tests]" value="yes">
                                                        </label>
                                                        <label class="radio-inline" for="DRTBPatientBaselineTests" style="padding-left:17px !important;margin-left:0;">DR-TB Patient Baseline tests</label>
                                                    </li>
                                                    <li>
                                                        <label class="radio-inline" style="width:4%;margin-left:0;">
                                                            <input type="checkbox" class="diagnosis-check reason-checkbox" id="DRTBpatientFollowup" name="purposeOfTbTest[DR-TB patient Follow up]" value="yes">
                                                        </label>
                                                        <label class="radio-inline" for="DRTBpatientFollowup" style="padding-left:17px !important;margin-left:0;">DR-TB patient Follow up</label>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr style=" border: 1px solid #8080804f; ">
                                        <td>
                                            <label class="radio-inline" style="margin-left:0;">
                                                <input type="radio" class="isRequired testrequest-check" id="testTypeRequested1" name="requestPurpose[request]" value="" title="Select TB test(s) requested" onchange="checkSubReason(this,'testrequest','followup-uncheck', 'request-hide-reasons');">
                                                <strong><?php echo _translate("TB test(s) requested"); ?></strong>
                                            </label>
                                        </td>
                                        <td style="float: left;text-align: center;" colspan="3">
                                            <div class="testrequest request-hide-reasons" style="display: none;">
                                                <ul style=" display: inline-flex; list-style: none; padding: 0px; ">
                                                    <li>
                                                        <label class="radio-inline" style="width:4%;margin-left:0;">
                                                            <input type="checkbox" class="testrequest-check reason-checkbox" id="LEDMicroscopy" name="testTypeRequested[LED microscopy]" value="yes">
                                                        </label>
                                                        <label class="radio-inline" for="LEDMicroscopy" style="padding-left:17px !important;margin-left:0;">LED microscopy</label>
                                                    </li>
                                                    <li>
                                                        <label class="radio-inline" style="width:4%;margin-left:0;">
                                                            <input type="checkbox" class="testrequest-check reason-checkbox" id="TBLAMTest" name="testTypeRequested[TB LAM test]" value="yes">
                                                        </label>
                                                        <label class="radio-inline" for="TBLAMTest" style="padding-left:17px !important;margin-left:0;">TB LAM test</label>
                                                    </li>
                                                    <li>
                                                        <label class="radio-inline" style="width:4%;margin-left:0;">
                                                            <input type="checkbox" class="testrequest-check reason-checkbox" id="MTBRIFUltra " name="testTypeRequested[MTB/ RIF Ultra ]" value="yes">
                                                        </label>
                                                        <label class="radio-inline" for="MTBRIFUltra" style="padding-left:17px !important;margin-left:0;">MTB/ RIF Ultra </label>
                                                    </li>
                                                    <li>
                                                        <label class="radio-inline" style="width:4%;margin-left:0;">
                                                            <input type="checkbox" class="testrequest-check reason-checkbox" id="MTBXDR" name="testTypeRequested[MTB/ XDR (if RIF detected)]" value="yes">
                                                        </label>
                                                        <label class="radio-inline" for="MTBXDR" style="padding-left:17px !important;margin-left:0;">MTB/ XDR (if RIF detected)</label>
                                                    </li>
                                                    <li>
                                                        <label class="radio-inline" style="width:4%;margin-left:0;">
                                                            <input type="checkbox" class="testrequest-check reason-checkbox" id="tBCultureAndDrugSusceptibilityTest" name="testTypeRequested[TB culture and Drug susceptibility test (DST)]" value="yes">
                                                        </label>
                                                        <label class="radio-inline" for="tBCultureAndDrugSusceptibilityTest" style="padding-left:17px !important;margin-left:0;"><?php echo _translate("TB culture and Drug susceptibility test (DST)"); ?></label>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                </table>

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title"><?php echo _translate("SPECIMEN INFORMATION"); ?></h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;"><?php echo _translate("Please complete full information and circle/ tick as appropriate"); ?></h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true">
                                    <tr>
                                        <td style="width: 50%;">
                                            <label class="label-control" for="sampleCollectionDate"><?php echo _translate("Date Specimen Collected"); ?><span class="mandatory">*</span></label>
                                            <input class="form-control isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="<?php echo _translate("Sample Collection Date"); ?>" onchange="generateSampleCode(); checkCollectionDate(this.value);" />
                                            <span class="expiredCollectionDate" style="color:red; display:none;"></span>
                                        </td>
                                        <td style="width: 50%;">
                                            <label class="label-control" for="specimenType"><?php echo _translate("Specimen Type"); ?> <span class="mandatory">*</span></label>
                                            <select name="specimenType" id="specimenType" class="form-control isRequired select2" title="<?php echo _translate("Please choose specimen type"); ?>" multiple style="width:100%" onchange="showOther(this.value,'specimenTypeOther')">
                                                <?php echo $general->generateSelectOptions($specimenTypeResult, null, '-- Select --'); ?>
                                                <option value='other'> <?php echo _translate("Other"); ?> </option>
                                            </select>
                                            <input type="text" class="form-control specimenTypeOther" id="specimenTypeOther" name="specimenTypeOther" placeholder="<?php echo _translate("Enter specimen type of others"); ?>" title="<?php echo _translate("Please enter the specimen type if others"); ?>" style="display: none;" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="width: 50%;">
                                            <label class="label-control" for="reOrderedCorrectiveAction"><?php echo _translate("Is specimen re-ordered as part of corrective action?"); ?></label>
                                            <select class="form-control" name="reOrderedCorrectiveAction" id="reOrderedCorrectiveAction" title="<?php echo _translate("Is specimen re-ordered as part of corrective action"); ?>" style="width:100%;">
                                                <option value="">--<?php echo _translate("Select"); ?>--</option>
                                                <option value="no"><?php echo _translate("No"); ?></option>
                                                <option value="yes"><?php echo _translate("Yes"); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php if (_isAllowed('/tb/results/tb-update-result.php') || $_SESSION['accessType'] != 'collection-site') { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title"><?php echo _translate("TEST RESULTS INFORMATION"); ?></h3>
                                    </div>
                                    <div class="box-header with-border">
                                        <h3 class="box-title" style="font-size:1em;"><?php echo _translate("Please complete full information and appropriate results with reference to TB test(s) requested above"); ?></h3>
                                    </div>
                                    <div id="testSections">
                                        <!-- Initial test section -->
                                        <div class="test-section" data-count="1">
                                            <div class="section-header">Test #<span class="section-number">1</span></div>
                                            <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                                <tr>
                                                    <td style="width: 50%;">
                                                        <label class="label-control" for="labId1"><?php echo _translate("Testing Lab"); ?></label>
                                                        <select name="labId[]" id="labId1" class="form-control resultSelect2" title="<?php echo _translate("Please select testing laboratory"); ?>" style="width:100%;">
                                                            <?= $general->generateSelectOptions($testingLabs, null, '-- Select lab --'); ?>
                                                        </select>
                                                    </td>
                                                    <td style="width: 50%;">
                                                        <label class="label-control" for="sampleReceivedDate"><?php echo _translate("Date specimen received at TB testing site"); ?> </label>
                                                        <input type="text" class="date-time form-control" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate("Please enter date"); ?>" title="<?php echo _translate("Please enter sample receipt date"); ?>" style="width:100%;" />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="width: 50%;">
                                                        <label class="label-control" for="isSampleRejected"><?php echo _translate("Is Sample Rejected?"); ?></label>
                                                        <select class="form-control" name="isSampleRejected" id="isSampleRejected" title="<?php echo _translate("Please select the Is sample rejected?"); ?>">
                                                            <option value=''> -- <?php echo _translate("Select"); ?> -- </option>
                                                            <option value="yes"> <?php echo _translate("Yes"); ?> </option>
                                                            <option value="no"> <?php echo _translate("No"); ?> </option>
                                                        </select>
                                                    </td>
                                                    <td style="width: 50%;display:none;" class="show-rejection">
                                                        <label class="label-control" for="sampleRejectionReason"><?php echo _translate("Reason for Rejection"); ?><span class="mandatory">*</span></label>
                                                        <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="<?php echo _translate("Please select the reason for rejection"); ?>">
                                                            <option value=''> -- <?php echo _translate("Select"); ?> -- </option>
                                                            <?php echo $rejectionReason; ?>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr class="show-rejection" style="display:none;">
                                                    <td style="width: 50%;">
                                                        <label class="label-control" for="rejectionDate"><?php echo _translate("Rejection Date"); ?><span class="mandatory">*</span></label>
                                                        <input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="<?php echo _translate("Select rejection date"); ?>" title="<?php echo _translate("Please select the rejection date"); ?>" />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <!-- <td style="width: 50%;">
                                                        <label class="label-control" for="sampleDispatchedDate1"><?php echo _translate("Sample Dispatched On"); ?></label>
                                                        <input type="text" class="date-time form-control" id="sampleDispatchedDate1" name="sampleDispatchedDate" placeholder="<?= _translate("Please enter date"); ?>" title="<?php echo _translate("Please choose sample dispatched date"); ?>" style="width:100%;" />
                                                    </td> -->
                                                    <td style="width: 50%;">
                                                        <label class="label-control" for="specimenType1"><?php echo _translate("Specimen Type"); ?></label>
                                                        <select name="specimenType[]" id="specimenType1" class="form-control resultSelect2 select2" title="<?php echo _translate("Please choose specimen type"); ?>" style="width:100%">
                                                            <?php echo $general->generateSelectOptions($specimenTypeResult, null, '-- Select --'); ?>
                                                        </select>
                                                    </td>
                                                    <td style="width: 50%;">
                                                        <label class="label-control" for="testType1"><?php echo _translate("Test Type"); ?></label>
                                                        <select class="form-control resultSelect2" name="testType[]" id="testType1" title="<?php echo _translate("Please select the test type"); ?>" onchange="updateTestResults(1);updateRowStyling(1)">
                                                            <option value=""><?php echo _translate("Select test type"); ?></option>
                                                            <option value="Smear Microscopy">Smear Microscopy</option>
                                                            <option value="TB LAM test">TB LAM test</option>
                                                            <option value="MTB/ RIF Ultra">MTB/ RIF Ultra</option>
                                                            <option value="MTB/ XDR (if RIF detected)">MTB/ XDR (if RIF detected)</option>
                                                            <option value="TB culture and Drug susceptibility test (DST)">TB culture and Drug susceptibility test (DST)</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="width: 50%;">
                                                        <label class="label-control" for="testResult1"><?php echo _translate("Test Result"); ?></label><br>
                                                        <select class="form-control resultSelect2" name="testResults[]" id="testResult1" title="<?php echo _translate("Please select the test result"); ?>" onchange="updateRowStyling(1)">
                                                            <option value=""><?php echo _translate("Select test result"); ?></option>
                                                        </select>
                                                    </td>
                                                    <td style="width: 50%;">
                                                        <label class="label-control" for="sampleTestedDateTime1"><?php echo _translate("Tested On"); ?></label>
                                                        <input type="text" class="date-time form-control" id="sampleTestedDateTime1" name="sampleTestedDateTime[]" placeholder="<?= _translate("Please enter date"); ?>" title="<?php echo _translate("Please enter sample tested"); ?>" style="width:100%;" />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="width: 50%;">
                                                        <label class="label-control" for="reviewedBy1"><?php echo _translate("Reviewed By"); ?></label>
                                                        <select name="reviewedBy[]" id="reviewedBy1" class="resultSelect2 form-control" title="<?php echo _translate("Please choose reviewed by"); ?>" style="width: 100%;">
                                                            <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                        </select>
                                                    </td>
                                                    <td style="width: 50%;">
                                                        <label class="label-control" for="reviewedOn1"><?php echo _translate("Reviewed on"); ?></label>
                                                        <input type="text" name="reviewedOn[]" id="reviewedOn1" class="dateTime disabled-field form-control" placeholder="<?php echo _translate("Reviewed on"); ?>" title="<?php echo _translate("Please enter the Reviewed on"); ?>" />
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="width: 50%;">
                                                        <label class="label-control" for="approvedBy1"><?php echo _translate("Approved By"); ?></label>
                                                        <select name="approvedBy[]" id="approvedBy1" class="resultSelect2 form-control" title="<?php echo _translate("Please choose approved by"); ?>" style="width: 100%;">
                                                            <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                        </select>
                                                    </td>
                                                    <td style="width: 50%;">
                                                        <label class="label-control" for="approvedOn1"><?php echo _translate("Approved on"); ?></label>
                                                        <input type="text" name="approvedOn[]" id="approvedOn1" class="dateTime form-control" placeholder="<?php echo _translate("Approved on"); ?>" title="<?php echo _translate("Please enter the approved on"); ?>" />
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="controls">
                                        <button type="button" class="btn btn-success" onclick="addTestSection()">+ Add Test</button>
                                        <button type="button" class="btn btn-danger" onclick="removeTestSection()">- Remove Test</button>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <?php if ($arr['tb_sample_code'] == 'auto' || $arr['tb_sample_code'] == 'YY' || $arr['tb_sample_code'] == 'MMYY') { ?>
                            <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                            <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                            <input type="hidden" name="saveNext" id="saveNext" />
                        <?php } ?>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Save"); ?></a>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();$('#saveNext').val('next');return false;"><?php echo _translate("Save and Next"); ?></a>
                        <input type="hidden" name="formId" id="formId" value="1" />
                        <input type="hidden" name="tbSampleId" id="tbSampleId" value="" />
                        <a href="/tb/requests/tb-requests.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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
    provinceName = true;
    facilityName = true;

    function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
        var removeDots = obj.value.replace(/\./g, "");
        removeDots = removeDots.replace(/\,/g, "");
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

    function getTestingPoints() {
        var labId = $("#labId").val();
        var selectedTestingPoint = null;
        if (labId) {
            $.post("/includes/getTestingPoints.php", {
                    labId: labId,
                    selectedTestingPoint: selectedTestingPoint
                },
                function(data) {
                    if (data != "") {
                        $(".testingPointField").show();
                        $("#testingPoint").html(data);
                    } else {
                        $(".testingPointField").hide();
                        $("#testingPoint").html('');
                    }
                });
        }
    }

    function getfacilityDetails(obj) {

        $.blockUI();
        var cName = $("#facilityId").val();
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

    function getPatientDistrictDetails(obj) {

        $.blockUI();
        var pName = obj.value;
        if ($.trim(pName) != '') {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    pName: pName,
                    testType: 'tb'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#patientDistrict").html(details[1]);
                    }
                });
        } else if (pName == '') {
            $(obj).html("<?php echo $province; ?>");
            $("#patientDistrict").html("<option value=''> -- Select -- </option>");
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
        //check facility name
        var cName = $("#facilityId").val();
        var pName = $("#province").val();
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
        } else if (pName == '' && cName == '') {
            provinceName = true;
            facilityName = true;
            $("#province").html("<?php echo $province; ?>");
            $("#facilityId").html("<?php echo $facility; ?>");
        }
        $.unblockUI();
    }


    function validateNow() {
        if ($('#isResultAuthorized').val() != "yes") {
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
        }
        flag = deforayValidator.init({
            formId: 'addTbRequestForm'
        });
        if (flag) {
            $('.btn-disabled').attr('disabled', 'yes');
            $(".btn-disabled").prop("onclick", null).off("click");
            <?php
            if ($arr['tb_sample_code'] == 'auto' || $arr['tb_sample_code'] == 'YY' || $arr['tb_sample_code'] == 'MMYY') {
            ?>
                insertSampleCode('addTbRequestForm', 'tbSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 3, 'sampleCollectionDate');
            <?php
            } else {
            ?>
                document.getElementById('addTbRequestForm').submit();
            <?php
            } ?>
        }
    }

    function checkIsResultAuthorized() {
        if ($('#isResultAuthorized').val() == 'no') {
            $('#authorizedBy,#authorizedOn').val('');
            $('#authorizedBy,#authorizedOn').prop('disabled', true);
            $('#authorizedBy,#authorizedOn').addClass('disabled');
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
        } else {
            $('#authorizedBy,#authorizedOn').prop('disabled', false);
            $('#authorizedBy,#authorizedOn').removeClass('disabled');
            $('#authorizedBy,#authorizedOn').addClass('isRequired');
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

    let testCount = 1;

    function addTestSection() {
        testCount++;
        const container = document.getElementById('testSections');
        const firstSection = container.querySelector('.test-section');
        const newSection = firstSection.cloneNode(true);

        // Update data-count attribute
        newSection.setAttribute('data-count', testCount);

        // Update section header
        newSection.querySelector('.section-number').textContent = testCount;

        // Update all IDs and labels
        updateIdsAndLabels(newSection, testCount);

        // Clear all form values
        clearFormValues(newSection);

        container.appendChild(newSection);

        // Reinitialize plugins for new section only
        initializePluginsForSection(newSection, testCount);
    }

    function removeTestSection() {
        if (testCount > 1) {
            const container = document.getElementById('testSections');
            const lastSection = container.querySelector('.test-section:last-child');
            if (lastSection) {
                // Destroy Select2 instances before removing
                $(lastSection).find('select.select2-hidden-accessible').each(function() {
                    $(this).select2('destroy');
                });
                lastSection.remove();
                testCount--;
            }
        }
    }

    function updateIdsAndLabels(section, count) {
        // Update all labels
        const labels = section.querySelectorAll('label[for]');
        labels.forEach(label => {
            const oldFor = label.getAttribute('for');
            const newFor = oldFor.replace(/\d+$/, count);
            label.setAttribute('for', newFor);
        });

        // Update all input/select IDs and names
        const inputs = section.querySelectorAll('input[id], select[id]');
        inputs.forEach(input => {
            const oldId = input.getAttribute('id');
            const newId = oldId.replace(/\d+$/, count);
            input.setAttribute('id', newId);

            // Update name attribute
            if (input.hasAttribute('name')) {
                const oldName = input.getAttribute('name');
                const newName = oldName.replace(/\d+$/, count);
                input.setAttribute('name', newName);
            }

            // Clean Select2 elements
            const baseName = newId.replace(/\d+$/, '');
            if (['approvedBy', 'reviewedBy', 'testResult', 'testType', 'specimenType', 'labId'].includes(baseName)) {
                const $element = $(input);

                // Remove Select2 containers and clean element
                $element.siblings('.select2-container').remove();
                $element.removeClass('select2-hidden-accessible')
                    .removeAttr('data-select2-id tabindex aria-hidden')
                    .show();
            }
        });
    }

    function initializePluginsForSection(section, count) {
        // Initialize Select2 for specific fields
        const $section = $(section);

        ['approvedBy', 'reviewedBy', 'testResult', 'testType', 'specimenType', 'labId'].forEach(item => {
            const $element = $section.find('#' + item + count);
            if ($element.length) {
                $element.select2({
                    placeholder: "<?php echo _translate('Select '); ?> " + camelCaseToSpaced(item),
                    width: '100%',
                    tags: true
                });
            }
        });

        // Initialize datepickers for new section
        $section.find('.date').datepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            maxDate: new Date(),
            yearRange: '<?= (date('Y') - 100); ?>:<?= date('Y'); ?>'
        });

        $section.find('.date-time').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        });
    }

    function clearFormValues(section) {
        const inputs = section.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
            } else if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
            } else {
                input.value = '';
            }
        });
    }

    function camelCaseToSpaced(str) {
        return str.replace(/([a-z])([A-Z])/g, '$1 $2').toLowerCase();
    }

    function updateTestResults(rowNumber) {
        const testTypeSelect = document.getElementById(`testType${rowNumber}`);
        const testResultSelect = document.getElementById(`testResult${rowNumber}`);
        const selectedTestType = testTypeSelect.value;

        // Clear existing options
        testResultSelect.innerHTML = '<option value="">Select Test Result</option>';

        // Populate based on selected test type
        if (selectedTestType && testResultOptions[selectedTestType]) {
            testResultOptions[selectedTestType].forEach(option => {
                const optionElement = document.createElement('option');
                optionElement.value = option;
                optionElement.textContent = option;
                testResultSelect.appendChild(optionElement);
            });
        }

        // Trigger Select2 update
        $(testResultSelect).trigger('change');
    }

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

    $(document).ready(function() {
        // Initialize Select2 for existing elements
        $(".resultSelect2").select2({
            placeholder: "<?php echo _translate('Select option'); ?>",
            width: '100%',
            tags: true
        });

        $('#typeOfPatient').select2({
            placeholder: "<?php echo _translate('Select patient type'); ?>",
            width: '100%'
        });

        $('#specimenType').select2({
            placeholder: "<?php echo _translate('Select specimen type'); ?>",
            width: '100%'
        });

        $('#labId').select2({
            placeholder: "<?php echo _translate('Select testing lab'); ?>"
        });

        $('#facilityId').select2({
            placeholder: "<?php echo _translate('Select Clinic/Health Center'); ?>"
        });

        // Initialize datepickers
        $('.date').datepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            maxDate: new Date(),
            yearRange: '<?= (date('Y') - 100); ?>:<?= date('Y'); ?>'
        });

        $('.date-time').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        // Event handlers
        $('#isPatientInitiatedTreatment').on('change', function() {
            if (this.value === 'yes') {
                $('.treatmentSelected').show();
                $('.treatmentSelectedInput').addClass('isRequired');
            } else {
                $('.treatmentSelected').hide();
                $('.treatmentSelectedInput').removeClass('isRequired');
            }
        });

        $("#labId,#facilityId,#sampleCollectionDate").on('change', function() {
            if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleDispatchedDate").val() == "") {
                $('#sampleDispatchedDate').datetimepicker("setDate", new Date($('#sampleCollectionDate').datetimepicker('getDate')));
            }
        });

        $('#isResultAuthorized').change(function(e) {
            checkIsResultAuthorized();
        });

        $('#sourceOfAlertPOE').change(function(e) {
            if (this.value == 'others') {
                $('.show-alert-poe').show();
                $('#alertPoeOthers').addClass('isRequired');
            } else {
                $('.show-alert-poe').hide();
                $('#alertPoeOthers').removeClass('isRequired');
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
                        //console.log(data);
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
    });
</script>