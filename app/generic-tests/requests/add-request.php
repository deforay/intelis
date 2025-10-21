<?php

use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = _sanitizeInput($request->getQueryParams());

$title = " Add New Test";

require_once APPLICATION_PATH . '/header.php';

$labFieldDisabled = '';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$arr = $general->getGlobalConfig();

$healthFacilities = $facilitiesService->getHealthFacilities('generic-tests');
$testingLabs = $facilitiesService->getTestingLabs('generic-tests');

// get instruments
$condition = "status = 'active'";
$importResult = $general->fetchDataFromTable('instruments', $condition);
$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$reasonForFailure = $genericTestsService->getReasonForFailure();

/* To get testing platform names */
$testPlatformResult = $general->getTestingPlatforms('generic-tests');
foreach ($testPlatformResult as $row) {
     $testPlatformList[$row['machine_name']] = $row['machine_name'];
}

$userInfo = [];
foreach ($userResult as $user) {
     $userInfo[$user['user_id']] = ($user['user_name']);
}

//sample rejection reason
$condition = "rejection_reason_status ='active'";
$rejectionResult = $general->fetchDataFromTable('r_generic_sample_rejection_reasons', $condition);

//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_generic_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//get active sample types
$condition1 = "sample_type_status = 'active'";
$sResult = $general->fetchDataFromTable('r_generic_sample_types', $condition1);
//echo '<pre>'; print_r($sResult); die;
//get vltest reason details
$testReason = $general->fetchDataFromTable('r_generic_test_reasons');
$pdResult = $general->fetchDataFromTable('geographical_divisions');

$testResultUnits = $general->getDataByTableAndFields("r_generic_test_result_units", array("unit_id", "unit_name"), true, "unit_status='active'");

$lResult = $facilitiesService->getTestingLabs('generic-tests', true, true);

if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'alphanumeric' || $arr['sample_code'] == 'MMYY' || $arr['sample_code'] == 'YY') {
     $sampleClass = '';
     $maxLength = '';
     if ($arr['max_length'] != '' && $arr['sample_code'] == 'alphanumeric') {
          $maxLength = $arr['max_length'];
          $maxLength = "maxlength=" . $maxLength;
     }
} else {
     $sampleClass = '';
     $maxLength = '';
     if ($arr['max_length'] != '') {
          $maxLength = $arr['max_length'];
          $maxLength = "maxlength=" . $maxLength;
     }
}
// check if STS
$rKey = '';

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


$sKey = '';
$sFormat = '';

$testTypeQuery = "SELECT * FROM r_test_types where test_status='active' ORDER BY test_standard_name ASC";
$testTypeResult = $db->rawQuery($testTypeQuery);
$mandatoryClass = "";
if (!empty($_SESSION['instance']['type']) && $general->isLISInstance()) {
     $mandatoryClass = "isRequired";
}

$minPatientIdLength = 0;
if (isset($arr['generic_min_patient_id_length']) && $arr['generic_min_patient_id_length'] != "") {
     $minPatientIdLength = $arr['generic_min_patient_id_length'];
}
?>
<link rel="stylesheet" href="/assets/css/jquery.multiselect.css" type="text/css" />

<style>
     .ms-choice {
          border: 0px solid #aaa;
     }

     .table>tbody>tr>td {
          border-top: none;
     }

     .form-control {
          width: 100% !important;
     }

     .row {
          margin-top: 6px;
     }

     .ui_tpicker_second_label {
          display: none !important;
     }

     .ui_tpicker_second_slider {
          display: none !important;
     }

     .ui_tpicker_millisec_label {
          display: none !important;
     }

     .ui_tpicker_millisec_slider {
          display: none !important;
     }

     .ui_tpicker_microsec_label {
          display: none !important;
     }

     .ui_tpicker_microsec_slider {
          display: none !important;
     }

     .ui_tpicker_timezone_label {
          display: none !important;
     }

     .ui_tpicker_timezone {
          display: none !important;
     }

     .ui_tpicker_time_input {
          width: 100%;
     }

     .facilitySectionInput,
     .patientSectionInput,
     #otherSection .col-md-6 {
          margin: 3px 0px;
     }

     .facilitySectionInput,
     .patientSectionInput .select2,
     #otherSection .col-md-6 .select2 {
          margin: 3px 0px;
     }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
     <!-- Content Header (Page header) -->
     <section class="content-header">
          <h1><em class="fa-solid fa-pen-to-square"></em> <?= _translate("LABORATORY REQUEST FORM"); ?> </h1>
          <ol class="breadcrumb">
               <li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
               <li class="active"><?= _translate("Add Request"); ?></li>
          </ol>
     </section>
     <!-- Main content -->
     <section class="content">
          <div class="box box-default">
               <div class="box-header with-border">
                    <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate("indicates required fields"); ?> &nbsp;</div>
               </div>
               <div class="box-body">
                    <!-- form start -->
                    <form class="form-inline" method="post" name="vlRequestFormSs" id="vlRequestFormSs" autocomplete="off" action="add-request-helper.php">
                         <div class="box-body">
                              <div class="box box-primary">
                                   <div class="box-header with-border">
                                        <h3 class="box-title"><?= _translate("Clinic Information: (To be filled by requesting Clinican/Nurse)"); ?></h3>
                                   </div>
                                   <div class="row">
                                        <div class="col-md-6">
                                             <label class="col-lg-5" for="testType"><?= _translate("Test Type"); ?> <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <select class="form-control isRequired" name="testType" id="testType" title="Please choose test type" onchange="getTestTypeForm();getSubTestList(this.value);loadSubTests();">
                                                       <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                       <?php foreach ($testTypeResult as $testType) { ?>
                                                            <option value="<?php echo $testType['test_type_id'] ?>" data-short="<?php echo $testType['test_short_code']; ?>"><?php echo $testType['test_standard_name'] . ' (' . $testType['test_loinc_code'] . ')' ?></option>
                                                       <?php } ?>
                                                  </select>
                                             </div>
                                        </div>
                                   </div>
                                   <div class="row requestForm" style="display:none;">
                                        <div class="col-md-6">
                                             <label class="col-lg-5" for="sampleCode"><?= _translate("Sample ID"); ?> <span class="mandatory">*</span></label>
                                             <div class="col-lg-7">
                                                  <input type="text" class="form-control isRequired <?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" <?php echo $maxLength; ?> placeholder="<?php echo _translate('Enter Sample ID'); ?>" title="<?php echo _translate('Please enter sample id'); ?>" style="width:100%;" readonly onblur="checkSampleNameValidation('form_generic','<?php echo $sampleCode; ?>',this.id,null,'This sample number already exists.Try another number',null)" />
                                             </div>
                                        </div>
                                        <div class="col-md-6">
                                             <label class="col-lg-5" for="sampleReordered"> <?= _translate("Sample Reordered"); ?></label>
                                             <div class="col-lg-7">
                                                  <input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" <?php echo (trim((string) $genericResultInfo['sample_reordered']) == 'yes') ? 'checked="checked"' : '' ?> title="<?php echo _translate('Please indicate if this is a reordered sample'); ?>">

                                             </div>
                                        </div>
                                   </div>
                                   <div class="requestForm" style="display:none;">
                                        <div class="row">
                                             <!-- BARCODESTUFF START -->
                                             <?php if (isset($global['bar_code_printing']) && $global['bar_code_printing'] != "off") { ?>
                                                  <div class="col-md-6 pull-right">
                                                       <div class="form-group">
                                                            <label for="sampleCode"><?= _translate("Print Barcode Label"); ?><span class="mandatory">*</span> </label>
                                                            <input type="checkbox" class="" id="printBarCode" name="printBarCode" checked />
                                                       </div>
                                                  </div>
                                             <?php } ?>
                                             <!-- BARCODESTUFF END -->
                                        </div>
                                        <div class="row">
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="province"><?= _translate("State/Province"); ?> <span class="mandatory">*</span></label>
                                                  <div class="col-lg-7">
                                                       <select class="form-control isRequired" name="province" id="province" title="Please choose state" style="width:100%;" onchange="getProvinceDistricts(this);">
                                                            <?php echo $province; ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="district"><?= _translate("District/County"); ?> <span class="mandatory">*</span></label>
                                                  <div class="col-lg-7">
                                                       <select class="form-control isRequired" name="district" id="district" title="Please choose county" style="width:100%;" onchange="getFacilities(this);">
                                                            <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                       </select>
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row">
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="facilityId"><?= _translate("Clinic/Health Center"); ?> <span class="mandatory">*</span></label>
                                                  <div class="col-lg-7">
                                                       <select class="form-control isRequired select2" id="facilityId" name="facilityId" title="Please select clinic/health center name" style="width:100%;" onchange="getfacilityProvinceDetails(this);fillFacilityDetails();setSampleDispatchDate();">
                                                            <?php echo $facility; ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-md-6" style="display:none;">
                                                  <label class="col-lg-5" for="facilityCode"><?= _translate("Clinic/Health Center Code"); ?></label>
                                                  <div class="col-lg-7">
                                                       <input type="text" class="form-control" style="width:100%;" name="facilityCode" id="facilityCode" placeholder="<?php echo _translate('Clinic/Health Center Code'); ?>" title="<?php echo _translate('Please enter clinic/health center code'); ?>">
                                                  </div>
                                             </div>
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="implementingPartner"><?= _translate("Implementing Partner"); ?></label>
                                                  <div class="col-lg-7">
                                                       <select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose implementing partner" style="width:100%;">
                                                            <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                            <?php
                                                            foreach ($implementingPartnerList as $implementingPartner) {
                                                            ?>
                                                                 <option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
                                                            <?php } ?>
                                                       </select>
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row facilityDetails" style="display:none;">
                                             <div class="col-xs-2 col-md-2 femails" style="display:none;"><strong><?= _translate("Clinic Email(s)"); ?> -</strong></div>
                                             <div class="col-xs-2 col-md-2 femails facilityEmails" style="display:none;"></div>
                                             <div class="col-xs-2 col-md-2 fmobileNumbers" style="display:none;"><strong>Clinic Mobile No.(s) -</strong></div>
                                             <div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers" style="display:none;"></div>
                                             <div class="col-xs-2 col-md-2 fContactPerson" style="display:none;"><strong>Clinic Contact Person -</strong></div>
                                             <div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson" style="display:none;"></div>
                                        </div>
                                        <div class="row">
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="fundingSource"><?= _translate("Funding Source"); ?></label>
                                                  <div class="col-lg-7">
                                                       <select class="form-control" name="fundingSource" id="fundingSource" title="<?= _translate('Please choose implementing partner'); ?>" style="width:100%;">
                                                            <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                            <?php
                                                            foreach ($fundingSourceList as $fundingSource) {
                                                            ?>
                                                                 <option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>"><?= $fundingSource['funding_source_name']; ?></option>
                                                            <?php } ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="labId"><?= _translate("Testing Lab"); ?> <span class="mandatory">*</span></label>
                                                  <div class="col-lg-7">
                                                       <select name="labId" id="labId" class="select2 form-control isRequired" title="Please choose lab" onchange="autoFillFocalDetails();setSampleDispatchDate();" style="width:100%;">
                                                            <option value=""><?= _translate("-- Select --"); ?></option>
                                                            <?php foreach ($lResult as $labName) { ?>
                                                                 <option data-focalperson="<?php echo $labName['contact_person']; ?>" data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>" value="<?php echo $labName['facility_id']; ?>"><?= $labName['facility_name']; ?></option>
                                                            <?php } ?>
                                                       </select>
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row" id="facilitySection"></div>
                                   </div>
                              </div>
                              <div class="box box-primary requestForm" style="display:none;">
                                   <div class="box-header with-border">
                                        <h3 class="box-title"><?= _translate("Patient Information"); ?></h3>&nbsp;&nbsp;&nbsp;
                                        <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="" placeholder="<?php echo _translate('Enter EPID Number'); ?>" title="<?php echo _translate('Please enter the Enter EPID Number'); ?>" />&nbsp;&nbsp;
                                        <a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No Patient Found</strong></span>
                                   </div>
                                   <div class="box-body">
                                        <div class="row">
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="artNo"><?= _translate("EPID Number"); ?> <?php if ($general->isLISInstance()) { ?><span class="mandatory">*</span><?php } ?></label>
                                                  <div class="col-lg-7">
                                                       <input type="text" name="artNo" id="artNo" class="form-control <?= $mandatoryClass; ?> patientId" placeholder="<?php echo _translate('Enter EPID Number'); ?>" title="<?php echo _translate('Enter EPID Number'); ?>" onchange="checkPatientDetails('form_generic','patient_id',this,null)" />
                                                  </div>
                                             </div>
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="laboratoryNumber"><?= _translate("Laboratory Number"); ?> <?php if ($general->isLISInstance()) { ?><span class="mandatory">*</span><?php } ?></label>
                                                  <div class="col-lg-7">
                                                       <input type="text" name="laboratoryNumber" id="laboratoryNumber" class="form-control <?= $mandatoryClass; ?>" placeholder="<?php echo _translate('Enter Laboratory Number'); ?>" title="<?php echo _translate('Enter Laboratory Number'); ?>" />
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row">
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="dob"><?= _translate("Date of Birth"); ?> </label>
                                                  <div class="col-lg-7">
                                                       <input type="text" name="dob" id="dob" class="form-control date" placeholder="<?php echo _translate('Enter DOB'); ?>" title="<?php echo _translate('Enter dob'); ?>" onchange="getAge();" />
                                                  </div>
                                             </div>
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="ageInYears"><?= _translate("If DOB unknown, Age in Years"); ?> </label>
                                                  <div class="col-lg-7">
                                                       <input type="text" name="ageInYears" id="ageInYears" class="form-control forceNumeric" maxlength="3" placeholder="<?php echo _translate('Age in Years'); ?>" title="<?php echo _translate('Enter age in years'); ?>" />
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row">
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="ageInMonths"><?= _translate("If Age < 1, Age in Months"); ?> </label>
                                                  <div class="col-lg-7">
                                                       <input type="text" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="<?php echo _translate('Age in Month'); ?>" title="<?php echo _translate('Enter age in months'); ?>" />
                                                  </div>
                                             </div>
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="patientFirstName"><?= _translate("Patient Name (First Name, Last Name)"); ?> <span class="mandatory">*</span></label>
                                                  <div class="col-lg-7">
                                                       <input type="text" name="patientFirstName" id="patientFirstName" class="form-control isRequired" placeholder="<?php echo _translate('Enter Patient Name'); ?>" title="<?php echo _translate('Enter patient name'); ?>" />
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row ">
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="gender"><?= _translate("Sex"); ?></label>
                                                  <div class="col-lg-5">
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="genderMale" name="gender" value="male" title="<?php echo _translate('Please choose sex'); ?>"><?= _translate("Male"); ?>
                                                       </label>
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="genderFemale" name="gender" value="female" title="<?php echo _translate('Please choose sex'); ?>"><?= _translate("Female"); ?>
                                                       </label>
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="genderUnreported" name="gender" value="unreported" title="<?php echo _translate('Please choose sex'); ?>"><?= _translate("Unreported"); ?>
                                                       </label>
                                                  </div>
                                             </div>
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="receiveSms"><?= _translate("Patient consent to receive SMS?"); ?></label>
                                                  <div class="col-lg-7">
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="receivesmsYes" name="receiveSms" value="yes" title="<?php echo _translate('Patient consent to receive SMS'); ?>" onclick="checkPatientReceivesms(this.value);"> <?= _translate("Yes"); ?>
                                                       </label>
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="receivesmsNo" name="receiveSms" value="no" title="<?php echo _translate('Patient consent to receive SMS'); ?>" onclick="checkPatientReceivesms(this.value);"> <?= _translate("No"); ?>
                                                       </label>
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row">
                                             <div class="col-md-6">
                                                  <label class="col-lg-5" for="patientPhoneNumber"><?= _translate("Phone Number"); ?></label>
                                                  <div class="col-lg-7">
                                                       <input type="text" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control phone-number" maxlength="15" placeholder="<?php echo _translate('Enter Phone Number'); ?>" title="<?php echo _translate('Enter phone number'); ?>" />
                                                  </div>
                                             </div>
                                             <div class="col-md-6 femaleSection">
                                                  <label class="col-lg-5" for="patientPregnant"><?= _translate("Is Patient Pregnant?"); ?> </label>
                                                  <div class="col-lg-7">
                                                       <label class="radio-inline">
                                                            <input type="radio" class="" id="pregYes" name="patientPregnant" value="yes" title="<?php echo _translate('Is Patient Pregnant?'); ?>"> <?= _translate("Yes"); ?>
                                                       </label>
                                                       <label class="radio-inline">
                                                            <input type="radio" class="" id="pregNo" name="patientPregnant" value="no"> <?= _translate("No"); ?>
                                                       </label>
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row ">
                                             <div class="col-md-6 femaleSection">
                                                  <label class="col-lg-5" for="breastfeeding"><?= _translate("Is Patient Breastfeeding?"); ?> </label>
                                                  <div class="col-lg-7">
                                                       <label class="radio-inline">
                                                            <input type="radio" class="" id="breastfeedingYes" name="breastfeeding" value="yes" title="<?php echo _translate('Is Patient Breastfeeding?'); ?>"> <?= _translate("Yes"); ?>
                                                       </label>
                                                       <label class="radio-inline">
                                                            <input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no"> <?= _translate("No"); ?>
                                                       </label>
                                                  </div>
                                             </div>
                                             <div class="col-md-6" style="display:none;" id="patientSection">
                                                  <label class="col-lg-5" for="treatPeriod"><?= _translate("How long has this patient been on treatment ?"); ?> </label>
                                                  <div class="col-lg-7">
                                                       <input type="text" class="form-control" id="treatPeriod" name="treatPeriod" placeholder="<?php echo _translate('Enter Treatment Period'); ?>" title="<?php echo _translate('Please enter how long has this patient been on treatment'); ?>" />
                                                  </div>
                                             </div>
                                        </div>
                                   </div>
                                   <div class="box box-primary">
                                        <div class="box-header with-border">
                                             <h3 class="box-title"><?= _translate("Sample Information"); ?></h3>
                                        </div>
                                        <div class="box-body">
                                             <div class="row">
                                                  <div class="col-md-6">
                                                       <label class="col-lg-5" for="sampleCollectionDate"><?= _translate("Date of Sample Collection"); ?> <span class="mandatory">*</span></label>
                                                       <div class="col-lg-7">
                                                            <input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="<?php echo _translate('Sample Collection Date'); ?>" title="<?php echo _translate('Please select sample collection date'); ?>" onchange="checkSampleTestingDate();generateSampleCode();setSampleDispatchDate();checkCollectionDate(this.value);">
                                                            <span class="expiredCollectionDate" style="color:red; display:none;"></span>
                                                       </div>
                                                  </div>
                                                  <div class="col-md-6">
                                                       <label class="col-lg-5" for="sampleDispatchedDate"><?= _translate("Sample Dispatched On"); ?> <span class="mandatory">*</span></label>
                                                       <div class="col-lg-7">
                                                            <input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="<?php echo _translate('Sample Dispatched On'); ?>" title="<?php echo _translate('Please select sample dispatched on'); ?>">
                                                       </div>
                                                  </div>
                                             </div>
                                             <div class="row">
                                                  <div class="col-md-6" id="specimenSection">
                                                       <label class="col-lg-5" for="specimenType"><?= _translate("Sample Type"); ?> <span class="mandatory">*</span></label>
                                                       <div class="col-lg-7">
                                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="<?php echo _translate('Please choose sample type'); ?>">
                                                                 <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                                 <?php foreach ($sResult as $name) { ?>
                                                                      <option value="<?php echo $name['sample_type_id']; ?>"><?= $name['sample_type_name']; ?></option>
                                                                 <?php } ?>
                                                            </select>
                                                       </div>
                                                  </div>
                                                  <div class="col-md-6">
                                                       <label class="col-lg-5 control-label labels" for="reasonForTesting"><?= _translate("Reason For Testing"); ?> <span class="mandatory result-span">*</span></label>
                                                       <div class="col-lg-7">
                                                            <select name="reasonForTesting" id="reasonForTesting" class="form-control result-optional" title="<?php echo _translate('Please choose reason for testing'); ?>">
                                                                 <option value=""><?= _translate("-- Select --"); ?></option>
                                                                 <?php foreach ($testReason as $treason) { ?>
                                                                      <option value="<?php echo $treason['test_reason_id']; ?>"><?php echo (string) $treason['test_reason']; ?></option>
                                                                 <?php } ?>
                                                            </select>
                                                       </div>
                                                  </div>
                                             </div>

                                             <!-- <div id="specimenSection"></div> -->
                                        </div>
                                   </div>
                                   <div id="otherSection"></div>
                                   <?php if (_isAllowed('/generic-tests/results/generic-test-results.php') && $_SESSION['accessType'] != 'collection-site') { ?>
                                        <div class="box box-primary">
                                             <div class="box-header with-border">
                                                  <h3 class="box-title"><?= _translate("Laboratory Information"); ?></h3>
                                             </div>
                                             <div class="box-body">
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label for="vlFocalPerson" class="col-lg-5 control-label labels"> <?= _translate("Focal Person"); ?> </label>
                                                            <div class="col-lg-7">
                                                                 <select class="form-control ajax-select2" id="vlFocalPerson" name="vlFocalPerson" placeholder="<?php echo _translate('Focal Person'); ?>" title="<?php echo _translate('Please enter focal person name'); ?>"></select>
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6">
                                                            <label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label labels"> <?= _translate("Focal Person Phone Number"); ?></label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" class="form-control phone-number" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" placeholder="<?php echo _translate('Phone Number'); ?>" title="<?php echo _translate('Please enter focal person phone number'); ?>" />
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="sampleReceivedAtHubOn"><?= _translate("Date Sample Received at Hub"); ?> </label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" class="form-control dateTime" id="sampleReceivedAtHubOn" name="sampleReceivedAtHubOn" placeholder="<?php echo _translate('Sample Received at HUB Date'); ?>" title="<?php echo _translate('Please select sample received at Hub date'); ?>" />
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="sampleReceivedDate"><?= _translate("Date Sample Received at Testing Lab"); ?> </label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?php echo _translate('Sample Received at LAB Date'); ?>" title="<?php echo _translate('Please select sample received at Lab date'); ?>" />
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label for="testPlatform" class="col-lg-5 control-label labels"> <?= _translate("Testing Platform"); ?> <span class="mandatory result-span">*</span></label>
                                                            <div class="col-lg-7">
                                                                 <select name="testPlatform" id="testPlatform" class="form-control result-optional" title="<?php echo _translate('Please choose Testing Platform'); ?>">
                                                                      <option value="">-- Select --</option>
                                                                      <?php foreach ($importResult as $mName) { ?>
                                                                           <option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['instrument_id']; ?>"><?php echo $mName['machine_name']; ?></option>
                                                                      <?php } ?>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="isSampleRejected"><?= _translate("Is Sample Rejected?"); ?> <span class="mandatory result-span">*</span></label>
                                                            <div class="col-lg-7">
                                                                 <select name="isSampleRejected" id="isSampleRejected" class="form-control" title="<?php echo _translate('Please check if sample is rejected or not'); ?>">
                                                                      <option value=""><?= _translate("-- Select --"); ?></option>
                                                                      <option value="yes"><?= _translate("Yes"); ?></option>
                                                                      <option value="no"><?= _translate("No"); ?></option>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row rejectionReason" style="display:none;">
                                                       <div class="col-md-6 rejectionReason" style="display:none;">
                                                            <label class="col-lg-5 control-label labels" for="rejectionReason"><?= _translate("Rejection Reason"); ?> </label>
                                                            <div class="col-lg-7">
                                                                 <select name="rejectionReason" id="rejectionReason" class="form-control" title="<?php echo _translate('Please choose reason'); ?>" onchange="checkRejectionReason();">
                                                                      <option value=""><?= _translate("-- Select --"); ?></option>
                                                                      <?php foreach ($rejectionTypeResult as $type) { ?>
                                                                           <optgroup label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
                                                                                <?php foreach ($rejectionResult as $reject) {
                                                                                     if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                                                          <option value="<?php echo $reject['rejection_reason_id']; ?>"><?= $reject['rejection_reason_name']; ?></option>
                                                                                <?php }
                                                                                } ?>
                                                                           </optgroup>
                                                                      <?php }
                                                                      if ($general->isLISInstance() === false) { ?>
                                                                           <option value="other"><?= _translate("Other (Please Specify)"); ?> </option>
                                                                      <?php } ?>
                                                                 </select>
                                                                 <input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="<?php echo _translate('Rejection Reason'); ?>" title="<?php echo _translate('Please enter rejection reason'); ?>" style="width:100%;display:none;margin-top:2px;">
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6 rejectionReason" style="display:none;">
                                                            <label class="col-lg-5 control-label labels" for="rejectionDate"><?= _translate("Rejection Date"); ?> </label>
                                                            <div class="col-lg-7">
                                                                 <input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="<?php echo _translate('Select Rejection Date'); ?>" title="<?php echo _translate('Please select rejection date'); ?>" />
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="sampleTestingDateAtLab"><?= _translate("Sample Testing Date"); ?> <span class="mandatory result-span">*</span></label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" class="form-control result-fields dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="<?php echo _translate('Sample Testing Date'); ?>" title="<?php echo _translate('Please select sample testing date'); ?>" onchange="checkSampleTestingDate();" disabled />
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6 vlResult">
                                                            <label class="col-lg-5 control-label labels" for="resultDispatchedOn"><?= _translate("Date Results Dispatched"); ?></label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" class="form-control dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="<?php echo _translate('Result Dispatch Date'); ?>" title="<?php echo _translate('Please select result dispatched date'); ?>" />
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6 vlResult subTestFields">
                                                            <label class="col-lg-5 control-label labels" for="subTestResult"><?= _translate("Tests Performed"); ?></label>
                                                            <div class="col-lg-7">
                                                                 <select class="form-control ms-container multiselect" id="subTestResult" name="subTestResult[]" title="<?php echo _translate('Please select sub tests'); ?>" multiple onchange="loadSubTests();">
                                                                 </select>
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <?php if (count($reasonForFailure) > 0) { ?>
                                                       <div class="row">
                                                            <div class="col-md-6" style="display: none;">
                                                                 <label class="col-lg-5 control-label" for="reasonForFailure"><?= _translate("Reason for Failure"); ?> <span class="mandatory">*</span> </label>
                                                                 <div class="col-lg-7">
                                                                      <select name="reasonForFailure" id="reasonForFailure" class="form-control" title="<?php echo _translate('Please choose reason for failure'); ?>" style="width: 100%;">
                                                                           <?= $general->generateSelectOptions($reasonForFailure, null, '-- Select --'); ?>
                                                                      </select>
                                                                 </div>
                                                            </div>
                                                       </div>
                                                  <?php } ?>
                                                  <div class="subTestResultSection">

                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label" for="reviewedBy"><?= _translate("Reviewed By"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                            <div class="col-lg-7">
                                                                 <select name="reviewedBy" id="reviewedBy" class="select2 form-control labels" title="<?php echo _translate('Please choose reviewed by'); ?>" style="width: 100%;">
                                                                      <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="reviewedOn"><?= _translate("Reviewed On"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="<?php echo _translate('Reviewed on'); ?>" title="<?php echo _translate('Please enter the Reviewed on'); ?>" />
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="testedBy"><?= _translate("Tested By"); ?> </label>
                                                            <div class="col-lg-7">
                                                                 <select name="testedBy" id="testedBy" class="select2 form-control" title="<?php echo _translate('Please choose approved by'); ?>">
                                                                      <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="approvedBy"><?= _translate("Approved By"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                            <div class="col-lg-7">
                                                                 <select name="approvedBy" id="approvedBy" class="select2 form-control" title="<?php echo _translate('Please choose approved by'); ?>">
                                                                      <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="approvedOn"><?= _translate("Approved On"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" value="" class="form-control dateTime" id="approvedOn" title="<?php echo _translate('Please choose Approved On'); ?>" name="approvedOn" placeholder="<?= _translate("Please enter date"); ?>" style="width:100%;" />
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="labComments"><?= _translate("Lab Tech. Comments"); ?> </label>
                                                            <div class="col-lg-7">
                                                                 <textarea class="form-control" name="labComments" id="labComments" placeholder="<?php echo _translate('Lab comments'); ?>" title="<?php echo _translate('Please enter LabComments'); ?>"></textarea>
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row" id="labSection">
                                                  </div>
                                             </div>
                                        <?php } ?>
                                        </div>
                                        <div class="box-footer">
                                             <!-- BARCODESTUFF START -->
                                             <?php if (isset($global['bar_code_printing']) && $global['bar_code_printing'] == 'zebra-printer') { ?>
                                                  <div id="printer_data_loading" style="display:none"><span id="loading_message"><?= _translate("Loading Printer Details..."); ?></span><br />
                                                       <div class="progress" style="width:100%">
                                                            <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
                                                            </div>
                                                       </div>
                                                  </div> <!-- /printer_data_loading -->
                                                  <div id="printer_details" style="display:none">
                                                       <span id="selected_printer"><?= _translate("No printer selected!"); ?></span>
                                                       <button type="button" class="btn btn-success" onclick="changePrinter()"><?= _translate("Change/Retry"); ?></button>
                                                  </div><br /> <!-- /printer_details -->
                                                  <div id="printer_select" style="display:none">
                                                       <?= _translate("Zebra Printer Options"); ?><br />
                                                       <?= _translate("Printer"); ?>: <select id="printers"></select>
                                                  </div> <!-- /printer_select -->
                                             <?php } ?>
                                             <!-- BARCODESTUFF END -->
                                             <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;"><?= _translate("Save"); ?></a>
                                             <input type="hidden" name="saveNext" id="saveNext" />
                                             <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
                                             <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
                                                  <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                                                  <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                                             <?php } ?>
                                             <input type="hidden" name="vlSampleId" id="vlSampleId" value="" />
                                             <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateSaveNow('next');return false;"><?= _translate("Save and Next"); ?></a>
                                             <?php if (_isAllowed("/batch/add-batch.php?type=" . $_GET['type'])) { ?>
                                                  <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateSaveNow('clone');return false;"><?= _translate("Save and Clone"); ?></a>
                                             <?php } ?>
                                             <a href="view-requests.php" class="btn btn-default"> <?= _translate("Cancel"); ?></a>
                                        </div>
                              </div>
                         </div>
               </div>
          </div>
          <input type="hidden" id="selectedSample" value="" name="selectedSample" class="" />
          <input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />

     </section>
</div>
<!-- BARCODESTUFF START -->
<?php
if (isset($global['bar_code_printing']) && $global['bar_code_printing'] != "off") {
     if ($global['bar_code_printing'] == 'dymo-labelwriter-450') {
?>
          <script src="/assets/js/DYMO.Label.Framework.js"></script>
          <script src="/uploads/barcode-formats/dymo-format.js"></script>
          <script src="/assets/js/dymo-print.js"></script>
     <?php
     } else if ($global['bar_code_printing'] == 'zebra-printer') {
     ?>
          <script src="/assets/js/zebra-browserprint.js?v=<?= filemtime(WEB_ROOT . "/assets/js/zebra-browserprint.js") ?>"></script>
          <script src="/uploads/barcode-formats/zebra-format.js?v=<?= filemtime(WEB_ROOT . "/uploads/barcode-formats/zebra-format.js") ?>"></script>
          <script src="/assets/js/zebra-print.js?v=<?= filemtime(WEB_ROOT . "/assets/js/zebra-print.js") ?>"></script>
<?php
     }
}
?>
<!-- BARCODESTUFF END -->
<script type="text/javascript" src="/assets/js/jquery.multiselect.js"></script>

<script type="text/javascript" src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/datalist-css.min.js") ?>"></script>
<script type="text/javascript" src="/assets/js/moment.min.js"></script>
<script>
     let provinceName = true;
     let facilityName = true;
     let testCounter = 1;

     $(document).ready(function() {
          $("#subTestResult").multipleSelect({
               placeholder: '<?php echo _translate("Select Sub Tests", true); ?>',
               width: '100%'
          });

          $("#labId,#facilityId,#sampleCollectionDate").on('change', function() {

               if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleDispatchedDate").val() == "") {
                    $('#sampleDispatchedDate').val($('#sampleCollectionDate').val());
               }
               if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleReceivedDate").val() == "") {
                    $('#sampleReceivedDate').val($('#sampleCollectionDate').val());
                    $('#sampleReceivedAtHubOn').val($('#sampleCollectionDate').val());
               }
          });



          $("#specimenType").select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Specimen Type", true); ?>"
          });
          $("#testType").select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Test Type", true); ?>"
          });
          $('#labId').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Testing Lab", true); ?>"
          });
          $('#facilityId').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Clinic/Health Center", true); ?>"
          });
          $('#reviewedBy').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Reviewed By", true); ?>"
          });
          $('#testedBy').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Tested By", true); ?>"
          });

          $('#approvedBy').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Approved By", true); ?>"
          });
          $('#facilityId').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Select Clinic/Health Center", true); ?>"
          });
          $('#district').select2({
               width: '100%',
               placeholder: "<?php echo _translate("District", true); ?>"
          });
          $('#province').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Province", true); ?>"
          });
          $('#implementingPartner').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Implementing Partner", true); ?>"
          });
          $('#fundingSource').select2({
               width: '100%',
               placeholder: "<?php echo _translate("Funding Source", true); ?>"
          });



          // BARCODESTUFF START
          <?php
          if (isset($_GET['barcode']) && $_GET['barcode'] == 'true') {
               $sampleCode = htmlspecialchars($_GET['s']);
               $facilityCode = htmlspecialchars($_GET['f']);
               $patientID = htmlspecialchars($_GET['p']);
               echo "printBarcodeLabel('$sampleCode','$facilityCode','$patientID');";
          }
          ?>
          // BARCODESTUFF END

          $("#reqClinician").select2({
               placeholder: "<?= _translate('Enter Request Clinician name'); ?>",
               minimumInputLength: 0,
               width: '100%',
               allowClear: true,
               id: function(bond) {
                    return bond._id;
               },
               ajax: {
                    placeholder: "<?= _translate('Type one or more character to search'); ?>",
                    url: "/includes/get-data-list.php",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                         return {
                              fieldName: 'request_clinician_name',
                              tableName: 'form_generic',
                              q: params.term, // search term
                              page: params.page
                         };
                    },
                    processResults: function(data, params) {
                         params.page = params.page || 1;
                         return {
                              results: data.result,
                              pagination: {
                                   more: (params.page * 30) < data.total_count
                              }
                         };
                    },
                    //cache: true
               },
               escapeMarkup: function(markup) {
                    return markup;
               }
          });

          $("#reqClinician").change(function() {
               $.blockUI();
               var search = $(this).val();
               if ($.trim(search) != '') {
                    $.get("/includes/get-data-list.php", {
                              fieldName: 'request_clinician_name',
                              tableName: 'form_generic',
                              returnField: 'request_clinician_phone_number',
                              limit: 1,
                              q: search,
                         },
                         function(data) {
                              if (data != "") {
                                   $("#reqClinicianPhoneNumber").val(data);
                              }
                         });
               }
               $.unblockUI();
          });

          $("#vlFocalPerson").select2({
               placeholder: "<?= _translate('Enter Request Focal name', true); ?>",
               minimumInputLength: 0,
               width: '100%',
               allowClear: true,
               id: function(bond) {
                    return bond._id;
               },
               ajax: {
                    placeholder: "<?= _translate('Type one or more character to search'); ?>",
                    url: "/includes/get-data-list.php",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                         return {
                              fieldName: 'testing_lab_focal_person',
                              tableName: 'form_generic',
                              q: params.term, // search term
                              page: params.page
                         };
                    },
                    processResults: function(data, params) {
                         params.page = params.page || 1;
                         return {
                              results: data.result,
                              pagination: {
                                   more: (params.page * 30) < data.total_count
                              }
                         };
                    },
                    //cache: true
               },
               escapeMarkup: function(markup) {
                    return markup;
               }
          });

          $("#vlFocalPerson").change(function() {
               $.blockUI();
               var search = $(this).val();
               if ($.trim(search) != '') {
                    $.get("/includes/get-data-list.php", {
                              fieldName: 'testing_lab_focal_person',
                              tableName: 'form_generic',
                              returnField: 'testing_lab_focal_person_phone_number',
                              limit: 1,
                              q: search,
                         },
                         function(data) {
                              if (data != "") {
                                   $("#vlFocalPersonPhoneNumber").val(data);
                              }
                         });
               }
               $.unblockUI();
          });
     });

     function getProvinceDistricts(obj) {
          $.blockUI();
          var cName = $("#facilityId").val();
          var pName = $("#province").val();
          if (pName != '' && provinceName && facilityName) {
               facilityName = false;
          }
          if (pName != '') {
               if (provinceName) {
                    $.post("/includes/siteInformationDropdownOptions.php", {
                              pName: pName,
                              testType: 'generic-tests'
                         },
                         function(data) {
                              if (data != "") {
                                   details = data.split("###");
                                   $("#district").html(details[1]);
                                   $("#facilityId").html("<option data-code='' data-emails='' data-mobile-nos='' data-contact-person='' value=''> -- Select -- </option>");
                                   $("#facilityCode").val('');
                                   $(".facilityDetails").hide();
                                   $(".facilityEmails").html('');
                                   $(".facilityMobileNumbers").html('');
                                   $(".facilityContactPerson").html('');
                              }
                         });
               }
               generateSampleCode();
          } else if (pName == '' && cName == '') {
               provinceName = true;
               facilityName = true;
               $("#province").html("<?php echo $province; ?>");
               $("#facilityId").html("<?php echo $facility; ?>");
          }
          $.unblockUI();
     }

     function generateSampleCode() {
          var testType = $("#testType").val();
          var pName = $("#province").val();
          var sDate = $("#sampleCollectionDate").val();

          var provinceCode = ($("#province").find(":selected").attr("data-code") == null || $("#province").find(":selected").attr("data-code") == '') ? $("#province").find(":selected").attr("data-name") : $("#province").find(":selected").attr("data-code");
          $("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
          if (pName != '' && sDate != '' && testType != '') {
               $.post("/generic-tests/requests/generateSampleCode.php", {
                         sampleCollectionDate: sDate,
                         provinceCode: provinceCode,
                         testType: $('#testType').find(':selected').data('short')
                    },
                    function(data) {
                         var sCodeKey = JSON.parse(data);
                         $("#sampleCode").val(sCodeKey.sampleCode);
                         $("#sampleCodeInText").html(sCodeKey.sampleCode);
                         $("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
                         $("#sampleCodeKey").val(sCodeKey.maxId);
                         checkSampleNameValidation('form_generic', '<?php echo $sampleCode; ?>', 'sampleCode', null, 'This sample number already exists.Try another number', null)
                    });
          }
     }

     function getFacilities(obj) {
          $.blockUI();
          var dName = $("#district").val();
          var cName = $("#facilityId").val();
          if (dName != '') {
               $.post("/includes/siteInformationDropdownOptions.php", {
                         dName: dName,
                         cliName: cName,
                         testType: 'generic-tests'
                    },
                    function(data) {
                         if (data != "") {
                              details = data.split("###");
                              $("#facilityId").html(details[0]);
                              // $("#labId").html(details[1]);
                              $(".facilityDetails").hide();
                              $(".facilityEmails").html('');
                              $(".facilityMobileNumbers").html('');
                              $(".facilityContactPerson").html('');
                         }
                    });
          }
          $.unblockUI();
     }

     function fillFacilityDetails() {
          $("#facilityCode").val($('#facilityId').find(':selected').data('code'));
          var femails = $('#facilityId').find(':selected').data('emails');
          var fmobilenos = $('#facilityId').find(':selected').data('mobile-nos');
          var fContactPerson = $('#facilityId').find(':selected').data('contact-person');
          if ($.trim(femails) != '' || $.trim(fmobilenos) != '' || fContactPerson != '') {
               $(".facilityDetails").show();
          } else {
               $(".facilityDetails").hide();
          }
          ($.trim(femails) != '') ? $(".femails").show(): $(".femails").hide();
          ($.trim(femails) != '') ? $(".facilityEmails").html(femails): $(".facilityEmails").html('');
          ($.trim(fmobilenos) != '') ? $(".fmobileNumbers").show(): $(".fmobileNumbers").hide();
          ($.trim(fmobilenos) != '') ? $(".facilityMobileNumbers").html(fmobilenos): $(".facilityMobileNumbers").html('');
          ($.trim(fContactPerson) != '') ? $(".fContactPerson").show(): $(".fContactPerson").hide();
          ($.trim(fContactPerson) != '') ? $(".facilityContactPerson").html(fContactPerson): $(".facilityContactPerson").html('');
     }
     $("input:radio[name=gender]").click(function() {
          if ($(this).val() == 'male' || $(this).val() == 'unreported') {
               $('.femaleSection').hide();
               $('input[name="breastfeeding"]').prop('checked', false);
               $('input[name="patientPregnant"]').prop('checked', false);
          } else if ($(this).val() == 'female') {
               $('.femaleSection').show();
          }
     });
     $("#sampleTestingDateAtLab").on("change", function() {
          if ($(this).val() != "") {
               $(".result-fields").attr("disabled", false);
               $(".result-fields").addClass("isRequired");
               $(".result-span").show();
               $('.vlResult').css('display', 'block');
               $('.rejectionReason').hide();
               $('#rejectionReason').removeClass('isRequired');
               $('#rejectionDate').removeClass('isRequired');
               $('#rejectionReason').val('');
               $(".review-approve-span").hide();
          }
     });
     $("#isSampleRejected").on("change", function() {


          if ($(this).val() == 'yes') {
               $('.rejectionReason').show();
               $('.vlResult').css('display', 'none');
               $("#sampleTestingDateAtLab, #vlResult").val("");
               $(".result-fields").val("");
               $(".result-fields").attr("disabled", true);
               $(".result-fields").removeClass("isRequired");
               $(".result-span").hide();
               $(".review-approve-span").show();
               $('#rejectionReason').addClass('isRequired');
               $('#rejectionDate').addClass('isRequired');
               $('#reviewedBy').addClass('isRequired');
               $('#reviewedOn').addClass('isRequired');
               $('#approvedBy').addClass('isRequired');
               $('#approvedOn').addClass('isRequired');
               $(".result-optional").removeClass("isRequired");
               $("#reasonForFailure").removeClass('isRequired');
          } else if ($(this).val() == 'no') {
               $(".result-fields").attr("disabled", false);
               $(".result-fields").addClass("isRequired");
               $(".result-span").show();
               $(".review-approve-span").show();
               $('.vlResult').css('display', 'block');
               $('.rejectionReason').hide();
               $('#rejectionReason').removeClass('isRequired');
               $('#rejectionDate').removeClass('isRequired');
               $('#rejectionReason').val('');
               $('#reviewedBy').addClass('isRequired');
               $('#reviewedOn').addClass('isRequired');
               $('#approvedBy').addClass('isRequired');
               $('#approvedOn').addClass('isRequired');
          } else {
               $(".result-fields").attr("disabled", false);
               $(".result-fields").removeClass("isRequired");
               $(".result-optional").removeClass("isRequired");
               $(".result-span").show();
               $('.vlResult').css('display', 'block');
               $('.rejectionReason').hide();
               $(".result-span").hide();
               $(".review-approve-span").hide();
               $('#rejectionReason').removeClass('isRequired');
               $('#rejectionDate').removeClass('isRequired');
               $('#rejectionReason').val('');
               $('#reviewedBy').removeClass('isRequired');
               $('#reviewedOn').removeClass('isRequired');
               $('#approvedBy').removeClass('isRequired');
               $('#approvedOn').removeClass('isRequired');
          }
     });


     $('#vlResult').on('change', function() {
          if ($(this).val().trim().toLowerCase() == 'failed' || $(this).val().trim().toLowerCase() == 'error') {
               if ($(this).val().trim().toLowerCase() == 'failed') {
                    $('.reasonForFailure').show();
                    $('#reasonForFailure').addClass('isRequired');
               }
          } else {
               $('.reasonForFailure').hide();
               $('#reasonForFailure').removeClass('isRequired');
          }
     });

     function checkRejectionReason() {
          var rejectionReason = $("#rejectionReason").val();
          if (rejectionReason == "other") {
               $("#newRejectionReason").show();
               $("#newRejectionReason").addClass("isRequired");
          } else {
               $("#newRejectionReason").hide();
               $("#newRejectionReason").removeClass("isRequired");
               $('#newRejectionReason').val("");
          }
     }

     $('#testingPlatform').on("change", function() {
          $(".vlResult").show();
          //$('#vlResult, #isSampleRejected').addClass('isRequired');
          $("#isSampleRejected").val("");
          //$("#isSampleRejected").trigger("change");
     });



     function setSampleDispatchDate() {
          if ($("#labId").val() != "" && $("#labId").val() == $("#facilityId").val() && $('#sampleDispatchedDate').val() == "") {
               $('#sampleDispatchedDate').val($("sampleCollectionDate").val());
          }
     }

     function validateNow() {
          var format = '<?php echo $arr['sample_code']; ?>';
          var sCodeLentgh = $("#sampleCode").val();
          var minLength = '<?php echo $arr['min_length']; ?>';
          if ((format == 'alphanumeric' || format == 'numeric') && sCodeLentgh.length < minLength && sCodeLentgh != '') {
               alert("Sample ID length must be a minimum length of " + minLength + " characters");
               return false;
          }

          flag = deforayValidator.init({
               formId: 'vlRequestFormSs'
          });
          $('.isRequired').each(function() {
               ($(this).val() == '') ? $(this).css('background-color', '#FFFF99'): $(this).css('background-color', '#FFFFFF')
          });
          if (flag) {
               $('.btn-disabled').attr('disabled', 'yes');
               $(".btn-disabled").prop("onclick", null).off("click");
               $.blockUI();
               var provinceCode = ($("#province").find(":selected").attr("data-code") == null || $("#province").find(":selected").attr("data-code") == '') ? $("#province").find(":selected").attr("data-name") : $("#province").find(":selected").attr("data-code");
               <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'auto2' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
                    insertSampleCode('vlRequestFormSs', 'vlSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', '1', 'sampleCollectionDate', provinceCode, $("#province").find(":selected").attr("data-province-id"));
               <?php } else { ?>
                    document.getElementById('vlRequestFormSs').submit();
               <?php } ?>
          }
     }

     function validateSaveNow(option = 'next') {
          var format = '<?php echo $arr['sample_code']; ?>';
          var sCodeLentgh = $("#sampleCode").val();
          var minLength = '<?php echo $arr['min_length']; ?>';
          if ((format == 'alphanumeric' || format == 'numeric') && sCodeLentgh.length < minLength && sCodeLentgh != '') {
               alert("Sample ID length must be a minimum length of " + minLength + " characters");
               return false;
          }
          flag = deforayValidator.init({
               formId: 'vlRequestFormSs'
          });
          $('.isRequired').each(function() {
               ($(this).val() == '') ? $(this).css('background-color', '#FFFF99'): $(this).css('background-color', '#FFFFFF')
          });
          $("#saveNext").val(option);
          if (flag) {
               $('.btn-disabled').attr('disabled', 'yes');
               $(".btn-disabled").prop("onclick", null).off("click");
               $.blockUI();
               var provinceCode = ($("#province").find(":selected").attr("data-code") == null || $("#province").find(":selected").attr("data-code") == '') ? $("#province").find(":selected").attr("data-name") : $("#province").find(":selected").attr("data-code");
               <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'auto2' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
                    insertSampleCode('vlRequestFormSs', 'vlSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 1, 'sampleCollectionDate', provinceCode, $("#province").find(":selected").attr("data-province-id"));
               <?php } else { ?>
                    document.getElementById('vlRequestFormSs').submit();
               <?php } ?>
          }
     }

     function checkPatientReceivesms(val) {
          if (val == 'yes') {
               $('#patientPhoneNumber').addClass('isRequired');
          } else {
               $('#patientPhoneNumber').removeClass('isRequired');
          }
     }

     function autoFillFocalDetails() {
          labId = $("#labId").val();
          if ($.trim(labId) != '') {
               $("#vlFocalPerson").val($('#labId option:selected').attr('data-focalperson'));
               $("#vlFocalPersonPhoneNumber").val($('#labId option:selected').attr('data-focalphone'));
          }
     }

     function setPatientDetails(pDetails) {
          $("#selectedSample").val(pDetails);
          var patientArray = JSON.parse(pDetails);
          //  alert(pDetails);
          $("#patientFirstName").val(patientArray['name']);
          $("#patientPhoneNumber").val(patientArray['mobile']);
          if ($.trim(patientArray['dob']) != '') {
               $("#dob").val(patientArray['dob']);
               getAge();
          } else if ($.trim(patientArray['age_in_years']) != '' && $.trim(patientArray['age_in_years']) != 0) {
               $("#ageInYears").val(patientArray['age_in_years']);
          } else if ($.trim(patientArray['age_in_months']) != '') {
               $("#ageInMonths").val(patientArray['age_in_months']);
          }

          if ($.trim(patientArray['gender']) != '') {
               $('#breastfeedingYes').removeClass('isRequired');
               $('#pregYes').removeClass('isRequired');
               if (patientArray['gender'] == 'male' || patientArray['gender'] == 'unreported') {
                    $('.femaleSection').hide();
                    $('input[name="breastfeeding"]').prop('checked', false);
                    $('input[name="patientPregnant"]').prop('checked', false);
                    if (patientArray['gender'] == 'male') {
                         $("#genderMale").prop('checked', true);
                    } else {
                         $("#genderUnreported").prop('checked', true);
                    }
               } else if (patientArray['gender'] == 'female') {
                    $('.femaleSection').show();
                    $("#genderFemale").prop('checked', true);
                    $('#breastfeedingYes').addClass('isRequired');
                    $('#pregYes').addClass('isRequired');
                    if ($.trim(patientArray['is_pregnant']) != '') {
                         if ($.trim(patientArray['is_pregnant']) == 'yes') {
                              $("#pregYes").prop('checked', true);
                         } else if ($.trim(patientArray['is_pregnant']) == 'no') {
                              $("#pregNo").prop('checked', true);
                         }
                    }
                    if ($.trim(patientArray['is_pregnant']) != '') {
                         if ($.trim(patientArray['is_pregnant']) == 'yes') {
                              $("#breastfeedingYes").prop('checked', true);
                         } else if ($.trim(patientArray['is_pregnant']) == 'no') {
                              $("#breastfeedingNo").prop('checked', true);
                         }
                    }
               }
          }
          if ($.trim(patientArray['consent_to_receive_sms']) != '') {
               if (patientArray['consent_to_receive_sms'] == 'yes') {
                    $("#receivesmsYes").prop('checked', true);
               } else if (patientArray['consent_to_receive_sms'] == 'no') {
                    $("#receivesmsNo").prop('checked', true);
               }
          }
          if ($.trim(patientArray['patient_id']) != '') {
               $("#artNo").val($.trim(patientArray['patient_id']));
          }

          if ($.trim(patientArray['sample_tested_datetime']) != '') {
               $("#rmTestingLastVLDate").val($.trim(patientArray['sample_tested_datetime']));
               $("#repeatTestingLastVLDate").val($.trim(patientArray['sample_tested_datetime']));
               $("#suspendTreatmentLastVLDate").val($.trim(patientArray['sample_tested_datetime']));

          }
          if ($.trim(patientArray['result']) != '') {
               $("#rmTestingVlValue").val($.trim(patientArray['result']));
               $("#repeatTestingVlValue").val($.trim(patientArray['result']));
               $("#suspendTreatmentVlValue").val($.trim(patientArray['result']));
          }

     }


     function vlResultChange(value) {
          if (value != "") {
               $('#vlResult').val(value);
          }
     }

     function showPatientList() {
          $("#showEmptyResult").hide();
          if ($.trim($("#artPatientNo").val()) != '') {
               $.post("/generic-tests/requests/search-patients.php", {
                         artPatientNo: $.trim($("#artPatientNo").val()),
                         testType: $.trim($("#testType").val())
                    },
                    function(data) {
                         if (data >= '1') {
                              showModal('patientModal.php?artNo=' + $.trim($("#artPatientNo").val()) + '&testType=' + $.trim($("#testType").val()), 900, 520);
                         } else {
                              $("#showEmptyResult").show();
                         }
                    });
          }
     }

     function checkPatientDetails(tableName, fieldName, obj, fnct) {
          //if ($.trim(obj.value).length == 10) {
          if ($.trim(obj.value) != '') {
               $.post("/includes/checkDuplicate.php", {
                         tableName: tableName,
                         fieldName: fieldName,
                         value: obj.value,
                         fnct: fnct,
                         format: "html"
                    },
                    function(data) {
                         if (data === '1') {
                              showModal('patientModal.php?artNo=' + obj.value + '&testType=' + $.trim($("#testType").val()), 900, 520);
                         }
                    });
          }
     }

     function checkSampleNameValidation(tableName, fieldName, id, fnct, alrt) {
          if ($.trim($("#" + id).val()) != '') {
               //$.blockUI();
               $.post("/generic-tests/requests/checkSampleDuplicate.php", {
                         tableName: tableName,
                         fieldName: fieldName,
                         value: $("#" + id).val(),
                         fnct: fnct,
                         format: "html"
                    },
                    function(data) {
                         if (data != 0) {

                         }
                    });
               $.unblockUI();
          }
     }

     function insertSampleCode(formId, vlSampleId = null, sampleCode = null, sampleCodeKey = null, sampleCodeFormat = null, countryId = null, sampleCollectionDate = null, provinceCode = null, provinceId = null) {
          $.blockUI();
          let formData = $("#" + formId).serialize();
          formData += "&provinceCode=" + encodeURIComponent(provinceCode);
          formData += "&provinceId=" + encodeURIComponent(provinceId);
          formData += "&countryId=" + encodeURIComponent(countryId);
          formData += "&testType=" + encodeURIComponent($('#testType').find(':selected').data('short'))
          $.post("/generic-tests/requests/insert-sample.php", formData,
               function(data) {
                    //alert(data);
                    if (data > 0) {
                         $.unblockUI();
                         document.getElementById("vlSampleId").value = data;
                         document.getElementById(formId).submit();
                    } else {
                         $.unblockUI();
                         $("#sampleCollectionDate").val('');
                         generateSampleCode();
                         alert("<?= _translate("Could not save this form. Please try again."); ?>");
                    }
               });
     }

     function clearDOB(val) {
          if ($.trim(val) != "") {
               $("#dob").val("");
          }
     }

     function getfacilityProvinceDetails(obj) {
          $.blockUI();
          //check facility name`
          var cName = $("#facilityId").val();
          var pName = $("#province").val();
          if (cName != '' && provinceName && facilityName) {
               provinceName = false;
          }
          if (cName != '' && facilityName) {
               $.post("/includes/siteInformationDropdownOptions.php", {
                         cName: cName,
                         testType: 'generic-tests'
                    },
                    function(data) {
                         if (data != "") {
                              details = data.split("###");
                              $("#province").html(details[0]);
                              $("#district").html(details[1]);
                         }
                    });
          } else if (pName == '' && cName == '') {
               provinceName = true;
               facilityName = true;
               $("#province").html("<?php echo $province ?? ""; ?>");
               $("#facilityId").html("<?php echo $facility ?? ""; ?>");
          }
          $.unblockUI();
     }

     function getTestTypeForm() {
          var testType = $("#testType").val();
          if (testType != "") {
               getTestTypeConfigList(testType);
               $(".requestForm").show();
               $.post("/generic-tests/requests/getTestTypeForm.php", {
                         result: $('#result').val(),
                         testType: testType,
                    },
                    function(data) {
                         if (data != undefined && data !== null) {

                              console.log(data.result);
                              data = JSON.parse(data);
                              $("#facilitySection,#labSection,.subTestResultSection,#otherSection").html('');
                              $('.patientSectionInput').remove();
                              if (typeof(data.facilitySection) != "undefined" && data.facilitySection !== null && data.facilitySection.length > 0) {
                                   $("#facilitySection").html(data.facilitySection);
                              }
                              if (typeof(data.patientSection) != "undefined" && data.patientSection !== null && data.patientSection.length > 0) {
                                   $("#patientSection").after(data.patientSection);
                              }
                              if (typeof(data.labSection) != "undefined" && data.labSection !== null && data.labSection.length > 0) {
                                   $("#labSection").html(data.labSection);
                              }
                              if (typeof(data.result) != "undefined" && data.result !== null && data.result.length > 0) {
                                   $(".subTestResultSection").html(data.result);
                              } else {
                                   $('.subTestResultSection').hide();
                              }
                              if (typeof(data.specimenSection) != "undefined" && data.specimenSection !== null && data.specimenSection.length > 0) {
                                   $("#specimenSection").after(data.specimenSection);
                              }
                              if (typeof(data.otherSection) != "undefined" && data.otherSection !== null && data.otherSection.length > 0) {
                                   $("#otherSection").html(data.otherSection);
                              }



                              $(".dynamicFacilitySelect2").select2({
                                   width: '100%',
                                   placeholder: "<?php echo _translate("Select any one of the option"); ?>"
                              });
                              $(".dynamicSelect2").select2({
                                   width: '100%',
                                   placeholder: "<?php echo _translate("Select any one of the option"); ?>"
                              });

                              $(".multipleSelectize").selectize({
                                   plugins: ["restore_on_backspace", "remove_button", "clear_button"],
                              });

                              if ($('#resultType').val() == 'qualitative') {
                                   $('.final-result-row').attr('colspan', 4)
                                   $('.testResultUnit').hide();
                              } else {
                                   $('.final-result-row').attr('colspan', 5)
                                   $('.testResultUnit').show();
                              }
                         }
                    });

          } else {
               $(".facilitySection").html('');
               $(".patientSectionInput").remove();
               $("#labSection").html('');
               $(".specimenSectionInput").remove();
               $("#otherSection").html('');
               $(".requestForm").hide();
          }


     }

     function loadSubTests() {
          var testType = $("#testType").val();
          var subTestResult = $("#subTestResult").val();
          console.log(subTestResult);
          if (testType != "") {
               $(".requestForm").show();
               $.post("/generic-tests/requests/getTestTypeForm.php", {
                         result: $('#result').val(),
                         testType: testType,
                         subTests: subTestResult,
                    },
                    function(data) {
                         data = JSON.parse(data);
                         $(".subTestResultSection").html('');
                         if (typeof(data.result) != "undefined" && data.result !== null && data.result.length > 0) {
                              $(".subTestResultSection").html(data.result);
                              $('.subTestResultSection').show();
                         } else {
                              $('.subTestResultSection').hide();
                         }

                    });
          } else {
               $(".subTestResultSection").hide();
          }

     }

     function getTestTypeConfigList(testTypeId) {

          $.post("/includes/get-test-type-config.php", {
                    testTypeId: testTypeId
               },
               function(data) {
                    Obj = $.parseJSON(data);
                    if (data != "") {
                         $("#specimenType").html(Obj['sampleTypes']);
                         $("#reasonForTesting").html(Obj['testReasons']);
                    }
               });

     }

     function getSubTestList(testType) {

          $.post("/generic-tests/requests/get-sub-test-list.php", {
                    testTypeId: testType
               },
               function(data) {
                    if (data != "") {
                         $("#subTestResult").append(data);
                         $("#subTestResult").multipleSelect({
                              placeholder: '<?php echo _translate("Select Sub Tests"); ?>',
                              width: '100%'
                         });
                         var length = $('#subTestResult > option').length;
                         if (length > 1) {
                              $('.subTestFields').show();
                         } else {
                              $('.subTestFields').hide();
                         }
                    }
               });
     }

     function addTestRow(row, subTest) {
          var unitTest = '';
          subrow = document.getElementById("testKitNameTable" + row).rows.length
          $('.ins-row-' + row + subrow).attr('disabled', true);
          $('.ins-row-' + row + subrow).addClass('disabled');
          testCounter = (subrow + 1);
          options = $("#resultListQl" + row).html();
          testMethodOptions = $("#testName" + row + (testCounter - 1)).html();
          if ($('.qualitative-field').hasClass('testResultUnit')) {
               unitTest = `<td class="testResultUnit">
                    <select class="form-control resultUnit" id="testResultUnit${row}${testCounter}" name="testResultUnit[${subTest}][]" placeholder='<?php echo _translate("Enter test result unit"); ?>' title='<?php echo _translate("Please enter test result unit"); ?>'>
                         <option value="">--Select--</option>
                         <?php foreach ($testResultUnits as $key => $unit) { ?>
                              <option value="<?php echo $key; ?>"><?php echo $unit; ?></option>
                         <?php } ?>
                    </select>
                    </td>`;
          }
          let rowString = `<tr>
                    <td class="text-center">${(subrow+1)}</td>
                    <td>
                         <select class="form-control test-name-table-input" id="testName${row}${testCounter}" name="testName[${subTest}][]" title="Please enter the name of the Testkit (or) Test Method used">${testMethodOptions}</select>
                         <input type="text" name="testNameOther[${subTest}][]" id="testNameOther${row}${testCounter}" class="form-control testNameOther${testCounter}" title="Please enter the name of the Testkit (or) Test Method used" placeholder="<?php echo _translate('Please enter the name of the Testkit (or) Test Method used'); ?>" style="display: none;margin-top: 10px;" />
                    </td>
                    <td><input type="text" name="testDate[${subTest}][]" id="testDate${row}${testCounter}" class="form-control test-name-table-input dateTime" placeholder="<?php echo _translate('Tested on'); ?>" title="Please enter the tested on for row ${testCounter}" /></td>
                    <td><select name="testingPlatform[${subTest}][]" id="testingPlatform${row}${testCounter}" class="form-control test-name-table-input" title="Please select the Testing Platform for ${testCounter}"><?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?></select></td>
                    <td class="kitlabels" style="display: none;"><input type="text" name="lotNo[${subTest}][]" id="lotNo${row}${testCounter}" class="form-control kit-fields${testCounter}" placeholder="<?php echo _translate('Kit lot no'); ?>" title="Please enter the kit lot no. for row ${testCounter}" style="display:none;"/></td>
                    <td class="kitlabels" style="display: none;"><input type="text" name="expDate[${subTest}][]" id="expDate${row}${testCounter}" class="form-control expDate kit-fields${testCounter}" placeholder="<?php echo _translate('Expiry date'); ?>" title="Please enter the expiry date for row ${testCounter}" style="display:none;"/></td>
                    <td><select class="form-control result-select" name="testResult[${subTest}][]" id="testResult${row}${testCounter}" title="Enter result">${options}</select></td>
                    ${unitTest}
                    <td style="vertical-align:middle;text-align: center;width:100px;">
                         <a class="btn btn-xs btn-primary ins-row-${row}${testCounter} test-name-table" href="javascript:void(0);" onclick="addTestRow(${row}, \'${subTest}\');"><em class="fa-solid fa-plus"></em></a>&nbsp;
                         <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode, ${row},${subrow});"><em class="fa-solid fa-minus"></em></a>
                    </td>
               </tr>`;
          $("#testKitNameTable" + row).append(rowString);

          $('.date').datepicker({
               changeMonth: true,
               changeYear: true,
               onSelect: function() {
                    $(this).change();
               },
               dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
               timeFormat: "HH:mm",
               maxDate: "Today",
               yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
          }).click(function() {
               $('.ui-datepicker-calendar').show();
          });

          $('.expDate').datepicker({
               changeMonth: true,
               changeYear: true,
               onSelect: function() {
                    $(this).change();
               },
               dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
               timeFormat: "HH:mm",
               // minDate: "Today",
               yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
          }).click(function() {
               $('.ui-datepicker-calendar').show();
          });



          if ($('.kitlabels').is(':visible') == true) {
               $('.kitlabels').show();
          }

          if ($('#resultType').val() == 'qualitative') {
               $('.final-result-row').attr('colspan', 4)
               $('.testResultUnit').hide();
          } else {
               $('.final-result-row').attr('colspan', 5)
               $('.testResultUnit').show();
          }
     }

     function removeTestRow(el, row, subrow) {
          $('.ins-row-' + row + subrow).attr('disabled', false);
          $('.ins-row-' + row + subrow).removeClass('disabled');
          $(el).fadeOut("slow", function() {
               el.parentNode.removeChild(el);
               rl = document.getElementById("testKitNameTable" + row).rows.length;
               if (rl == 0) {
                    testCounter = 0;
                    addTestRow(row, (subrow + 1));
               }
          });
     }

     function updateInterpretationResult(obj) {
          if (obj.value) {
               $.post("get-result-interpretation.php", {
                         result: obj.value,
                         resultType: $('#resultType').val(),
                         testType: $('#testType').val()
                    },
                    function(interpretation) {
                         if (interpretation != "") {
                              $('#resultInterpretation').val(interpretation);
                         } else {
                              $('#resultInterpretation').val('');
                         }
                    });
          }
     }
</script>

<?php include APPLICATION_PATH . '/footer.php';
