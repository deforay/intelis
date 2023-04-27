<?php

// imported in covid-19-edit-request.php based on country in global config

use App\Services\Covid19Service;
use App\Utilities\DateUtils;




//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);

//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);


$covid19Obj = new Covid19Service();
$covid19Results = $covid19Obj->getCovid19Results();


// Getting the list of Provinces, Districts and Facilities

$rKey = '';
$pdQuery = "SELECT * FROM province_details";


if ($_SESSION['instanceType'] == 'remoteuser') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    if(!empty($covid19Info['remote_sample']) && $covid19Info['remote_sample'] == 'yes'){
		$sampleCode = 'remote_sample_code';
	}else{
		$sampleCode = 'sample_code';
	}
    //check user exist in user_facility_map table
    $chkUserFcMapQry = "SELECT user_id from user_facility_map where user_id='" . $_SESSION['userId'] . "'";
    $chkUserFcMapResult = $db->query($chkUserFcMapQry);
    if ($chkUserFcMapResult) {
        $pdQuery = "SELECT * from province_details as pd JOIN facility_details as fd ON fd.facility_state=pd.province_name JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where user_id='" . $_SESSION['userId'] . "' group by province_name";
    }
    $rKey = 'R';
} else {
    $sampleCodeKey = 'sample_code_key';
    $sampleCode = 'sample_code';
    $rKey = '';
}
$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
    $province .= "<option data-code='" . $provinceName['province_code'] . "' data-province-id='" . $provinceName['province_id'] . "' data-name='" . $provinceName['province_name'] . "' value='" . $provinceName['province_name'] . "##" . $provinceName['province_code'] . "'>" . ($provinceName['province_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, $covid19Info['facility_id'], '-- Select --');


//suggest sample id when lab user add request sample
$sampleSuggestion = '';
$sampleSuggestionDisplay = 'display:none;';
$sCode = (isset($_GET['c']) && $_GET['c'] != '') ? $_GET['c'] : '';
if ($sarr['sc_user_type'] == 'vluser' && $sCode != '') {
    $vlObj = new Covid19Service();
    $sampleCollectionDate = explode(" ", $sampleCollectionDate);
    $sampleCollectionDate = DateUtils::humanReadableDateFormat($sampleCollectionDate[0]);
    $sampleSuggestionJson = $vlObj->generateCovid19SampleCode($stateResult[0]['province_code'], $sampleCollectionDate, 'png');
    $sampleCodeKeys = json_decode($sampleSuggestionJson, true);
    $sampleSuggestion = $sampleCodeKeys['sampleCode'];
    $sampleSuggestionDisplay = 'display:block;';
}

?>


<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _("WHO COVID-19 VIRUS LABORATORY TEST REQUEST FORM");?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home");?></a></li>
            <?php if (isset($covid19Info['result']) && $covid19Info['result'] != "") { ?>
                <li class="active"><?php echo _("View Request");?></li>
            <?php } else { ?>
                <li class="active"><?php echo _("Update Request");?></li>
            <?php } ?>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">

        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->
                <form class="form-horizontal" method="post" name="editCovid19RequestForm" id="editCovid19RequestForm" autocomplete="off" action="covid-19-edit-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border">
                                    <h3 class="box-title">SITE INFORMATION</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
                                </div>
                                <table class="table" aria-hidden="true"  style="width:100%">
                                    <?php if ($covid19Info['remote_sample'] == 'yes') { ?>
                                        <tr>
                                            <?php
                                            if ($covid19Info['sample_code'] != '') {
                                            ?>
                                                <td colspan="4"> <label for="sampleSuggest" class="text-danger">&nbsp;&nbsp;&nbsp;Please note that this Remote Sample has already been imported with VLSM Sample ID </td>
                                                <td colspan="2" align="left"> <?php echo $covid19Info['sample_code']; ?></label> </td>
                                            <?php
                                            } else {
                                            ?>
                                                <td colspan="4"> <label for="sampleSuggest">Sample ID (might change while submitting the form)</label></td>
                                                <td colspan="2" align="left"> <?php echo $sampleSuggestion; ?></td>
                                            <?php } ?>
                                        </tr>
                                    <?php } ?>
                                    <tr>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <td><label for="sampleCode">Sample ID </label> </td>
                                            <td colspan="5">
                                                <span id="sampleCodeInText" style="width:30%;border-bottom:1px solid #333;"><?php echo ($sCode != '') ? $sCode : $covid19Info[$sampleCode]; ?></span>
                                                <input type="hidden" class="<?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" value="<?php echo ($sCode != '') ? $sCode : $covid19Info[$sampleCode]; ?>" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode">Sample ID </label><span class="mandatory">*</span> </td>
                                            <td colspan="5">
                                                <input type="text" readonly value="<?php echo ($sCode != '') ? $sCode : $covid19Info[$sampleCode]; ?>" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Sample ID" title="Please enter Sample ID" style="width:30%;" onchange="" />
                                            </td>
                                        <?php } ?>
                                    </tr>
                                    <tr>
                                        <td><label for="province">Province </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="province" id="province" title="Please choose province" onchange="getfacilityDetails(this);" style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <td><label for="district">District </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="district" id="district" title="Please choose district" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                                                <option value=""> -- Select -- </option>
                                            </select>
                                        </td>
                                        <td><label for="facilityId">Health Facility </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired " name="facilityId" id="facilityId" title="Please choose service provider" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
                                                <?php echo $facility; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><label for="supportPartner">Implementing Partner </label></td>
                                        <td>

                                            <select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose implementing partner" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($implementingPartnerList as $implementingPartner) {
                                                ?>
                                                    <option value="<?php echo ($implementingPartner['i_partner_id']); ?>" <?php echo ($covid19Info['implementing_partner'] == $implementingPartner['i_partner_id']) ? "selected='selected'" : ""; ?>><?= $implementingPartner['i_partner_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td><label for="fundingSource">Funding Partner</label></td>
                                        <td>
                                            <select class="form-control" name="fundingSource" id="fundingSource" title="Please choose funding source" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($fundingSourceList as $fundingSource) {
                                                ?>
                                                    <option value="<?php echo ($fundingSource['funding_source_id']); ?>" <?php echo ($covid19Info['funding_source'] == $fundingSource['funding_source_id']) ? "selected='selected'" : ""; ?>><?= $fundingSource['funding_source_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <!-- <tr> -->
                                            <td><label for="labId">Lab Name <span class="mandatory">*</span></label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control isRequired" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, $covid19Info['lab_id'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <!-- </tr> -->
                                        <?php } ?>
                                    </tr>
                                </table>
                                <br>
                                <hr style="border: 1px solid #ccc;">

                                <div class="box-header with-border">
                                    <h3 class="box-title">PATIENT INFORMATION</h3>
                                </div>
                                <table class="table" aria-hidden="true"  style="width:100%">

                                    <tr>
                                        <th style="width:15% !important"><label for="firstName">First Name <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="firstName" name="firstName" placeholder="First Name" title="Please enter patient first name" style="width:100%;" value="<?php echo $covid19Info['patient_name']; ?>" />
                                        </td>
                                        <th style="width:15% !important"><label for="lastName">Last name </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="lastName" name="lastName" placeholder="Last name" title="Please enter patient last name" style="width:100%;" value="<?php echo $covid19Info['patient_surname']; ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="patientId">Patient ID <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="patientId" name="patientId" placeholder="Patient Identification" title="Please enter Patient ID" style="width:100%;" value="<?php echo $covid19Info['patient_id']; ?>" />
                                        </td>
                                        <th scope="row"><label for="patientDob">Date of Birth <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <input type="text" class="form-control isRequired" id="patientDob" name="patientDob" placeholder="Date of Birth" title="Please enter Date of birth" style="width:100%;" onchange="calculateAgeInYears();" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['patient_dob']); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Patient Age (years)</th>
                                        <td><input type="number" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="patientAge" name="patientAge" placeholder="Patient Age (in years)" title="Patient Age" style="width:100%;" value="<?php echo $covid19Info['patient_age']; ?>" /></td>
                                        <th scope="row"><label for="patientGender">Gender <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <select class="form-control isRequired" name="patientGender" id="patientGender">
                                                <option value=''> -- Select -- </option>
                                                <option value='male' <?php echo ($covid19Info['patient_gender'] == 'male') ? "selected='selected'" : ""; ?>> Male </option>
                                                <option value='female' <?php echo ($covid19Info['patient_gender'] == 'female') ? "selected='selected'" : ""; ?>> Female </option>
                                                <option value='other' <?php echo ($covid19Info['patient_gender'] == 'other') ? "selected='selected'" : ""; ?>> Other </option>

                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Phone number</th>
                                        <td><input type="text" class="form-control " id="patientPhoneNumber" name="patientPhoneNumber" placeholder="Patient Phone Number" title="Patient Phone Number" style="width:100%;" value="<?php echo $covid19Info['patient_phone_number']; ?>" /></td>

                                        <th scope="row">Patient address</th>
                                        <td><textarea class="form-control " id="patientAddress" name="patientAddress" placeholder="Patient Address" title="Patient Address" style="width:100%;" onchange=""><?php echo $covid19Info['patient_address']; ?></textarea></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Province</th>
                                        <td><input type="text" value="<?php echo $covid19Info['patient_province']; ?>" class="form-control " id="patientProvince" name="patientProvince" placeholder="Patient Province" title="Please enter the patient province" style="width:100%;" /></td>

                                        <th scope="row">District</th>
                                        <td><input class="form-control" value="<?php echo $covid19Info['patient_district']; ?>" id="patientDistrict" name="patientDistrict" placeholder="Patient District" title="Please enter the patient district" style="width:100%;"></td>
                                    </tr>
                                </table>



                                <br><br>
                                <table class="table" aria-hidden="true" >
                                    <tr>
                                        <th colspan=4 style="border-top:#ccc 2px solid;">
                                            <h4>SPECIMEN INFORMATION</h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <td colspan=4>
                                            <ul>
                                                <li>All specimens collected should be regarded as potentially infectious and you <u>MUST CONTACT</u> the reference laboratory before sending samples.</li>
                                                <li>All samples must be sent in accordance with category B transport requirements.</li>
                                            </ul>

                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important">Sample Collection Date <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" value="<?php echo ($covid19Info['sample_collection_date']); ?>" />
                                        </td>
                                        <th scope="row">Specimen Type <span class="mandatory">*</span></th>
                                        <td>
                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose specimen type" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <?php foreach ($specimenTypeResult as $name) { ?>
                                                    <option value="<?php echo $name['sample_id']; ?>" <?php echo ($covid19Info['specimen_type'] == $name['sample_id']) ? "selected='selected'" : ""; ?>><?= $name['sample_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Is clinical sample post-mortem ?</th>
                                        <td>
                                            <select name="isSamplePostMortem" id="isSamplePostMortem" class="form-control" title="Is Clinical Sample Post-mortem ?" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <option value='yes' <?php echo ($covid19Info['is_sample_post_mortem'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                <option value='no' <?php echo ($covid19Info['is_sample_post_mortem'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                            </select>
                                        </td>
                                        <th scope="row">Priority Status</th>
                                        <td>
                                            <select name="priorityStatus" id="priorityStatus" class="form-control" title="Priority Status" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <option value='high' <?php echo ($covid19Info['priority_status'] == 'high') ? "selected='selected'" : ""; ?>> High </option>
                                                <option value='medium' <?php echo ($covid19Info['priority_status'] == 'medium') ? "selected='selected'" : ""; ?>> Medium </option>
                                                <option value='low' <?php echo ($covid19Info['priority_status'] == 'low') ? "selected='selected'" : ""; ?>> Low </option>
                                            </select>
                                        </td>
                                    </tr>

                                </table>

                                <br><br>
                                <table class="table" aria-hidden="true" >
                                    <tr>
                                        <th colspan=4 style="border-top:#ccc 2px solid;">
                                            <h4>CLINICAL DETAILS</h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important">Date of Symptom Onset <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control date isRequired" type="text" name="dateOfSymptomOnset" id="dateOfSymptomOnset" placeholder="Symptom Onset Date" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['date_of_symptom_onset']); ?> " />
                                        </td>
                                        <th style="width:15% !important">Has the patient had contact with a confirmed case? <span class="mandatory">*</span></th>
                                        <td style="width:25% !important;">
                                            <select name="contactWithConfirmedCase" id="contactWithConfirmedCase" class="form-control isRequired" title="Please choose if the patient has had a contact with confirmed case" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <option value='yes' <?php echo ($covid19Info['contact_with_confirmed_case'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                <option value='no' <?php echo ($covid19Info['contact_with_confirmed_case'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                <option value='unknown' <?php echo ($covid19Info['contact_with_confirmed_case'] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                                <option value='other' <?php echo ($covid19Info['contact_with_confirmed_case'] == 'other') ? "selected='selected'" : ""; ?>> Other Exposure </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th colspan=2>Has the patient had a recent history of travelling to an affected area? <span class="mandatory">*</span></th>
                                        <td style="width:25% !important;">
                                            <select name="hasRecentTravelHistory" id="hasRecentTravelHistory" class="form-control isRequired" title="Please choose if the patient has had a recent history of travelling to an affected area" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <option value='yes' <?php echo ($covid19Info['has_recent_travel_history'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                <option value='no' <?php echo ($covid19Info['has_recent_travel_history'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                <option value='unknown' <?php echo ($covid19Info['has_recent_travel_history'] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr class="historyfield">
                                        <th scope="row">If Yes, Country Name(s)</th>
                                        <td>
                                            <input class="historyfield form-control" type="text" name="countryName" id="countryName" placeholder="Country Name(s)" value="<?php echo $covid19Info['travel_country_names']; ?>" />
                                        </td>
                                        <th scope="row">Return Date</th>
                                        <td>
                                            <input class="historyfield form-control date" type="text" name="returnDate" id="returnDate" placeholder="Return Date" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['travel_return_date']); ?>" />
                                        </td>
                                    </tr>

                                </table>


                            </div>
                        </div>
                        <?php if ($_SESSION['instanceType'] != 'remoteuser') { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Reserved for Laboratory Use </h3>
                                    </div>
                                    <table class="table" aria-hidden="true"  style="width:100%">
                                        <tr>
                                            <th scope="row"><label for="">Sample Received Date </label></th>
                                            <td>
                                                <input type="text" class="form-control" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter sample receipt date" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['sample_received_at_vl_lab_datetime']) ?>" onchange="" style="width:100%;" />
                                            </td>
                                            <td class="lab-show"><label for="labId">Lab Name </label> </td>
                                            <td class="lab-show">
                                                <select name="labId" id="labId" class="form-control" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, $covid19Info['lab_id'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        <tr>
                                            <th scope="row">Is Sample Rejected?</th>
                                            <td>
                                                <select class="form-control result-focus" name="isSampleRejected" id="isSampleRejected">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="yes" <?php echo ($covid19Info['is_sample_rejected'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                    <option value="no" <?php echo ($covid19Info['is_sample_rejected'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                </select>
                                            </td>

                                            <th class="show-rejection" style="display:none;">Reason for Rejection</th>
                                            <td class="show-rejection" style="display:none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason">
                                                    <option value="">-- Select --</option>
                                                    <?php foreach ($rejectionTypeResult as $type) { ?>
                                                        <optgroup label="<?php echo ($type['rejection_type']); ?>">
                                                            <?php
                                                            foreach ($rejectionResult as $reject) {
                                                                if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                                    <option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($covid19Info['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?= $reject['rejection_reason_name']; ?></option>
                                                            <?php }
                                                            } ?>
                                                        </optgroup>
                                                    <?php }  ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="show-rejection" style="display:none;">
                                            <th scope="row">Rejection Date<span class="mandatory">*</span></th>
                                            <td><input value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4">
                                                <table class="table table-bordered table-striped" aria-hidden="true"  id="testNameTable">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center">Test No.</th>
                                                            <th class="text-center">Name of the Testkit (or) Test Method used</th>
                                                            <th class="text-center">Date of Testing</th>
                                                            <th class="text-center">Test Result</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="testKitNameTable">
                                                        <?php if (isset($covid19TestInfo) && count($covid19TestInfo) > 0) {
                                                            foreach ($covid19TestInfo as $indexKey => $rows) { ?>
                                                                <tr>
                                                                    <td class="text-center"><?= ($indexKey + 1); ?><input type="hidden" name="testId[]" value="<?php echo base64_encode($rows['test_id']); ?>"></td>
                                                                    <td><input type="text" value="<?php echo $rows['test_name']; ?>" name="testName[]" id="testName<?= ($indexKey + 1); ?>" class="form-control test-name-table-input" placeholder="Test name" title="Please enter the test name for row <?= ($indexKey + 1); ?>" /></td>
                                                                    <td><input type="text" value="<?php echo DateUtils::humanReadableDateFormat($rows['sample_tested_datetime']); ?>" name="testDate[]" id="testDate<?= ($indexKey + 1); ?>" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row <?= ($indexKey + 1); ?>" /></td>
                                                                    <td><select class="form-control test-result test-result test-name-table-input result-focus" name="testResult[]" id="testResult<?= ($indexKey + 1); ?>" title="Please select the result for row <?= ($indexKey + 1); ?>">
                                                                            <option value=''> -- Select -- </option>
                                                                            <?php foreach ($covid19Results as $c19ResultKey => $c19ResultValue) { ?>
                                                                                <option value="<?php echo $c19ResultKey; ?>" <?php echo ($rows['result'] == $c19ResultKey) ? "selected='selected'" : ""; ?>> <?php echo $c19ResultValue; ?> </option>
                                                                            <?php } ?>
                                                                        </select>
                                                                    </td>
                                                                    <td style="vertical-align:middle;text-align: center;">
                                                                        <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
                                                                        <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);deleteRow('<?php echo base64_encode($rows['test_id']); ?>');"><em class="fa-solid fa-minus"></em></a>
                                                                    </td>
                                                                </tr>
                                                            <?php }
                                                        } else { ?>
                                                            <tr>
                                                                <td class="text-center">1</td>
                                                                <td><input type="text" name="testName[]" id="testName1" class="form-control test-name-table-input" placeholder="Test name" title="Please enter the test name for row 1" /></td>
                                                                <td><input type="text" name="testDate[]" id="testDate1" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row 1" /></td>
                                                                <td><select class="form-control test-result test-name-table-input" name="testResult[]" id="testResult1" title="Please select the result for row 1">
                                                                        <option value=''> -- Select -- </option>
                                                                        <?php foreach ($covid19Results as $c19ResultKey => $c19ResultValue) { ?>
                                                                            <option value="<?php echo $c19ResultKey; ?>"> <?php echo $c19ResultValue; ?> </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                </td>
                                                                <td style="vertical-align:middle;text-align: center;">
                                                                    <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
                                                                    <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
                                                                </td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <th colspan="3" class="text-right">Final Result</th>
                                                            <td>
                                                                <select class="form-control result-focus" name="result" id="result">
                                                                    <option value=''> -- Select -- </option>
                                                                    <?php foreach ($covid19Results as $c19ResultKey => $c19ResultValue) { ?>
                                                                        <option value="<?php echo $c19ResultKey; ?>" <?php echo ($covid19Info['result'] == $c19ResultKey) ? "selected='selected'" : ""; ?>> <?php echo $c19ResultValue; ?> </option>
                                                                    <?php } ?>
                                                                </select>
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Reviewed By</th>
                                            <td>
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, $covid19Info['result_reviewed_by'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row">Reviewed on</td>
                                            <td><input type="text" value="<?php echo $covid19Info['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" /></td>
                                        </tr>
                                        <tr class="change-reason" style="display: none;">
                                            <th scope="row">Reason for Changing <span class="mandatory">*</span></td>
                                            <td colspan="3"><textarea type="text" name="reasonForChanging" id="reasonForChanging" class="form-control date" placeholder="Enter the reason for changing" title="Please enter the reason for changing"></textarea></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Is Result Authorized ?</th>
                                            <td>
                                                <select name="isResultAuthorized" id="isResultAuthorized" class="disabled-field form-control" title="Is Result authorized ?" style="width:100%">
                                                    <option value="">-- Select --</option>
                                                    <option value='yes' <?php echo ($covid19Info['is_result_authorised'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                    <option value='no' <?php echo ($covid19Info['is_result_authorised'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                </select>
                                            </td>
                                            <th scope="row">Authorized By</th>
                                            <td><input type="text" value="<?php echo $covid19Info['authorized_by']; ?>" name="authorizedBy" id="authorizedBy" class="disabled-field form-control" placeholder="Authorized By" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Authorized on</td>
                                            <td><input type="text" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['authorized_on']); ?>" name="authorizedOn" id="authorizedOn" class="disabled-field form-control date" placeholder="Authorized on" /></td>
                                            <th scope="row"></th>
                                            <td></td>
                                        </tr>
                                        <!-- <tr>
                                            <td style="width:25%;"><label for="">Sample Test Date </label></td>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control dateTime" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _("Please enter date"); ?>" title="Test effectué le" <?php echo $labFieldDisabled; ?> onchange="" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['sample_tested_datetime']) ?>" style="width:100%;" />
                                            </td>


                                            <th scope="row">Result</th>
                                            <td>
                                                <select class="form-control" name="result" id="result">
                                                    <option value=''> -- Select -- </option>
                                                    <?php foreach ($covid19Results as $covid19ResultKey => $covid19ResultValue) { ?>
                                                        <option value="<?php echo $covid19ResultKey; ?>" <?php echo ($covid19Info['result'] == $covid19ResultKey) ? "selected='selected'" : ""; ?>> <?php echo $covid19ResultValue; ?> </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                        </tr> -->

                                    </table>
                                </div>
                            </div>
                        <?php } ?>

                    </div>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
                            <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo (isset($sFormat) && $sFormat != '') ? $sFormat : ''; ?>" />
                            <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo (isset($sKey) && $sKey != '') ? $sKey : ''; ?>" />
                        <?php } ?>
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                        <input type="hidden" name="revised" id="revised" value="no" />
                        <input type="hidden" name="formId" id="formId" value="7" />
                        <input type="hidden" name="deletedRow" id="deletedRow" value="" />
                        <input type="hidden" name="covid19SampleId" id="covid19SampleId" value="<?php echo $covid19Info['covid19_id']; ?>" />
                        <input type="hidden" name="sampleCodeCol" id="sampleCodeCol" value="<?php echo $arr['sample_code']; ?>" />
                        <input type="hidden" name="oldStatus" id="oldStatus" value="<?php echo $covid19Info['result_status']; ?>" />
                        <input type="hidden" name="provinceCode" id="provinceCode" />
                        <input type="hidden" name="provinceId" id="provinceId" />
                        <a href="/covid-19/requests/covid-19-requests.php" class="btn btn-default"> Cancel</a>
                    </div>
                    <!-- /.box-footer -->
                </form>
                <!-- /.row -->
            </div>
        </div>
        <!-- /.box -->
    </section>
    <!-- /.content -->
</div>

<script type="text/javascript">
    changeProvince = true;
    changeFacility = true;
    provinceName = true;
    facilityName = true;
    machineName = true;
    tableRowId = <?php echo (isset($covid19TestInfo) && count($covid19TestInfo) > 0) ? (count($covid19TestInfo) + 1) : 2; ?>;
    deletedRow = [];

    function getfacilityDetails(obj) {
        $.blockUI();
        var cName = $("#facilityId").val();
        var pName = $("#province").val();
        if (pName != '' && provinceName && facilityName) {
            facilityName = false;
        }
        if ($.trim(pName) != '') {
            //if (provinceName) {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    pName: pName,
                    testType: 'covid19'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#facilityId").html(details[0]);
                        $("#district").html(details[1]);
                        $("#clinicianName").val(details[2]);
                    }
                });
            //}
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
                    testType: 'covid19'
                },
                function(data) {
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
        //check facility name
        var cName = $("#facilityId").val();
        var pName = $("#province").val();
        if (cName != '' && provinceName && facilityName) {
            provinceName = false;
        }
        if (cName != '' && facilityName) {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    cName: cName,
                    testType: 'covid19'
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

    function validateNow() {
        if ($('#isResultAuthorized').val() != "yes") {
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
        }
        $("#provinceCode").val($("#province").find(":selected").attr("data-code"));
        $("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
        flag = deforayValidator.init({
            formId: 'editCovid19RequestForm'
        });
        if (flag) {
            document.getElementById('editCovid19RequestForm').submit();
        }
    }

    function updateMotherViralLoad() {
        var motherVl = $("#motherViralLoadCopiesPerMl").val();
        var motherVlText = $("#motherViralLoadText").val();
        if (motherVlText != '') {
            $("#motherViralLoadCopiesPerMl").val('');
        }
    }



    $(document).ready(function() {
        $('.result-focus').change(function(e) {
            var status = false;
            $(".result-focus").each(function(index) {
                if ($(this).val() != "") {
                    status = true;
                }
            });
            if (status) {
                $('.change-reason').show();
                $('#reasonForChanging').addClass('isRequired');
            } else {
                $('.change-reason').hide();
                $('#reasonForChanging').removeClass('isRequired');
            }
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
        getfacilityProvinceDetails($("#facilityId").val());
        <?php if (isset($covid19Info['mother_treatment']) && in_array('Other', $covid19Info['mother_treatment'])) { ?>
            $('#motherTreatmentOther').prop('disabled', false);
        <?php } ?>

        <?php if (isset($covid19Info['mother_vl_result']) && !empty($covid19Info['mother_vl_result'])) { ?>
            updateMotherViralLoad();
        <?php } ?>

        $("#motherViralLoadCopiesPerMl").on("change keyup paste", function() {
            var motherVl = $("#motherViralLoadCopiesPerMl").val();
            //var motherVlText = $("#motherViralLoadText").val();
            if (motherVl != '') {
                $("#motherViralLoadText").val('');
            }
        });
        $('#isResultAuthorized').change(function(e) {
            checkIsResultAuthorized();
        });
        checkIsResultAuthorized();
        <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            $(document).on('change', '.test-result, #result', function(e) {
                checkPostive();
            });
            checkPostive();
        <?php } ?>
    });

    function insRow() {
        rl = document.getElementById("testKitNameTable").rows.length;
        var a = document.getElementById("testKitNameTable").insertRow(rl);
        a.setAttribute("style", "display:none");
        var b = a.insertCell(0);
        var c = a.insertCell(1);
        var d = a.insertCell(2);
        var e = a.insertCell(3);
        var f = a.insertCell(4);
        f.setAttribute("align", "center");
        b.setAttribute("align", "center");
        f.setAttribute("style", "vertical-align:middle");

        b.innerHTML = tableRowId;
        c.innerHTML = '<input type="text" name="testName[]" id="testName' + tableRowId + '" class="form-control test-name-table-input" placeholder="Test name" title="Please enter the test name for row ' + tableRowId + '"/>';
        d.innerHTML = '<input type="text" name="testDate[]" id="testDate' + tableRowId + '" class="form-control test-name-table-input dateTime" placeholder="Tested on"  title="Please enter the tested on for row ' + tableRowId + '"/>';
        e.innerHTML = '<select class="form-control test-result test-name-table-input" name="testResult[]" id="testResult' + tableRowId + '" title="Please select the result for row ' + tableRowId + '"><option value=""> -- Select -- </option><?php foreach ($covid19Results as $c19ResultKey => $c19ResultValue) { ?> <option value="<?php echo $c19ResultKey; ?>"> <?php echo $c19ResultValue; ?> </option> <?php } ?> </select>';
        f.innerHTML = '<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>';
        $(a).fadeIn(800);
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
            },
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });
        tableRowId++;

        <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            $(document).on('change', '.test-result, #result', function(e) {
                checkPostive();
            });
        <?php } ?>
    }

    function removeAttributeRow(el) {
        $(el).fadeOut("slow", function() {
            el.parentNode.removeChild(el);
            rl = document.getElementById("testKitNameTable").rows.length;
            if (rl == 0) {
                insRow();
            }
        });
    }

    function deleteRow(id) {
        deletedRow.push(id);
        $('#deletedRow').val(deletedRow);
    }

    function checkPostive() {
        var itemLength = document.getElementsByName("testResult[]");
        for (i = 0; i < itemLength.length; i++) {

            if (itemLength[i].value == 'positive') {
                $('#result,.disabled-field').val('');
                $('#result,.disabled-field').prop('disabled', true);
                $('#result,.disabled-field').addClass('disabled');
                $('#result,.disabled-field').removeClass('isRequired');
                return false;
            } else {
                $('#result,.disabled-field').prop('disabled', false);
                $('#result,.disabled-field').removeClass('disabled');
                $('#result,.disabled-field').addClass('isRequired');
            }
            if (itemLength[i].value != '') {
                $('#labId').addClass('isRequired');
            }
        }
    }

    function checkIsResultAuthorized() {
        if ($('#isResultAuthorized').val() == 'no') {
            $('#authorizedBy,#authorizedOn').val('');
            $('#authorizedBy,#authorizedOn').prop('disabled', true);
            $('#authorizedBy,#authorizedOn').addClass('disabled');
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
            return false;
        } else {
            $('#authorizedBy,#authorizedOn').prop('disabled', false);
            $('#authorizedBy,#authorizedOn').removeClass('disabled');
            $('#authorizedBy,#authorizedOn').addClass('isRequired');
        }
    }
</script>