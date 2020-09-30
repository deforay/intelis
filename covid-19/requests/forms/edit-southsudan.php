<?php

// imported in covid-19-edit-request.php based on country in global config

ob_start();


//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);

/* To get testing platform names */
$testPlatformQry = "SELECT * FROM import_config WHERE status='active' ORDER BY machine_name ASC";
$testPlatformResult = $db->query($testPlatformQry);

foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name']] = $row['machine_name'];
}

//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);


$covid19Obj = new \Vlsm\Models\Covid19($db);


$covid19Results = $covid19Obj->getCovid19Results();
$specimenTypeResult = $covid19Obj->getCovid19SampleTypes();
$covid19ReasonsForTesting = $covid19Obj->getCovid19ReasonsForTesting();

$covid19Symptoms = $covid19Obj->getCovid19Symptoms();
$covid19SelectedSymptoms = $covid19Obj->getCovid19SymptomsByFormId($covid19Info['covid19_id']);


$covid19Comorbidities = $covid19Obj->getCovid19Comorbidities();
$covid19SelectedComorbidities = $covid19Obj->getCovid19ComorbiditiesByFormId($covid19Info['covid19_id']);


// Getting the list of Provinces, Districts and Facilities

$rKey = '';
$pdQuery = "SELECT * FROM province_details";


if ($sarr['user_type'] == 'remoteuser') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    //check user exist in user_facility_map table
    $chkUserFcMapQry = "SELECT user_id from vl_user_facility_map where user_id='" . $_SESSION['userId'] . "'";
    $chkUserFcMapResult = $db->query($chkUserFcMapQry);
    if ($chkUserFcMapResult) {
        $pdQuery = "SELECT * from province_details as pd JOIN facility_details as fd ON fd.facility_state=pd.province_name JOIN vl_user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where user_id='" . $_SESSION['userId'] . "' group by province_name";
    }
    $rKey = 'R';
} else {
    $sampleCodeKey = 'sample_code_key';
    $sampleCode = 'sample_code';
    $rKey = '';
}
$pdResult = $db->query($pdQuery);
$province = "";
$province .= "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
    $province .= "<option data-code='" . $provinceName['province_code'] . "' data-province-id='" . $provinceName['province_id'] . "' data-name='" . $provinceName['province_name'] . "' value='" . $provinceName['province_name'] . "##" . $provinceName['province_code'] . "'>" . ucwords($provinceName['province_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, $covid19Info['facility_id'], '-- Select --');


//suggest sample id when lab user add request sample
//$sampleSuggestion = '';
//$sampleSuggestionDisplay = 'display:none;';
// $sCode = (isset($_GET['c']) && $_GET['c'] != '') ? $_GET['c'] : '';
// if ($sarr['user_type'] == 'vluser' && $sCode != '') {
//     $vlObj = new \Vlsm\Models\Covid19($db);
//     $sampleCollectionDate = explode(" ", $sampleCollectionDate);
//     $sampleCollectionDate = $general->humanDateFormat($sampleCollectionDate[0]);
//     $sampleSuggestionJson = $vlObj->generateCovid19SampleCode($stateResult[0]['province_code'], $sampleCollectionDate, 'png');
//     $sampleCodeKeys = json_decode($sampleSuggestionJson, true);
//     $sampleSuggestion = $sampleCodeKeys['sampleCode'];
//     $sampleSuggestionDisplay = 'display:block;';
// }

?>


<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><i class="fa fa-edit"></i> COVID-19 VIRUS LABORATORY TEST REQUEST FORM</h1>
        <ol class="breadcrumb">
            <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
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
                <form class="form-horizontal" method="post" name="editCovid19RequestForm" id="editCovid19RequestForm" autocomplete="off" action="covid-19-edit-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">SITE INFORMATION</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
                                </div>
                                <table class="table" style="width:100%">
                                    <!-- <?php if ($covid19Info['remote_sample'] == 'yes') { ?>
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
                                    <?php } ?> -->
                                    <tr>
                                        <?php if ($sarr['user_type'] == 'remoteuser') { ?>
                                            <td><label for="sampleCode">Sample ID </label> </td>
                                            <td colspan="5">
                                                <span id="sampleCodeInText" style="width:30%;border-bottom:1px solid #333;"><?php echo $covid19Info[$sampleCode]; ?></span>
                                                <input type="hidden" class="<?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" value="<?php echo $covid19Info[$sampleCode]; ?>" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode">Sample ID </label><span class="mandatory">*</span> </td>
                                            <td colspan="5">
                                                <input type="text" readonly value="<?php echo $covid19Info[$sampleCode]; ?>" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Sample ID" title="Please enter Sample ID" style="width:30%;" onchange="" />
                                            </td>
                                        <?php } ?>
                                    </tr>
                                    <tr>
                                        <td><label for="province">State </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="province" id="province" title="Please choose State" onchange="getfacilityDetails(this);" style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <td><label for="district">County </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="district" id="district" title="Please choose County" style="width:100%;" onchange="getfacilityDistrictwise(this);">
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
                                                    <option value="<?php echo ($implementingPartner['i_partner_id']); ?>" <?php echo ($covid19Info['implementing_partner'] == $implementingPartner['i_partner_id']) ? "selected='selected'" : ""; ?>><?php echo ucwords($implementingPartner['i_partner_name']); ?></option>
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
                                                    <option value="<?php echo ($fundingSource['funding_source_id']); ?>" <?php echo ($covid19Info['funding_source'] == $fundingSource['funding_source_id']) ? "selected='selected'" : ""; ?>><?php echo ucwords($fundingSource['funding_source_name']); ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <?php if ($sarr['user_type'] == 'remoteuser') { ?>
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

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">CASE DETAILS/DEMOGRAPHICS</h3>
                                </div>
                                <table class="table" style="width:100%">
                                    <tr>
                                        <th style="width:15% !important"><label for="patientId">Case ID <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="patientId" name="patientId" placeholder="Identification" title="Please enter ID" style="width:100%;" value="<?php echo $covid19Info['patient_id']; ?>" />
                                        </td>
                                        <th style="width:15% !important"><label for="externalSampleCode">DHIS2 Case ID <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important"><input type="text" class="form-control" id="externalSampleCode" name="externalSampleCode" placeholder="DHIS2 Case ID" title="Please enter DHIS2 Case ID" style="width:100%;" value="<?php echo $covid19Info['serial_no']; ?>" /></td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="firstName">First Name <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="firstName" name="firstName" placeholder="First Name" title="Please enter Case first name" style="width:100%;" value="<?php echo $covid19Info['patient_name']; ?>" />
                                        </td>
                                        <th style="width:15% !important"><label for="lastName">Last name </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="lastName" name="lastName" placeholder="Last name" title="Please enter Case last name" style="width:100%;" value="<?php echo $covid19Info['patient_surname']; ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="patientDob">Date of Birth <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <input type="text" class="form-control isRequired" id="patientDob" name="patientDob" placeholder="Date of Birth" title="Please enter Date of birth" style="width:100%;" onchange="calculateAgeInYears();" value="<?php echo $general->humanDateFormat($covid19Info['patient_dob']); ?>" />
                                        </td>
                                        <th>Case Age (years)</th>
                                        <td><input type="number" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="patientAge" name="patientAge" placeholder="Age (in years)" title="Age" style="width:100%;" value="<?php echo $covid19Info['patient_age']; ?>" /></td>
                                    </tr>
                                    <tr>
                                        <th><label for="patientGender">Gender <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <select class="form-control isRequired" name="patientGender" id="patientGender">
                                                <option value=''> -- Select -- </option>
                                                <option value='male' <?php echo ($covid19Info['patient_gender'] == 'male') ? "selected='selected'" : ""; ?>> Male </option>
                                                <option value='female' <?php echo ($covid19Info['patient_gender'] == 'female') ? "selected='selected'" : ""; ?>> Female </option>
                                                <option value='other' <?php echo ($covid19Info['patient_gender'] == 'other') ? "selected='selected'" : ""; ?>> Other </option>

                                            </select>
                                        </td>
                                        <th>Phone number</th>
                                        <td><input type="text" class="form-control " id="patientPhoneNumber" name="patientPhoneNumber" placeholder="Phone Number" title="Phone Number" style="width:100%;" value="<?php echo $covid19Info['patient_phone_number']; ?>" /></td>
                                        </tr>
                                    <tr>
                                        <th>Case address</th>
                                        <td><textarea class="form-control " id="patientAddress" name="patientAddress" placeholder="Address" title="Address" style="width:100%;" onchange=""><?php echo $covid19Info['patient_address']; ?></textarea></td>
                                    
                                        <th>Case State</th>
                                        <td><input type="text" value="<?php echo $covid19Info['patient_province']; ?>" class="form-control " id="patientProvince" name="patientProvince" placeholder="State" title="Please enter the State" style="width:100%;" /></td>
                                        </tr>
                                    <tr>
                                        <th>County</th>
                                        <td><input class="form-control" value="<?php echo $covid19Info['patient_district']; ?>" id="patientDistrict" name="patientDistrict" placeholder="County" title="Please enter the County" style="width:100%;"></td>
                                    
                                        <th>City/Village</th>
                                        <td><input class="form-control" value="<?php echo $covid19Info['patient_city']; ?>" id="patientCity" name="patientCity" placeholder="Case City/Village" title="Please enter the Case City/Village" style="width:100%;"></td>
                                        </tr>
                                    <tr>
                                        <th>Country of Residence</th>
                                        <td><input type="text" class="form-control" value="<?php echo $covid19Info['patient_nationality']; ?>" id="patientNationality" name="patientNationality" placeholder="Country of Residence" title="Please enter transit" style="width:100%;" /></td>
                                    
                                        <th>Passport Number</th>
                                        <td><input class="form-control" id="patientPassportNumber" name="patientPassportNumber" value="<?php echo $covid19Info['patient_passport_number']; ?>" placeholder="Passport Number" title="Please enter Passport Number" style="width:100%;"></td>

                                    </tr>
                                </table>

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">SPECIMEN INFORMATION</h3>
                                </div>
                                <table class="table">

                                    <tr>
                                        <td colspan=4>
                                            <ul>
                                                <li>All specimens collected should be regarded as potentially infectious and you <u>MUST CONTACT</u> the reference laboratory before sending samples.</li>
                                                <li>All samples must be sent in accordance with category B transport requirements.</li>
                                            </ul>

                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Type of Test Request</th>
                                        <td>
                                            <select name="testTypeRequested" id="testTypeRequested" class="form-control" title="Please choose type of test request" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <option value="PCR" <?php echo (isset($covid19Info['type_of_test_requested']) && $covid19Info['type_of_test_requested'] == 'PCR') ? "selected='selected'" : ""; ?>>PCR</option>
                                                <option value="GeneXpert" <?php echo (isset($covid19Info['type_of_test_requested']) && $covid19Info['type_of_test_requested'] == 'GeneXpert') ? "selected='selected'" : ""; ?>>GeneXpert</option>
                                                <option value="RDT" <?php echo (isset($covid19Info['type_of_test_requested']) && $covid19Info['type_of_test_requested'] == 'RDT') ? "selected='selected'" : ""; ?>>RDT</option>
                                                <option value="ELISA" <?php echo (isset($covid19Info['type_of_test_requested']) && $covid19Info['type_of_test_requested'] == 'ELISA') ? "selected='selected'" : ""; ?>>ELISA</option>
                                            </select>
                                            </select>
                                        </td>
                                        <th>Reason for Test Request <span class="mandatory">*</span></th>
                                        <td>
                                            <select name="reasonForCovid19Test" id="reasonForCovid19Test" class="form-control isRequired" title="Please choose specimen type" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <?php echo $general->generateSelectOptions($covid19ReasonsForTesting, $covid19Info['reason_for_covid19_test']); ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important">Sample Collection Date <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" value="<?php echo ($covid19Info['sample_collection_date']); ?>" />
                                        </td>
                                        <th>Specimen Type <span class="mandatory">*</span></th>
                                        <td>
                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose specimen type" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <?php echo $general->generateSelectOptions($specimenTypeResult, $covid19Info['specimen_type']); ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="testNumber">Test Number</label></th>
                                        <td>
                                            <select class="form-control" name="testNumber" id="testNumber" title="Prélévement" style="width:100%;">
                                                <option value="">--Select--</option>
                                                <?php foreach (range(1, 5) as $element) {
                                                    $selected = (isset($covid19Info['test_number']) && $covid19Info['test_number'] == $element) ? "selected='selected'" : "";
                                                    echo '<option value="' . $element . '" ' . $selected . '>' . $element . '</option>';
                                                } ?>
                                            </select>
                                        </td>
                                        <th></th>
                                        <td></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php if ($sarr['user_type'] != 'remoteuser') { ?>
                        <?php //if (false) { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Reserved for Laboratory Use </h3>
                                    </div>
                                    <table class="table" style="width:100%">
                                        <tr>
                                            <th><label for="">Sample Received Date </label></th>
                                            <td>
                                                <input type="text" class="form-control" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="e.g 09-Jan-1992 05:30" title="Please enter sample receipt date" value="<?php echo $general->humanDateFormat($covid19Info['sample_received_at_vl_lab_datetime']) ?>" onchange="" style="width:100%;" />
                                            </td>
                                            <td class="lab-show"><label for="labId">Lab Name </label> </td>
                                            <td class="lab-show">
                                                <select name="labId" id="labId" class="form-control" title="Please select Testing Lab name" style="width:100%;" onchange="getTestingPoints();">
                                                    <?= $general->generateSelectOptions($testingLabs, $covid19Info['lab_id'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><label for="specimenQuality">Specimen Quality</label></td>
                                            <td>
                                                <select class="form-control" id="specimenQuality" name="specimenQuality" title="Please enter the specimen quality">
                                                    <option value="">--Select--</option>
                                                    <option value="good" <?php echo (isset($covid19Info['sample_condition']) && $covid19Info['sample_condition'] == 'good') ? "selected='selected'" : ""; ?>>Good</option>
                                                    <option value="poor" <?php echo (isset($covid19Info['sample_condition']) && $covid19Info['sample_condition'] == 'poor') ? "selected='selected'" : ""; ?>>Poor</option>
                                                </select>
                                            </td>
                                            <th><label for="labTechnician">Lab Technician </label></th>
                                            <td>
                                                <select name="labTechnician" id="labTechnician" class="form-control" title="Please select a Lab Technician" style="width:100%;">
                                                    <option value="">--Select--</option>
                                                    <?php foreach ($labTechnicians as $labTech) {
                                                        $selected = (isset($covid19Info['lab_technician']) && $labTech['user_id'] == $covid19Info['lab_technician']) ? "selected='selected'" : "";
                                                        echo '<option value="' . $labTech['user_id'] . '" ' . $selected . '>' . ucwords($labTech['user_name']) . '</option>';
                                                    } ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th class="testingPointField" style="display:none;"><label for="">Testing Point </label></th>
                                            <td class="testingPointField" style="display:none;">
                                                <select name="testingPoint" id="testingPoint" class="form-control" title="Please select a Testing Point" style="width:100%;">
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Is Sample Rejected?</th>
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
                                                        <optgroup label="<?php echo ucwords($type['rejection_type']); ?>">
                                                            <?php
                                                            foreach ($rejectionResult as $reject) {
                                                                if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                                    <option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($covid19Info['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ucwords($reject['rejection_reason_name']); ?></option>
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
                                            <th>Rejection Date<span class="mandatory">*</span></th>
                                            <td><input value="<?php echo $general->humanDateFormat($covid19Info['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4">
                                                <table class="table table-bordered table-striped" id="testNameTable">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center">Test No.</th>
                                                            <th class="text-center">Test Method</th>
                                                            <th class="text-center">Date of Testing</th>
                                                            <th class="text-center">Test Platform</th>
                                                            <th class="text-center">Test Result</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="testKitNameTable">
                                                        <?php if (isset($covid19TestInfo) && count($covid19TestInfo) > 0) {
                                                            foreach ($covid19TestInfo as $indexKey => $rows) { ?>
                                                                <tr>
                                                                    <td class="text-center"><?php echo ($indexKey + 1); ?><input type="hidden" name="testId[]" value="<?php echo base64_encode($covid19TestInfo[$indexKey]['test_id']); ?>"></td>
                                                                    <td>
                                                                        <?php
                                                                        $value = '';
                                                                        if (!in_array($covid19TestInfo[$indexKey]['test_name'], array('PCR', 'GeneXpert', 'RDT', 'ELISA', 'other'))) {
                                                                            $value = 'value="' . $covid19TestInfo[$indexKey]['test_name'] . '"';
                                                                            $show =  "block";
                                                                        } else {
                                                                            $show =  "none";
                                                                        } ?>
                                                                        <select onchange="otherCovidTestName(this.value,<?php echo ($indexKey + 1); ?>)" class="form-control test-name-table-input" id="testName<?php echo ($indexKey + 1); ?>" name="testName[]" title="Please enter the name of the Testkit (or) Test Method used">
                                                                            <option value="">--Select--</option>
                                                                            <option value="PCR" <?php echo (isset($covid19TestInfo[$indexKey]['test_name']) && $covid19TestInfo[$indexKey]['test_name'] == 'PCR') ? "selected='selected'" : ""; ?>>PCR</option>
                                                                            <option value="GeneXpert" <?php echo (isset($covid19TestInfo[$indexKey]['test_name']) && $covid19TestInfo[$indexKey]['test_name'] == 'GeneXpert') ? "selected='selected'" : ""; ?>>GeneXpert</option>
                                                                            <option value="RDT" <?php echo (isset($covid19TestInfo[$indexKey]['test_name']) && $covid19TestInfo[$indexKey]['test_name'] == 'RDT') ? "selected='selected'" : ""; ?>>RDT</option>
                                                                            <option value="ELISA" <?php echo (isset($covid19TestInfo[$indexKey]['test_name']) && $covid19TestInfo[$indexKey]['test_name'] == 'ELISA') ? "selected='selected'" : ""; ?>>ELISA</option>
                                                                            <option value="other" <?php echo (isset($show) && $show == 'block') ? "selected='selected'" : ""; ?>>Others</option>
                                                                        </select>
                                                                        <input <?php echo $value; ?> type="text" name="testNameOther[]" id="testNameOther<?php echo ($indexKey + 1); ?>" class="form-control testNameOther<?php echo ($indexKey + 1); ?>" title="Please enter the name of the Testkit (or) Test Method used" placeholder="Enter Test Method used" style="display: <?php echo $show; ?>;margin-top: 10px;" />
                                                                    </td>
                                                                    <td><input type="text" value="<?php echo $general->humanDateFormat($covid19TestInfo[$indexKey]['sample_tested_datetime']); ?>" name="testDate[]" id="testDate<?php echo ($indexKey + 1); ?>" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row <?php echo ($indexKey + 1); ?>" /></td>
                                                                    <td>
                                                                        <select type="text" name="testingPlatform[]" id="testingPlatform<?php echo ($indexKey + 1); ?>" class="form-control test-name-table-input" title="Please select the Testing Platform for <?php echo ($indexKey + 1); ?>">
                                                                            <?= $general->generateSelectOptions($testPlatformList, $covid19TestInfo[$indexKey]['testing_platform'], '-- Select --'); ?>
                                                                        </select>
                                                                    </td>
                                                                    <td><select class="form-control test-result test-name-table-input result-focus" name="testResult[]" id="testResult<?php echo ($indexKey + 1); ?>" title="Please select the result for row <?php echo ($indexKey + 1); ?>">
                                                                            <option value=''> -- Select -- </option>
                                                                            <?php foreach ($covid19Results as $c19ResultKey => $c19ResultValue) { ?>
                                                                                <option value="<?php echo $c19ResultKey; ?>" <?php echo ($covid19TestInfo[$indexKey]['result'] == $c19ResultKey) ? "selected='selected'" : ""; ?>> <?php echo $c19ResultValue; ?> </option>
                                                                            <?php } ?>
                                                                        </select>
                                                                    </td>
                                                                    <td style="vertical-align:middle;text-align: center;width:100px;">
                                                                        <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow();"><i class="fa fa-plus"></i></a>&nbsp;
                                                                        <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);deleteRow('<?php echo base64_encode($covid19TestInfo[$indexKey]['test_id']); ?>');"><i class="fa fa-minus"></i></a>
                                                                    </td>
                                                                </tr>
                                                        <?php }
                                                        } ?>
                                                    </tbody>
                                                    <!-- < ?php if (isset($_SESSION['privileges']) && in_array("record-final-result.php", $_SESSION['privileges'])) { ?>
                                                    < ?php }?> -->
                                                    <tfoot>
                                                        <tr>
                                                            <th colspan="4" class="text-right">Final Result</th>
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
                                        <tr class="change-reason" style="display: none;">

                                            <th class="change-reason" style="display: none;">Reason for Changing <span class="mandatory">*</span></td>
                                            <td class="change-reason" style="display: none;"><textarea type="text" name="reasonForChanging" id="reasonForChanging" class="form-control date" placeholder="Enter the reason for changing" title="Please enter the reason for changing"></textarea></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th>Is Result Authorized ?</th>
                                            <td>
                                                <select name="isResultAuthorized" id="isResultAuthorized" class="disabled-field form-control" title="Is Result authorized ?" style="width:100%">
                                                    <option value="">-- Select --</option>
                                                    <option value='yes' <?php echo ($covid19Info['is_result_authorised'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                    <option value='no' <?php echo ($covid19Info['is_result_authorised'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                </select>
                                            </td>
                                            <th>Authorized By</th>
                                            <td><input type="text" value="<?php echo $covid19Info['authorized_by']; ?>" name="authorizedBy" id="authorizedBy" class="disabled-field form-control" placeholder="Authorized By" /></td>
                                        </tr>
                                        <tr>
                                            <th>Authorized on</td>
                                            <td><input type="text" value="<?php echo $general->humanDateFormat($covid19Info['authorized_on']); ?>" name="authorizedOn" id="authorizedOn" class="disabled-field form-control date" placeholder="Authorized on" /></td>
                                            <th></th>
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
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
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
    let testCounter = <?php echo (isset($covid19TestInfo) && count($covid19TestInfo) > 0) ? (count($covid19TestInfo)) : 0; ?>;
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

    function getTestingPoints() {
        var labId = $("#labId").val();
        var selectedTestingPoint = '<?php echo $covid19Info['testing_point']; ?>';
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

    $(document).ready(function() {
        if (testCounter == 0) {
            addTestRow();
        }

        $('.result-focus').change(function(e) {
            $('.change-reason').show(500);
            $('#reasonForChanging').addClass('isRequired');
        });

        $('#facilityId').select2({
            placeholder: "Select Clinic/Health Center"
        });

        $('#labTechnician').select2({
            placeholder: "Select Lab Technician"
        });
        getfacilityProvinceDetails($("#facilityId").val());
        getTestingPoints();

        $('#isResultAuthorized').change(function(e) {
            checkIsResultAuthorized();
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
            $(document).on('change', '.test-result, #result', function(e) {
                checkPostive();
            });
            checkPostive();
        <?php } ?>

    });


    function addTestRow() {
        testCounter++;
        let rowString = `<tr>
                    <td class="text-center">${testCounter}</td>
                    <td>
                    <select onchange="otherCovidTestName(this.value,${testCounter})" class="form-control test-name-table-input" id="testName${testCounter}" name="testName[]" title="Please enter the name of the Testkit (or) Test Method used">
                    <option value="">--Select--</option>
                    <option value="PCR">PCR</option>
                    <option value="GeneXpert">GeneXpert</option>
                    <option value="RDT">RDT</option>
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
                <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow(this);"><i class="fa fa-plus"></i></a>&nbsp;
                <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><i class="fa fa-minus"></i></a>
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
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
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

    function otherCovidTestName(val, id) {
        if (val == 'other') {
            $('.testNameOther' + id).show();
        } else {
            $('.testNameOther' + id).hide();
        }
    }
</script>