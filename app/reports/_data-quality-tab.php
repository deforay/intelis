<?php

/**
 * The Data Quality tab, shared by every clinic report page. Set $dqTestType
 * and include it as the tab pane; clinic-reports.js and data-quality.js must
 * be on the page.
 *
 * Uses the host page's $state, $facilitiesDropdown (or $fResult),
 * $implementingPartnerList when it has one, and its getByProvince() and
 * getByDistrict() helpers.
 *
 * @var string $dqTestType
 */

use App\Utilities\DataQualityReportUtility;

$dqFacilityOptions = $facilitiesDropdown ?? '';
if ($dqFacilityOptions === '' && !empty($fResult)) {
    foreach ($fResult as $dqFacility) {
        $dqFacilityOptions .= '<option value="' . (int) $dqFacility['facility_id'] . '">'
            . htmlspecialchars((string) $dqFacility['facility_name'], ENT_QUOTES) . '</option>';
    }
}

$dqChecks = [];
foreach (DataQualityReportUtility::checks($dqTestType) as $dqKey => $dqCheck) {
    $dqChecks[] = ['key' => $dqKey, 'group' => $dqCheck['group'], 'label' => $dqCheck['label']];
}

$dqConfig = [
    'endpoint' => '/reports/get-data-quality.php',
    'testType' => $dqTestType,
    'locale' => str_replace('_', '-', (string) ($_SESSION['APP_LOCALE'] ?? 'en_US')),
    'checks' => $dqChecks,
    'labels' => [
        'loading' => _translate('Checking records...'),
        'error' => _translate('Unable to load the data quality report right now'),
        'noData' => _translate('No samples were collected in the selected period for these filters.'),
        'checked' => _translate('Samples Checked'),
        'complete' => _translate('Complete Records'),
        'incomplete' => _translate('Missing Key Fields'),
        'facilitiesAffected' => _translate('Facilities with Gaps'),
        'ofSamples' => _translate('%s of samples'),
        'ofFacilities' => _translate('of %s facilities'),
        'fields' => _translate('Key Fields'),
        'fieldsHint' => _translate('Share of samples where each field is blank or holds a placeholder such as NA or unknown. Lab fields are only expected once a sample reaches the lab.'),
        'groups' => [
            'patient' => _translate('Patient'),
            'sample' => _translate('Sample'),
            'lab' => _translate('Lab'),
        ],
        'allCaptured' => _translate('Captured on every sample'),
        'notExpected' => _translate('No samples at this stage yet'),
        'notCaptured' => _translate('Not recorded on any sample. Left out of the counts above.'),
        'ofExpected' => _translate('%s of %s samples missing'),
        'view' => _translate('View samples'),
        'facilities' => _translate('Gaps by Facility'),
        'facilitiesHint' => _translate('Click a count to list the samples behind it.'),
        'facility' => _translate('Facility'),
        'state' => _translate('Province/State'),
        'district' => _translate('District/County'),
        'samples' => _translate('Samples'),
        'withGaps' => _translate('With Gaps'),
        'completeRate' => _translate('% Complete'),
        'noFacility' => _translate('No facility recorded'),
        'noGaps' => _translate('Every key field was captured on every sample in this period.'),
        'listAll' => _translate('Samples missing any key field'),
        'listCheck' => _translate('Samples missing %s'),
        'atFacility' => _translate('at %s'),
        'showing' => _translate('Showing the %s most recent. Export for the full list.'),
        'close' => _translate('Close'),
        'export' => _translate('Export to Excel'),
        'exportFailed' => _translate('Unable to generate the excel file'),
        'sampleCode' => _translate('Sample ID'),
        'patientId' => _translate('Patient ID'),
        'collected' => _translate('Sample Collection Date'),
        'requestedOn' => _translate('Requested %s'),
        'status' => _translate('Status'),
        'missingFields' => _translate('Missing Fields'),
        'edit' => _translate('Edit'),
        'selectProvince' => _translate('Select Province'),
        'selectDistrict' => _translate('Select District'),
        'selectFacilities' => _translate('Select Facilities'),
        'ranges' => [
            'last30' => _translate('Last 30 Days'),
            'last90' => _translate('Last 90 Days'),
            'last180' => _translate('Last 180 Days'),
            'thisMonth' => _translate('This Month'),
            'lastMonth' => _translate('Last Month'),
            'last12Months' => _translate('Last 12 Months'),
            'yearToDate' => _translate('Current Year To Date'),
            'previousYear' => _translate('Previous Year'),
            'apply' => _translate('Apply'),
            'cancel' => _translate('Cancel'),
            'custom' => _translate('Custom Range'),
        ],
    ],
];
?>
<div class="box box-default filter-panel filter-panel-collapsed">
    <div class="box-body pageFilters filter-panel-body">
        <div class="row">
            <div class="col-md-4 col-sm-6">
                <div class="form-group">
                    <label class="control-label" for="dqSampleCollectionDate"><?= _translate('Sample Collection Date'); ?></label>
                    <input type="text" id="dqSampleCollectionDate" name="dqSampleCollectionDate" class="form-control dqReportFilter" placeholder="<?= _htmlTranslate('Select Sample Collection Date'); ?>" readonly style="background:#fff;" />
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="form-group">
                    <label class="control-label" for="dqState"><?= _translate('Province/State'); ?></label>
                    <select class="form-control dqReportFilter" id="dqState" name="dqState" onchange="getByProvince('dqDistrict','dqFacilityName',this.value)" title="<?= _htmlTranslate('Please select Province/State'); ?>">
                        <?= $general->generateSelectOptions($state ?? [], null, _translate('-- Select --')); ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="form-group">
                    <label class="control-label" for="dqDistrict"><?= _translate('District/County'); ?></label>
                    <select class="form-control dqReportFilter" id="dqDistrict" name="dqDistrict" onchange="getByDistrict('dqFacilityName',this.value)" title="<?= _htmlTranslate('Please select District/County'); ?>">
                    </select>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="form-group">
                    <label class="control-label" for="dqFacilityName"><?= _translate('Facility Name'); ?></label>
                    <select class="form-control dqReportFilter" id="dqFacilityName" name="dqFacilityName" multiple="multiple" title="<?= _htmlTranslate('Please select facility name'); ?>">
                        <?= $dqFacilityOptions; ?>
                    </select>
                </div>
            </div>
            <?php if (!empty($implementingPartnerList)) { ?>
                <div class="col-md-4 col-sm-6">
                    <div class="form-group">
                        <label class="control-label" for="dqImplementingPartner"><?= _translate('Implementing Partner'); ?></label>
                        <select class="form-control dqReportFilter" id="dqImplementingPartner" name="dqImplementingPartner" title="<?= _htmlTranslate('Please choose implementing partner'); ?>">
                            <option value=""><?= _translate('-- Select --'); ?></option>
                            <?php foreach ($implementingPartnerList as $dqPartner) { ?>
                                <option value="<?= base64_encode((string) $dqPartner['i_partner_id']); ?>"><?= htmlspecialchars((string) $dqPartner['i_partner_name'], ENT_QUOTES); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
    <div class="box-footer filter-actions">
        <input type="button" value="<?= _htmlTranslate('Search'); ?>" class="filter-search btn btn-success btn-sm dq-search">
        <button type="button" class="btn btn-default btn-sm dq-reset"><span><?= _translate('Reset'); ?></span></button>
    </div>
</div>
<div class="dq-app" id="dataQualityApp" aria-live="polite"></div>
<script>
    $(function() {
        var dqConfig = <?= json_encode($dqConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
        ClinicReports.registerTab('dataQualityReport', {
            init: function() {
                DataQuality.init($('#dataQualityApp'), dqConfig);
            },
            search: function() {
                DataQuality.search();
            }
        });
    });
</script>
