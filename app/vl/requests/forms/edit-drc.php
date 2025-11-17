<?php

use App\Services\CommonService;
use App\Services\StorageService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var StorageService $storageService */
$storageService = ContainerRegistry::get(StorageService::class);

$arr = $general->getGlobalConfig();

// check if STS
if ($general->isSTSInstance()) {

	if (!empty($vlQueryInfo['remote_sample']) && $vlQueryInfo['remote_sample'] == 'yes') {
		$sampleCode = 'remote_sample_code';
	} else {
		$sampleCode = 'sample_code';
	}
} else {
	$sampleCode = 'sample_code';
}
$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);
$facility = $general->generateSelectOptions($healthFacilities, $vlQueryInfo['facility_id'], _translate("-- Select --"));


//Get selected state
$stateQuery = "SELECT * FROM facility_details WHERE facility_id=?";
$stateResult = $db->rawQueryOne($stateQuery, [$vlQueryInfo['facility_id']]);

$stateResult['facility_state'] ??= "";
$stateResult['facility_district'] ??= "";

//district details
$districtQuery = "SELECT DISTINCT facility_district FROM facility_details WHERE facility_state= ?";
$districtResult = $db->rawQuery($districtQuery, [$stateResult['facility_state']]);
$provinceQuery = "SELECT geo_code FROM geographical_divisions WHERE geo_id= ?";
$provinceResult = $db->rawQueryOne($provinceQuery, [$stateResult['facility_state_id']]);

$provinceResult['geo_code'] ??= '';

//get ART list
$aQuery = "SELECT * from r_vl_art_regimen WHERE art_status like 'active' ORDER by parent_art ASC, art_code ASC";
$aResult = $db->query($aQuery);


$duVisibility = (trim((string) $vlQueryInfo['is_patient_new']) === "" || trim((string) $vlQueryInfo['is_patient_new']) === "no") ? 'hidden' : 'visible';
$duRequiredClass = ($duVisibility === 'visible') ? 'isRequired' : '';
$duMandatoryLabel = ($duVisibility === 'visible') ? '<span class="mandatory">*</span>' : '';

$displayArvChangedElement = (trim((string) $vlQueryInfo['has_patient_changed_regimen']) === "yes");
$arvRequiredClass = $displayArvChangedElement ? 'isRequired' : '';
$arvMandatoryLabel = $displayArvChangedElement ? '<span class="mandatory">*</span>' : '';

$femaleSectionDisplay = (trim((string) $vlQueryInfo['patient_gender']) === "" || trim((string) $vlQueryInfo['patient_gender']) === "male") ? 'none' : 'block';
$trimsterDisplay = (trim((string) $vlQueryInfo['is_patient_pregnant']) === "" || trim((string) $vlQueryInfo['is_patient_pregnant']) === "no") ? 'none' : 'block';

$formAttributes = json_decode((string) $vlQueryInfo['form_attributes']);
if (is_object($formAttributes->storage)) {
	$formAttributes->storage = json_encode($formAttributes->storage);
}
$storageObj = json_decode($formAttributes->storage);
$storageInfo = $storageService->getLabStorage();


?>

<style>
	.translate-content {
		color: #0000FF;
		font-size: 12.5px;
	}

	.du {
		visibility: <?php echo $duVisibility; ?>;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> VIRAL LOAD LABORATORY REQUEST FORM</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Edit HIV VL Test Request</li>
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
				<form class="form-horizontal" method="post" name="editVlRequestForm" id="editVlRequestForm" autocomplete="off" action="editVlRequestHelper.php">
					<div class="box-body">
						<div class="box box-default">
							<div class="box-body">
								<div class="box-header with-border">
									<h3 class="box-title">1. Réservé à la structure de soins</h3>
								</div>
								<div class="box-header with-border">
									<h3 class="box-title">Information sur la structure de soins</h3>
								</div>

								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">


									<tr>
										<?php if ($general->isSTSInstance()) { ?>
											<td><label for="sampleCode">Échantillon ID </label></td>
											<td>
												<span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;">
													<?php echo $vlQueryInfo[$sampleCode]; ?>
												</span>
												<input type="hidden" id="sampleCode" name="sampleCode" value="<?php echo $vlQueryInfo[$sampleCode]; ?>" />
											</td>
										<?php } else { ?>
											<td><label for="sampleCode">Échantillon ID </label><span class="mandatory">*</span></td>
											<td>
												<input type="text" class="form-control isRequired" readonly id="sampleCode" name="sampleCode" placeholder="Échantillon ID" title="Please enter échantillon id" value="<?php echo $vlQueryInfo[$sampleCode]; ?>" style="width:100%;" onchange="checkSampleNameValidation('form_vl','<?php echo $sampleCode; ?>',this.id,'<?php echo "vl_sample_id##" . $vlQueryInfo["vl_sample_id"]; ?>','The échantillon id that you entered already exists. Please try another échantillon id',null)" />
											</td>
										<?php } ?>

										<td><label for="serialNo">
												<?= _translate("Recency ID"); ?>
											</label></td>
										<td><input type="text" class="form-control" id="serialNo" name="serialNo" placeholder="<?= _translate("Recency ID"); ?>" title="<?= _translate("Recency ID"); ?>" style="width:100%;" value="<?php echo $vlQueryInfo['external_sample_code']; ?>" /></td>
										<?php if ($general->isSTSInstance()) { ?>
											<td style=" display:<?php echo ($sCode == '') ? 'none' : ''; ?>"><label for="">Date de réception de léchantillon <span class="mandatory">*</span></label></td>
											<td style=" display:<?php echo ($sCode == '') ? 'none' : ''; ?>">
												<input type="text" class="form-control dateTime isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de réception de léchantillon" <?php echo $labFieldDisabled; ?> value="<?php echo ($vlQueryInfo['sample_received_at_lab_datetime'] != '' && $vlQueryInfo['sample_received_at_lab_datetime'] != null) ? $vlQueryInfo['sample_received_at_lab_datetime'] : date('d-M-Y H:i:s'); ?>" style="width:100%;" />
											</td>
										<?php } else { ?>
											<td></td>
											<td></td>
										<?php } ?>

									</tr>
									<tr>
										<td><label for="province">Province </label><span class="mandatory">*</span></td>
										<td>
											<select class="form-control isRequired" name="province" id="province" title="Please choose province" onchange="getfacilityDetails(this);" style="width:100%;">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php foreach ($pdResult as $provinceName) { ?>
													<option value="<?php echo $provinceName['geo_name'] . "##" . $provinceName['geo_code']; ?>" <?php echo (strtolower((string) $stateResult['facility_state']) . "##" . strtolower((string) $provinceResult['geo_code']) === strtolower((string) $provinceName['geo_name']) . "##" . strtolower((string) $provinceName['geo_code'])) ? "selected='selected'" : "" ?>><?php echo ($provinceName['geo_name']); ?></option>
												<?php } ?>
											</select>
										</td>
										<td><label for="district">Zone de santé </label><span class="mandatory">*</span>
										</td>
										<td>
											<select class="form-control isRequired" name="district" id="district" title="Veuillez choisir le quartier" style="width:100%;" onchange="getfacilityDistrictwise(this);">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php foreach ($districtResult as $districtName) { ?>
													<option value="<?php echo $districtName['facility_district']; ?>" <?php echo ($stateResult['facility_district'] == $districtName['facility_district']) ? "selected='selected'" : "" ?>><?php echo ($districtName['facility_district']); ?></option>
												<?php } ?>
											</select>
										</td>
										<td><label for="facilityId">POINT DE COLLECT <span class="mandatory">*</span></label>
										</td>
										<td>
											<select class="form-control isRequired" name="facilityId" id="facilityId" title="Veuillez choisir le POINT DE COLLECT" onchange="getfacilityProvinceDetails(this);" style="width:100%;">
												<?= $facility; ?>
											</select>
										</td>
									</tr>
									<tr>
										<td><label for="reqClinician">Demandeur <span class="mandatory">*</span></label></td>
										<td>
											<input type="text" class="form-control isRequired" id="reqClinician" name="reqClinician" placeholder="Demandeur" title="Veuillez saisir le demandeur" value="<?php echo $vlQueryInfo['request_clinician_name']; ?>" style="width:100%;" />
										</td>
										<td><label for="reqClinicianPhoneNumber">Téléphone <span class="mandatory">*</span></label></td>
										<td>
											<input type="text" class="form-control phone-number isRequired" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" placeholder="Téléphone" title="Veuillez entrer le téléphone" value="<?php echo $vlQueryInfo['request_clinician_phone_number']; ?>" style="width:100%;" />
										</td>
										<td><label for="supportPartner">Partenaire d'appui <span class="mandatory">*</span></label></td>
										<td>
											<!-- <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire d'appui" title="Please enter Partenaire d'appui" value="< ?php echo $vlQueryInfo['facility_support_partner']; ?>" style="width:100%;"/> -->
											<select class="form-control select2 isRequired" name="implementingPartner" id="implementingPartner" title="<?= _translate("Please choose implementing partner"); ?>" style="width:100%;">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php
												foreach ($implementingPartnerList as $implementingPartner) {
												?>
													<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>" <?php echo ($implementingPartner['i_partner_id'] == $vlQueryInfo['implementing_partner']) ? 'selected="selected"' : ''; ?>><?= $implementingPartner['i_partner_name']; ?></option>
												<?php } ?>
											</select>
										</td>
									</tr>
									<tr>
										<td><label for="">Date de la demande<span class="mandatory">*</span> </label></td>
										<td>
											<input type="text" class="form-control date isRequired" id="dateOfDemand" name="dateOfDemand" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de la demande" value="<?php echo $vlQueryInfo['date_test_ordered_by_physician']; ?>" style="width:100%;" />
										</td>
										<td><label for="fundingSource">Source de financement <span class="mandatory">*</span></label></td>
										<td>
											<select class="form-control select2 isRequired" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php
												foreach ($fundingSourceList as $fundingSource) {
												?>
													<option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $vlQueryInfo['funding_source']) ? 'selected="selected"' : ''; ?>><?= $fundingSource['funding_source_name']; ?></option>
												<?php } ?>
											</select>
										</td>
										<td><label for="labId">Nom du laboratoire <span class="mandatory">*</span></label> </td>
										<td>
											<select name="labId" id="labId" class="form-control isRequired" title="Please choose laboratoire" style="width:100%;">
												<?= $general->generateSelectOptions($testingLabs, $vlQueryInfo['lab_id'], '-- Sélectionner --'); ?>
											</select>
										</td>
									</tr>
								</table>
								<div class="box-header with-border">
									<h3 class="box-title">Information sur le patient </h3>&nbsp;&nbsp;&nbsp;
									<input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" placeholder="Code du patient" title="<?= _translate("Please enter the Patient ID"); ?>" />&nbsp;&nbsp;
									<a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No Patient
											Found</strong></span>
								</div>
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr class="encryptPIIContainer">
										<th scope="row" style="width:15% !important"><label for="childId"><?= _translate('Encrypt PII'); ?> </label></th>
										<td>
											<select name="encryptPII" id="encryptPII" class="form-control" title="<?= _translate('Encrypt PII'); ?>">
												<option value=""><?= _translate('--Select--'); ?></option>
												<option value="no" <?php echo ($vlQueryInfo['is_encrypted'] == "no") ? "selected='selected'" : ""; ?>><?= _translate('No'); ?></option>
												<option value="yes" <?php echo ($vlQueryInfo['is_encrypted'] == "yes") ? "selected='selected'" : ""; ?>><?= _translate('Yes'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;"><label for="artNo">Code du patient <span class="mandatory">*</span></label>
										</td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control isRequired patientId" id="artNo" name="artNo" placeholder="Code du patient" title="<?= _translate("Please enter the Patient ID"); ?>" value="<?= ($vlQueryInfo['patient_art_no']); ?>" style="width:100%;" />
										</td>
										<td style="width: 15% !important;"><label for="">Date de naissance <span class="mandatory">*</span></label></td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control date isRequired" id="dob" name="dob" placeholder="<?= _translate("Please enter date"); ?>" title="Please select date de naissance" onchange="getAge();checkARTInitiationDate();" value="<?= ($vlQueryInfo['patient_dob']); ?>" style="width:100%;" />
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;"><label for="ageInYears">Âge en années <span class="mandatory">*</span></label></td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control forceNumeric isRequired" id="ageInYears" name="ageInYears" placeholder="Aannées" title="<?= _translate("Please enter Patient age") ?>" value="<?= ($vlQueryInfo['patient_age_in_years']); ?>" onchange="clearDOB(this.value);" style="width:100%;" />
										</td>
										<td style="width: 15% !important;"><label for="ageInMonths">Âge en mois </label></td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control forceNumeric" id="ageInMonths" name="ageInMonths" placeholder="Mois" title="Please enter àge en mois" value="<?= ($vlQueryInfo['patient_age_in_months']); ?>" onchange="clearDOB(this.value);" style="width:100%;" />
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;"><label for="sex">Sexe <span class="mandatory">*</span></label></td>
										<td style="width: 35% !important;">

											<select name="gender" id="gender" class="form-control isRequired" title="Please select sex">
												<option value="male" <?php echo (trim((string) $vlQueryInfo['patient_gender']) === "male") ? 'selected="selected"' : ''; ?>><?= _translate("M"); ?></option>
												<option value="female" <?php echo (trim((string) $vlQueryInfo['patient_gender']) === "female") ? 'selected="selected"' : ''; ?>><?= _translate("F"); ?></option>
											</select>
										</td>
										<!-- <td style="width: 15% !important;"><label>KP </label></td>
										<td style="width: 35% !important;">
											<select class="form-control" name="keyPopulation" id="keyPopulation" title="<?= _translate('Please choose KP'); ?>">
											</select>
										</td> -->
									</tr>
									<tr class="femaleSection">
										<td style="width:10% !important;"><strong>Si Femme : </strong></td>
										<td style="width:20% !important;">
											<label for="breastfeeding">Allaitante ?<span class="mandatory" style="display:none;">*</span></label>
											<select class="form-control" id="breastfeeding" name="breastfeeding" title="Please check Si allaitante">
												<option value=""> -- Select -- </option>
												<option id="breastfeedingYes" <?php echo (trim((string) $vlQueryInfo['is_patient_breastfeeding']) === "yes") ? 'selected="selected"' : ''; ?> value="yes">Oui</option>
												<option id="breastfeedingNo" <?php echo (trim((string) $vlQueryInfo['is_patient_breastfeeding']) === "no") ? 'selected="selected"' : ''; ?> value="no">Non</option>
											</select>
										</td>
										<td style="width:15% !important;">
											<label for="pregnant">Ou enceinte ?<span class="mandatory" style="display:none;">*</span></label>
											<select class="form-control" id="pregnant" name="patientPregnant" title="Please check Si Ou enceinte ">
												<option value=""> -- Select -- </option>
												<option id="pregYes" <?php echo (trim((string) $vlQueryInfo['is_patient_pregnant']) === "yes") ? 'selected="selected"' : ''; ?> value="yes">Oui</option>
												<option id="pregNo" <?php echo (trim((string) $vlQueryInfo['is_patient_pregnant']) === "no") ? 'selected="selected"' : ''; ?> value="no">Non</option>
											</select>
										</td>
										<td class="trimesterSection" style="width:30% !important;">
											<label for="trimester">Si Femme enceinte :<span class="mandatory" style="display:none;">*</span></label>
											<select class="form-control" id="trimester" name="trimester" title="Please check trimestre">
												<option value=""> -- Select -- </option>
												<option id="trimester1" <?php echo (trim((string) $vlQueryInfo['pregnancy_trimester']) === "1") ? 'selected="selected"' : ''; ?> value="1">Trimestre 1</option>
												<option id="trimester2" <?php echo (trim((string) $vlQueryInfo['pregnancy_trimester']) === "2") ? 'selected="selected"' : ''; ?> value="2">Trimestre 2</option>
												<option id="trimester3" <?php echo (trim((string) $vlQueryInfo['pregnancy_trimester']) === "3") ? 'selected="selected"' : ''; ?> value="3">Trimestre 3</option>
											</select>
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;"><label>Régime ARV en cours <span class="mandatory">*</span></label></td>
										<td style="width: 35% !important;">
											<select class="form-control isRequired" name="artRegimen" id="artRegimen" title="Please choose régime ARV en cours" onchange="checkARTRegimenValue();" style="width:100%;">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php foreach ($aResult as $arv) { ?>
													<option value="<?php echo $arv['art_code']; ?>" <?php echo ($arv['art_code'] == $vlQueryInfo['current_regimen']) ? 'selected="selected"' : ''; ?>><?php echo $arv['art_code']; ?>
													</option>
												<?php }
												if ($general->isLISInstance() === false) { ?>
													<option value="other">Autre</option>
												<?php } ?>
											</select>
											<input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="Enter Régime ARV" title="Please enter régime ARV" style="width:100%;margin-top:1vh;display:none;">
										</td>
										<td><label for="patientPhoneNumber">Numéro de portable du patient </label></td>
										<td>
											<input type="text" class="form-control phone-number" id="patientPhoneNumber" name="patientPhoneNumber" placeholder="Téléphone" title="Veuillez entrer le téléphone" value="<?php echo $vlQueryInfo['patient_mobile_number']; ?>" style="width:100%;" />
										</td>

									</tr>
									<tr>
										<td style="width: 15% !important;"><label for="isPatientNew">Si S/ARV <span class="mandatory">*</span></label></td>
										<td style="width: 35% !important;">
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Oui</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="isRequired" id="isPatientNewYes" name="isPatientNew" <?php echo ($vlQueryInfo['is_patient_new'] == 'yes') ? 'checked="checked"' : ''; ?> value="yes" title="Please check Si S/ ARV">
											</label>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Non</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="isRequired" id="isPatientNewNo" name="isPatientNew" <?php echo ($vlQueryInfo['is_patient_new'] == 'no') ? 'checked="checked"' : ''; ?> value="no">
											</label>
										</td>
										<td style="width: 15% !important;" class="du"><label for="">Date du début des ARV <?php echo $duMandatoryLabel; ?></label></td>
										<td style="width: 35% !important;" class="du">
											<input type="text" class="form-control date <?php echo $duRequiredClass; ?>" id="dateOfArtInitiation" name="dateOfArtInitiation" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date du début des ARV" value="<?php echo $vlQueryInfo['treatment_initiated_date']; ?>" onchange="checkARTInitiationDate();checkLastVLTestDate();" style="width:100%;" />&nbsp;(Jour/Mois/Année)
										</td>
									</tr>
									<tr>
										<td style="width: 15%;">
											<label for="hasChangedRegimen">Ce patient a-t-il déjà changé de régime de
												traitement? <span class="mandatory">*</span></label>
										</td>
										<td style="width: 35%;">
											<br>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Oui </label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="isRequired" id="changedRegimenYes" name="hasChangedRegimen" value="yes" title="<?= _translate("Please choose if ARV Regimen changed"); ?>" <?php echo (trim((string) $vlQueryInfo['has_patient_changed_regimen']) === "yes") ? 'checked="checked"' : ''; ?>>
											</label>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Non </label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="isRequired" id="changedRegimenNo" name="hasChangedRegimen" value="no" title="<?= _translate("Please choose if ARV Regimen changed"); ?>" <?php echo (trim((string) $vlQueryInfo['has_patient_changed_regimen']) === "no") ? 'checked="checked"' : ''; ?>>
											</label>
										</td>
									</tr>
									<tr class="arvChangedElement" style="display:<?php echo ($displayArvChangedElement) ? '' : 'none'; ?>;">
										<td style="width: 15%;"><label for="reasonForArvRegimenChange" class="arvChangedElement">Motif
												de changement de régime ARV <?php echo $arvMandatoryLabel; ?></label></td>
										<td style="width: 35%;">
											<input type="text" class="form-control arvChangedElement <?php echo $arvRequiredClass; ?>" id="reasonForArvRegimenChange" name="reasonForArvRegimenChange" placeholder="Motif de changement de régime ARV" title="Please enter motif de changement de régime ARV" value="<?php echo $vlQueryInfo['reason_for_regimen_change']; ?>" style="width:100%;" />
										</td>
										<td style="width: 15%;"><label for="" class="arvChangedElement">Date du changement de régime ARV </label></td>
										<td style="width: 35%;">
											<input type="text" class="form-control date arvChangedElement <?php echo $arvRequiredClass; ?>" id="dateOfArvRegimenChange" name="dateOfArvRegimenChange" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date du changement de régime ARV" value="<?php echo $vlQueryInfo['regimen_change_date']; ?>" style="width:100%;" />&nbsp;(Jour/Mois/Année)
										</td>
									</tr>
									<tr>
										<td style="width: 15%;"><label for="reasonForRequest">Motif de la demande <span class="mandatory">*</span></label></td>
										<td style="width: 35%;">
											<select name="reasonForVLTesting" id="reasonForVLTesting" class="form-control isRequired" title="Please choose motif de la demande" onchange="checkreasonForVLTesting();">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php foreach ($vlTestReasonResult as $tReason) { ?>
													<option value="<?php echo $tReason['test_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_vl_testing'] == $tReason['test_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ($tReason['test_reason_name']); ?></option>
												<?php }
												if ($general->isLISInstance() === false) { ?>
													<option value="other">Autre</option>
												<?php } ?>
											</select>
										</td>
										<td style="width: 15%;"><label for="viralLoadNo">Charge virale N <span class="mandatory">*</span></label>
										</td>
										<td style="width: 35%;">
											<input type="text" class="form-control isRequired" id="viralLoadNo" name="viralLoadNo" placeholder="Charge virale N" title="Please enter charge virale N" value="<?php echo $vlQueryInfo['vl_test_number']; ?>" style="width:100%;" />
										</td>
									</tr>
									<tr>
										<td style="width: 15%;"><label for="">Date dernière charge virale (demande) </label></td>
										<td style="width: 35%;">
											<input type="text" class="form-control date" id="lastViralLoadTestDate" name="lastViralLoadTestDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date dernière charge virale" value="<?php echo $vlQueryInfo['last_viral_load_date']; ?>" onchange="checkLastVLTestDate();" style="width:100%;" />
										</td>
										<td style="width: 15%;"><label for="lastViralLoadResult">Résultat dernière charge virale </label>
										</td>
										<td style="width: 35%;">
											<input type="text" class="form-control" id="lastViralLoadResult" name="lastViralLoadResult" placeholder="Résultat dernière charge virale" title="Please enter résultat dernière charge virale" value="<?php echo $vlQueryInfo['last_viral_load_result']; ?>" style="width:100%;" />copies/mL
										</td>
									</tr>
									<tr class="newreasonForVLTesting" style="display:none;">
										<td style="width: 15%;"><label for="newreasonForVLTesting">Autre, à préciser <span class="mandatory">*</span></label></td>
										<td style="width: 35%;">
											<input type="text" class="form-control" name="newreasonForVLTesting" id="newreasonForVLTesting" placeholder="Virale Demande Raison" title="Please enter virale demande raison" style="width:100%;" value="<?php echo $vlQueryInfo['reason_for_vl_testing_other']; ?>">
										</td>
									</tr>
									<tr>
										<td colspan="8"><label class="radio-inline" style="margin:0;padding:0;">A
												remplir par le service demandeur dans la structure de soins</label></td>
									</tr>
								</table>
								<div class="box-header with-border">
									<h3 class="box-title">Informations sur le prélèvement <small>(A remplir par le
											préleveur)</small> </h3>
								</div>
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr>
										<td style="width:25%;"><label for="">Date du prélèvement <span class="mandatory">*</span></label></td>
										<td style="width:25%;">
											<input type="text" class="form-control dateTime isRequired" id="sampleCollectionDate" name="sampleCollectionDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date du prélèvement" value="<?php echo $vlQueryInfo['sample_collection_date']; ?>" onchange="checkSampleTestingDate(); checkCollectionDate(this.value);" style="width:100%;" />
											<span class="expiredCollectionDate" style="color:red; display:none;"></span>
										</td>
										<td style="width:25%;"></td>
										<td style="width:25%;"></td>
									</tr>
									<?php if (isset($arr['sample_type']) && trim((string) $arr['sample_type']) === "enabled") { ?>
										<tr>
											<td><label for="specimenType">Type d'échantillon <span class="mandatory">*</span></label></td>
											<td>
												<select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose type d'échantillon" onchange="checkSpecimenType();" style="width:100%;">
													<option value=""><?= _translate("-- Select --"); ?> </option>
													<?php foreach ($sResult as $type) { ?>
														<option value="<?php echo $type['sample_id']; ?>" <?php echo ($vlQueryInfo['specimen_type'] == $type['sample_id']) ? 'selected="selected"' : ''; ?>><?= $type['sample_name']; ?></option>
													<?php } ?>
												</select>
											</td>
											<td></td>
											<td></td>
										</tr>
									<?php } ?>
									<tr class="plasmaElement" style="display:<?php echo ($vlQueryInfo['specimen_type'] == 2) ? '' : 'none'; ?>;">
										<td><label for="conservationTemperature">Si
												plasma,&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Température de conservation
											</label></td>
										<td>
											<input type="text" class="form-control forceNumeric" id="conservationTemperature" name="conservationTemperature" placeholder="Température de conservation" title="Please enter température de conservation" value="<?php echo $vlQueryInfo['plasma_conservation_temperature']; ?>" style="width:100%;" />&nbsp;(°C)
										</td>
										<td style="text-align:center;"><label for="durationOfConservation">Durée de
												conservation </label></td>
										<td>
											<input type="text" class="form-control" id="durationOfConservation" name="durationOfConservation" placeholder="e.g 9/1" title="Please enter durée de conservation" value="<?php echo $vlQueryInfo['plasma_conservation_duration']; ?>" style="width:100%;" />&nbsp;(Jour/Heures)
										</td>
									</tr>
									<tr>
										<td><label for="">Date de départ au Labo biomol <span class="mandatory">*</span></label></td>
										<td>
											<input type="text" class="form-control dateTime isRequired" id="sampleDispatchedDate" name="sampleDispatchedDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de départ au Labo biomol" value="<?php echo $vlQueryInfo['sample_dispatched_datetime']; ?>" style="width:100%;" />
										</td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td colspan="4"><label class="radio-inline" style="margin:0;padding:0;">A
												remplir par le préleveur </label></td>
									</tr>
								</table>
							</div>
						</div>
						<?php if (!$general->isSTSInstance()) { ?>
							<div class="box box-primary">
								<div class="box-body">
									<div class="box-header with-border">
										<h3 class="box-title">2. Réservé au Laboratoire de biologie moléculaire </h3>
									</div>
									<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
										<tr style="<?php echo ($sCode != '') ? 'display:none' : ''; ?>">
											<td><label for="">Date de réception de l'échantillon </label></td>
											<td>
												<input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de réception de léchantillon" <?php echo $labFieldDisabled; ?> value="<?php echo $vlQueryInfo['sample_received_at_lab_datetime']; ?>" style="width:100%;" />
											</td>
											<td style="width: 25%;"><label for=""><?php echo _translate('Freezer'); ?> <em class="fas fa-edit"></em> :</label></td>
											<td style="width: 25%;">
												<select class="form-control select2 editableSelect" id="freezer" name="freezer" placeholder="<?php echo _translate('Enter Freezer'); ?>" title="<?php echo _translate('Please enter Freezer'); ?>">
													<?= $general->generateSelectOptions($storageInfo, $storageObj->storageId, '-- Select --') ?>
												</select>
											</td>
										</tr>
										<tr>
											<td style="width: 25%;"><label for="rack"><?php echo _translate('Rack'); ?> : </label> </td>
											<td style="width: 25%;">
												<input type="text" class="form-control" id="rack" name="rack" value="<?= $storageObj->rack; ?>" placeholder="<?php echo _translate('Rack'); ?>" title="<?php echo _translate('Please enter rack'); ?>" value="<?= $storageObj->rack; ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
											<td style="width: 25%;"><label for=""><?php echo _translate('Box'); ?> :</label></td>
											<td style="width: 25%;">
												<input type="text" class="form-control" id="box" name="box" value="<?= $storageObj->box; ?>" placeholder="<?php echo _translate('Box'); ?>" title="<?php echo _translate('Please enter box'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
										</tr>
										<tr>
											<td style="width: 25%;"><label for="position"><?php echo _translate('Position'); ?> : </label> </td>
											<td style="width: 25%;">
												<input type="text" class="form-control" id="position" name="position" value="<?= $storageObj->position; ?>" placeholder="<?php echo _translate('Position'); ?>" title="<?php echo _translate('Please enter position'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
											<td style="width: 25%;"><label for=""><?php echo _translate('Volume (ml)'); ?> :</label></td>
											<td style="width: 25%;">
												<input type="text" class="form-control" id="volume" name="volume" value="<?= $storageObj->volume; ?>" placeholder="<?php echo _translate('Volume'); ?>" title="<?php echo _translate('Please enter volume'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
										</tr>
										<tr>
											<td><label for="">Date de réalisation de la charge virale </label></td>
											<td>
												<input type="text" class="form-control dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de réalisation de la charge virale" <?php echo $labFieldDisabled; ?> value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>" style="width:100%;" />
											</td>
											<td><label for="testingPlatform">Technique utilisée </label></td>
											<td>
												<select name="testingPlatform" id="testingPlatform" class="form-control" title="Please choose VL Testing Platform" <?php echo $labFieldDisabled; ?> style="width:100%;" onchange="getVlResults(this.value)">
													<option value=""><?= _translate("-- Select --"); ?> </option>
													<?php foreach ($importResult as $mName) { ?>
														<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['instrument_id']; ?>" <?php echo ($vlQueryInfo['vl_test_platform'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] === $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']) ? "selected='selected'" : "" ?>><?php echo $mName['machine_name']; ?>
														</option>
													<?php } ?>
												</select>
											</td>
										</tr>
										<tr>
											<td><label for="">Décision prise </label></td>
											<td>
												<select class="form-control" id="isSampleRejected" name="isSampleRejected" title="Please select décision prise" <?php echo $labFieldDisabled; ?> onchange="checkTestStatus();" style="width:100%;">
													<option value=""><?= _translate("-- Select --"); ?> </option>
													<option value="no" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'selected="selected"' : ''; ?>>Echantillon accepté</option>
													<option value="yes" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'selected="selected"' : ''; ?>>Echantillon rejeté</option>
												</select>
											</td>
										</tr>
										<tr class="rejectionReason" style="display:<?php echo ($vlQueryInfo['result_status'] == 4) ? '' : 'none'; ?>;">
											<td class="rejectionReason" style="<?php echo ($vlQueryInfo['is_sample_rejected'] != 'yes') ? 'display: none;' : ''; ?>"><label for="rejectionReason">Motifs de rejet <span class="mandatory">*</span></label></td>
											<td class="rejectionReason" style="<?php echo ($vlQueryInfo['is_sample_rejected'] != 'yes') ? 'display: none;' : ''; ?>">
												<select class="form-control" id="rejectionReason" name="rejectionReason" title="Please select motifs de rejet" <?php echo $labFieldDisabled; ?> onchange="checkRejectionReason();" style="width:100%;">
													<option value=""><?= _translate("-- Select --"); ?> </option>
													<?php foreach ($rejectionResult as $rjctReason) { ?>
														<option value="<?php echo $rjctReason['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $rjctReason['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ($rjctReason['rejection_reason_name']); ?></option>
													<?php } ?>
													<option value="other">Autre <span class="mandatory">*</span></option>
												</select>
												<input type="text" class="form-control newRejectionReason" id="newRejectionReason" name="newRejectionReason" placeholder="Motifs de rejet" title="Please enter motifs de rejet" <?php echo $labFieldDisabled; ?> style="width:100%;display:none;" />
											</td>
											<td class="newRejectionReason" style="text-align:center;display:none;"><label for="newRejectionReason" class="newRejectionReason" style="display:none;">Autre, à préciser <span class="mandatory">*</span></label></td>
											<td class="newRejectionReason" style="display:none;"><input type="text" class="form-control newRejectionReason" id="newRejectionReason" name="newRejectionReason" placeholder="Motifs de rejet" title="Please enter motifs de rejet" <?php echo $labFieldDisabled; ?> style="width:100%;display:none;" /></td>
											<th scope="row" class="rejectionReason" style="display:none;">
												<?php echo _translate("Rejection Date"); ?> <span class="mandatory">*</span>
											</th>
											<td class="rejectionReason" style="display:none;"><input class="form-control date rejection-date" type="text" name="rejectionDate" value="<?php echo $vlQueryInfo['result_reviewed_datetime']; ?>" id="rejectionDate" placeholder="Select Rejection Date" /></td>
										</tr>
										<tr class="vlResult" style="<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'display: none;' : ''; ?>">
											<td class="vlResult"><label for="vlResult">Résultat</label></td>
											<td class="vlResult resultInputContainer">
												<input list="possibleVlResults" class="form-control result-fields labSection" id="vlResult" name="vlResult" placeholder="Select or Type VL Result" title="Please enter résultat" value="<?= ($vlQueryInfo['result']); ?>" onchange="calculateLogValue(this)">
												<datalist id="possibleVlResults">
													<!--<option value="< 20" < ?php echo (isset($vlQueryInfo['result']) && $vlQueryInfo['result'] == '< 20') ? "selected='selected'" : ""; ?>> &lt; 20 </option>
													<option value="< 40" < ?php echo (isset($vlQueryInfo['result']) && $vlQueryInfo['result'] == '< 40') ? "selected='selected'" : ""; ?>> &lt; 40 </option>
													<option value="< 400" < ?php echo (isset($vlQueryInfo['result']) && $vlQueryInfo['result'] == '< 400') ? "selected='selected'" : ""; ?>> &lt; 400 </option>
													<option value="Target Not Detected" < ?php echo (isset($vlQueryInfo['result']) && $vlQueryInfo['result'] == 'Target Not Detected') ? "selected='selected'" : ""; ?>> Target Not Detected </option>-->
												</datalist>
											</td>
											<td class="vlLog" style="text-align:center;"><label for="vlLog">Log </label>
											</td>
											<td class="vlLog">
												<input type="text" class="form-control forceNumeric other-failed-results" id="vlLog" name="vlLog" placeholder="Log" title="Please enter log" value="<?= ($vlQueryInfo['result_value_log']); ?>" <?php echo $labFieldDisabled; ?> onchange="calculateLogValue(this)" style="width:100%;" />&nbsp;(copies/mL)
											</td>
										</tr>
										<?php if (count($reasonForFailure) > 0) { ?>
											<tr class="reasonForFailure" style="<?php echo (strtolower(trim((string) $vlQueryInfo['result'])) !== 'failed') ? 'display: none;' : ''; ?>">
												<th scope="row" class="reasonForFailure">
													<?php echo _translate("Reason for Failure"); ?>
												</th>
												<td class="reasonForFailure">
													<select name="reasonForFailure" id="reasonForFailure" class="form-control" title="Please choose reason for failure" style="width: 100%;">
														<?= $general->generateSelectOptions($reasonForFailure, $vlQueryInfo['reason_for_failure'], '-- Select --'); ?>
													</select>
												</td>
											</tr>
										<?php } ?>
										<tr>
											<td style="width:14%;"><label for="reviewedOn"> Revu le </label></td>
											<td style="width:14%;">
												<input type="text" name="reviewedOn" value="<?php echo $vlQueryInfo['result_reviewed_datetime']; ?>" id="reviewedOn" class="dateTime authorisation form-control" placeholder="Revu le" title="Please enter the Revu le" />
											</td>
											<td style="width:14%;"><label for="reviewedBy"> Revu par </label></td>
											<td style="width:14%;">
												<select name="reviewedBy" id="reviewedBy" class="select2 authorisation form-control" title="Please choose revu par" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_reviewed_by'], '-- Select --'); ?>
												</select>
											</td>
										</tr>
										<tr>
											<th scope="row">Approuvé le</th>
											<td>
												<input type="text" name="approvedOnDateTime" id="approvedOnDateTime" value="<?php echo $vlQueryInfo['result_approved_datetime']; ?>" class="dateTime authorisation form-control" placeholder="Approuvé le" title="Please enter the Approuvé le" />
											</td>
											<th scope="row">Approuvé par</th>
											<td>
												<select name="approvedBy" id="approvedBy" class="select2 authorisation form-control" title="Please choose Approuvé par" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_approved_by'], '-- Select --'); ?>
												</select>
											</td>
										</tr>
										<tr>
											<td class=" reasonForResultChanges" style="visibility:hidden;">
												<label for="reasonForResultChanges">
													<?= _translate("Enter reason for changing the result"); ?> <span class="mandatory">*</span>
												</label>
											</td>
											<td colspan="7" class="reasonForResultChanges" style="visibility:hidden;">
												<textarea class="form-control" name="reasonForResultChanges" id="reasonForResultChanges" placeholder="<?= _translate("Enter reason for changing the result"); ?>" title="<?= _translate("Enter reason for changing the result"); ?>" style="width:100%;"></textarea>
											</td>
										</tr>
									</table>
								</div>
							</div>
						<?php } ?>
						<div class="box-header with-border">
							<label class="radio-inline" style="margin:0;padding:0;">1. Biffer la mention inutile <br>2.
								Sélectionner un seul régime de traitement </label>
						</div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="sampleCodeCol" value="<?= ($vlQueryInfo['sample_code']); ?>" />
						<input type="hidden" id="vlSampleId" name="vlSampleId" value="<?= ($vlQueryInfo['vl_sample_id']); ?>" />
						<input type="hidden" id="status" name="oldStatus" value="<?= ($vlQueryInfo['result_status']); ?>" />
						<input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
						<a href="/vl/requests/vl-requests.php" class="btn btn-default"> Cancel</a>
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

<script type="text/javascript" src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/datalist-css.min.js") ?>"></script>
<script type="text/javascript">
	changeProvince = true;
	changeFacility = true;


	provinceName = true;
	facilityName = true;


	$(document).ready(function() {
		checkCollectionDate('<?php echo $vlQueryInfo['sample_collection_date']; ?>');

		$(".select2").select2();
		$(".select2").select2({
			tags: true
		});

		storageEditableSelect('freezer', 'storage_code', 'storage_id', 'lab_storage', 'Freezer Code');

		showFemale('<?php echo $femaleSectionDisplay; ?>');

		showTrimesterSection('<?php echo $trimsterDisplay; ?>');

		$("#gender").trigger('change');
		$('#serialNo').trigger('change');

		getVlResults($("#testingPlatform").val());
		if ($("#status").val() == 4) {
			$(".rejectionReason").show();
			$("#rejectionReason").addClass('isRequired');
			$("#vlResult").val('').css('pointer-events', 'none');
			$("#vlLog").val('').css('pointer-events', 'none');
			$(".vlResult, .vlLog").hide();
		} else {
			$(".rejectionReason").hide();
			$("#rejectionReason").removeClass('isRequired');
			$("#vlResult").css('pointer-events', 'auto');
			$("#vlLog").css('pointer-events', 'auto');
			$(".vlResult, .vlLog").show();
		}

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
			placeholder: "Select Nom du laboratoire"
		});
		$('#reviewedBy').select2({
			placeholder: "Select Revu par"
		});
		$('#approvedBy').select2({
			placeholder: "Select Approuvé par"
		});
		$('#artRegimen').select2({
			placeholder: "Select régime ARV en cours"
		});
		$('#reasonForVLTesting').select2({
			placeholder: "Select motif de la demande"
		});

		//$('#sampleCollectionDate').trigger("change");

		checkreasonForVLTesting();

		$("#ageInYears").on('input', function() {
			if ($(this).val()) {
				// If Age is entered, make DoB non-mandatory
				makeDOBNonMandatory();
			} else {
				// If Age is cleared, make DoB mandatory again
				makeDOBMandatory();
			}
		});
	});

	function makeDOBNonMandatory() {
		$("#dob").removeClass('isRequired');
		$("#dob").closest('td').prev('td').find('label .mandatory').remove();
	}

	function makeDOBMandatory() {
		$("#dob").addClass('isRequired');
		if ($("#dob").closest('td').prev('td').find('label .mandatory').length === 0) {
			$("#dob").closest('td').prev('td').find('label').append(' <span class="mandatory">*</span>');
		}
	}

	function showFemale(genderProp) {
		if (genderProp == "none") {
			hideFemaleSection();
		} else {
			showFemaleSection();
		}
	}

	function showFemaleSection() {
		$(".femaleSection").show();
		addMandatoryField('breastfeeding');
		addMandatoryField('pregnant');
	}

	function hideFemaleSection() {
		$(".femaleSection").hide();
		removeMandatoryField('breastfeeding');
		removeMandatoryField('pregnant');
		removeMandatoryField('trimester');
	}

	function addMandatoryField(fieldId) {
		$('label[for="' + fieldId + '"] .mandatory').show();
		$('#' + fieldId).addClass('isRequired');
	}

	function removeMandatoryField(fieldId) {
		$('label[for="' + fieldId + '"] .mandatory').hide();
		$('#' + fieldId).removeClass('isRequired');
		$('#' + fieldId).val('');
	}

	function showTrimesterSection(trimesterProp) {
		if (trimesterProp == "none") {
			removeMandatoryField('trimester');
			$(".trimesterSection").hide();

		} else {
			$(".trimesterSection").show();
			addMandatoryField('trimester');
		}
	}

	//$('#sampleDispatchedDate').val($('#sampleCollectionDate').val());
	//	var startDate = $('#sampleCollectionDate').datetimepicker('getDate');

	//$('#sampleDispatchedDate').datetimepicker('option', 'minDate', minDate);
	//	$("#sampleDispatchedDate").datetimepicker( {minDate, minDate });
	function getfacilityDetails(obj) {
		$.blockUI();
		var pName = $("#province").val();
		if ($.trim(pName) != '') {
			$.post("/includes/siteInformationDropdownOptions.php", {
					pName: pName,
					testType: 'vl'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#facilityId").html(details[0]);
						$("#district").html(details[1]);
					}
				});
		} else {
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
					testType: 'vl'
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

	function setPatientDetails(pDetails) {
		var patientArray = JSON.parse(pDetails);
		//console.log(patientArray);
		if ($.trim(patientArray['dob']) != '') {
			$("#dob").val(patientArray['dob']);
			getAge();
		} else if ($.trim(patientArray['age_in_years']) != '' && $.trim(patientArray['age_in_years']) != 0) {
			$("#ageInYears").val(patientArray['age_in_years']);
		} else if ($.trim(patientArray['age_in_months']) != '') {
			$("#ageInMonths").val(patientArray['age_in_months']);
		}
		if ($.trim(patientArray['gender']) != '') {
			if (patientArray['gender'] == 'male') {
				$("#genderMale").prop('checked', true);
			} else if (patientArray['gender'] == 'female') {
				$("#genderFemale").prop('checked', true);
				$(".femaleSection").show();
				if ($.trim(patientArray['is_breastfeeding']) != '') {
					$("#breastfeeding").val($.trim(patientArray['is_breastfeeding']));
				}
				if ($.trim(patientArray['is_pregnant']) != '') {
					$("#pregnant").val($.trim(patientArray['is_pregnant']));
					$('#pregnant').trigger('change');
					if ($.trim(patientArray['is_pregnant']) == 'yes') {
						if ($.trim(patientArray['trimester']) != '') {
							$("#trimester").val($.trim(patientArray['trimester']));
						}
					}
				}
			}
		}
		if ($.trim(patientArray['patient_art_no']) != '') {
			$("#artNo").val($.trim(patientArray['patient_art_no']));
		}
		if ($.trim(patientArray['current_regimen']) != '') {
			$("#artRegimen").val($.trim(patientArray['current_regimen']));
			$('#artRegimen').trigger('change');
		}
		if ($.trim(patientArray['is_patient_new']) != '') {
			if (patientArray['is_patient_new'] == 'yes') {
				$("#isPatientNewYes").prop('checked', true);
			} else if (patientArray['is_patient_new'] == 'no') {
				$("#isPatientNewNo").prop('checked', true);
			}
		}
	}


	$("input:radio[name=hasChangedRegimen]").click(function() {
		if ($(this).val() == 'yes') {
			$(".arvChangedElement").show();
			$(".arvChangedElement label").each(function() {
				if ($(this).find('.mandatory').length === 0) {
					$(this).append(' <span class="mandatory">*</span>');
				}
			});
			$(".arvChangedElement input").addClass('isRequired');
		} else if ($(this).val() == 'no') {
			$(".arvChangedElement label .mandatory").remove();
			$(".arvChangedElement input").removeClass('isRequired');
			$(".arvChangedElement input").val('');
			$(".arvChangedElement").hide();
		}
	});

	$("input:radio[name=isPatientNew]").click(function() {
		if ($(this).val() == 'yes') {
			$(".du").css("visibility", "visible");
			if ($(".du label .mandatory").length === 0) {
				$(".du label").append(' <span class="mandatory">*</span>');
			}
			$("#dateOfArtInitiation").addClass('isRequired');
		} else if ($(this).val() == 'no') {
			$(".du").css("visibility", "hidden");
			$(".du label .mandatory").remove();
			$("#dateOfArtInitiation").removeClass('isRequired');
			$("#dateOfArtInitiation").val('');
		}
	});
	$("#gender").change(function() {
		if ($(this).val() == 'female') {
			//$('#keyPopulation').html('<option value=""><?= _translate("-- Select --"); ?> </option><option value="ps" <?php echo (trim((string) $vlQueryInfo['key_population']) === "ps") ? 'selected="selected"' : ''; ?>><?= _translate("PS"); ?> </option>');
			showFemaleSection();
		} else if ($(this).val() == 'male') {
			//$('#keyPopulation').html('<option value=""><?= _translate("-- Select --"); ?> </option><option value="cps" <?php echo (trim((string) $vlQueryInfo['key_population']) === "cps") ? 'selected="selected"' : ''; ?>><?= _translate("CPS"); ?> </option><option value="msm" <?php echo (trim((string) $vlQueryInfo['key_population']) === "msm") ? 'selected="selected"' : ''; ?>><?= _translate("MSM"); ?> </option>');
			hideFemaleSection();
		}
	});

	$("#pregnant").change(function() {
		if ($(this).val() == 'yes') {
			$(".trimesterSection").show();
			addMandatoryField('trimester');
		} else {
			removeMandatoryField('trimester');
			$(".trimesterSection").hide();
		}
	});

	function checkreasonForVLTesting() {
		var reasonForVLTesting = $("#reasonForVLTesting").val();
		if (reasonForVLTesting == "other") {
			$(".newreasonForVLTesting").show();
			$("#newreasonForVLTesting").addClass("isRequired");
		} else {
			$(".newreasonForVLTesting").hide();
			$("#newreasonForVLTesting").removeClass("isRequired");
		}
	}

	function checkSpecimenType() {
		var specimenType = $("#specimenType").val();
		if (specimenType == 2) {
			$(".plasmaElement").show();
		} else {
			$(".plasmaElement").hide();
		}
	}

	function checkTestStatus() {
		var status = $("#isSampleRejected").val();
		if (status == 'yes') {
			$('#vlResult').attr('disabled', false);
			$('#vlLog').attr('disabled', false);
			$(".rejectionReason").show();
			$("#rejectionReason").addClass('isRequired');
			$("#vlResult").val('').css('pointer-events', 'none');
			$("#vlLog").val('').css('pointer-events', 'none');
			$("#rejectionReason").val('').css('pointer-events', 'auto');
			$(".vlResult, .vlLog").hide();
			$('#reasonForFailure').val('');
			$('#reasonForFailure').removeClass('isRequired');
			$('.reasonForFailure').hide();
		} else {
			$(".rejectionReason").hide();
			$("#rejectionReason").val('');
			$("#rejectionReason").removeClass('isRequired');
			$("#vlResult").css('pointer-events', 'auto');
			$("#vlLog").css('pointer-events', 'auto');
			$("#vlResult").val('').css('pointer-events', 'auto');
			$("#vlLog").val('').css('pointer-events', 'auto');
			$(".vlResult, .vlLog").show();
		}
	}

	$('#vlResult').on('change', function() {
		if ($(this).val() != "") {
			$('.authorisation').addClass("isRequired");
		} else {
			$('.authorisation').removeClass("isRequired");
		}
		if ($(this).val().trim().toLowerCase() == 'failed' || $(this).val().trim().toLowerCase() == 'error') {
			if ($(this).val().trim().toLowerCase() == 'failed') {
				$('.reasonForFailure').show();
				$('#reasonForFailure').addClass('isRequired');
			}
			$('#vlLog').attr('readonly', true);
		} else {
			$('.reasonForFailure').hide();
			$('#reasonForFailure').removeClass('isRequired');
			$('#vlLog').attr('readonly', false);
		}
	});

	$('#serialNo').on('change', function() {
		artDateVisibility = '<?php echo $duVisibility; ?>';
		if ($(this).val() != "") {
			$("#reasonForVLTesting").removeClass("isRequired");
			$("#artRegimen").removeClass("isRequired");
			$("#dateOfArtInitiation").removeClass("isRequired");
			$("#viralLoadNo").removeClass("isRequired");
		} else {
			$("#reasonForVLTesting").addClass("isRequired");
			$("#artRegimen").addClass("isRequired");
			if (artDateVisibility == 'visible') {
				$("#dateOfArtInitiation").addClass("isRequired");
			}
			$("#viralLoadNo").addClass("isRequired");
		}
	});

	function checkRejectionReason() {
		var rejectionReason = $("#rejectionReason").val();
		if (rejectionReason == "other") {
			$(".newRejectionReason").show();
			$("#newRejectionReason").addClass('isRequired');
		} else {
			$(".newRejectionReason").hide();
			$("#newRejectionReason").removeClass('isRequired');
		}
	}

	function checkLastVLTestDate() {
		var artInitiationDate = $("#dateOfArtInitiation").val();
		var dateOfLastVLTest = $("#lastViralLoadTestDate").val();
		if ($.trim(artInitiationDate) != '' && $.trim(dateOfLastVLTest) != '') {
			if (moment(artInitiationDate).isAfter(dateOfLastVLTest)) {
				alert("Dernier test de charge virale Les données ne peuvent pas être antérieures à la date d'initiation de l'ARV!");
				$("#lastViralLoadTestDate").val("");
			}
		}
	}

	function calculateLogValue(obj) {
		if (obj.id == "vlResult") {
			absValue = $("#vlResult").val();
			absValue = Number.parseFloat(absValue).toFixed();
			if (absValue != '' && absValue != 0 && !isNaN(absValue)) {
				//$("#vlResult").val(absValue);
				$("#vlLog").val(Math.round(Math.log10(absValue) * 100) / 100);
			} else {
				$("#vlLog").val('');
			}
		}
		if (obj.id == "vlLog") {
			logValue = $("#vlLog").val();
			if (logValue != '' && logValue != 0) {
				var absVal = Math.round(Math.pow(10, logValue) * 100) / 100;
				if (absVal != 'Infinity' && !isNaN(absVal)) {
					$("#vlResult").val(Math.round(Math.pow(10, logValue) * 100) / 100);
				} else {
					$("#vlResult").val('');
				}
			}
		}
	}

	function validateNow() {

		clearDatePlaceholderValues('input.date, input.dateTime');

		flag = deforayValidator.init({
			formId: 'editVlRequestForm'
		});
		if (flag) {
			$.blockUI();
			document.getElementById('editVlRequestForm').submit();
		}
	}

	function getVlResults(platformInfo) {
		if (!platformInfo) {
			$("#vlResult").attr("disabled", true);
			return;
		}

		var str1 = platformInfo.split("##");
		//Get VL results by platform id
		var platformId = str1[3];
		$("#possibleVlResults").html('');
		$.post("/vl/requests/getVlResults.php", {
				instrumentId: platformId,
			},
			function(data) {
				if (data != "") {
					$("#possibleVlResults").html(data);
					$("#vlResult").attr("disabled", false);
				}
			});
	}

	function storageEditableSelect(id, _fieldName, fieldId, table, _placeholder) {
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
						fieldId: fieldId,
						tableName: table,
						q: params.term, // search term
						page: params.page,
						labId: $("#labId").val(),
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
</script>