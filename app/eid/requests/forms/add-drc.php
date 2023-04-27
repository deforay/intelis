<?php
// imported in eid-add-request.php based on country in global config

use App\Services\EidService;
use App\Utilities\DateUtils;



//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);

//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);


// $configQuery = "SELECT * from global_config";
// $configResult = $db->query($configQuery);
// $arr = [];
// $prefix = $arr['sample_code_prefix'];

// Getting the list of Provinces, Districts and Facilities

$eidModel = new EidService();
$eidResults = $eidModel->getEidResults();


$rKey = '';
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {
	$sampleCodeKey = 'remote_sample_code_key';
	$sampleCode = 'remote_sample_code';
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
$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Sélectionner -- </option>";
foreach ($pdResult as $provinceName) {
	$province .= "<option data-code='" . $provinceName['geo_code'] . "' data-province-id='" . $provinceName['geo_id'] . "' data-name='" . $provinceName['geo_name'] . "' value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, null, '-- Sélectionner --');

?>

<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _("EARLY INFANT DIAGNOSIS (EID) LABORATORY REQUEST FORM"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Add EID Request"); ?></li>
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
				<form class="form-horizontal" method="post" name="addEIDRequestForm" id="addEIDRequestForm" autocomplete="off" action="eid-add-request-helper.php">
					<div class="box-body">
						<div class="box box-default">
							<div class="box-body">
								<div class="box-header with-border">
									<h3 class="box-title">A. Réservé à la structure de soins</h3>
								</div>
								<div class="box-header with-border">
									<h3 class="box-title">Information sur la structure de soins</h3>
								</div>
								<table class="table" aria-hidden="true"  style="width:100%">
									<tr>
										<?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
											<td><label for="sampleCode">Échantillon ID </label></td>
											<td>
												<span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
												<input type="hidden" id="sampleCode" name="sampleCode" />
											</td>
										<?php } else { ?>
											<td><label for="sampleCode">Échantillon ID </label></td>
											<td>
												<input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Échantillon ID" title="Please enter échantillon id" style="width:100%;" onchange="checkSampleNameValidation('form_eid','<?php echo $sampleCode; ?>',this.id,null,'The échantillon id that you entered already exists. Please try another échantillon id',null)" />
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
													<option value="<?php echo ($implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
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
													<option value="<?php echo ($fundingSource['funding_source_id']); ?>"><?= $fundingSource['funding_source_name']; ?></option>
												<?php } ?>
											</select>
										</td>
										<?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
											<!-- <tr> -->
											<td><label for="labId">Nom du Laboratoire <span class="mandatory">*</span></label> </td>
											<td>
												<select name="labId" id="labId" class="form-control isRequired" title="Nom du Laboratoire" style="width:100%;">
													<?= $general->generateSelectOptions($testingLabs, null, '-- Sélectionner --'); ?>
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
											<h5 style="font-weight:bold;font-size:1.1em;">ID de la mère </h5>
										</th>
									</tr>
									<tr>
										<th scope="row"><label for="mothersId">Code (si applicable) </label></th>
										<td>
											<input type="text" class="form-control " id="mothersId" name="mothersId" placeholder="Code du mère" title="Please enter code du mère" style="width:100%;" onchange="" />
										</td>
										<th scope="row"><label for="mothersName">Nom </label></th>
										<td>
											<input type="text" class="form-control " id="mothersName" name="mothersName" placeholder="Nom du mère" title="Please enter nom du mère" style="width:100%;" onchange="" />
										</td>
										<th scope="row"><label for="mothersDob">Date de naissance </label></th>
										<td>
											<input type="text" class="form-control date" id="mothersDob" name="mothersDob" placeholder="Date de naissance" title="Please enter Date de naissance" style="width:100%;" onchange="" />
										</td>
										<th scope="row"><label for="mothersMaritalStatus">Etat civil </label></th>
										<td>
											<select class="form-control " name="mothersMaritalStatus" id="mothersMaritalStatus">
												<option value=''> -- Sélectionner -- </option>
												<option value='single'> Single </option>
												<option value='married'> Married </option>
												<option value='cohabitating'> Cohabitating </option>
												<option value='widow'> Widow </option>
												<option value='unknown'> Unknown </option>
											</select>
										</td>
									</tr>

									<tr>
										<th colspan=8>
											<h5 style="font-weight:bold;font-size:1.1em;">ID de l'enfant</h5>
										</th>
									</tr>
									<tr>
										<th scope="row"><label for="childId">Code de l’enfant (Patient) <span class="mandatory">*</span></label></th>
										<td>
											<input type="text" class="form-control isRequired" id="childId" name="childId" placeholder="Code (Patient)" title="Please enter Code de l’enfant " style="width:100%;" onchange="showPatientList();" />
										</td>
										<th scope="row"><label for="childName">Nom </label></th>
										<td>
											<input type="text" class="form-control " id="childName" name="childName" placeholder="Nom" title="Please enter nom" style="width:100%;" onchange="" />
										</td>
										<th scope="row"><label for="childDob">Date de naissance </label></th>
										<td>
											<input type="text" class="form-control date" id="childDob" name="childDob" placeholder="Date de naissance" title="Please enter Date de naissance" style="width:100%;" onchange="calculateAgeInMonths();" />
										</td>
										<th scope="row"><label for="childGender">Gender </label></th>
										<td>
											<select class="form-control " name="childGender" id="childGender">
												<option value=''> -- Sélectionner -- </option>
												<option value='male'> Male </option>
												<option value='female'> Female </option>

											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">Age en mois</th>
										<td><input type="number" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="childAge" name="childAge" placeholder="Age en mois" title="Age en mois" style="width:100%;" onchange="$('#childDob').val('')" /></td>
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
											<input type="checkbox" name="motherTreatment[]" value="Nothing" /> Rien <br>
											<input type="checkbox" name="motherTreatment[]" value="ARV Initiated during Pregnancy" /> ARV débutés durant la grossesse <br>
											<input type="checkbox" name="motherTreatment[]" value="ARV Initiated prior to Pregnancy" /> ARV débutés avant la grossesse <br>
											<input type="checkbox" name="motherTreatment[]" value="ARV at Child Birth" /> ARV à l’accouchement <br>
											<input type="checkbox" name="motherTreatment[]" value="Option B plus" /> Option B plus <br>
											<input type="checkbox" name="motherTreatment[]" value="AZT/3TC/NVP" /> AZT/3TC/NVP <br>
											<input type="checkbox" name="motherTreatment[]" value="TDF/3TC/EFV" /> TDF/3TC/EFV <br>
											<input type="checkbox" name="motherTreatment[]" value="Other" onclick="$('#motherTreatmentOther').prop('disabled', function(i, v) { return !v; });" /> Autres (à préciser): <input class="form-control" style="max-width:200px;display:inline;" disabled="disabled" placeholder="Autres" type="text" name="motherTreatmentOther" id="motherTreatmentOther" /> <br>
											<input type="checkbox" name="motherTreatment[]" value="Unknown" /> Inconnu
										</td>
									</tr>
									<tr>
										<th style="vertical-align:middle;">CD4</th>
										<td style="vertical-align:middle;">
											<div class="input-group">
												<input type="text" class="form-control " id="mothercd4" name="mothercd4" placeholder="CD4" title="CD4" style="width:100%;" onchange="" />
												<div class="input-group-addon">/mm3</div>
											</div>
										</td>
										<th style="vertical-align:middle;">Viral Load</th>
										<td style="vertical-align:middle;">
											<div class="input-group">
												<input type="number" class="form-control " id="motherViralLoadCopiesPerMl" name="motherViralLoadCopiesPerMl" placeholder="Viral Load in copies/mL" title="Viral Load" style="width:100%;" onchange="" />
												<div class="input-group-addon">copies/mL</div>
											</div>
										</td>
										<td style="vertical-align:middle;">- OR -</td>
										<td style="vertical-align:middle;">
											<select class="form-control " name="motherViralLoadText" id="motherViralLoadText" onchange="updateMotherViralLoad()">
												<option value=''> -- Sélectionner -- </option>
												<option value='tnd'> Target Not Detected </option>
												<option value='bdl'> Below Detection Limit </option>
												<option value='< 20'>
													< 20 </option>
												<option value='< 40'>
													< 40 </option>
												<option value='invalid'> Invalid
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
											<input type="checkbox" name="childTreatment[]" value="Nothing" />&nbsp;Rien &nbsp; &nbsp;&nbsp;&nbsp;
											<input type="checkbox" name="childTreatment[]" value="AZT" />&nbsp;AZT &nbsp; &nbsp;&nbsp;&nbsp;
											<input type="checkbox" name="childTreatment[]" value="NVP" />&nbsp;NVP &nbsp; &nbsp;&nbsp;&nbsp;
											<input type="checkbox" name="childTreatment[]" value="Unknown" />&nbsp;Inconnu &nbsp; &nbsp;&nbsp;&nbsp;
										</td>
									</tr>
									<tr>
										<th scope="row">Bébé a arrêté allaitement maternel ?</th>
										<td>
											<select class="form-control" name="hasInfantStoppedBreastfeeding" id="hasInfantStoppedBreastfeeding">
												<option value=''> -- Sélectionner -- </option>
												<option value="yes"> Oui </option>
												<option value="no"> Non </option>
												<option value="unknown"> Inconnu </option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">Age (mois) arrêt allaitement :</th>
										<td colspan="4">
											<input type="number" class="form-control" style="max-width:200px;display:inline;" placeholder="Age (mois) arrêt allaitement" type="text" name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" />
										</td>
									</tr>
									<!-- <tr>
                              <th scope="row">Bébé encore allaité?</th>
                              <td>
                                  <select class="form-control" name="isInfantStillBeingBreastfed" id="isInfantStillBeingBreastfed">
                                    <option value=''> -- Sélectionner -- </option>
                                    <option value="yes"> Oui </option>
                                    <option value="no" /> Non </option>
                                    <option value="unknown" /> Inconnu </option>
                                  </select>
                              </td>
                            </tr> -->
									<tr>
										<th scope="row">Choix d’allaitement de bébé :</th>
										<td>
											<select class="form-control" name="choiceOfFeeding" id="choiceOfFeeding">
												<option value=''> -- Sélectionner -- </option>
												<option value="Breastfeeding only"> Allaitement seul </option>
												<option value="Milk substitute"> Substitut de lait </option>
												<option value="Combination"> Mixte </option>
												<option value="Other"> Autre </option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">Cotrimoxazole donné au bébé?</th>
										<td>
											<select class="form-control" name="isCotrimoxazoleBeingAdministered" id="choiceOfFeeding">
												<option value=''> -- Sélectionner -- </option>
												<option value="no"> Non </option>
												<option value="Yes, takes CTX everyday"> Oui, prend CTX chaque jour </option>
												<option value="Starting on CTX today"> Commence CTX aujourd’hui </option>
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
										<th scope="row">Date de collecte <span class="mandatory">*</span> </th>
										<td>
											<input class="form-control dateTime isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Date de collecte" onchange="sampleCodeGeneration();" />
										</td>
									</tr>
									<tr>
										<th scope="row">Tel. du préleveur</th>
										<td>
											<input class="form-control" type="text" name="sampleRequestorPhone" id="sampleRequestorPhone" placeholder="Tel. du préleveur" />
										</td>
									</tr>
									<tr>
										<th style="width:14%;"> Type d'échantillon</th>
										<td style="width:35%;">
											<select name="specimenType" id="specimenType" class="form-control" title="Veuillez choisir le type d'échantillon" style="width:100%">
												<option value="">-- Selecione --</option>
												<?php foreach ($sampleResult as $name) { ?>
													<option value="<?php echo $name['sample_id']; ?>"><?= $name['sample_name']; ?></option>
												<?php } ?>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">Nom du demandeur</th>
										<td>
											<input class="form-control" type="text" name="sampleRequestorName" id="sampleRequestorName" placeholder="Nom du demandeur" />
										</td>
									</tr>
									<tr>
										<th scope="row">Raison de la PCR (cocher une):</th>
										<td>
											<select class="form-control" name="pcrTestReason" id="pcrTestReason">
												<option value=''> -- Sélectionner -- </option>
												<option value="Nothing"> Rien</option>
												<option value="First Test for exposed baby"> 1st test pour bébé exposé</option>
												<option value="First test for sick baby"> 1st test pour bébé malade</option>
												<option value="Repeat due to problem with first test"> Répéter car problème avec 1er test</option>
												<option value="Repeat to confirm the first result"> Répéter pour confirmer 1er résultat</option>
												<option value="Repeat test once breastfeeding is stopped"> Répéter test après arrêt allaitement maternel (6 semaines au moins après arrêt allaitement)</option>
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
												<option value="yes"> Oui </option>
												<option value="no"> Non </option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">Si oui, date :</th>
										<td>
											<input class="form-control date" type="text" name="rapidtestDate" id="rapidtestDate" placeholder="Si oui, date" />
										</td>
									</tr>
									<tr>
										<th scope="row">Résultat test rapide</th>
										<td>
											<select class="form-control" name="rapidTestResult" id="rapidTestResult">
												<option value=''> -- Sélectionner -- </option>
												<?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
													<option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
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
												<input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter date de réception de léchantillon" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
											</td>
											<td><label for="labId">Nom du Laboratoire</label> </td>
											<td>
												<select name="labId" id="labId" class="form-control" title="Nom du Laboratoire" style="width:100%;">
													<?= $general->generateSelectOptions($testingLabs, null, '-- Sélectionner --'); ?>
												</select>
											</td>
										<tr>
											<th scope="row">Is Sample Rejected?</th>
											<td>
												<select class="form-control" name="isSampleRejected" id="isSampleRejected">
													<option value=''> -- Sélectionner -- </option>
													<option value="yes"> Oui </option>
													<option value="no"> Non </option>
												</select>
											</td>

											<th class="rejected" style="display: none;">Raison du rejet</th>
											<td class="rejected" style="display: none;">

												<select name="sampleRejectionReason" id="sampleRejectionReason" class="form-control labSection" title="Please choose a Rejection Reason" <?php echo $labFieldDisabled; ?>>
													<option value="">-- Sélectionner --</option>
													<?php foreach ($rejectionTypeResult as $type) { ?>
														<optgroup label="<?php echo ($type['rejection_type']); ?>">
															<?php
															foreach ($rejectionResult as $reject) {
																if ($type['rejection_type'] == $reject['rejection_type']) { ?>
																	<option value="<?php echo $reject['rejection_reason_id']; ?>"><?= $reject['rejection_reason_name']; ?></option>
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
												<input type="text" class="form-control dateTime" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _("Please enter date"); ?>" title="Test effectué le" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
											</td>


											<th scope="row">Résultat</th>
											<td>
												<select class="form-control" name="result" id="result">
													<option value=''> -- Sélectionner -- </option>
													<?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
														<option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
													<?php } ?>
												</select>
											</td>
										</tr>
										<tr>
											<th scope="row">Revu le</th>
											<td><input type="text" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control" placeholder="Revu le" title="Please enter the Revu le" /></td>
											<th scope="row">Revu par</th>
											<td>
												<select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose Revu par" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
												</select>
											</td>
										</tr>
										<tr>
											<th scope="row">Approuvé le</th>
											<td>
												<input type="text" name="approvedOn" id="approvedOn" class="dateTime disabled-field form-control" placeholder="Approuvé le" title="Please enter the Approuvé le" />
											</td>
											<th scope="row">Approuvé par</th>
											<td>
												<select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose Approuvé par" style="width: 100%;">
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
						<?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
							<input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
							<input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
						<?php } ?>
						<a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
						<input type="hidden" name="formId" id="formId" value="3" />
						<input type="hidden" name="eidSampleId" id="eidSampleId" value="" />
						<input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
						<input type="hidden" name="provinceId" id="provinceId" />
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
							$("#clinicianName").val(details[2]);
						}
					});
			}
			sampleCodeGeneration();
		} else if (pName == '') {
			provinceName = true;
			facilityName = true;
			$("#province").html("<?php echo $province; ?>");
			$("#facilityId").html("<?php echo $facility; ?>");
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

	function sampleCodeGeneration() {
		var pName = $("#province").val();
		var sDate = $("#sampleCollectionDate").val();
		if (pName != '' && sDate != '') {
			$.post("/eid/requests/generateSampleCode.php", {
					sDate: sDate,
					pName: pName
				},
				function(data) {
					var sCodeKey = JSON.parse(data);
					$("#sampleCode").val(sCodeKey.sampleCode);
					$("#sampleCodeInText").html(sCodeKey.sampleCodeInText);
					$("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
					$("#sampleCodeKey").val(sCodeKey.sampleCodeKey);
					$("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
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
		var provinceCode = ($("#province").find(":selected").attr("data-code") == null || $("#province").find(":selected").attr("data-code") == '') ? $("#province").find(":selected").attr("data-name") : $("#province").find(":selected").attr("data-code");
		$("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
		flag = deforayValidator.init({
			formId: 'addEIDRequestForm'
		});
		if (flag) {
			$('.btn-disabled').attr('disabled', 'yes');
			$(".btn-disabled").prop("onclick", null).off("click");
			$.blockUI();
			<?php if ($arr['eid_sample_code'] == 'auto' || $arr['eid_sample_code'] == 'YY' || $arr['eid_sample_code'] == 'MMYY') { ?>
				insertSampleCode('addEIDRequestForm', 'eidSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', $("#formId").val(), 'sampleCollectionDate', provinceCode);
			<?php } else { ?>
				document.getElementById('addEIDRequestForm').submit();
			<?php } ?>
		}
	}

	function updateMotherViralLoad() {
		//var motherVl = $("#motherViralLoadCopiesPerMl").val();
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

		$("#motherViralLoadCopiesPerMl").on("change keyup paste", function() {
			var motherVl = $("#motherViralLoadCopiesPerMl").val();
			//var motherVlText = $("#motherViralLoadText").val();
			if (motherVl != '') {
				$("#motherViralLoadText").val('');
			}
		});


	});
</script>