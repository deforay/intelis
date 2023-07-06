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
    $testMethodList = $general->getTestMethod($testTypeId);
    if (!empty($testMethodList)) { ?>
        <option value="">
            <?php echo _("-- Select --"); ?>
        </option>
        <?php foreach ($testMethodList as $method) { ?>
            <option value="<?php echo $method['test_method_id']; ?>" <?php echo (!empty($_POST['testMethodId']) && $_POST['testMethodId'] == $method['test_method_id']) ? "selected='selected'" : ""; ?>><?php echo $method['test_method_name']; ?></option>
        <?php }
    } else { ?>
        <option value="">
            <?php echo _("-- Select --"); ?>
        </option>
    <?php }
}
