<?php

// The tb Testing Target Report. app/reports/_testing-target-report.php is the page.

$testType = 'tb';
$title = _translate("TB Testing Target Report");
$dataUrl = '/tb/management/get-tb-monthly-threshold-report.php';
$exportUrl = '/tb/management/tb-testing-target-in-excel.php';

require APPLICATION_PATH . '/reports/_testing-target-report.php';
