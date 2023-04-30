<?php

// imported in eid-edit-request.php based on country in global config

use App\Registries\ContainerRegistry;
use App\Services\EidService;
use App\Utilities\DateUtility;




//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);

//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);




// Getting the list of Provinces, Districts and Facilities


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();


$rKey = '';
$sKey = '';
$sFormat = '';
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    if (!empty($eidInfo['remote_sample']) && $eidInfo['remote_sample'] == 'yes') {
        $sampleCode = 'remote_sample_code';
    } else {
        $sampleCode = 'sample_code';
    }
    //check user exist in user_facility_map table
    $chkUserFcMapQry = "SELECT user_id FROM user_facility_map where user_id='" . $_SESSION['userId'] . "'";
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



//set province
if (isset($eidInfo['province_id']) && !empty($eidInfo['province_id'])) {
    $stateQuery = "SELECT * from geographical_divisions where geo_id= " . $eidInfo['province_id'];
    $stateResult = $db->query($stateQuery);
}
if (!isset($stateResult[0]['geo_code'])) {
    $provinceCode = '';
} else {
    $provinceCode = $stateResult[0]['geo_code'];
}

//suggest sample id when lab user add request sample
$sampleSuggestion = '';
$sampleSuggestionDisplay = 'display:none;';
$sCode = $_GET['c'];
if ($sarr['sc_user_type'] == 'vluser' && $sCode != '') {
    $vlObj = ContainerRegistry::get(EidService::class);
    $sampleCollectionDate = explode(" ", $sampleCollectionDate);
    $sampleCollectionDate = DateUtility::humanReadableDateFormat($sampleCollectionDate[0]);
    $sampleSuggestionJson = $vlObj->generateEIDSampleCode($provinceCode, $sampleCollectionDate, 'png');
    $sampleCodeKeys = json_decode($sampleSuggestionJson, true);
    $sampleSuggestion = $sampleCodeKeys['sampleCode'];
    $sampleSuggestionDisplay = 'display:block;';
}

$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
    $province .= "<option data-code='" . $provinceName['geo_code'] . "' data-province-id='" . $provinceName['geo_id'] . "' data-name='" . $provinceName['geo_name'] . "' value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, $eidInfo['facility_id'], '-- Select --');

$eidInfo['mother_treatment'] = isset($eidInfo['mother_treatment']) ? explode(",", $eidInfo['mother_treatment']) : array();
//$eidInfo['child_treatment'] = isset($eidInfo['child_treatment']) ? explode(",", $eidInfo['child_treatment']) : array();

?>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _("EARLY INFANT DIAGNOSIS (EID) LABORATORY REQUEST FORM"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
            <li class="active"><?php echo _("Edit EID Request"); ?></li>
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
                <div class="disabledForm">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border">
                                    <h3 class="box-title">A. CHILD and MOTHER INFORMATION</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
                                </div>

                                <div class="col-xs-12 col-md-12 col-lg-12" style="<?php echo $sampleSuggestionDisplay; ?>">
                                    <?php
                                    if ($eidInfo['sample_code'] != '') {
                                    ?>
                                        <label for="sampleSuggest" class="text-danger">Please note that this Remote Sample has already been imported with VLSM Sample ID <?php echo $eidInfo['sample_code']; ?></label>
                                    <?php
                                    } else {
                                    ?>
                                        <label for="sampleSuggest">Sample ID (might change while submitting the form) - </label>
                                        <?php echo $sampleSuggestion; ?>
                                    <?php } ?>
                                    <br><br>
                                </div>
                                <table class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <td><label for="sampleCode">Sample ID </label></td>
                                            <td>
                                                <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?php echo $eidInfo[$sampleCode] ?></span>
                                                <input type="hidden" id="sampleCode" name="sampleCode" value="<?php echo $eidInfo[$sampleCode] ?>" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode">Sample ID </label><span class="mandatory">*</span></td>
                                            <td>
                                                <input type="text" readonly value="<?php echo ($sCode != '') ? $sCode : $eidInfo[$sampleCode]; ?>" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Sample ID" title="Please enter sample id" style="width:100%;" onchange="" />
                                            </td>
                                        <?php } ?>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
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
                                            <!-- <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire dappui" title="Please enter partenaire dappui" style="width:100%;"/> -->
                                            <select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose partenaire de mise en œuvre" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($implementingPartnerList as $implementingPartner) {
                                                ?>
                                                    <option value="<?php echo ($implementingPartner['i_partner_id']); ?>" <?php echo ($eidInfo['implementing_partner'] == $implementingPartner['i_partner_id']) ? "selected='selected'" : ""; ?>><?= $implementingPartner['i_partner_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td><label for="fundingSource">Funding Partner</label></td>
                                        <td>
                                            <select class="form-control" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($fundingSourceList as $fundingSource) {
                                                ?>
                                                    <option value="<?php echo ($fundingSource['funding_source_id']); ?>" <?php echo ($eidInfo['funding_source'] == $fundingSource['funding_source_id']) ? "selected='selected'" : ""; ?>><?= $fundingSource['funding_source_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <!-- <tr> -->
                                            <td><label for="labId">Lab Name <span class="mandatory">*</span></label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control isRequired" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, $eidInfo['lab_id'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <!-- </tr> -->
                                        <?php } ?>
                                    </tr>
                                </table>
                                <br><br>
                                <table class="table" aria-hidden="true" style="width:100%">

                                    <tr>
                                        <th style="width:15% !important"><label for="childId">Exposed Infant Identification <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="childId" name="childId" placeholder="Exposed Infant Identification (Patient)" title="Please enter Exposed Infant Identification" style="width:100%;" onchange="" value="<?php echo $eidInfo['child_id']; ?>" />
                                        </td>
                                        <th style="width:15% !important"><label for="childName">Infant name </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="childName" name="childName" placeholder="Infant name" title="Please enter Infant Name" style="width:100%;" value="<?php echo $eidInfo['child_name']; ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="childDob">Date of Birth </label></th>
                                        <td>
                                            <input type="text" class="form-control date" id="childDob" name="childDob" placeholder="Date of birth" title="Please enter Date of birth" style="width:100%;" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['child_dob']) ?>" onchange="calculateAgeInMonths();" />
                                        </td>
                                        <th scope="row"><label for="childGender">Gender </label></th>
                                        <td>
                                            <select class="form-control " name="childGender" id="childGender">
                                                <option value=''> -- Select -- </option>
                                                <option value='male' <?php echo ($eidInfo['child_gender'] == 'male') ? "selected='selected'" : ""; ?>> Male </option>
                                                <option value='female' <?php echo ($eidInfo['child_gender'] == 'female') ? "selected='selected'" : ""; ?>> Female </option>

                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Infant Age (months)</th>
                                        <td><input type="number" max=9 maxlength="1" value="<?php echo $eidInfo['child_age']; ?>" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="childAge" name="childAge" placeholder="Age" title="Age" style="width:100%;" onchange="$('#childDob').val('')" /></td>
                                        <th scope="row">Mother ART Number</th>
                                        <td><input type="text" class="form-control " value="<?php echo $eidInfo['mother_id']; ?>" id="mothersId" name="mothersId" placeholder="Mother ART Number" title="Mother ART Number" style="width:100%;" onchange="" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Caretaker phone number</th>
                                        <td><input type="text" class="form-control " value="<?php echo $eidInfo['caretaker_phone_number']; ?>" id="caretakerPhoneNumber" name="caretakerPhoneNumber" placeholder="Caretaker Phone Number" title="Caretaker Phone Number" style="width:100%;" onchange="" /></td>

                                        <th scope="row">Infant caretaker address</th>
                                        <td><textarea class="form-control " id="caretakerAddress" name="caretakerAddress" placeholder="Caretaker Address" title="Caretaker Address" style="width:100%;" onchange=""><?php echo $eidInfo['caretaker_address']; ?></textarea></td>

                                    </tr>


                                </table>



                                <br><br>
                                <table class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th colspan=4>
                                            <h4>Infant and Mother's Health Information</h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important">Mother's HIV Status:</th>
                                        <td style="width:35% !important">
                                            <select class="form-control" name="mothersHIVStatus" id="mothersHIVStatus">
                                                <option value=''> -- Select -- </option>
                                                <option value="positive" <?php echo ($eidInfo['mother_hiv_status'] == 'positive') ? "selected='selected'" : ""; ?>> Positive </option>
                                                <option value="negative" <?php echo ($eidInfo['mother_hiv_status'] == 'negative') ? "selected='selected'" : ""; ?>> Negative </option>
                                                <option value="unknown" <?php echo ($eidInfo['mother_hiv_status'] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                            </select>
                                        </td>

                                        <th style="width:15% !important">ART given to the Mother during:</th>
                                        <td style="width:35% !important">
                                            <input type="checkbox" name="motherTreatment[]" value="No ART given" <?php echo in_array('No ART given', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?>> No ART given <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Pregnancy" <?php echo in_array('Pregnancy', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?>> Pregnancy <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Labour/Delivery" <?php echo in_array('Labour/Delivery', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?>> Labour/Delivery <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Postnatal" <?php echo in_array('Postnatal', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?>> Postnatal <br>
                                            <!-- <input type="checkbox" name="motherTreatment[]" value="Other" onclick="$('#motherTreatmentOther').prop('disabled', function(i, v) { return !v; });" /> Other (Please specify): <input class="form-control" style="max-width:200px;display:inline;" disabled="disabled" placeholder="Other" type="text" name="motherTreatmentOther" id="motherTreatmentOther" /> <br> -->
                                            <input type="checkbox" name="motherTreatment[]" value="Unknown" <?php echo in_array('Unknown', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?>> Unknown
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row">Infant Rapid HIV Test Done</th>
                                        <td>
                                            <select class="form-control" name="rapidTestPerformed" id="rapidTestPerformed">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes" <?php echo ($eidInfo['rapid_test_performed'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                <option value="no" <?php echo ($eidInfo['rapid_test_performed'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                            </select>
                                        </td>

                                        <th scope="row">If yes, test date :</th>
                                        <td>
                                            <input class="form-control date" type="text" name="rapidtestDate" id="rapidtestDate" placeholder="if yes, test date" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['rapid_test_date']); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Rapid Test Result</th>
                                        <td>
                                            <select class="form-control" name="rapidTestResult" id="rapidTestResult">
                                                <option value=''> -- Select -- </option>
                                                <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                    <option value="<?php echo $eidResultKey; ?>" <?php echo ($eidInfo['rapid_test_result'] == $eidResultKey) ? "selected='selected'" : ""; ?>> <?php echo $eidResultValue; ?> </option>
                                                <?php } ?>

                                            </select>
                                        </td>

                                        <th scope="row">Infant stopped breastfeeding ?</th>
                                        <td>
                                            <select class="form-control" name="hasInfantStoppedBreastfeeding" id="hasInfantStoppedBreastfeeding">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                <option value="no" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                <option value="unknown" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Age (months) breastfeeding stopped :</th>
                                        <td>
                                            <input type="number" class="form-control" value="<?php echo $eidInfo['age_breastfeeding_stopped_in_months'] ?>" style="max-width:200px;display:inline;" placeholder="Age (months) breastfeeding stopped" type="text" name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" />
                                        </td>

                                        <th scope="row">PCR test performed on child before :</th>
                                        <td>
                                            <select class="form-control" name="pcrTestPerformedBefore" id="pcrTestPerformedBefore">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'unknown') ? "selected='selected'" : ""; ?>> Yes </option>
                                                <option value="no" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'unknown') ? "selected='selected'" : ""; ?>> No </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Previous PCR test date :</th>
                                        <td>
                                            <input class="form-control date" type="text" name="previousPCRTestDate" id="previousPCRTestDate" placeholder="if yes, test date" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['last_pcr_date']); ?>" />
                                        </td>

                                        <th scope="row">Reason for 2nd PCR :</th>
                                        <td>
                                            <select class="form-control" name="pcrTestReason" id="pcrTestReason">
                                                <option value=''> -- Select -- </option>
                                                <option value="Confirmation of positive first EID PCR test result" <?php echo ($eidInfo['reason_for_pcr'] == 'Confirmation of positive first EID PCR test result') ? "selected='selected'" : ""; ?>> Confirmation of positive first EID PCR test result </option>
                                                <option value="Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months" <?php echo ($eidInfo['reason_for_pcr'] == 'Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months') ? "selected='selected'" : ""; ?>> Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months </option>
                                                <option value="Positive HIV rapid test result at 9 months or later"> Positive HIV rapid test result at 9 months or later </option>
                                                <option value="Other" <?php echo ($eidInfo['reason_for_pcr'] == 'Other') ? "selected='selected'" : ""; ?>> Other </option>
                                            </select>
                                        </td>
                                    </tr>


                                </table>

                                <br><br>
                                <table class="table" aria-hidden="true">
                                    <tr>
                                        <th colspan=4>
                                            <h4>Sample Information</h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important">Sample Collection Date <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control dateTime isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" value="<?php echo ($eidInfo['sample_collection_date']); ?>" />
                                        </td>
                                        <th style="width:15% !important;"></th>
                                        <td style="width:35% !important;"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Requesting Officer</th>
                                        <td>
                                            <input class="form-control" type="text" name="sampleRequestorName" id="sampleRequestorName" value="<?php echo $eidInfo['sample_requestor_name'] ?>" placeholder="Requesting Officer" />
                                        </td>
                                        <th scope="row">Sample Requestor Phone</th>
                                        <td>
                                            <input class="form-control" type="text" name="sampleRequestorPhone" id="sampleRequestorPhone" value="<?php echo $eidInfo['sample_requestor_phone'] ?>" placeholder="Requesting Officer Phone" />
                                        </td>
                                    </tr>

                                </table>


                            </div>
                        </div>
                    </div>
                </div>
                <form class="form-horizontal" method="post" name="editEIDRequestForm" id="editEIDRequestForm" autocomplete="off" action="eid-update-result-helper.php">
                    <div class="box box-primary">
                        <div class="box-body">
                            <div class="box-header with-border">
                                <h3 class="box-title">B. Reserved for Laboratory Use </h3>
                            </div>
                            <table class="table" aria-hidden="true" style="width:100%">
                                <tr>
                                    <th scope="row"><label for="">Sample Received Date <span class="mandatory">*</span> </label></th>
                                    <td>
                                        <input type="text" class="form-control dateTime isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter date de réception de léchantillon" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
                                    </td>
                                    <td><label for="labId">Lab Name <span class="mandatory">*</span></label> </td>
                                    <td>
                                        <select name="labId" id="labId" class="form-control isRequired" title="Please select Testing Lab name" style="width:100%;">
                                            <?= $general->generateSelectOptions($testingLabs, $eidInfo['lab_id'], '-- Select --'); ?>
                                        </select>
                                    </td>
                                <tr>
                                    <th scope="row">Is Sample Rejected? <span class="mandatory">*</span> </th>
                                    <td>
                                        <select class="form-control isRequired" name="isSampleRejected" id="isSampleRejected" onchange="sampleRejection();">
                                            <option value=''> -- Select -- </option>
                                            <option value="yes"> Yes </option>
                                            <option value="no"> No </option>
                                        </select>
                                    </td>

                                    <th scope="row">Reason for Rejection</th>
                                    <td>
                                        <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="Please select the reason for Sample Rejection">
                                            <option value=''> -- Select -- </option>
                                            <?php foreach ($rejectionTypeResult as $type) { ?>
                                                <optgroup label="<?php echo ($type['rejection_type']); ?>">
                                                    <?php
                                                    foreach ($rejectionResult as $reject) {
                                                        if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                            <option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($eidInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?= $reject['rejection_reason_name']; ?></option>
                                                    <?php }
                                                    } ?>
                                                </optgroup>
                                            <?php } ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="show-rejection" style="display:none;">
                                    <th scope="row">Rejection Date<span class="mandatory">*</span></th>
                                    <td><input value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td style="width:25%;"><label for="">Sample Test Date </label></td>
                                    <td style="width:25%;">
                                        <input type="text" class="form-control dateTime isRequired" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _("Please enter date"); ?>" title="Please enter the Sample Test Date" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
                                    </td>


                                    <th scope="row">Result</th>
                                    <td>
                                        <select class="result-focus form-control isRequired" name="result" id="result" title="Please choose the EID result">
                                            <option value=''> -- Select -- </option>
                                            <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                <option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Reviewed By</th>
                                    <td>
                                        <select name="reviewedBy" id="reviewedBy" class="select2 form-control isRequired" title="Please choose reviewed by" style="width: 100%;">
                                            <?= $general->generateSelectOptions($userInfo, $eidInfo['result_reviewed_by'], '-- Select --'); ?>
                                        </select>
                                    </td>
                                    <th scope="row">Reviewed on</td>
                                    <td><input type="text" value="<?= DateUtility::humanReadableDateFormat($eidInfo['result_reviewed_datetime']); ?>" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control isRequired" placeholder="Reviewed on" title="Please enter the Reviewed on" /></td>
                                </tr>
                                <tr class="change-reason">
                                    <th class="change-reason" style="display: none;">Reason for Changing <span class="mandatory">*</span></td>
                                    <td class="change-reason" style="display: none;"><textarea type="text" name="reasonForChanging" id="reasonForChanging" class="form-control date" placeholder="Enter the reason for changing" title="Please enter the reason for changing"></textarea></td>
                                    <th scope="row"></th>
                                    <td></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <input type="hidden" name="formId" id="formId" value="5" />
                    <input type="hidden" name="eidSampleId" id="eidSampleId" value="<?php echo $eidInfo['eid_id']; ?>" />
                    <input type="hidden" name="sampleCodeCol" id="sampleCodeCol" value="<?php echo $eidInfo['sample_code']; ?>" />
                    <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
                    <input type="hidden" name="oldStatus" id="oldStatus" value="<?php echo $eidInfo['result_status']; ?>" />
                    <input type="hidden" name="provinceId" id="provinceId" />
                    <input type="hidden" name="provinceCode" id="provinceCode" />
                    <input type="hidden" name="revised" id="revised" value="no" />
                    <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>

                    <input type="hidden" id="sampleCode" name="sampleCode" value="<?php echo $eidInfo['sample_code'] ?>" />
                    <a href="/eid/results/eid-manual-results.php" class="btn btn-default"> Cancel</a>
                </form>


                <!-- /.box-body -->
                <div class="box-footer">
                    <?php if (isset($arr['eid_sample_code'])) { ?>
                        <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                        <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                    <?php } ?>

                </div>
                <!-- /.box-footer -->

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
                    testType: 'eid'
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
        $("#provinceCode").val($("#province").find(":selected").attr("data-code"));
        $("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
        flag = deforayValidator.init({
            formId: 'editEIDRequestForm'
        });
        if (flag) {
            document.getElementById('editEIDRequestForm').submit();
        }
    }


    function sampleRejection() {
        if ($("#isSampleRejected").val() == 'yes') {
            $("#sampleRejectionReason").addClass('isRequired');
            $("#sampleRejectionReason").prop('disabled', false);
            $("#result").removeClass('isRequired');
            $("#sampleTestedDateTime").removeClass('isRequired');
            $("#result").prop('disabled', true);
            $("#sampleTestedDateTime").prop('disabled', true);
        } else {
            $("#sampleRejectionReason").removeClass('isRequired');
            $("#sampleRejectionReason").prop('disabled', true);
            $("#result").addClass('isRequired');
            $("#sampleTestedDateTime").addClass('isRequired');
            $("#result").prop('disabled', false);
            $("#sampleTestedDateTime").prop('disabled', false);
        }
    }

    $(document).ready(function() {


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
        getfacilityProvinceDetails($("#facilityId").val());
        $("#motherViralLoadCopiesPerMl").on("change keyup paste", function() {
            var motherVl = $("#motherViralLoadCopiesPerMl").val();
            //var motherVlText = $("#motherViralLoadText").val();
            if (motherVl != '') {
                $("#motherViralLoadText").val('');
            }
        });



        $('#sampleCollectionDate, #sampleReceivedDate').mask('99-aaa-9999 99:99');

        $('#sampleCollectionDate, #sampleReceivedDate').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });


    });
</script>