<?php

use App\Services\CommonService;
use App\Services\StorageService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var StorageService $storageService */
$storageService = ContainerRegistry::get(StorageService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);

$facility = $general->generateSelectOptions($healthFacilities, $vlQueryInfo['facility_id'], _translate("-- Select --"));
//Get selected state
$stateQuery = "SELECT * FROM facility_details WHERE facility_id= ?";
$stateResult = $db->rawQueryOne($stateQuery, [$vlQueryInfo['facility_id']]);
$stateResult['facility_state'] ??= "";
$stateResult['facility_district'] ??= "";

//district details
$districtQuery = "SELECT DISTINCT facility_district FROM facility_details WHERE facility_state=?";
$districtResult = $db->rawQuery($districtQuery, [$stateResult['facility_state']]);

$provinceQuery = "SELECT geo_code FROM geographical_divisions WHERE geo_name=?";
$provinceResult = $db->rawQueryOne($provinceQuery, [$stateResult['facility_state']]);

$provinceResult['geo_code'] ??= '';


//get ART list
$aQuery = "SELECT * from r_vl_art_regimen WHERE art_status like 'active' ORDER by parent_art ASC, art_code ASC";
$aResult = $db->query($aQuery);

//Set plasma storage temp.
if (isset($vlQueryInfo['specimen_type']) && $vlQueryInfo['specimen_type'] != 2) {
	$vlQueryInfo['plasma_storage_temperature'] = '';
}

$disable = "disabled = 'disabled'";
$reasonChange = "";
if (isset($vlQueryInfo['reason_for_result_changes']) && $vlQueryInfo['reason_for_result_changes'] != "") {
	$result = explode("##", (string) $vlQueryInfo['reason_for_result_changes']);
	$reasonChange = $result[1];
}


$duVisibility = (trim((string) $vlQueryInfo['is_patient_new']) === "" || trim((string) $vlQueryInfo['is_patient_new']) === "no") ? 'hidden' : 'visible';
$femaleSectionDisplay = (trim((string) $vlQueryInfo['patient_gender']) === "" || trim((string) $vlQueryInfo['patient_gender']) === "male") ? 'none' : 'block';

$formAttributes = json_decode((string) $vlQueryInfo['form_attributes']);
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
			<li class="active">ENter VL Result</li>
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
				<form class="form-horizontal" method="post" name="updateVlRequestForm" id="updateVlRequestForm" autocomplete="off" action="updateVlTestResultHelper.php">
					<div class="box-body">
						<div class="box box-default">
							<div class="box-body">
								<div class="box-header with-border">
									<h3 class="box-title">1. Réservé à la structure de soins</h3>
								</div>
								<div class="box-header with-border">
									<h3 class="box-title">Information sur la structure de soins</h3>
								</div>
								<!-- <h4 id="sampleCodeValue">exemple de code:< ?php echo $vlQueryInfo['sample_code']; ?></h4>-->
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr>
										<td><label for="sampleCode">Échantillon id </label></td>
										<td>
											<input type="text" class="form-control" id="sampleCode" name="sampleCode" placeholder="Échantillon id" title="Please enter échantillon id" <?php echo $disable; ?> value="<?= htmlspecialchars((string) $vlQueryInfo['sample_code']); ?>" style="width:100%;" />
										</td>
										<td></td>
										<td></td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td><label for="province">Province </label></td>
										<td>
											<select class="form-control" name="province" id="province" title="Please choose province" <?php echo $disable; ?> style="width:100%;">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php
												foreach ($pdResult as $provinceName) { ?>
													<option value="<?php echo $provinceName['geo_name'] . "##" . $provinceName['geo_code']; ?>" <?php echo (strtolower((string) $stateResult['facility_state']) . "##" . strtolower((string) $provinceResult['geo_code']) === strtolower((string) $provinceName['geo_name']) . "##" . strtolower((string) $provinceName['geo_code'])) ? "selected='selected'" : "" ?>><?php echo ($provinceName['geo_name']); ?></option>
												<?php } ?>
											</select>
										</td>
										<td><label for="district">Zone de santé </label></td>
										<td>
											<select class="form-control" name="district" id="district" title="Please choose district" <?php echo $disable; ?> style="width:100%;">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php
												foreach ($districtResult as $districtName) {
												?>
													<option value="<?php echo $districtName['facility_district']; ?>" <?php echo ($stateResult['facility_district'] == $districtName['facility_district']) ? "selected='selected'" : "" ?>><?php echo ($districtName['facility_district']); ?></option>
												<?php
												}
												?>
											</select>
										</td>
										<td><label for="clinicName">POINT DE COLLECT </label></td>
										<td>
											<select class="form-control" name="clinicName" id="clinicName" title="<?= _translate("Please choose facility"); ?>" <?php echo $disable; ?> style="width:100%;">
												<?= $facility; ?>
											</select>
										</td>
									</tr>
									<tr>
										<td><label for="clinicianName">Demandeur </label></td>
										<td>
											<input type="text" class="form-control" id="clinicianName" name="clinicianName" placeholder="Demandeur" title="<?= _translate("Please enter requesting clinician name"); ?>" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['request_clinician_name']; ?>" style="width:100%;" />
										</td>
										<td><label for="clinicanTelephone">Téléphone </label></td>
										<td>
											<input type="text" class="form-control forceNumeric" id="clinicanTelephone" name="clinicanTelephone" placeholder="Téléphone" title="<?= _translate("Please enter phone number"); ?>" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['request_clinician_phone_number']; ?>" style="width:100%;" />
										</td>
										<td><label for="supportPartner">Partnaire d'appui </label></td>
										<td>
											<!-- <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire d'appui" title="Please enter Partenaire d'appui" <?php echo $disable; ?> value="< ?php echo $vlQueryInfo['facility_support_partner']; ?>" style="width:100%;"/> -->
											<select class="form-control" name="implementingPartner" id="implementingPartner" title="<?= _translate("Please choose implementing partner"); ?>" <?php echo $disable; ?> style="width:100%;">
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
										<td><label for="">Date de la demande </label></td>
										<td>
											<input type="text" class="form-control date" id="dateOfDemand" name="dateOfDemand" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de la demande" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['date_test_ordered_by_physician']; ?>" style="width:100%;" />
										</td>
										<td><label for="fundingSource">Source de financement </label></td>
										<td>
											<select class="form-control" name="fundingSource" id="fundingSource" title="Please choose source de financement" <?php echo $disable; ?> style="width:100%;">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php
												foreach ($fundingSourceList as $fundingSource) {
												?>
													<option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $vlQueryInfo['funding_source']) ? 'selected="selected"' : ''; ?>><?= $fundingSource['funding_source_name']; ?></option>
												<?php } ?>
											</select>
										</td>
										<td><label for="labId">Nom du laboratoire </label> </td>
										<td>
											<select name="labId" id="labId" class="form-control" title="Please choose laboratoire" <?php echo $disable; ?> style="width:100%;">
												<?= $general->generateSelectOptions($testingLabs, $vlQueryInfo['lab_id'], '-- Sélectionner --'); ?>
											</select>
										</td>
									</tr>
								</table>
								<div class="box-header with-border">
									<h3 class="box-title">Information sur le patient </h3>
								</div>
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr>
										<td style="width: 15% !important;"><label for="patientArtNo">Code du patient </label></td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control" id="patientArtNo" name="patientArtNo" placeholder="Code du patient" title="<?= _translate("Please enter the Patient ID"); ?>" <?php echo $disable; ?> value="<?= htmlspecialchars((string) $vlQueryInfo['patient_art_no']); ?>" style="width:100%;" />
										</td>
										<td style="width:15%;"><label for="">Date de naissance </label></td>
										<td style="width:35%;">
											<input type="text" class="form-control date" id="dob" name="dob" placeholder="<?= _translate("Please enter date"); ?>" title="Please select date de naissance" <?php echo $disable; ?> value="<?= htmlspecialchars((string) $vlQueryInfo['patient_dob']); ?>" style="width:100%;" />
										</td>
									</tr>
									<tr>
										<td style="width:15%;"><label for="ageInYears">Âge en années </label></td>
										<td style="width:35%;">
											<input type="text" class="form-control forceNumeric" id="ageInYears" name="ageInYears" placeholder="Aannées" title="Please enter àge en années" <?php echo $disable; ?> value="<?= htmlspecialchars((string) $vlQueryInfo['patient_age_in_years']); ?>" style="width:100%;" />
										</td>
										<td style="width:15%;"><label for="ageInMonths">Âge en mois </label></td>
										<td style="width:35%;">
											<input type="text" class="form-control forceNumeric" id="ageInMonths" name="ageInMonths" placeholder="Mois" title="Please enter àge en mois" <?php echo $disable; ?> value="<?= htmlspecialchars((string) $vlQueryInfo['patient_age_in_months']); ?>" style="width:100%;" />
										</td>
									</tr>
									<tr>
										<td style="width:15%;"><label for="sex">Sexe </label></td>
										<td style="width:35%;">
											<!--<label class="radio-inline" style="padding-left:12px !important;margin-left:0;">&nbsp;M</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="genderMale" name="gender" < ?php echo $disable; ?> value="male" title="<?= _translate("Please select sex"); ?>" <?php echo (trim((string) $vlQueryInfo['patient_gender']) === "male") ? 'checked="checked"' : ''; ?>>
											</label>
											<label class="radio-inline" style="padding-left:12px !important;margin-left:0;">F</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="genderFemale" name="gender" < ?php echo $disable; ?> value="female" title="<?= _translate("Please select sex"); ?>" <?php echo (trim((string) $vlQueryInfo['patient_gender']) === "female") ? 'checked="checked"' : ''; ?>>
											</label>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">KP</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="genderKp" name="gender" < ?php echo $disable; ?> value="kp" title="<?= _translate("Please select sex"); ?>" <?php echo (trim((string) $vlQueryInfo['patient_gender']) === "kp") ? 'checked="checked"' : ''; ?>>
											</label>-->
											<select name="gender" id="gender" class="form-control" title="Please select sex" <?php echo $disable; ?>>
												<option value="male" <?php echo (trim((string) $vlQueryInfo['patient_gender']) === "male") ? 'selected="selected"' : ''; ?>><?= _translate("M"); ?></option>
												<option value="female" <?php echo (trim((string) $vlQueryInfo['patient_gender']) === "female") ? 'selected="selected"' : ''; ?>><?= _translate("F"); ?></option </select>
										</td>
										<!-- <td style="width: 15% !important;"><label>KP </label></td>
										<td style="width: 35% !important;">
											<select class="form-control" name="keyPopulation" id="keyPopulation" title="<?= _translate('Please choose KP'); ?>" <?php echo $disable; ?>>
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<option value="ps" <?php echo (trim((string) $vlQueryInfo['key_population']) === "ps") ? 'selected="selected"' : ''; ?>><?= _translate("PS"); ?> </option>
												<option value="cps" <?php echo (trim((string) $vlQueryInfo['key_population']) === "cps") ? 'selected="selected"' : ''; ?>><?= _translate("CPS"); ?> </option>
												<option value="msm" <?php echo (trim((string) $vlQueryInfo['key_population']) === "msm") ? 'selected="selected"' : ''; ?>><?= _translate("MSM"); ?> </option>
											</select>
										</td> -->

									</tr>
									<tr>
										<td style="width: 15% !important;"><label>Régime ARV en cours </label></td>
										<td style="width: 35% !important;">
											<select class="form-control" name="artRegimen" id="artRegimen" title="Please choose régime ARV en cours" <?php echo $disable; ?> onchange="checkARTRegimenValue();" style="width:100%;">
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
											<input type="text" class="form-control phone-number" id="patientPhoneNumber" name="patientPhoneNumber" placeholder="Téléphone" title="Veuillez entrer le téléphone" value="<?php echo $vlQueryInfo['patient_mobile_number']; ?>" style="width:100%;" <?php echo $disable; ?> />
										</td>

									</tr>
									<tr>
										<td style="width:15%;">
											<label for="isPatientNew">Si S/ARV </label>
										</td>
										<td style="width: 35% !important;">
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Oui</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="isPatientNewYes" name="isPatientNew" <?php echo ($vlQueryInfo['is_patient_new'] == 'yes') ? 'checked="checked"' : ''; ?> value="yes" <?php echo $disable; ?> title="Please check Si S/ARV">
											</label>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Non</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="isPatientNewNo" name="isPatientNew" <?php echo ($vlQueryInfo['is_patient_new'] == 'no') ? 'checked="checked"' : ''; ?> <?php echo $disable; ?> value="no">
											</label>
										</td>
										<td style="width: 15% !important;" class="du"><label for="">Date du début des ARV </label></td>
										<td tyle="width: 35% !important;" class="du">
											<input type="text" class="form-control date" id="dateOfArtInitiation" name="dateOfArtInitiation" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date du début des ARV" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['treatment_initiated_date']; ?>" style="width:100%;" /> &nbsp;(Jour/Mois/Année) </span>
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;">
											<label for="hasChangedRegimen">Ce patient a-t-il déjà changé de régime de
												traitement? </label>
										</td>
										<td style="width: 35% !important;">
											<label class="radio-inline">Oui </label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="changedRegimenYes" name="hasChangedRegimen" value="yes" title="<?= _translate("Please choose if ARV Regimen changed"); ?>" <?php echo $disable; ?> <?php echo (trim((string) $vlQueryInfo['has_patient_changed_regimen']) === "yes") ? 'checked="checked"' : ''; ?>>
											</label>
											<label class="radio-inline">Non </label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="changedRegimenNo" name="hasChangedRegimen" value="no" title="<?= _translate("Please choose if ARV Regimen changed"); ?>" <?php echo $disable; ?> <?php echo (trim((string) $vlQueryInfo['has_patient_changed_regimen']) === "no") ? 'checked="checked"' : ''; ?>>
											</label>
										</td>
									</tr>
									<tr class="arvChangedElement" style="display:<?php echo (trim((string) $vlQueryInfo['has_patient_changed_regimen']) === "yes") ? '' : 'none'; ?>;">
										<td style="width: 15% !important;"><label for="reasonForArvRegimenChange" class="arvChangedElement">Motif
												de changement de régime ARV </label></td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control arvChangedElement" id="reasonForArvRegimenChange" name="reasonForArvRegimenChange" placeholder="Motif de changement de régime ARV" title="Please enter motif de changement de régime ARV" value="<?php echo $vlQueryInfo['reason_for_regimen_change']; ?>" <?php echo $disable; ?> style="width:100%;" />
										</td>
										<td style="width: 15% !important;"><label for="">Date du changement de régime ARV </label></td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control date" id="dateOfArvRegimenChange" name="dateOfArvRegimenChange" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date du changement de régime ARV" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['regimen_change_date']; ?>" style="width:100%;" /> (Jour/Mois/Année)
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;"><label for="reasonForRequest">Motif de la demande <span class="mandatory">*</span></label></td>
										<td style="width: 35% !important;">
											<select name="vlTestReason" id="vlTestReason" class="form-control" title="Please choose motif de la demande" <?php echo $disable; ?>>
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php
												foreach ($vlTestReasonResult as $tReason) {
												?>
													<option value="<?php echo $tReason['test_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_vl_testing'] == $tReason['test_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ($tReason['test_reason_name']); ?></option>
												<?php } ?>
												<option value="other">Autre</option>
											</select>
										</td>
										<td style="width: 15% !important;"><label for="viralLoadNo">Charge virale N </label>
										</td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control" id="viralLoadNo" name="viralLoadNo" placeholder="Charge virale N" title="Please enter charge virale N" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['vl_test_number']; ?>" style="width:100%;" />
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;"><label for="lastViralLoadResult">Résultat dernière charge virale </label>
										</td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control" id="lastViralLoadResult" name="lastViralLoadResult" placeholder="Résultat dernière charge virale" title="Please enter résultat dernière charge virale" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['last_viral_load_result']; ?>" style="width:100%;" />copies/mL
										</td>
										<td style="width: 15% !important;"><label for="">Date dernière charge virale (demande) </label></td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control date" id="lastViralLoadTestDate" name="lastViralLoadTestDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date dernière charge virale" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['last_viral_load_date']; ?>" style="width:100%;" />
										</td>
									</tr>
									<tr class="femaleSection">
										<td style="width:10% !important;"><strong>Si Femme : </strong></td>
										<td colspan="1">
											<label for="breastfeeding">allaitante ?</label>
											<select class="form-control" id="breastfeeding" name="breastfeeding" title="Please check Si allaitante" <?php echo $disable; ?>>
												<option value=""> -- Select -- </option>
												<option id="breastfeedingYes" <?php echo (trim((string) $vlQueryInfo['is_patient_breastfeeding']) === "yes") ? 'selected="selected"' : ''; ?> value="yes">Oui</option>
												<option id="breastfeedingNo" <?php echo (trim((string) $vlQueryInfo['is_patient_breastfeeding']) === "no") ? 'selected="selected"' : ''; ?> value="no">Non</option>
											</select>
										</td>
										<td colspan="1">
											<label for="patientPregnant">Ou enceinte ?</label>
											<select class="form-control" id="pregnant" name="patientPregnant" title="Please check Si Ou enceinte" <?php echo $disable; ?>>
												<option value=""> -- Select -- </option>
												<option id="pregYes" <?php echo (trim((string) $vlQueryInfo['is_patient_pregnant']) === "yes") ? 'selected="selected"' : ''; ?> value="yes">Oui</option>
												<option id="pregNo" <?php echo (trim((string) $vlQueryInfo['is_patient_pregnant']) === "no") ? 'selected="selected"' : ''; ?> value="no">Non</option>
											</select>
										</td>
										<td colspan="1">
											<label for="trimester">Si Femme enceinte :</label>
											<select class="form-control" id="trimester" name="trimester" title="Please check trimestre" <?php echo $disable; ?>>
												<option value=""> -- Select -- </option>
												<option id="trimester1" <?php echo (trim((string) $vlQueryInfo['pregnancy_trimester']) === "1") ? 'selected="selected"' : ''; ?> value="1">Trimestre 1</option>
												<option id="trimester2" <?php echo (trim((string) $vlQueryInfo['pregnancy_trimester']) === "2") ? 'selected="selected"' : ''; ?> value="2">Trimestre 2</option>
												<option id="trimester3" <?php echo (trim((string) $vlQueryInfo['pregnancy_trimester']) === "3") ? 'selected="selected"' : ''; ?> value="3">Trimestre 3</option>
											</select>
										</td>

									</tr>
									<tr class="newVlTestReason" style="display:none;">
										<td style="width: 15% !important;"><label for="newVlTestReason">Autre, à préciser <span class="mandatory">*</span></label></td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control" name="newVlTestReason" id="newVlTestReason" placeholder="Virale Demande Raison" title="Please enter virale demande raison" <?php echo $disable; ?> style="width:100%;" value="<?php echo $vlQueryInfo['reason_for_vl_testing_other']; ?>">
										</td>
									</tr>
									<tr>
										<td colspan="8"><label class="radio-inline" style="margin:0;padding:0;">A
												remplir par le service demandeur dans la structure de soins</label></td>
									</tr>
								</table>
								<div class="box-header with-border">
									<h3 class="box-title">Informations sur le prélèvement </h3>
								</div>
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr>
										<td><label for="">Date du prélèvement </label></td>
										<td>
											<input type="text" class="form-control dateTime" id="sampleCollectionDate" name="sampleCollectionDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date du prélèvement" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['sample_collection_date']; ?>" style="width:100%;" />
										</td>
										<td></td>
										<td></td>
									</tr>
									<?php
									if (isset($arr['sample_type']) && trim((string) $arr['sample_type']) === "enabled") { ?>
										<tr>
											<td><label for="specimenType">Type déchantillon </label></td>
											<td>
												<select name="specimenType" id="specimenType" class="form-control" title="Please choose type déchantillon" <?php echo $disable; ?> style="width:100%;">
													<option value=""><?= _translate("-- Select --"); ?> </option>
													<?php
													foreach ($sResult as $type) {
													?>
														<option value="<?php echo $type['sample_id']; ?>" <?php echo ($vlQueryInfo['specimen_type'] == $type['sample_id']) ? 'selected="selected"' : ''; ?>><?= $type['sample_name']; ?></option>
													<?php
													}
													?>
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
											<input type="text" class="form-control forceNumeric" id="conservationTemperature" name="conservationTemperature" placeholder="Température de conservation" title="Please enter température de conservation" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['plasma_conservation_temperature']; ?>" style="width:100%;" />&nbsp;(°C)
										</td>
										<td style="text-align:center;"><label for="durationOfConservation">Durée de
												conservation </label></td>
										<td>
											<input type="text" class="form-control" id="durationOfConservation" name="durationOfConservation" placeholder="e.g 9/1" title="Please enter durée de conservation" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['plasma_conservation_duration']; ?>" style="width:100%;" />&nbsp;(Jour/Heures)
										</td>
									</tr>
									<tr>
										<td><label for="">Date de départ au Labo biomol </label></td>
										<td>
											<input type="text" class="form-control dateTime" id="dateDispatchedFromClinicToLab" name="dateDispatchedFromClinicToLab" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de départ au Labo biomol" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['sample_dispatched_datetime']; ?>" style="width:100%;" />
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
						<div class="box box-primary">
							<div class="box-body">
								<div class="box-header with-border">
									<h3 class="box-title">2. Réservé au Laboratoire de biologie moléculaire </h3>
								</div>
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr style="<?php echo ($sCode != '') ? 'display:none' : ''; ?>">
										<td><label for="">Date de réception de l'échantillon <span class="mandatory">*</span> </label></td>
										<td>
											<input type="text" class="form-control dateTime isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de réception de l'échantillon" <?php echo $labFieldDisabled; ?> value="<?php echo $vlQueryInfo['sample_received_at_lab_datetime']; ?>" style="width:100%;" />
										</td>
										<td style="width: 25%;"><label for=""><?php echo _translate('Freezer'); ?> <em class="fas fa-edit"></em> :
											</label></td>
										<td style="width: 25%;">
											<select class="form-control select2 editableSelect" id="freezer" name="freezer" placeholder="<?php echo _translate('Enter Freezer'); ?>" title="<?php echo _translate('Please enter Freezer'); ?>">
												<?= $general->generateSelectOptions($storageInfo, $storageObj->freezer, '-- Select --') ?>
											</select>
										</td>
									</tr>
									<tr>
										<td style="width: 25%;"><label for="rack"><?php echo _translate('Rack'); ?> : </label> </td>
										<td style="width: 25%;">
											<input type="text" class="form-control" id="rack" name="rack" value="<?= $storageObj->rack; ?>" placeholder="<?php echo _translate('rack'); ?>" title="<?php echo _translate('Please enter rack'); ?>" value="<?= $storageObj->rack; ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
										</td>
										<td style="width: 25%;"><label for=""><?php echo _translate('Box'); ?> :
											</label></td>
										<td style="width: 25%;">
											<input type="text" class="form-control" id="box" name="box" value="<?= $storageObj->box; ?>" placeholder="<?php echo _translate('box'); ?>" title="<?php echo _translate('Please enter box'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
										</td>
									</tr>
									<tr>
										<td style="width: 25%;"><label for="position"><?php echo _translate('Position'); ?> : </label> </td>
										<td style="width: 25%;">
											<input type="text" class="form-control" id="position" name="position" value="<?= $storageObj->position; ?>" placeholder="<?php echo _translate('Position'); ?>" title="<?php echo _translate('Please enter position'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
										</td>
										<td style="width: 25%;"><label for=""><?php echo _translate('Volume (ml)'); ?> :
											</label></td>
										<td style="width: 25%;">
											<input type="text" class="form-control" id="volume" name="volume" value="<?= $storageObj->volume; ?>" placeholder="<?php echo _translate('Volume'); ?>" title="<?php echo _translate('Please enter volume'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
										</td>
									</tr>
									<tr>
										<td><label for="isSampleRejected">Décision prise <span class="mandatory">*</span></label></td>
										<td>
											<select class="form-control result-focus isRequired" id="isSampleRejected" name="isSampleRejected" title="<?= _translate('Please select if sample is rejected'); ?>" <?php echo $labFieldDisabled; ?> onchange="checkTestStatus();" style="width:100%;">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<option value="no" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'selected="selected"' : ''; ?>>Echantillon accepté</option>
												<option value="yes" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'selected="selected"' : ''; ?>>Echantillon rejeté</option>
											</select>
										</td>
									</tr>
									<tr class="rejectionReason" style="display:<?php echo ($vlQueryInfo['result_status'] == 4) ? '' : 'none'; ?>;">
										<td><label for="rejectionReason">Motifs de rejet <span class="mandatory rejection-mandatory">*</span></label></td>
										<td>
											<select class="form-control" id="rejectionReason" name="rejectionReason" title="<?= _translate('Please select reason for rejection'); ?>" <?php echo $labFieldDisabled; ?> onchange="checkRejectionReason();" style="width:100%;">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php foreach ($rejectionResult as $rjctReason) { ?>
													<option value="<?php echo $rjctReason['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $rjctReason['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ($rjctReason['rejection_reason_name']); ?></option>
												<?php } ?>
												<option value="other">Autre</option>
											</select>
											<input type="text" class="form-control newRejectionReason" id="newRejectionReason" name="newRejectionReason" placeholder="Motifs de rejet" title="Please enter motifs de rejet" <?php echo $labFieldDisabled; ?> style="width:100%;display:none;" />
										</td>
										<th scope="row" class="rejectionReason" style="display:none;">

											<label for="rejectionDate"><?= _translate("Rejection Date"); ?> <span class="mandatory">*</span></label>
										</th>
										<td class="rejectionReason" style="display:none;">
											<input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" />
										</td>
									</tr>

									<tr>
										<td><label for="sampleTestingDateAtLab">Date de réalisation de la charge virale <span class="mandatory test-date-mandatory">*</span></label></td>
										<td>
											<input type="text" class="form-control isRequired" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de réalisation de la charge virale" <?php echo $labFieldDisabled; ?> value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>" style="width:100%;" />
										</td>
										<td><label for="testingPlatform">Technique utilisée <span class="mandatory test-date-mandatory">*</span></label></td>
										<td>
											<select name="testingPlatform" id="testingPlatform" class="form-control isRequired" title="Please choose VL Testing Platform" <?php echo $labFieldDisabled; ?> style="width:100%;" onchange="getVlResults(this.value)">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php foreach ($importResult as $mName) { ?>
													<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['instrument_id']; ?>" <?php echo ($vlQueryInfo['vl_test_platform'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] === $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']) ? "selected='selected'" : "" ?>><?php echo $mName['machine_name']; ?>
													</option>
												<?php } ?>
											</select>
										</td>
									</tr>
									<tr class="vlResult" style="<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'display: none;' : ''; ?>">
										<td class="vlResult"><label for="vlResult">Résultat</label></td>
										<td class="vlResult resultInputContainer">
											<input list="possibleVlResults" class="form-control result-fields labSection" id="vlResult" name="vlResult" placeholder="Select or Type VL Result" title="Please enter résultat" value="<?= htmlspecialchars((string) $vlQueryInfo['result']); ?>" oninput="calculateLogValue(this)">
											<datalist id="possibleVlResults">

											</datalist>
										</td>
										<td class="vlLog"><label for="vlLog">Log </label>
										</td>
										<td class="vlLog">
											<input type="text" class="form-control forceNumeric other-failed-results" id="vlLog" name="vlLog" placeholder="Log" title="Please enter log" value="<?= htmlspecialchars((string) $vlQueryInfo['result_value_log']); ?>" <?php echo $labFieldDisabled; ?> oninput="calculateLogValue(this)" style="width:100%;" />&nbsp;(copies/mL)
										</td>
									</tr>
									<?php if (count($reasonForFailure) > 0) { ?>
										<tr class="reasonForFailure vlResult" style="<?php echo (!isset($vlQueryInfo['result']) || $vlQueryInfo['result'] != 'Failed') ? 'display: none;' : ''; ?>">
											<th scope="row" class="reasonForFailure">
												<?php echo _translate("Reason for Failure"); ?>
											</th>
											<td class="reasonForFailure">
												<select name="reasonForFailure" id="reasonForFailure" class="form-control vlResult" title="Please choose reason for failure" style="width: 100%;">
													<?= $general->generateSelectOptions($reasonForFailure, $vlQueryInfo['reason_for_failure'], '-- Select --'); ?>
												</select>
											</td>
										</tr>
									<?php } ?>
									<tr>
										<td style="width:14%;"><label for="reviewedOn">Revu le <span class="mandatory">*</span></label></td>
										<td style="width:14%;">
											<input type="text" name="reviewedOn" value="<?php echo $vlQueryInfo['result_reviewed_datetime']; ?>" id="reviewedOn" class="dateTime form-control isRequired" placeholder="Revu le" title="Please enter the Revu le" />
										</td>
										<td style="width:14%;"><label for="reviewedBy">Revu par <span class="mandatory">*</span></label></td>
										<td style="width:14%;">
											<select name="reviewedBy" id="reviewedBy" class="select2 form-control isRequired" title="Please choose revu par" style="width: 100%;">
												<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_reviewed_by'], '-- Select --'); ?>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">Approuvé le <span class="mandatory">*</span></th>
										<td>
											<input type="text" name="approvedOnDateTime" id="approvedOnDateTime" value="<?php echo $vlQueryInfo['result_approved_datetime']; ?>" class="dateTime form-control isRequired" placeholder="Approuvé le" title="Please enter the Approuvé le" />
										</td>
										<th scope="row">Approuvé par <span class="mandatory">*</span></th>
										<td>
											<select name="approvedBy" id="approvedBy" class="select2 form-control isRequired" title="Please choose Approuvé par" style="width: 100%;">
												<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_approved_by'], '-- Select --'); ?>
											</select>
										</td>
									</tr>
									<tr>
										<td class="reasonForResultChanges" style="display:<?php if ($reasonChange !== "") {
																								echo "block";
																							} else {
																								echo "none;";
																							} ?>">
											<label for="reasonForResultChanges">
												<?= _translate("Enter reason for changing the result"); ?> <span class="mandatory">*</span>
											</label>
										</td>
										<td class="reasonForResultChanges" style="display:<?php if ($reasonChange !== "") {
																								echo "block";
																							} else {
																								echo "none;";
																							} ?>none;">
											<textarea class="form-control" name="reasonForResultChanges" id="reasonForResultChanges" placeholder="<?= _translate("Enter reason for changing the result"); ?>" title="<?= _translate("Enter reason for changing the result"); ?>" style="width:100%;"><?php if ($reasonChange !== "") {
																																																																											echo $reasonChange;
																																																																										} ?></textarea>
										</td>
									</tr>
								</table>
							</div>
						</div>
						<div class="box-header with-border">
							<label class="radio-inline" style="margin:0;padding:0;">1. Biffer la mention inutile <br>2.
								Sélectionner un seul régime de traitement </label>
						</div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="revised" id="revised" value="no" />
						<input type="hidden" id="rSrc" name="rSrc" value="er" />
						<input type="hidden" id="duplicatePatientArtNo" name="duplicatePatientArtNo" value="<?= htmlspecialchars((string) $vlQueryInfo['patient_art_no']); ?>" />
						<input type="hidden" name="reasonForResultChangesHistory" id="reasonForResultChangesHistory" value="<?php echo base64_encode((string) $vlQueryInfo['reason_for_result_changes']); ?>" />
						<input type="hidden" id="vlSampleId" name="vlSampleId" value="<?= htmlspecialchars((string) $vlQueryInfo['vl_sample_id']); ?>" />
						<input type="hidden" name="sampleCode" id="sampleCode" value="<?= ($vlQueryInfo['sample_code']); ?>" />
						<input type="hidden" name="artNo" id="artNo" value="<?= ($vlQueryInfo['patient_art_no']); ?>" />
						<input type="hidden" name="labId" id="labId" value="<?= ($vlQueryInfo['lab_id']); ?>" />
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
						<a href="vlTestResult.php" class="btn btn-default"> Cancel</a>
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
	$(document).ready(function() {
		$(".select2").select2();
		$(".select2").select2({
			tags: true
		});

		storageEditableSelect('freezer', 'storage_code', 'storage_id', 'lab_storage', 'Freezer Code');

		getVlResults($("#testingPlatform").val());
		showFemaleSection('<?php echo $femaleSectionDisplay; ?>');

		if ($("#status").val() == 4) {
			$(".rejectionReason").show();
			$("#rejectionReason").addClass('isRequired');
			// $("#vlResult").val('').css('pointer-events', 'none');
			// $("#vlLog").val('').css('pointer-events', 'none');
			// $(".vlResult, .vlLog").hide();
			// $("#vlResult").removeClass('isRequired');
		} else {
			$(".rejectionReason").hide();
			$("#rejectionReason").removeClass('isRequired');
			// $("#vlResult").css('pointer-events', 'auto');
			// $("#vlLog").css('pointer-events', 'auto');
			// $(".vlResult, .vlLog").show();
			// $("#vlResult").addClass('isRequired');
		}
		checkreasonForVLTesting();
		/*$('#labId').select2({
			placeholder: "Select Nom du laboratoire"
		});*/
		$('#testingPlatform').select2({
			placeholder: "Select Technique utilisée"
		});
		$('#reviewedBy').select2({
			placeholder: "Select Revu par"
		});
		$('#approvedBy').select2({
			placeholder: "Select Approuvé par"
		});
	});

	function showFemaleSection(genderProp) {
		if (genderProp == "none") {
			$(".femaleSection").hide();
		} else {
			$(".femaleSection").show();
		}
	}

	function checkreasonForVLTesting() {
		var reasonForVLTesting = $("#vlTestReason").val();
		if (reasonForVLTesting == "other") {
			$(".newVlTestReason").show();
		} else {
			$(".newVlTestReason").hide();
		}
	}

	function checkTestStatus() {
		const status = $("#isSampleRejected").val();

		if (status === 'yes') {
			$(".rejectionReason").show();
			$("#rejectionReason").addClass('isRequired');
			$("#rejectionDate").addClass('isRequired');

			// Hide mandatory asterisks
			$(".test-date-mandatory").hide();
			$(".rejection-mandatory").show();

			$(".vlResult, .vlLog").hide();
			$("#vlResult, #vlLog").val('').css('pointer-events', 'none');
			$("#vlResult").removeClass('isRequired');
			$("#sampleTestingDateAtLab").removeClass('isRequired');
			$("#testingPlatform").removeClass('isRequired');
		} else {
			$(".rejectionReason").hide();
			$("#rejectionReason").removeClass('isRequired');
			$("#rejectionDate").removeClass('isRequired');

			// Show mandatory asterisks
			$(".test-date-mandatory").show();
			$(".rejection-mandatory").hide();

			$(".vlResult, .vlLog").show();
			$("#vlResult, #vlLog").val('').css('pointer-events', 'auto');
			$("#vlResult").addClass('isRequired');
			$("#sampleTestingDateAtLab").addClass('isRequired');
			$("#testingPlatform").addClass('isRequired');
		}
	}


	$('#vlResult').on('change', function() {
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
		flag = deforayValidator.init({
			formId: 'updateVlRequestForm'
		});
		if (flag) {
			$.blockUI();
			document.getElementById('updateVlRequestForm').submit();
		}
	}

	function getVlResults(platformInfo) {
		if (!platformInfo) return;
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