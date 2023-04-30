<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;


 
require_once(APPLICATION_PATH . '/header.php');

//global config
$cSampleQuery = "SELECT * FROM global_config";
$cSampleResult = $db->query($cSampleQuery);
$arr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($cSampleResult); $i++) {
  $arr[$cSampleResult[$i]['name']] = $cSampleResult[$i]['value'];
}

$importedBy = $_SESSION['userId'];

$import_decided = (isset($arr['import_non_matching_sample']) && $arr['import_non_matching_sample'] == 'no') ? 'INNER JOIN' : 'LEFT JOIN';


$tQuery = "SELECT `module` FROM `temp_sample_import` WHERE `imported_by` =? limit 1";

$tResult = $db->rawQueryOne($tQuery, array($_SESSION['userId']));

$module = $tResult['module'];

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);

if ($module == 'vl') {
  require_once(APPLICATION_PATH . '/import-result/import-stats-vl.php');
} else if ($module == 'eid') {
  require_once(APPLICATION_PATH . '/import-result/import-stats-eid.php');
} else if ($module == 'covid19') {
  require_once(APPLICATION_PATH . '/import-result/import-stats-covid-19.php');
} else if ($module == 'hepatitis') {
  require_once(APPLICATION_PATH . '/import-result/import-stats-hepatitis.php');
}

require_once(APPLICATION_PATH . '/footer.php');
