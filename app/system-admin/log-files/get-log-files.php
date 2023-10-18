<?php

use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$date = DateUtility::isoDateFormat($_GET['date']);
$file = ROOT_PATH . '/logs/'.$date.'-logfile.log';

if(file_exists($file)) {
    echo file_get_contents($file);
}else{
    echo 'No files found';
}