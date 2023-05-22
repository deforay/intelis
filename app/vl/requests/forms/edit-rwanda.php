<?php

use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');




if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'alphanumeric') {
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
//check remote user
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {
     $sampleCode = 'remote_sample_code';
     if (!empty($vlQueryInfo['remote_sample']) && $vlQueryInfo['remote_sample'] == 'yes') {
          $sampleCode = 'remote_sample_code';
     } else {
          $sampleCode = 'sample_code';
     }
     //check user exist in user_facility_map table
     $chkUserFcMapQry = "Select user_id from user_facility_map where user_id='" . $_SESSION['userId'] . "'";
     $chkUserFcMapResult = $db->query($chkUserFcMapQry);
     if ($chkUserFcMapResult) {
          $pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code
                         FROM geographical_divisions as gd
                         JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id
                         JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id
                         WHERE gd.geo_parent = 0 AND gd.geo_status='active'
                         AND vlfm.user_id='" . $_SESSION['userId'] . "'";
     }
} else {
     $sampleCode = 'sample_code';
}
$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
     $province .= "<option value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}
$facility = $general->generateSelectOptions($healthFacilities, $vlQueryInfo['facility_id'], '-- Select --');
//regimen heading
$artRegimenQuery = "SELECT DISTINCT headings FROM r_vl_art_regimen";
$artRegimenResult = $db->rawQuery($artRegimenQuery);
$aQuery = "SELECT * from r_vl_art_regimen where art_status ='active'";
$aResult = $db->query($aQuery);
//facility details
if (isset($vlQueryInfo['facility_id']) && $vlQueryInfo['facility_id'] > 0) {
     $facilityQuery = "SELECT * FROM facility_details WHERE facility_id= ? AND status='active'";
     $facilityResult = $db->rawQuery($facilityQuery, array($vlQueryInfo['facility_id']));
}
if (!isset($facilityResult[0]['facility_code'])) {
     $facilityResult[0]['facility_code'] = '';
}
if (!isset($facilityResult[0]['facility_mobile_numbers'])) {
     $facilityResult[0]['facility_mobile_numbers'] = '';
}
if (!isset($facilityResult[0]['contact_person'])) {
     $facilityResult[0]['contact_person'] = '';
}
if (!isset($facilityResult[0]['facility_emails'])) {
     $facilityResult[0]['facility_emails'] = '';
}
if (!isset($facilityResult[0]['facility_state'])) {
     $facilityResult[0]['facility_state'] = '';
}
if (!isset($facilityResult[0]['facility_district'])) {
     $facilityResult[0]['facility_district'] = '';
}
if (trim($facilityResult[0]['facility_state']) != '') {
     $stateQuery = "SELECT * FROM geographical_divisions where geo_name='" . $facilityResult[0]['facility_state'] . "'";
     $stateResult = $db->query($stateQuery);
}
if (!isset($stateResult[0]['geo_code'])) {
     $stateResult[0]['geo_code'] = '';
}
//district details
$districtResult = [];
if (trim($facilityResult[0]['facility_state']) != '') {
     $districtQuery = "SELECT DISTINCT facility_district from facility_details where facility_state='" . $facilityResult[0]['facility_state'] . "' AND status='active'";
     $districtResult = $db->query($districtQuery);
}


//suggest sample id when lab user add request sample
$sampleSuggestion = '';
$sampleSuggestionDisplay = 'display:none;';
//set reason for changes history
$rch = '';
if (isset($vlQueryInfo['reason_for_vl_result_changes']) && $vlQueryInfo['reason_for_vl_result_changes'] != '' && $vlQueryInfo['reason_for_vl_result_changes'] != null) {
     $rch .= '<h4>Result Changes History</h4>';
     $rch .= '<table style="width:100%;">';
     $rch .= '<thead><tr style="border-bottom:2px solid #d3d3d3;"><th style="width:20%;">USER</th><th style="width:60%;">MESSAGE</th><th style="width:20%;text-align:center;">DATE</th></tr></thead>';
     $rch .= '<tbody>';
     $splitChanges = explode('vlsm', $vlQueryInfo['reason_for_vl_result_changes']);
     for ($c = 0; $c < count($splitChanges); $c++) {
          $getData = explode("##", $splitChanges[$c]);
          $expStr = explode(" ", $getData[2]);
          $changedDate = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
          $rch .= '<tr><td>' . ($getData[0]) . '</td><td>' . ($getData[1]) . '</td><td style="text-align:center;">' . $changedDate . '</td></tr>';
     }
     $rch .= '</tbody>';
     $rch .= '</table>';
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

     #sampleCode {
          background-color: #fff;
     }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
     <!-- Content Header (Page header) -->
     <section class="content-header">
          <h1><em class="fa-solid fa-pen-to-square"></em> VIRAL LOAD LABORATORY REQUEST FORM </h1>
          <ol class="breadcrumb">
               <li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
               <li class="active">Edit Vl Request</li>
          </ol>
     </section>
     <!-- Main content -->
     <section class="content">

          <div class="box box-default">
               <div class="box-header with-border">
                    <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
               </div>
               <div class="box-body">
                    <!-- form start -->
                    <form class="form-inline" method="post" name="vlRequestFormRwd" id="vlRequestFormRwd" autocomplete="off" action="editVlRequestHelper.php">
                         <div class="box-body">
                              <div class="box box-primary">
                                   <div class="box-header with-border">
                                        <h3 class="box-title">Clinic Information: (To be filled by requesting Clinican/Nurse)</h3>
                                   </div>
                                   <div class="">
                                        <div class="" style="<?php echo $sampleSuggestionDisplay; ?>">
                                             <?php
                                             if ($vlQueryInfo['sample_code'] != '') {
                                             ?>
                                                  <label for="sampleSuggest" class="text-danger">&nbsp;&nbsp;&nbsp;Please note that this Remote Sample has already been imported with VLSM Sample ID <?= ($vlQueryInfo['sample_code']); ?></label>
                                             <?php
                                             } else {
                                             ?>
                                                  <label for="sampleSuggest">&nbsp;&nbsp;&nbsp;Sample ID (might change while submitting the form) - </label>
                                                  <?php echo $sampleSuggestion; ?>
                                             <?php } ?>

                                        </div>
                                        <div class="box-body">
                                             <div class="row">
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="">
                                                            <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                                                 <label for="sampleCode">Sample ID </label><br>
                                                                 <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?php echo $vlQueryInfo[$sampleCode]; ?></span>
                                                                 <input type="hidden" class="<?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" value="<?php echo $vlQueryInfo[$sampleCode]; ?>" />
                                                            <?php } else { ?>
                                                                 <label for="sampleCode">Sample ID <span class="mandatory">*</span></label>
                                                                 <input type="text" class="form-control isRequired <?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" <?php echo $maxLength; ?> placeholder="Enter Sample ID" title="Please enter sample id" value="<?php echo $vlQueryInfo[$sampleCode]; ?>" style="width:100%;" readonly="readonly" onchange="checkSampleNameValidation('form_vl','<?php echo $sampleCode; ?>',this.id,'<?php echo "vl_sample_id##" . $vlQueryInfo["vl_sample_id"]; ?>','This sample number already exists.Try another number',null)" />
                                                                 <input type="hidden" name="sampleCodeCol" value="<?= ($vlQueryInfo['sample_code']); ?>" />
                                                            <?php } ?>
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="">
                                                            <label for="sampleReordered">
                                                                 <input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" <?php echo (trim($vlQueryInfo['sample_reordered']) == 'yes') ? 'checked="checked"' : '' ?> title="Please indicate if this is a reordered sample"> Sample Reordered
                                                            </label>
                                                       </div>
                                                  </div>


                                             </div>
                                             <div class="row">
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="">
                                                            <label for="province">Province <span class="mandatory">*</span></label>
                                                            <select class="form-control isRequired" name="province" id="province" title="Please choose a province" style="width:100%;" onchange="getProvinceDistricts(this);">
                                                                 <?= $province; ?>
                                                            </select>
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="">
                                                            <label for="district">District <span class="mandatory">*</span></label>
                                                            <select class="form-control isRequired" name="district" id="district" title="Please choose a district" style="width:100%;" onchange="getFacilities(this);">
                                                                 <option value=""> -- Select -- </option>
                                                            </select>
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="">
                                                            <label for="fName">Clinic/Health Center <span class="mandatory">*</span></label>
                                                            <select class="form-control isRequired" id="fName" name="fName" title="Please select clinic/health center name" style="width:100%;" onchange="fillFacilityDetails(this);">
                                                                 <?= $facility; ?>
                                                            </select>
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="fCode">Clinic/Health Center Code </label>
                                                            <input type="text" class="form-control" style="width:100%;" name="fCode" id="fCode" placeholder="Clinic/Health Center Code" title="Please enter clinic/health center code" value="<?php echo $facilityResult[0]['facility_code']; ?>">
                                                       </div>
                                                  </div>
                                             </div>
                                             <div class="row facilityDetails" style="display:<?php echo (trim($facilityResult[0]['facility_emails']) != '' || trim($facilityResult[0]['facility_mobile_numbers']) != '' || trim($facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;">
                                                  <div class="col-xs-2 col-md-2 femails" style="display:<?php echo (trim($facilityResult[0]['facility_emails']) != '') ? '' : 'none'; ?>;"><strong>Clinic Email(s)</strong></div>
                                                  <div class="col-xs-2 col-md-2 femails facilityEmails" style="display:<?php echo (trim($facilityResult[0]['facility_emails']) != '') ? '' : 'none'; ?>;"><?php echo $facilityResult[0]['facility_emails']; ?></div>
                                                  <div class="col-xs-2 col-md-2 fmobileNumbers" style="display:<?php echo (trim($facilityResult[0]['facility_mobile_numbers']) != '') ? '' : 'none'; ?>;"><strong>Clinic Mobile No.(s)</strong></div>
                                                  <div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers" style="display:<?php echo (trim($facilityResult[0]['facility_mobile_numbers']) != '') ? '' : 'none'; ?>;"><?php echo $facilityResult[0]['facility_mobile_numbers']; ?></div>
                                                  <div class="col-xs-2 col-md-2 fContactPerson" style="display:<?php echo (trim($facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;"><strong>Clinic Contact Person -</strong></div>
                                                  <div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson" style="display:<?php echo (trim($facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;"><?php echo ($facilityResult[0]['contact_person']); ?></div>
                                             </div>
                                             <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                                  <div class="row">
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="">
                                                                 <label for="labId">VL Testing Hub <span class="mandatory">*</span></label>
                                                                 <select name="labId" id="labId" class="form-control isRequired" title="Please choose a VL testing hub">
                                                                      <?= $general->generateSelectOptions($testingLabs, $vlQueryInfo['lab_id'], '-- Select --'); ?>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                  </div>
                                             <?php } ?>
                                        </div>
                                   </div>
                                   <div class="box box-primary">
                                        <div class="box-header with-border">
                                             <h3 class="box-title">Patient Information</h3>
                                        </div>
                                        <div class="box-body">
                                             <div class="row">
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="artNo">ART (TRACNET) No. <span class="mandatory">*</span></label>
                                                            <input type="text" name="artNo" id="artNo" maxlength="10" minlength="10" class="form-control isRequired" placeholder="Enter ART Number" title="Enter art number" value="<?= ($vlQueryInfo['patient_art_no']); ?>" onchange="if(this.value.length!=10) alert('ART No. should be 10 characters long');" />
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="dob">Date of Birth <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                            <input type="text" name="dob" id="dob" class="form-control date <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "isRequired" : ''; ?>" placeholder="Enter DOB" title="Enter dob" value="<?= ($vlQueryInfo['patient_dob']); ?>" onchange="getAge();checkARTInitiationDate();" />
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="ageInYears">If DOB unknown, Age in Years </label>
                                                            <input type="text" name="ageInYears" id="ageInYears" class="form-control forceNumeric" maxlength="2" placeholder="Age in Year" title="Enter age in years" value="<?= ($vlQueryInfo['patient_age_in_years']); ?>" />
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="ageInMonths">If Age
                                                                 < 1, Age in Months </label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="Age in Month" title="Enter age in months" value="<?= ($vlQueryInfo['patient_age_in_months']); ?>" />
                                                       </div>
                                                  </div>
                                             </div>
                                             <div class="row">
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="patientFirstName">Patient Name </label>
                                                            <input type="text" name="patientFirstName" id="patientFirstName" class="form-control" placeholder="Enter Patient Name" title="Enter patient name" value="<?php echo $patientFirstName; ?>" />
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="gender">Gender <span class="mandatory">*</span></label><br>
                                                            <label class="radio-inline" style="margin-left:0px;">
                                                                 <input type="radio" class="isRequired" id="genderMale" name="gender" value="male" title="Please check gender" <?php echo ($vlQueryInfo['patient_gender'] == 'male') ? "checked='checked'" : "" ?>> Male
                                                            </label>&nbsp;&nbsp;
                                                            <label class="radio-inline" style="margin-left:0px;">
                                                                 <input type="radio" id="genderFemale" name="gender" value="female" title="Please check gender" <?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "checked='checked'" : "" ?>> Female
                                                            </label>
                                                            <!--<label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" id="genderNotRecorded" name="gender" value="not_recorded" title="Please check gender" < ?php echo ($vlQueryInfo['patient_gender']=='not_recorded')?"checked='checked'":""?>>Not Recorded
                                                       </label>-->
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="patientPhoneNumber">Phone Number</label>
                                                            <input type="text" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control forceNumeric" maxlength="15" placeholder="Enter Phone Number" title="Enter phone number" value="<?= ($vlQueryInfo['patient_mobile_number']); ?>" />
                                                       </div>
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="box box-primary">
                                             <div class="box-header with-border">
                                                  <h3 class="box-title">Sample Information</h3>
                                             </div>
                                             <div class="box-body">
                                                  <div class="row">
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group">
                                                                 <label for="">Date of Sample Collection <span class="mandatory">*</span></label>
                                                                 <input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select sample collection date" value="<?php echo $vlQueryInfo['sample_collection_date']; ?>" onchange="checkSampleReceviedDate();checkSampleTestingDate();">
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group">
                                                                 <label for="specimenType">Sample Type <span class="mandatory">*</span></label>
                                                                 <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose a sample type">
                                                                      <option value=""> -- Select -- </option>
                                                                      <?php foreach ($sResult as $name) { ?>
                                                                           <option value="<?php echo $name['sample_id']; ?>" <?php echo ($vlQueryInfo['sample_type'] == $name['sample_id']) ? "selected='selected'" : "" ?>><?= $name['sample_name']; ?></option>
                                                                      <?php } ?>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                  </div>
                                             </div>
                                             <div class="box box-primary">
                                                  <div class="box-header with-border">
                                                       <h3 class="box-title">Treatment Information</h3>
                                                  </div>
                                                  <div class="box-body">
                                                       <div class="row">
                                                            <div class="col-xs-3 col-md-3">
                                                                 <div class="form-group">
                                                                      <label for="">Date of Treatment Initiation</label>
                                                                      <input type="text" class="form-control date" name="dateOfArtInitiation" id="dateOfArtInitiation" placeholder="Date of Treatment initiation" title="Date of treatment initiation" value="<?php echo $vlQueryInfo['treatment_initiated_date']; ?>" style="width:100%;" onchange="checkARTInitiationDate();">
                                                                 </div>
                                                            </div>
                                                            <div class="col-xs-3 col-md-3">
                                                                 <div class="form-group">
                                                                      <label for="artRegimen">Current Regimen <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                      <select class="form-control <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "isRequired" : ''; ?>" id="artRegimen" name="artRegimen" title="Please choose an ART Regimen" style="width:100%;" onchange="checkARTRegimenValue();">
                                                                           <option value="">-- Select --</option>
                                                                           <?php foreach ($artRegimenResult as $heading) { ?>
                                                                                <optgroup label="<?= $heading['headings']; ?>">
                                                                                     <?php
                                                                                     foreach ($aResult as $regimen) {
                                                                                          if ($heading['headings'] == $regimen['headings']) { ?>
                                                                                               <option value="<?php echo $regimen['art_code']; ?>" <?php echo ($vlQueryInfo['current_regimen'] == $regimen['art_code']) ? "selected='selected'" : "" ?>><?php echo $regimen['art_code']; ?></option>
                                                                                     <?php }
                                                                                     } ?>
                                                                                </optgroup>
                                                                           <?php }
                                                                           if ($sarr['sc_user_type'] != 'vluser') {  ?>
                                                                                <!-- <option value="other">Other</option> -->
                                                                           <?php } ?>
                                                                      </select>
                                                                      <input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="ART Regimen" title="Please enter the ART Regimen" style="width:100%;display:none;margin-top:2px;">
                                                                 </div>
                                                            </div>
                                                            <div class="col-xs-3 col-md-3">
                                                                 <div class="form-group">
                                                                      <label for="">Date of Initiation of Current Regimen<?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                      <input type="text" class="form-control date  <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "isRequired" : ''; ?>" style="width:100%;" name="regimenInitiatedOn" id="regimenInitiatedOn" placeholder="Current Regimen Initiated On" title="Please enter current regimen initiated on" value="<?php echo $vlQueryInfo['date_of_initiation_of_current_regimen']; ?>">
                                                                 </div>
                                                            </div>
                                                            <div class="col-xs-3 col-md-3">
                                                                 <div class="form-group">
                                                                      <label for="arvAdherence">ARV Adherence <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                      <select name="arvAdherence" id="arvAdherence" class="form-control  <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "isRequired" : ''; ?>" title="Please choose an adherence %">
                                                                           <option value=""> -- Select -- </option>
                                                                           <option value="good" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'good') ? "selected='selected'" : "" ?>>Good >= 95%</option>
                                                                           <option value="fair" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'fair') ? "selected='selected'" : "" ?>>Fair (85-94%)</option>
                                                                           <option value="poor" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'poor') ? "selected='selected'" : "" ?>>Poor < 85%</option>
                                                                      </select>
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row femaleSection" style="display:<?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "" : "none" ?>" ;>
                                                            <div class="col-xs-3 col-md-3">
                                                                 <div class="form-group">
                                                                      <label for="patientPregnant">Is Patient Pregnant? <span class="mandatory">*</span></label><br>
                                                                      <label class="radio-inline">
                                                                           <input type="radio" class="<?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "isRequired" : ""; ?>" id="pregYes" name="patientPregnant" value="yes" title="Please check if patient is pregnant" <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'yes') ? "checked='checked'" : "" ?>> Yes
                                                                      </label>
                                                                      <label class="radio-inline">
                                                                           <input type="radio" class="" id="pregNo" name="patientPregnant" value="no" <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'no') ? "checked='checked'" : "" ?>> No
                                                                      </label>
                                                                 </div>
                                                            </div>
                                                            <div class="col-xs-3 col-md-3">
                                                                 <div class="form-group">
                                                                      <label for="breastfeeding">Is Patient Breastfeeding? <span class="mandatory">*</span></label><br>
                                                                      <label class="radio-inline">
                                                                           <input type="radio" class="<?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "isRequired" : ""; ?>" id="breastfeedingYes" name="breastfeeding" value="yes" title="Please check if patient is breastfeeding" <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'yes') ? "checked='checked'" : "" ?>> Yes
                                                                      </label>
                                                                      <label class="radio-inline">
                                                                           <input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no" <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'no') ? "checked='checked'" : "" ?>> No
                                                                      </label>
                                                                 </div>
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="box box-primary">
                                                       <div class="box-header with-border">
                                                            <h3 class="box-title">Indication for Viral Load Testing <span class="mandatory">*</span></h3><small> (Please tick one):(To be completed by clinician)</small>
                                                       </div>
                                                       <div class="box-body">
                                                            <div class="row">
                                                                 <div class="col-md-6">
                                                                      <div class="form-group">
                                                                           <div class="col-lg-12">
                                                                                <label class="radio-inline">
                                                                                     <?php
                                                                                     $vlTestReasonQueryRow = "SELECT * from r_vl_test_reasons where test_reason_id='" . trim($vlQueryInfo['reason_for_vl_testing']) . "' OR test_reason_name = '" . trim($vlQueryInfo['reason_for_vl_testing']) . "'";
                                                                                     $vlTestReasonResultRow = $db->query($vlTestReasonQueryRow);
                                                                                     $checked = '';
                                                                                     $display = '';
                                                                                     $vlValue = '';
                                                                                     if (trim($vlQueryInfo['reason_for_vl_testing']) == 'routine' || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'routine') {
                                                                                          $checked = 'checked="checked"';
                                                                                          $display = 'block';
                                                                                          if ($vlQueryInfo['last_vl_result_routine'] != null && trim($vlQueryInfo['last_vl_result_routine']) != '' && trim($vlQueryInfo['last_vl_result_routine']) != '<20' && trim($vlQueryInfo['last_vl_result_routine']) != 'tnd') {
                                                                                               $vlValue = $vlQueryInfo['last_vl_result_routine'];
                                                                                          }
                                                                                     } else {
                                                                                          $checked = '';
                                                                                          $display = 'none';
                                                                                     }
                                                                                     ?>
                                                                                     <input type="radio" class="isRequired" id="rmTesting" name="reasonForVLTesting" value="routine" title="Please check viral load indication testing type" <?php echo $checked; ?> onclick="showTesting('rmTesting');">
                                                                                     <strong>Routine Monitoring</strong>
                                                                                </label>
                                                                           </div>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row rmTesting hideTestData well" style="display:<?php echo $display; ?>;">
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label">Date of Last VL Test</label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control date viralTestData" id="rmTestingLastVLDate" name="rmTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim($vlQueryInfo['last_vl_date_routine']) != '' && $vlQueryInfo['last_vl_date_routine'] != null && $vlQueryInfo['last_vl_date_routine'] != '0000-00-00') ? DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_routine']) : ''; ?>" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label for="rmTestingVlValue" class="col-lg-3 control-label">VL Result</label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control forceNumeric viralTestData" id="rmTestingVlValue" name="rmTestingVlValue" placeholder="Enter VL Result" title="Please enter VL Result" <?php echo (($vlQueryInfo['last_vl_result_routine'] == null || trim($vlQueryInfo['last_vl_result_routine']) == '') || trim($vlValue) != '') ? '' : 'readonly="readonly"'; ?> value="<?php echo $vlValue; ?>" />
                                                                           (copies/ml)<br>
                                                                           <input type="checkbox" id="rmTestingVlCheckValuelt20" name="rmTestingVlCheckValue" <?php echo ($vlQueryInfo['last_vl_result_routine'] == '<20') ? 'checked="checked"' : ''; ?> value="<20" <?php echo (($vlQueryInfo['last_vl_result_routine'] == null || trim($vlQueryInfo['last_vl_result_routine']) == '') || trim($vlQueryInfo['last_vl_result_routine']) == '<20') ? '' : 'disabled="disabled"'; ?> title="Please check VL Result">
                                                                           < 20<br>
                                                                                <input type="checkbox" id="rmTestingVlCheckValueTnd" name="rmTestingVlCheckValue" <?php echo ($vlQueryInfo['last_vl_result_routine'] == 'tnd') ? 'checked="checked"' : ''; ?> value="tnd" <?php echo (($vlQueryInfo['last_vl_result_routine'] == null || trim($vlQueryInfo['last_vl_result_routine']) == '') || trim($vlQueryInfo['last_vl_result_routine']) == 'tnd') ? '' : 'disabled="disabled"'; ?> title="Please check VL Result"> Target Not Detected
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-6">
                                                                      <div class="form-group">
                                                                           <div class="col-lg-12">
                                                                                <label class="radio-inline">
                                                                                     <?php
                                                                                     $checked = '';
                                                                                     $display = '';
                                                                                     $vlValue = '';
                                                                                     if (trim($vlQueryInfo['reason_for_vl_testing']) == 'suspect' || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'suspect') {
                                                                                          $checked = 'checked="checked"';
                                                                                          $display = 'block';
                                                                                          if ($vlQueryInfo['last_vl_result_failure'] != null && trim($vlQueryInfo['last_vl_result_failure']) != '' && trim($vlQueryInfo['last_vl_result_failure']) != '<20' && trim($vlQueryInfo['last_vl_result_failure']) != 'tnd') {
                                                                                               $vlValue = $vlQueryInfo['last_vl_result_failure'];
                                                                                          }
                                                                                     } else {
                                                                                          $checked = '';
                                                                                          $display = 'none';
                                                                                     }
                                                                                     ?>
                                                                                     <input type="radio" id="suspendTreatment" name="reasonForVLTesting" value="suspect" title="Please check viral load indication testing type" <?php echo $checked; ?> onclick="showTesting('suspendTreatment');">
                                                                                     <strong>Suspect Treatment Failure</strong>
                                                                                </label>
                                                                           </div>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row suspendTreatment hideTestData well" style="display: <?php echo $display; ?>;">
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label">Date of Last VL Test</label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control date viralTestData" id="suspendTreatmentLastVLDate" name="suspendTreatmentLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim($vlQueryInfo['last_vl_date_failure']) != '' && $vlQueryInfo['last_vl_date_failure'] != null && $vlQueryInfo['last_vl_date_failure'] != '0000-00-00') ? DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_failure']) : ''; ?>" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label for="suspendTreatmentVlValue" class="col-lg-3 control-label">VL Result</label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control forceNumeric viralTestData" id="suspendTreatmentVlValue" name="suspendTreatmentVlValue" placeholder="Enter VL Result" title="Please enter VL Result" <?php echo (($vlQueryInfo['last_vl_result_failure'] == null || trim($vlQueryInfo['last_vl_result_failure']) == '') || trim($vlValue) != '') ? '' : 'readonly="readonly"'; ?> value="<?php echo $vlValue; ?>" />
                                                                           (copies/ml)<br>
                                                                           <input type="checkbox" id="suspendTreatmentVlCheckValuelt20" name="suspendTreatmentVlCheckValue" <?php echo ($vlQueryInfo['last_vl_result_failure'] == '<20') ? 'checked="checked"' : ''; ?> value="<20" <?php echo (($vlQueryInfo['last_vl_result_failure'] == null || trim($vlQueryInfo['last_vl_result_failure']) == '') || trim($vlQueryInfo['last_vl_result_failure']) == '<20') ? '' : 'disabled="disabled"'; ?> title="Please check VL Result">
                                                                           < 20<br>
                                                                                <input type="checkbox" id="suspendTreatmentVlCheckValueTnd" name="suspendTreatmentVlCheckValue" <?php echo ($vlQueryInfo['last_vl_result_failure'] == 'tnd') ? 'checked="checked"' : ''; ?> value="tnd" <?php echo (($vlQueryInfo['last_vl_result_failure'] == null || trim($vlQueryInfo['last_vl_result_failure']) == '') || trim($vlQueryInfo['last_vl_result_failure']) == 'tnd') ? '' : 'disabled="disabled"'; ?> title="Please check VL Result"> Target Not Detected
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-8">
                                                                      <div class="form-group">
                                                                           <div class="col-lg-12">
                                                                                <label class="radio-inline">
                                                                                     <?php
                                                                                     $checked = '';
                                                                                     $display = '';
                                                                                     $vlValue = '';
                                                                                     if (trim($vlQueryInfo['reason_for_vl_testing']) == 'failure' || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'failure') {
                                                                                          $checked = 'checked="checked"';
                                                                                          $display = 'block';
                                                                                          if ($vlQueryInfo['last_vl_result_failure_ac'] != null && trim($vlQueryInfo['last_vl_result_failure_ac']) != '' && trim($vlQueryInfo['last_vl_result_failure_ac']) != '<20' && trim($vlQueryInfo['last_vl_result_failure_ac']) != 'tnd') {
                                                                                               $vlValue = $vlQueryInfo['last_vl_result_failure_ac'];
                                                                                          }
                                                                                     } else {
                                                                                          $checked = '';
                                                                                          $display = 'none';
                                                                                     }
                                                                                     ?>
                                                                                     <input type="radio" id="repeatTesting" name="reasonForVLTesting" value="failure" title="Please check viral load indication testing type" <?php echo $checked; ?> onclick="showTesting('repeatTesting');">
                                                                                     <strong>Control VL test after adherence counselling addressing suspected treatment failure </strong>
                                                                                </label>
                                                                           </div>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row repeatTesting hideTestData well" style="display: <?php echo $display; ?>;">
                                                                 <div class="col-md-6">
                                                                      <label class="col-lg-5 control-label">Date of Last VL Test</label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control date viralTestData" id="repeatTestingLastVLDate" name="repeatTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim($vlQueryInfo['last_vl_date_failure_ac']) != '' && $vlQueryInfo['last_vl_date_failure_ac'] != null && $vlQueryInfo['last_vl_date_failure_ac'] != '0000-00-00') ? DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_failure_ac']) : ''; ?>" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-6">
                                                                      <label for="repeatTestingVlValue" class="col-lg-3 control-label">VL Result</label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control forceNumeric viralTestData" id="repeatTestingVlValue" name="repeatTestingVlValue" placeholder="Enter VL Result" title="Please enter VL Result" <?php echo (($vlQueryInfo['last_vl_result_failure_ac'] == null || trim($vlQueryInfo['last_vl_result_failure_ac']) == '') || trim($vlValue) != '') ? '' : 'readonly="readonly"'; ?> value="<?php echo $vlValue; ?>" />
                                                                           (copies/ml)<br>
                                                                           <input type="checkbox" id="repeatTestingVlCheckValuelt20" name="repeatTestingVlCheckValue" <?php echo ($vlQueryInfo['last_vl_result_failure_ac'] == '<20') ? 'checked="checked"' : ''; ?> value="<20" <?php echo (($vlQueryInfo['last_vl_result_failure_ac'] == null || trim($vlQueryInfo['last_vl_result_failure_ac']) == '') || trim($vlQueryInfo['last_vl_result_failure_ac']) == '<20') ? '' : 'disabled="disabled"'; ?> title="Please check VL Result">
                                                                           < 20<br>
                                                                                <input type="checkbox" id="repeatTestingVlCheckValueTnd" name="repeatTestingVlCheckValue" <?php echo ($vlQueryInfo['last_vl_result_failure_ac'] == 'tnd') ? 'checked="checked"' : ''; ?> value="tnd" <?php echo (($vlQueryInfo['last_vl_result_failure_ac'] == null || trim($vlQueryInfo['last_vl_result_failure_ac']) == '') || trim($vlQueryInfo['last_vl_result_failure_ac']) == 'tnd') ? '' : 'disabled="disabled"'; ?> title="Please check VL Result"> Target Not Detected
                                                                      </div>
                                                                 </div>
                                                            </div>

                                                            <?php if (isset(SYSTEM_CONFIG['recency']['vlsync']) && SYSTEM_CONFIG['recency']['vlsync']) {  ?>
                                                                 <div class="row">
                                                                      <div class="col-md-6">
                                                                           <div class="form-group">
                                                                                <div class="col-lg-12">
                                                                                     <label class="radio-inline">
                                                                                          <input type="radio" class="" id="recencyTest" name="reasonForVLTesting" value="recency" title="Please check viral load indication testing type" <?php echo trim($vlQueryInfo['reason_for_vl_testing']) == '9999' ? "checked='checked'" : ""; ?> onclick="showTesting('recency')">
                                                                                          <strong>Confirmation Test for Recency</strong>
                                                                                     </label>
                                                                                </div>
                                                                           </div>
                                                                      </div>
                                                                 </div>
                                                            <?php }  ?>
                                                            <hr>
                                                            <div class="row">
                                                                 <div class="col-md-4">
                                                                      <label for="reqClinician" class="col-lg-5 control-label">Request Clinician <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control  <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "isRequired" : ''; ?>" id="reqClinician" name="reqClinician" placeholder="Request Clinician" title="Please enter request clinician" value="<?php echo $vlQueryInfo['request_clinician_name']; ?>" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label for="reqClinicianPhoneNumber" class="col-lg-5 control-label">Phone Number <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control forceNumeric  <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "isRequired" : ''; ?>" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter request clinician phone number" value="<?php echo $vlQueryInfo['request_clinician_phone_number']; ?>" />
                                                                      </div>
                                                                 </div>
                                                                 <!--  <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label" for="requestDate">Request Date <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control date  <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "isRequired" : ''; ?>" id="requestDate" name="requestDate" placeholder="Request Date" title="Please select request date" value="<?php echo $vlQueryInfo['test_requested_on']; ?>" />
                                                                      </div>
                                                                 </div>-->
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-4">
                                                                      <label for="vlFocalPerson" class="col-lg-5 control-label">Shipper Name<?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control  <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "isRequired" : ''; ?>" id="vlFocalPerson" name="vlFocalPerson" placeholder="VL Focal Person" title="Please enter shipper name" value="<?= ($vlQueryInfo['vl_focal_person']); ?>" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label">VL Shipper Phone Number <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control forceNumeric  <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? "isRequired" : ''; ?>" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter vl shipper phone number" value="<?= ($vlQueryInfo['vl_focal_person_phone_number']); ?>" />
                                                                      </div>
                                                                 </div>
                                                                 <!-- <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label" for="emailHf">Email for HF </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control isEmail" id="emailHf" name="emailHf" placeholder="Email for HF" title="Please enter email for hf" value="<?php echo $facilityResult[0]['facility_emails']; ?>" />
                                                                      </div>
                                                                 </div>-->
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <?php if ($usersService->isAllowed('updateVlTestResult.php') && $_SESSION['accessType'] != 'collection-site') { ?>
                                                       <div class="box box-primary">
                                                            <div class="box-header with-border">
                                                                 <h3 class="box-title">Laboratory Information</h3>
                                                            </div>
                                                            <div class="box-body">
                                                                 <div class="row">
                                                                      <div class="col-md-4">
                                                                           <label for="testingPlatform" class="col-lg-5 control-label">VL Testing Platform </label>
                                                                           <div class="col-lg-7">
                                                                                <select name="testingPlatform" id="testingPlatform" class="form-control labSection" title="Please choose VL Testing Platform" <?php echo $labFieldDisabled; ?>>
                                                                                     <option value="">-- Select --</option>
                                                                                     <?php foreach ($importResult as $mName) { ?>
                                                                                          <option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']; ?>" <?php echo ($vlQueryInfo['vl_test_platform'] == $mName['machine_name']) ? 'selected="selected"' : ''; ?>><?php echo $mName['machine_name']; ?></option>
                                                                                     <?php } ?>
                                                                                </select>
                                                                           </div>
                                                                      </div>
                                                                      <div class="col-md-4">
                                                                           <label class="col-lg-5 control-label" for="sampleReceivedDate">Date Sample Received at Testing Lab</label>
                                                                           <div class="col-lg-7">
                                                                                <input type="text" class="form-control labSection dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="Sample Received Date" title="Please select sample received date" value="<?php echo $vlQueryInfo['sample_received_at_vl_lab_datetime']; ?>" <?php echo $labFieldDisabled; ?> onchange="checkSampleReceviedDate();" />
                                                                           </div>
                                                                      </div>
                                                                      <div class="col-md-4">
                                                                           <label class="col-lg-5 control-label" for="sampleTestingDateAtLab">Sample Testing Date </label>
                                                                           <div class="col-lg-7">
                                                                                <input type="text" class="form-control labSection dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Sample Testing Date" title="Please select sample testing date" value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>" <?php echo $labFieldDisabled; ?> onchange="checkSampleTestingDate();" />
                                                                           </div>
                                                                      </div>
                                                                 </div>
                                                                 <div class="row">

                                                                      <div class="col-md-4">
                                                                           <label class="col-lg-5 control-label" for="resultDispatchedOn">Date Results Dispatched </label>
                                                                           <div class="col-lg-7">
                                                                                <input type="text" class="form-control labSection dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatched Date" title="Please select result dispatched date" value="<?php echo $vlQueryInfo['result_dispatched_datetime']; ?>" <?php echo $labFieldDisabled; ?> />
                                                                           </div>
                                                                      </div>

                                                                      <div class="col-md-4">
                                                                           <label class="col-lg-5 control-label" for="noResult">Sample Rejection </label>
                                                                           <div class="col-lg-7">
                                                                                <select name="noResult" id="noResult" class="form-control isRequired" title="Please check if sample is rejected or not">
                                                                                     <option value="">-- Select --</option>
                                                                                     <option value="yes" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'selected="selected"' : ''; ?>>Yes</option>
                                                                                     <option value="no" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'selected="selected"' : ''; ?>>No</option>
                                                                                </select>
                                                                           </div>
                                                                      </div>
                                                                      <div class="col-md-4 rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
                                                                           <label class="col-lg-5 control-label" for="rejectionReason">Rejection Reason <span class="mandatory">*</span></label>
                                                                           <div class="col-lg-7">
                                                                                <select name="rejectionReason" id="rejectionReason" class="form-control labSection" title="Please choose a Rejection Reason" <?php echo $labFieldDisabled; ?> onchange="checkRejectionReason();">
                                                                                     <option value="">-- Select --</option>
                                                                                     <?php foreach ($rejectionTypeResult as $type) { ?>
                                                                                          <optgroup label="<?php echo ($type['rejection_type']); ?>">
                                                                                               <?php
                                                                                               foreach ($rejectionResult as $reject) {
                                                                                                    if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                                                                         <option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?= $reject['rejection_reason_name']; ?></option>
                                                                                               <?php }
                                                                                               } ?>
                                                                                          </optgroup>
                                                                                     <?php }
                                                                                     if ($sarr['sc_user_type'] != 'vluser') {  ?>
                                                                                          <option value="other">Other (Please Specify) </option>
                                                                                     <?php } ?>
                                                                                </select>
                                                                                <input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="Rejection Reason" title="Please enter rejection reason" style="width:100%;display:none;margin-top:2px;">
                                                                           </div>
                                                                      </div>
                                                                      <div class="col-md-4 vlResult" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'none' : 'block'; ?>;">
                                                                           <label class="col-lg-5 control-label" for="vlResult">Viral Load Result (copies/ml) </label>
                                                                           <div class="col-lg-7">
                                                                                <input type="text" class="form-control labSection" id="vlResult" name="vlResult" placeholder="Viral Load Result" title="Please enter viral load result" value="<?= ($vlQueryInfo['result']); ?>" <?php echo ($vlQueryInfo['result'] == 'Target Not Detected' || $vlQueryInfo['result'] == 'Below Detection Level') ? 'readonly="readonly"' : $labFieldDisabled; ?> style="width:100%;" onchange="calculateLogValue(this);" />
                                                                                <input type="checkbox" class="labSection specialResults" name="lt20" value="yes" title="Please check VL Result" <?php echo ($vlQueryInfo['result'] == '< 20' || $vlQueryInfo['result'] == '<20') ? 'checked="checked"' : ''; ?>>
                                                                                &lt; 20<br>
                                                                                <input type="checkbox" class="labSection specialResults" name="lt40" value="yes" title="Please check VL Result" <?php echo ($vlQueryInfo['result'] == '< 40' || $vlQueryInfo['result'] == '<40') ? 'checked="checked"' : ''; ?>>
                                                                                &lt; 40<br>
                                                                                <input type="checkbox" class="labSection specialResults" name="tnd" value="yes" <?php echo ($vlQueryInfo['result'] == 'Target Not Detected') ? 'checked="checked"' : ''; ?> title="Please check tnd"> Target Not Detected<br>
                                                                                <input type="checkbox" class="labSection specialResults" name="bdl" value="yes" <?php echo ($vlQueryInfo['result'] == 'Below Detection Level') ? 'checked="checked"' : ''; ?> title="Please check bdl"> Below Detection Level<br>
                                                                                <input type="checkbox" class="labSection specialResults" name="failed" value="yes" <?php echo ($vlQueryInfo['result'] == 'Failed') ? 'checked="checked"' : ''; ?> title="Please check Failed"> Failed <br>
                                                                                <input type="checkbox" class="labSection specialResults" name="invalid" value="yes" <?php echo ($vlQueryInfo['result'] == 'Invalid') ? 'checked="checked"' : ''; ?> title="Please check Invalid"> Invalid
                                                                           </div>
                                                                      </div>
                                                                 </div>
                                                                 <div class="row">

                                                                      <div class="col-md-4 vlResult" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'none' : 'block'; ?>;">
                                                                           <label class="col-lg-5 control-label" for="vlLog">Viral Load Log </label>
                                                                           <div class="col-lg-7">
                                                                                <input type="text" class="form-control labSection" id="vlLog" name="vlLog" placeholder="Viral Load Log" title="Please enter viral load log" value="<?= ($vlQueryInfo['result_value_log']); ?>" <?php echo ($vlQueryInfo['result'] == 'Target Not Detected' || $vlQueryInfo['result'] == 'Below Detection Level') ? 'readonly="readonly"' : $labFieldDisabled; ?> style="width:100%;" onchange="calculateLogValue(this);" />
                                                                           </div>
                                                                      </div>
                                                                      <div class="col-md-4">
                                                                           <label class="col-lg-5 control-label" for="reviewedOn">Reviewed On </label>
                                                                           <div class="col-lg-7">
                                                                                <input type="text" value="<?php echo $vlQueryInfo['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" />
                                                                           </div>
                                                                      </div>
                                                                      <div class="col-md-4">
                                                                           <label class="col-lg-5 control-label" for="reviewedBy">Reviewed By </label>
                                                                           <div class="col-lg-7">
                                                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
                                                                                     <?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_reviewed_by'], '-- Select --'); ?>
                                                                                </select>
                                                                           </div>
                                                                      </div>
                                                                 </div>
                                                                 <div class="row">

                                                                      <div class="col-md-4">
                                                                           <label class="col-lg-5 control-label" for="approvedOn">Approved On </label>
                                                                           <div class="col-lg-7">
                                                                                <input type="text" name="approvedOn" id="approvedOn" class="dateTime form-control" placeholder="Approved on" value="<?php echo $vlQueryInfo['result_approved_datetime']; ?>" title="Please enter the Approved on" />
                                                                           </div>
                                                                      </div>
                                                                      <div class="col-md-4">
                                                                           <label class="col-lg-5 control-label" for="approvedBy">Approved By </label>
                                                                           <div class="col-lg-7">
                                                                                <select name="approvedBy" id="approvedBy" class="form-control labSection" title="Please choose approved by" <?php echo $labFieldDisabled; ?>>
                                                                                     <option value="">-- Select --</option>
                                                                                     <?php foreach ($userResult as $uName) { ?>
                                                                                          <option value="<?php echo $uName['user_id']; ?>" <?php echo ($vlQueryInfo['result_approved_by'] == $uName['user_id']) ? "selected=selected" : ""; ?>><?php echo ($uName['user_name']); ?></option>
                                                                                     <?php } ?>
                                                                                </select>
                                                                           </div>
                                                                      </div>
                                                                      <div class="col-md-4">
                                                                           <label class="col-lg-5 control-label" for="labComments">Lab Tech. Comments </label>
                                                                           <div class="col-lg-7">
                                                                                <textarea class="form-control labSection" name="labComments" id="labComments" placeholder="Lab comments" <?php echo $labFieldDisabled; ?>><?php echo trim($vlQueryInfo['lab_tech_comments']); ?></textarea>
                                                                           </div>
                                                                      </div>
                                                                 </div>


                                                                 <div class="row">
                                                                      <div class="col-md-8 reasonForResultChanges" style="display:none;">
                                                                           <label class="col-lg-2 control-label" for="reasonForResultChanges">Reason For Changes in Result<span class="mandatory">*</span> </label>
                                                                           <div class="col-lg-10">
                                                                                <textarea class="form-control" name="reasonForResultChanges" id="reasonForResultChanges" placeholder="Enter Reason For Result Changes" title="Please enter reason for result changes" <?php echo $labFieldDisabled; ?> style="width:100%;"></textarea>
                                                                           </div>
                                                                      </div>
                                                                 </div>
                                                                 <?php if (trim($rch) != '') { ?>
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
                                                  <input type="hidden" name="isRemoteSample" value="<?= ($vlQueryInfo['remote_sample']); ?>" />
                                                  <input type="hidden" name="reasonForResultChangesHistory" id="reasonForResultChangesHistory" value="<?php echo $vlQueryInfo['reason_for_vl_result_changes']; ?>" />
                                                  <input type="hidden" name="oldStatus" value="<?= ($vlQueryInfo['result_status']); ?>" />
                                                  <input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />
                                                  <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>&nbsp;
                                                  <a href="vlRequest.php" class="btn btn-default"> Cancel</a>
                                             </div>
                    </form>
               </div>
     </section>
</div>
<script>
     provinceName = true;
     facilityName = true;
     $(document).ready(function() {



          if ($(".specialResults:checked").length > 0) {
               $('#vlResult, #vlLog').val('');
               $('#vlResult,#vlLog').attr('readonly', true);
               $('#vlResult, #vlLog').removeClass('isRequired');
               $(".specialResults").attr('disabled', false);
               $(".specialResults").not($(".specialResults:checked")).attr('disabled', true);
               $('.specialResults').not($(".specialResults:checked")).prop('checked', false).removeAttr('checked');
          }
          if ($('#vlResult, #vlLog').val() != '') {
               $('.specialResults').prop('checked', false).removeAttr('checked');
               $(".specialResults").attr('disabled', true);
               $('#vlResult').addClass('isRequired');
          }
          $('#fName').select2({
               placeholder: "Select Clinic/Health Center"
          });
          $('#labId').select2({
               placeholder: "Select Lab Name"
          });
          $('#reviewedBy').select2({
               placeholder: "Select Reviewed By"
          });
          $('#approvedBy').select2({
               placeholder: "Select Approved By"
          });

          getfacilityProvinceDetails($("#fName").val());

          getAge();
          __clone = $("#vlRequestFormRwd .labSection").clone();
          reason = ($("#reasonForResultChanges").length) ? $("#reasonForResultChanges").val() : '';
          result = ($("#vlResult").length) ? $("#vlResult").val() : '';
          //logVal = ($("#vlLog").length)?$("#vlLog").val():'';

          $("#vlResult, #vlLog").on('keyup keypress blur change paste', function() {
               if ($(this).val() != '') {
                    if ($(this).val() != $(this).val().replace(/[^\d\.]/g, "")) {
                         $(this).val('');
                         alert('Please enter only numeric values for Viral Load Result')
                    }
               }
          });
     });

     function showTesting(chosenClass) {
          $(".viralTestData").val('');
          $(".hideTestData").hide();
          $("." + chosenClass).show();
     }

     function getProvinceDistricts(obj) {
          $.blockUI();
          var cName = $("#fName").val();
          var pName = $("#province").val();
          if (pName != '' && provinceName && facilityName) {
               facilityName = false;
          }
          if (pName != '') {
               //if (provinceName) {
               $.post("/includes/siteInformationDropdownOptions.php", {
                         pName: pName,
                         testType: 'vl'
                    },
                    function(data) {
                         if (data != "") {
                              details = data.split("###");
                              $("#district").html(details[1]);
                              $("#fName").html("<option data-code='' data-emails='' data-mobile-nos='' data-contact-person='' value=''> -- Select -- </option>");
                              $(".facilityDetails").hide();
                              $(".facilityEmails").html('');
                              $(".facilityMobileNumbers").html('');
                              $(".facilityContactPerson").html('');
                         }
                    });
               //}

          } else if (pName == '') {
               provinceName = true;
               facilityName = true;
               $("#province").html("<?php echo $province; ?>");
               $("#district").html("<option value=''> -- Select -- </option>");
               $("#fName").html("<?php echo $facility; ?>");
               $("#fName").select2("val", "");
          }
          $.unblockUI();
     }

     function getFacilities(obj) {
          $.blockUI();
          var dName = $("#district").val();
          var cName = $("#fName").val();
          if (dName != '') {
               $.post("/includes/siteInformationDropdownOptions.php", {
                         dName: dName,
                         cliName: cName,
                         testType: 'vl'
                    },
                    function(data) {
                         if (data != "") {
                              details = data.split("###");
                              $("#fName").html(details[0]);
                              //$("#labId").html(details[1]);
                              $(".facilityDetails").hide();
                              $(".facilityEmails").html('');
                              $(".facilityMobileNumbers").html('');
                              $(".facilityContactPerson").html('');
                         }
                    });
          }
          $.unblockUI();
     }

     function getfacilityProvinceDetails(obj) {
          $.blockUI();
          //check facility name
          var cName = $("#fName").val();
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

     function fillFacilityDetails(obj) {
          getfacilityProvinceDetails(obj)
          $("#fCode").val($('#fName').find(':selected').data('code'));
          var femails = $('#fName').find(':selected').data('emails');
          var fmobilenos = $('#fName').find(':selected').data('mobile-nos');
          var fContactPerson = $('#fName').find(':selected').data('contact-person');
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
          if ($(this).val() == 'male' || $(this).val() == 'not_recorded') {
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

     $("input:radio[name=noResult]").click(function() {

          if ($(this).val() == 'yes') {
               $('.rejectionReason').show();
               $('.vlResult').css('display', 'none');
               $('#rejectionReason').addClass('isRequired');
               $('#vlResult').removeClass('isRequired');
          } else {
               $('.vlResult').css('display', 'block');
               $('.rejectionReason').hide();
               $('#rejectionReason').removeClass('isRequired');
               $('#vlResult').addClass('isRequired');
               // if any of the special results like tnd,bld are selected then remove isRequired from vlResult
               if ($('.specialResults:checkbox:checked').length) {
                    $('#vlResult').removeClass('isRequired');
               }
               $('#rejectionReason').val('');
          }
     });


     $('.specialResults').change(function() {
          if ($(this).is(':checked')) {
               $('#vlResult,#vlLog').attr('readonly', true);
               $('#vlResult').removeClass('isRequired');
               $(".specialResults").not(this).attr('disabled', true);
               $("#sampleTestingDateAtLab").addClass('isRequired');
          } else {
               $('#vlResult,#vlLog').attr('readonly', false);
               $(".specialResults").not(this).attr('disabled', false);
               if ($('#noResultNo').is(':checked')) {
                    $('#vlResult').addClass('isRequired');
                    //$("#sampleTestingDateAtLab").addClass('isRequired');
               }
          }
     });

     $('#vlResult,#vlLog').on('keyup keypress blur change paste input', function(e) {
          if (this.value != '') {
               $(".specialResults").not(this).attr('disabled', true);
               $("#sampleTestingDateAtLab").addClass('isRequired');
          } else {
               $(".specialResults").not(this).attr('disabled', false);
               $("#sampleTestingDateAtLab").removeClass('isRequired');
          }
     });

     $('#rmTestingVlValue').on('input', function(e) {
          if (this.value != '') {
               $('#rmTestingVlCheckValuelt20').attr('disabled', true);
               $('#rmTestingVlCheckValueTnd').attr('disabled', true);
          } else {
               $('#rmTestingVlCheckValuelt20').attr('disabled', false);
               $('#rmTestingVlCheckValueTnd').attr('disabled', false);
          }
     });

     $('#rmTestingVlCheckValuelt20').change(function() {
          if ($('#rmTestingVlCheckValuelt20').is(':checked')) {
               $('#rmTestingVlValue').attr('readonly', true);
               $('#rmTestingVlCheckValueTnd').attr('disabled', true);
          } else {
               $('#rmTestingVlValue').attr('readonly', false);
               $('#rmTestingVlCheckValueTnd').attr('disabled', false);
          }
     });

     $('#rmTestingVlCheckValueTnd').change(function() {
          if ($('#rmTestingVlCheckValueTnd').is(':checked')) {
               $('#rmTestingVlValue').attr('readonly', true);
               $('#rmTestingVlCheckValuelt20').attr('disabled', true);
          } else {
               $('#rmTestingVlValue').attr('readonly', false);
               $('#rmTestingVlCheckValuelt20').attr('disabled', false);
          }
     });

     $('#repeatTestingVlValue').on('input', function(e) {
          if (this.value != '') {
               $('#repeatTestingVlCheckValuelt20').attr('disabled', true);
               $('#repeatTestingVlCheckValueTnd').attr('disabled', true);
          } else {
               $('#repeatTestingVlCheckValuelt20').attr('disabled', false);
               $('#repeatTestingVlCheckValueTnd').attr('disabled', false);
          }
     });

     $('#repeatTestingVlCheckValuelt20').change(function() {
          if ($('#repeatTestingVlCheckValuelt20').is(':checked')) {
               $('#repeatTestingVlValue').attr('readonly', true);
               $('#repeatTestingVlCheckValueTnd').attr('disabled', true);
          } else {
               $('#repeatTestingVlValue').attr('readonly', false);
               $('#repeatTestingVlCheckValueTnd').attr('disabled', false);
          }
     });

     $('#repeatTestingVlCheckValueTnd').change(function() {
          if ($('#repeatTestingVlCheckValueTnd').is(':checked')) {
               $('#repeatTestingVlValue').attr('readonly', true);
               $('#repeatTestingVlCheckValuelt20').attr('disabled', true);
          } else {
               $('#repeatTestingVlValue').attr('readonly', false);
               $('#repeatTestingVlCheckValuelt20').attr('disabled', false);
          }
     });

     $('#suspendTreatmentVlValue').on('input', function(e) {
          if (this.value != '') {
               $('#suspendTreatmentVlCheckValuelt20').attr('disabled', true);
               $('#suspendTreatmentVlCheckValueTnd').attr('disabled', true);
          } else {
               $('#suspendTreatmentVlCheckValuelt20').attr('disabled', false);
               $('#suspendTreatmentVlCheckValueTnd').attr('disabled', false);
          }
     });

     $('#suspendTreatmentVlCheckValuelt20').change(function() {
          if ($('#suspendTreatmentVlCheckValuelt20').is(':checked')) {
               $('#suspendTreatmentVlValue').attr('readonly', true);
               $('#suspendTreatmentVlCheckValueTnd').attr('disabled', true);
          } else {
               $('#suspendTreatmentVlValue').attr('readonly', false);
               $('#suspendTreatmentVlCheckValueTnd').attr('disabled', false);
          }
     });

     $('#suspendTreatmentVlCheckValueTnd').change(function() {
          if ($('#suspendTreatmentVlCheckValueTnd').is(':checked')) {
               $('#suspendTreatmentVlValue').attr('readonly', true);
               $('#suspendTreatmentVlCheckValuelt20').attr('disabled', true);
          } else {
               $('#suspendTreatmentVlValue').attr('readonly', false);
               $('#suspendTreatmentVlCheckValuelt20').attr('disabled', false);
          }
     });

     $(".labSection").on("change", function() {
          if ($.trim(result) != '') {
               if ($(".labSection").serialize() == $(__clone).serialize()) {
                    $(".reasonForResultChanges").css("display", "none");
                    $("#reasonForResultChanges").removeClass("isRequired");
               } else {
                    $(".reasonForResultChanges").css("display", "block");
                    $("#reasonForResultChanges").addClass("isRequired");
               }
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

     function validateNow() {
          var ARTlength = $("#artNo").val();
          if (ARTlength.length != 10) {
               alert("<?= _("Patient ART No. should be 10 characters long"); ?>");
               //return false;
          }
          flag = deforayValidator.init({
               formId: 'vlRequestFormRwd'
          });

          $('.isRequired').each(function() {
               ($(this).val() == '') ? $(this).css('background-color', '#FFFF99'): $(this).css('background-color', '#FFFFFF')
          });
          var userType = "<?php echo $sarr['sc_user_type']; ?>";
          if (userType != 'remoteuser') {
               if ($.trim($("#dob").val()) == '' && $.trim($("#ageInYears").val()) == '' && $.trim($("#ageInMonths").val()) == '') {
                    alert("Please make sure to enter DOB or Age");
                    return false;
               }
          }
          if (flag) {
               $.blockUI();
               document.getElementById('vlRequestFormRwd').submit();
          }
     }
</script>
