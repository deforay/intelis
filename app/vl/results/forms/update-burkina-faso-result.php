<?php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$globalConfig = $general->getGlobalConfig();

$lResult = $facilitiesService->getTestingLabs('vl', byPassFacilityMap: true, allColumns: true);

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
if (!empty($vlQueryInfo['remote_sample']) && $vlQueryInfo['remote_sample'] == 'yes') {
     $sampleCode = 'remote_sample_code';
} else {
     $sampleCode = 'sample_code';
}

$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);

$facility = $general->generateSelectOptions($healthFacilities, $vlQueryInfo['facility_id'], '-- Select --');


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

//facility details
if (isset($vlQueryInfo['facility_id']) && $vlQueryInfo['facility_id'] > 0) {
     $facilityQuery = "SELECT * FROM facility_details WHERE facility_id= ? AND status='active'";
     $facilityResult = $db->rawQuery($facilityQuery, [$vlQueryInfo['facility_id']]);
}

$facilityCode = $facilityResult[0]['facility_code'] ?? '';
$facilityMobileNumbers = $facilityResult[0]['facility_mobile_numbers'] ?? '';
$contactPerson = $facilityResult[0]['contact_person'] ?? '';
$facilityEmails = $facilityResult[0]['facility_emails'] ?? '';
$facilityState = $facilityResult[0]['facility_state'] ?? '';
$facilityDistrict = $facilityResult[0]['facility_district'] ?? '';

$user = '';
if ($contactPerson != '') {
     $contactUser = $usersService->getUserByID($contactPerson);
     if (!empty($contactUser)) {
          $user = $contactUser['user_name'];
     }
}

//set reason for changes history
$rch = '';
$allChange = [];
if (isset($vlQueryInfo['reason_for_result_changes']) && $vlQueryInfo['reason_for_result_changes'] != '' && $vlQueryInfo['reason_for_result_changes'] != null) {
     $allChange = json_decode((string) $vlQueryInfo['reason_for_result_changes'], true);
     if (!empty($allChange)) {
          $rch .= '<h4>Result Changes History</h4>';
          $rch .= '<table style="width:100%;">';
          $rch .= '<thead><tr style="border-bottom:2px solid #d3d3d3;"><th style="width:20%;">USER</th><th style="width:60%;">MESSAGE</th><th style="width:20%;text-align:center;">DATE</th></tr></thead>';
          $rch .= '<tbody>';
          $allChange = array_reverse($allChange);
          foreach ($allChange as $change) {
               $usrResult = $usersService->getUserByID($change['usr']);
               $name = $usrResult['user_name'] ?? '';
               $changedDate = DateUtility::humanReadableDateFormat($change['dtime'] ?? '', true);
               $rch .= '<tr><td>' . $name . '</td><td>' . ($change['msg']) . '</td><td style="text-align:center;">' . $changedDate . '</td></tr>';
          }
          $rch .= '</tbody>';
          $rch .= '</table>';
     }
}

$isGeneXpert = !empty($vlQueryInfo['vl_test_platform']) && (strcasecmp((string) $vlQueryInfo['vl_test_platform'], "genexpert") === 0);

if ($isGeneXpert && !empty($vlQueryInfo['result_value_hiv_detection']) && !empty($vlQueryInfo['result'])) {
     $vlQueryInfo['result'] = trim(str_ireplace((string) $vlQueryInfo['result_value_hiv_detection'], "", (string) $vlQueryInfo['result']));
} elseif ($isGeneXpert && !empty($vlQueryInfo['result'])) {

     $vlQueryInfo['result_value_hiv_detection'] = null;

     $hivDetectedStringsToSearch = [
          'HIV-1 Detected',
          'HIV 1 Detected',
          'HIV1 Detected',
          'HIV 1Detected',
          'HIV1Detected',
          'HIV Detected',
          'HIVDetected',
     ];

     $hivNotDetectedStringsToSearch = [
          'HIV-1 Not Detected',
          'HIV-1 NotDetected',
          'HIV-1Not Detected',
          'HIV 1 Not Detected',
          'HIV1 Not Detected',
          'HIV 1Not Detected',
          'HIV1Not Detected',
          'HIV1NotDetected',
          'HIV1 NotDetected',
          'HIV 1NotDetected',
          'HIV Not Detected',
          'HIVNotDetected',
     ];

     $detectedMatching = $general->checkIfStringExists($vlQueryInfo['result'] ?? '', $hivDetectedStringsToSearch);
     if ($detectedMatching !== false) {
          $vlQueryInfo['result'] = trim(str_ireplace((string) $detectedMatching, "", (string) $vlQueryInfo['result']));
          $vlQueryInfo['result_value_hiv_detection'] = "HIV-1 Detected";
     } else {
          $notDetectedMatching = $general->checkIfStringExists($vlQueryInfo['result'] ?? '', $hivNotDetectedStringsToSearch);
          if ($notDetectedMatching !== false) {
               $vlQueryInfo['result'] = trim(str_ireplace((string) $notDetectedMatching, "", (string) $vlQueryInfo['result']));
               $vlQueryInfo['result_value_hiv_detection'] = "HIV-1 Not Detected";
          }
     }
}
$disable = "disabled = 'disabled'";
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
                                                  <input type="text" class="form-control isRequired <?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" <?php echo $maxLength; ?> placeholder="Enter Sample ID" readonly="readonly" title="<?= _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>" value="<?php echo $vlQueryInfo[$sampleCode]; ?>" style="width:100%;" <?php echo $disable; ?> onchange="checkSampleNameValidation('form_vl','<?php echo $sampleCode; ?>',this.id,'<?php echo "vl_sample_id##" . $vlQueryInfo["vl_sample_id"]; ?>','This sample number already exists.Try another number',null)" />
                                                  <input type="hidden" name="sampleCodeCol" value="<?= $vlQueryInfo['sample_code'] ?>" style="width:100%;">
                                             </div>
                                        </div>
                                        <div class="col-xs-4 col-md-4">
                                             <div class="form-group" style=" width: 100%; ">
                                                  <label for="sampleReordered">
                                                       <input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" <?php echo (trim((string) $vlQueryInfo['sample_reordered']) === 'yes') ? 'checked="checked"' : '' ?> <?php echo $disable; ?> title="Please indicate if this is a reordered sample"> <?= _translate("Sample Reordered") ?>
                                                  </label>
                                             </div>
                                        </div>
                                   </div>
                                   <div class="row">
                                        <div class="col-xs-4 col-md-4">
                                             <div class="form-group" style=" width: 100%; ">
                                                  <label for="province"><?= _translate("State/Province"); ?> <span class="mandatory">*</span></label>
                                                  <select class="form-control isRequired" name="province" <?php echo $disable; ?> id="province" title="Please choose state" style="width:100%;" onchange="getProvinceDistricts(this);">
                                                       <?php echo $province; ?>
                                                  </select>
                                             </div>
                                        </div>
                                        <div class="col-xs-4 col-md-4">
                                             <div class="form-group" style=" width: 100%; ">
                                                  <label for="district"><?= _translate("District/County"); ?> <span class="mandatory">*</span></label>
                                                  <select class="form-control isRequired" name="district" id="district" <?php echo $disable; ?> title="Please choose county" style="width:100%;" onchange="getFacilities(this);">
                                                       <option value=""> -- <?= _translate("Select"); ?> -- </option>
                                                  </select>
                                             </div>
                                        </div>
                                        <div class="col-xs-4 col-md-4">
                                             <div class="form-group" style=" width: 100%; ">
                                                  <label for="facilityId"><?= _translate("Clinic/Health Center"); ?> <span class="mandatory">*</span></label>
                                                  <select class="form-control isRequired select2" <?php echo $disable; ?> id="facilityId" name="facilityId" title="Please select clinic/health center name" style="width:100%;" onchange="getfacilityProvinceDetails(this);fillFacilityDetails();setSampleDispatchDate();">
                                                       <?php echo $facility; ?>
                                                  </select>
                                             </div>
                                        </div>
                                        <div class="col-xs-3 col-md-3" style="display:none;">
                                             <div class="form-group" style=" width: 100%; ">
                                                  <label for="facilityCode"><?= _translate("Clinic/Health Center Code"); ?> </label>
                                                  <input type="text" class="form-control" <?php echo $disable; ?> style="width:100%;" name="facilityCode" id="facilityCode" placeholder="Clinic/Health Center Code" title="Please enter clinic/health center code" value="<?php echo $facilityResult[0]['facility_code']; ?>">
                                             </div>
                                        </div>
                                   </div>
                                   <div class="row facilityDetails" style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '' || trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '' || trim((string) $facilityResult[0]['contact_person']) !== '') ? '' : 'none'; ?>;">
                                        <div class="col-xs-2 col-md-2 femails" style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '') ? '' : 'none'; ?>;"><strong><?= _translate("Clinic Email(s)"); ?></strong></div>
                                        <div class="col-xs-2 col-md-2 femails facilityEmails" style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '') ? '' : 'none'; ?>;"><?php echo $facilityResult[0]['facility_emails']; ?></div>
                                        <div class="col-xs-2 col-md-2 fmobileNumbers" style="display:<?php echo (trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '') ? '' : 'none'; ?>;"><strong><?= _translate("Clinic Mobile No.(s)"); ?></strong></div>
                                        <div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers" style="display:<?php echo (trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '') ? '' : 'none'; ?>;"><?php echo $facilityResult[0]['facility_mobile_numbers']; ?></div>
                                        <div class="col-xs-2 col-md-2 fContactPerson" style="display:<?php echo (trim((string) $facilityResult[0]['contact_person']) !== '') ? '' : 'none'; ?>;"><strong><?= _translate("Clinic Contact Person"); ?> -</strong></div>
                                        <div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson" style="display:<?php echo (trim((string) $user) !== '') ? '' : 'none'; ?>;"><?php echo ($user); ?></div>
                                   </div>
                                   <div class="row">
                                        <div class="col-xs-4 col-md-4">
                                             <div class="form-group" style=" width: 100%; ">
                                                  <label for="implementingPartner"><?= _translate("Implementing Partner"); ?></label>
                                                  <select class="form-control" name="implementingPartner" <?php echo $disable; ?> id="implementingPartner" title="Please choose implementing partner" style="width:100%;">
                                                       <option value=""> -- <?= _translate("Select"); ?> -- </option>
                                                       <?php foreach ($implementingPartnerList as $implementingPartner) { ?>
                                                            <option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>" <?php echo ($implementingPartner['i_partner_id'] == $vlQueryInfo['implementing_partner']) ? 'selected="selected"' : ''; ?>><?= $implementingPartner['i_partner_name']; ?></option>
                                                       <?php } ?>
                                                  </select>
                                             </div>
                                        </div>
                                        <div class="col-xs-4 col-md-4">
                                             <div class="form-group" style=" width: 100%; ">
                                                  <label for="fundingSource"><?= _translate("Funding Source"); ?></label>
                                                  <select class="form-control" name="fundingSource" id="fundingSource" <?php echo $disable; ?> title="Please choose implementing partner" style="width:100%;">
                                                       <option value=""> -- <?= _translate("Select"); ?> -- </option>
                                                       <?php foreach ($fundingSourceList as $fundingSource) { ?>
                                                            <option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $vlQueryInfo['funding_source']) ? 'selected="selected"' : ''; ?>><?= $fundingSource['funding_source_name']; ?></option>
                                                       <?php } ?>
                                                  </select>
                                             </div>
                                        </div>

                                        <div class="col-md-4 col-md-4">
                                             <label for="labId"><?= _translate("Testing Lab"); ?> <span class="mandatory">*</span></label>
                                             <select name="labId" id="labId" class="select2 form-control isRequired" title="Please choose lab" <?php echo $disable; ?> onchange="autoFillFocalDetails();setSampleDispatchDate();" style="width:100%;">
                                                  <option value="">-- Select --</option>
                                                  <?php foreach ($lResult as $labName) { ?>
                                                       <option data-focalperson="<?php echo $labName['contact_person']; ?>" data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>" value="<?php echo $labName['facility_id']; ?>" <?php echo (isset($vlQueryInfo['lab_id']) && $vlQueryInfo['lab_id'] == $labName['facility_id']) ? 'selected="selected"' : ''; ?>><?= $labName['facility_name']; ?></option>
                                                  <?php } ?>
                                             </select>
                                        </div>

                                   </div>
                              </div>
                         </div>
                         <div class="box box-primary">
                              <div class="box-header with-border">
                                   <h3 class="box-title"><?= _translate("Patient Information"); ?></h3>&nbsp;&nbsp;&nbsp;
                                   <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="" placeholder="Enter ART Number or Patient Name" <?php echo $disable; ?> title="Enter art number or patient name" />&nbsp;&nbsp;
                                   <a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No Patient Found</strong></span>
                              </div>
                              <div class="box-body">
                                   <div class="row">
                                        <div class="col-xs-3 col-md-3">
                                             <div class="form-group" style=" width: 100%; ">
                                                  <label for="artNo"><?= _translate("Patient ID"); ?> <span class="mandatory">*</span></label>
                                                  <input type="text" <?php echo $disable; ?> name="artNo" id="artNo" class="form-control isRequired patientId" placeholder="Enter ART Number" title="Enter art number" value="<?= ($vlQueryInfo['patient_art_no']); ?>" />
                                                  <span class="artNoGroup" id="artNoGroup"></span>
                                             </div>
                                        </div>
                                        <div class="col-xs-3 col-md-3">
                                             <div class="form-group" style=" width: 100%; ">
                                                  <label for="dob"><?= _translate("Date of Birth"); ?> </label>
                                                  <input type="text" name="dob" id="dob" <?php echo $disable; ?> class="form-control date" placeholder="Enter DOB" title="Enter dob" value="<?= ($vlQueryInfo['patient_dob']); ?>" />
                                             </div>
                                        </div>
                                        <div class="col-xs-3 col-md-3">
                                             <div class="form-group" style=" width: 100%; ">
                                                  <label for="ageInYears"><?= _translate("If DOB unknown, Age in Years"); ?></label>
                                                  <input type="text" name="ageInYears" id="ageInYears" <?php echo $disable; ?> class="form-control forceNumeric" maxlength="3" placeholder="Age in Years" title="Enter age in years" value="<?= ($vlQueryInfo['patient_age_in_years']); ?>" />
                                             </div>
                                        </div>
                                        <div class="col-xs-3 col-md-3">
                                             <div class="form-group" style=" width: 100%; ">
                                                  <label for="ageInMonths"><?= _translate("If Age < 1, Age in Months"); ?></label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="Age in Month" <?php echo $disable; ?> title="Enter age in months" value="<?= ($vlQueryInfo['patient_age_in_months']); ?>" />
                                             </div>
                                        </div>
                                   </div>
                                   <div class="row">
                                        <div class="col-xs-3 col-md-3">
                                             <div class="form-group" style=" width: 100%; ">
                                                  <label for="patientFirstName"><?= _translate("Patient Name (First Name, Last Name)"); ?><span class="mandatory">*</span></label>
                                                  <input type="text" name="patientFirstName" id="patientFirstName" class="form-control isRequired" <?php echo $disable; ?> placeholder="Enter Patient Name" title="Enter patient name" value="<?php echo $patientFullName; ?>" />
                                             </div>
                                        </div>
                                        <div class="col-xs-3 col-md-3">
                                             <div class="form-group" style=" width: 100%; ">
                                                  <label for="gender"><?= _translate("Sex"); ?> <span class="mandatory">*</span></label><br>
                                                  <select class="form-control ajax-select2" id="gender" name="gender" placeholder="Sex" <?php echo $disable; ?> style="width:100%;">
                                                       <option value="">-- Select --</option>
                                                       <option value="male" <?php echo ($vlQueryInfo['patient_gender'] == 'male') ? "selected='selected'" : "" ?>><?= _translate("Male"); ?></option>
                                                       <option value="female" <?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "selected='selected'" : "" ?>><?= _translate("Female"); ?></option>
                                                       <option value="unreported" <?php echo ($vlQueryInfo['patient_gender'] == 'unreported') ? "selected='selected'" : "" ?>><?= _translate("Unreported"); ?></option>
                                                  </select>
                                             </div>
                                        </div>
                                        <div class="col-xs-3 col-md-3 femaleSection" style="display:<?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "" : "none" ?>">
                                             <div class="form-group">
                                                  <label for="patientPregnant"><?= _translate('Is Patient Pregnant?'); ?> <span class="mandatory">*</span></label><br>
                                                  <label class="radio-inline">
                                                       <input type="radio" class="<?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "isRequired" : ""; ?>" id="pregYes" name="patientPregnant" value="yes" title="<?= _translate('Please check if patient is pregnant'); ?>" <?php echo $disable; ?> <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'yes') ? "checked='checked'" : "" ?>> <?= _translate('Yes'); ?>
                                                  </label>
                                                  <label class="radio-inline">
                                                       <input type="radio" class="<?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "isRequired" : ""; ?>" id="pregNo" name="patientPregnant" value="no" <?php echo $disable; ?> <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'no') ? "checked='checked'" : "" ?>> <?= _translate('No'); ?>
                                                  </label>
                                             </div>
                                        </div>
                                        <div class="col-xs-3 col-md-3 femaleSection" style="display:<?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "" : "none" ?>">
                                             <div class="form-group">
                                                  <label for="breastfeeding"><?= _translate('Is Patient Breastfeeding?'); ?> <span class="mandatory">*</span></label><br>
                                                  <label class="radio-inline">
                                                       <input type="radio" class="" id="breastfeedingYes" name="breastfeeding" value="yes" title="<?= _translate('Please check if patient is breastfeeding'); ?>" <?php echo $disable; ?> <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'yes') ? "checked='checked'" : "" ?>> <?= _translate('Yes'); ?>
                                                  </label>
                                                  <label class="radio-inline">
                                                       <input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no" <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'no') ? "checked='checked'" : "" ?> <?php echo $disable; ?>> <?= _translate('No'); ?>
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
                                                       <input type="text" value="<?php echo $vlQueryInfo['sample_collection_date']; ?>" class="form-control isRequired dateTime" <?php echo $disable; ?> style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select sample collection date" onchange="checkSampleTestingDate();setSampleDispatchDate();">
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for=""><?= _translate("Sample Dispatched On"); ?> <span class="mandatory">*</span></label>
                                                       <input type="text" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['sample_dispatched_datetime']; ?>" class="form-control isRequired dateTime" style="width:100%;" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="Sample Dispatched On" title="Please select sample dispatched on">
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group" style=" width: 100%; ">
                                                       <label for="specimenType"><?= _translate("Sample Type"); ?> <span class="mandatory">*</span></label>
                                                       <select name="specimenType" id="specimenType" <?php echo $disable; ?> class="form-control isRequired" title="Please choose sample type">
                                                            <option value=""> -- <?= _translate("Select"); ?> -- </option>
                                                            <?php foreach ($sResult as $name) { ?>
                                                                 <option value="<?php echo $name['sample_id']; ?>" <?php echo ($vlQueryInfo['specimen_type'] == $name['sample_id']) ? "selected='selected'" : "" ?>><?= _translate($name['sample_name']); ?></option>
                                                            <?php } ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <?php if ($general->isLISInstance()) { ?>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group" style=" width: 100%; ">
                                                            <label for="sampleReceivedDate"><?= _translate("Date Sample Received at Testing Lab"); ?> </label>
                                                            <input type="text" value="<?php echo $vlQueryInfo['sample_received_at_lab_datetime']; ?>" <?php echo $disable; ?> class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="Sample Received at LAB Date" title="Please select sample received at Lab date" />
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
                                                            <select class="form-control" name="lineOfTreatment" id="lineOfTreatment" <?php echo $disable; ?> title="<?= _translate('Line Of Treatment'); ?>">
                                                                 <option value=""><?= _translate('--Select--'); ?></option>
                                                                 <option value="1" <?php echo ($vlQueryInfo['line_of_treatment'] == '1') ? "selected='selected' " : "" ?>><?= _translate('1st Line'); ?></option>
                                                                 <option value="2" <?php echo ($vlQueryInfo['line_of_treatment'] == '2') ? "selected='selected' " : "" ?>><?= _translate('2nd Line'); ?></option>
                                                                 <option value="3" <?php echo ($vlQueryInfo['line_of_treatment'] == '3') ? "selected='selected' " : "" ?>><?= _translate('3rd Line'); ?></option>
                                                                 <option value="n/a" <?php echo ($vlQueryInfo['line_of_treatment'] == 'n/a') ? "selected='selected' " : "" ?>><?= _translate('N/A'); ?></option>
                                                            </select>
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group" style=" width: 100%; ">
                                                            <label for=""><?= _translate("Date of Initiation of Treatment Line"); ?> </label>
                                                            <input type="text" class="form-control date" style="width:100%;" name="regimenInitiatedOn" <?php echo $disable; ?> id="regimenInitiatedOn" placeholder="Current Regimen Initiated On" title="Please enter current regimen initiated on" value="<?php echo $vlQueryInfo['date_of_initiation_of_current_regimen']; ?>">
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group" style=" width: 100%; ">
                                                            <label for="arvAdherence"><?= _translate("ARV Adherence"); ?> </label>
                                                            <select <?php echo $disable; ?> name="arvAdherence" id="arvAdherence" class="form-control" title="Please choose adherence">
                                                                 <option value=""> -- <?= _translate("Select"); ?> -- </option>
                                                                 <option value="good" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'good') ? "selected='selected'" : "" ?>>Good >= 95%</option>
                                                                 <option value="fair" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'fair') ? "selected='selected'" : "" ?>>Fair (85-94%)</option>
                                                                 <option value="poor" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'poor') ? "selected='selected'" : "" ?>>Poor < 85%</option>
                                                            </select>
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group" style=" width: 100%; ">
                                                            <label for="treatmentDurationPrecise"><?= _translate("Duration of treatment"); ?> </label>
                                                            <select class="form-control" <?php echo $disable; ?> id="treatmentDurationPrecise" name="treatmentDurationPrecise" placeholder="Select Treatment Period" title="Please select how long has this patient been on treatment" onchange="treatmentDuration(this.value)">
                                                                 <option value=""> -- <?= _translate("Select"); ?> -- </option>
                                                                 <option value="6 Months" <?php echo ($vlQueryInfo['treatment_duration_precise'] == '6 Months') ? "selected='selected'" : "" ?>><?= _translate("6 Months"); ?></option>
                                                                 <option value="12 Months" <?php echo ($vlQueryInfo['treatment_duration_precise'] == '12 Months') ? "selected='selected'" : "" ?>><?= _translate("12 Months"); ?></option>
                                                                 <option value="More than 12 Months" <?php echo (($vlQueryInfo['treatment_duration_precise'] == 'More than 12 Months') || (!in_array($vlQueryInfo['treatment_duration_precise'], ["6 Months", "12 Months"]) && isset($vlQueryInfo['treatment_duration_precise']))) ? "selected='selected'" : "" ?>><?= _translate("More than 12 Months"); ?></option>
                                                            </select>
                                                            <?php $display =  ((!in_array($vlQueryInfo['treatment_duration_precise'], ["6 Months", "12 Months"])) && isset($vlQueryInfo['treatment_duration_precise'])) ? "block" : "none"; ?>
                                                            <input type="text" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['treatment_duration_precise'] ?? null; ?>" class="form-control" name="treatmentDurationPrecise1" id="treatmentDurationPrecise1" placeholder="Enter treatment period" title="Please enter treatment period" style="width:100%;display:<?php echo $display; ?>;margin-top:10px;">
                                                       </div>
                                                  </div>
                                             </div>
                                             <div class="row">
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group" style=" width: 100%; ">
                                                            <label for="cd4Result"><?= _translate("Latest CD4 count"); ?> <small>(cells/µl)</small></label>
                                                            <input type="text" class="form-control" <?php echo $disable; ?> name="cd4Result" id="cd4Result" placeholder="Enter CD4 count" title="Please enter CD4 count" style="width:100%;margin-top:2px;" value="<?php echo $vlQueryInfo['last_cd4_result']; ?>">
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group" style=" width: 100%; ">
                                                            <label for="cd4Percentage"><?= _translate("CD4 (%)"); ?> </label>
                                                            <input type="text" class="form-control" name="cd4Percentage" <?php echo $disable; ?> id="cd4Percentage" placeholder="Enter CD4 %" title="Please enter CD4 %" style="width:100%;margin-top:2px;" value="<?php echo $vlQueryInfo['last_cd4_percentage']; ?>">
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group" style=" width: 100%; ">
                                                            <label for="cd4Date"><?= _translate("Date"); ?> </label>
                                                            <input type="text" class="form-control date" name="cd4Date" id="cd4Date" <?php echo $disable; ?> placeholder="Enter CD Date" title="Please enter CD Date" style="width:100%;margin-top:2px;" value="<?php echo DateUtility::humanReadableDateFormat($vlQueryInfo['last_cd4_date'] ?? '', true); ?>">
                                                       </div>
                                                  </div>
                                             </div>
                                             <div class="row">
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group" style=" width: 100%; ">
                                                            <label for="cd8Result"><?= _translate("Last HIV-1 Viral Load"); ?> <small>(copies/mL)</small></label>
                                                            <input type="text" class="form-control" name="cd8Result" id="cd8Result" <?php echo $disable; ?> placeholder="Enter CD4 count" title="Please enter HIV-1 Result" style="width:100%;margin-top:2px;" value="<?php echo $vlQueryInfo['last_cd8_result']; ?>">
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group" style=" width: 100%; ">
                                                            <label for="cd8Date"><?= _translate("Date"); ?> </label>
                                                            <input type="text" class="form-control date" name="cd8Date" id="cd8Date" <?php echo $disable; ?> placeholder="Enter HIV-1 Date" title="Please enter HIV-1 Date" style="width:100%;margin-top:2px;" value="<?php echo DateUtility::humanReadableDateFormat($vlQueryInfo['last_cd8_date'] ?? '', true); ?>">
                                                       </div>
                                                  </div>
                                             </div>
                                        </div>

                                        <div class="box box-primary">
                                             <div class="box-header with-border">
                                                  <h3 class="box-title"><?= _translate("Indication for Viral Load Testing"); ?> <span class="mandatory">*</span></h3><small> <?= _translate("(Please choose one):(To be completed by clinician)"); ?></small>
                                             </div>
                                             <div class="box-body">
                                                  <?php if (isset($testReasonsResult) && $testReasonsResult !== []) {
                                                       foreach ($testReasonsResult as $key => $title) { ?>
                                                            <div class="row">
                                                                 <div class="col-md-6">
                                                                      <div class="form-group">
                                                                           <div class="col-lg-12">
                                                                                <label class="radio-inline">
                                                                                     <input <?php echo $disable; ?> type="radio" <?php echo ($vlQueryInfo['reason_for_vl_testing'] == $key || (isset($subTestReasons[$key]) && in_array($vlQueryInfo['reason_for_vl_testing'], array_keys($subTestReasons[$key])))) ? "checked='checked'" : ""; ?> class="isRequired" id="rmTesting<?php echo $key; ?>" name="reasonForVLTesting" value="<?php echo $key; ?>" title="<?= _translate('Please check viral load indication testing type'); ?>" onclick="showTesting('rmTesting<?php echo $key; ?>', <?php echo $key; ?>);">
                                                                                     <strong><?= _translate($title); ?></strong>
                                                                                </label>
                                                                           </div>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <?php if ($key == 5) { ?>
                                                                 <div class="row rmTesting5 hideTestData well" style="display:<?php echo (isset($vlQueryInfo['reason_for_vl_testing_other']) && !empty($vlQueryInfo['reason_for_vl_testing_other'])) ? "block" : "none"; ?>;">
                                                                      <div class="col-md-6">
                                                                           <label class="col-lg-5 control-label"><?= _translate('Please specify other reasons'); ?></label>
                                                                           <div class="col-lg-7">
                                                                                <input type="text" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['reason_for_vl_testing_other'] ?? null; ?>" class="form-control" id="newreasonForVLTesting" name="newreasonForVLTesting" placeholder="<?= _translate('Please specify other test reason') ?>" title="<?= _translate('Please specify other test reason') ?>" />
                                                                           </div>
                                                                      </div>
                                                                 </div>
                                                            <?php } ?>
                                                            <?php if (isset($subTestReasons[$key]) && (isset($subTestReasons[$key]) && $subTestReasons[$key] !== [])) { ?>
                                                                 <div class="row rmTesting<?php echo $key; ?> hideTestData well" style="display:<?php echo ($vlQueryInfo['reason_for_vl_testing'] == $key || in_array($vlQueryInfo['reason_for_vl_testing'], array_keys($subTestReasons[$key]))) ? "block" : "none"; ?>;">
                                                                      <div class="col-md-6">
                                                                           <label class="col-lg-5 control-label"><?= _translate('Choose reason for testing'); ?></label>
                                                                           <div class="col-lg-7">
                                                                                <select <?php echo $disable; ?> name="controlVlTestingType[<?php echo $key; ?>]" id="controlVlType<?php echo $key; ?>" class="form-control controlVlTypeFields" title="<?= _translate('Please choose a reason for VL testing'); ?>" onchange="checkreasonForVLTesting();">
                                                                                     <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                                                     <?php foreach ($subTestReasons[$key] as $testReasonId => $row) { ?>
                                                                                          <option value="<?php echo $testReasonId; ?>" <?php echo ($vlQueryInfo['reason_for_vl_testing'] == $testReasonId) ? "selected='selected'" : ""; ?>><?php echo _translate($row); ?></option>
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
                                             <form class="form-inline" method="post" name="vlRequestFormFasco" id="vlRequestFormFasco" autocomplete="off" action="updateVlTestResultHelper.php">
                                                  <div class="box box-primary">
                                                       <div class="box-header with-border">
                                                            <h3 class="box-title"><?= _translate("Laboratory Information"); ?></h3>
                                                       </div>
                                                       <div class="box-body">
                                                            <div class="row">
                                                                 <div class="col-md-6">
                                                                      <label for="vlFocalPerson" class="col-lg-5 control-label labels"><?= _translate("VL Focal Person"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <select class="form-control ajax-select2" id="vlFocalPerson" name="vlFocalPerson" placeholder="VL Focal Person" title="Please enter vl focal person name">
                                                                                <option value="<?= ($vlQueryInfo['vl_focal_person']); ?>" selected='selected'> <?= ($vlQueryInfo['vl_focal_person']); ?></option>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label labels"><?= _translate("VL Focal Person Phone Number"); ?></label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" value="<?= ($vlQueryInfo['vl_focal_person_phone_number']); ?>" class="form-control phone-number" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter vl focal person phone number" />
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
                                                                                     <option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['instrument_id']; ?>" <?php echo ($vlQueryInfo['vl_test_platform'] == $mName['machine_name']) ? 'selected="selected"' : ''; ?>><?php echo $mName['machine_name']; ?></option>
                                                                                <?php } ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="isSampleRejected"><?= _translate("Is Sample Rejected?"); ?></label>
                                                                      <div class="col-lg-7">
                                                                           <select name="isSampleRejected" id="isSampleRejected" class="form-control" title="Please check if sample is rejected or not">
                                                                                <option value="">-- Select --</option>
                                                                                <option value="yes" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'selected="selected"' : ''; ?>>Yes</option>
                                                                                <option value="no" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'selected="selected"' : ''; ?>>No</option>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-6 rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
                                                                      <label class="col-lg-5 control-label labels" for="rejectionReason"><?= _translate("Rejection Reason"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="rejectionReason" id="rejectionReason" class="form-control" title="Please choose reason" onchange="checkRejectionReason();">
                                                                                <option value="">-- Select --</option>
                                                                                <?php foreach ($rejectionTypeResult as $type) { ?>
                                                                                     <optgroup label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
                                                                                          <?php foreach ($rejectionResult as $reject) {
                                                                                               if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                                                                    <option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?= $reject['rejection_reason_name']; ?></option>
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
                                                                           <input value="<?php echo DateUtility::humanReadableDateFormat($vlQueryInfo['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" title="Please select rejection date" />
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="sampleTestingDateAtLab"><?= _translate("Sample Testing Date"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control result-fields dateTime <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'isRequired' : ''; ?>" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? ' disabled="disabled" ' : ''; ?> id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Sample Testing Date" title="Please select sample testing date" value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>" onchange="checkSampleTestingDate();" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6 vlResult">
                                                                      <label class="col-lg-5 control-label  labels" for="vlResult"><?= _translate("Viral Load Result (copies/mL)"); ?> </label>
                                                                      <div class="col-lg-7 resultInputContainer">
                                                                           <input list="possibleVlResults" autocomplete="off" class="form-control result-fields labSection" id="vlResult" name="vlResult" placeholder="Select or Type VL Result" title="Please enter viral load result" value="<?= ($vlQueryInfo['result']); ?>" onchange="calculateLogValue(this)" disabled>
                                                                           <datalist id="possibleVlResults">

                                                                           </datalist>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">

                                                                 <div class="vlLog col-md-6" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'none' : 'block'; ?>;">
                                                                      <label class="col-lg-5 control-label  labels" for="vlLog"><?= _translate("Viral Load (Log)"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control" id="vlLog" name="vlLog" placeholder="Viral Load (Log)" title="Please enter viral load result in Log" value="<?= ($vlQueryInfo['result_value_log']); ?>" <?php echo ($vlQueryInfo['result'] == 'Target Not Detected' || $vlQueryInfo['result'] == 'Below Detection Level') ? 'readonly="readonly"' : ''; ?> style="width:100%;" onchange="calculateLogValue(this);" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label" for="reviewedBy"><?= _translate("Reviewed By"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="reviewedBy" id="reviewedBy" class="select2 form-control labels" title="Please choose reviewed by" style="width: 100%;">
                                                                                <?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_reviewed_by'], '-- Select --'); ?>
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
                                                                                <option value="HIV-1 Detected" <?php echo (isset($vlQueryInfo['result_value_hiv_detection']) && $vlQueryInfo['result_value_hiv_detection'] == 'HIV-1 Detected') ? 'selected="selected"' : ''; ?>>HIV-1 Detected</option>
                                                                                <option value="HIV-1 Not Detected" <?php echo (isset($vlQueryInfo['result_value_hiv_detection']) && $vlQueryInfo['result_value_hiv_detection'] == 'HIV-1 Not Detected') ? 'selected="selected"' : ''; ?>>HIV-1 Not Detected</option>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <?php if (count($reasonForFailure) > 0) { ?>
                                                                      <div class="col-md-6 reasonForFailure" style="display: none;">
                                                                           <label class="col-lg-5 control-label" for="reasonForFailure"><?= _translate("Reason for Failure"); ?> <span class="mandatory">*</span> </label>
                                                                           <div class="col-lg-7">
                                                                                <select name="reasonForFailure" id="reasonForFailure" class="form-control" title="Please choose reason for failure" style="width: 100%;">
                                                                                     <?= $general->generateSelectOptions($reasonForFailure, $vlQueryInfo['reason_for_failure'], '-- Select --'); ?>
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
                                                                           <input type="text" value="<?php echo $vlQueryInfo['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="testedBy"><?= _translate("Tested By"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="testedBy" id="testedBy" class="select2 form-control" title="Please choose approved by">
                                                                                <?= $general->generateSelectOptions($userInfo, $vlQueryInfo['tested_by'], '-- Select --'); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <?php $styleStatus = '';
                                                                 if ((($_SESSION['accessType'] == 'collection-site') && $vlQueryInfo['result_status'] == SAMPLE_STATUS\RECEIVED_AT_CLINIC) || ($sCode != '')) {
                                                                      $styleStatus = "display:none"; ?>
                                                                      <input type="hidden" name="status" value="<?= ($vlQueryInfo['result_status']); ?>" />
                                                                 <?php } ?>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="approvedBy"><?= _translate("Approved By"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose approved by">
                                                                                <?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_approved_by'], '-- Select --'); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="approvedOn"><?= _translate("Approved On"); ?> <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" value="<?php echo $vlQueryInfo['result_approved_datetime']; ?>" class="form-control dateTime" id="approvedOnDateTime" title="Please choose Approved On" name="approvedOnDateTime" placeholder="<?= _translate("Please enter date"); ?>" style="width:100%;" />
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">

                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="resultDispatchedOn"><?= _translate("Date Results Dispatched"); ?></label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatch Date" title="Please select result dispatched date" value="<?php echo $vlQueryInfo['result_dispatched_datetime']; ?>" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label labels" for="labComments"><?= _translate("Lab Tech. Comments"); ?> </label>
                                                                      <div class="col-lg-7">
                                                                           <textarea class="form-control" name="labComments" id="labComments" placeholder="Lab comments" title="Please enter LabComments"><?php echo trim((string) $vlQueryInfo['lab_tech_comments']); ?></textarea>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-6 reasonForResultChanges" style="display:none;">
                                                                      <label class="col-lg-5 control-label" for="reasonForResultChanges">Reason For Changes in Result<span class="mandatory">*</span></label>
                                                                      <div class="col-lg-7">
                                                                           <textarea class="form-control" name="reasonForResultChanges" id="reasonForResultChanges" placeholder="Enter Reason For Result Changes" title="Please enter reason for result changes" style="width:100%;"></textarea>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <?php if (!empty($allChange)) { ?>
                                                                 <div class="row">
                                                                      <div class="col-md-12"><?php echo $rch; ?></div>
                                                                 </div>
                                                            <?php } ?>
                                                       </div>
                                                  </div>
                                             <?php } ?>
                                   </div>
                                   <div class="box-footer">
                                        <input type="hidden" name="revised" id="revised" value="no" />
                                        <input type="hidden" name="vlSampleId" id="vlSampleId" value="<?= ($vlQueryInfo['vl_sample_id']); ?>" />
                                        <input type="hidden" name="reasonForResultChangesHistory" id="reasonForResultChangesHistory" value="<?php echo base64_encode((string) $vlQueryInfo['reason_for_result_changes']); ?>" />
                                        <input type="hidden" name="sampleCode" id="sampleCode" value="<?= ($vlQueryInfo['sample_code']); ?>" />
                                        <input type="hidden" name="artNo" id="artNo" value="<?= ($vlQueryInfo['patient_art_no']); ?>" />
                                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>&nbsp;
                                        <a href="vlTestResult.php" class="btn btn-default"> Cancel</a>
                                   </div>
                                   </form>
                              </div>
                         </div>
                    </div>
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
                              testType: 'vl',
                              sampleId: '<?php echo $vlQueryInfo['specimen_type']; ?>'
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

          getfacilityProvinceDetails($("#facilityId").val());
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
          getfacilityProvinceDetails(obj)
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
                         testType: 'vl'
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
          if ($('#isSampleRejected').val() == "yes") {
               $('.vlResult, #vlResult').removeClass('isRequired');
          }
          if ($('#failed').prop('checked')) {
               $('#vlResult').removeClass('isRequired');
          }
          flag = deforayValidator.init({
               formId: 'vlRequestFormFasco'
          });

          $('.isRequired').each(function() {
               ($(this).val() == '') ? $(this).css('background-color', '#FFFF99'): $(this).css('background-color', '#FFFFFF')
          });
          if (flag) {
               $.blockUI();
               document.getElementById('vlRequestFormFasco').submit();
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
               formId: 'vlRequestFormFasco'
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
                    insertSampleCode('vlRequestFormFasco', 'vlSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 1, 'sampleCollectionDate');
               <?php } else { ?>
                    document.getElementById('vlRequestFormFasco').submit();
               <?php } ?>
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