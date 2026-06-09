<?php

/**
 * Shared request/result form BODY (fields only).
 *
 * Included by add-request.php (and, progressively, clone/edit/result). The
 * including page owns the <form> tag (name/action differ per mode), the data
 * fetched into the variables used below, and the page <script> block.
 *
 * Prefill: in add mode $genericResultInfo is null, so prefill helpers resolve
 * to blank. In clone/edit/result modes the including page sets it to the
 * source form_generic row.
 *
 * The including page may also set these mode flags (each defaults to add-mode
 * behaviour when unset):
 *   $formMode          'add' | 'clone' | 'edit' | 'result'    (default 'add')
 *   $showBarcode       show the barcode/print blocks            (default true)
 *   $showPatientSearch show the EPID patient-search box         (default true)
 *   $showSaveNextClone show Save-and-Next / Save-and-Clone      (default true)
 *   $showSubTestPicker show the "Tests Performed" multiselect   (default true)
 *   $showChangeReason  show the "Reason for Result Changes" row (default false)
 *   $disableNonResult  render non-result sections read-only     (default false)
 */
$genericResultInfo = $genericResultInfo ?? null;
$formMode          = $formMode ?? 'add';
$showBarcode       = $showBarcode ?? true;
$showPatientSearch = $showPatientSearch ?? true;
$showSaveNextClone = $showSaveNextClone ?? true;
$showSubTestPicker = $showSubTestPicker ?? true;
$showChangeReason  = $showChangeReason ?? false;
$disableNonResult  = $disableNonResult ?? false;
$sFormat           = $sFormat ?? '';
$sKey              = $sKey ?? '';
$arr               = $arr ?? $general->getGlobalConfig();
$mandatoryClass    = $mandatoryClass ?? '';
$dnr               = $disableNonResult ? ' disabledForm' : '';  // read-only marker for non-result boxes (result mode)
$cancelUrl         = $cancelUrl ?? 'view-requests.php';
// getSubTestList() loads the sub-test picker, then (in its callback, once the
// picker is populated) calls getTestTypeForm() ONCE with the selected sub-tests
// so the sections and the result table arrive in a single getTestTypeForm.php
// request -- instead of the old getTestTypeForm()+loadSubTests() double call,
// whose loadSubTests() raced ahead of the (async) picker and fetched nothing.
$onTestTypeChange         = $onTestTypeChange ?? "getSubTestList(this.value);updateTestTypeUrl(this.value);";
$onFacilityChange         = $onFacilityChange ?? "getfacilityProvinceDetails(this);fillFacilityDetails();setSampleDispatchDate();";
$onLabChange              = $onLabChange ?? "autoFillFocalDetails();setSampleDispatchDate();";
$onSampleCollectionChange = $onSampleCollectionChange ?? "checkSampleTestingDate();generateSampleCode();setSampleDispatchDate();checkCollectionDate(this.value);";

// Prefill helpers — resolve from the source row in clone/edit/result, blank in add.
$gri   = is_array($genericResultInfo) ? $genericResultInfo : [];
$pf    = static fn(string $k): string => htmlspecialchars((string) ($gri[$k] ?? ''), ENT_QUOTES, 'UTF-8'); // value="..."
$pfRaw = static fn(string $k) => $gri[$k] ?? null;                                                          // for generateSelectOptions / comparisons
$pfSel = static fn(string $k, $v): string => ((string) ($gri[$k] ?? '') === (string) $v) ? "selected='selected'" : ''; // <option>
$pfChk = static fn(string $k, $v): string => ((string) ($gri[$k] ?? '') === (string) $v) ? "checked='checked'" : '';   // radio/checkbox
$e     = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');                         // escape DB-sourced display text
?>
<div class="box-body">
     <?php // Test Type anchors the whole form: it is always visible, and the rest
     // of the request form (Clinic / Patient / Sample / Lab boxes, all .requestForm)
     // only loads once a type is chosen. On edit/result the value is fixed -- changing
     // it would orphan the test's configured methods/results -- so the select is
     // disabled and the value is mirrored in a hidden input (a disabled select
     // does not submit).
     $gtTypeLocked = ($formMode !== 'add'); ?>
     <div class="box box-primary<?= $dnr ?>">
          <div class="box-body" style="padding: 12px 15px;">
               <div style="text-align:center;">
                    <label for="testType" style="font-weight:600;margin:0 12px 0 0;vertical-align:middle;"><?= _translate("Test Type"); ?>
                         <span class="mandatory">*</span></label>
                    <div style="display:inline-block;width:340px;max-width:80%;text-align:left;vertical-align:middle;">
                         <select class="form-control isRequired" name="testType" id="testType"
                              title="Please choose test type" style="width:100%;"
                              <?= $gtTypeLocked ? 'disabled' : '' ?>
                              onchange="<?= $onTestTypeChange ?>">
                              <option value=""> <?= _translate("-- Select --"); ?> </option>
                              <?php foreach ($testTypeResult as $testType) { ?>
                                   <option value="<?php echo $testType['test_type_id'] ?>" <?= $pfSel('test_type', $testType['test_type_id']) ?>
                                        data-short="<?php echo $testType['test_short_code']; ?>">
                                        <?= $e($testType['test_standard_name'] . ' (' . $testType['test_loinc_code'] . ')') ?>
                                   </option>
                              <?php } ?>
                         </select>
                         <?php if ($gtTypeLocked) { ?>
                              <input type="hidden" name="testType" value="<?= $pf('test_type') ?>" />
                         <?php } ?>
                    </div>
                    <?php if ($formMode === 'add') { ?>
                         <p class="selectTestTypePrompt" style="margin:10px 0 0;font-size:12.5px;color:#8a939b;">
                              <i class="fa-solid fa-circle-info" style="margin-right:4px;"></i>
                              <?= _translate("Choose a test type to load the rest of the request form."); ?>
                         </p>
                    <?php } ?>
               </div>
          </div>
     </div>
     <div class="box box-primary requestForm<?= $dnr ?>" style="display:none;">
          <div class="box-header with-border">
               <h3 class="box-title">
                    <?= _translate("Clinic Information: (To be filled by requesting Clinican/Nurse)"); ?>
               </h3>
          </div>
          <div class="row requestForm" style="display:none;">
               <div class="col-md-6">
                    <label class="col-lg-5" for="sampleCode"><?= _translate("Sample ID"); ?>
                         <span class="mandatory">*</span></label>
                    <div class="col-lg-7">
                         <input type="text"
                              class="form-control isRequired <?php echo $sampleClass; ?>"
                              id="sampleCode" name="sampleCode" value="<?= $pf($sampleCode) ?>" <?php echo $maxLength; ?>
                              placeholder="<?php echo _translate('Enter Sample ID'); ?>"
                              title="<?php echo _translate('Please enter sample id'); ?>"
                              style="width:100%;" readonly
                              onblur="checkSampleNameValidation('form_generic','<?php echo $sampleCode; ?>',this.id,null,'This sample number already exists.Try another number',null)" />
                    </div>
               </div>
               <div class="col-md-6">
                    <label class="col-lg-5" for="sampleReordered">
                         <?= _translate("Sample Reordered"); ?></label>
                    <div class="col-lg-7">
                         <input type="checkbox" class="" id="sampleReordered"
                              name="sampleReordered" value="yes" <?= $pfChk('sample_reordered', 'yes') ?>
                              title="<?php echo _translate('Please indicate if this is a reordered sample'); ?>">

                    </div>
               </div>
          </div>
          <div class="requestForm" style="display:none;">
               <div class="row">
                    <!-- BARCODESTUFF START -->
                    <?php if ($showBarcode && isset($global['bar_code_printing']) && $global['bar_code_printing'] != "off") { ?>
                         <div class="col-md-6 pull-right">
                              <div class="form-group">
                                   <label
                                        for="sampleCode"><?= _translate("Print Barcode Label"); ?><span
                                             class="mandatory">*</span> </label>
                                   <input type="checkbox" class="" id="printBarCode"
                                        name="printBarCode" checked />
                              </div>
                         </div>
                    <?php } ?>
                    <!-- BARCODESTUFF END -->
               </div>
               <div class="row">
                    <div class="col-md-6">
                         <label class="col-lg-5"
                              for="province"><?= _translate("State/Province"); ?> <span
                                   class="mandatory">*</span></label>
                         <div class="col-lg-7">
                              <select class="form-control isRequired" name="province"
                                   id="province" title="Please choose state"
                                   style="width:100%;" onchange="getProvinceDistricts(this);">
                                   <?php echo $province; ?>
                              </select>
                         </div>
                    </div>
                    <div class="col-md-6">
                         <label class="col-lg-5"
                              for="district"><?= _translate("District/County"); ?> <span
                                   class="mandatory">*</span></label>
                         <div class="col-lg-7">
                              <select class="form-control isRequired" name="district"
                                   id="district" title="Please choose county"
                                   style="width:100%;" onchange="getFacilities(this);">
                                   <option value=""> <?= _translate("-- Select --"); ?>
                                   </option>
                              </select>
                         </div>
                    </div>
               </div>
               <div class="row">
                    <div class="col-md-6">
                         <label class="col-lg-5"
                              for="facilityId"><?= _translate("Clinic/Health Center"); ?> <span
                                   class="mandatory">*</span></label>
                         <div class="col-lg-7">
                              <select class="form-control isRequired select2" id="facilityId"
                                   name="facilityId"
                                   title="Please select clinic/health center name"
                                   style="width:100%;"
                                   onchange="<?= $onFacilityChange ?>">
                                   <?= $general->generateSelectOptions($healthFacilities, $pfRaw('facility_id') ?: null, '-- Select --'); ?>
                              </select>
                         </div>
                    </div>
                    <div class="col-md-6" style="display:none;">
                         <label class="col-lg-5"
                              for="facilityCode"><?= _translate("Clinic/Health Center Code"); ?></label>
                         <div class="col-lg-7">
                              <input type="text" class="form-control" style="width:100%;"
                                   name="facilityCode" id="facilityCode"
                                   placeholder="<?php echo _translate('Clinic/Health Center Code'); ?>"
                                   title="<?php echo _translate('Please enter clinic/health center code'); ?>">
                         </div>
                    </div>
                    <div class="col-md-6">
                         <label class="col-lg-5" for="labId"><?= _translate("Testing Lab"); ?>
                              <span class="mandatory">*</span></label>
                         <div class="col-lg-7">
                              <select name="labId" id="labId"
                                   class="select2 form-control isRequired"
                                   title="Please choose lab"
                                   onchange="<?= $onLabChange ?>"
                                   style="width:100%;">
                                   <option value=""><?= _translate("-- Select --"); ?></option>
                                   <?php foreach ($lResult as $labName) { ?>
                                        <option
                                             data-focalperson="<?= $e($labName['contact_person']) ?>"
                                             data-focalphone="<?= $e($labName['facility_mobile_numbers']) ?>"
                                             value="<?php echo $labName['facility_id']; ?>" <?= $pfSel('lab_id', $labName['facility_id']) ?>>
                                             <?= $e($labName['facility_name']) ?></option>
                                   <?php } ?>
                              </select>
                         </div>
                    </div>
               </div>
               <div class="row facilityDetails" style="display:none;">
                    <div class="col-xs-2 col-md-2 femails" style="display:none;">
                         <strong><?= _translate("Clinic Email(s)"); ?> -</strong>
                    </div>
                    <div class="col-xs-2 col-md-2 femails facilityEmails"
                         style="display:none;"></div>
                    <div class="col-xs-2 col-md-2 fmobileNumbers" style="display:none;">
                         <strong>Clinic Mobile No.(s) -</strong>
                    </div>
                    <div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers"
                         style="display:none;"></div>
                    <div class="col-xs-2 col-md-2 fContactPerson" style="display:none;">
                         <strong>Clinic Contact Person -</strong>
                    </div>
                    <div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson"
                         style="display:none;"></div>
               </div>
               <?php // Implementing Partner + Funding Source are the LAST two clinic fields and
               // are optional (toggled via the test type's Advanced Configuration). Keeping them
               // trailing means hiding them just drops a clean row -- no holes in the grid above. 
               ?>
               <div class="row">
                    <div class="col-md-6">
                         <label class="col-lg-5"
                              for="implementingPartner"><?= _translate("Implementing Partner"); ?></label>
                         <div class="col-lg-7">
                              <select class="form-control" name="implementingPartner"
                                   id="implementingPartner"
                                   title="Please choose implementing partner"
                                   style="width:100%;">
                                   <option value=""> <?= _translate("-- Select --"); ?>
                                   </option>
                                   <?php
                                   foreach ($implementingPartnerList as $implementingPartner) {
                                   ?>
                                        <option
                                             value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>" <?= $pfSel('implementing_partner', $implementingPartner['i_partner_id']) ?>>
                                             <?= $e($implementingPartner['i_partner_name']) ?></option>
                                   <?php } ?>
                              </select>
                         </div>
                    </div>
                    <div class="col-md-6">
                         <label class="col-lg-5"
                              for="fundingSource"><?= _translate("Funding Source"); ?></label>
                         <div class="col-lg-7">
                              <select class="form-control" name="fundingSource"
                                   id="fundingSource"
                                   title="<?= _translate('Please choose implementing partner'); ?>"
                                   style="width:100%;">
                                   <option value=""> <?= _translate("-- Select --"); ?>
                                   </option>
                                   <?php
                                   foreach ($fundingSourceList as $fundingSource) {
                                   ?>
                                        <option
                                             value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>" <?= $pfSel('funding_source', $fundingSource['funding_source_id']) ?>>
                                             <?= $e($fundingSource['funding_source_name']) ?></option>
                                   <?php } ?>
                              </select>
                         </div>
                    </div>
               </div>
               <div class="row" id="facilitySection"></div>
          </div>
     </div>
     <div class="box box-primary requestForm<?= $dnr ?>" style="display:none;">
          <div class="box-header with-border">
               <h3 class="box-title"><?= _translate("Patient Information"); ?></h3>
               <?php if ($showPatientSearch) { ?>
                    &nbsp;&nbsp;&nbsp;
                    <input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo"
                         class="" placeholder="<?php echo _translate('Enter Patient Identifier'); ?>"
                         title="<?php echo _translate('Please enter the Enter Patient Identifier'); ?>" />&nbsp;&nbsp;
                    <a style="margin-top:-0.35%;" href="javascript:void(0);"
                         class="btn btn-default btn-sm" onclick="showPatientList();"><em
                              class="fa-solid fa-magnifying-glass"></em>Search</a><span
                         id="showEmptyResult"
                         style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No
                              Patient Found</strong></span>
               <?php } ?>
          </div>
          <div class="box-body">
               <div class="row">
                    <div class="col-md-6">
                         <label class="col-lg-5" for="artNo"><span id="artNoLabelText"><?= _translate("EPID Number"); ?></span>
                              <?php if ($general->isLISInstance()) { ?><span
                                        class="mandatory">*</span><?php } ?></label>
                         <div class="col-lg-7">
                              <input type="text" name="artNo" id="artNo" value="<?= $pf('patient_id') ?>"
                                   class="form-control <?= $mandatoryClass; ?> patientId"
                                   placeholder="<?php echo _translate('Enter Patient Identifier'); ?>"
                                   title="<?php echo _translate('Enter Patient Identifier'); ?>"
                                   onchange="checkPatientDetails('form_generic','patient_id',this,null)" />
                         </div>
                    </div>
                    <div class="col-md-6">
                         <label class="col-lg-5"
                              for="laboratoryNumber"><?= _translate("Laboratory Number"); ?>
                              <?php if ($general->isLISInstance()) { ?><span
                                        class="mandatory">*</span><?php } ?></label>
                         <div class="col-lg-7">
                              <input type="text" name="laboratoryNumber" id="laboratoryNumber" value="<?= $pf('laboratory_number') ?>"
                                   class="form-control <?= $mandatoryClass; ?>"
                                   placeholder="<?php echo _translate('Enter Laboratory Number'); ?>"
                                   title="<?php echo _translate('Enter Laboratory Number'); ?>" />
                         </div>
                    </div>
               </div>
               <div class="row">
                    <div class="col-md-6">
                         <label class="col-lg-5" for="dob"><?= _translate("Date of Birth"); ?>
                         </label>
                         <div class="col-lg-7">
                              <input type="text" name="dob" id="dob" value="<?= $pf('patient_dob') ?>" class="form-control date"
                                   placeholder="<?php echo _translate('Enter DOB'); ?>"
                                   title="<?php echo _translate('Enter dob'); ?>"
                                   onchange="getAge();" />
                         </div>
                    </div>
                    <div class="col-md-6">
                         <label class="col-lg-5"
                              for="ageInYears"><?= _translate("If DOB unknown, Age in Years"); ?>
                         </label>
                         <div class="col-lg-7">
                              <input type="text" name="ageInYears" id="ageInYears" value="<?= $pf('patient_age_in_years') ?>"
                                   class="form-control forceNumeric" maxlength="3"
                                   placeholder="<?php echo _translate('Age in Years'); ?>"
                                   title="<?php echo _translate('Enter age in years'); ?>" />
                         </div>
                    </div>
               </div>
               <div class="row">
                    <div class="col-md-6">
                         <label class="col-lg-5"
                              for="ageInMonths"><?= _translate("If Age < 1, Age in Months"); ?>
                         </label>
                         <div class="col-lg-7">
                              <input type="text" name="ageInMonths" id="ageInMonths" value="<?= $pf('patient_age_in_months') ?>"
                                   class="form-control forceNumeric" maxlength="2"
                                   placeholder="<?php echo _translate('Age in Month'); ?>"
                                   title="<?php echo _translate('Enter age in months'); ?>" />
                         </div>
                    </div>
                    <div class="col-md-6">
                         <label class="col-lg-5"
                              for="patientFirstName"><?= _translate("Patient Name (First Name, Last Name)"); ?>
                              <span class="mandatory">*</span></label>
                         <div class="col-lg-7">
                              <input type="text" name="patientFirstName" id="patientFirstName" value="<?= htmlspecialchars((string) ($patientFullName ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   class="form-control isRequired"
                                   placeholder="<?php echo _translate('Enter Patient Name'); ?>"
                                   title="<?php echo _translate('Enter patient name'); ?>" />
                         </div>
                    </div>
               </div>
               <div class="row ">
                    <div class="col-md-6">
                         <label class="col-lg-5" for="gender"><?= _translate("Sex"); ?></label>
                         <div class="col-lg-5">
                              <label class="radio-inline" style="margin-left:0px;">
                                   <input type="radio" class="" id="genderMale" name="gender"
                                        value="male" <?= $pfChk('patient_gender', 'male') ?>
                                        title="<?php echo _translate('Please choose sex'); ?>"><?= _translate("Male"); ?>
                              </label>
                              <label class="radio-inline" style="margin-left:0px;">
                                   <input type="radio" class="" id="genderFemale" name="gender"
                                        value="female" <?= $pfChk('patient_gender', 'female') ?>
                                        title="<?php echo _translate('Please choose sex'); ?>"><?= _translate("Female"); ?>
                              </label>
                              <label class="radio-inline" style="margin-left:0px;">
                                   <input type="radio" class="" id="genderUnreported"
                                        name="gender" value="unreported" <?= $pfChk('patient_gender', 'unreported') ?>
                                        title="<?php echo _translate('Please choose sex'); ?>"><?= _translate("Unreported"); ?>
                              </label>
                         </div>
                    </div>
                    <div class="col-md-6">
                         <label class="col-lg-5"
                              for="receiveSms"><?= _translate("Patient consent to receive SMS?"); ?></label>
                         <div class="col-lg-7">
                              <label class="radio-inline" style="margin-left:0px;">
                                   <input type="radio" class="" id="receivesmsYes"
                                        name="receiveSms" value="yes" <?= $pfChk('consent_to_receive_sms', 'yes') ?>
                                        title="<?php echo _translate('Patient consent to receive SMS'); ?>"
                                        onclick="checkPatientReceivesms(this.value);">
                                   <?= _translate("Yes"); ?>
                              </label>
                              <label class="radio-inline" style="margin-left:0px;">
                                   <input type="radio" class="" id="receivesmsNo"
                                        name="receiveSms" value="no" <?= $pfChk('consent_to_receive_sms', 'no') ?>
                                        title="<?php echo _translate('Patient consent to receive SMS'); ?>"
                                        onclick="checkPatientReceivesms(this.value);">
                                   <?= _translate("No"); ?>
                              </label>
                         </div>
                    </div>
               </div>
               <div class="row">
                    <div class="col-md-6">
                         <label class="col-lg-5"
                              for="patientPhoneNumber"><?= _translate("Phone Number"); ?></label>
                         <div class="col-lg-7">
                              <input type="text" name="patientPhoneNumber"
                                   id="patientPhoneNumber" value="<?= $pf('patient_mobile_number') ?>" class="form-control phone-number"
                                   maxlength="15"
                                   placeholder="<?php echo _translate('Enter Phone Number'); ?>"
                                   title="<?php echo _translate('Enter phone number'); ?>" />
                         </div>
                    </div>
                    <div class="col-md-6 femaleSection">
                         <label class="col-lg-5"
                              for="patientPregnant"><?= _translate("Is Patient Pregnant?"); ?>
                         </label>
                         <div class="col-lg-7">
                              <label class="radio-inline">
                                   <input type="radio" class="" id="pregYes"
                                        name="patientPregnant" value="yes" <?= $pfChk('is_patient_pregnant', 'yes') ?>
                                        title="<?php echo _translate('Is Patient Pregnant?'); ?>">
                                   <?= _translate("Yes"); ?>
                              </label>
                              <label class="radio-inline">
                                   <input type="radio" class="" id="pregNo"
                                        name="patientPregnant" value="no" <?= $pfChk('is_patient_pregnant', 'no') ?>>
                                   <?= _translate("No"); ?>
                              </label>
                         </div>
                    </div>
               </div>
               <div class="row ">
                    <div class="col-md-6 femaleSection">
                         <label class="col-lg-5"
                              for="breastfeeding"><?= _translate("Is Patient Breastfeeding?"); ?>
                         </label>
                         <div class="col-lg-7">
                              <label class="radio-inline">
                                   <input type="radio" class="" id="breastfeedingYes"
                                        name="breastfeeding" value="yes" <?= $pfChk('is_patient_breastfeeding', 'yes') ?>
                                        title="<?php echo _translate('Is Patient Breastfeeding?'); ?>">
                                   <?= _translate("Yes"); ?>
                              </label>
                              <label class="radio-inline">
                                   <input type="radio" class="" id="breastfeedingNo"
                                        name="breastfeeding" value="no" <?= $pfChk('is_patient_breastfeeding', 'no') ?>>
                                   <?= _translate("No"); ?>
                              </label>
                         </div>
                    </div>
                    <div class="col-md-6" style="display:none;" id="patientSection">
                         <label class="col-lg-5"
                              for="treatPeriod"><?= _translate("How long has this patient been on treatment ?"); ?>
                         </label>
                         <div class="col-lg-7">
                              <input type="text" class="form-control" id="treatPeriod"
                                   name="treatPeriod" value="<?= $pf('treatment_initiation') ?>"
                                   placeholder="<?php echo _translate('Enter Treatment Period'); ?>"
                                   title="<?php echo _translate('Please enter how long has this patient been on treatment'); ?>" />
                         </div>
                    </div>
               </div>
          </div>
          <div class="box box-primary caseInformationBox<?= $dnr ?>" id="caseInformationBox"
               style="display:none;">
               <div class="box-header with-border">
                    <h3 class="box-title"><?= _translate("Case Information"); ?></h3>
               </div>
               <div class="box-body">
                    <div class="row" id="caseInformation"></div>
               </div>
          </div>
          <div class="box box-primary<?= $dnr ?>">
               <div class="box-header with-border">
                    <h3 class="box-title"><?= _translate("Sample Information"); ?></h3>
               </div>
               <div class="box-body">
                    <div class="row">
                         <div class="col-md-6">
                              <label class="col-lg-5"
                                   for="sampleCollectionDate"><?= _translate("Date of Sample Collection"); ?>
                                   <span class="mandatory">*</span></label>
                              <div class="col-lg-7">
                                   <input type="text" class="form-control isRequired dateTime"
                                        style="width:100%;" name="sampleCollectionDate"
                                        id="sampleCollectionDate" value="<?= $pf('sample_collection_date') ?>"
                                        placeholder="<?php echo _translate('Sample Collection Date'); ?>"
                                        title="<?php echo _translate('Please select sample collection date'); ?>"
                                        onchange="<?= $onSampleCollectionChange ?>">
                                   <span class="expiredCollectionDate"
                                        style="color:red; display:none;"></span>
                              </div>
                         </div>
                         <div class="col-md-6">
                              <label class="col-lg-5"
                                   for="sampleDispatchedDate"><?= _translate("Sample Dispatched On"); ?>
                                   <span class="mandatory">*</span></label>
                              <div class="col-lg-7">
                                   <input type="text" class="form-control isRequired dateTime"
                                        style="width:100%;" name="sampleDispatchedDate"
                                        id="sampleDispatchedDate" value="<?= $pf('sample_dispatched_datetime') ?>"
                                        placeholder="<?php echo _translate('Sample Dispatched On'); ?>"
                                        title="<?php echo _translate('Please select sample dispatched on'); ?>">
                              </div>
                         </div>
                    </div>
                    <div class="row">
                         <div class="col-md-6" id="specimenSection">
                              <label class="col-lg-5"
                                   for="specimenType"><?= _translate("Sample Type"); ?> <span
                                        class="mandatory">*</span></label>
                              <div class="col-lg-7">
                                   <select name="specimenType" id="specimenType"
                                        class="form-control isRequired"
                                        title="<?php echo _translate('Please choose sample type'); ?>">
                                        <option value=""> <?= _translate("-- Select --"); ?>
                                        </option>
                                        <?php foreach ($sResult as $name) { ?>
                                             <option value="<?php echo $name['sample_type_id']; ?>" <?= $pfSel('specimen_type', $name['sample_type_id']) ?>>
                                                  <?= $e($name['sample_type_name']) ?></option>
                                        <?php } ?>
                                   </select>
                              </div>
                         </div>
                         <div class="col-md-6">
                              <label class="col-lg-5 control-label labels"
                                   for="reasonForTesting"><?= _translate("Reason For Testing"); ?>
                                   <span class="mandatory result-span">*</span></label>
                              <div class="col-lg-7">
                                   <select name="reasonForTesting" id="reasonForTesting"
                                        class="form-control result-optional"
                                        title="<?php echo _translate('Please choose reason for testing'); ?>">
                                        <option value=""><?= _translate("-- Select --"); ?>
                                        </option>
                                        <?php foreach ($testReason as $treason) { ?>
                                             <option
                                                  value="<?php echo $treason['test_reason_id']; ?>" <?= $pfSel('reason_for_testing', $treason['test_reason_id']) ?>>
                                                  <?= $e($treason['test_reason']) ?></option>
                                        <?php } ?>
                                   </select>
                              </div>
                         </div>
                    </div>

                    <!-- <div id="specimenSection"></div> -->
               </div>
          </div>
          <div id="otherSection" class="<?= ltrim($dnr) ?>"></div>
          <?php // Result entry lives ONLY on the result-update page (the per-Test multi-test
          // cards). The add/edit REQUEST forms register the sample; results are entered
          // later from the result page. So this whole "Laboratory Information" / result box
          // renders only in result mode. (It can be brought back to add/edit later if needed.)
          if ($formMode === 'result') { ?>
               <div class="box box-primary">
                    <?php // Multi-test mode supplies its own "TEST RESULTS INFORMATION" heading
                    // via _test-section.php, so the box title would be a redundant second heading.
                    if (empty($multiTestResults)) { ?>
                         <div class="box-header with-border">
                              <h3 class="box-title"><?= _translate("Laboratory Information"); ?></h3>
                         </div>
                    <?php } ?>
                    <div class="box-body">
                         <?php
                         // These are the legacy SINGLE-RESULT sample-level fields. They are still
                         // needed on the add/edit REQUEST forms (sample registration). In the
                         // multi-test RESULT page they are superseded by the per-Test cards from
                         // _test-section.php, so we do not render them there at all (no JS hiding).
                         if (empty($multiTestResults)) { ?>
                              <div class="row">
                                   <div class="col-md-6">
                                        <label for="vlFocalPerson" class="col-lg-5 control-label labels">
                                             <?= _translate("Focal Person"); ?> </label>
                                        <div class="col-lg-7">
                                             <select class="form-control ajax-select2" id="vlFocalPerson"
                                                  name="vlFocalPerson"
                                                  placeholder="<?php echo _translate('Focal Person'); ?>"
                                                  title="<?php echo _translate('Please enter focal person name'); ?>"><?php if ($pf('testing_lab_focal_person') !== '') { ?><option value="<?= $pf('testing_lab_focal_person') ?>" selected="selected"><?= $pf('testing_lab_focal_person') ?></option><?php } ?></select>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <label for="vlFocalPersonPhoneNumber"
                                             class="col-lg-5 control-label labels">
                                             <?= _translate("Focal Person Phone Number"); ?></label>
                                        <div class="col-lg-7">
                                             <input type="text" class="form-control phone-number"
                                                  id="vlFocalPersonPhoneNumber"
                                                  name="vlFocalPersonPhoneNumber" value="<?= $pf('testing_lab_focal_person_phone_number') ?>"
                                                  placeholder="<?php echo _translate('Phone Number'); ?>"
                                                  title="<?php echo _translate('Please enter focal person phone number'); ?>" />
                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6">
                                        <label class="col-lg-5 control-label labels"
                                             for="sampleReceivedAtHubOn"><?= _translate("Date Sample Received at Hub"); ?>
                                        </label>
                                        <div class="col-lg-7">
                                             <input type="text" class="form-control dateTime"
                                                  id="sampleReceivedAtHubOn" name="sampleReceivedAtHubOn" value="<?= $pf('sample_received_at_hub_datetime') ?>"
                                                  placeholder="<?php echo _translate('Sample Received at HUB Date'); ?>"
                                                  title="<?php echo _translate('Please select sample received at Hub date'); ?>" />
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <label class="col-lg-5 control-label labels"
                                             for="sampleReceivedDate"><?= _translate("Date Sample Received at Testing Lab"); ?>
                                        </label>
                                        <div class="col-lg-7">
                                             <input type="text" class="form-control dateTime"
                                                  id="sampleReceivedDate" name="sampleReceivedDate" value="<?= $pf('sample_received_at_lab_datetime') ?>"
                                                  placeholder="<?php echo _translate('Sample Received at LAB Date'); ?>"
                                                  title="<?php echo _translate('Please select sample received at Lab date'); ?>" />
                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6">
                                        <label for="testPlatform" class="col-lg-5 control-label labels">
                                             <?= _translate("Testing Platform"); ?> <span
                                                  class="mandatory result-span">*</span></label>
                                        <div class="col-lg-7">
                                             <select name="testPlatform" id="testPlatform"
                                                  class="form-control result-optional"
                                                  title="<?php echo _translate('Please choose Testing Platform'); ?>">
                                                  <option value="">-- Select --</option>
                                                  <?php foreach ($importResult as $mName) { ?>
                                                       <option
                                                            value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['instrument_id']; ?>" <?= (((string) ($pfRaw('test_platform') ?? '')) === (string) $mName['machine_name']) ? "selected='selected'" : '' ?>>
                                                            <?= $e($mName['machine_name']) ?></option>
                                                  <?php } ?>
                                             </select>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <label class="col-lg-5 control-label labels"
                                             for="isSampleRejected"><?= _translate("Is Sample Rejected?"); ?>
                                             <span class="mandatory result-span">*</span></label>
                                        <div class="col-lg-7">
                                             <select name="isSampleRejected" id="isSampleRejected"
                                                  class="form-control"
                                                  title="<?php echo _translate('Please check if sample is rejected or not'); ?>">
                                                  <option value=""><?= _translate("-- Select --"); ?>
                                                  </option>
                                                  <option value="yes" <?= $pfSel('is_sample_rejected', 'yes') ?>><?= _translate("Yes"); ?></option>
                                                  <option value="no" <?= $pfSel('is_sample_rejected', 'no') ?>><?= _translate("No"); ?></option>
                                             </select>
                                        </div>
                                   </div>
                              </div>
                              <div class="row rejectionReason" style="display:none;">
                                   <div class="col-md-6 rejectionReason" style="display:none;">
                                        <label class="col-lg-5 control-label labels"
                                             for="rejectionReason"><?= _translate("Rejection Reason"); ?>
                                        </label>
                                        <div class="col-lg-7">
                                             <select name="rejectionReason" id="rejectionReason"
                                                  class="form-control"
                                                  title="<?php echo _translate('Please choose reason'); ?>"
                                                  onchange="checkRejectionReason();">
                                                  <option value=""><?= _translate("-- Select --"); ?>
                                                  </option>
                                                  <?php foreach ($rejectionTypeResult as $type) { ?>
                                                       <optgroup
                                                            label="<?= $e(strtoupper((string) $type['rejection_type'])) ?>">
                                                            <?php foreach ($rejectionResult as $reject) {
                                                                 if ($type['rejection_type'] == $reject['rejection_type']) { ?>
                                                                      <option
                                                                           value="<?php echo $reject['rejection_reason_id']; ?>" <?= $pfSel('reason_for_sample_rejection', $reject['rejection_reason_id']) ?>>
                                                                           <?= $e($reject['rejection_reason_name']) ?>
                                                                      </option>
                                                            <?php }
                                                            } ?>
                                                       </optgroup>
                                                  <?php }
                                                  if ($general->isLISInstance() === false) { ?>
                                                       <option value="other">
                                                            <?= _translate("Other (Please Specify)"); ?>
                                                       </option>
                                                  <?php } ?>
                                             </select>
                                             <input type="text" class="form-control newRejectionReason"
                                                  name="newRejectionReason" id="newRejectionReason"
                                                  placeholder="<?php echo _translate('Rejection Reason'); ?>"
                                                  title="<?php echo _translate('Please enter rejection reason'); ?>"
                                                  style="width:100%;display:none;margin-top:2px;">
                                        </div>
                                   </div>
                                   <div class="col-md-6 rejectionReason" style="display:none;">
                                        <label class="col-lg-5 control-label labels"
                                             for="rejectionDate"><?= _translate("Rejection Date"); ?>
                                        </label>
                                        <div class="col-lg-7">
                                             <input class="form-control date rejection-date" type="text"
                                                  name="rejectionDate" id="rejectionDate" value="<?= $pf('rejection_on') ?>"
                                                  placeholder="<?php echo _translate('Select Rejection Date'); ?>"
                                                  title="<?php echo _translate('Please select rejection date'); ?>" />
                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6">
                                        <label class="col-lg-5 control-label labels"
                                             for="sampleTestingDateAtLab"><?= _translate("Sample Testing Date"); ?>
                                             <span class="mandatory result-span">*</span></label>
                                        <div class="col-lg-7">
                                             <input type="text"
                                                  class="form-control result-fields dateTime"
                                                  id="sampleTestingDateAtLab"
                                                  name="sampleTestingDateAtLab" value="<?= $pf('sample_tested_datetime') ?>"
                                                  placeholder="<?php echo _translate('Sample Testing Date'); ?>"
                                                  title="<?php echo _translate('Please select sample testing date'); ?>"
                                                  onchange="checkSampleTestingDate();" disabled />
                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6 vlResult">
                                        <label class="col-lg-5 control-label labels"
                                             for="resultDispatchedOn"><?= _translate("Date Results Dispatched"); ?></label>
                                        <div class="col-lg-7">
                                             <input type="text" class="form-control dateTime"
                                                  id="resultDispatchedOn" name="resultDispatchedOn" value="<?= $pf('result_dispatched_datetime') ?>"
                                                  placeholder="<?php echo _translate('Result Dispatch Date'); ?>"
                                                  title="<?php echo _translate('Please select result dispatched date'); ?>" />
                                        </div>
                                   </div>
                                   <?php if ($showSubTestPicker) { ?>
                                        <div class="col-md-6 vlResult subTestFields">
                                             <label class="col-lg-5 control-label labels"
                                                  for="subTestResult"><?= _translate("Tests Performed"); ?></label>
                                             <div class="col-lg-7">
                                                  <select class="form-control ms-container multiselect"
                                                       id="subTestResult" name="subTestResult[]"
                                                       title="<?php echo _translate('Please select sub tests'); ?>"
                                                       multiple onchange="loadSubTests();">
                                                  </select>
                                             </div>
                                        </div>
                                   <?php } ?>
                              </div>
                         <?php } // end empty($multiTestResults): legacy single-result fields are not rendered in multi-test mode 
                         ?>
                         <?php if (count($reasonForFailure) > 0) { ?>
                              <div class="row">
                                   <div class="col-md-6" style="display: none;">
                                        <label class="col-lg-5 control-label"
                                             for="reasonForFailure"><?= _translate("Reason for Failure"); ?>
                                             <span class="mandatory">*</span> </label>
                                        <div class="col-lg-7">
                                             <select name="reasonForFailure" id="reasonForFailure"
                                                  class="form-control"
                                                  title="<?php echo _translate('Please choose reason for failure'); ?>"
                                                  style="width: 100%;">
                                                  <?= $general->generateSelectOptions($reasonForFailure, null, '-- Select --'); ?>
                                             </select>
                                        </div>
                                   </div>
                              </div>
                         <?php } ?>
                         <div class="subTestResultSection" id="resultSection">

                         </div>
                         <?php
                         // TB-style multi-test Test Section (only when the page opts in, i.e. the
                         // result-update page). Replaces the single-result widgets above.
                         if (!empty($multiTestResults)) {
                              include __DIR__ . '/_test-section.php';
                         }
                         ?>
                         <?php // Page-level Reviewed/Tested/Approved By are the legacy single-result
                         // widgets. In multi-test mode review/approval is captured PER Test on the
                         // cards, and the helper derives the sample row from the last Test, so these
                         // are not rendered (and are never read by the save helper) here.
                         if (empty($multiTestResults)) { ?>
                              <div class="row">
                                   <div class="col-md-6">
                                        <label class="col-lg-5 control-label"
                                             for="reviewedBy"><?= _translate("Reviewed By"); ?> <span
                                                  class="mandatory review-approve-span"
                                                  style="display: none;">*</span> </label>
                                        <div class="col-lg-7">
                                             <select name="reviewedBy" id="reviewedBy"
                                                  class="select2 form-control labels"
                                                  title="<?php echo _translate('Please choose reviewed by'); ?>"
                                                  style="width: 100%;">
                                                  <?= $general->generateSelectOptions($userInfo, $pfRaw('result_reviewed_by'), '-- Select --'); ?>
                                             </select>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <label class="col-lg-5 control-label labels"
                                             for="reviewedOn"><?= _translate("Reviewed On"); ?> <span
                                                  class="mandatory review-approve-span"
                                                  style="display: none;">*</span> </label>
                                        <div class="col-lg-7">
                                             <input type="text" name="reviewedOn" id="reviewedOn" value="<?= $pf('result_reviewed_datetime') ?>"
                                                  class="dateTime form-control"
                                                  placeholder="<?php echo _translate('Reviewed on'); ?>"
                                                  title="<?php echo _translate('Please enter the Reviewed on'); ?>" />
                                        </div>
                                   </div>
                              </div>
                              <div class="row">
                                   <div class="col-md-6">
                                        <label class="col-lg-5 control-label labels"
                                             for="testedBy"><?= _translate("Tested By"); ?> </label>
                                        <div class="col-lg-7">
                                             <select name="testedBy" id="testedBy"
                                                  class="select2 form-control"
                                                  title="<?php echo _translate('Please choose approved by'); ?>">
                                                  <?= $general->generateSelectOptions($userInfo, $pfRaw('tested_by'), '-- Select --'); ?>
                                             </select>
                                        </div>
                                   </div>
                                   <div class="col-md-6">
                                        <label class="col-lg-5 control-label labels"
                                             for="approvedBy"><?= _translate("Approved By"); ?> <span
                                                  class="mandatory review-approve-span"
                                                  style="display: none;">*</span> </label>
                                        <div class="col-lg-7">
                                             <select name="approvedBy" id="approvedBy"
                                                  class="select2 form-control"
                                                  title="<?php echo _translate('Please choose approved by'); ?>">
                                                  <?= $general->generateSelectOptions($userInfo, $pfRaw('result_approved_by'), '-- Select --'); ?>
                                             </select>
                                        </div>
                                   </div>
                              </div>
                         <?php } // end legacy page-level review/approve rows (single-result only) 
                         ?>
                         <div class="row">
                              <?php if (empty($multiTestResults)) { ?>
                                   <div class="col-md-6">
                                        <label class="col-lg-5 control-label labels"
                                             for="approvedOn"><?= _translate("Approved On"); ?> <span
                                                  class="mandatory review-approve-span"
                                                  style="display: none;">*</span> </label>
                                        <div class="col-lg-7">
                                             <input type="text" value="<?= $pf('result_approved_datetime') ?>" class="form-control dateTime"
                                                  id="approvedOn"
                                                  title="<?php echo _translate('Please choose Approved On'); ?>"
                                                  name="approvedOn"
                                                  placeholder="<?= _translate("Please enter date"); ?>"
                                                  style="width:100%;" />
                                        </div>
                                   </div>
                              <?php } // end Approved On column (single-result only) 
                              ?>
                              <div class="col-md-6">
                                   <label class="col-lg-5 control-label labels"
                                        for="labComments"><?= _translate("Lab Tech. Comments"); ?>
                                   </label>
                                   <div class="col-lg-7">
                                        <textarea class="form-control" name="labComments"
                                             id="labComments"
                                             placeholder="<?php echo _translate('Lab comments'); ?>"
                                             title="<?php echo _translate('Please enter LabComments'); ?>"><?= $pf('lab_tech_comments') ?></textarea>
                                   </div>
                              </div>
                         </div>
                         <?php if ($showChangeReason) { ?>
                              <div class="row change-reason reasonForResultChanges"
                                   style="display:none;">
                                   <div class="col-md-12">
                                        <label class="control-label"
                                             for="reasonForResultChanges"><?= _translate("Reason For Changes in Result"); ?>
                                             <span class="mandatory">*</span></label>
                                        <textarea class="form-control" name="reasonForResultChanges"
                                             id="reasonForResultChanges"
                                             placeholder="<?php echo _translate('Enter Reason For Result Changes'); ?>"
                                             title="<?php echo _translate('Please enter reason for result changes'); ?>"><?= htmlspecialchars((string) ($latestChangeReason ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                   </div>
                              </div>
                         <?php } ?>
                         <div class="row" id="labSection">
                         </div>
                    </div>
               <?php } ?>
               </div>
               <div class="box-footer">
                    <!-- BARCODESTUFF START -->
                    <?php if ($showBarcode && isset($global['bar_code_printing']) && $global['bar_code_printing'] == 'zebra-printer') { ?>
                         <div id="printer_data_loading" style="display:none"><span
                                   id="loading_message"><?= _translate("Loading Printer Details..."); ?></span><br />
                              <div class="progress" style="width:100%">
                                   <div class="progress-bar progress-bar-striped active"
                                        role="progressbar" aria-valuenow="100" aria-valuemin="0"
                                        aria-valuemax="100" style="width: 100%">
                                   </div>
                              </div>
                         </div> <!-- /printer_data_loading -->
                         <div id="printer_details" style="display:none">
                              <span
                                   id="selected_printer"><?= _translate("No printer selected!"); ?></span>
                              <button type="button" class="btn btn-success"
                                   onclick="changePrinter()"><?= _translate("Change/Retry"); ?></button>
                         </div><br /> <!-- /printer_details -->
                         <div id="printer_select" style="display:none">
                              <?= _translate("Zebra Printer Options"); ?><br />
                              <?= _translate("Printer"); ?>: <select id="printers"></select>
                         </div> <!-- /printer_select -->
                    <?php } ?>
                    <!-- BARCODESTUFF END -->
                    <a class="btn btn-primary btn-disabled" href="javascript:void(0);"
                         onclick="validateNow();return false;"><?= _translate("Save"); ?></a>
                    <input type="hidden" name="saveNext" id="saveNext" />
                    <input type="hidden" name="sampleCodeTitle" id="sampleCodeTitle"
                         value="<?php echo $arr['sample_code']; ?>" />
                    <?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
                         <input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat"
                              value="<?php echo $sFormat; ?>" />
                         <input type="hidden" name="sampleCodeKey" id="sampleCodeKey"
                              value="<?php echo $sKey; ?>" />
                    <?php } ?>
                    <?php if ($formMode !== 'add') { ?>
                         <input type="hidden" name="revised" id="revised" value="no" />
                         <input type="hidden" name="isRemoteSample" id="isRemoteSample" value="<?= $pf('remote_sample') ?>" />
                         <input type="hidden" name="oldStatus" id="oldStatus" value="<?= $pf('result_status') ?>" />
                         <input type="hidden" name="reasonForResultChangesHistory" id="reasonForResultChangesHistory" value="<?= htmlspecialchars(base64_encode((string) json_encode($resultChangeHistory ?? [])), ENT_QUOTES, 'UTF-8') ?>" />
                    <?php } ?>
                    <?php if ($disableNonResult) { /* result mode: disabled non-result fields don't submit, mirror the ones the helper needs */ ?>
                         <input type="hidden" name="sampleCode" value="<?= $pf($sampleCode) ?>" />
                         <input type="hidden" name="artNo" value="<?= $pf('patient_id') ?>" />
                         <input type="hidden" name="labId" value="<?= $pf('lab_id') ?>" />
                         <input type="hidden" name="reasonForTesting" value="<?= $pf('reason_for_testing') ?>" />
                    <?php } ?>
                    <?php if ($showSaveNextClone) { ?>
                         <a class="btn btn-primary btn-disabled" href="javascript:void(0);"
                              onclick="validateSaveNow('next');return false;"><?= _translate("Save and Next"); ?></a>
                         <?php if (_isAllowed("/batch/add-batch.php?type=" . $_GET['type'])) { ?>
                              <a class="btn btn-primary btn-disabled" href="javascript:void(0);"
                                   onclick="validateSaveNow('clone');return false;"><?= _translate("Save and Clone"); ?></a>
                         <?php } ?>
                    <?php } ?>
                    <a href="<?= $cancelUrl ?>" class="btn btn-default">
                         <?= _translate("Cancel"); ?></a>
               </div>
     </div>
</div>
</div>
</div>
<input type="hidden" id="selectedSample" value="" name="selectedSample" class="" />
<input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />
<input type="hidden" name="vlSampleId" id="vlSampleId" value="<?= $pf('sample_id') ?>" />