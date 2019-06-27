<?php
ob_start();
if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'alphanumeric') {
     $sampleClass = '';
     $maxLength = '';
     if ($arr['max_length'] != '' && $arr['sample_code'] == 'alphanumeric') {
          $maxLength = $arr['max_length'];
          $maxLength = "maxlength=" . $maxLength;
     }
} else {
     $sampleClass = 'checkNum';
     $maxLength = '';
     if ($arr['max_length'] != '') {
          $maxLength = $arr['max_length'];
          $maxLength = "maxlength=" . $maxLength;
     }
}
//check remote user
$pdQuery = "SELECT * from province_details";
if ($sarr['user_type'] == 'remoteuser') {
     $sampleCode = 'remote_sample_code';
     //check user exist in user_facility_map table
     $chkUserFcMapQry = "Select user_id from vl_user_facility_map where user_id='" . $_SESSION['userId'] . "'";
     $chkUserFcMapResult = $db->query($chkUserFcMapQry);
     if ($chkUserFcMapResult) {
          $pdQuery = "SELECT * from province_details as pd JOIN facility_details as fd ON fd.facility_state=pd.province_name JOIN vl_user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where user_id='" . $_SESSION['userId'] . "'";
     }
} else {
     $sampleCode = 'sample_code';
}
$pdResult = $db->query($pdQuery);
$province = '';
$province .= "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
     $province .= "<option value='" . $provinceName['province_name'] . "##" . $provinceName['province_code'] . "'>" . ucwords($provinceName['province_name']) . "</option>";
}
$facility = '';
$facility .= "<option data-code='' data-emails='' data-mobile-nos='' data-contact-person='' value=''> -- Select -- </option>";
foreach ($fResult as $fDetails) {
     $facility .= "<option data-code='" . $fDetails['facility_code'] . "' data-emails='" . $fDetails['facility_emails'] . "' data-mobile-nos='" . $fDetails['facility_mobile_numbers'] . "' data-contact-person='" . ucwords($fDetails['contact_person']) . "' value='" . $fDetails['facility_id'] . "'>" . ucwords($fDetails['facility_name']) . "</option>";
}
//regimen heading
$artRegimenQuery = "SELECT DISTINCT headings FROM r_art_code_details WHERE nation_identifier ='rwd'";
$artRegimenResult = $db->rawQuery($artRegimenQuery);
$aQuery = "SELECT * from r_art_code_details where nation_identifier='rwd' AND art_status ='active'";
$aResult = $db->query($aQuery);
//facility details
if (isset($vlQueryInfo[0]['facility_id']) && $vlQueryInfo[0]['facility_id'] > 0) {
     $facilityQuery = "SELECT * from facility_details where facility_id='" . $vlQueryInfo[0]['facility_id'] . "' AND status='active'";
     $facilityResult = $db->query($facilityQuery);
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
     $stateQuery = "SELECT * from province_details where province_name='" . $facilityResult[0]['facility_state'] . "'";
     $stateResult = $db->query($stateQuery);
}
if (!isset($stateResult[0]['province_code'])) {
     $stateResult[0]['province_code'] = '';
}
//district details
$districtResult = array();
if (trim($facilityResult[0]['facility_state']) != '') {
     $districtQuery = "SELECT DISTINCT facility_district from facility_details where facility_state='" . $facilityResult[0]['facility_state'] . "' AND status='active'";
     $districtResult = $db->query($districtQuery);
}
//suggest sample id when lab user add request sample
$sampleSuggestion = '';
$sampleSuggestionDisplay = 'display:none;';
if ($sarr['user_type'] == 'vluser' && $sCode != '') {
     $sExpDT = explode(" ", $sampleCollectionDate);
     $sExpDate = explode("-", $sExpDT[0]);
     $start_date = date($sExpDate[0] . '-01-01') . " " . '00:00:00';
     $end_date = date($sExpDate[0] . '-12-31') . " " . '23:59:59';
     $mnthYr = substr($sExpDate[0], -2);
     if ($arr['sample_code'] == 'MMYY') {
          $mnthYr = $sExpDate[1] . substr($sExpDate[0], -2);
     } else if ($arr['sample_code'] == 'YY') {
          $mnthYr = substr($sExpDate[0], -2);
     }
     $auto = substr($sExpDate[0], -2) . $sExpDate[1] . $sExpDate[2];
     $svlQuery = 'SELECT sample_code_key FROM vl_request_form as vl WHERE
     DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '" AND sample_code!="" ORDER BY sample_code_key DESC LIMIT 1';
     $svlResult = $db->query($svlQuery);
     $prefix = $arr['sample_code_prefix'];
     if (isset($svlResult[0]['sample_code_key']) && $svlResult[0]['sample_code_key'] != '' && $svlResult[0]['sample_code_key'] != NULL) {
          $maxId = $svlResult[0]['sample_code_key'] + 1;
          $strparam = strlen($maxId);
          $zeros = substr("000", $strparam);
          $maxId = $zeros . $maxId;
     } else {
          $maxId = '001';
     }
     if ($arr['sample_code'] == 'auto') {
          $sampleSuggestion = $auto . $maxId;
     } else if ($arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') {
          $sampleSuggestion = $prefix . $mnthYr . $maxId;
     }
     $sampleSuggestionDisplay = 'display:block;';
}

//set reason for changes history
$rch = '';
if (isset($vlQueryInfo[0]['reason_for_vl_result_changes']) && $vlQueryInfo[0]['reason_for_vl_result_changes'] != '' && $vlQueryInfo[0]['reason_for_vl_result_changes'] != null) {
     $rch .= '<h4>Result Changes History</h4>';
     $rch .= '<table style="width:100%;">';
     $rch .= '<thead><tr style="border-bottom:2px solid #d3d3d3;"><th style="width:20%;">USER</th><th style="width:60%;">MESSAGE</th><th style="width:20%;text-align:center;">DATE</th></tr></thead>';
     $rch .= '<tbody>';
     $splitChanges = explode('vlsm', $vlQueryInfo[0]['reason_for_vl_result_changes']);
     for ($c = 0; $c < count($splitChanges); $c++) {
          $getData = explode("##", $splitChanges[$c]);
          $expStr = explode(" ", $getData[2]);
          $changedDate = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
          $rch .= '<tr><td>' . ucwords($getData[0]) . '</td><td>' . ucfirst($getData[1]) . '</td><td style="text-align:center;">' . $changedDate . '</td></tr>';
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
          <h1><i class="fa fa-edit"></i> VIRAL LOAD LABORATORY REQUEST FORM </h1>
          <ol class="breadcrumb">
               <li><a href="../dashboard/index.php"><i class="fa fa-dashboard"></i> Home</a></li>
               <li class="active">Edit Vl Request</li>
          </ol>
     </section>
     <!-- Main content -->
     <section class="content">
          <!-- SELECT2 EXAMPLE -->
          <div class="box box-default">
               <div class="box-header with-border">
                    <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
               </div>
               <div class="box-body">
                    <!-- form start -->
                    <form class="form-inline" method="post" name="vlRequestFormRwd" id="vlRequestFormRwd" autocomplete="off" action="editVlRequestHelperRwd.php">
                         <div class="box-body">
                              <div class="box box-primary">
                                   <div class="box-header with-border">
                                        <h3 class="box-title">Clinic Information: (To be filled by requesting Clinican/Nurse)</h3>
                                   </div>
                                   <div class="">
                                        <div class="" style="<?php echo $sampleSuggestionDisplay; ?>">
                                             <?php
                                             if ($vlQueryInfo[0]['sample_code'] != '') {
                                                  ?>
                                                  <label for="sampleSuggest" class="text-danger">&nbsp;&nbsp;&nbsp;Please note that this Remote Sample has already been imported with VLSM Sample ID <?php echo $vlQueryInfo[0]['sample_code']; ?></label>
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
                                                            <?php if ($sarr['user_type'] == 'remoteuser') { ?>
                                                                 <label for="sampleCode">Sample ID </label><br>
                                                                 <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?php echo ($sCode != '') ? $sCode : $vlQueryInfo[0][$sampleCode]; ?></span>
                                                                 <input type="hidden" class="<?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" value="<?php echo ($sCode != '') ? $sCode : $vlQueryInfo[0][$sampleCode]; ?>" />
                                                            <?php } else { ?>
                                                                 <label for="sampleCode">Sample ID <span class="mandatory">*</span></label>
                                                                 <input type="text" class="form-control isRequired <?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" <?php echo $maxLength; ?> placeholder="Enter Sample ID" title="Please enter sample id" value="<?php echo ($sCode != '') ? $sCode : $vlQueryInfo[0][$sampleCode]; ?>" style="width:100%;" readonly="readonly" onchange="checkSampleNameValidation('vl_request_form','<?php echo $sampleCode; ?>',this.id,'<?php echo "vl_sample_id##" . $vlQueryInfo[0]["vl_sample_id"]; ?>','This sample number already exists.Try another number',null)" />
                                                                 <input type="hidden" name="sampleCodeCol" value="<?php echo $vlQueryInfo[0]['sample_code']; ?>" />
                                                            <?php } ?>
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="">
                                                            <label for="sampleReordered">
                                                                 <input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" <?php echo (trim($vlQueryInfo[0]['sample_reordered']) == 'yes') ? 'checked="checked"' : '' ?> title="Please check sample reordered"> Sample Reordered
                                                            </label>
                                                       </div>
                                                  </div>

                                                  <div class="col-xs-3 col-md-3" style="display:<?php echo ($sCode != '') ? 'block' : 'none'; ?>">
                                                       <div class="">
                                                            <label class="" for="sampleReceivedDate">Date Sample Received at Testing Lab <span class="mandatory">*</span></label><br />
                                                            <input type="text" class="form-control labSection dateTime isRequired" id="sampleReceivedDate<?php echo ($sCode == '') ? 'Lab' : ''; ?>" name="sampleReceivedDate<?php echo ($sCode == '') ? 'Lab' : ''; ?>" placeholder="Sample Received Date" title="Please select sample received date" value="<?php echo ($vlQueryInfo[0]['sample_received_at_vl_lab_datetime'] != '' && $vlQueryInfo[0]['sample_received_at_vl_lab_datetime'] != NULL) ? $vlQueryInfo[0]['sample_received_at_vl_lab_datetime'] : date('d-M-Y H:i:s'); ?>" <?php echo $labFieldDisabled; ?> onchange="checkSampleReceviedDate();" />
                                                       </div>
                                                  </div>

                                             </div>
                                             <div class="row">
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="">
                                                            <label for="province">Province <span class="mandatory">*</span></label>
                                                            <select class="form-control isRequired" name="province" id="province" title="Please choose a province" style="width:100%;" onchange="getProvinceDistricts(this);">
                                                                 <option value=""> -- Select -- </option>
                                                                 <?php foreach ($pdResult as $provinceName) { ?>
                                                                      <option value="<?php echo $provinceName['province_name'] . "##" . $provinceName['province_code']; ?>" <?php echo ($facilityResult[0]['facility_state'] . "##" . $stateResult[0]['province_code'] == $provinceName['province_name'] . "##" . $provinceName['province_code']) ? "selected='selected'" : "" ?>><?php echo ucwords($provinceName['province_name']); ?></option>;
                                                                 <?php } ?>
                                                            </select>
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="">
                                                            <label for="district">District <span class="mandatory">*</span></label>
                                                            <select class="form-control isRequired" name="district" id="district" title="Please choose a district" style="width:100%;" onchange="getFacilities(this);">
                                                                 <option value=""> -- Select -- </option>
                                                                 <?php foreach ($districtResult as $districtName) { ?>
                                                                      <option value="<?php echo $districtName['facility_district']; ?>" <?php echo ($facilityResult[0]['facility_district'] == $districtName['facility_district']) ? "selected='selected'" : "" ?>><?php echo ucwords($districtName['facility_district']); ?></option>
                                                                 <?php } ?>
                                                            </select>
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="">
                                                            <label for="fName">Clinic/Health Center <span class="mandatory">*</span></label>
                                                            <select class="form-control isRequired" id="fName" name="fName" title="Please select clinic/health center name" style="width:100%;" onchange="fillFacilityDetails();">
                                                                 <option data-code="" data-emails="" data-mobile-nos="" value=""> -- Select -- </option>
                                                                 <?php foreach ($fResult as $fDetails) { ?>
                                                                      <option data-code="<?php echo $fDetails['facility_code']; ?>" data-emails="<?php echo $fDetails['facility_emails']; ?>" data-mobile-nos="<?php echo $fDetails['facility_mobile_numbers']; ?>" data-contact-person="<?php echo ucwords($fDetails['contact_person']); ?>" value="<?php echo $fDetails['facility_id']; ?>" <?php echo ($vlQueryInfo[0]['facility_id'] == $fDetails['facility_id']) ? "selected='selected'" : "" ?>><?php echo ucwords($fDetails['facility_name']) . " - " . $fDetails['facility_code']; ?></option>
                                                                 <?php } ?>
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
                                                  <div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson" style="display:<?php echo (trim($facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;"><?php echo ucwords($facilityResult[0]['contact_person']); ?></div>
                                             </div>
                                             <?php if ($sarr['user_type'] == 'remoteuser') { ?>
                                                  <div class="row">
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="">
                                                                 <label for="labId">VL Testing Hub <span class="mandatory">*</span></label>
                                                                 <select name="labId" id="labId" class="form-control isRequired" title="Please choose a VL testing hub">
                                                                      <option value="">-- Select --</option>
                                                                      <?php foreach ($lResult as $labName) { ?>
                                                                           <option value="<?php echo $labName['facility_id']; ?>" <?php echo ($vlQueryInfo[0]['lab_id'] == $labName['facility_id']) ? "selected='selected'" : "" ?>><?php echo ucwords($labName['facility_name']); ?></option>
                                                                      <?php } ?>
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
                                                            <input type="text" name="artNo" id="artNo" class="form-control isRequired" placeholder="Enter ART Number" title="Enter art number" value="<?php echo $vlQueryInfo[0]['patient_art_no']; ?>" />
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="dob">Date of Birth <?php echo ($sarr['user_type'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                            <input type="text" name="dob" id="dob" class="form-control date <?php echo ($sarr['user_type'] == 'remoteuser') ? "isRequired" : ''; ?>" placeholder="Enter DOB" title="Enter dob" value="<?php echo $vlQueryInfo[0]['patient_dob']; ?>" onchange="getAge();checkARTInitiationDate();" />
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="ageInYears">If DOB unknown, Age in Years </label>
                                                            <input type="text" name="ageInYears" id="ageInYears" class="form-control checkNum" maxlength="2" placeholder="Age in Year" title="Enter age in years" value="<?php echo $vlQueryInfo[0]['patient_age_in_years']; ?>" />
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="ageInMonths">If Age
                                                                 < 1, Age in Months </label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control checkNum" maxlength="2" placeholder="Age in Month" title="Enter age in months" value="<?php echo $vlQueryInfo[0]['patient_age_in_months']; ?>" />
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
                                                                 <input type="radio" class="isRequired" id="genderMale" name="gender" value="male" title="Please check gender" <?php echo ($vlQueryInfo[0]['patient_gender'] == 'male') ? "checked='checked'" : "" ?>> Male
                                                            </label>&nbsp;&nbsp;
                                                            <label class="radio-inline" style="margin-left:0px;">
                                                                 <input type="radio" id="genderFemale" name="gender" value="female" title="Please check gender" <?php echo ($vlQueryInfo[0]['patient_gender'] == 'female') ? "checked='checked'" : "" ?>> Female
                                                            </label>
                                                            <!--<label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" id="genderNotRecorded" name="gender" value="not_recorded" title="Please check gender" < ?php echo ($vlQueryInfo[0]['patient_gender']=='not_recorded')?"checked='checked'":""?>>Not Recorded
                                                       </label>-->
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="patientPhoneNumber">Phone Number</label>
                                                            <input type="text" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control checkNum" maxlength="15" placeholder="Enter Phone Number" title="Enter phone number" value="<?php echo $vlQueryInfo[0]['patient_mobile_number']; ?>" />
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
                                                                 <input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select sample collection date" value="<?php echo $vlQueryInfo[0]['sample_collection_date']; ?>" onchange="checkSampleReceviedDate();checkSampleTestingDate();">
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group">
                                                                 <label for="specimenType">Sample Type <span class="mandatory">*</span></label>
                                                                 <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose a sample type">
                                                                      <option value=""> -- Select -- </option>
                                                                      <?php foreach ($sResult as $name) { ?>
                                                                           <option value="<?php echo $name['sample_id']; ?>" <?php echo ($vlQueryInfo[0]['sample_type'] == $name['sample_id']) ? "selected='selected'" : "" ?>><?php echo ucwords($name['sample_name']); ?></option>
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
                                                                      <input type="text" class="form-control date" name="dateOfArtInitiation" id="dateOfArtInitiation" placeholder="Date of Treatment initiation" title="Date of treatment initiation" value="<?php echo $vlQueryInfo[0]['treatment_initiated_date']; ?>" style="width:100%;" onchange="checkARTInitiationDate();">
                                                                 </div>
                                                            </div>
                                                            <div class="col-xs-3 col-md-3">
                                                                 <div class="form-group">
                                                                      <label for="artRegimen">Current Regimen <?php echo ($sarr['user_type'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                      <select class="form-control <?php echo ($sarr['user_type'] == 'remoteuser') ? "isRequired" : ''; ?>" id="artRegimen" name="artRegimen" title="Please choose an ART Regimen" style="width:100%;" onchange="checkARTRegimenValue();">
                                                                           <option value="">-- Select --</option>
                                                                           <?php foreach ($artRegimenResult as $heading) { ?>
                                                                                <optgroup label="<?php echo ucwords($heading['headings']); ?>">
                                                                                     <?php
                                                                                     foreach ($aResult as $regimen) {
                                                                                          if ($heading['headings'] == $regimen['headings']) { ?>
                                                                                               <option value="<?php echo $regimen['art_code']; ?>" <?php echo ($vlQueryInfo[0]['current_regimen'] == $regimen['art_code']) ? "selected='selected'" : "" ?>><?php echo $regimen['art_code']; ?></option>
                                                                                          <?php }
                                                                                } ?>
                                                                                </optgroup>
                                                                           <?php }
                                                                      if ($sarr['user_type'] != 'vluser') {  ?>
                                                                                <!-- <option value="other">Other</option> -->
                                                                           <?php } ?>
                                                                      </select>
                                                                      <input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="ART Regimen" title="Please enter the ART Regimen" style="width:100%;display:none;margin-top:2px;">
                                                                 </div>
                                                            </div>
                                                            <div class="col-xs-3 col-md-3">
                                                                 <div class="form-group">
                                                                      <label for="">Date of Initiation of Current Regimen<?php echo ($sarr['user_type'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                      <input type="text" class="form-control date  <?php echo ($sarr['user_type'] == 'remoteuser') ? "isRequired" : ''; ?>" style="width:100%;" name="regimenInitiatedOn" id="regimenInitiatedOn" placeholder="Current Regimen Initiated On" title="Please enter current regimen initiated on" value="<?php echo $vlQueryInfo[0]['date_of_initiation_of_current_regimen']; ?>">
                                                                 </div>
                                                            </div>
                                                            <div class="col-xs-3 col-md-3">
                                                                 <div class="form-group">
                                                                      <label for="arvAdherence">ARV Adherence <?php echo ($sarr['user_type'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                      <select name="arvAdherence" id="arvAdherence" class="form-control  <?php echo ($sarr['user_type'] == 'remoteuser') ? "isRequired" : ''; ?>" title="Please choose an adherence %">
                                                                           <option value=""> -- Select -- </option>
                                                                           <option value="good" <?php echo ($vlQueryInfo[0]['arv_adherance_percentage'] == 'good') ? "selected='selected'" : "" ?>>Good >= 95%</option>
                                                                           <option value="fair" <?php echo ($vlQueryInfo[0]['arv_adherance_percentage'] == 'fair') ? "selected='selected'" : "" ?>>Fair (85-94%)</option>
                                                                           <option value="poor" <?php echo ($vlQueryInfo[0]['arv_adherance_percentage'] == 'poor') ? "selected='selected'" : "" ?>>Poor < 85%</option> </select> </div> </div> </div> <div class="row femaleSection" style="display:<?php echo ($vlQueryInfo[0]['patient_gender'] == 'female') ? "" : "none" ?>" ;>
                                                                                     <div class="col-xs-3 col-md-3">
                                                                                          <div class="form-group">
                                                                                               <label for="patientPregnant">Is Patient Pregnant? <span class="mandatory">*</span></label><br>
                                                                                               <label class="radio-inline">
                                                                                                    <input type="radio" class="<?php echo ($vlQueryInfo[0]['patient_gender'] == 'female') ? "isRequired" : ""; ?>" id="pregYes" name="patientPregnant" value="yes" title="Please check patient pregnant status" <?php echo ($vlQueryInfo[0]['is_patient_pregnant'] == 'yes') ? "checked='checked'" : "" ?>> Yes
                                                                                               </label>
                                                                                               <label class="radio-inline">
                                                                                                    <input type="radio" class="" id="pregNo" name="patientPregnant" value="no" <?php echo ($vlQueryInfo[0]['is_patient_pregnant'] == 'no') ? "checked='checked'" : "" ?>> No
                                                                                               </label>
                                                                                          </div>
                                                                                     </div>
                                                                                     <div class="col-xs-3 col-md-3">
                                                                                          <div class="form-group">
                                                                                               <label for="breastfeeding">Is Patient Breastfeeding? <span class="mandatory">*</span></label><br>
                                                                                               <label class="radio-inline">
                                                                                                    <input type="radio" class="<?php echo ($vlQueryInfo[0]['patient_gender'] == 'female') ? "isRequired" : ""; ?>" id="breastfeedingYes" name="breastfeeding" value="yes" title="Please check patient breastfeeding status" <?php echo ($vlQueryInfo[0]['is_patient_breastfeeding'] == 'yes') ? "checked='checked'" : "" ?>> Yes
                                                                                               </label>
                                                                                               <label class="radio-inline">
                                                                                                    <input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no" <?php echo ($vlQueryInfo[0]['is_patient_breastfeeding'] == 'no') ? "checked='checked'" : "" ?>> No
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
                                                                                               $vlTestReasonQueryRow = "SELECT * from r_vl_test_reasons where test_reason_id='" . trim($vlQueryInfo[0]['reason_for_vl_testing']) . "' OR test_reason_name = '" . trim($vlQueryInfo[0]['reason_for_vl_testing']) . "'";
                                                                                               $vlTestReasonResultRow = $db->query($vlTestReasonQueryRow);
                                                                                               $checked = '';
                                                                                               $display = '';
                                                                                               $vlValue = '';
                                                                                               if (trim($vlQueryInfo[0]['reason_for_vl_testing']) == 'routine' || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'routine') {
                                                                                                    $checked = 'checked="checked"';
                                                                                                    $display = 'block';
                                                                                                    if ($vlQueryInfo[0]['last_vl_result_routine'] != NULL && trim($vlQueryInfo[0]['last_vl_result_routine']) != '' && trim($vlQueryInfo[0]['last_vl_result_routine']) != '<20' && trim($vlQueryInfo[0]['last_vl_result_routine']) != 'tnd') {
                                                                                                         $vlValue = $vlQueryInfo[0]['last_vl_result_routine'];
                                                                                                    }
                                                                                               } else {
                                                                                                    $checked = '';
                                                                                                    $display = 'none';
                                                                                               }
                                                                                               ?>
                                                                                               <input type="radio" class="isRequired" id="rmTesting" name="stViralTesting" value="routine" title="Please check viral load indication testing type" <?php echo $checked; ?> onclick="showTesting('rmTesting');">
                                                                                               <strong>Routine Monitoring</strong>
                                                                                          </label>
                                                                                     </div>
                                                                                </div>
                                                                           </div>
                                                                      </div>
                                                                      <div class="row rmTesting hideTestData" style="display:<?php echo $display; ?>;">
                                                                           <div class="col-md-6">
                                                                                <label class="col-lg-5 control-label">Date of last viral load test</label>
                                                                                <div class="col-lg-7">
                                                                                     <input type="text" class="form-control date viralTestData" id="rmTestingLastVLDate" name="rmTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim($vlQueryInfo[0]['last_vl_date_routine']) != '' && $vlQueryInfo[0]['last_vl_date_routine'] != null && $vlQueryInfo[0]['last_vl_date_routine'] != '0000-00-00') ? $general->humanDateFormat($vlQueryInfo[0]['last_vl_date_routine']) : ''; ?>" />
                                                                                </div>
                                                                           </div>
                                                                           <div class="col-md-6">
                                                                                <label for="rmTestingVlValue" class="col-lg-3 control-label">VL Value</label>
                                                                                <div class="col-lg-7">
                                                                                     <input type="text" class="form-control checkNum viralTestData" id="rmTestingVlValue" name="rmTestingVlValue" placeholder="Enter VL Value" title="Please enter vl value" <?php echo (($vlQueryInfo[0]['last_vl_result_routine'] == NULL || trim($vlQueryInfo[0]['last_vl_result_routine']) == '') || trim($vlValue) != '') ? '' : 'readonly="readonly"'; ?> value="<?php echo $vlValue; ?>" />
                                                                                     (copies/ml)<br>
                                                                                     <input type="checkbox" id="rmTestingVlCheckValuelt20" name="rmTestingVlCheckValue" <?php echo ($vlQueryInfo[0]['last_vl_result_routine'] == '<20') ? 'checked="checked"' : ''; ?> value="<20" <?php echo (($vlQueryInfo[0]['last_vl_result_routine'] == NULL || trim($vlQueryInfo[0]['last_vl_result_routine']) == '') || trim($vlQueryInfo[0]['last_vl_result_routine']) == '<20') ? '' : 'disabled="disabled"'; ?> title="Please check VL value">
                                                                                     < 20<br>
                                                                                          <input type="checkbox" id="rmTestingVlCheckValueTnd" name="rmTestingVlCheckValue" <?php echo ($vlQueryInfo[0]['last_vl_result_routine'] == 'tnd') ? 'checked="checked"' : ''; ?> value="tnd" <?php echo (($vlQueryInfo[0]['last_vl_result_routine'] == NULL || trim($vlQueryInfo[0]['last_vl_result_routine']) == '') || trim($vlQueryInfo[0]['last_vl_result_routine']) == 'tnd') ? '' : 'disabled="disabled"'; ?> title="Please check VL value"> Target Not Detected
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
                                                                                               if (trim($vlQueryInfo[0]['reason_for_vl_testing']) == 'failure' || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'failure') {
                                                                                                    $checked = 'checked="checked"';
                                                                                                    $display = 'block';
                                                                                                    if ($vlQueryInfo[0]['last_vl_result_failure_ac'] != NULL && trim($vlQueryInfo[0]['last_vl_result_failure_ac']) != '' && trim($vlQueryInfo[0]['last_vl_result_failure_ac']) != '<20' && trim($vlQueryInfo[0]['last_vl_result_failure_ac']) != 'tnd') {
                                                                                                         $vlValue = $vlQueryInfo[0]['last_vl_result_failure_ac'];
                                                                                                    }
                                                                                               } else {
                                                                                                    $checked = '';
                                                                                                    $display = 'none';
                                                                                               }
                                                                                               ?>
                                                                                               <input type="radio" id="repeatTesting" name="stViralTesting" value="failure" title="Please check viral load indication testing type" <?php echo $checked; ?> onclick="showTesting('repeatTesting');">
                                                                                               <strong>Repeat VL test after suspected treatment failure adherence counselling </strong>
                                                                                          </label>
                                                                                     </div>
                                                                                </div>
                                                                           </div>
                                                                      </div>
                                                                      <div class="row repeatTesting hideTestData" style="display: <?php echo $display; ?>;">
                                                                           <div class="col-md-6">
                                                                                <label class="col-lg-5 control-label">Date of last viral load test</label>
                                                                                <div class="col-lg-7">
                                                                                     <input type="text" class="form-control date viralTestData" id="repeatTestingLastVLDate" name="repeatTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim($vlQueryInfo[0]['last_vl_date_failure_ac']) != '' && $vlQueryInfo[0]['last_vl_date_failure_ac'] != null && $vlQueryInfo[0]['last_vl_date_failure_ac'] != '0000-00-00') ? $general->humanDateFormat($vlQueryInfo[0]['last_vl_date_failure_ac']) : ''; ?>" />
                                                                                </div>
                                                                           </div>
                                                                           <div class="col-md-6">
                                                                                <label for="repeatTestingVlValue" class="col-lg-3 control-label">VL Value</label>
                                                                                <div class="col-lg-7">
                                                                                     <input type="text" class="form-control checkNum viralTestData" id="repeatTestingVlValue" name="repeatTestingVlValue" placeholder="Enter VL Value" title="Please enter vl value" <?php echo (($vlQueryInfo[0]['last_vl_result_failure_ac'] == NULL || trim($vlQueryInfo[0]['last_vl_result_failure_ac']) == '') || trim($vlValue) != '') ? '' : 'readonly="readonly"'; ?> value="<?php echo $vlValue; ?>" />
                                                                                     (copies/ml)<br>
                                                                                     <input type="checkbox" id="repeatTestingVlCheckValuelt20" name="repeatTestingVlCheckValue" <?php echo ($vlQueryInfo[0]['last_vl_result_failure_ac'] == '<20') ? 'checked="checked"' : ''; ?> value="<20" <?php echo (($vlQueryInfo[0]['last_vl_result_failure_ac'] == NULL || trim($vlQueryInfo[0]['last_vl_result_failure_ac']) == '') || trim($vlQueryInfo[0]['last_vl_result_failure_ac']) == '<20') ? '' : 'disabled="disabled"'; ?> title="Please check VL value">
                                                                                     < 20<br>
                                                                                          <input type="checkbox" id="repeatTestingVlCheckValueTnd" name="repeatTestingVlCheckValue" <?php echo ($vlQueryInfo[0]['last_vl_result_failure_ac'] == 'tnd') ? 'checked="checked"' : ''; ?> value="tnd" <?php echo (($vlQueryInfo[0]['last_vl_result_failure_ac'] == NULL || trim($vlQueryInfo[0]['last_vl_result_failure_ac']) == '') || trim($vlQueryInfo[0]['last_vl_result_failure_ac']) == 'tnd') ? '' : 'disabled="disabled"'; ?> title="Please check VL value"> Target Not Detected
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
                                                                                               if (trim($vlQueryInfo[0]['reason_for_vl_testing']) == 'suspect' || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'suspect') {
                                                                                                    $checked = 'checked="checked"';
                                                                                                    $display = 'block';
                                                                                                    if ($vlQueryInfo[0]['last_vl_result_failure'] != NULL && trim($vlQueryInfo[0]['last_vl_result_failure']) != '' && trim($vlQueryInfo[0]['last_vl_result_failure']) != '<20' && trim($vlQueryInfo[0]['last_vl_result_failure']) != 'tnd') {
                                                                                                         $vlValue = $vlQueryInfo[0]['last_vl_result_failure'];
                                                                                                    }
                                                                                               } else {
                                                                                                    $checked = '';
                                                                                                    $display = 'none';
                                                                                               }
                                                                                               ?>
                                                                                               <input type="radio" id="suspendTreatment" name="stViralTesting" value="suspect" title="Please check viral load indication testing type" <?php echo $checked; ?> onclick="showTesting('suspendTreatment');">
                                                                                               <strong>Suspect Treatment Failure</strong>
                                                                                          </label>
                                                                                     </div>
                                                                                </div>
                                                                           </div>
                                                                      </div>
                                                                      <div class="row suspendTreatment hideTestData" style="display: <?php echo $display; ?>;">
                                                                           <div class="col-md-6">
                                                                                <label class="col-lg-5 control-label">Date of last viral load test</label>
                                                                                <div class="col-lg-7">
                                                                                     <input type="text" class="form-control date viralTestData" id="suspendTreatmentLastVLDate" name="suspendTreatmentLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim($vlQueryInfo[0]['last_vl_date_failure']) != '' && $vlQueryInfo[0]['last_vl_date_failure'] != null && $vlQueryInfo[0]['last_vl_date_failure'] != '0000-00-00') ? $general->humanDateFormat($vlQueryInfo[0]['last_vl_date_failure']) : ''; ?>" />
                                                                                </div>
                                                                           </div>
                                                                           <div class="col-md-6">
                                                                                <label for="suspendTreatmentVlValue" class="col-lg-3 control-label">VL Value</label>
                                                                                <div class="col-lg-7">
                                                                                     <input type="text" class="form-control checkNum viralTestData" id="suspendTreatmentVlValue" name="suspendTreatmentVlValue" placeholder="Enter VL Value" title="Please enter vl value" <?php echo (($vlQueryInfo[0]['last_vl_result_failure'] == NULL || trim($vlQueryInfo[0]['last_vl_result_failure']) == '') || trim($vlValue) != '') ? '' : 'readonly="readonly"'; ?> value="<?php echo $vlValue; ?>" />
                                                                                     (copies/ml)<br>
                                                                                     <input type="checkbox" id="suspendTreatmentVlCheckValuelt20" name="suspendTreatmentVlCheckValue" <?php echo ($vlQueryInfo[0]['last_vl_result_failure'] == '<20') ? 'checked="checked"' : ''; ?> value="<20" <?php echo (($vlQueryInfo[0]['last_vl_result_failure'] == NULL || trim($vlQueryInfo[0]['last_vl_result_failure']) == '') || trim($vlQueryInfo[0]['last_vl_result_failure']) == '<20') ? '' : 'disabled="disabled"'; ?> title="Please check VL value">
                                                                                     < 20<br>
                                                                                          <input type="checkbox" id="suspendTreatmentVlCheckValueTnd" name="suspendTreatmentVlCheckValue" <?php echo ($vlQueryInfo[0]['last_vl_result_failure'] == 'tnd') ? 'checked="checked"' : ''; ?> value="tnd" <?php echo (($vlQueryInfo[0]['last_vl_result_failure'] == NULL || trim($vlQueryInfo[0]['last_vl_result_failure']) == '') || trim($vlQueryInfo[0]['last_vl_result_failure']) == 'tnd') ? '' : 'disabled="disabled"'; ?> title="Please check VL value"> Target Not Detected
                                                                                </div>
                                                                           </div>
                                                                      </div>
                                                                      <div class="row">
                                                                           <div class="col-md-4">
                                                                                <label for="reqClinician" class="col-lg-5 control-label">Request Clinician <?php echo ($sarr['user_type'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                                <div class="col-lg-7">
                                                                                     <input type="text" class="form-control  <?php echo ($sarr['user_type'] == 'remoteuser') ? "isRequired" : ''; ?>" id="reqClinician" name="reqClinician" placeholder="Request Clinician" title="Please enter request clinician" value="<?php echo $vlQueryInfo[0]['request_clinician_name']; ?>" />
                                                                                </div>
                                                                           </div>
                                                                           <div class="col-md-4">
                                                                                <label for="reqClinicianPhoneNumber" class="col-lg-5 control-label">Phone Number <?php echo ($sarr['user_type'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                                <div class="col-lg-7">
                                                                                     <input type="text" class="form-control checkNum  <?php echo ($sarr['user_type'] == 'remoteuser') ? "isRequired" : ''; ?>" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter request clinician phone number" value="<?php echo $vlQueryInfo[0]['request_clinician_phone_number']; ?>" />
                                                                                </div>
                                                                           </div>
                                                                           <div class="col-md-4">
                                                                                <label class="col-lg-5 control-label" for="requestDate">Request Date <?php echo ($sarr['user_type'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                                <div class="col-lg-7">
                                                                                     <input type="text" class="form-control date  <?php echo ($sarr['user_type'] == 'remoteuser') ? "isRequired" : ''; ?>" id="requestDate" name="requestDate" placeholder="Request Date" title="Please select request date" value="<?php echo $vlQueryInfo[0]['test_requested_on']; ?>" />
                                                                                </div>
                                                                           </div>
                                                                      </div>
                                                                      <div class="row">
                                                                           <div class="col-md-4">
                                                                                <label for="vlFocalPerson" class="col-lg-5 control-label">VL Focal Person<?php echo ($sarr['user_type'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                                <div class="col-lg-7">
                                                                                     <input type="text" class="form-control  <?php echo ($sarr['user_type'] == 'remoteuser') ? "isRequired" : ''; ?>" id="vlFocalPerson" name="vlFocalPerson" placeholder="VL Focal Person" title="Please enter vl focal person name" value="<?php echo $vlQueryInfo[0]['vl_focal_person']; ?>" />
                                                                                </div>
                                                                           </div>
                                                                           <div class="col-md-4">
                                                                                <label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label">VL Focal Person Phone Number <?php echo ($sarr['user_type'] == 'remoteuser') ? "<span class='mandatory'>*</span>" : ''; ?></label>
                                                                                <div class="col-lg-7">
                                                                                     <input type="text" class="form-control checkNum  <?php echo ($sarr['user_type'] == 'remoteuser') ? "isRequired" : ''; ?>" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter vl focal person phone number" value="<?php echo $vlQueryInfo[0]['vl_focal_person_phone_number']; ?>" />
                                                                                </div>
                                                                           </div>
                                                                           <div class="col-md-4">
                                                                                <label class="col-lg-5 control-label" for="emailHf">Email for HF </label>
                                                                                <div class="col-lg-7">
                                                                                     <input type="text" class="form-control isEmail" id="emailHf" name="emailHf" placeholder="Email for HF" title="Please enter email for hf" value="<?php echo $facilityResult[0]['facility_emails']; ?>" />
                                                                                </div>
                                                                           </div>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <?php if ($sarr['user_type'] != 'remoteuser') { ?>
                                                                 <div class="box box-primary">
                                                                      <div class="box-header with-border">
                                                                           <h3 class="box-title">Laboratory Information</h3>
                                                                      </div>
                                                                      <div class="box-body">
                                                                           <div class="row">
                                                                                <div class="col-md-4">
                                                                                     <label for="labId" class="col-lg-5 control-label">Lab Name </label>
                                                                                     <div class="col-lg-7">
                                                                                          <select name="labId" id="labId" class="form-control labSection" title="Please choose lab">
                                                                                               <option value="">-- Select --</option>
                                                                                               <?php foreach ($lResult as $labName) { ?>
                                                                                                    <option value="<?php echo $labName['facility_id']; ?>" <?php echo ($vlQueryInfo[0]['lab_id'] == $labName['facility_id']) ? "selected='selected'" : "" ?>><?php echo ucwords($labName['facility_name']); ?></option>
                                                                                               <?php } ?>
                                                                                          </select>
                                                                                     </div>
                                                                                </div>
                                                                                <div class="col-md-4">
                                                                                     <label for="testingPlatform" class="col-lg-5 control-label">VL Testing Platform </label>
                                                                                     <div class="col-lg-7">
                                                                                          <select name="testingPlatform" id="testingPlatform" class="form-control labSection" title="Please choose VL Testing Platform" <?php echo $labFieldDisabled; ?>>
                                                                                               <option value="">-- Select --</option>
                                                                                               <?php foreach ($importResult as $mName) { ?>
                                                                                                    <option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']; ?>" <?php echo ($vlQueryInfo[0]['vl_test_platform'] == $mName['machine_name']) ? 'selected="selected"' : ''; ?>><?php echo $mName['machine_name']; ?></option>
                                                                                               <?php } ?>
                                                                                          </select>
                                                                                     </div>
                                                                                </div>
                                                                           </div>
                                                                           <div class="row">
                                                                                <div class="col-md-4" style="display:<?php echo ($sCode != '') ? 'none' : 'block'; ?>">
                                                                                     <label class="col-lg-5 control-label" for="sampleReceivedDate">Date Sample Received at Testing Lab </label>
                                                                                     <div class="col-lg-7">
                                                                                          <input type="text" class="form-control labSection dateTime" id="sampleReceivedDate<?php echo ($sCode != '') ? 'Lab' : ''; ?>" name="sampleReceivedDate<?php echo ($sCode != '') ? 'Lab' : ''; ?>" placeholder="Sample Received Date" title="Please select sample received date" value="<?php echo $vlQueryInfo[0]['sample_received_at_vl_lab_datetime']; ?>" <?php echo $labFieldDisabled; ?> onchange="checkSampleReceviedDate();" />
                                                                                     </div>
                                                                                </div>
                                                                                <div class="col-md-4">
                                                                                     <label class="col-lg-5 control-label" for="sampleTestingDateAtLab">Sample Testing Date </label>
                                                                                     <div class="col-lg-7">
                                                                                          <input type="text" class="form-control labSection dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Sample Testing Date" title="Please select sample testing date" value="<?php echo $vlQueryInfo[0]['sample_tested_datetime']; ?>" <?php echo $labFieldDisabled; ?> onchange="checkSampleTestingDate();" />
                                                                                     </div>
                                                                                </div>
                                                                                <div class="col-md-4">
                                                                                     <label class="col-lg-5 control-label" for="resultDispatchedOn">Date Results Dispatched </label>
                                                                                     <div class="col-lg-7">
                                                                                          <input type="text" class="form-control labSection dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatched Date" title="Please select result dispatched date" value="<?php echo $vlQueryInfo[0]['result_dispatched_datetime']; ?>" <?php echo $labFieldDisabled; ?> />
                                                                                     </div>
                                                                                </div>
                                                                           </div>
                                                                           <div class="row">
                                                                                <div class="col-md-4">
                                                                                     <label class="col-lg-5 control-label" for="noResult">Sample Rejection </label>
                                                                                     <div class="col-lg-7">
                                                                                          <label class="radio-inline">
                                                                                               <input class="labSection" id="noResultYes" name="noResult" value="yes" title="Please check one" type="radio" <?php echo ($vlQueryInfo[0]['is_sample_rejected'] == 'yes') ? 'checked="checked"' : ''; ?> <?php echo $labFieldDisabled; ?>> Yes
                                                                                          </label>
                                                                                          <label class="radio-inline">
                                                                                               <input class="labSection" id="noResultNo" name="noResult" value="no" title="Please check one" type="radio" <?php echo ($vlQueryInfo[0]['is_sample_rejected'] == 'no') ? 'checked="checked"' : ''; ?> <?php echo $labFieldDisabled; ?>> No
                                                                                          </label>
                                                                                     </div>
                                                                                </div>
                                                                                <div class="col-md-4 rejectionReason" style="display:<?php echo ($vlQueryInfo[0]['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
                                                                                     <label class="col-lg-5 control-label" for="rejectionReason">Rejection Reason <span class="mandatory">*</span></label>
                                                                                     <div class="col-lg-7">
                                                                                          <select name="rejectionReason" id="rejectionReason" class="form-control labSection" title="Please choose a Rejection Reason" <?php echo $labFieldDisabled; ?> onchange="checkRejectionReason();">
                                                                                               <option value="">-- Select --</option>
                                                                                               <?php foreach ($rejectionTypeResult as $type) { ?>
                                                                                                    <optgroup label="<?php echo ucwords($type['rejection_type']); ?>">
                                                                                                         <?php
                                                                                                         foreach ($rejectionResult as $reject) {
                                                                                                              if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                                                                                   <option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo[0]['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ucwords($reject['rejection_reason_name']); ?></option>
                                                                                                              <?php }
                                                                                                    } ?>
                                                                                                    </optgroup>
                                                                                               <?php }
                                                                                          if ($sarr['user_type'] != 'vluser') {  ?>
                                                                                                    <option value="other">Other (Please Specify) </option>
                                                                                               <?php } ?>
                                                                                          </select>
                                                                                          <input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="Rejection Reason" title="Please enter rejection reason" style="width:100%;display:none;margin-top:2px;">
                                                                                     </div>
                                                                                </div>
                                                                                <div class="col-md-4 vlResult" style="visibility:<?php echo ($vlQueryInfo[0]['is_sample_rejected'] == 'yes') ? 'hidden' : 'visible'; ?>;">
                                                                                     <label class="col-lg-5 control-label" for="vlResult">Viral Load Result (copiesl/ml) </label>
                                                                                     <div class="col-lg-7">
                                                                                          <input type="text" class="form-control labSection" id="vlResult" name="vlResult" placeholder="Viral Load Result" title="Please enter viral load result" value="<?php echo $vlQueryInfo[0]['result_value_absolute']; ?>" <?php echo ($vlQueryInfo[0]['result'] == 'Target Not Detected' || $vlQueryInfo[0]['result'] == 'Below Detection Level') ? 'readonly="readonly"' : $labFieldDisabled; ?> style="width:100%;" onchange="calculateLogValue(this);" />
                                                                                          <input type="checkbox" class="labSection specialResults" id="lt20" name="lt20" value="yes" <?php echo ($vlQueryInfo[0]['result'] == '<20') ? 'checked="checked"' : '';
                                                                                                                                                                                         echo ($vlQueryInfo[0]['result'] == '<20' || $vlQueryInfo[0]['result'] == '< 20') ? 'disabled="disabled"' : $labFieldDisabled; ?> title="Please check <20">
                                                                                          <20<br>
                                                                                               <input type="checkbox" class="labSection specialResults" id="tnd" name="tnd" value="yes" <?php echo ($vlQueryInfo[0]['result'] == 'Target Not Detected') ? 'checked="checked"' : '';
                                                                                                                                                                                         echo ($vlQueryInfo[0]['result'] == 'Below Detection Level') ? 'disabled="disabled"' : $labFieldDisabled; ?> title="Please check tnd"> Target Not Detected<br>
                                                                                               <input type="checkbox" class="labSection specialResults" id="bdl" name="bdl" value="yes" <?php echo ($vlQueryInfo[0]['result'] == 'Below Detection Level') ? 'checked="checked"' : '';
                                                                                                                                                                                         echo ($vlQueryInfo[0]['result'] == 'Target Not Detected') ? 'disabled="disabled"' : $labFieldDisabled; ?> title="Please check bdl"> Below Detection Level
                                                                                     </div>
                                                                                </div>
                                                                                <div class="col-md-4 vlResult" style="visibility:<?php echo ($vlQueryInfo[0]['is_sample_rejected'] == 'yes') ? 'hidden' : 'visible'; ?>;">
                                                                                     <label class="col-lg-5 control-label" for="vlLog">Viral Load Log </label>
                                                                                     <div class="col-lg-7">
                                                                                          <input type="text" class="form-control labSection" id="vlLog" name="vlLog" placeholder="Viral Load Log" title="Please enter viral load log" value="<?php echo $vlQueryInfo[0]['result_value_log']; ?>" <?php echo ($vlQueryInfo[0]['result'] == 'Target Not Detected' || $vlQueryInfo[0]['result'] == 'Below Detection Level') ? 'readonly="readonly"' : $labFieldDisabled; ?> style="width:100%;" onchange="calculateLogValue(this);" />
                                                                                     </div>
                                                                                </div>
                                                                           </div>
                                                                           <div class="row">
                                                                                <div class="col-md-4">
                                                                                     <label class="col-lg-5 control-label" for="approvedBy">Approved By </label>
                                                                                     <div class="col-lg-7">
                                                                                          <select name="approvedBy" id="approvedBy" class="form-control labSection" title="Please choose approved by" <?php echo $labFieldDisabled; ?>>
                                                                                               <option value="">-- Select --</option>
                                                                                               <?php foreach ($userResult as $uName) { ?>
                                                                                                    <option value="<?php echo $uName['user_id']; ?>" <?php echo ($vlQueryInfo[0]['result_approved_by'] == $uName['user_id']) ? "selected=selected" : ""; ?>><?php echo ucwords($uName['user_name']); ?></option>
                                                                                               <?php } ?>
                                                                                          </select>
                                                                                     </div>
                                                                                </div>
                                                                                <div class="col-md-8">
                                                                                     <label class="col-lg-2 control-label" for="labComments">Laboratory Scientist Comments </label>
                                                                                     <div class="col-lg-10">
                                                                                          <textarea class="form-control labSection" name="labComments" id="labComments" placeholder="Lab comments" <?php echo $labFieldDisabled; ?> style="width:100%"><?php echo trim($vlQueryInfo[0]['approver_comments']); ?></textarea>
                                                                                     </div>
                                                                                </div>
                                                                           </div>
                                                                           <div class="row">
                                                                                <div class="col-md-4" style="<?php echo ((($sarr['user_type'] == 'remoteuser') && $vlQueryInfo[0]['result_status'] == 9) || ($sCode != '')) ? 'display:none;' : ''; ?>">
                                                                                     <label class="col-lg-5 control-label" for="status">Status </label>
                                                                                     <div class="col-lg-7">
                                                                                          <select class="form-control labSection <?php echo ($sarr['user_type'] == 'remoteuser') ? '' : ''; ?>" id="status" name="status" title="Please select test status" <?php echo $labFieldDisabled; ?>>
                                                                                               <option value="">-- Select --</option>
                                                                                               <?php foreach ($statusResult as $status) { ?>
                                                                                                    <option value="<?php echo $status['status_id']; ?>" <?php echo ($vlQueryInfo[0]['result_status'] == $status['status_id']) ? 'selected="selected"' : ''; ?>><?php echo ucwords($status['status_name']); ?></option>
                                                                                               <?php } ?>
                                                                                          </select>
                                                                                     </div>
                                                                                </div>
                                                                                <div class="col-md-8 reasonForResultChanges" style="visibility:hidden;">
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
                                                            <input type="hidden" name="vlSampleId" id="vlSampleId" value="<?php echo $vlQueryInfo[0]['vl_sample_id']; ?>" />
                                                            <input type="hidden" name="isRemoteSample" value="<?php echo $vlQueryInfo[0]['remote_sample']; ?>" />
                                                            <input type="hidden" name="reasonForResultChangesHistory" id="reasonForResultChangesHistory" value="<?php echo $vlQueryInfo[0]['reason_for_vl_result_changes']; ?>" />
                                                            <input type="hidden" name="oldStatus" value="<?php echo $vlQueryInfo[0]['result_status']; ?>" />
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
          $('#fName').select2({
               placeholder: "Select Clinic/Health Center"
          });
          getAge();
          __clone = $("#vlRequestFormRwd .labSection").clone();
          reason = ($("#reasonForResultChanges").length) ? $("#reasonForResultChanges").val() : '';
          result = ($("#vlResult").length) ? $("#vlResult").val() : '';
          //logVal = ($("#vlLog").length)?$("#vlLog").val():'';

          $("#vlResult").on('keyup keypress blur change paste', function() {
               if ($('#vlResult').val() != '') {
                    if ($('#vlResult').val() != $('#vlResult').val().replace(/[^\d\.]/g, "")) {
                         $('#vlResult').val('');
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
               if (provinceName) {
                    $.post("../includes/getFacilityForClinic.php", {
                              pName: pName
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
               }

          } else if (pName == '' && cName == '') {
               provinceName = true;
               facilityName = true;
               $("#province").html("<?php echo $province; ?>");
               $("#fName").html("<option data-code='' data-emails='' data-mobile-nos='' data-contact-person='' value=''> -- Select -- </option>");
          }
          $.unblockUI();
     }

     function getFacilities(obj) {
          $.blockUI();
          var dName = $("#district").val();
          var cName = $("#fName").val();
          if (dName != '') {
               $.post("../includes/getFacilityForClinic.php", {
                         dName: dName,
                         cliName: cName
                    },
                    function(data) {
                         if (data != "") {
                              details = data.split("###");
                              $("#fName").html(details[0]);
                              $("#labId").html(details[1]);
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
               $('.vlResult').css('visibility', 'hidden');
               $('#rejectionReason').addClass('isRequired');
               $('#vlResult').removeClass('isRequired');
          } else {
               $('.vlResult').css('visibility', 'visible');
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

     $("#vlRequestFormRwd .labSection").on("change", function() {
          if ($.trim(result) != '') {
               if ($("#vlRequestFormRwd .labSection").serialize() == $(__clone).serialize()) {
                    $(".reasonForResultChanges").css("visibility", "hidden");
                    $("#reasonForResultChanges").removeClass("isRequired");
               } else {
                    $(".reasonForResultChanges").css("visibility", "visible");
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
               if (absValue != '' && absValue != 0 && !isNaN(absValue)) {
                    $("#vlLog").val(Math.round(Math.log10(absValue) * 100) / 100);
               } else {
                    $("#vlLog").val('');
               }
          }
          if (obj.id == "vlLog") {
               logValue = $("#vlLog").val();
               if (logValue != '' && logValue != 0 && !isNaN(logValue)) {
                    var absVal = Math.round(Math.pow(10, logValue) * 100) / 100;
                    if (absVal != 'Infinity') {
                         $("#vlResult").val(Math.round(Math.pow(10, logValue) * 100) / 100);
                    }
               } else {
                    $("#vlResult").val('');
               }
          }
     }

     function validateNow() {
          flag = deforayValidator.init({
               formId: 'vlRequestFormRwd'
          });

          $('.isRequired').each(function() {
               ($(this).val() == '') ? $(this).css('background-color', '#FFFF99'): $(this).css('background-color', '#FFFFFF')
          });
          var userType = "<?php echo $sarr['user_type']; ?>";
          if (userType != 'remoteuser') {
               if ($.trim($("#dob").val()) == '' && $.trim($("#ageInYears").val()) == '' && $.trim($("#ageInMonths").val()) == '') {
                    alert("Please make sure enter DOB or Age");
                    return false;
               }
          }
          if (flag) {
               $.blockUI();
               document.getElementById('vlRequestFormRwd').submit();
          }
     }
</script>