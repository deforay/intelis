<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = _translate("Lab Command History");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (!$general->isSTSInstance()) {
    echo '<div class="content-wrapper"><section class="content"><div class="alert alert-warning">'
       . _translate('Lab Command History is only available on STS instances.')
       . '</div></section></div>';
    require_once APPLICATION_PATH . '/footer.php';
    return;
}

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$labNameList = $facilitiesService->getTestingLabs();

$canCancel = _isAllowed('/admin/monitoring/cancel-lis-command.php');
$canReplay = _isAllowed('/admin/monitoring/queue-lis-command.php');

$commandOptions = [
    '' => _translate('-- All commands --'),
    'resend-results' => 'resend-results',
    'resend-requests' => 'resend-requests',
    'metadata-resync' => 'metadata-resync',
    'refresh-cache' => 'refresh-cache',
    'rotate-token' => 'rotate-token',
    'refresh-perms' => 'refresh-perms',
    'restart-apache' => 'restart-apache',
    'upgrade' => 'upgrade',
    'upgrade-prepare' => 'upgrade-prepare',
    'upgrade-apply' => 'upgrade-apply',
];

$statusOptions = [
    '' => _translate('-- Any status --'),
    'pending' => 'pending',
    'picked' => 'picked',
    'running' => 'running',
    'preparing' => 'preparing',
    'prepared' => 'prepared',
    'applying' => 'applying',
    'completed' => 'completed',
    'failed' => 'failed',
    'expired' => 'expired',
    'cancelled' => 'cancelled',
];
?>
<style>
    .cmd-history-table td,
    .cmd-history-table th {
        vertical-align: middle;
    }
    .cmd-status-completed { color: #155724; font-weight: bold; }
    .cmd-status-failed    { color: #721c24; font-weight: bold; }
    .cmd-status-cancelled { color: #6c757d; font-weight: bold; font-style: italic; }
    .cmd-status-expired   { color: #6c757d; font-weight: bold; font-style: italic; }
    .cmd-status-pending,
    .cmd-status-picked,
    .cmd-status-running,
    .cmd-status-preparing,
    .cmd-status-prepared,
    .cmd-status-applying  { color: #856404; font-weight: bold; }

    .result-tail-box {
        max-height: 400px; overflow: auto; background: #f6f8fa; padding: 8px;
        font-family: monospace; font-size: 12px; white-space: pre-wrap;
        border-radius: 4px;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <h1><em class="fa-solid fa-scroll"></em> <?= _translate("Lab Command History"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
            <li><a href="/admin/monitoring/sync-status.php"><?= _translate("Lab Sync Status"); ?></a></li>
            <li class="active"><?= _translate("Lab Command History"); ?></li>
        </ol>
    </section>

    <section class="content">
        <div class="box">
            <div class="box-header">
                <div class="row">
                    <div class="col-md-3">
                        <label><?= _translate('Lab'); ?></label>
                        <select class="form-control select2" id="filterLab">
                            <?= $general->generateSelectOptions($labNameList, null, _translate('-- All labs --')); ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label><?= _translate('Command'); ?></label>
                        <select class="form-control" id="filterCommand">
                            <?php foreach ($commandOptions as $v => $t) { ?>
                                <option value="<?= htmlspecialchars($v); ?>"><?= htmlspecialchars($t); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label><?= _translate('Status'); ?></label>
                        <select class="form-control" id="filterStatus">
                            <?php foreach ($statusOptions as $v => $t) { ?>
                                <option value="<?= htmlspecialchars($v); ?>"><?= htmlspecialchars($t); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label><?= _translate('Date range'); ?></label>
                        <input type="text" class="form-control" id="filterDateRange" readonly
                            placeholder="<?= _translate('Click to pick'); ?>">
                    </div>
                    <div class="col-md-2" style="margin-top: 24px;">
                        <button class="btn btn-primary" id="applyFilters" type="button">
                            <i class="fa fa-search"></i> <?= _translate('Search'); ?>
                        </button>
                        <button class="btn btn-default" id="resetFilters" type="button">
                            <i class="fa fa-refresh"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover cmd-history-table" id="cmdHistoryTable">
                        <thead>
                            <tr>
                                <th><?= _translate('Requested at'); ?></th>
                                <th><?= _translate('Lab'); ?></th>
                                <th><?= _translate('Command'); ?></th>
                                <th><?= _translate('Params'); ?></th>
                                <th><?= _translate('Status'); ?></th>
                                <th><?= _translate('Requested by'); ?></th>
                                <th><?= _translate('Completed at'); ?></th>
                                <th><?= _translate('Actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="cmdHistoryBody">
                            <tr>
                                <td colspan="8" class="text-center">
                                    <i class="fa fa-spinner fa-spin"></i> <?= _translate('Loading...'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted"><small>
                    <?= _translate('Showing the 200 most recent commands matching the filters. Use filters to narrow older history.'); ?>
                </small></p>
            </div>
        </div>
    </section>
</div>

<!-- Command details modal -->
<div class="modal fade" id="cmdDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= _translate('Command details'); ?></h4>
            </div>
            <div class="modal-body" id="cmdDetailsBody">
                <p class="text-muted"><?= _translate('Loading...'); ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _translate('Close'); ?></button>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script>
const canCancel = <?= $canCancel ? 'true' : 'false'; ?>;
const canReplay = <?= $canReplay ? 'true' : 'false'; ?>;
let dateFrom = '', dateTo = '';

$(function () {
    $('#filterLab').select2({ width: '100%', allowClear: true, placeholder: '<?= _translate("All labs"); ?>' });

    $('#filterDateRange').daterangepicker({
        autoUpdateInput: false,
        locale: {
            cancelLabel: "<?= _translate('Clear'); ?>",
            format: 'DD-MMM-YYYY'
        }
    });
    $('#filterDateRange').on('apply.daterangepicker', function (ev, picker) {
        $(this).val(picker.startDate.format('DD-MMM-YYYY') + ' - ' + picker.endDate.format('DD-MMM-YYYY'));
        dateFrom = picker.startDate.format('YYYY-MM-DD');
        dateTo = picker.endDate.format('YYYY-MM-DD');
    });
    $('#filterDateRange').on('cancel.daterangepicker', function () {
        $(this).val(''); dateFrom = ''; dateTo = '';
    });

    $('#applyFilters').on('click', loadHistory);
    $('#resetFilters').on('click', function () {
        $('#filterLab').val('').trigger('change');
        $('#filterCommand').val('');
        $('#filterStatus').val('');
        $('#filterDateRange').val('');
        dateFrom = ''; dateTo = '';
        loadHistory();
    });

    $('#cmdHistoryBody').on('click', '.details-link', function (e) {
        e.preventDefault();
        const commandId = $(this).data('commandId');
        $('#cmdDetailsBody').html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> <?= _translate('Loading...'); ?></p>');
        $('#cmdDetailsModal').modal('show');
        $.get('/admin/monitoring/get-lis-command-history.php', { detailFor: commandId }, function (html) {
            $('#cmdDetailsBody').html(html);
        }).fail(function () {
            $('#cmdDetailsBody').html('<p class="text-danger"><?= _translate('Failed to load details.'); ?></p>');
        });
    });

    if (canCancel) {
        $('#cmdHistoryBody').on('click', '.cancel-link', function (e) {
            e.preventDefault();
            const commandId = $(this).data('commandId');
            if (!window.confirm('<?= _translate('Cancel this pending command?'); ?>')) return;
            $.post('/admin/monitoring/cancel-lis-command.php', { commandId: commandId }, function (res) {
                if (res && res.status === 'success') {
                    loadHistory();
                } else {
                    alert((res && res.error) || '<?= _translate('Failed to cancel'); ?>');
                }
            }, 'json').fail(function (xhr) {
                let msg = '<?= _translate('Failed to cancel'); ?>';
                try { const b = JSON.parse(xhr.responseText); if (b && b.error) msg = b.error; } catch (_) {}
                alert(msg);
            });
        });
    }

    if (canReplay) {
        $('#cmdHistoryBody').on('click', '.replay-link', function (e) {
            e.preventDefault();
            const commandId = $(this).data('commandId');
            if (!window.confirm('<?= _translate('Re-queue this command with the same params?'); ?>')) return;
            $.post('/admin/monitoring/replay-lis-command.php', { commandId: commandId }, function (res) {
                if (res && res.status === 'success') {
                    loadHistory();
                } else {
                    alert((res && res.error) || '<?= _translate('Failed to replay'); ?>');
                }
            }, 'json').fail(function (xhr) {
                let msg = '<?= _translate('Failed to replay'); ?>';
                try { const b = JSON.parse(xhr.responseText); if (b && b.error) msg = b.error; } catch (_) {}
                alert(msg);
            });
        });
    }

    loadHistory();
});

function loadHistory() {
    const params = {
        labId: $('#filterLab').val() || '',
        command: $('#filterCommand').val() || '',
        status: $('#filterStatus').val() || '',
        dateFrom: dateFrom,
        dateTo: dateTo
    };
    $('#cmdHistoryBody').html('<tr><td colspan="8" class="text-center"><i class="fa fa-spinner fa-spin"></i> <?= _translate("Loading..."); ?></td></tr>');
    $.post('/admin/monitoring/get-lis-command-history.php', params, function (html) {
        $('#cmdHistoryBody').html(html);
    }).fail(function () {
        $('#cmdHistoryBody').html('<tr><td colspan="8" class="text-center text-danger"><?= _translate('Failed to load history.'); ?></td></tr>');
    });
}
</script>

<?php require_once APPLICATION_PATH . '/footer.php';
