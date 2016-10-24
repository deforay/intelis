  <?php
    ob_start();
    include('General.php');
    $general=new Deforay_Commons_General();
    //Get VL info
    $vlQuery="SELECT * from vl_request_form where vl_sample_id=$id";
    $vlQueryInfo=$db->query($vlQuery);
    //get province list
    $pdQuery="SELECT * from province_details";
    $pdResult=$db->query($pdQuery);
    //get lab facility list
    $fQuery="SELECT * FROM facility_details where status='active'";
    $fResult = $db->rawQuery($fQuery);
    $province = "";
    $province.="<option value=''> -- S�lectionner -- </option>";
    foreach($pdResult as $provinceName){
      $province .= "<option value='".$provinceName['province_name']."##".$provinceName['province_code']."'>".ucwords($provinceName['province_name'])."</option>";
    }
    $facility = "";
    $facility.="<option value=''> -- S�lectionner -- </option>";
    foreach($fResult as $fDetails){
      $facility .= "<option value='".$fDetails['facility_id']."'>".ucwords($fDetails['facility_name'])."</option>";
    }
    //Get selected state
    $stateQuery="SELECT * from facility_details where facility_id='".$vlQueryInfo[0]['facility_id']."'";
    $stateResult=$db->query($stateQuery);
    if(!isset($stateResult[0]['state']) || $stateResult[0]['state']==''){
      $stateResult[0]['state'] = 0;
    }
    $provinceQuery="SELECT * from province_details where province_name='".$stateResult[0]['state']."'";
    $provinceResult=$db->query($provinceQuery);
    if(!isset($provinceResult[0]['province_code']) || $provinceResult[0]['province_code']==''){
      $provinceResult[0]['province_code'] = 0;
    }
    //get ART list
    $aQuery="SELECT * from r_art_code_details where nation_identifier='drc'";
    $aResult=$db->query($aQuery);
    $sQuery="SELECT * from r_sample_type where form_identification='2'";
    $sResult=$db->query($sQuery);
    //Set DOB
    if(isset($vlQueryInfo[0]['patient_dob']) && trim($vlQueryInfo[0]['patient_dob'])!='' && $vlQueryInfo[0]['patient_dob']!='0000-00-00'){
      $vlQueryInfo[0]['patient_dob']=$general->humanDateFormat($vlQueryInfo[0]['patient_dob']);
    }else{
      $vlQueryInfo[0]['patient_dob']='';
    }
     //Set Date of demand
    if(isset($vlQueryInfo[0]['date_of_demand']) && trim($vlQueryInfo[0]['date_of_demand'])!='' && $vlQueryInfo[0]['date_of_demand']!='0000-00-00'){
      $vlQueryInfo[0]['date_of_demand']=$general->humanDateFormat($vlQueryInfo[0]['date_of_demand']);
    }else{
      $vlQueryInfo[0]['date_of_demand']='';
    }
    //Set ARV initiation date
    if(isset($vlQueryInfo[0]['date_of_initiation_of_current_regimen']) && trim($vlQueryInfo[0]['date_of_initiation_of_current_regimen'])!='' && $vlQueryInfo[0]['date_of_initiation_of_current_regimen']!='0000-00-00'){
      $vlQueryInfo[0]['date_of_initiation_of_current_regimen']=$general->humanDateFormat($vlQueryInfo[0]['date_of_initiation_of_current_regimen']);
    }else{
      $vlQueryInfo[0]['date_of_initiation_of_current_regimen']='';
    }
    //Has patient changed regimen section
    if(trim($vlQueryInfo[0]['has_patient_changed_regimen']) == "yes"){
      if(isset($vlQueryInfo[0]['date_of_regimen_changed']) && trim($vlQueryInfo[0]['date_of_regimen_changed'])!='' && $vlQueryInfo[0]['date_of_regimen_changed']!='0000-00-00'){
        $vlQueryInfo[0]['date_of_regimen_changed']=$general->humanDateFormat($vlQueryInfo[0]['date_of_regimen_changed']);
      }else{
        $vlQueryInfo[0]['date_of_regimen_changed']='';
      }
    }else{
      $vlQueryInfo[0]['reason_for_regimen_change'] = '';
      $vlQueryInfo[0]['date_of_regimen_changed'] = '';
    }
    //Set last VL result
    if(isset($vlQueryInfo[0]['last_viral_load_date']) && trim($vlQueryInfo[0]['last_viral_load_date'])!='' && $vlQueryInfo[0]['last_viral_load_date']!='0000-00-00'){
      $vlQueryInfo[0]['last_viral_load_date']=$general->humanDateFormat($vlQueryInfo[0]['last_viral_load_date']);
    }else{
      $vlQueryInfo[0]['last_viral_load_date']='';
    }
    //Set Sample Collection Date
     if(isset($vlQueryInfo[0]['sample_collection_date']) && trim($vlQueryInfo[0]['sample_collection_date'])!='' && $vlQueryInfo[0]['sample_collection_date']!='0000-00-00'){
      $expStr=explode(" ",$vlQueryInfo[0]['sample_collection_date']);
      $vlQueryInfo[0]['sample_collection_date']=$general->humanDateFormat($expStr[0])." ".$expStr[1];
    }else{
      $vlQueryInfo[0]['sample_collection_date']='';
    }
    //Set Dispatched From Clinic To Lab Date
     if(isset($vlQueryInfo[0]['date_dispatched_from_clinic_to_lab']) && trim($vlQueryInfo[0]['date_dispatched_from_clinic_to_lab'])!='' && $vlQueryInfo[0]['date_dispatched_from_clinic_to_lab']!='0000-00-00'){
      $expStr=explode(" ",$vlQueryInfo[0]['date_dispatched_from_clinic_to_lab']);
      $vlQueryInfo[0]['date_dispatched_from_clinic_to_lab']=$general->humanDateFormat($expStr[0])." ".$expStr[1];
    }else{
      $vlQueryInfo[0]['date_dispatched_from_clinic_to_lab']='';
    }
    //Set plasma storage temp.
    if(isset($vlQueryInfo[0]['sample_id']) && $vlQueryInfo[0]['sample_id']!= 2){
      $vlQueryInfo[0]['plasma_storage_temperature'] = '';
    }
    //Set sample received date
    if(isset($vlQueryInfo[0]['date_sample_received_at_testing_lab']) && trim($vlQueryInfo[0]['date_sample_received_at_testing_lab'])!='' && $vlQueryInfo[0]['date_sample_received_at_testing_lab']!='0000-00-00 00:00:00'){
      $expStr=explode(" ",$vlQueryInfo[0]['date_sample_received_at_testing_lab']);
      $vlQueryInfo[0]['date_sample_received_at_testing_lab']=$general->humanDateFormat($expStr[0])." ".$expStr[1];
    }else{
      $vlQueryInfo[0]['date_sample_received_at_testing_lab']='';
    }
    //Set sample test date
    if(isset($vlQueryInfo[0]['lab_tested_date']) && trim($vlQueryInfo[0]['lab_tested_date'])!='' && $vlQueryInfo[0]['lab_tested_date']!='0000-00-00 00:00:00'){
      $expStr=explode(" ",$vlQueryInfo[0]['lab_tested_date']);
      $vlQueryInfo[0]['lab_tested_date']=$general->humanDateFormat($expStr[0])." ".$expStr[1];
    }else{
      $vlQueryInfo[0]['lab_tested_date']='';
    }
    //get reason for rejection list
    $rjctReasonQuery="SELECT * from r_sample_rejection_reasons where rejection_reason_status = 'active'";
    $rjctReasonResult=$db->query($rjctReasonQuery);
    //get vl test reason list
    $vlTestReasonQuery="SELECT * from r_vl_test_reasons where test_reason_status = 'active'";
    $vlTestReasonResult=$db->query($vlTestReasonQuery);
    ?>
    <style>
      .ui_tpicker_second_label {
       display: none !important;
      }
      .ui_tpicker_second_slider {
       display: none !important;
      }.ui_tpicker_millisec_label {
       display: none !important;
      }.ui_tpicker_millisec_slider {
       display: none !important;
      }.ui_tpicker_microsec_label {
       display: none !important;
      }.ui_tpicker_microsec_slider {
       display: none !important;
      }.ui_tpicker_timezone_label {
       display: none !important;
      }.ui_tpicker_timezone {
       display: none !important;
      }.ui_tpicker_time_input{
       width:100%;
      }
      .translate-content{
        color:#0000FF;
        font-size:12.5px;
      }
   </style>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1><i class="fa fa-edit"></i> VIRAL LOAD LABORATORY REQUEST FORM</h1>
      <ol class="breadcrumb">
        <li><a href="index.php"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Edit Vl Request</li>
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
            <form class="form-inline" method="post" name="editVlRequestForm" id="editVlRequestForm" autocomplete="off" action="editVlRequestHelperDrc.php">
              <div class="box-body">
                <div class="box box-default">
                    <div class="box-body">
                        <div class="box-header with-border">
                            <h3 class="box-title">1. R�serv� � la structure de soins</h3>
                        </div>
                        <div class="box-header with-border">
                            <h3 class="box-title">Information sur la structure de soins</h3>
                        </div>
                        <table class="table" style="width:100%">
                            <tr>
                                <td><label for="province">Province </label></td>
                                <td>
                                    <select class="form-control" name="province" id="province" title="Please choose province" onchange="getfacilityDetails(this);" style="width:100%;">
                                      <option value=""> -- S�lectionner -- </option>
                                      <?php
                                      foreach($pdResult as $provinceName){ ?>
                                        <option value="<?php echo $provinceName['province_name']."##".$provinceName['province_code']; ?>" <?php echo ($stateResult[0]['state']."##".$provinceResult[0]['province_code']==$provinceName['province_name']."##".$provinceName['province_code'])?"selected='selected'":""?>><?php echo ucwords($provinceName['province_name']); ?></option>
                                      <?php } ?>
                                    </select>
                                </td>
                                <td><label for="clinicName">Zone de sant� </label></td>
                                <td>
                                    <select class="form-control" name="clinicName" id="clinicName" title="Please choose Zone de sant�" onchange="getfacilityProvinceDetails(this);" style="width:100%;">
                                        <option value=""> -- S�lectionner -- </option>
                                        <?php
                                        foreach($fResult as $fDetails){ ?>
                                          <option value="<?php echo $fDetails['facility_id']; ?>" <?php echo ($vlQueryInfo[0]['facility_id']==$fDetails['facility_id'])?"selected='selected'":""?>><?php echo ucwords($fDetails['facility_name']); ?></option>
                                        <?php } ?>
                                    </select>
                                </td>
                                <td><label for="service">Structure/Service </label></td>
                                <td>
                                    <input type="text" class="form-control" id="service" name="service" placeholder="Structure/Service" title="Please enter structure/service" value="<?php echo $vlQueryInfo[0]['service']; ?>" style="width:100%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="clinicianName">Demandeur </label></td>
                                <td>
                                    <input type="text" class="form-control" id="clinicianName" name="clinicianName" placeholder="Demandeur" title="Please enter demandeur" value="<?php echo $vlQueryInfo[0]['request_clinician']; ?>" style="width:100%;"/>
                                </td>
                                <td><label for="clinicanTelephone">T�l�phone </label></td>
                                <td>
                                    <input type="text" class="form-control checkNum" id="clinicanTelephone" name="clinicanTelephone" placeholder="T�l�phone" title="Please enter t�l�phone" value="<?php echo $vlQueryInfo[0]['clinician_ph_no']; ?>" style="width:100%;"/>
                                </td>
                                <td><label for="supportPartner">Partenaire d�appui </label></td>
                                <td>
                                    <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire d�appui" title="Please enter partenaire d�appui" value="<?php echo $vlQueryInfo[0]['support_partner']; ?>" style="width:100%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="">Date de la demande </label></td>
                                <td colspan="5">
                                    <input type="text" class="form-control date" id="dateOfDemand" name="dateOfDemand" placeholder="e.g 09-Jan-1992" title="Please enter date de la demande" value="<?php echo $vlQueryInfo[0]['date_of_demand']; ?>" style="width:21%;"/>
                                </td>
                            </tr>
                        </table>
                        <div class="box-header with-border">
                            <h3 class="box-title">Information sur le patient </h3>
                        </div>
                        <table class="table" style="width:100%">
                            <tr>
                                <td style="width:14%;"><label for="">Date de naissance </label></td>
                                <td style="width:14%;">
                                    <input type="text" class="form-control date" id="dob" name="dob" placeholder="e.g 09-Jan-1992" title="Please select date de naissance" onchange="setDobMonthYear();" value="<?php echo $vlQueryInfo[0]['patient_dob']; ?>" style="width:100%;"/>
                                </td>
                                <td style="width:14%;text-align:center;"><label for="ageInYears">�ge en ann�es </label></td>
                                <td style="width:14%;">
                                    <input type="text" class="form-control checkNum" id="ageInYears" name="ageInYears" placeholder="Aann�es" title="Please enter �ge en ann�es" value="<?php echo $vlQueryInfo[0]['age_in_yrs']; ?>" style="width:100%;"/>
                                </td>
                                <td style="width:14%;"><label for="ageInMonths">�ge en mois </label></td>
                                <td style="width:14%;">
                                    <input type="text" class="form-control checkNum" id="ageInMonths" name="ageInMonths" placeholder="Mois" title="Please enter �ge en mois" value="<?php echo $vlQueryInfo[0]['age_in_mnts']; ?>" style="width:100%;"/>
                                </td>
                                <td><label for="sex">Sexe </label></td>
                                <td style="width:16%;">
                                    <label class="radio-inline">M</label>
                                    <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                                        <input type="radio" class="" id="genderMale" name="gender" value="male" title="Please check sexe" <?php echo (trim($vlQueryInfo[0]['gender']) == "male")?'checked="checked"':''; ?>>
                                    </label>
                                    <label class="radio-inline">F</label>
                                    <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                                        <input type="radio" class="" id="genderFemale" name="gender" value="female" title="Please check sexe" <?php echo (trim($vlQueryInfo[0]['gender']) == "female")?'checked="checked"':''; ?>>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="patientArtNo">Code du patient </label></td>
                                <td>
                                    <input type="text" class="form-control" id="patientArtNo" name="patientArtNo" placeholder="Code du patient" title="Please enter code du patient" value="<?php echo $vlQueryInfo[0]['art_no']; ?>" style="width:100%;"/>
                                </td>
                                <td><label for="">Date du d�but des ARV </label></td>
                                <td colspan="5">
                                    <input type="text" class="form-control date" id="dateOfArtInitiation" name="dateOfArtInitiation" placeholder="e.g 09-Jan-1992" title="Please enter date du d�but des ARV" value="<?php echo $vlQueryInfo[0]['date_of_initiation_of_current_regimen']; ?>" style="width:40%;"/> (Jour/Mois/Ann�e) </span>
                                </td>
                            </tr>
                            <tr>
                                <td><label>R�gime ARV en cours </label></td>
                                <td colspan="7">
                                  <select class="form-control" name="artRegimen" id="artRegimen" title="Please choose r�gime ARV en cours" onchange="checkCurrentRegimen();" style="width:30%;">
                                    <option value=""> -- S�lectionner -- </option>
                                      <?php
                                      foreach($aResult as $arv){
                                      ?>
                                       <option value="<?php echo $arv['art_code']; ?>" <?php echo($arv['art_code'] == $vlQueryInfo[0]['current_regimen'])?'selected="selected"':''; ?>><?php echo $arv['art_code']; ?></option>
                                      <?php
                                      }
                                      ?>
                                      <option value="other">Autre</option>
                                  </select>
                                </td>
                            </tr>
                            <tr class="newArtRegimen" style="display:none;">
                                <td><label for="newArtRegimen">Autre, � pr�ciser </label></td>
                                <td colspan="7">
                                    <input type="text" class="form-control" name="newArtRegimen" id="newArtRegimen" placeholder="R�gime ARV" title="Please enter r�gime ARV" style="width:30%;" >
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2"><label for="hasChangedRegimen">Ce patient a-t-il d�j� chang� de r�gime de traitement? </label></td>
                                <td colspan="2">
                                    <label class="radio-inline">Oui </label>
                                    <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                                        <input type="radio" class="" id="changedRegimenYes" name="hasChangedRegimen" value="yes" title="Please check any of one option" <?php echo(trim($vlQueryInfo[0]['has_patient_changed_regimen']) == "yes")?'checked="checked"':''; ?>>
                                    </label>
                                    <label class="radio-inline">Non </label>
                                    <label class="radio-inline" style="width:4%;padding-bottom:22px;margin-left:0;">
                                        <input type="radio" class="" id="changedRegimenNo" name="hasChangedRegimen" value="no" title="Please check any of one option" <?php echo(trim($vlQueryInfo[0]['has_patient_changed_regimen']) == "no")?'checked="checked"':''; ?>>
                                    </label>
                                </td>
                                <td><label for="reasonForArvRegimenChange" class="arvChangedElement" style="display:<?php echo(trim($vlQueryInfo[0]['has_patient_changed_regimen']) == "yes")?'':'none'; ?>;">Motif de changement de r�gime ARV </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control arvChangedElement" id="reasonForArvRegimenChange" name="reasonForArvRegimenChange" placeholder="Motif de changement de r�gime ARV" title="Please enter motif de changement de r�gime ARV" value="<?php echo $vlQueryInfo[0]['reason_for_regimen_change']; ?>" style="width:100%;display:<?php echo(trim($vlQueryInfo[0]['has_patient_changed_regimen']) == "yes")?'':'none'; ?>;"/>
                                </td>
                            </tr>
                            <tr class="arvChangedElement" style="display:<?php echo(trim($vlQueryInfo[0]['has_patient_changed_regimen']) == "yes")?'':'none'; ?>;">
                                <td><label for="">Date du changement de r�gime ARV </label></td>
                                <td colspan="7">
                                    <input type="text" class="form-control date" id="dateOfArvRegimenChange" name="dateOfArvRegimenChange" placeholder="e.g 09-Jan-1992" title="Please enter date du changement de r�gime ARV" value="<?php echo $vlQueryInfo[0]['date_of_regimen_changed']; ?>" style="width:30%;"/> (Jour/Mois/Ann�e)
                                </td>
                            </tr>
                            <tr>
                                <td><label for="reasonForRequest">Motif de la demande </label></td>
                                <td colspan="2">
                                   <select name="vlTestReason" id="vlTestReason" class="form-control" title="Please choose motif de la demande" onchange="checkVLTestReason();">
                                      <option value=""> -- S�lectionner -- </option>
                                      <?php
                                      foreach($vlTestReasonResult as $tReason){
                                      ?>
                                       <option value="<?php echo $tReason['test_reason_id']; ?>" <?php echo($vlQueryInfo[0]['vl_test_reason'] == $tReason['test_reason_id'])?'selected="selected"':''; ?>><?php echo ucwords($tReason['test_reason_name']); ?></option>
                                      <?php } ?>
                                      <option value="other">Autre</option>
                                    </select>
                                </td>
                                <td><label for="viralLoadNo">Charge virale N </label></td>
                                <td colspan="4">
                                    <input type="text" class="form-control" id="viralLoadNo" name="viralLoadNo" placeholder="Charge virale N" title="Please enter charge virale N" value="<?php echo $vlQueryInfo[0]['viral_load_no']; ?>" style="width:60%;"/>
                                </td>
                            </tr>
                            <tr class="newVlTestReason" style="display:none;">
                                <td><label for="newVlTestReason">Autre, � pr�ciser </label></td>
                                <td colspan="7">
                                    <input type="text" class="form-control" name="newVlTestReason" id="newVlTestReason" placeholder="Virale Demande Raison" title="Please enter virale demande raison" style="width:30%;" >
                                </td>
                            </tr>
                            <tr>
                                <td><label for="lastViralLoadResult">R�sultat derni�re charge virale </label></td>
                                <td colspan="7">
                                    <input type="text" class="form-control" id="lastViralLoadResult" name="lastViralLoadResult" placeholder="R�sultat derni�re charge virale" title="Please enter r�sultat derni�re charge virale" value="<?php echo $vlQueryInfo[0]['last_viral_load_result']; ?>" style="width:30%;"/> copies/ml
                                </td>
                            </tr>
                            <tr>
                                <td><label for="">Date derni�re charge virale (demande) </label></td>
                                <td colspan="7">
                                    <input type="text" class="form-control date" id="lastViralLoadTestDate" name="lastViralLoadTestDate" placeholder="e.g 09-Jan-1992" title="Please enter date derni�re charge virale" value="<?php echo $vlQueryInfo[0]['last_viral_load_date']; ?>" style="width:30%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="8"><label class="radio-inline" style="margin:0;padding:0;">A remplir par le service demandeur dans la structure de soins</label></td>
                            </tr>
                        </table>
                        <div class="box-header with-border">
                            <h3 class="box-title">Informations sur le pr�l�vement </h3>
                        </div>
                        <table class="table" style="width:100%">
                            <tr>
                                <td style="width:20%;"><label for="">Date du pr�l�vement </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control dateTime" id="sampleCollectionDate" name="sampleCollectionDate" placeholder="e.g 09-Jan-1992 05:30" title="Please enter date du pr�l�vement" value="<?php echo $vlQueryInfo[0]['sample_collection_date']; ?>" style="width:30%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="specimenType">Type d��chantillon </label></td>
                                <td colspan="3">
                                  <select name="specimenType" id="specimenType" class="form-control" title="Please choose type d��chantillon" onchange="checkSpecimenType();" style="width:30%;">
                                    <option value=""> -- S�lectionner -- </option>
                                    <?php
                                    foreach($sResult as $type){
                                     ?>
                                     <option value="<?php echo $type['sample_id'];?>" <?php echo($vlQueryInfo[0]['sample_id'] == $type['sample_id'])?'selected="selected"':''; ?>><?php echo ucwords($type['sample_name']);?></option>
                                     <?php
                                    }
                                    ?>
                                  </select>
                                </td>
                            </tr>
                            <tr class="plasmaElement" style="display:<?php echo($vlQueryInfo[0]['sample_id'] == 2)?'':'none'; ?>;">
                                <td><label for="conservationTemperature">Si plasma,&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Temp�rature de conservation </label></td>
                                <td>
                                    <input type="text" class="form-control checkNum" id="conservationTemperature" name="conservationTemperature" placeholder="Temp�rature de conservation" title="Please enter temp�rature de conservation" value="<?php echo $vlQueryInfo[0]['plasma_conservation_temperature']; ?>" style="width:80%;"/>�C
                                </td>
                                <td><label for="durationOfConservation">Dur�e de conservation </label></td>
                                <td>
                                    <input type="text" class="form-control" id="durationOfConservation" name="durationOfConservation" placeholder="e.g 9/1" title="Please enter dur�e de conservation" value="<?php echo $vlQueryInfo[0]['duration_of_conservation']; ?>" style="width:60%;"/>Jour/Heures
                                </td>
                            </tr>
                            <tr>
                                <td><label for="">Date de d�part au Labo biomol </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control dateTime" id="dateDispatchedFromClinicToLab" name="dateDispatchedFromClinicToLab" placeholder="e.g 09-Jan-1992 05:30" title="Please enter date de d�part au Labo biomol" value="<?php echo $vlQueryInfo[0]['date_dispatched_from_clinic_to_lab']; ?>" style="width:30%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4"><label class="radio-inline" style="margin:0;padding:0;">A remplir par le pr�leveur </label></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="box box-primary">
                    <div class="box-body">
                        <div class="box-header with-border">
                            <h3 class="box-title">2. R�serv� au Laboratoire de biologie mol�culaire </h3>
                        </div>
                        <table class="table" style="width:100%">
                            <tr>
                                <td style="width:20%;"><label for="">Date de r�ception de l��chantillon </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="e.g 09-Jan-1992 05:30" title="Please enter date de r�ception de l��chantillon" value="<?php echo $vlQueryInfo[0]['date_sample_received_at_testing_lab']; ?>" style="width:30%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="">D�cision prise </label></td>
                                <td colspan="3">
                                    <select class="form-control" id="status" name="status" title="Please select d�cision prise" onchange="checkTestStatus();" style="width:30%;">
                                      <option value=""> -- S�lectionner -- </option>
                                      <option value="7" <?php echo($vlQueryInfo[0]['status'] == 7)?'selected="selected"':''; ?>>Echantillon accept�</option>
                                      <option value="4" <?php echo($vlQueryInfo[0]['status'] == 4)?'selected="selected"':''; ?>>Echantillon rejet�</option>
                                    </select>
                                </td>
                            </tr>
                            <tr class="rejectionReason" style="display:<?php echo($vlQueryInfo[0]['status'] == 4)?'':'none'; ?>;">
                                <td><label for="rejectionReason">Motifs de rejet </label></td>
                                <td>
                                    <select class="form-control" id="rejectionReason" name="rejectionReason" title="Please select motifs de rejet" onchange="checkRejectionReason();" style="width:80%;">
                                      <option value=""> -- S�lectionner -- </option>
                                      <?php
                                      foreach($rjctReasonResult as $rjctReason){
                                      ?>
                                       <option value="<?php echo $rjctReason['rejection_reason_id']; ?>" <?php echo($vlQueryInfo[0]['sample_rejection_reason'] == $rjctReason['rejection_reason_id'])?'selected="selected"':''; ?>><?php echo ucwords($rjctReason['rejection_reason_name']); ?></option>
                                      <?php } ?>
                                       <option value="other">Autre</option>
                                    </select>
                                </td>
                                <td style="text-align:center;"><label for="newRejectionReason" class="newRejectionReason" style="display:none;">Autre, � pr�ciser </label></td>
                                <td><input type="text" class="form-control newRejectionReason" id="newRejectionReason" name="newRejectionReason" placeholder="Motifs de rejet" title="Please enter motifs de rejet" style="width:90%;display:none;"/></td>
                            </tr>
                            <tr>
                                <td><label for="labNo">Code Labo </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control checkNum" id="labNo" name="labNo" placeholder="Code Labo" title="Please enter code labo" value="<?php echo $vlQueryInfo[0]['lab_no']; ?>" style="width:30%;"/>
                                </td>
                            </tr>
                            <tr><td colspan="4" style="height:30px;border:none;"></td></tr>
                            <tr>
                                <td><label for="">Date de r�alisation de la charge virale </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control date" id="dateOfCompletionOfViralLoad" name="dateOfCompletionOfViralLoad" placeholder="e.g 09-Jan-1992" title="Please enter date de r�alisation de la charge virale" style="width:30%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="testingPlatform">Technique utilis�e </label></td>
                                <td colspan="3">
                                    <select class="form-control" id="testingPlatform" name="testingPlatform" title="Please select technique utilis�e" style="width:30%;">
                                        <option value=""> -- S�lectionner -- </option>
                                        <option value="plasma_protocole_600" <?php echo($vlQueryInfo[0]['vl_test_platform'] == "plasma_protocole_600")?'selected="selected"':''; ?>>Plasma protocole 600�l</option>
                                        <option value="dbs_protocole_1000" <?php echo($vlQueryInfo[0]['vl_test_platform'] == "dbs_protocole_1000")?'selected="selected"':''; ?>>DBS protocole 1000 �l</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="vlResult">R�sultat </label></td>
                                <td>
                                    <input type="text" class="form-control" id="vlResult" name="vlResult" placeholder="R�sultat" title="Please enter r�sultat" value="<?php echo $vlQueryInfo[0]['result']; ?>" style="width:80%;"/>copies/ml
                                </td>
                                <td colspan="2" style="vertical-align:middle;">Limite de d�tection : < 40 Copies/ml ou  log  < 1.6 ( pour DBS )</td>
                            </tr>
                            <tr>
                                <td colspan="4"><label class="radio-inline" style="margin:0;padding:0;">A remplir par le service effectuant la charge virale </label></td>
                            </tr>
                            <tr><td colspan="4" style="height:30px;border:none;"></td></tr>
                            <tr>
                                <td><label for="">Date de remise du r�sultat </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="e.g 09-Jan-1992 05:30" title="Please enter date de remise du r�sultat" value="<?php echo $vlQueryInfo[0]['lab_tested_date']; ?>" style="width:30%;"/>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="box-header with-border">
                  <label class="radio-inline" style="margin:0;padding:0;">1. Biffer la mention inutile <br>2. S�lectionner un seul r�gime de traitement </label>
                </div>
              </div>
              <!-- /.box-body -->
              <div class="box-footer">
                <input type="hidden" id="vlSampleId" name="vlSampleId" value="<?php echo $vlQueryInfo[0]['vl_sample_id']; ?>"/>
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
     $(document).ready(function() {
        $('.date').datepicker({
        changeMonth: true,
        changeYear: true,
        dateFormat: 'dd-M-yy',
        yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
       }).click(function(){
           $('.ui-datepicker-calendar').show();
        });
        
        $('.dateTime').datetimepicker({
          changeMonth: true,
          changeYear: true,
          dateFormat: 'dd-M-yy',
          timeFormat: "HH:mm",
          yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
          }).click(function(){
   	    $('.ui-datepicker-calendar').show();
          });
        
        $('.date').mask('99-aaa-9999');
        $('.dateTime').mask('99-aaa-9999 99:99');
     });
     
     function getfacilityDetails(obj){
      var pName = $("#province").val();
      var cName = $("#clinicName").val();
      if($.trim(pName)!='' && changeProvince && changeFacility){
        changeFacility = false;
      }
      if($.trim(pName)!='' && changeProvince){
            $.post("getFacilityForClinic.php", { pName : pName},
            function(data){
                if(data!= ""){   
                  details = data.split("###");
                  $("#clinicName").html(details[0]);
                }
            });
      }else if($.trim(pName)=='' && $.trim(cName)==''){
        changeProvince = true;
        changeFacility = true; 
        $("#province").html("<?php echo $province;?>");
        $("#clinicName").html("<?php echo $facility;?>");
      }
    }
    
    function getfacilityProvinceDetails(obj){
        var pName = $("#province").val();
        var cName = $("#clinicName").val();
        if($.trim(cName)!='' && changeProvince && changeFacility){
          changeProvince = false;
        }
        if($.trim(cName)!='' && changeFacility){
          $.post("getFacilityForClinic.php", { cName : cName},
          function(data){
              if(data!= ""){
                details = data.split("###");
                $("#province").html(details[0]);
              }
          });
        }else if($.trim(pName)=='' && $.trim(cName)==''){
           changeFacility = true;
           changeProvince = true;
           $("#province").html("<?php echo $province;?>");
           $("#clinicName").html("<?php echo $facility;?>");
        }
    }
    
    function checkCurrentRegimen(){
      var currentRegimen = $("#artRegimen").val();
      if(currentRegimen == "other"){
        $(".newArtRegimen").show();
      }else{
        $(".newArtRegimen").hide();
      }
    }
    
    $("input:radio[name=hasChangedRegimen]").click(function() {
      if($(this).val() == 'yes'){
         $(".arvChangedElement").show();
      }else if($(this).val() == 'no'){
        $(".arvChangedElement").hide();
      }
    });
    
    function checkVLTestReason(){
      var vlTestReason = $("#vlTestReason").val();
      if(vlTestReason == "other"){
        $(".newVlTestReason").show();
      }else{
        $(".newVlTestReason").hide();
      }
    }
    
    function checkSpecimenType(){
      var specimenType = $("#specimenType").val();
      if(specimenType == 2){
        $(".plasmaElement").show();
      }else{
        $(".plasmaElement").hide();
      }
    }
    
    function checkTestStatus(){
      var status = $("#status").val();
      if(status == 4){
        $(".rejectionReason").show();
      }else{
        $(".rejectionReason").hide();
      }
    }
    
    function checkRejectionReason(){
      var rejectionReason = $("#rejectionReason").val();
      if(rejectionReason == "other"){
        $(".newRejectionReason").show();
      }else{
        $(".newRejectionReason").hide();
      }
    }
    
    function setDobMonthYear(){
      var today = new Date();
      var dob = $("#dob").val();
      if($.trim(dob) == ""){
        $("#ageInMonths").val("");
        $("#ageInYears").val("");
        return false;
      }
      var dd = today.getDate();
      var mm = today.getMonth();
      var yyyy = today.getFullYear();
      if(dd<10) {
        dd='0'+dd
      } 
      
      if(mm<10) {
        mm='0'+mm
      }
      
      splitDob = dob.split("-");
      var dobDate = new Date(splitDob[1] + splitDob[2]+", "+splitDob[0]);
      var monthDigit = dobDate.getMonth();
      var dobYear = splitDob[2];
      var dobMonth = isNaN(monthDigit) ? 0 : (monthDigit);
      dobMonth = (dobMonth<10) ? '0'+dobMonth: dobMonth;
      var dobDate = (splitDob[0]<10) ? '0'+splitDob[0]: splitDob[0];
      
      var date1 = new Date(yyyy,mm,dd);
      var date2 = new Date(dobYear,dobMonth,dobDate);
      var diff = new Date(date1.getTime() - date2.getTime());
      if((diff.getUTCFullYear() - 1970) == 0){
        $("#ageInMonths").val((diff.getUTCMonth() > 0)? diff.getUTCMonth(): ''); // Gives month count of difference
      }else{
        $("#ageInMonths").val("");
      }
      $("#ageInYears").val((diff.getUTCFullYear() - 1970 > 0)? (diff.getUTCFullYear() - 1970) : ''); // Gives difference as year
    }
    
    function validateNow(){
      flag = deforayValidator.init({
        formId: 'editVlRequestForm'
      });
      if(flag){
        $.blockUI();
        document.getElementById('editVlRequestForm').submit();
      }
    }
  </script>
  
 <?php
 //include('footer.php');
 ?>
