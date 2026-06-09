<?php

/**
 * Optional "Advanced Configuration" for a Custom Test type. Collapsed by default.
 *
 * Toggles a few NON-dynamic (hard-coded) fields on the request form and overrides
 * the Patient ID field label. Stored under test_results_config['advancedFormConfig'].
 * The inputs POST as resultConfig[advancedFormConfig][...], so the existing save
 * helpers (which json_encode $_POST['resultConfig'] into test_results_config) and
 * export/import carry it with NO extra wiring.
 *
 * Defaults (no saved config): every field shown, Patient ID label unchanged -- so an
 * existing test type with no advancedFormConfig behaves exactly as before.
 *
 * Expects (optional): $testResultAttribute = json_decode(test_results_config) of the
 * test type being edited/cloned/imported; unset on the add form (pure defaults).
 */

$advCfg = (isset($testResultAttribute['advancedFormConfig']) && is_array($testResultAttribute['advancedFormConfig']))
    ? $testResultAttribute['advancedFormConfig'] : [];
// A toggle is ON unless explicitly saved as 'no' (so missing keys default to shown).
$advChecked = static fn(string $k): string => (($advCfg[$k] ?? 'yes') !== 'no') ? 'checked' : '';
// Empty = no override (the request form keeps its existing default label). Only a
// value here relabels the Patient ID field.
$advLabel = (isset($advCfg['patientIdLabel']) && trim((string) $advCfg['patientIdLabel']) !== '')
    ? (string) $advCfg['patientIdLabel'] : '';
?>
<style>
    #advancedConfigBox .box-body {
        padding: 16px 20px 20px;
    }

    #advancedConfigBox .adv-intro {
        color: #8a939b;
        font-size: 12.5px;
        margin: 0 0 18px;
    }

    #advancedConfigBox .adv-section-title {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .6px;
        text-transform: uppercase;
        color: #9aa3ab;
        margin: 0 0 12px;
        padding-bottom: 7px;
        border-bottom: 1px solid #eceff1;
    }

    #advancedConfigBox .adv-toggle {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 0 8px;
        padding: 9px 12px;
        border: 1px solid #e7ebee;
        border-radius: 5px;
        font-weight: 400 !important;
        cursor: pointer;
        transition: background .15s ease, border-color .15s ease;
    }

    #advancedConfigBox .adv-toggle:hover {
        background: #f6f9fb;
        border-color: #cfdae3;
    }

    #advancedConfigBox .adv-toggle input[type="checkbox"] {
        margin: 0;
        width: 16px;
        height: 16px;
        flex: 0 0 auto;
        cursor: pointer;
    }

    #advancedConfigBox .adv-toggle .adv-toggle-text {
        font-size: 14px;
        color: #3a4149;
        line-height: 1.25;
    }

    #advancedConfigBox .adv-field {
        margin: 16px 0 0;
        max-width: 340px;
    }

    #advancedConfigBox .adv-field>label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #5a636b;
        margin-bottom: 5px;
    }
</style>
<div class="box box-primary collapsed-box" id="advancedConfigBox" style="margin-top:0;">
    <div class="box-header with-border">
        <h3 class="box-title"><?= _translate("Advanced Configuration"); ?>
            <small class="text-muted">&nbsp;&middot;&nbsp;<?= _translate("optional"); ?></small>
        </h3>
        <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool" data-widget="collapse"
                title="<?= _translate('Expand / collapse'); ?>">
                <i class="fa-solid fa-plus"></i>
            </button>
        </div>
    </div>
    <div class="box-body">
        <p class="adv-intro">
            <?= _translate("Fine-tune a few standard (non-dynamic) request-form fields for this test. Leaving everything as-is keeps all fields visible with the default Patient ID label."); ?>
        </p>
        <div class="row">
            <div class="col-md-6">
                <div class="adv-section-title"><?= _translate("Clinic Section"); ?></div>
                <label class="adv-toggle">
                    <input type="hidden" name="resultConfig[advancedFormConfig][showImplementingPartner]" value="no">
                    <input type="checkbox" name="resultConfig[advancedFormConfig][showImplementingPartner]" value="yes" <?= $advChecked('showImplementingPartner') ?>>
                    <span class="adv-toggle-text"><?= _translate("Capture Implementing Partner"); ?></span>
                </label>
                <label class="adv-toggle">
                    <input type="hidden" name="resultConfig[advancedFormConfig][showFundingSource]" value="no">
                    <input type="checkbox" name="resultConfig[advancedFormConfig][showFundingSource]" value="yes" <?= $advChecked('showFundingSource') ?>>
                    <span class="adv-toggle-text"><?= _translate("Capture Funding Source"); ?></span>
                </label>
            </div>
            <div class="col-md-6">
                <div class="adv-section-title"><?= _translate("Patient Section"); ?></div>
                <label class="adv-toggle">
                    <input type="hidden" name="resultConfig[advancedFormConfig][showPatientName]" value="no">
                    <input type="checkbox" name="resultConfig[advancedFormConfig][showPatientName]" value="yes" <?= $advChecked('showPatientName') ?>>
                    <span class="adv-toggle-text"><?= _translate("Capture Patient Name"); ?></span>
                </label>
                <label class="adv-toggle">
                    <input type="hidden" name="resultConfig[advancedFormConfig][showLaboratoryNumber]" value="no">
                    <input type="checkbox" name="resultConfig[advancedFormConfig][showLaboratoryNumber]" value="yes" <?= $advChecked('showLaboratoryNumber') ?>>
                    <span class="adv-toggle-text"><?= _translate("Capture Laboratory Number"); ?></span>
                </label>
                <label class="adv-toggle">
                    <input type="hidden" name="resultConfig[advancedFormConfig][showPregnancy]" value="no">
                    <input type="checkbox" name="resultConfig[advancedFormConfig][showPregnancy]" value="yes" <?= $advChecked('showPregnancy') ?>>
                    <span class="adv-toggle-text"><?= _translate("Capture Pregnancy status"); ?></span>
                </label>
                <label class="adv-toggle">
                    <input type="hidden" name="resultConfig[advancedFormConfig][showBreastfeeding]" value="no">
                    <input type="checkbox" name="resultConfig[advancedFormConfig][showBreastfeeding]" value="yes" <?= $advChecked('showBreastfeeding') ?>>
                    <span class="adv-toggle-text"><?= _translate("Capture Breastfeeding status"); ?></span>
                </label>
                <label class="adv-toggle">
                    <input type="hidden" name="resultConfig[advancedFormConfig][showAgeInMonths]" value="no">
                    <input type="checkbox" name="resultConfig[advancedFormConfig][showAgeInMonths]" value="yes" <?= $advChecked('showAgeInMonths') ?>>
                    <span class="adv-toggle-text"><?= _translate("Capture Age in Months when Age is under 1 year"); ?></span>
                </label>
                <div class="adv-field">
                    <label for="advPatientIdLabel"><?= _translate("Patient ID field label"); ?></label>
                    <?php // .resultInputContainer gives the datalist-css dropdown its positioning + styling. ?>
                    <div class="resultInputContainer">
                        <input type="text" class="form-control input-sm" id="advPatientIdLabel"
                            name="resultConfig[advancedFormConfig][patientIdLabel]"
                            list="advPatientIdLabelPresets" value="<?= htmlspecialchars($advLabel, ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="<?= _translate('Default (EPID)'); ?>" autocomplete="off"
                            title="<?= _translate('Label for the Patient ID field on the request form. Leave blank for the default. Pick a preset or type your own.'); ?>">
                        <datalist id="advPatientIdLabelPresets">
                            <option value="EPID">EPID</option>
                            <option value="Patient ID">Patient ID</option>
                            <option value="Patient ART">Patient ART</option>
                        </datalist>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php // Styles the Patient ID label datalist consistently (the config pages don't load it otherwise). ?>
<script type="text/javascript"
    src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . '/assets/js/datalist-css.min.js') ?>"></script>
