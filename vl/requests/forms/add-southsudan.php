<?php
ob_start();

//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);

$lResult = $facilitiesDb->getTestingLabs('vl', true, true);

if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'alphanumeric' || $arr['sample_code'] == 'MMYY' || $arr['sample_code'] == 'YY') {
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
$rKey = '';
$pdQuery = "SELECT * FROM province_details";
if ($_SESSION['accessType'] == 'collection-site') {
     $sampleCodeKey = 'remote_sample_code_key';
     $sampleCode = 'remote_sample_code';
     $rKey = 'R';
} else {
     $sampleCodeKey = 'sample_code_key';
     $sampleCode = 'sample_code';
     $rKey = '';
}
//check user exist in user_facility_map table
$chkUserFcMapQry = "Select user_id from vl_user_facility_map where user_id='" . $_SESSION['userId'] . "'";
$chkUserFcMapResult = $db->query($chkUserFcMapQry);
if ($chkUserFcMapResult) {
     $pdQuery = "SELECT * FROM province_details as pd JOIN facility_details as fd ON fd.facility_state=pd.province_name JOIN vl_user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where user_id='" . $_SESSION['userId'] . "'";
}
$pdResult = $db->query($pdQuery);
$province = '';
$province .= "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
     $province .= "<option value='" . $provinceName['province_name'] . "##" . $provinceName['province_code'] . "'>" . ucwords($provinceName['province_name']) . "</option>";
}
$facility = $general->generateSelectOptions($healthFacilities, null, '-- Select --');
//regimen heading
$artRegimenQuery = "SELECT DISTINCT headings FROM r_vl_art_regimen";
$artRegimenResult = $db->rawQuery($artRegimenQuery);
$aQuery = "SELECT * FROM r_vl_art_regimen where art_status ='active'";
$aResult = $db->query($aQuery);

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
          <h1><i class="fa fa-edit"></i> VIRAL LOAD LABORATORY REQUEST FORM </h1>
          <ol class="breadcrumb">
               <li><a href="/dashboard/index.php"><i class="fa fa-dashboard"></i> Home</a></li>
               <li class="active">Add Vl Request</li>
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
                    <form class="form-inline" method="post" name="vlRequestFormSs" id="vlRequestFormSs" autocomplete="off" action="addVlRequestHelper.php">
                         <div class="box-body">
                              <div class="box box-primary">
                                   <div class="box-header with-border">
                                        <h3 class="box-title">Clinic Information: (To be filled by requesting Clinican/Nurse)</h3>
                                   </div>
                                   <div class="box-body">
                                        <div class="row">
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="sampleCode">Sample ID <span class="mandatory">*</span></label>
                                                       <input type="text" class="form-control isRequired <?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" <?php echo $maxLength; ?> placeholder="Enter Sample ID" title="Please enter sample id" style="width:100%;" readonly onblur="checkSampleNameValidation('vl_request_form','<?php echo $sampleCode; ?>',this.id,null,'This sample number already exists.Try another number',null)" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="sampleReordered">
                                                            <input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" title="Please check sample reordered"> Sample Reordered
                                                       </label>
                                                  </div>
                                             </div>
                                             <!-- BARCODESTUFF START -->
                                             <?php if (isset($global['bar_code_printing']) && $global['bar_code_printing'] != "off") { ?>
                                                  <div class="col-xs-4 col-md-4 pull-right">
                                                       <div class="form-group">
                                                            <label for="sampleCode">Print Barcode Label<span class="mandatory">*</span> </label>
                                                            <input type="checkbox" class="" id="printBarCode" name="printBarCode" checked />
                                                       </div>
                                                  </div>
                                             <?php } ?>
                                             <!-- BARCODESTUFF END -->
                                        </div>
                                        <div class="row">
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="province">State/Province <span class="mandatory">*</span></label>
                                                       <select class="form-control isRequired" name="province" id="province" title="Please choose state" style="width:100%;" onchange="getProvinceDistricts(this);">
                                                            <?php echo $province; ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="district">District/County <span class="mandatory">*</span></label>
                                                       <select class="form-control isRequired" name="district" id="district" title="Please choose county" style="width:100%;" onchange="getFacilities(this);">
                                                            <option value=""> -- Select -- </option>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="fName">Clinic/Health Center <span class="mandatory">*</span></label>
                                                       <select class="form-control isRequired" id="fName" name="fName" title="Please select clinic/health center name" style="width:100%;" onchange="fillFacilityDetails();">
                                                            <?php echo $facility;  ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3" style="display:none;">
                                                  <div class="form-group">
                                                       <label for="fCode">Clinic/Health Center Code </label>
                                                       <input type="text" class="form-control" style="width:100%;" name="fCode" id="fCode" placeholder="Clinic/Health Center Code" title="Please enter clinic/health center code">
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row facilityDetails" style="display:none;">
                                             <div class="col-xs-2 col-md-2 femails" style="display:none;"><strong>Clinic Email(s) -</strong></div>
                                             <div class="col-xs-2 col-md-2 femails facilityEmails" style="display:none;"></div>
                                             <div class="col-xs-2 col-md-2 fmobileNumbers" style="display:none;"><strong>Clinic Mobile No.(s) -</strong></div>
                                             <div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers" style="display:none;"></div>
                                             <div class="col-xs-2 col-md-2 fContactPerson" style="display:none;"><strong>Clinic Contact Person -</strong></div>
                                             <div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson" style="display:none;"></div>
                                        </div>
                                        <div class="row">
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="implementingPartner">Implementing Partner</label>
                                                       <select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose implementing partner" style="width:100%;">
                                                            <option value=""> -- Select -- </option>
                                                            <?php
                                                            foreach ($implementingPartnerList as $implementingPartner) {
                                                            ?>
                                                                 <option value="<?php echo base64_encode($implementingPartner['i_partner_id']); ?>"><?php echo ucwords($implementingPartner['i_partner_name']); ?></option>
                                                            <?php } ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="fundingSource">Funding Source</label>
                                                       <select class="form-control" name="fundingSource" id="fundingSource" title="Please choose implementing partner" style="width:100%;">
                                                            <option value=""> -- Select -- </option>
                                                            <?php
                                                            foreach ($fundingSourceList as $fundingSource) {
                                                            ?>
                                                                 <option value="<?php echo base64_encode($fundingSource['funding_source_id']); ?>"><?php echo ucwords($fundingSource['funding_source_name']); ?></option>
                                                            <?php } ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
                                                  <div class="col-md-4 col-md-4">
                                                       <label for="labId">Lab Name </label>
                                                       <select name="labId" id="labId" class="form-control" title="Please choose lab" onchange="autoFillFocalDetails();" style="width:100%;">
                                                            <option value="">-- Select --</option>
                                                            <?php foreach ($lResult as $labName) { ?>
                                                                 <option data-focalperson="<?php echo $labName['contact_person']; ?>" data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>" value="<?php echo $labName['facility_id']; ?>"><?php echo ucwords($labName['facility_name']); ?></option>
                                                            <?php } ?>
                                                       </select>
                                                  </div>
                                             <?php } ?>
                                        </div>
                                   </div>
                              </div>
                              <div class="box box-primary">
                                   <div class="box-header with-border">
                                        <h3 class="box-title">Patient Information</h3>&nbsp;&nbsp;&nbsp;
                                        <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="" placeholder="Enter ART Number or Patient Name" title="Enter art number or patient name" />&nbsp;&nbsp;
                                        <a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><i class="fa fa-search">&nbsp;</i>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><b>&nbsp;No Patient Found</b></span>
                                   </div>
                                   <div class="box-body">
                                        <div class="row">
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="artNo">ART (TRACNET) No. <span class="mandatory">*</span></label>
                                                       <input type="text" name="artNo" id="artNo" class="form-control isRequired" placeholder="Enter ART Number" title="Enter art number" onchange="checkPatientDetails('vl_request_form','patient_art_no',this,null)" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="dob">Date of Birth </label>
                                                       <input type="text" name="dob" id="dob" class="form-control date" placeholder="Enter DOB" title="Enter dob" onchange="getAge();checkARTInitiationDate();" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="ageInYears">If DOB unknown, Age in Years </label>
                                                       <input type="text" name="ageInYears" id="ageInYears" class="form-control checkNum" maxlength="2" placeholder="Age in Year" title="Enter age in years" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="ageInMonths">If Age
                                                            < 1, Age in Months </label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control checkNum" maxlength="2" placeholder="Age in Month" title="Enter age in months" />
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row">
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="patientFirstName">Patient Name (First Name, Last Name) </label>
                                                       <input type="text" name="patientFirstName" id="patientFirstName" class="form-control" placeholder="Enter Patient Name" title="Enter patient name" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="gender">Gender</label><br>
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="genderMale" name="gender" value="male" title="Please check gender">Male
                                                       </label>
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="genderFemale" name="gender" value="female" title="Please check gender">Female
                                                       </label>
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="genderNotRecorded" name="gender" value="not_recorded" title="Please check gender">Not Recorded
                                                       </label>
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="gender">Patient consent to receive SMS?</label><br>
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="receivesmsYes" name="receiveSms" value="yes" title="Patient consent to receive SMS" onclick="checkPatientReceivesms(this.value);"> Yes
                                                       </label>
                                                       <label class="radio-inline" style="margin-left:0px;">
                                                            <input type="radio" class="" id="receivesmsNo" name="receiveSms" value="no" title="Patient consent to receive SMS" onclick="checkPatientReceivesms(this.value);"> No
                                                       </label>
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="patientPhoneNumber">Phone Number</label>
                                                       <input type="text" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control checkNum" maxlength="15" placeholder="Enter Phone Number" title="Enter phone number" />
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
                                                            <input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select sample collection date" onchange="checkSampleReceviedDate();checkSampleTestingDate();sampleCodeGeneration();">
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="specimenType">Sample Type <span class="mandatory">*</span></label>
                                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose sample type">
                                                                 <option value=""> -- Select -- </option>
                                                                 <?php
                                                                 foreach ($sResult as $name) {
                                                                 ?>
                                                                      <option value="<?php echo $name['sample_id']; ?>"><?php echo ucwords($name['sample_name']); ?></option>
                                                                 <?php
                                                                 }
                                                                 ?>
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
                                                                 <input type="text" class="form-control date" name="dateOfArtInitiation" id="dateOfArtInitiation" placeholder="Date Of Treatment Initiated" title="Date Of treatment initiated" style="width:100%;" onchange="checkARTInitiationDate();">
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group">
                                                                 <label for="artRegimen">Current Regimen</label>
                                                                 <select class="form-control" id="artRegimen" name="artRegimen" title="Please choose ART Regimen" style="width:100%;" onchange="checkARTRegimenValue();">
                                                                      <option value="">-- Select --</option>
                                                                      <?php foreach ($artRegimenResult as $heading) { ?>
                                                                           <optgroup label="<?php echo ucwords($heading['headings']); ?>">
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
                                                                      <?php }
                                                                      if ($sarr['sc_user_type'] != 'vluser') { ?>
                                                                           <option value="other">Other</option>
                                                                      <?php } ?>
                                                                 </select>
                                                                 <input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="ART Regimen" title="Please enter art regimen" style="width:100%;display:none;margin-top:2px;">
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group">
                                                                 <label for="">Date of Initiation of Current Regimen </label>
                                                                 <input type="text" class="form-control date" style="width:100%;" name="regimenInitiatedOn" id="regimenInitiatedOn" placeholder="Current Regimen Initiated On" title="Please enter current regimen initiated on">
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group">
                                                                 <label for="arvAdherence">ARV Adherence </label>
                                                                 <select name="arvAdherence" id="arvAdherence" class="form-control" title="Please choose adherence">
                                                                      <option value=""> -- Select -- </option>
                                                                      <option value="good">Good >= 95%</option>
                                                                      <option value="fair">Fair (85-94%)</option>
                                                                      <option value="poor">Poor < 85%</option>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row ">
                                                       <div class="col-xs-3 col-md-3 femaleSection">
                                                            <div class="form-group">
                                                                 <label for="patientPregnant">Is Patient Pregnant? </label><br>
                                                                 <label class="radio-inline">
                                                                      <input type="radio" class="" id="pregYes" name="patientPregnant" value="yes" title="Please check one"> Yes
                                                                 </label>
                                                                 <label class="radio-inline">
                                                                      <input type="radio" class="" id="pregNo" name="patientPregnant" value="no"> No
                                                                 </label>
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3 femaleSection">
                                                            <div class="form-group">
                                                                 <label for="breastfeeding">Is Patient Breastfeeding? </label><br>
                                                                 <label class="radio-inline">
                                                                      <input type="radio" class="" id="breastfeedingYes" name="breastfeeding" value="yes" title="Please check one"> Yes
                                                                 </label>
                                                                 <label class="radio-inline">
                                                                      <input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no"> No
                                                                 </label>
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3" style="display:none;">
                                                            <div class="form-group">
                                                                 <label for="">How long has this patient been on treatment ? </label>
                                                                 <input type="text" class="form-control" id="treatPeriod" name="treatPeriod" placeholder="Enter Treatment Period" title="Please enter how long has this patient been on treatment" />
                                                            </div>
                                                       </div>
                                                  </div>
                                             </div>
                                             <div class="box box-primary">
                                                  <div class="box-header with-border">
                                                       <h3 class="box-title">Indication for Viral Load Testing</h3><small> (Please tick one):(To be completed by clinician)</small>
                                                  </div>
                                                  <div class="box-body">
                                                       <div class="row">
                                                            <div class="col-md-6">
                                                                 <div class="form-group">
                                                                      <div class="col-lg-12">
                                                                           <label class="radio-inline">
                                                                                <input type="radio" class="isRequired" id="rmTesting" name="stViralTesting" value="routine" title="Please check routine monitoring (Reason for testing)" onclick="showTesting('rmTesting');">
                                                                                <strong>Routine Monitoring</strong>
                                                                           </label>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row rmTesting hideTestData" style="display:none;">
                                                            <div class="col-md-6">
                                                                 <label class="col-lg-5 control-label">Date of last viral load test</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control date viralTestData" id="rmTestingLastVLDate" name="rmTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" />
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                 <label for="rmTestingVlValue" class="col-lg-3 control-label">VL Value</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control checkNum viralTestData" id="rmTestingVlValue" name="rmTestingVlValue" placeholder="Enter VL Value" title="Please enter vl value" />
                                                                      (copies/ml)
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row">
                                                            <div class="col-md-8">
                                                                 <div class="form-group">
                                                                      <div class="col-lg-12">
                                                                           <label class="radio-inline">
                                                                                <input type="radio" class="isRequired" id="repeatTesting" name="stViralTesting" value="failure" title="Repeat VL test after suspected treatment failure adherence counseling (Reason for testing)" onclick="showTesting('repeatTesting');">
                                                                                <strong>Repeat VL test after suspected treatment failure adherence counselling </strong>
                                                                           </label>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row repeatTesting hideTestData" style="display:none;">
                                                            <div class="col-md-6">
                                                                 <label class="col-lg-5 control-label">Date of last viral load test</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control date viralTestData" id="repeatTestingLastVLDate" name="repeatTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" />
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                 <label for="repeatTestingVlValue" class="col-lg-3 control-label">VL Value</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control checkNum viralTestData" id="repeatTestingVlValue" name="repeatTestingVlValue" placeholder="Enter VL Value" title="Please enter vl value" />
                                                                      (copies/ml)
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row">
                                                            <div class="col-md-6">
                                                                 <div class="form-group">
                                                                      <div class="col-lg-12">
                                                                           <label class="radio-inline">
                                                                                <input type="radio" class="isRequired" id="suspendTreatment" name="stViralTesting" value="suspect" title="Suspect Treatment Failure (Reason for testing)" onclick="showTesting('suspendTreatment');">
                                                                                <strong>Suspect Treatment Failure</strong>
                                                                           </label>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row suspendTreatment hideTestData" style="display: none;">
                                                            <div class="col-md-6">
                                                                 <label class="col-lg-5 control-label">Date of last viral load test</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control date viralTestData" id="suspendTreatmentLastVLDate" name="suspendTreatmentLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" />
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                 <label for="suspendTreatmentVlValue" class="col-lg-3 control-label">VL Value</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control checkNum viralTestData" id="suspendTreatmentVlValue" name="suspendTreatmentVlValue" placeholder="Enter VL Value" title="Please enter vl value" />
                                                                      (copies/ml)
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row">
                                                            <div class="col-md-4">
                                                                 <label for="reqClinician" class="col-lg-5 control-label">Request Clinician</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control" id="reqClinician" name="reqClinician" placeholder="Request Clinician" title="Please enter request clinician" />
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                 <label for="reqClinicianPhoneNumber" class="col-lg-5 control-label">Phone Number</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control checkNum" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter request clinician phone number" />
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                 <label class="col-lg-5 control-label" for="requestDate">Request Date </label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control date" id="requestDate" name="requestDate" placeholder="Request Date" title="Please select request date" />
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row" style="display:none;">
                                                            <div class="col-md-4">
                                                                 <label class="col-lg-5 control-label" for="emailHf">Email for HF </label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control isEmail" id="emailHf" name="emailHf" placeholder="Email for HF" title="Please enter email for hf" />
                                                                 </div>
                                                            </div>
                                                       </div>
                                                  </div>
                                             </div>
                                             <?php if ($usersModel->isAllowed('vlTestResult.php', $systemConfig) && $_SESSION['accessType'] != 'collection-site') { ?>
                                                  <div class="box box-primary">
                                                       <div class="box-header with-border">
                                                            <h3 class="box-title">Laboratory Information</h3>
                                                       </div>
                                                       <div class="box-body">
                                                            <div class="row">
                                                                 <div class="col-md-4">
                                                                      <label for="labId" class="col-lg-5 control-label">Lab Name </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="labId" id="labId" class="select2 form-control" title="Please choose lab" onchange="autoFillFocalDetails();">
                                                                                <option value="">-- Select --</option>
                                                                                <?php foreach ($lResult as $labName) { ?>
                                                                                     <option data-focalperson="<?php echo $labName['contact_person']; ?>" data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>" value="<?php echo $labName['facility_id']; ?>"><?php echo ucwords($labName['facility_name']); ?></option>
                                                                                <?php } ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label for="vlFocalPerson" class="col-lg-5 control-label">VL Focal Person </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control" id="vlFocalPerson" name="vlFocalPerson" placeholder="VL Focal Person" title="Please enter vl focal person name" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label">VL Focal Person Phone Number</label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control checkNum" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter vl focal person phone number" />
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label" for="sampleReceivedAtHubOn">Date Sample Received at Hub (PHL) </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control dateTime" id="sampleReceivedAtHubOn" name="sampleReceivedAtHubOn" placeholder="Sample Received at HUB Date" title="Please select sample received at HUB date" onchange="checkSampleReceviedAtHubDate()" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label" for="sampleReceivedDate">Date Sample Received at Testing Lab </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="Sample Received at LAB Date" title="Please select sample received at Lab date" onchange="checkSampleReceviedDate()" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label" for="sampleTestingDateAtLab">Sample Testing Date </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Sample Testing Date" title="Please select sample testing date" onchange="checkSampleTestingDate();" />
                                                                      </div>
                                                                 </div>

                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-4">
                                                                      <label for="testingPlatform" class="col-lg-5 control-label">VL Testing Platform </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="testingPlatform" id="testingPlatform" class="form-control" title="Please choose VL Testing Platform">
                                                                                <option value="">-- Select --</option>
                                                                                <?php foreach ($importResult as $mName) { ?>
                                                                                     <option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']; ?>"><?php echo $mName['machine_name']; ?></option>
                                                                                <?php } ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label" for="noResult">Sample Rejected ? </label>
                                                                      <div class="col-lg-7">
                                                                           <label class="radio-inline">
                                                                                <input class="" id="noResultYes" name="noResult" value="yes" title="Please check one" type="radio"> Yes
                                                                           </label>
                                                                           <label class="radio-inline">
                                                                                <input class="" id="noResultNo" name="noResult" value="no" title="Please check one" type="radio"> No
                                                                           </label>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4 rejectionReason" style="display:none;">
                                                                      <label class="col-lg-5 control-label" for="rejectionReason">Rejection Reason </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="rejectionReason" id="rejectionReason" class="form-control" title="Please choose reason" onchange="checkRejectionReason();">
                                                                                <option value="">-- Select --</option>
                                                                                <?php foreach ($rejectionTypeResult as $type) { ?>
                                                                                     <optgroup label="<?php echo ucwords($type['rejection_type']); ?>">
                                                                                          <?php foreach ($rejectionResult as $reject) {
                                                                                               if ($type['rejection_type'] == $reject['rejection_type']) {
                                                                                          ?>
                                                                                                    <option value="<?php echo $reject['rejection_reason_id']; ?>"><?php echo ucwords($reject['rejection_reason_name']); ?></option>
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
                                                                 <div class="col-md-4 vlResult">
                                                                      <label class="col-lg-5 control-label" for="vlResult">Viral Load Result (copiesl/ml) </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control" id="vlResult" name="vlResult" placeholder="Viral Load Result" title="Please enter viral load result" style="width:100%;" onchange="calculateLogValue(this)" />
                                                                           <input type="checkbox" class="" id="tnd" name="tnd" value="yes" title="Please check tnd"> Target Not Detected<br>
                                                                           <input type="checkbox" class="" id="bdl" name="bdl" value="yes" title="Please check bdl"> Below Detection Level
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class=" vlLog col-md-4">
                                                                      <label class="col-lg-5 control-label" for="vlLog">Viral Load Log </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control " id="vlLog" name="vlLog" placeholder="Viral Load Log" title="Please enter viral load log" style="width:100%;" onchange="calculateLogValue(this);" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label" for="resultDispatchedOn">Date Results Dispatched </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatch Date" title="Please select result dispatched date" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label" for="reviewedBy">Reviewed By </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
                                                                                <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                            </div><br />
                                                            <div class="row">
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label" for="reviewedOn">Reviewed On </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label" for="testedBy">Tested By </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="testedBy" id="testedBy" class="select2 form-control" title="Please choose approved by">
                                                                                <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label" for="approvedBy">Approved By </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose approved by">
                                                                                <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label" for="approvedOnDateTime">Approved On </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" value="<?php echo $general->humanDateFormat($general->getDateTime()); ?>" class="form-control dateTime" id="approvedOnDateTime" name="approvedOnDateTime" placeholder="e.g 09-Jan-1992 05:30" <?php echo $labFieldDisabled; ?> style="width:100%;" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-2 control-label" for="labComments">Lab Tech. Comments </label>
                                                                      <div class="col-lg-10">
                                                                           <textarea class="form-control" name="labComments" id="labComments" placeholder="Lab comments" style="width:100%"></textarea>
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
                                                  <div id="printer_data_loading" style="display:none"><span id="loading_message">Loading Printer Details...</span><br />
                                                       <div class="progress" style="width:100%">
                                                            <div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
                                                            </div>
                                                       </div>
                                                  </div> <!-- /printer_data_loading -->
                                                  <div id="printer_details" style="display:none">
                                                       <span id="selected_printer">No printer selected!</span>
                                                       <button type="button" class="btn btn-success" onclick="changePrinter()">Change/Retry</button>
                                                  </div><br /> <!-- /printer_details -->
                                                  <div id="printer_select" style="display:none">
                                                       Zebra Printer Options<br />
                                                       Printer: <select id="printers"></select>
                                                  </div> <!-- /printer_select -->
                                             <?php } ?>
                                             <!-- BARCODESTUFF END -->
                                             <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                                             <input type="hidden" name="saveNext" id="saveNext" />
                                             <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
                                             <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
                                                  <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                                                  <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                                             <?php } ?>
                                             <input type="hidden" name="vlSampleId" id="vlSampleId" value="" />
                                             <a class="btn btn-primary" href="javascript:void(0);" onclick="validateSaveNow();return false;">Save and Next</a>
                                             <a href="vlRequest.php" class="btn btn-default"> Cancel</a>
                                        </div>
                    </form>
               </div>
     </section>
</div>
<!-- BARCODESTUFF START -->
<?php
if (isset($global['bar_code_printing']) && $global['bar_code_printing'] != "off") {
     if ($global['bar_code_printing'] == 'dymo-labelwriter-450') {
?>
          <script src="/assets/js/DYMO.Label.Framework.js"></script>
          <script src="/configs/dymo-format.js"></script>
          <script src="/assets/js/dymo-print.js"></script>
     <?php
     } else if ($global['bar_code_printing'] == 'zebra-printer') {
     ?>
          <script src="/assets/js/zebra-browserprint.js.js"></script>
          <script src="/configs/zebra-format.js"></script>
          <script src="/assets/js/zebra-print.js"></script>
<?php
     }
}
?>
<!-- BARCODESTUFF END -->
<script type="text/javascript" src="/assets/js/moment.min.js"></script>
<script>
     provinceName = true;
     facilityName = true;
     $(document).ready(function() {
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
          // BARCODESTUFF START
          <?php
          if (isset($_GET['barcode']) && $_GET['barcode'] == 'true') {
               echo "printBarcodeLabel('" . $_GET['s'] . "','" . $_GET['f'] . "');";
          }
          ?>
          // BARCODESTUFF END
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
                    $.post("/includes/siteInformationDropdownOptions.php", {
                              pName: pName,
                              testType: 'vl'
                         },
                         function(data) {
                              if (data != "") {
                                   details = data.split("###");
                                   $("#district").html(details[1]);
                                   $("#fName").html("<option data-code='' data-emails='' data-mobile-nos='' data-contact-person='' value=''> -- Select -- </option>");
                                   $("#fCode").val('');
                                   $(".facilityDetails").hide();
                                   $(".facilityEmails").html('');
                                   $(".facilityMobileNumbers").html('');
                                   $(".facilityContactPerson").html('');
                              }
                         });
               }
               sampleCodeGeneration();
          } else if (pName == '' && cName == '') {
               provinceName = true;
               facilityName = true;
               $("#province").html("<?php echo $province; ?>");
               $("#fName").html("<?php echo $facility; ?>");
          }
          $.unblockUI();
     }

     function sampleCodeGeneration() {
          var pName = $("#province").val();
          var sDate = $("#sampleCollectionDate").val();
          $("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
          if (pName != '' && sDate != '') {
               $.post("/vl/requests/sampleCodeGeneration.php", {
                         sDate: sDate
                    },
                    function(data) {
                         var sCodeKey = JSON.parse(data);
                         $("#sampleCode").val(sCodeKey.sampleCode);
                         $("#sampleCodeInText").html(sCodeKey.sampleCode);
                         $("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
                         $("#sampleCodeKey").val(sCodeKey.maxId);
                         checkSampleNameValidation('vl_request_form', '<?php echo $sampleCode; ?>', 'sampleCode', null, 'This sample number already exists.Try another number', null)
                    });
          }
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
          } else if ($(this).val() == 'female') {
               $('.femaleSection').show();
          }
     });
     $("input:radio[name=noResult]").click(function() {
          if ($(this).val() == 'yes') {
               $('.rejectionReason').show();
               $('.vlResult').css('display', 'none');
               $('.vlLog').css('display', 'none');

               $('#rejectionReason').addClass('isRequired');
          } else {
               $('.vlResult').css('display', 'block');
               $('.vlLog').css('display', 'block');
               $('.rejectionReason').hide();
               $('#rejectionReason').removeClass('isRequired');
               $('#rejectionReason').val('');
          }
     });
     $('#tnd').change(function() {
          if ($('#tnd').is(':checked')) {
               $('#vlResult,#vlLog').attr('readonly', true);
               $('#vlResult,#vlLog').removeClass('isRequired');
               $('#bdl').attr('disabled', true);
          } else {
               $('#vlResult,#vlLog').attr('readonly', false);
               $('#bdl').attr('disabled', false);
          }
     });
     $('#bdl').change(function() {
          if ($('#bdl').is(':checked')) {
               $('#vlResult,#vlLog').attr('readonly', true);
               $('#tnd').attr('disabled', true);
          } else {
               $('#vlResult,#vlLog').attr('readonly', false);
               $('#tnd').attr('disabled', false);
          }
     });
     $('#vlResult,#vlLog').on('input', function(e) {
          if (this.value != '') {
               $('#tnd,#bdl').attr('disabled', true);
          } else {
               $('#tnd,#bdl').attr('disabled', false);
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

     function validateNow() {
          var format = '<?php echo $arr['sample_code']; ?>';
          var sCodeLentgh = $("#sampleCode").val();
          var minLength = '<?php echo $arr['min_length']; ?>';
          if ((format == 'alphanumeric' || format == 'numeric') && sCodeLentgh.length < minLength && sCodeLentgh != '') {
               alert("Sample id length must be a minimum length of " + minLength + " characters");
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
               $.blockUI();
               <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
                    insertSampleCode('vlRequestFormSs', 'vlSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', '1', 'sampleCollectionDate');
               <?php } else { ?>
                    document.getElementById('vlRequestFormSs').submit();
               <?php } ?>
          }
     }

     function validateSaveNow() {
          var format = '<?php echo $arr['sample_code']; ?>';
          var sCodeLentgh = $("#sampleCode").val();
          var minLength = '<?php echo $arr['min_length']; ?>';
          if ((format == 'alphanumeric' || format == 'numeric') && sCodeLentgh.length < minLength && sCodeLentgh != '') {
               alert("Sample id length must be a minimum length of " + minLength + " characters");
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
          patientArray = pDetails.split("##");
          $("#patientFirstName").val(patientArray[0] + " " + patientArray[1]);
          $("#patientPhoneNumber").val(patientArray[8]);
          if ($.trim(patientArray[3]) != '') {
               $("#dob").val(patientArray[3]);
               getAge();
          } else if ($.trim(patientArray[4]) != '' && $.trim(patientArray[4]) != 0) {
               $("#ageInYears").val(patientArray[4]);
          } else if ($.trim(patientArray[5]) != '') {
               $("#ageInMonths").val(patientArray[5]);
          }

          if ($.trim(patientArray[2]) != '') {
               if (patientArray[2] == 'male' || patientArray[2] == 'not_recorded') {
                    $('.femaleSection').hide();
                    $('input[name="breastfeeding"]').prop('checked', false);
                    $('input[name="patientPregnant"]').prop('checked', false);
                    if (patientArray[2] == 'male') {
                         $("#genderMale").prop('checked', true);
                    } else {
                         $("#genderNotRecorded").prop('checked', true);
                    }
               } else if (patientArray[2] == 'female') {
                    $('.femaleSection').show();
                    $("#genderFemale").prop('checked', true);
                    if ($.trim(patientArray[6]) != '') {
                         if ($.trim(patientArray[6]) == 'yes') {
                              $("#pregYes").prop('checked', true);
                         } else if ($.trim(patientArray[6]) == 'no') {
                              $("#pregNo").prop('checked', true);
                         }
                    }
                    if ($.trim(patientArray[7]) != '') {
                         if ($.trim(patientArray[7]) == 'yes') {
                              $("#breastfeedingYes").prop('checked', true);
                         } else if ($.trim(patientArray[7]) == 'no') {
                              $("#breastfeedingNo").prop('checked', true);
                         }
                    }
               }
          }
          if ($.trim(patientArray[9]) != '') {
               if (patientArray[9] == 'yes') {
                    $("#receivesmsYes").prop('checked', true);
               } else if (patientArray[9] == 'no') {
                    $("#receivesmsNo").prop('checked', true);
               }
          }
          if ($.trim(patientArray[15]) != '') {
               $("#artNo").val($.trim(patientArray[15]));
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
</script>