<?php

//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);
//check remote user
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {

	if (!empty($vlQueryInfo['remote_sample']) && $vlQueryInfo['remote_sample'] == 'yes') {
		$sampleCode = 'remote_sample_code';
	} else {
		$sampleCode = 'sample_code'; 
	}
	//check user exist in user_facility_map table
	$chkUserFcMapQry = "SELECT user_id FROM user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
	$chkUserFcMapResult = $db->query($chkUserFcMapQry);
	if ($chkUserFcMapResult) {
		//$pdQuery="SELECT * from province_details as pd JOIN facility_details as fd ON fd.facility_state=pd.province_name JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where user_id='".$_SESSION['userId']."'";
        $pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
	}
} else {
	$sampleCode = 'sample_code';
}
$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Sélectionner -- </option>";
foreach ($pdResult as $provinceName) {
	$province .= "<option value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, $vlQueryInfo['facility_id'], '-- Sélectionner --');

//Get selected state
$stateQuery = "SELECT * FROM facility_details WHERE facility_id='" . $vlQueryInfo['facility_id'] . "'";
$stateResult = $db->query($stateQuery);
if (!isset($stateResult[0]['facility_state']) || $stateResult[0]['facility_state'] == '') {
	$stateResult[0]['facility_state'] = "";
}
//district details
$districtQuery = "SELECT DISTINCT facility_district FROM facility_details WHERE facility_state='" . $stateResult[0]['facility_state'] . "'";
$districtResult = $db->query($districtQuery);
$provinceQuery = "SELECT * FROM geographical_divisions WHERE geo_id='" . $stateResult[0]['facility_state_id'] . "'";
$provinceResult = $db->query($provinceQuery);
if (!isset($provinceResult[0]['geo_code']) || $provinceResult[0]['geo_code'] == '') {
	$provinceResult[0]['geo_code'] = "";
}
//get ART list
$aQuery = "SELECT * FROM r_vl_art_regimen";
$aResult = $db->query($aQuery);


//suggest sample id when lab user add request sample
$sampleSuggestion = '';
$sampleSuggestionDisplay = 'display:none;';


?>

<style>
	.translate-content {
		color: #0000FF;
		font-size: 12.5px;
	}

	.du {
		<?php
		if (trim($vlQueryInfo['is_patient_new']) == "" || trim($vlQueryInfo['is_patient_new']) == "no") { ?>visibility: hidden;
		<?php } ?>
	}

	#femaleElements {
		<?php
		if (trim($vlQueryInfo['patient_gender']) == "" || trim($vlQueryInfo['patient_gender']) == "male") { ?>display: none;
		<?php } ?>
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> VIRAL LOAD LABORATORY REQUEST FORM</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Edit Vl Request</li>
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

								<div class="" style="<?php echo $sampleSuggestionDisplay; ?>">
									<?php
									if ($vlQueryInfo['sample_code'] != '') {
									?>
										<label for="sampleSuggest" class="text-danger">Cet exemple a déjà été importé avec l'ID échantillon VLSM <?php echo $vlQueryInfo['sample_code']; ?></label>
									<?php
									} else {
									?>
										<label for="sampleSuggest">&nbsp;&nbsp;&nbsp;Suggérer un ID d'échantillon (peut changer en soumettant le formulaire) - </label>
										<?php echo $sampleSuggestion; ?>
									<?php } ?>
								</div>


								<table class="table" aria-hidden="true" style="width:100%">


									<tr>
										<?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
											<td><label for="sampleCode">Échantillon ID </label></td>
											<td>
												<span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?php echo $vlQueryInfo[$sampleCode]; ?></span>
												<input type="hidden" id="sampleCode" name="sampleCode" value="<?php echo $vlQueryInfo[$sampleCode]; ?>" />
											</td>
										<?php } else { ?>
											<td><label for="sampleCode">Échantillon ID </label><span class="mandatory">*</span></td>
											<td>
												<input type="text" class="form-control isRequired" readonly id="sampleCode" name="sampleCode" placeholder="Échantillon ID" title="Please enter échantillon id" value="<?php echo $vlQueryInfo[$sampleCode]; ?>" style="width:100%;" onchange="checkSampleNameValidation('form_vl','<?php echo $sampleCode; ?>',this.id,'<?php echo "vl_sample_id##" . $vlQueryInfo["vl_sample_id"]; ?>','The échantillon id that you entered already exists. Please try another échantillon id',null)" />
											</td>
										<?php } ?>

										<td><label for="serialNo">Recency ID</label></td>
										<td><input type="text" class="form-control" id="serialNo" name="serialNo" placeholder="Recency ID" title="Please enter recency id" style="width:100%;" value="<?php echo $vlQueryInfo['external_sample_code']; ?>" /></td>

										<td style=" display:<?php echo ($sCode == '') ? 'none' : ''; ?>"><label for="">Date de réception de léchantillon <span class="mandatory">*</span></label></td>
										<td style=" display:<?php echo ($sCode == '') ? 'none' : ''; ?>">
											<input type="text" class="form-control dateTime isRequired" id="sampleReceivedDate<?php echo ($sCode == '') ? 'Lab' : ''; ?>" name="sampleReceivedDate<?php echo ($sCode == '') ? 'Lab' : ''; ?>" placeholder="<?= _("Please enter date"); ?>" title="Please enter date de réception de léchantillon" <?php echo $labFieldDisabled; ?> onchange="checkSampleReceviedDate();" value="<?php echo ($vlQueryInfo['sample_received_at_vl_lab_datetime'] != '' && $vlQueryInfo['sample_received_at_vl_lab_datetime'] != null) ? $vlQueryInfo['sample_received_at_vl_lab_datetime'] : date('d-M-Y H:i:s'); ?>" style="width:100%;" />
										</td>


									</tr>
									<tr>
										<td><label for="province">Province </label><span class="mandatory">*</span></td>
										<td>
											<select class="form-control isRequired" name="province" id="province" title="Please choose province" onchange="getfacilityDetails(this);" style="width:100%;">
												<option value=""> -- Sélectionner -- </option>
												<?php foreach ($pdResult as $provinceName) { ?>
													<option value="<?php echo $provinceName['geo_name'] . "##" . $provinceName['geo_code']; ?>" <?php echo (strtolower($stateResult[0]['facility_state']) . "##" . strtolower($provinceResult[0]['geo_code']) == strtolower($provinceName['geo_name']) . "##" . strtolower($provinceName['geo_code'])) ? "selected='selected'" : "" ?>><?php echo ($provinceName['geo_name']); ?></option>
												<?php } ?>
											</select>
										</td>
										<td><label for="district">Zone de santé </label><span class="mandatory">*</span></td>
										<td>
											<select class="form-control isRequired" name="district" id="district" title="Veuillez choisir le quartier" style="width:100%;" onchange="getfacilityDistrictwise(this);">
												<option value=""> -- Sélectionner -- </option>
												<?php foreach ($districtResult as $districtName) { ?>
													<option value="<?php echo $districtName['facility_district']; ?>" <?php echo ($stateResult[0]['facility_district'] == $districtName['facility_district']) ? "selected='selected'" : "" ?>><?php echo ($districtName['facility_district']); ?></option>
												<?php } ?>
											</select>
										</td>
										<td><label for="fName">POINT DE COLLECT </label><span class="mandatory">*</span></td>
										<td>
											<select class="form-control isRequired" name="fName" id="fName" title="Veuillez choisir le POINT DE COLLECT" onchange="getfacilityProvinceDetails(this);" style="width:100%;">
												<?= $facility; ?>
											</select>
										</td>
									</tr>
									<tr>
										<td><label for="reqClinician">Demandeur </label></td>
										<td>
											<input type="text" class="form-control" id="reqClinician" name="reqClinician" placeholder="Demandeur" title="Veuillez saisir le demandeur" value="<?php echo $vlQueryInfo['request_clinician_name']; ?>" style="width:100%;" />
										</td>
										<td><label for="reqClinicianPhoneNumber">Téléphone </label></td>
										<td>
											<input type="text" class="form-control forceNumeric" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" placeholder="Téléphone" title="Veuillez entrer le téléphone" value="<?php echo $vlQueryInfo['request_clinician_phone_number']; ?>" style="width:100%;" />
										</td>
										<td><label for="supportPartner">Partenaire dappui </label></td>
										<td>
											<!-- <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire dappui" title="Please enter partenaire dappui" value="< ?php echo $vlQueryInfo['facility_support_partner']; ?>" style="width:100%;"/> -->
											<select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose partenaire de mise en œuvre" style="width:100%;">
												<option value=""> -- Sélectionner -- </option>
												<?php
												foreach ($implementingPartnerList as $implementingPartner) {
												?>
													<option value="<?php echo base64_encode($implementingPartner['i_partner_id']); ?>" <?php echo ($implementingPartner['i_partner_id'] == $vlQueryInfo['implementing_partner']) ? 'selected="selected"' : ''; ?>><?php echo ($implementingPartner['i_partner_name']); ?></option>
												<?php } ?>
											</select>
										</td>
									</tr>
									<tr>
										<td><label for="">Date de la demande </label></td>
										<td>
											<input type="text" class="form-control date" id="dateOfDemand" name="dateOfDemand" placeholder="<?= _("Please enter date"); ?>" title="Please enter date de la demande" value="<?php echo $vlQueryInfo['date_test_ordered_by_physician']; ?>" style="width:100%;" />
										</td>
										<td><label for="fundingSource">Source de financement </label></td>
										<td>
											<select class="form-control" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
												<option value=""> -- Sélectionner -- </option>
												<?php
												foreach ($fundingSourceList as $fundingSource) {
												?>
													<option value="<?php echo base64_encode($fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $vlQueryInfo['funding_source']) ? 'selected="selected"' : ''; ?>><?php echo ($fundingSource['funding_source_name']); ?></option>
												<?php } ?>
											</select>
										</td>
										<?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
											<td><label for="labId">Nom du laboratoire <span class="mandatory">*</span></label> </td>
											<td>
												<select name="labId" id="labId" class="form-control isRequired" title="Please choose laboratoire" style="width:100%;">
													<?= $general->generateSelectOptions($testingLabs, $vlQueryInfo['lab_id'], '-- Sélectionner --'); ?>
												</select>
											</td>
										<?php } ?>
										<!-- <td><label for="implementingPartner">Partenaire de mise en œuvre </label></td>
                                <td>
                                    <select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose partenaire de mise en œuvre" style="width:100%;">
                                      <option value=""> -- Sélectionner -- </option>
                                      < ?php
                                      foreach($implementingPartnerList as $implementingPartner){
                                      ?>
                                        <option value="< ?php echo base64_encode($implementingPartner['i_partner_id']); ?>" < ?php echo ($implementingPartner['i_partner_id'] == $vlQueryInfo['implementing_partner'])?'selected="selected"':''; ?>>< ?php echo ($implementingPartner['i_partner_name']); ?></option>
                                      < ?php } ?>
                                    </select>
                                </td> -->
									</tr>

								</table>
								<div class="box-header with-border">
									<h3 class="box-title">Information sur le patient </h3>&nbsp;&nbsp;&nbsp;
									<input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" placeholder="Code du patient" title="Please enter code du patient" />&nbsp;&nbsp;
									<a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No Patient Found</strong></span>
								</div>
								<table class="table" aria-hidden="true" style="width:100%">
									<tr>
										<td style="width:10%;"><label for="">Date de naissance </label></td>
										<td style="width:15%;">
											<input type="text" class="form-control date" id="dob" name="dob" placeholder="<?= _("Please enter date"); ?>" title="Please select date de naissance" onchange="getAge();checkARTInitiationDate();" value="<?php echo $vlQueryInfo['patient_dob']; ?>" style="width:100%;" />
										</td>
										<td style="width:6%;"><label for="ageInYears">Âge en années </label></td>
										<td style="width:19%;">
											<input type="text" class="form-control forceNumeric" id="ageInYears" name="ageInYears" placeholder="Aannées" title="Please enter àge en années" value="<?php echo $vlQueryInfo['patient_age_in_years']; ?>" onchange="clearDOB(this.value);" style="width:100%;" />
										</td>
										<td style="width:10%;"><label for="ageInMonths">Âge en mois </label></td>
										<td style="width:15%;">
											<input type="text" class="form-control forceNumeric" id="ageInMonths" name="ageInMonths" placeholder="Mois" title="Please enter àge en mois" value="<?php echo $vlQueryInfo['patient_age_in_months']; ?>" onchange="clearDOB(this.value);" style="width:100%;" />
										</td>
										<td style="width:10%;text-align:center;"><label for="sex">Sexe </label></td>
										<td style="width:15%;">
											<label class="radio-inline" style="padding-left:12px !important;margin-left:0;">M</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="genderMale" name="gender" value="male" title="Please check sexe" <?php echo (trim($vlQueryInfo['patient_gender']) == "male") ? 'checked="checked"' : ''; ?>>
											</label>
											<label class="radio-inline" style="padding-left:12px !important;margin-left:0;">F</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="genderFemale" name="gender" value="female" title="Please check sexe" <?php echo (trim($vlQueryInfo['patient_gender']) == "female") ? 'checked="checked"' : ''; ?>>
											</label>
										</td>
									</tr>
									<tr>
										<td><label for="artNo">Code du patient <span class="mandatory">*</span></label></td>
										<td>
											<input type="text" class="form-control isRequired" id="artNo" name="artNo" placeholder="Code du patient" title="Please enter code du patient" value="<?php echo $vlQueryInfo['patient_art_no']; ?>" style="width:100%;" />
										</td>
										<td colspan="2"><label for="isPatientNew">Si S/ARV </label>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Oui</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="isPatientNewYes" name="isPatientNew" <?php echo ($vlQueryInfo['is_patient_new'] == 'yes') ? 'checked="checked"' : ''; ?> value="yes" title="Please check Si S/ ARV">
											</label>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Non</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="isPatientNewNo" name="isPatientNew" <?php echo ($vlQueryInfo['is_patient_new'] == 'no') ? 'checked="checked"' : ''; ?> value="no">
											</label>
										</td>
										<td class="du"><label for="">Date du début des ARV </label></td>
										<td class="du">
											<input type="text" class="form-control date" id="dateOfArtInitiation" name="dateOfArtInitiation" placeholder="<?= _("Please enter date"); ?>" title="Please enter date du début des ARV" value="<?php echo $vlQueryInfo['treatment_initiated_date']; ?>" onchange="checkARTInitiationDate();checkLastVLTestDate();" style="width:100%;" />&nbsp;(Jour/Mois/Année)
										</td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td><label>Régime ARV en cours </label></td>
										<td>
											<select class="form-control" name="artRegimen" id="artRegimen" title="Please choose régime ARV en cours" onchange="checkARTRegimenValue();" style="width:100%;">
												<option value=""> -- Sélectionner -- </option>
												<?php foreach ($aResult as $arv) { ?>
													<option value="<?php echo $arv['art_code']; ?>" <?php echo ($arv['art_code'] == $vlQueryInfo['current_regimen']) ? 'selected="selected"' : ''; ?>><?php echo $arv['art_code']; ?></option>
												<?php }
												if ($sarr['sc_user_type'] != 'vluser') {  ?>
													<option value="other">Autre</option>
												<?php } ?>
											</select>
											<input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="Enter Régime ARV" title="Please enter régime ARV" style="width:100%;margin-top:1vh;display:none;">
										</td>
										<td></td>
										<td></td>
										<td></td>
										<td></td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td colspan="4">
											<label for="hasChangedRegimen">Ce patient a-t-il déjà changé de régime de traitement? </label>
											<br><label class="radio-inline">&nbsp;&nbsp;&nbsp;&nbsp;Oui </label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="changedRegimenYes" name="hasChangedRegimen" value="yes" title="Please check any of one option" <?php echo (trim($vlQueryInfo['has_patient_changed_regimen']) == "yes") ? 'checked="checked"' : ''; ?>>
											</label>
											<label class="radio-inline">Non </label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="changedRegimenNo" name="hasChangedRegimen" value="no" title="Please check any of one option" <?php echo (trim($vlQueryInfo['has_patient_changed_regimen']) == "no") ? 'checked="checked"' : ''; ?>>
											</label>
										</td>
										<td colspan="2"><label for="reasonForArvRegimenChange" class="arvChangedElement" style="display:<?php echo (trim($vlQueryInfo['has_patient_changed_regimen']) == "yes") ? '' : 'none'; ?>;">Motif de changement de régime ARV </label></td>
										<td colspan="2">
											<input type="text" class="form-control arvChangedElement" id="reasonForArvRegimenChange" name="reasonForArvRegimenChange" placeholder="Motif de changement de régime ARV" title="Please enter motif de changement de régime ARV" value="<?php echo $vlQueryInfo['reason_for_regimen_change']; ?>" style="width:100%;display:<?php echo (trim($vlQueryInfo['has_patient_changed_regimen']) == "yes") ? '' : 'none'; ?>;" />
										</td>
									</tr>
									<tr class="arvChangedElement" style="display:<?php echo (trim($vlQueryInfo['has_patient_changed_regimen']) == "yes") ? '' : 'none'; ?>;">
										<td><label for="">Date du changement de régime ARV </label></td>
										<td colspan="3">
											<input type="text" class="form-control date" id="dateOfArvRegimenChange" name="dateOfArvRegimenChange" placeholder="<?= _("Please enter date"); ?>" title="Please enter date du changement de régime ARV" value="<?php echo $vlQueryInfo['regimen_change_date']; ?>" style="width:100%;" />&nbsp;(Jour/Mois/Année)
										</td>
										<td></td>
										<td></td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td><label for="reasonForRequest">Motif de la demande <span class="mandatory">*</span></label></td>
										<td colspan="3">
											<select name="reasonForVLTesting" id="reasonForVLTesting" class="form-control isRequired" title="Please choose motif de la demande" onchange="checkreasonForVLTesting();">
												<option value=""> -- Sélectionner -- </option>
												<?php foreach ($vlTestReasonResult as $tReason) { ?>
													<option value="<?php echo $tReason['test_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_vl_testing'] == $tReason['test_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ($tReason['test_reason_name']); ?></option>
												<?php }
												if ($sarr['sc_user_type'] != 'vluser') {  ?>
													<option value="other">Autre</option>
												<?php } ?>
											</select>
										</td>
										<td style="text-align:center;"><label for="viralLoadNo">Charge virale N </label></td>
										<td colspan="3">
											<input type="text" class="form-control" id="viralLoadNo" name="viralLoadNo" placeholder="Charge virale N" title="Please enter charge virale N" value="<?php echo $vlQueryInfo['vl_test_number']; ?>" style="width:100%;" />
										</td>
									</tr>
									<tr class="newreasonForVLTesting" style="display:none;">
										<td><label for="newreasonForVLTesting">Autre, à préciser <span class="mandatory">*</span></label></td>
										<td colspan="3">
											<input type="text" class="form-control" name="newreasonForVLTesting" id="newreasonForVLTesting" placeholder="Virale Demande Raison" title="Please enter virale demande raison" style="width:100%;">
										</td>
										<td></td>
										<td></td>
										<td></td>
										<td></td>
									</tr>
									<tr id="femaleElements">
										<td><strong>Si Femme : </strong></td>
										<td colspan="2">
											<label for="breastfeeding">allaitante?</label>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Oui</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="breastfeedingYes" name="breastfeeding" <?php echo (trim($vlQueryInfo['is_patient_breastfeeding']) == "yes") ? 'checked="checked"' : ''; ?> value="yes" title="Please check Si allaitante">
											</label>
											<label class="radio-inline" style="padding-left:0px !important;margin-left:0;">Non</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="breastfeedingNo" name="breastfeeding" <?php echo (trim($vlQueryInfo['is_patient_breastfeeding']) == "no") ? 'checked="checked"' : ''; ?> value="no">
											</label>
										</td>
										<td colspan="5"><label for="patientPregnant">Ou enceinte ? </label>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Oui</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="pregYes" name="patientPregnant" <?php echo (trim($vlQueryInfo['is_patient_pregnant']) == "yes") ? 'checked="checked"' : ''; ?> value="yes" title="Please check Si Ou enceinte ">
											</label>
											<label class="radio-inline" style="padding-left:0px !important;margin-left:0;">Non</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" class="" id="pregNo" name="patientPregnant" <?php echo (trim($vlQueryInfo['is_patient_pregnant']) == "no") ? 'checked="checked"' : ''; ?> value="no">
											</label>&nbsp;&nbsp;&nbsp;&nbsp;
											<label for="trimester">Si Femme enceinte </label>
											<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Trimestre 1</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" id="trimester1" name="trimester" <?php echo (trim($vlQueryInfo['pregnancy_trimester']) == "1") ? 'checked="checked"' : ''; ?> value="1" title="Please check trimestre">
											</label>
											<label class="radio-inline" style="padding-left:0px !important;margin-left:0;">Trimestre 2</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" id="trimester2" name="trimester" <?php echo (trim($vlQueryInfo['pregnancy_trimester']) == "2") ? 'checked="checked"' : ''; ?> value="2">
											</label>
											<label class="radio-inline" style="padding-left:0px !important;margin-left:0;">Trimestre 3</label>
											<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
												<input type="radio" id="trimester3" name="trimester" <?php echo (trim($vlQueryInfo['pregnancy_trimester']) == "3") ? 'checked="checked"' : ''; ?> value="3">
											</label>
										</td>
									</tr>
									<tr>
										<td><label for="lastViralLoadResult">Résultat dernière charge virale </label></td>
										<td colspan="3">
											<input type="text" class="form-control" id="lastViralLoadResult" name="lastViralLoadResult" placeholder="Résultat dernière charge virale" title="Please enter résultat dernière charge virale" value="<?php echo $vlQueryInfo['last_viral_load_result']; ?>" style="width:100%;" />
										</td>
										<td>copies/ml</td>
										<td></td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td><label for="">Date dernière charge virale (demande) </label></td>
										<td colspan="3">
											<input type="text" class="form-control date" id="lastViralLoadTestDate" name="lastViralLoadTestDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter date dernière charge virale" value="<?php echo $vlQueryInfo['last_viral_load_date']; ?>" onchange="checkLastVLTestDate();" style="width:100%;" />
										</td>
										<td></td>
										<td></td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td colspan="8"><label class="radio-inline" style="margin:0;padding:0;">A remplir par le service demandeur dans la structure de soins</label></td>
									</tr>
								</table>
								<div class="box-header with-border">
									<h3 class="box-title">Informations sur le prélèvement <small>(A remplir par le préleveur)</small> </h3>
								</div>
								<table class="table" aria-hidden="true" style="width:100%">
									<tr>
										<td style="width:25%;"><label for="">Date du prélèvement <span class="mandatory">*</span></label></td>
										<td style="width:25%;">
											<input type="text" class="form-control dateTime isRequired" id="sampleCollectionDate" name="sampleCollectionDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter date du prélèvement" value="<?php echo $vlQueryInfo['sample_collection_date']; ?>" onchange="checkSampleReceviedDate();checkSampleTestingDate();" style="width:100%;" />
										</td>
										<td style="width:25%;"></td>
										<td style="width:25%;"></td>
									</tr>
									<?php if (isset($arr['sample_type']) && trim($arr['sample_type']) == "enabled") { ?>
										<tr>
											<td><label for="specimenType">Type d'échantillon <span class="mandatory">*</span></label></td>
											<td>
												<select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose type d'échantillon" onchange="checkSpecimenType();" style="width:100%;">
													<option value=""> -- Sélectionner -- </option>
													<?php foreach ($sResult as $type) { ?>
														<option value="<?php echo $type['sample_id']; ?>" <?php echo ($vlQueryInfo['sample_type'] == $type['sample_id']) ? 'selected="selected"' : ''; ?>><?php echo ($type['sample_name']); ?></option>
													<?php } ?>
												</select>
											</td>
											<td></td>
											<td></td>
										</tr>
									<?php } ?>
									<tr class="plasmaElement" style="display:<?php echo ($vlQueryInfo['sample_type'] == 2) ? '' : 'none'; ?>;">
										<td><label for="conservationTemperature">Si plasma,&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Température de conservation </label></td>
										<td>
											<input type="text" class="form-control forceNumeric" id="conservationTemperature" name="conservationTemperature" placeholder="Température de conservation" title="Please enter température de conservation" value="<?php echo $vlQueryInfo['plasma_conservation_temperature']; ?>" style="width:100%;" />&nbsp;(°C)
										</td>
										<td style="text-align:center;"><label for="durationOfConservation">Durée de conservation </label></td>
										<td>
											<input type="text" class="form-control" id="durationOfConservation" name="durationOfConservation" placeholder="e.g 9/1" title="Please enter durée de conservation" value="<?php echo $vlQueryInfo['plasma_conservation_duration']; ?>" style="width:100%;" />&nbsp;(Jour/Heures)
										</td>
									</tr>
									<tr>
										<td><label for="">Date de départ au Labo biomol </label></td>
										<td>
											<input type="text" class="form-control dateTime" id="sampleDispatchedDate" name="sampleDispatchedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter date de départ au Labo biomol" value="<?php echo $vlQueryInfo['sample_dispatched_datetime']; ?>" style="width:100%;" />
										</td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td colspan="4"><label class="radio-inline" style="margin:0;padding:0;">A remplir par le préleveur </label></td>
									</tr>
								</table>
							</div>
						</div>
						<?php if ($_SESSION['instanceType'] != 'remoteuser') { ?>
							<div class="box box-primary">
								<div class="box-body">
									<div class="box-header with-border">
										<h3 class="box-title">2. Réservé au Laboratoire de biologie moléculaire </h3>
									</div>
									<table class="table" aria-hidden="true" style="width:100%">
										<tr style="<?php echo ($sCode != '') ? 'display:none' : ''; ?>">
											<td><label for="">Date de réception de l'échantillon  </label></td>
											<td>
												<input type="text" class="form-control dateTime" id="sampleReceivedDate<?php echo ($sCode != '') ? 'Lab' : ''; ?>" name="sampleReceivedDate<?php echo ($sCode != '') ? 'Lab' : ''; ?>" placeholder="<?= _("Please enter date"); ?>" title="Please enter date de réception de léchantillon" <?php echo $labFieldDisabled; ?> onchange="checkSampleReceviedDate();" value="<?php echo $vlQueryInfo['sample_received_at_vl_lab_datetime']; ?>" style="width:100%;" />
											</td>
											<td><label for="labId">Nom du laboratoire </label> </td>
											<td>
												<select name="labId" id="labId" class="form-control" title="Please choose laboratoire" style="width:100%;">
													<?= $general->generateSelectOptions($testingLabs, $vlQueryInfo['lab_id'], '-- Sélectionner --'); ?>
												</select>
											</td>
										</tr>
										<tr>
											<td><label for="">Date de réalisation de la charge virale </label></td>
											<td>
												<input type="text" class="form-control dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="<?= _("Please enter date"); ?>" title="Please enter date de réalisation de la charge virale" <?php echo $labFieldDisabled; ?> value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>" style="width:100%;" />
											</td>
											<td><label for="testingPlatform">Technique utilisée </label></td>
											<td>
												<select name="testingPlatform" id="testingPlatform" class="form-control" title="Please choose VL Testing Platform" <?php echo $labFieldDisabled; ?> style="width:100%;" onchange="getVlResults(this.value)">
													<option value="">-- Sélectionner --</option>
													<?php foreach ($importResult as $mName) { ?>
														<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['config_id']; ?>" <?php echo ($vlQueryInfo['vl_test_platform'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] == $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']) ? "selected='selected'" : "" ?>><?php echo $mName['machine_name']; ?></option>
													<?php } ?>
												</select>
											</td>
										</tr>
										<tr>
											<td><label for="">Décision prise </label></td>
											<td>
												<select class="form-control" id="noResult" name="noResult" title="Please select décision prise" <?php echo $labFieldDisabled; ?> onchange="checkTestStatus();" style="width:100%;">
													<option value=""> -- Sélectionner -- </option>
													<option value="no" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'selected="selected"' : ''; ?>>Echantillon accepté</option>
													<option value="yes" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'selected="selected"' : ''; ?>>Echantillon rejeté</option>
												</select>
											</td>
											<td class="rejectionReason" style="display:none;"><label for="rejectionReason">Motifs de rejet <span class="mandatory">*</span></label></td>
											<td class="rejectionReason" style="display:none;">
												<select class="form-control" id="rejectionReason" name="rejectionReason" title="Please select motifs de rejet" <?php echo $labFieldDisabled; ?> onchange="checkRejectionReason();" style="width:100%;">
													<option value=""> -- Sélectionner -- </option>
													<?php foreach ($rejectionResult as $rjctReason) { ?>
														<option value="<?php echo $rjctReason['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $rjctReason['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ($rjctReason['rejection_reason_name']); ?></option>
													<?php } ?>
													<option value="other">Autre <span class="mandatory">*</span></option>
												</select>
												<input type="text" class="form-control newRejectionReason" id="newRejectionReason" name="newRejectionReason" placeholder="Motifs de rejet" title="Please enter motifs de rejet" <?php echo $labFieldDisabled; ?> style="width:100%;display:none;" />
											</td>
										</tr>
										<tr class="rejectionReason" style="display:<?php echo ($vlQueryInfo['result_status'] == 4) ? '' : 'none'; ?>;">
											<td class="newRejectionReason" style="text-align:center;display:none;"><label for="newRejectionReason" class="newRejectionReason" style="display:none;">Autre, à préciser <span class="mandatory">*</span></label></td>
											<td class="newRejectionReason" style="display:none;"><input type="text" class="form-control newRejectionReason" id="newRejectionReason" name="newRejectionReason" placeholder="Motifs de rejet" title="Please enter motifs de rejet" <?php echo $labFieldDisabled; ?> style="width:100%;display:none;" /></td>
											<th class="rejectionReason" style="display:none;"><?php echo _("Rejection Date"); ?> <span class="mandatory">*</span></th>
											<td class="rejectionReason" style="display:none;"><input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
										</tr>
										<tr class="vlResult" style="<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'display: none;' : ''; ?>">
											<td class="vlResult"><label for="vlResult">Résultat</label></td>
											<td class="vlResult resultInputContainer">
												<input list="possibleVlResults" class="form-control result-fields labSection" id="vlResult" name="vlResult" placeholder="Select or Type VL Result" title="Please enter résultat" value="<?php echo $vlQueryInfo['result']; ?>" onchange="calculateLogValue(this)">
												<datalist id="possibleVlResults">
													<!--<option value="< 20" <?php echo (isset($vlQueryInfo['result']) && $vlQueryInfo['result'] == '< 20') ? "selected='selected'" : ""; ?>> &lt; 20 </option>
													<option value="< 40" <?php echo (isset($vlQueryInfo['result']) && $vlQueryInfo['result'] == '< 40') ? "selected='selected'" : ""; ?>> &lt; 40 </option>
													<option value="< 400" <?php echo (isset($vlQueryInfo['result']) && $vlQueryInfo['result'] == '< 400') ? "selected='selected'" : ""; ?>> &lt; 400 </option>
													<option value="Target Not Detected" <?php echo (isset($vlQueryInfo['result']) && $vlQueryInfo['result'] == 'Target Not Detected') ? "selected='selected'" : ""; ?>> Target Not Detected </option>-->
												</datalist>
											</td>
											<td class="vlLog" style="text-align:center;"><label for="vlLog">Log </label></td>
											<td class="vlLog">
												<input type="text" class="form-control forceNumeric other-failed-results" id="vlLog" name="vlLog" placeholder="Log" title="Please enter log" value="<?php echo $vlQueryInfo['result_value_log']; ?>" <?php echo $labFieldDisabled; ?> onchange="calculateLogValue(this)" style="width:100%;" />&nbsp;(copies/ml)
											</td>
										</tr>
										<?php if (count($reasonForFailure) > 0) { ?>
											<tr class="reasonForFailure vlResult" style="<?php echo (!isset($vlQueryInfo['result']) || $vlQueryInfo['result'] != 'Failed') ? 'display: none;' : ''; ?>">
												<th class="reasonForFailure"><?php echo _("Reason for Failure"); ?></th>
												<td class="reasonForFailure">
													<select name="reasonForFailure" id="reasonForFailure" class="form-control vlResult" title="Please choose reason for failure" style="width: 100%;">
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
											<th>Approuvé le</th>
											<td>
												<input type="text" name="approvedOn" id="approvedOn" value="<?php echo $vlQueryInfo['result_approved_datetime']; ?>" class="dateTime authorisation form-control" placeholder="Approuvé le" title="Please enter the Approuvé le" />
											</td>
											<th>Approuvé par</th>
											<td>
												<select name="approvedBy" id="approvedBy" class="select2 authorisation form-control" title="Please choose Approuvé par" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_approved_by'], '-- Select --'); ?>
												</select>
											</td>
										</tr>
										<tr>
											<td class=" reasonForResultChanges" style="visibility:hidden;">
												<label for="reasonForResultChanges">Razão para as mudanças nos resultados <span class="mandatory">*</span></label>
											</td>
											<td colspan="7" class="reasonForResultChanges" style="visibility:hidden;">
												<textarea class="form-control" name="reasonForResultChanges" id="reasonForResultChanges" placeholder="Enter Reason For Result Changes" title="Razão para as mudanças nos resultados" style="width:100%;"></textarea>
											</td>
										</tr>
									</table>
								</div>
							</div>
						<?php } ?>
						<div class="box-header with-border">
							<label class="radio-inline" style="margin:0;padding:0;">1. Biffer la mention inutile <br>2. Sélectionner un seul régime de traitement </label>
						</div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="sampleCodeCol" value="<?php echo $vlQueryInfo['sample_code']; ?>" />
						<input type="hidden" id="vlSampleId" name="vlSampleId" value="<?php echo $vlQueryInfo['vl_sample_id']; ?>" />
						<input type="hidden" name="oldStatus" value="<?php echo $vlQueryInfo['result_status']; ?>" />
						<input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
						<a href="vlRequest.php" class="btn btn-default"> Cancel</a>
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
<script type="text/javascript" src="/assets/js/datalist-css.min.js"></script>
<script type="text/javascript">
	
	changeProvince = true;
	changeFacility = true;


	provinceName = true;
	facilityName = true;

	
	$(document).ready(function() {
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

		$('#fName').select2({
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

		//$('#sampleCollectionDate').trigger("change");
		$('#sampleCollectionDate').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            maxDate: "+1Y",
           // yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>",
			onSelect: function(date) {
				var dt2 = $('#sampleDispatchedDate');
				var startDate = $(this).datetimepicker('getDate');
				var minDate = $(this).datetimepicker('getDate');
				dt2.datetimepicker('setDate', minDate);
				startDate.setDate(startDate.getDate() + 1000000);
				dt2.datetimepicker('option', 'maxDate', "+1Y");
				dt2.datetimepicker('option', 'minDate', minDate);
				dt2.datetimepicker('option', 'minDateTime', minDate);
				dt2.val($(this).val());
			}
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });
	

		var minDate = $('#sampleCollectionDate').datetimepicker('getDate');
		if($("#sampleDispatchedDate").val()=="")
		$("#sampleDispatchedDate").val($('#sampleCollectionDate').val());
		
		$('#sampleDispatchedDate').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            minDate: minDate,
			startDate: minDate,
        });
	});
	

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
						$("#fName").html(details[0]);
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
		var cName = $("#fName").val();
		if (dName != '') {
			$.post("/includes/siteInformationDropdownOptions.php", {
					dName: dName,
					cliName: cName,
					testType: 'vl'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#fName").html(details[0]);
					}
				});
		} else {
			$("#fName").html("<option value=''> -- Sélectionner -- </option>");
		}
		$.unblockUI();
	}

	function setPatientDetails(pDetails) {
		var patientArray = JSON.parse(pDetails);
		console.log(patientArray);
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
			}
		}
		if ($.trim(patientArray['patient_art_no']) != '') {
			$("#artNo").val($.trim(patientArray['patient_art_no']));
		}
		if ($.trim(patientArray['current_regimen']) != '') {
			$("#artRegimen").val($.trim(patientArray['current_regimen']));
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
		} else if ($(this).val() == 'no') {
			$(".arvChangedElement").hide();
		}
	});

	$("input:radio[name=isPatientNew]").click(function() {
		if ($(this).val() == 'yes') {
			$(".du").css("visibility", "visible");
		} else if ($(this).val() == 'no') {
			$(".du").css("visibility", "hidden");
		}
	});
	$("input:radio[name=gender]").click(function() {
		if ($(this).val() == 'female') {
			$("#femaleElements").show();
		} else if ($(this).val() == 'male') {
			$("#femaleElements").hide();
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
		var status = $("#noResult").val();
		if (status == 'yes') {
			$('#vlResult').attr('disabled', false);
			$('#vlLog').attr('disabled', false);
			$(".rejectionReason").show();
			$("#rejectionReason").addClass('isRequired');
			$("#vlResult").val('').css('pointer-events', 'none');
			$("#vlLog").val('').css('pointer-events', 'none');
			$("#rejectionReason").val('').css('pointer-events', 'auto');
			$(".vlResult, .vlLog").hide();
			$("#reasonForFailure").removeClass('isRequired');
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
		if($(this).val() != ""){
			$('.authorisation').addClass("isRequired");
		}else{
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

	
</script>