<?php

declare(strict_types=1);

/** @var int $id */
/** @var string $intelisUrl */
/** @var list<array<string, mixed>> $interfaceInstallations */
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$connectionDescription = _translate(
    'Connect facility computers running the Interface Tool. '
    . 'Each connection can manage multiple instruments and physical machines.'
);
$statusPillClasses = [
    'active' => 'ifc-pill-active',
    'revoked' => 'ifc-pill-revoked',
];
$interfaceUiConfig = [
    'endpoint' => '/facilities/interfaceConnectionAction.php',
    'facilityId' => (int) $id,
    'csrfToken' => $_SESSION['csrf_token'] ?? '',
    'messages' => [
        'newTitle' => _translate('New Connection Code'),
        'reconnectTitle' => _translate('Reconnect / Reinstall Code'),
        'copied' => _translate('Copied to clipboard.'),
        'confirmRevoke' => _translate(
            'Revoke this Interface Tool installation? Other installations will not be affected.'
        ),
        'activeCode' => _translate(
            'Cancel or wait for the current Connection Code to expire before creating another.'
        ),
        'requestFailed' => _translate('Unable to complete the request. Please try again.'),
    ],
];
?>
<style>
    /* Scoped to this box so nothing here leaks into the rest of the legacy theme. */
    #interfaceToolConnections .ifc-panel {
        margin-top: 15px;
        padding: 15px 18px;
        background-color: #f8fafb;
        border: 1px solid #e4e8ec;
        border-left: 3px solid #3c8dbc;
        border-radius: 3px;
    }

    #interfaceToolConnections .ifc-panel-title {
        font-size: 14px;
        font-weight: 600;
        color: #333;
    }

    #interfaceToolConnections .ifc-panel-help {
        margin: 4px 0 12px;
        font-size: 13px;
        color: #8a9299;
    }

    #interfaceToolConnections .ifc-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px 20px;
    }

    /* The code and its Copy button read as one control. */
    #interfaceToolConnections .ifc-code-group {
        display: flex;
        align-items: stretch;
    }

    #interfaceToolConnections .ifc-code {
        width: 260px;
        height: 44px;
        padding: 0 6px;
        font-family: 'Courier New', Consolas, monospace;
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 3px;
        text-align: center;
        color: #2c3e50;
        background-color: #fff;
        border: 1px solid #ccd3d9;
        border-radius: 3px 0 0 3px;
    }

    #interfaceToolConnections .ifc-code:focus {
        border-color: #3c8dbc;
        outline: 0;
    }

    #interfaceToolConnections .ifc-copy {
        height: 44px;
        margin-left: -1px;
        border-radius: 0 3px 3px 0;
    }

    #interfaceToolConnections .ifc-expiry {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-left: auto;
    }

    #interfaceToolConnections .ifc-expiry-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #8a9299;
    }

    #interfaceToolConnections .ifc-countdown {
        font-family: 'Courier New', Consolas, monospace;
        font-size: 19px;
        font-weight: 700;
        line-height: 1.2;
        color: #444;
    }

    #interfaceToolConnections .ifc-pill {
        display: inline-block;
        margin: 0 4px 2px 0;
        padding: 3px 9px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.5;
        border-radius: 11px;
    }

    #interfaceToolConnections .ifc-pill-muted {
        color: #5a6570;
        background-color: #eef1f4;
        border: 1px solid #e0e5ea;
    }

    #interfaceToolConnections .ifc-pill-active {
        color: #2e7d46;
        background-color: #e8f5ec;
        border: 1px solid #cfe8d8;
    }

    #interfaceToolConnections .ifc-pill-revoked {
        color: #b03a2e;
        background-color: #fdecea;
        border: 1px solid #f5c6c0;
    }

    #interfaceToolConnections .ifc-installations td {
        vertical-align: middle;
    }

    #interfaceToolConnections .ifc-actions .btn {
        margin-right: 4px;
    }
</style>
<div class="box box-primary" id="interfaceToolConnections">
    <div class="box-header with-border">
        <h3 class="box-title">
            <em class="fa-solid fa-plug"></em>
            <?= _translate('Interface Tool Connections'); ?>
        </h3>
    </div>
    <div class="box-body">
        <p class="text-muted" id="interface-connections-description">
            <?= $escape($connectionDescription); ?>
        </p>

        <div class="row">
            <div class="col-md-8">
                <label for="interfaceIntelisUrl"><?= _translate('InteLIS URL'); ?></label>
                <div class="input-group">
                    <input type="text" class="form-control" id="interfaceIntelisUrl"
                        value="<?= $escape($intelisUrl); ?>" readonly>
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-default" id="copyInterfaceUrl">
                            <em class="fa-regular fa-copy"></em> <?= _translate('Copy'); ?>
                        </button>
                    </span>
                </div>
            </div>
            <div class="col-md-4" style="padding-top:25px;">
                <button type="button" class="btn btn-primary" id="generateInterfaceCode">
                    <em class="fa-solid fa-link"></em> <?= _translate('Generate Connection Code'); ?>
                </button>
            </div>
        </div>

        <div class="ifc-panel" id="interfaceCodePanel" style="display:none;">
            <div class="ifc-panel-title" id="interfaceCodeTitle"><?= _translate('Connection Code'); ?></div>
            <p class="ifc-panel-help">
                <?= _translate(
                    'Enter these three groups in the Interface Tool. '
                    . 'This code is shown only once and can be used only once.'
                ); ?>
            </p>
            <div class="ifc-row">
                <div class="ifc-code-group">
                    <input type="text" class="ifc-code" id="interfaceConnectionCode" readonly
                        aria-label="<?= _translate('Connection Code'); ?>">
                    <button type="button" class="btn btn-default ifc-copy" id="copyInterfaceCode">
                        <em class="fa-regular fa-copy"></em> <?= _translate('Copy'); ?>
                    </button>
                </div>
                <div class="ifc-expiry">
                    <div style="text-align:right;">
                        <div class="ifc-expiry-label"><?= _translate('Expires in'); ?></div>
                        <div class="ifc-countdown" id="interfaceCodeCountdown">--:--</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-default" id="cancelInterfaceCode">
                        <?= _translate('Cancel Code'); ?>
                    </button>
                </div>
            </div>
        </div>

        <hr>
        <h4><?= _translate('Connected Installations'); ?></h4>
        <div class="table-responsive">
            <table class="table table-bordered table-striped ifc-installations"
                aria-describedby="interface-connections-description">
                <thead>
                    <tr>
                        <th><?= _translate('Display Name'); ?></th>
                        <th><?= _translate('Status'); ?></th>
                        <th><?= _translate('Created'); ?></th>
                        <th><?= _translate('Last Seen'); ?></th>
                        <th><?= _translate('Scopes'); ?></th>
                        <th><?= _translate('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($interfaceInstallations === []) { ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">
                                <?= _translate('No Interface Tool installations are connected yet.'); ?>
                            </td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($interfaceInstallations as $installation) {
                            $status = (string) ($installation['status'] ?? 'unknown');
                            $statusClass = $statusPillClasses[$status] ?? 'ifc-pill-muted';
                            $installationId = (string) $installation['installation_id'];
                            $scopes = (array) ($installation['credential_scopes'] ?? []);
                            ?>
                            <tr>
                                <td><?= $escape((string) $installation['display_name']); ?></td>
                                <td>
                                    <span class="ifc-pill <?= $statusClass; ?>">
                                        <?= $escape(ucfirst($status)); ?>
                                    </span>
                                </td>
                                <td><?= $escape((string) ($installation['created_at'] ?? '-')); ?></td>
                                <td><?= $escape((string) ($installation['last_seen_at'] ?? '-')); ?></td>
                                <td>
                                    <?php foreach ($scopes as $scope) { ?>
                                        <span class="ifc-pill ifc-pill-muted">
                                            <?= $escape((string) $scope); ?>
                                        </span>
                                    <?php } ?>
                                </td>
                                <td class="ifc-actions">
                                    <button type="button" class="btn btn-xs btn-primary interface-reconnect"
                                        data-installation-id="<?= $escape($installationId); ?>">
                                        <?= _translate('Reconnect / Reinstall'); ?>
                                    </button>
                                    <?php if ($status !== 'revoked') { ?>
                                        <button type="button" class="btn btn-xs btn-danger interface-revoke"
                                            data-installation-id="<?= $escape($installationId); ?>">
                                            <?= _translate('Revoke'); ?>
                                        </button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
window.InterfaceConnectionsConfig = <?= json_encode(
    $interfaceUiConfig,
    JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
); ?>;
</script>
<script src="/assets/js/interface-connections.js"></script>
