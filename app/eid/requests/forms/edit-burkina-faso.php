<?php

use App\Registries\ContainerRegistry;
use App\Services\EidService;
use App\Utilities\DateUtility;

$eidObj = ContainerRegistry::get(EidService::class);
$eidResults = $eidObj->getEidResults();
$labFieldDisabled = '';

$specimenTypeResult = $eidObj->getEidSampleTypes();

$rKey = '';
$sKey = '';
$sFormat = '';
//$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['accessType'] == 'collection-site') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    $rKey = 'R';
    if (!empty($eidInfo['remote_sample']) && $eidInfo['remote_sample'] == 'yes') {
        $sampleCode = 'remote_sample_code';
    } else {
        $sampleCode = 'sample_code';
    }
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
        <h1><em class="fa-solid fa-pen-to-square"></em> <?= _translate("EARLY INFANT DIAGNOSIS (EID) LABORATORY REQUEST FORM");?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home");?></a></li>
            <li class="active"><?= _translate("Add EID Request");?></li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate("indicates required
                    field");?> &nbsp;</div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->
                <form class="form-horizontal" method="post" name="addEIDRequestForm" id="addEIDRequestForm" autocomplete="off" action="eid-add-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><?= _translate("SITE INFORMATION");?></h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;"><?= _translate("To be filled by requesting
                                        Clinician/Nurse");?></h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <?php if ($_SESSION['instance']['type'] == 'remoteuser') { ?>
                                            <td><label for="sampleCode"><?= _translate('Sample ID'); ?> </label></td>
                                            <td>
                                                <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?php echo $eidInfo[$sampleCode]; ?></span>
                                                <input type="hidden" class="<?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" value="<?php echo $eidInfo[$sampleCode]; ?>" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode"><?= _translate('Sample ID'); ?> </label><span class="mandatory">*</span></td>
                                            <td>
                                                <input type="text" readonly value="<?php echo $eidInfo[$sampleCode]; ?>" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="<?= _translate('Sample ID'); ?>" title="<?= _translate('Please enter sample id'); ?>" style="width:100%;" onchange="" />
                                            </td>
                                        <?php } ?>
                                        <td class="labels" style="width:15%"><label for="province"><?= _translate("Health Facility/POE State");?> </label><span class="mandatory">*</span></td>
                                        <td style="width:35%">
                                            <select class="form-control isRequired" name="province" id="province" title="Please choose State" onchange="getfacilityDetails(this);" style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="labels" style="width:15%"><label for="district"><?= _translate("Health Facility/POE County");?>
                                            </label><span class="mandatory">*</span></td>
                                        <td style="width:35%">
                                            <select class="form-control isRequired" name="district" id="district" title="Please choose County" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                                                <option value=""> -- Select -- </option>
                                            </select>
                                        </td>
                                        <td class="labels" style="width:15%"><label for="facilityId"><?= _translate("Health Facility/POE");?> </label><span class="mandatory">*</span></td>
                                        <td style="width:35%">
                                            <select class="form-control isRequired " name="facilityId" id="facilityId" title="Please choose facility" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
                                                <?php echo $facility; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="labels" style="width:15%"><label for="supportPartner"><?= _translate("Implementing Partner");?> </label></td>
                                        <td style="width:35%">
                                            <select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose partenaire de mise en œuvre" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php foreach ($implementingPartnerList as $implementingPartner) { ?>
                                                    <option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>" <?php echo ($implementingPartner['i_partner_id'] == $eidInfo['implementing_partner']) ? 'selected="selected"' : ''; ?>>
                                                        <?= $implementingPartner['i_partner_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td class="labels" style="width:15%"><label for="fundingSource"><?= _translate("Funding Partner");?></label></td>
                                        <td style="width:35%">
                                            <select class="form-control" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php foreach ($fundingSourceList as $fundingSource) { ?>
                                                    <option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $eidInfo['funding_source']) ? 'selected="selected"' : ''; ?>><?= $fundingSource['funding_source_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="labels" style="width:15%"><label for="labId"><?= _translate("Testing Laboratory");?> <span class="mandatory">*</span></label> </td>
                                        <td style="width:35%">
                                            <select name="labId" id="labId" class="select2 form-control isRequired" title="Please select the Testing Laboratory" style="width:100%;">
                                                <?= $general->generateSelectOptions($testingLabs, $eidInfo['lab_id'], '-- Select --'); ?>
                                            </select>
                                        </td>
                                        <th scope="row" style="width:15%"><?= _translate('Requesting Clinician Name'); ?></th>
                                        <td style="width:35%"> <input type="text" value="<?php echo $eidInfo['clinician_name'] ?? null;?>" class="form-control" id="clinicianName" name="clinicianName" placeholder="Requesting Clinician Name" title="Please enter request clinician" /></td>
                                    </tr>
                                </table>
                                <br>
                                <hr style="border: 1px solid #ccc;">

                                <div class="box-header with-border">
                                    <h3 class="box-title"><?= _translate("CHILD and MOTHER INFORMATION");?></h3>
                                    <h3 class="box-title"><?= _translate("Patient Information");?></h3>&nbsp;&nbsp;&nbsp;
                                    <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="" placeholder="Enter Infant ID or Infant Name" title="Enter art number or patient name" />&nbsp;&nbsp;
                                    <a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList($('#artPatientNo').val(),0);"><em class="fa-solid fa-magnifying-glass"></em><?= _translate("Search");?></a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;<?= _translate("No Patient Found");?></strong></span>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr class="encryptPIIContainer">
                                        <th scope="row" style="width:15%"><label for="encryptPII"><?= _translate('Encrypt PII'); ?> </label></th>
                                        <td style="width:35%">
                                            <select name="encryptPII" id="encryptPII" class="form-control" title="<?= _translate('Encrypt Patient Identifying Information'); ?>">
                                                <option value=""><?= _translate('--Select--'); ?></option>
                                                <option value="no" <?php echo ($eidInfo['is_encrypted'] == "no") ? 'selected="selected"' : ''; ?>><?= _translate('No'); ?></option>
                                                <option value="yes" <?php echo ($eidInfo['is_encrypted'] == "yes") ? 'selected="selected"' : ''; ?>><?= _translate('Yes'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15%" class="labels"><label for="childId"><?= _translate("Infant Code");?> <span class="mandatory">*</span> </label></th>
                                        <td style="width:35%">
                                            <input type="text" class="form-control isRequired patientId" id="childId" name="childId" placeholder="Infant Identification (Patient)" title="Please enter Exposed Infant Identification" value="<?php echo $eidInfo['child_id'] ?? null;?>" style="width:100%;" oninput="showPatientList($(this).val(), 1500);" />
                                        </td>
                                        <th scope="row" style="width:15%" class="labels"><label for="childName"><?= _translate("Infant name");?> </label></th>
                                        <td style="width:35%">
                                            <input type="text" class="form-control" value="<?php echo $eidInfo['child_name'] ?? null;?>" id="childName" name="childName" placeholder="Infant name" title="Please enter Infant Name" style="width:100%;"  />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15%" scope="row" class="labels"><label for="childDob"><?= _translate("Date of Birth");?> </label></th>
                                        <td style="width:35%">
                                            <input type="text" class="form-control date" value="<?php echo $eidInfo['child_dob'] ?? null;?>" id="childDob" name="childDob" placeholder="Date of birth" title="Please enter Date of birth" style="width:100%;" onchange="calculateAgeInMonths();" />
                                        </td>
                                        <th style="width:15%" scope="row" class="labels"><label for="childGender"><?= _translate("Gender");?> <span class="mandatory">*</span> </label></th>
                                        <td style="width:35%">
                                            <select class="form-control isRequired" name="childGender" id="childGender">
                                                <option value=''> -- Select -- </option>
                                                <option value='male' <?php echo ($eidInfo['child_gender'] == "male") ? 'selected="selected"' : ''; ?>> <?= _translate("Male");?> </option>
                                                <option value='female' <?php echo ($eidInfo['child_gender'] == "female") ? 'selected="selected"' : ''; ?>> <?= _translate("Female");?> </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15%" scope="row" class="labels"><?= _translate("Infant Age (months)");?> <span class="mandatory">*</span></th>
                                        <td style="width:35%"><input value="<?php echo $eidInfo['child_age'] ?? null;?>" type="number" step=".1" max="60" maxlength="4" class="form-control isRequired" id="childAge" name="childAge" placeholder="Age" title="Age" style="width:100%;"  /></td>
                                        <th style="width:15%" scope="row" class="labels"><?= _translate("Mother ART Number");?></th>
                                        <td style="width:35%"><input value="<?php echo $eidInfo['mother_id'] ?? null;?>" type="text" class="form-control " id="mothersId" name="mothersId" placeholder="Mother ART Number" title="Mother ART Number" style="width:100%;"  /></td>
                                    </tr>
                                    <tr>
                                        <th style="width:15%" scope="row" class="labels"><?= _translate("Caretaker phone number");?></th>
                                        <td style="width:35%"><input type="text" value="<?php echo $eidInfo['caretaker_phone_number'] ?? null;?>" class="form-control phone-number" id="caretakerPhoneNumber" name="caretakerPhoneNumber" placeholder="Caretaker Phone Number" title="Caretaker Phone Number" style="width:100%;"  /></td>

                                        <th style="width:15%" scope="row" class="labels"><?= _translate("Infant caretaker address");?></th>
                                        <td style="width:35%"><textarea class="form-control " id="caretakerAddress" name="caretakerAddress" placeholder="Caretaker Address" title="Caretaker Address" style="width:100%;" ><?php echo $eidInfo['caretaker_address'] ?? null;?></textarea></td>
                                    </tr>
                                </table>
                                <br>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th scope="row" colspan=4 style="border-top:#ccc 2px solid;">
                                            <h4><?= _translate("Infant and Mother's Health Information");?></h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15%" class="labels"><?= _translate("Mother Alive");?>:</th>
                                        <td style="width:35%">
                                            <select class="form-control" name="mothersHIVStatus" id="mothersHIVStatus" title="Please select the Alive or not" style="width:100%;">
                                                <option value=''> -- Select -- </option>
                                                <option value="no" <?php echo ($eidInfo['mother_hiv_status'] == "no") ? 'selected="selected"' : ''; ?>><?= _translate('No'); ?></option>
                                                <option value="yes" <?php echo ($eidInfo['mother_hiv_status'] == "yes") ? 'selected="selected"' : ''; ?>><?= _translate('Yes'); ?></option>
                                            </select>
                                        </td>
                                        <th scope="row" style="width:15%"><?= _translate('Mode of Delivery'); ?> </th>
                                        <td style="width:35%">
                                            <select class="form-control" name="modeOfDelivery" id="modeOfDelivery" style="width:100%;">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value="Normal" <?php echo ($eidInfo['mode_of_delivery'] == "Normal") ? 'selected="selected"' : ''; ?>> <?= _translate('Normal'); ?> </option>
                                                <option value="Caesarean" <?php echo ($eidInfo['mode_of_delivery'] == "Caesarean") ? 'selected="selected"' : ''; ?>> <?= _translate('Caesarean'); ?> </option>
                                                <option value="Unknown" <?php echo ($eidInfo['mode_of_delivery'] == "Unknown") ? 'selected="selected"' : ''; ?>> <?= _translate('Gravidity N*'); ?>' </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15%" class="labels"><label for="mothersDob"><?= _translate("Mother DOB");?> </label></th>
                                        <td style="width:35%">
                                            <input type="text" value="<?php echo $eidInfo['mother_dob'] ?? null;?>" class="form-control date" id="mothersDob" name="mothersDob" placeholder="Date of birth" title="Please enter Date of birth" style="width:100%;" onchange="calculateAgeInYears('mothersDob', 'motherAgeInYears');" style="width:100%;"/>
                                        </td>
                                        <th scope="row" style="width:15%" class="labels"><label for="motherAgeInYears"><?= _translate("Mother Age");?> <span class="mandatory">*</span></label></th>
                                        <td style="width:35%"><input value="<?php echo $eidInfo['mother_age_in_years'] ?? null;?>" type="number" step=".1" max="60" maxlength="4" class="form-control isRequired" id="motherAgeInYears" name="motherAgeInYears" placeholder="Enter mother age" title="Please enter mother age" style="width:100%;"  /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15%" class="labels"><?= _translate("Infant On PMTCT Prophylaxis");?><span class="mandatory">*</span></th>
                                        <td style="width:35%">
                                            <select class="form-control isRequired" name="infantOnPMTCTProphylaxis" id="infantOnPMTCTProphylaxis" title="Please select the PMTCT" style="width:100%;">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes"> <?= _translate("Yes");?> </option>
                                                <option value="no"> <?= _translate("No");?> </option>
                                            </select>
                                        </td>
                                        <th scope="row" style="width:15%" class="labels"><?= _translate("Mother's HIV Status");?>:</th>
                                        <td style="width:35%">
                                            <select class="form-control" name="mothersHIVStatus" id="mothersHIVStatus" title="Please select the HIV Status" style="width:100%;">
                                                <option value=''> -- Select -- </option>
                                                <option value="positive"> <?= _translate("Positive");?> </option>
                                                <option value="negative"> <?= _translate("Negative");?> </option>
                                                <option value="unknown"> <?= _translate("Unknown");?> </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15%" class="labels"><?= _translate("ART given to the Mother during");?>:</th>
                                        <td style="width:35%;">
                                            <select class="form-control" multiple name="motherTreatment[]" id="motherTreatment" title="Please select ART given to the Mother during" style="width:100%;">
                                                <option value="No ART given"><?= _translate("No ART given");?></option>
                                                <option value="Pregnancy"><?= _translate("Pregnancy");?></option>
                                                <option value="Labour/Delivery"><?= _translate("Pregnancy");?></option>
                                                <option value="Postnatal"><?= _translate("Pregnancy");?></option>
                                                <option value="Unknown"><?= _translate("Unknown");?></option>
                                            </select>
                                        </td>
                                        <th scope="row" class="labels" style="width:15%;"><?= _translate("Viral Load");?></th>
										<td style="width:35%;">
											<div class="input-group">
												<input type="number" class="form-control " id="motherViralLoadCopiesPerMl" name="motherViralLoadCopiesPerMl" placeholder="Viral Load in copies/mL" title="Viral Load" style="width:100%;"  />
												<div class="input-group-addon"><?= _translate("copies/mL");?></div>
											</div>
										</td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="labels" style="width:15%;"><?= _translate("Date of HIV diagnosis"); ?></th>
                                        <td style="width:35%">
                                            <input type="text" class="form-control date" name="motherHivTestDate" id="motherHivTestDate" placeholder="<?= _translate("Enter date of Mother's Hiv Test"); ?>" title="<?= _translate("Enter date of Mother's Hiv Test"); ?>" style="width:100%;"/>
                                        </td>
                                        <th scope="row" class="labels" style="width:15%;"><?= _translate('Prophylactic ARV given to child'); ?><span class="mandatory">*</span></th>
                                        <td style="width:35%">
                                            <select class="form-control isRequired" name="childProphylacticArv" id="childProphylacticArv" title="<?= _translate('Prophylactic ARV given to child'); ?>" onchange="showOtherARV();" style="width:100%;">
                                                <option value=''> <?= _translate('-- Select --'); ?> </option>
                                                <option value='nothing'> <?= _translate('Nothing'); ?> </option>
                                                <option value='nvp'> <?= _translate('NVP'); ?> </option>
                                                <option value='azt'> <?= _translate('AZT'); ?> </option>
                                                <option value='other'> <?= _translate('Other'); ?> </option>
                                            </select>
                                            <input type="text" name="childProphylacticArvOther" id="childProphylacticArvOther" class="form-control" placeholder="<?= _translate('Please specify other prophylactic ARV given'); ?>" title="<?= _translate('Please specify other prophylactic ARV given'); ?>" style="display:none;width:100%;" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="labels" style="width:15%;"><?= _translate('Date of Initiation'); ?></th>
                                        <td style="width:35%">
                                            <input type="text" class="form-control date" name="childTreatmentInitiationDate" id="childTreatmentInitiationDate" placeholder="<?= _translate('Enter date of initiation'); ?>" style="width:100%;"/>
                                        </td>
                                        <th scope="row" class="labels" style="width:15%;"><?= _translate("Infant still breastfeeding?");?></th>
                                        <td style="width:35%">
                                            <select class="form-control" name="hasInfantStoppedBreastfeeding" id="hasInfantStoppedBreastfeeding" title="Infant breastfeeding" style="width:100%;">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes"> <?= _translate("Yes");?> </option>
                                                <option value="no"> <?= _translate("No");?> </option>
                                                <option value="unknown"> <?= _translate("Unknown");?> </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="labels" style="width:15%;"><?= _translate("Age (months) breastfeeding stopped");?> :</th>
                                        <td style="width:35%;">
                                            <input type="number" class="form-control" style="width:100%;display:inline;" placeholder="Age (months) breastfeeding stopped" type="text" name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" />
                                        </td>
                                    </tr>
                                </table>

                                <br><br>
                                <table aria-describedby="table" class="table" aria-hidden="true">
                                    <tr>
                                        <th scope="row" colspan=4 style="border-top:#ccc 2px solid;">
                                            <h4><?= _translate("Sample Information");?></h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15%" class="labels"><?= _translate("Sample Collection
                                            Date");?> <span class="mandatory">*</span> </th>
                                        <td style="width:35%;">
                                            <input class="form-control dateTime isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" onchange="generateSampleCode();" />
                                        </td>
                                        <th scope="row" style="width:15%" class="labels"><?= _translate("Sample Dispatched On");?>
                                            <span class="mandatory">*</span>
                                        </th>
                                        <td style="width:35%;">
                                            <input class="form-control dateTime isRequired" type="text" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="Sample Dispatched On" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15%" class="labels"><?= _translate("Sample Type");?> <span class="mandatory">*</span> </th>
                                        <td style="width:35%;">
                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose specimen type" style="width:100%">
                                                <?php echo $general->generateSelectOptions($specimenTypeResult, null, '-- Select --'); ?>
                                            </select>
                                        </td>
                                        <th scope="row" class="labels"><?= _translate("Requesting Officer");?></th>
                                        <td>
                                            <input class="form-control" type="text" name="sampleRequestorName" id="sampleRequestorName" placeholder="Requesting Officer" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="labels"><?= _translate("Sample Requestor Phone");?></th>
                                        <td>
                                            <input class="form-control" type="text" name="sampleRequestorPhone" id="sampleRequestorPhone" placeholder="Requesting Officer Phone" />
                                        </td>
                                        <?php if (_isAllowed('/eid/results/eid-manual-results.php') && $_SESSION['accessType'] != 'collection-site') { ?>
                                            <th scope="row" class="labels"><?= _translate("Sample Received Date (at Testing Lab)");?> <span class="mandatory">*</span></th>
                                            <td>
                                                <input type="text" class="form-control dateTime isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter sample received date" <?php echo $labFieldDisabled; ?>  style="width:100%;" />
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
                                        <h3 class="box-title" class="labels"><?= _translate("Reserved for Laboratory Use");?> </h3>
                                    </div>
                                    <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                        <tr>
                                            <th scope="row" class="labels" style="width:15%;"><?= _translate("Testing Platform");?> </th>
                                            <td style="width:35%;"><select name="eidPlatform" id="eidPlatform" class="form-control result-optional" title="Please select the testing platform">
                                                    <?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>
                                                </select>
                                            </td>

                                            <th scope="row" class="labels" style="width:15%;"><?= _translate("Machine used to test");?> </th>
                                            <td style="width:35%;"><select name="machineName" id="machineName" class="form-control result-optional" title="Please select the machine name">
                                                    <option value="">-- Select --</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" class="labels" style="width:15%;"><?= _translate("Is Sample Rejected?");?></th>
                                            <td style="width:35%;">
                                                <select class=" form-control" name="isSampleRejected" id="isSampleRejected" title="Please select if the sample is rejected or not">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="yes"> <?= _translate("Yes");?> </option>
                                                    <option value="no"> <?= _translate("No");?> </option>
                                                </select>
                                            </td>
                                            <th scope="row" style="width:15%;" class="labels"><?= _translate("Sample Test Date");?> </th>
                                            <td style="width:35%;">
                                                <input type="text" class="form-control dateTime" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter the sample tested date and time" <?php echo $labFieldDisabled; ?>  style="width:100%;" />
                                            </td>
                                        </tr>
                                        <tr class=" show-rejection rejected" style="display: none">
                                            <td class="labels" style="width:15%;"><?= _translate("Rejection Date");?><span class="mandatory">*</span></td>
                                            <td style="width:35%;"><input class="show-rejection rejected form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
                                            <th scope="row" class="rejected labels" style="display: none;width:15%;"><?= _translate("Reason for
                                                Rejection");?></th>
                                            <td style="width:35%">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="Please select the reason for sample rejection">
                                                    <option value=''> -- Select -- </option>
                                                    <?php echo $rejectionReason; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            
                                            <th scope="row" class="labels" style="width:15%;"><?= _translate("Result");?></th>
                                            <td style="width:35%;">
                                                <select class="form-control" name="result" id="result" title="Please select the test result">
                                                    <option value=''> -- Select -- </option>
                                                    <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                        <option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                            <th scope="row" class="labels" style="width:15%;"><?= _translate("Tested By");?></th>
                                            <td style="width:35%;">
                                                <select name="testedBy" id="testedBy" class="select2 form-control" title="Please choose tested by">
                                                    <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" class="labels" style="width:15%;"><?= _translate("Reviewed By");?></th>
                                            <td style="width:35%;">
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row" class="labels" style="width:15%;"><?= _translate("Reviewed on");?></th>
                                            <td style="width:35%;"><input type="text" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control" placeholder="Reviewed on" title="Please enter reviewed on" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row" class="labels" style="width:15%;"><?= _translate("Approved By");?></th>
                                            <td style="width:35%;">
                                                <select name="approvedBy" id="approvedBy" class="form-control labSection" title="Please choose approved by">
                                                    <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row" class="labels" style="width:15%;"><?= _translate("Approved On");?></th>
                                            <td style="width:35%;">
                                                <input type="text" value="<?php DateUtility::humanReadableDateFormat(DateUtility::getCurrentDateTime()); ?>" class="form-control dateTime" id="approvedOnDateTime" name="approvedOnDateTime" placeholder="<?= _translate("Please enter date"); ?>" <?php echo $labFieldDisabled; ?>style="width:100%;" title="Please select approved on" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" class="labels" style="width:15%;"><?= _translate("Results Dispatched Date");?></th>
                                            <td style="width:35%;">
                                                <input type="text" class="form-control dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatch Date" title="Please select result dispatched date" />
                                            </td>
                                            <th scope="row" class="labels" style="width:15%;"><?= _translate("Lab Tech. Comments");?> </th>
                                            <td style="width:35%;">
                                                <textarea class="form-control" id="labTechCmt" name="labTechCmt" <?php echo $labFieldDisabled; ?> style="width:100%;" placeholder="Comments from the Lab Technician " title="Please Comments from the Lab Technician "></textarea>
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
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;"><?= _translate("Save");?></a>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();$('#saveNext').val('next');return false;"><?= _translate("Save and Next");?></a>
                        <input type="hidden" name="formId" id="formId" value="1" />
                        <input type="hidden" name="eidSampleId" id="eidSampleId" value="" />
                        <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
                        <a href="/eid/requests/eid-requests.php" class="btn btn-default"> <?= _translate("Cancel");?></a>
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

    function checkPCRTestReason() {
        var otherReason = $("#pcrTestReason").val();
        if (otherReason == 'Other') {
            $(".reasonForRepeatPcrOther").show();
            $("#reasonForRepeatPcrOther").addClass("isRequired");
            $("#reasonForRepeatPcrOther").focus();
        } else {
            $(".reasonForRepeatPcrOther").hide();
            $("#reasonForRepeatPcrOther").removeClass("isRequired");
            $('#reasonForRepeatPcrOther').val("");
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

    function setPatientDetails(pDetails) {
        var patientArray = JSON.parse(pDetails);
        $("#childId").val(patientArray['child_id']);
        $("#childName").val(patientArray['name']);
        $("#childDob").val(patientArray['dob']);
        $("#childGender").val(patientArray['gender']);
        $("#childAge").val(patientArray['age']);
        $("#mothersId").val(patientArray['mother_id']);
        $("#caretakerPhoneNumber").val(patientArray['caretaker_no']);
        $("#caretakerAddress").text(patientArray['caretaker_address']);

        /* setTimeout(function() {
             $("#patientDistrict").val(patientArray[15]).trigger('change');
         }, 3000);*/
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
            formId: 'addEIDRequestForm'
        });

        if (flag) {
            // $('.btn-disabled').attr('disabled', 'yes');
            // $(".btn-disabled").prop("onclick", null).off("click");
            //$.blockUI();
            <?php if ($arr['eid_sample_code'] == 'auto' || $arr['eid_sample_code'] == 'YY' || $arr['eid_sample_code'] == 'MMYY') { ?>
                insertSampleCode('addEIDRequestForm', 'eidSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 3, 'sampleCollectionDate');
            <?php } else { ?>
                document.getElementById('addEIDRequestForm').submit();
            <?php } ?>
        }
    }

    function updateMotherViralLoad() {
        var motherVlText = $("#motherViralLoadText").val();
        if (motherVlText != '') {
            $("#motherViralLoadCopiesPerMl").val('');
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
                        testType: 'eid'
                    },
                    function(data) {
                        if (data != "") {
                            $("#specimenType").html(data);
                        }
                    });
            }
        });
        getfacilityProvinceDetails($("#facilityId").val());
        $('.select2').select2();
        $('#motherTreatment').select2({
            width: '100%',
            placeholder: "ART given to the Mother during"
        });
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

        $('#district').select2({
            placeholder: "District"
        });
        $('#province').select2({
            placeholder: "Province"
        });
        $('#facilityId').select2({
            placeholder: "Select Clinic/Health Center"
        });
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

    });

    function getMachine(value) {
        $.post("/instruments/get-machine-names-by-instrument.php", {
                instrumentId: value,
                machine: '',
                testType: 'eid'
            },
            function(data) {
                $('#machineName').html('');
                if (data != "") {
                    $('#machineName').append(data);
                }
            });
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
</script>
