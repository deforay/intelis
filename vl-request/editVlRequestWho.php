<?php
ob_start();
if($arr['sample_code']=='auto' || $arr['sample_code']=='alphanumeric'){
  $numeric = '';
  $maxLength = '';
  if($arr['max_length']!='' && $arr['sample_code']=='alphanumeric'){
  $maxLength = $arr['max_length'];
  $maxLength = "maxlength=".$maxLength;
  }
}else{
  $numeric = 'checkNum';
  $maxLength = '';
  if($arr['max_length']!=''){
  $maxLength = $arr['max_length'];
  $maxLength = "maxlength=".$maxLength;
  }
}
//check remote user
$pdQuery="SELECT * from province_details";
if($sarr['user_type']=='remoteuser'){
  $sampleCode = 'remote_sample_code';
  //check user exist in user_facility_map table
    $chkUserFcMapQry = "Select user_id from vl_user_facility_map where user_id='".$_SESSION['userId']."'";
    $chkUserFcMapResult = $db->query($chkUserFcMapQry);
    if($chkUserFcMapResult){
    $pdQuery="SELECT * from province_details as pd JOIN facility_details as fd ON fd.facility_state=pd.province_name JOIN vl_user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where user_id='".$_SESSION['userId']."'";
    }
}else{
  $sampleCode = 'sample_code';
}
$pdResult=$db->query($pdQuery);
$province = '';
$province.="<option value=''> -- Select -- </option>";
            foreach($pdResult as $provinceName){
              $province .= "<option value='".$provinceName['province_name']."##".$provinceName['province_code']."'>".ucwords($provinceName['province_name'])."</option>";
            }
$facility = '';
$facility.="<option data-code='' value=''> -- Select -- </option>";
foreach($fResult as $fDetails){
  $facility .= "<option data-code='".$fDetails['facility_code']."' value='".$fDetails['facility_id']."'>".ucwords($fDetails['facility_name'])."</option>";
}
$sQuery="SELECT * from r_vl_sample_type where status='active'";
$sResult=$db->query($sQuery);
$aQuery="SELECT * from r_art_code_details where nation_identifier='who'";
$aResult=$db->query($aQuery);
//facility details
$facilityQuery="SELECT * from facility_details where facility_id='".$vlQueryInfo[0]['facility_id']."'";
$facilityResult=$db->query($facilityQuery);
if(!isset($facilityResult[0]['facility_code'])){
  $facilityResult[0]['facility_code'] = '';
}
if(!isset($facilityResult[0]['facility_state']) || $facilityResult[0]['facility_state']==''){
  $facilityResult[0]['facility_state'] = '';
}
if(!isset($facilityResult[0]['facility_district']) || $facilityResult[0]['facility_district']==''){
  $facilityResult[0]['facility_district'] = '';
}
$stateName = $facilityResult[0]['facility_state'];
$stateQuery="SELECT * from province_details where province_name='".$stateName."'";
$stateResult=$db->query($stateQuery);
if(!isset($stateResult[0]['province_code']) || $stateResult[0]['province_code'] == ''){
  $stateResult[0]['province_code'] = '';
}
//district details
$districtQuery="SELECT DISTINCT facility_district from facility_details where facility_state='".$stateName."'";
$districtResult=$db->query($districtQuery);
?>
<!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1><i class="fa fa-edit"></i> VIRAL LOAD LABORATORY REQUEST FORM </h1>
      <ol class="breadcrumb">
        <li><a href="../dashboard/index.php"><i class="fa fa-dashboard"></i> Home</a></li>
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
        <div class="box-body">
          <!-- form start -->
            <form class="form-inline" method="post" name="vlRequestForm" id="vlRequestForm" autocomplete="off" action="editVlRequestHelperWho.php">
              <div class="box-body">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Specimen identification information: To be completed by laboratory staff</h3>
                    </div>
                  <div class="box-body">
                    <div class="row">
                      <div class="col-xs-3 col-md-3">
                        <div class="form-group">
                        <?php if($sarr['user_type']=='remoteuser'){ ?>
                            <label for="sampleCode">Sample ID </label><br>
                            <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?php echo ($sCode!='') ? $sCode : $vlQueryInfo[0][$sampleCode]; ?></span>
                            <input type="hidden" class="" id="sampleCode" name="sampleCode" value="<?php echo ($sCode!='') ? $sCode : $vlQueryInfo[0][$sampleCode]; ?>"/>
                          <?php } else { ?>
                          <label for="sampleCode">Sample Code <span class="mandatory">*</span></label>
                          <input type="text" class="form-control isRequired <?php echo $numeric;?>" id="sampleCode" name="sampleCode" <?php echo $maxLength;?> placeholder="Enter Sample Code" title="Please enter sample code" style="width:100%;" value="<?php echo $vlQueryInfo[0]['sample_code'];?>" onchange="checkSampleNameValidation('vl_request_form','<?php echo $sampleCode;?>',this.id,'<?php echo "vl_sample_id##".$vlQueryInfo[0]["vl_sample_id"];?>','This sample number already exists.Try another number',null)"/>
                          <input type="hidden" name="sampleCodeCol" value="<?php echo $vlQueryInfo[0]['sample_code'];?>"/>
                          <?php } ?>
                        </div>
                      </div>
                    </div>
                    <div class="row">
                      <div class="col-xs-3 col-md-3">
                        <div class="form-group">
                        <label for="province">Province <span class="mandatory">*</span></label>
                          <select class="form-control isRequired" name="province" id="province" title="Please choose province" style="width:100%;" onchange="getfacilityDetails(this);">
                            <option value=""> -- Select -- </option>
                            <?php foreach($pdResult as $provinceName){ ?>
                            <option value="<?php echo $provinceName['province_name']."##".$provinceName['province_code'];?>" <?php echo ($facilityResult[0]['facility_state']."##".$stateResult[0]['province_code']==$provinceName['province_name']."##".$provinceName['province_code'])?"selected='selected'":""?>><?php echo ucwords($provinceName['province_name']);?></option>;
                            <?php } ?>
                          </select>
                        </div>
                      </div>
                      <div class="col-xs-3 col-md-3">
                        <div class="form-group">
                        <label for="District">District  <span class="mandatory">*</span></label>
                          <select class="form-control isRequired" name="district" id="district" title="Please choose district" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                            <option value=""> -- Select -- </option>
                            <?php foreach($districtResult as $districtName){ ?>
                              <option value="<?php echo $districtName['facility_district'];?>" <?php echo ($facilityResult[0]['facility_district']==$districtName['facility_district'])?"selected='selected'":""?>><?php echo ucwords($districtName['facility_district']);?></option>
                              <?php } ?>
                          </select>
                        </div>
                      </div>
                      <div class="col-xs-3 col-md-3">
                        <div class="form-group">
                          <label for="fName">Facility Name <span class="mandatory">*</span></label>
                            <select class="form-control isRequired" id="fName" name="fName" title="Please select facility name" style="width:100%;" onchange="autoFillFacilityCode();">
                              <option data-code="" value=''> -- Select -- </option>
                                <?php foreach($fResult as $fDetails){ ?>
                                <option data-code="<?php echo $fDetails['facility_code']; ?>" value="<?php echo $fDetails['facility_id'];?>" <?php echo ($vlQueryInfo[0]['facility_id']==$fDetails['facility_id'])?"selected='selected'":""?>><?php echo ucwords($fDetails['facility_name']);?></option>
                                <?php } ?>
                            </select>
                          </div>
                      </div>
                      <div class="col-xs-3 col-md-3">
                        <div class="form-group">
                          <label for="fCode">Facility Code </label>
                            <input type="text" class="form-control" style="width:100%;" name="fCode" id="fCode" placeholder="Facility Code" title="Please enter facility code" value="<?php echo $facilityResult[0]['facility_code'];?>">
                          </div>
                      </div>
                    </div>

                    <div class="row">
                        <div class="col-xs-3 col-md-3">
                          <div class="form-group">
                          <label for="sampleCollectionDate">Date Specimen Collected <span class="mandatory">*</span></label>
                          <input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select sample collection date"  value="<?php echo $vlQueryInfo[0]['sample_collection_date'];?>"  onchange="checkSampleReceviedDate();checkSampleTestingDate();">
                          </div>
                        </div>
                        <div class="col-xs-3 col-md-3">
                          <div class="form-group">
                          <label for="">Specimen Type</label>
                          <select name="specimenType" id="specimenType" class="form-control" title="Please choose Specimen type" style="width: 100%;">
                                <option value=""> -- Select -- </option>
                                <?php foreach($sResult as $name){ ?>
                                 <option value="<?php echo $name['sample_id'];?>"<?php echo ($vlQueryInfo[0]['sample_type']==$name['sample_id'])?"selected='selected'":""?>><?php echo ucwords($name['sample_name']);?></option>
                                 <?php } ?>
                            </select>
                          </div>
                        </div>
                        <?php if($sarr['user_type'] == 'remoteuser') { ?>
                                                  <div class="col-xs-3 col-md-3">
                                                       <div class="">
                                                            <label for="labId">VL Testing Hub <span class="mandatory">*</span></label>
                                                            <select name="labId" id="labId" class="form-control isRequired" title="Please choose VL testing hub" style="width:100%;">
                                                                 <option value="">-- Select --</option>
                                                                 <?php foreach($lResult as $labName){ ?>
                                                                  <option value="<?php echo $labName['facility_id'];?>" <?php echo ($vlQueryInfo[0]['lab_id']==$labName['facility_id'])?"selected='selected'":""?>><?php echo ucwords($labName['facility_name']);?></option>
                                                                 <?php } ?>
                                                            </select>
                                                       </div>
                                                  </div>
                                        <?php } ?>
                    </div>
                    </div>
                </div>
                <div class="box box-primary">
                    <div class="box-body">
                      <div class="box-header with-border">
                        <h3 class="box-title">Paitent information: To be completed by clinician</h3>
                      </div>
                    </div>
                    <div class="box-body">
                        <table class="table">
                            <tr>
                              <td><label for="patientFirstName">Patient First Name</label></td>
                              <td>
                                <input type="text" name="patientFirstName" id="patientFirstName" class="form-control" placeholder="Enter First Name" title="Enter patient first name" value="<?php echo $patientFirstName;?>"/>
                              </td>
                              <td><label for="patientMiddleName">Patient Middle Name</label></td>
                              <td>
                                <input type="text" name="patientMiddleName" id="patientMiddleName" class="form-control" placeholder="Enter Middle Name" title="Enter patient middle name" value="<?php echo $patientMiddleName;?>"/>
                              </td>
                              <td><label for="patientLastName">Patient Last Name</label></td>
                              <td>
                                <input type="text" name="patientLastName" id="patientLastName" class="form-control" placeholder="Enter Last Name" title="Enter patient last name" value="<?php echo $patientLastName;?>"/>
                              </td>
                            </tr>
                            <tr>
                              <td><label for="uniqueId">Unique identifier</label></td>
                              <td>
                                <input type="text" name="uniqueId" id="uniqueId" class="uniqueId form-control" placeholder="Enter Unique Id" title="Enter unique identifier" value="<?php echo $vlQueryInfo[0]['patient_other_id'];?>"/>
                              </td>
                              <td><label for="dob">Date Of Birth</label></td>
                              <td>
                                <input type="text" name="dob" id="dob" class="date form-control" placeholder="Enter DOB" title="Enter dob" value="<?php echo $vlQueryInfo[0]['patient_dob'];?>" onchange="getAge();checkARTInitiationDate();" />
                              </td>
                              <td><label for="artNo">Art Number</label></td>
                              <td>
                                <input type="text" name="artNo" id="artNo" class="form-control" placeholder="Enter ART Number" title="Enter art number" value="<?php echo $vlQueryInfo[0]['patient_art_no'];?>"/>
                              </td>
                            </tr>
                            <tr>
                              <td><label for="ageInYears">If unknown, age in years</label></td>
                              <td>
                                <input type="text" class="form-control" name="ageInYears" id="ageInYears" placeholder="If DOB Unkown" title="Enter age in years" style="width:100%;" value="<?php echo $vlQueryInfo[0]['patient_age_in_years'];?>" >
                              </td>
                              <td><label for="dob">If age < 1, age in months</label></td>
                              <td>
                                <input type="text" class="form-control" name="ageInMonths" id="ageInMonths" placeholder="If age < 1 year" title="Enter age in months" style="width:100%;" value="<?php echo $vlQueryInfo[0]['patient_age_in_months'];?>" >
                              </td>
                              <td colspan="2">
                                <label for="gender">Gender &nbsp;&nbsp;</label>
                                 <label class="radio-inline">
                                  <input type="radio" class="" id="genderMale" name="gender" value="male" title="Please check gender"<?php echo ($vlQueryInfo[0]['patient_gender']=='male')?"checked='checked'":""?>> Male
                                  </label>
                                <label class="radio-inline">
                                  <input type="radio" class="" id="genderFemale" name="gender" value="female" title="Please check gender"<?php echo ($vlQueryInfo[0]['patient_gender']=='female')?"checked='checked'":""?>> Female
                                </label>
                                <label class="radio-inline">
                                  <input type="radio" class="" id="genderNotRecorded" name="gender" value="not_recorded" title="Please check gender"<?php echo ($vlQueryInfo[0]['patient_gender']=='not_recorded')?"checked='checked'":""?>> Not Recorded
                                </label>
                              </td>
                            </tr>
                            <tr>
                                <td><label for="artRegimen">Current Regimen</label></td>
                                <td>
                                    <select class="form-control" id="artRegimen" name="artRegimen" placeholder="Enter ART Regimen" title="Please choose ART Regimen" style="width:100%;" onchange="checkARTRegimenValue();">
                                 <option value=""> -- Select -- </option>
                                 <?php foreach($aResult as $parentRow){ ?>
                                  <option value="<?php echo $parentRow['art_code']; ?>"<?php echo ($vlQueryInfo[0]['current_regimen']==$parentRow['art_code'])?"selected='selected'":""?>><?php echo $parentRow['art_code']; ?></option>
                                 <?php } if($sarr['user_type']!='vluser'){  ?>
                                 <option value="other">Other</option>
                                 <?php } ?>
                                </select>
                                <input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="New ART Regimen" title="Please enter new art regimen" style="width:100%;display:none;margin-top:2px;" >
                                </td>
                                <td><label for="dateOfArtInitiation">Date treatment initiated</td>
                                <td colspan="3">
                                  <input type="text" class="form-control date" name="dateOfArtInitiation" id="dateOfArtInitiation" placeholder="Date Of treatment initiated" title="Date Of treatment initiated" value="<?php echo $vlQueryInfo[0]['date_of_initiation_of_current_regimen'];?>" style="width:36%;" onchange="checkARTInitiationDate();" >
                                </td>
                            </tr>
                            <tr>
                              <td><label for="lineOfTreatment">Line of Treatment </label></td>
                              <td>
                                  <select name="lineOfTreatment" id="lineOfTreatment" class="form-control" title="Please choose line of treatment" style="width:100%;">
                                    <option value=""> -- Select -- </option>
                                    <option value="1" <?php echo ($vlQueryInfo[0]['line_of_treatment'] == 1)?'selected="selected"':''; ?>>First Line</option>
                                    <option value="2" <?php echo ($vlQueryInfo[0]['line_of_treatment'] == 2)?'selected="selected"':''; ?>>Second Line</option>
                                    <option value="3" <?php echo ($vlQueryInfo[0]['line_of_treatment'] == 3)?'selected="selected"':''; ?>>Third Line</option>
                                   </select>
                              </td>
                              <td colspan="4"><label for="therapy">Is the Patient receiving second-line theraphy? </label>
                                    <label class="radio-inline">
                                        <input type="radio" class="" id="theraphyYes" name="theraphy" value="yes" <?php echo($vlQueryInfo[0]['patient_receiving_therapy'] == 'yes' )?"checked='checked'":""; ?> title="Is the Patient receiving second-line theraphy? "> Yes
                                    </label>
                                    <label class="radio-inline">
                                        <input type="radio" class=" " id="theraphyNo" name="theraphy" value="no"<?php echo($vlQueryInfo[0]['patient_receiving_therapy'] == 'no' )?"checked='checked'":""; ?> title="Is the Patient receiving second-line theraphy?"> No
                                    </label>
                              </td>
                            </tr>
                            <tr class="femaleSection" style="display:<?php echo($vlQueryInfo[0]['patient_gender'] == 'male' || $vlQueryInfo[0]['patient_gender'] == 'not_recorded')?'none':''; ?>">
                                <td colspan="3" class=""><label for="breastfeeding" class="">Is the Patient Pregnant or Breastfeeding?</label>
                                  <label class="radio-inline">
                                     <input type="radio" id="breastfeedingYes" name="breastfeeding" value="yes" title="Is Patient Pregnant or Breastfeeding" <?php echo ($vlQueryInfo[0]['is_patient_breastfeeding']=='yes')?"checked='checked'":""?>>Yes
                                  </label>
                                  <label class="radio-inline">
                                    <input type="radio" id="breastfeedingNo" name="breastfeeding" value="no" title="Is Patient Pregnant or Breastfeeding" <?php echo ($vlQueryInfo[0]['is_patient_breastfeeding']=='no')?"checked='checked'":""?>>No
                                  </label>
                                </td>
                                <td colspan="3" class=""><label for="drugTransmission" class="">Is the Patient receiving ARV drugs for <br>preventing mother-to-child transmission?</label>
                                  <label class="radio-inline">
                                     <input type="radio" id="transmissionYes" name="drugTransmission" value="yes" <?php echo($vlQueryInfo[0]['patient_drugs_transmission'] == 'yes' )?"checked='checked'":""; ?> title="Is the Patient receiving ARV drugs for preventing mother-to-child transmission?">Yes
                                  </label>
                                  <label class="radio-inline">
                                    <input type="radio" id="transmissionNo" name="drugTransmission" value="no" <?php echo($vlQueryInfo[0]['patient_drugs_transmission'] == 'no' )?"checked='checked'":""; ?> title="Is the Patient receiving ARV drugs for preventing mother-to-child transmission?">No
                                  </label>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3"><label for="patientTB">Does the patient have active TB?</label>
                                    <label class="radio-inline">
                                        <input type="radio" class="" id="patientTBYes" name="patientTB" value="yes" title="Does the patient have active TB?" <?php echo($vlQueryInfo[0]['patient_tb'] == 'yes' )?"checked='checked'":""; ?>> Yes
                                    </label>
                                    <label class="radio-inline">
                                        <input type="radio" class=" " id="patientTBNo" name="patientTB" value="no" title="Does the patient have active TB?" <?php echo($vlQueryInfo[0]['patient_tb'] == 'no' )?"checked='checked'":""; ?>> No
                                    </label>
                                </td>
                                <td colspan=""><label for="patientPhoneNumber">Patient's telephone number</td>
                                <td colspan="2">
                                  <input type="text" class="form-control " name="patientPhoneNumber" id="patientPhoneNumber" placeholder="Phone Number" title="Enter telephone number" style="width:100%;" value="<?php echo $vlQueryInfo[0]['patient_mobile_number'];?>" >
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3"><label for="patientTB">If Yes, is he or she on</label>
                                    <label class="radio-inline">
                                        <input type="radio" class="" id="patientTBInitiation" name="patientTBActive" value="yes" title="Does the patient have active TB? Yes" <?php echo($vlQueryInfo[0]['patient_tb'] == 'no')?'disabled':''; ?> <?php echo($vlQueryInfo[0]['patient_tb_yes'] == 'yes' )?"checked='checked'":""; ?>> Initiation
                                    </label>
                                    <label class="radio-inline">
                                        <input type="radio" class=" " id="patientTBPhase" name="patientTBActive" value="no" title="Does the patient have active TB? Yes" <?php echo($vlQueryInfo[0]['patient_tb'] == 'no')?'disabled':''; ?> <?php echo($vlQueryInfo[0]['patient_tb_yes'] == 'no' )?"checked='checked'":""; ?>> Continuation Phase
                                    </label>
                                </td>
                                <td colspan=""><label for="arvAdherence">ARV adherence</td>
                                <td colspan="2">
                                  <select name="arvAdherence" id="arvAdherence" class="form-control" title="Please choose Adherence" style="width:100%;">
                                    <option value=""> -- Select -- </option>
                                    <option value="good" <?php echo ($vlQueryInfo[0]['arv_adherance_percentage']=='good')?"selected='selected'":""?>>Good >= 95%</option>
                                    <option value="fair" <?php echo ($vlQueryInfo[0]['arv_adherance_percentage']=='fair')?"selected='selected'":""?>>Fair (85-94%)</option>
                                    <option value="poor" <?php echo ($vlQueryInfo[0]['arv_adherance_percentage']=='poor')?"selected='selected'":""?>>Poor < 85%</option>
                                   </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Indication for viral load testing</h3>
                    <small>(Please tick one):(To be completed by clinician)</small>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="col-lg-12">
                                <label class="radio-inline">
                                    <?php
                                    $checked = '';
                                    $display = '';
                                    if($vlQueryInfo[0]['last_vl_date_routine']!='' || $vlQueryInfo[0]['last_vl_result_routine']!='' || $vlQueryInfo[0]['last_vl_sample_type_routine']!=''){
                                    //if($vlQueryInfo[0]['reason_for_vl_testing']=='routine'){
                                     $checked = 'checked="checked"';
                                     $display = 'block';
                                    }else{
                                     $checked = '';
                                     $display = 'none';
                                    }
                                    ?>
                                    <input type="radio" class="" id="RmTesting" name="stViralTesting" value="routine" title="Please check routine monitoring" <?php echo $checked;?> onclick="showTesting('RmTesting');">
                                    <strong>Routine Monitoring</strong>
                                </label>
                                </div>
                            </div>
                        </div>
                    </div><br/>
                    <div class="row RmTesting hideTestData" style="display: <?php echo $display;?>;">
                       <div class="col-md-6">
                            <label class="col-lg-5 control-label">Date of last viral load test</label>
                            <div class="col-lg-7">
                            <input type="text" class="form-control date viralTestData readonly" readonly='readonly' id="rmTestingLastVLDate" name="rmTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo $general->humanDateFormat($vlQueryInfo[0]['last_vl_date_routine']); ?>"/>
                        </div>
                      </div>
                       <div class="col-md-6">
                            <label for="rmTestingVlValue" class="col-lg-3 control-label">VL Value</label>
                            <div class="col-lg-7">
                            <input type="text" class="form-control viralTestData" id="rmTestingVlValue" name="rmTestingVlValue" placeholder="Enter VL Value" title="Please enter vl value" value="<?php echo $vlQueryInfo[0]['last_vl_result_routine']; ?>"/>
                            (copies/ml)
                        </div>
                      </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <div class="col-lg-12">
                                <label class="radio-inline">
                                    <?php
                                    $checked = '';
                                    $display = '';
                                    if($vlQueryInfo[0]['last_vl_date_failure_ac']!='' || $vlQueryInfo[0]['last_vl_result_failure_ac']!='' || $vlQueryInfo[0]['last_vl_sample_type_failure_ac']!=''){
                                    //if($vlQueryInfo[0]['reason_for_vl_testing']=='failure'){
                                     $checked = 'checked="checked"';
                                     $display = 'block';
                                    }else{
                                     $checked = '';
                                     $display = 'none';
                                    }
                                    ?>
                                    <input type="radio" class="" id="RepeatTesting" name="stViralTesting" value="failure" title="Repeat VL test after suspected treatment failure adherence counseling" <?php echo $checked;?> onclick="showTesting('RepeatTesting');">
                                    <strong>Repeat VL test after detectable viraemia and six months of adherence counselling </strong>
                                </label>
                                </div>
                            </div>
                        </div>
                    </div><br/>
                    <div class="row RepeatTesting hideTestData" style="display: <?php echo $display;?>;">
                       <div class="col-md-6">
                            <label class="col-lg-5 control-label">Date of last viral load test</label>
                            <div class="col-lg-7">
                            <input type="text" class="form-control date viralTestData readonly" readonly='readonly' id="repeatTestingLastVLDate" name="repeatTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo $general->humanDateFormat($vlQueryInfo[0]['last_vl_date_failure_ac']); ?>"/>
                            </div>
                      </div>
                       <div class="col-md-6">
                            <label for="repeatTestingVlValue" class="col-lg-3 control-label">VL Value</label>
                            <div class="col-lg-7">
                            <input type="text" class="form-control viralTestData" id="repeatTestingVlValue" name="repeatTestingVlValue" placeholder="Enter VL Value" title="Please enter vl value" value="<?php echo $vlQueryInfo[0]['last_vl_result_failure_ac']; ?>" />
                            (copies/ml)
                            </div>
                      </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="col-lg-12">
                                <label class="radio-inline">
                                    <?php
                                    $checked = '';
                                    $display = '';
                                    if($vlQueryInfo[0]['last_vl_date_failure']!='' || $vlQueryInfo[0]['last_vl_result_failure']!='' || $vlQueryInfo[0]['last_vl_sample_type_failure']!=''){
                                    //if($vlQueryInfo[0]['reason_for_vl_testing']=='suspect'){
                                     $checked = 'checked="checked"';
                                     $display = 'block';
                                    }else{
                                     $checked = '';
                                     $display = 'none';
                                    }
                                    ?>
                                    <input type="radio" class="" id="suspendTreatment" name="stViralTesting" value="suspect" title="Suspect Treatment Failure" <?php echo $checked;?> onclick="showTesting('suspendTreatment');">
                                    <strong>Suspect Treatment Failure</strong>
                                </label>
                                </div>
                            </div>
                        </div>
                    </div><br/>
                    <div class="row suspendTreatment hideTestData" style="display: <?php echo $display;?>;">
                        <div class="col-md-6">
                             <label class="col-lg-5 control-label">Date of last viral load test</label>
                             <div class="col-lg-7">
                             <input type="text" class="form-control date viralTestData readonly" readonly='readonly' id="suspendTreatmentLastVLDate" name="suspendTreatmentLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo $general->humanDateFormat($vlQueryInfo[0]['last_vl_date_failure']); ?>"/>
                             </div>
                       </div>
                        <div class="col-md-6">
                             <label for="suspendTreatmentVlValue" class="col-lg-3 control-label">VL Value</label>
                             <div class="col-lg-7">
                             <input type="text" class="form-control viralTestData" id="suspendTreatmentVlValue" name="suspendTreatmentVlValue" placeholder="Enter VL Value" title="Please enter vl value" value="<?php echo $vlQueryInfo[0]['last_vl_result_failure']; ?>" />
                             (copies/ml)
                             </div>
                       </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label for="reqClinician" class="col-lg-4 control-label">Request Clinician</label>
                        <div class="col-lg-7">
                           <input type="text" class="form-control" id="reqClinician" name="reqClinician" placeholder="Request Clinician" title="Please enter request clinician" value="<?php echo $vlQueryInfo[0]['request_clinician_name'];?>"/>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="col-lg-4 control-label" for="requestDate">Requested Date </label>
                        <div class="col-lg-7">
                            <input type="text" class="form-control date readonly" readonly='readonly' id="requestDate" name="requestDate" placeholder="Request Date" title="Please select request date" value="<?php echo $general->humanDateFormat($vlQueryInfo[0]['test_requested_on']); ?>"/>
                        </div>
                    </div>
                </div><br/>
                </div>
              </div>
              <?php if($sarr['user_type'] != 'remoteuser') { ?>
              <div class="box box-primary">
                  <div class="box-body">
                    <div class="box-header with-border">
                    <h3 class="box-title">Laboratory Information</h3>
                    </div>
                    <table class="table">
                      <tr>
                        <td><label for="testingPlatform">VL Testing Platform</label></td>
                        <td>
                          <select name="testingPlatform" id="testingPlatform" class="form-control labSection" title="Please choose VL Testing Platform" style="width:100%;">
                            <option value="">-- Select --</option>
                            <?php foreach($importResult as $mName) { ?>
                              <option value="<?php echo $mName['machine_name'].'##'.$mName['lower_limit'].'##'.$mName['higher_limit'];?>" <?php echo($vlQueryInfo[0]['vl_test_platform'] == $mName['machine_name'])? 'selected="selected"':''; ?>><?php echo $mName['machine_name'];?></option>
                              <?php } ?>
                          </select>
                        </td>
                        <td><label for="testMethods">Test Methods</label></td>
                        <td>
                          <select name="testMethods" id="testMethods" class="form-control labSection" title="Please choose test methods">
                          <option value=""> -- Select -- </option>
                          <option value="individual" <?php echo($vlQueryInfo[0]['test_methods'] == 'individual')? 'selected="selected"':''; ?>>Individual</option>
                          <option value="minipool" <?php echo($vlQueryInfo[0]['test_methods'] == 'minipool')? 'selected="selected"':''; ?>>Minipool</option>
                          <option value="other pooling algorithm" <?php echo($vlQueryInfo[0]['test_methods'] == 'other pooling algorithm')? 'selected="selected"':''; ?>>Other Pooling Algorithm</option>
                         </select>
                        </td>
                        <td class="rejectionReason"><label for="rejectionReason">Reason For Failure<span class="mandatory">*</span> </label></td>
                        <td class="rejectionReason">
                            <select name="rejectionReason" id="rejectionReason" class="isRequired rejectionReason form-control labSection" title="Please choose reason" style="width:100%;">
                                <option value="">-- Select --</option>
                               <?php foreach($rejectionResult as $reject){ ?>
                                 <option value="<?php echo $reject['rejection_reason_id'];?>" <?php echo ($vlQueryInfo[0]['reason_for_sample_rejection']==$reject['rejection_reason_id'])?"selected='selected'":""?>><?php echo ucwords($reject['rejection_reason_name']);?></option>
                                 <?php } ?>
                            </select>
                        </td>
                      </tr>
                      <tr>
                        <td><label for="sampleTestingDateAtLab">Sample Testing Date</label></td>
                        <td><input type="text" class="form-control labSection dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Enter Sample Testing Date." title="Please enter Sample Testing Date" value="<?php echo $vlQueryInfo[0]['sample_tested_datetime'];?>" onchange="checkSampleTestingDate();" style="width:100%;"/></td>
                        <td class="vlResult"><label for="vlResult">Viral Load Result<span class="mandatory">*</span><br/> (copiesl/ml)</label></td>
                        <td><input type="text" class="vlResult form-control labSection" id="vlResult" name="vlResult" placeholder="Enter Viral Load Result" title="Please enter viral load result" value="<?php echo $vlQueryInfo[0]['result_value_absolute'];?>" style="width:100%;" /></td>
                        <td><label for="labId">Lab Name</label></td>
                        <td>
                          <select name="labId" id="labId" class="form-control labSection" title="Please choose lab name" style="width:100%;">
                            <option value=""> -- Select -- </option>
                            <?php foreach($lResult as $labName){ ?>
                              <option value="<?php echo $labName['facility_id'];?>" <?php echo ($vlQueryInfo[0]['lab_id']==$labName['facility_id'])?"selected='selected'":""?>><?php echo ucwords($labName['facility_name']);?></option>
                              <?php } ?>
                          </select>
                        </td>
                      </tr>
                      <tr>
                        <td><label>Approved By</label></td>
                        <td>
                          <select name="approvedBy" id="approvedBy" class="form-control labSection" title="Please choose approved by" style="width:100%;">
                            <option value="">-- Select --</option>
                            <?php foreach($userResult as $uName){ ?>
                              <option value="<?php echo $uName['user_id'];?>" <?php echo ($vlQueryInfo[0]['result_approved_by'] == $uName['user_id'])?"selected=selected":""; ?>><?php echo ucwords($uName['user_name']);?></option>
                              <?php } ?>
                          </select>
                         </td>
                        <td><label for="labComments">Laboratory <br/>Scientist Comments</label></td>
                        <td colspan="3"><textarea class="form-control labSection" name="labComments" id="labComments" title="Enter lab comments" style="width:100%"><?php echo trim($vlQueryInfo[0]['approver_comments']); ?></textarea></td>
                      </tr>
                      <tr>
                        <td><label for="status">Status <span class="mandatory">*</span></label></td>
                        <td>
                          <select class="form-control labSection isRequired" id="status" name="status" title="Please select test status" style="width:100%;" onchange="hideReasonfield()">
                            <option value="">-- Select --</option>
                            <option value="7"<?php echo (7==$vlQueryInfo[0]['result_status']) ? 'selected="selected"':'';?>>Accepted</option>
                            <option value="4"<?php echo (4==$vlQueryInfo[0]['result_status']) ? 'selected="selected"':'';?>>Rejected</option>
                          </select>
                        </td>
                        <td><label for="reasonForResultChanges" class="reasonForResultChanges" style="visibility:<?php echo ($vlQueryInfo[0]['reason_for_vl_result_changes'] == '')?'hidden':'visible' ?>;">Reason For Changes in Result</label></td>
                        <td colspan="3"><textarea class="form-control reasonForResultChanges" name="reasonForResultChanges" id="reasonForResultChanges" placeholder="Enter Reason For Result Changes" title="Please enter reason for result changes" style="width:100%;visibility:<?php echo ($vlQueryInfo[0]['reason_for_vl_result_changes'] == '')?'hidden':'visible' ?>;"><?php echo trim($vlQueryInfo[0]['reason_for_vl_result_changes']);?></textarea></td>
                      </tr>
                    </table>
                  </div>
                </div>
                            <?php } ?>
              <div class="box-footer">
              <input type="hidden" name="isRemoteSample" value="<?php echo $vlQueryInfo[0]['remote_sample'];?>"/>
                <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                <input type="hidden" name="vlSampleId" id="vlSampleId" value="<?php echo $vlQueryInfo[0]['vl_sample_id'];?>"/>
                <a href="vlRequest.php" class="btn btn-default"> Cancel</a>
              </div>
            </form>
      </div>
    </section>
  </div>
<script>
  provinceName = true;
  facilityName = true;
  $(document).ready(function() {
    getAge();
    __clone = $("#vlRequestForm .labSection").clone();
    reason = ($("#reasonForResultChanges").length)?$("#reasonForResultChanges").val():'';
    result = ($("#vlResult").length)?$("#vlResult").val():'';


    hideReasonfield();

  });
  function validateNow(){
    var format = '<?php echo $arr['sample_code'];?>';
    var sCodeLentgh = $("#sampleCode").val();
    var minLength = '<?php echo $arr['min_length'];?>';
    if((format == 'alphanumeric' || format =='numeric') && sCodeLentgh.length < minLength && sCodeLentgh!=''){
      alert("Sample code length atleast "+minLength+" characters");
      return false;
    }
    flag = deforayValidator.init({
      formId: 'vlRequestForm'
    });
    $('.isRequired').each(function () {
      ($(this).val() == '') ? $(this).css('background-color', '#FFFF99') : $(this).css('background-color', '#FFFFFF')
    });
    $("#saveNext").val('save');
    if(flag){
      $.blockUI();
      document.getElementById('vlRequestForm').submit();
    }
  }
  $("input:radio[name=gender]").click(function() {
    if($(this).val() == 'male' || $(this).val() == 'not_recorded'){
      $('.femaleSection').hide();
      $('input[name="breastfeeding"]').prop('checked', false);
      $('input[name="drugTransmission"]').prop('checked', false);
    }else if($(this).val() == 'female'){
      $('.femaleSection').show();
    }
  });
  $("input:radio[name=patientTB]").click(function() {
    if($(this).val() == 'no'){
      $('input[name="patientTBActive"]').prop('checked', false);
      $('input[name="patientTBActive"]').prop('disabled', true);
    }else if($(this).val() == 'yes'){
      $('input[name="patientTBActive"]').prop('disabled', false);
    }
  });
    function showTesting(chosenClass){
     $(".viralTestData").val('');
     $(".hideTestData").hide();
     $("."+chosenClass).show();
    }
  function getfacilityDetails(obj)
  {
    $.blockUI();
      var cName = $("#fName").val();
      var pName = $("#province").val();
      if(pName!='' && provinceName && facilityName){
        facilityName = false;
      }
    if(pName!=''){
      if(provinceName){
      $.post("../includes/getFacilityForClinic.php", { pName : pName},
      function(data){
	  if(data != ""){
            details = data.split("###");
            $("#district").html(details[1]);
            $("#fName").html("<option data-code='' value=''> -- Select -- </option>");
	  }
      });
      }
    }else if(pName=='' && cName==''){
      provinceName = true;
      facilityName = true;
      $("#province").html("<?php echo $province;?>");
      $("#fName").html("<option data-code='' value=''> -- Select -- </option>");
    }
    $.unblockUI();
  }
  function getfacilityDistrictwise(obj){
    $.blockUI();
    var dName = $("#district").val();
    var cName = $("#fName").val();
    if(dName!=''){
      $.post("../includes/getFacilityForClinic.php", {dName:dName,cliName:cName},
      function(data){
	  if(data != ""){
      details = data.split("###");
          $("#fName").html(details[0]);
          $("#labId").html(details[1]);
	  }
      });
    }
    $.unblockUI();
  }
  function autoFillFacilityCode(){
    $("#fCode").val($('#fName').find(':selected').data('code'));
  }
    $("#vlRequestForm .labSection").on("change", function() {
      if($.trim(result)!= ''){
        if($("#vlRequestForm .labSection").serialize() == $(__clone).serialize()){
          if($.trim(reason)== '') {
            $(".reasonForResultChanges").css("visibility","hidden");
          }else{
            $(".reasonForResultChanges").css("visibility","visible");
            $("#reasonForResultChanges").val(reason);
          }
          $("#reasonForResultChanges").removeClass("isRequired");
        }else{
          $(".reasonForResultChanges").css("visibility","visible");
          $("#reasonForResultChanges").addClass("isRequired");
        }
      }
    });
    function hideReasonfield()
    {
      var status = $("#status").val();
      //console.log(status);
      if(status=='4'){
        $("#vlResult").removeClass("isRequired").val('');
        $(".vlResult").hide();
        $(".rejectionReason").show();
        $("#rejectionReason").addClass("isRequired");
        $(".failureReason").show();
      }else{
        $("#vlResult").addClass("isRequired");
        $(".vlResult").show();
          $(".rejectionReason").hide();
        $("#rejectionReason").removeClass("isRequired");
        $("#rejectionReason").val('');
        $(".failureReason").hide();
      }
    }

</script>
