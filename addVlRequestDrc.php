  <?php
    ob_start();
    include('General.php');
    //get province list
    $pdQuery="SELECT * from province_details";
    $pdResult=$db->query($pdQuery);
    //get lab facility list
    $fQuery="SELECT * FROM facility_details where status='active'";
    $fResult = $db->rawQuery($fQuery);
    $province = "";
    $province.="<option value=''> -- Select -- </option>";
    foreach($pdResult as $provinceName){
      $province .= "<option value='".$provinceName['province_name']."##".$provinceName['province_code']."'>".ucwords($provinceName['province_name'])."</option>";
    }
    $facility = "";
    $facility.="<option value=''> -- Select -- </option>";
    foreach($fResult as $fDetails){
      $facility .= "<option value='".$fDetails['facility_id']."'>".ucwords($fDetails['facility_name'])."</option>";
    }
    //get ART list
    $aQuery="SELECT * from r_art_code_details where nation_identifier='zmb'";
    $aResult=$db->query($aQuery);
    $sQuery="SELECT * from r_sample_type where form_identification='2'";
    $sResult=$db->query($sQuery);
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
   </style>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>VIRAL LOAD LABORATORY REQUEST FORM</h1>
      <ol class="breadcrumb">
        <li><a href="index.php"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Add Vl Request</li>
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
            <form class="form-inline" method="post" name="vlRequestForm" id="vlRequestForm" autocomplete="off" action="">
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
                                        <?php echo $province; ?>
                                    </select>
                                </td>
                                <td><label for="clinicName">Zone de sant� </label></td>
                                <td>
                                    <select class="form-control" name="clinicName" id="clinicName" title="Please choose Zone de sant�" onchange="getfacilityProvinceDetails(this);" style="width:100%;">
                                        <?php echo $facility; ?>
                                    </select>
                                </td>
                                <td><label for="service">Structure/Service </label></td>
                                <td>
                                    <input type="text" class="form-control" id="service" name="service" placeholder="Structure/Service" title="Please enter structure/service" style="width:100%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="clinicianName">Demandeur </label></td>
                                <td>
                                    <input type="text" class="form-control" id="clinicianName" name="clinicianName" placeholder="Demandeur" title="Please enter demandeur" style="width:100%;"/>
                                </td>
                                <td><label for="clinicanTelephone">T�l�phone </label></td>
                                <td>
                                    <input type="text" class="form-control" id="clinicanTelephone" name="clinicanTelephone" placeholder="T�l�phone" title="Please enter t�l�phone" style="width:100%;"/>
                                </td>
                                <td><label for="supportPartner">Partenaire d�appui </label></td>
                                <td>
                                    <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire d�appui" title="Please enter partenaire d�appui" style="width:100%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="">Date de la demande </label></td>
                                <td colspan="5">
                                    <input type="text" class="form-control date" id="dateOfDemand" name="dateOfDemand" placeholder="dd/mm/yyyy" title="Please enter date de la demande" style="width:21%;"/>
                                </td>
                            </tr>
                        </table>
                        <div class="box-header with-border">
                            <h3 class="box-title">Information sur le patient</h3>
                        </div>
                        <table class="table" style="width:100%">
                            <tr>
                                <td><label for="">Date de naissance </label></td>
                                <td style="width:14%;">
                                    <input type="text" class="form-control date" id="dob" name="dob" placeholder="dd/mm/yyyy" title="Please select date de naissance" onchange="setDobMonthYear();" style="width:100%;"/>
                                </td>
                                <td><label for="ageInYears">�ge en ann�es </label></td>
                                <td>
                                    <input type="text" class="form-control" id="ageInYears" name="ageInYears" placeholder="Aann�es" title="Please enter �ge en ann�es" style="width:100%;"/>
                                </td>
                                <td><label for="ageInMonths">�ge en mois </label></td>
                                <td>
                                    <input type="text" class="form-control" id="ageInMonths" name="ageInMonths" placeholder="Mois" title="Please enter �ge en mois" style="width:100%;"/>
                                </td>
                                <td><label for="sex">Sexe </label></td>
                                <td style="width:16%;">
                                    <label class="radio-inline">M</label>
                                    <label class="radio-inline" style="width:4%;padding-bottom:22px;">
                                        <input type="radio" class="" id="genderMale" name="gender" value="male" title="Please check sexe">
                                    </label>
                                    <label class="radio-inline">F</label>
                                    <label class="radio-inline" style="width:4%;padding-bottom:22px;">
                                        <input type="radio" class="" id="genderFemale" name="gender" value="female" title="Please check sexe">
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="patientArtNo">Code du patient </label></td>
                                <td>
                                    <input type="text" class="form-control" id="patientArtNo" name="patientArtNo" placeholder="Code du patient" title="Please enter code du patient" style="width:100%;"/>
                                </td>
                                <td colspan="2"><label for="">Date du d�but des ARV </label></td>
                                <td colspan="4">
                                    <input type="text" class="form-control" id="dateOfArtInitiation" name="dateOfArtInitiation" placeholder="dd/mm/yy" title="Please enter date du d�but des ARV" style="width:60%;"/> (Jour/Mois/Ann�e)
                                </td>
                            </tr>
                            <tr>
                                <td><label>R�gime ARV en cours</label></td>
                                <td colspan="7">
                                  <select class="form-control" name="artRegimen" id="artRegimen" title="Please choose r�gime ARV en cours" onchange="checkCurrentRegimen();" style="width:30%;">
                                    <option value=""> -- Select -- </option>
                                      <?php
                                      foreach($aResult as $arv){
                                      ?>
                                       <option value="<?php echo $arv['art_code']; ?>"><?php echo $arv['art_code']; ?></option>
                                      <?php
                                      }
                                      ?>
                                      <option value="other">Other</option>
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
                                <td><label for="hasChangedRegimen">Ce patient a-t-il d�j� chang� de r�gime de traitement?  </label></td>
                                <td colspan="3">
                                    <label class="radio-inline">Yes</label>
                                    <label class="radio-inline" style="width:4%;padding-bottom:22px;">
                                        <input type="radio" class="" id="changedRegimenYes" name="hasChangedRegimen" value="yes" title="Please check any of one option">
                                    </label>
                                    <label class="radio-inline">No</label>
                                    <label class="radio-inline" style="width:4%;padding-bottom:22px;">
                                        <input type="radio" class="" id="changedRegimenNo" name="hasChangedRegimen" value="no" title="Please check any of one option">
                                    </label>
                                </td>
                                <td><label for="reasonForArvRegimenChange" class="arvChangedElement" style="display:none;">Motif de changement de r�gime ARV </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control arvChangedElement" id="reasonForArvRegimenChange" name="reasonForArvRegimenChange" placeholder="Motif de changement de r�gime ARV" title="Please enter motif de changement de r�gime ARV" style="width:100%;display:none;"/>
                                </td>
                            </tr>
                            <tr class="arvChangedElement" style="display:none;">
                                <td><label for="">Date du changement de r�gime ARV </label></td>
                                <td colspan="7">
                                    <input type="text" class="form-control" id="dateOfArvRegimenChange" name="dateOfArvRegimenChange" placeholder="dd/mm/yy" title="Please enter date du changement de r�gime ARV" style="width:30%;"/> (Jour/Mois/Ann�e)
                                </td>
                            </tr>
                            <tr>
                                <td><label for="reasonForRequest">Motif de la demande </label></td>
                                <td colspan="3">
                                   <select name="vlTestReason" id="vlTestReason" class="form-control" title="Please choose motif de la demande" onchange="checkVLTestReason();">
                                      <option value=""> -- Select -- </option>
                                      <option value="routine_check">Contr�le de routine</option>
                                      <option value="confirmation_of_treatment_failure">Suspicion d��chec Th�rapeutique</option>
                                      <option value="other">Other</option>
                                    </select>
                                </td>
                                <td><label for="viralLoadN">Charge virale N </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control" id="viralLoadN" name="viralLoadN" placeholder="Charge virale N" title="Please enter charge virale N" style="width:100%;"/>
                                </td>
                            </tr>
                            <tr class="newVlTestReason" style="display:none;">
                                <td><label for="newVlTestReason">Other, Please specify </label></td>
                                <td colspan="7">
                                    <input type="text" class="form-control" name="newVlTestReason" id="newVlTestReason" placeholder="Virale Demande Raison" title="Please enter virale demande raison" style="width:30%;" >
                                </td>
                            </tr>
                            <tr>
                                <td><label for="lastViralLoadResult">R�sultat derni�re charge virale </label></td>
                                <td colspan="7">
                                    <input type="text" class="form-control" id="lastViralLoadResult" name="lastViralLoadResult" placeholder="R�sultat derni�re charge virale" title="Please enter r�sultat derni�re charge virale" style="width:30%;"/> copies/ml
                                </td>
                            </tr>
                            <tr>
                                <td><label for="">Date derni�re charge virale (demande) </label></td>
                                <td colspan="7">
                                    <input type="text" class="form-control date" id="lastViralLoadTestDate" name="lastViralLoadTestDate" placeholder="dd/mm/yyyy" title="Please enter date derni�re charge virale" style="width:30%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="8"><label class="radio-inline" style="margin:0;padding:0;">A remplir par le service demandeur dans la structure de soins</label></td>
                            </tr>
                        </table>
                        <div class="box-header with-border">
                            <h3 class="box-title">Informations sur le pr�l�vement</h3>
                        </div>
                        <table class="table" style="width:100%">
                            <tr>
                                <td style="width:20%;"><label for="">Date du pr�l�vement </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control dateTime" id="dateOfWithdrawal" name="dateOfWithdrawal" placeholder="dd/mm/yyyy hh:mm" title="Please enter date du pr�l�vement" style="width:30%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="specimenType">Type d��chantillon </label></td>
                                <td colspan="3">
                                  <select name="specimenType" id="specimenType" class="form-control" title="Please choose type d��chantillon" onchange="checkSpecimenType();" style="width:30%;">
                                    <option value=""> -- Select -- </option>
                                    <?php
                                    foreach($sResult as $type){
                                     ?>
                                     <option value="<?php echo $type['sample_id'];?>"><?php echo ucwords($type['sample_name']);?></option>
                                     <?php
                                    }
                                    ?>
                                  </select>
                                </td>
                            </tr>
                            <tr class="plasmaElement" style="display:none;">
                                <td><label for="storageTemperature">Si plasma,&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Temp�rature de conservation </label></td>
                                <td>
                                    <input type="text" class="form-control" id="storageTemperature" name="storageTemperature" placeholder="Temp�rature de conservation" title="Please enter temp�rature de conservation" style="width:60%;"/>�C
                                </td>
                                <td><label for="duationOfConservation">Dur�e de conservation </label></td>
                                <td>
                                    <input type="text" class="form-control" id="duationOfConservation" name="duationOfConservation" placeholder="jour/heures" title="Please enter dur�e de conservation" style="width:60%;"/>Jour/Heures
                                </td>
                            </tr>
                            <tr>
                                <td><label for="">Date de d�part au Labo biomol </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control dateTime" id="departureDateInLaboBiomol" name="departureDateInLaboBiomol" placeholder="dd/mm/yyyy hh:mm" title="Please enter date de d�part au Labo biomol" style="width:30%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4"><label class="radio-inline" style="margin:0;padding:0;">A remplir par le pr�leveur</label></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="box box-primary">
                    <div class="box-body">
                        <div class="box-header with-border">
                            <h3 class="box-title">2. R�serv� au Laboratoire de biologie mol�culaire</h3>
                        </div>
                        <table class="table" style="width:100%">
                            <tr>
                                <td style="width:20%;"><label for="">Date de r�ception de l��chantillon </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="dd/mm/yyyy hh:mm" title="Please enter date de r�ception de l��chantillon" style="width:30%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="">D�cision prise </label></td>
                                <td colspan="3">
                                    <select class="form-control" id="status" name="status" title="Please select d�cision prise" onchange="checkTestStatus();" style="width:30%;">
                                      <option value="">-- Select --</option>
                                      <option value="7">Echantillon accept�</option>
                                      <option value="4">Echantillon rejet�</option>
                                    </select>
                                </td>
                            </tr>
                            <tr class="reasonForRejection" style="display:none;">
                                <td><label for="reasonForRejection">Motifs de rejet </label></td>
                                <td colspan="3">
                                    <textarea class="form-control" id="reasonForRejection" name="reasonForRejection" placeholder="Motifs de rejet" title="Please enter motifs de rejet" style="width:60%;height:60px !important;"></textarea>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="labNo">Code Labo </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control" id="labNo" name="labNo" placeholder="Code Labo" title="Please enter code labo" style="width:30%;"/>
                                </td>
                            </tr>
                            <tr><td colspan="4" style="height:30px;border:none;"></td></tr>
                            <tr>
                                <td><label for="">Date de r�alisation de la charge virale </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control date" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="dd/mm/yyyy" title="Please enter date de r�alisation de la charge virale" style="width:30%;"/>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="testingPlatform">Technique utilis�e </label></td>
                                <td colspan="3">
                                    <select class="form-control" id="testingPlatform" name="testingPlatform" title="Please select technique utilis�e" style="width:30%;">
                                        <option value=""> -- Select -- </option>
                                        <option value="plasma protocole 600�l">Plasma protocole 600�l</option>
                                        <option value="DBS protocole 1000 �l">DBS protocole 1000 �l</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="vlResult">R�sultat </label></td>
                                <td>
                                    <input type="text" class="form-control" id="vlResult" name="vlResult" placeholder="R�sultat" title="Please enter r�sultat" style="width:80%;"/>copies/ml
                                </td>
                                <td colspan="2" style="text-align:center;">Limite de d�tection : < 40 Copies/ml ou  log  < 1.6 ( pour DBS )</td>
                            </tr>
                            <tr>
                                <td colspan="4"><label class="radio-inline" style="margin:0;padding:0;">A remplir par le service effectuant la charge virale</label></td>
                            </tr>
                            <tr><td colspan="4" style="height:30px;border:none;"></td></tr>
                            <tr>
                                <td><label for="">Date de remise du r�sultat </label></td>
                                <td colspan="3">
                                    <input type="text" class="form-control date" id="dateOfResult" name="dateOfResult" placeholder="dd/mm/yyyy" title="Please enter date de remise du r�sultat" style="width:30%;"/>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="box-header with-border">
                  <label class="radio-inline" style="margin:0;padding:0;">1. Biffer la mention inutile<br>2. S�lectionner un seul r�gime de traitement</label>
                </div>
              </div>
              <!-- /.box-body -->
              <div class="box-footer">
                <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                <input type="hidden" name="saveNext" id="saveNext"/>
                <input type="hidden" name="formId" id="formId" value="3"/>
                
                <a class="btn btn-primary" href="javascript:void(0);" onclick="validateSaveNow();return false;">Save and Next</a>
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
        dateFormat: 'dd/mm/yy',
        yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
       }).click(function(){
           $('.ui-datepicker-calendar').show();
        });
        
       $('#dateOfArtInitiation,#dateOfArvRegimenChange').datepicker({
        changeMonth: true,
        changeYear: true,
        dateFormat: 'dd/mm/y',
        yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
       }).click(function(){
           $('.ui-datepicker-calendar').show();
        });
        
        $('.dateTime').datetimepicker({
          changeMonth: true,
          changeYear: true,
          dateFormat: 'dd/mm/yyyy',
          timeFormat: "HH:mm",
          yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
          }).click(function(){
   	    $('.ui-datepicker-calendar').show();
          });
        
        $('.date').mask('99/99/9999');
        $('#dateOfArtInitiation,#dateOfArvRegimenChange').mask('99/99/99');
        $('.dateTime').mask('99/99/9999 99:99');
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
        $(".reasonForRejection").show();
      }else{
        $(".reasonForRejection").hide();
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
      
      splitDob = dob.split("/");
      var dobYear = splitDob[2];
      var dobMonth = splitDob[1];
      var dobDate = splitDob[0];
      
      var date1 = new Date(yyyy,mm,dd);
      var date2 = new Date(dobYear,dobMonth,dobDate);
      var diff = new Date(date1.getTime() - date2.getTime());
      if((diff.getUTCFullYear() - 1970) == 0){
        $("#ageInMonths").val((diff.getUTCMonth() >= 0)? parseInt(diff.getUTCMonth())+parseInt(1): ''); // Gives month count of difference
      }else{
        $("#ageInMonths").val("");
      }
      $("#ageInYears").val((diff.getUTCFullYear() - 1970 >= 1)? (diff.getUTCFullYear() - 1970) : ''); // Gives difference as year
    }
    
    function validateNow(){
    }
    
    function validateSaveNow(){
    }
  </script>
  
 <?php
 //include('footer.php');
 ?>
