<?php
// imported in covid-19-add-request.php based on country in global config

use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$cpyReq = $general->getGlobalConfig('covid19_copy_request_save_and_next');

// Nationality
$nationalityQry = "SELECT * FROM `r_countries` ORDER BY `iso_name` ASC";
$nationalityResult = $db->query($nationalityQry);

foreach ($nationalityResult as $nrow) {
    $nationalityList[$nrow['id']] = ($nrow['iso_name']) . ' (' . $nrow['iso3'] . ')';
}

$pResult = $general->fetchDataFromTable('geographical_divisions', "geo_parent = 0 AND geo_status='active'");
// Getting the list of Provinces, Districts and Facilities


/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);


$covid19Results = $covid19Service->getCovid19Results();
$specimenTypeResult = $covid19Service->getCovid19SampleTypes();
$covid19ReasonsForTesting = $covid19Service->getCovid19ReasonsForTesting();
$covid19Symptoms = $covid19Service->getCovid19Symptoms();
$covid19Comorbidities = $covid19Service->getCovid19Comorbidities();


$rKey = '';
$sKey = '';
$sFormat = '';
if ($_SESSION['accessType'] == 'collection-site') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    $rKey = 'R';
} else {
    $sampleCodeKey = 'sample_code_key';
    $sampleCode = 'sample_code';
    $rKey = '';
}

$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);

$facility = $general->generateSelectOptions($healthFacilities, $_SESSION['covid19Data']['facility_id'], '-- Select --');

?>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> <?= _translate("COVID-19 VIRUS LABORATORY TEST REQUEST FORM"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
            <li class="active"><?= _translate("Add New Request"); ?></li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">

        <div class="box box-default">
            <div class="box-header with-border">

                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span><?= _translate("indicates required fields"); ?> &nbsp;</div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->
                <form class="form-horizontal" method="post" name="addCovid19RequestForm" id="addCovid19RequestForm" autocomplete="off" action="covid-19-add-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title"><?= _translate("SITE INFORMATION"); ?></h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;"><?= _translate("To be filled by requesting Clinician/Nurse"); ?></h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
                                            <td><label for="sampleCode"><?= _translate("Sample ID"); ?> </label></td>
                                            <td>
                                                <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
                                                <input type="hidden" id="sampleCode" name="sampleCode" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode"><?= _translate("Sample ID"); ?> </label><span class="mandatory">*</span></td>
                                            <td>
                                                <input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" readonly="readonly" placeholder="Sample ID" title="<?= _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>" style="width:100%;" onchange="checkSampleNameValidation('form_covid19','<?php echo $sampleCode; ?>',this.id,null,'The Sample ID that you entered already exists. Please try another Sample ID',null)" />
                                            </td>
                                        <?php } ?>
                                        <td><label for="sourceOfAlertPOE"><?= _translate("Source of Alert / POE"); ?></label></td>
                                        <td>
                                            <select class="form-control select2" name="sourceOfAlertPOE" id="sourceOfAlertPOE" title="Please choose source of Alert / POE" style="width:100%;">
                                                <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                <option value="hotline"><?= _translate("Hotline"); ?></option>
                                                <option value="community-surveillance"><?= _translate("Community Surveillance"); ?></option>
                                                <option value="poe"><?= _translate("POE"); ?></option>
                                                <option value="contact-tracing"><?= _translate("Contact Tracing"); ?></option>
                                                <option value="clinic"><?= _translate("Clinic"); ?></option>
                                                <option value="sentinel-site"><?= _translate("Sentinel Site"); ?></option>
                                                <option value="screening"><?= _translate("Screening"); ?></option>
                                                <option value="others"><?= _translate("Others"); ?></option>
                                            </select>
                                        </td>
                                        <td class="show-alert-poe" style="display: none;"><label for="sourceOfAlertPOE"><?= _translate("Source of Alert / POE Others"); ?><span class="mandatory">*</span></label></td>
                                        <td class="show-alert-poe" style="display: none;">
                                            <input type="text" class="form-control" name="alertPoeOthers" id="alertPoeOthers" placeholder="<?= _translate('Source of Alert / POE Others'); ?>" title="<?= _translate('Please choose source of Alert / POE'); ?>" style="width:100%;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><label for="province"><?= _translate("Region"); ?> </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control" name="province" id="province" title="<?= _translate('Please choose State'); ?>" onchange="getfacilityDetails(this);" style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <td><label for="district"><?= _translate("Health Facility/POE County"); ?> </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control " name="district" id="district" title="Please choose County" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                                                <option value=""> <?= _translate("-- Select --"); ?> </option>
                                            </select>
                                        </td>
                                        <td><label for="facilityId"><?= _translate("Facility"); ?> </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control  " name="facilityId" id="facilityId" title="Please choose facility" style="width:100%;" onchange="getfacilityProvinceDetails(this),fillFacilityDetails();">
                                                <option value=""> <?= _translate('-- Select --'); ?> </option>
                                                <?php echo $facility; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addFacility">Add Facility</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?= _translate("Project Name"); ?></th>
                                        <td><select class="form-control" name="fundingSource" id="fundingSource" title="Please choose implementing partner" style="width:100%;">
                                                <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                <?php
                                                foreach ($fundingSourceList as $fundingSource) {
                                                ?>
                                                    <option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>" <?php echo (isset($_SESSION['covid19Data']['funding_source']) && $_SESSION['covid19Data']['funding_source'] == $fundingSource['funding_source_id']) ? 'selected="selected"' : ''; ?>><?= $fundingSource['funding_source_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <th><?= _translate("Implementing Partner"); ?></th>
                                        <td><select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose implementing partner" style="width:100%;">
                                                <option value=""> <?= _translate("-- Select --"); ?> </option>
                                                <?php
                                                foreach ($implementingPartnerList as $implementingPartner) {
                                                ?>
                                                    <option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>" <?php echo (isset($_SESSION['covid19Data']['implementing_partner']) && $_SESSION['covid19Data']['implementing_partner'] == $implementingPartner['i_partner_id']) ? 'selected="selected"' : ''; ?>><?= $implementingPartner['i_partner_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
                                            <!-- <tr> -->
                                            <td><label for="labId"><?= _translate("Testing Laboratory"); ?> <span class="mandatory">*</span></label> </td>
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

                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                </table>


                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title"><?= _translate("CASE DETAILS/DEMOGRAPHICS"); ?></h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title"><?= _translate("Patient Information"); ?></h3>&nbsp;&nbsp;&nbsp;
                                    <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="" placeholder="<?= _translate('Enter Case ID or Patient Name'); ?>" title="<?= _translate('Enter art number or patient name'); ?>" />&nbsp;&nbsp;
                                    <a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em><?= _translate("Search"); ?></a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;<?= _translate("No Patient Found"); ?></strong></span>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr class="encryptPIIContainer">
                                        <th scope="row" style="width:15% !important"><label for="encryptPII"><?= _translate('Encrypt PII'); ?> </label></th>
                                        <td>
                                            <select name="encryptPII" id="encryptPII" class="form-control" title="<?= _translate('Encrypt Patient Identifying Information'); ?>">
                                                <option value=""><?= _translate('--Select--'); ?></option>
                                                <option value="no" selected='selected'><?= _translate('No'); ?></option>
                                                <option value="yes"><?= _translate('Yes'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15% !important"><label for="patientId"><?= _translate("Case ID"); ?> <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired patientId" id="patientId" name="patientId" placeholder="<?= _translate('Case Identification'); ?>" title="<?= _translate('Please enter Case ID'); ?>" style="width:100%;" onchange="" />
                                        </td>
                                        <th scope="row" style="width:15% !important"><label for="externalSampleCode"><?= _translate("DHIS2 Case ID"); ?> </label></th>
                                        <td style="width:35% !important"><input type="text" class="form-control" id="externalSampleCode" name="externalSampleCode" placeholder="<?= _translate('DHIS2 Case ID'); ?>" title="<?= _translate('Please enter DHIS2 Case ID'); ?>" style="width:100%;" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15% !important"><label for="firstName"><?= _translate("First Name"); ?> <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="firstName" name="firstName" placeholder="First Name" title="Please enter First name" style="width:100%;" onchange="" />
                                        </td>
                                        <th scope="row" style="width:15% !important"><label for="lastName"><?= _translate("Last name"); ?> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="lastName" name="lastName" placeholder="Last name" title="Please enter Last name" style="width:100%;" onchange="" />
                                        </td>
                                    </tr>
                                    <tr>

                                        <th scope="row"><label for="dob"><?= _translate('Date of Birth'); ?> <span class="mandatory">*</span></label></th>
                                        <td>
                                            <input type="text" class="form-control date" id="dob" name="dob" placeholder="<?= _translate('Date of Birth'); ?>" title="<?= _translate('Please enter Date of birth'); ?>" style="width:100%;" onchange="getAge();" />
                                            <input type="checkbox" name="ageUnreported" id="ageUnreported" onclick="updateAgeInfo();" /> <label for="dob"><?= _translate('Unreported'); ?> </label>
                                        </td>
                                        <th scope="row"><?= _translate("Age (years)"); ?></th>
                                        <td><input type="number" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control" id="ageInYears" name="ageInYears" placeholder="<?= _translate('Case Age (in years)'); ?>" title="<?= _translate('Case Age'); ?>" style="width:100%;" onchange="" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="patientGender"><?= _translate("Sex"); ?> <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <select class="form-control isRequired" name="patientGender" id="patientGender" title="<?= _translate('Please choose sex'); ?>">
                                                <option value=''> <?= _translate("-- Select --"); ?> </option>
                                                <option value='male'> <?= _translate("Male"); ?> </option>
                                                <option value='female'> <?= _translate("Female"); ?> </option>
                                                <option value='unreported'> <?= _translate('Unreported'); ?> </option>
                                                <!-- <option value='other'> < ?= _translate("Other"); ?> </option> -->

                                            </select>
                                        </td>
                                        <th scope="row"><?= _translate('Universal Health Coverage'); ?></th>
                                        <td><input type="text" name="healthInsuranceCode" id="healthInsuranceCode" class="form-control" placeholder="<?= _translate('Enter Universal Health Coverage'); ?>" title="<?= _translate('Enter Universal Health Coverage'); ?>" maxlength="32" /></td>

                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate("Phone number"); ?></th>
                                        <td><input type="text" class="form-control phone-number" id="patientPhoneNumber" name="patientPhoneNumber" maxlength="<?= $maxPhoneLength; ?>" placeholder="<?= _translate('Phone Number'); ?>" title="<?= _translate('Case Phone Number'); ?>" style="width:100%;" onchange="" /></td>

                                        <th scope="row"><?= _translate("Address"); ?></th>
                                        <td><textarea class="form-control " id="patientAddress" name="patientAddress" placeholder="<?= _translate('Address'); ?>" title="<?= _translate('Case Address'); ?>" style="width:100%;" onchange=""></textarea></td>

                                    </tr>
                                    <tr>

                                        <th scope="row"><?= _translate("State"); ?></th>
                                        <td>
                                            <select class="form-control " name="patientProvince" id="patientProvince" title="<?= _translate('Please Case State'); ?>" onchange="getPatientDistrictDetails(this);" style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <th scope="row"><?= _translate("County"); ?></th>
                                        <td>
                                            <select class="form-control select2" name="patientDistrict" id="patientDistrict" title="Please Case County" style="width:100%;">
                                                <option value=""> <?= _translate("-- Select --"); ?> </option>
                                            </select>
                                        </td>

                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate("Payam"); ?></th>
                                        <td><input class="form-control" id="patientZone" name="patientZone" placeholder="<?= _translate('Case Payam'); ?>" title="<?= _translate('Please enter the Case Payam'); ?>" style="width:100%;"></td>

                                        <th scope="row"><?= _translate("Boma/Village"); ?></th>
                                        <td><input class="form-control" id="patientCity" name="patientCity" placeholder="<?= _translate('Case Boma/Village'); ?>" title="<?= _translate('Please enter the Case Boma/Village'); ?>" style="width:100%;"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate("Nationality"); ?></th>
                                        <td>
                                            <select name="patientNationality" id="patientNationality" class="form-control" title="<?= _translate('Please choose nationality'); ?>" style="width:100%">
                                                <?= $general->generateSelectOptions($nationalityList, null, '-- Select --'); ?>
                                            </select>
                                        </td>

                                        <th scope="row"><?= _translate("Passport Number"); ?></th>
                                        <td><input class="form-control" id="patientPassportNumber" name="patientPassportNumber" placeholder="<?= _translate('Passport Number'); ?>" title="<?= _translate('Please enter Passport Number'); ?>" style="width:100%;"></td>

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
                                                <li><?= _translate("All samples must be sent in accordance with Category B transport requirements."); ?></li>
                                            </ul>

                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate("Type of Test Request"); ?></th>
                                        <td>
                                            <select name="testTypeRequested" id="testTypeRequested" class="form-control" title="<?= _translate('Please choose type of test request'); ?>" style="width:100%">
                                                <option value=""><?= _translate("-- Select --"); ?></option>
                                                <option value="Real Time RT-PCR" <?php echo (isset($_SESSION['covid19Data']['type_of_test_requested']) && $_SESSION['covid19Data']['type_of_test_requested'] == 'Real Time RT-PCR') ? 'selected="selected"' : ''; ?>><?= _translate("Real Time RT-PCR"); ?></option>
                                                <option value="RDT-Antibody" <?php echo (isset($_SESSION['covid19Data']['type_of_test_requested']) && $_SESSION['covid19Data']['type_of_test_requested'] == 'RDT-Antibody') ? 'selected="selected"' : ''; ?>><?= _translate("RDT-Antibody"); ?></option>
                                                <option value="RDT-Antigen" <?php echo (isset($_SESSION['covid19Data']['type_of_test_requested']) && $_SESSION['covid19Data']['type_of_test_requested'] == 'RDT-Antigen') ? 'selected="selected"' : ''; ?>><?= _translate("RDT-Antigen"); ?></option>
                                                <option value="GeneXpert" <?php echo (isset($_SESSION['covid19Data']['type_of_test_requested']) && $_SESSION['covid19Data']['type_of_test_requested'] == 'GeneXpert') ? 'selected="selected"' : ''; ?>><?= _translate("GeneXpert"); ?></option>
                                                <option value="ELISA" <?php echo (isset($_SESSION['covid19Data']['type_of_test_requested']) && $_SESSION['covid19Data']['type_of_test_requested'] == 'ELISA') ? 'selected="selected"' : ''; ?>><?= _translate("ELISA"); ?></option>
                                            </select>
                                        </td>
                                        <th scope="row"><?= _translate("Reason for Test Request"); ?> <span class="mandatory">*</span></th>
                                        <td>
                                            <select name="reasonForCovid19Test" id="reasonForCovid19Test" class="form-control isRequired" title="<?= _translate('Please choose reason for testing'); ?>" style="width:100%">
                                                <?= $general->generateSelectOptions($covid19ReasonsForTesting, $_SESSION['covid19Data']['reason_for_covid19_test'], '-- Select --'); ?>
                                            </select>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row" style="width:15% !important"><?= _translate("Sample Collection Date"); ?> <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="<?= _translate('Sample Collection Date'); ?>" value="<?php if (isset($_SESSION['covid19Data']['sample_collection_date'])) echo DateUtility::humanReadableDateFormat($_SESSION['covid19Data']['sample_collection_date'], true); ?>" onchange="generateSampleCode(); checkCollectionDate(this.value);" />
                                            <span class="expiredCollectionDate" style="color:red; display:none;"></span>
                                        </td>
                                        <th scope="row" style="width:15% !important"><?= _translate("Sample Dispatched On"); ?> <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control dateTime isRequired" type="text" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="<?= _translate('Sample Dispatched On'); ?>" value="<?php if (isset($_SESSION['covid19Data']['sample_collection_date'])) echo DateUtility::humanReadableDateFormat($_SESSION['covid19Data']['sample_collection_date'], true); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate("Specimen Type"); ?> <span class="mandatory">*</span></th>
                                        <td>
                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="<?= _translate('Please choose specimen type'); ?>" style="width:100%">
                                                <?php echo $general->generateSelectOptions($specimenTypeResult, $_SESSION['covid19Data']['specimen_type'], '-- Select --'); ?>
                                            </select>
                                        </td>
                                        <th scope="row"><label for="testNumber"><?= _translate("Number of Times Tested"); ?></label></th>
                                        <td>
                                            <select class="form-control" name="testNumber" id="testNumber" title="<?= _translate('Number of Times Tested'); ?>" style="width:100%;">
                                                <option value=""><?= _translate("-- Select --"); ?></option>
                                                <?php foreach (range(1, 5) as $element) { ?>
                                                    <option value="<?= $element; ?>" <?php echo (isset($_SESSION['covid19Data']['test_number']) && $_SESSION['covid19Data']['test_number'] == $element) ? 'selected="selected"' : ''; ?>><?= $element; ?></option>;
                                                <?php
                                                } ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php if ($general->isLISInstance()) { ?>
                                        <tr>
                                            <th scope="row"><label for=""><?= _translate("Sample Received Date"); ?> </label></th>
                                            <td>
                                                <input type="text" class="form-control" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate("Please enter date"); ?>" title="<?= _translate('Please enter sample receipt date'); ?>" value="<?php if (isset($_SESSION['covid19Data']['sample_received_at_lab_datetime'])) echo DateUtility::humanReadableDateFormat($_SESSION['covid19Data']['sample_received_at_lab_datetime'], true); ?>" style="width:100%;" />
                                            </td>
                                        </tr>
                                    <?php } ?>
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
                                            <td class="lab-show"><label for="labId"><?= _translate("Testing Laboratory"); ?> </label> </td>
                                            <td class="lab-show">
                                                <select name="labId" id="labId" class="select2 form-control" title="<?= _translate('Please select Testing Testing Laboratory'); ?>" style="width:100%;" onchange="getTestingPoints();">
                                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <td><label for="specimenQuality"><?= _translate("Specimen Quality"); ?></label></td>
                                            <td>
                                                <select class="form-control" id="specimenQuality" name="specimenQuality" title="Please enter the specimen quality">
                                                    <option value=""><?= _translate("-- Select --"); ?></option>
                                                    <option value="good"><?= _translate("Good"); ?></option>
                                                    <option value="poor"><?= _translate("Poor"); ?></option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>

                                            <th scope="row"><label for="labTechnician"><?= _translate("Lab Technician"); ?> </label></th>
                                            <td>
                                                <select name="labTechnician" id="labTechnician" class="form-control" title="Please select a Lab Technician" style="width:100%;">
                                                    <option value=""><?= _translate("-- Select --"); ?></option>
                                                    <?= $general->generateSelectOptions($labTechniciansResults, $_SESSION['userId'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row" class="testingPointField" style="display:none;"><label for=""><?= _translate("Testing Point"); ?> </label></th>
                                            <td class="testingPointField" style="display:none;">
                                                <select name="testingPoint" id="testingPoint" class="form-control" title="<?= _translate('Please select a Testing Point'); ?>" style="width:100%;">
                                                </select>
                                            </td>

                                        </tr>
                                        <tr>
                                            <th scope="row"><?= _translate("Is Sample Rejected?"); ?></th>
                                            <td>
                                                <select class="form-control" name="isSampleRejected" id="isSampleRejected">
                                                    <option value=''> <?= _translate("-- Select --"); ?> </option>
                                                    <option value="yes"> <?= _translate("Yes"); ?> </option>
                                                    <option value="no"> <?= _translate("No"); ?> </option>
                                                </select>
                                            </td>
                                            <th scope="row" class="show-rejection" style="display:none;"><?= _translate("Reason for Rejection"); ?></th>
                                            <td class="show-rejection" style="display:none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="<?= _translate('Please select the Reason for Rejection'); ?>">
                                                    <option value=''> <?= _translate("-- Select --"); ?> </option>
                                                    <?php echo $rejectionReason; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="show-rejection" style="display:none;">
                                            <th scope="row"><?= _translate("Rejection Date"); ?><span class="mandatory">*</span></th>
                                            <td><input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="<?= _translate('Select Rejection Date'); ?>" title="<?= _translate('Please select the Rejection Date'); ?>" /></td>

                                        </tr>

                                        <tr>
                                            <td colspan="4">
                                                <table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true">
                                                    <thead>
                                                        <tr>
                                                            <th scope="row" class="text-center"><?= _translate("Test Number"); ?></th>
                                                            <th scope="row" class="text-center"><?= _translate("Test Method"); ?></th>
                                                            <th scope="row" class="text-center"><?= _translate("Date of Testing"); ?></th>
                                                            <th scope="row" class="text-center"><?= _translate("Test Platform/Instrument"); ?></th>
                                                            <th scope="row" class="text-center kitlabels" style="display: none;"><?= _translate("Kit Lot No"); ?></th>
                                                            <th scope="row" class="text-center kitlabels" style="display: none;"><?= _translate("Expiry Date"); ?></th>
                                                            <th scope="row" class="text-center"><?= _translate("Test Result"); ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="testKitNameTable">
                                                        <tr>
                                                            <td class="text-center">1</td>
                                                            <td>
                                                                <select onchange="testMethodChanged(this.value,1)" class="form-control test-name-table-input" id="testName1" name="testName[]" title="<?= _translate('Please enter the name of the Testkit (or) Test Method used'); ?>">
                                                                    <option value=""><?= _translate("-- Select --"); ?></option>
                                                                    <option value="Real Time RT-PCR"><?= _translate("Real Time RT-PCR"); ?></option>
                                                                    <option value="RDT-Antibody"><?= _translate("RDT-Antibody"); ?></option>
                                                                    <option value="RDT-Antigen"><?= _translate("RDT-Antigen"); ?></option>
                                                                    <option value="GeneXpert"><?= _translate("GeneXpert"); ?></option>
                                                                    <option value="ELISA"><?= _translate("ELISA"); ?></option>
                                                                    <option value="other"><?= _translate("Others"); ?></option>
                                                                </select>
                                                                <input type="text" name="testNameOther[]" id="testNameOther1" class="form-control testNameOther1" title="<?= _translate('Please enter the name of the Testkit (or) Test Method used'); ?>" placeholder="<?= _translate('Please enter the name of the Testkit (or) Test Method used'); ?>" style="display: none;margin-top: 10px;" />
                                                            </td>
                                                            <td><input type="text" name="testDate[]" id="testDate1" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row 1" /></td>
                                                            <td>
                                                                <select name="testingPlatform[]" id="testingPlatform<?= ($indexKey + 1); ?>" class="form-control  result-optional test-name-table-input" title="Please select the Testing Platform for <?= ($indexKey + 1); ?>">
                                                                    <?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>
                                                                </select>
                                                            </td>
                                                            <td class="kitlabels" style="display: none;"><input type="text" name="lotNo[]" id="lotNo1" class="form-control kit-fields1" placeholder="Kit lot no" title="Please enter the kit lot no. for row 1" style="display:none;" /></td>
                                                            <td class="kitlabels" style="display: none;"><input type="text" name="expDate[]" id="expDate1" class="form-control expDate kit-fields1" placeholder="Expiry date" title="Please enter the expiry date for row 1" style="display:none;" /></td>
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
                                                            <th scope="row" colspan="4" class="text-right final-result-row">Final Result</th>
                                                            <td>
                                                                <select class="form-control" name="result" id="result">
                                                                    <option value=''> <?= _translate("-- Select --"); ?> </option>
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
                                            <th scope="row">Reviewed By</th>
                                            <td>
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row">Reviewed on</th>
                                            <td><input type="text" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Tested By</th>
                                            <td>
                                                <select name="testedBy" id="testedBy" class="select2 form-control" title="Please choose approved by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row"></th>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Is Result Authorized ?</th>
                                            <td>
                                                <select name="isResultAuthorized" id="isResultAuthorized" class="disabled-field form-control" title="Is Result authorized ?" style="width:100%">
                                                    <option value="">-- Select --</option>
                                                    <option value='yes'> Yes </option>
                                                    <option value='no'> No </option>
                                                </select>
                                            </td>
                                            <th scope="row">Authorized By</th>
                                            <td><select name="authorizedBy" id="authorizedBy" class="disabled-field form-control" title="Please choose authorized by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, null, '-- Select --'); ?>
                                                </select></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Authorized on</th>
                                            <td><input type="text" name="authorizedOn" id="authorizedOn" class="disabled-field form-control date" placeholder="Authorized on" title="Please select the Authorized On" /></td>
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
                            <input type="hidden" name="testData[]" id="testData" />

                            <!-- <input type="hidden" name="pageURL" id="pageURL" value="<?php echo htmlspecialchars((string) $_SERVER['PHP_SELF']); ?>" /> -->
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

<!-- The Modal -->
<div class="modal" id="addFacility">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Add Facility</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <!-- Modal body -->
            <div class="modal-body">
                <form class="form-horizontal" method='post' name='addFacilityForm' id='addFacilityForm' autocomplete="off" enctype="multipart/form-data">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="facilityName" class="col-lg-4 control-label"><?= _translate('Facility Name'); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="facilityName" name="facilityName" placeholder="<?= _translate('Facility Name'); ?>" title="<?= _translate('Please enter facility name'); ?>" onblur="checkNameValidation('facility_details','facility_name',this,null,'The facility name that you entered already exists.Enter another name',null)" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="facilityCode" class="col-lg-4 control-label"><?= _translate('Facility Code'); ?></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="facilityCode" name="facilityCode" placeholder="<?= _translate('Facility Code'); ?>" title="<?= _translate('Please enter facility code'); ?>" onblur="checkNameValidation('facility_details','facility_code',this,null,'The code that you entered already exists.Try another code',null)" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="otherId" class="col-lg-4 control-label"><?= _translate('Other ID'); ?> </label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="otherId" name="otherId" placeholder="<?= _translate('Other ID'); ?>" />
                                        <input type="hidden" class="form-control isRequired" id="facilityType" name="facilityType" value="1" title="<?= _translate('Please select facility type'); ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="col-lg-4 control-label"><?= _translate('Email(s)'); ?> </label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="email" name="email" placeholder="e.g. email1@gmail.com, email2@gmail.com" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testingPoints" class="col-lg-4 control-label"><?= _translate('Testing Point(s)'); ?><br> <small><?= _translate('(comma separated)'); ?></small> </label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="testingPoints" name="testingPoints" placeholder="<?= _translate('eg. VCT, PMTCT'); ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contactPerson" class="col-lg-4 control-label"><?= _translate('Contact Person'); ?></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="contactPerson" name="contactPerson" placeholder="<?= _translate('Contact Person'); ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="state" class="col-lg-4 control-label"><?= _translate('Province/State'); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select name="state" id="state" class="form-control isRequired" title="<?= _translate('Please choose province/state'); ?>">
                                            <option value=""> <?= _translate("-- Select --"); ?> </option>
                                            <?php
                                            foreach ($pResult as $province) {
                                            ?>
                                                <option value="<?php echo $province['geo_name']; ?>"><?php echo $province['geo_name']; ?></option>
                                            <?php
                                            }
                                            ?>
                                            <option value="other"><?= _translate('Other'); ?></option>
                                        </select>
                                        <input type="text" class="form-control" name="provinceNew" id="provinceNew" placeholder="<?= _translate('Enter Province/State'); ?>" title="<?= _translate('Please enter province/state'); ?>" style="margin-top:4px;display:none;" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phoneNo" class="col-lg-4 control-label"><?= _translate('Phone Number'); ?></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control phone-number" id="phoneNo" name="phoneNo" placeholder="<?= _translate('Phone Number'); ?>" onblur="checkNameValidation('facility_details','facility_mobile_numbers',this,null,'The mobile no that you entered already exists.Enter another mobile no.',null)" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="hubName" class="col-lg-4 control-label"><?= _translate('Linked Hub Name (If Applicable)'); ?></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="hubName" name="hubName" placeholder="<?= _translate('Hub Name'); ?>" title="<?= _translate('Please enter hub name'); ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="district" class="col-lg-4 control-label"><?= _translate('District/County'); ?> <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="district" name="district" placeholder="<?= _translate('District/County'); ?>" title="<?= _translate('Please enter district/county'); ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="country" class="col-lg-4 control-label"><?= _translate('Country'); ?></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="country" name="country" placeholder="Country" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="address" class="col-lg-4 control-label"><?= _translate('Address'); ?></label>
                                    <div class="col-lg-7">
                                        <textarea class="form-control" name="address" id="address" placeholder="Address"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="latitude" class="col-lg-4 control-label"><?= _translate('Latitude'); ?></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control forceNumeric" id="latitude" name="latitude" placeholder="<?= _translate('Latitude'); ?>" title="<?= _translate('Please enter latitude'); ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="longitude" class="col-lg-4 control-label"><?= _translate('Longitude'); ?></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control forceNumeric" id="longitude" name="longitude" placeholder="<?= _translate('Longitude'); ?>" title="<?= _translate('Please enter longitude'); ?>" />
                                        <input type="hidden" name="reqForm" id="reqForm" value="1" />
                                        <input type="hidden" name="headerText" id="headerText" />
                                        <input type="hidden" name="testType[]" id="testType" value="covid19" />
                                        <input type="hidden" name="selectedUser[]" id="selectedUser" />
                                        <input type="hidden" name="fromAPI" id="fromAPI" value="yes" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- /.box-body -->
                        <div class="box-footer">

                        </div>
                        <!-- /.box-footer -->
                </form>
            </div>

            <!-- Modal footer -->
            <div class="modal-footer">
                <a class="btn btn-primary" href="javascript:void(0);" onclick="addFacility();"><?= _translate('Submit'); ?></a>
                <button type="button" class="btn btn-danger" data-dismiss="modal"><?= _translate('Close'); ?></button>
            </div>

        </div>
    </div>
</div>


<script type="text/javascript">
    changeProvince = true;
    changeFacility = true;
    provinceName = true;
    facilityName = true;
    machineName = true;
    tableRowId = 2;

    // $(function () {
    // $('#addFacilityForm').bind('submit', function (event) {
    // using this page stop being refreshing
    // event.preventDefault();
    function addFacility() {
        flag = deforayValidator.init({
            formId: 'addFacilityForm'
        });
        if (flag) {
            $.ajax({
                type: 'POST',
                url: '/facilities/addFacilityHelper.php',
                data: $('#addFacilityForm').serialize(),
                success: function() {
                    alert('Facility details added successfully');
                    $('#addFacility').modal('hide');
                    getfacilityDistrictwise('');
                }
            });
        }
    }
    // });
    //   });

    function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
        let removeDots = obj.value.replace(/\./g, "");
        removeDots = removeDots.replace(/\,/g, "");
        //str=obj.value;
        removeDots = removeDots.replace(/\s{2,}/g, ' ');

        $.post("/includes/checkDuplicate.php", {
                tableName: tableName,
                fieldName: fieldName,
                value: removeDots.trim(),
                fnct: fnct,
                format: "html"
            },
            function(data) {
                if (data === '1') {
                    alert(alrt);
                    document.getElementById(obj.id).value = "";
                }
            });
    }

    function getTestingPoints() {
        var labId = $("#labId").val();
        var selectedTestingPoint = null;
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
            generateSampleCode();
        } else if (pName == '') {
            provinceName = true;
            facilityName = true;
            $("#province").html("<?php echo $province; ?>");
            $("#facilityId").html("<?php echo ((string) $facility); ?>");
            $("#facilityId").select2("val", "");
            $("#district").html("<option value=''> <?= _translate("-- Select --"); ?> </option>");
        }
        $.unblockUI();
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
            $("#patientDistrict").html("<option value=''> <?= _translate("-- Select --"); ?> </option>");
        }
        $.unblockUI();
    }

    function setPatientDetails(pDetails) {
        patientArray = JSON.parse(pDetails);
        ////console.log(patientArray);
        $("#patientProvince").val(patientArray['geo_name'] + '##' + patientArray['geo_code']).trigger('change');
        $("#firstName").val(patientArray['firstname']);
        $("#lastName").val(patientArray['lastname']);
        $("#patientPhoneNumber").val(patientArray['patient_phone_number']);
        $("#patientGender").val(patientArray['gender']);
        $("#ageInYears").val(patientArray['age']);
        $("#patientDob").val(patientArray['dob']);
        $("#patientId").val(patientArray['patient_id']);
        $("#patientPassportNumber").val(patientArray['patient_passport_number']);
        $("#patientAddress").text(patientArray['patient_address']);
        $("#patientNationality").val(patientArray['patient_nationality']).trigger('change');
        $("#patientDistrict").val(patientArray['patient_district']).trigger('change');
        $("#patientCity").val(patientArray['patient_city']);
        $("#patientZone").val(patientArray['patient_zone']);
        $("#externalSampleCode").val(patientArray['external_sample_code']);

        setTimeout(function() {
            $("#patientDistrict").val(patientArray['patient_district']).trigger('change');
        }, 3000);
    }

    function generateSampleCode() {
        var pName = $("#province").val();
        var provinceCode = $("#province").find(":selected").attr("data-code");

        var sDate = $("#sampleCollectionDate").val();
        if (pName != '' && sDate != '') {
            $.post("/covid-19/requests/generateSampleCode.php", {
                    sampleCollectionDate: sDate,
                    provinceCode: provinceCode
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
            $("#facilityId").html("<option value=''> <?= _translate("-- Select --"); ?> </option>");
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
        flag = deforayValidator.init({
            formId: 'addCovid19RequestForm'
        });
        if (flag) {
            $('.btn-disabled').attr('disabled', 'yes');
            $(".btn-disabled").prop("onclick", null).off("click");
            $.blockUI();
            <?php
            if ($arr['covid19_sample_code'] == 'auto' || $arr['covid19_sample_code'] == 'YY' || $arr['covid19_sample_code'] == 'MMYY') {
            ?>
                insertSampleCode('addCovid19RequestForm', 'covid19SampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 3, 'sampleCollectionDate');
            <?php
            } else {
            ?>
                document.getElementById('addCovid19RequestForm').submit();
            <?php
            } ?>
        }
    }

    function updateAgeInfo() {
        var isChecked = $("#ageUnreported").is(":checked");
        if (isChecked == true) {
            $("#dob").val("");
            $("#ageInYears").val("");
            $('#dob').prop('readonly', true);
            $('#ageInYears').prop('readonly', true);
            $('#dob').removeClass('isRequired');
        } else {
            $('#dob').prop('readonly', false);
            $('#ageInYears').prop('readonly', false);
            $('#dob').addClass('isRequired');
        }
    }

    $(document).ready(function() {
        $("#labId,#facilityId,#sampleCollectionDate").on('change', function() {
            if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleDispatchedDate").val() == "") {
                $('#sampleDispatchedDate').datetimepicker("setDate", new Date($('#sampleCollectionDate').datetimepicker('getDate')));
            }
            if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleReceivedDate").val() == "") {
                // $('#sampleReceivedDate').datetimepicker("setDate", new Date($('#sampleCollectionDate').datetimepicker('getDate')));
            }

            if ($("#labId").val() != "") {
                $.post("/includes/get-sample-type.php", {
                        facilityId: $('#labId').val(),
                        testType: 'covid19'
                    },
                    function(data) {
                        if (data != "") {
                            $("#specimenType").html(data);
                        }
                    });
            }
        });



        $(".select2").select2();
        $(".select2").select2({
            tags: true
        });
        $('#labId').select2({
            width: '100%',
            placeholder: "<?= _translate('Select Testing Lab'); ?>"
        });
        $('#reviewedBy').select2({
            width: '100%',
            placeholder: "<?= _translate('Select Reviewed By'); ?>"
        });
        $('#province').select2({
            placeholder: "<?= _translate('Select Province'); ?>"
        });
        $('#district').select2({
            placeholder: "<?= _translate('Select District'); ?>"
        });
        $('#facilityId').select2({
            placeholder: "<?= _translate('Select Clinic/Health Center'); ?>"
        });
        $('#labTechnician').select2({
            placeholder: "<?= _translate('Select Lab Technician'); ?>"
        });

        $('#patientNationality').select2({
            placeholder: "<?= _translate('Select Nationality'); ?>"
        });

        $('#patientProvince').select2({
            placeholder: "<?= _translate('Select Case State'); ?>"
        });
        $('#authorizedBy').select2({
            width: '100%',
            placeholder: "<?= _translate('Select Authorized By'); ?>"
        });

        $('#isResultAuthorized').change(function(e) {
            checkIsResultAuthorized();
        });

        $('#sourceOfAlertPOE').change(function(e) {
            if (this.value == 'others') {
                $('.show-alert-poe').show();
                $('#alertPoeOthers').addClass('isRequired');
            } else {
                $('.show-alert-poe').hide();
                $('#alertPoeOthers').removeClass('isRequired');
            }
        });
        <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            $(document).on('change', '.test-result, #result', function(e) {
                checkPostive();
            });
        <?php } ?>

        // BARCODESTUFF END
        <?php if (isset($cpyReq) && !empty($cpyReq) && $cpyReq == 'yes') {
            //  unset($_SESSION['covid19Data']);
        ?>
            getfacilityProvinceDetails($('#facilityId'));
        <?php } ?>

    });

    function fillFacilityDetails() {
        $.blockUI();
        //check facility name

        $.unblockUI();
        $("#facilityCode").val($('#facilityId').find(':selected').data('code'));
    }

    let testCounter = 1;

    function addTestRow() {
        testCounter++;
        let rowString = `<tr>
                    <td class="text-center">${testCounter}</td>
                    <td>
                    <select onchange="testMethodChanged(this.value,${testCounter})" class="form-control test-name-table-input" id="testName${testCounter}" name="testName[]" title="Please enter the name of the Testkit (or) Test Method used">
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
    <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>

        function checkPostive() {
            // alert("show");
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
            $('#authorizedBy').val(null).trigger('change');
            $('#authorizedOn').val('');
            $('#authorizedBy,#authorizedOn').prop('disabled', true);
            $('#authorizedBy,#authorizedOn').addClass('disabled');
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
        } else if ($('#isResultAuthorized').val() == 'yes') {
            $('#authorizedBy,#authorizedOn').prop('disabled', false);
            $('#authorizedBy,#authorizedOn').removeClass('disabled');
            $('#authorizedBy,#authorizedOn').addClass('isRequired');
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
        var show = true;

        if (~str.indexOf("RDT")) {
            let option = `<option value=''>-- Select --</option>
            <option value='Abbott Panbio™ COVID-19 Ag Test'>Abbott Panbio™ COVID-19 Ag Test</option>
            <option value='STANDARD™ Q COVID-19 Ag Test'>STANDARD™ Q COVID-19 Ag Test</option>
            <option value='LumiraDx ™ SARS-CoV-2 Ag Test'>LumiraDx ™ SARS-CoV-2 Ag Test</option>
            <option value='Sure Status® COVID-19 Antigen Card Test'>Sure Status® COVID-19 Antigen Card Test</option>`;
            $("#testingPlatform" + id).html(option);
            $('.kitlabels,.kit-fields' + id).show();
            $('.final-result-row').attr('colspan', 6);
            $('.kit-fields' + id).prop('disabled', false);
        } else {
            if ($('.kitlabels').is(':visible') == false) {
                $('.final-result-row').attr('colspan', 4);
            }
            $('.kit-label').text('Test Platform');
            $('#expDate' + id + ', #lotNo' + id).prop('disabled', true);
            $("#testingPlatform" + id).html("<?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>");
        }
        if (val == 'other') {
            $('.testNameOther' + id).show();
        } else {
            $('.testNameOther' + id).hide();
        }
    }
    $('#addCovid19RequestForm').keypress((e) => {
        // Enter key corresponds to number 13
        if (e.which === 13) {
            e.preventDefault();
            validateNow(); // Trigger the validateNow function
        }
    });
    // Handle Enter key specifically for select2 elements
    $(document).on('keydown', '.select2-container--open', function(e) {
        if (e.which === 13) {
            e.preventDefault(); // Prevent the default form submission
            validateNow(); // Trigger the validateNow function
        }
    });
</script>