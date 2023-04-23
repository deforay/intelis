<?php

// imported in eid-edit-request.php based on country in global config

use App\Services\EidService;
use App\Utilities\DateUtils;



//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);

//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);



$eidModel = new EidService();
$eidResults = $eidModel->getEidResults();


// Getting the list of Provinces, Districts and Facilities

$rKey = '';
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {
	$sampleCodeKey = 'remote_sample_code_key';
	$sampleCode = 'remote_sample_code';
	if (!empty($eidInfo['remote_sample']) && $eidInfo['remote_sample'] == 'yes') {
		$sampleCode = 'remote_sample_code';
	} else {
		$sampleCode = 'sample_code';
	}
	//check user exist in user_facility_map table
	$chkUserFcMapQry = "Select user_id from user_facility_map where user_id='" . $_SESSION['userId'] . "'";
	$chkUserFcMapResult = $db->query($chkUserFcMapQry);
	if ($chkUserFcMapResult) {
        $pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
	}
	$rKey = 'R';
} else {
	$sampleCodeKey = 'sample_code_key';
	$sampleCode = 'sample_code';
	$rKey = '';
}

//suggest sample id when lab user add request sample
$sampleSuggestion = '';
$sampleSuggestionDisplay = 'display:none;';


$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Sélectionner -- </option>";
foreach ($pdResult as $provinceName) {
	$province .= "<option value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, $eidInfo['facility_id'], '-- Sélectionner --');

$eidInfo['mother_treatment'] = isset($eidInfo['mother_treatment']) ? explode(",", $eidInfo['mother_treatment']) : array();
$eidInfo['child_treatment'] = isset($eidInfo['child_treatment']) ? explode(",", $eidInfo['child_treatment']) : array();

?>

<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _("EARLY INFANT DIAGNOSIS (EID) LABORATORY REQUEST FORM"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Edit EID Request"); ?></li>
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
				<form class="form-horizontal" method="post" name="editEIDRequestForm" id="editEIDRequestForm" autocomplete="off" action="eid-edit-request-helper.php">
					<div class="box-body">
						<div class="box box-default">
							<div class="box-body">
								<div class="box-header with-border">
									<h3 class="box-title">A. Réservé à la structure de soins</h3>
								</div>
								<div class="box-header with-border">
									<h3 class="box-title">Information sur la structure de soins</h3>
								</div>

								<div class="" style="<?php echo $sampleSuggestionDisplay; ?>">
									<?php
									if ($eidInfo['sample_code'] != '') {
									?>
										<label for="sampleSuggest" class="text-danger">&nbsp;&nbsp;&nbsp;Please note that this Remote Sample has already been imported with VLSM Sample ID <?php echo $eidInfo['sample_code']; ?></label>
									<?php
									} else {
									?>
										<label for="sampleSuggest">&nbsp;&nbsp;&nbsp;Échantillon ID (peut changer en soumettant le formulaire) - </label>
										<?php echo $sampleSuggestion; ?>
									<?php } ?>

								</div>
								<table class="table" aria-hidden="true"  style="width:100%">
									<tr>

										<?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
											<td><label for="sampleCode">Échantillon ID <span class="mandatory">*</span> </label></td>
											<td>
												<span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?php echo $eidInfo[$sampleCode]; ?></span>
												<input type="hidden" class="<?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" value="<?php echo $eidInfo[$sampleCode]; ?>" />
											</td>
										<?php } else { ?>
											<td><label for="sampleCode">Échantillon ID <span class="mandatory">*</span></label></td>
											<td>
												<input type="text" class="form-control isRequired <?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" <?php echo $maxLength; ?> placeholder="Enter Sample ID" title="Please enter sample id" value="<?php echo ($sCode != '') ? $sCode : $eidInfo[$sampleCode]; ?>" style="width:100%;" readonly="readonly" onchange="checkSampleNameValidation('form_eid','<?php echo $sampleCode; ?>',this.id,'<?php echo "eid_id##" . $eidInfo["eid_id"]; ?>','This sample number already exists.Try another number',null)" />
												<input type="hidden" name="sampleCodeCol" value="<?php echo $eidInfo['sample_code']; ?>" />
											</td>
										<?php } ?>
										</td>
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
												<option value=""> -- Sélectionner -- </option>
											</select>
										</td>
										<td><label for="facilityId">POINT DE COLLECT </label><span class="mandatory">*</span></td>
										<td>
											<select class="form-control isRequired " name="facilityId" id="facilityId" title="Please choose service provider" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
												<?php echo $facility; ?>
											</select>
										</td>
									</tr>
									<tr>
										<td><label for="supportPartner">Partnaire d'appui </label></td>
										<td>
											<!-- <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire dappui" title="Please enter partenaire dappui" style="width:100%;"/> -->
											<select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose partenaire de mise en œuvre" style="width:100%;">
												<option value=""> -- Sélectionner -- </option>
												<?php
												foreach ($implementingPartnerList as $implementingPartner) {
												?>
													<option value="<?php echo ($implementingPartner['i_partner_id']); ?>" <?php echo ($eidInfo['implementing_partner'] == $implementingPartner['i_partner_id']) ? "selected='selected'" : ""; ?>><?php echo ($implementingPartner['i_partner_name']); ?></option>
												<?php } ?>
											</select>
										</td>
										<td><label for="fundingSource">Source de Financement</label></td>
										<td>
											<select class="form-control" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
												<option value=""> -- Sélectionner -- </option>
												<?php
												foreach ($fundingSourceList as $fundingSource) {
												?>
													<option value="<?php echo ($fundingSource['funding_source_id']); ?>" <?php echo ($eidInfo['funding_source'] == $fundingSource['funding_source_id']) ? "selected='selected'" : ""; ?>><?php echo ($fundingSource['funding_source_name']); ?></option>
												<?php } ?>
											</select>
										</td>
										<?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
											<!-- <tr> -->
											<td><label for="labId">Nom du Laboratoire <span class="mandatory">*</span></label> </td>
											<td>
												<select name="labId" id="labId" class="form-control isRequired" title="Nom du Laboratoire" style="width:100%;">
													<?= $general->generateSelectOptions($testingLabs, $eidInfo['lab_id'], '-- Sélectionner --'); ?>
												</select>
											</td>
											<!-- </tr> -->
										<?php } ?>
									</tr>
								</table>
								<br><br>
								<table class="table" aria-hidden="true"  style="width:100%">
									<tr>
										<th colspan=8>
											<h4>1. Données démographiques mère / enfant </h4><br>
											<h4 class="box-title">Information sur le patient &nbsp;&nbsp;&nbsp;
												<input style="width:30%;font-size: smaller;" type="text" name="artPatientNo" id="artPatientNo" placeholder="Code du patient" title="Please enter code du patient" />&nbsp;&nbsp;
												<a style="margin-top:-0.35%;font-weight:500;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList($('#artPatientNo').val(),0);"><em class="fa-solid fa-magnifying-glass"></em>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No Patient Found</strong></span>
											</h4>
										</th>
									</tr>
									<tr>
										<th colspan=8>
											<h5 style="font-weight:bold;font-size:1.1em;">ID de la mère</h5>
										</th>
									</tr>
									<tr>
										<th scope="row"><label for="mothersId">Code (si applicable) </label></th>
										<td>
											<input type="text" class="form-control " id="mothersId" name="mothersId" placeholder="Code du mère" title="Please enter code du mère" style="width:100%;" value="<?php echo $eidInfo['mother_id'] ?>" />
										</td>
										<th scope="row"><label for="mothersName">Nom </label></th>
										<td>
											<input type="text" class="form-control " id="mothersName" name="mothersName" placeholder="Nom du mère" title="Please enter nom du mère" style="width:100%;" value="<?php echo $eidInfo['mother_name'] ?>" />
										</td>
										<th scope="row"><label for="mothersDob">Date de naissance </label></th>
										<td>
											<input type="text" class="form-control date" id="mothersDob" name="mothersDob" placeholder="Date de naissance" title="Please enter Date de naissance" style="width:100%;" value="<?php echo DateUtils::humanReadableDateFormat($eidInfo['mother_dob']); ?>" />
										</td>
										<th scope="row"><label for="mothersMaritalStatus">Etat civil </label></th>
										<td>
											<select class="form-control " name="mothersMaritalStatus" id="mothersMaritalStatus">
												<option value=''> -- Sélectionner -- </option>
												<option value='single' <?php echo ($eidInfo['mother_marital_status'] == 'single') ? "selected='selected'" : ""; ?>> Single </option>
												<option value='married' <?php echo ($eidInfo['mother_marital_status'] == 'married') ? "selected='selected'" : ""; ?>> Married </option>
												<option value='cohabitating' <?php echo ($eidInfo['mother_marital_status'] == 'cohabitating') ? "selected='selected'" : ""; ?>> Cohabitating </option>

											</select>
										</td>
									</tr>

									<tr>
										<th colspan=8>
											<h5 style="font-weight:bold;font-size:1.1em;">ID de l'enfant </h5>
										</th>
									</tr>
									<tr>
										<th scope="row"><label for="childId">Code de l’enfant (Patient) <span class="mandatory">*</span></label></th>
										<td>
											<input type="text" class="form-control isRequired" id="childId" name="childId" placeholder="Code (Patient)" title="Please enter code du enfant" style="width:100%;" value="<?php echo $eidInfo['child_id']; ?>" />
										</td>
										<th scope="row"><label for="childName">Nom </label></th>
										<td>
											<input type="text" class="form-control " id="childName" name="childName" placeholder="Nom" title="Please enter nom du enfant" style="width:100%;" value="<?php echo $eidInfo['child_name']; ?>" />
										</td>
										<th scope="row"><label for="childDob">Date de naissance </label></th>
										<td>
											<input type="text" class="form-control date" id="childDob" name="childDob" placeholder="Date de naissance" title="Please enter Date de naissance" style="width:100%;" value="<?php echo DateUtils::humanReadableDateFormat($eidInfo['child_dob']) ?>" onchange="calculateAgeInMonths();" />
										</td>
										<th scope="row"><label for="childGender">Gender </label></th>
										<td>
											<select class="form-control " name="childGender" id="childGender">
												<option value=''> -- Sélectionner -- </option>
												<option value='male' <?php echo ($eidInfo['child_gender'] == 'male') ? "selected='selected'" : ""; ?>> Male </option>
												<option value='female' <?php echo ($eidInfo['child_gender'] == 'female') ? "selected='selected'" : ""; ?>> Female </option>

											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">Age en mois</th>
										<td><input type="number" value="<?php echo $eidInfo['child_age']; ?>" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="childAge" name="childAge" placeholder="Age en mois" title="Age en mois" style="width:100%;" onchange="$('#childDob').val('')" /></td>
										<th scope="row"></th>
										<td></td>
										<th scope="row"></th>
										<td></td>
										<th scope="row"></th>
										<td></td>
										<th scope="row"></th>
										<td></td>
									</tr>

								</table>



								<br><br>
								<table class="table" aria-hidden="true"  style="width:100%">
									<tr>
										<th colspan=6>
											<h4>2. Management de la mère</h4>
										</th>
									</tr>
									<tr>
										<th colspan=2>ARV donnés à la maman pendant la grossesse:</th>
										<td colspan=4>
											<input type="checkbox" name="motherTreatment[]" value="Nothing" <?php echo in_array('Nothing', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> Rien <br>
											<input type="checkbox" name="motherTreatment[]" value="ARV Initiated during Pregnancy" <?php echo in_array('ARV Initiated during Pregnancy', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> ARV débutés durant la grossesse <br>
											<input type="checkbox" name="motherTreatment[]" value="ARV Initiated prior to Pregnancy" <?php echo in_array('ARV Initiated prior to Pregnancy', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> ARV débutés avant la grossesse <br>
											<input type="checkbox" name="motherTreatment[]" value="ARV at Child Birth" <?php echo in_array('ARV at Child Birth', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> ARV à l’accouchement <br>
											<input type="checkbox" name="motherTreatment[]" value="Option B plus" <?php echo in_array('Option B plus', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> Option B plus <br>
											<input type="checkbox" name="motherTreatment[]" value="AZT/3TC/NVP" <?php echo in_array('AZT/3TC/NVP', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> AZT/3TC/NVP <br>
											<input type="checkbox" name="motherTreatment[]" value="TDF/3TC/EFV" <?php echo in_array('TDF/3TC/EFV', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> TDF/3TC/EFV <br>
											<input type="checkbox" name="motherTreatment[]" value="Other" <?php echo in_array('Other', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> onclick="$('#motherTreatmentOther').prop('disabled', function(i, v) { return !v; });" /> Autres (à préciser): <input class="form-control" style="max-width:200px;display:inline;" disabled="disabled" placeholder="Autres" type="text" name="motherTreatmentOther" id="motherTreatmentOther" value="<?php echo $eidInfo['mother_treatment_other']; ?>" /> <br>
											<input type="checkbox" name="motherTreatment[]" value="Unknown" <?php echo in_array('Unknown', $eidInfo['mother_treatment']) ? "checked='checked'" : ""; ?> /> Inconnu
										</td>
									</tr>
									<tr>
										<th style="vertical-align:middle;">CD4</th>
										<td style="vertical-align:middle;">
											<div class="input-group">
												<input type="text" class="form-control" id="mothercd4" name="mothercd4" placeholder="CD4" title="CD4" style="width:100%;" value="<?php echo $eidInfo['mother_cd4']; ?>" />
												<div class="input-group-addon">/mm3</div>
											</div>
										</td>
										<th style="vertical-align:middle;">Viral Load</th>
										<td style="vertical-align:middle;">
											<div class="input-group">
												<input type="number" class="form-control " id="motherViralLoadCopiesPerMl" name="motherViralLoadCopiesPerMl" placeholder="Viral Load in copies/mL" title="Mother's Viral Load" style="width:100%;" value="<?php echo $eidInfo['mother_vl_result']; ?>" />
												<div class="input-group-addon">copies/mL</div>
											</div>
										</td>
										<td style="vertical-align:middle;">- OR -</td>
										<td style="vertical-align:middle;">
											<select class="form-control " title="Mother's Viral Load" name="motherViralLoadText" id="motherViralLoadText" onchange="updateMotherViralLoad();">
												<option value=''> -- Sélectionner -- </option>
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
								<table class="table" aria-hidden="true"  style="width:70%">
									<tr>
										<th colspan=2>
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
									</tr>
									<tr>
										<th scope="row">Bébé a arrêté allaitement maternel ?</th>
										<td>
											<select class="form-control" name="hasInfantStoppedBreastfeeding" id="hasInfantStoppedBreastfeeding">
												<option value=''> -- Sélectionner -- </option>
												<option value="yes" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
												<option value="no" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'no') ? "selected='selected'" : ""; ?> /> Non </option>
												<option value="unknown" <?php echo ($eidInfo['has_infant_stopped_breastfeeding'] == 'unknown') ? "selected='selected'" : ""; ?> /> Inconnu </option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">Age (mois) arrêt allaitement :</th>
										<td colspan="4">
											<input type="number" class="form-control" style="max-width:200px;display:inline;" placeholder="Age (mois) arrêt allaitement" type="text" name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" value="<?php echo $eidInfo['age_breastfeeding_stopped_in_months'] ?>" />
										</td>
									</tr>
									<tr>
										<th scope="row">Choix d’allaitement de bébé :</th>
										<td>
											<select class="form-control" name="choiceOfFeeding" id="choiceOfFeeding">
												<option value=''> -- Sélectionner -- </option>
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
												<option value=''> -- Sélectionner -- </option>
												<option value="no" <?php echo ($eidInfo['is_cotrimoxazole_being_administered_to_the_infant'] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
												<option value="Yes, takes CTX everyday" <?php echo ($eidInfo['is_cotrimoxazole_being_administered_to_the_infant'] == 'Yes, takes CTX everyday') ? "selected='selected'" : ""; ?>> Oui, prend CTX chaque jour </option>
												<option value="Starting on CTX today" <?php echo ($eidInfo['is_cotrimoxazole_being_administered_to_the_infant'] == 'Starting on CTX today') ? "selected='selected'" : ""; ?>> Commence CTX aujourd’hui </option>
											</select>

										</td>
									</tr>
								</table>






								<br><br>
								<table class="table" aria-hidden="true"  style="width:70%">
									<tr>
										<th colspan=2>
											<h4>4. Information sur l’échantillon</h4>
										</th>
									</tr>
									<tr>
										<th scope="row">Date de collecte</th>
										<td>
											<input class="form-control dateTime isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Date de collecte" value="<?php echo ($eidInfo['sample_collection_date']); ?>" />
										</td>
									</tr>
									<tr>
										<th scope="row">Tel. du préleveur</th>
										<td>
											<input class="form-control" type="text" name="sampleRequestorPhone" id="sampleRequestorPhone" placeholder="Tel. du préleveur" value="<?php echo $eidInfo['sample_requestor_phone']; ?>" />
										</td>
									</tr>
									<tr>
										<th style="width:14%;"> Type d'échantillon</th>
										<td style="width:35%;">
											<select name="specimenType" id="specimenType" class="form-control" title="Veuillez choisir le type d'échantillon" style="width:100%">
												<option value="">-- Selecione --</option>
												<?php foreach ($sampleResult as $name) { ?>
													<option value="<?php echo $name['sample_id']; ?>" <?php echo ($eidInfo['specimen_type'] == $name['sample_id']) ? "selected='selected'" : ""; ?>><?php echo ($name['sample_name']); ?></option>
												<?php } ?>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">Nom du demandeur</th>
										<td>
											<input class="form-control" type="text" name="sampleRequestorName" id="sampleRequestorName" placeholder="Nom du demandeur" value="<?php echo $eidInfo['sample_requestor_name']; ?>" />
										</td>
									</tr>
									<tr>
										<th scope="row">Raison de la PCR (cocher une):</th>
										<td>
											<select class="form-control" name="pcrTestReason" id="pcrTestReason">
												<option value=''> -- Sélectionner -- </option>
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
										<th colspan=2><strong>Pour enfant de 9 mois ou plus</strong></th>
									</tr>
									<tr>
										<th scope="row">Test rapide effectué?</th>
										<td>
											<select class="form-control" name="rapidTestPerformed" id="rapidTestPerformed">
												<option value=''> -- Sélectionner -- </option>
												<option value="yes" <?php echo ($eidInfo['rapid_test_performed'] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
												<option value="no" <?php echo ($eidInfo['rapid_test_performed'] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">Si oui, date :</th>
										<td>
											<input class="form-control date" type="text" name="rapidtestDate" id="rapidtestDate" placeholder="Si oui, date" value="<?php echo DateUtils::humanReadableDateFormat($eidInfo['rapid_test_date']); ?>" />
										</td>
									</tr>
									<tr>
										<th scope="row">Résultat test rapide</th>
										<td>
											<select class="form-control" name="rapidTestResult" id="rapidTestResult">
												<option value=''> -- Sélectionner -- </option>
												<?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
													<option value="<?php echo $eidResultKey; ?>" <?php echo ($eidInfo['rapid_test_result'] == $eidResultKey) ? "selected='selected'" : ""; ?>> <?php echo $eidResultValue; ?> </option>
												<?php } ?>
											</select>
										</td>
									</tr>
								</table>


							</div>
						</div>
						<?php if ($_SESSION['instanceType'] != 'remoteuser') { ?>
							<div class="box box-primary">
								<div class="box-body">
									<div class="box-header with-border">
										<h3 class="box-title">B. Réservé au laboratoire d’analyse </h3>
									</div>
									<table class="table" aria-hidden="true"  style="width:100%">
										<tr>
											<th scope="row"><label for="">Date de réception de l'échantillon </label></th>
											<td>
												<input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter date de réception de léchantillon" <?php echo $labFieldDisabled; ?> value="<?php echo DateUtils::humanReadableDateFormat($eidInfo['sample_received_at_vl_lab_datetime']) ?>" style="width:100%;" />
											</td>
											<td><label for="labId">Nom du Laboratoire </label> </td>
											<td>
												<select name="labId" id="labId" class="form-control" title="Nom du Laboratoire" style="width:100%;">
													<?= $general->generateSelectOptions($testingLabs, $eidInfo['lab_id'], '-- Sélectionner --'); ?>
												</select>
											</td>
										<tr>
											<th scope="row">Is Sample Rejected?</th>
											<td>
												<select class="form-control" name="isSampleRejected" id="isSampleRejected">
													<option value=''> -- Sélectionner -- </option>
													<option value="yes" <?php echo ($eidInfo['is_sample_rejected'] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
													<option value="no" <?php echo ($eidInfo['is_sample_rejected'] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
												</select>
											</td>

											<th class="rejected" style="display: none;">Raison du rejet</th>
											<td class="rejected" style="display: none;">

												<select name="sampleRejectionReason" id="sampleRejectionReason" class="form-control labSection" title="Veuillez choisir la raison du rejet" <?php echo $labFieldDisabled; ?> <option value="">-- Sélectionner --</option>
													<option value="">-- Sélectionner --</option>
													<?php foreach ($rejectionTypeResult as $type) { ?>
														<optgroup label="<?php echo ($type['rejection_type']); ?>">
															<?php
															foreach ($rejectionResult as $reject) {
																if ($type['rejection_type'] == $reject['rejection_type']) { ?>
																	<option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($eidInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ($reject['rejection_reason_name']); ?></option>
															<?php }
															} ?>
														</optgroup>
													<?php }  ?>
												</select>
											</td>
										</tr>
										<tr class="rejected" style="display:none;">
											<th scope="row">Date de rejet<span class="mandatory">*</span></th>
											<td><input value="<?php echo DateUtils::humanReadableDateFormat($eidInfo['rejection_on']); ?>" class="form-control date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Date de rejet" title="Veuillez choisir la date rejetée" /></td>
											<td></td>
											<td></td>
										</tr>
										<tr>
											<td style="width:25%;"><label for="">Test effectué le </label></td>
											<td style="width:25%;">
												<input type="text" class="form-control dateTime" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _("Please enter date"); ?>" title="Test effectué le" <?php echo $labFieldDisabled; ?> value="<?php echo DateUtils::humanReadableDateFormat($eidInfo['sample_tested_datetime']) ?>" style="width:100%;" />
											</td>


											<th scope="row">Résultat</th>
											<td>
												<select class="form-control result-focus" name="result" id="result">
													<option value=''> -- Sélectionner -- </option>
													<?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
														<option value="<?php echo $eidResultKey; ?>" <?php echo ($eidInfo['result'] == $eidResultKey) ? "selected='selected'" : ""; ?>> <?php echo $eidResultValue; ?> </option>
													<?php } ?>

												</select>
											</td>
										</tr>
										<tr>
											<th scope="row">Revu le</td>
											<td><input type="text" value="<?php echo $eidInfo['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control" placeholder="Revu le" title="Please enter the Revu le" /></td>
											<th scope="row">Revu par</th>
											<td>
												<select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose Revu par" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, $eidInfo['result_reviewed_by'], '-- Select --'); ?>
												</select>
											</td>
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
											<th class="change-reason" style="display: none;">Raison du changement <span class="mandatory">*</span></td>
											<td class="change-reason" style="display: none;"><textarea type="text" name="reasonForChanging" id="reasonForChanging" class="form-control date" placeholder="Entrez la raison du changement" title="Veuillez saisir la raison du changement"></textarea></td>
											<th scope="row"></th>
											<td></td>
										</tr>
									</table>
								</div>
							</div>
						<?php } ?>

					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="revised" id="revised" value="no" />
						<input type="hidden" name="formId" id="formId" value="3" />
						<input type="hidden" name="eidSampleId" id="eidSampleId" value="<?php echo $eidInfo['eid_id']; ?>" />
						<input type="hidden" name="sampleCodeCol" id="sampleCodeCol" value="<?php echo $eidInfo['sample_code']; ?>" />
						<input type="hidden" name="oldStatus" id="oldStatus" value="<?php echo $eidInfo['result_status']; ?>" />
						<input type="hidden" name="provinceCode" id="provinceCode" />
						<input type="hidden" name="provinceId" id="provinceId" />

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

	function setPatientDetails(pDetails) {
		var patientArray = JSON.parse(pDetails);
		//   console.log(patientArray);
		$("#childId").val(patientArray['child_id']);
		$("#childName").val(patientArray['child_name']);
		$("#childDob").val(patientArray['dob']);
		$("#childGender").val(patientArray['gender']);
		$("#childAge").val(patientArray['age']);
		$("#mothersId").val(patientArray['mother_id']);
		$("#mothersName").val(patientArray['mother_name']);
		$("#mothersDob").val(patientArray['mother_dob']);
		$("#mothersMaritalStatus").val(patientArray['mother_marital_status']);
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
		var motherVl = $("#motherViralLoadCopiesPerMl").val();
		var motherVlText = $("#motherViralLoadText").val();
		if (motherVlText != '') {
			$("#motherViralLoadCopiesPerMl").val('');
		}
	}



	$(document).ready(function() {


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
		getfacilityProvinceDetails($("#facilityId").val());
		<?php
		if (isset($eidInfo['mother_treatment']) && in_array('Other', $eidInfo['mother_treatment'])) {
		?>
			$('#motherTreatmentOther').prop('disabled', false);
		<?php
		}
		?>

		<?php
		if (isset($eidInfo['mother_vl_result']) && !empty($eidInfo['mother_vl_result'])) {
		?>
			updateMotherViralLoad();
		<?php
		} ?>

		$("#motherViralLoadCopiesPerMl").on("change keyup paste", function() {
			var motherVl = $("#motherViralLoadCopiesPerMl").val();
			//var motherVlText = $("#motherViralLoadText").val();
			if (motherVl != '') {
				$("#motherViralLoadText").val('');
			}
		});

	});
</script>