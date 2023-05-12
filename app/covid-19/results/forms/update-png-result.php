<?php
// imported in covid-19-add-request.php based on country in global config

use App\Registries\ContainerRegistry;
use App\Services\Covid19Service;
use App\Utilities\DateUtility;



/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);

//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
/* To get testing platform names */
$testPlatformResult = $general->getTestingPlatforms('covid19');
// Nationality
$nationalityQry = "SELECT * FROM `r_countries` ORDER BY `iso_name` ASC";
$nationalityResult = $db->query($nationalityQry);

foreach ($nationalityResult as $nrow) {
    $nationalityList[$nrow['id']] = ($nrow['iso_name']) . ' (' . $nrow['iso3'] . ')';
}

foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name']] = $row['machine_name'];
}
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);


$covid19Results = $covid19Service->getCovid19Results();
$specimenTypeResult = $covid19Service->getCovid19SampleTypes();

$covid19Symptoms = $covid19Service->getCovid19Symptoms();
$covid19SelectedSymptoms = $covid19Service->getCovid19SymptomsByFormId($covid19Info['covid19_id']);
$covid19Comorbidities = $covid19Service->getCovid19Comorbidities();
$covid19SelectedComorbidities = $covid19Service->getCovid19ComorbiditiesByFormId($covid19Info['covid19_id']);

$covid19ReasonsForTesting = $covid19Service->getCovid19ReasonsForTestingDRC();
$covid19SelectedReasonsForTesting = $covid19Service->getCovid19ReasonsForTestingByFormId($covid19Info['covid19_id']);
$covid19SelectedReasonsDetailsForTesting = $covid19Service->getCovid19ReasonsDetailsForTestingByFormId($covid19Info['covid19_id']);
// To get the reason details value
$reasonDetails = json_decode($covid19SelectedReasonsDetailsForTesting['reason_details'], true);
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
$pdResult = $db->query($pdQuery);
$provinceInfo = [];
foreach ($pdResult as $state) {
    $provinceInfo[$state['geo_name']] = ($state['geo_name']);
}
foreach ($implementingPartnerList as $implementingPartner) {
    $implementingPartnerArray[$implementingPartner['i_partner_id']] = ($implementingPartner['i_partner_name']);
}
foreach ($fundingSourceList as $fundingSource) {
    $fundingSourceArray[$fundingSource['funding_source_id']] = ($fundingSource['funding_source_name']);
}
$facility = $general->generateSelectOptions($healthFacilities, $covid19Info['facility_id'], '-- Select --');
$implementingPartnerOptions = $general->generateSelectOptions($implementingPartnerArray, $covid19Info['implementing_partner'], '-- Select --');
$fundingSourceOptions = $general->generateSelectOptions($fundingSourceArray, $covid19Info['funding_source'], '-- Select --');

//suggest N°EPID when lab user add request sample
$sampleSuggestion = '';
$sampleSuggestionDisplay = 'display:none;';
$sCode = (isset($_GET['c']) && $_GET['c'] != '') ? $_GET['c'] : '';
if ($sarr['sc_user_type'] == 'vluser' && $sCode != '') {
    $vlObj = new Covid19Service();
    $sampleCollectionDate = explode(" ", $sampleCollectionDate);
    $sampleCollectionDate = DateUtility::humanReadableDateFormat($sampleCollectionDate[0]);
    $sampleSuggestionJson = $vlObj->generateCovid19SampleCode($stateResult[0]['geo_code'], $sampleCollectionDate, 'png');
    $sampleCodeKeys = json_decode($sampleSuggestionJson, true);
    $sampleSuggestion = $sampleCodeKeys['sampleCode'];
    $sampleSuggestionDisplay = 'display:block;';
}
?>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> COVID-19 VIRUS LABORATORY TEST PNG REQUEST FORM</h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> HOME</a></li>
            <li class="active">Add New Request</li>
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
                <div class="box-body">
                    <div class="box box-default">
                        <div class="box-body disabledForm">
                            <div class="box-header with-border sectionHeader">
                                <h3 class="box-title">SITE INFORMATION</h3>
                            </div>
                            <div class="box-header with-border">
                                <h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
                            </div>
                            <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                <tr>
                                    <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                        <td><label for="sampleCode">EPID</label> </td>
                                        <td>
                                            <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?php echo $covid19Info[$sampleCode]; ?></span>
                                            <input type="hidden" class="<?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" value="<?php echo $covid19Info[$sampleCode]; ?>" />
                                        </td>
                                    <?php } else { ?>
                                        <td><label for="sampleCode">EPID</label><span class="mandatory">*</span> </td>
                                        <td>
                                            <input type="text" readonly value="<?php echo $covid19Info[$sampleCode]; ?>" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="EPID" title="Please enter EPID" style="width:100%;" onchange="" />
                                        </td>
                                    <?php } ?>
                                </tr>
                                <tr>
                                    <td><label for="province">Health Facility/Province </label><span class="mandatory">*</span></td>
                                    <td>
                                        <select class="form-control select2 isRequired" name="province" id="province" title="Please choose province" onchange="getfacilityDetails(this);" style="width:100%;">
                                            <?php echo $province; ?>
                                        </select>
                                    </td>
                                    <td><label for="district">Health Facility/District </label><span class="mandatory">*</span></td>
                                    <td>
                                        <select class="form-control select2 isRequired" name="district" id="district" title="Please choose district" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                                            <option value=""> -- Select -- </option>
                                        </select>
                                    </td>
                                    <td><label for="facilityId">Health Facility </label><span class="mandatory">*</span></td>
                                    <td>
                                        <select class="form-control isRequired " name="facilityId" id="facilityId" title="Please choose health facility" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
                                            <?php echo $facility; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="investigatorName">Investigator’s name </label></th>
                                    <td>
                                        <input type="text" class="form-control" value="<?php echo $covid19Info['investigator_name']; ?>" id="investigatorName" name="investigatorName" placeholder="Investigator’s name" title="Please enter Investigator’s name" style="width:100%;" />
                                    </td>
                                    <td><label for="investigatorPhone">Investigator’s phone</label></td>
                                    <td>
                                        <input type="text" class="form-control" value="<?php echo $covid19Info['investigator_phone']; ?>" id="investigatorPhone" name="investigatorPhone" placeholder="Investigator’s phone" title="Please enter Investigator’s phone" style="width:100%;" />
                                    </td>
                                    <td><label for="investigatorEmail">Investigator’s email</label></td>
                                    <td>
                                        <input type="text" class="form-control" value="<?php echo $covid19Info['investigator_email']; ?>" id="investigatorEmail" name="investigatorEmail" placeholder="Investigator’s email" title="Please enter Investigator’s email" style="width:100%;" />
                                    </td>
                                </tr>
                                <tr>
                                    <td><label for="clinicianName">Clinician name</label> </td>
                                    <td>
                                        <input type="text" class="form-control" value="<?php echo $covid19Info['clinician_name']; ?>" id="clinicianName" name="clinicianName" placeholder="Clinician name" title="Please enter Clinician name" style="width:100%;" />
                                    </td>
                                    </td>
                                    <td><label for="clinicianPhone">Clinician phone</label></td>
                                    <td>
                                        <input type="text" class="form-control" value="<?php echo $covid19Info['clinician_phone']; ?>" id="clinicianPhone" name="clinicianPhone" placeholder="Clinician phone" title="Please enter Clinician phone" style="width:100%;" />
                                    </td>
                                    <td><label for="investigatorEmail">Clinician email</label></td>
                                    <td>
                                        <input type="text" class="form-control" value="<?php echo $covid19Info['clinician_email']; ?>" id="clinicianEmail" name="clinicianEmail" placeholder="Clinician email" title="Please enter Clinician email" style="width:100%;" />
                                    </td>
                                </tr>
                                <tr>
                                    <td><label for="supportPartner">Implementing Partner </label></td>
                                    <td>
                                        <select class="form-control select2" name="implementingPartner" id="implementingPartner" title="Please choose implementing partner" style="width:100%;">
                                            <?php echo $implementingPartnerOptions; ?>
                                        </select>
                                    </td>
                                    <td><label for="fundingSource">Funding Partner</label></td>
                                    <td>
                                        <select class="form-control select2" name="fundingSource" id="fundingSource" title="Please choose source of funding" style="width:100%;">
                                            <?php echo $fundingSourceOptions; ?>
                                        </select>
                                    </td>
                                    <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                        <!-- <tr> -->
                                        <td><label for="labId">Testing Laboratory <span class="mandatory">*</span></label> </td>
                                        <td>
                                            <select name="labId" id="labId" class="form-control select2 isRequired" title="Please select Testing Testing Laboratory" style="width:100%;">
                                                <?= $general->generateSelectOptions($testingLabs, $covid19Info['lab_id'], '-- Select --'); ?>
                                            </select>
                                        </td>
                                        <!-- </tr> -->
                                    <?php } else { ?>
                                        <th scope="row"></th>
                                        <td></td>
                                    <?php } ?>
                                </tr>
                            </table>


                            <div class="box-header with-border sectionHeader">
                                <h3 class="box-title">PATIENT INFORMATION</h3>
                            </div>
                            <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">

                                <tr>
                                    <th scope="row" style="width:15% !important"><label for="firstName">Patient first name <span class="mandatory">*</span> </label></th>
                                    <td style="width:35% !important">
                                        <input type="text" class="form-control isRequired" value="<?php echo $covid19Info['patient_name']; ?>" id="firstName" name="firstName" placeholder="Patient first name" title="Please enter the Patient first name" style="width:100%;" />
                                    </td>
                                    <th scope="row" style="width:15% !important"><label for="lastName">Patient last name </label></th>
                                    <td style="width:35% !important">
                                        <input type="text" class="form-control " value="<?php echo $covid19Info['patient_surname']; ?>" id="lastName" name="lastName" placeholder="Patient last name" title="Please enter the Patient last name" style="width:100%;" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width:15% !important"><label for="patientId">Patient ID if admitted in ward <span class="mandatory">*</span> </label></th>
                                    <td style="width:35% !important">
                                        <input type="text" class="form-control isRequired" value="<?php echo $covid19Info['patient_id']; ?>" id="patientId" name="patientId" placeholder="Patient ID" title="Please enter the Patient ID" style="width:100%;" />
                                    </td>
                                    <th scope="row"><label for="patientDob">Patient date of birth<span class="mandatory">*</span> </label></th>
                                    <td>
                                        <input type="text" class="form-control isRequired" value="<?php echo DateUtility::humanReadableDateFormat($covid19Info['patient_dob']); ?>" id="patientDob" name="patientDob" placeholder="Date of birth" title="Please enter Date of birth" style="width:100%;" onchange="calculateAgeInYears();" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Age (years)</th>
                                    <td><input type="number" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " value="<?php echo $covid19Info['patient_age']; ?>" id="patientAge" name="patientAge" placeholder="Age (in years)" title="Please enter Age (in years)" style="width:100%;" /></td>
                                    <th scope="row"><label for="patientGender">Sex <span class="mandatory">*</span> </label></th>
                                    <td>
                                        <select class="form-control isRequired" name="patientGender" id="patientGender" title="Please select the gender">
                                            <option value=''> -- Select -- </option>
                                            <option value='male' <?php echo ($covid19Info['patient_gender'] == 'male') ? "selected='selected'" : ""; ?>> Male </option>
                                            <option value='female' <?php echo ($covid19Info['patient_gender'] == 'female') ? "selected='selected'" : ""; ?>> Female </option>
                                            <option value='other' <?php echo ($covid19Info['patient_gender'] == 'other') ? "selected='selected'" : ""; ?>> Other </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="patientPhoneNumber">Patient phone</label></th>
                                    <td><input type="text" class="form-control " value="<?php echo $covid19Info['patient_phone_number']; ?>" id="patientPhoneNumber" name="patientPhoneNumber" placeholder="Patient phone" title="Please enter the patient phone" style="width:100%;" /></td>

                                    <th scope="row"><label for="patientProvince">Province</label></th>
                                    <td>
                                        <select class="form-control " name="patientProvince" id="patientProvince" title="Please select the patient province" onchange="getPatientDistrictDetails(this.value);" style="width:100%;">
                                            <?= $general->generateSelectOptions($provinceInfo, $covid19Info['patient_province'], '-- Select --'); ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="patientDistrict">District</label></th>
                                    <td>
                                        <select class="form-control select2" name="patientDistrict" id="patientDistrict" title="Please select the patient district" style="width:100%;">
                                            <option value=""> -- Select -- </option>
                                        </select>
                                    </td>

                                    <th scope="row"><label for="patientCity">Village</label></th>
                                    <td><input class="form-control" value="<?php echo $covid19Info['patient_city']; ?>" id="patientCity" name="patientCity" placeholder="City/Village" title="Please enter the City/Village" style="width:100%;"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="patientNationality">Country of origin</label></th>
                                    <td>
                                        <select name="patientNationality" id="patientNationality" class="form-control" title="Please choose Country of origin:" style="width:100%">
                                            <?= $general->generateSelectOptions($nationalityList, $covid19Info['patient_nationality'], '-- Select --'); ?>
                                        </select>
                                    </td>
                                    <th scope="row"></th>
                                    <td></td>
                                </tr>
                            </table>

                            <div class="box-header with-border sectionHeader">
                                <h3 class="box-title">
                                    PATIENT HISTORY
                                </h3>
                            </div>
                            <table aria-describedby="table" id="responseTable" class="table table-bordered" aria-hidden="true">
                                <th scope="row"><label for="suspectedCase">Is the suspected case</label></th>
                                <td>
                                    <select name="suspectedCase" id="suspectedCase" class="form-control" title="Please choose suspected case">
                                        <option value="">--Select--</option>
                                        <option value="asymptomatic" <?php echo (isset($covid19Info['suspected_case']) && $covid19Info['suspected_case'] == 'asymptomatic') ? "selected='selected'" : ""; ?>>Asymptomatic</option>
                                        <option value="symptomatic" <?php echo (isset($covid19Info['suspected_case']) && $covid19Info['suspected_case'] == 'symptomatic') ? "selected='selected'" : ""; ?>>Symptomatic</option>
                                        <option value="unknown" <?php echo (isset($covid19Info['suspected_case']) && $covid19Info['suspected_case'] == 'unknown') ? "selected='selected'" : ""; ?>>Unknown</option>
                                    </select>
                                </td>
                                <th scope="row"><label for="dateOfSymptomOnset">Date of symptom onset</label></th>
                                <td>
                                    <input class="form-control date" value="<?php echo DateUtility::humanReadableDateFormat($covid19Info['date_of_symptom_onset']); ?>" type="text" name="dateOfSymptomOnset" id="dateOfSymptomOnset" placeholder="Date of symptom onset" title="Please choose Date of symptom onset" />
                                </td>
                                <tr>
                                    <th scope="row" colspan="4" style="width:15% !important">Symptoms <span class="mandatory">*</span> </th>
                                </tr>
                                <tr>
                                    <td colspan="4">
                                        <table aria-describedby="table" id="symptomsTable" class="table table-bordered table-striped" aria-hidden="true">
                                            <?php $index = 0;
                                            foreach ($covid19Symptoms as $symptomId => $symptomName) {
                                                $diarrhée = "";
                                                $display = "display:none;";
                                                if ($symptomId == 13) {
                                                    $display = (isset($covid19SelectedSymptoms[$symptomId]['value']) && $covid19SelectedSymptoms[$symptomId]['value'] == "yes") ? "" : 'display:none;';
                                                } ?>
                                                <tr class="row<?php echo $index; ?>">
                                                    <th style="width:50%;"><label for="symptomDetected<?php echo $symptomId; ?>"><?php echo $symptomName; ?></label></th>
                                                    <td style="width:50%;">
                                                        <input name="symptomId[]" type="hidden" value="<?php echo $symptomId; ?>">
                                                        <select name="symptomDetected[]" id="symptomDetected<?php echo $symptomId; ?>" class="form-control <?php echo $diarrhée; ?>" title="Please select the <?php echo $symptomName; ?>" style="width:100%">
                                                            <option value="">-- Select --</option>
                                                            <option value='yes' <?php echo (isset($covid19SelectedSymptoms[$symptomId]) && $covid19SelectedSymptoms[$symptomId] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                            <option value='no' <?php echo (isset($covid19SelectedSymptoms[$symptomId]) && $covid19SelectedSymptoms[$symptomId] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                            <option value='unknown' <?php echo (isset($covid19SelectedSymptoms[$symptomId]) && $covid19SelectedSymptoms[$symptomId] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                                        </select>
                                                    </td>
                                                </tr>

                                            <?php $index++;
                                            } ?>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" colspan=2><label for="hasRecentTravelHistory">Has the patient had a recent history of travelling to an affected area?</label><span class="mandatory">*</span></th>
                                    <td style="width:25% !important;">
                                        <select name="hasRecentTravelHistory" id="hasRecentTravelHistory" class="form-control isRequired" title="Please choose if the patient has had a recent history of travelling to an affected area" style="width:100%">
                                            <option value="">-- Select --</option>
                                            <option value='yes' <?php echo (isset($covid19Info['has_recent_travel_history']) && $covid19Info['has_recent_travel_history'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                            <option value='no' <?php echo (isset($covid19Info['has_recent_travel_history']) && $covid19Info['has_recent_travel_history'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                            <option value='unknown' <?php echo (isset($covid19Info['has_recent_travel_history']) && $covid19Info['has_recent_travel_history'] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="historyfield">
                                    <!-- <th scope="row"><label for="overseas">Overseas</label></th>
                                        <td>
                                            <input class="historyfield form-control" type="text" name="overseas" id="overseas" placeholder="Overseas" title="Please enter the overseas" />
                                        </td> -->
                                    <th scope="row"><label for="countryName">If Yes, Country Name(s)</label></th>
                                    <td>
                                        <input class="historyfield form-control" value="<?php echo $covid19Info['travel_country_names']; ?>" type="text" name="countryName" id="countryName" placeholder="Country Name(s)" title="Please enter the country name(s)" />
                                    </td>
                                    <th scope="row"><label for="returnDate">Return Date</label></th>
                                    <td>
                                        <input class="historyfield form-control date" value="<?php echo DateUtility::humanReadableDateFormat($covid19Info['date_of_symptom_onset']); ?>" type="text" name="returnDate" id="returnDate" placeholder="Return Date" title="Please enter the return date" />
                                    </td>
                                </tr>
                                <tr class="historyfield">
                                </tr>
                                <tr>
                                    <th scope="row" style="width:15% !important"><label for="closeContacts">Close contacts of the Patient</label></th>
                                    <td colspan="3">
                                        <textarea name="closeContacts" class="form-control" style="width:100%;min-height:100px;" title="Please enter the close contacts of the patient"><?php echo $covid19Info['close_contacts']; ?></textarea>
                                        <span class="text-danger">*A close contact includes living in the same household, having face-to face contact (<1m) for 15 mins or more, or spending ≥2hrs in the same enclosed space, or having direct physical contact with a COVID-19 case</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="patientOccupation">Patient's Occupation</label></th>
                                    <td>
                                        <input class="form-control" value="<?php echo $covid19Info['patient_occupation']; ?>" type="text" name="patientOccupation" id="patientOccupation" placeholder="Patient's Occupation" title="Please enter the patient's occupation" />
                                    </td>

                                    <th scope="row"><label for="contactWithConfirmedCase">Cared for a COVID-19 patient?</th>
                                    <td>
                                        <select name="contactWithConfirmedCase" id="contactWithConfirmedCase" class="form-control" title="Please choose if the person cared for a COVID-19 patient" style="width:100%">
                                            <option value="">-- Select --</option>
                                            <option value='yes' <?php echo (isset($covid19Info['contact_with_confirmed_case']) && $covid19Info['contact_with_confirmed_case'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                            <option value='no' <?php echo (isset($covid19Info['contact_with_confirmed_case']) && $covid19Info['contact_with_confirmed_case'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                            <option value='unknown' <?php echo (isset($covid19Info['contact_with_confirmed_case']) && $covid19Info['contact_with_confirmed_case'] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            <div class="box-header with-border sectionHeader">
                                <h3 class="box-title">
                                    TEST REASON
                                </h3>
                            </div>
                            <table aria-describedby="table" class="table" aria-hidden="true">
                                <tr>
                                    <th scope="row" style="width: 15%;"><label for="reasonForCovid19Test">Reason for Test Request<span class="mandatory">*</span></label></th>
                                    <td style="width: 35%;">
                                        <select name="reasonForCovid19Test" id="reasonForCovid19Test" class="form-control isRequired" title="Please choose reason for testing" style="width:100%">
                                            <?= $general->generateSelectOptions($covid19ReasonsForTesting, $covid19Info['reason_for_covid19_test'], '-- Select --'); ?>
                                        </select>
                                    </td>
                                    <th scope="row" style="width: 15%;"></th>
                                    <td style="width: 35%;"></td>
                                </tr>
                            </table>
                            <div class="box-header with-border sectionHeader">
                                <h3 class="box-title">
                                    COMORBIDITIES
                                </h3>
                            </div>
                            <table aria-describedby="table" class="table" aria-hidden="true">
                                <tr>
                                    <th scope="row"><label for="otherDiseases">Does the patient have another diagnosis/etiology for their illness?</label></th>
                                    <td>
                                        <select name="otherDiseases" id="otherDiseases" class="form-control" title="Please choose another diagnosis">
                                            <option value="">-- Select --</option>
                                            <option value='yes' <?php echo (isset($covid19Info['other_diseases']) || $covid19Info['other_diseases'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                            <option value='no' <?php echo (isset($covid19Info['other_diseases']) && $covid19Info['other_diseases'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                            <option value='unknown' <?php echo (isset($covid19Info['other_diseases']) && $covid19Info['other_diseases'] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                        </select>
                                    </td>
                                    <th scope="row"><label for="otherDiseases">If it is yes? Then Please specify</label></th>
                                    <td>
                                        <input class="form-control" value="<?php echo $covid19Info['other_diseases']; ?>" type="text" name="otherDiseases" id="otherDiseases" placeholder="Another diagnosis" title="Please enter the another diagnosis" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="medicalHistory">Comorbidities Medical History</label></th>
                                    <td>
                                        <select name="medicalHistory" id="medicalHistory" class="form-control" title="Please choose the comorbidities medical history">
                                            <option value="">-- Select --</option>
                                            <option value='yes' <?php echo (isset($covid19Info['medical_history']) && $covid19Info['medical_history'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                            <option value='no' <?php echo (isset($covid19Info['medical_history']) && $covid19Info['medical_history'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                            <option value='unknown' <?php echo (isset($covid19Info['medical_history']) && $covid19Info['medical_history'] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="comorbidities-row" style="<?php echo ($covid19Info['medical_history'] != 'yes') ? 'display:none' : ''; ?>">
                                    <td colspan="4">
                                        <table aria-describedby="table" id="comorbiditiesTable" class="table table-bordered" aria-hidden="true">
                                            <?php $index = 0;
                                            foreach ($covid19Comorbidities as $comorbiditiesId => $comorbiditiesName) { ?>
                                                <tr>
                                                    <th style="width:50%;"><?php echo $comorbiditiesName; ?></th>
                                                    <td style="width:50%;">
                                                        <input name="comorbidityId[]" type="hidden" value="<?php echo $comorbiditiesId; ?>">
                                                        <select name="comorbidityDetected[]" class="form-control" title="<?php echo $comorbiditiesName; ?>" style="width:100%">
                                                            <option value="">-- Select --</option>
                                                            <option value='yes' <?php echo (isset($covid19SelectedComorbidities[$comorbiditiesId]) && $covid19SelectedComorbidities[$comorbiditiesId] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                            <option value='no' <?php echo (isset($covid19SelectedComorbidities[$comorbiditiesId]) && $covid19SelectedComorbidities[$comorbiditiesId] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                            <option value='unknown' <?php echo (isset($covid19SelectedComorbidities[$comorbiditiesId]) && $covid19SelectedComorbidities[$comorbiditiesId] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php $index++;
                                            } ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <div class="box-header with-border sectionHeader">
                                <h3 class="box-title">
                                    SAMPLE COLLECTION
                                </h3>
                            </div>
                            <table aria-describedby="table" class="table" aria-hidden="true">
                                <tr>
                                    <th scope="row" style="width: 15%;"><label for="specimenType"> Type of sample collection <span class="mandatory">*</span></label></th>
                                    <td style="width: 35%;">
                                        <select class="form-control isRequired" id="specimenType" name="specimenType" title="Please choose the sample type">
                                            <?php echo $general->generateSelectOptions($specimenTypeResult, $covid19Info['specimen_type'], '-- Select --'); ?>
                                        </select>
                                    </td>
                                    <th scope="row" style="width:15% !important"><label for="sampleCollectionDate">Sample Collection Date<span class="mandatory">*</span></label></th>
                                    <td style="width:35% !important;">
                                        <input class="form-control isRequired" value="<?php echo ($covid19Info['sample_collection_date']); ?>" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select the sample collection date" onchange="sampleCodeGeneration();" />
                                    </td>
                                </tr>
                            </table>
                            <div class="box-header with-border sectionHeader">
                                <h3 class="box-title">
                                    HEALTH OUTCOME
                                </h3>
                            </div>
                            <table aria-describedby="table" class="table" aria-hidden="true">
                                <tr>
                                    <th scope="row" style="width: 15%;"><label for="healthOutcome"> Health Outcome</label></th>
                                    <td style="width: 35%;">
                                        <select class="form-control" id="healthOutcome" name="healthOutcome" title="Please select the health outcome">
                                            <option value="">-- Select --</option>
                                            <option value='alive' <?php echo (isset($covid19Info['health_outcome']) && $covid19Info['health_outcome'] == 'alive') ? "selected='selected'" : ""; ?>>Alive</option>
                                            <option value='recovered' <?php echo (isset($covid19Info['health_outcome']) && $covid19Info['health_outcome'] == 'recovered') ? "selected='selected'" : ""; ?>>Recovered</option>
                                            <option value='transferred' <?php echo (isset($covid19Info['health_outcome']) && $covid19Info['health_outcome'] == 'transferred') ? "selected='selected'" : ""; ?>>Transferred</option>
                                            <option value='died' <?php echo (isset($covid19Info['health_outcome']) && $covid19Info['health_outcome'] == 'died') ? "selected='selected'" : ""; ?>> Died </option>
                                        </select>
                                    </td>
                                    <th scope="row" style="width:15% !important"><label for="outcomeDate">Outcome Date</label></th>
                                    <td style="width:35% !important;">
                                        <input class="form-control date" value="<?php echo DateUtility::humanReadableDateFormat($covid19Info['health_outcome_date']); ?>" type="text" name="outcomeDate" id="outcomeDate" placeholder="Outcome Date" title="Please select the outcome date" />
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <form class="form-horizontal" method="post" name="updateCovid19ConfirmatoryRequestForm" id="updateCovid19ConfirmatoryRequestForm" autocomplete="off" action="covid-19-update-result-helper.php">
                        <?php if ($_SESSION['instanceType'] != 'remoteuser') { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">RESPIRATORY LABORATORY DIAGNOSTIC RESULTS </h3>
                                    </div>
                                    <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                        <tr>
                                            <th scope="row"><label for="sampleReceivedDate">Date of Sample Received </label></th>
                                            <td>
                                                <input type="text" class="form-control isRequired" value="<?php echo DateUtility::humanReadableDateFormat($covid19Info['sample_received_at_vl_lab_datetime']); ?>" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter the date of sample was received" <?php echo (isset($labFieldDisabled) && trim($labFieldDisabled) != '') ? $labFieldDisabled : ''; ?> style="width:100%;" />
                                            </td>

                                            <td class="lab-show"><label for="labId">Lab ID number (Filled by lab staff)</label> </td>
                                            <td class="lab-show">
                                                <select name="labId" id="labId" class="form-control isRequired" title="Please choose the laboratory name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, $covid19Info['lab_id'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        <tr>
                                            <th scope="row"><label for="isSampleRejected">Is sample Rejected?</label></th>
                                            <td>
                                                <select class="form-control isRequired" name="isSampleRejected" id="isSampleRejected" title="Please select Is sample rejected or not">
                                                    <option value="">--Select--</option>
                                                    <option value="yes" <?php echo (isset($covid19Info['is_sample_rejected']) && $covid19Info['is_sample_rejected'] == 'yes') ? "selected='selected'" : ""; ?>>yes</option>
                                                    <option value="no" <?php echo (isset($covid19Info['is_sample_rejected']) && $covid19Info['is_sample_rejected'] == 'no') ? "selected='selected'" : ""; ?>>No</option>
                                                </select>
                                            </td>

                                        </tr>
                                        <tr class="show-rejection" style="display:none;">
                                            <th scope="row" class="show-rejection" style="display:none;"><label for="sampleRejectionReason">Rejection reason<span class="mandatory">*</span></label></th>
                                            <td class="show-rejection" style="display:none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="Please select the rejection reason">
                                                    <option value=''> -- Select -- </option>
                                                    <?php echo $rejectionReason; ?>
                                                </select>
                                            </td>
                                            <th scope="row" class="show-rejection" style="display:none;"><label for="rejectionDate">Date of rejected<span class="mandatory">*</span></label></th>
                                            <td class="show-rejection" style="display:none;"><input class="form-control date rejection-date" type="text" value="<?php echo DateUtility::humanReadableDateFormat($covid19Info['rejection_on']); ?>" name="rejectionDate" id="rejectionDate" placeholder="Date of rejected" title="Please select when sample rejected" /></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4">
                                                <table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true">
                                                    <thead>
                                                        <tr>
                                                            <th scope="row" class="text-center">Test No</th>
                                                            <th scope="row" class="text-center">Test Method</th>
                                                            <th scope="row" class="text-center">Date of Testing</th>
                                                            <th scope="row" class="text-center">Test Platform</th>
                                                            <th scope="row" class="text-center">Test Result</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="testKitNameTable">
                                                        <?php if (isset($covid19TestInfo) && !empty($covid19TestInfo)) {
                                                            foreach ($covid19TestInfo as $indexKey => $rows) { ?>
                                                                <tr>
                                                                    <td class="text-center"><?= ($indexKey + 1); ?> <input type="hidden" name="testId[]" value="<?php echo base64_encode($rows['test_id']); ?>"></td>
                                                                    <td>
                                                                        <select onchange="otherCovidTestName(this.value,<?= ($indexKey + 1); ?>)" class="form-control test-name-table-input isRequired" id="testName<?= ($indexKey + 1); ?>" name="testName[]" title="Please enter the name of the Testkit (or) Test Method used">
                                                                            <option value="">-- Select --</option>
                                                                            <option value="Real Time RT-PCR" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'Real Time RT-PCR') ? "selected='selected'" : ""; ?>>Real Time RT-PCR</option>
                                                                            <option value="RDT-Antibody" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'RDT-Antibody') ? "selected='selected'" : ""; ?>>RDT-Antibody</option>
                                                                            <option value="RDT-Antigen" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'RDT-Antigen') ? "selected='selected'" : ""; ?>>RDT-Antigen</option>
                                                                            <option value="ELISA" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'ELISA') ? "selected='selected'" : ""; ?>>ELISA</option>
                                                                            <option value="other" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'other') ? "selected='selected'" : ""; ?>>Others</option>
                                                                        </select>
                                                                        <?php
                                                                        $value = '';
                                                                        if ($rows['test_name'] != 'Real Time RT-PCR' && $rows['test_name'] != 'RDT-Antibody' && $rows['test_name'] != 'RDT-Antigen' && $rows['test_name'] != 'ELISA' && $rows['test_name'] != 'other') {
                                                                            $value = 'value="' . $rows['test_name'] . '"';
                                                                            $show =  "block";
                                                                        } else {
                                                                            $show =  "none";
                                                                        } ?>
                                                                        <input <?php echo $value; ?> type="text" name="testNameOther[]" id="testNameOther<?= ($indexKey + 1); ?>" class="form-control testNameOther<?= ($indexKey + 1); ?> isRequired" title="Please enter the name of the Testkit (or) Test Method used" placeholder="Please enter the name of the Testkit (or) Test Method used" style="display: <?php echo $show; ?>;margin-top: 10px;" />
                                                                    </td>
                                                                    <td><input type="text" value="<?php echo DateUtility::humanReadableDateFormat($rows['sample_tested_datetime']); ?>" name="testDate[]" id="testDate<?= ($indexKey + 1); ?>" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row <?= ($indexKey + 1); ?>" /></td>
                                                                    <td>
                                                                        <select type="text" name="testingPlatform[]" id="testingPlatform<?= ($indexKey + 1); ?>" class="form-control test-name-table-input isRequired" title="Please select the Testing Platform for <?= ($indexKey + 1); ?>">
                                                                            <?= $general->generateSelectOptions($testPlatformList, $rows['testing_platform'], '-- Select --'); ?>
                                                                        </select>
                                                                    </td>
                                                                    <td>
                                                                        <select class="form-control test-result test-name-table-input isRequired" name="testResult[]" id="testResult<?= ($indexKey + 1); ?>" title="Please select the result for row <?= ($indexKey + 1); ?>">
                                                                            <?= $general->generateSelectOptions($covid19Results, $rows['result'], '-- Select --'); ?>
                                                                        </select>
                                                                    </td>
                                                                    <td style="vertical-align:middle;text-align: center;width:100px;">
                                                                        <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
                                                                        <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);deleteRow('<?php echo base64_encode($rows['test_id']); ?>');"><em class="fa-solid fa-minus"></em></a>
                                                                    </td>
                                                                </tr>
                                                            <?php }
                                                        } else { ?>
                                                            <tr>
                                                                <td class="text-center">1</td>
                                                                <td>
                                                                    <select onchange="otherCovidTestName(this.value,1)" class="form-control test-name-table-input isRequired" id="testName1" name="testName[]" title="Please enter the name of the Testkit (or) Test Method used">
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
                                                                <td><input type="text" name="testDate[]" id="testDate1" class="form-control test-name-table-input dateTime isRequired" placeholder="Tested on" title="Please enter the tested on for row 1" /></td>
                                                                <td>
                                                                    <select type="text" name="testingPlatform[]" id="testingPlatform1" class="form-control test-name-table-input isRequired" title="Please select the Testing Platform for 1">
                                                                        <?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <select class="form-control test-result test-name-table-input isRequired" name="testResult[]" id="testResult1" title="Please select the result for row 1">
                                                                        <?= $general->generateSelectOptions($covid19Results, null, '-- Select --'); ?>
                                                                    </select>
                                                                </td>
                                                                <td style="vertical-align:middle;text-align: center;width:100px;">
                                                                    <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
                                                                    <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
                                                                </td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <th scope="row" colspan="4" class="text-right"><label for="result">Final result</label></th>
                                                            <td>
                                                                <select class="result-focus form-control isRequired" name="result" id="result" title="Please select the Final result">
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
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control isRequired" title="Please choose reviewed by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, $covid19Info['result_reviewed_by'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row">Reviewed On</th>
                                            <td><input type="text" value="<?php echo $covid19Info['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control isRequired" placeholder="Reviewed on" title="Please enter the Reviewed on" /></td>
                                        </tr>
                                        <tr>

                                            <th scope="row"><label for="isResultAuthorized">Referred for other testing?</label></th>
                                            <td>
                                                <select name="isResultAuthorized" id="isResultAuthorized" class="disabled-field form-control isRequired" title="Please select referred for other testing?" style="width:100%">
                                                    <option value="">-- Select --</option>
                                                    <option value='yes' <?php echo ($covid19Info['is_result_authorised'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                    <option value='no' <?php echo ($covid19Info['is_result_authorised'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                </select>
                                            </td>
                                            <?php
                                            $disabled = (isset($covid19Info['is_result_authorised']) && $covid19Info['is_result_authorised'] == 'no') ? "disabled" : "";
                                            ?>
                                            <th scope="row"><label for="isResultAuthorized">Referred By</label></th>
                                            <td><input type="text" <?php echo $disabled; ?> value="<?php echo $covid19Info['authorized_by']; ?>" name="authorizedBy" id="authorizedBy" class="disabled-field form-control isRequired" placeholder="Referred By" title="Please enter who referred result" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="isResultAuthorized">Referred On</label></th>
                                            <td><input type="text" <?php echo $disabled; ?> value="<?php echo DateUtility::humanReadableDateFormat($covid19Info['authorized_on']); ?>" name="authorizedOn" id="authorizedOn" class="disabled-field form-control date isRequired" placeholder="Referred On" title="Please enter when referred result" /></td>
                                            <th scope="row"></th>
                                            <td></td>
                                        </tr>
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
                    <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Update</a>
                    <input type="hidden" id="sampleCode" name="sampleCode" value="<?php echo $covid19Info[$sampleCode]; ?>" />
                    <input type="hidden" name="revised" id="revised" value="no" />
                    <input type="hidden" name="formId" id="formId" value="5" />
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
    tableRowId = 2;
    let testCounter = <?php echo (isset($covid19TestInfo) && !empty($covid19TestInfo)) ? (count($covid19TestInfo)) : 0; ?>;
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
                        //$("#clinicianName").val(details[2]);
                    }
                });
            //}
            sampleCodeGeneration();
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

    function sampleCodeGeneration() {
        var pName = $("#province").val();
        var sDate = $("#sampleCollectionDate").val();
        if (pName != '' && sDate != '') {
            $.post("/covid-19/requests/generateSampleCode.php", {
                    sDate: sDate,
                    pName: pName
                },
                function(data) {
                    var sCodeKey = JSON.parse(data);
                    $("#sampleCode").val(sCodeKey.sampleCode);
                    $("#sampleCodeInText").html(sCodeKey.sampleCodeInText);
                    $("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
                    $("#sampleCodeKey").val(sCodeKey.sampleCodeKey);
                });
        }
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
                        // $("#clinicianName").val(details[2]);
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
            formId: 'updateCovid19ConfirmatoryRequestForm'
        });
        if (flag) {
            document.getElementById('updateCovid19ConfirmatoryRequestForm').submit();
        }
    }


    $(document).ready(function() {

        $('#facilityId').select2({
            placeholder: "POINT DE COLLECT"
        });
        /* $('#investigatorName').select2({
            placeholder: "Investigator"
        });
        $('#clinicianName').select2({
            placeholder: "Clinician"
        }); */
        // $('#province').select2({
        //     placeholder: "Province"
        // });
        $('.diarrhée').change(function(e) {
            if (this.value == "yes") {
                $('.diarrhée-sub').show();
            } else {
                $('.diarrhée-sub').hide();
            }
        });
        $('#medicalHistory').change(function(e) {
            if ($(this).val() == "yes") {
                $('.comorbidities-row').show();
            } else {
                $('.comorbidities-row').hide();
            }
        });

        $('#isResultAuthorized').change(function(e) {
            checkIsResultAuthorized();
        });
        $('#medicalBackground').change(function(e) {
            if (this.value == 'yes') {
                $('.medical-background-info').css('display', 'table-cell');
                $('.medical-background-info').css('color', 'red');
                $('.medical-background-yes').css('display', 'table-row');
            } else {
                $('.medical-background-yes,.medical-background-info').css('display', 'noe');
            }
        });

        $('#respiratoryRateSelect').change(function(e) {
            if (this.value == 'yes') {
                $('.respiratory-rate').css('display', 'inline-flex ');
            } else {
                $('.respiratory-rate').css('display', 'noe');
            }
        });

        $('#oxygenSaturationSelect').change(function(e) {
            if (this.value == 'yes') {
                $('.oxygen-saturation').css('display', 'inline-flex');
            } else {
                $('.oxygen-saturation').css('display', 'noe');
            }
        });
        $('#result').change(function(e) {
            if (this.value == 'positive') {
                $('.other-diseases').hide();
                $('#otherDiseases').removeClass('isRequired');
            } else {
                $('.other-diseases').show();
                $('#otherDiseases').addClass('isRequired');
            }
        });

        <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            $(document).on('change', '.test-result, #result', function(e) {
                checkPostive();
            });
        <?php } ?>

        getfacilityProvinceDetails($("#facilityId").val());
        getPatientDistrictDetails('<?php echo $covid19Info['patient_province']; ?>');

        <?php if (isset($covid19Info['result']) && $covid19Info['result'] != "") { ?>
            $("#updateCovid19ConfirmatoryRequestForm :input").prop("disabled", true);
            $("#updateCovid19ConfirmatoryRequestForm :input").removeClass("isRequired");
            $('.submit-btn').remove();
        <?php } else { ?>
            $('.disabledForm input, .disabledForm select , .disabledForm textarea, .test-name-table-input').attr('disabled', true);
            $('.test-name-table-input').prop('disabled', true);
        <?php } ?>

        $('.enable-input input, .enable-input select , .enable-input textarea').attr('disabled', false);
        $('#sampleCode').attr('disabled', false);
    });


    function deleteRow(id) {
        deletedRow.push(id);
        console.log(deleteRow);
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
        if ($('#isResultAuthorized').val() == 'yes') {
            $('#authorizedBy,#authorizedOn').prop('disabled', false);
            $('#authorizedBy,#authorizedOn').removeClass('disabled');
            $('#authorizedBy,#authorizedOn').addClass('isRequired');
        } else {
            $('#authorizedBy,#authorizedOn').val('');
            $('#authorizedBy,#authorizedOn').prop('disabled', true);
            $('#authorizedBy,#authorizedOn').addClass('disabled');
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
        }
    }

    function otherCovidTestName(val, id) {
        if (val == 'other') {
            $('.testInputOther' + id).show();
        } else {
            $('.testInputOther' + id).hide();
        }
    }

    function checkSubReason(obj, show) {
        $('.reason-checkbox').prop("checked", false);
        if ($(obj).prop("checked", true)) {
            $('.' + show).show();
            $('.' + show).removeClass('hide-reasons');
            $('.hide-reasons').hide();
            $('.' + show).addClass('hide-reasons');
        }
    }

    function checkSubSymptoms(obj, parent, row, sub = "") {
        //alert(obj.value);
        if (obj.value === 'yes') {
            $.post("getSymptomsByParentId.php", {
                    symptomParent: parent
                },
                function(data) {
                    if (data != "") {
                        if ($('.hide-symptoms').hasClass('symptomRow' + parent)) {
                            $('.symptomRow' + parent).remove();
                        }
                        $(".row" + row).after(data);
                    }
                });
        } else {
            $('.symptomRow' + parent).remove();
        }
    }

    function addTestRow() {
        testCounter++;
        let rowString = `<tr>
                    <td class="text-center">${testCounter}</td>
                    <td>
                    <select onchange="otherCovidTestName(this.value,${testCounter})" class="form-control test-name-table-input" id="testName${testCounter}" name="testName[]" title="Please enter the name of the Testkit (or) Test Method used">
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
            <td><select type="text" name="testingPlatform[]" id="testingPlatform${testCounter}" class="form-control test-name-table-input" title="Please select the Testing Platform for ${testCounter}"><?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?></select></td>
            <td>
                <select class="form-control test-result test-name-table-input" name="testResult[]" id="testResult${testCounter}" title="Please select the result"><?= $general->generateSelectOptions($covid19Results, null, '-- Select --'); ?></select>
            </td>
            <td style="vertical-align:middle;text-align: center;width:100px;">
                <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow(this);"><em class="fa-solid fa-plus"></em></a>&nbsp;
                <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
            </td>
        </tr>`;

        $("#testKitNameTable").append(rowString);

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

        <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            $(document).on('change', '.test-result, #result', function(e) {
                checkPostive();
            });
        <?php } ?>
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

    function getPatientDistrictDetails(val) {

        $.blockUI();
        var pName = val;
        if ($.trim(pName) != '') {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    pName: pName,
                    requestType: 'patient',
                    dName: '<?php echo $covid19Info['patient_district']; ?>',
                    testType: 'covid19'

                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#patientDistrict").html(details[1]);
                    }
                });
        } else if (pName == '') {
            $('#patientProvince').html("<?php echo $province; ?>");
            $("#patientDistrict").html("<option value=''> -- Select -- </option>");
        }
        $.unblockUI();
    }
</script>