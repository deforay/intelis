<?php

use App\Services\TestsService;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


if (isset($_POST['testType'])) {
    $testType = (string) $_POST['testType'];
    if (!in_array($testType, TestsService::getActiveTests(), true)) {
        return;
    }
    $table = TestsService::getTestTableName($testType);
    $sourceList = $general->getSourcesOfTestRequests($table, true);

    // One option per source: rows stored under an older name of the same source
    // are folded in by the report's filter, so they must not show twice here.
    $options = [];
    foreach ($sourceList as $optionValue => $displayText) {
        $stored = CommonService::storedSourcesOfRequest((string) $optionValue);
        $options[$stored[0]] ??= $displayText;
    }

    $option = "<option value=''>" . _translate("All Sources") . "</option>";
    foreach ($options as $optionValue => $displayText) {
        $option .= "<option value='" . htmlspecialchars((string) $optionValue, ENT_QUOTES) . "'>"
            . htmlspecialchars((string) $displayText) . "</option>";
    }
    $option .= "<option value='unrecorded'>" . _translate("Not Recorded") . "</option>";
    echo $option;
}
