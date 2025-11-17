<?php

// imported in covid-19-update-result.php based on country in global config

use App\Registries\ContainerRegistry;
use App\Services\Covid19Service;
use App\Utilities\DateUtility;





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




/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);
$covid19Results = $covid19Service->getCovid19Results();

$specimenTypeResult = $covid19Service->getCovid19SampleTypes();
$covid19ReasonsForTesting = $covid19Service->getCovid19ReasonsForTesting();

$covid19Symptoms = $covid19Service->getCovid19Symptoms();
$covid19SelectedSymptoms = $covid19Service->getCovid19SymptomsByFormId($covid19Info['covid19_id']);


$covid19Comorbidities = $covid19Service->getCovid19Comorbidities();
$covid19SelectedComorbidities = $covid19Service->getCovid19ComorbiditiesByFormId($covid19Info['covid19_id']);


// Getting the list of Provinces, Districts and Facilities

$rKey = '';

if ($_SESSION['accessType'] == 'collection-site') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    if (!empty($covid19Info['remote_sample']) && $covid19Info['remote_sample'] == 'yes') {
        $sampleCode = 'remote_sample_code';
    } else {
        $sampleCode = 'sample_code';
    }
    $rKey = 'R';
} else {
    $sampleCodeKey = 'sample_code_key';
    $sampleCode = 'sample_code';
    $rKey = '';
}
$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);
$facility = $general->generateSelectOptions($healthFacilities, $covid19Info['facility_id'], '-- Select --');

?>


<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> <?= _translate("COVID-19 VIRUS LABORATORY TEST REQUEST FORM"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
            <li class="active"><?= _translate("Edit Request"); ?></li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">

        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate("indicates required fields"); ?> &nbsp;</div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->
                <form class="form-horizontal" method="post" name="updateCovid19ConfirmatoryRequestForm" id="updateCovid19ConfirmatoryRequestForm" autocomplete="off" action="covid-19-update-result-helper.php">
                    <div class="box-body">
                        <div class="box box-default disabledForm">
                            <div class="box-body">
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title"><?= _translate("SITE INFORMATIO"); ?>N</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;"><?= _translate("To be filled by requesting Clinician/Nurse"); ?></h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
                                            <td><label for="sampleCode"><?= _translate("Sample ID"); ?> </label> </td>
                                            <td>
                                                <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?php echo $covid19Info[$sampleCode]; ?></span>
                                                <input type="hidden" class="<?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" value="<?php echo $covid19Info[$sampleCode]; ?>" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode"><?= _translate("Sample ID"); ?> </label><span class="mandatory">*</span> </td>
                                            <td>
                                                <input type="text" readonly value="<?php echo $covid19Info[$sampleCode]; ?>" class="form-control" id="sampleCode" name="sampleCode" placeholder="<?= _translate("Sample ID"); ?>" title="<?= _translate("Please enter Sample ID"); ?>" style="width:100%;" onchange="" />
                                            </td>
                                        <?php } ?>
                                        <td><label for="sourceOfAlertPOE"><?= _translate("Source of Alert / POE"); ?></label></td>
                                        <td>
                                            <select class="form-control select2" name="sourceOfAlertPOE" id="sourceOfAlertPOE" title="<?= _translate('Please choose source of Alert / POE'); ?>" style="width:100%;">
                                                <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                <option value="hotline" <?php echo (isset($covid19Info['source_of_alert']) && $covid19Info['source_of_alert'] == 'hotline') ? "selected='selected'" : ""; ?>><?= _translate("Hotline"); ?></option>
                                                <option value="community-surveillance" <?php echo (isset($covid19Info['source_of_alert']) && $covid19Info['source_of_alert'] == 'community-surveillance') ? "selected='selected'" : ""; ?>><?= _translate("Community Surveillance"); ?></option>
                                                <option value="poe" <?php echo (isset($covid19Info['source_of_alert']) && $covid19Info['source_of_alert'] == 'poe') ? "selected='selected'" : ""; ?>><?= _translate("POE"); ?></option>
                                                <option value="contact-tracing" <?php echo (isset($covid19Info['source_of_alert']) && $covid19Info['source_of_alert'] == 'contact-tracing') ? "selected='selected'" : ""; ?>><?= _translate("Contact Tracing"); ?></option>
                                                <option value="clinic" <?php echo (isset($covid19Info['source_of_alert']) && $covid19Info['source_of_alert'] == 'clinic') ? "selected='selected'" : ""; ?>><?= _translate("Clinic"); ?></option>
                                                <option value="sentinel-site" <?php echo (isset($covid19Info['source_of_alert']) && $covid19Info['source_of_alert'] == 'sentinel-site') ? "selected='selected'" : ""; ?>><?= _translate("Sentinel Site"); ?></option>
                                                <option value="screening" <?php echo (isset($covid19Info['source_of_alert']) && $covid19Info['source_of_alert'] == 'screening') ? "selected='selected'" : ""; ?>><?= _translate("Screening"); ?></option>
                                                <option value="others" <?php echo (isset($covid19Info['source_of_alert']) && $covid19Info['source_of_alert'] == 'others') ? "selected='selected'" : ""; ?>><?= _translate("Others"); ?></option>
                                            </select>
                                        </td>
                                        <td class="show-alert-poe" style="<?php echo (isset($covid19Info['source_of_alert']) && $covid19Info['source_of_alert'] == 'others') ? "" : "display:none;"; ?>"><label for="sourceOfAlertPOE"><?= _translate("Source of Alert / POE Others"); ?></label></td>
                                        <td class="show-alert-poe" style="<?php echo (isset($covid19Info['source_of_alert']) && $covid19Info['source_of_alert'] == 'others') ? "" : "display:none;"; ?>">
                                            <input type="text" value="<?php echo $covid19Info['source_of_alert_other']; ?>" class="form-control" name="alertPoeOthers" id="alertPoeOthers" placeholder="<?= _translate("Source of Alert / POE Others"); ?>" title="<?= _translate("Please choose source of Alert / POE"); ?>" style="width:100%;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><label for="province"><?= _translate("Region"); ?> </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control select2" name="province" id="province" title="<?= _translate("Please choose State"); ?>" style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <td><label for="district"><?= _translate("Health Facility/POE County"); ?> </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control select2" name="district" id="district" title="<?= _translate("Please choose County"); ?>" style="width:100%;">
                                                <option value=""> <?= _translate("-- Select --"); ?> </option>
                                            </select>
                                        </td>
                                        <td><label for="facilityId"><?= _translate("Facility"); ?> </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control select2" name="facilityId" id="facilityId" title="<?= _translate("Please choose facility"); ?>" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
                                                <?php echo $facility; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?= _translate("Project Name"); ?></th>
                                        <td><select class="form-control" name="fundingSource" id="fundingSource" title="<?= _translate('Please choose implementing partner'); ?>" style="width:100%;">
                                                <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                <?php
                                                foreach ($fundingSourceList as $fundingSource) {
                                                ?>
                                                    <option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $covid19Info['funding_source']) ? 'selected="selected"' : ''; ?>><?= $fundingSource['funding_source_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <th><?= _translate("Implementing Partner"); ?></th>
                                        <td><select class="form-control" name="implementingPartner" id="implementingPartner" title="<?= _translate('Please choose implementing partner'); ?>" style="width:100%;">
                                                <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                <?php
                                                foreach ($implementingPartnerList as $implementingPartner) {
                                                ?>
                                                    <option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>" <?php echo ($implementingPartner['i_partner_id'] == $covid19Info['implementing_partner']) ? 'selected="selected"' : ''; ?>><?= $implementingPartner['i_partner_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                    </tr>
                                </table>

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title"><?= _translate("CASE DETAILS/DEMOGRAPHICS"); ?></h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">

                                    <tr>
                                        <th scope="row" style="width:15% !important"><label for="firstName"><?= _translate("First Name"); ?> <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control" id="firstName" name="firstName" placeholder="<?= _translate("First Name"); ?>" title="<?= _translate("Please enter Case first name"); ?>" style="width:100%;" value="<?php echo $covid19Info['patient_name']; ?>" />
                                        </td>
                                        <th scope="row" style="width:15% !important"><label for="lastName"><?= _translate("Last name"); ?> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="lastName" name="lastName" placeholder="<?= _translate("Last name"); ?>" title="<?= _translate("Please enter Case last name"); ?>" style="width:100%;" value="<?php echo $covid19Info['patient_surname']; ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15% !important"><label for="patientId"><?= _translate("Case ID"); ?> <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control" id="patientId" name="patientId" placeholder="<?= _translate("Identification"); ?>" title="<?= _translate("Please enter ID"); ?>" style="width:100%;" value="<?php echo $covid19Info['patient_id']; ?>" />
                                        </td>
                                        <th scope="row"><label for="dob"><?= _translate("Date of Birth"); ?> </label></th>
                                        <td>
                                            <input type="text" class="form-control" id="dob" name="dob" placeholder="<?= _translate("Date of Birth"); ?>" title="<?= _translate("Please enter Date of birth"); ?>" style="width:100%;" onchange="calculateAgeInYears();" value="<?php echo DateUtility::humanReadableDateFormat($covid19Info['patient_dob']); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate("Case Age (years)"); ?></th>
                                        <td><input type="number" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="patientAge" name="patientAge" placeholder="<?= _translate("Age (in years)"); ?>" title="<?= _translate("Age"); ?>" style="width:100%;" value="<?php echo $covid19Info['patient_age']; ?>" /></td>
                                        <th scope="row"><label for="patientGender"><?= _translate("Sex"); ?> <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <select class="form-control" name="patientGender" id="patientGender">
                                                <option value=''> <?= _translate("-- Select --"); ?> </option>
                                                <option value='male' <?php echo ($covid19Info['patient_gender'] == 'male') ? "selected='selected'" : ""; ?>> <?= _translate("Male"); ?> </option>
                                                <option value='female' <?php echo ($covid19Info['patient_gender'] == 'female') ? "selected='selected'" : ""; ?>> <?= _translate("Female"); ?> </option>
                                                <option value='unreported' <?php echo ($covid19Info['patient_gender'] == 'unreported') ? "selected='selected'" : ""; ?>> <?= _translate('Unreported'); ?> </option>
                                                <option value='other' <?php echo ($covid19Info['patient_gender'] == 'other') ? "selected='selected'" : ""; ?>> <?= _translate("Other"); ?> </option>

                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate('Universal Health Coverage'); ?></th>
                                        <td><input type="text" name="healthInsuranceCode" id="healthInsuranceCode" class="form-control" value="<?= $covid19Info['health_insurance_code']; ?>" placeholder="<?= _translate('Enter Universal Health Coverage'); ?>" title="<?= _translate('Enter Universal Health Coverage'); ?>" maxlength="32" /></td>

                                        <th scope="row"><?= _translate("Phone number"); ?></th>
                                        <td><input type="text" class="form-control phone-number" id="patientPhoneNumber" name="patientPhoneNumber" placeholder="<?= _translate("Phone Number"); ?>" title="<?= _translate("Phone Number"); ?>" style="width:100%;" value="<?php echo $covid19Info['patient_phone_number']; ?>" /></td>

                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate("Case address"); ?></th>
                                        <td><textarea class="form-control " id="patientAddress" name="patientAddress" placeholder="<?= _translate("Address"); ?>" title="<?= _translate("Address"); ?>" style="width:100%;" onchange=""><?php echo $covid19Info['patient_address']; ?></textarea></td>

                                        <th scope="row"><?= _translate("Case State"); ?></th>
                                        <td>
                                            <select class="form-control select2" name="patientProvince" id="patientProvince" title="<?= _translate("Please Case State"); ?>" onchange="getPatientDistrictDetails(this.value);" style="width:100%;">
                                                <?= $general->generateSelectOptions($provinceInfo, $covid19Info['patient_province'], '-- Select --'); ?>
                                            </select>
                                        </td>

                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate("County"); ?></th>
                                        <td>
                                            <select class="form-control select2" name="patientDistrict" id="patientDistrict" title="Please Case County" style="width:100%;">
                                                <option value=""><?= _translate("-- Select --"); ?></option>
                                            </select>
                                        </td>

                                        <th scope="row"><?= _translate("City/Village"); ?></th>
                                        <td><input class="form-control" value="<?php echo $covid19Info['patient_city']; ?>" id="patientCity" name="patientCity" placeholder="<?= _translate("Case City/Village"); ?>" title="<?= _translate("Please enter the Case City/Village"); ?>" style="width:100%;"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate("Nationality"); ?></th>
                                        <td>
                                            <select name="patientNationality" id="patientNationality" class="form-control" title="<?= _translate('Please choose nationality'); ?>" style="width:100%">
                                                <?= $general->generateSelectOptions($nationalityList, $covid19Info['patient_nationality'], '-- Select --'); ?>
                                            </select>
                                        </td>
                                    </tr>
                                </table>

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title"><?= _translate("SPECIMEN INFORMATION"); ?></h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true">

                                    <tr>
                                        <td colspan=4>
                                            <ul>
                                                <li><?= _translate("All specimens collected should be regarded as potentially infectious and you <u>MUST CONTACT</u> the reference laboratory before sending samples."); ?></li>
                                                <li><?= _translate("All samples must be sent in accordance with category B transport requirements."); ?></li>
                                            </ul>

                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate("Type of Test Request"); ?></th>
                                        <td>
                                            <select name="testTypeRequested" id="testTypeRequested" class="form-control" title="<?= _translate('Please choose type of test request'); ?>" style="width:100%">
                                                <option value=""><?= _translate("-- Select --"); ?></option>
                                                <option value="Real Time RT-PCR" <?php echo (isset($covid19Info['type_of_test_requested']) && $covid19Info['type_of_test_requested'] == 'Real Time RT-PCR') ? "selected='selected'" : ""; ?>><?= _translate("Real Time RT-PCR"); ?></option>
                                                <option value="RDT-Antibody" <?php echo (isset($covid19Info['type_of_test_requested']) && $covid19Info['type_of_test_requested'] == 'RDT-Antibody') ? "selected='selected'" : ""; ?>><?= _translate("RDT-Antibody"); ?></option>
                                                <option value="RDT-Antigen" <?php echo (isset($covid19Info['type_of_test_requested']) && $covid19Info['type_of_test_requested'] == 'RDT-Antigen') ? "selected='selected'" : ""; ?>><?= _translate("RDT-Antigen"); ?></option>
                                                <option value="ELISA" <?php echo (isset($covid19Info['type_of_test_requested']) && $covid19Info['type_of_test_requested'] == 'ELISA') ? "selected='selected'" : ""; ?>><?= _translate("ELISA"); ?></option>
                                            </select>
                                        </td>
                                        <th scope="row"><?= _translate("Reason for Test Request"); ?> <span class="mandatory">*</span></th>
                                        <td>
                                            <select name="reasonForCovid19Test" id="reasonForCovid19Test" class="form-control isRequired" title="<?= _translate('Please choose specimen type'); ?>" style="width:100%">
                                                <option value=""><?= _translate("-- Select --"); ?></option>
                                                <?php echo $general->generateSelectOptions($covid19ReasonsForTesting, $covid19Info['reason_for_covid19_test']); ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15% !important"><?= _translate("Sample Collection Date"); ?> <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="<?= _translate("Sample Collection Date"); ?>" value="<?php echo ($covid19Info['sample_collection_date']); ?>" />
                                        </td>
                                        <th scope="row" style="width:15% !important"><?= _translate("Sample Dispatched On"); ?> <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control dateTime isRequired" type="text" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="<?= _translate("Sample Dispatched On"); ?>" value="<?php echo date('d-M-Y H:i:s', strtotime((string) $covid19Info['sample_dispatched_datetime'])); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate("Specimen Type"); ?> <span class="mandatory">*</span></th>
                                        <td>
                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="<?= _translate("Please choose specimen type"); ?>" style="width:100%">
                                                <option value=""><?= _translate("-- Select --"); ?></option>
                                                <?php echo $general->generateSelectOptions($specimenTypeResult, $covid19Info['specimen_type']); ?>
                                            </select>
                                        </td>
                                        <th scope="row"><label for="testNumber"><?= _translate("Number of Times Tested"); ?></label></th>
                                        <td>
                                            <select class="form-control" name="testNumber" id="testNumber" title="<?= _translate("Number of Times Tested"); ?>" style="width:100%;">
                                                <option value=""><?= _translate("-- Select --"); ?></option>
                                                <?php foreach (range(1, 5) as $element) {
                                                    $selected = (isset($covid19Info['test_number']) && $covid19Info['test_number'] == $element) ? "selected='selected'" : "";
                                                    echo '<option value="' . $element . '" ' . $selected . '>' . $element . '</option>';
                                                } ?>
                                            </select>
                                        </td>
                                        <th scope="row"></th>
                                        <td></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php if (_isAllowed('/covid-19/results/covid-19-update-result.php') && $_SESSION['accessType'] != 'collection-site') { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title"><?= _translate("Reserved for Laboratory Use"); ?> </h3>
                                    </div>
                                    <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                        <tr>
                                            <th scope="row"><label for=""><?= _translate("Sample Received Date"); ?> <span class="mandatory">*</span></label></th>
                                            <td>
                                                <input type="text" class="form-control isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate("Please enter date"); ?>" title="<?= _translate("Please enter sample receipt date"); ?>" value="<?php echo DateUtility::humanReadableDateFormat($covid19Info['sample_received_at_lab_datetime']) ?>" onchange="" style="width:100%;" />
                                            </td>
                                            <td class="lab-show"><label for="labId"><?= _translate("Testing Laboratory"); ?> <span class="mandatory">*</span></label> </td>
                                            <td class="lab-show">
                                                <select name="labId" id="labId" class="select2 form-control isRequired" title="<?= _translate("Please select Testing Testing Laboratory"); ?>" style="width:100%;" onchange="getTestingPoints();">
                                                    <?= $general->generateSelectOptions($testingLabs, $covid19Info['lab_id'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        <tr>
                                        <tr>
                                            <td><label for="specimenQuality"><?= _translate("Specimen Quality"); ?><span class="mandatory">*</span></label></td>
                                            <td>
                                                <select class="form-control isRequired" id="specimenQuality" name="specimenQuality" title="<?= _translate("Please enter the specimen quality"); ?>">
                                                    <option value=""><?= _translate("-- Select --"); ?></option>
                                                    <option value="good" <?php echo (isset($covid19Info['sample_condition']) && $covid19Info['sample_condition'] == 'good') ? "selected='selected'" : ""; ?>><?= _translate("Good"); ?></option>
                                                    <option value="poor" <?php echo (isset($covid19Info['sample_condition']) && $covid19Info['sample_condition'] == 'poor') ? "selected='selected'" : ""; ?>><?= _translate("Poor"); ?></option>
                                                </select>
                                            </td>
                                            <th scope="row"><label for="labTechnician"><?= _translate("Lab Technician"); ?> </label></th>
                                            <td>
                                                <select name="labTechnician" id="labTechnician" class="form-control isRequired" title="<?= _translate("Please select a Lab Technician"); ?>" style="width:100%;">
                                                    <option value=""><?= _translate("-- Select --"); ?></option>
                                                    <?= $general->generateSelectOptions($labTechniciansResults, $covid19Info['lab_technician'], '<?= _translate("-- Select --"); ?>'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" class="testingPointField" style="display:none;"><label for=""><?= _translate("Testing Point"); ?> </label></th>
                                            <td class="testingPointField" style="display:none;">
                                                <select name="testingPoint" id="testingPoint" class="form-control" title="<?= _translate("Please select a Testing Point"); ?>" style="width:100%;">
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?= _translate("Is Sample Rejected?"); ?> <span class="mandatory">*</span></th>
                                            <td>
                                                <select class="form-control result-focus isRequired" name="isSampleRejected" id="isSampleRejected">
                                                    <option value=''> <?= _translate("-- Select --"); ?> </option>
                                                    <option value="yes" <?php echo ($covid19Info['is_sample_rejected'] == 'yes') ? "selected='selected'" : ""; ?>> <?= _translate("Yes"); ?> </option>
                                                    <option value="no" <?php echo ($covid19Info['is_sample_rejected'] == 'no') ? "selected='selected'" : ""; ?>> <?= _translate("No"); ?> </option>
                                                </select>
                                            </td>

                                            <th scope="row" class="show-rejection" style="display:none;"><?= _translate("Reason for Rejection"); ?> <span class="mandatory">*</span></th>
                                            <td class="show-rejection" style="display:none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason">
                                                    <option value=""><?= _translate("-- Select --"); ?></option>
                                                    <?php foreach ($rejectionTypeResult as $type) { ?>
                                                        <optgroup label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
                                                            <?php
                                                            foreach ($rejectionResult as $reject) {
                                                                if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                                    <option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($covid19Info['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?= $reject['rejection_reason_name']; ?></option>
                                                            <?php }
                                                            } ?>
                                                        </optgroup>
                                                    <?php }
                                                    if ($covid19Info['reason_for_sample_rejection'] == 9999) {
                                                        echo '<option value="9999" selected="selected">Unspecified</option>';
                                                    } ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="show-rejection" style="display:none;">
                                            <th scope="row"><?= _translate("Rejection Date"); ?><span class="mandatory">*</span></th>
                                            <td><input value="<?php echo DateUtility::humanReadableDateFormat($covid19Info['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="<?= _translate("Select Rejection Date"); ?>" /></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4">
                                                <table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true" id="testNameTable">
                                                    <thead>
                                                        <tr>
                                                            <th scope="row" class="text-center"><?= _translate("Test No."); ?></th>
                                                            <th scope="row" class="text-center"><?= _translate("Test Method"); ?></th>
                                                            <th scope="row" class="text-center"><?= _translate("Date of Testing"); ?></th>
                                                            <th scope="row" class="text-center"><?= _translate("Test Platform/Instrument"); ?></th>
                                                            <th scope="row" class="text-center kitlabels" style="display: none;"><?= _translate("Kit Lot No"); ?></th>
                                                            <th scope="row" class="text-center kitlabels" style="display: none;"><?= _translate("Expiry Date"); ?></th>
                                                            <th scope="row" class="text-center"><?= _translate("Test Result"); ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="testKitNameTable">
                                                        <?php $span = 4;
                                                        if (!empty($covid19TestInfo)) {
                                                            $kitShow = false;
                                                            foreach ($covid19TestInfo as $indexKey => $rows) { ?>
                                                                <tr>
                                                                    <td class="text-center"><?= ($indexKey + 1); ?><input type="hidden" name="testId[]" value="<?php echo base64_encode((string) $rows['test_id']); ?>"></td>
                                                                    <td>
                                                                        <?php
                                                                        $value = '';
                                                                        if (!in_array($rows['test_name'], ['Real Time RT-PCR', 'RDT-Antibody', 'RDT-Antigen', 'ELISA', 'other'])) {
                                                                            $value = 'value="' . $rows['test_name'] . '"';
                                                                            $show =  "block";
                                                                        } else {
                                                                            $show =  "none";
                                                                        } ?>
                                                                        <select onchange="testMethodChanged(this.value,<?= ($indexKey + 1); ?>)" class="form-control test-name-table-input isRequired" id="testName<?= ($indexKey + 1); ?>" name="testName[]" title="<?= _translate("Please enter the name of the Testkit (or) Test Method used"); ?>">
                                                                            <option value=""><?= _translate("-- Select --"); ?></option>
                                                                            <option value="Real Time RT-PCR" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'Real Time RT-PCR') ? "selected='selected'" : ""; ?>><?= _translate("Real Time RT-PCR"); ?></option>
                                                                            <option value="RDT-Antibody" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'RDT-Antibody') ? "selected='selected'" : ""; ?>><?= _translate("RDT-Antibody"); ?></option>
                                                                            <option value="RDT-Antigen" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'RDT-Antigen') ? "selected='selected'" : ""; ?>><?= _translate("RDT-Antigen"); ?></option>
                                                                            <option value="ELISA" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'ELISA') ? "selected='selected'" : ""; ?>><?= _translate("ELISA"); ?></option>
                                                                            <option value="other" <?php echo (isset($show) && $show === 'block') ? "selected='selected'" : ""; ?>><?= _translate("Others"); ?></option>
                                                                        </select>
                                                                        <input <?php echo $value; ?> type="text" name="testNameOther[]" id="testNameOther<?= ($indexKey + 1); ?>" class="form-control testNameOther<?= ($indexKey + 1); ?>" title="<?= _translate("Please enter the name of the Testkit (or) Test Method used"); ?>" placeholder="<?= _translate("Enter the name of the Testkit <?= ($indexKey + 1); ?>"); ?>" style="display: <?php echo $show; ?>;margin-top: 10px;" />
                                                                    </td>
                                                                    <td><input type="text" value="<?php echo DateUtility::humanReadableDateFormat($rows['sample_tested_datetime']); ?>" name="testDate[]" id="testDate<?= ($indexKey + 1); ?>" class="form-control test-name-table-input dateTime isRequired" placeholder="<?= _translate("Tested on"); ?>" title="<?= _translate("Please enter the tested on for row <?= ($indexKey + 1); ?>"); ?>" /></td>
                                                                    <td>
                                                                        <select name="testingPlatform[]" id="testingPlatform<?= ($indexKey + 1); ?>" class="form-control result-optional test-name-table-input isRequired" title="<?= _translate("Please select the Testing Platform for <?= ($indexKey + 1); ?>"); ?>">
                                                                            <?php $display = "display:none;";
                                                                            if ((str_contains((string)$rows['test_name'], 'RDT'))) {
                                                                                $display = "";
                                                                                $span = 6;
                                                                                $kitShow = true; ?>
                                                                                <option value=""><?= _translate("-- Select --"); ?></option>
                                                                                <option value="Abbott Panbio™ COVID-19 Ag Test" <?php echo (isset($rows['testing_platform']) && $rows['testing_platform'] == 'Abbott Panbio™ COVID-19 Ag Test') ? "selected='selected'" : ""; ?>><?= _translate("Abbott Panbio™ COVID-19 Ag Test"); ?></option>
                                                                                <option value="STANDARD™ Q COVID-19 Ag Test" <?php echo (isset($rows['testing_platform']) && $rows['testing_platform'] == 'STANDARD™ Q COVID-19 Ag Test') ? "selected='selected'" : ""; ?>><?= _translate("STANDARD™ Q COVID-19 Ag Test"); ?></option>
                                                                                <option value="LumiraDx ™ SARS-CoV-2 Ag Test" <?php echo (isset($rows['testing_platform']) && $rows['testing_platform'] == 'LumiraDx ™ SARS-CoV-2 Ag Test') ? "selected='selected'" : ""; ?>><?= _translate("LumiraDx ™ SARS-CoV-2 Ag Test"); ?></option>
                                                                                <option value="Sure Status® COVID-19 Antigen Card Test" <?php echo (isset($rows['testing_platform']) && $rows['testing_platform'] == 'Sure Status® COVID-19 Antigen Card Test') ? "selected='selected'" : ""; ?>><?= _translate("Sure Status® COVID-19 Antigen Card Test"); ?></option>
                                                                                <option value="other" <?php echo (isset($show) && $show === 'block') ? "selected='selected'" : ""; ?>><?= _translate("Others"); ?></option>
                                                                            <?php } else { ?>
                                                                            <?= $general->generateSelectOptions($testPlatformList, $rows['testing_platform'], '-- Select --');
                                                                            } ?>
                                                                        </select>
                                                                    </td>
                                                                    <td class="kitlabels" style="display: none;">
                                                                        <input type="text" value="<?php echo $rows['kit_lot_no']; ?>" name="lotNo[]" id="lotNo<?= ($indexKey + 1); ?>" class="form-control kit-fields<?= ($indexKey + 1); ?>" placeholder="<?= _translate("Kit lot no"); ?>" title="<?= _translate("Please enter the kit lot no. for row 1"); ?>" style="<?php echo $display; ?>" />
                                                                    </td>
                                                                    <td class="kitlabels" style="display: none;">
                                                                        <input type="text" value="<?php echo DateUtility::humanReadableDateFormat($rows['kit_expiry_date']); ?>" name="expDate[]" id="expDate<?= ($indexKey + 1); ?>" class="form-control expDate kit-fields<?= ($indexKey + 1); ?>" placeholder="<?= _translate("Expiry date"); ?>" title="<?= _translate("Please enter the expiry date for row 1"); ?>" style="<?php echo $display; ?>" />
                                                                    </td>
                                                                    <td><select class="form-control test-result test-name-table-input result-focus isRequired" name="testResult[]" id="testResult<?= ($indexKey + 1); ?>" title="<?= _translate("Please select the result for row <?= ($indexKey + 1); ?>"); ?>">
                                                                            <option value=''> <?= _translate("-- Select --"); ?> </option>
                                                                            <?php foreach ($covid19Results as $c19ResultKey => $c19ResultValue) { ?>
                                                                                <option value="<?php echo $c19ResultKey; ?>" <?php echo ($rows['result'] == $c19ResultKey) ? "selected='selected'" : ""; ?>> <?php echo $c19ResultValue; ?> </option>
                                                                            <?php } ?>
                                                                        </select>
                                                                    </td>
                                                                    <td style="vertical-align:middle;text-align: center;width:100px;">
                                                                        <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
                                                                        <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);deleteRow('<?php echo base64_encode((string) $rows['test_id']); ?>');"><em class="fa-solid fa-minus"></em></a>
                                                                    </td>
                                                                </tr>
                                                        <?php }
                                                        } ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <th scope="row" colspan="<?php echo $span; ?>" class="text-right final-result-row">Final Result</th>
                                                            <td>
                                                                <select class="form-control result-focus isRequired" name="result" id="result">
                                                                    <option value=''> <?= _translate("-- Select --"); ?> </option>
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
                                            <th scope="row"><?= _translate("Reviewed By"); ?></th>
                                            <td>
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control isRequired" title="<?= _translate("Please choose reviewed by"); ?>" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, $covid19Info['result_reviewed_by'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row"><?= _translate("Reviewed On"); ?></th>
                                            <td><input type="text" value="<?php echo $covid19Info['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control isRequired" placeholder="<?= _translate("Reviewed on"); ?>" title="<?= _translate("Please enter the Reviewed on"); ?>" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?= _translate("Tested By"); ?></th>
                                            <td>
                                                <select name="testedBy" id="testedBy" class="select2 form-control isRequired" title="<?= _translate("Please choose approved by"); ?>" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, $covid19Info['tested_by'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row" class="change-reason" style="display: none;"><?= _translate("Reason for Changing"); ?> <span class="mandatory">*</span></th>
                                            <td class="change-reason" style="display: none;"><textarea name="reasonForChanging" id="reasonForChanging" class="form-control" placeholder="<?= _translate("Enter the reason for changing"); ?>" title="<?= _translate("Please enter the reason for changing"); ?>"></textarea></td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?= _translate("Is Result Authorized ?"); ?></th>
                                            <td>
                                                <select name="isResultAuthorized" id="isResultAuthorized" class="disabled-field form-control isRequired" title="<?= _translate('Is Result authorized ?'); ?>" style="width:100%">
                                                    <option value=""><?= _translate("-- Select --"); ?></option>
                                                    <option value='yes' <?php echo ($covid19Info['is_result_authorised'] == 'yes') ? "selected='selected'" : ""; ?>> <?= _translate("Yes"); ?> </option>
                                                    <option value='no' <?php echo ($covid19Info['is_result_authorised'] == 'no') ? "selected='selected'" : ""; ?>> <?= _translate("No"); ?> </option>
                                                </select>
                                            </td>
                                            <th scope="row"><?= _translate("Authorized By"); ?></th>
                                            <td>
                                                <select name="authorizedBy" id="authorizedBy" class="select2 form-control isRequired" title="<?= _translate("Please choose authorized by"); ?>" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, $covid19Info['authorized_by'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?= _translate("Authorized on"); ?></th>
                                            <td><input type="text" value="<?php echo DateUtility::humanReadableDateFormat($covid19Info['authorized_on']); ?>" name="authorizedOn" id="authorizedOn" class="disabled-field form-control date isRequired" placeholder="<?= _translate("Authorized on"); ?>" title="<?= _translate("Please enter the Authorized on"); ?>" /></td>
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
                        <a class="btn btn-primary submit-btn" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                        <input type="hidden" id="sampleCode" name="sampleCode" value="<?php echo $covid19Info[$sampleCode]; ?>" />
                        <input type="hidden" name="patientId" id="patientId" value="<?php echo $covid19Info['patient_id']; ?>" />
                        <input type="hidden" name="revised" id="revised" value="no" />
                        <input type="hidden" name="formId" id="formId" value="7" />
                        <input type="hidden" name="deletedRow" id="deletedRow" value="" />
                        <input type="hidden" name="covid19SampleId" id="covid19SampleId" value="<?php echo $covid19Info['covid19_id']; ?>" />
                        <input type="hidden" name="sampleCodeCol" id="sampleCodeCol" value="<?php echo $covid19Info['sample_code']; ?>" />
                        <input type="hidden" name="remoteSampleCodeCol" id="remoteSampleCodeCol" value="<?php echo $covid19Info['remote_sample_code']; ?>" />
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
    let testCounter = <?php echo (empty($covid19TestInfo)) ? (0) : count($covid19TestInfo); ?>;
    deletedRow = [];

    function getTestingPoints() {
        var labId = $("#labId").val();
        var selectedTestingPoint = '<?php echo $covid19Info['covid19_id']; ?>';
        if (labId) {
            $.post("/includes/getTestingPoints.php", {
                    labId: labId,
                    selectedTestingPoint: selectedTestingPoint
                },
                function(data) {
                    if (data != "") {
                        $(".testingPointField").show();
                        $("#testingPoint").html(data);
                    } else {
                        $(".testingPointField").hide();
                        $("#testingPoint").html('');
                    }
                });
        }
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
        if ($('#isResultAuthorized').val() == "no") {
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
        } else {
            $('#isResultAuthorized').val('yes');
            $('#authorizedBy,#authorizedOn').addClass('isRequired');
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
            $("#patientDistrict").html("<?php echo $province; ?>");
            $("#patientDistrict").html("<option value=''> <?= _translate("-- Select --"); ?> </option>");
        }
        $.unblockUI();
    }

    $(document).ready(function() {
        $('.select2').select2();
        $('#labId').select2({
            width: '100%',
            placeholder: "Select Testing Lab"
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
        $('#patientNationality').select2({
            placeholder: "Select Nationality"
        });

        <?php if (!empty($covid19TestInfo)) { ?>
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
        <?php } ?>

        $('#facilityId').select2({
            placeholder: "Select Clinic/Health Center"
        });

        $('#labTechnician').select2({
            placeholder: "Select Lab Technician"
        });
        getfacilityProvinceDetails($("#facilityId").val());
        getTestingPoints();



        $('.disabledForm input, .disabledForm select , .disabledForm textarea').attr('disabled', true);
        $('#sampleCode').attr('disabled', false);
        // $('.test-name-table-input').prop('disabled',true);
        if (testCounter == 0) {
            addTestRow();
        }

        $('.expDate').datepicker({
            changeMonth: true,
            changeYear: true,
            onSelect: function() {
                $(this).change();
            },
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            // minDate: "Today",
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        $('#isResultAuthorized').change(function(e) {
            checkIsResultAuthorized();
        });
        $('#sourceOfAlertPOE').change(function(e) {
            if (this.value == 'others') {
                $('.show-alert-poe').show();
            } else {
                $('.show-alert-poe').hide();
            }
        });
        $('#result, .test-result').change(function(e) {
            if (this.value == 'positive') {
                $('.other-diseases').hide();
                $('#otherDiseases').removeClass('isRequired');
            } else {
                $('.other-diseases').show();
                $('#otherDiseases').addClass('isRequired');
            }
        });
        checkIsResultAuthorized();
        <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            $(document).change('.test-result, #result', function(e) {
                checkPostive();
            });
            checkPostive();
        <?php } else { ?>
            $('#result').addClass('isRequired');
            $('#isResultAuthorized').val('yes');
        <?php } ?>
        if ($('#result').val() != "") {
            $('#isResultAuthorized').val('yes');
        }
        getPatientDistrictDetails('<?php echo (isset($covid19Info['patient_province']) && $covid19Info['patient_province'] != ""); ?>');
        <?php if ($kitShow) { ?>
            $('.kitlabels').show();
        <?php } ?>
    });


    function addTestRow() {
        testCounter++;
        let rowString = `<tr>
                    <td class="text-center">${testCounter}</td>
                    <td>
                    <select onchange="testMethodChanged(this.value,${testCounter})" class="form-control test-name-table-input" id="testName${testCounter}" name="testName[]" title="<?= _translate('Please enter the name of the Testkit (or) Test Method used'); ?>">
                    <option value="">--Select--</option>
                    <option value="Real Time RT-PCR">Real Time RT-PCR</option>
                    <option value="RDT-Antibody">RDT-Antibody</option>
                    <option value="RDT-Antigen">RDT-Antigen</option>
                    <option value="GeneXpert">GeneXpert</option>
                    <option value="ELISA">ELISA</option>
                    <option value="other">Others</option>
                </select>
                <input type="text" name="testNameOther[]" id="testNameOther${testCounter}" class="form-control testNameOther${testCounter}" title="<?= _translate('Please enter the name of the Testkit (or) Test Method used'); ?>" placeholder="<?= _translate('Please enter the name of the Testkit (or) Test Method used'); ?>" style="display: none;margin-top: 10px;" />
            </td>
            <td><input type="text" name="testDate[]" id="testDate${testCounter}" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row ${testCounter}" /></td>
            <td><select name="testingPlatform[]" id="testingPlatform${testCounter}" class="form-control test-name-table-input" title="Please select the Testing Platform for ${testCounter}"><?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?></select></td>
            <td class="kitlabels" style="display: none;"><input type="text" name="lotNo[]" id="lotNo${testCounter}" class="form-control kit-fields${testCounter}" placeholder="<?= _translate('Kit lot no'); ?>" title="<?= _translate('Please enter the kit lot no. for row'); ?> ${testCounter}" style="display: none;"/></td>
            <td class="kitlabels" style="display: none;"><input type="text" name="expDate[]" id="expDate${testCounter}" class="form-control expDate kit-fields${testCounter}" placeholder="<?= _translate('Expiry date'); ?>" title="<?= _translate('Please enter the expiry date for row'); ?> ${testCounter}" style="display: none;"/></td>
            <td>
                <select class="form-control test-result test-name-table-input" name="testResult[]" id="testResult${testCounter}" title="Please select the result"><?= $general->generateSelectOptions($covid19Results, null, '-- Select --'); ?></select>
            </td>
            <td style="vertical-align:middle;text-align: center;width:100px;">
                <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow(this);"><em class="fa-solid fa-plus"></em></a>&nbsp;
                <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
            </td>
        </tr>`;

        $("#testKitNameTable").append(rowString);





        $('.expDate').datepicker({
            changeMonth: true,
            changeYear: true,
            onSelect: function() {
                $(this).change();
            },
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            // minDate: "Today",
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });

        if ($('.kitlabels').is(':visible') == true) {
            $('.kitlabels').show();
        }

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

    function deleteRow(id) {
        deletedRow.push(id);
        $('#deletedRow').val(deletedRow);
    }

    function checkIsResultAuthorized() {
        if ($('#isResultAuthorized').val() == 'yes') {
            $('#authorizedBy,#authorizedOn').prop('disabled', false);
            $('#authorizedBy,#authorizedOn').removeClass('disabled');
            $('#authorizedBy,#authorizedOn').addClass('isRequired');
        } else if ($('#isResultAuthorized').val() == 'no') {
            $('#authorizedBy').val(null).trigger('change');
            $('#authorizedOn').val('');
            $('#authorizedBy,#authorizedOn').prop('disabled', true);
            $('#authorizedBy,#authorizedOn').addClass('disabled');
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
        }
        if ($('#isResultAuthorized').val() == '') {
            $('#authorizedBy').val(null).trigger('change');
            $('#authorizedOn').val('');
            $('#authorizedBy,#authorizedOn').prop('disabled', false);
            $('#authorizedBy,#authorizedOn').removeClass('disabled');
        }
    }

    function testMethodChanged(val, id) {
        var str = $("#testName" + id + " option:selected").text();
        var selected = $("#testingPlatform" + id + " option:selected").val();
        var show = true;

        if (~str.indexOf("RDT")) {
            let option = `<option value=''>-- Select --</option>
            <option value='Abbott Panbio™ COVID-19 Ag Test'>Abbott Panbio™ COVID-19 Ag Test</option>
            <option value='STANDARD™ Q COVID-19 Ag Test'>STANDARD™ Q COVID-19 Ag Test</option>
            <option value='LumiraDx ™ SARS-CoV-2 Ag Test'>LumiraDx ™ SARS-CoV-2 Ag Test</option>
            <option value='Sure Status® COVID-19 Antigen Card Test'>Sure Status® COVID-19 Antigen Card Test</option>`;
            $("#testingPlatform" + id).html(option);
            $('.kitlabels,.kit-fields' + id).show();
            $("#testingPlatform" + id).val(selected);
            $('.final-result-row').attr('colspan', 6);
            $('#expDate' + id + ', #lotNo' + id).show();
        } else {
            if ($('.kitlabels').is(':visible') == false) {
                $('.final-result-row').attr('colspan', 4);
            }
            $('.kit-label').text('Test Platform');
            $('#expDate' + id + ', #lotNo' + id).val('');
            $('#expDate' + id + ', #lotNo' + id).hide();
            $("#testingPlatform" + id).html("<?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>");
        }
        if (val == 'other') {
            $('.testNameOther' + id).show();
        } else {
            $('.testNameOther' + id).hide();
        }
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
    $('#updateCovid19ConfirmatoryRequestForm').keypress((e) => {
        // Enter key corresponds to number 13
        if (e.which === 13) {
            e.preventDefault();
            //console.log('form submitted');
            validateNow() // Trigger the validateNow function
        }
    });
    // Handle Enter key specifically for select2 elements
    $(document).on('keydown', '.select2-container--open', function(e) {
        if (e.which === 13) {
            e.preventDefault(); // Prevent the default form submission
            validateNow() // Trigger the validateNow function
        }
    });
</script>