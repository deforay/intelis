<?php
#require_once('../../startup.php'); 

$general=new \Vlsm\Models\General();
$reportFilename = '';
include('generateVlWeeklyReportPdf.php');
echo $reportFilename;
