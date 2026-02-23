# VL Data Dictionary (Privacy-First)

> Derived from `sql/init.sql`, `app/vl/requests/forms/*`, `app/vl/requests/*Helper.php`, `app/classes/Services/VlService.php`, `app/system/constants.php`.
>
> Classification: **PII** = must never appear in analytics | **Quasi** = use only bucketed/aggregated | **Safe** = safe for aggregate analytics

---

## Tables Relevant to VL

- **Primary write table:** `form_vl`
- **Lookup/reference tables (used directly in add/edit handlers):**
  - `r_sample_status` — sample lifecycle states
  - `r_vl_test_reasons` — reasons for VL testing
  - `r_vl_sample_rejection_reasons` — rejection reasons with type grouping
  - `r_vl_art_regimen` — ART regimen codes (hierarchical)
  - `r_vl_sample_type` — specimen types
  - `r_recommended_corrective_actions` — corrective actions for rejections
  - `facility_details` — facility master with geography
  - `geographical_divisions` — geographic hierarchy (province/district/country)
  - `instruments` — testing platforms/machines with VL limits
  - `lab_storage` — lab freezer/storage locations
- **Related tables (used in broader VL workflows, not directly in add/edit):**
  - `r_vl_test_failure_reasons` — test failure reasons (referenced by `reason_for_failure` FK)
  - `r_vl_results` — result values with interpretation (used in result import/processing)
  - `batch_details` — batch processing records (used in batch testing workflow)

### Analytics Reference Tables (`intelis_insights`)

The following lookup tables are copied into `intelis_insights` (safe columns only) so that `insights_ro` can resolve IDs to labels without access to `vlsm.*`:

| Analytics Table | Source | Key Columns |
|----------------|--------|-------------|
| `ref_sample_status` | `r_sample_status` | status_id, status_name, status |
| `ref_facilities` | `facility_details` | facility_id, facility_name, facility_code, vlsm_instance_id, facility_state_id, facility_district_id, facility_state, facility_district, facility_type, status, test_type |
| `ref_geographical_divisions` | `geographical_divisions` | geo_id, geo_name, geo_code, geo_parent, geo_status |
| `ref_vl_rejection_reasons` | `r_vl_sample_rejection_reasons` | rejection_reason_id, rejection_reason_name, rejection_type, rejection_reason_status, rejection_reason_code |
| `ref_vl_test_reasons` | `r_vl_test_reasons` | test_reason_id, test_reason_name, parent_reason, test_reason_status |
| `ref_vl_sample_types` | `r_vl_sample_type` | sample_id, sample_name, status |

**Excluded from `ref_facilities`:** contact_person, facility_emails, report_email, facility_mobile_numbers, address, latitude, longitude, facility_logo, sts_token, sts_token_expiry (PII/auth fields).

---

## Canonical Timestamp Fields

| Column | UI / Request Key | Meaning | Type | Classification |
|--------|-----------------|---------|------|----------------|
| `sample_collection_date` | `sampleCollectionDate` | When specimen was collected from patient | datetime | Quasi |
| `sample_dispatched_datetime` | `sampleDispatchedDate` | When specimen was dispatched from collection site | datetime | Quasi |
| `date_dispatched_from_clinic_to_lab` | — | When dispatched from clinic to lab (alternative field) | datetime | Quasi |
| `sample_received_at_hub_datetime` | `sampleReceivedAtHubOn` | When specimen reached intermediate transport hub | datetime | Quasi |
| `sample_received_at_lab_datetime` | `sampleReceivedDate` | When specimen received at testing lab | datetime | Quasi |
| `sample_tested_datetime` | `sampleTestingDateAtLab` | When lab performed VL test | datetime | Quasi |
| `sample_testing_date` | — | Alternative testing date (set by imports) | datetime | Quasi |
| `result_reviewed_datetime` | `reviewedOn` | When reviewer validated result | datetime | Quasi |
| `result_approved_datetime` | `approvedOnDateTime` | When approver approved result | datetime | Quasi |
| `result_dispatched_datetime` | `resultDispatchedOn` | When result was dispatched back to facility | datetime | Quasi |
| `result_printed_datetime` | — | When result was printed (set outside add/edit) | datetime | Quasi |
| `result_sms_sent_datetime` | — | When SMS notification was sent | datetime | Quasi |
| `result_printed_on_sts_datetime` | — | When printed on STS system | datetime | Quasi |
| `result_printed_on_lis_datetime` | — | When printed on LIS system | datetime | Quasi |
| `samples_referred_datetime` | — | When sample was referred to another lab | datetime | Quasi |
| `rejection_on` | `rejectionDate` | Date sample was rejected | date | Quasi |
| `request_created_datetime` | server-side | DB creation timestamp | timestamp | Quasi |
| `last_modified_datetime` | server-side | Last update timestamp | datetime | Quasi |
| `failed_test_date` | `failedTestDate` | When failed test occurred (PNG) | datetime | Quasi |

**Transform rules:** `DateUtility::isoDateFormat(value, true)` for datetime; `DateUtility::isoDateFormat(value)` for date.

---

## Key Derived/Controlled Fields

| Column | Meaning | Allowed Values / Derivation | Classification |
|--------|---------|----------------------------|----------------|
| `result_status` | Workflow lifecycle state | 1-13 via `constants.php` (see Status table below) | Safe |
| `is_sample_rejected` | Rejection flag | `yes` / `no` (enum) | Safe |
| `reason_for_sample_rejection` | Rejection reason ID | FK → `r_vl_sample_rejection_reasons` | Safe |
| `recommended_corrective_action` | Corrective action ID | FK → `r_recommended_corrective_actions` | Safe |
| `vl_result_category` | Clinical result category | Derived by `VlService::getVLResultCategory()` | Safe |
| `result` | Final normalized VL result | Produced by `processViralLoadResultFromForm()` | Quasi |
| `result_value_absolute` | Absolute VL copies/mL | Numeric string | Quasi |
| `result_value_log` | Log10 VL value | Numeric string (e.g., "3.5") | Safe |
| `result_value_text` | Text result | e.g., "Target Not Detected" | Safe |
| `result_value_hiv_detection` | HIV detection status | "HIV-1 Detected" / "HIV-1 Not Detected" | Safe |
| `reason_for_vl_testing` | Test reason ID | FK → `r_vl_test_reasons` | Safe |
| `facility_id` | Requesting facility | FK → `facility_details` | Safe |
| `lab_id` | Testing lab | FK → `facility_details` | Safe |
| `province_id` | Province/state ID | FK → `geographical_divisions` | Safe |
| `vlsm_country_id` | Country ID | integer | Safe |

---

## `r_sample_status` — Status Reference

| status_id | status_name | Code Constant |
|-----------|-------------|---------------|
| 1 | Hold | `SAMPLE_STATUS::ON_HOLD` |
| 2 | Lost | `SAMPLE_STATUS::LOST_OR_MISSING` |
| 3 | Sample Reordered | `SAMPLE_STATUS::REORDERED_FOR_TESTING` |
| 4 | Rejected | `SAMPLE_STATUS::REJECTED` |
| 5 | Failed/Invalid | `SAMPLE_STATUS::TEST_FAILED` |
| 6 | Sample Registered at Testing Lab | `SAMPLE_STATUS::RECEIVED_AT_TESTING_LAB` |
| 7 | Accepted | `SAMPLE_STATUS::ACCEPTED` |
| 8 | Awaiting Approval | `SAMPLE_STATUS::PENDING_APPROVAL` |
| 9 | Sample Currently Registered at Health Center | `SAMPLE_STATUS::RECEIVED_AT_CLINIC` |
| 10 | Expired | `SAMPLE_STATUS::EXPIRED` |
| 11 | No Result | `SAMPLE_STATUS::NO_RESULT` |
| 12 | Cancelled | `SAMPLE_STATUS::CANCELLED` |
| 13 | Sample Referred to another Lab | `SAMPLE_STATUS::REFERRED` |

---

## VL Result Category Logic

Determined by `VlService::getVLResultCategory($resultStatus, $finalResult)`:

| Condition | Category |
|-----------|----------|
| result_status = 4 (Rejected) | `rejected` |
| result_status = 5 (Failed/Invalid) | `invalid` |
| Result matches failure patterns (fail, error, invalid) | `failed` |
| result_status in (1, 2, 3, 10) — Hold/Lost/Reordered/Expired | `NULL` |
| Numeric result < suppression threshold (default 1000 cp/mL) | `suppressed` |
| Numeric result >= suppression threshold | `not suppressed` |
| Text matches suppressed patterns (TND, BDL, not detected, <20, <40) | `suppressed` |
| No result / empty | `NULL` |

Suppression threshold is configurable per instance (default 1000 cp/mL). Category is pre-computed and stored in `vl_result_category`.

---

## `form_vl` Full Column Inventory

### Identifiers & Sample Codes

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `vl_sample_id` | int AUTO_INCREMENT | PK, system-generated | PII |
| `unique_id` | varchar(256) | System-generated UUID | PII |
| `sample_code` | varchar(100) | Auto-generated lab code | PII |
| `remote_sample_code` | varchar(100) | Remote/STS sync code | PII |
| `external_sample_code` | varchar(100) | `$_POST['serialNo']` — "Recency ID" | PII |
| `facility_sample_id` | varchar(100) | Facility-level sample ID | PII |
| `lab_assigned_code` | varchar(32) | `$_POST['labAssignedCode']` | PII |
| `app_sample_code` | varchar(100) | Application sample code | PII |
| `sample_code_key` | int | Sequence for code generation | PII |
| `sample_code_format` | varchar(100) | Code format pattern (MMYY, YY) | Safe |
| `remote_sample_code_key` | int | Remote code sequence | PII |
| `remote_sample_code_format` | varchar(100) | Remote code format | Safe |
| `vlsm_instance_id` | varchar(100) | `$instanceId` — installation ID | Safe |
| `cv_number` | varchar(20) | `$_POST['cvNumber']` — country ref number | PII |

### Patient Demographics

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `system_patient_code` | varchar(43) | `$systemGeneratedCode` | PII |
| `patient_first_name` | varchar(100) | `$_POST['patientFirstName']` (encrypted if PII enabled) | PII |
| `patient_middle_name` | varchar(100) | `$_POST['patientMiddleName']` | PII |
| `patient_last_name` | varchar(100) | `$_POST['patientLastName']` | PII |
| `patient_responsible_person` | text | Guardian/responsible person | PII |
| `patient_art_no` | varchar(100) | `$_POST['artNo']` — ART registration number | PII |
| `patient_other_id` | text | Other patient identifier | PII |
| `patient_anc_no` | varchar(100) | Antenatal care number | PII |
| `patient_dob` | date | `$_POST['dob']` — Date of Birth | PII |
| `patient_age_in_years` | int | `$_POST['ageInYears']` | PII |
| `patient_age_in_months` | int | `$_POST['ageInMonths']` | PII |
| `patient_below_five_years` | varchar(255) | Below-5 flag | PII |
| `patient_gender` | varchar(10) | `$_POST['gender']` — male/female/unreported | PII |
| `patient_nationality` | int | Nationality reference ID | PII |
| `patient_mobile_number` | varchar(20) | `$_POST['patientPhoneNumber']` | PII |
| `patient_location` | text | Location description | PII |
| `patient_address` | mediumtext | Full address | PII |
| `patient_province` | text | Patient province (free text) | PII |
| `patient_district` | text | Patient district (free text) | PII |
| `patient_group` | text | Patient group/population category | PII |
| `key_population` | varchar(10) | `$_POST['keyPopulation']` | PII |
| `health_insurance_code` | varchar(32) | `$_POST['healthInsuranceCode']` | PII |
| `is_encrypted` | varchar(10) | `'no'` default — PII encryption flag | Safe |

### Facility & Geography

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `facility_id` | int | `$_POST['facilityId']` — Clinic/Health Center | Safe |
| `province_id` | int | Province/state dropdown | Safe |
| `vlsm_country_id` | int | `$formId` — Country ID | Safe |
| `lab_id` | int | `$_POST['labId']` — Testing Lab | Safe |
| `lab_code` | int | Laboratory code | Safe |
| `lab_name` | text | Lab name (denormalized) | Safe |
| `referring_lab_id` | int | Referring lab FK | Safe |
| `requesting_facility_id` | int | Requesting facility FK | Safe |
| `ward` | varchar(100) | Hospital ward | Safe |

### Sample & Specimen

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `specimen_type` | int | `$_POST['specimenType']` — FK → r_vl_sample_type | Safe |
| `sample_reordered` | varchar(3) | `$_POST['sampleReordered']` — yes/no | Safe |
| `community_sample` | varchar(10) | `$_POST['communitySample']` — yes/no | Safe |
| `location_of_sample_collection` | varchar(20) | `$_POST['locationOfSampleCollection']` | Safe |
| `sample_batch_id` | varchar(11) | `$_POST['batchNo']` — FK → batch_details | Safe |
| `sample_package_id` | int | Package ID for transport | Safe |
| `sample_package_code` | varchar(64) | Package code for transport | Safe |
| `sample_to_transport` | text | `$_POST['typeOfSample']` (PNG) | Safe |

### Status & Results

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `result_status` | int NOT NULL | `$resultStatus` — FK → r_sample_status (1-13) | Safe |
| `vl_result_category` | varchar(32) | `VlService::getVLResultCategory()` | Safe |
| `is_sample_rejected` | enum('yes','no') | `$isRejected` | Safe |
| `reason_for_sample_rejection` | int | `$_POST['rejectionReason']` — FK | Safe |
| `recommended_corrective_action` | int | `$_POST['correctiveAction']` — FK | Safe |
| `reason_for_failure` | int | `$_POST['reasonForFailure']` — FK | Safe |
| `result_value_log` | varchar(32) | `$logVal` — log10 | Safe |
| `result_value_absolute` | varchar(32) | `$absVal` — copies/mL | Quasi |
| `result_value_absolute_decimal` | varchar(255) | `$absDecimalVal` | Quasi |
| `result_value_text` | text | `$txtVal` | Safe |
| `result_value_hiv_detection` | varchar(32) | `$hivDetection` | Safe |
| `result` | text | `$finalResult` | Quasi |
| `cphl_vl_result` | varchar(32) | `$_POST['cphlVlResult']` (PNG) | Safe |
| `result_modified` | varchar(3) | `'no'` default; `'yes'` on edit | Safe |
| `reason_for_result_changes` | text | JSON audit trail | Quasi |
| `reason_for_vl_testing` | int | `$_POST['reasonForVLTesting']` — FK | Safe |
| `reason_for_vl_testing_other` | text | `$_POST['newreasonForVLTesting']` | Safe |
| `control_vl_testing_type` | text | Testing type context | Safe |

### Treatment & Clinical

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `is_patient_new` | varchar(45) | `$_POST['isPatientNew']` — yes/no | PII |
| `treatment_initiated_date` | date | `$_POST['dateOfArtInitiation']` | Quasi |
| `treatment_duration` | text | `$_POST['treatmentDuration']` | Safe |
| `treatment_duration_precise` | varchar(50) | `$_POST['treatmentDurationPrecise']` | Safe |
| `treatment_indication` | text | `$_POST['treatmentIndication']` | Safe |
| `treatment_details` | mediumtext | Additional details | Safe |
| `current_regimen` | text | `$_POST['artRegimen']` — FK | Safe |
| `current_arv_protocol` | text | ARV protocol | Safe |
| `line_of_treatment` | int | `$_POST['lineOfTreatment']` — 1/2/3 | Safe |
| `line_of_treatment_failure_assessed` | text | `$_POST['lineOfTreatmentFailureAssessed']` | Safe |
| `has_patient_changed_regimen` | varchar(45) | `$_POST['hasChangedRegimen']` — yes/no | PII |
| `reason_for_regimen_change` | text | `$_POST['reasonForArvRegimenChange']` | Quasi |
| `regimen_change_date` | date | `$_POST['dateOfArvRegimenChange']` | Quasi |
| `arv_adherance_percentage` | text | `$_POST['arvAdherence']` | Safe |
| `drug_substitution` | text | Drug substitution info | Safe |
| `coinfection_type` | text | Co-infection type | Safe |
| `sample_visit_type` | varchar(45) | Visit type | Safe |

### Pregnancy & Breastfeeding

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `is_patient_pregnant` | varchar(3) | `$_POST['patientPregnant']` — yes/no | PII |
| `no_of_pregnancy_weeks` | int | `$_POST['noOfPregnancyWeeks']` | Safe |
| `is_patient_breastfeeding` | varchar(3) | `$_POST['breastfeeding']` — yes/no | PII |
| `no_of_breastfeeding_weeks` | int | `$_POST['noOfBreastfeedingWeeks']` | Safe |
| `pregnancy_trimester` | int | `$_POST['trimester']` — 1/2/3 | Safe |
| `patient_has_active_tb` | varchar(3) | `$_POST['activeTB']` — yes/no | PII |
| `patient_active_tb_phase` | text | `$_POST['tbPhase']` | PII |

### CD4/CD8 & Prior VL Results

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `last_cd4_result` | varchar(50) | `$_POST['cd4Result']` | Safe |
| `last_cd4_percentage` | varchar(50) | `$_POST['cd4Percentage']` | Safe |
| `last_cd4_date` | date | `$_POST['cd4Date']` | Quasi |
| `last_cd8_result` | varchar(50) | `$_POST['cd8Result']` | Safe |
| `last_cd8_date` | varchar(50) | `$_POST['cd8Date']` | Quasi |
| `last_viral_load_date` | date | `$_POST['lastViralLoadTestDate']` | Quasi |
| `last_viral_load_result` | text | `$_POST['lastViralLoadResult']` | Safe |
| `last_vl_result_in_log` | text | Last VL in log | Safe |
| `last_vl_date_routine` | date | `$_POST['rmTestingLastVLDate']` | Quasi |
| `last_vl_result_routine` | text | `$_POST['rmTestingVlValue']` | Safe |
| `last_vl_sample_type_routine` | int | `$_POST['rmLastVLTestSampleType']` | Safe |
| `last_vl_date_failure_ac` | date | `$_POST['repeatTestingLastVLDate']` | Quasi |
| `last_vl_result_failure_ac` | text | `$_POST['repeatTestingVlValue']` | Safe |
| `last_vl_sample_type_failure_ac` | int | `$_POST['repeatLastVLTestSampleType']` | Safe |
| `last_vl_date_failure` | date | `$_POST['suspendTreatmentLastVLDate']` | Quasi |
| `last_vl_result_failure` | text | `$_POST['suspendTreatmentVlValue']` | Safe |
| `last_vl_sample_type_failure` | int | `$_POST['suspendLastVLTestSampleType']` | Safe |
| `last_vl_date_recency` | date | `$_POST['confirmRecencyTestingLastVLDate']` | Quasi |
| `last_vl_result_recency` | text | `$_POST['confirmRecencyTestingVlValue']` | Safe |
| `last_vl_date_ecd` | date | — | Quasi |
| `last_vl_result_ecd` | text | — | Safe |
| `last_vl_date_cf` | date | — | Quasi |
| `last_vl_result_cf` | text | — | Safe |
| `last_vl_date_if` | date | — | Quasi |
| `last_vl_result_if` | text | — | Safe |

### Personnel

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `request_clinician_name` | text | `$_POST['reqClinician']` | PII |
| `request_clinician_phone_number` | varchar(32) | `$_POST['reqClinicianPhoneNumber']` | PII |
| `request_created_by` | varchar(50) | `$_SESSION['userId']` | Quasi |
| `last_modified_by` | text | `$_SESSION['userId']` | Quasi |
| `tested_by` | varchar(50) | `$_POST['testedBy']` | Quasi |
| `result_approved_by` | varchar(50) | `$_POST['approvedBy']` | Quasi |
| `result_reviewed_by` | varchar(50) | `$_POST['reviewedBy']` | Quasi |
| `revised_by` | varchar(50) | — | Quasi |
| `revised_on` | datetime | — | Quasi |
| `lab_technician` | text | — | PII |
| `lab_contact_person` | text | — | PII |
| `lab_phone_number` | text | — | PII |
| `vl_focal_person` | text | `$_POST['vlFocalPerson']` | PII |
| `vl_focal_person_phone_number` | text | — | PII |
| `physician_name` | text | — | PII |
| `requesting_person` | text | — | PII |
| `requesting_phone` | text | — | PII |
| `sample_collected_by` | text | `$_POST['collectedBy']` | PII |

### Testing & Lab

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `vl_test_platform` | text | `$testingPlatform` (parsed with ## separator) | Safe |
| `instrument_id` | varchar(50) | `$instrumentId` (parsed from testingPlatform) | Safe |
| `import_machine_name` | int | Machine FK | Safe |
| `import_machine_file_name` | text | Import filename | Safe |
| `test_methods` | text | Testing methods | Safe |
| `lot_number` | text | Reagent lot number | Safe |
| `lot_expiration_date` | date | Reagent expiry | Quasi |
| `manual_result_entry` | varchar(10) | `'yes'` for manual entry | Safe |
| `locked` | varchar(10) | Record lock flag — yes/no | Safe |
| `test_urgency` | varchar(10) | Urgency of request | Safe |
| `vl_test_number` | text | `$_POST['viralLoadNo']` | Safe |

### Funding & Partners

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `funding_source` | int | `base64_decode($_POST['fundingSource'])` | Safe |
| `implementing_partner` | int | `base64_decode($_POST['implementingPartner'])` | Safe |
| `facility_support_partner` | text | — | Safe |

### Comments

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `approver_comments` | mediumtext | — | Quasi |
| `lab_tech_comments` | mediumtext | `$_POST['labComments']` | Quasi |
| `facility_comments` | mediumtext | — | Quasi |

### Data Exchange & Sync

| Column | Type | Classification |
|--------|------|----------------|
| `test_request_export` / `test_request_import` | int | Safe |
| `test_result_export` / `test_result_import` | int | Safe |
| `request_exported_datetime` / `request_imported_datetime` | datetime | Quasi |
| `result_exported_datetime` / `result_imported_datetime` | datetime | Quasi |
| `is_request_mail_sent` / `is_result_mail_sent` / `is_result_sms_sent` | varchar | Safe |
| `request_mail_datetime` / `result_mail_datetime` | datetime | Quasi |
| `consent_to_receive_sms` | text | PII |
| `source_of_request` | text | Safe |
| `source` | varchar(100) | Safe |
| `source_data_dump` | text | Quasi |
| `result_sent_to_source` / `result_sent_to_external` | varchar/text | Safe |
| `result_sent_to_source_datetime` / `result_sent_to_external_datetime` | datetime/text | Quasi |
| `result_pulled_via_api_datetime` | datetime | Quasi |
| `data_sync` / `vldash_sync` / `recency_sync` | int | Safe |
| `remote_sample` | varchar(10) | Safe |
| `recency_vl` | varchar(10) | Safe |
| `form_attributes` | json | Quasi |

### Plasma & PNG-Specific

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `plasma_conservation_temperature` | float | `$_POST['conservationTemperature']` | Safe |
| `plasma_conservation_duration` | text | `$_POST['durationOfConservation']` | Safe |
| `whole_blood_ml` / `whole_blood_vial` | text | `$_POST['wholeBloodOne/Two']` | Safe |
| `plasma_ml` / `plasma_vial` | text | `$_POST['plasmaOne/Two']` | Safe |
| `plasma_process_time` / `plasma_process_tech` | text | — | Safe / PII |
| `batch_quality` / `sample_test_quality` | text | — | Safe |
| `repeat_sample_collection` | text | — | Safe |
| `first_line` / `second_line` | varchar(32) | — | Safe |
| `failed_test_date` | datetime | `$_POST['failedTestDate']` | Quasi |
| `failed_test_tech` | varchar(100) | `$_POST['failedTestingTech']` | PII |
| `failed_vl_result` | varchar(32) | `$_POST['failedvlResult']` | Safe |
| `failed_batch_quality` / `failed_sample_test_quality` | varchar(32) | — | Safe |
| `failed_batch_id` | varchar(32) | `$_POST['failedbatchNo']` | Safe |
| `reason_for_failure` | int | `$_POST['reasonForFailure']` — FK | Safe |

### WHO/Clinical (PNG)

| Column | Type | Mapping Evidence | Classification |
|--------|------|-----------------|----------------|
| `art_cd_cells` | varchar(100) | `$_POST['cdCells']` | Safe |
| `art_cd_date` | date | `$_POST['cdDate']` | Quasi |
| `who_clinical_stage` | varchar(100) | `$_POST['clinicalStage']` | Safe |
| `clinic_date` / `report_date` / `requesting_date` | date | — | Quasi |
| `tech_name_png` / `qc_tech_name` / `qc_tech_sign` | text | — | PII |
| `qc_date` | text | — | Quasi |
| `reason_testing_png` | mediumtext | — | Safe |
| `contact_complete_status` | text | — | Safe |
| `sample_rejection_facility` | int | Facility that rejected | Safe |

---

## Reference Tables (Column-by-Column)

### `r_vl_test_reasons`

| Column | Type | Classification |
|--------|------|----------------|
| `test_reason_id` | int PK | Safe |
| `test_reason_name` | varchar(255) | Safe |
| `parent_reason` | int (default 0) | Safe |
| `test_reason_status` | varchar(45) — active/inactive | Safe |
| `updated_datetime` | datetime | Quasi |
| `data_sync` | int | Safe |

### `r_vl_sample_rejection_reasons`

| Column | Type | Classification |
|--------|------|----------------|
| `rejection_reason_id` | int PK | Safe |
| `rejection_reason_name` | varchar(255) | Safe |
| `rejection_type` | varchar(255) — general/whole blood/plasma/dbs/testing | Safe |
| `rejection_reason_status` | varchar(255) — active/inactive | Safe |
| `rejection_reason_code` | varchar(255) | Safe |
| `updated_datetime` | datetime | Quasi |
| `data_sync` | int | Safe |

### `r_vl_art_regimen`

| Column | Type | Classification |
|--------|------|----------------|
| `art_id` | int PK | Safe |
| `art_code` | varchar(255) — regimen code | Safe |
| `parent_art` | int — parent grouping | Safe |
| `headings` | varchar(255) — dropdown section titles | Safe |
| `art_status` | varchar(45) — active/inactive | Safe |

### `r_vl_sample_type`

| Column | Type | Classification |
|--------|------|----------------|
| `sample_id` | int PK | Safe |
| `sample_name` | varchar(255) — e.g., Plasma, Venous blood | Safe |
| `status` | varchar(45) — active/inactive | Safe |

### `r_vl_test_failure_reasons`

| Column | Type | Classification |
|--------|------|----------------|
| `failure_id` | int PK | Safe |
| `failure_reason` | varchar(256) | Safe |
| `status` | varchar(256) — active/inactive | Safe |

### `r_vl_results`

| Column | Type | Classification |
|--------|------|----------------|
| `result_id` | int PK | Safe |
| `result` | varchar(255) | Safe |
| `interpretation` | varchar(25) — suppressed/not suppressed | Safe |
| `available_for_instruments` | json | Safe |

### `facility_details` (VL-relevant columns)

| Column | Type | Classification |
|--------|------|----------------|
| `facility_id` | int PK | Safe |
| `facility_name` | varchar(255) | Safe |
| `facility_code` | varchar(255) | Safe |
| `country` | varchar(255) | Safe |
| `facility_state` / `facility_state_id` | varchar / int | Safe |
| `facility_district` / `facility_district_id` | varchar / int | Safe |
| `facility_type` | int | Safe |
| `status` | varchar(255) — active/inactive | Safe |

### `geographical_divisions`

| Column | Type | Classification |
|--------|------|----------------|
| `geo_id` | int PK | Safe |
| `geo_name` | varchar(255) | Safe |
| `geo_code` | varchar(255) | Safe |
| `geo_parent` | varchar — parent node ID | Safe |
| `geo_status` | varchar — active/inactive | Safe |

### `instruments`

| Column | Type | Classification |
|--------|------|----------------|
| `instrument_id` | varchar PK | Safe |
| `machine_name` | varchar — platform name | Safe |
| `lab_id` | int — owning lab | Safe |
| `lower_limit` / `higher_limit` | int — VL detection limits | Safe |
| `status` | varchar — active/inactive | Safe |

### `lab_storage`

| Column | Type | Classification |
|--------|------|----------------|
| `storage_id` | char PK | Safe |
| `storage_code` | varchar — freezer label | Safe |
| `lab_id` | int | Safe |
| `storage_status` | varchar — active/inactive | Safe |
