<?php
// imported in covid-19-add-request.php based on country in global config

ob_start();

//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);

//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);

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
$province .= "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
    $province .= "<option data-code='" . $provinceName['province_code'] . "' data-province-id='" . $provinceName['province_id'] . "' data-name='" . $provinceName['province_name'] . "' value='" . $provinceName['province_name'] . "##" . $provinceName['province_code'] . "'>" . ucwords($provinceName['province_name']) . "</option>";
}

?>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><i class="fa fa-edit"></i> WHO HEPATITIS LABORATORY TEST REQUEST FORM</h1>
        <ol class="breadcrumb">
            <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
            <li class="active">Add New Request</li>
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
                <form class="form-horizontal" method="post" name="addHepatitisRequestForm" id="addHepatitisRequestForm" autocomplete="off" action="hepatitis-add-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border">
                                    <h3 class="box-title">SITE INFORMATION</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
                                </div>
                                <table class="table" style="width:100%">
                                    <tr>
                                        <?php if ($sarr['user_type'] == 'remoteuser') { ?>
                                            <td><label for="sampleCode">Sample ID </label></td>
                                            <td>
                                                <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
                                                <input type="hidden" id="sampleCode" name="sampleCode" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode">Sample ID </label><span class="mandatory">*</span></td>
                                            <td>
                                                <input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Sample ID" title="Please enter sample id" style="width:100%;" onchange="checkSampleNameValidation('form_hepatitis','<?php echo $sampleCode; ?>',this.id,null,'The sample id that you entered already exists. Please try another sample id',null)" readonly/>
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
                                        <td><label for="district">District </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="district" id="district" title="Please choose district" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                                                <option value=""> -- Select -- </option>
                                            </select>
                                        </td>
                                        <td><label for="facilityId">Health Facility </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired " name="facilityId" id="facilityId" title="Please choose service provider" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
                                                <?= $general->generateSelectOptions($healthFacilities, null, '-- Select --'); ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><label for="supportPartner">Implementing Partner </label></td>
                                        <td>
                                            <!-- <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire dappui" title="Please enter partenaire dappui" style="width:100%;"/> -->
                                            <select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose partenaire de mise en œuvre" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($implementingPartnerList as $implementingPartner) {
                                                ?>
                                                    <option value="<?php echo ($implementingPartner['i_partner_id']); ?>"><?php echo ucwords($implementingPartner['i_partner_name']); ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td><label for="fundingSource">Funding Partner</label></td>
                                        <td>
                                            <select class="form-control" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($fundingSourceList as $fundingSource) {
                                                ?>
                                                    <option value="<?php echo ($fundingSource['funding_source_id']); ?>"><?php echo ucwords($fundingSource['funding_source_name']); ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <?php if ($sarr['user_type'] == 'remoteuser') { ?>
                                        <td><label for="labId">Lab Name <span class="mandatory">*</span></label> </td>
                                        <td>
                                            <select name="labId" id="labId" class="form-control isRequired" title="Please select Testing Lab name" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                            </select>
                                        </td>
                                        <?php } else{ ?> 
                                            <th></th>
                                            <td></td>
                                        <?php } ?>
                                    
                                </table>
                                <br>
                                <hr style="border: 1px solid #ccc;">

                                <div class="box-header with-border">
                                    <h3 class="box-title">PATIENT INFORMATION</h3>
                                </div>
                                <table class="table" style="width:100%">

                                    <tr>
                                        <th style="width:15% !important"><label for="firstName">First Name <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="firstName" name="firstName" placeholder="First Name" title="Please enter patient first name" style="width:100%;" onchange="" />
                                        </td>
                                        <th style="width:15% !important"><label for="lastName">Last name </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="lastName" name="lastName" placeholder="Last name" title="Please enter patient last name" style="width:100%;" onchange="" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important"><label for="patientId">Patient Code <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="patientId" name="patientId" placeholder="Patient Code" title="Please enter Patient Code" style="width:100%;" onchange="" />
                                        </td>
                                        <th><label for="patientDob">Date of Birth <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <input type="text" class="form-control isRequired" id="patientDob" name="patientDob" placeholder="Date of Birth" title="Please enter Date of birth" style="width:100%;" onchange="calculateAgeInYears();" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Patient Age (years)</th>
                                        <td><input type="number" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="patientAge" name="patientAge" placeholder="Patient Age (in years)" title="Patient Age" style="width:100%;" onchange="" /></td>
                                        <th><label for="patientGender">Gender <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <select class="form-control isRequired" name="patientGender" id="patientGender">
                                                <option value=''> -- Select -- </option>
                                                <option value='male'> Male </option>
                                                <option value='female'> Female </option>
                                                <option value='other'> Other </option>

                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label for="patientGender">Marital Status</label></th>
                                        <td>
                                            <select class="form-control isRequired" name="maritalStatus" id="maritalStatus">
                                                <option value=''> -- Select -- </option>
                                                <option value='married'> Married </option>
                                                <option value='single'> Single </option>
                                                <option value='widow'> Widow </option>
                                                <option value='divorced'> Divorced </option>
                                                <option value='separated'> Separated </option>

                                            </select>
                                        </td>
                                        <th><label for="patientGender">Insurance</label></th>
                                        <td>
                                            <select class="form-control isRequired" name="insurance" id="insurance">
                                                <option value=''> -- Select -- </option>
                                                <option value='mutuelle'> Mutuelle </option>
                                                <option value='RAMA'> RAMA </option>
                                                <option value='MMI'> MMI </option>
                                                <option value='private'> Private </option>
                                                <option value='none'> None </option>

                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Phone number</th>
                                        <td><input type="text" class="form-control " id="patientPhoneNumber" name="patientPhoneNumber" placeholder="Patient Phone Number" title="Patient Phone Number" style="width:100%;" onchange="" /></td>

                                        <th>Patient address</th>
                                        <td><textarea class="form-control " id="patientAddress" name="patientAddress" placeholder="Patient Address" title="Patient Address" style="width:100%;" onchange=""></textarea></td>
                                    </tr>
                                    <tr>
                                        <th>Province</th>
                                        <td><input type="text" class="form-control " id="patientProvince" name="patientProvince" placeholder="Patient Province" title="Please enter the patient province" style="width:100%;" /></td>

                                        <th>District</th>
                                        <td><input class="form-control" id="patientDistrict" name="patientDistrict" placeholder="Patient District" title="Please enter the patient district" style="width:100%;"></td>
                                    </tr>
                                </table>
                                <br><br>
                                <table class="table" style="border-top:#ccc 2px solid;">
                                    <tr>
                                        <th style="width:15% !important">
                                            <label for="testNumber">Ubudehe</label>
                                        </th>
                                        <td style="width:35% !important">
                                            <select name="testNumber" id="testNumber" class="form-control" title="Please choose ubudehe">
                                                <option value="">-- Select --</option>
                                                <?php foreach(array('A','B','C','D','E') as $val){ ?>
                                                    <option value="<?php echo $val;?>"><?php echo $val;?></option>
                                                <?php }?>
                                            </select>
                                        </td>
                                        <th style="width:15% !important"></th>
                                        <td style="width:35% !important"></td>
                                    </tr>
                                </table>
                                <br><br>
                                <table class="table">
                                    <tr>
                                        <th colspan=4 style="border-top:#ccc 2px solid;">
                                            <h4>COMORBIDITIES</h4>
                                        </th>
                                    </tr>
                                    <?php foreach($comorbidityData as $id=>$name){ ?>
                                        <tr>
                                            <th>
                                                <label for="riskFactors"><?php echo ucwords($name);?></label>
                                            </th>
                                            <td>
                                                <select name="comorbidity[<?php echo $id;?>]" id="comorbidity<?php echo $id;?>" class="form-control" title="Please choose <?php echo ucwords($name);?>" style="width:100%"  onchange="comorbidity(this,<?php echo $id;?>);">
                                                    <option value="">-- Select --</option>
                                                    <option value="yes">Yes</option>
                                                    <option value="no">No</option>
                                                    <option value="other">Others</option>
                                                </select>
                                            </td>
                                            
                                            <th class="show-comorbidity<?php echo $id;?>" style="display:none;">
                                                <label for="comorbidityOther<?php echo $id;?>">Enter other comorbidity for <?php echo ucwords($name);?></label>
                                            </th>
                                            <td class="show-comorbidity<?php echo $id;?>" style="display:none;">
                                                <input name="comorbidityOther[<?php echo $id;?>]" id="comorbidityOther<?php echo $id;?>" placeholder="Enter other comorbidity" type="text" class="form-control" title="Please enter <?php echo ucwords($name);?> others" style="width:100%">
                                            </td>
                                        </tr>
                                    <?php }?>
                                </table>
                                <table class="table">
                                    <tr>
                                        <th colspan=4 style="border-top:#ccc 2px solid;">
                                            <h4>HEPATITIS RISK FACTORS</h4>
                                        </th>
                                    </tr>
                                    <?php foreach($riskFactorsData as $id=>$name){ ?>
                                        <tr>
                                            <th>
                                                <label for="riskFactors"><?php echo ucwords($name);?></label>
                                            </th>
                                            <td>
                                                <select name="riskFactors[<?php echo $id;?>]" id="riskFactors<?php echo $id;?>" class="form-control" title="Please choose <?php echo ucwords($name);?>" style="width:100%"  onchange="riskfactor(this,<?php echo $id;?>);">
                                                    <option value="">-- Select --</option>
                                                    <option value="yes">Yes</option>
                                                    <option value="no">No</option>
                                                    <option value="other">Others</option>
                                                </select>
                                            </td>
                                            
                                            <th class="show-riskfactor<?php echo $id;?>" style="display:none;">
                                                <label for="riskFactors">Enter other risk factor for <?php echo ucwords($name);?></label>
                                            </th>
                                            <td class="show-riskfactor<?php echo $id;?>" style="display:none;">
                                                <input name="riskFactorsOther[<?php echo $id;?>]" id="riskFactorsOther<?php echo $id;?>" placeholder="Enter other risk factor" type="text" class="form-control" title="Please enter <?php echo ucwords($name);?> others" style="width:100%">
                                            </td>
                                        </tr>
                                    <?php }?>
                                    <tr>
                                        <th><label for="HbvVaccination">HBV vaccination</label></th>
                                        <td>
                                            <select name="HbvVaccination" id="HbvVaccination" class="form-control isRequired" title="Please choose HBV vaccination" style="width:100%">
                                                <option value="">-- Select --</option>
                                                <option value="yes">Yes</option>
                                                <option value="no">No</option>
                                                <option value="fully-vaccinated">Fully vaccinated</option>
                                            </select>
                                        </td>
                                    </tr>

                                </table>
                            </div>
                        </div>
                        <div class="box-body">
                            <div class="box-header with-border">
                                <h3 class="box-title">TEST RESULTS FOR SCREENING BY RDTs</h3>
                            </div>
                            <table class="table" style="width:100%">
                                <tr>
                                    <th style="width:15% !important">Sample Collection Date <span class="mandatory">*</span> </th>
                                    <td style="width:35% !important;">
                                        <input class="form-control isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" onchange="sampleCodeGeneration();" />
                                    </td>
                                    <th>Specimen Type <span class="mandatory">*</span></th>
                                    <td>
                                        <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose specimen type" style="width:100%">
                                            <option value="">-- Select --</option>
                                            <?php foreach ($specimenTypeResult as $name) { ?>
                                                <option value="<?php echo $name['sample_id']; ?>"><?php echo ucwords($name['sample_name']); ?></option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="HBsAg">HBsAg Result</label></th>
                                    <td>
                                        <select class="form-control" name="HBsAg" id="HBsAg" title="Please choose HBsAg result">
                                            <option value=''> -- Select -- </option>
                                            <option value='positive'>Positive</option>
                                            <option value='negative'>Negative</option>
                                            <option value='intermediate'>Intermediate</option>
                                        </select>
                                    </td>
                                    <th><label for="antiHcv">Anti-HCV Result</label></th>
                                    <td>
                                        <select class="form-control" name="antiHcv" id="antiHcv" title="Please choose Anti-HCV result">
                                            <option value=''> -- Select -- </option>
                                            <option value='positive'>Positive</option>
                                            <option value='negative'>Negative</option>
                                            <option value='intermediate'>Intermediate</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="testTypeRequested">Purpose of Test</label></th>
                                    <td>
                                        <select class="form-control" name="testTypeRequested" id="testTypeRequested" title="Please choose purpose of test">
                                        <?= $general->generateSelectOptions($testReasonResults, null, '-- Select --'); ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <?php if ($sarr['user_type'] != 'remoteuser') { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">TO BE FILLED AT VIRAL LOAD TESTING SITE </h3>
                                    </div>
                                    <table class="table" style="width:100%">
                                        <tr>
                                            <th><label for="">Sample Received Date </label></th>
                                            <td>
                                                <input type="text" class="labSecInput form-control" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="e.g 09-Jan-1992 05:30" title="Please enter sample receipt date" style="width:100%;" />
                                            </td>
                                            <td><label for="labId">Lab Name <span class="mandatory">*</span></label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, $hepatitisInfo['lab_id'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label for="sampleTestedDateTime">Vl Testing Date</label></th>
                                            <td>
                                                <input type="text" class="labSecInput form-control" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="e.g 09-Jan-1992 05:30" title="Please enter testing date" style="width:100%;" />
                                            </td>
                                            <th><label for="vlTestingSite">Vl Testing Site</label></th>
                                            <td>
                                                <input type="text" class="labSecInput form-control" id="vlTestingSite" name="vlTestingSite" placeholder="Testing Site" title="Please enter testing site" style="width:100%;" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label for="reasonVlTest">VL test purpose</label></th>
                                            <td>
                                                <select class="labSecInput form-control" name="reasonVlTest" id="reasonVlTest" title="Please choose VL test purpose">
                                                    <option value=''> -- Select -- </option>
                                                    <option value='Initial HCV VL'>Initial HCV VL</option>
                                                    <option value='SVR12 HCV VL'>SVR12 HCV VL</option>
                                                    <option value='Initial HBV VL'>Initial HBV VL</option>
                                                    <option value='Follow up HBV VL'>Follow up HBV VL</option>
                                                </select>
                                            </td>
                                            <th>Is Sample Rejected ?</th>
                                            <td>
                                                <select class="labSecInput form-control" name="isSampleRejected" id="isSampleRejected">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="yes"> Yes </option>
                                                    <option value="no"> No </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="show-rejection" style="display:none;">
                                            <th class="show-rejection" style="display:none;">Reason for Rejection<span class="mandatory">*</span></th>
                                            <td class="show-rejection" style="display:none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="Please choose reason for rejection">
                                                    <option value=''> -- Select -- </option>
                                                    <?php echo $rejectionReason; ?>
                                                </select>
                                            </td>
                                            <th>Rejection Date<span class="mandatory">*</span></th>
                                            <td><input class="form-control date rejection-show" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
                                        </tr>
                                        <tr>
                                            <th><label for="hcv">HCV VL Result</label></th>
                                            <td>
                                                <select class="labSecInput form-control rejected-input" name="hcv" id="hcv">
                                                    <?= $general->generateSelectOptions($hepatitisResults, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th><label for="hbv">HBV VL Result</label></th>
                                            <td>
                                                <select class="labSecInput form-control rejected-input" name="hbv" id="hbv">
                                                    <?= $general->generateSelectOptions($hepatitisResults, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label for="hcvCount">HCV VL Count</label></th>
                                            <td>
                                                <input type="text" class="labSecInput form-control rejected-input" placeholder="Enter HCV Count" title="Please enter HCV Count" name="hcvCount" id="hcvCount">
                                            </td>
                                            <th><label for="hbvCount">HBV VL Count</label></th>
                                            <td>
                                                <input type="text" class="labSecInput form-control rejected-input" placeholder="Enter HBV Count" title="Please enter HBV Count" name="hbvCount" id="hbvCount">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><label for="">Testing Platform </label></td>
                                            <td><select name="hepatitisPlatform" id="hepatitisPlatform" class="labSecInput form-control rejected-input" title="Please select the testing platform">
                                                    <?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <td><label for="">Machine used to test </label></td>
                                            <td><select name="machineName" id="machineName" class="labSecInput form-control rejected-input" title="Please select the machine name" ">
                                                <option value="">-- Select --</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Is Result Authorized ?</th>
                                            <td>
                                                <select name="isResultAuthorized" id="isResultAuthorized" class="labSecInput disabled-field form-control rejected-input" title="Is Result authorized ?" style="width:100%">
                                                    <option value="">-- Select --</option>
                                                    <option value='yes'> Yes </option>
                                                    <option value='no'> No </option>
                                                </select>
                                            </td>
                                            <th>Authorized By</th>
                                            <td><input type="text" name="authorizedBy" id="authorizedBy" class="labSecInput disabled-field form-control rejected-input" placeholder="Authorized By" /></td>
                                        </tr>
                                        <tr>
                                            <th>Authorized on</td>
                                            <td><input type="text" name="authorizedOn" id="authorizedOn" class="labSecInput disabled-field form-control date rejected-input" placeholder="Authorized on" /></td>
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
                        <?php if ($arr['hepatitis_sample_code'] == 'auto' || $arr['hepatitis_sample_code'] == 'YY' || $arr['hepatitis_sample_code'] == 'MMYY') { ?>
                            <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                            <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                            <input type="hidden" name="saveNext" id="saveNext" />
                            <!-- <input type="hidden" name="pageURL" id="pageURL" value="<?php echo $_SERVER['PHP_SELF']; ?>" /> -->
                        <?php } ?>
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                        <a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();$('#saveNext').val('next');return false;">Save and Next</a>
                        <input type="hidden" name="formId" id="formId" value="<?php echo $arr['vl_form']; ?>" />
                        <input type="hidden" name="hepatitisSampleId" id="hepatitisSampleId" value="" />
                        <a href="/hepatitis/requests/hepatitis-requests.php" class="btn btn-default"> Cancel</a>
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

    function comorbidity(obj,id){
        if(obj.value == 'other'){
            $('.show-comorbidity'+id).show();
            $('#comorbidityOther').addClass('isRequired');
        } else{
            $('.show-comorbidity'+id).hide();
            $('#comorbidityOther').removeClass('isRequired');
        }
    }
    
    function riskfactor(obj,id){
        if(obj.value == 'other'){
            $('.show-riskfactor'+id).show();
            $('#riskFactorsOther').addClass('isRequired');
        } else{
            $('.show-riskfactor'+id).hide();
            $('#riskFactorsOther').removeClass('isRequired');
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
                    testType: 'hepatitis'
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
            $("#district").html("<option value=''> -- Select -- </option>");
        }
        $.unblockUI();
    }

    function sampleCodeGeneration() {
        var pName = $("#province").val();
        var sDate = $("#sampleCollectionDate").val();
        if (pName != '' && sDate != '') {
            $.post("/hepatitis/requests/generate-sample-code.php", {
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
                    testType: 'hepatitis'
                },
                function(data) {
                    if (data != "") {
                        details = data.split("###");
                        $("#facilityId").html(details[0]);
                    }
                });
        } else {
            $("#facilityId").html("<option value=''> -- Select -- </option>");
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
                    testType: 'hepatitis'
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
        $('#labId').removeClass('isRequired');
        $('.labSecInput').each(function(){
            if ($(this).val()){
                $('#labId').addClass('isRequired');
            }
        });
        if($('#antiHcv').val() != "" || $('#HBsAg').val() != ""){
            checkresult = true;
        } else{
            checkresult = false;
            alert("Please select least one of the result");
            $('#HBsAg').focus();
        }

        if ($('#isResultAuthorized').val() != "yes") {
            $('#authorizedBy,#authorizedOn').removeClass('isRequired');
        }

        flag = deforayValidator.init({
            formId: 'addHepatitisRequestForm'
        });
        if (flag && checkresult) {
            <?php
            if ($arr['hepatitis_sample_code'] == 'auto' || $arr['hepatitis_sample_code'] == 'YY' || $arr['hepatitis_sample_code'] == 'MMYY') {
            ?>
                insertSampleCode('addHepatitisRequestForm', 'hepatitisSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 3, 'sampleCollectionDate');
            <?php
            } else {
            ?>
                document.getElementById('addHepatitisRequestForm').submit();
            <?php
            } ?>
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

        $('#isResultAuthorized').change(function(e) {
            checkIsResultAuthorized();
        });

        $("#hepatitisPlatform").on("change", function() {
            if (this.value != "") {
                getMachine(this.value);
            }
        });
    });

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
</script>