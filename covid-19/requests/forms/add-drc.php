<?php
// imported in covid-19-add-request.php based on country in global config

ob_start();

//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);

//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);


// $configQuery = "SELECT * from global_config";
// $configResult = $db->query($configQuery);
// $arr = array();
// $prefix = $arr['sample_code_prefix'];

// Getting the list of Provinces, Districts and Facilities

$covid19Obj = new \Vlsm\Models\Covid19($db);


$covid19Results = $covid19Obj->getCovid19Results();
$specimenTypeResult = $covid19Obj->getCovid19SampleTypes();
$covid19ReasonsForTesting = $covid19Obj->getCovid19ReasonsForTestingDRC();
$covid19Symptoms = $covid19Obj->getCovid19SymptomsDRC();
$covid19Comorbidities = $covid19Obj->getCovid19Comorbidities();


$rKey = '';
$sKey = '';
$sFormat = '';
$pdQuery = "SELECT * from province_details";
if ($sarr['user_type'] == 'remoteuser') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    //check user exist in user_facility_map table
    $chkUserFcMapQry = "SELECT user_id FROM vl_user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
    $chkUserFcMapResult = $db->query($chkUserFcMapQry);
    if ($chkUserFcMapResult) {
        $pdQuery = "SELECT * FROM province_details as pd JOIN facility_details as fd ON fd.facility_state=pd.province_name JOIN vl_user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where user_id='" . $_SESSION['userId'] . "' group by province_name";
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
    $province .= "<option data-code='" . $provinceName['province_code'] . "' data-province-id='" . $provinceName['province_id'] . "' data-name='" . $provinceName['province_name'] . "' value='" . $provinceName['province_name'] . "##" . $provinceName['province_code'] . "'>" . ucwords($provinceName['province_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, null, '-- Sélectionner --');

?>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><i class="fa fa-edit"></i> COVID-19 VIRUS LABORATORY TEST DRC REQUEST FORM</h1>
        <ol class="breadcrumb">
            <li><a href="/"><i class="fa fa-dashboard"></i> Accueil</a></li>
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
                <form class="form-horizontal" method="post" name="addCovid19RequestForm" id="addCovid19RequestForm" autocomplete="off" action="covid-19-add-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">INFORMATIONS SUR LE SITE</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">À remplir par le clinicien / infirmier demandeur</h3>
                                </div>
                                <table class="table" style="width:100%">
                                    <tr>
                                        <?php if ($sarr['user_type'] == 'remoteuser') { ?>
                                            <td><label for="sampleCode">Échantillon ID </label></td>
                                            <td>
                                                <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
                                                <input type="hidden" id="sampleCode" name="sampleCode" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode">Échantillon ID </label><span class="mandatory">*</span></td>
                                            <td>
                                                <input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" readonly="readonly" placeholder="Échantillon ID" title="Échantillon ID" style="width:100%;" onchange="checkSampleNameValidation('form_covid19','<?php echo $sampleCode; ?>',this.id,null,'The sample id that you entered already exists. Please try another sample id',null)" />
                                            </td>
                                        <?php } ?>
                                        <th><label for="testNumber">Prélévement</label></th>
                                        <td>
                                            <select class="form-control" name="testNumber" id="testNumber" title="Prélévement" style="width:100%;">
                                                <option value="">--Select--</option>
                                                <?php foreach (range(1, 5) as $element) {
                                                    echo '<option value="' . $element . '">' . $element . '</option>';
                                                } ?>
                                            </select>
                                        </td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <th><label for="province">Province </label><span class="mandatory">*</span></th>
                                        <td>
                                            <select class="form-control isRequired" name="province" id="province" title="Province" onchange="getfacilityDetails(this);" style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <td><label for="district">Zone de Santé </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="district" id="district" title="Zone de Santé" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                                                <option value=""> -- Sélectionner -- </option>
                                            </select>
                                        </td>
                                        <td><label for="facilityId">Nom de l'installation </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired " name="facilityId" id="facilityId" title="Nom de Structure" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
                                                <?php echo $facility; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <?php if ($sarr['user_type'] == 'remoteuser') { ?>
                                            <!-- <tr> -->
                                            <td><label for="labId">LAB ID <span class="mandatory">*</span></label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control isRequired" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Sélectionner --'); ?>
                                                </select>
                                            </td>
                                            <!-- </tr> -->
                                        <?php } ?>
                                    </tr>
                                </table>


                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">INFORMATION PATIENT</h3>
                                </div>
                                <table class="table" style="width:100%">

                                    <tr>
                                        <th style="width:15% !important"><label for="firstName">Prénom <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="firstName" name="firstName" placeholder="Prénom" title="Prénom" style="width:100%;" onchange="" />
                                        </td>
                                        <th style="width:15% !important"><label for="lastName">Nom de famille </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="lastName" name="lastName" placeholder="Nom de famille" title="Nom de famille" style="width:100%;" onchange="" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="patientId">N&deg; EPID <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="patientId" name="patientId" placeholder="N&deg; EPID" title="N&deg; EPID" style="width:100%;" onchange="" />
                                        </td>
                                        <th><label for="patientDob">Date de naissance <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <input type="text" class="form-control isRequired" id="patientDob" name="patientDob" placeholder="Date de naissance" title="Date de naissance" style="width:100%;" onchange="calculateAgeInYears();" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Age (years)</th>
                                        <td><input type="number" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="patientAge" name="patientAge" placeholder="Age (years)" title="Age (years)" style="width:100%;" onchange="" /></td>
                                        <th><label for="patientGender">Sexe <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <select class="form-control isRequired" name="patientGender" id="patientGender">
                                                <option value=''> -- Sélectionner -- </option>
                                                <option value='male'> Homme </option>
                                                <option value='female'> Femme </option>
                                                <option value='other'> Other </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="isPatientPregnant">Enceinte</label></th>
                                        <td>
                                            <select class="form-control" name="isPatientPregnant" id="isPatientPregnant">
                                                <option value=''> -- Sélectionner -- </option>
                                                <option value='yes'> Enceinte </option>
                                                <option value='no'> Pas Enceinte </option>
                                                <option value='unknown'> Inconnue </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Numéro de téléphone</th>
                                        <td><input type="text" class="form-control " id="patientPhoneNumber" name="patientPhoneNumber" placeholder="Numéro de téléphone" title="Numéro de téléphone" style="width:100%;" onchange="" /></td>

                                        <th>Adresse du patient</th>
                                        <td><textarea class="form-control " id="patientAddress" name="patientAddress" placeholder="Adresse du patient" title="Adresse du patient" style="width:100%;" onchange=""></textarea></td>
                                    </tr>

                                    <tr>
                                        <th>Province du patient</th>
                                        <td><input type="text" class="form-control " id="patientProvince" name="patientProvince" placeholder="Province du patient" title="Province du patient" style="width:100%;" /></td>

                                        <th>District des patients</th>
                                        <td><input class="form-control" id="patientDistrict" name="patientDistrict" placeholder="District des patients" title="District des patients" style="width:100%;"></td>
                                    </tr>

                                    <tr>
                                        <th>Pays de résidence</th>
                                        <td><input type="text" class="form-control" id="patientNationality" name="patientNationality" placeholder="Pays de résidence" title="Pays de résidence" style="width:100%;" /></td>

                                        <th></th>
                                        <td></td>
                                    </tr>
                                </table>

                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">
                                        Definition de cas
                                    </h3>
                                </div>
                                <table id="responseTable" class="table table-bordered">
                                    <tr>
                                        <td colspan="2">
                                            <label class="radio-inline" style="margin-left:0;">
                                                <input type="radio" class="" id="reason1" name="reasonForCovid19Test" value="1" title="Cas suspect de COVID-1" onchange="checkSubReason(this,'Cas_suspect_de_COVID_19');">
                                                <strong>Cas suspect de COVID-19</strong>
                                            </label>

                                        </td>
                                    </tr>
                                    <tr class="Cas_suspect_de_COVID_19 hide-reasons" style="display: none;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="reason-checkbox" id="suspect1" name="reasonDetails[]" value="Fièvre d'accès brutal (Inferieur ou égale à 38°C, vérifié à la salle d'urgence, la consultation externe, ou l'hôpital) ET(cochez une ou deux des cases suivantes)">
                                            </label>
                                            <label class="radio-inline" for="suspect1" style="padding-left:17px !important;margin-left:0;">Fièvre d'accès brutal (Inferieur ou égale à 38°C, vérifié à la salle d'urgence, la consultation externe, ou l'hôpital) ET <br>(cochez une ou deux des cases suivantes)</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_suspect_de_COVID_19 hide-reasons" style="display: none;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <ul style=" display: inline-flex; list-style: none; padding: 0px; ">
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                    <input type="checkbox" class="reason-checkbox" id="suspect2" name="reasonDetails[]" value="Toux">
                                                    </label>
                                                    <label class="radio-inline" for="suspect2" style="padding-left:17px !important;margin-left:0;">Toux</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">(OU)</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="reason-checkbox" id="suspect3" name="reasonDetails[]" value="Rhume">
                                                    </label>
                                                    <label class="radio-inline" for="suspect3" style="padding-left:17px !important;margin-left:0;">Rhume</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="reason-checkbox" id="suspect4" name="reasonDetails[]" value="Mal de gorge">
                                                    </label>
                                                    <label class="radio-inline" for="suspect4" style="padding-left:17px !important;margin-left:0;">Mal de gorge</label>
                                                </li>
                                                <li>
                                                    <label class="radio-inline" style="width:4%;margin-left:0;">
                                                        <input type="checkbox" class="reason-checkbox" id="suspect5" name="reasonDetails[]" value="Difficulté respiratoire">
                                                    </label>
                                                    <label class="radio-inline" for="suspect5" style="padding-left:17px !important;margin-left:0;">Difficulté respiratoire</label>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                    <tr class="Cas_suspect_de_COVID_19 hide-reasons" style="display: none;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="reason-checkbox" id="suspect6" name="reasonDetails[]" value="Notion de séjour ou voyage dans les zones a épidémie a COVID-19 dans les 14 jours précédant les symptômes ci-dessous.">
                                            </label>
                                            <label class="radio-inline" for="suspect6" style="padding-left:17px !important;margin-left:0;">Notion de séjour ou voyage dans les zones a épidémie a COVID-19 dans les 14 jours précédant les symptômes ci-dessous.</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_suspect_de_COVID_19 hide-reasons text-center" style="display: none;">
                                        <td>
                                            <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">(OU)</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_suspect_de_COVID_19 hide-reasons" style="display: none;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="reason-checkbox" id="suspect7" name="reasonDetails[]" value="IRA d'intensité variable (simple a sévère) ayant été en contact étroite avec cas probable ou un cas confirmé de la maladie a COVID-19">
                                            </label>
                                            <label class="radio-inline" for="suspect7" style="padding-left:17px !important;margin-left:0;">IRA d'intensité variable (simple a sévère) ayant été en contact étroite avec cas probable ou un cas confirmé de la maladie a COVID-19</label>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td colspan="2">
                                            <label class="radio-inline" style="margin-left:0;">
                                                <input type="radio" id="reason2" name="reasonForCovid19Test" value="2" onchange="checkSubReason(this,'Cas_probable_de_COVID_19');">
                                                <strong>Cas probable de COVID-19</strong>
                                            </label>

                                        </td>
                                    </tr>
                                    <tr class="Cas_probable_de_COVID_19 hide-reasons" style="display: none;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="reason-checkbox" id="probable1" name="reasonDetails[]" value="Tout cas suspects dont le résultat de laboratoire pour le diagnostic de COVID-19 n'est pas concluant (indéterminé)">
                                            </label>
                                            <label class="radio-inline" for="probable1" style="padding-left:17px !important;margin-left:0;">Tout cas suspects dont le résultat de laboratoire pour le diagnostic de COVID-19 n'est pas concluant (indéterminé)</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_probable_de_COVID_19 hide-reasons text-center" style="display: none;">
                                        <td>
                                            <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">(OU)</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_probable_de_COVID_19 hide-reasons" style="display: none;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="reason-checkbox" id="probable2" name="reasonDetails[]" value="Tout décès dans un tableau d'IRA pour lequel il n'a pas été possible d'obtenir des échantillons biologiques pour confirmation au laboratoire mais dont les investigations ont révélé un lien épidémiologique avec un cas confirmé ou probable">
                                            </label>
                                            <label class="radio-inline" for="probable2" style="padding-left:17px !important;margin-left:0;">Tout décès dans un tableau d'IRA pour lequel il n'a pas été possible d'obtenir des échantillons biologiques pour confirmation au laboratoire mais dont les investigations ont révélé un lien épidémiologique avec un cas confirmé ou probable</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_probable_de_COVID_19 hide-reasons text-center" style="display: none;">
                                        <td>
                                            <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">(OU)</label>
                                        </td>
                                    </tr>
                                    <tr class="Cas_probable_de_COVID_19 hide-reasons" style="display: none;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="reason-checkbox" id="probable4" name="reasonDetails[]" value="Une notion de séjour ou voyage dans les 14 jours précédant le décès dans les zones a épidémie de la maladie a COVID-19">
                                            </label>
                                            <label class="radio-inline" for="probable4" style="padding-left:17px !important;margin-left:0;">Une notion de séjour ou voyage dans les 14 jours précédant le décès dans les zones a épidémie de la maladie a COVID-19</label>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td colspan="2">
                                            <label class="radio-inline" style="margin-left:0;">
                                                <input type="radio" id="reason3" name="reasonForCovid19Test" value="3" onchange="checkSubReason(this,'Cas_confirme_de_COVID_19');">
                                                <strong><b>Cas confirme de covid-19</strong>
                                            </label>

                                        </td>
                                    </tr>
                                    <tr class="Cas_confirme_de_COVID_19 hide-reasons" style="display: none;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="reason-checkbox" id="confirme1" name="reasonDetails[]" value="Toute personne avec une confirmation en laboratoire de l'infection au COVID-19, quelles que soient les signes et symptômes cliniques">
                                            </label>
                                            <label class="radio-inline" for="confirme1" style="padding-left:17px !important;margin-left:0;">Toute personne avec une confirmation en laboratoire de l'infection au COVID-19, quelles que soient les signes et symptômes cliniques</label>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td colspan="2">
                                            <label class="radio-inline" style="margin-left:0;">
                                                <input type="radio" id="reason4" name="reasonForCovid19Test" value="4" onchange="checkSubReason(this,'Non_cas_contact_de_COVID_19');">
                                                <strong><b>Non cas contact de COVID-19</strong>
                                            </label>

                                        </td>
                                    </tr>
                                    <tr class="Non_cas_contact_de_COVID_19 hide-reasons" style="display: none;">
                                        <td colspan="2" style="padding-left: 70px;display: flex;">
                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                <input type="checkbox" class="reason-checkbox" id="contact1" name="reasonDetails[]" value="Tout cas suspects avec deux résultats de laboratoire négatifs au COVID-19 a au moins 48 heures d'intervalle">
                                            </label>
                                            <label class="radio-inline" for="contact1" style="padding-left:17px !important;margin-left:0;">Tout cas suspects avec deux résultats de laboratoire négatifs au COVID-19 a au moins 48 heures d'intervalle</label>
                                        </td>
                                    </tr>
                                </table>
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">
                                        Signes vitaux du patient
                                    </h3>
                                </div>
                                <table class="table">
                                    <tr>
                                        <th style="width:15% !important">Fièvre / température (&deg;C) <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control isRequired" type="number" name="feverTemp" id="feverTemp" placeholder="Fièvre / température (en &deg;Celcius)" title="Fièvre / température (en &deg;Celcius)" />
                                        </td>
                                        <th style="width:15% !important"><label for="temperatureMeasurementMethod">Température</label></th>
                                        <td style="width:35% !important;">
                                            <select name="temperatureMeasurementMethod" id="temperatureMeasurementMethod" class="form-control" title="Température">
                                                <option value="">--Select--</option>
                                                <option value="auxillary">Axillaire</option>
                                                <option value="oral">Orale</option>
                                                <option value="rectal">Rectale</option>
                                                <option value="unknown">Inconnu</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="respiratoryRate"> Fréquence Respiratoire</label></th>
                                        <td style="width:35% !important;">
                                            <input class="form-control" type="number" name="respiratoryRate" id="respiratoryRate" placeholder="Fréquence Respiratoire" title="Fréquence Respiratoire" />
                                        </td>
                                        <th style="width:15% !important"><label for="oxygenSaturation"> Saturation en oxygène</label></th>
                                        <td style="width:35% !important;">
                                            <input class="form-control" type="number" name="oxygenSaturation" id="oxygenSaturation" placeholder="Saturation en oxygène" title="Saturation en oxygène" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width: 15%;"><label for="specimenType"> Type(s) d' échantillon(s) dans le tube (cochez au moins une des cases suivants) <span class="mandatory">*</span></label></th>
                                        <td style="width: 35%;">
                                            <select class="form-control isRequired" id="specimenType" name="specimenType" title="Type(s) d' échantillon(s) dans le tube">
                                                <option value="">--Select--</option>
                                                <?php echo $general->generateSelectOptions($specimenTypeResult); ?>
                                            </select>
                                        </td>
                                        <th style="width: 15% !important;"><label for="numberOfDaysSick">Depuis combien de jours êtes-vous malade?</label></th>
                                        <td style="width:35% !important;">
                                            <input type="text" class="form-control" id="numberOfDaysSick" name="numberOfDaysSick" placeholder="Depuis combien de jours êtes-vous malade?" title="Date de Result PCR" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important">Date d'apparition des symptômes <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control date isRequired" type="text" name="dateOfSymptomOnset" id="dateOfSymptomOnset" placeholder="Date d'apparition des symptômes" title="Date d'apparition des symptômes" />
                                        </td>
                                        <th style="width:15% !important">Date de la consultation initiale</th>
                                        <td style="width:35% !important;">
                                            <input class="form-control date" type="text" name="dateOfInitialConsultation" id="dateOfInitialConsultation" placeholder="Date de la consultation initiale" title="Date de la consultation initiale" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="specimenType">Date de prélèvement de l'échantillon <span class="mandatory">*</span></label></th>
                                        <td style="width:35% !important;">
                                            <input class="form-control isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Date de prélèvement de l'échantillon" title="Date de prélèvement de l'échantillon" onchange="sampleCodeGeneration();" />
                                        </td>
                                        <th></th>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <th colspan="4" style="width:15% !important">Symptômes <span class="mandatory">*</span> </th>
                                    </tr>
                                    <tr>
                                        <td colspan="4">
                                            <table id="symptomsTable" class="table table-bordered table-striped">
                                                <?php $index = 0;
                                                foreach ($covid19Symptoms as $symptomId => $symptomName) { 
                                                    $diarrhée = "";
                                                    if ($symptomId == 13) {
                                                        $diarrhée = "diarrhée";
                                                    }?>
                                                    <tr class="row<?php echo $index; ?>">
                                                        <!-- <td style="display: flex;">
                                                            <label class="radio-inline" style="width:4%;margin-left:0;">
                                                                <input type="checkbox" class="checkSymptoms" id="xsymptom<?php echo $symptomId; ?>" name="symptom[]" value="<?php echo $symptomId; ?>" title="Veuillez choisir la valeur pour <?php echo $symptomName; ?>" onclick="checkSubSymptoms(this.value,<?php echo $symptomId; ?>,<?php echo $index; ?>);">
                                                            </label>
                                                            <label class="radio-inline" for="symptom<?php echo $symptomId; ?>" style="padding-left:17px !important;margin-left:0;"><b><?php echo $symptomName; ?></b></label>
                                                        </td> -->
                                                        <th style="width:50%;"><?php echo $symptomName; ?></th>
                                                        <td style="width:50%;">
                                                            <input name="symptomId[]" type="hidden" value="<?php echo $symptomId; ?>">
                                                            <select name="symptomDetected[]" id="symptomDetected<?php echo $symptomId; ?>" class="form-control <?php echo $diarrhée;?>" title="Veuillez choisir la valeur pour <?php echo $symptomName; ?>" style="width:100%">
                                                                <option value="">-- Sélectionner --</option>
                                                                <option value='yes'> Oui </option>
                                                                <option value='no'> Non </option>
                                                                <option value='unknown'> Inconnu </option>
                                                            </select>

                                                            <br>
                                                            <?php
                                                            if ($symptomId == 13) {
                                                            ?>
                                                                <label class="diarrhée-sub" for="symptomDetails14" style="margin-left:0;display:none;">Si oui:<br> Sanglante?</label>
                                                                <select name="symptomDetails[13][]" class="form-control diarrhée-sub" style="width:100%;display:none;">
                                                                    <option value="">-- Sélectionner --</option>
                                                                    <option value='yes'> Oui </option>
                                                                    <option value='no'> Non </option>
                                                                    <option value='unknown'> Inconnu </option>
                                                                </select>
                                                                <label class="diarrhée-sub" for="symptomDetails15" style="margin-left:0;display:none;">Aqueuse?</label>
                                                                <select name="symptomDetails[13][]" class="form-control diarrhée-sub" style="width:100%;display:none;">
                                                                    <option value="">-- Sélectionner --</option>
                                                                    <option value='yes'> Oui </option>
                                                                    <option value='no'> Non </option>
                                                                    <option value='unknown'> Inconnu </option>
                                                                </select>
                                                                <label class="diarrhée-sub" for="symptomDetails16" style="margin-left:0;display:none;">Nombre De Selles Par /24h</label>
                                                                    <input type="text" value="" style="display:none;" class="form-control reason-checkbox symptoms-checkbox diarrhée-sub" id="symptomDetails16" name="symptomDetails[13][]" placeholder="Nombre de selles par /24h" title="Nombre de selles par /24h">

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
                                                <option value="">-- Sélectionner --</option>
                                                <option value="yes">Oui</option>
                                                <option value="no">Non</option>
                                                <option value="unknown">Inconnu</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr class="comorbidities-row" style="display: none;">
                                        <td colspan="4">
                                            <table id="comorbiditiesTable" class="table table-bordered">
                                                <?php $index = 0;
                                                foreach ($covid19Comorbidities as $comorbiditiesId => $comorbiditiesName) { ?>
                                                    <tr>
                                                        <th style="width:50%;"><?php echo $comorbiditiesName; ?></th>
                                                        <td style="width:50%;">
                                                            <input name="comorbidityId[]" type="hidden" value="<?php echo $comorbiditiesId; ?>">
                                                            <select name="comorbidityDetected[]" class="form-control" title="<?php echo $comorbiditiesName; ?>" style="width:100%">
                                                                <option value="">-- Sélectionner --</option>
                                                                <option value='yes'> Oui </option>
                                                                <option value='no'> Non </option>
                                                                <option value='unknown'> Inconnu </option>
                                                            </select>
                                                        </td>
                                                    </tr>
                                                <?php $index++;
                                                } ?>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                                <table class="table">
                                    <tr>
                                        <th style="width:15% !important"><label for="recentHospitalization"></label>Avez-vous été hospitalisé durant les 12 derniers mois? </th>
                                        <td style="width:35% !important;">
                                            <select name="recentHospitalization" id="recentHospitalization" class="form-control" title="Avez-vous été hospitalisé durant les 12 derniers mois? ">
                                                <option value="">--Select--</option>
                                                <option value="yes">Oui</option>
                                                <option value="no">Non</option>
                                                <option value="unknown">Inconnu</option>
                                            </select>
                                        </td>
                                        <th style="width:15% !important"><label for="patientLivesWithChildren"></label>Habitez-vous avec les enfants ?</th>
                                        <td style="width:35% !important;">
                                            <select name="patientLivesWithChildren" id="patientLivesWithChildren" class="form-control" title="Habitez-vous avec les enfants ?">
                                                <option value="">--Select--</option>
                                                <option value="yes">Oui</option>
                                                <option value="no">Non</option>
                                                <option value="unknown">Inconnu</option>
                                            </select>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th style="width:15% !important"><label for="patientCaresForChildren"></label>Prenez-vous soins des enfants ?</th>
                                        <td style="width:35% !important;">
                                            <select name="patientCaresForChildren" id="patientCaresForChildren" class="form-control" title="prenez-vous soins des enfants ?">
                                                <option value="">--Select--</option>
                                                <option value="yes">Oui</option>
                                                <option value="no">Non</option>
                                                <option value="unknown">Inconnu</option>
                                            </select>
                                        </td>
                                        <th style="width:15% !important">Avez-vous eu des contacts étroits avec toute personne une maladie similaire a la vôtre durant ces 3 derniers semaines?</th>
                                        <td colspan="3">
                                            <select name="closeContacts" id="closeContacts" class="form-control" title="prenez-vous soins des enfants ?">
                                                <option value="">--Select--</option>
                                                <option value="yes">Oui</option>
                                                <option value="no">Non</option>
                                                <option value="unknown">Inconnu</option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                                <div class="box-header with-border sectionHeader">
                                    <h3 class="box-title">
                                        VOYAGE ET CONTACT
                                    </h3>
                                </div>
                                <table class="table">
                                    <tr>
                                        <th style="width: 15% !important;"><label for="hasRecentTravelHistory">Avez-vous voyagé au cours des 14 derniers jours ? </label></th>
                                        <td style="width:35% !important;">
                                            <select class="form-control" id="hasRecentTravelHistory" name="hasRecentTravelHistory" title="Avez-vous voyagé au cours des 14 derniers jours ?">
                                                <option value="">--Select--</option>
                                                <option value="yes">Oui</option>
                                                <option value="no">Non</option>
                                                <option value="unknown">Inconnu</option>
                                            </select>
                                        </td>
                                        <th style="width: 15% !important;"><label for="countryName">Si oui, dans quels pays?</label></th>
                                        <td style="width:35% !important;">
                                            <input type="text" class="form-control" id="countryName" name="countryName" placeholder="Si oui, dans quels pays ?" title="Si oui, dans quels pays?" />
                                        </td>
                                    </tr>

                                    <tr>
                                        <th style="width: 15% !important;"><label for="returnDate">Date de retour</label></th>
                                        <td style="width:35% !important;">
                                            <input type="text" class="form-control date" id="returnDate" name="returnDate" placeholder="e.g 09-Jan-1992" title="Date de retour" />
                                        </td>
                                        
                                        <th>Compagnie aérienne</th>
                                        <td><input type="text" class="form-control " id="airline" name="airline" placeholder="Compagnie aérienne" title="Compagnie aérienne" style="width:100%;" /></td>
                                    </tr>
                                    <tr>
                                        <th>Numéro de siège</th>
                                        <td><input type="text" class="form-control " id="seatNo" name="seatNo" placeholder="Numéro de siège" title="Numéro de siège" style="width:100%;" /></td>

                                        <th>Date et heure d'arrivée</th>
                                        <td><input type="text" class="form-control dateTime" id="arrivalDateTime" name="arrivalDateTime" placeholder="Date et heure d'arrivée" title="Date et heure d'arrivée" style="width:100%;" /></td>
                                    </tr>
                                    <tr>
                                        <th>Aeroport DE DEPART</th>
                                        <td><input type="text" class="form-control " id="airportOfDeparture" name="airportOfDeparture" placeholder="Aeroport DE DEPART" title="Aeroport DE DEPART" style="width:100%;" /></td>

                                        <th>Transit</th>
                                        <td><input type="text" class="form-control" id="transit" name="transit" placeholder="Transit" title="Transit" style="width:100%;" /></td>
                                    </tr>
                                    <tr>
                                        <th>Raison de la visite (le cas échéant)</th>
                                        <td><input type="text" class="form-control" id="reasonOfVisit" name="reasonOfVisit" placeholder="Raison de la visite (le cas échéant)" title="Raison de la visite (le cas échéant)" style="width:100%;" /></td>

                                        <th>Occupation du patient</th>
                                        <td>
                                            <input class="form-control" type="text" name="patientOccupation" id="patientOccupation" placeholder="Occupation du patient" title="Occupation du patient" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Raison de la visite (le cas échéant)</th>
                                        <td><input type="text" class="form-control" id="reasonOfVisit" name="reasonOfVisit" placeholder="Raison de la visite (le cas échéant)" title="Raison de la visite (le cas échéant)" style="width:100%;" /></td>

                                        <th>Occupation du patient</th>
                                        <td>
                                            <input class="form-control" type="text" name="patientOccupation" id="patientOccupation" placeholder="Occupation du patient" title="Occupation du patient" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width: 15% !important;"><label for="hasRecentTravelHistory">Patiend fume-t-il? </label></th>
                                        <td style="width:35% !important;">
                                            <select class="form-control" id="doesPatientSmoke" name="doesPatientSmoke" title="Patiend fume-t-il?">
                                                <option value="">--Select--</option>
                                                <option value="yes">Oui</option>
                                                <option value="no">Non</option>
                                                <option value="unknown">Inconnu</option>
                                            </select>
                                        </td>
                                        <th></th>
                                        <td></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php if ($sarr['user_type'] != 'remoteuser') { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Réservé à une utilisation en laboratoire </h3>
                                    </div>
                                    <table class="table" style="width:100%">
                                        <tr>
                                            <th><label for="">Date de réception de l'échantillon </label></th>
                                            <td>
                                                <input type="text" class="form-control" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="e.g 09-Jan-1992 05:30" title="Veuillez saisir la date de réception de l'échantillon" <?php echo (isset($labFieldDisabled) && trim($labFieldDisabled) != '') ? $labFieldDisabled : ''; ?> onchange="" style="width:100%;" />
                                            </td>
                                            <th><label for="sampleCondition">Condition de l'échantillon</label></th>
                                            <td>
                                                <select class="form-control" name="sampleCondition" id="sampleCondition" title="Condition de l'échantillon">
                                                    <option value=''> -- Sélectionner -- </option>
                                                    <option value="adequate"> Adéquat </option>
                                                    <option value="not-adequate"> Non Adéquat </option>
                                                    <option value="autres"> Autres </option>
                                                </select>
                                            </td>
                                        <tr>
                                            <td class="lab-show"><label for="labId">Nom du laboratoire </label> </td>
                                            <td class="lab-show">
                                                <select name="labId" id="labId" class="form-control" title="Nom du laboratoire" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Sélectionner --'); ?>
                                                </select>
                                            </td>
                                            <th>L'échantillon est-il rejeté?</th>
                                            <td>
                                                <select class="form-control" name="isSampleRejected" id="isSampleRejected" title="L'échantillon est-il rejeté?">
                                                    <option value="">--Select--</option>
                                                    <option value="yes">Oui</option>
                                                    <option value="no">Non</option>
                                                </select>
                                            </td>

                                        </tr>
                                        <tr class="show-rejection" style="display:none;">
                                            <th class="show-rejection" style="display:none;">Raison du rejet</th>
                                            <td class="show-rejection" style="display:none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="Raison du rejet">
                                                    <option value=''> -- Sélectionner -- </option>
                                                    <?php echo $rejectionReason; ?>
                                                </select>
                                            </td>
                                            <th>Date de rejet<span class="mandatory">*</span></th>
                                            <td><input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Date de rejet" title="Date de rejet" /></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4">
                                                <table class="table table-bordered table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-center">Test non</th>
                                                            <th class="text-center">Nom du Testkit (ou) Méthode de test utilisée</th>
                                                            <th class="text-center">Date de l'analyse</th>
                                                            <th class="text-center">Résultat du test</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="testKitNameTable">
                                                        <tr>
                                                            <td class="text-center">1</td>
                                                            <td>
                                                                <select onchange="otherCovidTestName(this.value,1)" class="form-control test-name-table-input" id="testName1" name="testName[]" title="Veuillez saisir le nom du test pour les lignes 1">
                                                                    <option value="">--Select--</option>
                                                                    <option value="PCR/RT-PCR">PCR/RT-PCR</option>
                                                                    <option value="RdRp-SARS Cov-2">RdRp-SARS Cov-2</option>
                                                                    <option value="other">Others</option>
                                                                </select>
                                                                <input type="text" name="testNameOther[]" id="testNameOther1" class="form-control testInputOther1" title="Veuillez saisir le nom du test pour les lignes 1" placeholder="Entrez le nom du test 1" style="display: none;margin-top: 10px;" />
                                                            </td>
                                                            <td>
                                                                <input type="text" name="testDate[]" id="testDate1" class="form-control test-name-table-input dateTime" placeholder="Testé sur" title="Veuillez saisir le test pour la ligne 1" />
                                                            </td>
                                                            <td><select class="form-control test-result test-name-table-input" name="testResult[]" id="testResult1" title="Veuillez sélectionner le résultat pour la ligne 1">
                                                                    <option value=''> -- Sélectionner -- </option>
                                                                    <?php foreach ($covid19Results as $c19ResultKey => $c19ResultValue) { ?>
                                                                        <option value="<?php echo $c19ResultKey; ?>"> <?php echo $c19ResultValue; ?> </option>
                                                                    <?php } ?>
                                                                </select>
                                                            </td>
                                                            <td style="vertical-align:middle;text-align: center;">
                                                                <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="insRow();"><i class="fa fa-plus"></i></a>&nbsp;
                                                                <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><i class="fa fa-minus"></i></a>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <th colspan="3" class="text-right">Résultat final</th>
                                                            <td>
                                                                <select class="form-control" name="result" id="result" title="Résultat final">
                                                                    <option value=''> -- Sélectionner -- </option>
                                                                    <?php foreach ($covid19Results as $c19ResultKey => $c19ResultValue) { ?>
                                                                        <option value="<?php echo $c19ResultKey; ?>"> <?php echo $c19ResultValue; ?> </option>
                                                                    <?php } ?>
                                                                </select>
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th class="other-diseases" style="display: none;"><label for="otherDiseases">Autres maladies<span class="mandatory">*</span></label></th>
                                            <td colspan="3" class="other-diseases" style="display: none;">
                                                <select name="otherDiseases" id="otherDiseases" class="form-control" title="Autres maladies">
                                                    <option value="">--Select--</option>
                                                    <optgroup label="Coronavirus">
                                                        <option value="E-Sars-CoV">E-Sars-CoV</option>
                                                        <option value="N-Sars-Cov">N-Sars-Cov</option>
                                                        <option value="Other respiratory pathogens">Autres Pathogens Respiratories</option>
                                                        <option value="Other Coronavirus">Autres Coronavirus</option>
                                                    </optgroup>
                                                    <optgroup label="Influenza">
                                                        <option value="A/H1N1pdm09">A/H1N1pdm09</option>
                                                        <option value="A/H3N2">A/H3N2</option>
                                                        <option value="A/H5N1">A/H5N1</option>
                                                        <option value="B/Yan">B/Yan</option>
                                                        <option value="B/Vic">B/Vic</option>
                                                    </optgroup>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>

                                            <th>Le résultat est-il autorisé?</th>
                                            <td>
                                                <select name="isResultAuthorized" id="isResultAuthorized" class="disabled-field form-control" title="Le résultat est-il autorisé?" style="width:100%">
                                                    <option value="">-- Sélectionner --</option>
                                                    <option value='yes'> Oui </option>
                                                    <option value='no'> Non </option>
                                                </select>
                                            </td>
                                            <th>Autorisé par</th>
                                            <td><input type="text" name="authorizedBy" id="authorizedBy" class="disabled-field form-control" placeholder="Autorisé par" title="Autorisé par" /></td>

                                        </tr>
                                        <tr>

                                            <th>Autorisé le</td>
                                            <td><input type="text" name="authorizedOn" id="authorizedOn" class="disabled-field form-control date" placeholder="Autorisé le" title="Autorisé le" /></td>
                                            <th></th>
                                            <td></td>

                                        </tr>
                                    </table>
                                </div>
                            </div>
                        <?php } ?>

                    </div>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <?php if ($arr['covid19_sample_code'] == 'auto' || $arr['covid19_sample_code'] == 'YY' || $arr['covid19_sample_code'] == 'MMYY') { ?>
                            <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                            <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                            <input type="hidden" name="saveNext" id="saveNext" />
                            <!-- <input type="hidden" name="pageURL" id="pageURL" value="<?php echo $_SERVER['PHP_SELF']; ?>" /> -->
                        <?php } ?>
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Sauver</a>
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();$('#saveNext').val('next');return false;">Enregistrer et suivant</a>
                        <input type="hidden" name="formId" id="formId" value="<?php echo $arr['vl_form']; ?>" />
                        <input type="hidden" name="covid19SampleId" id="covid19SampleId" value="" />
                        <a href="/covid-19/requests/covid-19-requests.php" class="btn btn-default"> Annuler</a>
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
    tableRowId = 2;

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
                        //$("#clinicianName").val(details[2]);
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
        if ($('#isResultAuthorized').val() != "yes") {
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
        }
        if ($('#medicalHistory').val() == "yes") {
            if ($('input[name ="comorbidityDetected"] select option[selected=selected][value!=" "]').length > 0) {
                alert("Veuillez sélectionner au moins une option sous Antécédents médicaux");
                return false;
            }
        }
        if ($('input[name ="symptom"] select option[selected=selected][value!=" "]').length > 0) {
            alert("Veuillez sélectionner au moins une option sous Symptômes");
            return false;
        }
        flag = deforayValidator.init({
            formId: 'addCovid19RequestForm'
        });
        if (flag) {
            //$.blockUI();
            <?php
            if ($arr['covid19_sample_code'] == 'auto' || $arr['covid19_sample_code'] == 'YY' || $arr['covid19_sample_code'] == 'MMYY') {
            ?>
                insertSampleCode('addCovid19RequestForm', 'covid19SampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 3, 'sampleCollectionDate');
            <?php
            } else {
            ?>
                document.getElementById('addCovid19RequestForm').submit();
            <?php
            } ?>
        }
    }


    $(document).ready(function() {
        
        $('#facilityId').select2({
            placeholder: "Nom de l'installation"
        });
        // $('#district').select2({
        //     placeholder: "District"
        // });
        // $('#province').select2({
        //     placeholder: "Province"
        // });
        $('.diarrhée').change(function(e) {
            if(this.value == "yes"){
                $('.diarrhée-sub').show();
            } else{
                $('.diarrhée-sub').hide();
            }
        });
        $('#medicalHistory').change(function(e) {
            if ($(this).val() == "yes") {
                $('.comorbidities-row').show();
            } else {
                $('.comorbidities-row').hide();
            }
        });

        $('#isResultAuthorized').change(function(e) {
            checkIsResultAuthorized();
        });
        $('#medicalBackground').change(function(e) {
            if (this.value == 'yes') {
                $('.medical-background-info').css('display', 'table-cell');
                $('.medical-background-info').css('color', 'red');
                $('.medical-background-yes').css('display', 'table-row');
            } else {
                $('.medical-background-yes,.medical-background-info').css('display', 'none');
            }
        });

        $('#respiratoryRateSelect').change(function(e) {
            if (this.value == 'yes') {
                $('.respiratory-rate').css('display', 'inline-flex ');
            } else {
                $('.respiratory-rate').css('display', 'none');
            }
        });

        $('#oxygenSaturationSelect').change(function(e) {
            if (this.value == 'yes') {
                $('.oxygen-saturation').css('display', 'inline-flex');
            } else {
                $('.oxygen-saturation').css('display', 'none');
            }
        });
        $('#result').change(function(e) {
            if (this.value == 'positive') {
                $('.other-diseases').hide();
                $('#otherDiseases').removeClass('isRequired');
            } else {
                $('.other-diseases').show();
                $('#otherDiseases').addClass('isRequired');
            }
        });
        <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            $(document).on('change', '.test-result, #result', function(e) {
                checkPostive();
            });
        <?php } ?>
    });

    function insRow() {
        rl = document.getElementById("testKitNameTable").rows.length;
        tableRowId = (rl + 1);
        var a = document.getElementById("testKitNameTable").insertRow(rl);
        a.setAttribute("style", "display:none");
        var b = a.insertCell(0);
        var c = a.insertCell(1);
        var d = a.insertCell(2);
        var e = a.insertCell(3);
        var f = a.insertCell(4);
        f.setAttribute("align", "center");
        b.setAttribute("align", "center");
        f.setAttribute("style", "vertical-align:middle");

        b.innerHTML = tableRowId;
        c.innerHTML = '<select onchange="otherCovidTestName(this.value,' + tableRowId + ')" class="form-control test-name-table-input" id="testName' + tableRowId + '" name="testName[]" title="Veuillez saisir le nom du test pour les lignes ' + tableRowId + '"> <option value="">--Select--</option> <option value="PCR/RT-PCR">PCR/RT-PCR</option> <option value="RdRp-SARS Cov-2">RdRp-SARS Cov-2</option> <option value="other">Others</option> </select> <input type="text" name="testName[]" id="testName' + tableRowId + '" class="form-control testInputOther' + tableRowId + ' test-name-table-input" placeholder="Entrez le nom du test ' + tableRowId + '" title="Veuillez saisir le nom du test pour les lignes ' + tableRowId + '" style="display: none;margin-top: 10px;"/>';
        d.innerHTML = '<input type="text" name="testDate[]" id="testDate' + tableRowId + '" class="form-control test-name-table-input dateTime" placeholder="Testé sur"  title="Veuillez sélectionner la Date de l analyse pour la ligne ' + tableRowId + '"/>';
        e.innerHTML = '<select class="form-control test-result test-name-table-input" name="testResult[]" id="testResult' + tableRowId + '" title="Veuillez sélectionner le résultat pour la ligne ' + tableRowId + '"><option value=""> -- Sélectionner -- </option><?php foreach ($covid19Results as $c19ResultKey => $c19ResultValue) { ?> <option value="<?php echo $c19ResultKey; ?>"> <?php echo $c19ResultValue; ?> </option> <?php } ?> </select>';
        f.innerHTML = '<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="insRow();"><i class="fa fa-plus"></i></a>&nbsp;<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><i class="fa fa-minus"></i></a>';
        $(a).fadeIn(800);
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
            yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });
        tableRowId++;
        <?php if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
            $(document).on('change', '.test-result, #result', function(e) {
                checkPostive();
            });
        <?php } ?>
    }

    function removeAttributeRow(el) {
        $(el).fadeOut("slow", function() {
            el.parentNode.removeChild(el);
            rl = document.getElementById("testKitNameTable").rows.length;
            if (rl == 0) {
                insRow();
            }
        });
    }

    function checkPostive() {
        var itemLength = document.getElementsByName("testResult[]");
        for (i = 0; i < itemLength.length; i++) {

            if (itemLength[i].value == 'positive') {
                $('#result,.disabled-field').val('');
                $('#result,.disabled-field').prop('disabled', true);
                $('#result,.disabled-field').addClass('disabled');
                $('#result,.disabled-field').removeClass('isRequired');
                return false;
            } else {
                $('#result,.disabled-field').prop('disabled', false);
                $('#result,.disabled-field').removeClass('disabled');
                $('#result,.disabled-field').addClass('isRequired');
            }
            if (itemLength[i].value != '') {
                $('#labId').addClass('isRequired');
            }
        }
    }

    function checkIsResultAuthorized() {
        if ($('#isResultAuthorized').val() == 'no') {
            $('#authorizedBy,#authorizedOn').val('');
            $('#authorizedBy,#authorizedOn').prop('disabled', true);
            $('#authorizedBy,#authorizedOn').addClass('disabled');
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
            return false;
        } else {
            $('#authorizedBy,#authorizedOn').prop('disabled', false);
            $('#authorizedBy,#authorizedOn').removeClass('disabled');
            $('#authorizedBy,#authorizedOn').addClass('isRequired');
        }
    }

    function otherCovidTestName(val, id) {
        if (val == 'other') {
            $('.testInputOther' + id).show();
        } else {
            $('.testInputOther' + id).hide();
        }
    }

    function checkSubReason(obj, show) {
        $('.reason-checkbox').prop("checked", false);
        if ($(obj).prop("checked", true)) {
            $('.' + show).show();
            $('.' + show).removeClass('hide-reasons');
            $('.hide-reasons').hide();
            $('.' + show).addClass('hide-reasons');
        }
    }

    function checkSubSymptoms(obj, parent, row, sub = "") {
        //alert(obj.value);
        if (obj.value === 'yes') {
            $.post("getSymptomsByParentId.php", {
                    symptomParent: parent
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
</script>