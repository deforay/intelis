<?php
ob_start();
//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);
//check remote user
$pdQuery = "SELECT * FROM province_details";
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
		$pdQuery = "SELECT * from province_details as pd JOIN facility_details as fd ON fd.facility_state=pd.province_name JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where user_id='" . $_SESSION['userId'] . "' group by province_name";
	}
} else {
	$sampleCode = 'sample_code';
}
$pdResult = $db->query($pdQuery);
$province = "";
$province .= "<option value=''> -- Sélectionner -- </option>";
foreach ($pdResult as $provinceName) {
	$province .= "<option value='" . $provinceName['province_name'] . "##" . $provinceName['province_code'] . "'>" . ucwords($provinceName['province_name']) . "</option>";
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
$provinceQuery = "SELECT * FROM province_details WHERE province_name='" . $stateResult[0]['facility_state'] . "'";
$provinceResult = $db->query($provinceQuery);
if (!isset($provinceResult[0]['province_code']) || $provinceResult[0]['province_code'] == '') {
	$provinceResult[0]['province_code'] = "";
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
		<h1><i class="fa-solid fa-pen-to-square"></i> VIRAL LOAD LABORATORY REQUEST FORM</h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa-solid fa-chart-pie"></i> Home</a></li>
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
				<form class="form-horizontal" method="post" name="editVlRequestForm" id="editVlRequestForm" autocomplete="off" action="editVlRequestHelperDrc.php">
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


								<table class="table" style="width:100%">


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
											<input type="text" class="form-control dateTime isRequired" id="sampleReceivedDate<?php echo ($sCode == '') ? 'Lab' : ''; ?>" name="sampleReceivedDate<?php echo ($sCode == '') ? 'Lab' : ''; ?>" placeholder="e.g 09-Jan-1992 05:30" title="Please enter date de réception de léchantillon" <?php echo $labFieldDisabled; ?> onchange="checkSampleReceviedDate();" value="<?php echo ($vlQueryInfo['sample_received_at_vl_lab_datetime'] != '' && $vlQueryInfo['sample_received_at_vl_lab_datetime'] != NULL) ? $vlQueryInfo['sample_received_at_vl_lab_datetime'] : date('d-M-Y H:i:s'); ?>" style="width:100%;" />
										</td>


									</tr>
									<tr>
										<td><label for="province">Province </label><span class="mandatory">*</span></td>
										<td>
											<select class="form-control isRequired" name="province" id="province" title="Please choose province" onchange="getfacilityDetails(this);" style="width:100%;">
												<option value=""> -- Sélectionner -- </option>
												<?php foreach ($pdResult as $provinceName) { ?>
													<option value="<?php echo $provinceName['province_name'] . "##" . $provinceName['province_code']; ?>" <?php echo (strtolower($stateResult[0]['facility_state']) . "##" . strtolower($provinceResult[0]['province_code']) == strtolower($provinceName['province_name']) . "##" . strtolower($provinceName['province_code'])) ? "selected='selected'" : "" ?>><?php echo ucwords($provinceName['province_name']); ?></option>
												<?php } ?>
											</select>
										</td>
										<td><label for="district">Zone de santé </label><span class="mandatory">*</span></td>
										<td>
											<select class="form-control isRequired" name="district" id="district" title="Veuillez choisir le quartier" style="width:100%;" onchange="getfacilityDistrictwise(this);">
												<option value=""> -- Sélectionner -- </option>
												<?php foreach ($districtResult as $districtName) { ?>
													<option value="<?php echo $districtName['facility_district']; ?>" <?php echo ($stateResult[0]['facility_district'] == $districtName['facility_district']) ? "selected='selected'" : "" ?>><?php echo ucwords($districtName['facility_district']); ?></option>
												<?php } ?>
											</select>
										</td>
										<td><label for="clinicName">POINT DE COLLECT </label><span class="mandatory">*</span></td>
										<td>
											<select class="form-control isRequired" name="clinicName" id="clinicName" title="Veuillez choisir le POINT DE COLLECT" onchange="getfacilityProvinceDetails(this);" style="width:100%;">
												<?= $facility; ?>
											</select>
										</td>
									</tr>
									<tr>
										<td><label for="clinicianName">Demandeur </label></td>
										<td>
											<input type="text" class="form-control" id="clinicianName" name="clinicianName" placeholder="Demandeur" title="Veuillez saisir le demandeur" value="<?php echo $vlQueryInfo['request_clinician_name']; ?>" style="width:100%;" />
										</td>
										<td><label for="clinicanTelephone">Téléphone </label></td>
										<td>
											<input type="text" class="form-control forceNumeric" id="clinicanTelephone" name="clinicanTelephone" placeholder="Téléphone" title="Veuillez entrer le téléphone" value="<?php echo $vlQueryInfo['request_clinician_phone_number']; ?>" style="width:100%;" />
										</td>
										<td><label for="supportPartner">Partenaire dappui </label></td>
										<td>
											<!-- <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire dappui" title="Please enter partenaire dappui" value="< ?php echo $vlQueryInfo['facility_support_partner']; ?>" style="width:100%;"/> -->
											<select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose partenaire de mise en œuvre" style="width:100%;">
												<option value=""> -- Sélectionner -- </option>
												<?php
												foreach ($implementingPartnerList as $implementingPartner) {
												?>
													<option value="<?php echo base64_encode($implementingPartner['i_partner_id']); ?>" <?php echo ($implementingPartner['i_partner_id'] == $vlQueryInfo['implementing_partner']) ? 'selected="selected"' : ''; ?>><?php echo ucwords($implementingPartner['i_partner_name']); ?></option>
												<?php } ?>
											</select>
										</td>
									</tr>
									<tr>
										<td><label for="">Date de la demande </label></td>
										<td>
											<input type="text" class="form-control date" id="dateOfDemand" name="dateOfDemand" placeholder="e.g 09-Jan-1992" title="Please enter date de la demande" value="<?php echo $vlQueryInfo['date_test_ordered_by_physician']; ?>" style="width:100%;" />
										</td>
										<td><label for="fundingSource">Source de financement </label></td>
										<td>
											<select class="form-control" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
												<option value=""> -- Sélectionner -- </option>
												<?php
												foreach ($fundingSourceList as $fundingSource) {
												?>
													<option value="<?php echo base64_encode($fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $vlQueryInfo['funding_source']) ? 'selected="selected"' : ''; ?>><?php echo ucwords($fundingSource['funding_source_name']); ?></option>
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
                                        <option value="< ?php echo base64_encode($implementingPartner['i_partner_id']); ?>" < ?php echo ($implementingPartner['i_partner_id'] == $vlQueryInfo['implementing_partner'])?'selected="selected"':''; ?>>< ?php echo ucwords($implementingPartner['i_partner_name']); ?></option>
                                      < ?php } ?>
                                    </select>
                                </td> -->
									</tr>

								</table>
								<div class="box-header with-border">
									<h3 class="box-title">Information sur le patient </h3>&nbsp;&nbsp;&nbsp;
									<input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" placeholder="Code du patient" title="Please enter code du patient" />&nbsp;&nbsp;
									<a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><i class="fa-solid fa-magnifying-glass"></i>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><b>&nbsp;No Patient Found</b></span>
								</div>
								<table class="table" style="width:100%">
									<tr>
										<td style="width:10%;"><label for="">Date de naissance </label></td>
										<td style="width:15%;">
											<input type="text" class="form-control date" id="dob" name="dob" placeholder="e.g 09-Jan-1992" title="Please select date de naissance" onchange="getAge();checkARTInitiationDate();" value="<?php echo $vlQueryInfo['patient_dob']; ?>" style="width:100%;" />
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
										<td><label for="patientArtNo">Code du patient <span class="mandatory">*</span></label></td>
										<td>
											<input type="text" class="form-control isRequired" id="patientArtNo" name="patientArtNo" placeholder="Code du patient" title="Please enter code du patient" value="<?php echo $vlQueryInfo['patient_art_no']; ?>" style="width:100%;" />
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
											<input type="text" class="form-control date" id="dateOfArtInitiation" name="dateOfArtInitiation" placeholder="e.g 09-Jan-1992" title="Please enter date du début des ARV" value="<?php echo $vlQueryInfo['date_of_initiation_of_current_regimen']; ?>" onchange="checkARTInitiationDate();checkLastVLTestDate();" style="width:100%;" />&nbsp;(Jour/Mois/Année)
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
											<input type="text" class="form-control date" id="dateOfArvRegimenChange" name="dateOfArvRegimenChange" placeholder="e.g 09-Jan-1992" title="Please enter date du changement de régime ARV" value="<?php echo $vlQueryInfo['regimen_change_date']; ?>" style="width:100%;" />&nbsp;(Jour/Mois/Année)
										</td>
										<td></td>
										<td></td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td><label for="reasonForRequest">Motif de la demande <span class="mandatory">*</span></label></td>
										<td colspan="3">
											<select name="vlTestReason" id="vlTestReason" class="form-control isRequired" title="Please choose motif de la demande" onchange="checkVLTestReason();">
												<option value=""> -- Sélectionner -- </option>
												<?php foreach ($vlTestReasonResult as $tReason) { ?>
													<option value="<?php echo $tReason['test_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_vl_testing'] == $tReason['test_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ucwords($tReason['test_reason_name']); ?></option>
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
									<tr class="newVlTestReason" style="display:none;">
										<td><label for="newVlTestReason">Autre, à préciser <span class="mandatory">*</span></label></td>
										<td colspan="3">
											<input type="text" class="form-control" name="newVlTestReason" id="newVlTestReason" placeholder="Virale Demande Raison" title="Please enter virale demande raison" style="width:100%;">
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
											<input type="text" class="form-control date" id="lastViralLoadTestDate" name="lastViralLoadTestDate" placeholder="e.g 09-Jan-1992" title="Please enter date dernière charge virale" value="<?php echo $vlQueryInfo['last_viral_load_date']; ?>" onchange="checkLastVLTestDate();" style="width:100%;" />
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
								<table class="table" style="width:100%">
									<tr>
										<td style="width:25%;"><label for="">Date du prélèvement <span class="mandatory">*</span></label></td>
										<td style="width:25%;">
											<input type="text" class="form-control dateTime isRequired" id="sampleCollectionDate" name="sampleCollectionDate" placeholder="e.g 09-Jan-1992 05:30" title="Please enter date du prélèvement" value="<?php echo $vlQueryInfo['sample_collection_date']; ?>" onchange="checkSampleReceviedDate();checkSampleTestingDate();" style="width:100%;" />
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
														<option value="<?php echo $type['sample_id']; ?>" <?php echo ($vlQueryInfo['sample_type'] == $type['sample_id']) ? 'selected="selected"' : ''; ?>><?php echo ucwords($type['sample_name']); ?></option>
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
											<input type="text" class="form-control dateTime" id="dateDispatchedFromClinicToLab" name="dateDispatchedFromClinicToLab" placeholder="e.g 09-Jan-1992 05:30" title="Please enter date de départ au Labo biomol" value="<?php echo $vlQueryInfo['date_dispatched_from_clinic_to_lab']; ?>" style="width:100%;" />
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
									<table class="table" style="width:100%">
										<tr style="<?php echo ($sCode != '') ? 'display:none' : ''; ?>">
											<td><label for="">Date de réception de l'échantillon <span class="mandatory">*</span> </label></td>
											<td>
												<input type="text" class="form-control dateTime isRequired" id="sampleReceivedDate<?php echo ($sCode != '') ? 'Lab' : ''; ?>" name="sampleReceivedDate<?php echo ($sCode != '') ? 'Lab' : ''; ?>" placeholder="e.g 09-Jan-1992 05:30" title="Please enter date de réception de léchantillon" <?php echo $labFieldDisabled; ?> onchange="checkSampleReceviedDate();" value="<?php echo $vlQueryInfo['sample_received_at_vl_lab_datetime']; ?>" style="width:100%;" />
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
												<input type="text" class="form-control dateTime" id="dateOfCompletionOfViralLoad" name="dateOfCompletionOfViralLoad" placeholder="e.g 09-Jan-1992 05:30" title="Please enter date de réalisation de la charge virale" <?php echo $labFieldDisabled; ?> value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>" style="width:100%;" />
											</td>
											<td><label for="testingPlatform">Technique utilisée </label></td>
											<td>
												<select name="testingPlatform" id="testingPlatform" class="form-control" title="Please choose VL Testing Platform" <?php echo $labFieldDisabled; ?> style="width:100%;">
													<option value="">-- Sélectionner --</option>
													<?php foreach ($importResult as $mName) { ?>
														<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']; ?>" <?php echo ($vlQueryInfo['vl_test_platform'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] == $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']) ? "selected='selected'" : "" ?>><?php echo $mName['machine_name']; ?></option>
													<?php } ?>
												</select>
											</td>
										</tr>
										<tr>
											<!-- <td class="hivDetection resultSection" style="<?php echo (isset($vlQueryInfo['vl_test_platform']) && $vlQueryInfo['vl_test_platform'] != 'GeneXpert') ? 'display: none;' : ''; ?>"><?php echo _("HIV Detection"); ?></td>
											<td class="hivDetection resultSection" style="<?php echo (isset($vlQueryInfo['vl_test_platform']) && $vlQueryInfo['vl_test_platform'] != 'GeneXpert') ? 'display: none;' : ''; ?>">
												<select name="hivDetection" id="hivDetection" class="form-control" title="Please choose HIV detection">
													<option value="">-- <?php echo _("Select"); ?> --</option>
													<option value="HIV-1 Detected" <?php echo (isset($vlQueryInfo['result_value_hiv_detection']) && $vlQueryInfo['result_value_hiv_detection'] == 'HIV-1 Detected') ? 'selected="selected"' : ''; ?>><?php echo _("HIV-1 Detected"); ?></option>
													<option value="HIV-1 Not Detected" <?php echo (isset($vlQueryInfo['result_value_hiv_detection']) && $vlQueryInfo['result_value_hiv_detection'] == 'HIV-1 Not Detected') ? 'selected="selected"' : ''; ?>><?php echo _("HIV-1 Not Detected"); ?></option>
												</select>
											</td> -->
											<td><label for="">Décision prise </label></td>
											<td>
												<select class="form-control" id="isSampleRejected" name="isSampleRejected" title="Please select décision prise" <?php echo $labFieldDisabled; ?> onchange="checkTestStatus();" style="width:100%;">
													<option value=""> -- Sélectionner -- </option>
													<option value="no" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'selected="selected"' : ''; ?>>Echantillon accepté</option>
													<option value="yes" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'selected="selected"' : ''; ?>>Echantillon rejeté</option>
												</select>
											</td>
										</tr>
										<tr class="rejectionReason" style="display:<?php echo ($vlQueryInfo['result_status'] == 4) ? '' : 'none'; ?>;">
											<td><label for="rejectionReason">Motifs de rejet <span class="mandatory">*</span></label></td>
											<td>
												<select class="form-control" id="rejectionReason" name="rejectionReason" title="Please select motifs de rejet" <?php echo $labFieldDisabled; ?> onchange="checkRejectionReason();" style="width:100%;">
													<option value=""> -- Sélectionner -- </option>
													<?php foreach ($rejectionResult as $rjctReason) { ?>
														<option value="<?php echo $rjctReason['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $rjctReason['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ucwords($rjctReason['rejection_reason_name']); ?></option>
													<?php } ?>
													<option value="other">Autre</option>
												</select>
												<input type="text" class="form-control newRejectionReason" id="newRejectionReason" name="newRejectionReason" placeholder="Motifs de rejet" title="Please enter motifs de rejet" <?php echo $labFieldDisabled; ?> style="width:100%;display:none;" />
											</td>
											<td class="rejectionReason" style="display:none;"><?php echo _("Rejection Date"); ?></td>
											<td class="rejectionReason" style="display:none;"><input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
										</tr>
										<tr class="vlResult" style="<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'display: none;' : ''; ?>">
											<td class="vlResult"><label for="vlResult">Résultat</label></td>
											<td class="vlResult">

												<input type="text" class="vlResult form-control forceNumeric other-failed-results" id="vlResult" name="vlResult" placeholder="Résultat (copies/ml)" title="Please enter résultat" <?php echo $labFieldDisabled; ?> value="<?php echo $vlQueryInfo['result']; ?>" onchange="calculateLogValue(this)" style="width:100%;" />
												<input type="checkbox" class="specialResults other-failed-results" id="vlLt20" name="vlLt20" value="yes" title="Please check VL value" <?php echo ($vlQueryInfo['result'] == '< 20' || $vlQueryInfo['result'] == '<20') ? 'checked="checked"' : ''; ?>>
												&lt; 20<br>
												<input type="checkbox" class="specialResults other-failed-results" id="vlLt40" name="vlLt40" value="yes" title="Please check VL value" <?php echo ($vlQueryInfo['result'] == '< 40' || $vlQueryInfo['result'] == '<40') ? 'checked="checked"' : ''; ?>>
												&lt; 40<br>
												<input type="checkbox" class="specialResults other-failed-results" id="vlLt400" name="vlLt400" value="yes" title="Please check VL value" <?php echo ($vlQueryInfo['result'] == '< 400' || $vlQueryInfo['result'] == '<400') ? 'checked="checked"' : ''; ?>>
												&lt; 400<br>
												<input type="checkbox" class="specialResults other-failed-results" id="vlTND" name="vlTND" value="yes" title="Please check VL value" <?php echo in_array(strtolower($vlQueryInfo['result']), array('target not detected', 'non détecté', 'non détecté', 'non detecte', 'non detectee', 'tnd', 'bdl', 'below detection level')) ? 'checked="checked"' : ''; ?>> Target Not Detected / Non Détecté<br>
												<input type="checkbox" class="labSection specialResults" id="failed" name="failed" value="yes" title="Please check failed" <?php echo ($vlQueryInfo['result'] == 'Failed') ? 'checked="checked"' : ''; ?>> Failed<br>
											</td>
											<td class="vlLog" style="text-align:center;"><label for="vlLog">Log </label></td>
											<td class="vlLog">
												<input type="text" class="form-control forceNumeric other-failed-results" id="vlLog" name="vlLog" placeholder="Log" title="Please enter log" value="<?php echo $vlQueryInfo['result_value_log']; ?>" <?php echo $labFieldDisabled; ?> onchange="calculateLogValue(this)" style="width:100%;" />&nbsp;(copies/ml)
											</td>
										</tr>
										<?php if (count($reasonForFailure) > 0) { ?>
											<tr class="reasonForFailure vlResult" style="<?php echo (!isset($vlQueryInfo['result']) || $vlQueryInfo['result'] != 'Failed') ? 'display: none;' : ''; ?>">
												<td class="reasonForFailure"><?php echo _("Reason for Failure"); ?></td>
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
												<input type="text" name="reviewedOn" value="<?php echo $vlQueryInfo['result_reviewed_datetime']; ?>" id="reviewedOn" class="dateTime form-control" placeholder="Revu le" title="Please enter the Revu le" />
											</td>
											<td style="width:14%;"><label for="reviewedBy"> Revu par </label></td>
											<td style="width:14%;">
												<select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose revu par" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_reviewed_by'], '-- Select --'); ?>
												</select>
											</td>
										</tr>
										<tr>
											<td>Approuvé le</td>
											<td>
												<input type="text" name="approvedOn" id="approvedOn" value="<?php echo $vlQueryInfo['result_approved_datetime']; ?>" class="dateTime form-control" placeholder="Approuvé le" title="Please enter the Approuvé le" />
											</td>
											<td>Approuvé par</td>
											<td>
												<select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose Approuvé par" style="width: 100%;">
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
<script type="text/javascript">
	changeProvince = true;
	changeFacility = true;


	provinceName = true;
	facilityName = true;


	$(document).ready(function() {
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
	});

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
						$("#clinicName").html(details[0]);
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
		var cName = $("#clinicName").val();
		if (dName != '') {
			$.post("/includes/siteInformationDropdownOptions.php", {
					dName: dName,
					cliName: cName,
					testType: 'vl'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#clinicName").html(details[0]);
					}
				});
		} else {
			$("#clinicName").html("<option value=''> -- Sélectionner -- </option>");
		}
		$.unblockUI();
	}

	function setPatientDetails(pDetails) {
		patientArray = pDetails.split("##");
		console.log(patientArray);
		if ($.trim(patientArray[3]) != '') {
			$("#dob").val(patientArray[3]);
			getAge();
		} else if ($.trim(patientArray[4]) != '' && $.trim(patientArray[4]) != 0) {
			$("#ageInYears").val(patientArray[4]);
		} else if ($.trim(patientArray[5]) != '') {
			$("#ageInMonths").val(patientArray[5]);
		}
		if ($.trim(patientArray[2]) != '') {
			if (patientArray[2] == 'male') {
				$("#genderMale").prop('checked', true);
			} else if (patientArray[2] == 'female') {
				$("#genderFemale").prop('checked', true);
			}
		}
		if ($.trim(patientArray[15]) != '') {
			$("#patientArtNo").val($.trim(patientArray[15]));
		}
		if ($.trim(patientArray[11]) != '') {
			$("#artRegimen").val($.trim(patientArray[11]));
		}
		if ($.trim(patientArray[16]) != '') {
			if (patientArray[16] == 'yes') {
				$("#isPatientNewYes").prop('checked', true);
			} else if (patientArray[16] == 'no') {
				$("#isPatientNewNo").prop('checked', true);
			}
		}
	}

	function getfacilityProvinceDetails(obj) {
		$.blockUI();
		//check facility name
		var cName = $("#clinicName").val();
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

	function checkVLTestReason() {
		var vlTestReason = $("#vlTestReason").val();
		if (vlTestReason == "other") {
			$(".newVlTestReason").show();
			$("#newVlTestReason").addClass("isRequired");
		} else {
			$(".newVlTestReason").hide();
			$("#newVlTestReason").removeClass("isRequired");
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
			$('.specialResults').prop('checked', false).removeAttr('checked');
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
			//$("#vlResult").addClass('isRequired');
		}
	}

	$('#hivDetection').change(function() {
		if (this.value == 'HIV-1 Not Detected') {
			$('.specialResults').prop('checked', false).removeAttr('checked');
			$('#vlResult').attr('disabled', false);
			$('#vlLog').attr('disabled', false);
			$("#vlResult").val('').css('pointer-events', 'none');
			$("#vlLog").val('').css('pointer-events', 'none');
			$(".vlResult, .vlLog").hide();
			$("#reasonForFailure").removeClass('isRequired');
		} else {
			$("#vlResult").css('pointer-events', 'auto');
			$("#vlLog").css('pointer-events', 'auto');
			$("#vlResult").val('').css('pointer-events', 'auto');
			$("#vlLog").val('').css('pointer-events', 'auto');
			$(".vlResult, .vlLog").show();
		}
	});

	$('#testingPlatform').change(function() {
		var text = this.value;
		var str1 = text.split("##");
		var str = str1[0];
		if (str1[0] == 'GeneXpert' || str.toLowerCase() == 'genexpert') {
			$('.hivDetection').show();
		} else {
			$('.hivDetection').hide();
		}
	});
	$('#failed').change(function() {
		if ($('#failed').prop('checked')) {
			$('.reasonForFailure').show();
			$('#reasonForFailure').addClass('isRequired');
			$('.other-failed-results').removeClass('isRequired');
			$('.other-failed-results').prop('checked', false);
			$('.other-failed-results').val('');
			$('.other-failed-results').prop('disabled', true);
		} else {
			$('.reasonForFailure').hide();
			$('#reasonForFailure').removeClass('isRequired');
			$('.other-failed-results').prop('disabled', false);
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
				$("#vlResult").val(absValue);
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



	$(document).ready(function() {

		$('#vlResult, #vlLog').on('input', function(e) {
			if (this.value != '') {
				$('.specialResults').attr('disabled', true);
			} else {
				$('.specialResults').attr('disabled', false);
			}
		});

		$('.specialResults').on('change', function() {
			if ($(this).is(':checked')) {
				$('#vlResult, #vlLog').val('');
				$('#vlResult,#vlLog').attr('readonly', true);
				$(".specialResults").not(this).attr('disabled', true);
				//$('.specialResults').not(this).prop('checked', false).removeAttr('checked');
			} else {
				$('#vlResult,#vlLog').attr('readonly', false);
				$(".specialResults").not(this).attr('disabled', false);
			}
		});


		$('#clinicName').select2({
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
	});
</script>