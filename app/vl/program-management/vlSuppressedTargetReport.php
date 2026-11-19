<?php

// The VL Suppressed Target Report. app/reports/_testing-target-report.php is the page.

$testType = 'vl';
$targetKind = 'suppressed';
$title = _translate("Viral Load Suppression - Monthly Target Report");
$dataUrl = '/vl/program-management/getSuppressedTargetReport.php';
$exportUrl = '/vl/program-management/vlSuppressedTargetInExcel.php';

require APPLICATION_PATH . '/reports/_testing-target-report.php';
