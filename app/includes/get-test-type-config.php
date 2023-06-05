<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

if (empty($_POST)) {
    exit(0);
}

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$listData = array();


if (isset($_POST['testTypeId'])) {
        $testTypeId = $_POST['testTypeId'];

        //Get Sample Type options
        $sampleTypeList = $general->getSampleType($testTypeId);
        $sampleTypeOptions = "";
        if (!empty($sampleTypeList)) { 
             $sampleTypeOptions .= '<option value="">-- Select--</option>';
            foreach ($sampleTypeList as $sample) {
                $selected='';
                if(isset($_POST['sampleTypeId']) && !empty($_POST['sampleTypeId']) && $_POST['sampleTypeId'] == $sample['sample_type_id'])
                    $selected = "selected='selected'";
                    $sampleTypeOptions .= '<option value="'.$sample['sample_type_id'].'" >'.$sample['sample_type_name'].'</option>';
                }
        }
        $listData['sampleTypes'] = $sampleTypeOptions;
    

        //Get Test Reason options
        $testReasonList = $general->getTestReason($testTypeId);
        $testReasonOptions = "";
        if (!empty($testReasonList)) { 
             $testReasonOptions .= '<option value="">-- Select--</option>';
            foreach ($testReasonList as $reason) {
                $selected='';
                if(isset($_POST['testReasonId']) && !empty($_POST['testReasonId']) && $_POST['testReasonId'] == $reason['test_reason_id'])
                    $selected = "selected='selected'";
                        $testReasonOptions .= '<option value="'.$reason['test_reason_id'].'" >'.$reason['test_reason'].'</option>';
                }
        }
        $listData['testReasons'] = $testReasonOptions;
       

        //Get Test Methods options
        $testMethodList = $general->getTestMethod($testTypeId);
        $testReasonOptions = "";
        if (!empty($testMethodList)) { 
             $testMethodOptions .= '<option value="">-- Select--</option>';
            foreach ($testMethodList as $method) {
                $selected='';
                if(isset($_POST['testMethodId']) && !empty($_POST['testMethodId']) && $_POST['testMethodId'] == $method['test_method_id'])
                    $selected = "selected='selected'";
                        $testMethodOptions .= '<option value="'.$method['test_method_id'].'" >'.$method['test_method_name'].'</option>';
                }
        }
        $listData['testMethods'] = $testMethodOptions;


        //Get Test Result Units options
        $testResultUnitList = $general->getTestResultUnit($testTypeId);
        $testReasonOptions = "";
        if (!empty($testResultUnitList)) { 
            $testResultUnitOptions .= '<option value="">-- Select--</option>';
            foreach ($testResultUnitList as $reason) {
                $selected='';
                if(isset($_POST['testResultUnitId']) && !empty($_POST['testResultUnitId']) && $_POST['testResultUnitId'] == $reason['unit_id'])
                    $selected = "selected='selected'";
                        $testResultUnitOptions .= '<option value="'.$reason['unit_id'].'" >'.$reason['unit_name'].'</option>';
                }
        }
        $listData['testResultUnits'] = $testResultUnitOptions;
    
        echo json_encode($listData);
}


?>
