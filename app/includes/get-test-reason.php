<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (empty($_POST)) {
    exit(0);
}

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();
if (isset($_POST['testTypeId'])) {
    $testTypeId = $_POST['testTypeId'];
    $testReasonList = $general->getTestReason($testTypeId);
    if (!empty($testReasonList)) { ?>
        <option value=""><?php echo _("-- Select--"); ?></option>
        <?php foreach ($testReasonList as $reason) { ?>
            <option value="<?php echo $reason['test_reason_id']; ?>" <?php echo (!empty($_POST['testReasonId']) && $_POST['testReasonId'] == $reason['test_reason_id']) ? "selected='selected'" : ""; ?>><?php echo $reason['test_reason']; ?></option>
        <?php }
    } else { ?>
        <option value=""><?php echo _("-- Select--"); ?></option>
    <?php }
}