<?php

use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = _translate("Move Manifest");

require_once APPLICATION_PATH . '/header.php';

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$module = (string) ($_GET['t'] ?? '');
try {
    TestsService::getTestTableName($module);
} catch (Throwable) {
    $module = '';
}

// Which lab a sample belongs to is decided on the STS, by an administrator.
$canMove = $module !== '' && $general->isSTSInstance() && !$general->isCloudLisNonAdmin();
$testingLabs = $canMove ? $facilitiesService->getTestingLabs($module) : [];
// From a manifest's Move button: that one manifest is looked up straight away.
$manifestCode = trim((string) ($_GET['code'] ?? ''));
// Pending: made, not sent yet. Dispatched: printed and sent to the lab.
// Received at lab: the lab has activated it.
$statusLabels = [
    'pending' => _translate("Pending"),
    'dispatch' => _translate("Dispatched"),
    'received' => _translate("Received at lab"),
];
?>
<div class="content-wrapper">
    <section class="content-header">
        <h1><em class="fa-solid fa-angles-right"></em> <?= _translate("Move Manifests"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
            <li><a href="/specimen-referral-manifest/view-manifests.php?t=<?= _sanitizeOutput($module); ?>">
                    <?= _translate("Manage Specimen Referral Manifest"); ?></a></li>
            <li class="active"><?= _translate("Move Manifests"); ?></li>
        </ol>
    </section>
    <section class="content">
        <?php if (!$canMove) { ?>
            <div class="alert alert-warning">
                <?= _translate("Manifests can only be moved by an administrator on the Sample Tracking System"); ?>
            </div>
        <?php } else { ?>
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><em class="fa-solid fa-search"></em> <?= _translate("Find Manifests"); ?></h3>
                </div>
                <div class="box-body">
                    <div class="btn-group" role="group" id="findBy" style="margin-bottom:15px;"
                        aria-label="<?= _translate('How to find the manifests', 'html'); ?>">
                        <button type="button" class="btn btn-default" data-find="code">
                            <em class="fa-solid fa-barcode"></em> <?= _translate("Move one manifest"); ?>
                        </button>
                        <button type="button" class="btn btn-default" data-find="lab">
                            <em class="fa-solid fa-list"></em> <?= _translate("List a lab's manifests"); ?>
                        </button>
                    </div>
                    <div class="row" id="findByCode">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="manifestCode"><?= _translate("Manifest Code"); ?></label>
                                <input type="text" class="form-control" id="manifestCode"
                                    value="<?= _sanitizeOutput($manifestCode); ?>"
                                    title="<?= _translate('Enter the code of the manifest to move', 'html'); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row" id="findByLab" style="display:none;">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="testingLab"><?= _translate("Testing Lab"); ?></label>
                                <select class="form-control" id="testingLab" style="width:100%;"
                                    title="<?= _translate("Choose the testing lab the manifests are at", 'html'); ?>">
                                    <?= $general->generateSelectOptions($testingLabs, null, _translate('-- Select --')); ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="daterange"><?= _translate("Manifest Date"); ?></label>
                                <input type="text" class="form-control" id="daterange" readonly
                                    style="background-color:#fff;cursor:pointer;"
                                    placeholder="<?= _translate('Last 6 months', 'html'); ?>"
                                    title="<?= _translate('Choose the date range the manifests were created in', 'html'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box-footer text-center">
                    <button type="button" class="btn btn-primary" onclick="findManifests();">
                        <em class="fa-solid fa-search"></em> <?= _translate("Search"); ?>
                    </button>
                </div>
            </div>

            <form method="post" id="moveManifestForm" autocomplete="off" action="moveSpecimenManifestCodeHelper.php">
                <input type="hidden" name="testType" value="<?= _sanitizeOutput($module); ?>">
                <input type="hidden" name="receivedConfirmed" id="receivedConfirmed" value="">
                <div class="box box-success" id="moveSection" style="display:none;">
                    <div class="box-header with-border">
                        <h3 class="box-title"><em class="fa-solid fa-exchange"></em> <?= _translate("Move Selected Manifests"); ?></h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted">
                            <?= _translate("A manifest with any tested sample cannot be moved. The lab the samples are taken from is asked to cancel its copies."); ?>
                        </p>
                        <table id="manifestTable" class="table table-bordered table-striped" aria-describedby="moveSection">
                            <thead>
                                <tr>
                                    <th scope="col"><input type="checkbox" id="selectAll" title="<?= _translate('Select all', 'html'); ?>"></th>
                                    <th scope="col"><?= _translate("Manifest Code"); ?></th>
                                    <th scope="col"><?= _translate("Testing Lab"); ?></th>
                                    <th scope="col"><?= _translate("Created On"); ?></th>
                                    <th scope="col"><?= _translate("Status"); ?></th>
                                    <th scope="col"><?= _translate("Samples"); ?></th>
                                </tr>
                            </thead>
                            <tbody id="manifestRows"></tbody>
                        </table>
                        <div class="row form-horizontal" style="margin-top:20px;">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="assignLab" class="col-lg-5 control-label">
                                        <?= _translate("Move to Testing Lab"); ?> <span class="mandatory">*</span>
                                    </label>
                                    <div class="col-lg-7">
                                        <select class="form-control isRequired" id="assignLab" name="assignLab" style="width:100%;"
                                            title="<?= _translate("Choose the testing lab the manifests are moving to", 'html'); ?>">
                                            <?= $general->generateSelectOptions($testingLabs, null, _translate('-- Select --')); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="reasonForChange" class="col-lg-5 control-label">
                                        <?= _translate("Reason for Moving"); ?> <span class="mandatory">*</span>
                                    </label>
                                    <div class="col-lg-7">
                                        <textarea class="form-control isRequired" id="reasonForChange" name="reasonForChange" rows="3"
                                            title="<?= _translate("Enter the reason for moving the selected manifests", 'html'); ?>"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer text-center">
                        <button type="button" class="btn btn-success" id="moveButton" onclick="moveManifests();" disabled>
                            <em class="fa-solid fa-exchange"></em> <?= _translate("Move"); ?>
                        </button>
                        <a href="view-manifests.php?t=<?= _sanitizeOutput($module); ?>" class="btn btn-default">
                            <em class="fa-solid fa-times"></em> <?= _translate("Cancel"); ?>
                        </a>
                    </div>
                </div>
            </form>
        <?php } ?>
    </section>
</div>
<?php if ($canMove) { ?>
    <script src="/assets/js/moment.min.js"></script>
    <script type="text/javascript" src="<?= _asset('/assets/plugins/daterangepicker/daterangepicker.js') ?>"></script>
    <script type="text/javascript">
        const statusLabels = <?= json_encode($statusLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        // How the manifests are found: 'code' (one manifest) or 'lab' (a lab's list).
        let findingBy = 'code';

        $(document).ready(function() {
            $('#testingLab, #assignLab').select2({
                width: '100%',
                placeholder: "<?= _translate('-- Select --', true); ?>"
            });
            $('#daterange').daterangepicker({
                locale: {
                    cancelLabel: "<?= _translate('Clear', true); ?>",
                    format: 'DD-MMM-YYYY',
                    separator: ' to '
                },
                showDropdowns: true,
                autoUpdateInput: false,
                maxDate: moment()
            }, function(start, end) {
                $('#daterange').val(start.format('DD-MMM-YYYY') + ' to ' + end.format('DD-MMM-YYYY'));
            }).on('cancel.daterangepicker', function() {
                $(this).val('');
            });
            $('#selectAll').on('change', function() {
                manifestBoxes().prop('checked', this.checked);
                updateMoveButton();
            });
            $('#manifestRows').on('change', 'input:checkbox', updateMoveButton);
            // One manifest by its code, or the list of one lab's manifests.
            $('#findBy').on('click', 'button', function() {
                findBy($(this).data('find'));
            });
            findBy('code');
            $('#manifestCode').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    findManifests();
                }
            });
            // The lab the manifests are at is not a place to move them to.
            const destinations = $('#assignLab option').clone();
            $('#testingLab').on('change', function() {
                const from = $(this).val();
                const kept = $('#assignLab').val();
                $('#assignLab').empty().append(destinations.clone().filter(function() {
                    return this.value === '' || this.value !== from;
                }));
                $('#assignLab').val(kept !== from ? kept : '').trigger('change');
            });
            if ($('#manifestCode').val().trim() !== '') {
                findManifests();
            }
        });

        // Every row of the list, including those a search is hiding: a search
        // takes them out of the page, so they are reached through the table.
        function manifestBoxes() {
            if ($.fn.DataTable.isDataTable('#manifestTable')) {
                return $($('#manifestTable').DataTable().rows().nodes()).find('input:checkbox');
            }
            return $('#manifestRows input:checkbox');
        }

        function updateMoveButton() {
            $('#moveButton').prop('disabled', manifestBoxes().filter(':checked').length === 0);
        }

        function findBy(how) {
            findingBy = how;
            $('#findBy button').each(function() {
                const on = $(this).data('find') === how;
                $(this).toggleClass('btn-primary', on).toggleClass('btn-default', !on).attr('aria-pressed', on);
            });
            $('#findByCode').toggle(how === 'code');
            $('#findByLab').toggle(how === 'lab');
            if (how === 'code') {
                $('#manifestCode').trigger('focus');
            }
        }

        function findManifests() {
            const byCode = findingBy === 'code';
            const code = byCode ? $('#manifestCode').val().trim() : '';
            const lab = $('#testingLab').val();
            if (byCode && code === '') {
                alert("<?= _translate('Please enter the manifest code', true); ?>");
                return;
            }
            if (!byCode && !lab) {
                alert("<?= _translate('Please choose the testing lab', true); ?>");
                return;
            }
            $.blockUI();
            $.post('/specimen-referral-manifest/get-manifest-package-code.php', {
                module: <?= json_encode($module); ?>,
                manifestCode: code,
                testingLab: byCode ? '' : lab,
                daterange: $('#daterange').val()
            }).done(function(data) {
                if ($.fn.DataTable.isDataTable('#manifestTable')) {
                    $('#manifestTable').DataTable().destroy();
                }
                const rows = $('#manifestRows').empty();
                const manifests = (data && data.manifests) || [];
                if (manifests.length === 0) {
                    $('#moveSection').slideUp();
                    alert(code !== ''
                        ? "<?= _translate('No manifest found with this code', true); ?>"
                        : "<?= _translate('No manifests found for this lab', true); ?>");
                    return;
                }
                if (code !== '') {
                    // The manifest's own lab is not a place to move it to.
                    const at = String(manifests[0].labId || '');
                    if ($('#testingLab option').filter(function() { return this.value === at; }).length) {
                        $('#testingLab').val(at).trigger('change');
                    }
                }
                manifests.forEach(function(m) {
                    const box = $('<input type="checkbox" name="packageCode[]">').val(m.code)
                        .attr('data-status', m.status || '')
                        .attr('data-lab-id', m.labId || '')
                        .attr('data-lab', m.lab || '')
                        .prop('checked', manifests.length === 1);
                    const tr = $('<tr>');
                    tr.append($('<td>').append(box))
                        .append($('<td>').text(m.code))
                        .append($('<td>').text(m.lab || ''))
                        .append($('<td>').attr('data-order', m.created || '').text(m.createdLabel || ''))
                        .append($('<td>').text(statusLabels[m.status] || m.status || ''))
                        .append($('<td>').text(m.samples));
                    rows.append(tr);
                });
                $('#selectAll').prop('checked', false);
                updateMoveButton();
                $('#moveSection').slideDown();
                $('#manifestTable').DataTable({
                    paging: false,
                    info: false,
                    searching: true,
                    order: [[3, 'desc']],
                    columnDefs: [{ targets: 0, orderable: false }]
                });
            }).fail(function() {
                alert("<?= _translate('Could not load the manifests', true); ?>");
            }).always(function() {
                $.unblockUI();
            });
        }

        function moveManifests() {
            const to = $('#assignLab').val();
            const checked = manifestBoxes().filter(':checked');
            if (checked.filter(function() { return $(this).attr('data-lab-id') === to; }).length > 0) {
                alert("<?= _translate('Please choose a different lab to move the manifests to', true); ?>");
                return;
            }
            const flag = deforayValidator.init({
                formId: 'moveManifestForm'
            });
            if (!flag) {
                return;
            }
            // Received means the samples are at the lab now: moving them is only
            // right once they have physically gone on to the new lab.
            const receivedBoxes = checked.filter(function() {
                return $(this).attr('data-status') === 'received';
            });
            const received = receivedBoxes.map(function() {
                return this.value;
            }).get();
            const receivedAt = [...new Set(receivedBoxes.map(function() {
                return $(this).attr('data-lab');
            }).get())];
            $('#receivedConfirmed').val('');
            if (received.length > 0) {
                const question = "<?= _translate('These manifests were already received at %1$s: %2$s. Have their samples physically been sent on to %3$s?', true); ?>"
                    .replace('%1$s', receivedAt.join(', '))
                    .replace('%2$s', received.join(', '))
                    .replace('%3$s', $('#assignLab option:selected').text());
                if (!confirm(question)) {
                    return;
                }
                $('#receivedConfirmed').val('yes');
            }
            if (confirm("<?= _translate('Move the selected manifests and all their samples?', true); ?>")) {
                // A search takes the rows it hides out of the page, and their
                // checked boxes with them: clear it so every selection is sent.
                if ($.fn.DataTable.isDataTable('#manifestTable')) {
                    $('#manifestTable').DataTable().search('').draw();
                }
                $.blockUI();
                document.getElementById('moveManifestForm').submit();
            }
        }
    </script>
<?php }
require_once APPLICATION_PATH . '/footer.php';
