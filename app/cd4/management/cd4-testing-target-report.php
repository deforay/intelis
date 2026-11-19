<?php

// The cd4 Testing Target Report. app/reports/_testing-target-report.php is the page.

$testType = 'cd4';
$title = _translate("CD4 Testing Target Report");
$dataUrl = '/cd4/management/get-cd4-monthly-threshold-report.php';
$exportUrl = '/cd4/management/cd4-testing-target-in-excel.php';

require APPLICATION_PATH . '/reports/_testing-target-report.php';
