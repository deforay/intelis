<?php

// imported in addVlRequest.php based on country in global config

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


// check if STS
$rKey = '';
if ($general->isSTSInstance()) {
	$sampleCodeKey = 'remote_sample_code_key';
	$sampleCode = 'remote_sample_code';
	$rKey = 'R';
} else {
	$sampleCodeKey = 'sample_code_key';
	$sampleCode = 'sample_code';
	$rKey = '';
}
$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);
$facility = $general->generateSelectOptions($healthFacilities, null, _translate("-- Select --"));

//get ART list
$aQuery = "SELECT * from r_vl_art_regimen WHERE art_status like 'active' ORDER by parent_art ASC, art_code ASC";
$aResult = $db->query($aQuery);

$sKey = '';
$sFormat = '';


?>

<style>
	.translate-content {
		color: #0000FF;
		font-size: 12.5px;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> VIRAL LOAD LABORATORY REQUEST FORM</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Add VL Request</li>
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
				<form class="form-horizontal" method="post" name="addVlRequestForm" id="addVlRequestForm" autocomplete="off" action="addVlRequestHelper.php">
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
												<span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
												<input type="hidden" id="sampleCode" name="sampleCode" />
											</td>
										<?php } else { ?>
											<td><label for="sampleCode">Échantillon ID </label><span class="mandatory">*</span></td>
											<td>
												<input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" readonly placeholder="Échantillon ID" title="Please enter échantillon id" style="width:100%;" onchange="checkSampleNameValidation('form_vl','<?php echo $sampleCode; ?>',this.id,null,'<?= _translate("The Sample ID that you entered already exists. Please try another Sample ID", true); ?>',null)" />
											</td>
										<?php } ?>
										<td><label for="serialNo">
												<?= _translate("Recency ID"); ?>
											</label></td>
										<td><input type="text" class="form-control" id="serialNo" name="serialNo" placeholder="<?= _translate("Recency ID"); ?>" title="<?= _translate("Recency ID"); ?>" style="width:100%;" /></td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td><label for="province"><?= _translate("Province"); ?> </label><span class="mandatory">*</span></td>
										<td>
											<select class="form-control isRequired" name="province" id="province" title="Please choose province" onchange="getfacilityDetails(this);" style="width:100%;">
												<?php echo $province; ?>
											</select>
										</td>
										<td><label for="district">Zone de santé </label><span class="mandatory">*</span>
										</td>
										<td>
											<select class="form-control isRequired" name="district" id="district" title="Please choose district" style="width:100%;" onchange="getfacilityDistrictwise(this);">
												<option value=""><?= _translate("-- Select --"); ?> </option>
											</select>
										</td>
										<td><label for="facilityId">POINT DE COLLECT </label><span class="mandatory">*</span>
										</td>
										<td>
											<select class="form-control isRequired " name="facilityId" id="facilityId" title="<?= _translate("Please choose facility"); ?>" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
												<?php echo $facility; ?>
											</select>
										</td>
									</tr>
									<tr>
										<td><label for="reqClinician">Demandeur <span class="mandatory">*</span></label></td>
										<td>
											<input type="text" class="form-control isRequired" id="reqClinician" name="reqClinician" placeholder="Demandeur" title="<?= _translate("Please enter requesting clinician name"); ?>" style="width:100%;" />
										</td>
										<td><label for="reqClinicianPhoneNumber">Téléphone <span class="mandatory">*</span></label></td>
										<td>
											<input type="text" class="form-control phone-number isRequired" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" placeholder="Téléphone" title="<?= _translate("Please enter phone number"); ?>" style="width:100%;" />
										</td>
										<td><label for="implementingPartner">Partnaire d'appui <span class="mandatory">*</span></label></td>
										<td>
											<!-- <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire d'appui" title="Please enter Partenaire d'appui" style="width:100%;"/> -->
											<select class="form-control select2 isRequired" name="implementingPartner" id="implementingPartner" title="<?= _translate("Please choose implementing partner"); ?>" style="width:100%;">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php
												foreach ($implementingPartnerList as $implementingPartner) {
												?>
													<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>">
														<?= $implementingPartner['i_partner_name']; ?></option>
												<?php } ?>
											</select>
										</td>
									</tr>
									<tr>
										<td><label for="">Date de la demande <span class="mandatory">*</span></label></td>
										<td>
											<input type="text" class="form-control date isRequired" id="dateOfDemand" name="dateOfDemand" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de la demande" style="width:100%;" />
										</td>
										<td><label for="fundingSource">Source de financement<span class="mandatory">*</span> </label></td>
										<td>
											<select class="form-control isRequired" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php
												foreach ($fundingSourceList as $fundingSource) {
												?>
													<option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>">
														<?= $fundingSource['funding_source_name']; ?></option>
												<?php } ?>
											</select>
										</td>
										<td><label for="labId">Nom du laboratoire <span class="mandatory">*</span></label> </td>
										<td>
											<select name="labId" id="labId" class="form-control isRequired" title="Please choose laboratoire" style="width:100%;">
												<?= $general->generateSelectOptions($testingLabs, null, '-- Sélectionner --'); ?>
											</select>
										</td>
									</tr>
								</table>
								<div class="box-header with-border">
									<h3 class="box-title">Information sur le patient </h3>&nbsp;&nbsp;&nbsp;
									<input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" placeholder="Code du patient" title="<?= _translate("Please enter the Patient ID"); ?>" />&nbsp;&nbsp;
									<a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em>
										<?= _translate("Search"); ?>
									</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;
											<?= _translate("No Patient Found"); ?>
										</strong></span>
								</div>
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr class="encryptPIIContainer">
										<th scope="row" style="width:15% !important"><label for="childId"><?= _translate('Encrypt PII'); ?> </label></th>
										<td>
											<select name="encryptPII" id="encryptPII" class="form-control" title="<?= _translate('Encrypt Patient Identifying Information'); ?>">
												<option value=""><?= _translate('--Select--'); ?></option>
												<option value="no" selected='selected'><?= _translate('No'); ?></option>
												<option value="yes"><?= _translate('Yes'); ?></option>
											</select>
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;"><label for="artNo">Code du Patient <span class="mandatory">*</span></label>
										</td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control isRequired patientId" id="artNo" name="artNo" placeholder="Code du patient" title="<?= _translate("Please enter the Patient ID"); ?>" onchange="checkPatientDetails('form_vl','patient_art_no',this,null)" />
											<span class="artNoGroup" id="artNoGroup"></span>
										</td>
										<td style="width: 15% !important;"><label for="">Date de naissance <span class="mandatory">*</span></label></td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control date isRequired" id="dob" name="dob" placeholder="<?= _translate("Please enter the Date of Birth"); ?>" title="<?= _translate("Please enter the Date of Birth"); ?>" onchange="getAge();checkARTInitiationDate();" />
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;"><label for="ageInYears">Âge en années <span class="mandatory">*</span></label></td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control forceNumeric isRequired" id="ageInYears" name="ageInYears" placeholder="Aannées" title="<?= _translate("Please enter Patient age") ?>" onchange="clearDOB(this.value);" />
										</td>
										<td style="width:15% !important;"><label for="ageInMonths">Âge en mois </label>
										</td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control forceNumeric" id="ageInMonths" name="ageInMonths" placeholder="Mois" title="Please enter àge en mois" onchange="clearDOB(this.value);" />
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;"><label for="sex">Sexe<span class="mandatory">*</span>
											</label></td>
										<td style="width: 35% !important;">
											<!--<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">M</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="genderMale" name="gender" value="male" title="<?= _translate("Please select sex"); ?>">
											</label>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">F</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="genderFemale" name="gender" value="female" title="<?= _translate("Please select sex"); ?>">
											</label>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">KP</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="genderKp" name="gender" value="kp" title="<?= _translate("Please select sex"); ?>">
											</label> -->
											<select name="gender" id="gender" class="form-control isRequired" title="Please select sex">
												<option value=""><?= _translate("-- Select --"); ?></option>
												<option value="male"><?= _translate("M"); ?></option>
												<option value="female"><?= _translate("F"); ?></option>
											</select>
										</td>
										<!-- <td style="width: 15% !important;"><label>KP </label></td>
										<td style="width: 35% !important;">
											<select class="form-control" name="keyPopulation" id="keyPopulation" title="<?= _translate('Please choose KP'); ?>">

											</select>
										</td> -->
									</tr>
									<tr class="femaleSection" style="display:none;">
										<td style="width:10% !important;"><strong>Si Femme : </strong></td>
										<td style="width:20% !important;">
											<label for="breastfeeding">Allaitante ?<span class="mandatory" style="display:none;">*</span></label>
											<select class="form-control" id="breastfeeding" name="breastfeeding" title="<?= _translate('Please choose Allaitante'); ?>">
												<option value=""> -- Select -- </option>
												<option id="breastfeedingYes" value="yes">Oui</option>
												<option id="breastfeedingNo" value="no">Non</option>
											</select>
										</td>
										<td style="width:15% !important;">
											<label for="pregnant">Ou enceinte ?<span class="mandatory" style="display:none;">*</span></label>
											<select class="form-control" id="pregnant" name="patientPregnant" title="<?= _translate('Please choose Ou enceinte'); ?>">
												<option value=""> -- Select -- </option>
												<option id="pregYes" value="yes">Oui</option>
												<option id="pregNo" value="no">Non</option>
											</select>
										</td>
										<td class="trimesterSection" style="display:none; width:30% !important;">
											<label for="trimester">Si Femme enceinte :<span class="mandatory" style="display:none;">*</span></label>
											<select class="form-control" id="trimester" name="trimester" title="<?= _translate('Please choose Si Femme enceinte'); ?>">
												<option value=""> -- Select -- </option>
												<option id="trimester1" value="1">Trimestre 1</option>
												<option id="trimester2" value="2">Trimestre 2</option>
												<option id="trimester3" value="3">Trimestre 3</option>
											</select>
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;"><label>Régime ARV en cours <span class="mandatory">*</span></label></td>
										<td style="width: 35% !important;">
											<select class="form-control isRequired" name="artRegimen" id="artRegimen" title="Please choose régime ARV en cours" onchange="checkARTRegimenValue();">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php foreach ($aResult as $arv) { ?>
													<option value="<?php echo $arv['art_code']; ?>"><?php echo $arv['art_code']; ?></option>
												<?php }
												if ($general->isLISInstance() === false) { ?>
													<option value="other">Autre</option>
												<?php } ?>
											</select>
											<input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="Enter Régime ARV" title="Please enter régime ARV" style="margin-top:1vh;display:none;">
										</td>
										<td><label for="patientPhoneNumber">Numéro de portable du patient </label></td>
										<td>
											<input type="text" class="form-control phone-number" id="patientPhoneNumber" name="patientPhoneNumber" placeholder="Téléphone" title="Veuillez entrer le téléphone" style="width:100%;" />
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;"><label for="isPatientNew">Si S/ARV <span class="mandatory">*</span></label></td>
										<td style="width: 35% !important;"><label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Oui</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="isRequired" id="isPatientNewYes" name="isPatientNew" value="yes" title="Please check Si S/ ARV">
											</label>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Non</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="isRequired" id="isPatientNewNo" name="isPatientNew" value="no">
											</label>
										</td>
										<td class="du" style="display:none; width: 15% !important;"><label for="">Date du début des ARV
											</label></td>
										<td class="du" style="display:none; width: 35% !important;">
											<input type="text" class="form-control date" id="dateOfArtInitiation" name="dateOfArtInitiation" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date du début des ARV" onchange="checkARTInitiationDate();checkLastVLTestDate();" /> &nbsp;(Jour/Mois/Année)
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;">
											<label for="hasChangedRegimen">Ce patient a-t-il déjà changé de régime de
												traitement? <span class="mandatory">*</span></label>
										</td>
										<td style="width: 35% !important;"><label class="radio-inline">Oui </label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="isRequired" id="changedRegimenYes" name="hasChangedRegimen" value="yes" title="<?= _translate("Please choose if ARV Regimen changed"); ?>">
											</label>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Non </label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="isRequired" id="changedRegimenNo" name="hasChangedRegimen" value="no" title="<?= _translate("Please choose if ARV Regimen changed"); ?>">
											</label>
										</td>
									</tr>
									<tr style="display:none;" class="arvChangedElement">
										<td style="width: 15% !important;"><label for="reasonForArvRegimenChange" class="arvChangedElement" style="display:none;">Motif de changement de régime ARV </label></td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control arvChangedElement" id="reasonForArvRegimenChange" name="reasonForArvRegimenChange" placeholder="Motif de changement de régime ARV" title="Please enter motif de changement de régime ARV" style="display:none;" />
										</td>
										<td style="width: 15% !important;"><label for="" class="arvChangedElement">Date du changement de régime ARV </label></td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control date arvChangedElement" id="dateOfArvRegimenChange" name="dateOfArvRegimenChange" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date du changement de régime ARV" /> &nbsp;(Jour/Mois/Année)
										</td>
									</tr>
									<tr>
										<td style="width: 15% !important;"><label for="reasonForRequest">Motif de la demande <span class="mandatory">*</span></label></td>
										<td style="width: 35% !important;">
											<select name="reasonForVLTesting" id="reasonForVLTesting" class="form-control isRequired" title="Please choose motif de la demande" onchange="checkreasonForVLTesting();">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php
												foreach ($testReason as $tReason) {
												?>
													<option value="<?php echo $tReason['test_reason_id']; ?>"><?php echo ($tReason['test_reason_name']); ?></option>
												<?php } ?>
												<option value="other">Autre</option>
											</select>
										</td>
										<td style="width: 15% !important;"><label for="viralLoadNo">Charge virale N <span class="mandatory">*</span></label>
										</td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control isRequired" id="viralLoadNo" name="viralLoadNo" placeholder="Charge virale N" title="Please enter charge virale N" />
										</td>
									</tr>
									<tr>
										<td style="width:15% !important;"><label for="">Date dernière charge virale (demande) </label></td>
										<td style="width:35% !important;">
											<input type="text" class="form-control date" id="lastViralLoadTestDate" name="lastViralLoadTestDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date dernière charge virale" onchange="checkLastVLTestDate();" />
										</td>
										<td style="width:15% !important;"><label for="lastViralLoadResult">Résultat dernière charge virale </label></td>
										<td style="width:35% !important;">
											<input type="text" class="form-control" id="lastViralLoadResult" name="lastViralLoadResult" placeholder="Résultat dernière charge virale" title="Please enter résultat dernière charge virale" />copies/mL
										</td>
									</tr>
									<tr style="display:none;" class="newreasonForVLTesting">
										<td style="width: 15% !important;"><label for="newreasonForVLTesting" class="newreasonForVLTesting" style="display:none;">Autre, à préciser <span class="mandatory">*</span></label></td>
										<td style="width: 35% !important;">
											<input type="text" class="form-control newreasonForVLTesting" name="newreasonForVLTesting" id="newreasonForVLTesting" placeholder="Virale Demande Raison" title="Please enter virale demande raison" style="width:100%;display:none;">
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
										<td><label for="">Date du prélèvement <span class="mandatory">*</span></label>
										</td>
										<td>
											<input type="text" class="form-control dateTime isRequired" id="sampleCollectionDate" name="sampleCollectionDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date du prélèvement" onchange="generateSampleCode(); checkCollectionDate(this.value);" style="width:100%;" />
											<span class="expiredCollectionDate" style="color:red; display:none;"></span>
										</td>
										<td></td>
										<td></td>
									</tr>
									<?php if (isset($arr['sample_type']) && trim((string) $arr['sample_type']) == "enabled") { ?>
										<tr>
											<td><label for="specimenType">Type d'échantillon <span class="mandatory">*</span> </label></td>
											<td>
												<select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose type d'échantillon" onchange="checkSpecimenType();" style="width:100%;">
													<option value=""><?= _translate("-- Select --"); ?> </option>
													<?php foreach ($sResult as $type) { ?>
														<option value="<?php echo $type['sample_id']; ?>"><?php echo ($type['sample_name']); ?></option>
													<?php } ?>
												</select>
											</td>
											<td></td>
											<td></td>
										</tr>
									<?php } ?>
									<tr class="plasmaElement" style="display:none;">
										<td><label for="conservationTemperature">Si
												plasma,&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Température de conservation
											</label></td>
										<td>
											<input type="text" class="form-control forceNumeric" id="conservationTemperature" name="conservationTemperature" placeholder="Température de conservation" title="Please enter température de conservation" style="width:100%;" />&nbsp;(°C)
										</td>
										<td style="text-align:center;"><label for="durationOfConservation">Durée de
												conservation </label></td>
										<td>
											<input type="text" class="form-control" id="durationOfConservation" name="durationOfConservation" placeholder="e.g 9/1" title="Please enter durée de conservation" style="width:100%;" />&nbsp;(Jour/Heures)
										</td>
									</tr>
									<tr>
										<td><label for="">Date de départ au Labo biomol <span class="mandatory">*</span></label></td>
										<td>
											<input type="text" class="form-control dateTime isRequired" id="sampleDispatchedDate" name="sampleDispatchedDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de départ au Labo biomol" style="width:100%;" />
										</td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td colspan="4">
											<label class="radio-inline" style="margin:0;padding:0;">A remplir par le préleveur </label>
										</td>
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
										<tr>
											<td style="width: 25%;"><label for="">Date de réception de l'échantillon</label></td>
											<td style="width: 25%;">
												<input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de réception de l'échantillon" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
											<td style="width: 25%;"><label for=""><?php echo _translate('Freezer'); ?> <em class="fas fa-edit"></em> :</label></td>
											<td style="width: 25%;">
												<select class="form-control select2 editableSelect" id="freezer" name="freezer" placeholder="<?php echo _translate('Enter Freezer'); ?>" title="<?php echo _translate('Please enter Freezer'); ?>">
												</select>
											</td>
										</tr>
										<tr>
											<td style="width: 25%;"><label for="rack"><?php echo _translate('Rack'); ?> : </label> </td>
											<td style="width: 25%;">
												<input type="text" class="form-control" id="rack" name="rack" placeholder="<?php echo _translate('Rack'); ?>" title="<?php echo _translate('Please enter rack'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
											<td style="width: 25%;"><label for=""><?php echo _translate('Box'); ?> :</label></td>
											<td style="width: 25%;">
												<input type="text" class="form-control" id="box" name="box" placeholder="<?php echo _translate('Box'); ?>" title="<?php echo _translate('Please enter box'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
										</tr>
										<tr>
											<td style="width: 25%;"><label for="position"><?php echo _translate('Position'); ?> : </label> </td>
											<td style="width: 25%;">
												<input type="text" class="form-control" id="position" name="position" placeholder="<?php echo _translate('Position'); ?>" title="<?php echo _translate('Please enter position'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
											<td style="width: 25%;"><label for=""><?php echo _translate('Volume (ml)'); ?> :</label></td>
											<td style="width: 25%;">
												<input type="text" class="form-control" id="volume" name="volume" placeholder="<?php echo _translate('Volume'); ?>" title="<?php echo _translate('Please enter volume'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
										</tr>
										<tr>
											<td style="width: 25%;"><label for="sampleTestingDateAtLab">Date de réalisation
													de la charge virale </label></td>
											<td style="width: 25%;">
												<input type="text" class="form-control dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de réalisation de la charge virale" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
											<td style="width: 25%;"><label for="testingPlatform">Technique utilisée </label>
											</td>
											<td style="width: 25%;">
												<select name="testingPlatform" id="testingPlatform" class="form-control" title="Please choose VL Testing Platform" <?php echo $labFieldDisabled; ?> style="width:100%;" onchange="getVlResults(this.value)">
													<option value=""><?= _translate("-- Select --"); ?> </option>
													<?php foreach ($importResult as $mName) { ?>
														<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['instrument_id']; ?>">
															<?php echo $mName['machine_name']; ?></option>
													<?php } ?>
												</select>
											</td>
										</tr>
										<tr>
											<td style="width: 25%;"><label for="">Décision prise </label></td>
											<td style="width: 25%;">
												<select class="form-control" id="isSampleRejected" name="isSampleRejected" title="Please select décision prise" <?php echo $labFieldDisabled; ?> onchange="checkTestStatus();" style="width:100%;">
													<option value=""><?= _translate("-- Select --"); ?> </option>
													<option value="no">Echantillon accepté</option>
													<option value="yes">Echantillon rejeté</option>
												</select>
											</td>
										</tr>
										<tr class="rejectionReason" style="display:none;">
											<td class="rejectionReason" style="display:none;"><label for="rejectionReason">Motifs de rejet <span class="mandatory">*</span></label></td>
											<td class="rejectionReason" style="display:none;">
												<select class="form-control" id="rejectionReason" name="rejectionReason" title="Please select motifs de rejet" <?php echo $labFieldDisabled; ?> onchange="checkRejectionReason();" style="width:100%;">
													<option value=""><?= _translate("-- Select --"); ?> </option>
													<?php foreach ($rejectionResult as $rjctReason) { ?>
														<option value="<?php echo $rjctReason['rejection_reason_id']; ?>"><?php echo ($rjctReason['rejection_reason_name']); ?></option>
													<?php }
													if ($general->isLISInstance() === false) { ?>
														<option value="other">Autre</option>
													<?php } ?>
												</select>
											</td>
											<td class="newRejectionReason" style="text-align:center;display:none;"><label for="newRejectionReason" class="newRejectionReason" style="display:none;">Autre, à préciser <span class="mandatory">*</span></label></td>
											<td class="newRejectionReason" style="display:none;"><input type="text" class="form-control newRejectionReason" id="newRejectionReason" name="newRejectionReason" placeholder="Motifs de rejet" title="Please enter motifs de rejet" <?php echo $labFieldDisabled; ?> style="width:100%;display:none;" /></td>
											<th scope="row" class="rejectionReason" style="display:none;">
												<?php echo _translate("Rejection Date"); ?> <span class="mandatory">*</span>
											</th>
											<td class="rejectionReason" style="display:none;"><input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
										</tr>
										<tr class="resultSection">
											<td class="vlResult" style="width: 25%;"><label for="vlResult">Résultat </label>
											</td>
											<td class="resultInputContainer">
												<input list="possibleVlResults" autocomplete="off" class="form-control labSection" id="vlResult" name="vlResult" placeholder="Select or Type VL Result" title="Please enter résultat" <?php echo $labFieldDisabled; ?> onchange="calculateLogValue(this);">
												<datalist id="possibleVlResults">
												</datalist>
											</td>
											<td style="text-align:center;"><label for="vlLog">Log </label></td>
											<td>
												<input type="text" class="vlLog form-control forceNumeric other-failed-results" id="vlLog" name="vlLog" placeholder="Log" title="Please enter log" <?php echo $labFieldDisabled; ?> onchange="calculateLogValue(this)" style="width:100%;" />
											</td>
										</tr>
										<?php if (count($reasonForFailure) > 0) { ?>
											<tr class="reasonForFailure vlResult" style="display: none;">
												<td class="reasonForFailure" style="display: none;"><label for="reasonForFailure">
														<?php echo _translate("Reason for Failure"); ?>
													</label></td>
												<td class="reasonForFailure" style="display: none;">
													<select name="reasonForFailure" id="reasonForFailure" class="form-control" title="Please choose reason for failure" style="width: 100%;">
														<?= $general->generateSelectOptions($reasonForFailure, null, '-- Select --'); ?>
													</select>
												</td>
											</tr>
										<?php } ?>
										<tr>
											<td style="width:14%;"><label for="reviewedOn"> Revu le </label></td>
											<td style="width:14%;">
												<input type="text" name="reviewedOn" id="reviewedOn" class="dateTime authorisation form-control" placeholder="Revu le" title="Please enter the Revu le" />
											</td>
											<td style="width:14%;"><label for="reviewedBy"> Revu par </label></td>
											<td style="width:14%;">
												<select name="reviewedBy" id="reviewedBy" class="select2 authorisation form-control" title="Please choose revu par" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
												</select>
											</td>
										</tr>
										<tr>
											<th scope="row">Approuvé le</th>
											<td>
												<input type="text" name="approvedOnDateTime" id="approvedOnDateTime" class="dateTime authorisation form-control" placeholder="Approuvé le" title="Please enter the Approuvé le" />
											</td>
											<th scope="row">Approuvé par</th>
											<td>
												<select name="approvedBy" id="approvedBy" class="select2 authorisation form-control" title="Please choose Approuvé par" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
												</select>
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
						<?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
							<input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
							<input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
						<?php } ?>
						<a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
						<input type="hidden" name="formId" id="formId" value="3" />
						<input type="hidden" name="vlSampleId" id="vlSampleId" value="" />
						<input type="hidden" name="provinceId" id="provinceId" />
						<input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
						<input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />
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
					testType: 'vl'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#facilityId").html(details[0]);
						$("#district").html(details[1]);
						$("#reqClinician").val(details[2]);
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
					testType: 'vl'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#province").html(details[0]);
						$("#district").html(details[1]);
						$("#reqClinician").val(details[2]);
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
	$("input:radio[name=isPatientNew]").click(function() {
		if ($(this).val() == 'yes') {
			$(".du").show();
			$(".du label").append(' <span class="mandatory">*</span>');
			$("#dateOfArtInitiation").addClass('isRequired');
		} else if ($(this).val() == 'no') {
			$(".du").hide();
			$(".du label .mandatory").remove();
			$("#dateOfArtInitiation").removeClass('isRequired');
			$("#dateOfArtInitiation").val('');
		}
	});
	$("#gender").change(function() {
		if ($(this).val() == 'female') {
			//$('#keyPopulation').html('<option value=""><?= _translate("-- Select --"); ?> </option><option value="ps"><?= _translate("PS"); ?> </option>');
			showFemaleSection();
		} else if ($(this).val() == 'male') {
			//$('#keyPopulation').html('<option value=""><?= _translate("-- Select --"); ?> </option><option value="cps"><?= _translate("CPS"); ?> </option><option value="msm"><?= _translate("MSM"); ?> </option>');
			hideFemaleSection();
		}
	});

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
	$("#pregnant").change(function() {
		if ($(this).val() == 'yes') {
			$(".trimesterSection").show();
			addMandatoryField('trimester');
		} else {
			removeMandatoryField('trimester');
			$(".trimesterSection").hide();
		}
	});
	$("input:radio[name=hasChangedRegimen]").click(function() {
		if ($(this).val() == 'yes') {
			$(".arvChangedElement").show();
			$(".arvChangedElement label").append(' <span class="mandatory">*</span>');
			$(".arvChangedElement input").addClass('isRequired');
		} else if ($(this).val() == 'no') {
			$(".arvChangedElement label .mandatory").remove();
			$(".arvChangedElement input").removeClass('isRequired');
			$(".arvChangedElement input").val('');
			$(".arvChangedElement").hide();
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
			$(".rejectionReason").show();
			$(".resultSection").hide();
			$("#rejectionReason").addClass('isRequired');
			$("#vlResult").val('').css('pointer-events', 'none');
			$("#vlLog").val('').css('pointer-events', 'none');
			$('#reasonForFailure').val('');
			$('#reasonForFailure').removeClass('isRequired');
			$('.reasonForFailure').hide();
		} else {
			$(".resultSection").show();
			$("#rejectionReason").val('');
			$(".rejectionReason").hide();
			$("#rejectionReason").removeClass('isRequired');
			$("#vlResult").css('pointer-events', 'auto');
			$("#vlLog").css('pointer-events', 'auto');

		}
	}

	$('#vlResult').on('change', function() {
		if ($(this).val() != "") {
			$('.authorisation').addClass("isRequired");
		} else {
			$('.authorisation').removeClass("isRequired");
		}
		//if ($(this).val().trim().toLowerCase() == 'failed' || $(this).val().trim().toLowerCase() == 'no result' || $(this).val().trim().toLowerCase() == 'error' || $(this).val().trim().toLowerCase() == 'below detection level') {
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
		$(".du").show();
		if ($(this).val() != "") {
			$("#reasonForVLTesting").removeClass("isRequired");
			$("#artRegimen").removeClass("isRequired");
			$("#dateOfArtInitiation").removeClass("isRequired");
			$("#viralLoadNo").removeClass("isRequired");
		} else {
			$("#reasonForVLTesting").addClass("isRequired");
			$("#artRegimen").addClass("isRequired");
			$("#dateOfArtInitiation").addClass("isRequired");
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
				$("#vlLog").val("");
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

		if ($("#sampleCode").val() == '') {
			generateSampleCode();
		}
		$("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
		flag = deforayValidator.init({
			formId: 'addVlRequestForm'
		});
		if (flag) {
			$('.btn-disabled').attr('disabled', 'yes');
			$(".btn-disabled").prop("onclick", null).off("click");
			$.blockUI();
			<?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
				insertSampleCode('addVlRequestForm', 'vlSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 3, 'sampleCollectionDate');
			<?php } else { ?>
				document.getElementById('addVlRequestForm').submit();
			<?php } ?>
		}
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


	$(document).ready(function() {

		$("#sampleCollectionDate").trigger('change');
		$(".select2").select2();
		$(".select2").select2({
			tags: true
		});
		storageEditableSelect('freezer', 'storage_code', 'storage_id', 'lab_storage', 'Freezer Code');

		$("#freezer").on('change', function() {
			storage = $("#freezer option:selected").text().split('-');
			$("#freezerCode").val($.trim(storage[0]));
		});

		$("#artNo").on('input', function() {

			let artNo = $.trim($(this).val());


			if (artNo.length > 3) {

				$.post("/common/patient-last-request-details.php", {
						testType: 'vl',
						patientId: artNo,
					},
					function(data) {
						if (data != "0") {
							obj = $.parseJSON(data);
							if (obj.no_of_req_time != null && obj.no_of_req_time > 0) {
								$("#artNoGroup").html("<small style='color: red'><?= _translate("No. of times Test Requested for this Patient", true); ?> : " + obj.no_of_req_time + "</small>");
							}
							if (obj.request_created_datetime != null) {
								$("#artNoGroup").append("<br><small style='color:red'><?= _translate("Last Test Request Added On LIS/STS", true); ?> : " + obj.request_created_datetime + "</small>");
							}
							if (obj.sample_collection_date != null) {
								$("#artNoGroup").append("<br><small style='color:red'><?= _translate("Sample Collection Date for Last Request", true); ?> : " + obj.sample_collection_date + "</small>");
							}
							if (obj.no_of_tested_time != null && obj.no_of_tested_time > 0) {
								$("#artNoGroup").append("<br><small style='color:red'><?= _translate("Total No. of times Patient tested for HIV VL", true); ?> : " + obj.no_of_tested_time + "</small >");
							}
						} else {

							$("#artNoGroup").html('');
						}
					});
			}

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

	function getVlResults(platformInfo) {
		if (!platformInfo) {
			$("#vlResult").attr("disabled", true);
			return;
		} else {
			$("#vlResult").attr("disabled", false);

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