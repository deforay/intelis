<?php
// imported in eid-add-request.php based on country in global config
use App\Registries\ContainerRegistry;
use App\Services\EidService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
// Getting the list of Provinces, Districts and Facilities

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();

$specimenTypeResult = $eidService->getEidSampleTypes();

$labFieldDisabled = '';
// $rejectionReason = '';
$rKey = '';
$sKey = '';
$sFormat = '';
if ($general->isSTSInstance()) {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    $rKey = 'R';
} else {
    $sampleCodeKey = 'sample_code_key';
    $sampleCode = 'sample_code';
    $rKey = '';
}
$cpyReq = $general->getGlobalConfig('eid_copy_request_save_and_next');
$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);
$facility = $general->generateSelectOptions($healthFacilities, null, '-- Select --');
?>
<style>
    .prevResult {
        margin-right: 5px;
    }
</style>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _translate("EARLY INFANT DIAGNOSIS (EID) LABORATORY REQUEST FORM"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Add EID Request"); ?></li>
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
                <form class="form-horizontal" method="post" name="addEIDRequestForm" id="addEIDRequestForm" autocomplete="off" action="eid-add-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><?= _translate('HEALTH FACILITY INFORMATION'); ?></h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <?php if ($general->isSTSInstance()) { ?>
                                            <td><label for="sampleCode"><?= _translate('Sample ID'); ?> </label></td>
                                            <td>
                                                <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
                                                <input type="hidden" id="sampleCode" name="sampleCode" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode"><?= _translate('Sample ID'); ?> </label><span class="mandatory">*</span></td>
                                            <td>
                                                <input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="<?= _translate('Sample ID'); ?>" title="<?= _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>" style="width:100%;" readonly="readonly" />
                                            </td>
                                        <?php } ?>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td style="width:25%"><label for="province"><?= _translate('Region'); ?> </label><span class="mandatory">*</span><br>
                                            <select class="form-control isRequired" name="province" id="province" title="<?= _translate('Please select province'); ?>" style="width:100%;" onchange="getfacilityDetails(this);">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <td style="width:25%"><label for="district"><?= _translate('District'); ?> </label><span class="mandatory">*</span><br>
                                            <select class="form-control isRequired" name="district" id="district" title="Please select district" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                                                <option value=""> -- Select -- </option>
                                            </select>
                                        </td>
                                        <td style="width:25%">
                                            <label for="facilityId"><?= _translate('Facility'); ?> </label><span class="mandatory">*</span><br>
                                            <select class="form-control isRequired " name="facilityId" id="facilityId" title="Please select facility" style="width:100%;" onchange="getfacilityProvinceDetails(this),fillFacilityDetails();">
                                                <option value=""> <?= _translate('-- Select --'); ?> </option>
                                                <?php foreach ($healthFacilitiesAllColumns as $hFacility) { ?>
                                                    <option value="<?php echo $hFacility['facility_id']; ?>" data-code="<?php echo $hFacility['facility_code']; ?>" <?php echo (isset($_SESSION['eidData']['facility_id']) && $_SESSION['eidData']['facility_id'] == $hFacility['facility_id']) ? 'selected="selected"' : ''; ?>><?php echo $hFacility['facility_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td style="width:25%">
                                            <label for="facilityCode"><?= _translate('Facility Code'); ?> </label><br>
                                            <input type="text" class="form-control" style="width:100%;" name="facilityCode" id="facilityCode" placeholder="<?= _translate('Clinic/Health Center Code'); ?>" title="<?= _translate('Please enter clinic/health center code'); ?>">
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
                                                    <option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>" <?php echo (isset($_SESSION['eidData']['funding_source']) && $_SESSION['eidData']['funding_source'] == $fundingSource['funding_source_id']) ? 'selected="selected"' : ''; ?>><?= $fundingSource['funding_source_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td style="width:25%">
                                            <label for="implementingPartner"><?= _translate('Implementing Partner'); ?> </label><br>

                                            <select class="form-control" name="implementingPartner" id="implementingPartner" title="<?= _translate('Please choose implementing partner'); ?>" style="width:100%;">
                                                <option value=""> <?= _translate('-- Select --'); ?> </option>
                                                <?php
                                                foreach ($implementingPartnerList as $implementingPartner) {
                                                ?>
                                                    <option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>" <?php echo (isset($_SESSION['eidData']['implementing_partner']) && $_SESSION['eidData']['implementing_partner'] == $implementingPartner['i_partner_id']) ? 'selected="selected"' : ''; ?>><?= $implementingPartner['i_partner_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td style="width:25%">
                                            <label for="labId"><?= _translate('Lab Name'); ?> <span class="mandatory">*</span></label>
                                            <select name="labId" id="labId" class="form-control isRequired" title="<?= _translate('Please select Testing Lab name'); ?>" style="width:100%;">
                                                <?= $general->generateSelectOptions($testingLabs, $_SESSION['eidData']['lab_id'], '-- Select --'); ?>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                                <br>
                                <hr style="border: 1px solid #ccc;">

                                <div class="box-header with-border">
                                    <h3 class="box-title"><?= _translate("CHILD'S IDENTIFICATION"); ?></h3>
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
                                        <th scope="row" style="width:15% !important"><label for="childId"><?= _translate('CRVS file name'); ?> <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired patientId" id="childId" name="childId" placeholder="<?= _translate('Infant Identification (Patient)'); ?>" title="<?= _translate('Please enter Exposed Infant Identification'); ?>" style="width:100%;" onchange="" />
                                            <span class="artNoGroup"></span>
                                        </td>
                                        <th scope="row" style="width:15% !important"><label for="childName"><?= _translate('Infant name'); ?> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="childName" name="childName" placeholder="<?= _translate('Infant name'); ?>" title="<?= _translate('Please enter Infant Name'); ?>" style="width:100%;" onchange="" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="childDob"><?= _translate('Date of Birth'); ?> <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <input type="text" class="form-control date" id="childDob" name="childDob" placeholder="<?= _translate('Date of birth'); ?>" title="<?= _translate('Please enter Date of birth'); ?>" style="width:100%;" onchange="calculateAgeInMonths();" />
                                            <input type="checkbox" name="ageUnreported" id="ageUnreported" onclick="updateAgeInfo();" /> <label for="dob"><?= _translate('Unreported'); ?> </label>

                                        </td>
                                        <th scope="row"><?= _translate('Infant Age (months)'); ?></th>
                                        <td><input type="number" max="24" maxlength="2" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control" id="childAge" name="childAge" placeholder="<?= _translate('Age'); ?>" title="<?= _translate('Age'); ?>" style="width:100%;" /></td>

                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="childGender"><?= _translate('Sex'); ?> <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <select class="form-control isRequired" name="childGender" id="childGender">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value='male'> <?= _translate('Male'); ?> </option>
                                                <option value='female'> <?= _translate('Female'); ?> </option>
                                                <option value='unreported'> <?= _translate('Unreported'); ?> </option>

                                            </select>
                                        </td>

                                        <th scope="row"><?= _translate('Universal Health Coverage'); ?></th>
                                        <td><input type="text" name="healthInsuranceCode" id="healthInsuranceCode" class="form-control" placeholder="<?= _translate('Enter Universal Health Coverage'); ?>" title="<?= _translate('Enter Universal Health Coverage'); ?>" maxlength="32" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate('Weight of the day'); ?></th>
                                        <td><input type="text" class="form-control forceNumeric" id="childWeight" name="childWeight" placeholder="<?= _translate('Infant weight of the day in Kg'); ?>" title="<?= _translate('Infant weight of the day in Kg'); ?>" style="width:100%;" /></td>

                                        <th scope="row"><?= _translate('Caretaker phone number'); ?></th>
                                        <td><input type="text" class="form-control phone-number" id="caretakerPhoneNumber" name="caretakerPhoneNumber" maxlength="<?= $maxPhoneLength; ?>" placeholder="<?= _translate('Caretaker Phone Number'); ?>" title="<?= _translate('Caretaker Phone Number'); ?>" style="width:100%;" /></td>


                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate('Infant caretaker address'); ?></th>
                                        <td><textarea class="form-control " id="caretakerAddress" name="caretakerAddress" placeholder="<?= _translate('Caretaker Address'); ?>" title="<?= _translate('Caretaker Address'); ?>" style="width:100%;"></textarea></td>

                                        <th scope="row"><?= _translate('Prophylactic ARV given to child'); ?><span class="mandatory">*</span></th>
                                        <td>
                                            <select class="form-control isRequired" name="childProphylacticArv" id="childProphylacticArv" title="<?= _translate('Prophylactic ARV given to child'); ?>" onchange="showOtherARV();">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value='nothing'> <?= _translate('Nothing'); ?> </option>
                                                <option value='nvp'> <?= _translate('NVP'); ?> </option>
                                                <option value='azt'> <?= _translate('AZT'); ?> </option>
                                                <option value='other'> <?= _translate('Other'); ?> </option>
                                            </select>
                                            <input type="text" name="childProphylacticArvOther" id="childProphylacticArvOther" class="form-control" placeholder="<?= _translate('Please specify other prophylactic ARV given'); ?>" title="<?= _translate('Please specify other prophylactic ARV given'); ?>" style="display:none;" />
                                        </td>

                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate('Date of Initiation'); ?></th>
                                        <td>
                                            <input type="text" class="form-control date" name="childTreatmentInitiationDate" id="childTreatmentInitiationDate" placeholder="<?= _translate('Enter date of initiation'); ?>" />
                                        </td>
                                    </tr>
                                </table>

                                <br><br>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th scope="row" colspan=4 style="border-top:#ccc 2px solid;">
                                            <h4><?= _translate("MOTHER'S INFORMATION"); ?></h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15% !important"><label for="mothersName"><?= _translate('Mother name'); ?> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control" id="mothersName" name="mothersName" placeholder="<?= _translate('Mother name'); ?>" title="<?= _translate('Please enter Infant Name'); ?>" style="width:100%;" onchange="" />
                                        </td>
                                        <th scope="row"><label for="dob"><?= _translate('Date of Birth'); ?> <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <input type="text" class="form-control isRequired" id="mothersDob" name="mothersDob" placeholder="<?= _translate('Date of birth'); ?>" title="<?= _translate('Please enter Date of birth'); ?>" style="width:100%;" onchange="calculateAgeInMonths();" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:18% !important"><?= _translate('Date of next appointment'); ?> </th>
                                        <td>
                                            <input class="form-control" type="text" name="nextAppointmentDate" id="nextAppointmentDate" placeholder="<?= _translate('Please enter date of next appointment'); ?>" />
                                        </td>
                                        <th scope="row" style="width:18% !important"><?= _translate('Mode of Delivery'); ?> </th>
                                        <td>
                                            <select class="form-control" name="modeOfDelivery" id="modeOfDelivery" onchange="showOtherOption(this.value)">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value="Normal"> <?= _translate('Normal'); ?> </option>
                                                <option value="Caesarean"> <?= _translate('Caesarean'); ?> </option>
                                                <option value="Unknown"> <?= _translate('Gravidity N*'); ?>' </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:18% !important"><?= _translate('Number of exposed children'); ?> </th>
                                        <td>
                                            <input class="form-control forceNumeric" type="text" name="noOfExposedChildren" id="noOfExposedChildren" placeholder="<?= _translate('Please enter number of exposed children'); ?>" />
                                        </td>
                                        <th scope="row" style="width:18% !important"><?= _translate('Number of infected children'); ?> </th>
                                        <td>
                                            <input class="form-control forceNumeric" type="text" name="noOfInfectedChildren" id="noOfInfectedChildren" placeholder="<?= _translate('Please enter number of infected children'); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:18% !important"><?= _translate('ARV protocol followed by mother'); ?> </th>
                                        <td>
                                            <select class="form-control" name="motherArvProtocol" id="motherArvProtocol" onchange="showArvProtocolOtherOption()">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value="Nothing"> <?= _translate('Nothing'); ?> </option>
                                                <option value="TELE (TDF+TC+EFV)"><?= _translate('TELE (TDF+TC+EFV)'); ?> </option>
                                                <option value="other"><?= _translate('Other'); ?></option>
                                            </select>
                                            <input type="text" class="form-control" name="motherArvProtocolOther" id="motherArvProtocolOther" style="display:none;" />

                                        </td>
                                        <th scope="row"><?= _translate('Date of Initiation'); ?></th>
                                        <td>
                                            <input type="text" class="form-control date" name="motherTreatmentInitiationDate" id="motherTreatmentInitiationDate" placeholder="<?= _translate('Enter date of initiation'); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate("Date of HIV diagnosis"); ?></th>
                                        <td>
                                            <input type="text" class="form-control date" name="motherHivTestDate" id="motherHivTestDate" placeholder="<?= _translate("Enter date of Mother's Hiv Test"); ?>" title="<?= _translate("Enter date of Mother's Hiv Test"); ?>" />
                                        </td>
                                    </tr>
                                </table>
                                <br>
                                <hr style="border: 1px solid #ccc;">

                                <div class="box-header with-border">
                                    <h3 class="box-title"><?= _translate("CLINICAL INFORMATION"); ?></h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th scope="row" style="width:16% !important"><?= _translate('Is the child symptomatic?'); ?> <span class="mandatory">*</span></th>
                                        <td style="width:30% !important">
                                            <select class="form-control isRequired" name="isChildSymptomatic" id="isChildSymptomatic">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value="yes" <?php echo (isset($_SESSION['eidData']['is_child_symptomatic']) && !empty($_SESSION['eidData']['is_child_symptomatic']) && $_SESSION['eidData']['is_child_symptomatic'] == 'yes') ? 'selected="selected"' : ''; ?>> <?= _translate('Yes'); ?> </option>
                                                <option value="no" <?php echo (isset($_SESSION['eidData']['is_child_symptomatic']) && !empty($_SESSION['eidData']['is_child_symptomatic']) && $_SESSION['eidData']['is_child_symptomatic'] == 'no') ? 'selected="selected"' : ''; ?>> <?= _translate('No'); ?> </option>
                                            </select>
                                        </td>
                                        <th scope="row" style="width:16% !important"><?= _translate('Date of Weaning?'); ?> </th>
                                        <td style="width:30% !important">
                                            <input type="text" value="<?php echo $_SESSION['eidData']['date_of_weaning']; ?>" class="form-control date" name="dateOfWeaning" id="dateOfWeaning" title="<?= _translate('Enter date of weaning'); ?>" placeholder="<?= _translate('Enter date of weaning'); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:16% !important"><?= _translate('Was the child breastfed?'); ?> </th>
                                        <td style="width:30% !important">
                                            <select class="form-control" name="wasChildBreastfed" id="wasChildBreastfed">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value="yes" <?php echo (isset($_SESSION['eidData']['was_child_breastfed']) && !empty($_SESSION['eidData']['was_child_breastfed']) && $_SESSION['eidData']['was_child_breastfed'] == 'yes') ? 'selected="selected"' : ''; ?>> <?= _translate('Yes'); ?> </option>
                                                <option value="no" <?php echo (isset($_SESSION['eidData']['was_child_breastfed']) && !empty($_SESSION['eidData']['was_child_breastfed']) && $_SESSION['eidData']['was_child_breastfed'] == 'no') ? 'selected="selected"' : ''; ?>> <?= _translate('No'); ?> </option>
                                                <option value="unknown" <?php echo (isset($_SESSION['eidData']['was_child_breastfed']) && !empty($_SESSION['eidData']['was_child_breastfed']) && $_SESSION['eidData']['was_child_breastfed'] == 'unknown') ? 'selected="selected"' : ''; ?>> <?= _translate('Unknown'); ?> </option>
                                            </select>
                                        </td>
                                        <th scope="row" style="width:16% !important"><?= _translate('If Yes,'); ?> </th>
                                        <td style="width:30% !important">
                                            <select class="form-control" name="choiceOfFeeding" id="choiceOfFeeding">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value="Exclusive" <?php echo (isset($_SESSION['eidData']['choice_of_feeding']) && !empty($_SESSION['eidData']['choice_of_feeding']) && $_SESSION['eidData']['choice_of_feeding'] == 'Exclusive') ? 'selected="selected"' : ''; ?>><?= _translate('Exclusive'); ?></option>
                                                <option value="Mixed" <?php echo (isset($_SESSION['eidData']['choice_of_feeding']) && !empty($_SESSION['eidData']['choice_of_feeding']) && $_SESSION['eidData']['choice_of_feeding'] == 'Mixed') ? 'selected="selected"' : ''; ?>><?= _translate('Mixed'); ?></option>
                                                <option value="Exclusive formula feeding" <?php echo (isset($_SESSION['eidData']['choice_of_feeding']) && !empty($_SESSION['eidData']['choice_of_feeding']) && $_SESSION['eidData']['choice_of_feeding'] == 'Exclusive formula feeding') ? 'selected="selected"' : ''; ?>><?= _translate('Exclusive formula feeding'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:16% !important"><?= _translate('Is the child on Cotrim?'); ?> </th>
                                        <td style="width:30% !important">
                                            <select class="form-control" name="isChildOnCotrim" id="isChildOnCotrim">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value="yes" <?php echo (isset($_SESSION['eidData']['is_child_on_cotrim']) && !empty($_SESSION['eidData']['is_child_on_cotrim']) && $_SESSION['eidData']['is_child_on_cotrim'] == 'yes') ? 'selected="selected"' : ''; ?>> <?= _translate('Yes'); ?> </option>
                                                <option value="no" <?php echo (isset($_SESSION['eidData']['is_child_on_cotrim']) && !empty($_SESSION['eidData']['is_child_on_cotrim']) && $_SESSION['eidData']['is_child_on_cotrim'] == 'no') ? 'selected="selected"' : ''; ?>> <?= _translate('No'); ?> </option>
                                            </select>
                                        </td>
                                        <th scope="row" style="width:16% !important"><?= _translate('If Yes, Date of Initiation'); ?> </th>
                                        <td style="width:30% !important">
                                            <input type="text" value="<?php echo DateUtility::humanReadableDateFormat($_SESSION['eidData']['child_started_cotrim_date']); ?>" class="form-control date" name="childStartedCotrimDate" id="childStartedCotrimDate" title="<?= _translate('Enter date of Initiation'); ?>" placeholder="<?= _translate('Enter date of Initiation'); ?>" />

                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:16% !important"><?= _translate('Is the child on ART?'); ?> </th>
                                        <td style="width:30% !important">
                                            <select class="form-control" name="infantArtStatus" id="infantArtStatus">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value="yes" <?php echo (isset($_SESSION['eidData']['infant_art_status']) && !empty($_SESSION['eidData']['infant_art_status']) && $_SESSION['eidData']['infant_art_status'] == 'yes') ? 'selected="selected"' : ''; ?>> <?= _translate('Yes'); ?> </option>
                                                <option value="no" <?php echo (isset($_SESSION['eidData']['infant_art_status']) && !empty($_SESSION['eidData']['infant_art_status']) && $_SESSION['eidData']['infant_art_status'] == 'no') ? 'selected="selected"' : ''; ?>> <?= _translate('No'); ?> </option>
                                            </select>
                                        </td>
                                        <th scope="row" style="width:16% !important"><?= _translate('If Yes, Date of Initiation'); ?> </th>
                                        <td style="width:30% !important">
                                            <input type="text" value="<?php echo DateUtility::humanReadableDateFormat($_SESSION['eidData']['child_started_art_date']); ?>" class="form-control date" name="childStartedArtDate" id="childStartedArtDate" title="<?= _translate('Enter date of Initiation'); ?>" placeholder="<?= _translate('Enter date of Initiation'); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate('Stopped breastfeeding ?'); ?></th>
                                        <td>
                                            <select class="form-control" name="hasInfantStoppedBreastfeeding" id="hasInfantStoppedBreastfeeding">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value="yes" <?php echo (isset($_SESSION['eidData']['has_infant_stopped_breastfeeding']) && !empty($_SESSION['eidData']['has_infant_stopped_breastfeeding']) && $_SESSION['eidData']['has_infant_stopped_breastfeeding'] == 'yes') ? 'selected="selected"' : ''; ?>> <?= _translate('Yes'); ?> </option>
                                                <option value="no" <?php echo (isset($_SESSION['eidData']['has_infant_stopped_breastfeeding']) && !empty($_SESSION['eidData']['has_infant_stopped_breastfeeding']) && $_SESSION['eidData']['has_infant_stopped_breastfeeding'] == 'yes') ? 'selected="selected"' : ''; ?>> <?= _translate('No'); ?> </option>
                                                <option value="unknown" <?php echo (isset($_SESSION['eidData']['has_infant_stopped_breastfeeding']) && !empty($_SESSION['eidData']['has_infant_stopped_breastfeeding']) && $_SESSION['eidData']['has_infant_stopped_breastfeeding'] == 'yes') ? 'selected="selected"' : ''; ?>> <?= _translate('Unknown'); ?> </option>
                                            </select>
                                        </td>
                                        <th scope="row"><?= _translate('Age (months) breastfeeding stopped'); ?> </th>
                                        <td>
                                            <input type="number" value="<?php echo $_SESSION['eidData']['age_breastfeeding_stopped_in_months'] ?? ''; ?>" class="form-control" style="max-width:200px;display:inline;" placeholder="<?= _translate('Age (months) breastfeeding stopped'); ?>" type="text" name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" />
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><?= _translate('Requesting Clinician Name'); ?> <i class="fa fa-pen-to-square" aria-hidden="true"></i></th>
                                        <td> <select value="<?php echo $_SESSION['eidData']['clinician_name'] ?? null; ?>" class="form-control editableSelectClinician" id="clinicianName" name="clinicianName" placeholder="<?= _translate('Requesting Clinician Name'); ?>" title="<?= _translate('Please enter request clinician'); ?>">
                                                <option value=""> --Choose Request Clinician--</option>
                                            </select>
                                        </td>

                                    </tr>
                                    <tr>
                                        <th><?= _translate('Previous Results'); ?></th>
                                    </tr>
                                    <tr>
                                        <td style="text-align:center;" scope="row"><?= _translate('Serological Test'); ?> </td>
                                        <td colspan="2" style="text-align:center;">
                                            <input type="radio" class="form-check" name="serologicalTest" id="serologicalTest" value="positive" <?php echo (isset($_SESSION['eidData']['serological_test']) && !empty($_SESSION['eidData']['serological_test']) && $_SESSION['eidData']['serological_test'] == 'positive') ? 'selected="selected"' : ''; ?> />&nbsp;&nbsp;<label for="positive"><?= _translate('Positive'); ?></label>&nbsp;&nbsp;&nbsp;
                                            <input type="radio" class="form-check" name="serologicalTest" id="serologicalTest" value="negative" <?php echo (isset($_SESSION['eidData']['serological_test']) && !empty($_SESSION['eidData']['serological_test']) && $_SESSION['eidData']['serological_test'] == 'negative') ? 'selected="selected"' : ''; ?> />&nbsp;&nbsp;<label for="negative"><?= _translate('Negative'); ?>&nbsp;&nbsp;&nbsp;
                                                <input type="radio" class="form-check" name="serologicalTest" id="serologicalTest" value="notdone" <?php echo (isset($_SESSION['eidData']['serological_test']) && !empty($_SESSION['eidData']['serological_test']) && $_SESSION['eidData']['serological_test'] == 'notdone') ? 'selected="selected"' : ''; ?> />&nbsp;&nbsp;<label for="notdone"><?= _translate('Not Done'); ?>&nbsp;&nbsp;&nbsp;
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="text-align:center;" scope="row"><?= _translate('Previous PCR Tests'); ?> <br><br>PCR 1<br><br><br>PCR2<br><br><br>PCR 3</td>
                                        <td>
                                            <?= _translate('Date of sample collection'); ?><br> <br>
                                            <input class="form-control date" type="text" value="<?php echo DateUtility::humanReadableDateFormat($_SESSION['eidData']['pcr_1_test_date']); ?>" name="pcr1TestDate" id="pcr1TestDate" placeholder="<?= _translate('Test date'); ?>" /><br>
                                            <input class="form-control date" type="text" value="<?php echo DateUtility::humanReadableDateFormat($_SESSION['eidData']['pcr_21_test_date']); ?>" name="pcr2TestDate" id="pcr2TestDate" placeholder="<?= _translate('Test date'); ?>" /><br>
                                            <input class="form-control date" type="text" value="<?php echo DateUtility::humanReadableDateFormat($_SESSION['eidData']['pcr_3_test_date']); ?>" name="pcr3TestDate" id="pcr3TestDate" placeholder="<?= _translate('Test date'); ?>" />
                                        </td>
                                        <td>
                                            <?= _translate('Results'); ?><br><br>
                                            <input type="text" class="form-control input-sm" value="<?php echo $_SESSION['eidData']['pcr_1_test_result']; ?>" name="pcr1TestResult" id="pcr1TestResult" /><br>
                                            <input type="text" class="form-control input-sm" value="<?php echo $_SESSION['eidData']['pcr_2_test_result']; ?>" name="pcr2TestResult" id="pcr1TestResult" /><br>
                                            <input type="text" class="form-control input-sm" value="<?php echo $_SESSION['eidData']['pcr_3_test_result']; ?>" name="pcr3TestResult" id="pcr1TestResult" /><br>

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
                                                <option value="1st Test for well child born of HIV+ mother" <?php echo (isset($_SESSION['eidData']['sample_collection_reason']) && !empty($_SESSION['eidData']['sample_collection_reason']) && $_SESSION['eidData']['sample_collection_reason'] == '1st Test for well child born of HIV+ mother') ? 'selected="selected"' : ''; ?>><?= _translate('1st Test for well child born of HIV+ mother'); ?></option>
                                                <option value="1st Test for sick child" <?php echo (isset($_SESSION['eidData']['sample_collection_reason']) && !empty($_SESSION['eidData']['sample_collection_reason']) && $_SESSION['eidData']['sample_collection_reason'] == '1st Test for sick child') ? 'selected="selected"' : ''; ?>><?= _translate('1st Test for sick child'); ?></option>
                                                <option value="Repeat Testing for 6 weeks after weaning" <?php echo (isset($_SESSION['eidData']['sample_collection_reason']) && !empty($_SESSION['eidData']['sample_collection_reason']) && $_SESSION['eidData']['sample_collection_reason'] == 'Repeat Testing for 6 weeks after weaning') ? 'selected="selected"' : ''; ?>><?= _translate('Repeat Testing for 6 weeks after weaning'); ?></option>
                                                <option value="Repeat Testing due to loss of 1st sample" <?php echo (isset($_SESSION['eidData']['sample_collection_reason']) && !empty($_SESSION['eidData']['sample_collection_reason']) && $_SESSION['eidData']['sample_collection_reason'] == 'Repeat Testing due to loss of 1st sample') ? 'selected="selected"' : ''; ?>><?= _translate('Repeat Testing due to loss of 1st sample'); ?></option>
                                                <option value="Repeat due to clinical suspicion following negative 1st test" <?php echo (isset($_SESSION['eidData']['sample_collection_reason']) && !empty($_SESSION['eidData']['sample_collection_reason']) && $_SESSION['eidData']['sample_collection_reason'] == 'Repeat due to clinical suspicion following negative 1st test') ? 'selected="selected"' : ''; ?>><?= _translate('Repeat due to clinical suspicion following negative 1st test'); ?></option>
                                            </select>
                                        </td>
                                        <th scope="row"><?= _translate('Point of Entry'); ?></th>
                                        <td>
                                            <select class="form-control" name="labTestingPoint" id="labTestingPoint" onchange="showTestingPointOther();">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value="PMTCT(PT)" <?php echo (isset($_SESSION['eidData']['lab_testing_point']) && !empty($_SESSION['eidData']['lab_testing_point']) && $_SESSION['eidData']['lab_testing_point'] == 'PMTCT(PT)') ? 'selected="selected"' : ''; ?>><?= _translate('PMTCT(PT)'); ?></option>
                                                <option value="IWC(IC)" <?php echo (isset($_SESSION['eidData']['lab_testing_point']) && !empty($_SESSION['eidData']['lab_testing_point']) && $_SESSION['eidData']['lab_testing_point'] == 'IWC(IC)') ? 'selected="selected"' : ''; ?>> <?= _translate('IWC(IC)'); ?> </option>
                                                <option value="Hospitalization (HO)" <?php echo (isset($_SESSION['eidData']['lab_testing_point']) && !empty($_SESSION['eidData']['lab_testing_point']) && $_SESSION['eidData']['lab_testing_point'] == 'Hospitalization (HO)') ? 'selected="selected"' : ''; ?>> <?= _translate('Hospitalization (HO)'); ?>' </option>
                                                <option value="Consultation (CS)" <?php echo (isset($_SESSION['eidData']['lab_testing_point']) && !empty($_SESSION['eidData']['lab_testing_point']) && $_SESSION['eidData']['lab_testing_point'] == 'Consultation (CS)') ? 'selected="selected"' : ''; ?>> <?= _translate('Consultation (CS)'); ?> </option>
                                                <option value="EPI(PE)" <?php echo (isset($_SESSION['eidData']['lab_testing_point']) && !empty($_SESSION['eidData']['lab_testing_point']) && $_SESSION['eidData']['lab_testing_point'] == 'EPI(PE)') ? 'selected="selected"' : ''; ?>> <?= _translate('EPI(PE)'); ?> </option>
                                                <option value="other"><?= _translate('Other'); ?></option>
                                            </select>
                                            <input type="text" name="labTestingPointOther" id="labTestingPointOther" class="form-control" title="<?= _translate('Please specify other point of entry') ?>" placeholder="<?= _translate('Please specify other point of entry') ?>" style="display:none;" />
                                        </td>
                                    </tr>
                                    <?php if ($general->isLISInstance()) { ?>

                                        <tr>
                                            <th scope="row"><label for=""><?= _translate('Lab Assigned Code'); ?> </label></th>
                                            <td>
                                                <input type="text" value="<?php echo $_SESSION['eidData']['lab_assigned_code']; ?>" class="form-control" id="labAssignedCode" name="labAssignedCode" placeholder="<?= _translate("Enter Lab Assigned Code"); ?>" title="Enter Lab Assigned Code" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" onblur='checkNameValidation("form_eid","lab_assigned_code",this,null,"<?php echo _translate("The Lab Assigned Code that you entered already exists.Enter another Lab Assigned Code"); ?>",null)' />
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
                                            <input class="form-control dateTime isRequired" value="<?php if (isset($_SESSION['eidData']['sample_collection_date']) && !empty($_SESSION['eidData']['sample_collection_date'])) {
                                                    echo DateUtility::humanReadableDateFormat($_SESSION['eidData']['sample_collection_date'], true);
                                                } ?>" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="<?= _translate('Sample Collection Date'); ?>" onchange="generateSampleCode(); checkCollectionDate(this.value);" />
                                            <span class="expiredCollectionDate" style="color:red; display:none;"></span>
                                        </td>
                                        <th scope="row" style="width:15% !important" class="labels">Sample Type <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose specimen type" style="width:100%">
                                                <?php echo $general->generateSelectOptions($specimenTypeResult, $_SESSION['eidData']['specimen_type'], '-- Select --'); ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important;"><?= _translate('Recollected'); ?></th>
                                        <td style="width:35% !important;">
                                            <select class="form-control" name="isSampleRecollected" id="isSampleRecollected">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value="yes" <?php echo (isset($_SESSION['eidData']['is_sample_recollected']) && !empty($_SESSION['eidData']['is_sample_recollected']) && $_SESSION['eidData']['is_sample_recollected'] == 'yes') ? "selected='selected'" : ""; ?>> <?= _translate('Yes'); ?> </option>
                                                <option value="no" <?php echo (isset($_SESSION['eidData']['is_sample_recollected']) && !empty($_SESSION['eidData']['is_sample_recollected']) && $_SESSION['eidData']['is_sample_recollected'] == 'no') ? "selected='selected'" : ""; ?>> <?= _translate('No'); ?> </option>
                                            </select>
                                        </td>
                                        <th scope="row"><?= _translate('Name of health personnel'); ?></th>
                                        <td>
                                            <input value="<?php echo $_SESSION['eidData']['sample_requestor_name'] ?? null; ?>" class="form-control" type="text" name="sampleRequestorName" id="sampleRequestorName" placeholder="<?= _translate('Requesting Officer'); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _translate('Contact Number'); ?></th>
                                        <td>
                                            <input value="<?php echo $_SESSION['eidData']['sample_requestor_phone'] ?? null; ?>" class="form-control phone-number" type="text" name="sampleRequestorPhone" id="sampleRequestorPhone" maxlength="<?= $maxPhoneLength; ?>" placeholder="<?= _translate('Requesting Officer Phone'); ?>" />
                                        </td>
                                        <?php if ($general->isLISInstance()) { ?>
                                            <th scope="row"><label for=""><?= _translate('Sample Received Date'); ?> </label></th>
                                            <td>
                                                <input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate("Sample Reception Date"); ?>" title="<?= _translate("Please enter Sample Reception Date"); ?>" value="<?php if (isset($_SESSION['eidData']['sample_received_at_lab_datetime']) && !empty($_SESSION['eidData']['sample_received_at_lab_datetime'])) {
                                                    echo DateUtility::humanReadableDateFormat($_SESSION['eidData']['sample_received_at_lab_datetime'], true);
                                                } ?>" onchange="" style="width:100%;" />
                                            </td>
                                        <?php } ?>
                                    </tr>
                                </table>

                            </div>
                        </div>
                        <?php if (_isAllowed('/eid/results/eid-manual-results.php') && $_SESSION['accessType'] != 'collection-site') { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title"><?= _translate('Reserved for Laboratory Use'); ?> </h3>
                                    </div>
                                    <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                        <tr>
                                            <th><?= _translate('EID Number'); ?> </th>
                                            <td>
                                                <input name="eidNumber" id="eidNumber" class="form-control" placeholder="<?= _translate('Enter EID Number'); ?>" title="<?= _translate('Please enter EID Number'); ?>">
                                            </td>
                                            <th><?= _translate('Testing Platform'); ?> </th>
                                            <td>
                                                <select name="eidPlatform" id="eidPlatform" class="form-control" title="<?= _translate('Please choose VL Testing Platform'); ?>" <?php echo $labFieldDisabled; ?> onchange="hivDetectionChange();">
                                                    <?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?= _translate('Is Sample Rejected?'); ?></th>
                                            <td>
                                                <select class="form-control" name="isSampleRejected" id="isSampleRejected">
                                                    <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                    <option value="yes"> <?= _translate('Yes'); ?> </option>
                                                    <option value="no"> <?= _translate('No'); ?> </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="show-rejection rejected" style="display:none;">
                                            <th scope="row" class="rejected" style="display: none;"><?= _translate('Reason for Rejection'); ?></th>
                                            <td class="rejected" style="display: none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="<?= _translate('Please choose reason for rejection'); ?>">
                                                    <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                    <?php echo $rejectionReason; ?>
                                                </select>
                                            </td>
                                            <td><label for="rejectionDate"><?= _translate('Rejection Date'); ?></label><span class="mandatory">*</span></td>
                                            <td><input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="<?= _translate('Select Rejection Date'); ?>" /></td>
                                        </tr>
                                        <tr>
                                            <td style="width:25%;"><label for=""><?= _translate('Sample Test Date'); ?> </label></td>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control dateTime" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _translate("Please enter date"); ?>" title="<?= _translate("Please enter date"); ?>" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
                                            </td>


                                            <th scope="row"><?= _translate('Result'); ?></th>
                                            <td>
                                                <select class="form-control" name="result" id="result">
                                                    <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                    <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                        <option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?= _translate('Reviewed On'); ?></th>
                                            <td><input type="text" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control" placeholder="<?= _translate('Reviewed on'); ?>" title="<?= _translate('Please enter the Reviewed on'); ?>" /></td>
                                            <th scope="row"><?= _translate('Reviewed By'); ?></th>
                                            <td>
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="<?= _translate('Please choose reviewed by'); ?>" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?= _translate('Approved On'); ?></th>
                                            <td><input type="text" name="approvedOnDateTime" id="approvedOnDateTime" class="dateTime disabled-field form-control" placeholder="<?= _translate('Approved on'); ?>" title="<?= _translate('Please enter the Approved on'); ?>" /></td>
                                            <th scope="row"><?= _translate('Approved By'); ?></th>
                                            <td>
                                                <select name="approvedBy" id="approvedBy" class="select2 form-control" title="<?= _translate('Please choose approved by'); ?>" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
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
                        <?php if ($arr['eid_sample_code'] == 'auto' || $arr['eid_sample_code'] == 'YY' || $arr['eid_sample_code'] == 'MMYY') { ?>
                            <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                            <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                            <input type="hidden" name="saveNext" id="saveNext" />
                            <!-- <input type="hidden" name="pageURL" id="pageURL" value="<?php echo htmlspecialchars((string) $_SERVER['PHP_SELF']); ?>" /> -->
                        <?php } ?>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();$('#saveNext').val('next');return false;">Save and Next</a>
                        <input type="hidden" name="formId" id="formId" value="7" />
                        <input type="hidden" name="eidSampleId" id="eidSampleId" value="" />
                        <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
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

    function checkPatientDetails(tableName, fieldName, obj, fnct) {
        //if ($.trim(obj.value).length == 10) {
        if ($.trim(obj.value) != '') {
            $.post("/includes/checkDuplicate.php", {
                    tableName: tableName,
                    fieldName: fieldName,
                    value: obj.value,
                    fnct: fnct,
                    format: "html"
                },
                function(data) {
                    if (data === '1') {
                        showModal('patientModal.php?artNo=' + obj.value, 900, 520);
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
                    testType: 'eid'
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
            $("#facilityId").html("<?php echo $facility; ?>");
            $("#facilityId").select2("val", "");
            $("#district").html("<option value=''> -- Select -- </option>");
        }
        $.unblockUI();
    }

    function generateSampleCode() {
        var pName = $("#province").val();
        var sDate = $("#sampleCollectionDate").val();
        if (pName != '' && sDate != '') {
            $.post("/eid/requests/generateSampleCode.php", {
                    sampleCollectionDate: sDate,
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
                    testType: 'eid'
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

        clearDatePlaceholderValues('input.date, input.dateTime');


        flag = deforayValidator.init({
            formId: 'addEIDRequestForm'
        });
        if (flag) {
            $('.btn-disabled').attr('disabled', 'yes');
            $(".btn-disabled").prop("onclick", null).off("click");
            $.blockUI();
            <?php
            if ($arr['eid_sample_code'] == 'auto' || $arr['eid_sample_code'] == 'YY' || $arr['eid_sample_code'] == 'MMYY') {
            ?>
                insertSampleCode('addEIDRequestForm', 'eidSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 3, 'sampleCollectionDate');
            <?php
            } else {
            ?>
                document.getElementById('addEIDRequestForm').submit();
            <?php
            } ?>
        }
    }

    function setRelatedField(pcrVal) {
        if (pcrVal == 'yes') {
            $('#previousPCRTestDate').addClass('isRequired');
            $('#pcrTestReason').addClass('isRequired');
            $('#previousPCRTestDate').prop('disabled', false);
            $('#pcrTestReason').prop('disabled', false);
        } else {
            $('#previousPCRTestDate').prop('disabled', true);
            $('#pcrTestReason').prop('disabled', true);
            $('#previousPCRTestDate').removeClass('isRequired');
            $('#pcrTestReason').removeClass('isRequired');
        }
    }

    function fillFacilityDetails() {
        $.blockUI();
        //check facility name

        $.unblockUI();
        $("#facilityCode").val($('#facilityId').find(':selected').data('code'));

    }

    function showOtherARV() {
        arv = $("#childProphylacticArv").val();
        if (arv == "other") {
            $("#childProphylacticArvOther").show();
            $("#childProphylacticArvOther").addClass('isRequired');
        } else {
            $("#childProphylacticArvOther").removeClass('isRequired');
            $("#childProphylacticArvOther").hide();
        }
    }

    function showArvProtocolOtherOption() {
        arvMother = $("#motherArvProtocol").val();
        if (arvMother == "other") {
            $("#motherArvProtocolOther").show();
            $("#motherArvProtocolOther").addClass('isRequired');
        } else {
            $("#motherArvProtocolOther").removeClass('isRequired');
            $("#motherArvProtocolOther").hide();
        }
    }

    function showTestingPointOther() {
        entryPoint = $("#labTestingPoint").val();
        if (entryPoint == "other") {
            $("#labTestingPointOther").show();
            $("#labTestingPointOther").addClass('isRequired');
        } else {
            $("#labTestingPointOther").removeClass('isRequired');
            $("#labTestingPointOther").hide();
        }
    }

    function updateAgeInfo() {
        var isChecked = $("#ageUnreported").is(":checked");
        if (isChecked == true) {
            $("#childDob").val("");
            $("#childAge").val("");
            $('#childDob').prop('readonly', true);
            $('#childAge').prop('readonly', true);
            $('#childDob').removeClass('isRequired');

        } else {
            $('#childDob').prop('readonly', false);
            $('#childAge').prop('readonly', false);
            $('#childDob').addClass('isRequired');
        }
    }

    $(document).ready(function() {
        editableSelectClinician('clinicianName', 'clinician_name', 'form_eid');

        Utilities.autoSelectSingleOption('facilityId');
        $("#sampleCollectionDate").trigger('change');
        $("#childId").on('input', function() {
            $.post("/common/patient-last-request-details.php", {
                    patientId: $.trim($(this).val()),
                    testType: 'eid'
                },
                function(data) {
                    if (data != "0") {
                        obj = $.parseJSON(data);
                        $(".artNoGroup").html('<small style="color:red">No. of times Test Requested for this Patient : ' + obj.no_of_req_time +
                            '<br>Last Test Request Added On LIS/STS : ' + obj.request_created_datetime +
                            '<br>Sample Collection Date for Last Request : ' + obj.sample_collection_date +
                            '<br>Total No. of times Patient tested for EID : ' + obj.no_of_tested_time +
                            '</small>');
                    } else {
                        $(".artNoGroup").html('');
                    }
                });

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
        $('#labId').select2({
            placeholder: "<?= _translate('Select Lab Name'); ?>"
        });
        $('#reviewedBy').select2({
            placeholder: "<?= _translate('Select Reviewed By'); ?>"
        });
        $('#approvedBy').select2({
            placeholder: "<?= _translate('Select Approved By'); ?>"
        });

        $('#isChildOnCotrim').change(function() {
            if ($(this).val() == "no") {
                $('#childStartedCotrimDate').val('');
                $('#childStartedCotrimDate').prop('disabled', true);
            } else {
                $('#childStartedCotrimDate').prop('disabled', false);
            }
        });

        $('#infantArtStatus').change(function() {
            if ($(this).val() == "no") {
                $('#childStartedArtDate').val('');
                $('#childStartedArtDate').prop('disabled', true);
            } else {
                $('#childStartedArtDate').prop('disabled', false);
            }
        });
        <?php if (isset($cpyReq) && !empty($cpyReq) && $cpyReq == 'yes') {
            unset($_SESSION['vlData']); ?>
            getfacilityProvinceDetails($('#facilityId'));
        <?php } ?>
    });

    function editableSelectClinician(id, _fieldName, table, _placeholder) {
        $("#" + id).select2({
            placeholder: _placeholder,
            minimumInputLength: 0,
            width: '100%',
            allowClear: true,
            id: function(bond) {
                return bond._id;
            },
            ajax: {
                placeholder: "<?= _translate("Type one or more character to search", escapeTextOrContext: true); ?>",
                url: "/includes/get-data-list-for-generic.php",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        fieldName: _fieldName,
                        tableName: table,
                        q: params.term, // search term
                        page: params.page,
                        facilityId: $("#facilityId").val(),
                    };
                },
                processResults: function(data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.result,
                        pagination: {
                            more: (params.page * 30) < data.total_count
                        }
                    };
                },
                //cache: true
            },
            escapeMarkup: function(markup) {
                return markup;
            }
        });
    }

    function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
        var removeDots = obj.value.replace(/\./g, "");
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


    $('#addEIDRequestForm').keypress((e) => {
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