<?php
// imported in covid-19-add-request.php based on country in global config

use App\Models\Covid19;

ob_start();

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


// $configQuery = "SELECT * from global_config";
// $configResult = $db->query($configQuery);
// $arr = array();
// $prefix = $arr['sample_code_prefix'];

// Getting the list of Provinces, Districts and Facilities

$covid19Obj = new Covid19();


$covid19Results = $covid19Obj->getCovid19Results();
$specimenTypeResult = $covid19Obj->getCovid19SampleTypes();
$covid19ReasonsForTesting = $covid19Obj->getCovid19ReasonsForTestingDRC();
$covid19Symptoms = $covid19Obj->getCovid19SymptomsDRC();
$covid19Comorbidities = $covid19Obj->getCovid19Comorbidities();


$rKey = '';
$sKey = '';
$sFormat = '';
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    //check user exist in user_facility_map table
    $chkUserFcMapQry = "SELECT user_id FROM user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
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

$facility = $general->generateSelectOptions($healthFacilities, null, '-- Select --');

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
                <form class="form-horizontal" method="post" name="addCovid19RequestForm" id="addCovid19RequestForm" autocomplete="off" action="covid-19-add-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">SITE INFORMATION</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
                                </div>
                                <table class="table" aria-hidden="true"  style="width:100%">
                                    <tr>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <td><label for="sampleCode">EPID</label></td>
                                            <td>
                                                <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
                                                <input type="hidden" id="sampleCode" name="sampleCode" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode">EPID</label><span class="mandatory">*</span></td>
                                            <td>
                                                <input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" readonly="readonly" placeholder="EPID #" title="Please fill facility name and sample collection date" style="width:100%;" onchange="checkSampleNameValidation('form_covid19','<?php echo $sampleCode; ?>',this.id,null,'The EPID #that you entered already exists. Please try another EPID #,null)" />
                                            </td>
                                        <?php } ?>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
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
                                            <!-- <select name="investigatorName" id="investigatorName" class="form-control" title="Please select a Investigator's name" style="width:100%;">
                                                <?= $general->generateSelectOptions($labTechniciansResults, $_SESSION['userId'], '-- Select --'); ?>
                                            </select> -->
                                            <input type="text" class="form-control" id="investigatorName" name="investigatorName" placeholder="Investigator’s name" title="Please enter Investigator’s name" style="width:100%;" />
                                        </td>
                                        <td><label for="investigatorPhone">Investigator’s phone</label></td>
                                        <td>
                                            <input type="text" class="form-control" id="investigatorPhone" name="investigatorPhone" placeholder="Investigator’s phone" title="Please enter Investigator’s phone" style="width:100%;" />
                                        </td>
                                        <td><label for="investigatorEmail">Investigator’s email</label></td>
                                        <td>
                                            <input type="text" class="form-control" id="investigatorEmail" name="investigatorEmail" placeholder="Investigator’s email" title="Please enter Investigator’s email" style="width:100%;" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><label for="clinicianName">Clinician name</label> </td>
                                        <td>
                                            <!-- <select name="clinicianName" id="clinicianName" class="form-control" title="Please select Clinician name" style="width:100%;">
                                                <?= $general->generateSelectOptions($labTechniciansResults, $_SESSION['userId'], '-- Select --'); ?>
                                                </select> -->
                                            <input type="text" class="form-control" id="clinicianName" name="clinicianName" placeholder="Clinician name" title="Please enter Clinician name" style="width:100%;" />
                                        </td>
                                        <td><label for="clinicianPhone">Clinician phone</label></td>
                                        <td>
                                            <input type="text" class="form-control" id="clinicianPhone" name="clinicianPhone" placeholder="Clinician phone" title="Please enter Clinician phone" style="width:100%;" />
                                        </td>
                                        <td><label for="investigatorEmail">Clinician email</label></td>
                                        <td>
                                            <input type="text" class="form-control" id="clinicianEmail" name="clinicianEmail" placeholder="Clinician email" title="Please enter Clinician email" style="width:100%;" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><label for="supportPartner">Implementing Partner </label></td>
                                        <td>
                                            <select class="form-control select2" name="implementingPartner" id="implementingPartner" title="Please choose implementing partner" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($implementingPartnerList as $implementingPartner) {
                                                ?>
                                                    <option value="<?php echo ($implementingPartner['i_partner_id']); ?>"><?php echo ($implementingPartner['i_partner_name']); ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td><label for="fundingSource">Funding Partner</label></td>
                                        <td>
                                            <select class="form-control select2" name="fundingSource" id="fundingSource" title="Please choose source of funding" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php foreach ($fundingSourceList as $fundingSource) { ?>
                                                    <option value="<?php echo ($fundingSource['funding_source_id']); ?>"><?php echo ($fundingSource['funding_source_name']); ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <!-- <tr> -->
                                            <td><label for="labId">Testing Laboratory <span class="mandatory">*</span></label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control select2 isRequired" title="Please select Testing Testing Laboratory" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
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
                                <table class="table" aria-hidden="true"  style="width:100%">

                                    <tr>
                                        <th style="width:15% !important"><label for="firstName">Patient first name <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="firstName" name="firstName" placeholder="Patient first name" title="Please enter the Patient first name" style="width:100%;" />
                                        </td>
                                        <th style="width:15% !important"><label for="lastName">Patient last name </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="lastName" name="lastName" placeholder="Patient last name" title="Please enter the Patient last name" style="width:100%;" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="patientId">Patient ID if admitted in ward <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="patientId" name="patientId" placeholder="Patient ID" title="Please enter the Patient ID" style="width:100%;" />
                                        </td>
                                        <th scope="row"><label for="patientDob">Patient date of birth<span class="mandatory">*</span> </label></th>
                                        <td>
                                            <input type="text" class="form-control isRequired" id="patientDob" name="patientDob" placeholder="Date of birth" title="Please enter Date of birth" style="width:100%;" onchange="calculateAgeInYears();" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Age (years)</th>
                                        <td><input type="number" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="patientAge" name="patientAge" placeholder="Age (in years)" title="Please enter Age (in years)" style="width:100%;" /></td>
                                        <th scope="row"><label for="patientGender">Sex <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <select class="form-control isRequired" name="patientGender" id="patientGender" title="Please select the gender">
                                                <option value=''> -- Select -- </option>
                                                <option value='male'> Male </option>
                                                <option value='female'> Female </option>
                                                <option value='other'> Other </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="patientPhoneNumber">Patient phone</label></th>
                                        <td><input type="text" class="form-control " id="patientPhoneNumber" name="patientPhoneNumber" placeholder="Patient phone" title="Please enter the patient phone" style="width:100%;" /></td>
                                        <th scope="row"><label for="patientAddress">Patient Address</label></th>
                                        <td><textarea class="form-control " id="patientAddress" name="patientAddress" placeholder="Patient Address" title="Please enter the Patient Address" style="width:100%;"></textarea></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="patientProvince">Province</label></th>
                                        <td>
                                            <select class="form-control " name="patientProvince" id="patientProvince" title="Please select the patient province" onchange="getPatientDistrictDetails(this);" style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <th scope="row"><label for="patientDistrict">District</label></th>
                                        <td>
                                            <select class="form-control select2" name="patientDistrict" id="patientDistrict" title="Please select the patient district" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                            </select>
                                        </td>

                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="patientCity">Village</label></th>
                                        <td><input class="form-control" id="patientCity" name="patientCity" placeholder="City/Village" title="Please enter the City/Village" style="width:100%;"></td>
                                        <th scope="row"><label for="patientNationality">Country of origin</label></th>
                                        <td>
                                            <select name="patientNationality" id="patientNationality" class="form-control" title="Please choose Country of origin:" style="width:100%">
                                                <?= $general->generateSelectOptions($nationalityList, null, '-- Select --'); ?>
                                            </select>
                                        </td>
                                    </tr>
                                </table>

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">
                                        PATIENT HISTORY
                                    </h3>
                                </div>
                                <table id="responseTable" class="table table-bordered" aria-hidden="true" >
                                    <th scope="row"><label for="suspectedCase">Is the suspected case</label></th>
                                    <td>
                                        <select name="suspectedCase" id="suspectedCase" class="form-control" title="Please choose suspected case">
                                            <option value="">--Select--</option>
                                            <option value="asymptomatic">Asymptomatic</option>
                                            <option value="symptomatic">Symptomatic</option>
                                            <option value="unknown">Unknown</option>
                                        </select>
                                    </td>
                                    <th scope="row"><label for="dateOfSymptomOnset">Date of symptom onset</label></th>
                                    <td>
                                        <input class="form-control date" type="text" name="dateOfSymptomOnset" id="dateOfSymptomOnset" placeholder="Date of symptom onset" title="Please choose Date of symptom onset" />
                                    </td>
                                    <tr>
                                        <th colspan="4" style="width:15% !important">Symptoms <span class="mandatory">*</span> </th>
                                    </tr>
                                    <tr>
                                        <td colspan="4">
                                            <table id="symptomsTable" class="table table-bordered table-striped" aria-hidden="true" >
                                                <?php $index = 0;
                                                foreach ($covid19Symptoms as $symptomId => $symptomName) { ?>
                                                    <tr class="row<?php echo $index; ?>">
                                                        <th style="width:50%;"><label for="symptomDetected<?php echo $symptomId; ?>"><?php echo $symptomName; ?></label></th>
                                                        <td style="width:50%;">
                                                            <input name="symptomId[]" type="hidden" value="<?php echo $symptomId; ?>">
                                                            <select name="symptomDetected[]" id="symptomDetected<?php echo $symptomId; ?>" class="form-control" title="Please select the <?php echo $symptomName; ?>" style="width:100%">
                                                                <option value="">-- Select --</option>
                                                                <option value='yes'> Yes </option>
                                                                <option value='no'> No </option>
                                                                <option value='unknown'> Unknown </option>
                                                            </select>
                                                        </td>
                                                    </tr>

                                                <?php $index++;
                                                } ?>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th colspan=2><label for="hasRecentTravelHistory">Has the patient had a recent history of travelling to an affected area?</label><span class="mandatory">*</span></th>
                                        <td style="width:25% !important;">
                                            <select name="hasRecentTravelHistory" id="hasRecentTravelHistory" class="form-control isRequired" title="Please choose if the patient has had a recent history of travelling to an affected area" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <option value='yes'> Yes </option>
                                                <option value='no'> No </option>
                                                <option value='unknown'> Unknown </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr class="historyfield">
                                        <!-- <th scope="row"><label for="overseas">Overseas</label></th>
                                        <td>
                                            <input class="historyfield form-control" type="text" name="overseas" id="overseas" placeholder="Overseas" title="Please enter the overseas" />
                                        </td> -->
                                        <th style="display: none;"><label for="countryName">If Yes, Country Name(s)</label></th>
                                        <td style="display: none;">
                                            <input class="historyfield form-control" type="text" name="countryName" id="countryName" placeholder="Country Name(s)" title="Please enter the country name(s)" />
                                        </td>
                                        <th style="display: none;"><label for="returnDate">Return Date</label></th>
                                        <td style="display: none;">
                                            <input class="historyfield form-control date" type="text" name="returnDate" id="returnDate" placeholder="Return Date" title="Please enter the return date" />
                                        </td>
                                    </tr>
                                    <tr class="historyfield">
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="closeContacts">Close contacts of the Patient</label></th>
                                        <td colspan="3">
                                            <textarea name="closeContacts" class="form-control" style="width:100%;min-height:100px;" title="Please enter the close contacts of the patient"></textarea>
                                            <span class="text-danger">*A close contact includes living in the same household, having face-to face contact (<1m) for 15 mins or more, or spending ≥2hrs in the same enclosed space, or having direct physical contact with a COVID-19 case</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="patientOccupation">Patient's Occupation</label></th>
                                        <td>
                                            <input class="form-control" type="text" name="patientOccupation" id="patientOccupation" placeholder="Patient's Occupation" title="Please enter the patient's occupation" />
                                        </td>
                                        <th scope="row"><label for="hasRecentTravelHistory"><strong>In past 14 days</strong> has the patient been in a healthcare facility (as a patient, worker, or visitor) in PNG or other country(ies) with confirmed COVID-19?</label><span class="mandatory">*</span></th>
                                        <td>
                                            <select name="hasRecentTravelHistory" id="hasRecentTravelHistory" class="form-control isRequired" title="Please choose if the patient has had a recent history of travelling to an affected area" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <option value='yes'> Yes </option>
                                                <option value='no'> No </option>
                                                <option value='unknown'> Unknown </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="contactWithConfirmedCase">Cared for a COVID-19 patient?</th>
                                        <td>
                                            <select name="contactWithConfirmedCase" id="contactWithConfirmedCase" class="form-control" title="Please choose if the person cared for a COVID-19 patient" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <option value='yes'> Yes </option>
                                                <option value='no'> No </option>
                                                <option value='unknown'> Unknown </option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">
                                        TEST REASON
                                    </h3>
                                </div>
                                <table class="table" aria-hidden="true" >
                                    <tr>
                                        <th style="width: 15%;"><label for="reasonForCovid19Test">Reason for Test Request<span class="mandatory">*</span></label></th>
                                        <td style="width: 35%;">
                                            <select name="reasonForCovid19Test" id="reasonForCovid19Test" class="form-control isRequired" title="Please choose reason for testing" style="width:100%">
                                                <?= $general->generateSelectOptions($covid19ReasonsForTesting, null, '-- Select --'); ?>
                                            </select>
                                        </td>
                                        <th style="width: 15%;"></th>
                                        <td style="width: 35%;"></td>
                                    </tr>
                                </table>
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">
                                        COMORBIDITIES
                                    </h3>
                                </div>
                                <table class="table" aria-hidden="true" >
                                    <tr>
                                        <th scope="row"><label for="ifOtherDiseases">Does the patient have another diagnosis/etiology for their illness?</label></th>
                                        <td>
                                            <select name="ifOtherDiseases" id="ifOtherDiseases" class="form-control" title="Please choose If you have another diagnosis">
                                                <option value="">-- Select --</option>
                                                <option value="yes">yes</option>
                                                <option value="no">No</option>
                                                <option value="unknown">Unknown</option>
                                            </select>
                                        </td>
                                        <th scope="row"><label for="otherDiseases">If it is yes? Then Please specify</label></th>
                                        <td>
                                            <input class="form-control" type="text" name="otherDiseases" id="otherDiseases" placeholder="Another diagnosis" title="Please enter the another diagnosis" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="medicalHistory">Comorbidities Medical History</label></th>
                                        <td>
                                            <select name="medicalHistory" id="medicalHistory" class="form-control" title="Please choose the comorbidities medical history">
                                                <option value="">-- Select --</option>
                                                <option value="yes">yes</option>
                                                <option value="no">No</option>
                                                <option value="unknown">Unknown</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr class="comorbidities-row" style="display: none;">
                                        <td colspan="4">
                                            <table id="comorbiditiesTable" class="table table-bordered" aria-hidden="true" >
                                                <?php $index = 0;
                                                foreach ($covid19Comorbidities as $comorbiditiesId => $comorbiditiesName) { ?>
                                                    <tr>
                                                        <th style="width:50%;"><?php echo $comorbiditiesName; ?></th>
                                                        <td style="width:50%;">
                                                            <input name="comorbidityId[]" type="hidden" value="<?php echo $comorbiditiesId; ?>">
                                                            <select name="comorbidityDetected[]" class="form-control" title="<?php echo $comorbiditiesName; ?>" style="width:100%">
                                                                <option value="">-- Select --</option>
                                                                <option value='yes'> yes </option>
                                                                <option value='no'> No </option>
                                                                <option value='unknown'> Unknown </option>
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
                                <table class="table" aria-hidden="true" >
                                    <tr>
                                        <th style="width: 15%;"><label for="specimenType"> Type of sample collection <span class="mandatory">*</span></label></th>
                                        <td style="width: 35%;">
                                            <select class="form-control isRequired" id="specimenType" name="specimenType" title="Please choose the sample type">
                                                <?php echo $general->generateSelectOptions($specimenTypeResult, null, '-- Select --'); ?>
                                            </select>
                                        </td>
                                        <th style="width:15% !important"><label for="sampleCollectionDate">Sample Collection Date<span class="mandatory">*</span></label></th>
                                        <td style="width:35% !important;">
                                            <input class="form-control isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select the sample collection date" onchange="sampleCodeGeneration();" />
                                        </td>
                                    </tr>
                                </table>
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">
                                        HEALTH OUTCOME
                                    </h3>
                                </div>
                                <table class="table" aria-hidden="true" >
                                    <tr>
                                        <th style="width: 15%;"><label for="healthOutcome"> Health Outcome</label></th>
                                        <td style="width: 35%;">
                                            <select class="form-control" id="healthOutcome" name="healthOutcome" title="Please select the health outcome">
                                                <option value="">-- Select --</option>
                                                <option value='alive'>Alive</option>
                                                <option value='recovered'>Recovered</option>
                                                <option value='transferred'>Transferred</option>
                                                <option value='died'> Died </option>
                                            </select>
                                        </td>
                                        <th style="width:15% !important"><label for="outcomeDate">Outcome Date</label></th>
                                        <td style="width:35% !important;">
                                            <input class="form-control date" type="text" name="outcomeDate" id="outcomeDate" placeholder="Outcome Date" title="Please select the outcome date" />
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php if ($_SESSION['instanceType'] != 'remoteuser') { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">RESPIRATORY LABORATORY DIAGNOSTIC RESULTS </h3>
                                    </div>
                                    <table class="table" aria-hidden="true"  style="width:100%">
                                        <tr>
                                            <th scope="row"><label for="sampleReceivedDate">Date of Sample Received </label></th>
                                            <td>
                                                <input type="text" class="form-control" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter the date of sample was received" <?php echo (isset($labFieldDisabled) && trim($labFieldDisabled) != '') ? $labFieldDisabled : ''; ?> style="width:100%;" />
                                            </td>

                                            <td class="lab-show"><label for="labId">Lab ID number (Filled by lab staff)</label> </td>
                                            <td class="lab-show">
                                                <select name="labId" id="labId" class="form-control" title="Please choose the laboratory name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        <tr>
                                            <th scope="row"><label for="isSampleRejected">Is sample Rejected?</label></th>
                                            <td>
                                                <select class="form-control" name="isSampleRejected" id="isSampleRejected" title="Please select Is sample rejected or not">
                                                    <option value="">--Select--</option>
                                                    <option value="yes">yes</option>
                                                    <option value="no">No</option>
                                                </select>
                                            </td>

                                        </tr>
                                        <tr class="show-rejection" style="display:none;">
                                            <th class="show-rejection" style="display:none;"><label for="sampleRejectionReason">Rejection reason<span class="mandatory">*</span></label></th>
                                            <td class="show-rejection" style="display:none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="Please select the rejection reason">
                                                    <option value=''> -- Select -- </option>
                                                    <?php echo $rejectionReason; ?>
                                                </select>
                                            </td>
                                            <th class="show-rejection" style="display:none;"><label for="rejectionDate">Date of rejected<span class="mandatory">*</span></label></th>
                                            <td class="show-rejection" style="display:none;"><input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Date of rejected" title="Please select when sample rejected" /></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4">
                                                <table class="table table-bordered table-striped" aria-hidden="true" >
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center">Test No</th>
                                                            <th class="text-center">Test Method</th>
                                                            <th class="text-center">Date of Testing</th>
                                                            <th class="text-center">Test Platform</th>
                                                            <th class="text-center">Test Result</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="testKitNameTable">
                                                        <tr>
                                                            <td class="text-center">1</td>
                                                            <td>
                                                                <select onchange="otherCovidTestName(this.value,1)" class="form-control test-name-table-input" id="testName1" name="testName[]" title="Please enter the name of the Testkit (or) Test Method used">
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
                                                                <select type="text" name="testingPlatform[]" id="testingPlatform1" class="form-control test-name-table-input" title="Please select the Testing Platform for 1">
                                                                    <?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <select class="form-control test-result test-name-table-input" name="testResult[]" id="testResult1" title="Please select the result for row 1">
                                                                    <?= $general->generateSelectOptions($covid19Results, null, '-- Select --'); ?>
                                                                </select>
                                                            </td>
                                                            <td style="vertical-align:middle;text-align: center;width:100px;">
                                                                <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
                                                                <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <th colspan="4" class="text-right"><label for="result">Final result</label></th>
                                                            <td>
                                                                <select class="form-control" name="result" id="result" title="Please select the Final result">
                                                                    <option value=''> -- Select -- </option>
                                                                    <?php foreach ($covid19Results as $c19ResultKey => $c19ResultValue) { ?>
                                                                        <option value="<?php echo $c19ResultKey; ?>"> <?php echo $c19ResultValue; ?> </option>
                                                                    <?php } ?>
                                                                </select>
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Reviewed On</th>
                                            <td><input type="text" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" /></td>
                                            <th scope="row">Reviewed By</th>
                                            <td>
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Approved On</th>
                                            <td><input type="text" name="approvedOn" id="approvedOn" class="dateTime disabled-field form-control" placeholder="Approved on" title="Please enter the Approved on" /></td>
                                            <th scope="row">Approved By</th>
                                            <td>
                                                <select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose approved by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>

                                            <th scope="row"><label for="isResultAuthorized">Referred for other testing?</label></th>
                                            <td>
                                                <select name="isResultAuthorized" id="isResultAuthorized" class="disabled-field form-control" title="Please select referred for other testing?" style="width:100%">
                                                    <option value="">-- Select --</option>
                                                    <option value='yes'> yes </option>
                                                    <option value='no'> No </option>
                                                </select>
                                            </td>
                                            <th scope="row"><label for="isResultAuthorized">Referred By</label></th>
                                            <td><input type="text" name="authorizedBy" id="authorizedBy" class="disabled-field form-control" placeholder="Referred By" title="Please enter who referred result" /></td>

                                        </tr>
                                        <tr>

                                            <th scope="row"><label for="isResultAuthorized">Referred On</label></th>
                                            <td><input type="text" name="authorizedOn" id="authorizedOn" class="disabled-field form-control date" placeholder="Referred On" title="Please enter when referred result" /></td>
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
                        <?php if ($arr['covid19_sample_code'] == 'auto' || $arr['covid19_sample_code'] == 'YY' || $arr['covid19_sample_code'] == 'MMYY') { ?>
                            <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                            <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                            <input type="hidden" name="saveNext" id="saveNext" />
                            <!-- <input type="hidden" name="pageURL" id="pageURL" value="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" /> -->
                        <?php } ?>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();$('#saveNext').val('next');return false;">Save and Next</a>
                        <input type="hidden" name="formId" id="formId" value="<?php echo $arr['vl_form']; ?>" />
                        <input type="hidden" name="covid19SampleId" id="covid19SampleId" value="" />
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
        if ($('#medicalHistory').val() == "yes") {
            if ($('input[name ="comorbidityDetected"] select option[selected=selected][value!=" "]').length > 0) {
                alert("Please Select at least one option under Medical History");
                return false;
            }
        }
        if ($('input[name ="symptom"] select option[selected=selected][value!=" "]').length > 0) {
            alert("Please Select at least one option under Symptoms");
            return false;
        }
        flag = deforayValidator.init({
            formId: 'addCovid19RequestForm'
        });
        provinceId = $("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
        if (flag) {
            $('.btn-disabled').attr('disabled', 'yes');
            $(".btn-disabled").prop("onclick", null).off("click");
            $.blockUI();
            <?php
            if ($arr['covid19_sample_code'] == 'auto' || $arr['covid19_sample_code'] == 'YY' || $arr['covid19_sample_code'] == 'MMYY') {
            ?>
                insertSampleCode('addCovid19RequestForm', 'covid19SampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 9, 'sampleCollectionDate', null, provinceId);
            <?php
            } else {
            ?>
                document.getElementById('addCovid19RequestForm').submit();
            <?php
            } ?>
        }
    }


    $(document).ready(function() {

        $('#facilityId').select2({
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
    });

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

    let testCounter = 1;

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

    function getPatientDistrictDetails(obj) {

        $.blockUI();
        var pName = obj.value;
        if ($.trim(pName) != '') {
            $.post("/includes/siteInformationDropdownOptions.php", {
                    pName: pName,
                    testType: 'covid19'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#patientDistrict").html(details[1]);
                    }
                });
        } else if (pName == '') {
            $(obj).html("<?php echo $province; ?>");
            $("#patientDistrict").html("<option value=''> -- Select -- </option>");
        }
        $.unblockUI();
    }
</script>