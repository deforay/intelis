<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Services\UsersService;
use App\Services\GenericTestsService;


$title = " Add New Test";

require_once APPLICATION_PATH . '/header.php';

$labFieldDisabled = '';

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$arr = $general->getGlobalConfig();

$healthFacilities = $facilitiesService->getHealthFacilities('generic-tests');
$testingLabs = $facilitiesService->getTestingLabs('generic-tests');

//get import config
$condition = "status = 'active'";
$importResult = $general->fetchDataFromTable('instruments', $condition);
$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$reasonForFailure = $genericTestsService->getReasonForFailure();
$genericResults = $genericTestsService->getGenericResults();
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
$condition = "sample_type_status = 'active'";
$sResult = $general->fetchDataFromTable('r_generic_sample_types', $condition);


//get vltest reason details
$testReason = $general->fetchDataFromTable('r_generic_test_reasons');
$pdResult = $general->fetchDataFromTable('geographical_divisions');

?>
<style>
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
</style>
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

$testTypeQuery = "SELECT * FROM r_test_types where test_status='active' ORDER BY test_standard_name ASC";
$testTypeResult = $db->rawQuery($testTypeQuery);
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
          <h1><em class="fa-solid fa-pen-to-square"></em> LABORATORY REQUEST FORM </h1>
          <ol class="breadcrumb">
               <li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
               <li class="active">Add Request</li>
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
                    <form class="form-inline" method="post" name="vlRequestFormSs" id="vlRequestFormSs" autocomplete="off" action="add-request-helper.php">
                         <div class="box-body">
                              <div class="box box-primary">
                                   <div class="box-header with-border">
                                        <h3 class="box-title">Clinic Information: (To be filled by requesting Clinican/Nurse)</h3>
                                   </div>
                                   <div class="box-body row">
                                        <div class="col-xs-4 col-md-4">
                                             <div class="form-group">
                                                  <label for="testType">Test Type</label><br>
                                                  <select class="form-control" name="testType" id="testType" title="Please choose test type" style="width:90% !important;" onchange="getTestTypeForm()">
                                                       <option value=""> -- Select -- </option>
                                                       <?php foreach ($testTypeResult as $testType) { ?>
                                                            <option value="<?php echo $testType['test_type_id'] ?>" data-short="<?php echo $testType['test_short_code']; ?>"><?php echo $testType['test_standard_name'] . ' (' . $testType['test_loinc_code'] . ')' ?></option>
                                                       <?php } ?>
                                                  </select>
                                             </div>
                                        </div>
                                        <div class="col-xs-4 col-md-4 requestForm" style="display:none;">
                                             <div class="form-group">
                                                  <label for="sampleCode">Sample ID <span class="mandatory">*</span></label>
                                                  <input type="text" class="form-control isRequired <?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" <?php echo $maxLength; ?> placeholder="Enter Sample ID" title="Please enter sample id" style="width:100%;" readonly onblur="checkSampleNameValidation('form_generic','<?php echo $sampleCode; ?>',this.id,null,'This sample number already exists.Try another number',null)" />
                                             </div>
                                        </div>
                                        <div class="col-xs-4 col-md-4 requestForm" style="display:none;">
                                             <div class="form-group">
                                                  <label for="sampleReordered">
                                                       <input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" title="Please indicate if this is a reordered sample"> Sample Reordered
                                                  </label>
                                             </div>
                                        </div>
                                   </div>
                                   <div class="box-body requestForm" style="display:none;">
                                        <div class="row">
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
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="province">State/Province <span class="mandatory">*</span></label><br>
                                                       <select class="form-control isRequired" name="province" id="province" title="Please choose state" style="width:100%;" onchange="getProvinceDistricts(this);">
                                                            <?php echo $province; ?>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="district">District/County <span class="mandatory">*</span></label><br>
                                                       <select class="form-control isRequired" name="district" id="district" title="Please choose county" style="width:100%;" onchange="getFacilities(this);">
                                                            <option value=""> -- Select -- </option>
                                                       </select>
                                                  </div>
                                             </div>
                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="fName">Clinic/Health Center <span class="mandatory">*</span></label><br>
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
                                                       <label for="implementingPartner">Implementing Partner</label><br>
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
                                                       <label for="fundingSource">Funding Source</label><br>
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

                                             <div class="col-xs-4 col-md-4">
                                                  <div class="form-group">
                                                       <label for="labId">Testing Lab <span class="mandatory">*</span></label><br>
                                                       <select name="labId" id="labId" class="select2 form-control isRequired" title="Please choose lab" onchange="autoFillFocalDetails();setSampleDispatchDate();" style="width:100%;">
                                                            <option value="">-- Select --</option>
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
                                        <h3 class="box-title">Patient Information</h3>&nbsp;&nbsp;&nbsp;
                                        <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="" placeholder="Enter Patient ID" title="Please enter the Enter patient id" />&nbsp;&nbsp;
                                        <a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No Patient Found</strong></span>
                                   </div>
                                   <div class="box-body">
                                        <div class="row">
                                             <div class="col-xs-3 col-md-3">
                                                  <div class="form-group">
                                                       <label for="artNo">Patient ID <span class="mandatory">*</span></label>
                                                       <input type="text" name="artNo" id="artNo" class="form-control isRequired" placeholder="Enter patient ID" title="Enter patient ID" onchange="checkPatientDetails('form_generic','patient_id',this,null)" />
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
                                             <div class="col-xs-3 col-md-3" style="display:none;" id="patientSection">
                                                  <div class="form-group">
                                                       <label for="">How long has this patient been on treatment ? </label>
                                                       <input type="text" class="form-control" id="treatPeriod" name="treatPeriod" placeholder="Enter Treatment Period" title="Please enter how long has this patient been on treatment" />
                                                  </div>
                                             </div>
                                        </div>
                                        <!-- <div id="patientSection"></div> -->
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
                                                  <div class="col-xs-3 col-md-3" id="specimenSection">
                                                       <div class="form-group">
                                                            <label for="specimenType">Sample Type <span class="mandatory">*</span></label>
                                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose sample type">
                                                                 <option value=""> -- Select -- </option>
                                                                 <?php foreach ($sResult as $name) { ?>
                                                                      <option value="<?php echo $name['sample_type_id']; ?>"><?= $name['sample_type_name']; ?></option>
                                                                 <?php } ?>
                                                            </select>
                                                       </div>
                                                  </div>
                                             </div>
                                             <!-- <div id="specimenSection"></div> -->
                                        </div>
                                   </div>
                                   <div id="otherSection"></div>
                                   <?php if ($usersService->isAllowed('generic-test-results.php') && $_SESSION['accessType'] != 'collection-site') { ?>
                                        <div class="box box-primary">
                                             <div class="box-header with-border">
                                                  <h3 class="box-title">Laboratory Information</h3>
                                             </div>
                                             <div class="box-body">
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label for="vlFocalPerson" class="col-lg-5 control-label labels"> Focal Person </label>
                                                            <div class="col-lg-7">
                                                                 <select class="form-control ajax-select2" id="vlFocalPerson" name="vlFocalPerson" placeholder="Focal Person" title="Please enter focal person name"></select>
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6">
                                                            <label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label labels"> Focal Person Phone Number</label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" class="form-control forceNumeric" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter focal person phone number" />
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="sampleReceivedAtHubOn">Date Sample Received at Hub (PHL) </label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" class="form-control dateTime" id="sampleReceivedAtHubOn" name="sampleReceivedAtHubOn" placeholder="Sample Received at HUB Date" title="Please select sample received at Hub date" onchange="checkSampleReceviedAtHubDate()" />
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="sampleReceivedDate">Date Sample Received at Testing Lab </label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="Sample Received at LAB Date" title="Please select sample received at Lab date" onchange="checkSampleReceviedDate()" />
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label for="testPlatform" class="col-lg-5 control-label labels"> Testing Platform <span class="mandatory result-span">*</span></label>
                                                            <div class="col-lg-7">
                                                                 <select name="testPlatform" id="testPlatform" class="form-control isRequired result-optional" title="Please choose Testing Platform">
                                                                      <option value="">-- Select --</option>
                                                                      <?php foreach ($importResult as $mName) { ?>
                                                                           <option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['config_id']; ?>"><?php echo $mName['machine_name']; ?></option>
                                                                      <?php } ?>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="noResult">Sample Rejected? <span class="mandatory result-span">*</span></label>
                                                            <div class="col-lg-7">
                                                                 <select name="noResult" id="noResult" class="form-control isRequired" title="Please check if sample is rejected or not">
                                                                      <option value="">-- Select --</option>
                                                                      <option value="yes">Yes</option>
                                                                      <option value="no">No</option>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row rejectionReason" style="display:none;">
                                                       <div class="col-md-6 rejectionReason" style="display:none;">
                                                            <label class="col-lg-5 control-label labels" for="rejectionReason">Rejection Reason </label>
                                                            <div class="col-lg-7">
                                                                 <select name="rejectionReason" id="rejectionReason" class="form-control" title="Please choose reason" onchange="checkRejectionReason();">
                                                                      <option value="">-- Select --</option>
                                                                      <?php foreach ($rejectionTypeResult as $type) { ?>
                                                                           <optgroup label="<?php echo ($type['rejection_type']); ?>">
                                                                                <?php foreach ($rejectionResult as $reject) {
                                                                                     if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                                                          <option value="<?php echo $reject['rejection_reason_id']; ?>"><?= $reject['rejection_reason_name']; ?></option>
                                                                                <?php }
                                                                                } ?>
                                                                           </optgroup>
                                                                      <?php }
                                                                      if ($_SESSION['instanceType'] != 'vluser') {  ?>
                                                                           <option value="other">Other (Please Specify) </option>
                                                                      <?php } ?>
                                                                 </select>
                                                                 <input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="Rejection Reason" title="Please enter rejection reason" style="width:100%;display:none;margin-top:2px;">
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6 rejectionReason" style="display:none;">
                                                            <label class="col-lg-5 control-label labels" for="rejectionDate">Rejection Date </label>
                                                            <div class="col-lg-7">
                                                                 <input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" title="Please select rejection date" />
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="sampleTestingDateAtLab">Sample Testing Date <span class="mandatory result-span">*</span></label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" class="form-control isRequired result-fields dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Sample Testing Date" title="Please select sample testing date" onchange="checkSampleTestingDate();" disabled />
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="reasonForTesting">Reason For Testing <span class="mandatory result-span">*</span></label>
                                                            <div class="col-lg-7">
                                                                 <select name="reasonForTesting" id="reasonForTesting" class="form-control isRequired result-optional" title="Please choose reason for testing">
                                                                      <option value="">-- Select --</option>
                                                                      <?php foreach ($testReason as $treason) { ?>
                                                                           <option value="<?php echo $treason['test_reason_id']; ?>"><?php echo ucwords($treason['test_reason']); ?></option>
                                                                      <?php } ?>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6 vlResult">
                                                            <label class="col-lg-5 control-label labels" for="resultDispatchedOn">Date Results Dispatched</label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" class="form-control dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatch Date" title="Please select result dispatched date" />
                                                            </div>
                                                       </div>
                                                       <!--  <?php if (count($reasonForFailure) > 0) { ?>
                                                            <div class="col-md-6" style="display: none;">
                                                                 <label class="col-lg-5 control-label" for="reasonForFailure">Reason for Failure <span class="mandatory">*</span> </label>
                                                                 <div class="col-lg-7">
                                                                      <select name="reasonForFailure" id="reasonForFailure" class="form-control" title="Please choose reason for failure" style="width: 100%;">
                                                                           <?= $general->generateSelectOptions($reasonForFailure, null, '-- Select --'); ?>
                                                                      </select>
                                                                 </div>
                                                            </div>
                                                       <?php } ?>-->
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-12">
                                                            <table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true">
                                                                 <thead>
                                                                      <tr>
                                                                           <th scope="row" class="text-center">Test No</th>
                                                                           <th scope="row" class="text-center">Test Method</th>
                                                                           <th scope="row" class="text-center">Date of Testing</th>
                                                                           <th scope="row" class="text-center">Test Platform/Test Kit</th>
                                                                           <th scope="row" class="text-center">Test Result</th>
                                                                      </tr>
                                                                 </thead>
                                                                 <tbody id="testKitNameTable">
                                                                      <tr>
                                                                           <td class="text-center">1</td>
                                                                           <td>
                                                                                <select class="form-control test-name-table-input" id="testName1" name="testName[]" title="Please enter the name of the Testkit (or) Test Method used">
                                                                                     <option value="">-- Select --</option>
                                                                                     <option value="Real Time RT-PCR">Real Time RT-PCR</option>
                                                                                     <option value="RDT-Antibody">RDT-Antibody</option>
                                                                                     <option value="RDT-Antigen">RDT-Antigen</option>
                                                                                     <option value="GeneXpert">GeneXpert</option>
                                                                                     <option value="ELISA">ELISA</option>
                                                                                     <option value="other">Others</option>
                                                                                </select>
                                                                                <input type="text" name="testNameOther[]" id="testNameOther1" class="form-control testNameOther1" title="Please enter the name of the Testkit (or) Test Method used" placeholder="Please enter the name of the Testkit (or) Test Method used" style="display: none;margin-top: 10px;" />
                                                                           </td>
                                                                           <td><input type="text" name="testDate[]" id="testDate1" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row 1" /></td>
                                                                           <td>
                                                                                <select name="testingPlatform[]" id="testingPlatform1" class="form-control  result-optional test-name-table-input" title="Please select the Testing Platform for 1">
                                                                                     <?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>
                                                                                </select>
                                                                           </td>
                                                                           <td>
                                                                                <input type="text" id="testResult1" name="testResult[]" class="form-control" placeholder="Enter result" title="Please enter final results">
                                                                                <!-- <select class="form-control test-result test-name-table-input" name="testResult[]" id="testResult1" title="Please select the result for row 1">
                                                                                               <?= $general->generateSelectOptions($genericResults, null, '-- Select --'); ?>
                                                                                          </select> -->
                                                                           </td>
                                                                           <td style="vertical-align:middle;text-align: center;width:100px;">
                                                                                <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
                                                                                <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
                                                                           </td>
                                                                      </tr>
                                                                 </tbody>
                                                                 <tfoot id="resultSection">
                                                                      <tr>
                                                                           <th scope="row" colspan="4" class="text-right final-result-row">Final Result</th>
                                                                           <td id="result-sections">

                                                                           </td>
                                                                      </tr>
                                                                 </tfoot>
                                                            </table>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label" for="reviewedBy">Reviewed By <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                            <div class="col-lg-7">
                                                                 <select name="reviewedBy" id="reviewedBy" class="select2 form-control labels" title="Please choose reviewed by" style="width: 100%;">
                                                                      <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="reviewedOn">Reviewed On <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" />
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="testedBy">Tested By </label>
                                                            <div class="col-lg-7">
                                                                 <select name="testedBy" id="testedBy" class="select2 form-control" title="Please choose approved by">
                                                                      <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="approvedBy">Approved By <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                            <div class="col-lg-7">
                                                                 <select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose approved by">
                                                                      <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                                 </select>
                                                            </div>
                                                       </div>
                                                  </div>
                                                  <div class="row">
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="approvedOn">Approved On <span class="mandatory review-approve-span" style="display: none;">*</span> </label>
                                                            <div class="col-lg-7">
                                                                 <input type="text" value="" class="form-control dateTime" id="approvedOn" title="Please choose Approved On" name="approvedOn" placeholder="<?= _("Please enter date"); ?>" style="width:100%;" />
                                                            </div>
                                                       </div>
                                                       <div class="col-md-6">
                                                            <label class="col-lg-5 control-label labels" for="labComments">Lab Tech. Comments </label>
                                                            <div class="col-lg-7">
                                                                 <textarea class="form-control" name="labComments" id="labComments" placeholder="Lab comments" title="Please enter LabComments"></textarea>
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
                                             <a href="view-requests.php" class="btn btn-default"> Cancel</a>
                                        </div>
                              </div>
                         </div>
               </div>
          </div>
          <input type="hidden" id="selectedSample" value="" name="selectedSample" class="" />
          <input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />

     </section>
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
     let testCounter = 1;

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
          $("#testType").select2({
               width: '285px',
               placeholder: "<?php echo _("Select Test Type"); ?>"
          });
          $('#labId').select2({
               width: '285px',
               placeholder: "Select Testing Lab"
          });
          $('#fName').select2({
               width: '285px',
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
               width: '285px',
               placeholder: "Select Clinic/Health Center"
          });
          $('#district').select2({
               width: '285px',
               placeholder: "District"
          });
          $('#province').select2({
               width: '285px',
               placeholder: "Province"
          });
          $('#implementingPartner').select2({
               width: '285px',
               placeholder: "Implementing Partner"
          });
          $('#fundingSource').select2({
               width: '285px',
               placeholder: "Funding Source"
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
          var cName = $("#fName").val();
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
          var testType = $("#testType").val();
          var pName = $("#province").val();
          var sDate = $("#sampleCollectionDate").val();
          $("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
          if (pName != '' && sDate != '' && testType != '') {
               $.post("sampleCodeGeneration.php", {
                         sDate: sDate,
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
               $('.rejectionReason').hide();
               $('#rejectionReason').removeClass('isRequired');
               $('#rejectionDate').removeClass('isRequired');
               $('#rejectionReason').val('');
               $(".review-approve-span").hide();
          }
     });
     $("#noResult").on("change", function() {


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
          //$('#vlResult, #noResult').addClass('isRequired');
          $("#noResult").val("");
          //$("#noResult").trigger("change");
     });



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
          if ($.trim(patientArray['patient_id']) != '') {
               $("#artNo").val($.trim(patientArray['patient_id']));
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


     function vlResultChange(value) {
          if (value != "") {
               $('#vlResult').val(value);
          }
     }



     $(document).ready(function() {
          $('.date').datepicker({
               changeMonth: true,
               changeYear: true,
               dateFormat: 'dd-M-yy',
               timeFormat: "hh:mm",
               maxDate: "Today",
               yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
          }).click(function() {
               $('.ui-datepicker-calendar').show();
          });
          $('.dateTime').datetimepicker({
               changeMonth: true,
               changeYear: true,
               dateFormat: 'dd-M-yy',
               timeFormat: "HH:mm",
               maxDate: "Today",
               onChangeMonthYear: function(year, month, widget) {
                    setTimeout(function() {
                         $('.ui-datepicker-calendar').show();
                    });
               }
          }).click(function() {
               $('.ui-datepicker-calendar').show();
          });
          $('.date').mask('99-aaa-9999');
          $('.dateTime').mask('99-aaa-9999 99:99');
     });

     function checkSampleReceviedDate() {
          var sampleCollectionDate = $("#sampleCollectionDate").val();
          var sampleReceivedDate = $("#sampleReceivedDate").val();
          if ($.trim(sampleCollectionDate) != '' && $.trim(sampleReceivedDate) != '') {

               date1 = new Date(sampleCollectionDate);
               date2 = new Date(sampleReceivedDate);

               if (date2.getTime() < date1.getTime()) {
                    alert("<?= _("Sample Received at Testing Lab Date cannot be earlier than Sample Collection Date"); ?>");
                    $("#sampleReceivedDate").val("");
               }
          }
     }

     function checkSampleReceviedAtHubDate() {
          var sampleCollectionDate = $("#sampleCollectionDate").val();
          var sampleReceivedAtHubOn = $("#sampleReceivedAtHubOn").val();
          if ($.trim(sampleCollectionDate) != '' && $.trim(sampleReceivedAtHubOn) != '') {

               date1 = new Date(sampleCollectionDate);
               date2 = new Date(sampleReceivedAtHubOn);

               if (date2.getTime() < date1.getTime()) {
                    alert("<?= _("Sample Received at Hub Date cannot be earlier than Sample Collection Date"); ?>");
                    $("#sampleReceivedAtHubOn").val("");
               }
          }
     }

     function checkSampleTestingDate() {
          var sampleCollectionDate = $("#sampleCollectionDate").val();
          var sampleTestingDate = $("#sampleTestingDateAtLab").val();
          if ($.trim(sampleCollectionDate) != '' && $.trim(sampleTestingDate) != '') {

               date1 = new Date(sampleCollectionDate);
               date2 = new Date(sampleTestingDate);

               if (date2.getTime() < date1.getTime()) {
                    alert("<?= _("Sample Testing Date cannot be earlier than Sample Collection Date"); ?>");
                    $("#sampleTestingDateAtLab").val("");
               }
          }
     }

     function checkARTInitiationDate() {
          var dob = changeFormat($("#dob").val());
          var artInitiationDate = $("#dateOfArtInitiation").val();
          if ($.trim(dob) != '' && $.trim(artInitiationDate) != '') {

               date1 = new Date(dob);
               date2 = new Date(artInitiationDate);

               if (date2.getTime() < date1.getTime()) {
                    alert("<?= _("ART Initiation Date cannot be earlier than Patient Date of Birth"); ?>");
                    $("#dateOfArtInitiation").val("");
               }
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
                              showModal('patientModal.php?artNo=' + obj.value, 900, 520);
                         }
                    });
          }
     }

     function checkSampleNameValidation(tableName, fieldName, id, fnct, alrt) {
          if ($.trim($("#" + id).val()) != '') {
               //$.blockUI();
               $.post("/vl/requests/checkSampleDuplicate.php", {
                         tableName: tableName,
                         fieldName: fieldName,
                         value: $("#" + id).val(),
                         fnct: fnct,
                         format: "html"
                    },
                    function(data) {
                         if (data != 0) {
                              sampleCodeGeneration();
                         }
                    });
               $.unblockUI();
          }
     }

     function insertSampleCode(formId, vlSampleId, sampleCode, sampleCodeKey, sampleCodeFormat, countryId, sampleCollectionDate, provinceCode = null, provinceId = null) {
          $.blockUI();
          $.post("/generic-tests/requests/insertNewSample.php", {
                    sampleCode: $("#" + sampleCode).val(),
                    sampleCodeKey: $("#" + sampleCodeKey).val(),
                    sampleCodeFormat: $("#" + sampleCodeFormat).val(),
                    countryId: countryId,
                    sampleCollectionDate: $("#" + sampleCollectionDate).val(),
                    provinceCode: provinceCode,
                    provinceId: provinceId,
                    testType: $('#testType').find(':selected').data('short')
               },
               function(data) {
                    console.log(data);
                    if (data > 0) {
                         $.unblockUI();
                         document.getElementById("vlSampleId").value = data;
                         document.getElementById(formId).submit();
                    } else {
                         $.unblockUI();
                         $("#sampleCollectionDate").val('');
                         sampleCodeGeneration();
                         alert("<?= _("Could not save this form. Please try again."); ?>");
                    }
               });
     }

     function getAge() {
          let dob = $("#dob").val();
          if ($.trim(dob) != '') {
               let age = getAgeFromDob(dob);
               $("#ageInYears").val("");
               $("#ageInMonths").val("");
               if (age.years >= 1) {
                    $("#ageInYears").val(age.years);
               } else {
                    $("#ageInMonths").val(age.months);
               }
          }
     }

     function clearDOB(val) {
          if ($.trim(val) != "") {
               $("#dob").val("");
          }
     }

     function changeFormat(date) {
          splitDate = date.split("-");
          var fDate = new Date(splitDate[1] + splitDate[2] + ", " + splitDate[0]);
          var monthDigit = fDate.getMonth();
          var fMonth = isNaN(monthDigit) ? 1 : (parseInt(monthDigit) + parseInt(1));
          fMonth = (fMonth < 10) ? '0' + fMonth : fMonth;
          return splitDate[2] + '-' + fMonth + '-' + splitDate[0];
     }

     function getfacilityProvinceDetails(obj) {
          $.blockUI();
          //check facility name`
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
                         }
                    });
          } else if (pName == '' && cName == '') {
               provinceName = true;
               facilityName = true;
               $("#province").html("<?php echo $province ?? ""; ?>");
               $("#fName").html("<?php echo $facility ?? ""; ?>");
          }
          $.unblockUI();
     }

     function getTestTypeForm() {
          var testType = $("#testType").val();
          getSampleTypeList(testType);
          if (testType != "") {
               $(".requestForm").show();
               $.post("/generic-tests/requests/getTestTypeForm.php", {
                         result: $('#result').val(),
                         testType: testType,
                    },
                    function(data) {
                         data = JSON.parse(data);
                         if (data.facilitySection.length > 0) {
                              $("#facilitySection").html(data.facilitySection);
                         }
                         if (data.patientSection.length > 0) {
                              $("#patientSection").after(data.patientSection);
                         }
                         if (data.labSection.length > 0) {
                              $("#labSection").html(data.labSection);
                         }
                         if (data.result.length > 0) {
                              $("#result-sections").html(data.result);
                         } else {
                              $('#resultSection').hide()
                         }
                         if (data.specimenSection.length > 0) {
                              $("#specimenSection").after(data.specimenSection);
                         }
                         if (data.otherSection.length > 0) {
                              $("#otherSection").html(data.otherSection);
                         }
                         checkNum();
                         $('.date').datepicker({
                              changeMonth: true,
                              changeYear: true,
                              dateFormat: 'dd-M-yy',
                              timeFormat: "hh:mm",
                              maxDate: "Today",
                              yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
                         }).click(function() {
                              $('.ui-datepicker-calendar').show();
                         });
                         $(".dynamicFacilitySelect2").select2({
                              width: '285px',
                              placeholder: "<?php echo _("Select any one of the option"); ?>"
                         });
                         $(".dynamicSelect2").select2({
                              width: '100%',
                              placeholder: "<?php echo _("Select any one of the option"); ?>"
                         });
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

     function checkNum() {
          jQuery(".checkNum,.forceNumeric").keydown(function(e) {
               // Allow: backspace, delete, tab, escape, enter and .
               if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
                    // Allow: Ctrl+A
                    (e.keyCode == 65 && e.ctrlKey === true) ||
                    // Allow: home, end, left, right
                    (e.keyCode >= 35 && e.keyCode <= 39)) {
                    // let it happen, don't do anything
                    return;
               }
               // Ensure that it is a number and stop the keypress
               if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                    e.preventDefault();
               }
          });
     }

     function getSampleTypeList(testTypeId) {
          $.post("/includes/get-sample-type.php", {
                    testTypeId: testTypeId,
               },
               function(data) {
                    if (data != "") {
                         $("#specimenType").html(data);
                    }
               });
     }

     function addTestRow() {
          testCounter++;
          let rowString = `<tr>
                    <td class="text-center">${testCounter}</td>
                    <td>
                    <select class="form-control test-name-table-input" id="testName${testCounter}" name="testName[]" title="Please enter the name of the Testkit (or) Test Method used">
                    <option value="">-- Select --</option>
                    <option value="Real Time RT-PCR">Real Time RT-PCR</option>
                    <option value="RDT-Antibody">RDT-Antibody</option>
                    <option value="RDT-Antigen">RDT-Antigen</option>
                    <option value="GeneXpert">GeneXpert</option>
                    <option value="ELISA">ELISA</option>
                    <option value="other">Others</option>
                </select>
                <input type="text" name="testNameOther[]" id="testNameOther${testCounter}" class="form-control testNameOther${testCounter}" title="Please enter the name of the Testkit (or) Test Method used" placeholder="Please enter the name of the Testkit (or) Test Method used" style="display: none;margin-top: 10px;" />
            </td>
            <td><input type="text" name="testDate[]" id="testDate${testCounter}" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row ${testCounter}" /></td>
            <td><select name="testingPlatform[]" id="testingPlatform${testCounter}" class="form-control test-name-table-input" title="Please select the Testing Platform for ${testCounter}"><?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?></select></td>
            <td class="kitlabels" style="display: none;"><input type="text" name="lotNo[]" id="lotNo${testCounter}" class="form-control kit-fields${testCounter}" placeholder="Kit lot no" title="Please enter the kit lot no. for row ${testCounter}" style="display:none;"/></td>
            <td class="kitlabels" style="display: none;"><input type="text" name="expDate[]" id="expDate${testCounter}" class="form-control expDate kit-fields${testCounter}" placeholder="Expiry date" title="Please enter the expiry date for row ${testCounter}" style="display:none;"/></td>
            <td>
               <input type="text" id="testResult${testCounter}" name="testResult[]" class="form-control" placeholder="Enter result" title="Please enter final results">
            </td>
            <td style="vertical-align:middle;text-align: center;width:100px;">
                <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow(this);"><em class="fa-solid fa-plus"></em></a>&nbsp;
                <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
            </td>
        </tr>`;
          $("#testKitNameTable").append(rowString);

          $('.date').datepicker({
               changeMonth: true,
               changeYear: true,
               onSelect: function() {
                    $(this).change();
               },
               dateFormat: 'dd-M-yy',
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
               dateFormat: 'dd-M-yy',
               timeFormat: "HH:mm",
               // minDate: "Today",
               yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
          }).click(function() {
               $('.ui-datepicker-calendar').show();
          });

          $('.dateTime').datetimepicker({
               changeMonth: true,
               changeYear: true,
               dateFormat: 'dd-M-yy',
               timeFormat: "HH:mm",
               maxDate: "Today",
               onChangeMonthYear: function(year, month, widget) {
                    setTimeout(function() {
                         $('.ui-datepicker-calendar').show();
                    });
               }
          }).click(function() {
               $('.ui-datepicker-calendar').show();
          });

          if ($('.kitlabels').is(':visible') == true) {
               $('.kitlabels').show();
          }

     }

     function removeTestRow(el) {
          $(el).fadeOut("slow", function() {
               el.parentNode.removeChild(el);
               rl = document.getElementById("testKitNameTable").rows.length;
               if (rl == 0) {
                    testCounter = 0;
                    addTestRow();
               }
          });
     }
</script>

<?php include APPLICATION_PATH . '/footer.php';
