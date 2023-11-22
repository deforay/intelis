<?php
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if(isset($_POST['testType']))
{
    $testType = $_POST['testType'];
    if (isset($testType) && $testType == 'vl') {
        $table = "form_vl";
    }
    if (isset($testType) && $testType == 'eid') {
        $table = "form_eid";
    }
    if (isset($testType) && $testType == 'covid19') {
        $table = "form_covid19";
    }
    if (isset($testType) && $testType == 'hepatitis') {
        $table = "form_hepatitis";
    }
    if (isset($testType) && $testType == 'tb') {
        $table = "form_tb";
    }
    $sourceList = $general->getSourceOfRequest($table);
    $option="";
    foreach($sourceList as $list)
    {
        $option.="<option value='".$list['source_of_request']."'>".$list['source_of_request']."</option>";
    }
    echo $option;
}