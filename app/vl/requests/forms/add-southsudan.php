<?php

use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$lResult = $facilitiesService->getTestingLabs('vl', byPassFacilityMap: true, allColumns: true);

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

$facility = $general->generateSelectOptions($healthFacilities, null, _translate("-- Select --"));


$sKey = '';
$sFormat = '';

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
                                                  <div class="form-group">
                                                       <?php if ($general->isSTSInstance()) { ?>
                                                            <td><label for="sampleCode"><?= _translate("Sample ID"); ?> </label></td>
                                                            <td>
                                                                 <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
                                                                 <input type="hidden" id="sampleCode" name="sampleCode" />
                                                            </td>
                                                       <?php } else { ?>
                                                            <td><label for="sampleCode"><?= _translate("Sample ID"); ?> </label><span class="mandatory">*</span></td>
                                                            <td>
                                                                 <input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" readonly placeholder="<?= _htmlTranslate("Sample ID"); ?>" title="<?= _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>" style="width:100%;" onchange="checkSampleNameValidation('form_vl','<?php echo $sampleCode; ?>',this.id,null,'<?= _translate("The Sample ID that you entered already exists. Please try another Sample ID", true); ?>',null)" />
                                                            </td>
                                                       <?php } ?>
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="sampleReordered">
                                                            <input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" title="<?= _htmlTranslate("Please indicate if this is a reordered sample"); ?>"> <?= _translate("Sample Reordered"); ?>
                                                       </label>
                                                  </div>
                                             </div>

                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="communitySample"><?= _translate("Community Sample"); ?></label>
                                                       <select class="form-control" name="communitySample" id="communitySample" onclick="updateLocationOfSample();" title="<?= _htmlTranslate("Please choose if this is a community sample"); ?>" style="width:100%;">
                                                            <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                            <option value="yes"><?= _translate("Yes"); ?></option>
                                                            <option value="no"><?= _translate("No"); ?></option>
                                                       </select>
                                                  </div>
                                             </div>
                                             <!-- BARCODESTUFF START -->
                                             <?php if (isset($global['bar_code_printing']) && $global['bar_code_printing'] != "off") { ?>
                                                  <div class="col-xs-4 col-md-4 pull-right">
                                                       <div class="form-group">
                                                            <label for="sampleCode"><?= _translate("Print Barcode Label"); ?><span class="mandatory">*</span> </label>
                                                            <input type="checkbox" class="" id="printBarCode" name="printBarCode" checked />
                                                       </div>
                                                  </div>
                                             <?php } ?>
                                             <!-- BARCODESTUFF END -->
                                        </div>
                                        <div class="row">
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="province"><?= _translate("State/Province"); ?> <span class="mandatory">*</span></label>
                                                       <select class="form-control isRequired" name="province" id="province" title="<?= _htmlTranslate("Please choose state"); ?>" style="width:100%;" onchange="getProvinceDistricts(this);">
                                                            <?php echo $province; ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="district"><?= _translate("District/County"); ?> <span class="mandatory">*</span></label>
                                                       <select class="form-control isRequired" name="district" id="district" title="<?= _htmlTranslate("Please choose county"); ?>" style="width:100%;" onchange="getFacilities(this);">
                                                            <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="facilityId"><?= _translate("Clinic/Health Center"); ?> <span class="mandatory">*</span></label>
                                                       <select class="form-control isRequired select2" id="facilityId" name="facilityId" title="<?= _htmlTranslate("Please select clinic/health center name"); ?>" style="width:100%;" onchange="getfacilityProvinceDetails(this);fillFacilityDetails();setSampleDispatchDate();">
                                                            <?php echo $facility; ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3" style="display:none;">
                                                  <div class="form-group">
                                                       <label for="facilityCode"><?= _translate("Clinic/Health Center Code"); ?> </label>
                                                       <input type="text" class="form-control" style="width:100%;" name="facilityCode" id="facilityCode" placeholder="<?= _htmlTranslate("Clinic/Health Center Code"); ?>" title="<?= _htmlTranslate("Please enter clinic/health center code"); ?>">
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row facilityDetails" style="display:none;">
                                             <div class="col-xs-2 col-md-2 femails" style="display:none;"><strong><?= _translate("Clinic Email(s) -"); ?></strong></div>
                                             <div class="col-xs-2 col-md-2 femails facilityEmails" style="display:none;"></div>
                                             <div class="col-xs-2 col-md-2 fmobileNumbers" style="display:none;"><strong><?= _translate("Clinic Mobile No.(s) -"); ?></strong></div>
                                             <div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers" style="display:none;"></div>
                                             <div class="col-xs-2 col-md-2 fContactPerson" style="display:none;"><strong><?= _translate("Clinic Contact Person -"); ?></strong></div>
                                             <div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson" style="display:none;"></div>
                                        </div>
                                        <div class="row">
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="implementingPartner"><?= _translate("Implementing Partner"); ?></label>
                                                       <select class="form-control" name="implementingPartner" id="implementingPartner" title="<?= _htmlTranslate("Please choose implementing partner"); ?>" style="width:100%;">
                                                            <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                            <?php
                                                            foreach ($implementingPartnerList as $implementingPartner) {
                                                            ?>
                                                                 <option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
                                                            <?php } ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="fundingSource"><?= _translate("Funding Source"); ?></label>
                                                       <select class="form-control" name="fundingSource" id="fundingSource" title="<?= _htmlTranslate("Please choose implementing partner"); ?>" style="width:100%;">
                                                            <option value=""> <?= _translate("-- Select --"); ?> </option>
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
                                                  <select name="labId" id="labId" class="select2 form-control isRequired" title="<?= _htmlTranslate("Please choose lab"); ?>" onchange="autoFillFocalDetails();setSampleDispatchDate();" style="width:100%;">
                                                       <option value=""><?= _translate("-- Select --"); ?></option>
                                                       <?php foreach ($lResult as $labName) { ?>
                                                            <option data-focalperson="<?php echo $labName['contact_person']; ?>" data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>" value="<?php echo $labName['facility_id']; ?>"><?= $labName['facility_name']; ?></option>
                                                       <?php } ?>
                                                  </select>
                                             </div>

                                        </div>
                                   </div>
                              </div>
                              <div class="box box-primary">
                                   <div class="box-header with-border">
                                        <h3 class="box-title"><?= _translate("Patient Information"); ?></h3>&nbsp;&nbsp;&nbsp;
                                        <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="" placeholder="<?= _htmlTranslate("Enter art number or patient name"); ?>" title="<?= _htmlTranslate("Enter art number or patient name"); ?>" />&nbsp;&nbsp;
                                        <a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em><?= _translate("Search"); ?></a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;<?= _translate("No Patient Found"); ?></strong></span>
                                   </div>
                                   <div class="box-body">
                                        <div class="row">
                                             <div class="col-md-12 encryptPIIContainer">
                                                  <label class="col-lg-2 control-label" for="encryptPII"><?= _translate('Encrypt PII'); ?> </label>
                                                  <div class="col-lg-3">
                                                       <select name="encryptPII" id="encryptPII" class="form-control" title="<?= _translate('Encrypt Patient Identifying Information'); ?>">
                                                            <option value=""><?= _translate('--Select--'); ?></option>
                                                            <option value="no" selected='selected'><?= _translate('No'); ?></option>
                                                            <option value="yes"><?= _translate('Yes'); ?></option>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="artNo"><?= _translate("ART (TRACNET) No."); ?> <span class="mandatory">*</span></label>
                                                       <input type="text" name="artNo" id="artNo" class="form-control isRequired patientId" placeholder="<?= _htmlTranslate("Enter ART Number"); ?>" title="<?= _htmlTranslate("Enter art number"); ?>" onchange="checkPatientDetails('form_vl','patient_art_no',this,null)" />
                                                       <span class="artNoGroup" id="artNoGroup"></span>
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="dob"><?= _translate("Date of Birth"); ?> </label>
                                                       <input type="text" name="dob" id="dob" class="form-control date" placeholder="<?= _htmlTranslate("Enter DOB"); ?>" title="<?= _htmlTranslate("Enter dob"); ?>" onchange="getAge();checkARTInitiationDate();" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="ageInYears"><?= _translate("If DOB unknown, Age in Years"); ?> </label>
                                                       <input type="text" name="ageInYears" id="ageInYears" class="form-control forceNumeric" maxlength="3" placeholder="<?= _htmlTranslate("Age in Years"); ?>" title="<?= _htmlTranslate("Enter age in years"); ?>" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="ageInMonths"><?= _translate("If Age < 1, Age in Months"); ?> </label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="<?= _htmlTranslate("Age in Month"); ?>" title="<?= _htmlTranslate("Enter age in months"); ?>" />
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row">
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="patientFirstName"><?= _translate("Patient Name (First Name, Last Name)"); ?> <span class="mandatory">*</span></label>
                                                       <input type="text" name="patientFirstName" id="patientFirstName" class="form-control isRequired" placeholder="<?= _htmlTranslate("Enter Patient Name"); ?>" title="<?= _htmlTranslate("Enter patient name"); ?>" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="gender"><?= _translate("Sex"); ?> <span class="mandatory">*</span></label><br>
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="isRequired" id="genderMale" name="gender" value="male" title="<?= _htmlTranslate("Please choose sex"); ?>"><?= _translate("Male"); ?>
                                                       </label>
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="genderFemale" name="gender" value="female" title="<?= _htmlTranslate("Please choose sex"); ?>"><?= _translate("Female"); ?>
                                                       </label>
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="genderUnreported" name="gender" value="unreported" title="<?= _htmlTranslate("Please choose sex"); ?>"><?= _translate("Unreported"); ?>
                                                       </label>
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="receiveSms"><?= _translate("Patient consent to receive SMS?"); ?></label><br>
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="receivesmsYes" name="receiveSms" value="yes" title="<?= _htmlTranslate("Patient consent to receive SMS"); ?>" onclick="checkPatientReceivesms(this.value);"> <?= _translate("Yes"); ?>
                                                       </label>
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="receivesmsNo" name="receiveSms" value="no" title="<?= _htmlTranslate("Patient consent to receive SMS"); ?>" onclick="checkPatientReceivesms(this.value);"> <?= _translate("No"); ?>
                                                       </label>
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="patientPhoneNumber"><?= _translate("Phone Number"); ?></label>
                                                       <input type="text" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control phone-number" maxlength="15" placeholder="<?= _htmlTranslate("Enter Phone Number"); ?>" title="<?= _htmlTranslate("Enter phone number"); ?>" />
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row">
                                             <div class="col-xs-3 col-md-3 femaleSection">
                                                  <div class="form-group">
                                                       <label for="patientPregnant"><?= _translate("Is Patient Pregnant?"); ?> </label><br>
                                                       <label class="radio-inline">
                                                            <input type="radio" class="" id="pregYes" name="patientPregnant" value="yes" title="<?= _htmlTranslate("Is Patient Pregnant?"); ?>"> <?= _translate("Yes"); ?>
                                                       </label>
                                                       <label class="radio-inline">
                                                            <input type="radio" class="" id="pregNo" name="patientPregnant" value="no"> <?= _translate("No"); ?>
                                                       </label>
                                                  </div>
                                             </div>

                                             <div class="col-xs-3 col-md-3 femaleSection">
                                                  <div class="form-group">
                                                       <label for="breastfeeding"><?= _translate("Is Patient Breastfeeding?"); ?> </label><br>
                                                       <label class="radio-inline">
                                                            <input type="radio" class="" id="breastfeedingYes" name="breastfeeding" value="yes" title="<?= _htmlTranslate("Is Patient Breastfeeding?"); ?>"> <?= _translate("Yes"); ?>
                                                       </label>
                                                       <label class="radio-inline">
                                                            <input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no"> <?= _translate("No"); ?>
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
                                                       <div class="form-group">
                                                            <label for=""><?= _translate("Date of Sample Collection"); ?> <span class="mandatory">*</span></label>
                                                            <input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="<?= _htmlTranslate("Sample Collection Date"); ?>" title="<?= _htmlTranslate("Please select sample collection date"); ?>" onchange="checkSampleTestingDate();generateSampleCode();setSampleDispatchDate(); checkCollectionDate(this.value);">
                                                            <span class="expiredCollectionDate" style="color:red; display:none;"></span>
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for=""><?= _translate("Sample Dispatched On"); ?> <span class="mandatory">*</span></label>
                                                            <input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="<?= _htmlTranslate("Sample Dispatched On"); ?>" title="<?= _htmlTranslate("Please select sample dispatched on"); ?>">
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="specimenType"><?= _translate("Sample Type"); ?> <span class="mandatory">*</span></label>
                                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="<?= _htmlTranslate("Please choose sample type"); ?>">
                                                                 <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                                 <?php foreach ($sResult as $name) { ?>
                                                                      <option value="<?php echo $name['sample_id']; ?>"><?= $name['sample_name']; ?></option>
                                                                 <?php } ?>
                                                            </select>
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="locationOfSampleCollection"><?= _translate("Location Of Sample Collection"); ?></label>
                                                            <select name="locationOfSampleCollection" id="locationOfSampleCollection" onclick="updateLocationOfSample();" class="form-control" title="<?= _htmlTranslate("Please choose location of sample collection"); ?>">
                                                                 <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                                 <option value="facility"><?= _translate("Facility"); ?></option>
                                                                 <option value="community"><?= _translate("Community"); ?></option>
                                                                 <option value="unreported"><?= _translate("Unreported"); ?></option>
                                                            </select>
                                                       </div>
                                                  </div>

                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for=""><?= _translate("Date Sample Received at Hub (PHL)"); ?> <span class="mandatory">*</span></label>
                                                            <input type="text" class="form-control dateTime" id="sampleReceivedAtHubOn" name="sampleReceivedAtHubOn" placeholder="<?= _htmlTranslate("Sample Received at HUB Date"); ?>" title="<?= _htmlTranslate("Please select sample received at Hub date"); ?>" />
                                                       </div>
                                                  </div>

                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="sampleReceivedDate"><?= _translate("Date Sample Received at Testing Lab"); ?> <span class="mandatory">*</span></label>
                                                            <input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _htmlTranslate("Sample Received at LAB Date"); ?>" title="<?= _htmlTranslate("Please select sample received at Lab date"); ?>" />
                                                       </div>
                                                  </div>
                                             </div>

                                        </div>
                                        <div class="box box-primary">
                                             <div class="box-header with-border">
                                                  <h3 class="box-title"><?= _translate("Treatment Information"); ?></h3>
                                             </div>
                                             <div class="box-body">
                                                  <div class="row">
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group">
                                                                 <label for=""><?= _translate("Date of Treatment Initiation"); ?></label>
                                                                 <input type="text" class="form-control date" name="dateOfArtInitiation" id="dateOfArtInitiation" placeholder="<?= _htmlTranslate("Date Of Treatment Initiated"); ?>" title="<?= _htmlTranslate("Date Of treatment initiated"); ?>" style="width:100%;" onchange="checkARTInitiationDate();">
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group">
                                                                 <label for="artRegimen"><?= _translate("Current Regimen"); ?></label>
                                                                 <select class="form-control" id="artRegimen" name="artRegimen" title="<?= _htmlTranslate("Please choose ART Regimen"); ?>" style="width:100%;" onchange="checkARTRegimenValue();">
                                                                      <option value=""><?= _translate("-- Select --"); ?></option>
                                                                      <?php foreach ($artRegimenResult as $heading) { ?>
                                                                           <optgroup label="<?= $heading['headings']; ?>">
                                                                                <?php
                                                                                foreach ($aResult as $regimen) {
                                                                                     if ($heading['headings'] == $regimen['headings']) {
                                                                                ?>
                                                                                          <option value="<?php echo $regimen['art_code']; ?>"><?php echo $regimen['art_code']; ?></option>
                                                                                <?php
                                                                                     }
                                                                                }
                                                                                ?>
                                                                           </optgroup>
                                                                      <?php }  ?>
                                                                      <option value="other"><?= _translate("Other"); ?></option>

                                                                 </select>
                                                                 <input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="<?= _htmlTranslate("ART Regimen"); ?>" title="<?= _htmlTranslate("Please enter art regimen"); ?>" style="width:100%;display:none;margin-top:2px;">
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group">
                                                                 <label for=""><?= _translate("Date of Initiation of Current Regimen"); ?> </label>
                                                                 <input type="text" class="form-control date" style="width:100%;" name="regimenInitiatedOn" id="regimenInitiatedOn" placeholder="<?= _htmlTranslate("Current Regimen Initiated On"); ?>" title="<?= _htmlTranslate("Please enter current regimen initiated on"); ?>">
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group">
                                                                 <label for="arvAdherence"><?= _translate("ARV Adherence"); ?> </label>
                                                                 <select name="arvAdherence" id="arvAdherence" class="form-control" title="<?= _htmlTranslate("Please choose adherence"); ?>">
                                                                      <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                                      <option value="good"><?= _translate("Good >= 95%"); ?></option>
                                                                      <option value="fair"><?= _translate("Fair (85-94%)"); ?></option>
                                                                      <option value="poor"><?= _translate("Poor < 85%"); ?></option>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row ">
                                                       <div class="col-xs-3 col-md-3" style="display:none;">
                                                            <div class="form-group">
                                                                 <label for=""><?= _translate("How long has this patient been on treatment ?"); ?> </label>
                                                                 <input type="text" class="form-control" id="treatPeriod" name="treatPeriod" placeholder="<?= _htmlTranslate("Enter Treatment Period"); ?>" title="<?= _htmlTranslate("Please enter how long has this patient been on treatment"); ?>" />
                                                            </div>
                                                       </div>
                                                  </div>
                                             </div>
                                             <div class="box box-primary">
                                                  <div class="box-header with-border">
                                                       <h3 class="box-title"><?= _translate("Indication for Viral Load Testing"); ?> <span class="mandatory">*</span></h3><small> <?= _translate("(Please choose one):(To be completed by clinician)"); ?></small>
                                                  </div>
                                                  <div class="box-body">
                                                       <div class="row">
                                                            <div class="col-md-6">
                                                                 <div class="form-group">
                                                                      <div class="col-lg-12">
                                                                           <label class="radio-inline">
                                                                                <input type="radio" class="isRequired" id="rmTesting" name="reasonForVLTesting" value="routine" title="<?= _htmlTranslate("Please select indication/reason for testing"); ?>" onclick="showTesting('rmTesting');">
                                                                                <strong><?= _translate("Routine Monitoring"); ?></strong>
                                                                           </label>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row rmTesting hideTestData" style="display:none;">
                                                            <div class="col-md-6">
                                                                 <label class="col-lg-5 control-label"><?= _translate("Date of Last VL Test"); ?></label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control date viralTestData" id="rmTestingLastVLDate" name="rmTestingLastVLDate" placeholder="<?= _htmlTranslate("Select Last VL Date"); ?>" title="<?= _htmlTranslate("Please select Last VL Date"); ?>" />
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                 <label for="rmTestingVlValue" class="col-lg-3 control-label"><?= _translate("VL Result"); ?></label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control forceNumeric viralTestData" id="rmTestingVlValue" name="rmTestingVlValue" placeholder="<?= _htmlTranslate("Enter VL Result"); ?>" title="<?= _htmlTranslate("Please enter VL Result"); ?>" />
                                                                      (<?= _translate("copies/mL"); ?>)
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row">
                                                            <div class="col-md-8">
                                                                 <div class="form-group">
                                                                      <div class="col-lg-12">
                                                                           <label class="radio-inline">
                                                                                <input type="radio" class="isRequired" id="repeatTesting" name="reasonForVLTesting" value="failure" title="<?= _htmlTranslate("Repeat VL test after suspected treatment failure adherence counseling (Reason for testing)"); ?>" onclick="showTesting('repeatTesting');">
                                                                                <strong><?= _translate("Repeat VL test after suspected treatment failure adherence counselling"); ?> </strong>
                                                                           </label>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row repeatTesting hideTestData" style="display:none;">
                                                            <div class="col-md-6">
                                                                 <label class="col-lg-5 control-label"><?= _translate("Date of Last VL Test"); ?></label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control date viralTestData" id="repeatTestingLastVLDate" name="repeatTestingLastVLDate" placeholder="<?= _htmlTranslate("Select Last VL Date"); ?>" title="<?= _htmlTranslate("Please select Last VL Date"); ?>" />
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                 <label for="repeatTestingVlValue" class="col-lg-3 control-label"><?= _translate("VL Result"); ?></label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control forceNumeric viralTestData" id="repeatTestingVlValue" name="repeatTestingVlValue" placeholder="<?= _htmlTranslate("Enter VL Result"); ?>" title="<?= _htmlTranslate("Please enter VL Result"); ?>" />
                                                                      (<?= _translate("copies/mL"); ?>)
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row">
                                                            <div class="col-md-6">
                                                                 <div class="form-group">
                                                                      <div class="col-lg-12">
                                                                           <label class="radio-inline">
                                                                                <input type="radio" class="isRequired" id="suspendTreatment" name="reasonForVLTesting" value="suspect" title="<?= _htmlTranslate("Suspect Treatment Failure (Reason for testing)"); ?>" onclick="showTesting('suspendTreatment');">
                                                                                <strong><?= _translate("Suspected Treatment Failure"); ?></strong>
                                                                           </label>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row suspendTreatment hideTestData" style="display: none;">
                                                            <div class="col-md-6">
                                                                 <label class="col-lg-5 control-label"><?= _translate("Date of Last VL Test"); ?></label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control date viralTestData" id="suspendTreatmentLastVLDate" name="suspendTreatmentLastVLDate" placeholder="<?= _htmlTranslate("Select Last VL Date"); ?>" title="<?= _htmlTranslate("Please select Last VL Date"); ?>" />
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                 <label for="suspendTreatmentVlValue" class="col-lg-3 control-label"><?= _translate("VL Result"); ?></label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control forceNumeric viralTestData" id="suspendTreatmentVlValue" name="suspendTreatmentVlValue" placeholder="<?= _htmlTranslate("Enter VL Result"); ?>" title="<?= _htmlTranslate("Please enter VL Result"); ?>" />
                                                                      (<?= _translate("copies/mL"); ?>)
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <p>&nbsp;</p>
                                                       <div class="row">
                                                            <div class="col-md-4">
                                                                 <label for="reqClinician" class="col-lg-5 control-label"><?= _translate("Requesting Clinician"); ?></label>
                                                                 <div class="col-lg-7">
                                                                      <select class="form-control ajax-select2" id="reqClinician" name="reqClinician" placeholder="<?= _htmlTranslate("Requesting Clinician"); ?>" title="<?= _htmlTranslate("Please enter request clinician"); ?>"></select>
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                 <label for="reqClinicianPhoneNumber" class="col-lg-5 control-label"><?= _translate("Phone Number"); ?></label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control phone-number" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" maxlength="15" placeholder="<?= _htmlTranslate("Phone Number"); ?>" title="<?= _htmlTranslate("Please enter request clinician phone number"); ?>" />
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                 <label class="col-lg-5 control-label" for="requestDate"><?= _translate("Request Date"); ?> </label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control date" id="requestDate" name="requestDate" placeholder="<?= _htmlTranslate("Request Date"); ?>" title="<?= _htmlTranslate("Please select request date"); ?>" />
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row" style="display:none;">
                                                            <div class="col-md-4">
                                                                 <label class="col-lg-5 control-label" for="emailHf"><?= _translate("Email for HF"); ?> </label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control isEmail" id="emailHf" name="emailHf" placeholder="<?= _htmlTranslate("Email for HF"); ?>" title="<?= _htmlTranslate("Please enter email for hf"); ?>" />
                                                                 </div>
                                                            </div>
                                                       </div>
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
                                                                           <select class="form-control ajax-select2" id="vlFocalPerson" name="vlFocalPerson" placeholder="<?= _htmlTranslate("VL Focal Person"); ?>" title="<?= _htmlTranslate("Please enter focal person name"); ?>"></select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label labels"><?= _translate("VL Focal Person Phone Number"); ?></label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control phone-number" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" maxlength="15" placeholder="<?= _htmlTranslate("Phone Number"); ?>" title="<?= _htmlTranslate("Please enter focal person phone number"); ?>" />
                                                                      </div>
                                                                 </div>
                                                            </div>

                                                            <div class="row">
                                                                 <div class="col-md-6">
                                                                      <label for="testingPlatform" class="col-lg-5 control-label labels"><?= _translate("VL Testing Platform"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="testingPlatform" id="testingPlatform" class="form-control result-optional" title="<?= _htmlTranslate("Please choose VL Testing Platform"); ?>" onchange="hivDetectionChange();">
                                                                                <option value=""><?= _translate("-- Select --"); ?></option>
                                                                                <?php foreach ($importResult as $mName) { ?>
                                                                                     <option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['instrument_id']; ?>"><?php echo $mName['machine_name']; ?></option>
                                                                                <?php } ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="isSampleRejected"><?= _translate("Is Sample Rejected?"); ?></label>
                                                                      <div class="col-lg-7">
                                                                           <select name="isSampleRejected" id="isSampleRejected" class="form-control" title="<?= _htmlTranslate("Please check if sample is rejected or not"); ?>">
                                                                                <option value=""><?= _translate("-- Select --"); ?></option>
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
                                                                           <select name="rejectionReason" id="rejectionReason" class="form-control" title="<?= _htmlTranslate("Please choose reason"); ?>" onchange="checkRejectionReason();">
                                                                                <option value=""><?= _translate("-- Select --"); ?></option>
                                                                                <?php foreach ($rejectionTypeResult as $type) { ?>
                                                                                     <optgroup label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
                                                                                          <?php foreach ($rejectionResult as $reject) {
                                                                                               if ($type['rejection_type'] == $reject['rejection_type']) {
                                                                                          ?>
                                                                                                    <option value="<?php echo $reject['rejection_reason_id']; ?>"><?= $reject['rejection_reason_name']; ?></option>
                                                                                          <?php }
                                                                                          } ?>
                                                                                     </optgroup>
                                                                                <?php }  ?>
                                                                                <option value="other"><?= _translate("Other (Please Specify)"); ?> </option>

                                                                           </select>
                                                                           <input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="<?= _htmlTranslate("Rejection Reason"); ?>" title="<?= _htmlTranslate("Please enter rejection reason"); ?>" style="width:100%;display:none;margin-top:2px;">
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6 rejectionReason" style="display:none;">
                                                                      <label class="col-lg-5 control-label labels" for="rejectionDate"><?= _translate("Rejection Date"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="<?= _htmlTranslate("Select Rejection Date"); ?>" title="<?= _htmlTranslate("Please select rejection date"); ?>" />
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="sampleTestingDateAtLab"><?= _translate("Sample Testing Date"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control result-fields dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="<?= _htmlTranslate("Sample Testing Date"); ?>" title="<?= _htmlTranslate("Please select sample testing date"); ?>" onchange="checkSampleTestingDate();" disabled />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6 vlResult">
                                                                      <label class="col-lg-5 control-label  labels" for="vlResult"><?= _translate("Viral Load Result (copies/mL)"); ?> </label>
                                                                      <div class="col-lg-7 resultInputContainer">
                                                                           <input list="possibleVlResults" autocomplete="off" class="form-control result-fields labSection" id="vlResult" name="vlResult" placeholder="<?= _htmlTranslate("Select or Type VL Result"); ?>" title="<?= _htmlTranslate("Please enter viral load result"); ?>" onchange="calculateLogValue(this)" disabled>
                                                                           <datalist id="possibleVlResults">

                                                                           </datalist>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">

                                                                 <div class="vlLog col-md-6">
                                                                      <label class="col-lg-5 control-label  labels" for="vlLog"><?= _translate("Viral Load (Log)"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control" id="vlLog" name="vlLog" placeholder="<?= _htmlTranslate("Viral Load (Log)"); ?>" title="<?= _htmlTranslate("Please enter viral load result in Log"); ?>" style="width:100%;" onchange="calculateLogValue(this);" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label" for="reviewedBy"><?= _translate("Reviewed By"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="reviewedBy" id="reviewedBy" class="select2 form-control labels" title="<?= _htmlTranslate("Please choose reviewed by"); ?>" style="width: 100%;">
                                                                                <?= $general->generateSelectOptions($userInfo, null, _translate("-- Select --")); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-6 hivDetection" style="display: none;">
                                                                      <label for="hivDetection" class="col-lg-5 control-label labels"><?= _translate("HIV Detection"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="hivDetection" id="hivDetection" class="form-control hivDetection" title="<?= _htmlTranslate("Please choose HIV detection"); ?>">
                                                                                <option value=""><?= _translate("-- Select --"); ?></option>
                                                                                <option value="HIV-1 Detected"><?= _translate("HIV-1 Detected"); ?></option>
                                                                                <option value="HIV-1 Not Detected"><?= _translate("HIV-1 Not Detected"); ?></option>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <?php if (count($reasonForFailure) > 0) { ?>
                                                                      <div class="col-md-6 reasonForFailure" style="display: none;">
                                                                           <label class="col-lg-5 control-label" for="reasonForFailure"><?= _translate("Reason for Failure"); ?> <span class="mandatory">*</span> </label>
                                                                           <div class="col-lg-7">
                                                                                <select name="reasonForFailure" id="reasonForFailure" class="form-control" title="<?= _htmlTranslate("Please choose reason for failure"); ?>" style="width: 100%;">
                                                                                     <?= $general->generateSelectOptions($reasonForFailure, null, _translate("-- Select --")); ?>
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
                                                                           <input type="text" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="<?= _htmlTranslate("Reviewed on"); ?>" title="<?= _htmlTranslate("Please enter the Reviewed on"); ?>" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="testedBy"><?= _translate("Tested By"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="testedBy" id="testedBy" class="select2 form-control" title="<?= _htmlTranslate("Please choose approved by"); ?>">
                                                                                <?= $general->generateSelectOptions($userInfo, null, _translate("-- Select --")); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">

                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="approvedBy"><?= _translate("Approved By"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="approvedBy" id="approvedBy" class="select2 form-control" title="<?= _htmlTranslate("Please choose approved by"); ?>">
                                                                                <?= $general->generateSelectOptions($userInfo, null, _translate("-- Select --")); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="approvedOn"><?= _translate("Approved On"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" value="" class="form-control dateTime" id="approvedOnDateTime" title="<?= _htmlTranslate("Please choose Approved On"); ?>" name="approvedOnDateTime" placeholder="<?= _translate("Please enter date"); ?>" style="width:100%;" />
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">

                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="resultDispatchedOn"><?= _translate("Date Results Dispatched"); ?></label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="<?= _htmlTranslate("Result Dispatch Date"); ?>" title="<?= _htmlTranslate("Please select result dispatched date"); ?>" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="labComments"><?= _translate("Lab Tech. Comments"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <textarea class="form-control" name="labComments" id="labComments" placeholder="<?= _htmlTranslate("Lab comments"); ?>" title="<?= _htmlTranslate("Please enter LabComments"); ?>"></textarea>
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
                                             <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
                                             <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
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
                         <input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />

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
                    $('#sampleReceivedAtHubOn').val($('#sampleCollectionDate').val());
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
               placeholder: "<?= _jsTranslate("Select Testing Lab"); ?>"
          });
          $('#facilityId').select2({
               width: '100%',
               placeholder: "<?= _jsTranslate("Select Clinic/Health Center"); ?>"
          });
          $('#reviewedBy').select2({
               width: '100%',
               placeholder: "<?= _jsTranslate("Select Reviewed By"); ?>"
          });
          $('#testedBy').select2({
               width: '100%',
               placeholder: "<?= _jsTranslate("Select Tested By"); ?>"
          });

          $('#approvedBy').select2({
               width: '100%',
               placeholder: "<?= _jsTranslate("Select Approved By"); ?>"
          });
          $('#facilityId').select2({
               placeholder: "<?= _jsTranslate("Select Clinic/Health Center"); ?>"
          });
          $('#district').select2({
               placeholder: "<?= _jsTranslate("District"); ?>"
          });
          $('#province').select2({
               placeholder: "<?= _jsTranslate("Province"); ?>"
          });
          $('#artRegimen').select2({
               placeholder: "<?= _jsTranslate("Select ART Regimen"); ?>"
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

          $("#reqClinician").select2({
               placeholder: "<?= _jsTranslate("Enter Requesting Clinician Name"); ?>",
               minimumInputLength: 0,
               width: '100%',
               allowClear: true,
               id: function(bond) {
                    return bond._id;
               },
               ajax: {
                    placeholder: "<?= _jsTranslate("Type one or more character to search"); ?>",
                    url: "/includes/get-data-list.php",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                         return {
                              fieldName: 'request_clinician_name',
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

          $("#reqClinician").change(function() {
               $.blockUI();
               var search = $(this).val();
               if ($.trim(search) != '') {
                    $.get("/includes/get-data-list.php", {
                              fieldName: 'request_clinician_name',
                              tableName: 'form_vl',
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
               placeholder: "<?= _jsTranslate("Enter Request Focal name"); ?>",
               minimumInputLength: 0,
               width: '100%',
               allowClear: true,
               id: function(bond) {
                    return bond._id;
               },
               ajax: {
                    placeholder: "<?= _jsTranslate("Type one or more character to search"); ?>",
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

     function showTesting(chosenClass) {
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
                                   $("#facilityId").html("<option data-code='' data-emails='' data-mobile-nos='' data-contact-person='' value=''> <?= _jsTranslate("-- Select --"); ?> </option>");
                                   $("#facilityCode").val('');
                                   $(".facilityDetails").hide();
                                   $(".facilityEmails").html('');
                                   $(".facilityMobileNumbers").html('');
                                   $(".facilityContactPerson").html('');
                              }
                         });
               }

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
          var format = '<?php echo $arr['sample_code']; ?>';
          var sCodeLentgh = $("#sampleCode").val();
          var minLength = '<?php echo $arr['min_length']; ?>';
          if ((format == 'alphanumeric' || format == 'numeric') && sCodeLentgh.length < minLength && sCodeLentgh != '') {
               alert("<?= _jsTranslate("Sample ID length must be a minimum length of"); ?> " + minLength + " <?= _jsTranslate("characters"); ?>");
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
               <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
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
          var format = '<?php echo $arr['sample_code']; ?>';
          var sCodeLentgh = $("#sampleCode").val();
          var minLength = '<?php echo $arr['min_length']; ?>';
          if ((format == 'alphanumeric' || format == 'numeric') && sCodeLentgh.length < minLength && sCodeLentgh != '') {
               alert("<?= _jsTranslate("Sample ID length must be a minimum length of"); ?> " + minLength + " <?= _jsTranslate("characters"); ?>");
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
               <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
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
                         $("#vlResult").val(Math.round(Math.pow(10, logValue) * 100) / 100);
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
</script>