<?php

use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Services\ManifestMoveService;
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
// Only labs with manifests to move from are offered to list.
$sourceLabs = [];
if ($canMove) {
    /** @var ManifestMoveService $manifestMoveService */
    $manifestMoveService = ContainerRegistry::get(ManifestMoveService::class);
    $sourceLabs = $manifestMoveService->labsWithManifests($module);
}
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
                    <ul class="nav nav-tabs" id="findBy" role="tablist" style="margin-bottom:15px;">
                        <li role="presentation">
                            <a href="#" role="tab" data-find="code">
                                <em class="fa-solid fa-barcode"></em> <?= _translate("By Manifest Code"); ?>
                            </a>
                        </li>
                        <li role="presentation">
                            <a href="#" role="tab" data-find="lab">
                                <em class="fa-solid fa-hospital"></em> <?= _translate("By Testing Lab"); ?>
                            </a>
                        </li>
                    </ul>
                    <div class="row" id="findByCode">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label for="manifestCodes"><?= _translate("Manifest Codes"); ?></label>
                                <textarea class="form-control" id="manifestCodes" rows="3"
                                    title="<?= _translate('Enter the codes of the manifests to move', 'html'); ?>"><?= _sanitizeOutput($manifestCode); ?></textarea>
                                <p class="help-block"><?= _translate("Type, paste or scan the codes: one per line, or separated by commas"); ?></p>
                            </div>
                        </div>
                        <div class="col-md-2" style="padding-top:25px;">
                            <button type="button" class="btn btn-primary" onclick="findManifests();">
                                <em class="fa-solid fa-search"></em> <?= _translate("Search"); ?>
                            </button>
                        </div>
                    </div>
                    <div class="row" id="findByLab" style="display:none;">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="testingLab"><?= _translate("Testing Lab"); ?></label>
                                <select class="form-control" id="testingLab" style="width:100%;"
                                    title="<?= _translate("Choose the testing lab the manifests are at", 'html'); ?>">
                                    <?= $general->generateSelectOptions($sourceLabs, null, _translate('-- Select --')); ?>
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
                    <div class="alert alert-warning" id="findNotice" style="display:none;margin-bottom:0;"></div>
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
                        <p class="text-muted">
                            <?= _translate("The lab the samples are taken from is asked to cancel its copies."); ?>
                        </p>
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
        // How the manifests are found: 'code' (by their codes) or 'lab' (a lab's list).
        let findingBy = 'code';
        let destinations = null;

        $(document).ready(function() {
            $('#testingLab, #assignLab').select2({
                width: '100%',
                placeholder: "<?= _translate('-- Select --', true); ?>"
            });
            destinations = $('#assignLab option').clone();
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
                findLabManifests();
            }).on('cancel.daterangepicker', function() {
                $(this).val('');
                findLabManifests();
            });
            $('#selectAll').on('change', function() {
                manifestBoxes().filter(':enabled').prop('checked', this.checked);
                updateMoveButton();
            });
            $('#manifestRows').on('change', 'input:checkbox', updateMoveButton);
            $('#findBy').on('click', 'a', function(e) {
                e.preventDefault();
                findBy($(this).data('find'));
            });
            // Enter searches. It also starts a new line, so a scanner that ends
            // each code with Enter builds up the list.
            $('#manifestCodes').on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (this.value.trim() !== '') {
                        this.value = this.value.replace(/\s*$/, '\n');
                    }
                    findManifests();
                }
            });
            $('#testingLab').on('change', findLabManifests);
            findBy('code');
            if ($('#manifestCodes').val().trim() !== '') {
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
            $('#findBy a').each(function() {
                const on = $(this).data('find') === how;
                $(this).parent().toggleClass('active', on);
                $(this).attr('aria-selected', on);
            });
            $('#findByCode').toggle(how === 'code');
            $('#findByLab').toggle(how === 'lab');
            // The other tab's results are not left on the page to be moved by mistake.
            clearResults();
            if (how === 'code') {
                $('#manifestCodes').trigger('focus');
            } else {
                findLabManifests();
            }
        }

        function clearResults() {
            if ($.fn.DataTable.isDataTable('#manifestTable')) {
                $('#manifestTable').DataTable().destroy();
            }
            $('#manifestRows').empty();
            $('#moveSection').hide();
            $('#findNotice').hide().empty();
            fromLab('');
        }

        // The lab the manifests are at is not a place to move them to.
        function fromLab(labId) {
            const kept = $('#assignLab').val();
            $('#assignLab').empty().append(destinations.clone().filter(function() {
                return this.value === '' || this.value !== labId;
            }));
            $('#assignLab').val(kept !== labId ? kept : '').trigger('change');
        }

        function notice(text) {
            $('#findNotice').text(text).toggle(text !== '');
        }

        function findLabManifests() {
            if (findingBy === 'lab' && $('#testingLab').val()) {
                findManifests();
            }
        }

        function findManifests() {
            const byCode = findingBy === 'code';
            const codes = byCode ? $('#manifestCodes').val().trim() : '';
            const lab = $('#testingLab').val();
            if (byCode && codes === '') {
                alert("<?= _translate('Please enter the manifest code', true); ?>");
                return;
            }
            if (!byCode && !lab) {
                return;
            }
            $.blockUI();
            $.post('/specimen-referral-manifest/get-manifest-package-code.php', {
                module: <?= json_encode($module); ?>,
                manifestCodes: codes,
                testingLab: byCode ? '' : lab,
                daterange: byCode ? '' : $('#daterange').val()
            }).done(function(data) {
                clearResults();
                const manifests = (data && data.manifests) || [];
                const notFound = (data && data.notFound) || [];
                if (manifests.length === 0) {
                    notice(byCode
                        ? "<?= _translate('No manifest found with these codes', true); ?>"
                        : "<?= _translate('No manifests found for this lab', true); ?>");
                    return;
                }
                if (notFound.length > 0) {
                    notice("<?= _translate('Not found: %s', true); ?>".replace('%s', notFound.join(', ')));
                }
                const labs = [...new Set(manifests.map(function(m) {
                    return String(m.labId || '');
                }))];
                fromLab(labs.length === 1 ? labs[0] : '');
                const rows = $('#manifestRows');
                manifests.forEach(function(m) {
                    // Codes asked for one by one are ticked; a lab's list is not.
                    const box = $('<input type="checkbox" name="packageCode[]">').val(m.code)
                        .attr('data-status', m.status || '')
                        .attr('data-lab-id', m.labId || '')
                        .attr('data-lab', m.lab || '')
                        .prop('disabled', m.tested)
                        .prop('checked', byCode && !m.tested);
                    const status = $('<td>').text(statusLabels[m.status] || m.status || '');
                    if (m.tested) {
                        status.append(' ').append($('<span class="label label-default">')
                            .text("<?= _translate('Has tested samples, cannot be moved', true); ?>"));
                    }
                    const tr = $('<tr>').toggleClass('text-muted', m.tested);
                    tr.append($('<td>').append(box))
                        .append($('<td>').text(m.code))
                        .append($('<td>').text(m.lab || ''))
                        .append($('<td>').attr('data-order', m.created || '').text(m.createdLabel || ''))
                        .append(status)
                        .append($('<td>').text(m.samples));
                    rows.append(tr);
                });
                $('#selectAll').prop('checked', false)
                    .prop('disabled', manifests.every(function(m) { return m.tested; }));
                updateMoveButton();
                $('#moveSection').slideDown();
                $('#manifestTable').DataTable({
                    paging: false,
                    info: false,
                    // A short list needs no search box of its own.
                    searching: manifests.length > 25,
                    order: [[3, 'desc']],
                    columnDefs: [
                        { targets: 0, orderable: false },
                        // A lab's list is all at that lab.
                        { targets: 2, visible: byCode }
                    ]
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
