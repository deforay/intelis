<?php
// imported in eid-add-request.php based on country in global config

use App\Models\Eid;
use App\Utilities\DateUtils;

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

$eidObj = new Eid();
$eidResults = $eidObj->getEidResults();

$specimenTypeResult = $eidObj->getEidSampleTypes();

$rKey = '';
$sKey = '';
$sFormat = '';
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['accessType'] == 'collection-site') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    $rKey = 'R';
} else {
    $sampleCodeKey = 'sample_code_key';
    $sampleCode = 'sample_code';
    $rKey = '';
}
//check user exist in user_facility_map table
$chkUserFcMapQry = "Select user_id from user_facility_map where user_id='" . $_SESSION['userId'] . "'";
$chkUserFcMapResult = $db->query($chkUserFcMapQry);
if ($chkUserFcMapResult) {
    $pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
}
$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
    $province .= "<option data-code='" . $provinceName['geo_code'] . "' data-province-id='" . $provinceName['geo_id'] . "' data-name='" . $provinceName['geo_name'] . "' value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, null, '-- Select --');

?>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> EARLY INFANT DIAGNOSIS (EID) LABORATORY REQUEST FORM</h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
            <li class="active">Add EID Request</li>
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
                <form class="form-horizontal" method="post" name="addEIDRequestForm" id="addEIDRequestForm" autocomplete="off" action="eid-add-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border">
                                    <h3 class="box-title">SITE INFORMATION</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
                                </div>
                                <table class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <?php if ($_SESSION['accessType'] == 'collection-site') { ?>
                                            <td class="labels"><label for="sampleCode">Sample ID </label></td>
                                            <td>
                                                <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
                                                <input type="hidden" id="sampleCode" name="sampleCode" />
                                            </td>
                                        <?php } else { ?>
                                            <td class="labels"><label for="sampleCode">Sample ID </label><span class="mandatory">*</span></td>
                                            <td>
                                                <input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Sample ID" title="Please enter sample id" style="width:100%;" readonly="readonly" onchange="checkSampleNameValidation('form_eid','<?php echo $sampleCode; ?>',this.id,null,'The sample id that you entered already exists. Please try another sample id',null)" />
                                            </td>
                                        <?php } ?>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td class="labels"><label for="province">Health Facility/POE State </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="province" id="province" title="Please choose State" onchange="getfacilityDetails(this);" style="width:100%;">
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <td class="labels"><label for="district">Health Facility/POE County </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired" name="district" id="district" title="Please choose County" style="width:100%;" onchange="getfacilityDistrictwise(this);">
                                                <option value=""> -- Select -- </option>
                                            </select>
                                        </td>
                                        <td class="labels"><label for="facilityId">Health Facility/POE </label><span class="mandatory">*</span></td>
                                        <td>
                                            <select class="form-control isRequired " name="facilityId" id="facilityId" title="Please choose service provider" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
                                                <?php echo $facility; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="labels"><label for="supportPartner">Implementing Partner </label></td>
                                        <td>
                                            <!-- <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire dappui" title="Please enter partenaire dappui" style="width:100%;"/> -->
                                            <select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose partenaire de mise en œuvre" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($implementingPartnerList as $implementingPartner) {
                                                ?>
                                                    <option value="<?php echo ($implementingPartner['i_partner_id']); ?>"><?php echo ($implementingPartner['i_partner_name']); ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <td class="labels"><label for="fundingSource">Funding Partner</label></td>
                                        <td>
                                            <select class="form-control" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($fundingSourceList as $fundingSource) {
                                                ?>
                                                    <option value="<?php echo ($fundingSource['funding_source_id']); ?>"><?php echo ($fundingSource['funding_source_name']); ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>

                                        <td class="labels"><label for="labId">Testing Laboratory <span class="mandatory">*</span></label> </td>
                                        <td>
                                            <select name="labId" id="labId" class="select2 form-control isRequired" title="Please select the Testing Laboratory" style="width:100%;">
                                                <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                            </select>
                                        </td>

                                    </tr>
                                </table>
                                <br>
                                <hr style="border: 1px solid #ccc;">

                                <div class="box-header with-border">
                                    <h3 class="box-title">CHILD and MOTHER INFORMATION</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title">Patient Information</h3>&nbsp;&nbsp;&nbsp;
                                    <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="" placeholder="Enter Infant ID or Infant Name" title="Enter art number or patient name" />&nbsp;&nbsp;
                                    <a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList($('#artPatientNo').val(),0);"><em class="fa-solid fa-magnifying-glass"></em>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No Patient Found</strong></span>
                                </div>
                                <table class="table" aria-hidden="true" style="width:100%">

                                    <tr>
                                        <th style="width:15% !important" class="labels"><label for="childId">Infant Code <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="childId" name="childId" placeholder="Infant Identification (Patient)" title="Please enter Exposed Infant Identification" style="width:100%;" oninput="showPatientList($(this).val(), 1500);" />
                                        </td>
                                        <th style="width:15% !important" class="labels"><label for="childName">Infant name </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="childName" name="childName" placeholder="Infant name" title="Please enter Infant Name" style="width:100%;" onchange="" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="labels"><label for="childDob">Date of Birth </label></th>
                                        <td>
                                            <input type="text" class="form-control" id="childDob" name="childDob" placeholder="Date of birth" title="Please enter Date of birth" style="width:100%;" onchange="calculateAgeInMonths();" />
                                        </td>
                                        <th scope="row" class="labels"><label for="childGender">Gender <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <select class="form-control isRequired" name="childGender" id="childGender">
                                                <option value=''> -- Select -- </option>
                                                <option value='male'> Male </option>
                                                <option value='female'> Female </option>

                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="labels">Infant Age (months) <span class="mandatory">*</span></th>
                                        <td><input type="number" step=".1" max="60" maxlength="4" class="form-control isRequired" id="childAge" name="childAge" placeholder="Age" title="Age" style="width:100%;" onchange="" /></td>
                                        <th scope="row" class="labels">Mother ART Number</th>
                                        <td><input type="text" class="form-control " id="mothersId" name="mothersId" placeholder="Mother ART Number" title="Mother ART Number" style="width:100%;" onchange="" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="labels">Caretaker phone number</th>
                                        <td><input type="text" class="form-control " id="caretakerPhoneNumber" name="caretakerPhoneNumber" placeholder="Caretaker Phone Number" title="Caretaker Phone Number" style="width:100%;" onchange="" /></td>

                                        <th scope="row" class="labels">Infant caretaker address</th>
                                        <td><textarea class="form-control " id="caretakerAddress" name="caretakerAddress" placeholder="Caretaker Address" title="Caretaker Address" style="width:100%;" onchange=""></textarea></td>

                                    </tr>
                                </table>
                                <br><br>
                                <table class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th colspan=4 style="border-top:#ccc 2px solid;">
                                            <h4>Infant and Mother's Health Information</h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important" class="labels">Mother's HIV Status:</th>
                                        <td style="width:35% !important">
                                            <select class="form-control" name="mothersHIVStatus" id="mothersHIVStatus">
                                                <option value=''> -- Select -- </option>
                                                <option value="positive"> Positive </option>
                                                <option value="negative"> Negative </option>
                                                <option value="unknown"> Unknown </option>
                                            </select>
                                        </td>

                                        <th style="width:15% !important" class="labels">ART given to the Mother during:</th>
                                        <td style="width:35% !important">
                                            <input type="checkbox" name="motherTreatment[]" value="No ART given" /> No ART given <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Pregnancy" /> Pregnancy <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Labour/Delivery" /> Labour/Delivery <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Postnatal" /> Postnatal <br>
                                            <!-- <input type="checkbox" name="motherTreatment[]" value="Other" onclick="$('#motherTreatmentOther').prop('disabled', function(i, v) { return !v; });" /> Other (Please specify): <input class="form-control" style="max-width:200px;display:inline;" disabled="disabled" placeholder="Other" type="text" name="motherTreatmentOther" id="motherTreatmentOther" /> <br> -->
                                            <input type="checkbox" name="motherTreatment[]" value="Unknown" /> Unknown
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row" class="labels">Infant Rapid HIV Test Done</th>
                                        <td>
                                            <select class="form-control" name="rapidTestPerformed" id="rapidTestPerformed">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes"> Yes </option>
                                                <option value="no"> No </option>
                                                <option value="unknown"> Unknown </option>
                                            </select>
                                        </td>

                                        <th scope="row" class="labels">If yes, test date :</th>
                                        <td>
                                            <input class="form-control date" type="text" name="rapidtestDate" id="rapidtestDate" placeholder="if yes, test date" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="labels">Rapid Test Result</th>
                                        <td>
                                            <select class="form-control" name="rapidTestResult" id="rapidTestResult">
                                                <option value=''> -- Select -- </option>
                                                <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                    <option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
                                                <?php } ?>

                                            </select>
                                        </td>

                                        <th scope="row" class="labels">Infant still breastfeeding?</th>
                                        <td>
                                            <select class="form-control" name="hasInfantStoppedBreastfeeding" id="hasInfantStoppedBreastfeeding">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes"> Yes </option>
                                                <option value="no"> No </option>
                                                <option value="unknown"> Unknown </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="labels">Age (months) breastfeeding stopped :</th>
                                        <td>
                                            <input type="number" class="form-control" style="max-width:200px;display:inline;" placeholder="Age (months) breastfeeding stopped" type="text" name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" />
                                        </td>

                                        <th scope="row" class="labels">PCR test performed on child before :</th>
                                        <td>
                                            <select class="form-control" name="pcrTestPerformedBefore" id="pcrTestPerformedBefore">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes"> Yes </option>
                                                <option value="no"> No </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="labels">Previous PCR Test Result :</th>
                                        <td>
                                            <select class="form-control" name="prePcrTestResult" id="prePcrTestResult">
                                                <option value=''> -- Select -- </option>
                                                <option value="positive"> Positive </option>
                                                <option value="negative"> Negative </option>
                                                <option value="indeterminate"> Inderterminate </option>
                                            </select>
                                        </td>

                                        <th scope="row" class="labels">Previous PCR test date :</th>
                                        <td>
                                            <input class="form-control date" type="text" name="previousPCRTestDate" id="previousPCRTestDate" placeholder="if yes, test date" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="labels">Reason for Repeat PCR :</th>
                                        <td>
                                            <select class="form-control" name="pcrTestReason" id="pcrTestReason" onchange="checkPCRTestReason();">
                                                <option value=''> -- Select -- </option>
                                                <option value="Confirmation of positive first EID PCR test result"> Confirmation of positive first EID PCR test result </option>
                                                <option value="Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months"> Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months </option>
                                                <option value="Positive HIV rapid test result at 9 months or later"> Positive HIV rapid test result at 9 months or later </option>
                                                <option value="Other"> Other </option>
                                            </select>
                                            <input type="text" name="reasonForRepeatPcrOther" id="reasonForRepeatPcrOther" placeholder="Reason For Repeat PCR" class="form-control reasonForRepeatPcrOther" style="display:none; margin-top:12px;"/>
                                        </td>
                                        <th scope="row"></th>
                                        <td></td>
                                    </tr>
                                </table>

                                <br><br>
                                <table class="table" aria-hidden="true">
                                    <tr>
                                        <th colspan=4 style="border-top:#ccc 2px solid;">
                                            <h4>Sample Information</h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important" class="labels">Sample Collection Date <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control dateTime isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" onchange="sampleCodeGeneration();" />
                                        </td>
                                        <th style="width:15% !important" class="labels">Sample Dispatched On <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control dateTime isRequired" type="text" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="Sample Dispatched On" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th style="width:15% !important" class="labels">Sample Type <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose specimen type" style="width:100%">
                                                <?php echo $general->generateSelectOptions($specimenTypeResult, null, '-- Select --'); ?>
                                            </select>
                                        </td>
                                        <th scope="row" class="labels">Requesting Officer</th>
                                        <td>
                                            <input class="form-control" type="text" name="sampleRequestorName" id="sampleRequestorName" placeholder="Requesting Officer" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" class="labels">Sample Requestor Phone</th>
                                        <td>
                                            <input class="form-control" type="text" name="sampleRequestorPhone" id="sampleRequestorPhone" placeholder="Requesting Officer Phone" />
                                        </td>
                                        <?php if ($usersModel->isAllowed('eid-update-result.php') && $_SESSION['accessType'] != 'collection-site') { ?>
                                            <th scope="row" class="labels">Sample Received Date (at Testing Lab) <span class="mandatory">*</span></th>
                                            <td>
                                                <input type="text" class="form-control dateTime isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter sample received date" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
                                            </td>
                                        <?php } ?>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php if ($usersModel->isAllowed('eid-update-result.php') && $_SESSION['accessType'] != 'collection-site') { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title" class="labels">Reserved for Laboratory Use </h3>
                                    </div>
                                    <table class="table" aria-hidden="true" style="width:100%">
                                        <tr>
                                            <th scope="row" class="labels">Testing Platform </th>
                                            <td><select name="eidPlatform" id="eidPlatform" class="form-control result-optional" title="Please select the testing platform">
                                                    <?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>
                                                </select>
                                            </td>


                                            <th class="labels">Machine used to test </th>
                                            <td><select name="machineName" id="machineName" class="form-control result-optional" title="Please select the machine name">
                                                    <option value="">-- Select --</option>
                                                </select>
                                            </td>
                                        <tr>
                                            <th class="labels">Is Sample Rejected?</th>
                                            <td>
                                                <select class=" form-control" name="isSampleRejected" id="isSampleRejected" title="Please select if the sample is rejected or not">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="yes"> Yes </option>
                                                    <option value="no"> No </option>
                                                </select>
                                            </td>
                                            <th class="rejected labels" style="display: none;">Reason for Rejection</th>
                                            <td class="rejected" style="display: none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="Please select the reason for sample rejection">
                                                    <option value=''> -- Select -- </option>
                                                    <?php echo $rejectionReason; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="show-rejection rejected" style="display:none;">
                                            <td class="labels">Rejection Date<span class="mandatory">*</span></td>
                                            <td><input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <th style="width:25%;" class="labels">Sample Test Date </th>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control dateTime" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _("Please enter date"); ?>" title="Please enter the sample tested date and time" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
                                            </td>
                                            <th scope="row" class="labels">Result</th>
                                            <td>
                                                <select class="form-control" name="result" id="result" title="Please select the test result">
                                                    <option value=''> -- Select -- </option>
                                                    <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                        <option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" class="labels">Results Dispatched Date</th>
                                            <td>
                                                <input type="text" class="form-control dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatch Date" title="Please select result dispatched date" />
                                            </td>
                                            <th scope="row" class="labels">Tested By</th>
                                            <td>
                                                <select name="testedBy" id="testedBy" class="select2 form-control" title="Please choose tested by">
                                                    <?= $general->generateSelectOptions($userInfo, $eidInfo['tested_by'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" class="labels">Reviewed By</th>
                                            <td>
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row" class="labels">Reviewed on</th>
                                            <td><input type="text" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control" placeholder="Reviewed on" title="Please enter reviewed on" /></td>
                                        </tr>
                                        <tr>
                                            <th scope="row" class="labels">Approved By</th>
                                            <td>
                                                <select name="approvedBy" id="approvedBy" class="form-control labSection" title="Please choose approved by">
                                                    <?= $general->generateSelectOptions($userInfo, $eidInfo['result_approved_by'], '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row" class="labels">Approved On </th>
                                            <td style="width:25%;">
                                                <input type="text" value="<?php DateUtils::humanReadableDateFormat(DateUtils::getCurrentDateTime()); ?>" class="form-control dateTime" id="approvedOnDateTime" name="approvedOnDateTime" placeholder="<?= _("Please enter date"); ?>" <?php echo $labFieldDisabled; ?>style="width:100%;" title="Please select approved on" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" class="labels">Lab Tech. Comments </th>
                                            <td style="width:25%;">
                                                <textarea class="form-control" id="labTechCmt" name="labTechCmt" <?php echo $labFieldDisabled; ?> style="width:100%;" placeholder="Comments from the Lab Technician " title="Please Comments from the Lab Technician "></textarea>
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
                        <?php if ($arr['eid_sample_code'] == 'auto' || $arr['eid_sample_code'] == 'YY' || $arr['eid_sample_code'] == 'MMYY') { ?>
                            <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                            <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                            <input type="hidden" name="saveNext" id="saveNext" />
                            <!-- <input type="hidden" name="pageURL" id="pageURL" value="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" /> -->
                        <?php } ?>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();$('#saveNext').val('next');return false;">Save and Next</a>
                        <input type="hidden" name="formId" id="formId" value="1" />
                        <input type="hidden" name="eidSampleId" id="eidSampleId" value="" />
                        <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
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

    function checkPCRTestReason()
    {
        var otherReason = $("#pcrTestReason").val();
        if (otherReason == 'Other') {
            $(".reasonForRepeatPcrOther").show();
            $("#reasonForRepeatPcrOther").addClass("isRequired");
            $("#reasonForRepeatPcrOther").focus();
        } else {
            $(".reasonForRepeatPcrOther").hide();
            $("#reasonForRepeatPcrOther").removeClass("isRequired");
            $('#reasonForRepeatPcrOther').val("");
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

    function setPatientDetails(pDetails) {
        var patientArray = JSON.parse(pDetails);
        $("#childId").val(patientArray['child_id']);
        $("#childName").val(patientArray['name']);
        $("#childDob").val(patientArray['dob']);
        $("#childGender").val(patientArray['gender']);
        $("#childAge").val(patientArray['age']);
        $("#mothersId").val(patientArray['mother_id']);
        $("#caretakerPhoneNumber").val(patientArray['caretaker_no']);
        $("#caretakerAddress").text(patientArray['caretaker_address']);

       /* setTimeout(function() {
            $("#patientDistrict").val(patientArray[15]).trigger('change');
        }, 3000);*/
    }

    function sampleCodeGeneration() {
        var pName = $("#province").val();
        var sDate = $("#sampleCollectionDate").val();
        if (pName != '' && sDate != '') {
            $.post("/eid/requests/generateSampleCode.php", {
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
                    testType: 'eid'
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
                    testType: 'eid'
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
            formId: 'addEIDRequestForm'
        });

        if (flag) {
            // $('.btn-disabled').attr('disabled', 'yes');
            // $(".btn-disabled").prop("onclick", null).off("click");
            //$.blockUI();
            <?php
            if ($arr['eid_sample_code'] == 'auto' || $arr['eid_sample_code'] == 'YY' || $arr['eid_sample_code'] == 'MMYY') {
            ?>
                insertSampleCode('addEIDRequestForm', 'eidSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 3, 'sampleCollectionDate');
            <?php
            } else {
            ?>
                document.getElementById('addEIDRequestForm').submit();
            <?php
            } ?>
        }
    }

    function updateMotherViralLoad() {
        //var motherVl = $("#motherViralLoadCopiesPerMl").val();
        var motherVlText = $("#motherViralLoadText").val();
        if (motherVlText != '') {
            $("#motherViralLoadCopiesPerMl").val('');
        }
    }

    $(document).ready(function() {
        $("#labId,#facilityId,#sampleCollectionDate").on('change', function() {
            if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleDispatchedDate").val() == "") {
                $('#sampleDispatchedDate').datetimepicker("setDate", new Date($('#sampleCollectionDate').datetimepicker('getDate')));
            }
            if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleReceivedDate").val() == "") {
                // $('#sampleReceivedDate').datetimepicker("setDate", new Date($('#sampleCollectionDate').datetimepicker('getDate')));
            }

            if ($("#labId").val() != "") {
                $.post("/includes/get-sample-type.php", {
                        facilityId: $('#labId').val(),
                        testType: 'eid'
                    },
                    function(data) {
                        if (data != "") {
                            $("#specimenType").html(data);
                        }
                    });
            }
        });

        $('#sampleCollectionDate').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            maxDate: "Today",
           // yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>",
			onSelect: function(date) {
				var dt2 = $('#sampleDispatchedDate');
				var startDate = $(this).datetimepicker('getDate');
				var minDate = $(this).datetimepicker('getDate');
				dt2.datetimepicker('setDate', minDate);
				startDate.setDate(startDate.getDate() + 1000000);
				dt2.datetimepicker('option', 'maxDate', "Today");
				dt2.datetimepicker('option', 'minDate', minDate);
				dt2.datetimepicker('option', 'minDateTime', minDate);
				dt2.val($(this).val());
			}
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });
        $('.select2').select2();
        $('#labId').select2({
            width: '100%',
            placeholder: "Select Testing Lab"
        });
        $('#reviewedBy').select2({
            width: '100%',
            placeholder: "Select Reviewed By"
        });
        $('#testedBy').select2({
            width: '100%',
            placeholder: "Select Tested By"
        });

        $('#approvedBy').select2({
            width: '100%',
            placeholder: "Select Approved By"
        });

        $('#district').select2({
            placeholder: "District"
        });
        $('#province').select2({
            placeholder: "Province"
        });
        $('#facilityId').select2({
            placeholder: "Select Clinic/Health Center"
        });
        // $('#district').select2({
        //     placeholder: "District"
        // });
        // $('#province').select2({
        //     placeholder: "Province"
        // });
        $("#motherViralLoadCopiesPerMl").on("change keyup paste", function() {
            var motherVl = $("#motherViralLoadCopiesPerMl").val();
            //var motherVlText = $("#motherViralLoadText").val();
            if (motherVl != '') {
                $("#motherViralLoadText").val('');
            }
        });

        $("#eidPlatform").on("change", function() {
            if (this.value != "") {
                getMachine(this.value);
            }
        });

    });

    function getMachine(value) {
        $.post("/import-configs/get-config-machine-by-config.php", {
                configName: value,
                machine: '',
                testType: 'eid'
            },
            function(data) {
                $('#machineName').html('');
                if (data != "") {
                    $('#machineName').append(data);
                }
            });
    }
</script>