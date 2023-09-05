<?php

use App\Exceptions\SystemException;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

require_once APPLICATION_PATH . '/header.php';

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$allowImportingNonMatchingSamples = $general->getGlobalConfig('import_non_matching_sample');

$importedBy = $_SESSION['userId'];

$joinTypeWithTestTable = !empty($allowImportingNonMatchingSamples) && $allowImportingNonMatchingSamples == 'no' ? 'INNER JOIN' : 'LEFT JOIN';


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();

$module = $_GET['t'];

$importMap = [
  'vl' => 'import-stats-vl.php',
  'eid' => 'import-stats-eid.php',
  'covid19' => 'import-stats-covid-19.php',
  'hepatitis' => 'import-stats-hepatitis.php',
];

if (isset($importMap[$module])) {
  require_once(APPLICATION_PATH . '/import-result/' . $importMap[$module]);
} else {
  throw new SystemException(_translate('Invalid Test Type'));
}

require_once(APPLICATION_PATH . '/footer.php');
