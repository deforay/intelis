<?php

// imported in covid-19-edit-request.php based on country in global config

use App\Models\Covid19;
use App\Models\GeoLocations;
use App\Models\Patients;
use App\Utilities\DateUtils;

ob_start();


//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);

//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);


$covid19Obj = new Covid19();
$patientsModel = new Patients();


$covid19Results = $covid19Obj->getCovid19Results();
$specimenTypeResult = $covid19Obj->getCovid19SampleTypes();

$covid19Symptoms = $covid19Obj->getCovid19SymptomsDRC();
$covid19SelectedSymptomsData = $covid19Obj->getCovid19SymptomsByFormId($covid19Info['covid19_id'], true);
$covid19SelectedSymptoms = array();
foreach ($covid19SelectedSymptomsData as $row) {
    $covid19SelectedSymptoms[$row['symptom_id']]['value'] = $row['symptom_detected'];
    $covid19SelectedSymptoms[$row['symptom_id']]['sDetails'] = json_decode($row['symptom_details'], true);
}


$covid19ReasonsForTesting = $covid19Obj->getCovid19ReasonsForTestingDRC();
$covid19SelectedReasonsForTesting = $covid19Obj->getCovid19ReasonsForTestingByFormId($covid19Info['covid19_id']);
$covid19SelectedReasonsDetailsForTesting = $covid19Obj->getCovid19ReasonsDetailsForTestingByFormId($covid19Info['covid19_id']);
// To get the reason details value
$reasonDetails = json_decode($covid19SelectedReasonsDetailsForTesting['reason_details'], true);

$covid19Comorbidities = $covid19Obj->getCovid19Comorbidities();
$covid19SelectedComorbidities = $covid19Obj->getCovid19ComorbiditiesByFormId($covid19Info['covid19_id']);


// Getting the list of Provinces, Districts and Facilities

$rKey = '';
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";


if ($_SESSION['instanceType'] == 'remoteuser') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    if (!empty($covid19Info['remote_sample']) && $covid19Info['remote_sample'] == 'yes') {
        $sampleCode = 'remote_sample_code';
    } else {
        $sampleCode = 'sample_code';
    }
    //check user exist in user_facility_map table
    $chkUserFcMapQry = "SELECT user_id from user_facility_map where user_id='" . $_SESSION['userId'] . "'";
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

$facility = $general->generateSelectOptions($healthFacilities, $covid19Info['facility_id'], '-- Sélectionner --');


//suggest N°EPID when lab user add request sample
$sampleSuggestion = '';
$sampleSuggestionDisplay = 'display:none;';
$sCode = (isset($_GET['c']) && $_GET['c'] != '') ? $_GET['c'] : '';
if ($sarr['sc_user_type'] == 'vluser' && $sCode != '') {
    $vlObj = new Covid19();
    $sampleCollectionDate = explode(" ", $sampleCollectionDate);
    $sampleCollectionDate = DateUtils::humanReadableDateFormat($sampleCollectionDate[0]);
    $sampleSuggestionJson = $vlObj->generateCovid19SampleCode($stateResult[0]['province_code'], $sampleCollectionDate, 'png');
    $sampleCodeKeys = json_decode($sampleSuggestionJson, true);
    $sampleSuggestion = $sampleCodeKeys['sampleCode'];
    $sampleSuggestionDisplay = 'display:block;';
}
$geolocation = new GeoLocations();
$geoLocationParentArray = $geolocation->fetchActiveGeolocations(0, 0);

// Province
$pQuery = "SELECT DISTINCT patient_province FROM form_covid19 where patient_province is not null";
$pResult = $db->rawQuery($pQuery);
$patienProvince = array();
foreach ($pResult as $row) {
    $patienProvince[$row['patient_province']] = $row['patient_province'];
}
$patienProvince["other"] = "Other";
// District
$cQuery = "SELECT DISTINCT patient_district FROM form_covid19 where patient_district is not null";
$cResult = $db->rawQuery($cQuery);
$pateitnDistrict = array();
foreach ($cResult as $row) {
    $pateitnDistrict[$row['patient_district']] = $row['patient_district'];
}
$pateitnDistrict["other"] = "Other";

// Zones
$zQuery = "SELECT DISTINCT patient_zone FROM form_covid19 where patient_zone is not null";
$zResult = $db->rawQuery($zQuery);
$patienZones = array();
foreach ($zResult as $row) {
    $patienZones[$row['patient_zone']] = $row['patient_zone'];
}
$patienZones["other"] = "Other";

$generateAutomatedPatientCode = $general->getGlobalConfig('covid19_generate_patient_code');
if (!empty($generateAutomatedPatientCode) && $generateAutomatedPatientCode == 'yes') {
    //$patientCodePrefix = $general->getGlobalConfig('covid19_patient_code_prefix');
    $generateAutomatedPatientCode = true;
} else {
    $generateAutomatedPatientCode = false;
}

$patientData = $patientsModel->getPatient($covid19Info['patient_id']);
$patientCodePrefix = $patientCodeKey = "";
if (!empty($patientData)) {
    $patientCodePrefix = $patientData['patient_code_prefix'];
    $patientCodeKey = $patientData['patient_code_key'];
}


?>
<style>
    .other-comorbidities {
        display: none;
    }
</style>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> COVID-19 VIRUS LABORATORY TEST DRC REQUEST FORM</h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Accueil</a></li>
            <li class="active">Ajouter une nouvelle demande</li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indique un champ obligatoire &nbsp;</div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->
                <form class="form-horizontal" method="post" name="editCovid19RequestForm" id="editCovid19RequestForm" autocomplete="off" action="covid-19-edit-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">INFORMATIONS SUR LE SITE</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">À remplir par le clinicien / infirmier demandeur</h3>
                                </div>
                                <table class="table" aria-hidden="true"  style="width:100%">
                                    <?php if ($covid19Info['remote_sample'] == 'yes') { ?>
                                        <tr>
                                            <?php
                                            if ($covid19Info['sample_code'] != '') {
                                            ?>
                                                <td colspan="4"> <label for="sampleSuggest" class="text-danger">&nbsp;&nbsp;&nbsp;Veuillez noter que cet exemple distant a déjà été importé avec VLSM Échantillon ID </td>
                                                <td colspan="4" align="left"> <?php echo $covid19Info['sample_code']; ?></label> </td>
                                            <?php
                                            } else {
                                            ?>
                                                <td colspan="4"> <label for="sampleSuggest">Échantillon ID (peut changer lors de la soumission du formulaire)</label></td>
                                                <td colspan="4" align="left"> <?php echo $sampleSuggestion; ?></td>
                                            <?php } ?>
                                        </tr>
                                    <?php } ?>
                                    <tr>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <td><label for="sampleCode">Échantillon ID </label> </td>
                                            <td>
                                                <span id="sampleCodeInText" style="width:30%;border-bottom:1px solid #333;"><?php echo ($sCode != '') ? $sCode : $covid19Info[$sampleCode]; ?></span>
                                                <input type="hidden" class="<?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" value="<?php echo ($sCode != '') ? $sCode : $covid19Info[$sampleCode]; ?>" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode">Échantillon ID </label><span class="mandatory">*</span> </td>
                                            <td>
                                                <input type="text" readonly value="<?php echo ($sCode != '') ? $sCode : $covid19Info[$sampleCode]; ?>" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Échantillon ID" title="Échantillon ID" style="width:100%;" onchange="" />
                                            </td>
                                        <?php } ?>
                                        <th scope="row"><label for="testNumber">Prélévement</label></th>
                                        <td>
                                            <select class="form-control" name="testNumber" id="testNumber" title="Prélévement" style="width:100%;">
                                                <option value="">--Select--</option>
                                                <?php foreach (range(1, 5) as $element) {
                                                    $selected = (isset($covid19Info['test_number']) && $covid19Info['test_number'] == $element) ? "selected='selected'" : "";
                                                    echo '<option value="' . $element . '" ' . $selected . '>' . $element . '</option>';
                                                } ?>
                                            </select>
                                        </td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td><label for="province">Province </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="province" id="province" title="Province" onchange="getfacilityDetails(this);" style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <td><label for="district">Zone de Santé </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="district" id="district" title="Zone de Santé " style="width:100%;" onchange="getfacilityDistrictwise(this);">
                                                <option value=""> -- Sélectionner -- </option>
                                            </select>
                                        </td>
                                        <td><label for="facilityId">POINT DE COLLECT </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired " name="facilityId" id="facilityId" title="POINT DE COLLECT" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
                                                <?php echo $facility; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <!-- <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addFacility">Add Facility</button> -->
                                        </td>
                                    </tr>
                                    <tr>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <!-- <tr> -->
                                            <td><label for="labId">LAB ID <span class="mandatory">*</span></label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control isRequired" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, $covid19Info['lab_id'], '-- Sélectionner --'); ?>
                                                </select>
                                            </td>
                                            <!-- </tr> -->
                                        <?php } ?>
                                    </tr>
                                </table>

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">INFORMATION PATIENT</h3>&nbsp;&nbsp;&nbsp;
                                    <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="" placeholder="Code du patient" title="Please enter code du patient" />&nbsp;&nbsp;
                                    <a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No Patient Found</strong></span>
                                </div>
                                <table class="table" aria-hidden="true"  style="width:100%">

                                    <tr>
                                        <th style="width:15% !important"><label for="lastName">Nom de famille <span class="mandatory">*</span></label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="lastName" name="lastName" placeholder="Nom de famille" title="Nom de famille" style="width:100%;" value="<?php echo $covid19Info['patient_surname']; ?>" />
                                        </td>
                                        <th style="width:15% !important"><label for="firstName">Prénom </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control" id="firstName" name="firstName" placeholder="Prénom" title="Prénom" style="width:100%;" value="<?php echo $covid19Info['patient_name']; ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="patientId">N&deg; EPID </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control" id="patientId" name="patientId" placeholder="N&deg; EPID" title="N&deg; EPID" style="width:100%;" value="<?php echo $covid19Info['patient_id']; ?>" <?= ($generateAutomatedPatientCode) ? "readonly='readonly'" : "" ?> />
                                        </td>
                                        <th scope="row"><label for="patientDob">Date de naissance</label></th>
                                        <td>
                                            <input type="text" class="form-control" id="patientDob" name="patientDob" placeholder="Date de naissance" title="Date de naissance" style="width:100%;" onchange="calculateAgeInYears();" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['patient_dob']); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Age (years)</th>
                                        <td><input type="number" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="patientAge" name="patientAge" placeholder="Age (years)" title="Age (years)" style="width:100%;" value="<?php echo $covid19Info['patient_age']; ?>" /></td>
                                        <th scope="row"><label for="patientGender">Sexe <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <select class="form-control isRequired" name="patientGender" id="patientGender">
                                                <option value=''> -- Sélectionner -- </option>
                                                <option value='male' <?php echo ($covid19Info['patient_gender'] == 'male') ? "selected='selected'" : ""; ?>> Homme </option>
                                                <option value='female' <?php echo ($covid19Info['patient_gender'] == 'female') ? "selected='selected'" : ""; ?>> Femme </option>
                                                <option value='other' <?php echo ($covid19Info['patient_gender'] == 'other') ? "selected='selected'" : ""; ?>> Other </option>

                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="isPatientPregnant">Enceinte</label></th>
                                        <td>
                                            <select class="form-control" name="isPatientPregnant" id="isPatientPregnant">
                                                <option value=''> -- Sélectionner -- </option>
                                                <option value='yes' <?php echo ($covid19Info['is_patient_pregnant'] == 'yes') ? "selected='selected'" : ""; ?>> Enceinte </option>
                                                <option value='no' <?php echo ($covid19Info['is_patient_pregnant'] == 'no') ? "selected='selected'" : ""; ?>> Pas Enceinte </option>
                                                <option value='unknown' <?php echo ($covid19Info['is_patient_pregnant'] == 'unknown') ? "selected='selected'" : ""; ?>> Inconnue </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Numéro de téléphone</th>
                                        <td><input type="text" class="form-control " id="patientPhoneNumber" name="patientPhoneNumber" placeholder="Numéro de téléphone" title="Numéro de téléphone" style="width:100%;" value="<?php echo $covid19Info['patient_phone_number']; ?>" /></td>
                                        <th scope="row">Courriel du patient</th>
                                        <td><input type="text" class="form-control " id="patientEmail" name="patientEmail" placeholder="Courriel du patient" title="Province du patient" style="width:100%;" value="<?php echo $covid19Info['patient_email']; ?>" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Adresse du patient</th>
                                        <td><textarea class="form-control " id="patientAddress" name="patientAddress" placeholder="Adresse du patient" title="Adresse du patient" style="width:100%;" onchange=""><?php echo $covid19Info['patient_address']; ?></textarea></td>
                                        <th scope="row">Province du patient</th>
                                        <td>
                                            <select class="form-control ajax-select2" id="patientProvince" name="patientProvince" placeholder="Province du patient" style="width:100%;">
                                                <?= $general->generateSelectOptions($patienProvince, $covid19Info['patient_province'], '-- Sélectionner --'); ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Commune</th>
                                        <td><select class="form-control ajax-select2" id="patientZone" name="patientZone" placeholder="Commune" style="width:100%;">
                                                <?= $general->generateSelectOptions($patienZones, $covid19Info['patient_zone'], '-- Sélectionner --'); ?>
                                            </select>
                                        </td>
                                        <th scope="row">Zone de Santé du Patient</th>
                                        <td><select class="form-control ajax-select2" id="patientDistrict" name="patientDistrict" placeholder="Zone de Santé du Patient" style="width:100%;">
                                                <?= $general->generateSelectOptions($pateitnDistrict, $covid19Info['patient_district'], '-- Sélectionner --'); ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Pays de résidence</th>
                                        <td>
                                            <select class="form-control select2" id="patientNationality" name="patientNationality" title="Commune">
                                                <?= $general->generateSelectOptions($countyData, $covid19Info['patient_nationality'], '-- Sélectionner --'); ?>
                                            </select>
                                            <!-- <input type="text" class="form-control" value="<?php echo $covid19Info['patient_nationality']; ?>" id="patientNationality" name="patientNationality" placeholder="Pays de résidence" title="Pays de résidence" style="width:100%;" /> -->
                                        </td>
                                    </tr>
                                </table>
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">
                                        Definition de cas
                                    </h3>
                                </div>
                                <table id="responseTable" class="table table-bordered" aria-hidden="true" >
                                    <tr>
                                        <td colspan="2">
                                            <label class="radio-inline" style="margin-left:0;">
                                                <input type="radio" class="" id="reason1" name="reasonForCovid19Test" value="1" title="Please check response" onchange="checkSubReason(this,'Cas_suspect_de_COVID_19');" <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 1) ? "checked" : ""; ?>>
                                                <strong>Cas suspect de COVID-19</strong>
                                            </label>

                                        </td>
                                    </tr>
                                    <tr class="Cas_suspect_de_COVID_19 hide-reasons" style="display: <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 1) ? "grid" : "none"; ?>;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="checkbox" id="suspect1" name="reasonDetails[]" value="Fièvre d'accès brutal (Inferieur ou égale à 38°C, vérifié à la salle d'urgence, la consultation externe, ou l'hôpital) ET(cochez une ou deux des cases suivantes)" title="Please check response" <?php echo (in_array("Fièvre d'accès brutal (Inferieur ou égale à 38°C, vérifié à la salle d'urgence, la consultation externe, ou l'hôpital) ET(cochez une ou deux des cases suivantes)", $reasonDetails)) ? "checked" : ""; ?>>
                                            </label>
                                            <label class="radio-inline" for="suspect1" style="padding-left:17px !important;margin-left:0;">Fièvre d'accès brutal (Inferieur ou égale à 38°C, vérifié à la salle d'urgence, la consultation externe, ou l'hôpital) ET(cochez une ou deux des cases suivantes)</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_suspect_de_COVID_19 hide-reasons" style="display: <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 1) ? "grid" : "none"; ?>;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <ul style=" display: inline-flex; list-style: none; padding: 0px; ">
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="checkbox" id="suspect2" name="reasonDetails[]" value="Toux" title="Please check response" <?php echo (in_array("Toux", $reasonDetails) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 1) ? "checked" : ""; ?>>
                                                    </label>
                                                    <label class="radio-inline" for="suspect2" style="padding-left:17px !important;margin-left:0;">Toux</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="checkbox" id="suspect3" name="reasonDetails[]" value="Rhume" title="Please check response" <?php echo (in_array("Rhume", $reasonDetails) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 1) ? "checked" : ""; ?>>
                                                    </label>
                                                    <label class="radio-inline" for="suspect3" style="padding-left:17px !important;margin-left:0;">Rhume</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="checkbox" id="suspect4" name="reasonDetails[]" value="Mal de gorge" title="Please check response" <?php echo (in_array("Mal de gorge", $reasonDetails) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 1) ? "checked" : ""; ?>>
                                                    </label>
                                                    <label class="radio-inline" for="suspect4" style="padding-left:17px !important;margin-left:0;">Mal de gorge</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="checkbox" id="suspect5" name="reasonDetails[]" value="Difficulté respiratoire" title="Please check response" <?php echo (in_array("Difficulté respiratoire", $reasonDetails) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 1) ? "checked" : ""; ?>>
                                                    </label>
                                                    <label class="radio-inline" for="suspect5" style="padding-left:17px !important;margin-left:0;">Difficulté respiratoire</label>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                    <tr class="Cas_suspect_de_COVID_19 hide-reasons" style="display: <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 1) ? "grid" : "none"; ?>;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="checkbox" id="suspect6" name="reasonDetails[]" value="Notion de séjour ou voyage dans les zones a épidémie a COVID-19 dans les 14 jours précédant les symptômes ci-dessous." title="Please check response" <?php echo (in_array("Notion de séjour ou voyage dans les zones a épidémie a COVID-19 dans les 14 jours précédant les symptômes ci-dessous.", $reasonDetails) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 1) ? "checked" : ""; ?>>
                                            </label>
                                            <label class="radio-inline" for="suspect6" style="padding-left:17px !important;margin-left:0;">Notion de séjour ou voyage dans les zones a épidémie a COVID-19 dans les 14 jours précédant les symptômes ci-dessous.</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_suspect_de_COVID_19 hide-reasons text-center" style="display: <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 1) ? "grid" : "none"; ?>;">
                                        <td>
                                            <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">OU</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_suspect_de_COVID_19 hide-reasons" style="display: <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 1) ? "grid" : "none"; ?>;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="checkbox" id="suspect7" name="reasonDetails[]" value="IRA d'intensité variable (simple a sévère) ayant été en contact étroite avec cas probable ou un cas confirmé de la maladie a COVID-19" title="Please check response" <?php echo (in_array("IRA d'intensité variable (simple a sévère) ayant été en contact étroite avec cas probable ou un cas confirmé de la maladie a COVID-19", $reasonDetails) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 1) ? "checked" : ""; ?>>
                                            </label>
                                            <label class="radio-inline" for="suspect7" style="padding-left:17px !important;margin-left:0;">IRA d'intensité variable (simple a sévère) ayant été en contact étroite avec cas probable ou un cas confirmé de la maladie a COVID-19</label>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td colspan="2">
                                            <label class="radio-inline" style="margin-left:0;">
                                                <input type="radio" id="reason2" name="reasonForCovid19Test" value="2" title="Please check response" onchange="checkSubReason(this,'Cas_probable_de_COVID_19');" <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 2) ? "checked" : ""; ?>>
                                                <strong>Cas probable de COVID-19</strong>
                                            </label>

                                        </td>
                                    </tr>
                                    <tr class="Cas_probable_de_COVID_19 hide-reasons" style="display: <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 2) ? "grid" : "none"; ?>;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="checkbox" id="probable1" name="reasonDetails[]" value="Tout cas suspects dont le résultat de laboratoire pour le diagnostic de COVID-19 n'est pas concluant (indéterminé)" title="Please check response" <?php echo (in_array("Tout cas suspects dont le résultat de laboratoire pour le diagnostic de COVID-19 n'est pas concluant (indéterminé)", $reasonDetails) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 2) ? "checked" : ""; ?>>
                                            </label>
                                            <label class="radio-inline" for="probable1" style="padding-left:17px !important;margin-left:0;">Tout cas suspects dont le résultat de laboratoire pour le diagnostic de COVID-19 n'est pas concluant (indéterminé)</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_probable_de_COVID_19 hide-reasons text-center" style="display: <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 2) ? "grid" : "none"; ?>;">
                                        <td>
                                            <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">OU</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_probable_de_COVID_19 hide-reasons" style="display: <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 2) ? "grid" : "none"; ?>;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="checkbox" id="probable2" name="reasonDetails[]" value="Tout décès dans un tableau d'IRA pour lequel il n'a pas été possible d'obtenir des échantillons biologiques pour confirmation au laboratoire mais dont les investigations ont révélé un lien épidémiologique avec un cas confirmé ou probable" title="Please check response" <?php echo (in_array("Tout décès dans un tableau d'IRA pour lequel il n'a pas été possible d'obtenir des échantillons biologiques pour confirmation au laboratoire mais dont les investigations ont révélé un lien épidémiologique avec un cas confirmé ou probable", $reasonDetails) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 2) ? "checked" : ""; ?>>
                                            </label>
                                            <label class="radio-inline" for="probable2" style="padding-left:17px !important;margin-left:0;">Tout décès dans un tableau d'IRA pour lequel il n'a pas été possible d'obtenir des échantillons biologiques pour confirmation au laboratoire mais dont les investigations ont révélé un lien épidémiologique avec un cas confirmé ou probable</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_probable_de_COVID_19 hide-reasons text-center" style="display: <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 1) ? "grid" : "none"; ?>;">
                                        <td>
                                            <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">OU</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_probable_de_COVID_19 hide-reasons" style="display: <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 2) ? "grid" : "none"; ?>;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="checkbox" id="probable4" name="reasonDetails[]" value="Une notion de séjour ou voyage dans les 14 jours précédant le décès dans les zones a épidémie de la maladie a COVID-19" title="Please check response" <?php echo (in_array("Notion de séjour ou voyage dans les 14 jours précédant le décès dans les zones a épidémie de la maladie a COVID-19", $reasonDetails) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 2) ? "checked" : ""; ?>>
                                            </label>
                                            <label class="radio-inline" for="probable4" style="padding-left:17px !important;margin-left:0;">Une notion de séjour ou voyage dans les 14 jours précédant le décès dans les zones a épidémie de la maladie a COVID-19</label>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td colspan="2">
                                            <label class="radio-inline" style="margin-left:0;">
                                                <input type="radio" id="reason3" name="reasonForCovid19Test" value="3" title="Please check response" onchange="checkSubReason(this,'Cas_confirme_de_COVID_19');" <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 3) ? "checked" : ""; ?>>
                                                <strong>Cas confirme de covid-19</strong>
                                            </label>

                                        </td>
                                    </tr>
                                    <tr class="Cas_confirme_de_COVID_19 hide-reasons" style="display: <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 3) ? "grid" : "none"; ?>;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="checkbox" id="confirme1" name="reasonDetails[]" value="Toute personne avec une confirmation en laboratoire de l'infection au COVID-19, quelles que soient les signes et symptômes cliniques" title="Please check response" <?php echo (in_array("Toute personne avec une confirmation en laboratoire de l'infection au COVID-19, quelles que soient les signes et symptômes cliniques", $reasonDetails) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 3) ? "checked" : ""; ?>>
                                            </label>
                                            <label class="radio-inline" for="confirme1" style="padding-left:17px !important;margin-left:0;">Toute personne avec une confirmation en laboratoire de l'infection au COVID-19, quelles que soient les signes et symptômes cliniques</label>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td colspan="2">
                                            <label class="radio-inline" style="margin-left:0;">
                                                <input type="radio" id="reason4" name="reasonForCovid19Test" value="4" title="Please check response" onchange="checkSubReason(this,'Non_cas_contact_de_COVID_19');" <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 4) ? "checked" : ""; ?>>
                                                <strong>Non cas contact de COVID-19</strong>
                                            </label>

                                        </td>
                                    </tr>
                                    <tr class="Non_cas_contact_de_COVID_19 hide-reasons" style="display: <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 4) ? "grid" : "none"; ?>;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="checkbox" id="contact1" name="reasonDetails[]" value="Tout cas suspects avec deux résultats de laboratoire négatifs au COVID-19 a au moins 48 heures d'intervalle" title="Please check response" <?php echo (in_array("Tout cas suspects avec deux résultats de laboratoire négatifs au COVID-19 a au moins 48 heures d'intervalle", $reasonDetails) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 4) ? "checked" : ""; ?>>
                                            </label>
                                            <label class="radio-inline" for="contact1" style="padding-left:17px !important;margin-left:0;">Tout cas suspects avec deux résultats de laboratoire négatifs au COVID-19 a au moins 48 heures d'intervalle</label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <label class="radio-inline" style="margin-left:0;">
                                                <input type="radio" id="reason5" name="reasonForCovid19Test" value="5" title="Diagnostique">
                                                <input type="radio" id="reason5" name="reasonForCovid19Test" value="5" title="Diagnostique" <?php echo (isset($covid19SelectedReasonsDetailsForTesting['reasons_id']) && $covid19SelectedReasonsDetailsForTesting['reasons_id'] == 5) ? "checked" : ""; ?>>
                                                <strong>Diagnostique</strong>
                                            </label>
                                        </td>
                                    </tr>
                                </table>

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">
                                        Signes vitaux du patient
                                    </h3>
                                </div>
                                <table class="table" aria-hidden="true" >
                                    <tr>
                                        <th style="width:15% !important">Fever/Temperature (&deg;C)</th>
                                        <td style="width:35% !important;">
                                            <input class="form-control" type="number" value="<?php echo $covid19Info['fever_temp']; ?>" name="feverTemp" id="feverTemp" placeholder="Fever/Temperature (in &deg;Celcius)" />
                                        </td>
                                        <th style="width:15% !important"><label for="temperatureMeasurementMethod">Température</label></th>
                                        <td style="width:35% !important;">
                                            <select name="temperatureMeasurementMethod" id="temperatureMeasurementMethod" class="form-control" title="Température">
                                                <option value="">--Select--</option>
                                                <option value="auxillary" <?php echo ($covid19Info['temperature_measurement_method'] == 'auxillary') ? "selected='selected'" : ""; ?>>Axillaire</option>
                                                <option value="oral" <?php echo ($covid19Info['temperature_measurement_method'] == 'oral') ? "selected='selected'" : ""; ?>>Orale</option>
                                                <option value="rectal" <?php echo ($covid19Info['temperature_measurement_method'] == 'rectal') ? "selected='selected'" : ""; ?>>Rectale</option>
                                                <option value="unknown" <?php echo ($covid19Info['temperature_measurement_method'] == 'unknown') ? "selected='selected'" : ""; ?>>Inconnu</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="respiratoryRate"> Fréquence Respiratoire</label></th>
                                        <td style="width:35% !important;">
                                            <input class="form-control" type="number" value="<?php echo $covid19Info['respiratory_rate']; ?>" name="respiratoryRate" id="respiratoryRate" placeholder="Fréquence Respiratoire" title="Fréquence Respiratoire" />
                                        </td>
                                        <th style="width:15% !important"><label for="oxygenSaturation"> Saturation en oxygène</label></th>
                                        <td style="width:35% !important;">
                                            <input class="form-control" type="number" value="<?php echo $covid19Info['oxygen_saturation']; ?>" name="oxygenSaturation" id="oxygenSaturation" placeholder="Saturation en oxygène" title="Saturation en oxygène" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width: 15%;"><label for="specimenType"> Type(s) d' échantillon(s) dans le tube (cochez au moins une des cases suivants) <span class="mandatory">*</span></label></th>
                                        <td style="width: 35%;">
                                            <select class="form-control isRequired" id="specimenType" name="specimenType" title="Type(s) d' échantillon(s) dans le tube">
                                                <option value="">--Select--</option>
                                                <?php echo $general->generateSelectOptions($specimenTypeResult, $covid19Info['specimen_type']); ?>
                                            </select>
                                        </td>
                                        <th style="width: 15% !important;"><label for="numberOfDaysSick">Depuis combien de jours êtes-vous malade?</label></th>
                                        <td style="width:35% !important;">
                                            <input type="text" value="<?php echo $covid19Info['number_of_days_sick']; ?>" class="form-control" id="numberOfDaysSick" name="numberOfDaysSick" placeholder="Depuis combien de jours êtes-vous malade?" title="Date de Result PCR" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important">Date d'apparition des symptômes </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control date symptomSpecificFields" type="text" name="dateOfSymptomOnset" id="dateOfSymptomOnset" placeholder="Date d'apparition des symptômes" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['date_of_symptom_onset']); ?> " />
                                        </td>
                                        <th style="width:15% !important">Date de la consultation initiale</th>
                                        <td style="width:35% !important;">
                                            <input class="form-control date" type="text" name="dateOfInitialConsultation" id="dateOfInitialConsultation" placeholder="Date of Initial Consultation" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['date_of_initial_consultation']); ?> " />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="sampleCollectionDate">Date de prélèvement de l'échantillon <span class="mandatory">*</span></label></th>
                                        <td style="width:35% !important;">
                                            <input class="form-control isRequired" value="<?php echo date('d-M-Y H:i:s', strtotime($covid19Info['sample_collection_date'])); ?>" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Date de prélèvement de l'échantillon" title="Date de prélèvement de l'échantillon" onchange="sampleCodeGeneration();" />
                                        </td>
                                        <th style="width:15% !important">Échantillon expédié le <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control dateTime isRequired" type="text" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="Échantillon expédié le" value="<?php echo date('d-M-Y H:i:s', strtotime($covid19Info['sample_dispatched_datetime'])); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th colspan="4" style="width:15% !important">Symptômes <span class="mandatory">*</span> </th>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="asymptomatic">Asymptomatic <span class="mandatory">*</span></label></th>
                                        <td style="width:35% !important;">
                                            <select name="asymptomatic" id="asymptomatic" class="form-control isRequired" title="Asymptomatic" onchange="asymptomaticFn(this.value);">
                                                <option value="">--Select--</option>
                                                <option value="yes" <?php echo ($covid19Info['asymptomatic'] == 'yes') ? "selected='selected'" : ""; ?>>Oui</option>
                                                <option value="no" <?php echo ($covid19Info['asymptomatic'] == 'no') ? "selected='selected'" : ""; ?>>Non</option>
                                                <option value="unknown" <?php echo ($covid19Info['asymptomatic'] == 'unknown') ? "selected='selected'" : ""; ?>>Inconnu</option>
                                            </select>
                                        </td>
                                        <th scope="row"></th>
                                        <td></td>
                                    </tr>
                                    <tr class="symptoms" style="display: <?php echo ($covid19Info['asymptomatic'] == 'yes') ? "none" : "contents"; ?>;">
                                        <td colspan="4">
                                            <table id="symptomsTable" class="table table-bordered table-striped" aria-hidden="true" >
                                                <?php $index = 0;
                                                foreach ($covid19Symptoms as $symptomId => $symptomName) {
                                                    $diarrhée = "";
                                                    $display = "display:none;";
                                                    if ($symptomId == 13) {
                                                        $diarrhée = "diarrhée";
                                                        $display = (isset($covid19SelectedSymptoms[$symptomId]['value']) && $covid19SelectedSymptoms[$symptomId]['value'] == "yes") ? "" : 'display:none;';
                                                    }
                                                    ?>
                                                    <tr class="row<?php echo $index; ?>">
                                                        <!-- <td style="display: flex;">
                                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                                <input type="checkbox" class="checkSymptoms" id="xsymptom<?php echo $symptomId; ?>" name="symptom[]" value="<?php echo $symptomId; ?>" title="Veuillez choisir la valeur pour <?php echo $symptomName; ?>" onclick="checkSubSymptoms(this.value,<?php echo $symptomId; ?>,<?php echo $index; ?>);">
                                                            </label>
                                                            <label class="radio-inline" for="symptom<?php echo $symptomId; ?>" style="padding-left:17px !important;margin-left:0;"><strong><?php echo $symptomName; ?></strong></label>
                                                        </td> -->
                                                        <th style="width:50%;"><?php echo $symptomName; ?></th>
                                                        <td style="width:50%;">
                                                            <input name="symptomId[]" type="hidden" value="<?php echo $symptomId; ?>">
                                                            <select name="symptomDetected[]" id="symptomDetected<?php echo $symptomId; ?>" class="form-control <?php echo $diarrhée; ?>" title="Veuillez choisir la valeur pour <?php echo $symptomName; ?>" style="width:100%">
                                                                <option value="">-- Sélectionner --</option>
                                                                <option value='yes' <?php echo (isset($covid19SelectedSymptoms[$symptomId]['value']) && $covid19SelectedSymptoms[$symptomId]['value'] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
                                                                <option value='no' <?php echo (isset($covid19SelectedSymptoms[$symptomId]['value']) && $covid19SelectedSymptoms[$symptomId]['value'] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
                                                                <option value='unknown' <?php echo (isset($covid19SelectedSymptoms[$symptomId]['value']) && $covid19SelectedSymptoms[$symptomId]['value'] == 'unknown') ? "selected='selected'" : ""; ?>> Inconnu </option>
                                                            </select>

                                                            <br>
                                                            <?php
                                                            if ($symptomId == 13) {
                                                            ?>
                                                                <label class="diarrhée-sub" for="" style="margin-left:0;<?php echo $display; ?>">Si oui:<br> Sanglante?</label>
                                                                <select name="symptomDetails[13][]" class="form-control diarrhée-sub" style="width:100%;<?php echo $display; ?>">
                                                                    <option value="">-- Sélectionner --</option>
                                                                    <option value='yes' <?php echo (isset($covid19SelectedSymptoms[$symptomId]['sDetails'][0]) && $covid19SelectedSymptoms[$symptomId]['sDetails'][0] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
                                                                    <option value='no' <?php echo (isset($covid19SelectedSymptoms[$symptomId]['sDetails'][0]) && $covid19SelectedSymptoms[$symptomId]['sDetails'][0] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
                                                                    <option value='unknown' <?php echo (isset($covid19SelectedSymptoms[$symptomId]['sDetails'][0]) && $covid19SelectedSymptoms[$symptomId]['sDetails'][0] == 'unknown') ? "selected='selected'" : ""; ?>> Inconnu </option>
                                                                </select>
                                                                <label class="diarrhée-sub" for="" style="margin-left:0;<?php echo $display; ?>">Aqueuse?</label>
                                                                <select name="symptomDetails[13][]" class="form-control diarrhée-sub" style="width:100%;<?php echo $display; ?>">
                                                                    <option value="">-- Sélectionner --</option>
                                                                    <option value='yes' <?php echo (isset($covid19SelectedSymptoms[$symptomId]['sDetails'][1]) && $covid19SelectedSymptoms[$symptomId]['sDetails'][1] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
                                                                    <option value='no' <?php echo (isset($covid19SelectedSymptoms[$symptomId]['sDetails'][1]) && $covid19SelectedSymptoms[$symptomId]['sDetails'][1] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
                                                                    <option value='unknown' <?php echo (isset($covid19SelectedSymptoms[$symptomId]['sDetails'][1]) && $covid19SelectedSymptoms[$symptomId]['sDetails'][1] == 'unknown') ? "selected='selected'" : ""; ?>> Inconnu </option>
                                                                </select>
                                                                <label class="diarrhée-sub" for="" style="margin-left:0;<?php echo $display; ?>">Nombre De Selles Par /24h</label>
                                                                <input type="text" style="<?php echo $display; ?>" class="form-control reason-checkbox symptoms-checkbox diarrhée-sub" id="" name="symptomDetails[13][]" placeholder="Nombre de selles par /24h" title="Nombre de selles par /24h" value="<?php echo $covid19SelectedSymptoms[$symptomId]['sDetails'][2]; ?>">

                                                            <?php } ?>
                                                        </td>
                                                    </tr>
                                                <?php $index++;
                                                } ?>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="medicalHistory"></label>Antécédents Médicaux</th>
                                        <td style="width:35% !important;">
                                            <select name="medicalHistory" id="medicalHistory" class="form-control" title="Antécédents Médicaux">
                                                <option value="">--Select--</option>
                                                <option value="yes" <?php echo ($covid19Info['medical_history'] == 'yes') ? "selected='selected'" : ""; ?>>Oui</option>
                                                <option value="no" <?php echo ($covid19Info['medical_history'] == 'no') ? "selected='selected'" : ""; ?>>Non</option>
                                                <option value="unknown" <?php echo ($covid19Info['medical_history'] == 'unknown') ? "selected='selected'" : ""; ?>>Inconnu</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr class="comorbidities-row" style="<?php echo ($covid19Info['medical_history'] != 'yes') ? 'display:none' : ''; ?>">
                                        <td colspan="4">
                                            <table id="comorbiditiesTable" class="table table-bordered" aria-hidden="true" >
                                                <?php $index = 0;
                                                foreach ($covid19Comorbidities as $comorbiditiesId => $comorbiditiesName) { ?>
                                                    <tr>
                                                        <th style="width:50%;"><?php echo $comorbiditiesName; ?></th>
                                                        <td style="width:50%;">
                                                            <input name="comorbidityId[]" type="hidden" value="<?php echo $comorbiditiesId; ?>">
                                                            <select name="comorbidityDetected[]" class="form-control comorbidity-select" title="<?php echo $comorbiditiesName; ?>" style="width:100%">
                                                                <option value="">-- Sélectionner --</option>
                                                                <option value='yes' <?php echo (isset($covid19SelectedComorbidities[$comorbiditiesId]) && $covid19SelectedComorbidities[$comorbiditiesId] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
                                                                <option value='no' <?php echo (isset($covid19SelectedComorbidities[$comorbiditiesId]) && $covid19SelectedComorbidities[$comorbiditiesId] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
                                                                <option value='unknown' <?php echo (isset($covid19SelectedComorbidities[$comorbiditiesId]) && $covid19SelectedComorbidities[$comorbiditiesId] == 'unknown') ? "selected='selected'" : ""; ?>> Inconnu </option>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                <?php $index++;
                                                } ?>
                                            </table>
                                        </td>
                                    </tr>
                                </table>

                                <table class="table" aria-hidden="true" >
                                    <tr>
                                        <th style="width:15% !important"><label for="recentHospitalization"></label>Avez-vous été hospitalisé durant les 12 derniers mois ? Have you been hospitalized in the past 12 months ? </th>
                                        <td style="width:35% !important;">
                                            <select name="recentHospitalization" id="recentHospitalization" class="form-control" title="Avez-vous été hospitalisé durant les 12 derniers mois ? Have you been hospitalized in the past 12 months ? ">
                                                <option value="">--Select--</option>
                                                <option value="yes" <?php echo ($covid19Info['recent_hospitalization'] == 'yes') ? "selected='selected'" : ""; ?>>Oui</option>
                                                <option value="no" <?php echo ($covid19Info['recent_hospitalization'] == 'no') ? "selected='selected'" : ""; ?>>Non</option>
                                                <option value="unknown" <?php echo ($covid19Info['recent_hospitalization'] == 'unknown') ? "selected='selected'" : ""; ?>>Inconnu</option>
                                            </select>
                                        </td>
                                        <th style="width:15% !important"><label for="patientLivesWithChildren"></label>Habitez-vous avec les enfants ?</th>
                                        <td style="width:35% !important;">
                                            <select name="patientLivesWithChildren" id="patientLivesWithChildren" class="form-control" title="Habitez-vous avec les enfants ?">
                                                <option value="">--Select--</option>
                                                <option value="yes" <?php echo ($covid19Info['patient_lives_with_children'] == 'yes') ? "selected='selected'" : ""; ?>>Oui</option>
                                                <option value="no" <?php echo ($covid19Info['patient_lives_with_children'] == 'no') ? "selected='selected'" : ""; ?>>Non</option>
                                                <option value="unknown" <?php echo ($covid19Info['patient_lives_with_children'] == 'unknown') ? "selected='selected'" : ""; ?>>Inconnu</option>
                                            </select>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th style="width:15% !important"><label for="patientCaresForChildren"></label>Prenez-vous soins des enfants ?</th>
                                        <td style="width:35% !important;">
                                            <select name="patientCaresForChildren" id="patientCaresForChildren" class="form-control" title="prenez-vous soins des enfants ?">
                                                <option value="">--Select--</option>
                                                <option value="yes" <?php echo ($covid19Info['patient_cares_for_children'] == 'yes') ? "selected='selected'" : ""; ?>>Oui</option>
                                                <option value="no" <?php echo ($covid19Info['patient_cares_for_children'] == 'no') ? "selected='selected'" : ""; ?>>Non</option>
                                                <option value="unknown" <?php echo ($covid19Info['patient_cares_for_children'] == 'unknown') ? "selected='selected'" : ""; ?>>Inconnu</option>
                                            </select>
                                        </td>
                                        <th style="width:15% !important">Avez-vous eu des contacts étroits avec toute personne une maladie similaire a la vôtre durant ces 3 derniers semaines?</th>
                                        <td colspan="3">
                                            <select name="closeContacts" id="closeContacts" class="form-control" title="prenez-vous soins des enfants ?">
                                                <option value="yes" <?php echo ($covid19Info['close_contacts'] == 'yes') ? "selected='selected'" : ""; ?>>Oui</option>
                                                <option value="no" <?php echo ($covid19Info['close_contacts'] == 'no') ? "selected='selected'" : ""; ?>>Non</option>
                                                <option value="unknown" <?php echo ($covid19Info['close_contacts'] == 'unknown') ? "selected='selected'" : ""; ?>>Inconnu</option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">
                                        VOYAGE ET CONTACT
                                    </h3>
                                </div>
                                <table class="table" aria-hidden="true" >
                                    <tr>
                                        <th style="width: 15% !important;"><label for="hasRecentTravelHistory">Avez-vous voyagé au cours des 14 derniers jours ? </label></th>
                                        <td style="width:35% !important;">
                                            <select class="form-control" id="hasRecentTravelHistory" name="hasRecentTravelHistory" title="Avez-vous voyagé au cours des 14 derniers jours ?">
                                                <option value="">--Select--</option>
                                                <option value="yes" <?php echo ($covid19Info['has_recent_travel_history'] == 'yes') ? "selected='selected'" : ""; ?>>Oui</option>
                                                <option value="no" <?php echo ($covid19Info['has_recent_travel_history'] == 'no') ? "selected='selected'" : ""; ?>>Non</option>
                                                <option value="unknown" <?php echo ($covid19Info['has_recent_travel_history'] == 'unknown') ? "selected='selected'" : ""; ?>>Inconnu</option>
                                            </select>
                                        </td>
                                        <th style="width: 15% !important;"><label for="countryName">Si oui, dans quels pays?</label></th>
                                        <td style="width:35% !important;">
                                            <input type="text" value="<?php echo $covid19Info['travel_country_names']; ?>" class="form-control" id="countryName" name="countryName" placeholder="Si oui, dans quels pays ?" title="Si oui, dans quels pays?" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width: 15% !important;"><label for="returnDate">Date de retour</label></th>
                                        <td style="width:35% !important;">
                                            <input type="text" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['travel_return_date']); ?>" class="form-control date" id="returnDate" name="returnDate" placeholder="<?= _("Please enter date"); ?>" title="Date de retour" />
                                        </td>

                                        <th scope="row">Compagnie aérienne</th>
                                        <td><input type="text" class="form-control " value="<?php echo $covid19Info['flight_airline']; ?>" id="airline" name="airline" placeholder="Compagnie aérienne" title="Compagnie aérienne" style="width:100%;" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Numéro de siège</th>
                                        <td><input type="text" class="form-control " value="<?php echo $covid19Info['flight_seat_no']; ?>" id="seatNo" name="seatNo" placeholder="Numéro de siège" title="Numéro de siège" style="width:100%;" /></td>

                                        <th scope="row">Date et heure d'arrivée</th>
                                        <td><input type="text" class="form-control dateTime" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['flight_arrival_datetime']); ?>" id="arrivalDateTime" name="arrivalDateTime" placeholder="Date et heure d'arrivée" title="Date et heure d'arrivée" style="width:100%;" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Aeroport DE DEPART</th>
                                        <td><input type="text" class="form-control" value="<?php echo $covid19Info['flight_airport_of_departure']; ?>" id="airportOfDeparture" name="airportOfDeparture" placeholder="Aeroport DE DEPART" title="Aeroport DE DEPART" style="width:100%;" /></td>

                                        <th scope="row">Transit</th>
                                        <td><input type="text" class="form-control" value="<?php echo $covid19Info['flight_transit']; ?>" id="transit" name="transit" placeholder="Transit" title="Transit" style="width:100%;" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Raison de la visite (le cas échéant)</th>
                                        <td><input type="text" class="form-control" value="<?php echo $covid19Info['reason_of_visit']; ?>" id="reasonOfVisit" name="reasonOfVisit" placeholder="Raison de la visite (le cas échéant)" title="Raison de la visite (le cas échéant)" style="width:100%;" /></td>

                                        <th scope="row">Occupation du patient</th>
                                        <td>
                                            <input class="form-control" value="<?php echo $covid19Info['patient_occupation']; ?>" type="text" name="patientOccupation" id="patientOccupation" placeholder="Occupation du patient" title="Occupation du patient" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width: 15% !important;"><label for="hasRecentTravelHistory">Patiend fume-t-il? </label></th>
                                        <td style="width:35% !important;">
                                            <select class="form-control" id="doesPatientSmoke" name="doesPatientSmoke" title="Patiend fume-t-il?">
                                                <option value="">--Select--</option>
                                                <option value="yes" <?php echo ($covid19Info['does_patient_smoke'] == 'yes') ? "selected='selected'" : ""; ?>>Oui</option>
                                                <option value="no" <?php echo ($covid19Info['does_patient_smoke'] == 'no') ? "selected='selected'" : ""; ?>>Non</option>
                                                <option value="unknown" <?php echo ($covid19Info['does_patient_smoke'] == 'unknown') ? "selected='selected'" : ""; ?>>Inconnu</option>
                                            </select>
                                        </td>
                                        <th scope="row"></th>
                                        <td></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php if ($_SESSION['instanceType'] != 'remoteuser') { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Réservé à une utilisation en laboratoire </h3>
                                    </div>
                                    <table class="table" aria-hidden="true"  style="width:100%">
                                        <tr>
                                            <th scope="row"><label for="">Date de réception de l'échantillon </label></th>
                                            <td>
                                                <input type="text" class="form-control" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Date de réception de l'échantillon" value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['sample_received_at_vl_lab_datetime']) ?>" onchange="" style="width:100%;" />
                                            </td>
                                            <th scope="row"><label for="sampleCondition">Condition de l'échantillon</label></th>
                                            <td>
                                                <select class="form-control" name="sampleCondition" id="sampleCondition" title="Condition de l'échantillon">
                                                    <option value=''> -- Sélectionner -- </option>
                                                    <option value="adequate" <?php echo ($covid19Info['sample_condition'] == 'adequate') ? "selected='selected'" : ""; ?>> Adéquat </option>
                                                    <option value="not-adequate" <?php echo ($covid19Info['sample_condition'] == 'not-adequate') ? "selected='selected'" : ""; ?>> Non Adéquat </option>
                                                    <option value="autres" <?php echo ($covid19Info['sample_condition'] == 'autres') ? "selected='selected'" : ""; ?>> Autres </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="lab-show"><label for="labId">Nom du laboratoire</label> </td>
                                            <td class="lab-show">
                                                <select name="labId" id="labId" class="form-control" title="Nom du laboratoire" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, $covid19Info['lab_id'], '-- Sélectionner --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row">L'échantillon est-il rejeté?</th>
                                            <td>
                                                <select class="form-control result-focus" name="isSampleRejected" id="isSampleRejected" title="L'échantillon est-il rejeté?">
                                                    <option value=''> -- Sélectionner -- </option>
                                                    <option value="yes" <?php echo ($covid19Info['is_sample_rejected'] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
                                                    <option value="no" <?php echo ($covid19Info['is_sample_rejected'] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th class="show-rejection" style="display:none;">Raison du rejet</th>
                                            <td class="show-rejection" style="display:none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="Raison du rejet">
                                                    <option value="">-- Sélectionner --</option>
                                                    <?php foreach ($rejectionTypeResult as $type) { ?>
                                                        <optgroup label="<?php echo ($type['rejection_type']); ?>">
                                                            <?php
                                                            foreach ($rejectionResult as $reject) {
                                                                if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                                    <option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($covid19Info['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ($reject['rejection_reason_name']); ?></option>
                                                            <?php }
                                                            } ?>
                                                        </optgroup>
                                                    <?php }  ?>
                                                </select>
                                            </td>
                                            <th class="show-rejection" style="display: none;">Date de rejet<span class="mandatory">*</span></th>
                                            <td class="show-rejection" style="display: none;"><input value="<?php echo DateUtils::humanReadableDateFormat($covid19Info['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Date de rejet" title="Date de rejet" /></td>
                                        </tr>

                                        <tr>
                                            <td colspan="4">
                                                <table class="table table-bordered table-striped" aria-hidden="true"  id="testNameTable">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center">Test non</th>
                                                            <th class="text-center">Nom du Testkit (ou) Méthode de test utilisée</th>
                                                            <th class="text-center">Date de l'analyse</th>
                                                            <th class="text-center">Résultat du test</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="testKitNameTable">
                                                        <?php if (isset($covid19TestInfo) && !empty($covid19TestInfo)) {
                                                            $testMethod = array("PCR/RT-PCR", "RdRp-SARS Cov-2", "GeneXpert", "Rapid Antigen Test", "other");
                                                            foreach ($covid19TestInfo as $indexKey => $rows) { ?>
                                                                <tr>
                                                                    <td class="text-center"><?= ($indexKey + 1); ?><input type="hidden" name="testId[]" value="<?php echo base64_encode($rows['test_id']); ?>"></td>
                                                                    <td>
                                                                        <select onchange="changeDRCTestName(this.value,<?= ($indexKey + 1); ?>)" class="form-control test-name-table-input" id="testName<?= ($indexKey + 1); ?>" name="testName[]" title="Veuillez saisir le nom du test pour les lignes <?= ($indexKey + 1); ?>">
                                                                            <option value="">--Select--</option>
                                                                            <option value="PCR/RT-PCR" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'PCR/RT-PCR') ? "selected='selected'" : ""; ?>>PCR/RT-PCR</option>
                                                                            <option value="RdRp-SARS Cov-2" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'RdRp-SARS Cov-2') ? "selected='selected'" : ""; ?>>RdRp-SARS Cov-2</option>
                                                                            <option value="GeneXpert" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'GeneXpert') ? "selected='selected'" : ""; ?>>GeneXpert</option>
                                                                            <option value="Rapid Antigen Test" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'Rapid Antigen Test') ? "selected='selected'" : ""; ?>>Rapid Antigen Test</option>
                                                                            <option value="other" <?php echo (isset($rows['test_name']) && $rows['test_name'] == 'other') ? "selected='selected'" : ""; ?>>Others</option>
                                                                        </select>
                                                                        <?php
                                                                        $value = '';
                                                                        if (!in_array($rows['test_name'], $testMethod)) {
                                                                            $value = 'value="' . $rows['test_name'] . '"';
                                                                            $show =  "block";
                                                                        } else {
                                                                            $show =  "none";
                                                                        } ?>
                                                                        <input <?php echo $value; ?> type="text" name="testNameOther[]" id="testNameOther<?= ($indexKey + 1); ?>" class="form-control testInputOther<?= ($indexKey + 1); ?>" title="Veuillez saisir le nom du test pour les lignes <?= ($indexKey + 1); ?>" placeholder="Entrez le nom du test <?= ($indexKey + 1); ?>" style="display: <?php echo $show; ?>;margin-top: 10px;" />
                                                                    </td>
                                                                    <!-- <td><input type="text" value="<?php echo $rows['test_name']; ?>" name="testName[]" id="testName<?= ($indexKey + 1); ?>" class="form-control test-name-table-input" placeholder="Nom du test" title="Veuillez saisir le nom du test pour la ligne<?= ($indexKey + 1); ?>" /></td> -->
                                                                    <td><input type="text" value="<?php echo DateUtils::humanReadableDateFormat($rows['sample_tested_datetime']); ?>" name="testDate[]" id="testDate<?= ($indexKey + 1); ?>" class="form-control test-name-table-input dateTime" placeholder="Testé sur" title="Veuillez sélectionner la Date de l analyse pour la ligne <?= ($indexKey + 1); ?>" /></td>
                                                                    <td><select class="form-control test-result test-name-table-input result-focus" name="testResult[]" id="testResult<?= ($indexKey + 1); ?>" title="Veuillez sélectionner le résultat pour la ligne<?= ($indexKey + 1); ?>">
                                                                            <option value=''> -- Sélectionner -- </option>
                                                                            <?php foreach ($covid19Results as $c19ResultKey => $c19ResultValue) { ?>
                                                                                <option value="<?php echo $c19ResultKey; ?>" <?php echo ($rows['result'] == $c19ResultKey) ? "selected='selected'" : ""; ?>> <?php echo $c19ResultValue; ?> </option>
                                                                            <?php } ?>
                                                                        </select>
                                                                    </td>
                                                                    <td style="vertical-align:middle;text-align: center;">
                                                                        <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
                                                                        <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);deleteRow('<?php echo base64_encode($rows['test_id']); ?>');"><em class="fa-solid fa-minus"></em></a>
                                                                    </td>
                                                                </tr>
                                                            <?php }
                                                        } else { ?>
                                                            <tr>
                                                                <td class="text-center">1</td>
                                                                <td>
                                                                    <select onchange="changeDRCTestName(this.value,1)" class="form-control test-name-table-input" id="testName1" name="testName[]" title="Veuillez saisir le nom du test pour les lignes 1">
                                                                        <option value="">--Select--</option>
                                                                        <option value="PCR/RT-PCR">PCR/RT-PCR</option>
                                                                        <option value="RdRp-SARS Cov-2">RdRp-SARS Cov-2</option>
                                                                        <option value="GeneXpert">GeneXpert</option>
                                                                        <option value="Rapid Antigen Test">Rapid Antigen Test</option>
                                                                        <option value="other">Others</option>
                                                                    </select>
                                                                    <input type="text" name="testNameOther[]" id="testNameOther1" class="form-control testInputOther1" title="Veuillez saisir le nom du test pour les lignes 1" placeholder="Entrez le nom du test 1" style="display: none;margin-top: 10px;" />
                                                                </td>
                                                                <!-- <td><input type="text" name="testName[]" id="testName1" class="form-control test-name-table-input" placeholder="Nom du test" title="Veuillez saisir le nom du test pour les lignes 1" /></td> -->
                                                                <td><input type="text" name="testDate[]" id="testDate1" class="form-control test-name-table-input dateTime" placeholder="Testé sur" title="Veuillez saisir le test pour la ligne 1" /></td>
                                                                <td><select class="form-control test-result test-name-table-input" name="testResult[]" id="testResult1" title="Veuillez sélectionner le résultat pour la ligne 1">
                                                                        <option value=''> -- Sélectionner -- </option>
                                                                        <?php foreach ($covid19Results as $c19ResultKey => $c19ResultValue) { ?>
                                                                            <option value="<?php echo $c19ResultKey; ?>"> <?php echo $c19ResultValue; ?> </option>
                                                                        <?php } ?>
                                                                    </select>
                                                                </td>
                                                                <td style="vertical-align:middle;text-align: center;">
                                                                    <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
                                                                    <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
                                                                </td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <th colspan="3" class="text-right">Résultat final</th>
                                                            <td>
                                                                <select class="form-control result-focus" name="result" id="result" title="Résultat final">
                                                                    <option value=''> -- Sélectionner -- </option>
                                                                    <?php foreach ($covid19Results as $c19ResultKey => $c19ResultValue) { ?>
                                                                        <option value="<?php echo $c19ResultKey; ?>" <?php echo ($covid19Info['result'] == $c19ResultKey) ? "selected='selected'" : ""; ?>> <?php echo $c19ResultValue; ?> </option>
                                                                    <?php } ?>
                                                                </select>
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </td>
                                        </tr>
                                        <?php $otherDiseases = (isset($covid19Info['result']) && $covid19Info['result'] != 'positive') ? 'display' : 'none'; ?>
                                        <tr>
                                            <th class="other-diseases" style="display: <?php echo $otherDiseases; ?>;"><label for="otherDiseases">Autres maladies<span class="mandatory">*</span></label></th>
                                            <td class="other-diseases" style="display: <?php echo $otherDiseases; ?>;">
                                                <select name="otherDiseases" id="otherDiseases" class="form-control" title="Autres maladies">
                                                    <option value="">--Select--</option>
                                                    <optgroup label="Coronavirus">
                                                        <option value="E-Sars-CoV" <?php echo ($covid19Info['other_diseases'] == 'E-Sars-CoV') ? "selected='selected'" : ""; ?>>E-Sars-CoV</option>
                                                        <option value="N-Sars-Cov" <?php echo ($covid19Info['other_diseases'] == 'N-Sars-Cov') ? "selected='selected'" : ""; ?>>N-Sars-Cov</option>
                                                        <option value="Other respiratory pathogens" <?php echo ($covid19Info['other_diseases'] == 'Other respiratory pathogens') ? "selected='selected'" : ""; ?>>Autres Pathogens Respiratories</option>
                                                        <option value="Other Coronavirus" <?php echo ($covid19Info['other_diseases'] == 'Other Coronavirus') ? "selected='selected'" : ""; ?>>Autres Coronavirus</option>
                                                    </optgroup>
                                                    <optgroup label="Influenza">
                                                        <option value="A/H1N1pdm09" <?php echo ($covid19Info['other_diseases'] == 'A/H1N1pdm09') ? "selected='selected'" : ""; ?>>A/H1N1pdm09</option>
                                                        <option value="A/H3N2" <?php echo ($covid19Info['other_diseases'] == 'A/H3N2') ? "selected='selected'" : ""; ?>>A/H3N2</option>
                                                        <option value="A/H5N1" <?php echo ($covid19Info['other_diseases'] == 'A/H5N1') ? "selected='selected'" : ""; ?>>A/H5N1</option>
                                                        <option value="B/Yan" <?php echo ($covid19Info['other_diseases'] == 'B/Yan') ? "selected='selected'" : ""; ?>>B/Yan</option>
                                                        <option value="B/Vic" <?php echo ($covid19Info['other_diseases'] == 'B/Vic') ? "selected='selected'" : ""; ?>>B/Vic</option>
                                                    </optgroup>
                                                </select>
                                            </td>
                                            <th class="change-reason" style="display: none;">Raison du changement<span class="mandatory">*</span></td>
                                            <td class="change-reason" style="display: none;"><textarea type="text" name="reasonForChanging" id="reasonForChanging" class="form-control date" placeholder="Raison du changement" title="Raison du changement"></textarea></td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Revu le</td>
                                            <td><input type="text" value="<?php echo $covid19Info['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control" placeholder="Revu par" title="Please enter the Revu par" /></td>
                                            <th scope="row">Revu par</th>
                                            <td>
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose Revu par" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, $covid19Info['result_reviewed_by'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Le résultat est-il autorisé?</th>
                                            <td>
                                                <select name="isResultAuthorized" id="isResultAuthorized" class="form-control" title="Le résultat est-il autorisé?" style="width:100%">
                                                    <option value="">-- Sélectionner --</option>
                                                    <option value='yes' <?php echo ($covid19Info['is_result_authorised'] == 'yes') ? "selected='selected'" : ""; ?>> Oui </option>
                                                    <option value='no' <?php echo ($covid19Info['is_result_authorised'] == 'no') ? "selected='selected'" : ""; ?>> Non </option>
                                                </select>
                                            </td>
                                            <th scope="row">Approuvé par</th>
                                            <td>
                                                <select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose Approuvé par" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($labTechniciansResults, $covid19Info['result_approved_by'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Approuvé le</th>
                                            <td>
                                                <input type="text" name="approvedOn" id="approvedOn" value="<?php echo date('d-M-Y H:i:s', strtotime($covid19Info['result_approved_datetime'])); ?>" class="dateTime disabled-field form-control" placeholder="Approuvé le" title="Please enter the Approuvé le" />
                                            </td>
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
                        <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
                            <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo (isset($sFormat) && $sFormat != '') ? $sFormat : ''; ?>" />
                            <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo (isset($sKey) && $sKey != '') ? $sKey : ''; ?>" />
                        <?php } ?>
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                        <input type="hidden" name="revised" id="revised" value="no" />
                        <input type="hidden" name="formId" id="formId" value="7" />
                        <input type="hidden" name="deletedRow" id="deletedRow" value="" />
                        <input type="hidden" name="patientCodePrefix" id="patientCodePrefix" value="<?= $patientCodePrefix; ?>" />
                        <input type="hidden" name="patientCodeKey" id="patientCodeKey" value="<?= $patientCodeKey; ?>" />
                        <input type="hidden" name="covid19SampleId" id="covid19SampleId" value="<?php echo $covid19Info['covid19_id']; ?>" />
                        <input type="hidden" name="sampleCodeCol" id="sampleCodeCol" value="<?php echo $arr['sample_code']; ?>" />
                        <input type="hidden" name="oldStatus" id="oldStatus" value="<?php echo $covid19Info['result_status']; ?>" />
                        <input type="hidden" name="provinceCode" id="provinceCode" />
                        <input type="hidden" name="provinceId" id="provinceId" />
                        <a href="/covid-19/requests/covid-19-requests.php" class="btn btn-default"> Cancel</a>
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

<div class="modal" id="addFacility">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Add Facility</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <!-- Modal body -->
            <div class="modal-body">
                <form class="form-horizontal" method='post' name='addFacilityForm' id='addFacilityForm' autocomplete="off" enctype="multipart/form-data">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="facilityName" class="col-lg-4 control-label">Facility Name <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control isRequired" id="facilityName" name="facilityName" placeholder="Facility Name" title="Please enter facility name" onblur="checkNameValidation('facility_details','facility_name',this,null,'The facility name that you entered already exists.Enter another name',null)" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="facilityCode" class="col-lg-4 control-label">Facility Code</label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="facilityCode" name="facilityCode" placeholder="Facility Code" title="Please enter facility code" onblur="checkNameValidation('facility_details','facility_code',this,null,'The code that you entered already exists.Try another code',null)" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="otherId" class="col-lg-4 control-label">Other Id </label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="otherId" name="otherId" placeholder="Other Id" />
                                        <input type="hidden" class="form-control isRequired" id="facilityType" name="facilityType" value="1" title="Please select facility type" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="col-lg-4 control-label">Email(s) </label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="email" name="email" placeholder="eg-email1@gmail.com,email2@gmail.com" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testingPoints" class="col-lg-4 control-label">Testing Point(s)<br> <small>(comma separated)</small> </label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="testingPoints" name="testingPoints" placeholder="eg. VCT, PMTCT" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contactPerson" class="col-lg-4 control-label">Contact Person</label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="contactPerson" name="contactPerson" placeholder="Contact Person" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="state" class="col-lg-4 control-label">Province/State <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select name="state" id="state" class="form-control isRequired" title="Please choose province/state">
                                            <?= $general->generateSelectOptions($geoLocationParentArray, null, '-- Select --'); ?>
                                            <option value="other">Other</option>
                                        </select>
                                        <input type="text" class="form-control" name="provinceNew" id="provinceNew" placeholder="Enter Province/State" title="Please enter province/state" style="margin-top:4px;display:none;" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phoneNo" class="col-lg-4 control-label">Phone Number</label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control forceNumeric" id="phoneNo" name="phoneNo" placeholder="Phone Number" onblur="checkNameValidation('facility_details','facility_mobile_numbers',this,null,'The mobile no that you entered already exists.Enter another mobile no.',null)" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="hubName" class="col-lg-4 control-label">Linked Hub Name (If Applicable)</label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="hubName" name="hubName" placeholder="Hub Name" title="Please enter hub name" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="districtFacility" class="col-lg-4 control-label">District/County <span class="mandatory">*</span></label>
                                    <div class="col-lg-7">
                                        <select name="district" id="districtFacility" class="form-control isRequired" title="Please choose District/County">
                                            <option value="">-- Select --</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="country" class="col-lg-4 control-label">Country</label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control" id="country" name="country" placeholder="Country" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="address" class="col-lg-4 control-label">Address</label>
                                    <div class="col-lg-7">
                                        <textarea class="form-control" name="address" id="address" placeholder="Address"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="latitude" class="col-lg-4 control-label">Latitude</label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control forceNumeric" id="latitude" name="latitude" placeholder="Latitude" title="Please enter latitude" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="longitude" class="col-lg-4 control-label">Longitude</label>
                                    <div class="col-lg-7">
                                        <input type="text" class="form-control forceNumeric" id="longitude" name="longitude" placeholder="Longitude" title="Please enter longitude" />
                                        <input type="hidden" name="reqForm" id="reqForm" value="1" />
                                        <input type="hidden" name="headerText" id="headerText" />
                                        <input type="hidden" name="testType[]" id="testType" value="covid19" />
                                        <input type="hidden" name="selectedUser[]" id="selectedUser" />
                                        <input type="hidden" name="fromAPI" id="fromAPI" value="yes" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- /.box-body -->
                        <div class="box-footer">

                        </div>
                        <!-- /.box-footer -->
                </form>
            </div>
            <!-- Modal footer -->
            <div class="modal-footer">
                <a class="btn btn-primary" href="javascript:void(0);" onclick="addFacility();">Submit</a>
                <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>
<script type="text/javascript">
    changeProvince = true;
    changeFacility = true;
    provinceName = true;
    facilityName = true;
    machineName = true;
    tableRowId = <?php echo (isset($covid19TestInfo) && count($covid19TestInfo) > 0) ? (count($covid19TestInfo) + 1) : 2; ?>;
    deletedRow = [];

    function addFacility() {
        flag = deforayValidator.init({
            formId: 'addFacilityForm'
        });
        if (flag) {
            $.ajax({
                type: 'POST',
                url: '/facilities/addFacilityHelper.php',
                data: $('#addFacilityForm').serialize(),
                success: function() {
                    alert('Facility details added successfully');
                    $('#addFacility').modal('hide');
                    getfacilityDistrictwise('');
                }
            });
        }
    }

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
                    testType: 'covid19'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#facilityId").html(details[0]);
                        $("#district").html(details[1]);
                        $("#clinicianName").val(details[2]);
                    }
                });
            //}
            sampleCodeGeneration();
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
        patientArray = JSON.parse(pDetails); 
        //   console.log(patientArray);
        $("#patientProvince").val(patientArray['geo_name']);
        $("#firstName").val(patientArray['firstname']);
        $("#lastName").val(patientArray['lastname']);
        $("#patientPhoneNumber").val(patientArray['patient_phone_number']);
        $("#patientGender").val(patientArray['gender']);
        $("#patientAge").val(patientArray['age']);
        $("#patientDob").val(patientArray['dob']);
        $("#patientId").val(patientArray['patient_id']);
        $("#patientAddress").text(patientArray['patient_address']);
        $("#patientNationality").val(patientArray['patient_nationality']).trigger('change');
        $("#isPatientPregnant").val(patientArray['is_patient_pregnant']);
        $("#patientCodePrefix").val("");
        $("#patientCodeKey").val("");
        setTimeout(function() {
            $("#patientDistrict").val(patientArray['patient_district']).trigger('change');
        }, 3000);
    }

    function sampleCodeGeneration() {
        var pName = $("#province").val();
        var sDate = $("#sampleCollectionDate").val();
        if (pName != '' && sDate != '') {
            $.post("/covid-19/requests/generateSampleCode.php", {
                    sDate: sDate,
                    pName: pName
                },
                function(data) {
                    var sCodeKey = JSON.parse(data);
                    $("#sampleCode").val(sCodeKey.sampleCode);
                    $("#sampleCodeInText").html(sCodeKey.sampleCodeInText);
                    $("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
                    $("#sampleCodeKey").val(sCodeKey.sampleCodeKey);
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
                    testType: 'covid19'
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
                    testType: 'covid19'
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
        if ($("#patientDob").val() == "" && $("#patientAge").val() == "") {
            alert("Please select or enter patient DOB or Age");
            return false;
        }
        if ($('#isResultAuthorized').val() != "yes") {
            $('#approvedBy,#approvedOn').removeClass('isRequired');
        }
        /* if ($('#medicalHistory').val() == "yes") {
            if ($('.comorbidity-select :selected').length > 0) {
                alert("Veuillez sélectionner au moins une option sous Antécédents médicaux");
                return false;
            }
        } */
        if ($('input[name="symptom[]"]:checked').length > 0) {
            alert("Veuillez sélectionner au moins une option sous Symptômes");
            return false;
        }
        $("#provinceCode").val($("#province").find(":selected").attr("data-code"));
        $("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
        flag = deforayValidator.init({
            formId: 'editCovid19RequestForm'
        });
        if (flag) {
            document.getElementById('editCovid19RequestForm').submit();
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
        $('.result-focus').change(function(e) {
            var status = false;
            $(".result-focus").each(function(index) {
                if ($(this).val() != "") {
                    status = true;
                }
            });
            if (status) {
                $('.change-reason').show();
                $('#reasonForChanging').addClass('isRequired');
            } else {
                $('.change-reason').hide();
                $('#reasonForChanging').removeClass('isRequired');
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
        $('#patientNationality').select2({
            placeholder: "Nationalité du patient"
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
        <?php if (isset($covid19Info['mother_treatment']) && in_array('Other', $covid19Info['mother_treatment'])) { ?>
            $('#motherTreatmentOther').prop('disabled', false);
        <?php } ?>

        <?php if (isset($covid19Info['mother_vl_result']) && !empty($covid19Info['mother_vl_result'])) { ?>
            updateMotherViralLoad();
        <?php } ?>

        $('.diarrhée').change(function(e) {
            if (this.value == "yes") {
                $('.diarrhée-sub').show();
            } else {
                $('.diarrhée-sub').hide();
                $('.diarrhée-sub').val('');
            }
        });

        $('#medicalHistory').change(function(e) {
            if ($(this).val() == "yes") {
                $('.comorbidities-row').show();
            } else {
                $('.comorbidities-row').hide();
            }
        });

        $("#motherViralLoadCopiesPerMl").on("change keyup paste", function() {
            var motherVl = $("#motherViralLoadCopiesPerMl").val();
            //var motherVlText = $("#motherViralLoadText").val();
            if (motherVl != '') {
                $("#motherViralLoadText").val('');
            }
        });
        $('#isResultAuthorized').change(function(e) {
            checkIsResultAuthorized();
        });

        <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            $('.test-result,#result').change(function(e) {
                checkPostive();
            });
            checkPostive();
        <?php } ?>

        checkIsResultAuthorized();

        <?php $index = 0;
        if (isset($covid19Symptoms) && count($covid19Symptoms) > 0) {
            foreach ($covid19Symptoms as $symptomId => $symptomName) {
                if ($covid19SelectedSymptoms[$symptomId] == "yes") { ?>
                    checkSubSymptoms($('#symptom<?php echo $symptomId; ?>').val(), <?php echo $symptomId; ?>, <?php echo $index; ?>);
        <?php }
                $index++;
            }
        } ?>

        $("#state").change(function() {
            $.blockUI();
            var pName = $(this).val();
            if ($.trim(pName) != '') {
                $.post("/includes/siteInformationDropdownOptions.php", {
                        pName: pName,
                    },
                    function(data) {
                        if (data != "") {
                            details = data.split("###");
                            console.log(details);
                            $("#districtFacility").html(details[1]);
                            $("#districtFacility").append('<option value="other">Other</option>');
                        }
                    });
            }
            $.unblockUI();
        });

        $("#patientProvince").select2({
            placeholder: "Entrez le province du patient",
            minimumInputLength: 0,
            width: '100%',
            allowClear: true,
            ajax: {
                placeholder: "Tapez le nom du province à rechercher",
                url: "/covid-19/requests/get-province-district-list.php",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        type: 'province',
                        q: params.term, // search term
                        page: params.page
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

        $("#patientProvince").change(function() {
            $.blockUI();
            var pName = $(this).val();
            if ($.trim(pName) != '') {
                $.post("/covid-19/requests/get-province-district-list.php", {
                        pName: pName,
                    },
                    function(data) {
                        if (data != "") {
                            $("#patientZone").html(data);
                        }
                    });
            }
            $.unblockUI();
        });

        $("#patientDistrict").select2({
            placeholder: "Entrez le Zone de Santé du patient",
            minimumInputLength: 0,
            width: '100%',
            allowClear: true,
            ajax: {
                placeholder: "Tapez le Zone de Santé à rechercher",
                url: "/covid-19/requests/get-province-district-list.php",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        zName: $("#patientZone").val(),
                        type: 'district',
                        q: params.term, // search term
                        page: params.page
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

        $("#patientZone").select2({
            placeholder: "Entrez le commune du patient",
            minimumInputLength: 0,
            width: '100%',
            allowClear: true,
            ajax: {
                placeholder: "Tapez le nom du commune à rechercher",
                url: "/covid-19/requests/get-province-district-list.php",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        pName: $("#patientProvince").val(),
                        type: 'zone',
                        q: params.term, // search term
                        page: params.page
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

        $("#patientZone").change(function() {
            $.blockUI();
            var zName = $(this).val();
            if ($.trim(zName) != '') {
                $.post("/covid-19/requests/get-province-district-list.php", {
                        zName: zName,
                    },
                    function(data) {
                        if (data != "") {
                            $("#patientDistrict").html(data);
                        }
                    });
            }
            $.unblockUI();
        });
    });

    function checkSubSymptoms(obj, parent, row, sub = "") {
        if ($(obj).val() == 'yes') {
            $.post("getSymptomsByParentId.php", {
                    symptomParent: parent,
                    covid19Id: <?php echo $covid19Info['covid19_id']; ?>
                },
                function(data) {
                    if (data != "") {
                        if ($('.hide-symptoms').hasClass('symptomRow' + parent)) {
                            $('.symptomRow' + parent).remove();
                        }
                        $(".row" + row).after(data);
                    }
                });
        } else {

            $('.symptomRow' + parent).remove();
        }
    }

    function addTestRow() {
        let rowString = `<tr>
                    <td class="text-center">${tableRowId}</td>
                    <td>
                    <select onchange="otherCovidTestName(this.value,${tableRowId})" class="form-control test-name-table-input" id="testName${tableRowId}" name="testName[]" title="Please enter the name of the Testkit (or) Test Method used">
                    <option value="">--Select--</option>
                    <option value="PCR/RT-PCR">PCR/RT-PCR</option>
                    <option value="RdRp-SARS Cov-2">RdRp-SARS Cov-2</option>
                    <option value="GeneXpert">GeneXpert</option>
                    <option value="Rapid Antigen Test">Rapid Antigen Test</option>
                    <option value="other">Others</option>
                </select>
                <input type="text" name="testNameOther[]" id="testNameOther${tableRowId}" class="form-control testInputOther' + tableRowId + '" title="Please enter the name of the Testkit (or) Test Method used" placeholder="Please enter the name of the Testkit (or) Test Method used" style="display: none;margin-top: 10px;" />
            </td>
            <td><input type="text" name="testDate[]" id="testDate${tableRowId}" class="form-control test-name-table-input dateTime" placeholder="Tested on" title="Please enter the tested on for row ${tableRowId}" /></td>
            <td>
                <select class="form-control test-result test-name-table-input" name="testResult[]" id="testResult${tableRowId}" title="Please select the result"><?= $general->generateSelectOptions($covid19Results, null, '-- Sélectionner --'); ?></select>
            </td>
            <td style="vertical-align:middle;text-align: center;">
                <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow(this);"><em class="fa-solid fa-plus"></em></a>&nbsp;
                <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
            </td>
        </tr>`;

        $("#testKitNameTable").append(rowString);

        $('.dateTime').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            maxDate: "Today",
            onChangeMonthYear: function(year, month, widget) {
                setTimeout(function() {
                    $('.ui-datepicker-calendar').show();
                });
            },
            yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });
        tableRowId++;

        <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            $('.test-result,#result').change(function(e) {
                checkPostive();
            });
        <?php } ?>
        showcomorbidities();
    }

    function removeTestRow(el) {
        $(el).fadeOut("slow", function() {
            el.parentNode.removeChild(el);
            rl = document.getElementById("testKitNameTable").rows.length;
            if (rl == 0) {
                tableRowId = 0;
                addTestRow();
            }
        });
    }

    function deleteRow(id) {
        deletedRow.push(id);
        $('#deletedRow').val(deletedRow);
    }

    function checkPostive() {
        var itemLength = document.getElementsByName("testResult[]");
        for (i = 0; i < itemLength.length; i++) {

            if (itemLength[i].value == 'positive') {
                $('.disabled-field').val('');
                $('.disabled-field').prop('disabled', true);
                $('.disabled-field').addClass('disabled');
                $('.disabled-field').removeClass('isRequired');
                return false;
            } else {
                $('.disabled-field').prop('disabled', false);
                $('.disabled-field').removeClass('disabled');
                $('.disabled-field').addClass('isRequired');
            }
            if (itemLength[i].value != '') {
                $('#labId').addClass('isRequired');
            }
        }
    }

    function checkIsResultAuthorized() {
        if ($('#isResultAuthorized').val() == 'yes') {
            $('#approvedBy,#approvedOn').prop('disabled', false);
            $('#approvedBy,#approvedOn').removeClass('disabled');
            $('#approvedBy,#approvedOn').addClass('isRequired');
        } else if ($('#isResultAuthorized').val() == 'no') {
            $('#approvedOn').val('');
            $('#approvedBy').val(null).trigger('change');
            $('#approvedBy,#approvedOn').prop('disabled', true);
            $('#approvedBy,#approvedOn').addClass('disabled');
            $('#approvedBy,#approvedOn').removeClass('isRequired');
        }
        if ($('#isResultAuthorized').val() == '') {
            $('#approvedOn').val('');
            $('#approvedBy').val(null).trigger('change');
            $('#approvedBy,#approvedOn').prop('disabled', false);
            $('#approvedBy,#approvedOn').removeClass('disabled');
        }
    }

    function changeDRCTestName(val, id) {
        if (val == 'other') {
            $('.testInputOther' + id).show();
        } else {
            $('.testInputOther' + id).hide();
        }
    }

    function checkSubReason(obj, show) {
        $('.checkbox').prop("checked", false);
        if ($(obj).prop("checked", true)) {
            $('.' + show).show();
            $('.' + show).removeClass('hide-reasons');
            $('.hide-reasons').hide();
            $('.' + show).addClass('hide-reasons');
        }
    }

    function asymptomaticFn(value) {
        if (value == "yes") {
            $(".symptoms").hide();
            $(".symptomSpecificFields").removeClass('isRequired');
            $(".symptomMandatoryLabel").hide();
        } else {
            $(".symptoms").show();
            $(".symptomSpecificFields").addClass('isRequired');
            $(".symptomMandatoryLabel").show();
        }
    }
</script>