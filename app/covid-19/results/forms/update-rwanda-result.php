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
$specimenTypeResult = $covid19Obj->getCovid19SampleTypes();
$covid19ReasonsForTesting = $covid19Obj->getCovid19ReasonsForTesting();

$covid19Symptoms = $covid19Obj->getCovid19Symptoms();
$covid19SelectedSymptoms = $covid19Obj->getCovid19SymptomsByFormId($covid19Info['covid19_id']);


$covid19Comorbidities = $covid19Obj->getCovid19Comorbidities();
$covid19SelectedComorbidities = $covid19Obj->getCovid19ComorbiditiesByFormId($covid19Info['covid19_id']);


// Getting the list of Provinces, Districts and Facilities

$rKey = '';
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";


if ($_SESSION['instanceType'] == 'remoteuser') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    if (!empty($covid19Info['remote_sample']) && $covid19Info['remote_sample'] == 'yes') {
        $sampleCode = 'remote_sample_code';
    } else {
        $sampleCode = 'sample_code';
    }
    //check user exist in user_facility_map table
    $chkUserFcMapQry = "SELECT user_id from user_facility_map where user_id='" . $_SESSION['userId'] . "'";
    $chkUserFcMapResult = $db->query($chkUserFcMapQry);
    if ($chkUserFcMapResult) {
        $pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
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
    $province .= "<option data-code='" . $provinceName['geo_code'] . "' data-province-id='" . $provinceName['geo_id'] . "' data-name='" . $provinceName['geo_name'] . "' value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}


$facility = $general->generateSelectOptions($healthFacilities, $covid19Info['facility_id'], '-- Select --');

//var_dump($facilitiesDb->getTestingPoints(16));die;

?>


<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> COVID-19 VIRUS LABORATORY TEST REQUEST FORM</h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
            <li class="active">Edit Request</li>
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
                <form class="form-horizontal" method="post" name="editCovid19RequestForm" id="editCovid19RequestForm" autocomplete="off" action="covid-19-update-result-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body disabledForm">
                                <div class="box-header with-border sectionHeader">
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
                                                <input type="text" readonly value="<?php echo ($sCode != '') ? $sCode : $covid19Info[$sampleCode]; ?>" class="form-control " id="sampleCode" name="sampleCode" placeholder="Sample ID" title="Please enter Sample ID" style="width:30%;" onchange="" />
                                            </td>
                                        <?php } ?>
                                    </tr>
                                    <tr>
                                        <td><label for="province">Province </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control " name="province" id="province" title="Please choose province" onchange="getfacilityDetails(this);" style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <td><label for="district">District </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control " name="district" id="district" title="Please choose district" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                                                <option value=""> -- Select -- </option>
                                            </select>
                                        </td>
                                        <td><label for="facilityId">Health Facility </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control  " name="facilityId" id="facilityId" title="Please choose service provider" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
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
                                                    <option value="<?php echo ($implementingPartner['i_partner_id']); ?>" <?php echo ($covid19Info['implementing_partner'] == $implementingPartner['i_partner_id']) ? "selected='selected'" : ""; ?>><?php echo ($implementingPartner['i_partner_name']); ?></option>
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
                                                    <option value="<?php echo ($fundingSource['funding_source_id']); ?>" <?php echo ($covid19Info['funding_source'] == $fundingSource['funding_source_id']) ? "selected='selected'" : ""; ?>><?php echo ($fundingSource['funding_source_name']); ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <!-- <tr> -->
                                            <td><label for="labId">Lab Name <span class="mandatory">*</span></label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control " title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, $covid19Info['lab_id'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <!-- </tr> -->
                                        <?php } ?>
                                    </tr>
                                </table>

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">PATIENT INFORMATION</h3>
                                </div>
                                <table class="table" aria-hidden="true"  style="width:100%">

                                    <tr>
                                        <th style="width:15% !important"><label for="firstName">First Name <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="firstName" name="firstName" placeholder="First Name" title="Please enter patient first name" style="width:100%;" value="<?php echo $covid19Info['patient_name']; ?>" />
                                        </td>
                                        <th style="width:15% !important"><label for="lastName">Last name </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="lastName" name="lastName" placeholder="Last name" title="Please enter patient last name" style="width:100%;" value="<?php echo $covid19Info['patient_surname']; ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="patientId">Patient ID <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="patientId" name="patientId" placeholder="Patient Identification" title="Please enter Patient ID" style="width:100%;" value="<?php echo $covid19Info['patient_id']; ?>" />
                                        </td>
                                        <th scope="row"><label for="patientDob">Date of Birth <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <input type="text" class="form-control " id="patientDob" name="patientDob" placeholder="Date of Birth" title="Please enter Date of birth" style="width:100%;" onchange="calculateAgeInYears();" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['patient_dob']); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Patient Age (years)</th>
                                        <td><input type="number" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="patientAge" name="patientAge" placeholder="Patient Age (in years)" title="Patient Age" style="width:100%;" value="<?php echo $covid19Info['patient_age']; ?>" /></td>
                                        <th scope="row"><label for="patientGender">Gender <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <select class="form-control " name="patientGender" id="patientGender">
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
                                        <th scope="row">Patient Province</th>
                                        <td><input type="text" value="<?php echo $covid19Info['patient_province']; ?>" class="form-control " id="patientProvince" name="patientProvince" placeholder="Patient Province" title="Please enter the patient province" style="width:100%;" /></td>

                                        <th scope="row">Patient District</th>
                                        <td><input class="form-control" value="<?php echo $covid19Info['patient_district']; ?>" id="patientDistrict" name="patientDistrict" placeholder="Patient District" title="Please enter the patient district" style="width:100%;"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Country of Residence</th>
                                        <td><input type="text" class="form-control" value="<?php echo $covid19Info['patient_nationality']; ?>" id="patientNationality" name="patientNationality" placeholder="Country of Residence" title="Please enter transit" style="width:100%;" /></td>

                                        <th scope="row"></th>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4">
                                            <h4>Flight Details</h4>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Airline</th>
                                        <td><input type="text" class="form-control " value="<?php echo $covid19Info['flight_airline']; ?>" id="airline" name="airline" placeholder="Airline" title="Please enter the airline" style="width:100%;" /></td>

                                        <th scope="row">Seat Number</th>
                                        <td><input type="text" class="form-control " value="<?php echo $covid19Info['flight_seat_no']; ?>" id="seatNo" name="seatNo" placeholder="Seat Number" title="Please enter the seat number" style="width:100%;" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Arrival Date and Time</th>
                                        <td><input type="text" class="form-control dateTime" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['flight_arrival_datetime']); ?>" id="arrivalDateTime" name="arrivalDateTime" placeholder="Arrival Date and Time" title="Please enter the arrival date and time" style="width:100%;" /></td>

                                        <th scope="row">Airport of Departure</th>
                                        <td><input type="text" class="form-control" value="<?php echo $covid19Info['flight_airport_of_departure']; ?>" id="airportOfDeparture" name="airportOfDeparture" placeholder="Airport of Departure" title="Please enter the airport of departure" style="width:100%;" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Transit</th>
                                        <td><input type="text" class="form-control" value="<?php echo $covid19Info['flight_transit']; ?>" id="transit" name="transit" placeholder="Transit" title="Please enter transit" style="width:100%;" /></td>
                                        <th scope="row">Reason of Visit (if applicable)</th>
                                        <td><input type="text" class="form-control" value="<?php echo $covid19Info['reason_of_visit']; ?>" id="reasonOfVisit" name="reasonOfVisit" placeholder="Reason of Visit" title="Please enter reason of visit" style="width:100%;" /></td>

                                    </tr>
                                </table>

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">CLINICAL SIGNS AND SYMPTOMS</h3>
                                </div>
                                <table class="table" aria-hidden="true" >
                                    <tr>
                                        <th style="width:15% !important">Date of Symptom Onset <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control date " type="text" name="dateOfSymptomOnset" id="dateOfSymptomOnset" placeholder="Symptom Onset Date" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['date_of_symptom_onset']); ?> " />
                                        </td>
                                        <th style="width:15% !important">Date of Initial Consultation</th>
                                        <td style="width:35% !important;">
                                            <input class="form-control date" type="text" name="dateOfInitialConsultation" id="dateOfInitialConsultation" placeholder="Date of Initial Consultation" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['date_of_initial_consultation']); ?> " />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important">Fever/Temperature (&deg;C) <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control " type="number" value="<?php echo $covid19Info['fever_temp']; ?>" name="feverTemp" id="feverTemp" placeholder="Fever/Temperature (in &deg;Celcius)" />
                                        </td>
                                        <th style="width:15% !important"></th>
                                        <td style="width:35% !important;"></td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important">Symptoms Presented in last 14 days <span class="mandatory">*</span> </th>
                                        <td colspan="3">
                                            <table style="width:60%;" class="table table-bordered" aria-hidden="true" >
                                                <?php foreach ($covid19Symptoms as $symptomId => $symptomName) { ?>
                                                    <tr>
                                                        <th style="width:50%;"><?php echo $symptomName; ?></th>
                                                        <td>
                                                            <input name="symptomId[]" type="hidden" value="<?php echo $symptomId; ?>">
                                                            <select name="symptomDetected[]" class="form-control" title="Please choose value for <?php echo $symptomName; ?>" style="width:100%">
                                                                <option value="">-- Select --</option>
                                                                <option value='yes' <?php echo (isset($covid19SelectedSymptoms[$symptomId]) && $covid19SelectedSymptoms[$symptomId] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                                <option value='no' <?php echo (isset($covid19SelectedSymptoms[$symptomId]) && $covid19SelectedSymptoms[$symptomId] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                                <option value='unknown' <?php echo (isset($covid19SelectedSymptoms[$symptomId]) && $covid19SelectedSymptoms[$symptomId] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">OTHER CO-MORBIDITIES</h3>
                                </div>
                                <table class="table" aria-hidden="true" >
                                    <tr>
                                        <th style="width:15% !important">Co-morbidities <span class="mandatory">*</span> </th>
                                        <td colspan="3">
                                            <table style="width:60%;" class="table table-bordered" aria-hidden="true" >
                                                <?php foreach ($covid19Comorbidities as $comId => $comName) { ?>
                                                    <tr>
                                                        <th style="width:50%;"><?php echo $comName; ?></th>
                                                        <td>
                                                            <input name="comorbidityId[]" type="hidden" value="<?php echo $comId; ?>">
                                                            <select name="comorbidityDetected[]" class="form-control" title="Please choose value for <?php echo $comName; ?>" style="width:100%">
                                                                <option value="">-- Select --</option>
                                                                <option value='yes' <?php echo (isset($covid19SelectedComorbidities[$comId]) && $covid19SelectedComorbidities[$comId] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                                <option value='no' <?php echo (isset($covid19SelectedComorbidities[$comId]) && $covid19SelectedComorbidities[$comId] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                                <option value='unknown' <?php echo (isset($covid19SelectedComorbidities[$comId]) && $covid19SelectedComorbidities[$comId] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">EPIDEMIOLOGICAL RISK FACTORS AND EXPOSURES</h3>
                                </div>
                                <table class="table" aria-hidden="true" >
                                    <tr>
                                        <th style="width:15% !important">Close contacts of the Patient <span class="mandatory">*</span></th>
                                        <td colspan="3">
                                            <textarea name="closeContacts" class="form-control" style="width:100%;min-height:100px;"><?php echo $covid19Info['close_contacts']; ?></textarea>
                                            <span class="text-danger">
                                                Add close contact names and phone numbers (household, family members, friends you have been in contact with in the last 14 days)
                                            </span>
                                        </td>

                                    </tr>
                                    <tr>
                                        <th scope="row">Patient's Occupation</th>
                                        <td>
                                            <input class="form-control" value="<?php echo $covid19Info['patient_occupation']; ?>" type="text" name="patientOccupation" id="patientOccupation" placeholder="Patient's Occupation" />
                                        </td>
                                        <th scope="row"></th>
                                        <td></td>

                                    </tr>

                                </table>
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">SPECIMEN INFORMATION</h3>
                                </div>
                                <table class="table" aria-hidden="true" >

                                    <tr>
                                        <td colspan=4>
                                            <ul>
                                                <li>All specimens collected should be regarded as potentially infectious and you <u>MUST CONTACT</u> the reference laboratory before sending samples.</li>
                                                <li>All samples must be sent in accordance with category B transport requirements.</li>
                                            </ul>

                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important">Lab Sample Collected? <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <select name="isSampleCollected" id="isSampleCollected" class="form-control " title="Is Lab Sample collected ?" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <option value='yes' <?php echo ($covid19Info['is_sample_collected'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                <option value='no' <?php echo ($covid19Info['is_sample_collected'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                            </select>
                                        </td>
                                        <th scope="row">Reason for Covid-19 Testing <span class="mandatory">*</span></th>
                                        <td>
                                            <select name="reasonForCovid19Test" id="reasonForCovid19Test" class="form-control " title="Please choose specimen type" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <?php echo $general->generateSelectOptions($covid19ReasonsForTesting, $covid19Info['reason_for_covid19_test']); ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important">Sample Collection Date <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control " type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" value="<?php echo ($covid19Info['sample_collection_date']); ?>" />
                                        </td>
                                        <th scope="row">Specimen Type <span class="mandatory">*</span></th>
                                        <td>
                                            <select name="specimenType" id="specimenType" class="form-control " title="Please choose specimen type" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <?php echo $general->generateSelectOptions($specimenTypeResult, $covid19Info['specimen_type']); ?>

                                            </select>
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
                                            <th scope="row"><label for="">Sample Received Date <span class="mandatory">*</span></label></th>
                                            <td>
                                                <input type="text" class="form-control isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter sample receipt date" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['sample_received_at_vl_lab_datetime']) ?>" onchange="" style="width:100%;" />
                                            </td>
                                            <td>Testing Lab <span class="mandatory">*</span></td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control isRequired" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, $covid19Info['lab_id'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        <tr>
                                            <th scope="row">Is Sample Rejected? <span class="mandatory">*</span></th>
                                            <td>
                                                <select class="form-control result-focus isRequired" name="isSampleRejected" id="isSampleRejected">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="yes" <?php echo ($covid19Info['is_sample_rejected'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                    <option value="no" <?php echo ($covid19Info['is_sample_rejected'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                </select>
                                            </td>

                                            <th class="show-rejection" style="display:none;">Reason for Rejection <span class="mandatory">*</span></th>
                                            <td class="show-rejection" style="display:none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason">
                                                    <option value="">-- Select --</option>
                                                    <?php foreach ($rejectionTypeResult as $type) { ?>
                                                        <optgroup label="<?php echo ($type['rejection_type']); ?>">
                                                            <?php
                                                            foreach ($rejectionResult as $reject) {
                                                                if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                                    <option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($covid19Info['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ($reject['rejection_reason_name']); ?></option>
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
                                                                    <td><input type="text" value="<?php echo $rows['test_name']; ?>" name="testName[]" id="testName<?= ($indexKey + 1); ?>" class="form-control test-name-table-input isRequired" placeholder="Test name" title="Please enter the test name for row <?= ($indexKey + 1); ?>" /></td>
                                                                    <td><input type="text" value="<?php echo DateUtils::humanReadableDateFormat($rows['sample_tested_datetime']); ?>" name="testDate[]" id="testDate<?= ($indexKey + 1); ?>" class="form-control test-name-table-input dateTime isRequired" placeholder="Tested on" title="Please enter the tested on for row <?= ($indexKey + 1); ?>" /></td>
                                                                    <td><select class="form-control test-result test-name-table-input isRequired" name="testResult[]" id="testResult<?= ($indexKey + 1); ?>" title="Please select the result for row <?= ($indexKey + 1); ?>">
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
                                                                <td><input type="text" name="testName[]" id="testName1" class="form-control test-name-table-input isRequired" placeholder="Test name" title="Please enter the test name for row 1" /></td>
                                                                <td><input type="text" name="testDate[]" id="testDate1" class="form-control test-name-table-input dateTime isRequired" placeholder="Tested on" title="Please enter the tested on for row 1" /></td>
                                                                <td><select class="form-control test-result test-name-table-input isRequired" name="testResult[]" id="testResult1" title="Please select the result for row 1">
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
                                                                <select class="result-focus form-control isRequired" name="result" id="result">
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
                                        <tr class="change-reason" style="display: none;">
                                            <th scope="row">Reason for Changing <span class="mandatory">*</span></td>
                                            <td colspan="3"><textarea type="text" name="reasonForChanging" id="reasonForChanging" class="form-control date" placeholder="Enter the reason for changing" title="Please enter the reason for changing"></textarea></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Reviewed By</th>
                                            <td>
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control isRequired" title="Please choose reviewed by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, $covid19Info['result_reviewed_by'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row">Reviewed on</td>
                                            <td><input type="text" value="<?php echo $covid19Info['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control isRequired" placeholder="Reviewed on" title="Please enter the Reviewed on" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Is Result Authorized ?</th>
                                            <td>
                                                <select name="isResultAuthorized" id="isResultAuthorized" class="disabled-field form-control isRequired" title="Is Result authorized ?" style="width:100%">
                                                    <option value="">-- Select --</option>
                                                    <option value='yes' <?php echo ($covid19Info['is_result_authorised'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                    <option value='no' <?php echo ($covid19Info['is_result_authorised'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                </select>
                                            </td>
                                            <th scope="row">Authorized By</th>
                                            <td><input type="text" value="<?php echo $covid19Info['authorized_by']; ?>" name="authorizedBy" id="authorizedBy" class="disabled-field form-control isRequired" placeholder="Authorized By" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Authorized on</td>
                                            <td><input type="text" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['authorized_on']); ?>" name="authorizedOn" id="authorizedOn" class="disabled-field form-control date isRequired" placeholder="Authorized on" /></td>
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
                        <input type="hidden" id="sampleCode" name="sampleCode" value="<?php echo $covid19Info[$sampleCode]; ?>" />
                        <input type="hidden" name="revised" id="revised" value="no" />
                        <input type="hidden" name="formId" id="formId" value="7" />
                        <input type="hidden" name="deletedRow" id="deletedRow" value="" />
                        <input type="hidden" name="covid19SampleId" id="covid19SampleId" value="<?php echo $covid19Info['covid19_id']; ?>" />
                        <input type="hidden" name="sampleCodeCol" id="sampleCodeCol" value="<?php echo $covid19Info['sample_code']; ?>" />
                        <input type="hidden" name="oldStatus" id="oldStatus" value="<?php echo $covid19Info['result_status']; ?>" />
                        <input type="hidden" name="provinceCode" id="provinceCode" />
                        <input type="hidden" name="provinceId" id="provinceId" />
                        <a href="/covid-19/results/covid-19-manual-results.php" class="btn btn-default"> Cancel</a>
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
        if ($('#isResultAuthorized').val() != "yes" && $('#result').val() == "") {
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
        } else {
            $('#isResultAuthorized').val('yes');
            $('#authorizedBy,#authorizedOn').addClass('isRequired');
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
            if ($('#result').val() != '' || $('#sampleRejectionReason').val() != '') {
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
            }
        });
        $('.disabledForm input, .disabledForm select , .disabledForm textarea ').attr('disabled', true);
        $('#facilityId').select2({
            placeholder: "Select Clinic/Health Center"
        });
        $('#district').select2({
            placeholder: "District"
        });
        $('#province').select2({
            placeholder: "Province"
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
        $('#authorizedBy').select2({
            placeholder: "Select Authorized By"
        });
        getfacilityProvinceDetails($("#facilityId").val());
        <?php if (isset($covid19Info['mother_treatment']) && in_array('Other', $covid19Info['mother_treatment'])) { ?> $('#motherTreatmentOther').prop('disabled', false);
        <?php } ?>

        <?php if (isset($covid19Info['mother_vl_result']) && !empty($covid19Info['mother_vl_result'])) { ?> updateMotherViralLoad();
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
        <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?> $(document).change('.test-result, #result', function(e) {
                checkPostive();
            });
            checkPostive();
        <?php } else { ?> $('#result').addClass('isRequired');
            $('#isResultAuthorized').val('yes');
        <?php } ?>
        if ($('#result').val() != "") {
            $('#isResultAuthorized').val('yes');
        }
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

    <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>

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
    <?php } ?>

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
