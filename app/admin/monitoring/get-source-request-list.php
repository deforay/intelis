<?php

use App\Services\TestsService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


if (isset($_POST['testType'])) {
    $testType = (string) $_POST['testType'];
    if (!in_array($testType, TestsService::getActiveTests(), true)) {
        return;
    }
    $table = TestsService::getTestTableName($testType);
    // The cached list spans every lab, so a user working for one lab gets the
    // sources of that lab's samples only.
    $labScope = $general->labScopeWhere('vl');
    if ($labScope === '') {
        $sourceList = $general->getSourcesOfTestRequests($table, true);
    } else {
        $sourceList = array_flip(array_column(ContainerRegistry::get(DatabaseService::class)->rawQuery(
            "SELECT DISTINCT vl.source_of_request FROM $table AS vl
              WHERE IFNULL(vl.source_of_request, '') != '' AND $labScope"
        ) ?: [], 'source_of_request'));
    }

    // One option per source: rows stored under an older name of the same source
    // are folded in by the report's filter, so they must not show twice here.
    $options = [];
    foreach (array_keys($sourceList) as $optionValue) {
        $stored = CommonService::storedSourcesOfRequest((string) $optionValue);
        $options[$stored[0]] ??= CommonService::sourceOfRequestLabel($stored[0]);
    }

    $option = "<option value=''>" . _translate("All Sources") . "</option>";
    foreach ($options as $optionValue => $displayText) {
        $option .= "<option value='" . htmlspecialchars((string) $optionValue, ENT_QUOTES) . "'>"
            . htmlspecialchars((string) $displayText) . "</option>";
    }
    $option .= "<option value='unrecorded'>" . _translate("Not Recorded") . "</option>";
    echo $option;
}
