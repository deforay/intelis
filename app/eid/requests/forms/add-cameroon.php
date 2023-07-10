<?php
// imported in eid-add-request.php based on country in global config

use App\Registries\ContainerRegistry;
use App\Services\EidService;



//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);

//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);




// Getting the list of Provinces, Districts and Facilities


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();

$labFieldDisabled = '';
// $rejectionReason = '';
$rKey = '';
$sKey = '';
$sFormat = '';
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    //check user exist in user_facility_map table
    $chkUserFcMapQry = "Select user_id from user_facility_map where user_id='" . $_SESSION['userId'] . "'";
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
$province = "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
    $province .= "<option data-code='" . $provinceName['geo_code'] . "' data-province-id='" . $provinceName['geo_id'] . "' data-name='" . $provinceName['geo_name'] . "' value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, null, '-- Select --');

?>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _("EARLY INFANT DIAGNOSIS (EID) LABORATORY REQUEST FORM"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
            <li class="active"><?php echo _("Add EID Request"); ?></li>
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
                                    <h3 class="box-title"><?= _('HEALTH FACILITY INFORMATION'); ?></h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <td><label for="sampleCode"><?= _('Sample ID'); ?> </label></td>
                                            <td>
                                                <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
                                                <input type="hidden" id="sampleCode" name="sampleCode" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode"><?= _('Sample ID'); ?> </label><span class="mandatory">*</span></td>
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
                                        <td><label for="province"><?= _('Province'); ?> </label><span class="mandatory">*</span><br>
                                            <select class="form-control isRequired" name="province" id="province" title="<?= _('Please choose province'); ?>" onchange="getfacilityDetails(this);" >
                                                <?php echo $province; ?>
                                            </select>
                                        </td>
                                        <td><label for="district"><?= _('District'); ?> </label><span class="mandatory">*</span><br>
                                            <select class="form-control isRequired" name="district" id="district" title="Please choose district" onchange="getfacilityDistrictwise(this);">
                                                <option value=""> -- Select -- </option>
                                            </select>
                                        </td>
                                        <td><label for="facilityId"><?= _('Health Facility'); ?> </label><span class="mandatory">*</span><br>
                                            <select class="form-control isRequired " name="facilityId" id="facilityId" title="Please choose service provider" onchange="getfacilityProvinceDetails(this),fillFacilityDetails();">
                                                <?php echo $facility; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <label for="fCode"><?= _('Clinic/Health Center Code'); ?> </label><br>
                                           <input type="text" class="form-control" style="width:100%;" name="fCode" id="fCode" placeholder="<?= _('Clinic/Health Center Code'); ?>" title="<?= _('Please enter clinic/health center code'); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <!-- <td><label for="supportPartner">Implementing Partner </label></td>
                                        <td>
                                         <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire dappui" title="Please enter partenaire dappui" style="width:100%;"/>
                                            <select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose partenaire de mise en œuvre" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($implementingPartnerList as $implementingPartner) {
                                                ?>
                                                    <option value="<?php echo ($implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
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
                                                    <option value="<?php echo ($fundingSource['funding_source_id']); ?>"><?= $fundingSource['funding_source_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>-->
                                        <?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>
                                            <!-- <tr> -->
                                            <td><label for="labId">Lab Name <span class="mandatory">*</span></label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control isRequired" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <!-- </tr> -->
                                        <?php } ?>
                                    </tr>
                                </table>
                                <br>
                                <hr style="border: 1px solid #ccc;">

                                <div class="box-header with-border">
                                    <h3 class="box-title"><?= _("CHILD'S IDENTIFICATION"); ?></h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">

                                    <tr>
                                        <th scope="row" style="width:15% !important"><label for="childId"><?= _('CRVS file name'); ?> <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired" id="childId" name="childId" placeholder="Infant Identification (Patient)" title="Please enter Exposed Infant Identification" style="width:100%;" onchange="" />
                                            <span class="artNoGroup"></span>
                                        </td>
                                        <th scope="row" style="width:15% !important"><label for="childName"><?= _('Infant name'); ?> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="childName" name="childName" placeholder="Infant name" title="Please enter Infant Name" style="width:100%;" onchange="" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="childDob"><?= _('Date of Birth'); ?> <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <input type="text" class="form-control isRequired" id="childDob" name="childDob" placeholder="Date of birth" title="Please enter Date of birth" style="width:100%;" onchange="calculateAgeInMonths();" />
                                        </td>
                                        <th scope="row"><label for="childGender"><?= _('Gender'); ?> <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <select class="form-control isRequired" name="childGender" id="childGender">
                                                <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value='male'> <?= _('Male'); ?> </option>
                                                <option value='female'> <?= _('Female'); ?> </option>

                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _('Infant Age (months)'); ?></th>
                                        <td><input type="number" max="24" maxlength="2" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="childAge" name="childAge" placeholder="Age" title="Age" style="width:100%;" /></td>
                                        <th scope="row"><?= _('Weight of the day'); ?></th>
                                        <td><input type="text" class="form-control forceNumeric" id="childWeight" name="childWeight" placeholder="<?= _('Infant weight of the day in Kg'); ?>" title="<?= ('Infant weight of the day in Kg'); ?>" style="width:100%;" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _('Caretaker phone number'); ?></th>
                                        <td><input type="text" class="form-control " id="caretakerPhoneNumber" name="caretakerPhoneNumber" placeholder="<?= ('Caretaker Phone Number'); ?>" title="<?= ('Caretaker Phone Number'); ?>" style="width:100%;" /></td>

                                        <th scope="row"><?= _('Infant caretaker address'); ?></th>
                                        <td><textarea class="form-control " id="caretakerAddress" name="caretakerAddress" placeholder="<?= _('Caretaker Address'); ?>" title="<?= ('Caretaker Address'); ?>" style="width:100%;" ></textarea></td>

                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _('Prophylactic ARV given to child'); ?></th>
                                        <td>
                                            <select class="form-control isRequired" name="childProphylacticArv" id="childProphylacticArv" title="<?= _('Prophylactic ARV given to child'); ?>" onchange="showOtherARV();">
                                                <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value='nothing'> <?= _('Nothing'); ?> </option>
                                                <option value='nvp'> <?= _('NVP'); ?> </option>
                                                <option value='azt'> <?= _('AZT'); ?> </option>
                                                <option value='other'> <?= _('Other'); ?> </option>
                                            </select>
                                            <input type="text" name="childProphylacticArvOther" id="childProphylacticArvOther" class="form-control" placeholder="<?= _('Please specify other prophylactic ARV given'); ?>" title="<?= _('Please specify other prophylactic ARV given'); ?>" style="display:none;" />
                                        </td>
                                        <th scope="row"><?= _('Date of Initiation'); ?></th>
                                        <td>
                                            <input type="text" class="form-control date" name="childTreatmentInitiationDate" id="childTreatmentInitiationDate" placeholder="<?= _('Enter date of initiation'); ?>"/>
                                        </td>
                                    </tr>

                                </table>


                                <br><br>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th scope="row" colspan=4 style="border-top:#ccc 2px solid;">
                                            <h4><?= _("MOTHER'S INFORMATION"); ?></h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15% !important"><label for="mothersName"><?= _('Mother name'); ?> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control" id="mothersName" name="mothersName" placeholder="<?= _('Mother name'); ?>" title="<?= _('Please enter Infant Name'); ?>" style="width:100%;" onchange="" />
                                        </td>
                                        <th scope="row"><label for="dob"><?= _('Date of Birth'); ?> <span class="mandatory">*</span> </label></th>
                                        <td>
                                            <input type="text" class="form-control isRequired" id="mothersDob" name="mothersDob" placeholder="<?= _('Date of birth'); ?>" title="<?= _('Please enter Date of birth'); ?>" style="width:100%;" onchange="calculateAgeInMonths();" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:18% !important"><?= _('Date of next appointment'); ?> </th>
                                        <td>
                                            <input class="form-control date" type="text" name="nextAppointmentDate" id="nextAppointmentDate" placeholder="<?= _('Please enter date of next appointment'); ?>" />
                                        </td>
                                        <th scope="row" style="width:18% !important"><?= _('Mode of Delivery'); ?> </th>
                                        <td>
                                            <select class="form-control" name="modeOfDelivery" id="modeOfDelivery" onchange="showOtherOption(this.value)">
                                                <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="Normal"> <?= _('Normal'); ?> </option>
                                                <option value="Caesarean"> <?= _('Caesarean'); ?> </option>
                                                <option value="Unknown"> <?= _('Gravidity N*'); ?>' </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:18% !important"><?= _('Number of exposed children'); ?> </th>
                                        <td>
                                            <input class="form-control forceNumeric" type="text" name="noOfExposedChildren" id="noOfExposedChildren" placeholder="<?= _('Please enter number of exposed children'); ?>" />
                                        </td>
                                        <th scope="row" style="width:18% !important"><?= _('Number of infected children'); ?> </th>
                                        <td>
                                            <input class="form-control forceNumeric" type="text" name="noOfInfectedChildren" id="noOfInfectedChildren" placeholder="<?= _('Please enter number of infected children'); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:18% !important"><?= _('ARV protocol followed by mother'); ?> </th>
                                        <td>
                                            <select class="form-control" name="motherArvProtocol" id="motherArvProtocol" onchange="showArvProtocolOtherOption()">
                                                <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="Nothing"> <?= _('Nothing'); ?> </option>
                                                <option value="TELE (TDF+TC+EFV)"><?= _('TELE (TDF+TC+EFV)'); ?> </option>
                                                <option value="other"><?= _('Other'); ?></option>
                                            </select>
                                            <input type="text" class="form-control" name="motherArvProtocolOther" id="motherArvProtocolOther" style="display:none;"/>

                                      </td>
                                        <th scope="row"><?= _('Date of Initiation'); ?></th>
                                        <td>
                                            <input type="text" class="form-control date" name="motherTreatmentInitiationDate" id="motherTreatmentInitiationDate"  placeholder="<?= _('Enter date of initiation'); ?>"/>
                                        </td>
                                    </tr>
                                </table>
                                    <br>
                                <hr style="border: 1px solid #ccc;">

                                <div class="box-header with-border">
                                    <h3 class="box-title"><?= _("CLINICAL INFORMATION"); ?></h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                <tr>
                                        <th scope="row" style="width:16% !important"><?= _('Is the child symptomatic?'); ?> <span class="mandatory">*</span></th>
                                        <td style="width:30% !important">
                                            <select class="form-control isRequired" name="isChildSymptomatic" id="isChildSymptomatic">
                                            <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="yes"> <?= _('Yes'); ?> </option>
                                                <option value="no"> <?= _('No'); ?> </option>
                                            </select>
                                        </td>
                                        <th scope="row" style="width:16% !important"><?= _('Date of Weaning?'); ?> </th>
                                        <td style="width:30% !important">
                                            <input type="text" class="form-control date" name="dateOfWeaning" id="dateOfWeaning" title="<?= _('Enter date of weaning'); ?>" placeholder="<?= _('Enter date of weaning'); ?>" />
                                        </td>
                                </tr>
                                <tr>
                                <th scope="row" style="width:16% !important"><?= _('Was the child breastfed?'); ?> </th>
                                        <td style="width:30% !important">
                                            <select class="form-control" name="wasChildBreastfed" id="wasChildBreastfed">
                                                <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="yes"> <?= _('Yes'); ?> </option>
                                                <option value="no"> <?= _('No'); ?> </option>
                                                <option value="unknown"> <?= _('Unknown'); ?> </option>
                                            </select>
                                        </td>
                                <th scope="row" style="width:16% !important"><?= _('If Yes,'); ?> </th>
                                        <td style="width:30% !important">
                                            <select class="form-control" name="choiceOfFeeding" id="choiceOfFeeding">
                                                <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="Exclusive"><?= _('Exclusive'); ?></option>
                                                <option value="Mixed"><?= _('Mixed'); ?></option>
                                                <option value="Exclusive formula feeding"><?= _('Exclusive formula feeding'); ?></option>
                                            </select>
                                        </td>
                                </tr>
                                <tr>
                                <th scope="row" style="width:16% !important"><?= _('Is the child on Cotrim?'); ?> </th>
                                        <td style="width:30% !important">
                                            <select class="form-control" name="isChildOnCotrim" id="isChildOnCotrim">
                                            <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="yes"> <?= _('Yes'); ?> </option>
                                                <option value="no"> <?= _('No'); ?> </option>
                                            </select>
                                        </td>
                                <th scope="row" style="width:16% !important"><?= _('If Yes, Date of Initiation'); ?> </th>
                                        <td style="width:30% !important">
                                        <input type="text" class="form-control date" name="childStartedCotrimDate" id="childStartedCotrimDate" title="<?= _('Enter date of Initiation'); ?>" placeholder="<?= _('Enter date of Initiation'); ?>" />

                                        </td>
                                </tr>
                                <tr>
                                <th scope="row" style="width:16% !important"><?= _('Is the child on ART?'); ?> </th>
                                        <td style="width:30% !important">
                                            <select class="form-control" name="infantArtStatus" id="infantArtStatus">
                                            <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="yes"> <?= _('Yes'); ?> </option>
                                                <option value="no"> <?= _('No'); ?> </option>
                                            </select>
                                        </td>
                                <th scope="row" style="width:16% !important"><?= _('If Yes, Date of Initiation'); ?> </th>
                                        <td style="width:30% !important">
                                        <input type="text" class="form-control date" name="childStartedArtDate" id="childStartedArtDate" title="<?= _('Enter date of Initiation'); ?>" placeholder="<?= _('Enter date of Initiation'); ?>" />

                                        </td>
                                </tr>
                                    <tr>
                                        <th scope="row"><?= _('Stopped breastfeeding ?'); ?></th>
                                        <td>
                                            <select class="form-control" name="hasInfantStoppedBreastfeeding" id="hasInfantStoppedBreastfeeding">
                                            <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="yes"> <?= _('Yes'); ?> </option>
                                                <option value="no"> <?= _('No'); ?> </option>
                                                <option value="unknown"> <?= _('Unknown'); ?> </option>
                                            </select>
                                        </td>
                                        <th scope="row"><?= _('Age (months) breastfeeding stopped'); ?> </th>
                                        <td>
                                            <input type="number" class="form-control" style="max-width:200px;display:inline;" placeholder="Age (months) breastfeeding stopped" type="text" name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" />
                                        </td>
                                    </tr>

                                    <tr>

                                        <th scope="row"><?= _('Previous PCR test'); ?> </th>
                                        <td>
                                            <select class="form-control" title="Please select if Previous PCR Test was done" name="pcrTestPerformedBefore" id="pcrTestPerformedBefore" onchange="setRelatedField(this.value);">
                                                <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="yes"> <?= _('Yes'); ?> </option>
                                                <option value="no"> <?= _('No'); ?> </option>
                                            </select>
                                        </td>
                                        <th scope="row"><?= _('Previous PCR test date'); ?></th>
                                        <td>
                                            <input class="form-control date" type="text" name="previousPCRTestDate" id="previousPCRTestDate" placeholder="if yes, test date" />
                                        </td>
                                    </tr>

                                    <tr>

                                    <th scope="row"><?= _('Previous PCR test Result'); ?></th>
                                        <td>
                                            <select class="form-control" name="prePcrTestResult" id="prePcrTestResult">
                                                <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="Detected"> <?= _('Detected'); ?> </option>
                                                <option value="Not Detected"> <?= _('Not Detected'); ?> </option>
                                            </select>
                                        </td>
                                        <th scope="row"><?= _('Reason for 2nd PCR'); ?></th>
                                        <td>
                                            <select class="form-control" name="pcrTestReason" id="pcrTestReason">
                                                <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="Confirmation of positive first EID PCR test result"> <?= _('Confirmation of positive first EID PCR test result'); ?> </option>
                                                <option value="Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months"> Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months </option>
                                                <option value="Positive HIV rapid test result at 9 months or later"> Positive HIV rapid test result at 9 months or later </option>
                                                <option value="Other"> Other </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _('Reason for Sample Collection'); ?></th>
                                        <td>
                                            <select class="form-control" name="sampleCollectionReason" id="sampleCollectionReason">
                                                <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="1st Test for well child born of HIV+ mother"><?= _('1st Test for well child born of HIV+ mother'); ?></option>
                                                <option value="1st Test for sick child"><?= _('1st Test for sick child'); ?></option>
                                                <option value="Repeat Testing for 6 weeks after weaning"><?= _('Repeat Testing for 6 weeks after weaning'); ?></option>
                                                <option value="Repeat Testing due to loss of 1st sample"><?= _('Repeat Testing due to loss of 1st sample'); ?></option>
                                                <option value="Repeat due to clinical suspicion following negative 1st test"><?= _('Repeat due to clinical suspicion following negative 1st test');?></option>
                                            </select>
                                        </td>
                                        <th scope="row"><?= _('Point of Entry'); ?></th>
                                        <td>
                                            <select class="form-control" name="labTestingPoint" id="labTestingPoint" onchange="showTestingPointOther();">
                                                <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="PMTCT(PT)"><?= _('PMTCT(PT)'); ?></option>
                                                <option value="IWC(IC)"> <?= _('IWC(IC)'); ?> </option>
                                                <option value="Hospitalization (HO)"> <?= _('Hospitalization (HO)'); ?>' </option>
                                                <option value="Consultation (CS)"> <?= _('Consultation (CS)'); ?> </option>
                                                <option value="EPI(PE)"> <?= _('EPI(PE)'); ?> </option>
                                                <option value="other"><?= _('Other'); ?></option>
                                            </select>
                                            <input type="text" name="labTestingPointOther" id="labTestingPointOther" class="form-control" title="<?= _('Please specify other point of entry') ?>" placeholder="<?= _('Please specify other point of entry') ?>" style="display:none;"/>
                                        </td>
                                    </tr>
                                </table>

                                <br><br>
                                <table aria-describedby="table" class="table" aria-hidden="true">
                                    <tr>
                                        <th scope="row" colspan=4 style="border-top:#ccc 2px solid;">
                                            <h4><?= _('QUALITY SAMPLE INFORMATION'); ?></h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15% !important"><?= _('Sample Collection Date'); ?> <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control dateTime isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="<?= _('Sample Collection Date'); ?>" onchange="generateSampleCode();" />
                                        </td>
                                        <th style="width:15% !important;"></th>
                                        <td style="width:35% !important;"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?= _('Name of health personnel'); ?></th>
                                        <td>
                                            <input class="form-control" type="text" name="sampleRequestorName" id="sampleRequestorName" placeholder="Requesting Officer" />
                                        </td>
                                        <th scope="row"><?= _('Contact Number'); ?></th>
                                        <td>
                                            <input class="form-control forceNumeric" type="text" name="sampleRequestorPhone" id="sampleRequestorPhone" placeholder="Requesting Officer Phone" />
                                        </td>
                                    </tr>
                                </table>

                            </div>
                        </div>
                        <?php if ($usersService->isAllowed('/eid/results/eid-update-result.php') && $_SESSION['accessType'] != 'collection-site') { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title"><?= _('Reserved for Laboratory Use'); ?> </h3>
                                    </div>
                                    <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                        <tr>
                                            <th scope="row"><label for=""><?= _('Sample Received Date'); ?> </label></th>
                                            <td>
                                                <input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter date de réception de léchantillon" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
                                            </td>
                                            <td><label for="labId"><?= _('Lab Name'); ?> </label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        <tr>
                                            <th scope="row"><?= _('Is Sample Rejected?'); ?></th>
                                            <td>
                                                <select class="form-control" name="isSampleRejected" id="isSampleRejected">
                                                <option value=''> <?= _('-- Select --'); ?> </option>
                                                <option value="yes"> <?= _('Yes'); ?> </option>
                                                <option value="no"> <?= _('No'); ?> </option>
                                                </select>
                                            </td>

                                            <th scope="row" class="rejected" style="display: none;"><?= _('Reason for Rejection'); ?></th>
                                            <td class="rejected" style="display: none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="<?= _('Please choose reason for rejection'); ?>">
                                                    <option value=''> <?= _('-- Select --'); ?> </option>
                                                    <?php echo $rejectionReason; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="show-rejection rejected" style="display:none;">
                                            <td><label for="rejectionDate"><?= _('Rejection Date'); ?></label><span class="mandatory">*</span></td>
                                            <td><input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="<?= _('Select Rejection Date'); ?>" /></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td style="width:25%;"><label for=""><?= _('Sample Test Date'); ?> </label></td>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control dateTime" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _("Please enter date"); ?>" title="<?= _("Please enter date"); ?>" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
                                            </td>


                                            <th scope="row"><?= _('Result'); ?></th>
                                            <td>
                                                <select class="form-control" name="result" id="result">
                                                    <option value=''> <?= _('-- Select --'); ?> </option>
                                                    <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                        <option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?= _('Reviewed On'); ?></th>
                                            <td><input type="text" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control" placeholder="Reviewed on" title="<?= _('Please enter the Reviewed on'); ?>" /></td>
                                            <th scope="row"><?= _('Reviewed By'); ?></th>
                                            <td>
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?= _('Approved On'); ?></th>
                                            <td><input type="text" name="approvedOnDateTime" id="approvedOnDateTime" class="dateTime disabled-field form-control" placeholder="<?= _('Approved on'); ?>" title="<?= _('Please enter the Approved on'); ?>" /></td>
                                            <th scope="row"><?= _('Approved By'); ?></th>
                                            <td>
                                                <select name="approvedBy" id="approvedBy" class="select2 form-control" title="<?= _('Please choose approved by'); ?>" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
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
                        <?php if ($arr['eid_sample_code'] == 'auto' || $arr['eid_sample_code'] == 'YY' || $arr['eid_sample_code'] == 'MMYY') { ?>
                            <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                            <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                            <input type="hidden" name="saveNext" id="saveNext" />
                            <!-- <input type="hidden" name="pageURL" id="pageURL" value="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" /> -->
                        <?php } ?>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();$('#saveNext').val('next');return false;">Save and Next</a>
                        <input type="hidden" name="formId" id="formId" value="7" />
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
    function checkPatientDetails(tableName, fieldName, obj, fnct) {
        //if ($.trim(obj.value).length == 10) {
        if ($.trim(obj.value) != '') {
            $.post("/includes/checkDuplicate.php", {
                tableName: tableName,
                fieldName: fieldName,
                value: obj.value,
                fnct: fnct,
                format: "html"
            },
                function (data) {
                    if (data === '1') {
                        showModal('patientModal.php?artNo=' + obj.value, 900, 520);
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
            generateSampleCode();
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

    function generateSampleCode() {
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
            $('.btn-disabled').attr('disabled', 'yes');
            $(".btn-disabled").prop("onclick", null).off("click");
            $.blockUI();
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

    function setRelatedField(pcrVal) {
        if (pcrVal == 'yes') {
            $('#previousPCRTestDate').addClass('isRequired');
            $('#pcrTestReason').addClass('isRequired');
            $('#previousPCRTestDate').prop('disabled', false);
            $('#pcrTestReason').prop('disabled', false);
        } else {
            $('#previousPCRTestDate').prop('disabled', true);
            $('#pcrTestReason').prop('disabled', true);
            $('#previousPCRTestDate').removeClass('isRequired');
            $('#pcrTestReason').removeClass('isRequired');
        }
    }

    function fillFacilityDetails() {
          $.blockUI();
          //check facility name

          $.unblockUI();
          $("#fCode").val($('#facilityId').find(':selected').data('code'));

     }
function showOtherARV()
{
    arv = $("#childProphylacticArv").val();
    if(arv=="other")
    {
        $("#childProphylacticArvOther").show();
        $("#childProphylacticArvOther").addClass('isRequired');
    }
    else
    {
        $("#childProphylacticArvOther").removeClass('isRequired');
        $("#childProphylacticArvOther").hide();
    }
}

function showArvProtocolOtherOption()
{
    arvMother = $("#motherArvProtocol").val();
    if(arvMother=="other")
    {
        $("#motherArvProtocolOther").show();
        $("#motherArvProtocolOther").addClass('isRequired');
    }
    else
    {
        $("#motherArvProtocolOther").removeClass('isRequired');
        $("#motherArvProtocolOther").hide();
    }
}

function showTestingPointOther()
{
    entryPoint = $("#labTestingPoint").val();
    if(entryPoint=="other")
    {
        $("#labTestingPointOther").show();
        $("#labTestingPointOther").addClass('isRequired');
    }
    else
    {
        $("#labTestingPointOther").removeClass('isRequired');
        $("#labTestingPointOther").hide();
    }
}

    $(document).ready(function() {
        autoSelectSingleOption('facilityId');
        $("#childId").on('input', function() {
            $.post("/common/patient-last-request-details.php", {
                    patientId: $.trim($(this).val()),
                    testType: 'eid'
                },
                function(data) {
                    if (data != "0") {
                        obj = $.parseJSON(data);
                        $(".artNoGroup").html('<small style="color:red">No. of times Test Requested for this Patient : ' + obj.no_of_req_time +
                            '<br>Last Test Request Added On VLSM : ' + obj.request_created_datetime +
                            '<br>Sample Collection Date for Last Request : ' + obj.sample_collection_date +
                            '<br>Total No. of times Patient tested for EID : ' + obj.no_of_tested_time +
                            '</small>');
                    } else {
                        $(".artNoGroup").html('');
                    }
                });

        });
        $('#facilityId').select2({
            placeholder: "Select Clinic/Health Center"
        });
        $('#labId').select2({
            placeholder: "Select Lab Name"
        });
        $('#reviewedBy').select2({
            placeholder: "Select Reviewed By"
        });
        $('#approvedBy').select2({
            placeholder: "Select Approved By"
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


    });
</script>
