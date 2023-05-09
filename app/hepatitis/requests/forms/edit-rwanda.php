<?php

// imported in hepatitis-edit-request.php based on country in global config

use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\HepatitisService;
use App\Utilities\DateUtility;




/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$testingLabs = $facilitiesService->getTestingLabs('hepatitis');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, $hepatitisInfo['vl_testing_site'], "-- Select --");
//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);

//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);


// Getting the list of Provinces, Districts and Facilities

$rKey = '';
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";


if ($_SESSION['instanceType'] == 'remoteuser') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    //check user exist in user_facility_map table
    $chkUserFcMapQry = "SELECT user_id from user_facility_map where user_id='" . $_SESSION['userId'] . "'";
    $chkUserFcMapResult = $db->query($chkUserFcMapQry);
    if ($chkUserFcMapResult) {
        $pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id WHERE gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
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

//suggest sample id when lab user add request sample
$sampleSuggestion = '';
$sampleSuggestionDisplay = 'display:none;';
$sCode = (isset($_GET['c']) && $_GET['c'] != '') ? $_GET['c'] : '';
if ($sarr['sc_user_type'] == 'vluser' && $sCode != '') {
    $vlObj = new HepatitisService();
    $sampleCollectionDate = explode(" ", $sampleCollectionDate);
    $sampleCollectionDate = DateUtility::humanReadableDateFormat($sampleCollectionDate[0]);
    $sampleSuggestionJson = $vlObj->generatehepatitisSampleCode($stateResult[0]['province_code'], $sampleCollectionDate, 'png');
    $sampleCodeKeys = json_decode($sampleSuggestionJson, true);
    $sampleSuggestion = $sampleCodeKeys['sampleCode'];
    $sampleSuggestionDisplay = 'display:block;';
}

$facility = $general->generateSelectOptions($healthFacilities, null, '-- Select --');

?>


<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> RWANDA HEPATITIS LABORATORY TEST REQUEST FORM</h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
            <?php if (isset($hepatitisInfo['result']) && $hepatitisInfo['result'] != "") { ?>
                <li class="active">View Request</li>
            <?php } else { ?>
                <li class="active">Update Request</li>
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
                <form class="form-horizontal" method="post" name="editHepatitisRequestForm" id="editHepatitisRequestForm" autocomplete="off" action="hepatitis-edit-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border">
                                    <h3 class="box-title">SECTION 1. TO BE FILLED AT ENROLMENT FACILITY</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <?php if ($hepatitisInfo['remote_sample'] == 'yes') { ?>
                                        <tr>
                                            <?php
                                            if ($hepatitisInfo['sample_code'] != '') {
                                            ?>
                                                <td colspan="4"> <label for="sampleSuggest" class="text-danger">&nbsp;&nbsp;&nbsp;Please note that this Remote Sample has already been imported with VLSM Sample ID </td>
                                                <td colspan="2" align="left"> <?php echo $hepatitisInfo['sample_code']; ?></label> </td>
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
                                            <td>
                                                <span id="sampleCodeInText" style="width:30%;border-bottom:1px solid #333;"><?php echo ($sCode != '') ? $sCode : htmlspecialchars($hepatitisInfo[$sampleCode]); ?></span>
                                                <input type="hidden" class="<?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" value="<?php echo ($sCode != '') ? $sCode : htmlspecialchars($hepatitisInfo[$sampleCode]); ?>" />
                                            </td>

                                        <?php } else { ?>
                                            <td><label for="sampleCode">Sample ID </label><span class="mandatory">*</span> </td>
                                            <td>
                                                <input type="text" readonly value="<?php echo ($sCode != '') ? $sCode : htmlspecialchars($hepatitisInfo[$sampleCode]); ?>" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Sample ID" title="Please enter Sample ID" style="width:100%;" onchange="" />
                                            </td>
                                        <?php } ?>
                                        <th scope="row" style="width:15% !important"><label for="patientId">Patient Code <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <input type="text" class="form-control isRequired" id="patientId" name="patientId" placeholder="Patient Identification" title="Please enter Patient ID" style="width:100%;" value="<?php echo $hepatitisInfo['patient_id']; ?>" />
                                        </td>
                                        <td><label for="hepatitisTestType">Type of Hepatitis Test </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="hepatitisTestType" id="hepatitisTestType" title="Please choose type hepatitis test" style="width:100%;" onchange="hepatitisTestTypeFn(this.value);">
                                                <option value="">--Select--</option>
                                                <option value="HBV" <?php echo (isset($hepatitisInfo['hepatitis_test_type']) && $hepatitisInfo['hepatitis_test_type'] == 'HBV') ? "selected='selected'" : ""; ?>>HBV</option>
                                                <option value="HCV" <?php echo (isset($hepatitisInfo['hepatitis_test_type']) && $hepatitisInfo['hepatitis_test_type'] == 'HCV') ? "selected='selected'" : ""; ?>>HCV</option>
                                            </select>
                                        </td>
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
                                                <?= $general->generateSelectOptions($healthFacilities, $hepatitisInfo['facility_id'], '-- Select --'); ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser'  && $_SESSION['accessType'] == 'collection-site') { ?>
                                            <td><label for="labId">Lab Name <span class="mandatory">*</span></label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control isRequired" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, $hepatitisInfo['lab_id'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        <?php } ?>
                                        <td style="width:15% !important">Sample Collection Date <span class="mandatory">*</span> </td>
                                        <td>
                                            <input value="<?php echo ($hepatitisInfo['sample_collection_date']); ?>" class="form-control isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" />
                                        </td>
                                        <td style="width:15% !important">DHIS2 Case ID </td>
                                        <td>
                                            <input value="<?php echo ($hepatitisInfo['external_sample_code']); ?>" class="form-control" type="text" name="externalSampleCode" id="externalSampleCode" placeholder="DHIS2 Case ID" />
                                        </td>
                                    </tr>

                                </table>
                                <br>
                                <hr style="border: 1px solid #ccc;">

                                <div class="box-header with-border">
                                    <h3 class="box-title">DEMOGRAPHICS</h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">

                                    <tr>
                                        <th scope="row" style="width:15% !important"><label for="firstName">First Name <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="firstName" name="firstName" placeholder="First Name" title="Please enter patient first name" style="width:100%;" value="<?php echo $hepatitisInfo['patient_name']; ?>" />
                                        </td>
                                        <th scope="row" style="width:15% !important"><label for="lastName">Last name </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="lastName" name="lastName" placeholder="Last name" title="Please enter patient last name" style="width:100%;" value="<?php echo $hepatitisInfo['patient_surname']; ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="patientPhoneNumber">Phone number</label></th>
                                        <td><input type="text" class="form-control " id="patientPhoneNumber" name="patientPhoneNumber" placeholder="Patient Phone Number" title="Patient Phone Number" style="width:100%;" value="<?php echo $hepatitisInfo['patient_phone_number']; ?>" /></td>

                                        <th scope="row"><label for="patientDob">Date of Birth <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <input type="text" class="form-control isRequired" id="patientDob" name="patientDob" placeholder="Date of Birth" title="Please enter Date of birth" style="width:100%;" onchange="calculateAgeInYears();" value="<?php echo DateUtility::humanReadableDateFormat($hepatitisInfo['patient_dob']); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="patientAge">Patient Age (years)</label></th>
                                        <td><input type="number" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="patientAge" name="patientAge" placeholder="Patient Age (in years)" title="Patient Age" style="width:100%;" value="<?php echo $hepatitisInfo['patient_age']; ?>" /></td>
                                        <th scope="row"><label for="patientGender">Gender <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <select class="form-control isRequired" name="patientGender" id="patientGender">
                                                <option value=''> -- Select -- </option>
                                                <option value='male' <?php echo ($hepatitisInfo['patient_gender'] == 'male') ? "selected='selected'" : ""; ?>> Male </option>
                                                <option value='female' <?php echo ($hepatitisInfo['patient_gender'] == 'female') ? "selected='selected'" : ""; ?>> Female </option>
                                                <option value='other' <?php echo ($hepatitisInfo['patient_gender'] == 'other') ? "selected='selected'" : ""; ?>> Other </option>

                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Province</td>
                                        <td><input type="text" value="<?php echo $hepatitisInfo['patient_province']; ?>" class="form-control " id="patientProvince" name="patientProvince" placeholder="Patient Province" title="Please enter the patient province" style="width:100%;" /></td>

                                        <td>District</td>
                                        <td><input class="form-control" value="<?php echo $hepatitisInfo['patient_district']; ?>" id="patientDistrict" name="patientDistrict" placeholder="Patient District" title="Please enter the patient district" style="width:100%;"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="patientCity">Village</label></th>
                                        <td><input type="text" class="form-control" value="<?php echo $hepatitisInfo['patient_city']; ?>" id="patientCity" name="patientCity" placeholder="Patient Village" title="Please enter the patient village" style="width:100%;" /></td>

                                        <th scope="row"><label for="patientGender">Insurance</label></th>
                                        <td>
                                            <select class="form-control" name="insurance" id="insurance" title="Please select the Insurance">
                                                <option value=''> -- Select -- </option>
                                                <option value='mutuelle' <?php echo ($hepatitisInfo['patient_insurance'] == 'mutuelle') ? "selected='selected'" : ""; ?>> Mutuelle </option>
                                                <option value='RAMA' <?php echo ($hepatitisInfo['patient_insurance'] == 'RAMA') ? "selected='selected'" : ""; ?>> RAMA </option>
                                                <option value='MMI' <?php echo ($hepatitisInfo['patient_insurance'] == 'MMI') ? "selected='selected'" : ""; ?>> MMI </option>
                                                <option value='private' <?php echo ($hepatitisInfo['patient_insurance'] == 'private') ? "selected='selected'" : ""; ?>> Private </option>
                                                <option value='none' <?php echo ($hepatitisInfo['patient_insurance'] == 'none') ? "selected='selected'" : ""; ?>> None </option>

                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="box-body">
                            <div class="box-header with-border">
                                <h3 class="box-title">TEST RESULTS FOR SCREENING BY RDTs</h3>
                            </div>
                            <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                <tr>

                                    <td>Specimen Type <span class="mandatory">*</span></td>
                                    <td>
                                        <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose specimen type" style="width:100%">
                                            <?= $general->generateSelectOptions($specimenResult, $hepatitisInfo['specimen_type'], '-- Select --'); ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" class="hbvFields"><label for="HBsAg">HBsAg Result</label></th>
                                    <td class="hbvFields">
                                        <select class="hbvFields form-control" name="HBsAg" id="HBsAg" title="Please choose HBsAg result">
                                            <option value=''> -- Select -- </option>
                                            <option value='positive' <?php echo ($hepatitisInfo['hbsag_result'] == 'positive') ? "selected='selected'" : ""; ?>>Positive</option>
                                            <option value='negative' <?php echo ($hepatitisInfo['hbsag_result'] == 'negative') ? "selected='selected'" : ""; ?>>Negative</option>
                                            <option value='intermediate' <?php echo ($hepatitisInfo['hbsag_result'] == 'intermediate') ? "selected='selected'" : ""; ?>>Intermediate</option>
                                        </select>
                                    </td>
                                    <th scope="row" class="hcvFields"><label for="antiHcv">Anti-HCV Result</label></th>
                                    <td class="hcvFields">
                                        <select class="hcvFields form-control" name="antiHcv" id="antiHcv" title="Please choose Anti-HCV result">
                                            <option value=''> -- Select -- </option>
                                            <option value='positive' <?php echo ($hepatitisInfo['anti_hcv_result'] == 'positive') ? "selected='selected'" : ""; ?>>Positive</option>
                                            <option value='negative' <?php echo ($hepatitisInfo['anti_hcv_result'] == 'negative') ? "selected='selected'" : ""; ?>>Negative</option>
                                            <option value='intermediate' <?php echo ($hepatitisInfo['anti_hcv_result'] == 'intermediate') ? "selected='selected'" : ""; ?>>Intermediate</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="labTechnician">Lab Technician </label></th>
                                    <td>
                                        <select name="labTechnician" id="labTechnician" class="form-control" title="Please select a Lab Technician" style="width:100%;">
                                            <?= $general->generateSelectOptions($labTechniciansResults, (isset($hepatitisInfo['lab_technician']) && $hepatitisInfo['lab_technician'] != '') ? $hepatitisInfo['lab_technician'] : $_SESSION['userId'], '-- Select --'); ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>


                        <?php if ($usersService->isAllowed('hepatitis-update-result.php') && $_SESSION['accessType'] != 'collection-site') { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">SECTION 2. TO BE FILLED AT VIRAL LOAD TESTING SITE</h3>
                                    </div>
                                    <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                        <tr>
                                            <th scope="row"><label for="">Sample Received Date </label></th>
                                            <td>
                                                <input value="<?php echo DateUtility::humanReadableDateFormat($hepatitisInfo['sample_received_at_vl_lab_datetime']) ?>" type="text" class="labSecInput form-control" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter sample receipt date" style="width:100%;" />
                                            </td>
                                            <td><label for="labId">Lab Name <span class="mandatory">*</span></label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="labSecInput form-control" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, $hepatitisInfo['lab_id'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="sampleTestedDateTime">VL Testing Date</label></th>
                                            <td>
                                                <input value="<?php echo DateUtility::humanReadableDateFormat($hepatitisInfo['sample_tested_datetime']) ?>" type="text" class="labSecInput form-control" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _("Please enter date"); ?>" title="Please enter testing date" style="width:100%;" />
                                            </td>
                                            <th scope="row"><label for="vlTestingSite">VL Testing Site</label></th>
                                            <td>
                                                <select class="labSecInput form-control" id="vlTestingSite" name="vlTestingSite" title="Please select testing site" style="width:100%;">
                                                    <?= $testingLabsDropdown; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="reasonVlTest">VL test purpose</label></th>
                                            <td>
                                                <select class="labSecInput form-control" name="reasonVlTest" id="reasonVlTest">
                                                    <option value=''> -- Select -- </option>
                                                    <option value='Initial HCV VL' <?php echo ($hepatitisInfo['reason_for_vl_test'] == 'Initial HCV VL') ? "selected='selected'" : ""; ?>>Initial HCV VL</option>
                                                    <option value='SVR12 HCV VL' <?php echo ($hepatitisInfo['reason_for_vl_test'] == 'SVR12 HCV VL') ? "selected='selected'" : ""; ?>>SVR12 HCV VL</option>
                                                    <option value='SVR12 HCV VL - Second Line' <?php echo ($hepatitisInfo['reason_for_vl_test'] == 'SVR12 HCV VL - Second Line') ? "selected='selected'" : ""; ?>>SVR12 HCV VL - Second Line</option>
                                                    <option value='Initial HBV VL' <?php echo ($hepatitisInfo['reason_for_vl_test'] == 'Initial HBV VL') ? "selected='selected'" : ""; ?>>Initial HBV VL</option>
                                                    <option value='Follow up HBV VL' <?php echo ($hepatitisInfo['reason_for_vl_test'] == 'Follow up HBV VL') ? "selected='selected'" : ""; ?>>Follow up HBV VL</option>
                                                </select>
                                            </td>
                                            <td>Is Sample Rejected?</td>
                                            <td>
                                                <select class="labSecInput form-control result-focus" name="isSampleRejected" id="isSampleRejected">
                                                    <option value=""> -- Select -- </option>
                                                    <option value="yes" <?php echo ($hepatitisInfo['is_sample_rejected'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                    <option value="no" <?php echo ($hepatitisInfo['is_sample_rejected'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="show-rejection" style="display:none;">
                                            <th scope="row" class="show-rejection" style="display:none;">Reason for Rejection<span class="mandatory">*</span></th>
                                            <td class="show-rejection" style="display:none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="Please choose reason for rejection">
                                                    <option value="">-- Select --</option>
                                                    <?php echo $rejectionReason; ?>
                                                </select>
                                            </td>
                                            <th scope="row">Rejection Date<span class="mandatory">*</span></th>
                                            <td><input value="<?php echo DateUtility::humanReadableDateFormat($hepatitisInfo['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
                                        </tr>
                                        <!-- <tr>
                                            <th scope="row" class="hcvFields"><label for="hcv">HCV VL Result</label></th>
                                            <td class="hcvFields">
                                                <select class="hcvFields result-focus labSecInput form-control rejected-input" name="hcv" id="hcv" title="Please select HCV VL Result">
                                                    <?= $general->generateSelectOptions($hepatitisResults, $hepatitisInfo['hcv_vl_result'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row" class="hbvFields"><label for="hbv">HBV VL Result</label></th>
                                            <td class="hbvFields">
                                                <select class="hbvFields result-focus labSecInput form-control rejected-input" name="hbv" id="hbv" title="Please select the HBV VL Result">
                                                    <?= $general->generateSelectOptions($hepatitisResults, $hepatitisInfo['hbv_vl_result'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr> -->
                                        <tr>
                                            <th scope="row" class="hcvFields"><label for="hcvCount">HCV VL Count</label></th>
                                            <td class="hcvFields">
                                                <input value="<?php echo $hepatitisInfo['hcv_vl_count']; ?>" data-val="" type="text" class="hcvFields  result-focus labSecInput form-control rejected-input" placeholder="Enter HCV Count" title="Please enter HCV Count" name="hcvCount" id="hcvCount">
                                            </td>
                                            <th scope="row" class="hbvFields"><label for="hbvCount">HBV VL Count</label></th>
                                            <td class="hbvFields">
                                                <input value="<?php echo $hepatitisInfo['hbv_vl_count']; ?>" data-val="" type="text" class="hbvFields result-focus labSecInput form-control rejected-input" placeholder="Enter HBV Count" title="Please enter HBV Count" name="hbvCount" id="hbvCount">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><label for="">Testing Platform </label></td>
                                            <td><select name="hepatitisPlatform" id="hepatitisPlatform" class="labSecInput form-control rejected-input" title="Please select the testing platform">
                                                    <?= $general->generateSelectOptions($testPlatformList, $hepatitisInfo['hepatitis_test_platform'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <td><label for="">Machine used to test </label></td>
                                            <td><select name="machineName" id="machineName" class="labSecInput form-control rejected-input" title="Please select the machine name" ">
                                                <option value="">-- Select --</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Is Result Authorized ?</td>
                                            <td>
                                                <select name=" isResultAuthorized" id="isResultAuthorized" class="labSecInput disabled-field form-control rejected-input" title="Is Result authorized ?" style="width:100%">
                                                    <option value="">-- Select --</option>
                                                    <option value='yes' <?php echo ($hepatitisInfo['is_result_authorised'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                    <option value='no' <?php echo ($hepatitisInfo['is_result_authorised'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                </select>
                                            </td>
                                            <?php
                                            $disabled = (isset($hepatitisInfo['is_result_authorised']) && $hepatitisInfo['is_result_authorised'] == 'no') ? "disabled" : "";
                                            ?>
                                            <td>Authorized By</td>
                                            <td>
                                                <select name="authorizedBy" <?php echo $disabled; ?> id="authorizedBy" class="disabled-field form-control" title="Please choose authorized by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, $hepatitisInfo['authorized_by'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Authorized on</td>
                                            <td><input value="<?php echo DateUtility::humanReadableDateFormat($hepatitisInfo['authorized_on']) ?>" type="text" <?php echo $disabled; ?> name="authorizedOn" id="authorizedOn" class="labSecInput disabled-field rejected-input form-control date" placeholder="Authorized on" title="Please select the authorized on" /></td>
                                            <th scope="row" class="change-reason" style="display: none;">Reason for Changing <span class="mandatory">*</span></th>
                                            <td class="change-reason" style="display: none;"><textarea type="text" name="reasonForChanging" id="reasonForChanging" class="form-control date" placeholder="Enter the reason for changing" title="Please enter the reason for changing"></textarea></td>
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
                        <input type="hidden" name="revised" id="revised" value="no" />
                        <input type="hidden" name="formId" id="formId" value="7" />
                        <input type="hidden" name="deletedRow" id="deletedRow" value="" />
                        <input type="hidden" name="hepatitisSampleId" id="hepatitisSampleId" value="<?php echo $hepatitisInfo['hepatitis_id']; ?>" />
                        <input type="hidden" name="sampleCodeCol" id="sampleCodeCol" value="<?php echo $arr['sample_code']; ?>" />
                        <input type="hidden" name="oldStatus" id="oldStatus" value="<?php echo $hepatitisInfo['result_status']; ?>" />
                        <input type="hidden" name="provinceCode" id="provinceCode" />
                        <input type="hidden" name="provinceId" id="provinceId" />
                        <a href="/hepatitis/requests/hepatitis-requests.php" class="btn btn-default"> Cancel</a>
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
                    testType: 'hepatitis'
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
                    testType: 'hepatitis'
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
                    testType: 'hepatitis'
                },
                function(data) {
                    console.log(data);
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
        $('#labId').removeClass('isRequired');
        $('.labSecInput').each(function() {
            if ($(this).val()) {
                $('#labId').addClass('isRequired');
            }
        });
        if ($('#antiHcv').val() != "" || $('#HBsAg').val() != "") {
            checkresult = true;
        } else {
            checkresult = false;
            alert("Please select test result for screening");
            $('#HBsAg').focus();
        }

        if ($('#isResultAuthorized').val() != "yes") {
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
        }
        $("#provinceCode").val($("#province").find(":selected").attr("data-code"));
        $("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
        flag = deforayValidator.init({
            formId: 'editHepatitisRequestForm'
        });
        if (flag && checkresult) {
            document.getElementById('editHepatitisRequestForm').submit();
        }
    }

    $(document).ready(function() {
        $('#facilityId').select2({
            placeholder: "Select Clinic/Health Center"
        });
        $('#district').select2({
            placeholder: "District"
        });
        $('#province').select2({
            placeholder: "Province"
        });

        $("#labId").select2({
            placeholder: "Select Testing Lab"
        });
        $("#vlTestingSite").select2({
            placeholder: "Select Vl Testing Site"
        });
        $('#authorizedBy').select2({
            placeholder: "Select Authorized By"
        });

        getfacilityProvinceDetails($("#facilityId"));
        $('#isResultAuthorized').change(function(e) {
            checkIsResultAuthorized();
        });
        checkIsResultAuthorized();
    });

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

    function hepatitisTestTypeFn(val) {
        if (val == 'HBV') {
            option1 = 'Initial HBV VL';
            option2 = 'Follow up HBV VL';

            $('.hcvFields').hide();
            $('.hbvFields').show();
            $("#reasonVlTest option[value='Initial HCV VL']").remove();
            $("#reasonVlTest option[value='SVR12 HCV VL']").remove();

            if ($("#reasonVlTest option[value='" + option1 + "']").length == 0) {
                $('#reasonVlTest').append(`
                <option value="${option1}"> ${option1} </option>`);
            }
            if ($("#reasonVlTest option[value='" + option2 + "']").length == 0) {
                $('#reasonVlTest').append(`
                <option value="${option2}"> ${option2} </option>`);
            }
        } else if (val == 'HCV') {
            option1 = 'Initial HCV VL';
            option2 = 'SVR12 HCV VL';

            $('.hbvFields').hide();
            $('.hcvFields').show();
            $("#reasonVlTest option[value='Initial HBV VL']").remove();
            $("#reasonVlTest option[value='Follow up HBV VL']").remove();

            if ($("#reasonVlTest option[value='" + option1 + "']").length == 0) {
                $('#reasonVlTest').append(`
                <option value="${option1}"> ${option1} </option>`);
            }
            if ($("#reasonVlTest option[value='" + option2 + "']").length == 0) {
                $('#reasonVlTest').append(`
                <option value="${option2}"> ${option2} </option>`);
            }
        }
    }
</script>