<?php

// imported in /eid/results/eid-update-result.php based on country in global config

use App\Registries\ContainerRegistry;
use App\Services\EidService;
use App\Utilities\DateUtility;


$eidObj = ContainerRegistry::get(EidService::class);


$eidResults = $eidObj->getEidResults();

$specimenTypeResult = $eidObj->getEidSampleTypes();
/* To get testing platform names */
$testPlatformResult = $general->getTestingPlatforms('eid');
foreach ($testPlatformResult as $row) {
    $testPlatformList[$row['machine_name']] = $row['machine_name'];
}
// Getting the list of Provinces, Districts and Facilities

$rKey = '';

if ($_SESSION['accessType'] == 'collection-site') {
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

if (isset($eidInfo['facility_id']) && $eidInfo['facility_id'] > 0) {
    $facilityQuery = "SELECT * FROM facility_details WHERE facility_id= ? AND status='active'";
    $facilityResult = $db->rawQuery($facilityQuery, array($eidInfo['facility_id']));
}
?>


<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _translate("EARLY INFANT DIAGNOSIS (EID) LABORATORY REQUEST FORM"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Edit EID Request"); ?></li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">

        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate('indicates required fields'); ?> &nbsp;</div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->

                <div class="box-body">
                    <div class="box box-default">
                        <div class="box-body disabledForm">
                            <div class="box-header with-border">
                                <h3 class="box-title"><?= _translate('HEALTH FACILITY INFORMATION'); ?></h3>
                            </div>
                            <div class="box-header with-border">
                                <h3 class="box-title" style="font-size:1em;"><?= _translate('To be filled by requesting Clinician/Nurse'); ?></h3>
                            </div>
                            <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                <tr>
                                    <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
                                        <td class="labels"><label for="sampleCode"><?= _translate('Sample ID'); ?> </label></td>
                                        <td>
                                            <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?= htmlspecialchars((string) $eidInfo['sample_code']); ?></span>
                                            <input type="hidden" id="sampleCode" name="sampleCode" value="<?= htmlspecialchars((string) $eidInfo['sample_code']); ?>" />
                                        </td>
                                    <?php } else { ?>
                                        <td class="labels"><label for="sampleCode"><?= _translate('Sample ID'); ?> </label><span class="mandatory">*</span></td>
                                        <td>
                                            <input type="text" readonly value="<?= htmlspecialchars((string) $eidInfo['sample_code']); ?>" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="<?= _translate('Sample ID'); ?> title=" <?= _translate('Please enter sample id'); ?>" style="width:100%;" onchange="" />
                                        </td>
                                    <?php } ?>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td style="width:25%"><label for="province"><?= _translate('Region'); ?> </label><span class="mandatory">*</span></br>
                                        <select class="form-control isRequired" name="province" id="province" title="Please choose province" onchange="getfacilityDetails(this);" style="width:100%;">
                                            <?php echo $province; ?>
                                        </select>
                                    </td>
                                    <td style="width:25%"><label for="district"><?= _translate('District'); ?> </label><span class="mandatory">*</span><br>
                                        <select class="form-control isRequired" name="district" id="district" title="Please choose district" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                                            <option value=""> <?= _translate('-- Select --'); ?> </option>
                                        </select>
                                    </td>
                                    <td style="width:25%"><label for="facilityId"><?= _translate('Facility'); ?> </label><span class="mandatory">*</span><br>
                                        <select class="form-control isRequired " name="facilityId" id="facilityId" title="Please choose facility" style="width:100%;" onchange="getfacilityProvinceDetails(this),fillFacilityDetails();">
                                            <option value=""> <?= _translate('-- Select --'); ?> </option>
                                            <?php //echo $facility;
                                            foreach ($healthFacilitiesAllColumns as $hFacility) {
                                            ?>
                                                <option value="<?php echo $hFacility['facility_id']; ?>" <?php echo ($eidInfo['facility_id'] == $hFacility['facility_id']) ? "selected='selected'" : ""; ?> data-code="<?php echo $hFacility['facility_code']; ?>"><?php echo $hFacility['facility_name']; ?></option>
                                            <?php
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td style="width:25%">
                                        <label for="facilityCode"><?= _translate('Facility Code'); ?> </label><br>
                                        <input type="text" class="form-control" style="width:100%;" name="facilityCode" id="facilityCode" placeholder="<?= _translate('Clinic/Health Center Code'); ?>" title="<?= _translate('Please enter clinic/health center code'); ?>" value="<?php echo $facilityResult[0]['facility_code']; ?>">
                                    </td>
                                </tr>

                                <tr>
                                    <td style="width:25%">
                                        <label for="fundingSource"><?= _translate('Project Name'); ?> </label><br>
                                        <select class="form-control" name="fundingSource" id="fundingSource" title="Please choose implementing partner" style="width:100%;">
                                            <option value=""> <?= _translate('-- Select --'); ?> </option>
                                            <?php
                                            foreach ($fundingSourceList as $fundingSource) {
                                            ?>
                                                <option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $eidInfo['funding_source']) ? 'selected="selected"' : ''; ?>><?= $fundingSource['funding_source_name']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                    <td style="width:25%">
                                        <label for="implementingPartner"><?= _translate('Implementing Partner'); ?> </label><br>

                                        <select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose implementing partner" style="width:100%;">
                                            <option value=""> <?= _translate('-- Select --'); ?> </option>
                                            <?php
                                            foreach ($implementingPartnerList as $implementingPartner) {
                                            ?>
                                                <option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>" <?php echo ($implementingPartner['i_partner_id'] == $eidInfo['implementing_partner']) ? 'selected="selected"' : ''; ?>><?= $implementingPartner['i_partner_name']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                    <td style="width:25%">
                                        <label for="labId"><?= _translate('Lab Name'); ?> <span class="mandatory">*</span></label>
                                        <select name="labId" id="labId" class="form-control isRequired" title="<?= _translate('Please select Testing Lab name'); ?>" style="width:100%;">
                                            <?= $general->generateSelectOptions($testingLabs, $eidInfo['lab_id'], '-- Select --'); ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            <br><br>
                            <div class="box-header with-border">
                                <h3 class="box-title"><?= _translate("CHILD'S IDENTIFICATION"); ?></h3>
                            </div>
                            <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">

                                <tr>
                                    <th scope="row" class="labels" style="width:15% !important"><label for="childId"><?= _translate('CRVS file name'); ?> <span class="mandatory">*</span> </label></th>
                                    <td style="width:35% !important">
                                        <input type="text" class="form-control isRequired" id="childId" name="childId" placeholder="<?= _translate('Exposed Infant Identification (Patient)'); ?>" title="<?= _translate('Please enter Exposed Infant Identification'); ?>" style="width:100%;" value="<?php echo $eidInfo['child_id']; ?>" onchange="" />
                                    </td>
                                    <th scope="row" class="labels" style="width:15% !important"><label for="childName">Infant name </label></th>
                                    <td style="width:35% !important">
                                        <input type="text" class="form-control " id="childName" name="childName" placeholder="Infant name" title="Please enter Infant Name" style="width:100%;" value="<?= htmlspecialchars((string) $eidInfo['child_name']); ?>" onchange="" />
                                    </td>
                                </tr>
                                <tr>
                                    <th class="labels" scope="row"><label for="childDob"><?= _translate('Date of Birth'); ?> </label></th>
                                    <td>
                                        <input type="text" class="form-control date" id="childDob" name="childDob" placeholder="<?= _translate('Date of birth'); ?>" title="<?= _translate('Please enter Date of birth'); ?>" style="width:100%;" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['child_dob']) ?>" onchange="" />
                                    </td>
                                    <th class="labels" scope="row"><?= _translate('Infant Age (months)'); ?></th>
                                    <td><input type="number" max=9 maxlength="1" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="childAge" name="childAge" placeholder="Age" title="Age" style="width:100%;" onchange="" value="<?= htmlspecialchars((string) $eidInfo['child_age']); ?>" /></td>


                                </tr>
                                <tr>
                                    <th class="labels" scope="row"><label for="childGender"><?= _translate('Sex'); ?> </label></th>
                                    <td>
                                        <select class="form-control " name="childGender" id="childGender">
                                            <option value=''> <?= _translate('-- Select --'); ?> </option>
                                            <option value='male' <?php echo ($eidInfo['child_gender'] == 'male') ? "selected='selected'" : ""; ?>> <?= _translate('Male'); ?> </option>
                                            <option value='female' <?php echo ($eidInfo['child_gender'] == 'female') ? "selected='selected'" : ""; ?>> <?= _translate('Female'); ?> </option>
                                            <option value='unreported' <?php echo ($eidInfo['child_gender'] == 'unreported') ? "selected='selected'" : ""; ?>> <?= _translate('Unreported'); ?> </option>
                                        </select>
                                    </td>
                                    <th scope="row"><?= _translate('Universal Health Coverage'); ?></th>
                                    <td><input type="text" name="healthInsuranceCode" id="healthInsuranceCode" class="form-control" value="<?= $eidInfo['health_insurance_code']; ?>" placeholder="<?= _translate('Enter Universal Health Coverage'); ?>" title="<?= _translate('Enter Universal Health Coverage'); ?>" maxlength="32" /></td>

                                </tr>
                                <tr>
                                    <th scope="row"><?= ('Weight of the day'); ?></th>
                                    <td><input type="text" class="form-control forceNumeric" id="infantWeight" name="infantWeight" placeholder="<?= _translate('Infant weight of the day in Kg'); ?>" title="<?= _translate('Infant weight of the day'); ?>" style="width:100%;" value="<?= $eidInfo['child_weight']; ?>" /></td>

                                    <th class="labels" scope="row"><?= _translate('Caretaker phone number'); ?></th>
                                    <td><input type="text" class="form-control phone-number" id="caretakerPhoneNumber" name="caretakerPhoneNumber" placeholder="<?= _translate('Caretaker Phone Number'); ?>" title="<?= _translate('Caretaker Phone Number'); ?>" style="width:100%;" value="<?= htmlspecialchars((string) $eidInfo['caretaker_phone_number']); ?>" onchange="" /></td>

                                </tr>
                                <tr>
                                    <th class="labels" scope="row"><?= _translate('Infant caretaker address'); ?></th>
                                    <td><textarea class="form-control " id="caretakerAddress" name="caretakerAddress" placeholder="<?= _translate('Caretaker Address'); ?>" title="<?= _translate('Caretaker Address'); ?>" style="width:100%;" onchange=""><?= htmlspecialchars((string) $eidInfo['caretaker_address']); ?></textarea></td>

                                    <th scope="row"><?= _translate('Prophylactic ARV given to child'); ?><span class="mandatory">*</span></th>
                                    <td>
                                        <select class="form-control isRequired" name="childProphylacticArv" id="childProphylacticArv" title="<?= _translate('Prophylactic ARV given to child'); ?>" onchange="showOtherARV();">
                                            <option value=''> <?= _translate('-- Select --'); ?> </option>
                                            <option value='nothing' <?php echo ($eidInfo['child_prophylactic_arv'] == 'nothing') ? "selected='selected'" : ""; ?>> <?= _translate('Nothing'); ?> </option>
                                            <option value='nvp' <?php echo ($eidInfo['child_prophylactic_arv'] == 'nvp') ? "selected='selected'" : ""; ?>> <?= _translate('NVP'); ?> </option>
                                            <option value='azt' <?php echo ($eidInfo['child_prophylactic_arv'] == 'azt') ? "selected='selected'" : ""; ?>> <?= _translate('AZT'); ?> </option>
                                            <option value='other' <?php echo ($eidInfo['child_prophylactic_arv'] == 'other') ? "selected='selected'" : ""; ?>> <?= _translate('Other'); ?> </option>
                                        </select>
                                        <input type="text" name="childProphylacticArvOther" id="childProphylacticArvOther" value="<?php echo ($eidInfo['child_prophylactic_arv_other']); ?>" class="form-control" placeholder="<?= _translate('Please specify other prophylactic ARV given'); ?>" title="<?= _translate('Please specify other prophylactic ARV given'); ?>" style="display:none;" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?= _translate('Date of Initiation'); ?></th>
                                    <td>
                                        <input type="text" class="form-control date" name="childTreatmentInitiationDate" id="childTreatmentInitiationDate" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['child_treatment_initiation_date']); ?>" placeholder="<?= _translate('Enter date of initiation'); ?>" />
                                    </td>

                                </tr>
                            </table>

                            <br><br>
                            <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                <tr>
                                    <th scope="row" colspan=4>
                                        <h4><?= _translate("MOTHER'S INFORMATION"); ?></h4>
                                    </th>
                                </tr>
                                <tr>
                                    <th scope="row" style="width:15% !important"><label for="mothersName"><?= _translate('Mother name'); ?> </label></th>
                                    <td style="width:35% !important">
                                        <input type="text" class="form-control " id="mothersName" name="mothersName" placeholder="<?= _translate('Mother name'); ?>" title="<?= _translate('Please enter Infant Name'); ?>" style="width:100%;" onchange="" value="<?= $eidInfo['mother_name'] ?>" />
                                    </td>
                                    <th scope="row"><label for="dob"><?= _translate('Date of Birth'); ?> <span class="mandatory">*</span> </label></th>
                                    <td>
                                        <input type="text" class="form-control isRequired" id="mothersDob" name="mothersDob" placeholder="<?= _translate('Date of birth'); ?>" title="<?= _translate('Please enter Date of birth'); ?>" style="width:100%;" onchange="calculateAgeInMonths();" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['mother_dob']); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width:18% !important"><?= _translate('Date of next appointment'); ?> </th>
                                    <td>
                                        <input class="form-control date" type="text" name="nextAppointmentDate" id="nextAppointmentDate" placeholder="<?= _translate('Please enter date of next appointment'); ?>" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['next_appointment_date']); ?>" />
                                    </td>
                                    <th scope="row" style="width:18% !important"><?= _translate('Mode of Delivery'); ?> </th>
                                    <td>
                                        <select class="form-control" name="modeOfDelivery" id="modeOfDelivery" onchange="showOtherOption(this.value)">
                                            <option value=''> <?= _translate('-- Select --'); ?> </option>
                                            <option value="Normal" <?php echo ($eidInfo['mode_of_delivery'] == 'Normal') ? "selected='selected'" : ""; ?>> <?= _translate('Normal'); ?> </option>
                                            <option value="Caesarean" <?php echo ($eidInfo['mode_of_delivery'] == 'Caesarean') ? "selected='selected'" : ""; ?>> <?= _translate('Caesarean'); ?> </option>
                                            <option value="Unknown" <?php echo ($eidInfo['mode_of_delivery'] == 'Unknown') ? "selected='selected'" : ""; ?>> <?= _translate('Gravidity N*'); ?>' </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width:18% !important"><?= _translate('Number of exposed children'); ?> </th>
                                    <td>
                                        <input class="form-control forceNumeric" type="text" value="<?php echo ($eidInfo['no_of_exposed_children']); ?>" name="noOfExposedChildren" id="noOfExposedChildren" placeholder="<?= _translate('Please enter number of exposed children'); ?>" />
                                    </td>
                                    <th scope="row" style="width:18% !important"><?= _translate('Number of infected children'); ?> </th>
                                    <td>
                                        <input class="form-control forceNumeric" type="text" name="noOfInfectedChildren" value="<?php echo ($eidInfo['no_of_infected_children']); ?>" id="noOfInfectedChildren" placeholder="<?= _translate('Please enter number of infected children'); ?>" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row" style="width:18% !important"><?= _translate('ARV protocol followed by mother'); ?> </th>
                                    <td>
                                        <select class="form-control" name="motherArvProtocol" id="motherArvProtocol" onchange="showArvProtocolOtherOption()">
                                            <option value=''> <?= _translate('-- Select --'); ?> </option>
                                            <option value="Nothing" <?php echo ($eidInfo['mother_arv_protocol'] == 'Nothing') ? "selected='selected'" : ""; ?>> <?= _translate('Nothing'); ?> </option>
                                            <option value="TELE (TDF+TC+EFV)" <?php echo ($eidInfo['mother_arv_protocol'] == 'TELE (TDF+TC+EFV)') ? "selected='selected'" : ""; ?>><?= _translate('TELE (TDF+TC+EFV)'); ?> </option>
                                            <option value="other" <?php echo ($eidInfo['mother_arv_protocol'] == 'other') ? "selected='selected'" : ""; ?>><?= _translate('Other'); ?></option>
                                        </select>
                                        <input type="text" class="form-control" name="motherArvProtocolOther" id="motherArvProtocolOther" style="display:none;" />

                                    </td>
                                    <th scope="row"><?= _translate('Date of Initiation'); ?></th>
                                    <td>
                                        <input type="text" class="form-control date" name="motherTreatmentInitiationDate" id="motherTreatmentInitiationDate" placeholder="<?= _translate('Enter date of initiation'); ?>" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['mother_treatment_initiation_date']); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?= _translate("Date of HIV diagnosis"); ?></th>
                                    <td>
                                        <input type="text" class="form-control date" name="motherHivTestDate" id="motherHivTestDate" placeholder="<?= _translate("Enter date of Mother's Hiv Test"); ?>" title="<?= _translate("Enter date of Mother's Hiv Test"); ?>" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['mother_hiv_test_date']); ?>" />
                                    </td>
                                </tr>
                            </table>

                            <br><br>
                            <table aria-describedby="table" class="table" aria-hidden="true">
                                <tr>
                                    <th scope="row" colspan=4>
                                        <h4><?= _translate('CLINICAL INFORMATION'); ?></h4>
                                    </th>
                                </tr>
                                <tr>
                                    <th scope="row" style="width:16% !important"><?= _translate('Is the child symptomatic?'); ?> <span class="mandatory">*</span></th>
                                    <td style="width:30% !important">
                                        <select class="form-control isRequired" name="isChildSymptomatic" id="isChildSymptomatic">
                                            <option value=''> <?= _translate('-- Select --'); ?> </option>
                                            <option value="yes" <?php echo ($eidInfo['is_child_symptomatic'] == 'yes') ? "selected='selected'" : ""; ?>> <?= _translate('Yes'); ?> </option>
                                            <option value="no" <?php echo ($eidInfo['is_child_symptomatic'] == 'no') ? "selected='selected'" : ""; ?>> <?= _translate('No'); ?> </option>
                                        </select>
                                    </td>
                                    <th scope="row" style="width:16% !important"><?= _translate('Date of Weaning?'); ?> </th>
                                    <td style="width:30% !important">
                                        <input type="text" class="form-control date" name="dateOfWeaning" id="dateOfWeaning" title="<?= _translate('Enter date of weaning'); ?>" placeholder="<?= _translate('Enter date of weaning'); ?>" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['date_of_weaning']); ?>" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width:16% !important"><?= _translate('Was the child breastfed?'); ?> </th>
                                    <td style="width:30% !important">
                                        <select class="form-control" name="wasChildBreastfed" id="wasChildBreastfed">
                                            <option value=''> <?= _translate('-- Select --'); ?> </option>
                                            <option value="yes" <?php echo ($eidInfo['was_child_breastfed'] == 'yes') ? "selected='selected'" : ""; ?>> <?= _translate('Yes'); ?> </option>
                                            <option value="no" <?php echo ($eidInfo['was_child_breastfed'] == 'no') ? "selected='selected'" : ""; ?>> <?= _translate('No'); ?> </option>
                                            <option value="unknown" <?php echo ($eidInfo['was_child_breastfed'] == 'unknown') ? "selected='selected'" : ""; ?>> <?= _translate('Unknown'); ?> </option>
                                        </select>
                                    </td>
                                    <th scope="row" style="width:16% !important"><?= _translate('If Yes,'); ?> </th>
                                    <td style="width:30% !important">
                                        <select class="form-control" name="choiceOfFeeding" id="choiceOfFeeding">
                                            <option value=''> <?= _translate('-- Select --'); ?> </option>
                                            <option value="Exclusive" <?php echo ($eidInfo['choice_of_feeding'] == 'Exclusive') ? "selected='selected'" : ""; ?>><?= _translate('Exclusive'); ?></option>
                                            <option value="Mixed" <?php echo ($eidInfo['choice_of_feeding'] == 'Mixed') ? "selected='selected'" : ""; ?>><?= _translate('Mixed'); ?></option>
                                            <option value="Exclusive formula feeding" <?php echo ($eidInfo['choice_of_feeding'] == 'Exclusive formula feeding') ? "selected='selected'" : ""; ?>><?= _translate('Exclusive formula feeding'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width:16% !important"><?= _translate('Is the child on Cotrim?'); ?> </th>
                                    <td style="width:30% !important">
                                        <select class="form-control" name="isChildOnCotrim" id="isChildOnCotrim">
                                            <option value=''> <?= _translate('-- Select --'); ?> </option>
                                            <option value="yes" <?php echo ($eidInfo['is_child_on_cotrim'] == 'yes') ? "selected='selected'" : ""; ?>> <?= _translate('Yes'); ?> </option>
                                            <option value="no" <?php echo ($eidInfo['is_child_on_cotrim'] == 'no') ? "selected='selected'" : ""; ?>> <?= _translate('No'); ?> </option>
                                        </select>
                                    </td>
                                    <th scope="row" style="width:16% !important"><?= _translate('If Yes, Date of Initiation'); ?> </th>
                                    <td style="width:30% !important">
                                        <input type="text" class="form-control date" name="childStartedCotrimDate" id="childStartedCotrimDate" title="<?= _translate('Enter date of Initiation'); ?>" placeholder="<?= _translate('Enter date of Initiation'); ?>" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['child_started_cotrim_date']); ?>" />

                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="width:16% !important"><?= _translate('Is the child on ART?'); ?> </th>
                                    <td style="width:30% !important">
                                        <select class="form-control" name="infantArtStatus" id="infantArtStatus">
                                            <option value=''> <?= _translate('-- Select --'); ?> </option>
                                            <option value="yes" <?php echo ($eidInfo['infant_art_status'] == 'yes') ? "selected='selected'" : ""; ?>> <?= _translate('Yes'); ?> </option>
                                            <option value="no" <?php echo ($eidInfo['infant_art_status'] == 'no') ? "selected='selected'" : ""; ?>> <?= _translate('No'); ?> </option>
                                        </select>
                                    </td>
                                    <th scope="row" style="width:16% !important"><?= _translate('If Yes, Date of Initiation'); ?> </th>
                                    <td style="width:30% !important">
                                        <input type="text" class="form-control date" name="childStartedArtDate" id="childStartedArtDate" title="<?= _translate('Enter date of Initiation'); ?>" placeholder="<?= _translate('Enter date of Initiation'); ?>" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['child_started_art_date']); ?>" />

                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?= _translate('Stopped breastfeeding ?'); ?></th>
                                    <td>
                                        <select class="form-control" name="hasInfantStoppedBreastfeeding" id="hasInfantStoppedBreastfeeding">
                                            <option value=''> <?= _translate('-- Select --'); ?> </option>
                                            <option value="yes" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'yes') ? "selected='selected'" : ""; ?>> <?= _translate('Yes'); ?> </option>
                                            <option value="no" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'no') ? "selected='selected'" : ""; ?>> <?= _translate('No'); ?> </option>
                                            <option value="unknown" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'unknown') ? "selected='selected'" : ""; ?>> <?= _translate('Unknown'); ?> </option>
                                        </select>
                                    </td>
                                    <th scope="row"><?= _translate('Age (months) breastfeeding stopped'); ?> </th>
                                    <td>
                                        <input type="number" class="form-control" style="max-width:200px;display:inline;" placeholder="Age (months) breastfeeding stopped" type="text" name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" value="<?php echo ($eidInfo['age_breastfeeding_stopped_in_months']); ?>" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row"><?= _translate('Requesting Clinician Name'); ?></th>
                                    <td> <input type="text" class="form-control" id="clinicianName" name="clinicianName" placeholder="Requesting Clinician Name" title="Please enter request clinician" value="<?php echo $eidInfo['clinician_name']; ?>" /></td>

                                </tr>

                                <tr>
                                    <th><?= _translate('Previous Results'); ?></th>
                                </tr>
                                <tr>
                                    <td style="text-align:center;" scope="row"><?= _translate('Serological Test'); ?> </td>
                                    <td colspan="2" style="text-align:center;">
                                        <input <?php echo ($eidInfo['serological_test'] == 'positive') ? "checked='checked'" : ""; ?> type="radio" class="form-check" name="serologicalTest" id="serologicalTest" value="positive" />&nbsp;&nbsp;<label for="positive"><?= _translate('Positive'); ?></label>&nbsp;&nbsp;&nbsp;
                                        <input <?php echo ($eidInfo['serological_test'] == 'negative') ? "checked='checked'" : ""; ?> type="radio" class="form-check" name="serologicalTest" id="serologicalTest" value="negative" />&nbsp;&nbsp;<label for="negative"><?= _translate('Negative'); ?>&nbsp;&nbsp;&nbsp;
                                            <input <?php echo ($eidInfo['serological_test'] == 'notdone') ? "checked='checked'" : ""; ?> type="radio" class="form-check" name="serologicalTest" id="serologicalTest" value="notdone" />&nbsp;&nbsp;<label for="notdone"><?= _translate('Not Done'); ?>&nbsp;&nbsp;&nbsp;
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align:center;" scope="row"><?= _translate('Previous PCR Tests'); ?> <br><br>PCR 1<br><br><br>PCR2<br><br><br>PCR 3</td>
                                    <td>
                                        <?= _translate('Date of sample collection'); ?><br> <br>
                                        <input value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['pcr_1_test_date']); ?>" class="form-control date" type="text" name="pcr1TestDate" id="pcr1TestDate" placeholder="<?= _translate('Test date'); ?>" /><br>
                                        <input value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['pcr_2_test_date']); ?>" class="form-control date" type="text" name="pcr2TestDate" id="pcr2TestDate" placeholder="<?= _translate('Test date'); ?>" /><br>
                                        <input value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['pcr_3_test_date']); ?>" class="form-control date" type="text" name="pcr3TestDate" id="pcr3TestDate" placeholder="<?= _translate('Test date'); ?>" />
                                    </td>
                                    <td>
                                        <?= _translate('Results'); ?><br><br>
                                        <input value="<?php echo ($eidInfo['pcr_1_test_result']); ?>" type="text" class="form-control input-sm" name="pcr1TestResult" id="pcr1TestResult" /><br>
                                        <input value="<?php echo ($eidInfo['pcr_2_test_result']); ?>" type="text" class="form-control input-sm" name="pcr2TestResult" id="pcr1TestResult" /><br>
                                        <input value="<?php echo ($eidInfo['pcr_3_test_result']); ?>" type="text" class="form-control input-sm" name="pcr3TestResult" id="pcr1TestResult" /><br>

                                    </td>
                                    <td><br><br><br>
                                        D = <?= _translate('Detected'); ?><br>
                                        ND = <?= _translate('Not Detected'); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?= _translate('Reason for Sample Collection'); ?></th>
                                    <td>
                                        <select class="form-control" name="sampleCollectionReason" id="sampleCollectionReason">
                                            <option value=''> <?= _translate('-- Select --'); ?> </option>
                                            <option value="1st Test for well child born of HIV+ mother" <?php echo ($eidInfo['sample_collection_reason'] == '1st Test for well child born of HIV+ mother') ? "selected='selected'" : ""; ?>><?= _translate('1st Test for well child born of HIV+ mother'); ?></option>
                                            <option value="1st Test for sick child" <?php echo ($eidInfo['sample_collection_reason'] == '1st Test for sick child') ? "selected='selected'" : ""; ?>><?= _translate('1st Test for sick child'); ?></option>
                                            <option value="Repeat Testing for 6 weeks after weaning" <?php echo ($eidInfo['sample_collection_reason'] == 'Repeat Testing for 6 weeks after weaning') ? "selected='selected'" : ""; ?>><?= _translate('Repeat Testing for 6 weeks after weaning'); ?></option>
                                            <option value="Repeat Testing due to loss of 1st sample" <?php echo ($eidInfo['sample_collection_reason'] == 'Repeat Testing due to loss of 1st sample') ? "selected='selected'" : ""; ?>><?= _translate('Repeat Testing due to loss of 1st sample'); ?></option>
                                            <option value="Repeat due to clinical suspicion following negative 1st test" <?php echo ($eidInfo['sample_collection_reason'] == 'Repeat due to clinical suspicion following negative 1st test') ? "selected='selected'" : ""; ?>><?= _translate('Repeat due to clinical suspicion following negative 1st test'); ?></option>
                                        </select>
                                    </td>
                                    <th scope="row"><?= _translate('Point of Entry'); ?></th>
                                    <td>
                                        <select class="form-control" name="labTestingPoint" id="labTestingPoint" onchange="showTestingPointOther();">
                                            <option value=''> <?= _translate('-- Select --'); ?> </option>
                                            <option value="PMTCT(PT)" <?php echo ($eidInfo['lab_testing_point'] == 'PMTCT(PT)') ? "selected='selected'" : ""; ?>><?= _translate('PMTCT(PT)'); ?></option>
                                            <option value="IWC(IC)" <?php echo ($eidInfo['lab_testing_point'] == 'IWC(IC)') ? "selected='selected'" : ""; ?>> <?= _translate('IWC(IC)'); ?> </option>
                                            <option value="Hospitalization (HO)" <?php echo ($eidInfo['lab_testing_point'] == 'Hospitalization (HO)') ? "selected='selected'" : ""; ?>> <?= _translate('Hospitalization (HO)'); ?>' </option>
                                            <option value="Consultation (CS)" <?php echo ($eidInfo['lab_testing_point'] == 'Consultation (CS)') ? "selected='selected'" : ""; ?>> <?= _translate('Consultation (CS)'); ?> </option>
                                            <option value="EPI(PE)" <?php echo ($eidInfo['lab_testing_point'] == 'EPI(PE)') ? "selected='selected'" : ""; ?>> <?= _translate('EPI(PE)'); ?> </option>
                                            <option value="other" <?php echo ($eidInfo['lab_testing_point'] == 'other') ? "selected='selected'" : ""; ?>><?= _translate('Other'); ?></option>
                                        </select>
                                        <input type="text" name="labTestingPointOther" id="labTestingPointOther" class="form-control" title="<?= _translate('Please specify other point of entry') ?>" placeholder="<?= _translate('Please specify other point of entry') ?>" style="display:<?php echo ($eidInfo['lab_testing_point'] == 'other') ? 'block' : 'none' ?>;" value="<?php echo ($eidInfo['lab_testing_point_other']); ?>" />
                                    </td>
                                </tr>
                                <?php if ($general->isLISInstance()) { ?>

                                    <tr>
                                        <th scope="row"><label for=""><?= _translate('Lab Assigned Code'); ?> </label></th>
                                        <td>
                                            <input type="text" class="form-control" id="labAssignedCode" name="labAssignedCode" placeholder="<?= _translate("Enter Lab Assigned Code"); ?>" title="Enter Lab Assigned Code" <?php echo $labFieldDisabled; ?> value="<?php echo $eidInfo['lab_assigned_code']; ?>" onchange="" style="width:100%;" />
                                        </td>
                                    </tr>
                                <?php } ?>
                            </table>
                            <br><br>
                            <table aria-describedby="table" class="table" aria-hidden="true">
                                <tr>
                                    <th scope="row" colspan=4 style="border-top:#ccc 2px solid;">
                                        <h4><?= _translate('QUALITY SAMPLE INFORMATION'); ?></h4>
                                    </th>
                                </tr>
                                <tr>
                                    <th scope="row" style="width:15% !important"><?= _translate('Sample Collection Date'); ?> <span class="mandatory">*</span> </th>
                                    <td style="width:35% !important;">
                                        <input class="form-control dateTime isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="<?= _translate('Sample Collection Date'); ?>" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['sample_collection_date']); ?>" />
                                    </td>
                                    <th style="width:15% !important;"><?= _translate('Recollected'); ?></th>
                                    <td style="width:35% !important;">
                                        <select class="form-control" name="isSampleRecollected" id="isSampleRecollected">
                                            <option value=''> <?= _translate('-- Select --'); ?> </option>
                                            <option value="yes" <?php echo ($eidInfo['is_sample_recollected'] == 'yes') ? "selected='selected'" : ""; ?>> <?= _translate('Yes'); ?> </option>
                                            <option value="no" <?php echo ($eidInfo['is_sample_recollected'] == 'no') ? "selected='selected'" : ""; ?>> <?= _translate('No'); ?> </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?= _translate('Name of health personnel'); ?></th>
                                    <td>
                                        <input class="form-control" type="text" name="sampleRequestorName" id="sampleRequestorName" placeholder="Requesting Officer" value="<?= $eidInfo['sample_requestor_name'] ?>" />
                                    </td>
                                    <th scope="row"><?= _translate('Contact Number'); ?></th>
                                    <td>
                                        <input class="form-control phone-number" type="text" name="sampleRequestorPhone" id="sampleRequestorPhone" placeholder="Requesting Officer Phone" value="<?= $eidInfo['sample_requestor_phone'] ?>" />
                                    </td>
                                </tr>

                            </table>
                        </div>
                    </div>

                    <form class="form-horizontal" method="post" name="editEIDRequestForm" id="editEIDRequestForm" autocomplete="off" action="eid-update-result-helper.php">
                        <div class="box box-primary">
                            <div class="box-body">
                                <div class="box-header with-border">
                                    <h3 class="box-title"> <?= _translate('Reserved for Laboratory Use'); ?> </h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th><?= _translate('EID Number'); ?> </th>
                                        <td>
                                            <input name="eidNumber" id="eidNumber" class="form-control" placeholder="<?= _translate('Enter EID Number'); ?>" title="<?= _translate('Please enter EID Number'); ?>" value="<?php echo $eidInfo['eid_number']; ?>">
                                        </td>
                                        <th><?= _translate('Testing Platform'); ?><span class="mandatory">*</span> </th>
                                        <td>
                                            <select class="form-control isRequired" name="eidPlatform" id="eidPlatform" title="<?= _translate('Please select the testing platform'); ?>">
                                                <?= $general->generateSelectOptions($testPlatformList, $eidInfo['eid_test_platform'], '-- Select --'); ?>
                                            </select>
                                        </td>

                                    <tr>
                                        <th scope="row"><label for=""><?= _translate('Sample Received Date'); ?> <span class="mandatory">*</span></label></th>
                                        <td>
                                            <input type="text" class="form-control dateTime isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate("Please enter date"); ?>" title="<?= _translate('Please enter sample receipt date'); ?>" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['sample_received_at_lab_datetime']) ?>" onchange="" style="width:100%;" />
                                        </td>
                                        <td><label for="labId"><?= _translate('Lab Name'); ?> <span class="mandatory">*</span></label> </td>
                                        <td>
                                            <select name="labId" id="labId" class="form-control isRequired" title="<?= _translate('Please select Testing Lab name'); ?>" style="width:100%;">
                                                <?= $general->generateSelectOptions($testingLabs, $eidInfo['lab_id'], '-- Select --'); ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate('Is Sample Rejected?'); ?><span class="mandatory">*</span></th>
                                        <td>
                                            <select class="form-control isRequired" name="isSampleRejected" id="isSampleRejected">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value="yes" <?php echo ($eidInfo['is_sample_rejected'] == 'yes') ? "selected='selected'" : ""; ?>> <?= _translate('Yes'); ?> </option>
                                                <option value="no" <?php echo ($eidInfo['is_sample_rejected'] == 'no') ? "selected='selected'" : ""; ?>> <?= _translate('No'); ?> </option>
                                            </select>
                                        </td>
                                    </tr>

                                    <th scope="row" class="rejected" style="display: none;"><?= _translate('Reason for Rejection'); ?></th>
                                    <td class="rejected" style="display: none;">
                                        <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="<?= _translate('Please choose reason for rejection'); ?>">
                                            <option value=""><?= _translate('-- Select --'); ?></option>
                                            <?php foreach ($rejectionTypeResult as $type) { ?>
                                                <optgroup label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
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
                                    <tr class="show-rejection rejected" style="display:none;">
                                        <td><?= _translate('Rejection Date'); ?><span class="mandatory">*</span></td>
                                        <td><input value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="<?= _translate('Select Rejection Date'); ?>" /></td>
                                    </tr>
                                    <tr>
                                        <td style="width:25%;"><label for=""><?= _translate('Sample Test Date'); ?><span class="mandatory">*</span> </label></td>
                                        <td style="width:25%;">
                                            <input type="text" class="form-control dateTime isRequired" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _translate("Please enter date"); ?>" title="<?= _translate('Sample Test Date'); ?>" <?php echo $labFieldDisabled; ?> onchange="" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['sample_tested_datetime']) ?>" style="width:100%;" />
                                        </td>


                                        <th scope="row"><?= _translate('Result'); ?><span class="mandatory">*</span></th>
                                        <td>
                                            <select class="form-control result-focus isRequired" name="result" id="result">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                    <option value="<?php echo $eidResultKey; ?>" <?php echo ($eidInfo['result'] == $eidResultKey) ? "selected='selected'" : ""; ?>> <?php echo $eidResultValue; ?> </option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate('Reviewed On'); ?><span class="mandatory">*</span></th>
                                        <td><input type="text" value="<?php echo $eidInfo['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control isRequired" placeholder="<?= _translate('Reviewed on'); ?>" title="<?= _translate('Please enter the Reviewed on'); ?>" /></td>
                                        <th scope="row">Reviewed By<span class="mandatory">*</span></th>
                                        <td>
                                            <select name="reviewedBy" id="reviewedBy" class="select2 form-control isRequired" title="Please choose reviewed by" style="width: 100%;">
                                                <?= $general->generateSelectOptions($userInfo, $eidInfo['result_reviewed_by'], '-- Select --'); ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate('Approved On'); ?><span class="mandatory">*</span></th>
                                        <td><input type="text" value="<?php echo $eidInfo['result_approved_datetime']; ?>" name="approvedOnDateTime" id="approvedOnDateTime" class="dateTime disabled-field form-control isRequired" placeholder="<?= _translate('Approved on'); ?>" title="<?= _translate('Please enter the Approved on'); ?>" /></td>
                                        <th scope="row"><?= _translate('Approved By'); ?><span class="mandatory">*</span></th>
                                        <td>
                                            <select name="approvedBy" id="approvedBy" class="select2 form-control isRequired" title="<?= _translate('Please choose approved by'); ?>" style="width: 100%;">
                                                <?= $general->generateSelectOptions($userInfo, $eidInfo['result_approved_by'], '<?= _translate("-- Select --"); ?>'); ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr class="change-reason">
                                        <th scope="row" class="change-reason" style="display: none;"><?= _translate('Reason for Changing'); ?> <span class="mandatory">*</span></th>
                                        <td class="change-reason" style="display: none;"><textarea name="reasonForChanging" id="reasonForChanging" class="form-control" placeholder="<?= _translate('Enter the reason for changing'); ?>" title="<?= _translate('Please enter the reason for changing'); ?>"></textarea></td>
                                        <th scope="row"></th>
                                        <td></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                </div>
                <!-- /.box-body -->
                <div class="box-footer">
                    <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
                        <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                        <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                    <?php } ?>
                    <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                    <!--   <input type="hidden" class="" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="< ?= _translate("Please enter date"); ?>" title="Please enter sample receipt date" value="< ?php echo DateUtility::humanReadableDateFormat($eidInfo['sample_received_at_lab_datetime']) ?>" />-->
                    <input type="hidden" name="revised" id="revised" value="no" />
                    <input type="hidden" name="formId" id="formId" value="1" />
                    <input type="hidden" name="eidSampleId" id="eidSampleId" value="<?php echo ($eidInfo['eid_id']); ?>" />
                    <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
                    <input type="hidden" id="sampleCode" name="sampleCode" value="<?= htmlspecialchars((string) $eidInfo['sample_code']); ?>" />
                    <input type="hidden" id="childId" name="childId" value="<?php echo $eidInfo['child_id']; ?>" />
                    <a href="/eid/results/eid-manual-results.php" class="btn btn-default"> Cancel</a>
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
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#facilityId").html(details[0]);
                        $("#district").html(details[1]);
                        // $("#clinicianName").val(details[2]);
                    }
                });
            //}
        } else if (pName == '') {
            provinceName = true;
            facilityName = true;
            $("#province").html("<?php echo $province; ?>");
            $("#facilityId").html("<?php echo $facility; ?>");
            $("#facilityId").select2("val", "");
            $("#district").html("<option value=''> -- Sélectionner -- </option>");
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
                function(data) {
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
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#province").html(details[0]);
                        $("#district").html(details[1]);
                        //$("#clinicianName").val(details[2]);
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
        flag = deforayValidator.init({
            formId: 'editEIDRequestForm'
        });
        if (flag) {
            document.getElementById('editEIDRequestForm').submit();
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

        $('#result').change(function(e) {
            if ($(this).data("result") != "" && $(this).data("result") != $(this).val()) {
                $('.change-reason').show();
                $('#reasonForChanging').addClass('isRequired');
            } else {
                $('.change-reason').hide();
                $('#reasonForChanging').removeClass('isRequired');
            }
        });

        $('.disabledForm input, .disabledForm select , .disabledForm textarea ').attr('disabled', true);
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
        <?php if (isset($eidInfo['mother_treatment']) && in_array('Other', $eidInfo['mother_treatment'])) { ?>
            $('#motherTreatmentOther').prop('disabled', false);
        <?php } ?>

        <?php if (!empty($eidInfo['mother_vl_result'])) { ?>
            updateMotherViralLoad();
        <?php } ?>

        $("#motherViralLoadCopiesPerMl").on("change keyup paste", function() {
            var motherVl = $("#motherViralLoadCopiesPerMl").val();
            //var motherVlText = $("#motherViralLoadText").val();
            if (motherVl != '') {
                $("#motherViralLoadText").val('');
            }
        });

        $("#eidPlatform").on("change", function() {
            if (this.value != "") {
                getMachine(this.value);
            }
        });
        getMachine($("#eidPlatform").val());
    });

    function getMachine(value) {
        $.post("/instruments/get-machine-names-by-instrument.php", {
                instrumentId: value,
                machine: <?php echo !empty($eidInfo['import_machine_name']) ? $eidInfo['import_machine_name'] : '""'; ?>,
                testType: 'eid'
            },
            function(data) {
                $('#machineName').html('');
                if (data != "") {
                    $('#machineName').append(data);
                }
            });
    }
    $('#editEIDRequestForm').keypress((e) => {
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