<?php

// The covid19 Testing Target Report. app/reports/_testing-target-report.php is the page.

$testType = 'covid19';
$title = _translate("COVID-19 Testing Target Report");
$dataUrl = '/covid-19/management/getCovid19MonthlyThresholdReport.php';
$exportUrl = '/covid-19/management/covid-19-TestingTargetInExcel.php';

require APPLICATION_PATH . '/reports/_testing-target-report.php';
