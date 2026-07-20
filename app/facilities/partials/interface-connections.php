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

        <div class="alert alert-info" id="interfaceCodePanel" style="display:none;margin-top:15px;">
            <div class="row">
                <div class="col-md-8">
                    <strong id="interfaceCodeTitle"><?= _translate('Connection Code'); ?></strong>
                    <p class="help-block" style="margin-bottom:6px;">
                        <?= _translate(
                            'Enter these three groups in the Interface Tool. '
                            . 'This code is shown only once and can be used only once.'
                        ); ?>
                    </p>
                    <div class="input-group">
                        <input type="text" class="form-control" id="interfaceConnectionCode" readonly
                            style="font-family:'Courier New',Consolas,monospace;font-size:30px;line-height:1.3;
                                   font-weight:700;letter-spacing:3px;text-align:center;height:auto;padding:10px 6px;
                                   color:#111;background-color:#fff;">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default" id="copyInterfaceCode"
                                style="height:100%;">
                                <em class="fa-regular fa-copy"></em> <?= _translate('Copy'); ?>
                            </button>
                        </span>
                    </div>
                </div>
                <div class="col-md-4">
                    <p><strong><?= _translate('Expires in'); ?>:</strong></p>
                    <p class="lead" id="interfaceCodeCountdown" style="margin-bottom:8px;">--:--</p>
                    <button type="button" class="btn btn-sm btn-default" id="cancelInterfaceCode">
                        <?= _translate('Cancel Code'); ?>
                    </button>
                </div>
            </div>
        </div>

        <hr>
        <h4><?= _translate('Connected Installations'); ?></h4>
        <div class="table-responsive">
            <table class="table table-bordered table-striped" aria-describedby="interface-connections-description">
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
                            $statusClass = $status === 'active' ? 'label-success' : 'label-default';
                            $installationId = (string) $installation['installation_id'];
                            $scopes = (array) ($installation['credential_scopes'] ?? []);
                            ?>
                            <tr>
                                <td><?= $escape((string) $installation['display_name']); ?></td>
                                <td>
                                    <span class="label <?= $statusClass; ?>">
                                        <?= $escape(ucfirst($status)); ?>
                                    </span>
                                </td>
                                <td><?= $escape((string) ($installation['created_at'] ?? '-')); ?></td>
                                <td><?= $escape((string) ($installation['last_seen_at'] ?? '-')); ?></td>
                                <td>
                                    <?php foreach ($scopes as $scope) { ?>
                                        <span class="label label-info"><?= $escape((string) $scope); ?></span>
                                    <?php } ?>
                                </td>
                                <td>
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
