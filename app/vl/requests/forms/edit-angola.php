<?php

use App\Utilities\DateUtility;


$artRegimenQuery = "SELECT DISTINCT headings FROM r_vl_art_regimen";
$artRegimenResult = $db->rawQuery($artRegimenQuery);
//check remote user
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {
  $sampleCode = 'remote_sample_code';
  if (!empty($vlQueryInfo['remote_sample']) && $vlQueryInfo['remote_sample'] == 'yes') {
    $sampleCode = 'remote_sample_code';
  } else {
    $sampleCode = 'sample_code';
  }
  //check user exist in user_facility_map table
  $chkUserFcMapQry = "SELECT user_id FROM user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
  $chkUserFcMapResult = $db->query($chkUserFcMapQry);
  if ($chkUserFcMapResult) {
    $pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
  }
} else {
  $sampleCode = 'sample_code';
}
$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Selecione -- </option>";
foreach ($pdResult as $provinceName) {
  $province .= "<option value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, $vlQueryInfo['facility_id'], '-- Selecione --');

//Get selected state
$stateQuery = "SELECT * from facility_details where facility_id='" . $vlQueryInfo['requesting_facility_id'] . "'";
$stateResult = $db->query($stateQuery);
if (!isset($stateResult[0]['facility_state']) || $stateResult[0]['facility_state'] == '') {
  $stateResult[0]['facility_state'] = '';
}
//district details
$districtQuery = "SELECT DISTINCT facility_district from facility_details where facility_state='" . $stateResult[0]['facility_state'] . "'";
$districtResult = $db->query($districtQuery);
$provinceQuery = "SELECT * from geographical_divisions where geo_name='" . $stateResult[0]['facility_state'] . "'";
$provinceResult = $db->query($provinceQuery);
if (!isset($provinceResult[0]['geo_code']) || $provinceResult[0]['geo_code'] == '') {
  $provinceResult[0]['geo_code'] = '';
}
//get ART list
$aQuery = "SELECT * from r_vl_art_regimen";
$aResult = $db->query($aQuery);
$start_date = date('Y-m-01');
$end_date = date('Y-m-31');
if ($arr['sample_code'] == 'MMYY') {
  $mnthYr = date('my');
  $start_date = date('Y-m-01');
  $end_date = date('Y-m-31');
} else if ($arr['sample_code'] == 'YY') {
  $mnthYr = date('y');
  $start_date = date('Y-01-01');
  $end_date = date('Y-12-31');
}
//Set Dispatched From Clinic To Lab Date
if (isset($vlQueryInfo['sample_dispatched_datetime']) && trim($vlQueryInfo['sample_dispatched_datetime']) != '' && $vlQueryInfo['sample_dispatched_datetime'] != '0000-00-00 00:00:00') {
  $expStr = explode(" ", $vlQueryInfo['sample_dispatched_datetime']);
  $vlQueryInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
  $vlQueryInfo['sample_dispatched_datetime'] = '';
}
if (isset($vlQueryInfo['requesting_date']) && trim($vlQueryInfo['requesting_date']) != '' && $vlQueryInfo['requesting_date'] != '0000-00-00') {
  $vlQueryInfo['requesting_date'] = DateUtility::humanReadableDateFormat($vlQueryInfo['requesting_date']);
} else {
  $vlQueryInfo['requesting_date'] = '';
}
//set reason for changes history
$rch = '';
if (isset($vlQueryInfo['reason_for_vl_result_changes']) && $vlQueryInfo['reason_for_vl_result_changes'] != '' && $vlQueryInfo['reason_for_vl_result_changes'] != null) {
  $rch .= '<h4>Result Changes History</h4>';
  $rch .= '<table style="width:100%;">';
  $rch .= '<thead><tr style="border-bottom:2px solid #d3d3d3;"><th style="width:20%;">USER</th><th style="width:60%;">MESSAGE</th><th style="width:20%;text-align:center;">DATE</th></tr></thead>';
  $rch .= '<tbody>';
  $splitChanges = explode('vlsm', $vlQueryInfo['reason_for_vl_result_changes']);
  for ($c = 0; $c < count($splitChanges); $c++) {
    $getData = explode("##", $splitChanges[$c]);
    $expStr = explode(" ", $getData[2]);
    $changedDate = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
    $rch .= '<tr><td>' . ($getData[0]) . '</td><td>' . ($getData[1]) . '</td><td style="text-align:center;">' . $changedDate . '</td></tr>';
  }
  $rch .= '</tbody>';
  $rch .= '</table>';
}
//set patient group option
if ($vlQueryInfo['patient_group'] != '') {
  $patientGrp = json_decode($vlQueryInfo['patient_group']);
}
//set vl testing reason
$lastVlDate = '';
$lastVlResult = '';
if ($vlQueryInfo['reason_for_vl_testing'] != '') {
  if ($vlQueryInfo['reason_for_vl_testing'] == 'routine') {
    if (isset($vlQueryInfo['last_vl_date_routine']) && trim($vlQueryInfo['last_vl_date_routine']) != '' && $vlQueryInfo['last_vl_date_routine'] != '0000-00-00') {
      $lastVlDate = DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_routine']);
    }
    $lastVlResult = $vlQueryInfo['last_vl_result_routine'];
  } else if ($vlQueryInfo['reason_for_vl_testing'] == 'expose') {
    if (isset($vlQueryInfo['last_vl_date_ecd']) && trim($vlQueryInfo['last_vl_date_ecd']) != '' && $vlQueryInfo['last_vl_date_ecd'] != '0000-00-00') {
      $lastVlDate = DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_ecd']);
    }
    $lastVlResult = $vlQueryInfo['last_vl_result_ecd'];
  } else if ($vlQueryInfo['reason_for_vl_testing'] == 'suspect') {
    if (isset($vlQueryInfo['last_vl_date_failure']) && trim($vlQueryInfo['last_vl_date_failure']) != '' && $vlQueryInfo['last_vl_date_failure'] != '0000-00-00') {
      $lastVlDate = DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_failure']);
    }
    $lastVlResult = $vlQueryInfo['last_vl_result_failure'];
  } else if ($vlQueryInfo['reason_for_vl_testing'] == 'repetition') {
    if (isset($vlQueryInfo['last_vl_date_failure_ac']) && trim($vlQueryInfo['last_vl_date_failure_ac']) != '' && $vlQueryInfo['last_vl_date_failure_ac'] != '0000-00-00') {
      $lastVlDate = DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_failure_ac']);
    }
    $lastVlResult = $vlQueryInfo['last_vl_date_failure_ac'];
  } else if ($vlQueryInfo['reason_for_vl_testing'] == 'clinical') {
    if (isset($vlQueryInfo['last_vl_date_cf']) && trim($vlQueryInfo['last_vl_date_cf']) != '' && $vlQueryInfo['last_vl_date_cf'] != '0000-00-00') {
      $lastVlDate = DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_cf']);
    }
    $lastVlResult = $vlQueryInfo['last_vl_result_cf'];
  } else if ($vlQueryInfo['reason_for_vl_testing'] == 'immunological') {
    if (isset($vlQueryInfo['last_vl_date_if']) && trim($vlQueryInfo['last_vl_date_if']) != '' && $vlQueryInfo['last_vl_date_if'] != '0000-00-00') {
      $lastVlDate = DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_if']);
    }
    $lastVlResult = $vlQueryInfo['last_vl_result_if'];
  }
}

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
        <form class="form-inline" method="post" name="addVlRequestForm" id="vlRequestFormAng" autocomplete="off" action="editVlRequestHelperAng.php">
          <div class="box-body">
            <div class="box box-default">
              <div class="box-body">
                <div class="box-header with-border">
                  <h3 class="box-title">SOLICITAÇÃO DE QUANTIFICAÇÃO DE CARGA VIRAL DE VIH</h3>
                </div>
                <div class="box box-primary">
                  <div class="box-header with-border">
                    <h3 class="box-title">A. UNIDADE DE SOLICITAÇÃO</h3>
                  </div>
                  <table aria-describedby="table" class="table" aria-hidden="true"  style="width:100%">
                    <tr>
                      <td><label for="province">Província </label><span class="mandatory">*</span></td>
                      <td>
                        <select class="form-control isRequired" name="province" id="province" title="Please choose província" style="width:100%;" onchange="getfacilityDetails(this)">
                          <?php foreach ($pdResult as $provinceName) { ?>
                            <option value="<?php echo $provinceName['geo_name'] . "##" . $provinceName['geo_code']; ?>" <?php echo ($stateResult[0]['facility_state'] . "##" . $provinceResult[0]['geo_code'] == $provinceName['geo_name'] . "##" . $provinceName['geo_code']) ? "selected='selected'" : "" ?>><?php echo ($provinceName['geo_name']); ?></option>
                          <?php } ?>
                        </select>
                      </td>
                      <td><label for="district">Município </label><span class="mandatory">*</span></td>
                      <td>
                        <select class="form-control isRequired" name="district" id="district" title="Please choose município" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                          <option value=""> -- Selecione -- </option>
                          <?php foreach ($districtResult as $districtName) {
                          ?>
                            <option value="<?php echo $districtName['facility_district']; ?>" <?php echo ($stateResult[0]['facility_district'] == $districtName['facility_district']) ? "selected='selected'" : "" ?>><?php echo ($districtName['facility_district']); ?></option>
                          <?php } ?>
                        </select>
                      </td>
                      <td><label for="clinicName">Nome da Unidade </label><span class="mandatory">*</span></td>
                      <td>
                        <select name="clinicName" id="clinicName" title="Please choose Nome da Unidade" style="width:100%;" onchange="getfacilityProvinceDetails(this)">
                          <?= $facility; ?>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td><label for="sector">Serviço/Sector </label></td>
                      <td>
                        <input type="text" class="form-control" name="sector" id="sector" placeholder="Serviço/Sector" title="Please enter Serviço/Sector" value="<?php echo $vlQueryInfo['requesting_vl_service_sector']; ?>" />
                      </td>
                      <td><label for="requestingPerson">Nome do solicitante </label></td>
                      <td>
                        <input type="text" class="form-control" name="requestingPerson" id="requestingPerson" placeholder="Nome do solicitante" title="Please enter Nome do solicitante" value="<?php echo $vlQueryInfo['requesting_person']; ?>" />
                      </td>
                      <td><label for="category">Categoria </label></td>
                      <td>
                        <select class="form-control" name="category" id="category" title="Please choose Categoria" style="width:100%;">
                          <option value="">-- Selecione --</option>
                          <option value="nurse" <?php echo ($vlQueryInfo['requesting_category'] == 'nurse') ? "selected='selected'" : "" ?>>Enfermeiro/a</option>
                          <option value="clinician" <?php echo ($vlQueryInfo['requesting_category'] == 'clinician') ? "selected='selected'" : "" ?>>Médico/a</option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td><label for="profNumber">Nº da Ordem </label></td>
                      <td>
                        <input type="text" class="form-control" name="profNumber" id="profNumber" placeholder="Nº da Ordem" title="Please enter Nº da Ordem" value="<?php echo $vlQueryInfo['requesting_professional_number']; ?>" />
                      </td>
                      <td><label for="requestingContactNo">Contacto </label></td>
                      <td>
                        <input type="text" class="form-control" name="requestingContactNo" id="requestingContactNo" placeholder="Contacto" title="Please enter Contacto" value="<?php echo $vlQueryInfo['requesting_phone']; ?>" />
                      </td>
                      <td><label for="requestingDate">Data da solicitação </label></td>
                      <td>
                        <input type="text" class="form-control date" name="requestingDate" id="requestingDate" placeholder="Data da solicitação" title="Please choose Data da solicitação" value="<?php echo $vlQueryInfo['requesting_date']; ?>" style="width:100%;" />
                      </td>
                    </tr>
                  </table>
                </div>
                <div class="box box-primary">
                  <div class="box-header with-border">
                    <!-- <h3 class="box-title">Information sur le patient </h3>&nbsp;&nbsp;&nbsp;
                            <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="" placeholder="Code du patient" title="Please enter code du patient"/>&nbsp;&nbsp;
                            <a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No Patient Found</strong></span>-->
                    <h4>B. DADOS DO PACIENTE</h4>
                  </div>
                  <table aria-describedby="table" class="table" aria-hidden="true"  style="width:100%">
                    <tr>
                      <td style="width:14%;"><label for="patientFirstName">Nome completo </label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control " id="patientFirstName" name="patientFirstName" placeholder="Nome completo" title="Please enter Nome completo" style="width:100%;" value="<?php echo $patientFirstName; ?>" />
                      </td>
                      <td style="width:14%;"><label for="patientArtNo">Nº Processo Clínico </label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control " id="patientArtNo" name="patientArtNo" placeholder="Nº Processo Clínico" title="Please enter Nº Processo Clínico" style="width:100%;" value="<?php echo $vlQueryInfo['patient_art_no']; ?>" />
                      </td>
                      <td><label for="sex">Género </label></td>
                      <td style="width:16%;">
                        <label class="radio-inline" style="padding-left:10px !important;margin-left:0;">Masculino</label>
                        <label class="radio-inline" style="width:2%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="genderMale" name="gender" value="male" title="Please check sexe" <?php echo (trim($vlQueryInfo['patient_gender']) == "male") ? 'checked="checked"' : ''; ?>>
                        </label>
                        <label class="radio-inline" style="padding-left:10px !important;margin-left:0;">Feminino</label>
                        <label class="radio-inline" style="width:2%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="genderFemale" name="gender" value="female" title="Please check sexe" <?php echo (trim($vlQueryInfo['patient_gender']) == "female") ? 'checked="checked"' : ''; ?>>
                        </label>
                      </td>
                      <td style="width:14%;"><label for="ageInMonths">Data de nascimento </label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control date" id="dob" name="dob" placeholder="Data de nascimento" title="Please enter Data de nascimento" value="<?php echo $vlQueryInfo['patient_dob']; ?>" onchange="getAge();checkARTInitiationDate();" style="width:100%;" />
                      </td>
                    </tr>
                    <tr>
                      <td><label for="ageInMonths"> Idade (em meses se < 1 ano) </label>
                      </td>
                      <td>
                        <input type="text" class="form-control forceNumeric" id="ageInMonths" name="ageInMonths" placeholder="Mois" title="Please enter àge en mois" value="<?php echo $vlQueryInfo['patient_age_in_months']; ?>" style="width:100%;" />
                      </td>
                      <td colspan="3"><label for="responsiblePersonName">Nome da Mãe/ Pai/ Familiar responsáve </label></td>
                      <td>
                        <input type="text" class="form-control" id="responsiblePersonName" name="responsiblePersonName" placeholder="Nome da Mãe/ Pai/ Familiar responsáve" title="Please enter Nome da Mãe/ Pai/ Familiar responsáve" value="<?php echo $vlQueryInfo['patient_responsible_person']; ?>" style="width:100%;" />
                      </td>
                      <td><label for="patientDistrict">Município </label></td>
                      <td>
                        <input type="text" class="form-control" id="patientDistrict" name="patientDistrict" placeholder="Município" title="Please enter Município" value="<?php echo $vlQueryInfo['patient_district']; ?>" style="width:100%;" />
                      </td>
                    </tr>
                    <tr>
                      <td><label for="patientProvince">Província </label></td>
                      <td>
                        <input type="text" class="form-control" id="patientProvince" name="patientProvince" placeholder="Província" title="Please enter Província" value="<?php echo $vlQueryInfo['patient_province']; ?>" style="width:100%;" />
                      </td>
                      <td><label for="patientPhoneNumber">Contacto </label></td>
                      <td>
                        <input type="text" class="form-control" id="patientPhoneNumber" name="patientPhoneNumber" placeholder="Contacto" title="Please enter Contacto" value="<?php echo $vlQueryInfo['patient_mobile_number']; ?>" style="width:100%;" />
                      </td>
                      <td><label for="consentReceiveSms">Autoriza contacto </label></td>
                      <td style="width:16%;">
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Sim</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="consentReceiveSmsYes" name="consentReceiveSms" value="yes" <?php echo (trim($vlQueryInfo['consent_to_receive_sms']) == "yes") ? 'checked="checked"' : ''; ?> title="Please check Autoriza contacto">
                        </label>
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Não</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="consentReceiveSmsNo" name="consentReceiveSms" value="no" <?php echo (trim($vlQueryInfo['consent_to_receive_sms']) == "no") ? 'checked="checked"' : ''; ?> title="Please check Autoriza contacto">
                        </label>
                      </td>
                    </tr>
                  </table>
                </div>
                <div class="box box-primary">
                  <div class="box-header with-border">
                    <h3 class="box-title">C. INFORMAÇÃO DE TRATAMENTO</h3>
                  </div>
                  <table aria-describedby="table" class="table" aria-hidden="true"  style="width:100%">
                    <tr>
                      <td style="width:14%;"><label for="">Data de início de TARV </label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control date" id="dateOfArtInitiation" name="dateOfArtInitiation" placeholder="<?= _("Please enter date"); ?>" title="Please select Data de início de TARV" value="<?php echo $vlQueryInfo['treatment_initiated_date']; ?>" onchange="checkARTInitiationDate();" style="width:100%;" />
                      </td>
                      <td style="width:14%;"><label for="artRegimen"> Esquema de TARV actual </label></td>
                      <td style="width:14%;">
                        <select class="form-control " id="artRegimen" name="artRegimen" placeholder="Esquema de TARV actual" title="Please enter Esquema de TARV actual" style="width:100%;" onchange="checkARTRegimenValue();">
                          <option value="">-- Select --</option>
                          <?php foreach ($artRegimenResult as $heading) { ?>
                            <optgroup label="<?= $heading['headings']; ?>">
                              <?php
                              foreach ($aResult as $regimen) {
                                if ($heading['headings'] == $regimen['headings']) { ?>
                                  <option value="<?php echo $regimen['art_code']; ?>" <?php echo ($regimen['art_code'] == $vlQueryInfo['current_regimen']) ? 'selected="selected"' : ''; ?>><?php echo $regimen['art_code']; ?></option>
                              <?php }
                              } ?>
                            </optgroup>
                          <?php }
                          if ($sarr['sc_user_type'] != 'vluser') {  ?>
                            <option value="other">outros</option>
                          <?php } ?>
                        </select>
                        <input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="ART Regimen" title="Please enter art regimen" style="width:100%;display:none;margin-top:2px;">
                      </td>
                      <td><label for="sex">Linha de TARV actua </label></td>
                      <td style="width:32%;">
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Primeira</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="lineTrtFirst" name="lineTreatment" value="1" <?php echo (trim($vlQueryInfo['line_of_treatment']) == "1") ? 'checked="checked"' : ''; ?> title="Please check Linha de TARV actua">
                        </label>
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Segunda</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="lineTrtSecond" name="lineTreatment" value="2" <?php echo (trim($vlQueryInfo['line_of_treatment']) == "2") ? 'checked="checked"' : ''; ?> title="Please check Linha de TARV actua">
                        </label>
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Terceira</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="lineTrtThird" name="lineTreatment" value="3" <?php echo (trim($vlQueryInfo['line_of_treatment']) == "3") ? 'checked="checked"' : ''; ?> title="Please check Linha de TARV actua">
                        </label>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="3"><label for="sex">Se o paciente está em 2ª ou 3ª linha de TARV, indique o tipo de falência </label></td>
                      <td colspan="3">
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">N/A</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="lineTreatmentRefType" id="lineTreatmentNoResult" name="lineTreatmentRefType" value="na" <?php echo (trim($vlQueryInfo['line_of_treatment_ref_type']) == "na") ? 'checked="checked"' : ''; ?> title="Please check indique o tipo de falência">
                        </label>
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Virológica</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="lineTreatmentRefType" id="lineTreatmentVirological" name="lineTreatmentRefType" value="virological" <?php echo (trim($vlQueryInfo['line_of_treatment_ref_type']) == "virological") ? 'checked="checked"' : ''; ?> title="Please check indique o tipo de falência">
                        </label>
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Imunológica</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="lineTreatmentRefType" id="lineTreatmentimmunological" name="lineTreatmentRefType" value="immunological" <?php echo (trim($vlQueryInfo['line_of_treatment_ref_type']) == "immunological") ? 'checked="checked"' : ''; ?> title="Please check indique o tipo de falência">
                        </label>
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Clínica</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="lineTreatmentRefType" id="lineTreatmentClinical" name="lineTreatmentRefType" value="clinical" <?php echo (trim($vlQueryInfo['line_of_treatment_ref_type']) == "clinical") ? 'checked="checked"' : ''; ?> title="Please check indique o tipo de falência">
                        </label>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="6">Refira em que grupo(s) o paciente se enquadra</td>
                    </tr>
                    <tr>
                      <td colspan="6">
                        <label class="radio-inline" style="width:1%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="patientGeneralPopulation" name="patientGroup" value="general_population" title="Please check População geral" <?php echo (isset($patientGrp->patient_group) && trim($patientGrp->patient_group) == "general_population") ? 'checked="checked"' : ''; ?>>
                        </label>
                        <label class="radio-inline" style="padding-left:0px !important;margin-left:0;">População geral (adulto, criança ou mulheres não grávidas)</label>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="6">
                        <label class="radio-inline" style="width:1%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="patientKeyPopulation" name="patientGroup" value="key_population" title="Please check População chave – especifique" <?php echo (isset($patientGrp->patient_group) && trim($patientGrp->patient_group) == "key_population") ? 'checked="checked"' : ''; ?>>
                        </label>
                        <label class="radio-inline" style="padding-left:0px !important;margin-left:0;">População chave – especifique</label>

                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">HSH/Trans</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="patientGroupKeyMSM" name="patientGroupKeyOption" value="msm" title="Please check HSH/Trans" <?php echo (isset($patientGrp->patient_group_option) == "msm") ? 'checked="checked"' : ''; ?>>
                        </label>
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">TS</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="patientGroupKeySW" name="patientGroupKeyOption" value="sw" title="Please check TS" <?php echo (isset($patientGrp->patient_group_option) == "sw") ? 'checked="checked"' : ''; ?>>
                        </label>
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Outro</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="patientGroupKeyOther" name="patientGroupKeyOption" value="other" title="Please check Outro" <?php echo (isset($patientGrp->patient_group_option) == "other") ? 'checked="checked"' : ''; ?>>
                        </label>
                        <input type="text" class="form-control" name="patientGroupKeyOtherText" id="patientGroupKeyOtherText" title="Please enter value" value="<?php echo (isset($patientGrp->patient_group_option_other)) ? $patientGrp->patient_group_option_other : ''; ?>" />
                      </td>
                    </tr>
                    <tr>
                      <td colspan="6">
                        <label class="radio-inline" style="width:1%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="patientPregnantWoman" name="patientGroup" value="pregnant" title="Please check Mulher gestante" <?php echo (isset($patientGrp->patient_group) && trim($patientGrp->patient_group) == "pregnant") ? 'checked="checked"' : ''; ?>>
                        </label>
                        <label class="radio-inline" style="padding-left:0px !important;margin-left:0;">Mulher gestante – indique a data provável do parto</label>
                        <input type="text" class="form-control date" name="patientPregnantWomanDate" id="patientPregnantWomanDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter data provável do parto" value="<?php echo (isset($patientGrp->patient_group_option_date)) ? $patientGrp->patient_group_option_date : ''; ?>" />
                      </td>
                    </tr>
                    <tr>
                      <td colspan="6">
                        <label class="radio-inline" style="width:1%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="breastFeeding" name="patientGroup" value="breast_feeding" title="Please check Mulher lactante" <?php echo (isset($patientGrp->patient_group) && trim($patientGrp->patient_group) == "breast_feeding") ? 'checked="checked"' : ''; ?>>
                        </label>
                        <label class="radio-inline" style="padding-left:0px !important;margin-left:0;">Mulher lactante</label>
                      </td>
                    </tr>
                  </table>
                </div>
                <div class="box box-primary">
                  <div class="box-header with-border">
                    <h3 class="box-title">D. INDICAÇÃO PARA SOLICITAÇÃO DE CARGA VIRAL</h3>
                  </div>
                  <table aria-describedby="table" class="table" aria-hidden="true"  style="width:100%">
                    <?php
                    $vlTestReasonQueryRow = "SELECT * from r_vl_test_reasons where test_reason_id='" . trim($vlQueryInfo['reason_for_vl_testing']) . "' OR test_reason_name = '" . trim($vlQueryInfo['reason_for_vl_testing']) . "'";
                    $vlTestReasonResultRow = $db->query($vlTestReasonQueryRow);
                    ?>
                    <tr>
                      <td colspan="6">
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Monitoria de rotina</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="routineMonitoring" name="indicateVlTesing" value="routine" <?php echo (trim($vlQueryInfo['reason_for_vl_testing']) == "routine" || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'routine') ? 'checked="checked"' : ''; ?> title="Please check Monitoria de rotina">
                        </label>
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Diagnóstico de criança exposta </label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="exposeChild" name="indicateVlTesing" value="expose" <?php echo (trim($vlQueryInfo['reason_for_vl_testing']) == "expose" || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'expose') ? 'checked="checked"' : ''; ?> title="Please check Diagnóstico de criança exposta">
                        </label>
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Suspeita de falência de tratamento</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="suspectedTreatment" name="indicateVlTesing" value="suspect" <?php echo (trim($vlQueryInfo['reason_for_vl_testing']) == "suspect" || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'suspect') ? 'checked="checked"' : ''; ?> title="Please check Suspeita de falência de tratamento">
                        </label>
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Repetição após CV≥ 1000 cp/mL</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="repetition" name="indicateVlTesing" value="repetition" <?php echo (trim($vlQueryInfo['reason_for_vl_testing']) == "repetition" || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'repetition') ? 'checked="checked"' : ''; ?> title="Please check Repetição após CV≥ 1000 cp/mL">
                        </label>
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Falência clínica</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="clinicalFailure" name="indicateVlTesing" value="clinical" <?php echo (trim($vlQueryInfo['reason_for_vl_testing']) == "clinical" || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'clinical') ? 'checked="checked"' : ''; ?> title="Please check Falência clínica">
                        </label>
                        <label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Falência imunológica</label>
                        <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                          <input type="radio" class="" id="immunologicalFailure" name="indicateVlTesing" value="immunological" <?php echo (trim($vlQueryInfo['reason_for_vl_testing']) == "immunological" || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'immunological') ? 'checked="checked"' : ''; ?> title="Please check Falência imunológica">
                        </label>
                      </td>
                    </tr>
                    <tr>
                      <td style="width:14%;"><label for="">Se aplicável: data da última carga viral </label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control date" id="lastVlDate" name="lastVlDate" placeholder="<?= _("Please enter date"); ?>" title="Please select data da última carga viral" style="width:100%;" value="<?php echo $lastVlDate; ?>" />
                      </td>
                      <td style="width:14%;"><label for="lastVlResult"> Resultado da última carga vira </label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control" id="lastVlResult" name="lastVlResult" placeholder="Resultado da última carga vira" title="Please enter Resultado da última carga vira" style="width:100%;" value="<?php echo $lastVlResult; ?>" />
                      </td>
                    </tr>
                  </table>
                </div>
                <div class="box box-primary">
                  <div class="box-header with-border">
                    <h3 class="box-title">E. UNIDADE DE COLHEITA</h3>
                  </div>
                  <table aria-describedby="table" class="table" aria-hidden="true"  style="width:100%">
                    <tr>
                      <td style="width:14%;"><label for="fName">Nome da Unidade de colheita (se diferente da Unidade de solicitação) <span class="mandatory">*</span></label></td>
                      <td style="width:14%;">
                        <select class="form-control isRequired" name="fName" id="fName" title="Please choose Nome de colheita" style="width:100%;">
                          <?php foreach ($fResult as $fDetails) { ?>
                            <option value="<?php echo $fDetails['facility_id']; ?>" <?php echo ($vlQueryInfo['facility_id'] == $fDetails['facility_id']) ? "selected='selected'" : "" ?>><?php echo ($fDetails['facility_name']); ?></option>
                          <?php } ?>
                        </select>
                      </td>
                      <td style="width:14%;"><label for="collectionSite"> Local de colheita </label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control " id="collectionSite" name="collectionSite" placeholder="Local de colheita" title="Please enter Local de colheita" style="width:100%;" value="<?php echo $vlQueryInfo['collection_site']; ?>" />
                      </td>
                      <td style="width:14%;"><label for="sampleCollectionDate"> Data Hora de colheita <span class="mandatory">*</span></label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control isRequired dateTime" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Data Hora de colheita" title="Please enter Data Hora de colheita" value="<?php echo $vlQueryInfo['sample_collection_date']; ?>" onchange="checkSampleReceviedDate();checkSampleTestingDate();" style="width:100%;" />
                      </td>
                    </tr>
                    <tr>
                      <td style="width:14%;"><label for="reqClinician">Responsável pela colheita </label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control" id="reqClinician" name="reqClinician" placeholder="Responsável pela colheita" title="Please select Responsável pela colheita" value="<?php echo $vlQueryInfo['request_clinician_name']; ?>" style="width:100%;" />
                      </td>
                      <td style="width:14%;"><label for="reqClinicianPhoneNumber"> Contacto </label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" placeholder="Contacto" title="Please enter Contacto" value="<?php echo $vlQueryInfo['request_clinician_phone_number']; ?>" style="width:100%;" />
                      </td>
                      <td style="width:14%;"><label for="sampleType"> Tipo de amostra </label></td>
                      <td style="width:14%;">
                        <select name="specimenType" id="specimenType" class="form-control" title="Please choose Tipo de amostra" style="width: 100%" ;>
                          <option value="">-- Selecione --</option>
                          <?php foreach ($sResult as $name) { ?>
                            <option value="<?php echo $name['sample_id']; ?>" <?php echo ($vlQueryInfo['sample_type'] == $name['sample_id']) ? 'selected="selected"' : ''; ?>><?= $name['sample_name']; ?></option>
                          <?php } ?>
                        </select>
                      </td>
                    </tr>
                  </table>
                </div>
                <div class="box box-primary" style="<?php if ($_SESSION['instanceType'] == 'remoteuser') { ?> pointer-events:none;<?php } ?>">
                  <div class="box-header with-border">
                    <h3 class="box-title">Informações laboratoriais</h3>
                  </div>
                  <table aria-describedby="table" class="table" aria-hidden="true"  style="width:100%">
                    <tr>
                      <td style="width:14%;"><label for="sampleCode"> Nº de amostra </label><span class="mandatory">*</span></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Nº de amostra" title="Please enter Nº de amostra" style="width:100%;" value="<?php echo (isset($sCode) && trim($sCode) != '') ? $sCode : $vlQueryInfo[$sampleCode]; ?>" onchange="checkSampleNameValidation('form_vl','<?php echo $sampleCode; ?>',this.id,'<?php echo "vl_sample_id##" . $vlQueryInfo["vl_sample_id"]; ?>','The sample number that you entered already exists. Please try another number',null)" />
                        <input type="hidden" name="sampleCodeCol" value="<?php echo $vlQueryInfo['sample_code']; ?>" />
                      </td>
                    </tr>
                    <tr>
                      <td style="width:14%;"><label for="">Nome do laboratório</label></td>
                      <td style="width:14%;">
                        <select name="labId" id="labId" class="form-control" title="Please choose Nome do laboratório" style="width: 100%" ;>
                          <?= $general->generateSelectOptions($testingLabs, $vlQueryInfo['lab_id'], '-- Select --'); ?>
                        </select>
                      </td>
                      <td style="width:14%;"><label for="testingPlatform"> Plataforma de teste VL </label></td>
                      <td style="width:14%;">
                        <select name="testingPlatform" id="testingPlatform" class="form-control" title="Please choose Plataforma de teste VL" style="width: 100%">
                          <option value="">-- Select --</option>
                          <?php foreach ($importResult as $mName) { ?>
                            <option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']; ?>" <?php echo ($vlQueryInfo['vl_test_platform'] == $mName['machine_name']) ? 'selected="selected"' : ''; ?>><?php echo $mName['machine_name']; ?></option>
                          <?php } ?>
                        </select>
                      </td>
                      <td style="width:14%;"><label for="vlFocalPerson"> Responsável da recepção </label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control" id="vlFocalPerson" name="vlFocalPerson" placeholder="Responsável da recepção" title="Please enter responsável da recepção" style="width:100%;" value="<?php echo $vlQueryInfo['vl_focal_person']; ?>" />
                      </td>
                    </tr>
                    <tr>
                      <td style="width:14%;"><label for="sampleReceivedDate"> Data de Recepção de Amostras </label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="Data de Recepção de Amostras" title="Please select Data de Recepção de Amostras" value="<?php echo $vlQueryInfo['sample_received_at_vl_lab_datetime']; ?>" onchange="checkSampleReceviedDate();" />
                      </td>
                      <td style="width:14%;"><label for="">Data da Quantificação</label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Data da Quantificação" title="Please select Data da Quantificação" value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>" onchange="checkSampleTestingDate();" />
                      </td>
                      <td style="width:14%;"><label for="resultDispatchedOn"> Data de Emissão de Resultados </label></td>
                      <td style="width:14%;">
                        <input type="text" class="form-control dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Data de Emissão de Resultados" title="Please select Data de Emissão de Resultados" value="<?php echo $vlQueryInfo['result_dispatched_datetime']; ?>" />
                      </td>
                    </tr>
                    <tr>
                      <td style="width:14%;"><label for="reviewedBy"> Revisados ​Pela </label></td>
                      <td style="width:14%;">
                        <select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose revisados ​​pela" style="width: 100%;">
                          <?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_reviewed_by'], '-- Select --'); ?>
                        </select>
                      </td>
                      <td style="width:14%;"><label for="reviewedOn"> Revisado Em </label></td>
                      <td style="width:14%;">
                        <input type="text" name="reviewedOn" value="<?php echo $vlQueryInfo['result_reviewed_datetime']; ?>" id="reviewedOn" class="dateTime form-control" placeholder="Revisado em" title="Please enter the revisado em" />
                      </td>
                    </tr>
                    <tr>
                      <td style="width:14%;"><label for="noResult"> Rejeição da amostra</label></td>
                      <td style="width:14%;">
                        <label class="radio-inline">
                          <input class="" id="noResultYes" name="noResult" value="yes" title="Rejeição da amostra" type="radio" <?php echo (trim($vlQueryInfo['is_sample_rejected']) == "yes") ? 'checked="checked"' : ''; ?>> Yes
                        </label>
                        <label class="radio-inline">
                          <input class="" id="noResultNo" name="noResult" value="no" title="Rejeição da amostra" type="radio" <?php echo (trim($vlQueryInfo['is_sample_rejected']) == "no") ? 'checked="checked"' : ''; ?>> No
                        </label>
                      </td>
                      <td class="rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
                        <label for="rejectionReason">Razão de rejeição <span class="mandatory">*</span></label>
                      </td>
                      <td class="rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
                        <select name="rejectionReason" id="rejectionReason" class="form-control" title="Please choose Razão de rejeição" onchange="checkRejectionReason();" style="width: 193px;">
                          <option value="">-- Select --</option>
                          <?php foreach ($rejectionTypeResult as $type) { ?>
                            <optgroup label="<?php echo ($type['rejection_type']); ?>">
                              <?php
                              foreach ($rejectionResult as $reject) {
                                if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                  <option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?= $reject['rejection_reason_name']; ?></option>
                              <?php }
                              } ?>
                            </optgroup>
                          <?php }
                          if ($sarr['sc_user_type'] != 'vluser') {  ?>
                            <option value="other">Outro (por favor, especifique) </option>
                          <?php } ?>
                        </select>
                        <input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="Rejection Reason" title="Please enter rejection reason" style="width:100%;display:none;margin-top:2px;">
                      </td>
                      <td class="vlResult" style="visibility:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'hidden' : 'visible'; ?>;">
                        <label for="vlResult">Resultado da carga viral (cópias / ml) </label>
                      </td>
                      <td class="vlResult" style="visibility:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'hidden' : 'visible'; ?>;">
                        <input type="text" class="form-control labSection" id="vlResult" name="vlResult" placeholder="Viral Load Result" title="Please enter resultado da carga viral" value="<?php echo $vlQueryInfo['result']; ?>" <?php echo ($vlQueryInfo['result'] == 'Target Not Detected' || $vlQueryInfo['result'] == 'Below Detection Level' || $vlQueryInfo['result'] == 'Low Detection Level' || $vlQueryInfo['result'] == 'High Detection Level') ? 'readonly="readonly"' : ''; ?> style="width:100%;" onchange="calculateLogValue(this);" />
                        <input type="checkbox" class="labSection" id="tnd" name="tnd" value="yes" <?php echo ($vlQueryInfo['result'] == 'Target Not Detected') ? 'checked="checked"' : '';
                                                                                                  echo ($vlQueryInfo['result'] == 'Below Detection Level' || $vlQueryInfo['result'] == 'Low Detection Level' || $vlQueryInfo['result'] == 'High Detection Level') ? 'disabled="disabled"' : '' ?> title="Please check tnd"> Target não detectado<br>
                        <input type="checkbox" class="labSection" id="ldl" name="ldl" value="yes" <?php echo ($vlQueryInfo['result'] == 'Below Detection Level' || $vlQueryInfo['result'] == 'Low Detection Level') ? 'checked="checked"' : '';
                                                                                                  echo ($vlQueryInfo['result'] == 'Target Not Detected' || $vlQueryInfo['result'] == 'High Detection Level') ? 'disabled="disabled"' : '' ?> title="Please check ldl"> Abaixo do nível de detecção<br>
                        <input type="checkbox" class="labSection" id="hdl" name="hdl" value="yes" <?php echo ($vlQueryInfo['result'] == 'High Detection Level') ? 'checked="checked"' : '';
                                                                                                  echo ($vlQueryInfo['result'] == 'Target Not Detected' || $vlQueryInfo['result'] == 'Below Detection Level' || $vlQueryInfo['result'] == 'Low Detection Level') ? 'disabled="disabled"' : '' ?> title="Please check hdl"> Acima do limite superior de detecção
                      </td>
                      <td class="vlResult" style="visibility:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'hidden' : 'visible'; ?>;">
                        <label for="vlLog">Resultado da Carga Viral (Log) </label>
                      </td>
                      <td class="vlResult" style="visibility:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'hidden' : 'visible'; ?>;">
                        <input type="text" class="form-control labSection" id="vlLog" name="vlLog" placeholder="Resultado da Carga Viral" title="Please enter Resultado da Carga Viral" value="<?php echo $vlQueryInfo['result_value_log']; ?>" <?php echo ($vlQueryInfo['result'] == 'Target Not Detected' || $vlQueryInfo['result'] == 'Below Detection Level' || $vlQueryInfo['result'] == 'Low Detection Level' || $vlQueryInfo['result'] == 'High Detection Level') ? 'readonly="readonly"' : ''; ?> style="width:100%;" onchange="calculateLogValue(this);" />
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <label for="labTechnician">Técnico Executor </label>
                      </td>
                      <td>
                        <input type="text" class="form-control" id="labTechnician" name="labTechnician" placeholder="Técnico Executor" title="Please enter Técnico Executor" value="<?php echo $vlQueryInfo['lab_technician']; ?>" style="width:100%;" />
                      </td>
                      <td>
                        <label for="approvedBy">Aprovado por </label>
                      </td>
                      <td>
                        <select name="approvedBy" id="approvedBy" class="form-control" title="Please choose Aprovado por" style="width: 100%">
                          <option value="">-- Select --</option>
                          <?php foreach ($userResult as $uName) { ?>
                            <option value="<?php echo $uName['user_id']; ?>" <?php echo ($vlQueryInfo['result_approved_by'] == $uName['user_id']) ? "selected=selected" : ""; ?>><?php echo ($uName['user_name']); ?></option>
                          <?php } ?>
                        </select>
                      </td>
                      <td>
                        <!-- <label for="status">Status <span class="mandatory">*</span></label> -->
                      </td>
                      <td>
                        <!-- <select class="form-control labSection <?php echo ($_SESSION['instanceType'] == 'remoteuser') ? '' : 'isRequired'; ?>" id="status" name="status" title="Selecione o estado do teste" style="width: 100%;">
                            <option value="">-- Select --</option>
                            <?php foreach ($statusResult as $status) { ?>
                              <option value="<?php echo $status['status_id']; ?>"<?php echo ($vlQueryInfo['result_status'] == $status['status_id']) ? 'selected="selected"' : ''; ?>><?php echo ($status['status_name']); ?></option>
                            <?php } ?>
                          </select> -->
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <label for="labComments">Comentários do cientista de laboratório </label>
                      </td>
                      <td colspan="7">
                        <textarea class="form-control" name="labComments" id="labComments" placeholder="Comentários do laboratório" style="width:100%"><?php echo trim($vlQueryInfo['lab_tech_comments']); ?></textarea>
                      </td>
                    </tr>
                    <tr class="reasonForResultChanges" style="visibility:hidden;">
                      <td>
                        <label for="reasonForResultChanges">Razão para as mudanças nos resultados <span class="mandatory">*</span></label>
                      </td>
                      <td colspan="7">
                        <textarea class="form-control" name="reasonForResultChanges" id="reasonForResultChanges" placeholder="Enter Reason For Result Changes" title="Razão para as mudanças nos resultados" style="width:100%;"></textarea>
                      </td>
                    </tr>
                  </table>
                  <?php if (trim($rch) != '') { ?>
                    <div class="box-body">
                      <div class="row">
                        <div class="col-md-12"><?php echo $rch; ?></div>
                      </div>
                    </div>
                  <?php } ?>
                </div>
              </div>
            </div>
            <!-- /.box-body -->
            <div class="box-footer">
              <input type="hidden" name="revised" id="revised" value="no" />
              <input type="hidden" name="vlSampleId" id="vlSampleId" value="<?php echo $vlQueryInfo['vl_sample_id']; ?>" />
              <input type="hidden" name="isRemoteSample" value="<?php echo $vlQueryInfo['remote_sample']; ?>" />
              <input type="hidden" name="reasonForResultChangesHistory" id="reasonForResultChangesHistory" value="<?php echo $vlQueryInfo['reason_for_vl_result_changes']; ?>" />
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
  provinceName = facilityName = true;
  $(document).ready(function() {
    getfacilityProvinceDetails($("#clinicName").val());
    $("#clinicName").select2({
      placeholder: "Nome da Unidade"
    });
    __clone = $("#vlRequestFormAng .labSection").clone();
    reason = ($("#reasonForResultChanges").length) ? $("#reasonForResultChanges").val() : '';
    result = ($("#vlResult").length) ? $("#vlResult").val() : '';
  });

  function getfacilityDetails(obj) {
    $.blockUI();
    var cName = $("#clinicName").val();
    var pName = $("#province").val();
    if (pName != '' && provinceName && facilityName) {
      facilityName = false;
    }
    if ($.trim(pName) != '') {
      if (provinceName) {
        $.post("/includes/siteInformationDropdownOptions.php", {
            pName: pName,
            testType: 'vl'
          },
          function(data) {
            if (data != "") {
              details = data.split("###");
              $("#clinicName").html(details[0]);
              $("#district").html(details[1]);
              $("#clinicianName").val(details[2]);
            }
          });
      }
    } else if (pName == '' && cName == '') {
      provinceName = true;
      facilityName = true;
      $("#province").html("<?php echo $province; ?>");
      $("#clinicName").html("<?php echo $facility; ?>");
    } else {
      $("#district").html("<option value=''> -- Selecione -- </option>");
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
            //$("#clinicName").html(data);
            details = data.split("###");
            $("#clinicName").html(details[0]);
            //$("#labId").html(details[1]);
          }
        });
    } else {
      $("#clinicName").html("<option value=''> -- Selecione -- </option>");
    }
    $.unblockUI();
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
            //$("#clinicianName").val(details[2]);
          }
        });
    } else if (pName == '' && cName == '') {
      provinceName = true;
      facilityName = true;
      $("#province").html("<?php echo $province; ?>");
      $("#clinicName").html("<?php echo $facility; ?>");
    }
    $.unblockUI();
  }

  function checkRejectionReason() {
    var rejectionReason = $("#rejectionReason").val();
    if (rejectionReason == "other") {
      $(".newRejectionReason").show();
    } else {
      $(".newRejectionReason").hide();
    }
  }

  function validateNow() {
    flag = deforayValidator.init({
      formId: 'vlRequestFormAng'
    });
    if (flag) {
      if ($("#clinicName").val() == null || $("#clinicName").val() == '') {
        alert('Please choose Nome da Unidade');
        return false;
      }
      $.blockUI();
      document.getElementById('vlRequestFormAng').submit();
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
      if (logValue != '' && logValue != 0 && !isNaN(logValue)) {
        var absVal = Math.round(Math.pow(10, logValue) * 100) / 100;
        if (absVal != 'Infinity' && !isNaN(absVal)) {
          $("#vlResult").val(Math.round(Math.pow(10, logValue) * 100) / 100);
        }
      } else {
        $("#vlResult").val('');
      }
    }
  }

  $("input:radio[name=noResult]").click(function() {
    if ($(this).val() == 'yes') {
      $('.vlResult').hide();
      $('.rejectionReason').show();
      $('#rejectionReason').addClass('isRequired');
      $('#vlResult').removeClass('isRequired');
    } else {
      $('.vlResult').show();
      $('.rejectionReason').hide();
      $('#rejectionReason').removeClass('isRequired');
      $('#vlResult').addClass('isRequired');
      $('#rejectionReason').val('');
    }
  });

  $("input:radio[name=lineTreatment]").click(function() {
    if ($(this).val() == '1') {
      $('.lineTreatmentRefType').attr("disabled", true);
    } else {
      $('.lineTreatmentRefType').attr("disabled", false);
    }
  });

  $('#tnd').change(function() {
    if ($('#tnd').is(':checked')) {
      $('#vlResult,#vlLog').attr('readonly', true);
      $('#ldl,#hdl').attr('disabled', true);
    } else {
      $('#vlResult,#vlLog').attr('readonly', false);
      $('#ldl,#hdl').attr('disabled', false);
    }
  });
  $('#ldl').change(function() {
    if ($('#ldl').is(':checked')) {
      $('#vlResult,#vlLog').attr('readonly', true);
      $('#tnd,#hdl').attr('disabled', true);
    } else {
      $('#vlResult,#vlLog').attr('readonly', false);
      $('#tnd,#hdl').attr('disabled', false);
    }
  });
  $('#hdl').change(function() {
    if ($('#hdl').is(':checked')) {
      $('#vlResult,#vlLog').attr('readonly', true);
      $('#tnd,#ldl').attr('disabled', true);
    } else {
      $('#vlResult,#vlLog').attr('readonly', false);
      $('#tnd,#ldl').attr('disabled', false);
    }
  });
  $('#vlResult,#vlLog').on('input', function(e) {
    if (this.value != '') {
      $('#tnd,#ldl,#hdl').attr('disabled', true);
    } else {
      $('#tnd,#ldl,#hdl').attr('disabled', false);
    }
  });

  $(".labSection").on("change", function() {
    if ($.trim(result) != '') {
      if ($(".labSection").serialize() == $(__clone).serialize()) {
        $(".reasonForResultChanges").css("visibility", "hidden");
        $("#reasonForResultChanges").removeClass("isRequired");
      } else {
        $(".reasonForResultChanges").css("visibility", "visible");
        $("#reasonForResultChanges").addClass("isRequired");
      }
    }
  });
</script>