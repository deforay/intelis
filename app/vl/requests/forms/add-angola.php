<?php

$rKey = '';
if ($_SESSION['instanceType'] == 'remoteuser') {
	$sampleCodeKey = 'remote_sample_code_key';
	$sampleCode = 'remote_sample_code';
	$pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
	$rKey = 'R';
} else {
	$sampleCodeKey = 'sample_code_key';
	$sampleCode = 'sample_code';
	$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
}
$artRegimenQuery = "SELECT DISTINCT headings FROM r_vl_art_regimen";
$artRegimenResult = $db->rawQuery($artRegimenQuery);
$province = "<option value=''> -- Selecione -- </option>";
foreach ($pdResult as $provinceName) {
	$province .= "<option value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}
$facility = "<option value=''> -- Selecione -- </option>";
foreach ($fResult as $fDetails) {
	$facility .= "<option value='" . $fDetails['facility_id'] . "'>" . ($fDetails['facility_name']) . ' - ' . $fDetails['facility_code'] . "</option>";
}
//get ART list
$aQuery = "SELECT * from r_vl_art_regimen";
$aResult = $db->query($aQuery);

$sKey = '';
$sFormat = '';
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
			<li class="active">Add Vl Request</li>
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
				<form class="form-inline" method="post" name="addVlRequestForm" id="addVlRequestForm" autocomplete="off" action="addVlRequestHelperAng.php">
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
												<select class="form-control isRequired" name="province" id="province" title="Please choose província" onchange="getfacilityDetails(this);" style="width:100%;">
													<?php echo $province; ?>
												</select>
											</td>
											<td><label for="district">Município </label><span class="mandatory">*</span></td>
											<td>
												<select class="form-control isRequired" name="district" id="district" title="Please choose município" style="width:100%;" onchange="getfacilityDistrictwise(this);">
													<option value=""> -- Selecione -- </option>
												</select>
											</td>
											<td><label for="clinicName">Nome da Unidade </label><span class="mandatory">*</span></td>
											<td>
												<select name="clinicName" id="clinicName" title="Please choose Nome da Unidade" style="width:100%;" onchange="getfacilityProvinceDetails(this)">
													<?php echo $facility;  ?>
												</select>
											</td>
										</tr>
										<tr>
											<td><label for="sector">Serviço/Sector </label></td>
											<td>
												<input type="text" class="form-control" name="sector" id="sector" placeholder="Serviço/Sector" title="Please enter Serviço/Sector" />
											</td>
											<td><label for="reqClinician">Nome do solicitante </label></td>
											<td>
												<input type="text" class="form-control" name="reqClinician" id="reqClinician" placeholder="Nome do solicitante" title="Please enter Nome do solicitante" />
											</td>
											<td><label for="category">Categoria </label></td>
											<td>
												<select class="form-control" name="category" id="category" title="Please choose Categoria" style="width:100%;">
													<option value="">-- Selecione --</option>
													<option value="nurse">Enfermeiro/a</option>
													<option value="clinician">Médico/a</option>
												</select>
											</td>
										</tr>
										<tr>
											<td><label for="profNumber">Nº da Ordem </label></td>
											<td>
												<input type="text" class="form-control" name="profNumber" id="profNumber" placeholder="Nº da Ordem" title="Please enter Nº da Ordem" />
											</td>
											<td><label for="contactNo">Contacto </label></td>
											<td>
												<input type="text" class="form-control" name="contactNo" id="contactNo" placeholder="Contacto" title="Please enter Contacto" />
											</td>
											<td><label for="requestingDate">Data da solicitação </label></td>
											<td>
												<input type="text" class="form-control date" name="requestingDate" id="requestingDate" placeholder="Data da solicitação" title="Please choose Data da solicitação" style="width:100%;" />
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
												<input type="text" class="form-control " id="patientFirstName" name="patientFirstName" placeholder="Nome completo" title="Please enter Nome completo" style="width:100%;" />
											</td>
											<td style="width:14%;"><label for="patientArtNo">Nº Processo Clínico </label></td>
											<td style="width:14%;">
												<input type="text" class="form-control " id="patientArtNo" name="patientArtNo" placeholder="Nº Processo Clínico" title="Please enter Nº Processo Clínico" style="width:100%;" onchange="checkPatientDetails('form_vl','patient_art_no',this,null)" />
											</td>
											<td><label for="sex">Género </label></td>
											<td style="width:16%;">
												<label class="radio-inline" style="padding-left:10px !important;margin-left:0;">Masculino</label>
												<label class="radio-inline" style="width:2%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="genderMale" name="gender" value="male" title="Please check sexe">
												</label>
												<label class="radio-inline" style="padding-left:10px !important;margin-left:0;">Feminino</label>
												<label class="radio-inline" style="width:2%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="genderFemale" name="gender" value="female" title="Please check sexe">
												</label>
											</td>
											<td style="width:14%;"><label for="ageInMonths">Data de nascimento </label></td>
											<td style="width:14%;">
												<input type="text" class="form-control date" id="dob" name="dob" placeholder="Data de nascimento" title="Please enter Data de nascimento" onchange="getAge();checkARTInitiationDate();" style="width:100%;" />
											</td>
										</tr>
										<tr>
											<td><label for="ageInMonths"> Idade (em meses se < 1 ano) </label>
											</td>
											<td>
												<input type="text" class="form-control forceNumeric" id="ageInMonths" name="ageInMonths" placeholder="Mois" title="Please enter àge en mois" style="width:100%;" />
											</td>
											<td colspan="3"><label for="responsiblePersonName">Nome da Mãe/ Pai/ Familiar responsáve </label></td>
											<td>
												<input type="text" class="form-control" id="responsiblePersonName" name="responsiblePersonName" placeholder="Nome da Mãe/ Pai/ Familiar responsáve" title="Please enter Nome da Mãe/ Pai/ Familiar responsáve" style="width:100%;" />
											</td>
											<td><label for="patientDistrict">Município </label></td>
											<td>
												<input type="text" class="form-control" id="patientDistrict" name="patientDistrict" placeholder="Município" title="Please enter Município" style="width:100%;" />
											</td>
										</tr>
										<tr>
											<td><label for="patientProvince">Província </label></td>
											<td>
												<input type="text" class="form-control" id="patientProvince" name="patientProvince" placeholder="Província" title="Please enter Província" style="width:100%;" />
											</td>
											<td><label for="patientPhoneNumber">Contacto </label></td>
											<td>
												<input type="text" class="form-control" id="patientPhoneNumber" name="patientPhoneNumber" placeholder="Contacto" title="Please enter Contacto" style="width:100%;" />
											</td>
											<td><label for="consentReceiveSms">Autoriza contacto </label></td>
											<td style="width:16%;">
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Sim</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="consentReceiveSmsYes" name="consentReceiveSms" value="yes" title="Please check Autoriza contacto">
												</label>
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Não</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="consentReceiveSmsNo" name="consentReceiveSms" value="no" title="Please check Autoriza contacto">
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
												<input type="text" class="form-control date" id="dateOfArtInitiation" name="dateOfArtInitiation" placeholder="<?= _("Please enter date"); ?>" title="Please select Data de início de TARV" style="width:100%;" onchange="checkARTInitiationDate();" />
											</td>
											<td style="width:14%;"><label for="artRegimen"> Esquema de TARV actual </label></td>
											<td style="width:14%;">
												<select class="form-control " id="artRegimen" name="artRegimen" title="Please enter Esquema de TARV actual" style="width:100%;" onchange="checkARTRegimenValue();">
													<option value="">-- Select --</option>
													<?php foreach ($artRegimenResult as $heading) { ?>
														<optgroup label="<?= $heading['headings']; ?>">
															<?php foreach ($aResult as $regimen) {
																if ($heading['headings'] == $regimen['headings']) {
															?>
																	<option value="<?php echo $regimen['art_code']; ?>"><?php echo $regimen['art_code']; ?></option>
															<?php }
															} ?>
														</optgroup>
													<?php }
													if ($sarr['sc_user_type'] != 'vluser') {  ?>
														<option value="other">Outro</option>
													<?php } ?>
												</select>
												<input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="ART Regimen" title="Please enter art regimen" style="width:100%;display:none;margin-top:2px;">
											</td>
											<td><label for="lineTreatment">Linha de TARV actua </label></td>
											<td style="width:32%;">
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Primeira</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="lineTrtFirst" name="lineTreatment" value="1" title="Please check Linha de TARV actua">
												</label>
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Segunda</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="lineTrtSecond" name="lineTreatment" value="2" title="Please check Linha de TARV actua">
												</label>
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Terceira</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="lineTrtThird" name="lineTreatment" value="3" title="Please check Linha de TARV actua">
												</label>
											</td>
										</tr>
										<tr>
											<td colspan="3"><label for="sex">Se o paciente está em 2ª ou 3ª linha de TARV, indique o tipo de falência </label></td>
											<td colspan="3">
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">N/A</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="lineTreatmentRefType" id="lineTreatmentNoResult" name="lineTreatmentRefType" value="na" title="Please check indique o tipo de falência">
												</label>
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Virológica</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="lineTreatmentRefType" id="lineTreatmentVirological" name="lineTreatmentRefType" value="virological" title="Please check indique o tipo de falência">
												</label>
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Imunológica</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="lineTreatmentRefType" id="lineTreatmentimmunological" name="lineTreatmentRefType" value="immunological" title="Please check indique o tipo de falência">
												</label>
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Clínica</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="lineTreatmentRefType" id="lineTreatmentClinical" name="lineTreatmentRefType" value="clinical" title="Please check indique o tipo de falência">
												</label>
											</td>
										</tr>
										<tr>
											<td colspan="6">Refira em que grupo(s) o paciente se enquadra</td>
										</tr>
										<tr>
											<td colspan="6">
												<label class="radio-inline" style="width:1%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="patientGeneralPopulation" name="patientGroup" value="general_population" title="Please check População geral">
												</label>
												<label class="radio-inline" style="padding-left:0px !important;margin-left:0;">População geral (adulto, criança ou mulheres não grávidas)</label>
											</td>
										</tr>
										<tr>
											<td colspan="6">
												<label class="radio-inline" style="width:1%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="patientKeyPopulation" name="patientGroup" value="key_population" title="Please check População chave – especifique">
												</label>
												<label class="radio-inline" style="padding-left:0px !important;margin-left:0;">População chave – especifique</label>

												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">HSH/Trans</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="patientGroupKeyMSM" name="patientGroupKeyOption" value="msm" title="Please check HSH/Trans">
												</label>
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">TS</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="patientGroupKeySW" name="patientGroupKeyOption" value="sw" title="Please check TS">
												</label>
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Outro</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="patientGroupKeyOther" name="patientGroupKeyOption" value="other" title="Please check Outro">
												</label>
												<input type="text" class="form-control" name="patientGroupKeyOtherText" id="patientGroupKeyOtherText" title="Please enter value" />
											</td>
										</tr>
										<tr>
											<td colspan="6">
												<label class="radio-inline" style="width:1%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="patientPregnantWoman" name="patientGroup" value="pregnant" title="Please check Mulher gestante">
												</label>
												<label class="radio-inline" style="padding-left:0px !important;margin-left:0;">Mulher gestante – indique a data provável do parto</label>
												<input type="text" class="form-control date" name="patientPregnantWomanDate" id="patientPregnantWomanDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter data provável do parto" />
											</td>
										</tr>
										<tr>
											<td colspan="6">
												<label class="radio-inline" style="width:1%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="breastFeeding" name="patientGroup" value="breast_feeding" title="Please check Mulher lactante">
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
										<tr>
											<td colspan="6">
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Monitoria de rotina</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="routineMonitoring" name="indicateVlTesing" value="routine" title="Please check Monitoria de rotina">
												</label>
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Diagnóstico de criança exposta </label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="exposeChild" name="indicateVlTesing" value="expose" title="Please check Diagnóstico de criança exposta">
												</label>
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Suspeita de falência de tratamento</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="suspectedTreatment" name="indicateVlTesing" value="suspect" title="Please check Suspeita de falência de tratamento">
												</label>
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Repetição após CV≥ 1000 cp/mL</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="repetition" name="indicateVlTesing" value="repetition" title="Please check Repetição após CV≥ 1000 cp/mL">
												</label>
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Falência clínica</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="clinicalFailure" name="indicateVlTesing" value="clinical" title="Please check Falência clínica">
												</label>
												<label class="radio-inline" style="padding-left:17px !important;margin-left:0;">Falência imunológica</label>
												<label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
													<input type="radio" class="" id="immunologicalFailure" name="indicateVlTesing" value="immunological" title="Please check Falência imunológica">
												</label>
											</td>
										</tr>
										<tr>
											<td style="width:14%;"><label for="">Se aplicável: data da última carga viral </label></td>
											<td style="width:14%;">
												<input type="text" class="form-control date" id="lastVlDate" name="lastVlDate" placeholder="<?= _("Please enter date"); ?>" title="Please select data da última carga viral" style="width:100%;" />
											</td>
											<td style="width:14%;"><label for="lastVlResult"> Resultado da última carga vira </label></td>
											<td style="width:14%;">
												<input type="text" class="form-control" id="lastVlResult" name="lastVlResult" placeholder="Resultado da última carga vira" title="Please enter Resultado da última carga vira" style="width:100%;" />
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
													<?php echo $facility;  ?>
												</select>
											</td>
											<td style="width:14%;"><label for="collectionSite"> Local de colheita </label></td>
											<td style="width:14%;">
												<input type="text" class="form-control " id="collectionSite" name="collectionSite" placeholder="Local de colheita" title="Please enter Local de colheita" style="width:100%;" />
											</td>
											<td style="width:14%;"><label for="sampleCollectionDate"> Data Hora de colheita <span class="mandatory">*</span></label></td>
											<td style="width:14%;">
												<input type="text" class="form-control dateTime isRequired" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Data Hora de colheita" title="Please enter Data Hora de colheita" style="width:100%;" onchange="sampleCodeGeneration();" />
											</td>
										</tr>
										<tr>
											<td style="width:14%;"><label for="requestingPerson">Responsável pela colheita </label></td>
											<td style="width:14%;">
												<input type="text" class="form-control" id="requestingPerson" name="requestingPerson" placeholder="Responsável pela colheita" title="Please select Responsável pela colheita" style="width:100%;" />
											</td>
											<td style="width:14%;"><label for="requestingContactNo"> Contacto </label></td>
											<td style="width:14%;">
												<input type="text" class="form-control" id="requestingContactNo" name="requestingContactNo" placeholder="Contacto" title="Please enter Contacto" style="width:100%;" />
											</td>
											<td style="width:14%;"><label for="sampleType"> Tipo de amostra </label></td>
											<td style="width:14%;">
												<select name="specimenType" id="specimenType" class="form-control" title="Please choose Tipo de amostra" style="width:100%">
													<option value="">-- Selecione --</option>
													<?php foreach ($sResult as $name) { ?>
														<option value="<?php echo $name['sample_id']; ?>"><?= $name['sample_name']; ?></option>
													<?php } ?>
												</select>
											</td>
										</tr>
									</table>
								</div>
								<div class="box box-primary">
									<div class="box-header with-border">
										<h3 class="box-title">Informações laboratoriais</h3>
									</div>
									<table aria-describedby="table" class="table" aria-hidden="true"  style="width:100%">
										<tr>
											<td style="width:14%;"><label for="sampleCode"> Nº de amostra </label><span class="mandatory">*</span></td>
											<td style="width:14%;">
												<input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Nº de amostra" title="Please enter Nº de amostra" style="width:100%;" onblur="checkSampleNameValidation('form_vl','<?php echo $sampleCode; ?>',this.id,null,'The sample number that you entered already exists. Please try another number',null)" />
											</td>
										</tr>
										<tr>
											<td style="width:14%;"><label for="">Nome do laboratório</label></td>
											<td style="width:14%;">
												<select name="labId" id="labId" class="form-control" title="Please choose Nome do laboratório" style="width: 100%;">
													<?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
												</select>
											</td>
											<td style="width:14%;"><label for="testingPlatform"> Plataforma de teste VL </label></td>
											<td style="width:14%;">
												<select name="testingPlatform" id="testingPlatform" class="form-control" title="Please choose Plataforma de teste VL" style="width: 100%;">
													<option value="">-- Select --</option>
													<?php foreach ($importResult as $mName) { ?>
														<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']; ?>"><?php echo $mName['machine_name']; ?></option>
													<?php } ?>
												</select>
											</td>
											<td style="width:14%;"><label for="vlFocalPerson"> Responsável da recepção </label></td>
											<td style="width:14%;">
												<input type="text" class="form-control" id="vlFocalPerson" name="vlFocalPerson" placeholder="Responsável da recepção" title="Please enter Responsável da recepção" style="width:100%;" />
											</td>
										</tr>
										<tr>
											<td style="width:14%;"><label for="sampleReceivedDate"> Data de Recepção de Amostras </label></td>
											<td style="width:14%;">
												<input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="Data de Recepção de Amostras" title="Please select Data de Recepção de Amostras" onchange="checkSampleReceviedDate()" />
											</td>
											<td style="width:14%;"><label for="">Data da Quantificação</label></td>
											<td style="width:14%;">
												<input type="text" class="form-control dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Data da Quantificação" title="Please select Data da Quantificação" onchange="checkSampleTestingDate();" />
											</td>
											<td style="width:14%;"><label for="resultDispatchedOn"> Data de Emissão de Resultados </label></td>
											<td style="width:14%;">
												<input type="text" class="form-control dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Data de Emissão de Resultados" title="Please select Data de Emissão de Resultados" />
											</td>
										</tr>
										<tr>
											<td style="width:14%;"><label for="reviewedBy"> Revisados ​Pela </label></td>
											<td style="width:14%;">
												<select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose revisados ​​pela" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
												</select>
											</td>
											<td style="width:14%;"><label for="reviewedOn"> Revisado Em </label></td>
											<td style="width:14%;">
												<input type="text" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="Revisado em" title="Please enter the revisado em" />
											</td>
										</tr>
										<tr>
											<td style="width:14%;"><label for="noResult"> Rejeição da amostra</label></td>
											<td style="width:14%;">
												<label class="radio-inline">
													<input class="" id="noResultYes" name="noResult" value="yes" title="Rejeição da amostra" type="radio"> Yes
												</label>
												<label class="radio-inline">
													<input class="" id="noResultNo" name="noResult" value="no" title="Rejeição da amostra" type="radio"> No
												</label>
											</td>
											<td class=" rejectionReason" style="display:none;">
												<label for="rejectionReason">Razão de rejeição </label>
											</td>
											<td class="rejectionReason" style="display:none;">
												<select name="rejectionReason" id="rejectionReason" class="form-control" title="Please choose Razão de rejeição" onchange="checkRejectionReason();" style="width: 193px;">
													<option value="">-- Select --</option>
													<?php foreach ($rejectionTypeResult as $type) { ?>
														<optgroup label="<?php echo ($type['rejection_type']); ?>">
															<?php
															foreach ($rejectionResult as $reject) {
																if ($type['rejection_type'] == $reject['rejection_type']) { ?>
																	<option value="<?php echo $reject['rejection_reason_id']; ?>"><?= $reject['rejection_reason_name']; ?></option>
															<?php
																}
															} ?>
														</optgroup>
													<?php }
													if ($sarr['sc_user_type'] != 'vluser') {  ?>
														<option value="other">Outro (por favor, especifique) </option>
													<?php } ?>
												</select>
												<input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="Razão de rejeição" title="Please enter Razão de rejeição" style="width:100%;display:none;margin-top:2px;">
											</td>
											<td class="vlResult">
												<label for="vlResult">Resultado da carga viral (cópias / ml) </label>
											</td>
											<td class="vlResult">
												<input type="text" class="form-control" id="vlResult" name="vlResult" placeholder="resultado da carga viral" title="Please enter viral load result" style="width:100%;" onchange="calculateLogValue(this)" />
												<input type="checkbox" id="tnd" name="tnd" value="yes" title="Please check tnd"> Target não detectado<br>
												<input type="checkbox" id="ldl" name="ldl" value="yes" title="Please check ldl"> Abaixo do nível de detecção<br>
												<input type="checkbox" id="hdl" name="hdl" value="yes" title="Please check hdl"> Acima do limite superior de detecção
											</td>
											<td class="vlResult">
												<label for="vlLog">Resultado da Carga Viral (Log) </label>
											</td>
											<td class="vlResult">
												<input type="text" class="form-control" id="vlLog" name="vlLog" placeholder="Resultado da Carga Viral (Log)" title="Please enter Resultado da Carga Viral (Log)" style="width:100%;" onchange="calculateLogValue(this);" />
											</td>
										</tr>
										<tr>
											<td>
												<label for="labTechnician">Técnico Executor </label>
											</td>
											<td>
												<input type="text" class="form-control" id="labTechnician" name="labTechnician" placeholder="Técnico Executor" title="Please enter Técnico Executor" style="width:100%;" />
											</td>
											<td>
												<label for="approvedBy">Aprovado por </label>
											</td>
											<td colspan="5">
												<select name="approvedBy" id="approvedBy" class="form-control" title="Please choose Aprovado por" style="width:38%;">
													<option value="">-- Select --</option>
													<?php foreach ($userResult as $uName) { ?>
														<option value="<?php echo $uName['user_id']; ?>" <?php echo ($uName['user_id'] == $_SESSION['userId']) ? "selected=selected" : ""; ?>><?php echo ($uName['user_name']); ?></option>
													<?php } ?>
												</select>
											</td>
										</tr>
										<tr>
											<td>
												<label for="labComments">Comentários do cientista de laboratório </label>
											</td>
											<td colspan="5">
												<textarea class="form-control" name="labComments" id="labComments" placeholder="Comentários do laboratório" style="width:100%"></textarea>
											</td>
										</tr>
									</table>
								</div>
							</div>
						</div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
						<?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
							<input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
							<input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
						<?php } ?>
						<input type="hidden" name="vlSampleId" id="vlSampleId" value="" />
						<a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
						<input type="hidden" name="formId" id="formId" value="8" />
						<input type="hidden" name="provinceId" id="provinceId" />
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
	provinceName = true;
	facilityName = true;
	$(document).ready(function() {
		$("#clinicName").select2({
			placeholder: "Nome da Unidade"
		});
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
						}
					});
			}
			sampleCodeGeneration();
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

	function sampleCodeGeneration() {
		var pName = $("#province").val();
		var sDate = $("#sampleCollectionDate").val();
		if (pName != '' && sDate != '') {
			$.post("/vl/requests/sampleCodeGeneration.php", {
					sDate: sDate
				},
				function(data) {
					var sCodeKey = JSON.parse(data);
					<?php if ($arr['sample_code'] == 'auto') { ?>
						pNameVal = pName.split("##");
						sCode = sCodeKey.auto;
						$("#sampleCode").val('<?php echo $rKey; ?>' + pNameVal[1] + sCode + sCodeKey.maxId);
						$("#sampleCodeFormat").val('<?php echo $rKey; ?>' + pNameVal[1] + sCode);
						$("#sampleCodeKey").val(sCodeKey.maxId);
						checkSampleNameValidation('form_vl', '<?php echo $sampleCode; ?>', 'sampleCode', null, 'The sample number that you entered already exists. Please try another number', null);
					<?php } else if ($arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
						$("#sampleCode").val('<?php echo $rKey . $prefix; ?>' + sCodeKey.mnthYr + sCodeKey.maxId);
						$("#sampleCodeFormat").val('<?php echo $rKey . $prefix; ?>' + sCodeKey.mnthYr);
						$("#sampleCodeKey").val(sCodeKey.maxId);
						checkSampleNameValidation('form_vl', '<?php echo $sampleCode; ?>', 'sampleCode', null, 'The sample number that you entered already exists. Please try another number', null)
					<?php } ?>
				});
		}
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
		$("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
		flag = deforayValidator.init({
			formId: 'addVlRequestForm'
		});
		if (flag) {
			$('.btn-disabled').attr('disabled', 'yes');
			$(".btn-disabled").prop("onclick", null).off("click");
			if ($("#clinicName").val() == null || $("#clinicName").val() == '') {
				alert('Please choose Nome da Unidade');
				return false;
			}
			$.blockUI();
			<?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
				insertSampleCode('addVlRequestForm', 'vlSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 8, 'sampleCollectionDate');
			<?php } else { ?>
				document.getElementById('addVlRequestForm').submit();
			<?php } ?>
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
		} else {
			$('.vlResult').show();
			$('.rejectionReason').hide();
			$('#rejectionReason').removeClass('isRequired');
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
</script>