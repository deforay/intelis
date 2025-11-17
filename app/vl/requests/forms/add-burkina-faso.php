<?php

use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var  FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$lResult = $facilitiesService->getTestingLabs('vl', byPassFacilityMap: true, allColumns: true);

$globalConfig = $general->getGlobalConfig();

if ($globalConfig['sample_code'] == 'auto' || $globalConfig['sample_code'] == 'alphanumeric' || $globalConfig['sample_code'] == 'MMYY' || $globalConfig['sample_code'] == 'YY') {
     $sampleClass = '';
     $maxLength = '';
     if ($globalConfig['max_length'] != '' && $globalConfig['sample_code'] == 'alphanumeric') {
          $maxLength = $globalConfig['max_length'];
          $maxLength = "maxlength=" . $maxLength;
     }
} else {
     $sampleClass = '';
     $maxLength = '';
     if ($globalConfig['max_length'] != '') {
          $maxLength = $globalConfig['max_length'];
          $maxLength = "maxlength=" . $maxLength;
     }
}
// check if STS
$rKey = '';
if ($general->isSTSInstance() && $_SESSION['accessType'] == 'collection-site') {
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
$testReasonsResultDetails = $general->getDataByTableAndFields("r_vl_test_reasons", ['test_reason_id', 'test_reason_name', 'parent_reason'], false, " test_reason_status like 'active' ");
$subTestReasons = $testReasonsResult = [];
foreach ($testReasonsResultDetails as $row) {
     if ($row['parent_reason'] == 0) {
          $testReasonsResult[$row['test_reason_id']] = $row['test_reason_name'];
     } else {
          $subTestReasons[$row['parent_reason']][$row['test_reason_id']] = $row['test_reason_name'];
     }
}
?>
<style>
     .table>tbody>tr>td {
          border-top: none;
     }

     .form-control {
          width: 100% !important;
     }

     .row {
          margin-top: 6px;
     }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
     <!-- Content Header (Page header) -->
     <section class="content-header">
          <h1><em class="fa-solid fa-pen-to-square"></em> <?= _translate("VIRAL LOAD LABORATORY REQUEST FORM"); ?> </h1>
          <ol class="breadcrumb">
               <li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
               <li class="active"><?= _translate("Add Vl Request"); ?></li>
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
                    <form class="form-inline" method="post" name="vlRequestFormSs" id="vlRequestFormSs" autocomplete="off" action="addVlRequestHelper.php">
                         <div class="box-body">
                              <div class="box box-primary">
                                   <div class="box-header with-border">
                                        <h3 class="box-title"><?= _translate("Clinic Information: (To be filled by requesting Clinican/Nurse)"); ?></h3>
                                   </div>
                                   <div class="box-body">
                                        <div class="row">
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="sampleCode"><?= _translate("Sample ID"); ?> <span class="mandatory">*</span></label>
                                                       <input type="text" class="form-control isRequired <?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" <?php echo $maxLength; ?> placeholder="Enter Sample ID" title="<?= _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>" style="width:100%;" readonly onblur="checkSampleNameValidation('form_vl','<?php echo $sampleCode; ?>',this.id,null,'This sample number already exists.Try another number',null)" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="sampleReordered">
                                                            <input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" title="Please indicate if this is a reordered sample"> <?= _translate("Sample Reordered") ?>
                                                       </label>
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row">
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="province"><?= _translate("State/Province"); ?> <span class="mandatory">*</span></label>
                                                       <select class="form-control isRequired" name="province" id="province" title="Please choose state" style="width:100%;" onchange="getProvinceDistricts(this);">
                                                            <?php echo $province; ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="district"><?= _translate("District/County"); ?> <span class="mandatory">*</span></label>
                                                       <select class="form-control isRequired" name="district" id="district" title="Please choose county" style="width:100%;" onchange="getFacilities(this);">
                                                            <option value=""> -- <?= _translate("Select"); ?> -- </option>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="facilityId"><?= _translate("Clinic/Health Center"); ?> <span class="mandatory">*</span></label>
                                                       <select class="form-control isRequired select2" id="facilityId" name="facilityId" title="Please select clinic/health center name" style="width:100%;" onchange="getfacilityProvinceDetails(this);fillFacilityDetails();setSampleDispatchDate();generateSampleCode();">
                                                            <?php echo $facility; ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3" style="display:none;">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="facilityCode"><?= _translate("Clinic/Health Center Code"); ?> </label>
                                                       <input type="text" class="form-control" style="width:100%;" name="facilityCode" id="facilityCode" placeholder="Clinic/Health Center Code" title="Please enter clinic/health center code">
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
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="implementingPartner"><?= _translate("Implementing Partner"); ?></label>
                                                       <select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose implementing partner" style="width:100%;">
                                                            <option value=""> -- <?= _translate("Select"); ?> -- </option>
                                                            <?php foreach ($implementingPartnerList as $implementingPartner) { ?>
                                                                 <option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
                                                            <?php } ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="fundingSource"><?= _translate("Funding Source"); ?></label>
                                                       <select class="form-control" name="fundingSource" id="fundingSource" title="Please choose implementing partner" style="width:100%;">
                                                            <option value=""> -- <?= _translate("Select"); ?> -- </option>
                                                            <?php
                                                            foreach ($fundingSourceList as $fundingSource) {
                                                            ?>
                                                                 <option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>"><?= $fundingSource['funding_source_name']; ?></option>
                                                            <?php } ?>
                                                       </select>
                                                  </div>
                                             </div>

                                             <div class="col-md-4 col-md-4">
                                                  <label for="labId"><?= _translate("Testing Lab"); ?> <span class="mandatory">*</span></label>
                                                  <select name="labId" id="labId" class="select2 form-control isRequired" title="Please choose lab" onchange="autoFillFocalDetails();setSampleDispatchDate();" style="width:100%;">
                                                       <option value="">-- Select --</option>
                                                       <?php foreach ($lResult as $labName) { ?>
                                                            <option data-focalperson="<?php echo $labName['contact_person']; ?>" data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>" value="<?php echo $labName['facility_id']; ?>"><?= $labName['facility_name']; ?></option>
                                                       <?php } ?>
                                                  </select>
                                             </div>
                                        </div>
                                        <div class="row">
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="implementingPartner"><?= _translate("Test Request Date"); ?></label>
                                                       <input type="text" class="form-control date" readonly name="testRequestDate" id="testRequestDate" title="<?= _translate("Please enter the test request date"); ?>" placeholder="<?= _translate("Test request date"); ?>" style="width:100%;" />
                                                  </div>
                                             </div>
                                        </div>
                                   </div>
                              </div>
                              <div class="box box-primary">
                                   <div class="box-header with-border">
                                        <h3 class="box-title"><?= _translate("Patient Information"); ?></h3>&nbsp;&nbsp;&nbsp;
                                        <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="" placeholder="Enter ART Number or Patient Name" title="Enter art number or patient name" />&nbsp;&nbsp;
                                        <a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No Patient Found</strong></span>
                                   </div>
                                   <div class="box-body">
                                        <div class="row">
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="artNo"><?= _translate("Patient ID"); ?> <span class="mandatory">*</span></label>
                                                       <input type="text" name="artNo" id="artNo" class="form-control isRequired patientId" placeholder="Enter ART Number" title="Enter art number" onchange="checkPatientDetails('form_vl','patient_art_no',this,null)" />
                                                       <span class="artNoGroup" id="artNoGroup"></span>
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="dob"><?= _translate("Date of Birth"); ?> </label>
                                                       <input type="text" name="dob" id="dob" class="form-control date" placeholder="Enter DOB" title="Enter dob" onchange="getAge();checkARTInitiationDate();" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="ageInYears"><?= _translate("If DOB unknown, Age in Years"); ?></label>
                                                       <input type="text" name="ageInYears" id="ageInYears" class="form-control forceNumeric" maxlength="3" placeholder="Age in Years" title="Enter age in years" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="ageInMonths"><?= _translate("If Age < 1, Age in Months"); ?></label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="Age in Month" title="Enter age in months" />
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row">
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="patientFirstName"><?= _translate("Patient Name (First Name, Last Name)"); ?><span class="mandatory">*</span></label>
                                                       <input type="text" name="patientFirstName" id="patientFirstName" class="form-control isRequired" placeholder="Enter Patient Name" title="Enter patient name" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="gender"><?= _translate("Sex"); ?> <span class="mandatory">*</span></label><br>
                                                       <select class="form-control ajax-select2" id="gender" name="gender" placeholder="Sex" style="width:100%;">
                                                            <option value="">-- Select --</option>
                                                            <option value="male"><?= _translate("Male"); ?></option>
                                                            <option value="female"><?= _translate("Female"); ?></option>
                                                            <option value="unreported"><?= _translate("Unreported"); ?></option>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3 femaleSection" style="display:none;">
                                                  <div class="form-group">
                                                       <label for="patientPregnant"><?= _translate('Is Patient Pregnant?'); ?> <span class="mandatory">*</span></label><br>
                                                       <label class="radio-inline">
                                                            <input type="radio" class="" id="pregYes" name="patientPregnant" value="yes" title="<?= _translate('Please check if patient is pregnant'); ?>"> <?= _translate('Yes'); ?>
                                                       </label>
                                                       <label class="radio-inline">
                                                            <input type="radio" class="" id="pregNo" name="patientPregnant" value="no"> <?= _translate('No'); ?>
                                                       </label>
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3 femaleSection" style="display:none;">
                                                  <div class="form-group">
                                                       <label for="breastfeeding"><?= _translate('Is Patient Breastfeeding?'); ?> <span class="mandatory">*</span></label><br>
                                                       <label class="radio-inline">
                                                            <input type="radio" class="" id="breastfeedingYes" name="breastfeeding" value="yes" title="<?= _translate('Please check if patient is breastfeeding'); ?>"> <?= _translate('Yes'); ?>
                                                       </label>
                                                       <label class="radio-inline">
                                                            <input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no"> <?= _translate('No'); ?>
                                                       </label>
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
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group" style=" width: 100%; ">
                                                            <label for=""><?= _translate("Date of Sample Collection"); ?> <span class="mandatory">*</span></label>
                                                            <input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select sample collection date" onchange="checkSampleTestingDate();generateSampleCode();setSampleDispatchDate();checkCollectionDate(this.value);">
                                                            <span class="expiredCollectionDate" style="color:red; display:none;"></span>
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group" style=" width: 100%; ">
                                                            <label for=""><?= _translate("Sample Dispatched On"); ?> <span class="mandatory">*</span></label>
                                                            <input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="Sample Dispatched On" title="Please select sample dispatched on">
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group" style=" width: 100%; ">
                                                            <label for="specimenType"><?= _translate("Sample Type"); ?> <span class="mandatory">*</span></label>
                                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose sample type">
                                                                 <option value=""> -- <?= _translate("Select"); ?> -- </option>
                                                                 <?php foreach ($sResult as $name) { ?>
                                                                      <option value="<?php echo $name['sample_id']; ?>"><?= _translate($name['sample_name']); ?></option>
                                                                 <?php } ?>
                                                            </select>
                                                       </div>
                                                  </div>
                                                  <?php if ($general->isLISInstance()) { ?>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group" style="width: 100%; ">
                                                                 <label for="sampleReceivedDate"><?= _translate("Date Sample Received at Testing Lab"); ?><span class="mandatory">*</span> </label>
                                                                 <input type="text" class="form-control dateTime isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="Sample Received at LAB Date" title="Please select sample received at Lab date" />
                                                            </div>
                                                       </div>
                                                  <?php } ?>
                                             </div>
                                        </div>
                                        <div class="box box-primary">
                                             <div class="box-header with-border">
                                                  <h3 class="box-title"><?= _translate("Treatment Information"); ?></h3>
                                             </div>
                                             <div class="box-body">
                                                  <div class="row">
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group" style=" width: 100%; ">
                                                                 <label for="lineOfTreatment" class="labels"><?= _translate('Line of Treatment'); ?> </label>
                                                                 <select class="form-control" name="lineOfTreatment" id="lineOfTreatment" title="<?= _translate('Line Of Treatment'); ?>">
                                                                      <option value=""><?= _translate('--Select--'); ?></option>
                                                                      <option value="1"><?= _translate('1st Line'); ?></option>
                                                                      <option value="2"><?= _translate('2nd Line'); ?></option>
                                                                      <option value="3"><?= _translate('3rd Line'); ?></option>
                                                                      <option value="n/a"><?= _translate('N/A'); ?></option>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group" style=" width: 100%; ">
                                                                 <label for=""><?= _translate("Date of Initiation of Treatment Line"); ?> </label>
                                                                 <input type="text" class="form-control date" style="width:100%;" name="regimenInitiatedOn" id="regimenInitiatedOn" placeholder="Current Regimen Initiated On" title="Please enter current regimen initiated on">
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group" style=" width: 100%; ">
                                                                 <label for="arvAdherence"><?= _translate("ARV Adherence"); ?> </label>
                                                                 <select name="arvAdherence" id="arvAdherence" class="form-control" title="Please choose adherence">
                                                                      <option value=""> -- <?= _translate("Select"); ?> -- </option>
                                                                      <option value="good"><?= _translate("Good >= 95%"); ?></option>
                                                                      <option value="fair"><?= _translate("Fair (85-94%)"); ?></option>
                                                                      <option value="poor"><?= _translate("Poor < 85%"); ?></option>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group" style=" width: 100%; ">
                                                                 <label for="treatmentDurationPrecise"><?= _translate("Duration of treatment"); ?> </label>
                                                                 <select class="form-control" id="treatmentDurationPrecise" name="treatmentDurationPrecise" placeholder="Select Treatment Period" title="Please select how long has this patient been on treatment" onchange="treatmentDuration(this.value)">
                                                                      <option value=""> -- <?= _translate("Select"); ?> -- </option>
                                                                      <option value="6 Months"><?= _translate("6 Months"); ?></option>
                                                                      <option value="12 Months"><?= _translate("12 Months"); ?></option>
                                                                      <option value="More than 12 Months"><?= _translate("More than 12 Months"); ?></option>
                                                                 </select>
                                                                 <input type="text" class="form-control" name="treatmentDurationPrecise1" id="treatmentDurationPrecise1" placeholder="Enter treatment period" title="Please enter treatment period" style="width:100%;display:none;margin-top:2px;">
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group" style=" width: 100%; ">
                                                                 <label for="cd4Result"><?= _translate("Latest CD4 count"); ?> <small>(cells/µl)</small></label>
                                                                 <input type="text" class="form-control" name="cd4Result" id="cd4Result" placeholder="Enter CD4 count" title="Please enter CD4 count" style="width:100%;margin-top:2px;">
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group" style=" width: 100%; ">
                                                                 <label for="cd4Percentage"><?= _translate("CD4 (%)"); ?> </label>
                                                                 <input type="text" class="form-control" name="cd4Percentage" id="cd4Percentage" placeholder="Enter CD4 %" title="Please enter CD4 %" style="width:100%;margin-top:2px;">
                                                            </div>
                                                       </div>
                                                       <!-- <div class="col-xs-3 col-md-3">
                                                            <div class="form-group" style=" width: 100%; ">
                                                                <label for="cd8"><?= _translate("CD8"); ?> <small>(cells/µl)</small></label>
                                                                <input type="text" class="form-control" name="cd8" id="cd8" placeholder="Enter CD8" title="Please enter CD8" style="width:100%;margin-top:2px;">
                                                            </div>
                                                       </div> -->
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group" style=" width: 100%; ">
                                                                 <label for="cd4Date"><?= _translate("Date"); ?> </label>
                                                                 <input type="text" class="form-control date" name="cd4Date" id="cd4Date" placeholder="Enter CD Date" title="Please enter CD Date" style="width:100%;margin-top:2px;">
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group" style=" width: 100%; ">
                                                                 <label for="cd8Result"><?= _translate("Last HIV-1 Viral Load"); ?> <small>(copies/mL)</small></label>
                                                                 <input type="text" class="form-control" name="cd8Result" id="cd8Result" placeholder="Enter CD4 count" title="Please enter HIV-1 Result" style="width:100%;margin-top:2px;">
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group" style=" width: 100%; ">
                                                                 <label for="cd8Date"><?= _translate("Date"); ?> </label>
                                                                 <input type="text" class="form-control date" name="cd8Date" id="cd8Date" placeholder="Enter HIV-1 Date" title="Please enter HIV-1 Date" style="width:100%;margin-top:2px;">
                                                            </div>
                                                       </div>
                                                  </div>
                                             </div>

                                             <div class="box box-primary">
                                                  <div class="box-header with-border">
                                                       <h3 class="box-title"><?= _translate("Indication for Viral Load Testing"); ?> <span class="mandatory">*</span></h3><small> <?= _translate("(Please choose one):(To be completed by clinician)"); ?></small>
                                                  </div>
                                                  <div class="box-body">
                                                       <?php if ($testReasonsResult !== []) {
                                                            foreach ($testReasonsResult as $key => $title) { ?>
                                                                 <div class="row">
                                                                      <div class="col-md-6">
                                                                           <div class="form-group">
                                                                                <div class="col-lg-12">
                                                                                     <label class="radio-inline">
                                                                                          <input type="radio" class="isRequired" id="rmTesting<?php echo $key; ?>" name="reasonForVLTesting" value="<?php echo $key; ?>" title="<?= _translate('Please check viral load indication testing type'); ?>" onclick="showTesting('rmTesting<?php echo $key; ?>', <?php echo $key; ?>);">
                                                                                          <strong><?= _translate($title); ?></strong>
                                                                                     </label>
                                                                                </div>
                                                                           </div>
                                                                      </div>
                                                                 </div>
                                                                 <?php if ($key == 5) { ?>
                                                                      <div class="row rmTesting5 hideTestData well" style="display:none;">
                                                                           <div class="col-md-6">
                                                                                <label class="col-lg-5 control-label"><?= _translate('Please specify other reasons'); ?></label>
                                                                                <div class="col-lg-7">
                                                                                     <input type="text" class="form-control" id="newreasonForVLTesting" name="newreasonForVLTesting" placeholder="<?= _translate('Please specify other test reason') ?>" title="<?= _translate('Please specify other test reason') ?>" />
                                                                                </div>
                                                                           </div>
                                                                      </div>
                                                                 <?php } ?>
                                                                 <?php if (isset($subTestReasons[$key]) && $subTestReasons[$key] !== []) { ?>
                                                                      <div class="row rmTesting<?php echo $key; ?> hideTestData well" style="display:none;">
                                                                           <div class="col-md-6">
                                                                                <label class="col-lg-5 control-label"><?= _translate('Choose reason for testing'); ?></label>
                                                                                <div class="col-lg-7">
                                                                                     <select name="controlVlTestingType[<?php echo $key; ?>]" id="controlVlType<?php echo $key; ?>" class="form-control controlVlTypeFields" title="<?= _translate('Please choose a reason for VL testing'); ?>" onchange="checkreasonForVLTesting();">
                                                                                          <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                                                          <?php foreach ($subTestReasons[$key] as $testReasonId => $row) { ?>
                                                                                               <option value="<?php echo $testReasonId; ?>"><?php echo _translate($row); ?></option>
                                                                                          <?php } ?>
                                                                                     </select>
                                                                                </div>
                                                                           </div>
                                                                      </div>
                                                       <?php
                                                                 }
                                                            }
                                                       } ?>
                                                       <hr>
                                                  </div>
                                             </div>
                                             <?php if (_isAllowed('/vl/results/vlTestResult.php') && $_SESSION['accessType'] != 'collection-site') { ?>
                                                  <div class="box box-primary">
                                                       <div class="box-header with-border">
                                                            <h3 class="box-title"><?= _translate("Laboratory Information"); ?></h3>
                                                       </div>
                                                       <div class="box-body">
                                                            <div class="row">
                                                                 <!-- <div class="col-md-4">
                                                                      <label for="labId" class="col-lg-5 control-label labels">Lab Name </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="labId" id="labId" class="select2 form-control" title="Please choose the testing lab" onchange="autoFillFocalDetails();">
                                                                                <option value="">-- Select --</option>
                                                                                <?php foreach ($lResult as $labName) { ?>
                                                                                     <option data-focalperson="<?php echo $labName['contact_person']; ?>" data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>" value="<?php echo $labName['facility_id']; ?>"><?= $labName['facility_name']; ?></option>
                                                                                <?php } ?>
                                                                           </select>
                                                                      </div>
                                                                 </div> -->
                                                                 <div class="col-md-6">
                                                                      <label for="vlFocalPerson" class="col-lg-5 control-label labels"><?= _translate("VL Focal Person"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <select class="form-control ajax-select2" id="vlFocalPerson" name="vlFocalPerson" placeholder="VL Focal Person" title="Please enter vl focal person name"></select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label labels"><?= _translate("VL Focal Person Phone Number"); ?></label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter vl focal person phone number" />
                                                                      </div>
                                                                 </div>
                                                            </div>

                                                            <div class="row">
                                                                 <div class="col-md-6">
                                                                      <label for="testingPlatform" class="col-lg-5 control-label labels"><?= _translate("VL Testing Platform"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="testingPlatform" id="testingPlatform" class="form-control result-optional" title="Please choose VL Testing Platform" onchange="hivDetectionChange();">
                                                                                <option value="">-- Select --</option>
                                                                                <?php foreach ($importResult as $mName) { ?>
                                                                                     <option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['instrument_id']; ?>"><?php echo $mName['machine_name']; ?></option>
                                                                                <?php } ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="isSampleRejected"><?= _translate("Is Sample Rejected?"); ?></label>
                                                                      <div class="col-lg-7">
                                                                           <select name="isSampleRejected" id="isSampleRejected" class="form-control" title="Please check if sample is rejected or not">
                                                                                <option value="">-- Select --</option>
                                                                                <option value="yes"><?= _translate("Yes"); ?></option>
                                                                                <option value="no"><?= _translate("No"); ?></option>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-6 rejectionReason" style="display:none;">
                                                                      <label class="col-lg-5 control-label labels" for="rejectionReason"><?= _translate("Rejection Reason"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="rejectionReason" id="rejectionReason" class="form-control" title="Please choose reason" onchange="checkRejectionReason();">
                                                                                <option value="">-- Select --</option>
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
                                                                           <input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="Rejection Reason" title="Please enter rejection reason" style="width:100%;display:none;margin-top:2px;">
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6 rejectionReason" style="display:none;">
                                                                      <label class="col-lg-5 control-label labels" for="rejectionDate"><?= _translate("Rejection Date"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" title="Please select rejection date" />
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="sampleTestingDateAtLab"><?= _translate("Sample Testing Date"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control result-fields dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Sample Testing Date" title="Please select sample testing date" onchange="checkSampleTestingDate();" disabled />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6 vlResult">
                                                                      <label class="col-lg-5 control-label  labels" for="vlResult"><?= _translate("Viral Load Result (copies/mL)"); ?> </label>
                                                                      <div class="col-lg-7 resultInputContainer">
                                                                           <input list="possibleVlResults" autocomplete="off" class="form-control result-fields labSection" id="vlResult" name="vlResult" placeholder="Select or Type VL Result" title="Please enter viral load result" onchange="calculateLogValue(this)" disabled>
                                                                           <datalist id="possibleVlResults">

                                                                           </datalist>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">

                                                                 <div class="vlLog col-md-6">
                                                                      <label class="col-lg-5 control-label  labels" for="vlLog"><?= _translate("Viral Load (Log)"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control" id="vlLog" name="vlLog" placeholder="Viral Load (Log)" title="Please enter viral load result in Log" style="width:100%;" onchange="calculateLogValue(this);" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label" for="reviewedBy"><?= _translate("Reviewed By"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="reviewedBy" id="reviewedBy" class="select2 form-control labels" title="Please choose reviewed by" style="width: 100%;">
                                                                                <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-6 hivDetection" style="display: none;">
                                                                      <label for="hivDetection" class="col-lg-5 control-label labels"><?= _translate("HIV Detection"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="hivDetection" id="hivDetection" class="form-control hivDetection" title="Please choose HIV detection">
                                                                                <option value="">-- Select --</option>
                                                                                <option value="HIV-1 Detected"><?= _translate("HIV-1 Detected"); ?></option>
                                                                                <option value="HIV-1 Not Detected"><?= _translate("HIV-1 Not Detected"); ?></option>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <?php if (count($reasonForFailure) > 0) { ?>
                                                                      <div class="col-md-6 reasonForFailure" style="display: none;">
                                                                           <label class="col-lg-5 control-label" for="reasonForFailure"><?= _translate("Reason for Failure"); ?> <span class="mandatory">*</span> </label>
                                                                           <div class="col-lg-7">
                                                                                <select name="reasonForFailure" id="reasonForFailure" class="form-control" title="Please choose reason for failure" style="width: 100%;">
                                                                                     <?= $general->generateSelectOptions($reasonForFailure, null, '-- Select --'); ?>
                                                                                </select>
                                                                           </div>
                                                                      </div>
                                                                 <?php } ?>
                                                            </div>
                                                            <hr>
                                                            <div class="row">

                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="reviewedOn"><?= _translate("Reviewed On"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="testedBy"><?= _translate("Tested By"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="testedBy" id="testedBy" class="select2 form-control" title="Please choose approved by">
                                                                                <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">

                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="approvedBy"><?= _translate("Approved By"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose approved by">
                                                                                <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="approvedOn"><?= _translate("Approved On"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" value="" class="form-control dateTime" id="approvedOnDateTime" title="Please choose Approved On" name="approvedOnDateTime" placeholder="<?= _translate("Please enter date"); ?>" style="width:100%;" />
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">

                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="resultDispatchedOn"><?= _translate("Date Results Dispatched"); ?></label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatch Date" title="Please select result dispatched date" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="labComments"><?= _translate("Lab Tech. Comments"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <textarea class="form-control" name="labComments" id="labComments" placeholder="Lab comments" title="Please enter LabComments"></textarea>
                                                                      </div>
                                                                 </div>
                                                            </div>
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
                                                       <?= _translate("Printer:"); ?> <select id="printers"></select>
                                                  </div> <!-- /printer_select -->
                                             <?php } ?>
                                             <!-- BARCODESTUFF END -->
                                             <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;"><?= _translate("Save"); ?></a>
                                             <input type="hidden" name="saveNext" id="saveNext" />
                                             <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $globalConfig['sample_code']; ?>" />
                                             <?php if ($globalConfig['sample_code'] == 'auto' || $globalConfig['sample_code'] == 'YY' || $globalConfig['sample_code'] == 'MMYY') { ?>
                                                  <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                                                  <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                                             <?php } ?>
                                             <input type="hidden" name="vlSampleId" id="vlSampleId" value="" />
                                             <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateSaveNow();return false;"><?= _translate("Save and Next"); ?></a>
                                             <a href="/vl/requests/vl-requests.php" class="btn btn-default"> <?= _translate("Cancel"); ?></a>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <input type="hidden" id="selectedSample" value="" name="selectedSample" class="" />
                         <input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $globalConfig['vl_form']; ?>" />

                    </form>
               </div>
          </div>
     </section>
</div>

<?= CommonService::barcodeScripts(); ?>

<script type="text/javascript" src="/assets/js/moment.min.js"></script>
<script>
     let provinceName = true;
     let facilityName = true;
     $(document).ready(function() {

          $("#artNo").on('input', function() {

               let artNo = $.trim($(this).val());

               if (artNo.length > 3) {

                    $.post("/common/patient-last-request-details.php", {
                              testType: 'vl',
                              patientId: artNo,
                         },
                         function(data) {
                              if (data != "0") {
                                   obj = $.parseJSON(data);
                                   if (obj.no_of_req_time != null && obj.no_of_req_time > 0) {
                                        $("#artNoGroup").html("<small style='color: red'><?= _translate("No. of times Test Requested for this Patient", true); ?> : " + obj.no_of_req_time + "</small>");
                                   }
                                   if (obj.request_created_datetime != null) {
                                        $("#artNoGroup").append("<br><small style='color:red'><?= _translate("Last Test Request Added On LIS/STS", true); ?> : " + obj.request_created_datetime + "</small>");
                                   }
                                   if (obj.sample_collection_date != null) {
                                        $("#artNoGroup").append("<br><small style='color:red'><?= _translate("Sample Collection Date for Last Request", true); ?> : " + obj.sample_collection_date + "</small>");
                                   }
                                   if (obj.no_of_tested_time != null && obj.no_of_tested_time > 0) {
                                        $("#artNoGroup").append("<br><small style='color:red'><?= _translate("Total No. of times Patient tested for HIV VL", true); ?> : " + obj.no_of_tested_time + "</small >");
                                   }
                              } else {

                                   $("#artNoGroup").html('');
                              }
                         });
               }

          });
          $("#labId,#facilityId,#sampleCollectionDate").on('change', function() {

               if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleDispatchedDate").val() == "") {
                    $('#sampleDispatchedDate').val($('#sampleCollectionDate').val());
               }
               if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleReceivedDate").val() == "") {
                    $('#sampleReceivedDate').val($('#sampleCollectionDate').val());
               }
          });

          $("#labId").on('change', function() {
               if ($("#labId").val() != "") {
                    $.post("/includes/get-sample-type.php", {
                              facilityId: $('#labId').val(),
                              testType: 'vl'
                         },
                         function(data) {
                              if (data != "") {
                                   $("#specimenType").html(data);
                              }
                         });
               }
          });

          $('#labId').select2({
               width: '100%',
               placeholder: "Select Testing Lab"
          });
          $('#facilityId').select2({
               width: '100%',
               placeholder: "Select Clinic/Health Center"
          });
          $('#reviewedBy').select2({
               width: '100%',
               placeholder: "Select Reviewed By"
          });
          $('#testedBy').select2({
               width: '100%',
               placeholder: "Select Tested By"
          });

          $('#approvedBy').select2({
               width: '100%',
               placeholder: "Select Approved By"
          });
          $('#facilityId').select2({
               placeholder: "Select Clinic/Health Center"
          });
          $('#district').select2({
               placeholder: "District"
          });
          $('#province').select2({
               placeholder: "Province"
          });
          $('#artRegimen').select2({
               placeholder: "Select ART Regimen"
          });
          // BARCODESTUFF START
          <?php
          if (isset($_GET['barcode']) && $_GET['barcode'] == 'true') {
               $sampleCode = htmlspecialchars((string) $_GET['s']);
               $facilityCode = htmlspecialchars((string) $_GET['f']);
               $patientID = htmlspecialchars((string) $_GET['p']);
               echo "printBarcodeLabel('$sampleCode','$facilityCode','$patientID');";
          }
          ?>
          // BARCODESTUFF END
          $("#vlFocalPerson").select2({
               placeholder: "Enter Request Focal name",
               minimumInputLength: 0,
               width: '100%',
               allowClear: true,
               id: function(bond) {
                    return bond._id;
               },
               ajax: {
                    placeholder: "Type one or more character to search",
                    url: "/includes/get-data-list.php",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                         return {
                              fieldName: 'vl_focal_person',
                              tableName: 'form_vl',
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
                              fieldName: 'vl_focal_person',
                              tableName: 'form_vl',
                              returnField: 'vl_focal_person_phone_number',
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

          $("#patientProvince").select2({
               placeholder: "Enter patient province",
               minimumInputLength: 0,
               width: '100%',
               allowClear: true,
               ajax: {
                    placeholder: "Type one or more character to search",
                    url: "/covid-19/requests/get-province-district-list.php",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                         return {
                              type: 'province',
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
          $("#patientDistrict").select2({
               placeholder: "Select patient district",
               minimumInputLength: 0,
               width: '100%',
               allowClear: true,
               ajax: {
                    placeholder: "Type one or more character to search",
                    url: "/covid-19/requests/get-province-district-list.php",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                         return {
                              zName: $("#patientZone").val(),
                              type: 'district',
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
          $("#patientZone").select2({
               placeholder: "Select the patient region",
               minimumInputLength: 0,
               width: '100%',
               allowClear: true,
               ajax: {
                    placeholder: "Type one or more character to search",
                    url: "/covid-19/requests/get-province-district-list.php",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                         return {
                              pName: $("#patientProvince").val(),
                              type: 'zone',
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
     });

     // $(document).on('select2:open', (e) => {
     //      const selectId = e.target.id

     //      $(".select2-search__field[aria-controls='select2-" + selectId + "-results']").each(function(
     //           key,
     //           value,
     //      ) {
     //           value.focus();
     //      })
     // });

     function showTesting(chosenClass, id) {
          $('.controlVlTypeFields').removeClass('isRequired');
          if ($('#controlVlType' + id).length) {
               $('#controlVlType' + id).addClass('isRequired');
          }
          $(".viralTestData").val('');
          $(".hideTestData").hide();
          $("." + chosenClass).show();
          if ($("#selectedSample").val() != "") {
               patientInfo = JSON.parse($("#selectedSample").val());

               if ($.trim(patientInfo['sample_tested_datetime']) != '') {
                    $("#rmTestingLastVLDate").val($.trim(patientInfo['sample_tested_datetime']));
                    $("#repeatTestingLastVLDate").val($.trim(patientInfo['sample_tested_datetime']));
                    $("#suspendTreatmentLastVLDate").val($.trim(patientInfo['sample_tested_datetime']));
               }

               if ($.trim(patientInfo['result']) != '') {
                    $("#rmTestingVlValue").val($.trim(patientInfo['result']));
                    $("#repeatTestingVlValue").val($.trim(patientInfo['result']));
                    $("#suspendTreatmentVlValue").val($.trim(patientInfo['result']));
               }
          }
     }

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
                              testType: 'vl'
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




     function getFacilities(obj) {
          $.blockUI();
          var dName = $("#district").val();
          var cName = $("#facilityId").val();
          if (dName != '') {
               $.post("/includes/siteInformationDropdownOptions.php", {
                         dName: dName,
                         cliName: cName,
                         testType: 'vl'
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
     $("#gender").change(function() {
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
               $('.vlLog').css('display', 'block');
               $('.rejectionReason').hide();
               $('#rejectionReason').removeClass('isRequired');
               $('#rejectionDate').removeClass('isRequired');
               $('#rejectionReason').val('');
               $(".review-approve-span").hide();
          }
     });
     $("#isSampleRejected").on("change", function() {

          hivDetectionChange();

          if ($(this).val() == 'yes') {
               $('.rejectionReason').show();
               $('.vlResult, .hivDetection').css('display', 'none');
               $('.vlLog').css('display', 'none');
               $("#sampleTestingDateAtLab, #vlResult, .hivDetection").val("");
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
               $('.vlResult,.vlLog').css('display', 'block');
               $('.rejectionReason').hide();
               $('#rejectionReason').removeClass('isRequired');
               $('#rejectionDate').removeClass('isRequired');
               $('#rejectionReason').val('');
               $('#reviewedBy').addClass('isRequired');
               $('#reviewedOn').addClass('isRequired');
               $('#approvedBy').addClass('isRequired');
               $('#approvedOn').addClass('isRequired');
               //$(".hivDetection").trigger("change");
          } else {
               $(".result-fields").attr("disabled", false);
               $(".result-fields").removeClass("isRequired");
               $(".result-optional").removeClass("isRequired");
               $(".result-span").show();
               $('.vlResult,.vlLog').css('display', 'block');
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
               //$(".hivDetection").trigger("change");
          }
     });

     $('#hivDetection').on("change", function() {
          if (this.value == null || this.value == '' || this.value == undefined) {
               return false;
          } else if (this.value === 'HIV-1 Not Detected') {
               $("#isSampleRejected").val("no");
               $('#vlResult').attr('disabled', false);
               $('#vlLog').attr('disabled', false);
               $("#vlResult,#vlLog").val('');
               $(".vlResult, .vlLog").hide();
               $("#reasonForFailure").removeClass('isRequired');
               $('#vlResult').removeClass('isRequired');
          } else if (this.value === 'HIV-1 Detected') {
               $("#isSampleRejected").val("no");
               $(".vlResult, .vlLog").show();
               $('#vlResult').addClass('isRequired');
               $("#isSampleRejected").trigger("change");
          }
     });

     $('#vlResult').on('change', function() {
          if ($(this).val().trim().toLowerCase() == 'failed' || $(this).val().trim().toLowerCase() == 'error') {
               if ($(this).val().trim().toLowerCase() == 'failed') {
                    $('.reasonForFailure').show();
                    $('#reasonForFailure').addClass('isRequired');
               }
               $('#vlLog, .hivDetection').attr('readonly', true);
          } else {
               $('.reasonForFailure').hide();
               $('#reasonForFailure').removeClass('isRequired');
               $('#vlLog, .hivDetection').attr('readonly', false);
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
          $(".vlResult, .vlLog").show();
          //$('#vlResult, #isSampleRejected').addClass('isRequired');
          $("#isSampleRejected").val("");
          //$("#isSampleRejected").trigger("change");
          hivDetectionChange();
     });

     function hivDetectionChange() {

          var text = $('#testingPlatform').val();
          if (!text) {
               $("#vlResult").attr("disabled", true);
               return;
          }

          var str1 = text.split("##");
          var str = str1[0];
          console.log(str.toLowerCase());
          if ((str.trim() == 'GeneXpert' || str.toLowerCase() == 'genexpert') && $('#isSampleRejected').val() != 'yes') {
               $('.hivDetection').prop('disabled', false);
               $('.hivDetection').show();
          } else {
               $('.hivDetection').hide();
               $("#hivDetection").val("");
          }

          //Get VL results by platform id
          var platformId = str1[3];
          $("#possibleVlResults").html('');
          $.post("/vl/requests/getVlResults.php", {
                    instrumentId: platformId,
               },
               function(data) {
                    // alert(data);
                    $("#vlResult").attr("disabled", false);
                    if (data != "") {
                         $("#possibleVlResults").html(data);
                    }
               });

     }

     function setSampleDispatchDate() {
          if ($("#labId").val() != "" && $("#labId").val() == $("#facilityId").val() && $('#sampleDispatchedDate').val() == "") {
               $('#sampleDispatchedDate').val($("sampleCollectionDate").val());
          }
     }

     function validateNow() {

          clearDatePlaceholderValues('input.date, input.dateTime');

          if ($('#isSampleRejected').val() == "yes") {
               $('.vlResult, #vlResult').removeClass('isRequired');
          }
          var format = '<?php echo $globalConfig['sample_code']; ?>';
          var sCodeLentgh = $("#sampleCode").val();
          var minLength = '<?php echo $globalConfig['min_length']; ?>';
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
          $("#saveNext").val('save');
          if (flag) {
               $('.btn-disabled').attr('disabled', 'yes');
               $(".btn-disabled").prop("onclick", null).off("click");
               $.blockUI();
               <?php if ($globalConfig['sample_code'] == 'auto' || $globalConfig['sample_code'] == 'YY' || $globalConfig['sample_code'] == 'MMYY') { ?>
                    insertSampleCode('vlRequestFormSs', 'vlSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', '1', 'sampleCollectionDate');
               <?php } else { ?>
                    document.getElementById('vlRequestFormSs').submit();
               <?php } ?>
          }
     }

     function validateSaveNow() {
          if ($('#isSampleRejected').val() == "yes") {
               $('.vlResult, #vlResult').removeClass('isRequired');
          }
          var format = '<?php echo $globalConfig['sample_code']; ?>';
          var sCodeLentgh = $("#sampleCode").val();
          var minLength = '<?php echo $globalConfig['min_length']; ?>';
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
          $("#saveNext").val('next');
          if (flag) {
               $('.btn-disabled').attr('disabled', 'yes');
               $(".btn-disabled").prop("onclick", null).off("click");
               $.blockUI();
               <?php if ($globalConfig['sample_code'] == 'auto' || $globalConfig['sample_code'] == 'YY' || $globalConfig['sample_code'] == 'MMYY') { ?>
                    insertSampleCode('vlRequestFormSs', 'vlSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 1, 'sampleCollectionDate');
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
               $("#gender").val(patientArray['gender']);
               /*$('#breastfeedingYes').removeClass('isRequired');
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
               }*/
          }
          if ($.trim(patientArray['consent_to_receive_sms']) != '') {
               if (patientArray['consent_to_receive_sms'] == 'yes') {
                    $("#receivesmsYes").prop('checked', true);
               } else if (patientArray['consent_to_receive_sms'] == 'no') {
                    $("#receivesmsNo").prop('checked', true);
               }
          }
          if ($.trim(patientArray['patient_art_no']) != '') {
               $("#artNo").val($.trim(patientArray['patient_art_no']));
          }

          if ($.trim(patientArray['treatment_initiated_date']) != '') {
               $("#dateOfArtInitiation").val($.trim(patientArray['treatment_initiated_date']));
          }

          if ($.trim(patientArray['current_regimen']) != '') {
               $("#artRegimen").val($.trim(patientArray['current_regimen']));
               $('#artRegimen').trigger('change');
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

     function calculateLogValue(obj) {
          if (obj.id == "vlResult") {
               absValue = $("#vlResult").val();
               absValue = Number.parseFloat(absValue).toFixed();
               if (absValue != '' && absValue != 0 && !isNaN(absValue)) {
                    //$("#vlResult").val(absValue);
                    $("#vlLog").val(Math.round(Math.log10(absValue) * 100) / 100);
               } else {
                    $("#vlLog").val('');
               }
          }
          if (obj.id == "vlLog") {
               logValue = $("#vlLog").val();
               if (logValue != '' && logValue != 0 && !isNaN(logValue)) {
                    var absVal = Math.round(Math.pow(10, logValue) * 100) / 100;
                    if (absVal != 'Infinity' && !isNaN(absVal)) {
                         let result = Math.round(Math.pow(10, logValue) * 100) / 100;
                         $("#vlResult").val(result.toFixed(3));
                    }
               } else {
                    $("#vlResult").val('');
               }
          }
     }

     function vlResultChange(value) {
          if (value != "") {
               $('#vlResult').val(value);
          }
     }

     function treatmentDuration(value) {
          if (value == "More than 12 Months") {
               $('#treatmentDurationPrecise1').show();
          } else {
               $('#treatmentDurationPrecise1').hide();
          }
     }

     function checkreasonForVLTesting() {
          var reasonForVLTesting = $("#vlTestReason").val();
          if (reasonForVLTesting == "other") {
               $("#newreasonForVLTesting").show().addClass("isRequired");
          } else {
               $("#newreasonForVLTesting").hide().removeClass("isRequired");
          }
     }
</script>