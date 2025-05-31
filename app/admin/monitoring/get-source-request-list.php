<?php

use App\Services\TestsService;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


if (isset($_POST['testType'])) {
    $testType = $_POST['testType'] ?? 'vl';
    $table = TestsService::getTestTableName($testType);
    $sourceList = $general->getSourcesOfTestRequests($table, asNameValuePair: true);
    $option = "";
    foreach ($sourceList as $optionValue => $displayText) {
        $option .= "<option value='$optionValue'>$displayText</option>";
    }
    echo $option;
}
