<?php

use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\STS\RequestReceiptsService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var RequestReceiptsService $receipts */
$receipts = ContainerRegistry::get(RequestReceiptsService::class);

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$testType = (string) ($_POST['testType'] ?? 'vl');
if (!in_array($testType, TestsService::getActiveTests(), true)) {
    $testType = 'vl';
}
$labId = (int) base64_decode((string) ($_POST['labId'] ?? ''));

// The lab comes from the request and AJAX skips the ACL check, so a user tied to
// one lab (cloud-LIS) must not read another lab's failures by changing it
$rows = $labId > 0 ? $receipts->unsaved($labId, $testType, $general->labAdminScopeWhere('lab_id', 'f')) : [];

if ($rows === []) { ?>
    <tr>
        <td colspan="6" class="dataTables_empty">
            <?= _translate("The lab has saved every request sent to it"); ?>
        </td>
    </tr>
<?php }
foreach ($rows as $row) { ?>
    <tr class="<?= $row['gave_up'] ? 'danger' : 'warning'; ?>">
        <td>
            <?= htmlspecialchars((string) $row['sample_code']); ?>
        </td>
        <td>
            <?= htmlspecialchars((string) $row['facility_name']); ?>
        </td>
        <td>
            <?= htmlspecialchars((string) ($row['reason'] ?? '')); ?>
        </td>
        <td class="text-right">
            <?= (int) $row['attempts']; ?>
        </td>
        <td>
            <?= DateUtility::humanReadableDateFormat($row['first_failed_datetime'], true); ?>
            &ndash;
            <?= DateUtility::humanReadableDateFormat($row['last_failed_datetime'], true); ?>
        </td>
        <td>
            <?= $row['gave_up'] ? _translate("No longer sent. Fix and edit the request to send it again")
                : _translate("Sent again on the next pull"); ?>
        </td>
    </tr>
<?php }
