<?php

if(isset($_GET['code']))
{
    $table = $_GET['testType'];
    $sampleCode = $_GET['code'];
    $sql = "SELECT DISTINCT sample_code FROM $table WHERE sample_code like '$sampleCode%' OR remote_sample_code like '$sampleCode%'";
    $result = $db->rawQuery($sql);
    echo json_encode($result);
}