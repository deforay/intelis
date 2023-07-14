<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;



require_once APPLICATION_PATH . '/header.php';

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$allowImportingNonMatchingSamples = $general->getGlobalConfig('import_non_matching_sample');

$importedBy = $_SESSION['userId'];

$joinTypeWithTestTable = !empty($allowImportingNonMatchingSamples) && $allowImportingNonMatchingSamples == 'no' ? 'INNER JOIN' : 'LEFT JOIN';

$tQuery = "SELECT `module` FROM `temp_sample_import` WHERE `imported_by` =? limit 1";

$tResult = $db->rawQueryOne($tQuery, array($_SESSION['userId']));

$module = $tResult['module'];



if ($module == 'vl') {
  require_once(APPLICATION_PATH . '/import-result/import-stats-vl.php');
} else if ($module == 'eid') {
  require_once(APPLICATION_PATH . '/import-result/import-stats-eid.php');
} else if ($module == 'covid19') {
  require_once(APPLICATION_PATH . '/import-result/import-stats-covid-19.php');
} else if ($module == 'hepatitis') {
  require_once(APPLICATION_PATH . '/import-result/import-stats-hepatitis.php');
}

require_once APPLICATION_PATH . '/footer.php';
