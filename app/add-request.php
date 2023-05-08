<?php


//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);

$lResult = $facilitiesService->getTestingLabs('vl', true, true);

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
//check remote user
$rKey = '';
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
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
$chkUserFcMapQry = "SELECT user_id FROM user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
$chkUserFcMapResult = $db->query($chkUserFcMapQry);
if ($chkUserFcMapResult) {
     $pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
}
$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
     $province .= "<option value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_id'] . "'>" . ($provinceName['geo_name']) . "</option>";
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
          <h1><em class="fa-solid fa-pen-to-square"></em> VIRAL LOAD LABORATORY REQUEST FORM </h1>
          <ol class="breadcrumb">
               <li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
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
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="sampleCode">Sample ID <span class="mandatory">*</span></label>
                                                       <input type="text" class="form-control isRequired <?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" <?php echo $maxLength; ?> placeholder="Enter Sample ID" title="Please enter sample id" style="width:100%;" readonly onblur="checkSampleNameValidation('form_vl','<?php echo $sampleCode; ?>',this.id,null,'This sample number already exists.Try another number',null)" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="sampleReordered">
                                                            <input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" title="Please indicate if this is a reordered sample"> Sample Reordered
                                                       </label>
                                                  </div>
                                             </div>

                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="communitySample">Community Sample</label>
                                                       <select class="form-control" name="communitySample" id="communitySample" title="Please choose if this is a community sample" style="width:100%;">
                                                            <option value=""> -- Select -- </option>
                                                            <option value="yes">Yes</option>
                                                            <option value="no">No</option>
                                                       </select>
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
                                                       <select class="form-control isRequired select2" id="fName" name="fName" title="Please select clinic/health center name" style="width:100%;" onchange="getfacilityProvinceDetails(this);fillFacilityDetails();setSampleDispatchDate();">
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
                                                                 <option value="<?php echo base64_encode($implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
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
                                                                 <option value="<?php echo base64_encode($fundingSource['funding_source_id']); ?>"><?= $fundingSource['funding_source_name']; ?></option>
                                                            <?php } ?>
                                                       </select>
                                                  </div>
                                             </div>

                                             <div class="col-md-4 col-md-4">
                                                  <label for="labId">Testing Lab <span class="mandatory">*</span></label>
                                                  <select name="labId" id="labId" class="select2 form-control isRequired" title="Please choose lab" onchange="autoFillFocalDetails();setSampleDispatchDate();" style="width:100%;">
                                                       <option value="">-- Select --</option>
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
                                        <h3 class="box-title">Patient Information</h3>&nbsp;&nbsp;&nbsp;
                                        <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="" placeholder="Enter ART Number or Patient Name" title="Enter art number or patient name" />&nbsp;&nbsp;
                                        <a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No Patient Found</strong></span>
                                   </div>
                                   <div class="box-body">
                                        <div class="row">
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="artNo">ART (TRACNET) No. <span class="mandatory">*</span></label>
                                                       <input type="text" name="artNo" id="artNo" class="form-control isRequired" placeholder="Enter ART Number" title="Enter art number" onchange="checkPatientDetails('form_vl','patient_art_no',this,null)" />
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
                                                       <input type="text" name="ageInYears" id="ageInYears" class="form-control forceNumeric" maxlength="3" placeholder="Age in Years" title="Enter age in years" />
                                                  </div>
                                             </div>
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="ageInMonths">If Age
                                                            < 1, Age in Months </label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="Age in Month" title="Enter age in months" />
                                                  </div>
                                             </div>
                                        </div>
                                        <div class="row">
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="patientFirstName">Patient Name (First Name, Last Name) <span class="mandatory">*</span></label>
                                                       <input type="text" name="patientFirstName" id="patientFirstName" class="form-control isRequired" placeholder="Enter Patient Name" title="Enter patient name" />
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
                                                       <input type="text" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control forceNumeric" maxlength="15" placeholder="Enter Phone Number" title="Enter phone number" />
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
                                                            <input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select sample collection date" onchange="checkSampleReceviedDate();checkSampleTestingDate();sampleCodeGeneration();setSampleDispatchDate();">
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="">Sample Dispatched On <span class="mandatory">*</span></label>
                                                            <input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="Sample Dispatched On" title="Please select sample dispatched on">
                                                       </div>
                                                  </div>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="form-group">
                                                            <label for="specimenType">Sample Type <span class="mandatory">*</span></label>
                                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose sample type">
                                                                 <option value=""> -- Select -- </option>
                                                                 <?php foreach ($sResult as $name) { ?>
                                                                      <option value="<?php echo $name['sample_id']; ?>"><?= $name['sample_name']; ?></option>
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
                                                                 <input type="text" class="form-control date" name="dateOfArtInitiation" id="dateOfArtInitiation" placeholder="Date Of Treatment Initiated" title="Date Of treatment initiated" style="width:100%;" onchange="checkARTInitiationDate();">
                                                            </div>
                                                       </div>
                                                       <div class="col-xs-3 col-md-3">
                                                            <div class="form-group">
                                                                 <label for="artRegimen">Current Regimen</label>
                                                                 <select class="form-control" id="artRegimen" name="artRegimen" title="Please choose ART Regimen" style="width:100%;" onchange="checkARTRegimenValue();">
                                                                      <option value="">-- Select --</option>
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
                                                       <h3 class="box-title">Indication for Viral Load Testing <span class="mandatory">*</span></h3><small> (Please choose one):(To be completed by clinician)</small>
                                                  </div>
                                                  <div class="box-body">
                                                       <div class="row">
                                                            <div class="col-md-6">
                                                                 <div class="form-group">
                                                                      <div class="col-lg-12">
                                                                           <label class="radio-inline">
                                                                                <input type="radio" class="isRequired" id="rmTesting" name="reasonForVLTesting" value="routine" title="Please select indication/reason for testing" onclick="showTesting('rmTesting');">
                                                                                <strong>Routine Monitoring</strong>
                                                                           </label>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row rmTesting hideTestData" style="display:none;">
                                                            <div class="col-md-6">
                                                                 <label class="col-lg-5 control-label">Date of Last VL Test</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control date viralTestData" id="rmTestingLastVLDate" name="rmTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" />
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                 <label for="rmTestingVlValue" class="col-lg-3 control-label">VL Result</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control forceNumeric viralTestData" id="rmTestingVlValue" name="rmTestingVlValue" placeholder="Enter VL Result" title="Please enter VL Result" />
                                                                      (copies/ml)
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row">
                                                            <div class="col-md-8">
                                                                 <div class="form-group">
                                                                      <div class="col-lg-12">
                                                                           <label class="radio-inline">
                                                                                <input type="radio" class="isRequired" id="repeatTesting" name="reasonForVLTesting" value="failure" title="Repeat VL test after suspected treatment failure adherence counseling (Reason for testing)" onclick="showTesting('repeatTesting');">
                                                                                <strong>Repeat VL test after suspected treatment failure adherence counselling </strong>
                                                                           </label>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row repeatTesting hideTestData" style="display:none;">
                                                            <div class="col-md-6">
                                                                 <label class="col-lg-5 control-label">Date of Last VL Test</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control date viralTestData" id="repeatTestingLastVLDate" name="repeatTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" />
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                 <label for="repeatTestingVlValue" class="col-lg-3 control-label">VL Result</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control forceNumeric viralTestData" id="repeatTestingVlValue" name="repeatTestingVlValue" placeholder="Enter VL Result" title="Please enter VL Result" />
                                                                      (copies/ml)
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row">
                                                            <div class="col-md-6">
                                                                 <div class="form-group">
                                                                      <div class="col-lg-12">
                                                                           <label class="radio-inline">
                                                                                <input type="radio" class="isRequired" id="suspendTreatment" name="reasonForVLTesting" value="suspect" title="Suspect Treatment Failure (Reason for testing)" onclick="showTesting('suspendTreatment');">
                                                                                <strong>Suspect Treatment Failure</strong>
                                                                           </label>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <div class="row suspendTreatment hideTestData" style="display: none;">
                                                            <div class="col-md-6">
                                                                 <label class="col-lg-5 control-label">Date of Last VL Test</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control date viralTestData" id="suspendTreatmentLastVLDate" name="suspendTreatmentLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" />
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                 <label for="suspendTreatmentVlValue" class="col-lg-3 control-label">VL Result</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control forceNumeric viralTestData" id="suspendTreatmentVlValue" name="suspendTreatmentVlValue" placeholder="Enter VL Result" title="Please enter VL Result" />
                                                                      (copies/ml)
                                                                 </div>
                                                            </div>
                                                       </div>
                                                       <p>&nbsp;</p>
                                                       <div class="row">
                                                            <div class="col-md-4">
                                                                 <label for="reqClinician" class="col-lg-5 control-label">Requesting Clinician</label>
                                                                 <div class="col-lg-7">
                                                                      <select class="form-control ajax-select2" id="reqClinician" name="reqClinician" placeholder="Request Clinician" title="Please enter request clinician"></select>
                                                                 </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                 <label for="reqClinicianPhoneNumber" class="col-lg-5 control-label">Phone Number</label>
                                                                 <div class="col-lg-7">
                                                                      <input type="text" class="form-control forceNumeric" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter request clinician phone number" />
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
                                             <?php if ($usersService->isAllowed('vlTestResult.php') && $_SESSION['accessType'] != 'collection-site') { ?>
                                                  <div class="box box-primary">
                                                       <div class="box-header with-border">
                                                            <h3 class="box-title">Laboratory Information</h3>
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
                                                                 <div class="col-md-4">
                                                                      <label for="vlFocalPerson" class="col-lg-5 control-label labels">VL Focal Person </label>
                                                                      <div class="col-lg-7">
                                                                           <select class="form-control ajax-select2" id="vlFocalPerson" name="vlFocalPerson" placeholder="VL Focal Person" title="Please enter vl focal person name"></select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label labels">VL Focal Person Phone Number</label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control forceNumeric" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter vl focal person phone number" />
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label labels" for="sampleReceivedAtHubOn">Date Sample Received at Hub (PHL) </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control dateTime" id="sampleReceivedAtHubOn" name="sampleReceivedAtHubOn" placeholder="Sample Received at HUB Date" title="Please select sample received at Hub date" onchange="checkSampleReceviedAtHubDate()" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label labels" for="sampleReceivedDate">Date Sample Received at Testing Lab </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="Sample Received at LAB Date" title="Please select sample received at Lab date" onchange="checkSampleReceviedDate()" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label labels" for="sampleTestingDateAtLab">Sample Testing Date <span class="mandatory result-span">*</span></label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control isRequired result-fields dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Sample Testing Date" title="Please select sample testing date" onchange="checkSampleTestingDate();" disabled />
                                                                      </div>
                                                                 </div>

                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-4">
                                                                      <label for="testingPlatform" class="col-lg-5 control-label labels">VL Testing Platform <span class="mandatory result-span">*</span></label>
                                                                      <div class="col-lg-7">
                                                                           <select name="testingPlatform" id="testingPlatform" class="form-control isRequired result-optional" title="Please choose VL Testing Platform" onchange="hivDetectionChange();">
                                                                                <option value="">-- Select --</option>
                                                                                <?php foreach ($importResult as $mName) { ?>
                                                                                     <option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['config_id']; ?>"><?php echo $mName['machine_name']; ?></option>
                                                                                <?php } ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label labels" for="noResult">Sample Rejected? <span class="mandatory result-span">*</span></label>
                                                                      <div class="col-lg-7">
                                                                           <select name="noResult" id="noResult" class="form-control isRequired" title="Please check if sample is rejected or not">
                                                                                <option value="">-- Select --</option>
                                                                                <option value="yes">Yes</option>
                                                                                <option value="no">No</option>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4 rejectionReason" style="display:none;">
                                                                      <label class="col-lg-5 control-label labels" for="rejectionReason">Rejection Reason </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="rejectionReason" id="rejectionReason" class="form-control" title="Please choose reason" onchange="checkRejectionReason();">
                                                                                <option value="">-- Select --</option>
                                                                                <?php foreach ($rejectionTypeResult as $type) { ?>
                                                                                     <optgroup label="<?php echo ($type['rejection_type']); ?>">
                                                                                          <?php foreach ($rejectionResult as $reject) {
                                                                                               if ($type['rejection_type'] == $reject['rejection_type']) {
                                                                                          ?>
                                                                                                    <option value="<?php echo $reject['rejection_reason_id']; ?>"><?= $reject['rejection_reason_name']; ?></option>
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
                                                                 <div class="col-md-4 rejectionReason" style="display:none;">
                                                                      <label class="col-lg-5 control-label labels" for="rejectionDate">Rejection Date </label>
                                                                      <div class="col-lg-7">
                                                                           <input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" title="Please select rejection date" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4 hivDetection" style="display: none;">
                                                                      <label for="hivDetection" class="col-lg-5 control-label labels">HIV Detection </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="hivDetection" id="hivDetection" class="form-control hivDetection" title="Please choose HIV detection">
                                                                                <option value="">-- Select --</option>
                                                                                <option value="HIV-1 Detected">HIV-1 Detected</option>
                                                                                <option value="HIV-1 Not Detected">HIV-1 Not Detected</option>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-4 vlResult">
                                                                      <label class="col-lg-5 control-label  labels" for="vlResult">Viral Load Result (copies/ml) </label>
                                                                      <div class="col-lg-7 resultInputContainer">
                                                                           <input list="possibleVlResults" class="form-control result-fields labSection" id="vlResult" name="vlResult" placeholder="Select or Type VL Result" title="Please enter viral load result" onchange="calculateLogValue(this)" disabled>
                                                                           <datalist id="possibleVlResults">

                                                                           </datalist>
                                                                      </div>
                                                                 </div>
                                                                 <div class="vlLog col-md-4">
                                                                      <label class="col-lg-5 control-label  labels" for="vlLog">Viral Load (Log) </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control" id="vlLog" name="vlLog" placeholder="Viral Load (Log)" title="Please enter viral load result in Log" style="width:100%;" onchange="calculateLogValue(this);" />
                                                                      </div>
                                                                 </div>
                                                                 <?php if (count($reasonForFailure) > 0) { ?>
                                                                      <div class="col-md-4" style="display: none;">
                                                                           <label class="col-lg-5 control-label" for="reasonForFailure">Reason for Failure <span class="mandatory">*</span> </label>
                                                                           <div class="col-lg-7">
                                                                                <select name="reasonForFailure" id="reasonForFailure" class="form-control" title="Please choose reason for failure" style="width: 100%;">
                                                                                     <?= $general->generateSelectOptions($reasonForFailure, null, '-- Select --'); ?>
                                                                                </select>
                                                                           </div>
                                                                      </div>
                                                                 <?php } ?>
                                                                 <div class="col-md-4 vlResult">
                                                                      <label class="col-lg-5 control-label labels" for="resultDispatchedOn">Date Results Dispatched</label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" class="form-control dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatch Date" title="Please select result dispatched date" />
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label" for="reviewedBy">Reviewed By <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="reviewedBy" id="reviewedBy" class="select2 form-control labels" title="Please choose reviewed by" style="width: 100%;">
                                                                                <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label labels" for="reviewedOn">Reviewed On <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label labels" for="testedBy">Tested By </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="testedBy" id="testedBy" class="select2 form-control" title="Please choose approved by">
                                                                                <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                            </div>
                                                            <div class="row">
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label labels" for="approvedBy">Approved By <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose approved by">
                                                                                <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-5 control-label labels" for="approvedOn">Approved On <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                                      <div class="col-lg-7">
                                                                           <input type="text" value="" class="form-control dateTime" id="approvedOn" title="Please choose Approved On" name="approvedOn" placeholder="<?= _("Please enter date"); ?>" style="width:100%;" />
                                                                      </div>
                                                                 </div>
                                                                 <div class="col-md-4">
                                                                      <label class="col-lg-2 control-label labels" for="labComments">Lab Tech. Comments </label>
                                                                      <div class="col-lg-10">
                                                                           <textarea class="form-control" name="labComments" id="labComments" placeholder="Lab comments" title="Please enter LabComments" style=" width: 90% !important;margin-left: 24px;"></textarea>
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
                                             <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                                             <input type="hidden" name="saveNext" id="saveNext" />
                                             <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
                                             <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
                                                  <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                                                  <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                                             <?php } ?>
                                             <input type="hidden" name="vlSampleId" id="vlSampleId" value="" />
                                             <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateSaveNow();return false;">Save and Next</a>
                                             <a href="vlRequest.php" class="btn btn-default"> Cancel</a>
                                        </div>
                                   </div>
                              </div>
                         </div>
                         <input type="hidden" id="selectedSample" value="" name="selectedSample" class=""/>
                         <input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />

                    </form>
               </div>
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

<script type="text/javascript" src="/assets/js/datalist-css.min.js"></script>
<script type="text/javascript" src="/assets/js/moment.min.js"></script>
<script>
     let provinceName = true;
     let facilityName = true;
     $(document).ready(function() {
        
          $("#labId,#fName,#sampleCollectionDate").on('change', function() {

               if ($("#labId").val() != '' && $("#labId").val() == $("#fName").val() && $("#sampleDispatchedDate").val() == "") {
                    $('#sampleDispatchedDate').val($('#sampleCollectionDate').val());
               }
               if ($("#labId").val() != '' && $("#labId").val() == $("#fName").val() && $("#sampleReceivedDate").val() == "") {
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

          $('#sampleCollectionDate').datetimepicker({
               changeMonth: true,
               changeYear: true,
               dateFormat: 'dd-M-yy',
               timeFormat: "HH:mm",
               maxDate: "Today",
               onSelect: function(date) {
                    var dt2 = $('#sampleDispatchedDate');
                    var startDate = $(this).datetimepicker('getDate');
                    var minDate = $(this).datetimepicker('getDate');
                    dt2.datetimepicker('setDate', minDate);
                    startDate.setDate(startDate.getDate() + 1000000);
                    dt2.datetimepicker('option', 'maxDate', "Today");
                    dt2.datetimepicker('option', 'minDate', minDate);
                    dt2.datetimepicker('option', 'minDateTime', minDate);
                    dt2.val($(this).val());
               }
          }).click(function() {
               $('.ui-datepicker-calendar').show();
          });
          $('#labId').select2({
               width: '100%',
               placeholder: "Select Testing Lab"
          });
          $('#fName').select2({
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
          // BARCODESTUFF START
          <?php
          if (isset($_GET['barcode']) && $_GET['barcode'] == 'true') {
               echo "printBarcodeLabel('" . htmlspecialchars($_GET['s']) . "','" . htmlspecialchars($_GET['f']) . "');";
          }
          ?>
          // BARCODESTUFF END

          $("#reqClinician").select2({
               placeholder: "Enter Request Clinician name",
               minimumInputLength: 0,
               width: '100%',
               allowClear: true,
               id: function(bond) {
                    return bond._id;
               },
               ajax: {
                    placeholder: "Type one or more character tp search",
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
               placeholder: "Enter Request Focal name",
               minimumInputLength: 0,
               width: '100%',
               allowClear: true,
               id: function(bond) {
                    return bond._id;
               },
               ajax: {
                    placeholder: "Type one or more character tp search",
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
         
          if($("#selectedSample").val()!="")
          {
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
                         checkSampleNameValidation('form_vl', '<?php echo $sampleCode; ?>', 'sampleCode', null, 'This sample number already exists.Try another number', null)
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
     $("#noResult").on("change", function() {

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
               $("#noResult").val("no");
               $('#vlResult').attr('disabled', false);
               $('#vlLog').attr('disabled', false);
               $("#vlResult,#vlLog").val('');
               $(".vlResult, .vlLog").hide();
               $("#reasonForFailure").removeClass('isRequired');
               $('#vlResult').removeClass('isRequired');
          } else if (this.value === 'HIV-1 Detected') {
               $("#noResult").val("no");
               $(".vlResult, .vlLog").show();
               $('#vlResult').addClass('isRequired');
               $("#noResult").trigger("change");
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
          //$('#vlResult, #noResult').addClass('isRequired');
          $("#noResult").val("");
          //$("#noResult").trigger("change");
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
          if ((text == 'GeneXpert' || str.toLowerCase() == 'genexpert') && $('#noResult').val() != 'yes') {
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
                    if (data != "") {
                         $("#possibleVlResults").html(data);
                         $("#vlResult").attr("disabled", false);
                    }
               });

     }

     function setSampleDispatchDate() {
          if ($("#labId").val() != "" && $("#labId").val() == $("#fName").val() && $('#sampleDispatchedDate').val() == "") {
               $('#sampleDispatchedDate').val($("sampleCollectionDate").val());
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
               if (patientArray['gender'] == 'male' || patientArray['gender'] == 'not_recorded') {
                    $('.femaleSection').hide();
                    $('input[name="breastfeeding"]').prop('checked', false);
                    $('input[name="patientPregnant"]').prop('checked', false);
                    if (patientArray['gender'] == 'male') {
                         $("#genderMale").prop('checked', true);
                    } else {
                         $("#genderNotRecorded").prop('checked', true);
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