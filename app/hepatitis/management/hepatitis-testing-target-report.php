<?php

// The hepatitis Testing Target Report. app/reports/_testing-target-report.php is the page.

$testType = 'hepatitis';
$title = _translate("Hepatitis Testing Target Report");
$dataUrl = '/hepatitis/management/get-hepatitis-monthly-threshold-report.php';
$exportUrl = '/hepatitis/management/hepatitis-testing-target-in-excel.php';

require APPLICATION_PATH . '/reports/_testing-target-report.php';
