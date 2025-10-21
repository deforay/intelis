<?php
// imported in eid-add-request.php based on country in global config

use App\Registries\ContainerRegistry;
use App\Services\EidService;



// Getting the list of Provinces, Districts and Facilities


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();


$rKey = '';
$sKey = '';
$sFormat = '';
if ($general->isSTSInstance()) {
    $sampleCodeKey = 'remote_sample_code_key';
    $sampleCode = 'remote_sample_code';
    $rKey = 'R';
} else {
    $sampleCodeKey = 'sample_code_key';
    $sampleCode = 'sample_code';
    $rKey = '';
}
$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);
$facility = $general->generateSelectOptions($healthFacilities, null, '-- Select --');

?>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _translate("EARLY INFANT DIAGNOSIS (EID) LABORATORY REQUEST FORM"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li class="active"><?php echo _translate("Add EID Request"); ?></li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">

        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate("indicates required fields"); ?> &nbsp;</div>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->
                <form class="form-horizontal" method="post" name="addEIDRequestForm" id="addEIDRequestForm" autocomplete="off" action="eid-add-request-helper.php">
                    <div class="box-body">
                        <div class="box box-default">
                            <div class="box-body">
                                <div class="box-header with-border">
                                    <h3 class="box-title">Section 1: Clinic Information</h3>
                                </div>
                                <div class="box-header with-border">
                                    <h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <?php if ($general->isSTSInstance()) { ?>
                                            <td><label for="sampleCode">Sample ID </label></td>
                                            <td>
                                                <span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"></span>
                                                <input type="hidden" id="sampleCode" name="sampleCode" />
                                            </td>
                                        <?php } else { ?>
                                            <td><label for="sampleCode">Sample ID </label><span class="mandatory">*</span></td>
                                            <td>
                                                <input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Sample ID" title="<?= _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>" style="width:100%;" />
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
                                            <select class="form-control isRequired " name="facilityId" id="facilityId" title="Please choose facility" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
                                                <?php echo $facility; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><label for="supportPartner">Implementing Partner </label></td>
                                        <td>
                                            <!-- <input type="text" class="form-control" id="supportPartner" name="supportPartner" placeholder="Partenaire d'appui" title="Please enter Partenaire d'appui" style="width:100%;"/> -->
                                            <select class="form-control" name="implementingPartner" id="implementingPartner" title="<?= _translate("Please choose implementing partner"); ?>" style="width:100%;">
                                                <option value=""> -- Select -- </option>
                                                <?php
                                                foreach ($implementingPartnerList as $implementingPartner) {
                                                ?>
                                                    <option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
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
                                                    <option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>"><?= $fundingSource['funding_source_name']; ?></option>
                                                <?php } ?>
                                            </select>
                                        </td>
                                        <?php if ($general->isSTSInstance()) { ?>
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
                                    <tr>
                                        <th scope="row"><?= _translate('Requesting Clinician Name'); ?></th>
                                        <td> <input type="text" class="form-control" id="clinicianName" name="clinicianName" placeholder="Requesting Clinician Name" title="Please enter request clinician" value="<?php echo $eidInfo['clinician_name']; ?>" /></td>
                                        <th scope="row">Requesting Officer</th>
                                        <td>
                                            <input class="form-control" type="text" name="sampleRequestorName" id="sampleRequestorName" placeholder="Requesting Officer" />
                                        </td>
                                        <th scope="row">Sample Requestor Phone</th>
                                        <td>
                                            <input class="form-control" type="text" name="sampleRequestorPhone" id="sampleRequestorPhone" placeholder="Requesting Officer Phone" />
                                        </td>
                                    </tr>
                                </table>
                                <br><br>
                                <div class="box-header with-border">
                                    <h3 class="box-title">Section 2: Mother/Guardian Information</h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th scope="row" style="width:15% !important"><label for="mothersName">Mother's Name </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="mothersName" name="mothersName" placeholder="Mother name" title="Please enter Mother Name" style="width:100%;" onchange="" />
                                        </td>
                                        <th scope="row" style="width:15% !important"><label for="mothersSurname">Mother's Surname </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="mothersSurname" name="mothersSurname" placeholder="Mother Surname" title="Please enter Mother Surname" style="width:100%;" onchange="" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Caretaker phone number</th>
                                        <td><input type="text" class="form-control phone-number" id="caretakerPhoneNumber" name="caretakerPhoneNumber" placeholder="Caretaker Phone Number" title="Caretaker Phone Number" style="width:100%;" onchange="" /></td>

                                        <th scope="row">Infant caretaker address</th>
                                        <td><textarea class="form-control " id="caretakerAddress" name="caretakerAddress" placeholder="Caretaker Address" title="Caretaker Address" style="width:100%;" onchange=""></textarea></td>

                                    </tr>
                                </table>
                                <br><br>


                                <div class="box-header with-border">
                                    <h3 class="box-title">Section 3: Infant Information</h3>
                                </div>

                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr class="encryptPIIContainer">
                                        <th scope="row" style="width:15% !important"><label for="encryptPII"><?= _translate('Encrypt PII'); ?> </label></th>
                                        <td>
                                            <select name="encryptPII" id="encryptPII" class="form-control" title="<?= _translate('Encrypt Patient Identifying Information'); ?>">
                                                <option value=""><?= _translate('--Select--'); ?></option>
                                                <option value="no" selected='selected'><?= _translate('No'); ?></option>
                                                <option value="yes"><?= _translate('Yes'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15% !important"><label for="childId">Exposed Infant Identification <span class="mandatory">*</span> </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control isRequired patientId" id="childId" name="childId" placeholder="Exposed Infant Identification (Patient)" title="Please enter Exposed Infant Identification" style="width:100%;" onchange="" />
                                        </td>
                                        <th scope="row" style="width:15% !important"><label for="childName">Infant name </label></th>
                                        <td style="width:35% !important">
                                            <input type="text" class="form-control " id="childName" name="childName" placeholder="Infant name" title="Please enter Infant Name" style="width:100%;" onchange="" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="childDob">Date of Birth </label></th>
                                        <td>
                                            <input type="text" class="form-control date" id="childDob" name="childDob" placeholder="Date of birth" title="Please enter Date of birth" style="width:100%;" onchange="" />
                                        </td>
                                        <th scope="row"><label for="childGender"><?= _translate("Sex"); ?> </label></th>
                                        <td>
                                            <select class="form-control " name="childGender" id="childGender">
                                                <option value=''> -- Select -- </option>
                                                <option value='male'> Male </option>
                                                <option value='female'> Female </option>

                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Infant Age (months)</th>
                                        <td><input type="number" max=9 maxlength="1" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="childAge" name="childAge" placeholder="Age" title="Age" style="width:100%;" onchange="" /></td>

                                    </tr>
                                </table>
                                <br><br>
                                <div class="box-header with-border">
                                    <h3 class="box-title">Section 4: Reason For Test</h3>
                                </div>`
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th scope="row">Provide Recent previous Lab Number</th>
                                        <td>
                                            <input type="text" class="form-control " id="previousSampleCode" name="previousSampleCode" placeholder="Recent previous Lab Number" title="Recent previous Lab Number" style="width:100%;" onchange="" />
                                        </td>
                                        <th scope="row">Clinical Assessment</th>
                                        <td>
                                            <select class="form-control " name="clinicalAssessment" id="clinicalAssessment">
                                                <option value=''> -- Select -- </option>
                                                <option value='symptomatic'> Symptomatic </option>
                                                <option value='non-symptomatic'> Non-Symptomatic </option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Name of EID Personnel Collecting DBS</th>
                                        <td>
                                            <input type="text" class="form-control" id="clinicianName" name="clinicianName" placeholder="Name of EID Personnel Collecting DBS" title="Name of EID Personnel Collecting DBS" style="width:100%;" onchange="" />
                                        </td>
                                        <th scope="row" style="width:15% !important">Sample Collection Date <span class="mandatory">*</span> </th>
                                        <td style="width:35% !important;">
                                            <input class="form-control dateTime isRequired" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" onchange="generateSampleCode(); checkCollectionDate(this.value);" />
                                            <span class="expiredCollectionDate" style="color:red; display:none;"></span>
                                        </td>
                                    </tr>

                                </table>



                                <br><br>
                                <div class="box-header with-border">
                                    <h3 class="box-title"> Section 5: Mother PPTCT Information</h3>
                                </div>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">

                                    <tr>
                                        <th scope="row" style="width:15% !important">Mother's HIV Status:</th>
                                        <td style="width:35% !important">
                                            <select class="form-control" name="mothersHIVStatus" id="mothersHIVStatus">
                                                <option value=''> -- Select -- </option>
                                                <option value="positive"> Positive </option>
                                                <option value="negative"> Negative </option>
                                                <option value="unknown"> Unknown </option>
                                            </select>
                                        </td>
                                        <th scope="row">Mother ART Number</th>
                                        <td><input type="text" class="form-control " id="mothersId" name="mothersId" placeholder="Mother ART Number" title="Mother ART Number" style="width:100%;" onchange="" /></td>

                                    </tr>
                                    <tr>
                                        <th scope="row" style="width:15% !important">ART given to the Mother during:</th>
                                        <td style="width:35% !important">
                                            <input type="checkbox" name="motherTreatment[]" value="No ART given" /> No ART given <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Pregnancy" /> Pregnancy <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Labour/Delivery" /> Labour/Delivery <br>
                                            <input type="checkbox" name="motherTreatment[]" value="Postnatal" /> Postnatal <br>
                                            <!-- <input type="checkbox" name="motherTreatment[]" value="Other" onclick="$('#motherTreatmentOther').prop('disabled', function(i, v) { return !v; });" /> Other (Please specify): <input class="form-control" style="max-width:200px;display:inline;" disabled="disabled" placeholder="Other" type="text" name="motherTreatmentOther" id="motherTreatmentOther" /> <br> -->
                                            <input type="checkbox" name="motherTreatment[]" value="Unknown" /> Unknown
                                        </td>
                                        <th scope="row" style="width:15% !important">Mode of Delivery:</th>
                                        <td style="width:35% !important">
                                            <select class="form-control" name="modeOfDelivery" id="modeOfDelivery" onchange="showOtherOption(this.value)">
                                                <option value=''> -- Select -- </option>
                                                <option value="Supervised Vaginal"> Supervised Vaginal </option>
                                                <option value="Unsupervised Vaginal"> Unsupervised Vaginal </option>
                                                <option value="Caesarean Section"> Caesarean Section </option>
                                                <option value="Unknown"> Unknown </option>
                                                <option value="Other"> Other </option>
                                            </select>
                                            <input type="text" class="form-control" name="modeOfDeliveryOther" id="modeOfDeliveryOther" title="Enter Other mode of Delivery" placeholder="Enter Other mode of Delivery" style="display:none;" />
                                        </td>

                                    <tr>
                                        <th scope="row"> ART Status </th>
                                        <td>
                                            <select class="form-control" name="motherArtStatus" id="motherArtStatus">
                                                <option value="">--Select--</option>
                                                <option value="PLWHIV on ART">PLWHIV on ART </option>
                                                <option value="ART during pregnancy"> ART during pregnancy</option>
                                                <option value="Yes">Yes</option>
                                                <option value="No">No</option>
                                                <option value="Booked">Booked</option>
                                                <option value="Unbooked">Unbooked</option>
                                            </select>
                                        </td>
                                        <th scope="row"> ART Regimen </th>
                                        <td>
                                            <input type="text" class="form-control" name="motherRegimen" id="motherRegimen" title="Enter ART Regimen" placeholder="Enter ART Regimen" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Date started ART</th>
                                        <td> <input type="text" class="form-control date" name="startedArtDate" id="startedArtDate" title="Enter ART Started Date" placeholder="Enter ART Started Date" />
                                        </td>
                                        <th scope="row">MTCT Risk</th>
                                        <td>
                                            <select class="form-control" name="motherMtctRisk" id="motherMtctRisk">
                                                <option value="">--Select--</option>
                                                <optgroup label="High Risk">
                                                    <option value="< 4weeks on ART prior to delivery">
                                                        < 4weeks on ART prior to delivery </option>
                                                    <option value="VL > 1000 4 weeks prior to delivery"> VL > 1000 4 weeks prior to delivery </option>
                                                </optgroup>
                                                <optgroup label="Low Risk">
                                                    <option value="> 4weeks on ART prior to delivery">
                                                        < 4weeks on ART prior to delivery </option>
                                                    <option value="VL < 1000 4 weeks prior to delivery"> VL > 1000 4 weeks prior to delivery </option>
                                                </optgroup>
                                            </select>
                                        </td>

                                    </tr>
                                </table>

                                <br><br>

                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th scope="row" colspan=4>
                                            <h4>Section 6: Infant PPTCT Information</h4>
                                        </th>
                                    </tr>

                                    <tr>

                                        <th scope="row">Infant stopped breastfeeding ?</th>
                                        <td>
                                            <select class="form-control" name="hasInfantStoppedBreastfeeding" id="hasInfantStoppedBreastfeeding">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes"> Yes </option>
                                                <option value="no"> No </option>
                                                <option value="unknown"> Unknown </option>
                                            </select>
                                        </td>
                                        <th scope="row">Age (months) breastfeeding stopped :</th>
                                        <td>
                                            <input type="number" class="form-control" style="max-width:200px;display:inline;" placeholder="Age (months) breastfeeding stopped" type="text" name="ageBreastfeedingStopped" id="ageBreastfeedingStopped" />
                                        </td>

                                    </tr>
                                    <tr>
                                        <th scope="row">ART Status</th>
                                        <td>
                                            <select class="form-control" name="infantArtStatus" id="infantArtStatus" onchange="showOtherArt(this.value);">
                                                <option value="">--Select--</option>

                                                <optgroup label="High Risk of MTCT">
                                                    <option value="AZT + NVP first 6 weeks of life">AZT + NVP first 6 weeks of life</option>
                                                    <option value="NVP only - additional 6 weeks (Total 12 weeks)">NVP only - additional 6 weeks (Total 12 weeks)</option>
                                                </optgroup>
                                                <optgroup label="Low Risk">
                                                    <option value="NVP only for the first 6 weeks of life">NVP only for the first 6 weeks of life</option>
                                                </optgroup>
                                                <option value="Other ART">Other ART</option>
                                            </select>
                                            <input type="text" name="infantArtStatusOther" id="infantArtStatusOther" placeholder="Enter Other ART Regimen" class="form-control" style="display:none;" />
                                        </td>
                                    </tr>
                                </table>
                                <br><br>
                                <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                    <tr>
                                        <th scope="row" colspan=4>
                                            <h4>Section 7: Infant Testing History (Provide information for MOST recent test)</h4>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="row">Infant Rapid HIV Test Done</th>
                                        <td>
                                            <select class="form-control" name="rapidTestPerformed" id="rapidTestPerformed">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes"> Yes </option>
                                                <option value="no"> No </option>
                                            </select>
                                        </td>

                                        <th scope="row">If yes, test date :</th>
                                        <td>
                                            <input class="form-control date" type="text" name="rapidtestDate" id="rapidtestDate" placeholder="if yes, test date" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Rapid Test Result</th>
                                        <td>
                                            <select class="form-control" name="rapidTestResult" id="rapidTestResult">
                                                <option value=''> -- Select -- </option>
                                                <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                    <option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
                                                <?php } ?>

                                            </select>
                                        </td>
                                        <th scope="row">PCR test performed on child before :</th>
                                        <td>
                                            <select class="form-control" name="pcrTestPerformedBefore" id="pcrTestPerformedBefore">
                                                <option value=''> -- Select -- </option>
                                                <option value="yes"> Yes </option>
                                                <option value="no"> No </option>
                                            </select>
                                        </td>


                                    </tr>
                                    <tr>
                                        <th scope="row">Previous PCR test date :</th>
                                        <td>
                                            <input class="form-control date" type="text" name="previousPCRTestDate" id="previousPCRTestDate" placeholder="if yes, test date" />
                                        </td>

                                        <th scope="row">Reason for 2nd PCR :</th>
                                        <td>
                                            <select class="form-control" name="pcrTestReason" id="pcrTestReason">
                                                <option value=''> -- Select -- </option>
                                                <option value="Confirmation of positive first EID PCR test result"> Confirmation of positive first EID PCR test result </option>
                                                <option value="Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months"> Repeat EID PCR test 6 weeks after stopping breastfeeding for children < 9 months </option>
                                                <option value="Positive HIV rapid test result at 9 months or later"> Positive HIV rapid test result at 9 months or later </option>
                                                <option value="1st Test Positive"> 1st Test Positive </option>
                                                <option value="DBS Invalid"> DBS Invalid </option>
                                                <option value="Indeterminate"> Indeterminate </option>
                                                <option value="Infant Still breastfeeding"> Infant Still breastfeeding </option>
                                                <option value="Infact <2 months post cessation of breastfeeding"> Infact <2 months post cessation of breastfeeding </option>
                                                <option value="Infants less than 6 weeks"> Infants less than 6 weeks </option>
                                                <option value="Inadequate feeding history"> Inadequate feeding history </option>

                                                <option value="Other"> Other </option>
                                            </select>
                                        </td>
                                    </tr>

                                </table>



                            </div>
                        </div>
                        <?php if (!$general->isSTSInstance()) { ?>
                            <div class="box box-primary">
                                <div class="box-body">
                                    <div class="box-header with-border">
                                        <h3 class="box-title"> Reserved for Laboratory Use </h3>
                                    </div>
                                    <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
                                        <tr>
                                            <td><label for="" class="labels">Testing Platform </label></td>
                                            <td><select class="form-control result-optional" name="eidPlatform" id="eidPlatform" title="Please select the testing platform">
                                                    <?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row"><label for="">Sample Received Date </label></th>
                                            <td>
                                                <input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate("Please enter date"); ?>" title="Please enter date de réception de l'échantillon" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
                                            </td>

                                        <tr>
                                            <td><label for="labId">Lab Name </label> </td>
                                            <td>
                                                <select name="labId" id="labId" class="form-control" title="Please select Testing Lab name" style="width:100%;">
                                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                            <th scope="row">Is Sample Rejected?</th>
                                            <td>
                                                <select class="form-control" name="isSampleRejected" id="isSampleRejected">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="yes"> Yes </option>
                                                    <option value="no"> No </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" class="rejected" style="display: none;">Reason for Rejection</th>
                                            <td class="rejected" style="display: none;">
                                                <select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason">
                                                    <option value=''> -- Select -- </option>
                                                    <?php echo $rejectionReason; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="show-rejection rejected" style="display:none;">
                                            <th>Rejection Date<span class="mandatory">*</span></th>
                                            <td><input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td style="width:25%;"><label for="">Sample Test Date </label></td>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control dateTime" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _translate("Please enter date"); ?>" title="Test effectué le" <?php echo $labFieldDisabled; ?> onchange="" style="width:100%;" />
                                            </td>


                                            <th scope="row">Result</th>
                                            <td>
                                                <select class="form-control" name="result" id="result">
                                                    <option value=''> -- Select -- </option>
                                                    <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                        <option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                        </tr>
                                    </table>

                                    <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">

                                        <tr>
                                            <th scope="row" colspan=4>
                                                <h5> First Test Information</h5>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row" style="width:25%;"><label for=""> Test Date </label></th>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control date" id="test1Date" name="test1Date" placeholder="<?= _translate("Please enter test date"); ?>" title="Test Date" onchange="" style="width:100%;" />
                                            </td>


                                            <th scope="row">Batch</th>
                                            <td>
                                                <input type="text" class="form-control" id="test1Batch" name="test1Batch" placeholder="<?= _translate("Please enter Batch"); ?>" title="Batch" style="width:100%;" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" style="width:25%;"><label for=""> Assay </label></th>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control" id="test1Assay" name="test1Assay" placeholder="<?= _translate("Please enter assay"); ?>" title="Assay" style="width:100%;" />
                                            </td>


                                            <th scope="row">CT/QS value</th>
                                            <td>
                                                <input type="text" class="form-control" id="test1CtQs" name="test1CtQs" placeholder="<?= _translate("Please enter CT/QS value"); ?>" title="CT/QS Value" onchange="" style="width:100%;" />
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row" style="width:25%;"><label for=""> Result </label></th>
                                            <td style="width:25%;">
                                                <select class="form-control" name="test1Result" id="test1Result">
                                                    <option value=''> -- Select -- </option>
                                                    <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                        <option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
                                                    <?php } ?>
                                                </select>
                                            </td>

                                            <th scope="row">Repeat Test?</th>
                                            <td>
                                                <select class="form-control" name="test1Repeated" id="test1Repeated" onchange="showRepeatedReason(this.value)">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="yes"> Yes </option>
                                                    <option value="no"> No </option>
                                                </select>
                                                <span class="test1RepeatReason" style="display:none;">
                                                    Why? <input type="text" class="form-control" id="test1RepeatReason" name="test1RepeatReason" placeholder="<?= _translate("Reason for Repeating Test"); ?>" title="Reason for Repeating Test" onchange="" style="width:100%;" /></span>
                                            </td>
                                        </tr>


                                        <tr>
                                            <th scope="row" colspan=4>
                                                <h5> Second Test Information</h5>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row" style="width:25%;"><label for=""> Test Date </label></th>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control date" id="test2Date" name="test2Date" placeholder="<?= _translate("Please enter test date"); ?>" title="Test Date" onchange="" style="width:100%;" />
                                            </td>


                                            <th scope="row">Batch</th>
                                            <td>
                                                <input type="text" class="form-control" id="test2Batch" name="test2Batch" placeholder="<?= _translate("Please enter Batch"); ?>" title="Batch" onchange="" style="width:100%;" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row" style="width:25%;"><label for=""> Assay </label></th>
                                            <td style="width:25%;">
                                                <input type="text" class="form-control" id="test2Assay" name="test2Assay" placeholder="<?= _translate("Please enter assay"); ?>" title="Assay" onchange="" style="width:100%;" />
                                            </td>


                                            <th scope="row">CT/QS value</th>
                                            <td>
                                                <input type="text" class="form-control" id="test2CtQs" name="test2CtQs" placeholder="<?= _translate("Please enter CT/QS value"); ?>" title="CT/QS value" onchange="" style="width:100%;" />
                                            </td>
                                        </tr>

                                        <tr>
                                            <th scope="row" style="width:25%;"><label for=""> Result </label></th>
                                            <td style="width:25%;">
                                                <select class="form-control" name="test2Result" id="test2Result">
                                                    <option value=''> -- Select -- </option>
                                                    <?php foreach ($eidResults as $eidResultKey => $eidResultValue) { ?>
                                                        <option value="<?php echo $eidResultKey; ?>"> <?php echo $eidResultValue; ?> </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                        </tr>

                                    </table>
                                    <table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">

                                        <tr>
                                            <th scope="row">Reviewed On</th>
                                            <td><input type="text" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" /></td>
                                            <th scope="row">Reviewed By</th>
                                            <td>
                                                <select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Approved On</th>
                                            <td><input type="text" name="approvedOnDateTime" id="approvedOnDateTime" class="dateTime disabled-field form-control" placeholder="Approved on" title="Please enter the approved on date" /></td>
                                            <th scope="row">Approved By</th>
                                            <td>
                                                <select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose approved by" style="width: 100%;">
                                                    <?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Second DBS Requested</th>
                                            <td>
                                                <select class="form-control" name="secondDBSRequested" onchange="showDBSRequestedReason(this.value);" id="secondDBSRequested">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="yes"> Yes </option>
                                                    <option value="no"> No </option>
                                                </select>
                                            </td>
                                            <th scope="row" class="DBSRequestedReason" style="display: none;">If Yes, Why?</th>
                                            <td class="DBSRequestedReason" style="display: none;">
                                                <select class="form-control" name="secondDBSRequestedReason" id="secondDBSRequestedReason">
                                                    <option value=''> -- Select -- </option>
                                                    <option value="1st Test Positive"> 1st Test Positive </option>
                                                    <option value="DBS Invalid"> DBS Invalid </option>
                                                    <option value="Indeterminate"> Indeterminate </option>
                                                    <option value="Infant < 2 months post-exposure to delivery"> Infant < 2 months post-exposure to delivery </option>
                                                    <option value="Infant still breastfeeding"> Infant still breastfeeding </option>
                                                    <option value="Infant < 2 months post breastfeeding"> Infant < 2 months post breastfeeding </option>
                                                    <option value="Infant less than 6 weeks"> Infant less than 6 weeks </option>
                                                    <option value="Inadequate feeding history"> Inadequate feeding history </option>
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
                        <?php if (isset($arr['eid_sample_code'])) { ?>
                            <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
                            <input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
                        <?php } ?>
                        <a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
                        <input type="hidden" name="formId" id="formId" value="5" />
                        <input type="hidden" name="eidSampleId" id="eidSampleId" value="" />
                        <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle" value="<?php echo $arr['sample_code']; ?>" />
                        <input type="hidden" name="provinceId" id="provinceId" />
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
    let changeProvince = true;
    let changeFacility = true;
    let provinceName = true;
    let facilityName = true;
    let machineName = true;
    let sampleCodeGenerationEvent = null

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

    let generateSampleCodeRequest;

    function generateSampleCode() {
        let pName = $("#province").val();
        let sDate = $("#sampleCollectionDate").val();
        let provinceCode = $("#province").find(":selected").attr("data-code");

        if (pName != '' && sDate != '') {
            if (generateSampleCodeRequest) {
                generateSampleCodeRequest.abort();
            }
            $.blockUI();
            provinceCode = ($("#province").find(":selected").attr("data-code") == null || $("#province").find(":selected").attr("data-code") == '') ? $("#province").find(":selected").attr("data-name") : $("#province").find(":selected").attr("data-code");
            generateSampleCodeRequest = $.post("/eid/requests/generateSampleCode.php", {
                    sampleCollectionDate: sDate,
                    provinceCode: provinceCode,
                    provinceId: $("#province").find(":selected").attr("data-province-id")
                },
                function(data) {
                    let sCodeKey = JSON.parse(data);
                    $("#sampleCode").val(sCodeKey.sampleCode);
                    $("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
                    $("#sampleCodeKey").val(sCodeKey.maxId);
                    $("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
                    $.unblockUI();
                }).always(function() {
                generateSampleCodeRequest = null; // Reset the request object after completion
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
                    testType: 'eid'
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
            $("#facilityId").html("<?php echo $facility; ?>");
        }
        $.unblockUI();
    }


    function validateNow() {
        $.blockUI();
        var provinceId = $("#province").find(":selected").attr("data-province-id");
        flag = deforayValidator.init({
            formId: 'addEIDRequestForm'
        });
        if (flag) {
            $('.btn-disabled').attr('disabled', 'yes');
            $(".btn-disabled").prop("onclick", null).off("click");

            var provinceCode = ($("#province").find(":selected").attr("data-code") == null || $("#province").find(":selected").attr("data-code") == '') ? $("#province").find(":selected").attr("data-name") : $("#province").find(":selected").attr("data-code");

            <?php if (isset($arr['eid_sample_code'])) { ?>
                insertSampleCode('addEIDRequestForm', 'eidSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', $("#formId").val(), 'sampleCollectionDate', provinceCode, provinceId);
            <?php } else { ?>
                document.getElementById('addEIDRequestForm').submit();
            <?php } ?>
        }

    }

    function updateMotherViralLoad() {
        //var motherVl = $("#motherViralLoadCopiesPerMl").val();
        var motherVlText = $("#motherViralLoadText").val();
        if (motherVlText != '') {
            $("#motherViralLoadCopiesPerMl").val('');
        }
    }

    function showDBSRequestedReason(requested) {
        if (requested == "yes")
            $(".DBSRequestedReason").show();
        else
            $(".DBSRequestedReason").hide();
    }

    function showOtherOption(modeOfdelivery) {
        if (modeOfdelivery == "Other")
            $("#modeOfDeliveryOther").show();
        else
            $("#modeOfDeliveryOther").hide();
    }

    function showOtherArt(infantArt) {
        if (infantArt == "Other ART")
            $("#infantArtStatusOther").show();
        else
            $("#infantArtStatusOther").hide();
    }

    function showRepeatedReason(repeated) {
        if (repeated == "yes")
            $(".test1RepeatReason").show();
        else
            $(".test1RepeatReason").hide();

    }

    $(document).ready(function() {

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
        $('#district').select2({
            placeholder: "District"
        });
        $('#province').select2({
            placeholder: "Province"
        });
        $("#motherViralLoadCopiesPerMl").on("change keyup paste", function() {
            var motherVl = $("#motherViralLoadCopiesPerMl").val();
            //var motherVlText = $("#motherViralLoadText").val();
            if (motherVl != '') {
                $("#motherViralLoadText").val('');
            }
        });

    });
</script>