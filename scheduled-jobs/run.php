<?php

require_once(__DIR__ . "/../startup.php");



$logpath = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'logs/scheduled-jobs.log';

$general = new \Vlsm\Models\General($db);
$vldashboardUrl = $general->getGlobalConfig('vldashboard_url');
$timeZone = $general->getGlobalConfig('default_time_zone');
$timeZone = !empty($timeZone) ? $timeZone : 'UTC';

date_default_timezone_set($timeZone);


$jobby = new Jobby\Jobby();

if (!empty($interfaceConfig['enabled']) && $interfaceConfig['enabled'] == true) {
    $jobby->add('interfacing', array(
        'command' => PHP_BINARY . " " . __DIR__ . DIRECTORY_SEPARATOR . "interface.php",
        'schedule' => '* * * * *',
        'output' => $logpath,
        'enabled' => true,
        'debug' => false,
    ));
}

if (!empty($vldashboardUrl) && !empty($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) {
    $jobby->add('vldashboard-vl', array(
        'command' => PHP_BINARY . " " . __DIR__ . DIRECTORY_SEPARATOR . "vldashboard-v2/vldashboard-vl.php",
        'schedule' => '0 */1 * * *',
        'output' => $logpath,
        'enabled' => true,
        'debug' => false,
    ));
}

if (!empty($vldashboardUrl) && !empty($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
    $jobby->add('vldashboard-eid', array(
        'command' => PHP_BINARY . " " . __DIR__ . DIRECTORY_SEPARATOR . "vldashboard-v2/vldashboard-eid.php",
        'schedule' => '30 */1 * * *',
        'output' => $logpath,
        'enabled' => true,
        'debug' => false,
    ));
}
if (!empty($vldashboardUrl) && !empty($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
    $jobby->add('vldashboard-covid19', array(
        'command' => PHP_BINARY . " " . __DIR__ . DIRECTORY_SEPARATOR . "vldashboard-v2/vldashboard-covid19.php",
        'schedule' => '45 */1 * * *',
        'output' => $logpath,
        'enabled' => true,
        'debug' => false,
    ));
}

$jobby->run();
