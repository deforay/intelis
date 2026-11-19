<?php

// The vl Testing Target Report. app/reports/_testing-target-report.php is the page.

$testType = 'vl';
$title = _translate("Viral Load - Testing Target Report");
$dataUrl = '/vl/program-management/getVlMonthlyThresholdReport.php';
$exportUrl = '/vl/program-management/vlTestingTargetInExcel.php';

require APPLICATION_PATH . '/reports/_testing-target-report.php';
