<?php
// imported in /eid/results/eid-update-result.php based on country in global config
use App\Registries\ContainerRegistry;
use App\Utilities\DateUtility;
use App\Services\StorageService;

/** @var StorageService $storageService */
$storageService = ContainerRegistry::get(StorageService::class);


// Getting the list of Provinces, Districts and Facilities

$rKey = '';
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
$facility = $general->generateSelectOptions($healthFacilities, $eidInfo['facility_id'], '-- Sélectionner --');

$eidInfo['mother_treatment'] = explode(",", (string) $eidInfo['mother_treatment']);
$eidInfo['child_treatment'] = explode(",", (string) $eidInfo['child_treatment']);
$sampleResult = $general->fetchDataFromTable('r_eid_sample_type', "status = 'active'");
if (isset($eidInfo['result_approved_datetime']) && trim((string) $eidInfo['result_approved_datetime']) !== '' && $eidInfo['result_approved_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $eidInfo['result_approved_datetime']);
	$eidInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$eidInfo['result_approved_datetime'] = '';
}

$formAttributes = json_decode((string) $eidInfo['form_attributes']);

$storageObj = json_decode($formAttributes->storage);
$storageInfo = $storageService->getLabStorage();

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
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate("indicates required fields"); ?> &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->

				<div class="box-body">
					<div class="box box-default">
						<div class="box-body disabledForm">
							<div class="box-header with-border">
								<h3 class="box-title">A. Réservé à la structure de soins</h3>
							</div>
							<div class="box-header with-border">
								<h3 class="box-title">Information sur la structure de soins</h3>
							</div>
							<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
								<tr>
									<?php if ($general->isSTSInstance()) { ?>
										<td><label for="sampleCode">Échantillon ID </label></td>
										<td>
											<span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?= htmlspecialchars((string) $eidInfo['sample_code']); ?></span>

										</td>
									<?php } else { ?>
										<td><label for="sampleCode">Échantillon ID </label><span class="mandatory">*</span></td>
										<td>
											<input type="text" readonly value="<?= htmlspecialchars((string) $eidInfo['sample_code']); ?>" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Échantillon ID" title="Please enter échantillon id" style="width:100%;" />
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
									<td><label for="district">Zone de Santé </label><span class="mandatory">*</span></td>
									<td>
										<select class="form-control isRequired" name="district" id="district" title="Please choose district" style="width:100%;" onchange="getfacilityDistrictwise(this);">
											<option value=""><?= _translate("-- Select --"); ?> </option>
										</select>
									</td>
									<td><label for="facilityId">POINT DE COLLECT </label><span class="mandatory">*</span></td>
									<td>
										<select class="form-control isRequired " name="facilityId" id="facilityId" title="<?= _translate("Please choose facility"); ?>" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
											<?php echo $facility; ?>
										</select>
									</td>
								</tr>
								<tr>
									<td><label for="supportPartner">Partnaire d'appui </label></td>
									<td>
										<!-- <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire d'appui" title="Please enter Partenaire d'appui" style="width:100%;"/> -->
										<select class="form-control" name="implementingPartner" id="implementingPartner" title="<?= _translate("Please choose implementing partner"); ?>" style="width:100%;">
											<option value=""><?= _translate("-- Select --"); ?> </option>
											<?php
											foreach ($implementingPartnerList as $implementingPartner) { ?>
												<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>" <?php echo ($eidInfo['implementing_partner'] == $implementingPartner['i_partner_id']) ? "selected='selected'" : ""; ?>><?= $implementingPartner['i_partner_name']; ?></option>
											<?php } ?>
										</select>
									</td>
									<td><label for="fundingSource">Source de Financement</label></td>
									<td>
										<select class="form-control" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
											<option value=""><?= _translate("-- Select --"); ?> </option>
											<?php
											foreach ($fundingSourceList as $fundingSource) { ?>
												<option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>" <?php echo ($eidInfo['funding_source'] == $fundingSource['funding_source_id']) ? "selected='selected'" : ""; ?>><?= $fundingSource['funding_source_name']; ?></option>
											<?php } ?>
										</select>
									</td>
									<td><label for="clinicianName">Demandeur </label></td>
									<td>
										<input type="text" class="form-control" id="clinicianName" name="clinicianName" placeholder="Demandeur" title="<?= _translate("Please enter requesting clinician name"); ?>" style="width:100%;" value="<?= $eidInfo['clinician_name']; ?>" />
									</td>
								</tr>
								<tr>
									<td><label for="labId">Nom du Laboratoire <span class="mandatory">*</span></label> </td>
									<td>
										<select name="labId" id="labId" class="form-control isRequired" title="Nom du Laboratoire" style="width:100%;">
											<?= $general->generateSelectOptions($testingLabs, $eidInfo['lab_id'], '-- Sélectionner --'); ?>
										</select>
									</td>
								</tr>
							</table>
							<br><br>
							<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
								<tr>
									<th scope="row" colspan="2">
										<h4>1. Données démographiques mère / enfant</h4>
									</th>
								</tr>
								<tr>
									<th scope="row">
										<h5 style="font-weight:bold;font-size:1.1em;">ID de la mère</h5>
									</th>
								</tr>
								<tr>
									<th scope="row" style="width:15%;"><label for="mothersId">Code (si applicable) </label></th>
									<td style="width:35%;">
										<input type="text" class="form-control " id="mothersId" name="mothersId" placeholder="Code du mère" title="Please enter code du mère" style="width:100%;" value="<?= htmlspecialchars((string) $eidInfo['mother_id']); ?>" onchange="" />
									</td>
									<th scope="row" style="width:15%;"><label for="mothersName">Nom </label></th>
									<td style="width:35%;">
										<input type="text" class="form-control " id="mothersName" name="mothersName" placeholder="Nom du mère" title="Please enter nom du mère" style="width:100%;" value="<?php echo $eidInfo['mother_name'] ?>" onchange="" />
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="mothersDob">Date de naissance </label></th>
									<td>
										<input type="text" class="form-control date" id="mothersDob" name="mothersDob" placeholder="Date de naissance" title="Please enter Date de naissance" style="width:100%;" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['mother_dob']); ?>" onchange="" />
									</td>
									<th scope="row"><label for="mothersMaritalStatus">Etat civil </label></th>
									<td>
										<select class="form-control " name="mothersMaritalStatus" id="mothersMaritalStatus">
											<option value=''><?= _translate("-- Select --"); ?></option>
											<option value='single' <?php echo ($eidInfo['mother_marital_status'] == 'single') ? "selected='selected'" : ""; ?>> Single </option>
											<option value='married' <?php echo ($eidInfo['mother_marital_status'] == 'married') ? "selected='selected'" : ""; ?>> Married </option>
											<option value='cohabitating' <?php echo ($eidInfo['mother_marital_status'] == 'cohabitating') ? "selected='selected'" : ""; ?>> Cohabitating </option>
											<option value='widow' <?php echo ($eidInfo['mother_marital_status'] == 'widow') ? "selected='selected'" : ""; ?>> Widow </option>
											<option value='unknown' <?php echo ($eidInfo['mother_marital_status'] == 'unknown') ? "selected='selected'" : ""; ?>> Unknown </option>
										</select>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<h5 style="font-weight:bold;font-size:1.1em;">ID de l'enfant</h5>
									</th>
								</tr>
								<tr>
									<th scope="row"><label for="childId">Code de l’enfant (Patient) </label></th>
									<td>
										<input type="text" class="form-control " id="childId" name="childId" placeholder="Code (Patient)" title="Please enter code du enfant" style="width:100%;" value="<?php echo $eidInfo['child_id']; ?>" onchange="" />
									</td>
									<th scope="row"><label for="childName">Nom </label></th>
									<td>
										<input type="text" class="form-control " id="childName" name="childName" placeholder="Nom" title="Please enter nom du enfant" style="width:100%;" value="<?= htmlspecialchars((string) $eidInfo['child_name']); ?>" onchange="" />
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="childDob">Date de naissance </label></th>
									<td>
										<input type="text" class="form-control date" id="childDob" name="childDob" placeholder="Date de naissance" title="Please enter Date de naissance" style="width:100%;" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['child_dob']) ?>" onchange="" />
									</td>
									<th scope="row"><label for="childGender"><?= _translate("Sex"); ?> </label></th>
									<td>
										<select class="form-control " name="childGender" id="childGender">
											<option value=''><?= _translate("-- Select --"); ?></option>
											<option value='male' <?php echo ($eidInfo['child_gender'] == 'male') ? "selected='selected'" : ""; ?>> <?= _translate("Male"); ?> </option>
											<option value='female' <?php echo ($eidInfo['child_gender'] == 'female') ? "selected='selected'" : ""; ?>> <?= _translate("Female"); ?> </option>
											<option value='unreported' <?php echo ($eidInfo['child_gender'] == 'unreported') ? "selected='selected'" : ""; ?>> <?= _translate("Unreported"); ?> </option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">Age</th>
									<td><input type="number" value="<?= htmlspecialchars((string) $eidInfo['child_age']); ?>" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="childAge" name="childAge" placeholder="<?php echo _translate("Age in years"); ?>" title="<?php echo _translate("Age in years"); ?>" style="width:100%;" onchange="$('#childDob').val('')" /></td>
									<th scope="row">Age en semaines</th>
									<td><input type="number" value="<?= htmlspecialchars((string) $eidInfo['child_age_in_weeks']); ?>" maxlength="5" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="childAgeInWeeks" name="childAgeInWeeks" placeholder="<?php echo _translate("Age in weeks"); ?>" title="<?php echo _translate("Age in weeks"); ?>" style="width:100%;" /></td>
								</tr>
								<tr>
									<th scope="row"><label for="isInfantReceivingTratment">Bébé reçoit-il le traitement? <span class="mandatory">*</span></label></th>
									<td>
										<select class="form-control isRequired" id="isInfantReceivingTratment" name="isInfantReceivingTratment" title="Please select bébé reçoit-il le traitement" style="width:100%;">
											<option value=''> -- Sélectionner -- </option>
											<option value="Oui" <?php echo ($eidInfo['is_infant_receiving_treatment'] == 'Oui') ? "selected='selected'" : ""; ?>>Oui</option>
											<option value="Non" <?php echo ($eidInfo['is_infant_receiving_treatment'] == 'Non') ? "selected='selected'" : ""; ?>>Non</option>
											<option value="Inconnu" <?php echo ($eidInfo['is_infant_receiving_treatment'] == 'Inconnu') ? "selected='selected'" : ""; ?>>Inconnu</option>
										</select>
									</td>
									<th scope="row"><label for="specificInfantTreatment">Si Oui à préciser<span class="mandatory">*</span></label></th>
									<td>
										<select class="form-control isRequired" name="specificInfantTreatment" id="specificInfantTreatment" title="Please select the si oui à préciser" onchange="document.getElementById('specificInfantTreatmentOther').style.display = this.value === 'Autres' ? 'block' : 'none';">
											<option value=''>-- Raison de la PCR (cocher une) --</option>
											<option value='1st test pour bébé exposé (4 à 6 semaines)' <?php echo ($eidInfo['specific_infant_treatment'] == '1st test pour bébé exposé (4 à 6 semaines)') ? "selected='selected'" : ""; ?>> 1st test pour bébé exposé (4 à 6 semaines)</option>
											<option value='1st test pour bébé exposé (plus de 6 semaines)' <?php echo ($eidInfo['specific_infant_treatment'] == '1st test pour bébé exposé (plus de 6 semaines)') ? "selected='selected'" : ""; ?>>1st test pour bébé exposé (plus de 6 semaines)</option>
											<option value='Test à 9 mois' <?php echo ($eidInfo['specific_infant_treatment'] == 'Test à 9 mois') ? "selected='selected'" : ""; ?>>Test à 9 mois</option>
											<option value='Test à plus de 9 mois' <?php echo ($eidInfo['specific_infant_treatment'] == 'Test à plus de 9 mois') ? "selected='selected'" : ""; ?>>Test à plus de 9 mois</option>
											<option value='1st test pour bébé malade' <?php echo ($eidInfo['specific_infant_treatment'] == '1st test pour bébé malade') ? "selected='selected'" : ""; ?>>1st test pour bébé malade</option>
											<option value='Répéter car problème avec 1er test' <?php echo ($eidInfo['specific_infant_treatment'] == 'Répéter car problème avec 1er test') ? "selected='selected'" : ""; ?>>Répéter car problème avec 1er test</option>
											<option value='Répéter pour confirmer 1er résultat' <?php echo ($eidInfo['specific_infant_treatment'] == 'Répéter pour confirmer 1er résultat') ? "selected='selected'" : ""; ?>>Répéter pour confirmer 1er résultat</option>
											<option value='Répéter test après arrêt allaitement' <?php echo ($eidInfo['specific_infant_treatment'] == 'Répéter test après arrêt allaitement') ? "selected='selected'" : ""; ?>>Répéter test après arrêt allaitement</option>
											<option value='maternel (6 semaines au moins après arrêt allaitement)' <?php echo ($eidInfo['specific_infant_treatment'] == 'maternel (6 semaines au moins après arrêt allaitement)') ? "selected='selected'" : ""; ?>>maternel (6 semaines au moins après arrêt allaitement)</option>
											<option value='Autres'>Autres (à préciser)</option>
										</select>
										<input type="text" placeholder="Veuillez préciser si autre" title="Veuillez préciser si autre" id="specificInfantTreatmentOther" name="specificInfantTreatmentOther" class="form-control" style="display: none;" />
									</td>
								</tr>
							</table>
							<br><br>
							<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
								<tr>
									<th scope="row" colspan=6>
										<h4>2. Management de la mère</h4>
									</th>
								</tr>
								<tr>
									<th scope="row" colspan=2>ARV donnés à la maman pendant la grossesse:</th>
									<td colspan=4>
										<input type="checkbox" name="motherTreatment[]" value="Nothing" <?php echo in_array('Nothing', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> Rien &nbsp;&nbsp;&nbsp;&nbsp;
										<input type="checkbox" name="motherTreatment[]" value="ARV Initiated during Pregnancy" <?php echo in_array('ARV Initiated during Pregnancy', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> ARV débutés durant la grossesse &nbsp;&nbsp;&nbsp;&nbsp;
										<input type="checkbox" name="motherTreatment[]" value="ARV Initiated prior to Pregnancy" <?php echo in_array('ARV Initiated prior to Pregnancy', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> ARV débutés avant la grossesse &nbsp;&nbsp;&nbsp;&nbsp;
										<input type="checkbox" name="motherTreatment[]" value="ARV at Child Birth" <?php echo in_array('ARV at Child Birth', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> ARV à l’accouchement &nbsp;&nbsp;&nbsp;&nbsp;
										<input type="checkbox" name="motherTreatment[]" value="Option B plus" <?php echo in_array('Option B plus', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> Option B plus &nbsp;&nbsp;&nbsp;&nbsp;
										<input type="checkbox" name="motherTreatment[]" value="AZT/3TC/NVP" <?php echo in_array('AZT/3TC/NVP', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> AZT/3TC/NVP &nbsp;&nbsp;&nbsp;&nbsp;
										<input type="checkbox" name="motherTreatment[]" value="TDF/3TC/EFV" <?php echo in_array('TDF/3TC/EFV', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> TDF/3TC/EFV &nbsp;&nbsp;&nbsp;&nbsp;
										<input type="checkbox" name="motherTreatment[]" value="Other" <?php echo in_array('Other', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> onclick="$('#motherTreatmentOther').prop('disabled', function(i, v) { return !v; });" /> Autres (à préciser): <input class="form-control" style="max-width:200px;display:inline;" disabled="disabled" placeholder="Autres" type="text" name="motherTreatmentOther" id="motherTreatmentOther" value="<?php echo $eidInfo['mother_treatment_other']; ?>" /> &nbsp;&nbsp;&nbsp;&nbsp;
										<input type="checkbox" name="motherTreatment[]" value="Unknown" <?php echo in_array('Unknown', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> Inconnu
									</td>
								</tr>
								<tr>
									<th scope="row" style="vertical-align:middle;">CD4</th>
									<td style="vertical-align:middle;">
										<div class="input-group">
											<input type="text" class="form-control" id="mothercd4" name="mothercd4" placeholder="CD4" title="CD4" style="width:100%;" onchange="" value="<?php echo $eidInfo['mother_cd4']; ?>" />
											<div class="input-group-addon">/mm3</div>
										</div>
									</td>
									<th scope="row" style="vertical-align:middle;">Viral Load</th>
									<td style="vertical-align:middle;">
										<div class="input-group">
											<input type="number" class="form-control " id="motherViralLoadCopiesPerMl" name="motherViralLoadCopiesPerMl" placeholder="Viral Load in copies/mL" title="Mother's Viral Load" style="width:100%;" value="<?php echo $eidInfo['mother_vl_result']; ?>" onchange="" />
											<div class="input-group-addon">copies/mL</div>
										</div>
									</td>
									<td style="vertical-align:middle;">- OR -</td>
									<td style="vertical-align:middle;">
										<select class="form-control " title="Mother's Viral Load" name="motherViralLoadText" id="motherViralLoadText" onchange="updateMotherViralLoad();">
											<option value=''><?= _translate("-- Select --"); ?></option>
											<option value='tnd' <?php echo ($eidInfo['mother_vl_result'] == 'tnd') ? "selected='selected'" : ""; ?>> Target Not Detected </option>
											<option value='bdl' <?php echo ($eidInfo['mother_vl_result'] == 'bdl') ? "selected='selected'" : ""; ?>> Below Detection Limit </option>
											<option value='< 20' <?php echo ($eidInfo['mother_vl_result'] == '< 20') ? "selected='selected'" : ""; ?>>
												< 20 </option>
											<option value='< 40' <?php echo ($eidInfo['mother_vl_result'] == '< 40') ? "selected='selected'" : ""; ?>>
												< 40 </option>
											<option value='invalid' <?php echo ($eidInfo['mother_vl_result'] == 'invalid') ? "selected='selected'" : ""; ?>> Invalid
											</option>
										</select>
									</td>
								</tr>

							</table>






							<br><br>
							<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
								<tr>
									<th scope="row" colspan=2>
										<h4>3. Mangement de l’enfant</h4>
									</th>
								</tr>
								<tr>
									<th scope="row">Bébé a reçu:<br>(Cocher tout ce qui est reçu, Rien, ou inconnu)</th>
									<td>
										<input type="checkbox" name="childTreatment[]" value="Nothing" <?php echo in_array('Nothing', $eidInfo['child_treatment']) ? "checked='checked'" : ""; ?> />&nbsp;Rien &nbsp; &nbsp;&nbsp;&nbsp;
										<input type="checkbox" name="childTreatment[]" value="AZT" <?php echo in_array('AZT', $eidInfo['child_treatment']) ? "checked='checked'" : ""; ?> />&nbsp;AZT &nbsp; &nbsp;&nbsp;&nbsp;
										<input type="checkbox" name="childTreatment[]" value="NVP" <?php echo in_array('NVP', $eidInfo['child_treatment']) ? "checked='checked'" : ""; ?> />&nbsp;NVP &nbsp; &nbsp;&nbsp;&nbsp;
										<input type="checkbox" name="childTreatment[]" value="Unknown" <?php echo in_array('Unknown', $eidInfo['child_treatment']) ? "checked='checked'" : ""; ?> />&nbsp;Inconnu &nbsp; &nbsp;&nbsp;&nbsp;
									</td>
									<th scope="row">Bébé a arrêté allaitement maternel ?</th>
									<td>
										<select class="form-control" name="hasInfantStoppedBreastfeeding" id="hasInfantStoppedBreastfeeding">
											<option value=''><?= _translate("-- Select --"); ?></option>
											<option value="yes" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
											<option value="no" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'no') ? "selected='selected'" : ""; ?> /> Non </option>
											<option value="unknown" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'unknown') ? "selected='selected'" : ""; ?> /> Inconnu </option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">Age (mois) arrêt allaitement :</th>
									<td>
										<input type="number" class="form-control" style="max-width:200px;display:inline;" placeholder="Age (mois) arrêt allaitement" type="text" name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" value="<?php echo $eidInfo['age_breastfeeding_stopped_in_months'] ?>" />
									</td>
									<th scope="row">Choix d'allaitement de bébé :</th>
									<td>
										<select class="form-control" name="choiceOfFeeding" id="choiceOfFeeding">
											<option value=''><?= _translate("-- Select --"); ?></option>
											<option value="Breastfeeding only" <?php echo ($eidInfo['choice_of_feeding'] == 'Breastfeeding only') ? "selected='selected'" : ""; ?>> Allaitement seul </option>
											<option value="Milk substitute" <?php echo ($eidInfo['choice_of_feeding'] == 'Milk substitute') ? "selected='selected'" : ""; ?>> Substitut de lait </option>
											<option value="Combination" <?php echo ($eidInfo['choice_of_feeding'] == 'Combination') ? "selected='selected'" : ""; ?>> Mixte </option>
											<option value="Other" <?php echo ($eidInfo['choice_of_feeding'] == 'Other') ? "selected='selected'" : ""; ?>> Autre </option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">Cotrimoxazole donné au bébé?</th>
									<td>
										<select class="form-control" name="isCotrimoxazoleBeingAdministered" id="isCotrimoxazoleBeingAdministered">
											<option value=''><?= _translate("-- Select --"); ?></option>
											<option value="no" <?php echo ($eidInfo['is_cotrimoxazole_being_administered_to_the_infant'] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
											<option value="Yes, takes CTX everyday" <?php echo ($eidInfo['is_cotrimoxazole_being_administered_to_the_infant'] == 'Yes, takes CTX everyday') ? "selected='selected'" : ""; ?>> Oui, prend CTX chaque jour </option>
											<option value="Starting on CTX today" <?php echo ($eidInfo['is_cotrimoxazole_being_administered_to_the_infant'] == 'Starting on CTX today') ? "selected='selected'" : ""; ?>> Commence CTX aujourd’hui </option>
										</select>

									</td>
									<th scope="row"></th>
									<td></td>
								</tr>
							</table>






							<br><br>
							<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
								<tr>
									<th scope="row" colspan=2>
										<h4>4. Information sur l’échantillon</h4>
									</th>
								</tr>
								<tr>
									<th scope="row">Date de collecte</th>
									<td>
										<input class="form-control isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Date de collecte" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['sample_collection_date']); ?>" />
									</td>
									<th scope="row">Tel. du préleveur</th>
									<td>
										<input class="form-control" type="text" name="sampleRequestorPhone" id="sampleRequestorPhone" placeholder="Tel. du préleveur" value="<?= htmlspecialchars((string) $eidInfo['sample_requestor_phone']); ?>" />
									</td>
								</tr>
								<tr>
									<th scope="row" style="width:15%;"> Type d'échantillon</th>
									<td style="width:35%;">
										<select name="specimenType" id="specimenType" class="form-control" title="Veuillez choisir le type d'échantillon" style="width:100%">
											<option value="">-- Selecione --</option>
											<?php foreach ($sampleResult as $name) { ?>
												<option value="<?php echo $name['sample_id']; ?>" <?php echo ($eidInfo['specimen_type'] == $name['sample_id']) ? "selected='selected'" : ""; ?>><?= $name['sample_name']; ?></option>
											<?php } ?>
										</select>
									</td>
									<th scope="row" style="width:15%;">Nom du demandeur</th>
									<td style="width:35%;">
										<input class="form-control" type="text" name="sampleRequestorName" id="sampleRequestorName" placeholder="Nom du demandeur" value="<?= htmlspecialchars((string) $eidInfo['sample_requestor_name']); ?>" />
									</td>
								</tr>
								<tr>
									<th scope="row">Raison de la PCR (cocher une):</th>
									<td>
										<select class="form-control" name="pcrTestReason" id="pcrTestReason">
											<option value=''><?= _translate("-- Select --"); ?></option>
											<option value="Nothing" <?php echo ($eidInfo['reason_for_pcr'] == 'Nothing') ? "selected='selected'" : ""; ?>> Rien</option>
											<option value="First Test for exposed baby" <?php echo ($eidInfo['reason_for_pcr'] == 'First Test for exposed baby') ? "selected='selected'" : ""; ?>> 1st test pour bébé exposé</option>
											<option value="First test for sick baby" <?php echo ($eidInfo['reason_for_pcr'] == 'First test for sick baby') ? "selected='selected'" : ""; ?>> 1st test pour bébé malade</option>
											<option value="Repeat due to problem with first test" <?php echo ($eidInfo['reason_for_pcr'] == 'Repeat due to problem with first test') ? "selected='selected'" : ""; ?>> Répéter car problème avec 1er test</option>
											<option value="Repeat to confirm the first result" <?php echo ($eidInfo['reason_for_pcr'] == 'Repeat to confirm the first result') ? "selected='selected'" : ""; ?>> Répéter pour confirmer 1er résultat</option>
											<option value="Repeat test once breastfeeding is stopped" <?php echo ($eidInfo['reason_for_pcr'] == 'Repeat test once breastfeeding is stopped') ? "selected='selected'" : ""; ?>> Répéter test après arrêt allaitement maternel (6 semaines au moins après arrêt allaitement)</option>
										</select>

									</td>
								</tr>
								<tr>
									<th scope="row" colspan=2><strong>Pour enfant de 9 mois ou plus</strong></th>
								</tr>
								<tr>
									<th scope="row">Test rapide effectué? </th>
									<td>
										<select class="form-control" name="rapidTestPerformed" id="rapidTestPerformed">
											<option value=''><?= _translate("-- Select --"); ?></option>
											<option value="yes" <?php echo ($eidInfo['rapid_test_performed'] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
											<option value="no" <?php echo ($eidInfo['rapid_test_performed'] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
										</select>
									</td>
									<th scope="row">Si oui, date :</th>
									<td>
										<input class="form-control" type="text" name="rapidtestDate" id="rapidtestDate" placeholder="Si oui, date" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['rapid_test_date']); ?>" />
									</td>
								</tr>
								<tr>
									<th scope="row">Résultat test rapide</th>
									<td>
										<select class="form-control" name="rapidTestResult" id="rapidTestResult">
											<option value=''><?= _translate("-- Select --"); ?></option>
											<?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
												<option value="<?php echo $eidResultKey; ?>" <?php echo ($eidInfo['rapid_test_result'] == $eidResultKey) ? "selected='selected'" : ""; ?>> <?php echo $eidResultValue; ?> </option>
											<?php } ?>
										</select>
									</td>
								</tr>
							</table>


						</div>
					</div>

					<form class="form-horizontal" method="post" name="editEIDRequestForm" id="editEIDRequestForm" autocomplete="off" action="eid-update-result-helper.php">

						<div class="box box-primary">
							<div class="box-body">
								<div class="box-header with-border">
									<h3 class="box-title">B. Réservé au laboratoire d’analyse </h3>
								</div>
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr>
										<td style="width: 25%;"><label for="testingPlatform">Technique utilisée </label></td>
										<td style="width: 25%;">
											<select name="eidPlatform" id="eidPlatform" class="form-control" title="Please choose EID Testing Platform" <?php echo $labFieldDisabled; ?> style="width:100%;">
												<?= $general->generateSelectOptions($testPlatformList, $eidInfo['eid_test_platform'], '-- Select --'); ?>
											</select>
										</td>
										<th scope="row"><label for="">Date de réception de l'échantillon <span class="mandatory">*</span></label></th>
										<td>
											<input type="text" class="form-control dateTime isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate("Please enter date"); ?>" title="Date de réception de l'échantillon" <?php echo $labFieldDisabled; ?> value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['sample_received_at_lab_datetime']) ?>" onchange="" style="width:100%;" />
										</td>

									<tr>
										<th scope="row"><?= _translate("Is Sample Rejected?"); ?> <span class="mandatory">*</span></th>
										<td>
											<select class="form-control isRequired" name="isSampleRejected" title="Veuillez sélectionner si l'échantillon est rejeté ou non?" id="isSampleRejected" onchange="sampleRejection();">
												<option value=''><?= _translate("-- Select --"); ?></option>
												<option value="yes" <?php echo ($eidInfo['is_sample_rejected'] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
												<option value="no" <?php echo ($eidInfo['is_sample_rejected'] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
											</select>
										</td>
										<th scope="row">Raison du rejet</th>
										<td>
											<select name="sampleRejectionReason" id="sampleRejectionReason" class="form-control labSection" title="Veuillez choisir la raison du rejet" <?php echo $labFieldDisabled; ?> <option value=""><?= _translate("-- Select --"); ?> </option>
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php foreach ($rejectionTypeResult as $type) { ?>
													<optgroup label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
														<?php
														foreach ($rejectionResult as $reject) {
															if ($type['rejection_type'] == $reject['rejection_type']) { ?>
																<option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($eidInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?= $reject['rejection_reason_name']; ?></option>
														<?php }
														} ?>
													</optgroup>
												<?php }  ?>
											</select>
										</td>
									</tr>
									<tr class="rejected" style="display:none;">
										<th scope="row">Date de rejet<span class="mandatory">*</span></th>
										<td><input value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['rejection_on']); ?>" class="form-control date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Date de rejet" title="Veuillez choisir la date rejetée" /></td>
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
										<td style="width: 25%;"><label for=""><?php echo _translate('Volume (ml)'); ?> :
											</label></td>
										<td style="width: 25%;">
											<input type="text" class="form-control" id="volume" name="volume" value="<?= $storageObj->volume; ?>" placeholder="<?php echo _translate('Volume'); ?>" title="<?php echo _translate('Please enter volume'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
										</td>
									</tr>
									<tr>
										<td style="width:25%;"><label for="">Test effectué le </label></td>
										<td style="width:25%;">
											<input type="text" class="form-control dateTime isRequired" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _translate("Please enter date"); ?>" title="Test effectué le" <?php echo $labFieldDisabled; ?> onchange="" value="<?php echo DateUtility::humanReadableDateFormat($eidInfo['sample_tested_datetime']) ?>" style="width:100%;" />
										</td>
										<th scope="row">Résultat </label></th>
										<td>
											<select class="result-focus form-control isRequired" name="result" id="result" title="Résultat">
												<option value=''><?= _translate("-- Select --"); ?></option>
												<option value="positive" <?php echo ($eidInfo['result'] == 'positive') ? "selected='selected'" : ""; ?>> Positif </option>
												<option value="negative" <?php echo ($eidInfo['result'] == 'negative') ? "selected='selected'" : ""; ?>> Négatif </option>
												<option value="indeterminate" <?php echo ($eidInfo['result'] == 'indeterminate') ? "selected='selected'" : ""; ?>> Indéterminé </option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">Revu par</th>
										<td>
											<select name="reviewedBy" id="reviewedBy" class="select2 form-control isRequired" title="Please choose Revu par" style="width: 100%;">
												<?= $general->generateSelectOptions($userInfo, $eidInfo['result_reviewed_by'], '-- Select --'); ?>
											</select>
										</td>
										<th scope="row">Date de Revu</th>
										<td><input type="text" value="<?= DateUtility::humanReadableDateFormat($eidInfo['result_reviewed_datetime']); ?>" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control isRequired" placeholder="Date de revu" title="Date de revu" /></td>
									</tr>
									<tr>
										<th scope="row">Approuvé le</th>
										<td>
											<input type="text" name="approvedOnDateTime" id="approvedOnDateTime" value="<?php echo $eidInfo['result_approved_datetime']; ?>" class="dateTime form-control" placeholder="Approuvé le" title="Please enter the Approuvé le" />
										</td>
										<th scope="row">Approuvé par</th>
										<td>
											<select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose Approuvé par" style="width: 100%;">
												<?= $general->generateSelectOptions($userInfo, $eidInfo['result_approved_by'], '-- Select --'); ?>
											</select>
										</td>
									</tr>
									<tr class="change-reason">
										<th scope="row" class="change-reason" style="display: none;">Raison du changement <span class="mandatory">*</span></th>
										<td class="change-reason" style="display: none;"><textarea name="reasonForChanging" id="reasonForChanging" class="form-control" placeholder="Entrez la raison du changement" title="Veuillez saisir la raison du changement"></textarea></td>
										<th scope="row"></th>
										<td></td>
									</tr>
								</table>
							</div>
						</div>


				</div>
				<!-- /.box-body -->
				<div class="box-footer">
					<input type="hidden" name="formId" id="formId" value="3" />
					<input type="hidden" name="eidSampleId" id="eidSampleId" value="<?php echo ($eidInfo['eid_id']); ?>" />
					<input type="hidden" id="sampleCode" name="sampleCode" value="<?= htmlspecialchars((string) $eidInfo['sample_code']); ?>" />
					<input type="hidden" id="childId" name="childId" value="<?php echo $eidInfo['child_id']; ?>" />
					<input type="hidden" name="revised" id="revised" value="no" />
					<input type="hidden" name="labId" id="labId" value="<?= ($eidInfo['lab_id']); ?>" />

					<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
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
			if (provinceName) {
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
			}
		} else if (pName == '' && cName == '') {
			provinceName = true;
			facilityName = true;
			$("#province").html("<?php echo $province; ?>");
			$("#facilityId").html("<?php echo $facility; ?>");
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

		storageEditableSelect('freezer', 'storage_code', 'storage_id', 'lab_storage', 'Freezer Code');


		$('.disabledForm input, .disabledForm select ').attr('disabled', true);

		$('#facilityId').select2({
			placeholder: "Select Clinic/Health Center"
		});
		$('#district').select2({
			placeholder: "District"
		});
		$('#province').select2({
			placeholder: "Province"
		});
		$('#reviewedBy').select2({
			width: '100%',
			placeholder: "Select Reviewed By"
		});
		$('#approvedBy').select2({
			width: '100%',
			placeholder: "Select Approved By"
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
		sampleRejection();
	});

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