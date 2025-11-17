<?php
// imported in eid-add-request.php based on country in global config

use App\Services\EidService;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();


$sampleCode = $general->isSTSInstance() ? 'remote_sample_code' : 'sample_code';

$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);

$facility = $general->generateSelectOptions($healthFacilities, null, _translate("-- Select --"));


?>

<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em>
			<?php echo _translate("EARLY INFANT DIAGNOSIS (EID) LABORATORY REQUEST FORM"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("Add EID Request"); ?>
			</li>
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
									<h3 class="box-title">A. Réservé à la structure de soins</h3>
								</div>
								<div class="box-header with-border">
									<h3 class="box-title">Information sur la structure de soins</h3>
								</div>
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr>
										<?php if ($general->isSTSInstance()) { ?>
											<td style=" width: 20%; "><label for="sampleCode">Échantillon ID </label></td>
											<td>
												<span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
												<input type="hidden" id="sampleCode" name="sampleCode" />
											</td>
										<?php } else { ?>
											<td style=" width: 20%; "><label for="sampleCode">Échantillon ID </label></td>
											<td>
												<input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="<?= _translate("Sample ID"); ?>" title="<?= _translate("Please enter the Sample ID", true); ?>" style="width:100%;" readonly="readonly" />
											</td>
										<?php } ?>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td><label for="province">Province <span class="mandatory">*</span> </label></td>
										<td>
											<select class="form-control isRequired" name="province" id="province" title="Please choose province" onchange="getfacilityDetails(this);" style="width:100%;">
												<?php echo $province; ?>
											</select>
										</td>
										<td><label for="district">Zone de Santé <span class="mandatory">*</span> </label>
										</td>
										<td>
											<select class="form-control isRequired" name="district" id="district" title="Please choose district" style="width:100%;" onchange="getfacilityDistrictwise(this);">
												<option value=""><?= _translate("-- Select --"); ?> </option>
											</select>
										</td>
									</tr>
									<tr>
										<td><label for="facilityId">POINT DE COLLECT<span class="mandatory">*</span> </label></td>
										<td>
											<select class="form-control isRequired " name="facilityId" id="facilityId" title="<?= _translate("Please choose facility"); ?>" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
												<?php echo $facility; ?>
											</select>
										</td>
										<th scope="row"><label for="email">Adresse Email</label></th>
										<td>
											<input type="email" class="form-control isEmail" id="email" name="email" placeholder="Adresse Email" title="Please enter Adresse Email" style="width:100%;" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="testRequestDate">Date de la demande</label></th>
										<td>
											<input type="text" class="form-control date" id="testRequestDate" name="testRequestDate" placeholder="Date de la demande" title="Please enter Date de la demande" style="width:100%;" />
										</td>
										<td><label for="supportPartner">Partnaire d'appui <span class="mandatory">*</span></label></td>
										<td>
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
										<td><label for="fundingSource">Source de Financement<span class="mandatory">*</span></label></td>
										<td>
											<select class="form-control select2 isREquired" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
												<option value=""><?= _translate("-- Select --"); ?> </option>
												<?php
												foreach ($fundingSourceList as $fundingSource) {
												?>
													<option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>"><?= $fundingSource['funding_source_name']; ?></option>
												<?php } ?>
											</select>
										</td>
										<td><label for="clinicianName">Demandeur<span class="mandatory">*</span> </label></td>
										<td>
											<input type="text" class="form-control isRequired" id="clinicianName" name="clinicianName" placeholder="Demandeur" title="<?= _translate("Please enter requesting clinician name"); ?>" style="width:100%;" />
										</td>
									</tr>
									<tr>
										<td><label for="reqClinicianPhoneNumber">Demander le numéro de téléphone du clinicien <span class="mandatory">*</span></label></td>
										<td>
											<input type="text" class="form-control phone-number isRequired" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" placeholder="Téléphone" title="Veuillez entrer le téléphone" value="" style="width:100%;" />
										</td>
										<td><label for="labId">Nom du Laboratoire <span class="mandatory">*</span></label> </td>
										<td>
											<select name="labId" id="labId" class="form-control isRequired" title="Nom du Laboratoire" style="width:100%;">
												<?= $general->generateSelectOptions($testingLabs, null, '<?= _translate("-- Select --"); ?>'); ?>
											</select>
										</td>
									</tr>
								</table>
								<br><br>

								<div class="box-header with-border">
									<h4>1. Données démographiques mère / enfant </h4><br>
									<h4 class="box-title">Information sur le patient </h4>&nbsp;&nbsp;&nbsp;
									<input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" placeholder="Code du patient" title="<?= _translate("Please enter the Patient ID"); ?>" />&nbsp;&nbsp;
									<a style="margin-top:-0.35%;font-weight:500;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList($('#artPatientNo').val(),0);"><em class="fa-solid fa-magnifying-glass"></em>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No
											Patient Found</strong></span>
								</div>
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr>
										<th scope="row">
											<h5 style="font-weight:bold;font-size:1.1em;">ID de la mère </h5>
										</th>
									</tr>
									<tr>
										<th scope="row" style="width:15%;"><label for="mothersId">Code (si applicable) <span class="mandatory">*</span></label></th>
										<td style="width:35%;">
											<input type="text" class="form-control isRequired" id="mothersId" name="mothersId" placeholder="Code du mère" title="Please enter code du mère" style="width:100%;" onchange="" />
										</td>
										<th scope="row" style="width:15%;"><label for="mothersName">Nom <span class="mandatory">*</span></label></th>
										<td style="width:35%;">
											<input type="text" class="form-control isRequired" id="mothersName" name="mothersName" placeholder="Nom du mère" title="Please enter nom du mère" style="width:100%;" onchange="" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="mothersDob">Date de naissance <span class="mandatory">*</span></label></th>
										<td>
											<input type="text" class="form-control date isRequired" id="mothersDob" name="mothersDob" placeholder="Date de naissance" title="Please enter Date de naissance" style="width:100%;" onchange="" />
										</td>
										<th scope="row"><label for="mothersMaritalStatus">Etat civil <span class="mandatory">*</span></label></th>
										<td>
											<select class="form-control isRequired" name="mothersMaritalStatus" id="mothersMaritalStatus">
												<option value=''> <?= _translate("-- Select --"); ?> </option>
												<option value='single'> Single </option>
												<option value='married'> Married </option>
												<option value='cohabitating'> Cohabitating </option>
												<option value='widow'> Widow </option>
												<option value='unknown'> Unknown </option>
											</select>
										</td>
									</tr>

									<tr>
										<th scope="row">
											<h5 style="font-weight:bold;font-size:1.1em;">ID de l'enfant</h5>
										</th>
									</tr>
									<tr>
										<th scope="row"><label for="childId">Code de l’enfant (Patient) <span class="mandatory">*</span></label></th>
										<td>
											<input type="text" class="form-control isRequired patientId" id="childId" name="childId" placeholder="Code (Patient)" title="Please enter Code de l’enfant " style="width:100%;" onchange="showPatientList();" />
										</td>
										<th scope="row"><label for="childName">Nom<span class="mandatory">*</span> </label></th>
										<td>
											<input type="text" class="form-control isRequired" id="childName" name="childName" placeholder="Nom" title="Please enter nom" style="width:100%;" onchange="" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="phone">N° Téléphone</label></th>
										<td>
											<input type="text" class="form-control isMobile" id="phone" name="phone" placeholder="N° Téléphone" title="Please enter N° Téléphone" style="width:100%;" />
										</td>
										<th scope="row"><label for="childDob">Date de naissance <span class="mandatory">*</span></label></th>
										<td>
											<input type="text" class="form-control date isRequired" id="childDob" name="childDob" placeholder="Date de naissance" title="Please enter Date de naissance" style="width:100%;" onchange="calculateAgeInMonths();" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="childGender"><?= _translate("Sex"); ?> <span class="mandatory">*</span></label></th>
										<td>
											<select class="form-control isRequired" name="childGender" id="childGender">
												<option value=''> <?= _translate("-- Select --"); ?> </option>
												<option value='male'> <?= _translate("Male"); ?> </option>
												<option value='female'> <?= _translate("Female"); ?> </option>
												<option value='unreported'> <?= _translate("Unreported"); ?> </option>
											</select>
										</td>
										<th scope="row">Age en Jour</th>
										<td><input type="number" class="form-control " id="childAgeInDays" name="childAgeInDays" placeholder="Age en Jour" title="Age en Jour" style="width:100%;" /></td>
									</tr>
									<tr>
										<th scope="row">Age en mois<span class="mandatory">*</span></th>
										<td><input type="number" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control isRequired" id="childAge" name="childAge" placeholder="<?php echo _translate("Age in years"); ?>" title="<?php echo _translate("Age in years"); ?>" style="width:100%;" onchange="$('#childDob').val('')" /></td>
										<th scope="row">Age en semaines</th>
										<td><input type="number" maxlength="5" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="childAgeInWeeks" name="childAgeInWeeks" placeholder="<?php echo _translate("Age in weeks"); ?>" title="<?php echo _translate("Age in weeks"); ?>" style="width:100%;" /></td>
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
										<th scope="row" colspan=2>ARV donnés à la maman pendant la grossesse:<span class="mandatory">*</span></th>
										<td colspan=4>
											<input type="checkbox" class="isRequired" name="motherTreatment[]" value="Nothing" /> Rien
											&nbsp;&nbsp;&nbsp;&nbsp;
											<input type="checkbox" class="isRequired" name="motherTreatment[]" value="ARV Initiated during Pregnancy" /> ARV débutés durant la
											grossesse&nbsp;&nbsp;&nbsp;&nbsp;
											<input type="checkbox" class="isRequired" name="motherTreatment[]" value="ARV Initiated prior to Pregnancy" /> ARV débutés avant la
											grossesse &nbsp;&nbsp;&nbsp;&nbsp;
											<input type="checkbox" class="isRequired" name="motherTreatment[]" value="ARV at Child Birth" /> ARV à l’accouchement
											&nbsp;&nbsp;&nbsp;&nbsp;
											<input type="checkbox" class="isRequired" name="motherTreatment[]" value="Option B plus" />
											Option B plus <br><br>
											<input type="checkbox" class="isRequired" name="motherTreatment[]" value="AZT/3TC/NVP" />
											AZT/3TC/NVP &nbsp;&nbsp;&nbsp;&nbsp;
											<input type="checkbox" class="isRequired" name="motherTreatment[]" value="TDF/3TC/EFV" />
											TDF/3TC/EFV &nbsp;&nbsp;&nbsp;&nbsp;
											<input type="checkbox" class="isRequired" name="motherTreatment[]" value="Other" onclick="$('#motherTreatmentOther').prop('disabled', function(i, v) { return !v; });" />
											Autres (à préciser): <input class="form-control" style="max-width:180px;display:inline;" disabled="disabled" placeholder="Autres" type="text" name="motherTreatmentOther" id="motherTreatmentOther" /> &nbsp;&nbsp;&nbsp;&nbsp;
											<input type="checkbox" class="isRequired" name="motherTreatment[]" value="Unknown" /> Inconnu
										</td>
									</tr>
									<tr>
										<th scope="row" style="vertical-align:middle;">CD4</th>
										<td style="vertical-align:middle;">
											<div class="input-group">
												<input type="text" class="form-control" id="mothercd4" name="mothercd4" placeholder="CD4" title="CD4" style="width:100%;" onchange="" />
												<div class="input-group-addon">/mm3</div>
											</div>
										</td>
										<th scope="row" style="vertical-align:middle;">Viral Load</th>
										<td style="vertical-align:middle;">
											<div class="input-group">
												<input type="number" class="form-control" id="motherViralLoadCopiesPerMl" name="motherViralLoadCopiesPerMl" placeholder="Viral Load in copies/mL" title="Viral Load" style="width:100%;" onchange="" />
												<div class="input-group-addon">copies/mL</div>
											</div>
										</td>
										<td style="vertical-align:middle;">- OR -</td>
										<td style="vertical-align:middle;">
											<select class="form-control" name="motherViralLoadText" id="motherViralLoadText" onchange="updateMotherViralLoad()">
												<option value=''> <?= _translate("-- Select --"); ?> </option>
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
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr>
										<th scope="row" colspan=2>
											<h4>3. Mangement de l’enfant</h4>
										</th>
									</tr>
									<tr>
										<th scope="row">Bébé a reçu:<br>(Cocher tout ce qui est reçu, Rien, ou inconnu) <span class="mandatory">*</span>
										</th>
										<td>
											<input type="checkbox" class="isRequired" name="childTreatment[]" value="Nothing" />&nbsp;Rien
											&nbsp; &nbsp;&nbsp;&nbsp;
											<input type="checkbox" class="isRequired" name="childTreatment[]" value="AZT" />&nbsp;AZT
											&nbsp; &nbsp;&nbsp;&nbsp;
											<input type="checkbox" class="isRequired" name="childTreatment[]" value="NVP" />&nbsp;NVP
											&nbsp; &nbsp;&nbsp;&nbsp;
											<input type="checkbox" class="isRequired" name="childTreatment[]" value="Unknown" />&nbsp;Inconnu &nbsp; &nbsp;&nbsp;&nbsp;
										</td>

										<th scope="row"><label for="isInfantReceivingTratment">Bébé reçoit-il le traitement?</label></th>
										<td>
											<select class="form-control" id="isInfantReceivingTratment" name="isInfantReceivingTratment" title="Please select bébé reçoit-il le traitement" style="width:100%;" onchange="[...document.getElementsByClassName('specific-infant-treatment')].forEach(el => el.style.display = this.value === 'Oui' ? '' : 'none');">
												<option value=""> -- Sélectionner -- </option>
												<option value="Oui">Oui</option>
												<option value="Non">Non</option>
												<option value="Inconnu">Inconnu</option>
											</select>
										</td>
									</tr>
									<tr>
										<th class="specific-infant-treatment" style="display: none;" scope="row"><label for="specificInfantTreatment">Si Oui à préciser</label></th>
										<td class="specific-infant-treatment" style="display: none;">
											<select class="form-control" name="specificInfantTreatment" id="specificInfantTreatment" title="Please select the si oui à préciser" onchange="document.getElementById('specificInfantTreatmentOther').style.display = this.value === 'Autres' ? '' : 'none';">
												<option value="">-- Raison de la PCR (cocher une) --</option>
												<option value="1st test pour bébé exposé (4 à 6 semaines)">1st test pour bébé exposé (4 à 6 semaines)</option>
												<option value="1st test pour bébé exposé (plus de 6 semaines)">1st test pour bébé exposé (plus de 6 semaines)</option>
												<option value="Test à 9 mois">Test à 9 mois</option>
												<option value="Test à plus de 9 mois">Test à plus de 9 mois</option>
												<option value="1st test pour bébé malade">1st test pour bébé malade</option>
												<option value="Répéter car problème avec 1er test">Répéter car problème avec 1er test</option>
												<option value="Répéter pour confirmer 1er résultat">Répéter pour confirmer 1er résultat</option>
												<option value="Répéter test après arrêt allaitement">Répéter test après arrêt allaitement</option>
												<option value="maternel (6 semaines au moins après arrêt allaitement)">maternel (6 semaines au moins après arrêt allaitement)</option>
												<option value="Autres">Autres (à préciser)</option>
											</select>
											<input type="text" placeholder="Veuillez préciser si autre" title="Veuillez préciser si autre" id="specificInfantTreatmentOther" name="specificInfantTreatmentOther" class="form-control" style="display: none;" />
										</td>
										<th scope="row">Bébé a arrêté allaitement maternel ? <span class="mandatory">*</span></th>
										<td>
											<select class="form-control isRequired" name="hasInfantStoppedBreastfeeding" id="hasInfantStoppedBreastfeeding">
												<option value=''> <?= _translate("-- Select --"); ?> </option>
												<option value="yes"> Oui </option>
												<option value="no"> Non </option>
												<option value="unknown"> Inconnu </option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="ageBreastfeedingStopped">Age (mois) arrêt allaitement : <span class="mandatory" style="display:none;">*</span></label></th>
										<td>
											<input type="number" class="form-control" style="max-width:200px;display:inline;" placeholder="Age (mois) arrêt allaitement" type="text" name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" />
										</td>

										<!-- <tr>
							  <th scope="row">Bébé encore allaité?</th>
							  <td>
								  <select class="form-control" name="isInfantStillBeingBreastfed" id="isInfantStillBeingBreastfed">
									<option value=''> <?= _translate("-- Select --"); ?> </option>
									<option value="yes"> Oui </option>
									<option value="no" /> Non </option>
									<option value="unknown" /> Inconnu </option>
								  </select>
							  </td>
							</tr> -->

										<th scope="row"><label for="choiceOfFeeding">Choix d’allaitement de bébé : <span class="mandatory" style="display:none;">*</span></label></th>
										<td>
											<select class="form-control" name="choiceOfFeeding" id="choiceOfFeeding">
												<option value=''> <?= _translate("-- Select --"); ?> </option>
												<option value="Breastfeeding only"> Allaitement seul </option>
												<option value="Milk substitute"> Substitut de lait </option>
												<option value="Combination"> Mixte </option>
												<option value="Other"> Autre </option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row">Cotrimoxazole donné au bébé? <span class="mandatory">*</span></th>
										<td>
											<select class="form-control isRequired" name="isCotrimoxazoleBeingAdministered" id="choiceOfFeeding">
												<option value=''> <?= _translate("-- Select --"); ?> </option>
												<option value="no"> Non </option>
												<option value="Yes, takes CTX everyday"> Oui, prend CTX chaque jour
												</option>
												<option value="Starting on CTX today"> Commence CTX aujourd’hui
												</option>
											</select>

										</td>
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
										<th scope="row" style="width:15%;">Date de collecte <span class="mandatory">*</span> </th>
										<td style="width:35%;">
											<input class="form-control dateTime isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Date de collecte" onchange="generateSampleCode(); checkCollectionDate(this.value);" />
											<span class="expiredCollectionDate" style="color:red; display:none;"></span>
										</td>

										<th scope="row" style="width:15%;">Tel. du préleveur <span class="mandatory">*</span></th>
										<td style="width:35%;">
											<input class="form-control isRequired" type="text" name="sampleRequestorPhone" id="sampleRequestorPhone" placeholder="Tel. du préleveur" />
										</td>
									</tr>
									<tr>
										<th scope="row"> Type d'échantillon <span class="mandatory">*</span></th>
										<td>
											<select name="specimenType" id="specimenType" class="form-control isRequired" title="Veuillez choisir le type d'échantillon" style="width:100%">
												<option value=""><?= _translate("-- Select --"); ?></option>
												<?php foreach ($sampleResult as $name) { ?>
													<option value="<?php echo $name['sample_id']; ?>"><?= $name['sample_name']; ?></option>
												<?php } ?>
											</select>
										</td>

										<th scope="row">Nom du demandeur <span class="mandatory">*</span></th>
										<td>
											<input class="form-control isRequired" type="text" name="sampleRequestorName" id="sampleRequestorName" placeholder="Nom du demandeur" />
										</td>
									</tr>
									<tr>
										<th scope="row">Raison de la PCR (cocher une): <span class="mandatory">*</span></th>
										<td>
											<select class="form-control isRequired" name="pcrTestReason" id="pcrTestReason">
												<option value=''> <?= _translate("-- Select --"); ?> </option>
												<option value="Nothing"> Rien</option>
												<option value="First Test for exposed baby"> 1st test pour bébé exposé
												</option>
												<option value="First test for sick baby"> 1st test pour bébé malade
												</option>
												<option value="Repeat due to problem with first test"> Répéter car
													problème avec 1er test</option>
												<option value="Repeat to confirm the first result"> Répéter pour
													confirmer 1er résultat</option>
												<option value="Repeat test once breastfeeding is stopped"> Répéter test
													après arrêt allaitement maternel (6 semaines au moins après arrêt
													allaitement)</option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row" colspan=2><strong>Pour enfant de 9 mois ou plus</strong></th>
									</tr>
									<tr>
										<th scope="row">Test rapide effectué?</th>
										<td>
											<select class="form-control" name="rapidTestPerformed" id="rapidTestPerformed">
												<option value=''> <?= _translate("-- Select --"); ?> </option>
												<option value="yes"> Oui </option>
												<option value="no"> Non </option>
											</select>
										</td>

										<th scope="row">Si oui, date : </th>
										<td>
											<input class="form-control date" type="text" name="rapidtestDate" id="rapidtestDate" placeholder="Si oui, date" />
										</td>
									</tr>
									<tr>
										<th scope="row">Résultat test rapide </th>
										<td>
											<select class="form-control" name="rapidTestResult" id="rapidTestResult">
												<option value=''> <?= _translate("-- Select --"); ?> </option>
												<?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
													<option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
												<?php } ?>

											</select>
										</td>
									</tr>
								</table>


							</div>
						</div>
						<?php if (!$general->isSTSInstance()) { ?>
							<div class="box box-primary">
								<div class="box-body">
									<div class="box-header with-border">
										<h3 class="box-title">B. Réservé au laboratoire d’analyse </h3>
									</div>
									<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
										<tr>
											<td style="width: 25%;"><label for="testingPlatform">Technique utilisée </label></td>
											<td style="width: 25%;">
												<select name="eidPlatform" id="eidPlatform" class="form-control" title="Please choose VL Testing Platform" style="width:100%;">
													<?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>
												</select>
											</td>
											<th scope="row" style="width:15%;"><label for="">Date de réception de l'échantillon </label></th>
											<td style="width:35%;">
												<input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de réception de léchantillon" style="width:100%;" />
											</td>
										<tr>
											<td style="width: 25%;"><label for=""><?php echo _translate('Freezer'); ?> <em class="fas fa-edit"></em> :</label></td>
											<td style="width: 25%;">
												<select class="form-control select2 editableSelect" id="freezer" name="freezer" placeholder="<?php echo _translate('Enter Freezer'); ?>" title="<?php echo _translate('Please enter Freezer'); ?>">
												</select>
											</td>
											<td style="width: 25%;"><label for="rack"><?php echo _translate('Rack'); ?> : </label> </td>
											<td style="width: 25%;">
												<input type="text" class="form-control" id="rack" name="rack" placeholder="<?php echo _translate('Rack'); ?>" title="<?php echo _translate('Please enter rack'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
										</tr>
										<tr>
											<td style="width: 25%;"><label for=""><?php echo _translate('Box'); ?> :
												</label></td>
											<td style="width: 25%;">
												<input type="text" class="form-control" id="box" name="box" placeholder="<?php echo _translate('Box'); ?>" title="<?php echo _translate('Please enter box'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
											<td style="width: 25%;"><label for="position"><?php echo _translate('Position'); ?> : </label> </td>
											<td style="width: 25%;">
												<input type="text" class="form-control" id="position" name="position" placeholder="<?php echo _translate('Position'); ?>" title="<?php echo _translate('Please enter position'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
										</tr>
										<tr>
											<td style="width: 25%;"><label for=""><?php echo _translate('Volume (ml)'); ?> :
												</label></td>
											<td style="width: 25%;">
												<input type="text" class="form-control" id="volume" name="volume" placeholder="<?php echo _translate('Volume'); ?>" title="<?php echo _translate('Please enter volume'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" />
											</td>
											<th scope="row"><?php echo _translate('Is Sample Rejected?'); ?></th>
											<td>
												<select class="form-control" name="isSampleRejected" id="isSampleRejected">
													<option value=''> <?= _translate("-- Select --"); ?> </option>
													<option value="yes"> Oui </option>
													<option value="no"> Non </option>
												</select>
											</td>
										</tr>
										<tr class="rejected" style="display: none;">
											<th scope="row">Raison du rejet</th>
											<td>
												<select name="sampleRejectionReason" id="sampleRejectionReason" class="form-control labSection" title="Please choose a Rejection Reason">
													<option value=""><?= _translate("-- Select --"); ?> </option>
													<?php foreach ($rejectionTypeResult as $type) { ?>
														<optgroup label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
															<?php
															foreach ($rejectionResult as $reject) {
																if ($type['rejection_type'] == $reject['rejection_type']) { ?>
																	<option value="<?php echo $reject['rejection_reason_id']; ?>"><?= $reject['rejection_reason_name']; ?></option>
															<?php }
															} ?>
														</optgroup>
													<?php } ?>
												</select>
											</td>
											<th scope="row">Date de rejet<span class="mandatory">*</span></th>
											<td><input value="" class="form-control date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Date de rejet" title="Veuillez choisir la date rejetée" /></td>
										</tr>
										<tr>
											<th scope="row"><label for="">Test effectué le </label></th>
											<td>
												<input type="text" class="form-control dateTime" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _translate("Please enter date"); ?>" title="Test effectué le" onchange="" style="width:100%;" />
											</td>
											<th scope="row">Résultat</th>
											<td>
												<select class="form-control" name="result" id="result">
													<option value=''> <?= _translate("-- Select --"); ?> </option>
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
												<input type="text" name="approvedOnDateTime" id="approvedOnDateTime" class="dateTime disabled-field form-control" placeholder="Approuvé le" title="Please enter the Approuvé le" />
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
							<input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="" />
							<input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="" />
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
							//$("#clinicianName").val(details[2]);
						}
					});
			}
			generateSampleCode();
		} else if (pName == '') {
			provinceName = true;
			facilityName = true;
			$("#province").html("<?php echo $province; ?>");
			$("#facilityId").html("<?php echo $facility; ?>");
			$("#facilityId").html("<?php echo $facility; ?>");
			$("#facilityId").select2("val", "");
			$("#district").html("<option value=''> <?= _translate("-- Select --"); ?> </option>");
		}
		$.unblockUI();
	}

	function setPatientDetails(pDetails) {
		var patientArray = JSON.parse(pDetails);
		//   //console.log(patientArray);
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
			$("#facilityId").html("<option value=''> <?= _translate("-- Select --"); ?> </option>");
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

		clearDatePlaceholderValues('input.date, input.dateTime');


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
			$("#motherViralLoadCopiesPerMl").removeClass('isRequired');
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

		storageEditableSelect('freezer', 'storage_code', 'storage_id', 'lab_storage', 'Freezer Code');

		$("#freezer").on('change', function() {
			storage = $("#freezer option:selected").text().split('-');
			$("#freezerCode").val($.trim(storage[0]));
		});

		$("#motherViralLoadCopiesPerMl").on("change keyup paste", function() {
			var motherVl = $("#motherViralLoadCopiesPerMl").val();
			//var motherVlText = $("#motherViralLoadText").val();
			if (motherVl != '') {
				$("#motherViralLoadText").val('');
				$("#motherViralLoadText").removeClass('isRequired');
			}
		});

	});

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

	$("#hasInfantStoppedBreastfeeding").change(function() {
		if ($(this).val() == 'yes') {
			addMandatoryField('ageBreastfeedingStopped');
			addMandatoryField('choiceOfFeeding');
		} else {
			removeMandatoryField('ageBreastfeedingStopped');
			removeMandatoryField('choiceOfFeeding');
		}
	});

	function addMandatoryField(fieldId) {
		$('label[for="' + fieldId + '"] .mandatory').show();
		$('#' + fieldId).addClass('isRequired');
	}

	function removeMandatoryField(fieldId) {
		$('label[for="' + fieldId + '"] .mandatory').hide();
		$('#' + fieldId).removeClass('isRequired');
	}
</script>