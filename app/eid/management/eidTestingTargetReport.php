<?php

// The eid Testing Target Report. app/reports/_testing-target-report.php is the page.

$testType = 'eid';
$title = _translate("EARLY INFANT DIAGNOSIS - Testing Target Report");
$dataUrl = '/eid/management/getEidMonthlyThresholdReport.php';
$exportUrl = '/eid/management/eidTestingTargetInExcel.php';

require APPLICATION_PATH . '/reports/_testing-target-report.php';
