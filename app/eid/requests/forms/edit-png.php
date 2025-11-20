<?php

// imported in eid-edit-request.php based on country in global config

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\EidService;
use App\Utilities\DateUtility;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());


// Getting the list of Provinces, Districts and Facilities



/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();


$rKey = '';
$sKey = '';
$sFormat = '';
if ($general->isSTSInstance()) {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    if (!empty($eidInfo['remote_sample']) && $eidInfo['remote_sample'] == 'yes') {
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
$facility = $general->generateSelectOptions($healthFacilities, $eidInfo['facility_id'], '-- Select --');

$eidInfo['mother_treatment'] = isset($eidInfo['mother_treatment']) ? explode(",", (string) $eidInfo['mother_treatment']) : [];
//$eidInfo['child_treatment'] = isset($eidInfo['child_treatment']) ? explode(",", $eidInfo['child_treatment']) : [];

?>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em>
            <?php echo _translate("EARLY INFANT DIAGNOSIS (EID) LABORATORY REQUEST FORM"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Edit EID Request"); ?></li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">

        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
                    <?= _translate("indicates required fields"); ?> &nbsp;</div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->
                <form class="form-horizontal" method="post" name="editEIDRequestForm" id="editEIDRequestForm"
                    autocomplete="off" action="eid-edit-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Section 1: Clinic Information</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">To be filled by requesting
                                        Clinician/Nurse</h3>
                                </div>

                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <?php if ($general->isSTSInstance()) { ?>
                                            <td><label for="sampleCode">Sample ID </label></td>
                                            <td>
                                                <span id="sampleCodeInText"
                                                    style="width:100%;border-bottom:1px solid #333;"><?php echo $eidInfo[$sampleCode] ?></span>
                                                <input type="hidden" id="sampleCode" name="sampleCode"
                                                    value="<?php echo $eidInfo[$sampleCode] ?>" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode">Sample ID </label><span class="mandatory">*</span>
                                            </td>
                                            <td>
                                                <input type="text" readonly value="<?php echo $eidInfo[$sampleCode]; ?>"
                                                    class="form-control isRequired" id="sampleCode" name="sampleCode"
                                                    placeholder="Sample ID"
                                                    title="<?= _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>"
                                                    style="width:100%;" onchange="" />
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
                                            <select class="form-control isRequired" name="province" id="province"
                                                title="Please choose province" onchange="getfacilityDetails(this);"
                                                style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <td><label for="district">District </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="district" id="district"
                                                title="Please choose district" style="width:100%;"
                                                onchange="getfacilityDistrictwise(this);">
                                                <option value=""> -- Select -- </option>
                                            </select>
                                        </td>
                                        <td><label for="facilityId">Health Facility </label><span
                                                class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired " name="facilityId" id="facilityId"
                                                title="Please choose facility" style="width:100%;"
                                                onchange="getfacilityProvinceDetails(this);">
                                                <?php echo $facility; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><label for="supportPartner">Implementing Partner </label></td>
                                        <td>
                                            <!-- <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire d'appui" title="Please enter Partenaire d'appui" style="width:100%;"/> -->
                                            <select class="form-control" name="implementingPartner"
                                                id="implementingPartner"
                                                title="<?= _translate("Please choose implementing partner"); ?>"
                                                style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($implementingPartnerList as $implementingPartner) {
                                                    ?>
                                                    <option
                                                        value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>"
                                                        <?php echo ($eidInfo['implementing_partner'] == $implementingPartner['i_partner_id']) ? "selected='selected'" : ""; ?>>
                                                        <?= $implementingPartner['i_partner_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td><label for="fundingSource">Funding Partner</label></td>
                                        <td>
                                            <select class="form-control" name="fundingSource" id="fundingSource"
                                                title="Please choose source de financement" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($fundingSourceList as $fundingSource) {
                                                    ?>
                                                    <option
                                                        value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>"
                                                        <?php echo ($eidInfo['funding_source'] == $fundingSource['funding_source_id']) ? "selected='selected'" : ""; ?>>
                                                        <?= $fundingSource['funding_source_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <?php if ($general->isSTSInstance()) { ?>
                                            <!-- <tr> -->
                                            <td><label for="labId">Lab Name <span class="mandatory">*</span></label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control isRequired"
                                                    title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, $eidInfo['lab_id'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <!-- </tr> -->
                                        <?php } ?>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate('Requesting Clinician Name'); ?></th>
                                        <td> <input type="text" class="form-control" id="clinicianName"
                                                name="clinicianName" placeholder="Requesting Clinician Name"
                                                title="Please enter request clinician"
                                                value="<?php echo $eidInfo['clinician_name']; ?>" /></td>
                                        <th scope="row">Requesting Officer</th>
                                        <td>
                                            <input class="form-control" type="text" name="sampleRequestorName"
                                                id="sampleRequestorName"
                                                value="<?php echo $eidInfo['sample_requestor_name'] ?>"
                                                placeholder="Requesting Officer" />
                                        </td>
                                        <th scope="row">Sample Requestor Phone</th>
                                        <td>
                                            <input class="form-control" type="text" name="sampleRequestorPhone"
                                                id="sampleRequestorPhone"
                                                value="<?php echo $eidInfo['sample_requestor_phone'] ?>"
                                                placeholder="Requesting Officer Phone" />
                                        </td>
                                    </tr>
                                </table>
                                <br><br>
                                <div class="box-header with-border">
                                    <h3 class="box-title">Section 2: Mother/Guardian Information</h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th scope="row" style="width:15% !important"><label for="mothersName">Mother's
                                                Name </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="mothersName" name="mothersName"
                                                placeholder="Mother name" title="Please enter Mother Name"
                                                style="width:100%;" value="<?php echo $eidInfo['mother_name'] ?>" />
                                        </td>
                                        <th scope="row" style="width:15% !important"><label
                                                for="mothersSurname">Mother's Surname </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="mothersSurname"
                                                name="mothersSurname" placeholder="Mother Surname"
                                                title="Please enter Mother Surname" style="width:100%;"
                                                value="<?php echo $eidInfo['mother_surname'] ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Caretaker phone number</th>
                                        <td><input type="text" class="form-control phone-number"
                                                value="<?= htmlspecialchars((string) $eidInfo['caretaker_phone_number']); ?>"
                                                id="caretakerPhoneNumber" name="caretakerPhoneNumber"
                                                placeholder="Caretaker Phone Number" title="Caretaker Phone Number"
                                                style="width:100%;" onchange="" /></td>

                                        <th scope="row">Infant caretaker address</th>
                                        <td><textarea class="form-control " id="caretakerAddress"
                                                name="caretakerAddress" placeholder="Caretaker Address"
                                                title="Caretaker Address" style="width:100%;"
                                                onchange=""><?= htmlspecialchars((string) $eidInfo['caretaker_address']); ?></textarea>
                                        </td>

                                    </tr>
                                </table>
                                <br><br>

                                <div class="box-header with-border">
                                    <h3 class="box-title">Section 3: Infant Information</h3>
                                </div>


                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr class="encryptPIIContainer">
                                        <th scope="row" style="width:15% !important"><label
                                                for="encryptPII"><?= _translate('Encrypt PII'); ?> </label></th>
                                        <td>
                                            <select name="encryptPII" id="encryptPII" class="form-control"
                                                title="<?= _translate('Encrypt PII'); ?>">
                                                <option value=""><?= _translate('--Select--'); ?></option>
                                                <option value="no" <?php echo ($eidInfo['is_encrypted'] == "no") ? "selected='selected'" : ""; ?>><?= _translate('No'); ?></option>
                                                <option value="yes" <?php echo ($eidInfo['is_encrypted'] == "yes") ? "selected='selected'" : ""; ?>><?= _translate('Yes'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15% !important"><label for="childId">Exposed Infant
                                                Identification <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired patientId" id="childId"
                                                name="childId" placeholder="Exposed Infant Identification (Patient)"
                                                title="Please enter Exposed Infant Identification" style="width:100%;"
                                                onchange="" value="<?php echo $eidInfo['child_id']; ?>" />
                                        </td>
                                        <th scope="row" style="width:15% !important"><label for="childName">Infant name
                                            </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="childName" name="childName"
                                                placeholder="Infant name" title="Please enter Infant Name"
                                                style="width:100%;"
                                                value="<?= htmlspecialchars((string) $eidInfo['child_name']); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="childDob">Date of Birth </label></th>
                                        <td>
                                            <input type="text" class="form-control date" id="childDob" name="childDob"
                                                placeholder="Date of birth" title="Please enter Date of birth"
                                                style="width:100%;"
                                                value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['child_dob']) ?>"
                                                onchange="calculateAgeInMonths();" />
                                        </td>
                                        <th scope="row"><label for="childGender"><?= _translate("Sex"); ?> </label></th>
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
                                        <td><input type="number" max=9 maxlength="1"
                                                value="<?= htmlspecialchars((string) $eidInfo['child_age']); ?>"
                                                oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))"
                                                class="form-control " id="childAge" name="childAge" placeholder="Age"
                                                title="Age" style="width:100%;" onchange="$('#childDob').val('')" />
                                        </td>

                                    </tr>
                                </table>

                                <br><br>
                                <div class="box-header with-border">
                                    <h3 class="box-title"> Section 4: Reason For Test</h3>
                                </div>`

                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th scope="row">Provide Recent previous Lab Number</th>
                                        <td>
                                            <input type="text" class="form-control"
                                                value="<?php echo ($eidInfo['previous_sample_code']); ?>"
                                                id="previousSampleCode" name="previousSampleCode"
                                                placeholder="Recent previous Lab Number"
                                                title="Recent previous Lab Number" style="width:100%;" onchange="" />
                                        </td>
                                        <th scope="row">Clinical Assessment</th>
                                        <td>
                                            <select class="form-control " name="clinicalAssessment"
                                                id="clinicalAssessment">
                                                <option value=''> -- Select -- </option>
                                                <option value='symptomatic' <?php echo ($eidInfo['clinical_assessment'] == 'symptomatic') ? "selected='selected'" : ""; ?>> Symptomatic </option>
                                                <option value='non-symptomatic' <?php echo ($eidInfo['clinical_assessment'] == 'non-symptomatic') ? "selected='selected'" : ""; ?>> Non-Symptomatic </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Name of EID Personnel Collecting DBS</th>
                                        <td>
                                            <input type="text" class="form-control"
                                                value="<?php echo ($eidInfo['clinician_name']); ?>" id="clinicianName"
                                                name="clinicianName" placeholder="Name of EID Personnel Collecting DBS"
                                                title="Name of EID Personnel Collecting DBS" style="width:100%;"
                                                onchange="" />
                                        </td>
                                        <th scope="row" style="width:15% !important">Sample Collection Date <span
                                                class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control dateTime isRequired" type="text"
                                                name="sampleCollectionDate" id="sampleCollectionDate"
                                                placeholder="Sample Collection Date"
                                                value="<?php echo ($eidInfo['sample_collection_date']); ?>"
                                                onchange="checkCollectionDate(this.value);" />
                                            <span class="expiredCollectionDate" style="color:red; display:none;"></span>
                                        </td>

                                    </tr>

                                </table>

                                <br><br>

                                <div class="box-header with-border">
                                    <h3 class="box-title">Section 5: Mother PPTCT Information</h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">

                                    <tr>
                                        <th scope="row" style="width:15% !important">Mother's HIV Status:</th>
                                        <td style="width:35% !important">
                                            <select class="form-control" name="mothersHIVStatus" id="mothersHIVStatus">
                                                <option value=''> -- Select -- </option>
                                                <option value="positive" <?php echo ($eidInfo['mother_hiv_status'] == 'positive') ? "selected='selected'" : ""; ?>> Positive </option>
                                                <option value="negative" <?php echo ($eidInfo['mother_hiv_status'] == 'negative') ? "selected='selected'" : ""; ?>> Negative </option>
                                                <option value="unknown" <?php echo ($eidInfo['mother_hiv_status'] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                            </select>
                                        </td>
                                        <th scope="row">Mother ART Number</th>
                                        <td><input type="text" class="form-control "
                                                value="<?= htmlspecialchars((string) $eidInfo['mother_id']); ?>"
                                                id="mothersId" name="mothersId" placeholder="Mother ART Number"
                                                title="Mother ART Number" style="width:100%;" onchange="" /></td>

                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15% !important">ART given to the Mother during:
                                        </th>
                                        <td style="width:35% !important">
                                            <input type="checkbox" name="motherTreatment[]" value="No ART given" <?php echo in_array('No ART given', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?>> No ART given <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Pregnancy" <?php echo in_array('Pregnancy', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?>> Pregnancy <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Labour/Delivery"
                                                <?php echo in_array('Labour/Delivery', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?>> Labour/Delivery <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Postnatal" <?php echo in_array('Postnatal', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?>> Postnatal <br>
                                            <!-- <input type="checkbox" name="motherTreatment[]" value="Other" onclick="$('#motherTreatmentOther').prop('disabled', function(i, v) { return !v; });" /> Other (Please specify): <input class="form-control" style="max-width:200px;display:inline;" disabled="disabled" placeholder="Other" type="text" name="motherTreatmentOther" id="motherTreatmentOther" /> <br> -->
                                            <input type="checkbox" name="motherTreatment[]" value="Unknown" <?php echo in_array('Unknown', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?>> Unknown
                                        </td>
                                        <th scope="row" style="width:15% !important">Mode of Delivery:</th>
                                        <td style="width:35% !important">
                                            <select class="form-control" name="modeOfDelivery" id="modeOfDelivery"
                                                onchange="showOtherOption(this.value)">
                                                <option value=''> -- Select -- </option>
                                                <option value="Supervised Vaginal" <?php echo ($eidInfo['mode_of_delivery'] == 'Supervised Vaginal') ? "selected='selected'" : ""; ?>> Supervised Vaginal </option>
                                                <option value="Unsupervised Vaginal" <?php echo ($eidInfo['mode_of_delivery'] == 'Unsupervised Vaginal') ? "selected='selected'" : ""; ?>> Unsupervised Vaginal </option>
                                                <option value="Caesarean Section" <?php echo ($eidInfo['mode_of_delivery'] == 'Caesarean Section') ? "selected='selected'" : ""; ?>> Caesarean Section </option>
                                                <option value="Unknown" <?php echo ($eidInfo['mode_of_delivery'] == 'Unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                                <option value="Other" <?php echo ($eidInfo['mode_of_delivery'] == 'Other') ? "selected='selected'" : ""; ?>> Other </option>
                                            </select>
                                            <input type="text" class="form-control"
                                                value="<?php echo ($eidInfo['mode_of_delivery_other']); ?>"
                                                name="modeOfDeliveryOther" id="modeOfDeliveryOther"
                                                title="Enter Other mode of Delivery"
                                                placeholder="Enter Other mode of Delivery" style="display:none;" />
                                        </td>
                                    <tr>
                                    <tr>
                                        <th scope="row"> ART Status </th>
                                        <td>
                                            <select class="form-control" name="motherArtStatus" id="motherArtStatus">
                                                <option value="">--Select--</option>
                                                <option value="PLWHIV on ART" <?php echo ($eidInfo['mother_art_status'] == 'PLWHIV on ART') ? "selected='selected'" : ""; ?>>PLWHIV on ART </option>
                                                <option value="ART during pregnancy" <?php echo ($eidInfo['mother_art_status'] == 'ART during pregnancy') ? "selected='selected'" : ""; ?>> ART during pregnancy</option>
                                                <option value="Yes" <?php echo ($eidInfo['mother_art_status'] == 'Yes') ? "selected='selected'" : ""; ?>>Yes</option>
                                                <option value="No" <?php echo ($eidInfo['mother_art_status'] == 'No') ? "selected='selected'" : ""; ?>>No</option>
                                                <option value="Booked" <?php echo ($eidInfo['mother_art_status'] == 'Booked') ? "selected='selected'" : ""; ?>>Booked</option>
                                                <option value="Unbooked" <?php echo ($eidInfo['mother_art_status'] == 'Unbooked') ? "selected='selected'" : ""; ?>>Unbooked</option>
                                            </select>
                                        </td>
                                        <th scope="row"> ART Regimen </th>
                                        <td>
                                            <input type="text" class="form-control" name="motherRegimen"
                                                id="motherRegimen" title="Enter ART Regimen"
                                                placeholder="Enter ART Regimen"
                                                value="<?php echo ($eidInfo['mother_regimen']); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Date started ART</th>
                                        <td> <input type="text" class="form-control date"
                                                value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['started_art_date']); ?>"
                                                name="startedArtDate" id="startedArtDate" title="Enter ART Started Date"
                                                placeholder="Enter ART Started Date" />
                                        </td>
                                        <th scope="row">MTCT Risk</th>
                                        <td>
                                            <select class="form-control" name="motherMtctRisk" id="motherMtctRisk">
                                                <option value="">--Select--</option>
                                                <optgroup label="High Risk">
                                                    <option value="< 4weeks on ART prior to delivery" <?php echo ($eidInfo['mother_mtct_risk'] == '< 4weeks on ART prior to delivery') ? "selected='selected'" : ""; ?>>
                                                        < 4weeks on ART prior to delivery </option>
                                                    <option value="VL > 1000 4 weeks prior to delivery" <?php echo ($eidInfo['mother_mtct_risk'] == 'VL > 1000 4 weeks prior to delivery') ? "selected='selected'" : ""; ?>> VL > 1000 4 weeks
                                                        prior to delivery </option>
                                                </optgroup>
                                                <optgroup label="Low Risk">
                                                    <option value="> 4weeks on ART prior to delivery" <?php echo ($eidInfo['mother_mtct_risk'] == '> 4weeks on ART prior to delivery') ? "selected='selected'" : ""; ?>>
                                                        < 4weeks on ART prior to delivery </option>
                                                    <option value="VL < 1000 4 weeks prior to delivery" <?php echo ($eidInfo['mother_mtct_risk'] == 'VL < 1000 4 weeks prior to delivery') ? "selected='selected'" : ""; ?>> VL > 1000 4 weeks
                                                        prior to delivery </option>
                                                </optgroup>
                                            </select>
                                        </td>

                                    </tr>
                                </table>

                                <br><br>

                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th scope="row" colspan=4>
                                            <h4>Section 6: Infant PPTCT Information</h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="row">Infant stopped breastfeeding ?</th>
                                        <td>
                                            <select class="form-control" name="hasInfantStoppedBreastfeeding"
                                                id="hasInfantStoppedBreastfeeding">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                <option value="no" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                <option value="unknown" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
                                            </select>
                                        </td>

                                        <th scope="row">Age (months) breastfeeding stopped :</th>
                                        <td>
                                            <input type="number" class="form-control"
                                                value="<?php echo $eidInfo['age_breastfeeding_stopped_in_months'] ?>"
                                                style="max-width:200px;display:inline;"
                                                placeholder="Age (months) breastfeeding stopped" type="text"
                                                name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">ART Status</th>
                                        <td>
                                            <select class="form-control" name="infantArtStatus" id="infantArtStatus"
                                                onchange="showOtherArt(this.value);">
                                                <option value="">--Select--</option>

                                                <optgroup label="High Risk of MTCT">
                                                    <option value="AZT + NVP first 6 weeks of life" <?php echo ($eidInfo['infant_art_status'] == 'AZT + NVP first 6 weeks of life') ? "selected='selected'" : ""; ?>>AZT + NVP first 6 weeks
                                                        of life</option>
                                                    <option value="NVP only - additional 6 weeks (Total 12 weeks)" <?php echo ($eidInfo['infant_art_status'] == 'NVP only - additional 6 weeks (Total 12 weeks)') ? "selected='selected'" : ""; ?>>NVP
                                                        only - additional 6 weeks (Total 12 weeks)</option>
                                                </optgroup>
                                                <optgroup label="Low Risk">
                                                    <option value="NVP only for the first 6 weeks of life" <?php echo ($eidInfo['infant_art_status'] == 'NVP only for the first 6 weeks of life') ? "selected='selected'" : ""; ?>>NVP only for the
                                                        first 6 weeks of life</option>
                                                </optgroup>
                                                <option value="Other ART" <?php echo ($eidInfo['infant_art_status'] == 'Other ART') ? "selected='selected'" : ""; ?>>Other ART</option>
                                            </select>
                                            <input type="text" name="infantArtStatusOther" id="infantArtStatusOther"
                                                placeholder="Enter Other ART Regimen"
                                                value="<?php echo ($eidInfo['infant_art_status_other']); ?>"
                                                class="form-control" style="display:none;" />
                                        </td>
                                    </tr>
                                </table>
                                <br><br>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th scope="row" colspan=4>
                                            <h4>Section 7: Infant Testing History (Provide information for MOST recent
                                                test)</h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="row">Infant Rapid HIV Test Done</th>
                                        <td>
                                            <select class="form-control" name="rapidTestPerformed"
                                                id="rapidTestPerformed">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes" <?php echo ($eidInfo['rapid_test_performed'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                <option value="no" <?php echo ($eidInfo['rapid_test_performed'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                            </select>
                                        </td>

                                        <th scope="row">If yes, test date :</th>
                                        <td>
                                            <input class="form-control date" type="text" name="rapidtestDate"
                                                id="rapidtestDate" placeholder="if yes, test date"
                                                value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['rapid_test_date']); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Rapid Test Result</th>
                                        <td>
                                            <select class="form-control" name="rapidTestResult" id="rapidTestResult">
                                                <option value=''> -- Select -- </option>
                                                <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                    <option value="<?php echo $eidResultKey; ?>" <?php echo ($eidInfo['rapid_test_result'] == $eidResultKey) ? "selected='selected'" : ""; ?>> <?php echo $eidResultValue; ?>
                                                    </option>
                                                <?php } ?>

                                            </select>
                                        </td>

                                        <th scope="row">PCR test performed on child before :</th>
                                        <td>
                                            <select class="form-control" name="pcrTestPerformedBefore"
                                                id="pcrTestPerformedBefore">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes" <?php echo ($eidInfo['pcr_test_performed_before'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                <option value="no" <?php echo ($eidInfo['pcr_test_performed_before'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Previous PCR test date :</th>
                                        <td>
                                            <input class="form-control date" type="text" name="previousPCRTestDate"
                                                id="previousPCRTestDate" placeholder="if yes, test date"
                                                value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['last_pcr_date']); ?>" />
                                        </td>

                                        <th scope="row">Reason for 2nd PCR :</th>
                                        <td>
                                            <select class="form-control" name="pcrTestReason" id="pcrTestReason">
                                                <option value=''> -- Select -- </option>
                                                <option value="Confirmation of positive first EID PCR test result" <?php echo ($eidInfo['reason_for_pcr'] == 'Confirmation of positive first EID PCR test result') ? "selected='selected'" : ""; ?>> Confirmation
                                                    of positive first EID PCR test result </option>
                                                <option
                                                    value="Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months"
                                                    <?php echo ($eidInfo['reason_for_pcr'] == 'Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months') ? "selected='selected'" : ""; ?>> Repeat EID PCR test 6 weeks after
                                                    stopping breastfeeding for children < 9 months </option>
                                                <option value="Positive HIV rapid test result at 9 months or later"
                                                    <?php echo ($eidInfo['reason_for_pcr'] == 'Positive HIV rapid test result at 9 months or later') ? "selected='selected'" : ""; ?>>
                                                    Positive HIV rapid test result at 9 months or later </option>
                                                <option value="1st Test Positive" <?php echo ($eidInfo['reason_for_pcr'] == '1st Test Positive') ? "selected='selected'" : ""; ?>> 1st Test Positive </option>
                                                <option value="DBS Invalid" <?php echo ($eidInfo['reason_for_pcr'] == 'DBS Invalid') ? "selected='selected'" : ""; ?>> DBS Invalid </option>
                                                <option value="Indeterminate" <?php echo ($eidInfo['reason_for_pcr'] == 'Indeterminate') ? "selected='selected'" : ""; ?>> Indeterminate </option>
                                                <option value="Infant Still breastfeeding" <?php echo ($eidInfo['reason_for_pcr'] == 'Infant Still breastfeeding') ? "selected='selected'" : ""; ?>> Infant Still breastfeeding </option>
                                                <option value="Infact <2 months post cessation of breastfeeding" <?php echo ($eidInfo['reason_for_pcr'] == 'Infact <2 months post cessation of breastfeeding') ? "selected='selected'" : ""; ?>> Infact <2
                                                        months post cessation of breastfeeding </option>
                                                <option value="Infants less than 6 weeks" <?php echo ($eidInfo['reason_for_pcr'] == 'Infants less than 6 weeks') ? "selected='selected'" : ""; ?>> Infants less than 6 weeks </option>
                                                <option value="Inadequate feeding history" <?php echo ($eidInfo['reason_for_pcr'] == 'Inadequate feeding history') ? "selected='selected'" : ""; ?>> Inadequate feeding history </option>
                                                <option value="Other" <?php echo ($eidInfo['reason_for_pcr'] == 'Other') ? "selected='selected'" : ""; ?>> Other </option>
                                            </select>
                                        </td>
                                    </tr>

                                </table>

                            </div>
                        </div>
                        <?php if (!$general->isSTSInstance()) { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title"> Reserved for Laboratory Use </h3>
                                    </div>
                                    <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                        <tr>
                                            <td><label for="" class="labels">Testing Platform </label></td>
                                            <td><select class="form-control result-optional" name="eidPlatform"
                                                    id="eidPlatform" title="Please select the testing platform">
                                                    <?= $general->generateSelectOptions($testPlatformList, $eidInfo['eid_test_platform'] . '##' . $eidInfo['instrument_id'], '-- Select --'); ?>
                                                </select>
                                            </td>

                                            <th scope="row"><label for="">Sample Received Date </label></th>
                                            <td>
                                                <input type="text" class="form-control dateTime" id="sampleReceivedDate"
                                                    name="sampleReceivedDate"
                                                    value="<?php echo $eidInfo['sample_received_at_lab_datetime'] ?>"
                                                    placeholder="<?= _translate("Please enter date"); ?>"
                                                    title="Please enter date de réception de l\'échantillon" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
                                            </td>

                                        <tr>
                                            <td><label for="labId">Lab Name </label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control"
                                                    title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, $eidInfo['lab_id'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row">Is Sample Rejected?</th>
                                            <td>
                                                <select class="form-control" name="isSampleRejected" id="isSampleRejected">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="yes" <?php echo ($eidInfo['is_sample_rejected'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                    <option value="no" <?php echo ($eidInfo['is_sample_rejected'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" class="rejected" style="display: none;">Reason for Rejection
                                            </th>
                                            <td class="rejected" style="display: none;">
                                                <select class="form-control" name="sampleRejectionReason"
                                                    id="sampleRejectionReason">
                                                    <option value=''> -- Select -- </option>
                                                    <?php foreach ($rejectionTypeResult as $type) { ?>
                                                        <optgroup
                                                            label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
                                                            <?php
                                                            foreach ($rejectionResult as $reject) {
                                                                if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                                    <option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($eidInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>>
                                                                        <?= $reject['rejection_reason_name']; ?></option>
                                                                <?php }
                                                            } ?>
                                                        </optgroup>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="show-rejection rejected" style="display:none;">
                                            <th>Rejection Date<span class="mandatory">*</span></th>
                                            <td><input
                                                    value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['rejection_on']); ?>"
                                                    class="form-control date rejection-date" type="text"
                                                    name="rejectionDate" id="rejectionDate"
                                                    placeholder="Select Rejection Date" /></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td style="width:25%;"><label for="">Sample Test Date </label></td>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control dateTime" id="sampleTestedDateTime"
                                                    name="sampleTestedDateTime"
                                                    value="<?php echo $eidInfo['sample_tested_datetime'] ?>"
                                                    placeholder="<?= _translate("Please enter date"); ?>"
                                                    title="Test effectué le" <?php echo $labFieldDisabled; ?> onchange=""
                                                    style="width:100%;" />
                                            </td>


                                            <th scope="row">Result</th>
                                            <td>
                                                <select class="form-control result-focus" name="result" id="result">
                                                    <option value=''> -- Select -- </option>
                                                    <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                        <option value="<?php echo $eidResultKey; ?>" <?php echo ($eidResultKey == $eidInfo['result']) ? "selected='selected'" : ""; ?>> <?php echo $eidResultValue; ?> </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                        </tr>
                                    </table>


                                    <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">

                                        <tr>
                                            <th scope="row" colspan=4>
                                                <h5> First Test Information</h5>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row" style="width:25%;"><label for=""> Test Date </label></th>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control date"
                                                    value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['test_1_date']); ?>"
                                                    id="test1Date" name="test1Date"
                                                    placeholder="<?= _translate("Please enter test date"); ?>"
                                                    title="Test Date" onchange="" style="width:100%;" />
                                            </td>


                                            <th scope="row">Batch</th>
                                            <td>
                                                <input type="text" class="form-control" id="test1Batch" name="test1Batch"
                                                    value="<?php echo ($eidInfo['test_1_batch']); ?>"
                                                    placeholder="<?= _translate("Please enter Batch"); ?>" title="Batch"
                                                    onchange="" style="width:100%;" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" style="width:25%;"><label for=""> Assay </label></th>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control" id="test1Assay" name="test1Assay"
                                                    value="<?php echo ($eidInfo['test_1_assay']); ?>"
                                                    placeholder="<?= _translate("Please enter Assay"); ?>" title="Assay"
                                                    onchange="" style="width:100%;" />
                                            </td>


                                            <th scope="row">CT/QS value</th>
                                            <td>
                                                <input type="text" class="form-control" id="test1CtQs" name="test1CtQs"
                                                    value="<?php echo ($eidInfo['test_1_ct_qs']); ?>"
                                                    placeholder="<?= _translate("Please enter CT/QS value"); ?>"
                                                    title="CT/QS value" onchange="" style="width:100%;" />
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row" style="width:25%;"><label for=""> Result </label></th>
                                            <td style="width:25%;">
                                                <select class="form-control" name="test1Result" id="test1Result">
                                                    <option value=''> -- Select -- </option>
                                                    <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                        <option value="<?php echo $eidResultKey; ?>" <?php echo ($eidInfo['test_1_result'] == $eidResultKey) ? "selected='selected'" : ""; ?>> <?php echo $eidResultValue; ?> </option>
                                                    <?php } ?>
                                                </select>
                                            </td>

                                            <th scope="row">Repeat Test?</th>
                                            <td>
                                                <select class="form-control" name="test1Repeated" id="test1Repeated"
                                                    onchange="showRepeatedReason(this.value)">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="yes" <?php echo ($eidInfo['test_1_repeated'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                    <option value="no" <?php echo ($eidInfo['test_1_repeated'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                </select>
                                                <span class="test1RepeatReason" style="display:none;">
                                                    Why? <input type="text"
                                                        value="<?php echo ($eidInfo['test_1_repeat_reason']); ?>"
                                                        class="form-control" id="test1RepeatReason" name="test1RepeatReason"
                                                        placeholder="<?= _translate("Reason for Repeating Test"); ?>"
                                                        title="Reason for Repeating Test" onchange=""
                                                        style="width:100%;" /></span>
                                            </td>
                                        </tr>


                                        <tr>
                                            <th scope="row" colspan=4>
                                                <h5> Second Test Information</h5>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row" style="width:25%;"><label for=""> Test Date </label></th>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control date"
                                                    value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['test_2_date']); ?>"
                                                    id="test2Date" name="test2Date"
                                                    placeholder="<?= _translate("Please enter test date"); ?>"
                                                    title="Test Date" onchange="" style="width:100%;" />
                                            </td>


                                            <th scope="row">Batch</th>
                                            <td>
                                                <input type="text" class="form-control" id="test2Batch" name="test2Batch"
                                                    value="<?php echo ($eidInfo['test_2_batch']); ?>"
                                                    placeholder="<?= _translate("Please enter Batch"); ?>" title="Batch"
                                                    onchange="" style="width:100%;" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" style="width:25%;"><label for=""> Assay </label></th>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control" id="test2Assay" name="test2Assay"
                                                    value="<?php echo ($eidInfo['test_2_assay']); ?>"
                                                    placeholder="<?= _translate("Please enter assay"); ?>" title="Assay"
                                                    onchange="" style="width:100%;" />
                                            </td>


                                            <th scope="row">CT/QS value</th>
                                            <td>
                                                <input type="text" class="form-control" id="test2CtQs" name="test2CtQs"
                                                    value="<?php echo ($eidInfo['test_2_ct_qs']); ?>"
                                                    placeholder="<?= _translate("Please enter CT/QS value"); ?>"
                                                    title="CT/QS value" onchange="" style="width:100%;" />
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row" style="width:25%;"><label for=""> Result </label></th>
                                            <td style="width:25%;">
                                                <select class="form-control" name="test2Result" id="test2Result">
                                                    <option value=''> -- Select -- </option>
                                                    <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                        <option value="<?php echo $eidResultKey; ?>" <?php echo ($eidInfo['test_2_result'] == $eidResultKey) ? "selected='selected'" : ""; ?>> <?php echo $eidResultValue; ?> </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                        </tr>


                                    </table>

                                    <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">


                                        <tr>

                                            <th scope="row">Reviewed On</th>
                                            <td><input type="text"
                                                    value="<?php echo $eidInfo['result_reviewed_datetime']; ?>"
                                                    name="reviewedOn" id="reviewedOn"
                                                    class="dateTime disabled-field form-control" placeholder="Reviewed on"
                                                    title="Please enter the Reviewed on" /></td>
                                            <th scope="row">Reviewed By</th>
                                            <td>
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control"
                                                    title="Please choose reviewed by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($userInfo, $eidInfo['result_reviewed_by'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Approved On</th>
                                            <td><input type="text"
                                                    value="<?php echo $eidInfo['result_approved_datetime']; ?>"
                                                    name="approvedOnDateTime" id="approvedOnDateTime"
                                                    class="dateTime disabled-field form-control" placeholder="Approved on"
                                                    title="Please enter the approved on date" /></td>
                                            <th scope="row">Approved By</th>
                                            <td>
                                                <select name="approvedBy" id="approvedBy" class="select2 form-control"
                                                    title="Please choose Approved by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($userInfo, $eidInfo['result_approved_by'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="change-reason">
                                            <th scope="row" class="change-reason" style="display: none;">Reason for Changing
                                                <span class="mandatory">*</span></th>
                                            <td class="change-reason" style="display: none;"><textarea
                                                    name="reasonForChanging" id="reasonForChanging" class="form-control"
                                                    placeholder="Enter the reason for changing"
                                                    title="Please enter the reason for changing"></textarea></td>
                                            <th scope="row"></th>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Second DBS Requested</th>
                                            <td>
                                                <select class="form-control" name="secondDBSRequested"
                                                    onchange="showDBSRequestedReason(this.value);" id="secondDBSRequested">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="yes" <?php echo ($eidInfo['second_dbs_requested'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
                                                    <option value="no" <?php echo ($eidInfo['second_dbs_requested'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
                                                </select>
                                            </td>
                                            <th scope="row" class="DBSRequestedReason" style="display: none;">If Yes, Why?
                                            </th>
                                            <td class="DBSRequestedReason" style="display: none;">
                                                <select class="form-control" name="secondDBSRequestedReason"
                                                    id="secondDBSRequestedReason">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="1st Test Positive" <?php echo ($eidInfo['second_dbs_requested_reason'] == '1st Test Positive') ? "selected='selected'" : ""; ?>> 1st Test Positive </option>
                                                    <option value="DBS Invalid" <?php echo ($eidInfo['second_dbs_requested_reason'] == 'DBS Invalid') ? "selected='selected'" : ""; ?>> DBS Invalid </option>
                                                    <option value="Indeterminate" <?php echo ($eidInfo['second_dbs_requested_reason'] == 'Indeterminate') ? "selected='selected'" : ""; ?>> Indeterminate </option>
                                                    <option value="Infant < 2 months post-exposure to delivery" <?php echo ($eidInfo['second_dbs_requested_reason'] == 'Infant < 2 months post-exposure to delivery') ? "selected='selected'" : ""; ?>> Infant
                                                        < 2 months post-exposure to delivery </option>
                                                    <option value="Infant still breastfeeding" <?php echo ($eidInfo['second_dbs_requested_reason'] == 'Infant still breastfeeding') ? "selected='selected'" : ""; ?>> Infant still
                                                        breastfeeding </option>
                                                    <option value="Infant < 2 months post breastfeeding" <?php echo ($eidInfo['second_dbs_requested_reason'] == 'Infant < 2 months post breastfeeding') ? "selected='selected'" : ""; ?>> Infant < 2 months
                                                            post breastfeeding </option>
                                                    <option value="Infant less than 6 weeks" <?php echo ($eidInfo['second_dbs_requested_reason'] == 'Infant less than 6 weeks') ? "selected='selected'" : ""; ?>> Infant less than 6 weeks
                                                    </option>
                                                    <option value="Inadequate feeding history" <?php echo ($eidInfo['second_dbs_requested_reason'] == 'Inadequate feeding history') ? "selected='selected'" : ""; ?>> Inadequate feeding
                                                        history </option>
                                                </select>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        <?php } ?>

                    </div>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <?php if (isset($arr['eid_sample_code'])) { ?>
                            <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat"
                                value="<?php echo $sFormat; ?>" />
                            <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                        <?php } ?>
                        <a class="btn btn-primary" href="javascript:void(0);"
                            onclick="validateNow();return false;">Save</a>
                        <input type="hidden" name="revised" id="revised" value="no" />
                        <input type="hidden" name="formId" id="formId" value="5" />
                        <input type="hidden" name="eidSampleId" id="eidSampleId"
                            value="<?= htmlspecialchars((string) $eidInfo['eid_id']); ?>" />
                        <input type="hidden" name="sampleCodeCol" id="sampleCodeCol"
                            value="<?= htmlspecialchars((string) $eidInfo['sample_code']); ?>" />
                        <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle"
                            value="<?php echo $arr['sample_code']; ?>" />
                        <input type="hidden" name="oldStatus" id="oldStatus"
                            value="<?= htmlspecialchars((string) $eidInfo['result_status']); ?>" />
                        <input type="hidden" name="provinceId" id="provinceId" />
                        <input type="hidden" name="provinceCode" id="provinceCode" />
                        <a href="/eid/requests/eid-requests.php" class="btn btn-default"> Cancel</a>
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
                testType: 'eid'
            },
                function (data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#facilityId").html(details[0]);
                        $("#district").html(details[1]);
                        //$("#clinicianName").val(details[2]);
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
                testType: 'eid'
            },
                function (data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#facilityId").html(details[0]);
                    }
                });
        } else {
            $("#facilityId").html("<option value=''> -- Sélectionner -- </option>");
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
                testType: 'eid'
            },
                function (data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#province").html(details[0]);
                        $("#district").html(details[1]);
                        //  $("#clinicianName").val(details[2]);
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

    function updateMotherViralLoad() {
        //var motherVl = $("#motherViralLoadCopiesPerMl").val();
        var motherVlText = $("#motherViralLoadText").val();
        if (motherVlText != '') {
            $("#motherViralLoadCopiesPerMl").val('');
        }
    }

    function showDBSRequestedReason(requested) {
        if (requested == "yes")
            $(".DBSRequestedReason").show();
        else
            $(".DBSRequestedReason").hide();
    }

    function showOtherOption(modeOfdelivery) {
        if (modeOfdelivery == "Other")
            $("#modeOfDeliveryOther").show();
        else
            $("#modeOfDeliveryOther").hide();
    }

    function showOtherArt(infantArt) {
        if (infantArt == "Other ART") {
            $("#infantArtStatusOther").show();
        } else {
            $("#infantArtStatusOther").val("");
            $("#infantArtStatusOther").hide();
        }
    }

    function showRepeatedReason(repeated) {
        if (repeated == "yes")
            $(".test1RepeatReason").show();
        else
            $(".test1RepeatReason").hide();

    }

    $(document).ready(function () {
        checkCollectionDate('<?php echo $eidInfo['sample_collection_date']; ?>');

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
        getfacilityProvinceDetails($("#facilityId").val());
        $("#motherViralLoadCopiesPerMl").on("change keyup paste", function () {
            var motherVl = $("#motherViralLoadCopiesPerMl").val();
            //var motherVlText = $("#motherViralLoadText").val();
            if (motherVl != '') {
                $("#motherViralLoadText").val('');
            }
        });



        $('#sampleCollectionDate, #sampleReceivedDate').mask('<?= $_SESSION['jsDateFormatMask'] ?? '99-aaa-9999'; ?> 99:99');

        $('#sampleCollectionDate, #sampleReceivedDate').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
            timeFormat: "HH:mm",
            onChangeMonthYear: function (year, month, widget) {
                setTimeout(function () {
                    $('.ui-datepicker-calendar').show();
                });
            },
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function () {
            $('.ui-datepicker-calendar').show();
        });
        showDBSRequestedReason($("#secondDBSRequested").val());
        showOtherOption($("#modeOfDelivery").val());
        showOtherArt($("#infantArtStatus").val());
    });
</script>