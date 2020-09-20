<?php
// imported in /eid/results/eid-update-result.php based on country in global config

ob_start();

//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);

//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);

// Getting the list of Provinces, Districts and Facilities

$rKey = '';
$pdQuery = "SELECT * from province_details";
if ($sarr['user_type'] == 'remoteuser') {
  $sampleCodeKey = 'remote_sample_code_key';
  $sampleCode = 'remote_sample_code';
  //check user exist in user_facility_map table
  $chkUserFcMapQry = "Select user_id from vl_user_facility_map where user_id='" . $_SESSION['userId'] . "'";
  $chkUserFcMapResult = $db->query($chkUserFcMapQry);
  if ($chkUserFcMapResult) {
    $pdQuery = "SELECT * from province_details as pd JOIN facility_details as fd ON fd.facility_state=pd.province_name JOIN vl_user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where user_id='" . $_SESSION['userId'] . "' group by province_name";
  }
  $rKey = 'R';
} else {
  $sampleCodeKey = 'sample_code_key';
  $sampleCode = 'sample_code';
  $rKey = '';
}
$pdResult = $db->query($pdQuery);
$province = "";
$province .= "<option value=''> -- Sélectionner -- </option>";
foreach ($pdResult as $provinceName) {
  $province .= "<option value='" . $provinceName['province_name'] . "##" . $provinceName['province_code'] . "'>" . ucwords($provinceName['province_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, $eidInfo['facility_id'], '-- Sélectionner --');

$eidInfo['mother_treatment'] = explode(",", $eidInfo['mother_treatment']);
$eidInfo['child_treatment'] = explode(",", $eidInfo['child_treatment']);

?>

<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1><i class="fa fa-edit"></i> EARLY INFANT DIAGNOSIS (EID) LABORATORY REQUEST FORM</h1>
    <ol class="breadcrumb">
      <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Edit EID Request</li>
    </ol>
  </section>
  <!-- Main content -->
  <section class="content">
    <!-- SELECT2 EXAMPLE -->
    <div class="box box-default">
      <div class="box-header with-border">
        <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
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
              <table class="table" style="width:100%">
                <tr>
                  <?php if ($sarr['user_type'] == 'remoteuser') { ?>
                    <td><label for="sampleCode">Échantillon ID </label></td>
                    <td>
                      <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?php echo $eidInfo['sample_code'] ?></span>

                    </td>
                  <?php } else { ?>
                    <td><label for="sampleCode">Échantillon ID </label><span class="mandatory">*</span></td>
                    <td>
                      <input type="text" readonly value="<?php echo $eidInfo['sample_code'] ?>" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Échantillon ID" title="Please enter échantillon id" style="width:100%;" onchange="checkSampleNameValidation('eid_form','<?php echo $sampleCode; ?>',this.id,null,'The échantillon id that you entered already exists. Please try another échantillon id',null)" />
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
                  <td><label for="facilityId">Nom de l'installation </label><span class="mandatory">*</span></td>
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
                        <option value="<?php echo ($implementingPartner['i_partner_id']); ?>" <?php echo ($eidInfo['implementing_partner'] == $implementingPartner['i_partner_id']) ? "selected='selected'" : ""; ?>><?php echo ucwords($implementingPartner['i_partner_name']); ?></option>
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
                        <option value="<?php echo ($fundingSource['funding_source_id']); ?>" <?php echo ($eidInfo['funding_source'] == $fundingSource['funding_source_id']) ? "selected='selected'" : ""; ?>><?php echo ucwords($fundingSource['funding_source_name']); ?></option>
                      <?php } ?>
                    </select>
                  </td>
                  <?php if ($sarr['user_type'] == 'remoteuser') { ?>
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
              <table class="table" style="width:100%">
                <tr>
                  <th colspan=8>
                    <h4>1. Données démographiques mère / enfant</h4>
                  </th>
                </tr>
                <tr>
                  <th colspan=8>
                    <h5 style="font-weight:bold;font-size:1.1em;">ID de la mère</h5>
                  </th>
                </tr>
                <tr>
                  <th><label for="mothersId">Code (si applicable) </label></th>
                  <td>
                    <input type="text" class="form-control " id="mothersId" name="mothersId" placeholder="Code du mère" title="Please enter code du mère" style="width:100%;" value="<?php echo $eidInfo['mother_id'] ?>" onchange="" />
                  </td>
                  <th><label for="mothersName">Nom </label></th>
                  <td>
                    <input type="text" class="form-control " id="mothersName" name="mothersName" placeholder="Nom du mère" title="Please enter nom du mère" style="width:100%;" value="<?php echo $eidInfo['mother_name'] ?>" onchange="" />
                  </td>
                  <th><label for="mothersDob">Date de naissance </label></th>
                  <td>
                    <input type="text" class="form-control date" id="mothersDob" name="mothersDob" placeholder="Date de naissance" title="Please enter Date de naissance" style="width:100%;" value="<?php echo $general->humanDateFormat($eidInfo['mother_dob']); ?>" onchange="" />
                  </td>
                  <th><label for="mothersMaritalStatus">Etat civil </label></th>
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
                    <h5 style="font-weight:bold;font-size:1.1em;">ID de l'enfant</h5>
                  </th>
                </tr>
                <tr>
                  <th><label for="childId">Code de l’enfant (Patient) </label></th>
                  <td>
                    <input type="text" class="form-control " id="childId" name="childId" placeholder="Code (Patient)" title="Please enter code du enfant" style="width:100%;" value="<?php echo $eidInfo['child_id']; ?>" onchange="" />
                  </td>
                  <th><label for="childName">Nom </label></th>
                  <td>
                    <input type="text" class="form-control " id="childName" name="childName" placeholder="Nom" title="Please enter nom du enfant" style="width:100%;" value="<?php echo $eidInfo['child_name']; ?>" onchange="" />
                  </td>
                  <th><label for="childDob">Date de naissance </label></th>
                  <td>
                    <input type="text" class="form-control date" id="childDob" name="childDob" placeholder="Date de naissance" title="Please enter Date de naissance" style="width:100%;" value="<?php echo $general->humanDateFormat($eidInfo['child_dob']) ?>" onchange="" />
                  </td>
                  <th><label for="childGender">Gender </label></th>
                  <td>
                    <select class="form-control " name="childGender" id="childGender">
                      <option value=''> -- Sélectionner -- </option>
                      <option value='male' <?php echo ($eidInfo['child_gender'] == 'male') ? "selected='selected'" : ""; ?>> Male </option>
                      <option value='female' <?php echo ($eidInfo['child_gender'] == 'female') ? "selected='selected'" : ""; ?>> Female </option>

                    </select>
                  </td>
                </tr>
                <tr>
                  <th>Age</th>
                  <td><input type="number" <?php echo $eidInfo['child_age']; ?> max=9 maxlength="1" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="childAge" name="childAge" placeholder="Age" title="Age" style="width:100%;" onchange="" /></td>
                  <th></th>
                  <td></td>
                  <th></th>
                  <td></td>
                  <th></th>
                  <td></td>
                  <th></th>
                  <td></td>
                </tr>

              </table>



              <br><br>
              <table class="table" style="width:100%">
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
                      <input type="text" class="form-control" id="mothercd4" name="mothercd4" placeholder="CD4" title="CD4" style="width:100%;" onchange="" value="<?php echo $eidInfo['mother_cd4']; ?>" />
                      <div class="input-group-addon">/mm3</div>
                    </div>
                  </td>
                  <th style="vertical-align:middle;">Viral Load</th>
                  <td style="vertical-align:middle;">
                    <div class="input-group">
                      <input type="number" class="form-control " id="motherViralLoadCopiesPerMl" name="motherViralLoadCopiesPerMl" placeholder="Viral Load in copies/mL" title="Mother's Viral Load" style="width:100%;" value="<?php echo $eidInfo['mother_vl_result']; ?>" onchange="" />
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
                        < 20 </option> <option value='< 40' <?php echo ($eidInfo['mother_vl_result'] == '< 40') ? "selected='selected'" : ""; ?>>
                          < 40 </option> <option value='invalid' <?php echo ($eidInfo['mother_vl_result'] == 'invalid') ? "selected='selected'" : ""; ?>> Invalid
                      </option>
                    </select>
                  </td>
                </tr>

              </table>






              <br><br>
              <table class="table" style="width:70%">
                <tr>
                  <th colspan=2>
                    <h4>3. Mangement de l’enfant</h4>
                  </th>
                </tr>
                <tr>
                  <th>Bébé a reçu:<br>(Cocher tout ce qui est reçu, Rien, ou inconnu)</th>
                  <td>
                    <input type="checkbox" name="childTreatment[]" value="Nothing" <?php echo in_array('Nothing', $eidInfo['child_treatment']) ? "checked='checked'" : ""; ?> />&nbsp;Rien &nbsp; &nbsp;&nbsp;&nbsp;
                    <input type="checkbox" name="childTreatment[]" value="AZT" <?php echo in_array('AZT', $eidInfo['child_treatment']) ? "checked='checked'" : ""; ?> />&nbsp;AZT &nbsp; &nbsp;&nbsp;&nbsp;
                    <input type="checkbox" name="childTreatment[]" value="NVP" <?php echo in_array('NVP', $eidInfo['child_treatment']) ? "checked='checked'" : ""; ?> />&nbsp;NVP &nbsp; &nbsp;&nbsp;&nbsp;
                    <input type="checkbox" name="childTreatment[]" value="Unknown" <?php echo in_array('Unknown', $eidInfo['child_treatment']) ? "checked='checked'" : ""; ?> />&nbsp;Inconnu &nbsp; &nbsp;&nbsp;&nbsp;
                  </td>
                </tr>
                <tr>
                  <th>Bébé a arrêté allaitement maternel ?</th>
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
                  <th>Age (mois) arrêt allaitement :</th>
                  <td colspan="4">
                    <input type="number" class="form-control" style="max-width:200px;display:inline;" placeholder="Age (mois) arrêt allaitement" type="text" name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" value="<?php echo $eidInfo['age_breastfeeding_stopped_in_months'] ?>" />
                  </td>
                </tr>
                <tr>
                  <th>Choix d’allaitement de bébé :</th>
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
                  <th>Cotrimoxazole donné au bébé?</th>
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
              <table class="table" style="width:70%">
                <tr>
                  <th colspan=2>
                    <h4>4. Information sur l’échantillon</h4>
                  </th>
                </tr>
                <tr>
                  <th>Date de collecte</th>
                  <td>
                    <input class="form-control dateTime isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Date de collecte" value="<?php echo $general->humanDateFormat($eidInfo['sample_collection_date']); ?>" />
                  </td>
                </tr>
                <tr>
                  <th>Tel. du préleveur</th>
                  <td>
                    <input class="form-control" type="text" name="sampleRequestorPhone" id="sampleRequestorPhone" placeholder="Tel. du préleveur" value="<?php echo $eidInfo['sample_requestor_phone']; ?>" />
                  </td>
                </tr>
                </tr>
                <tr>
                  <th>Nom du demandeur</th>
                  <td>
                    <input class="form-control" type="text" name="sampleRequestorName" id="sampleRequestorName" placeholder="Nom du demandeur" value="<?php echo $eidInfo['sample_requestor_name']; ?>" />
                  </td>
                </tr>
                <tr>
                  <th>Raison de la PCR (cocher une):</th>
                  <td>
                    <select class="form-control" name="reasonForPCR" id="reasonForPCR">
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
                  <th>Test rapide effectué?</th>
                  <td>
                    <select class="form-control" name="rapidTestPerformed" id="rapidTestPerformed">
                      <option value=''> -- Sélectionner -- </option>
                      <option value="yes" <?php echo ($eidInfo['rapid_test_performed'] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
                      <option value="no" <?php echo ($eidInfo['rapid_test_performed'] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
                    </select>
                  </td>
                </tr>
                <tr>
                  <th>Si oui, date :</th>
                  <td>
                    <input class="form-control" type="text" name="rapidtestDate" id="rapidtestDate" placeholder="Si oui, date" value="<?php echo $general->humanDateFormat($eidInfo['rapid_test_date']); ?>" />
                  </td>
                </tr>
                <tr>
                  <th>Résultat test rapide</th>
                  <td>
                    <select class="form-control" name="rapidTestResult" id="rapidTestResult">
                      <option value=''> -- Sélectionner -- </option>
                      <option value="positive" <?php echo ($eidInfo['rapid_test_result'] == 'positive') ? "selected='selected'" : ""; ?>> Positif </option>
                      <option value="negative" <?php echo ($eidInfo['rapid_test_result'] == 'negative') ? "selected='selected'" : ""; ?>> Négatif </option>
                      <option value="indeterminate" <?php echo ($eidInfo['rapid_test_result'] == 'indeterminate') ? "selected='selected'" : ""; ?>> Indéterminé </option>
                    </select>
                  </td>
                </tr>
              </table>


            </div>
          </div>

          <form class="form-horizontal" method="post" name="editEIDRequestForm" id="editEIDRequestForm" autocomplete="off" action="eid-update-result-helper.php">
            <?php if ($sarr['user_type'] != 'remoteuser') { ?>

              <div class="box box-primary">
                <div class="box-body">
                  <div class="box-header with-border">
                    <h3 class="box-title">B. Réservé au laboratoire d’analyse </h3>
                  </div>
                  <table class="table" style="width:100%">
                    <tr>
                      <th><label for="">Date de réception de l'échantillon <span class="mandatory">*</span></label></th>
                      <td>
                        <input type="text" readonly class="form-control dateTime isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="e.g 09-Jan-1992 05:30" title="Date de réception de l'échantillon" <?php echo $labFieldDisabled; ?> value="<?php echo $general->humanDateFormat($eidInfo['sample_received_at_vl_lab_datetime']) ?>" onchange="" style="width:100%;" />
                      </td>
                      <td><label for="labId">Nom du Laboratoire <span class="mandatory">*</span></label> </td>
                      <td>
                        <select name="labId" id="labId" class="form-control isRequired" title="Nom du Laboratoire" style="width:100%;">
                          <?= $general->generateSelectOptions($testingLabs, $eidInfo['lab_id'], '-- Sélectionner --'); ?>
                        </select>
                      </td>
                    <tr>
                      <th>Is Sample Rejected ? <span class="mandatory">*</span></th>
                      <td>
                        <select class="form-control isRequired" name="isSampleRejected" title="Please enter if Sample Rejected or not" id="isSampleRejected" onchange="sampleRejection();">
                          <option value=''> -- Sélectionner -- </option>
                          <option value="yes" <?php echo ($eidInfo['is_sample_rejected'] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
                          <option value="no" <?php echo ($eidInfo['is_sample_rejected'] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
                        </select>
                      </td>

                      <th>Reason for Rejection</th>
                      <td>
                        <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="Please select reason for sample rejection">
                          <option value=''> -- Sélectionner -- </option>
                          <option value="Technical Problem" <?php echo ($eidInfo['reason_for_sample_rejection'] == 'Technical Problem') ? "selected='selected'" : ""; ?>> Problème technique </option>
                          <option value="Poor numbering" <?php echo ($eidInfo['reason_for_sample_rejection'] == 'Poor numbering') ? "selected='selected'" : ""; ?>> Mauvaise numérotation </option>
                          <option value="Insufficient sample" <?php echo ($eidInfo['reason_for_sample_rejection'] == 'Insufficient sample') ? "selected='selected'" : ""; ?>> Echantillon insuffisant </option>
                          <option value="Degraded sample or clot" <?php echo ($eidInfo['reason_for_sample_rejection'] == 'Degraded sample or clot') ? "selected='selected'" : ""; ?>> Echantillon dégradé ou caillot </option>
                          <option value="Poor packaging" <?php echo ($eidInfo['reason_for_sample_rejection'] == 'Poor packaging') ? "selected='selected'" : ""; ?>> Mauvais empaquetage </option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td style="width:25%;"><label for="">Test effectué le </label></td>
                      <td style="width:25%;">
                        <input type="text" readonly class="form-control dateTime" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="e.g 09-Jan-1992 05:30" title="Test effectué le" <?php echo $labFieldDisabled; ?> onchange="" value="<?php echo $general->humanDateFormat($eidInfo['sample_tested_datetime']) ?>" style="width:100%;" />
                      </td>


                      <th>Résultat </label></th>
                      <td>
                        <select class="form-control isRequired" name="result" id="result" title="Résultat">
                          <option value=''> -- Sélectionner -- </option>
                          <option value="positive" <?php echo ($eidInfo['result'] == 'positive') ? "selected='selected'" : ""; ?>> Positif </option>
                          <option value="negative" <?php echo ($eidInfo['result'] == 'negative') ? "selected='selected'" : ""; ?>> Négatif </option>
                          <option value="indeterminate" <?php echo ($eidInfo['result'] == 'indeterminate') ? "selected='selected'" : ""; ?>> Indéterminé </option>
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
          <input type="hidden" name="formId" id="formId" value="3" />
          <input type="hidden" name="eidSampleId" id="eidSampleId" value="<?php echo ($eidInfo['eid_id']); ?>" />
          <input type="hidden" id="sampleCode" name="sampleCode" value="<?php echo $eidInfo['sample_code'] ?>" />

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
        $.post("/includes/getFacilityForClinic.php", {
            pName: pName
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
      $.post("/includes/getFacilityForClinic.php", {
          dName: dName,
          cliName: cName
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
      $.post("/includes/getFacilityForClinic.php", {
          cName: cName
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
    getfacilityProvinceDetails($("#facilityId").val());
    <?php if (isset($eidInfo['mother_treatment']) && in_array('Other', $eidInfo['mother_treatment'])) { ?>
      $('#motherTreatmentOther').prop('disabled', false);
    <?php } ?>

    <?php if (isset($eidInfo['mother_vl_result']) && !empty($eidInfo['mother_vl_result'])) { ?>
      updateMotherViralLoad();
    <?php } ?>

    $("#motherViralLoadCopiesPerMl").on("change keyup paste", function() {
      var motherVl = $("#motherViralLoadCopiesPerMl").val();
      //var motherVlText = $("#motherViralLoadText").val();
      if (motherVl != '') {
        $("#motherViralLoadText").val('');
      }
    });

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
</script>