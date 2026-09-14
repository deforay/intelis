<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\SampleStatusDetailsService;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var SampleStatusDetailsService $details */
$details = ContainerRegistry::get(SampleStatusDetailsService::class);

$req = $details->resolveRequest($_GET);
if (!SampleStatusDetailsService::canView($req['testType'])) {
    throw new SystemException(_translate('You do not have permission to access this page or resource.'), 403);
}

$statusName = _translate((string) $details->statusName($req['status']));
$testName = $req['testType'] === 'recency' ? _translate('Recency') : _translate('VL');
$heading = $testName . ' ' . _translate('Samples') . ': ' . $statusName;
// header.php prints the title without escaping it.
$title = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');

$columns = SampleStatusDetailsService::columns($req['status']);

// What the pie was filtered by, named rather than shown as ids.
$appliedFilters = [];
$f = $req['filters'];
if ($f['sampleCollectionDate'] !== '') {
    $appliedFilters[_translate('Sample Collection Date')] = $f['sampleCollectionDate'];
}
if ($f['sampleReceivedDateAtLab'] !== '') {
    $appliedFilters[_translate('Sample Received Date At Lab')] = $f['sampleReceivedDateAtLab'];
}
if ($f['sampleTestedDate'] !== '') {
    $appliedFilters[_translate('Sample Tested Date')] = $f['sampleTestedDate'];
}
if ($f['batchCode'] !== '') {
    $appliedFilters[_translate('Batch Code')] = $f['batchCode'];
}
if ((int) $f['sampleType'] > 0) {
    $row = $db->rawQueryOne("SELECT sample_name FROM r_vl_sample_type WHERE sample_id = ?", [(int) $f['sampleType']]);
    $appliedFilters[_translate('Sample Type')] = $row['sample_name'] ?? $f['sampleType'];
}
if ((int) $f['labName'] > 0) {
    $row = $db->rawQueryOne("SELECT facility_name FROM facility_details WHERE facility_id = ?", [(int) $f['labName']]);
    $appliedFilters[_translate('Testing Lab')] = $row['facility_name'] ?? $f['labName'];
}

require_once APPLICATION_PATH . '/header.php';

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<style>
    .ssd-filters {
        margin: 0;
        padding: 0;
        list-style: none;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .ssd-filters li {
        background: #f4f4f4;
        border: 1px solid #ddd;
        border-radius: 3px;
        padding: 2px 8px;
    }
</style>
<div class="content-wrapper">
    <section class="content-header">
        <h1><em class="fa-solid fa-list"></em>
            <?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?= _htmlTranslate("Home"); ?>
                </a></li>
            <li class="active">
                <?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?>
            </li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-body">
                        <button class="btn btn-success btn-sm pull-right" type="button" onclick="exportSampleStatusDetails();">
                            <em class="fa-solid fa-cloud-arrow-down"></em> <?= _htmlTranslate("Export to Excel"); ?>
                        </button>
                        <?php if ($appliedFilters === []) { ?>
                            <p class="text-muted"><?= _htmlTranslate('No filters applied.'); ?></p>
                        <?php } else { ?>
                            <ul class="ssd-filters">
                                <?php foreach ($appliedFilters as $label => $value) { ?>
                                    <li><strong><?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?>:</strong>
                                        <?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                        <div class="clearfix"></div>
                        <div class="table-responsive" style="margin-top:15px;">
                            <table class="table table-bordered table-striped" id="sampleStatusDetailsTable" aria-describedby="sampleStatusDetailsTable" style="width:100%;">
                                <thead>
                                    <tr>
                                        <?php foreach ($columns as $label) { ?>
                                            <th><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<script>
    const SSD_PARAMS = <?= json_encode(['testType' => $req['testType'], 'status' => $req['status']] + $req['filters'], $jsonFlags); ?>;
    const SSD_COLUMNS = <?= json_encode(array_keys($columns), $jsonFlags); ?>;
    const SSD_UNSORTABLE = ['patientId', 'released', 'turnaround', 'rejectionReason', 'rejectedBy', 'failureReason',
        'referringLab', 'testedBy', 'approvedBy', 'lastModifiedBy'];

    $(function () {
        $('#sampleStatusDetailsTable').DataTable({
            processing: true,
            serverSide: true,
            autoWidth: false,
            pageLength: 25,
            order: [],
            columns: SSD_COLUMNS.map(function (key) {
                return { orderable: SSD_UNSORTABLE.indexOf(key) === -1 };
            }),
            ajax: {
                url: '/reports/get-sample-status-details.php',
                type: 'POST',
                data: function (d) {
                    return $.extend(d, SSD_PARAMS);
                },
                dataSrc: function (json) {
                    if (json.error) {
                        alert(json.error);
                        return [];
                    }
                    return json.data;
                }
            }
        });
    });

    function exportSampleStatusDetails() {
        $.blockUI();
        $.post('/reports/get-sample-status-details.php', $.extend({ section: 'export' }, SSD_PARAMS))
            .done(function (data) {
                $.unblockUI();
                if (!data || String(data).indexOf('{') === 0) {
                    alert("<?= _jsTranslate('Unable to generate excel'); ?>");
                    return;
                }
                window.open('/download.php?f=' + data, '_blank');
            })
            .fail(function () {
                $.unblockUI();
                alert("<?= _jsTranslate('Unable to generate excel'); ?>");
            });
    }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
