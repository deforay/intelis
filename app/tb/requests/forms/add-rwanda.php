<?php
// imported in tb-add-request.php based on country in global config

use App\Registries\ContainerRegistry;
use App\Utilities\DateUtility;
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
$facility = $general->generateSelectOptions($healthFacilities, null, '-- Select --');
$microscope = ["No AFB" => "No AFB", "1+" => "1+", "2+" => "2+", "3+" => "3+"];

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
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _translate("TB LABORATORY TEST REQUEST FORM"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Add New Request"); ?></li>
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
                <form class="form-horizontal" method="post" name="addTbRequestForm" id="addTbRequestForm"
                    autocomplete="off" action="tb-add-request-helper.php">

                    <!-- FACILITY INFORMATION -->
                    <div class="box box-default">
                        <div class="box-body">
                            <div class="box-header with-border sectionHeader" style="display: flex;">
                                <div class="col-md-7">
                                    <h3 class="box-title"><?php echo _translate("FACILITY INFORMATION"); ?></h3>
                                </div>
                                <div class="col-md-5" style="display: flex;">
                                    <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
                                        <span style="width: 20%;"><label class="label-control"
                                                for="sampleCode"><?php echo _translate("Sample ID"); ?></label></span>
                                        <span style="width: 80%;" id="sampleCodeInText"></span>
                                        <input type="hidden" id="sampleCode" name="sampleCode" />
                                    <?php } else { ?>
                                        <span style="width: 20%;"><label class="label-control"
                                                for="sampleCode"><?php echo _translate("Sample ID"); ?><span
                                                    class="mandatory">*</span></label></span>
                                        <input style="width: 80%;" type="text" class="form-control isRequired"
                                            id="sampleCode" name="sampleCode" readonly placeholder="Sample ID"
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
                                            placeholder="<?php echo _translate("Enter affiliated district hospital"); ?>"
                                            title="<?php echo _translate("Please enter affiliated district hospital"); ?>" />
                                    </td>
                                    <td style="width: 33.33%;">
                                        <label class="label-control"
                                            for="affiliatedLabId"><?php echo _translate("Affiliated TB Testing Site"); ?></label>
                                        <select name="affiliatedLabId" id="affiliatedLabId" class="form-control select2"
                                            title="<?php echo _translate("Please select affiliated TB testing site"); ?>">
                                            <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                        </select>
                                    </td>
                                    <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
                                        <td style="width: 33.33%;">
                                            <label class="label-control"
                                                for="labId"><?php echo _translate("Testing Laboratory"); ?><span
                                                    class="mandatory">*</span></label>
                                            <select name="labId" id="labId" class="form-control select2 isRequired"
                                                title="<?php echo _translate("Please select Testing Laboratory"); ?>">
                                                <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
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
                                            <option value="no" selected><?= _translate('No'); ?></option>
                                            <option value="yes"><?= _translate('Yes'); ?></option>
                                        </select>
                                    </td>
                                    <td style="width: 33.33%;"></td>
                                    <td style="width: 33.33%;"></td>
                                </tr>
                                <tr>
                                    <td style="width: 33.33%;">
                                        <label for="trackerNo"><?= _translate('e-TB tracker No.'); ?></label>
                                        <input type="text" class="form-control" id="trackerNo" name="trackerNo"
                                            placeholder="<?php echo _translate("Enter the e-TB tracker number"); ?>"
                                            title="<?php echo _translate("Please enter the e-TB tracker number"); ?>" />
                                    </td>
                                    <td style="width: 33.33%;">
                                        <label for="dob"><?= _translate('Date of Birth'); ?></label>
                                        <input type="text" class="form-control date" id="dob" name="dob"
                                            placeholder="<?php echo _translate("Date of Birth"); ?>"
                                            title="<?php echo _translate("Please enter Date of birth"); ?>"
                                            onchange="calculateAgeInYears('dob', 'patientAge');" />
                                    </td>
                                    <td style="width: 33.33%;">
                                        <label for="patientAge"><?= _translate('Age (years)'); ?></label>
                                        <input type="number" max="150" maxlength="3" class="form-control"
                                            id="patientAge" name="patientAge"
                                            placeholder="<?php echo _translate("Age (in years)"); ?>"
                                            title="<?php echo _translate("Patient Age"); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 33.33%;">
                                        <label for="patientId"><?php echo _translate("Patient ID"); ?></label>
                                        <input type="text" class="form-control patientId" id="patientId"
                                            name="patientId" placeholder="Patient Identification"
                                            title="<?php echo _translate("Please enter Patient ID"); ?>" />
                                    </td>
                                    <td style="width: 33.33%;">
                                        <label for="patientGender"><?php echo _translate("Sex"); ?><span
                                                class="mandatory">*</span></label>
                                        <select class="form-control isRequired" name="patientGender" id="patientGender"
                                            title="<?php echo _translate("Please choose sex"); ?>">
                                            <option value=''> -- <?php echo _translate("Select"); ?> -- </option>
                                            <option value='male'> <?php echo _translate("Male"); ?> </option>
                                            <option value='female'> <?php echo _translate("Female"); ?> </option>
                                            <option value='other'> <?php echo _translate("Other"); ?> </option>
                                        </select>
                                    </td>
                                    <td style="width: 33.33%;">
                                        <label for="firstName"><?php echo _translate("First Name"); ?><span
                                                class="mandatory">*</span></label>
                                        <input type="text" class="form-control isRequired" id="firstName"
                                            name="firstName" placeholder="<?php echo _translate("First Name"); ?>"
                                            title="<?php echo _translate("Please enter First name"); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 33.33%;">
                                        <label for="lastName"><?php echo _translate("Surname"); ?></label>
                                        <input type="text" class="form-control" id="lastName" name="lastName"
                                            placeholder="<?php echo _translate("Last name"); ?>"
                                            title="<?php echo _translate("Please enter Last name"); ?>" />
                                    </td>
                                    <td style="width: 33.33%;">
                                        <label
                                            for="patientPhoneNumber"><?php echo _translate("Phone contact"); ?>:</label>
                                        <input type="text" class="form-control checkNum" id="patientPhoneNumber"
                                            name="patientPhoneNumber"
                                            placeholder="<?php echo _translate("Phone Number"); ?>"
                                            title="<?php echo _translate("Please enter phone number"); ?>" />
                                    </td>
                                    <td style="width: 33.33%;">
                                        <label for="typeOfPatient"><?php echo _translate("Case Type"); ?><span
                                                class="mandatory">*</span></label>
                                        <select class="select2 form-control isRequired" name="typeOfPatient"
                                            id="typeOfPatient"
                                            title="<?php echo _translate("Please select the case type"); ?>"
                                            onchange="showOther(this.value,'typeOfPatientOther');">
                                            <option value=''> -- <?php echo _translate("Select"); ?> -- </option>
                                            <option value='new'> New </option>
                                            <option value='loss-to-follow-up'> Loss to Follow Up </option>
                                            <option value='treatment-failure'> Treatment Failure </option>
                                            <option value='relapse'> Relapse </option>
                                            <!-- <option value='other'> <?php echo _translate("Other"); ?> </option> -->
                                        </select>
                                        <input type="text" class="form-control typeOfPatientOther"
                                            id="typeOfPatientOther" name="typeOfPatientOther"
                                            placeholder="<?php echo _translate("Enter case type if others"); ?>"
                                            title="<?php echo _translate("Please enter case type if others"); ?>"
                                            style="display: none;" />
                                    </td>
                                </tr>
                            </table>

                            <!-- TREATMENT AND RISK FACTORS INFORMATION -->
                            <div class="box-header with-border sectionHeader">
                                <h3 class="box-title">
                                    <?php echo _translate("TREATMENT AND RISK FACTORS INFORMATION"); ?>
                                </h3>
                            </div>
                            <div class="box-header with-border">
                                <h3 class="box-title" style="font-size:1em;">
                                    <?php echo _translate("Enter treatment history, TB regimen, and risk factors"); ?>
                                </h3>
                            </div>

                            <table class="table" style="width:100%">
                                <tr>
                                    <td style="width: 33.33%;">
                                        <label
                                            for="isPatientInitiatedTreatment"><?php echo _translate("Is Patient initiated on TB treatment?"); ?><span
                                                class="mandatory">*</span></label>
                                        <select name="isPatientInitiatedTreatment" id="isPatientInitiatedTreatment"
                                            class="form-control isRequired" title="Please choose treatment status">
                                            <option value=''>-- <?php echo _translate("Select"); ?> --</option>
                                            <option value='no'><?php echo _translate("No"); ?></option>
                                            <option value='yes'><?php echo _translate("Yes"); ?></option>
                                        </select>
                                    </td>
                                    <td style="width: 33.33%;">
                                        <label class="label-control"
                                            for="riskFactors"><?php echo _translate("Risk Factors"); ?></label>
                                        <select id="riskFactors" name="riskFactors" class="form-control"
                                            title="Please select the any one of risk factors"
                                            onchange="(this.value == 'Others') ? $('#riskFactorsOther').show() : $('#riskFactorsOther').hide();">
                                            <option value="">Select risk factor...</option>
                                            <option value="TB Contact">TB Contact</option>
                                            <option value="PLHIV">PLHIV</option>
                                            <option value="Healthcare provider">Healthcare provider</option>
                                            <option value="CHW">CHW</option>
                                            <option value="Prisoner inmate">Prisoner/inmate</option>
                                            <option value="Tobacco smoking">Tobacco smoking</option>
                                            <option value="Crowded habitant">Crowded habitant</option>
                                            <option value="Diabetic">Diabetic</option>
                                            <option value="Miner">Miner</option>
                                            <option value="Refugee camp">Refugee camp</option>
                                            <option value="No information provided">No information provided</option>
                                            <option value="Others">Others</option>
                                        </select>
                                        <input style="display: none;" type="text" id="riskFactorsOther"
                                            name="riskFactorsOther" class="form-control"
                                            placeholder="Enter the other risk factor"
                                            title="Please enter the other risk factor" />
                                    </td>
                                    <td style="width: 33.33%;"></td>
                                </tr>
                                <tr class="treatmentSelected" style="display: none;">
                                    <td style="width: 33.33%;">
                                        <label class="label-control"
                                            for="treatmentDate"><?php echo _translate("Date of treatment Initiation"); ?><span
                                                class="mandatory">*</span></label>
                                        <input type="text" name="treatmentDate" id="treatmentDate"
                                            placeholder="Enter the date of treatment initiation"
                                            class="treatmentSelectedInput form-control date"
                                            title="Please choose treatment date" />
                                    </td>
                                    <td style="width: 33.33%;">
                                        <label for="currentRegimen"
                                            class="label-control"><?php echo _translate("Current regimen"); ?><span
                                                class="mandatory">*</span></label>
                                        <input type="text" class="form-control treatmentSelectedInput"
                                            id="currentRegimen" name="currentRegimen"
                                            placeholder="<?php echo _translate('Enter the current regimen'); ?>"
                                            title="<?php echo _translate('Please enter current regimen'); ?>">
                                    </td>
                                    <td style="width: 33.33%;">
                                        <label class="label-control"
                                            for="regimenDate"><?php echo _translate("Date of Initiation of Current Regimen"); ?><span
                                                class="mandatory">*</span></label>
                                        <input type="text" name="regimenDate" id="regimenDate"
                                            placeholder="Enter the initiation of current regimen"
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
                                        <select id="purposeOfTbTest" multiple name="purposeOfTbTest[]"
                                            class="form-control isRequired"
                                            title="Please select the any one of purpose of test">
                                            <option value="">Select purpose of TB test...</option>
                                            <option value="Initial TB diagnosis">Initial TB diagnosis</option>
                                            <option value="DS-TB Treatment Follow-Up">DS-TB Treatment Follow-Up</option>
                                            <option value="C2">C2</option>
                                            <option value="C5">C5</option>
                                            <option value="End of TB treatment">End of TB treatment</option>
                                            <option value="DR-TB Patient Baseline tests">DR-TB Patient Baseline tests
                                            </option>
                                            <option value="DR-TB patient Follow up">DR-TB patient Follow up</option>
                                        </select>
                                    </td>
                                    <td style="width: 50%;">
                                        <label class="label-control"
                                            for="tbTestsRequested"><?php echo _translate("TB test(s) requested"); ?></label>
                                        <select id="tbTestsRequested" multiple name="tbTestsRequested[]"
                                            class="form-control" title="Please select the TB test(s) requested">
                                            <option value="">Select TB test(s) requested...</option>
                                            <option value="LED microscopy">LED microscopy</option>
                                            <option value="TB LAM test">TB LAM test</option>
                                            <option value="MTB/ RIF Ultra">MTB/ RIF Ultra</option>
                                            <option value="MTB/ XDR (if RIF detected)">MTB/ XDR (if RIF detected)
                                            </option>
                                            <option value="TB culture and Drug susceptibility test (DST)">TB culture and
                                                Drug susceptibility test (DST)</option>
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
                                    <?php echo _translate("Enter specimen collection details"); ?>
                                </h3>
                            </div>

                            <table class="table">
                                <tr>
                                    <td style="width: 33.33%;">
                                        <label class="label-control"
                                            for="sampleCollectionDate"><?php echo _translate("Date Specimen Collected"); ?><span
                                                class="mandatory">*</span></label>
                                        <input class="form-control isRequired date-time" type="text"
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
                                            <?php echo $general->generateSelectOptions($specimenTypeResult, null, '-- Select --'); ?>
                                            <option value='other'> <?php echo _translate("Other"); ?> </option>
                                        </select>
                                        <input type="text" class="form-control specimenTypeOther" id="specimenTypeOther"
                                            name="specimenTypeOther"
                                            placeholder="<?php echo _translate("Enter specimen type of others"); ?>"
                                            title="<?php echo _translate("Please enter the specimen type if others"); ?>"
                                            style="display: none;" />
                                    </td>
                                    <td style="width: 33.33%;">
                                        <label class="label-control"
                                            for="correctiveAction"><?php echo _translate("Is specimen re-ordered as part of corrective action?"); ?></label>
                                        <select class="form-control" name="correctiveAction" id="correctiveAction"
                                            title="<?php echo _translate("Is specimen re-ordered as part of corrective action"); ?>">
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
                        <!-- TEST RESULTS INFORMATION -->
                        <div class="box box-primary">
                            <div class="box-body">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><?php echo _translate("TEST RESULTS INFORMATION"); ?></h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">
                                        <?php echo _translate("Enter test results for the requested TB test(s)"); ?>
                                    </h3>
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
                                <div id="testSections">
                                    <!-- Initial test section -->
                                    <div class="test-section" data-count="1">
                                        <div class="section-header"><strong>Test #<span
                                                    class="section-number">1</span></strong></div>
                                        <table class="table" style="width:100%; margin-top: 15px;">
                                            <tr>
                                                <td style="width: 33.33%;">
                                                    <label class="label-control"
                                                        for="labId1"><?php echo _translate("Testing Lab"); ?></label>
                                                    <select name="testResult[labId][]" id="labId1" class="form-control select2"
                                                        title="<?php echo _translate("Please select testing laboratory"); ?>">
                                                        <?= $general->generateSelectOptions($testingLabs, $currentLabId, '-- Select lab --'); ?>
                                                    </select>
                                                </td>
                                                <td style="width: 33.33%;">
                                                    <label class="label-control"
                                                        for="sampleReceivedDate1"><?php echo _translate("Date specimen received at TB testing site"); ?></label>
                                                    <input type="text" class="date-time form-control"
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
                                                        placeholder="<?php echo _translate("Revised On"); ?>"
                                                        title="<?php echo _translate("Please enter revised date"); ?>" />
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="controls" style="margin-top: 20px;">
                                    <button type="button" class="btn btn-success" onclick="addTestSection()">+
                                        <?php echo _translate("Add Test"); ?></button>
                                    <button type="button" id="removeTestBtn" class="btn btn-danger" style="display: none;"
                                        onclick="removeTestSection()">-
                                        <?php echo _translate("Remove Test"); ?></button>
                                </div>
                                <br>
                                <div class="row pr-5">
                                    <div class="col-md-6">
                                        <label class="label-control"
                                            for="finalResult"><?php echo _translate("Final Interpretation"); ?></label>
                                        <div class="resultInputContainer">
                                            <input type="text" list="possibleFinalResults" class="form-control"
                                                id="finalResult" name="finalResult"
                                                placeholder="<?php echo _translate('Select or Type Final Interpretation'); ?>"
                                                title="<?php echo _translate('Please enter the final interpretation'); ?>"
                                                onchange="confirmFinalInterpretation(this);" />
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
                    <?php } ?>

                    <!-- Form Footer -->
                    <div class="box-footer">
                        <?php if ($arr['tb_sample_code'] == 'auto' || $arr['tb_sample_code'] == 'YY' || $arr['tb_sample_code'] == 'MMYY') { ?>
                            <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat"
                                value="<?php echo $sFormat; ?>" />
                            <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                            <input type="hidden" name="saveNext" id="saveNext" />
                        <?php } ?>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);"
                            onclick="validateNow();return false;"><?php echo _translate("Save"); ?></a>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);"
                            onclick="validateNow();$('#saveNext').val('next');return false;"><?php echo _translate("Save and Next"); ?></a>
                        <input type="hidden" name="formId" id="formId" value="7" />
                        <input type="hidden" name="tbSampleId" id="tbSampleId" value="" />
                        <a href="/tb/requests/tb-requests.php"
                            class="btn btn-default"><?php echo _translate("Cancel"); ?></a>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<script type="text/javascript">
    let provinceName = true;
    let facilityName = true;
    let testCount = 1;

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

    // Initialize plugins for a specific section
    function initializePluginsForSection(section, count) {
        const $section = $(section);

        // Initialize Select2 for dropdowns
        $section.find('.resultSelect2, .select2').each(function () {
            const $this = $(this);
            if (!$this.hasClass('select2-hidden-accessible')) {
                $this.select2({
                    placeholder: "Select option",
                    width: '100%'
                });
            }
        });

        // Initialize date pickers
        $('.date:not(.hasDatePicker)').each(function () {
            $(this).datepicker({
                changeMonth: true,
                changeYear: true,
                onSelect: function () {
                    $(this).change();
                },
                dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
                maxDate: "Today",
                yearRange: <?= DateUtility::getYearMinus(100); ?> + ":" + "<?= date('Y') ?>"
            }).click(function () {
                $('.ui-datepicker-calendar').show();
            });
        });

        // Initialize datetime pickers  
        $('.dateTime:not(.hasDateTimePicker), .date-time:not(.hasDateTimePicker)').each(function () {
            $(this).datetimepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
                timeFormat: "HH:mm",
                maxDate: "Today",
                onChangeMonthYear: function (year, month, widget) {
                    setTimeout(function () {
                        $('.ui-datepicker-calendar').show();
                    });
                },
                yearRange: <?= DateUtility::getYearMinus(100); ?> + ":" + "<?= date('Y') ?>"
            }).click(function () {
                $('.ui-datepicker-calendar').show();
            });
        });

        // Bind sample rejection change event
        $section.find('.sample-rejection-select').off('change').on('change', function () {
            const $row = $(this).closest('.test-section');
            if ($(this).val() === 'yes') {
                $row.find('.rejection-reason-field, .rejection-date-field').show();
                $row.find('.rejection-reason-select, .rejection-date').addClass('isRequired');
            } else {
                $row.find('.rejection-reason-field, .rejection-date-field').hide();
                $row.find('.rejection-reason-select, .rejection-date').removeClass('isRequired').val('');
            }
        });

        // Bind test type change event
        $section.find('.test-type-select').on('change', function () {
            updateTestResults(count);
        });
    }

    // Update test results based on selected test type
    function updateTestResults(rowNumber) {
        const testTypeSelect = document.getElementById(`testType${rowNumber}`);
        const testResultSelect = document.getElementById(`testResult${rowNumber}`);

        if (!testTypeSelect || !testResultSelect) return;

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
    }

    // Update Remove Test button visibility
    function updateRemoveButtonVisibility() {
        const removeBtn = document.getElementById('removeTestBtn');
        if (removeBtn) {
            removeBtn.style.display = (testCount > 1) ? '' : 'none';
        }
    }

    // Add new test section
    function addTestSection() {
        testCount++;
        const container = document.getElementById('testSections');
        const firstSection = container.querySelector('.test-section');
        const newSection = firstSection.cloneNode(true);

        // Update data-count attribute
        newSection.setAttribute('data-count', testCount);

        // Update section header
        newSection.querySelector('.section-number').textContent = testCount;

        // Update all IDs, names and labels
        updateIdsAndLabels(newSection, testCount);

        // Clear all form values
        clearFormValues(newSection);

        // Hide conditional fields
        $(newSection).find('.rejection-reason-field, .rejection-date-field').hide();

        container.appendChild(newSection);

        // Initialize plugins for new section
        initializePluginsForSection(newSection, testCount);

        // Update Remove button visibility
        updateRemoveButtonVisibility();
    }

    // Remove test section
    function removeTestSection() {
        if (testCount > 1) {
            const container = document.getElementById('testSections');
            const lastSection = container.querySelector('.test-section:last-child');
            if (lastSection) {
                // Destroy Select2 instances before removing
                $(lastSection).find('select.select2-hidden-accessible').each(function () {
                    $(this).select2('destroy');
                });
                lastSection.remove();
                testCount--;
            }
        }
        // Update Remove button visibility
        updateRemoveButtonVisibility();
    }

    // Update IDs and labels for new section
    function updateIdsAndLabels(section, count) {
        // Update all labels with 'for' attribute
        const labels = section.querySelectorAll('label[for]');
        labels.forEach(label => {
            const oldFor = label.getAttribute('for');
            if (oldFor && /\d+$/.test(oldFor)) {
                const newFor = oldFor.replace(/\d+$/, count);
                label.setAttribute('for', newFor);
            }
        });
        $(section).find('.hasDatepicker, .hasDateTimePicker').removeClass('hasDatepicker hasDateTimePicker');
        // Update all input/select IDs and names
        const formElements = section.querySelectorAll('input[id], select[id]');
        formElements.forEach(element => {
            const oldId = element.getAttribute('id');
            if (oldId && /\d+$/.test(oldId)) {
                const newId = oldId.replace(/\d+$/, count);
                element.setAttribute('id', newId);

                // Update name attribute for array fields
                if (element.hasAttribute('name')) {
                    const oldName = element.getAttribute('name');
                    if (oldName.includes('[]')) {
                        // Keep array notation as is
                        element.setAttribute('name', oldName);
                    }
                }
                $(element).find('.hasDatepicker, .hasDateTimePicker').removeClass('hasDatepicker hasDateTimePicker');

                // Clean Select2 attributes
                if (element.tagName === 'SELECT') {
                    $(element).removeClass('select2-hidden-accessible')
                        .removeAttr('data-select2-id tabindex aria-hidden')
                        .siblings('.select2-container').remove();
                }

            }
        });
    }

    // Clear form values in section
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
            function (data) {
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
                function (data) {
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
                function (data) {
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
                function (data) {
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
                function (data) {
                    var sCodeKey = JSON.parse(data);
                    $("#sampleCode").val(sCodeKey.sampleCode);
                    $("#sampleCodeInText").html(sCodeKey.sampleCodeInText);
                    $("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
                    $("#sampleCodeKey").val(sCodeKey.sampleCodeKey);
                });
        }
    }

    function validateNow() {
        flag = deforayValidator.init({
            formId: 'addTbRequestForm'
        });

        if (flag) {
            $('.btn-disabled').attr('disabled', 'yes');
            $(".btn-disabled").prop("onclick", null).off("click");

            <?php if ($arr['tb_sample_code'] == 'auto' || $arr['tb_sample_code'] == 'YY' || $arr['tb_sample_code'] == 'MMYY') { ?>
                insertSampleCode('addTbRequestForm', 'tbSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 3, 'sampleCollectionDate');
            <?php } else { ?>
                document.getElementById('addTbRequestForm').submit();
            <?php } ?>
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
    $(document).ready(function () {
        // Initialize Select2 for main form elements
        $("#facilityId, #province, #district").select2({
            placeholder: "<?php echo _translate('Select'); ?>",
            width: '100%'
        });

        $('#typeOfPatient').select2({
            placeholder: "<?php echo _translate('Select patient type'); ?>",
            width: '100%'
        });

        $('#specimenType').select2({
            placeholder: "<?php echo _translate('Select specimen type'); ?>",
            width: '100%'
        });

        $('#affiliatedLabId').select2({
            placeholder: "<?php echo _translate('Select affiliated TB testing site'); ?>",
            width: '100%'
        });

        <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
            $('#labId').select2({
                placeholder: "<?php echo _translate('Select testing lab'); ?>"
            });
        <?php } ?>

        // Initialize first test section
        initializePluginsForSection(document.querySelector('.test-section'), 1);

        // Treatment initiation change handler
        $('#isPatientInitiatedTreatment').on('change', function () {
            if (this.value === 'yes') {
                $('.treatmentSelected').show();
                $('.treatmentSelectedInput').addClass('isRequired');
            } else {
                $('.treatmentSelected').hide();
                $('.treatmentSelectedInput').removeClass('isRequired').val('');
            }
        });

        $('#purposeOfTbTest').select2({
            placeholder: "Select purpose of test"
        });

        $('#tbTestsRequested').select2({
            placeholder: "Select TB test(s) requested"
        });

        // Lab and facility change handlers
        $("#labId, #facilityId, #sampleCollectionDate").on('change', function () {
            if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleDispatchedDate").val() == "") {
                $('#sampleDispatchedDate').datetimepicker("setDate", new Date($('#sampleCollectionDate').datetimepicker('getDate')));
            }
        });

        <?php if (isset($arr['tb_positive_confirmatory_tests_required_by_central_lab']) && $arr['tb_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            $(document).on('change', '.test-result, #result', function (e) {
                checkPostive();
            });
        <?php } ?>

        $("#labId").change(function (e) {
            if ($(this).val() != "") {
                $.post("/tb/requests/get-attributes-data.php", {
                    id: this.value,
                },
                    function (data) {
                        if (data != "" && data != false) {
                            _data = jQuery.parseJSON(data);
                            $(".platform").hide();
                            $.each(_data, function (index, value) {
                                $("." + value).show();
                            });
                        }
                    });
            }
        });
    });

    function confirmFinalInterpretation(input) {
        if (input.value !== '' && input.value !== input.dataset.previousValue) {
            if (!confirm('<?php echo _translate("Tests with Final Interpretation cannot be referred to other labs. Are you sure you want to continue?"); ?>')) {
                input.value = input.dataset.previousValue || '';
                return false;
            }
        }
        input.dataset.previousValue = input.value;
        return true;
    }

    // Store initial value on focus
    document.getElementById('finalResult')?.addEventListener('focus', function () {
        this.dataset.previousValue = this.value;
    });
</script>
<script type="text/javascript"
    src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/datalist-css.min.js") ?>"></script>