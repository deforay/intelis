<?php

use App\Services\CommonService;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);
$general = new CommonService();
echo $general->getLastSyncDateTime();
