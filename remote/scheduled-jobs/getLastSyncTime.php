<?php

require_once(dirname(__FILE__) . "/../../startup.php");
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);
$general = new \Vlsm\Models\General();
echo $general->getLatestSynDateTime();
