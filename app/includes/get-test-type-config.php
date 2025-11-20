<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GenericTestsService;

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

if ($_POST === []) {
    exit(0);
}

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$listData = [];


if (isset($_POST['testTypeId'])) {
    $testTypeId = $_POST['testTypeId'];

    //Get Sample Type options
    $sampleTypeList = $genericTestsService->getSampleType($testTypeId);
    $sampleTypeOptions = '<option value="">-- Select --</option>';
    if (!empty($sampleTypeList)) {
        foreach ($sampleTypeList as $sample) {
            $selected = '';
            if (!empty($_POST['sampleTypeId']) && $_POST['sampleTypeId'] == $sample['sample_type_id']) {
                $selected = "selected='selected'";
            }
            $sampleTypeOptions .= '<option value="' . $sample['sample_type_id'] . '"  ' . $selected . '>' . $sample['sample_type_name'] . '</option>';
        }
    }
    $listData['sampleTypes'] = $sampleTypeOptions;


    //Get Test Reason options
    $testReasonList = $genericTestsService->getTestReason($testTypeId);
    $testReasonOptions = '<option value="">-- Select --</option>';
    if (!empty($testReasonList)) {
        foreach ($testReasonList as $reason) {
            $selected = '';
            if (!empty($_POST['testReasonId']) && $_POST['testReasonId'] == $reason['test_reason_id']) {
                $selected = "selected='selected'";
            }
            $testReasonOptions .= '<option value="' . $reason['test_reason_id'] . '"  ' . $selected . '>' . $reason['test_reason'] . '</option>';
        }
    }
    $listData['testReasons'] = $testReasonOptions;


    //Get Test Methods options
    $testMethodList = $genericTestsService->getTestMethod($testTypeId);
    $testMethodOptions = '<option value="">-- Select --</option>';
    if (!empty($testMethodList)) {
        foreach ($testMethodList as $method) {
            $selected = '';
            if (!empty($_POST['testMethodId']) && $_POST['testMethodId'] == $method['test_method_id']) {
                $selected = "selected='selected'";
            }
            $testMethodOptions .= '<option value="' . $method['test_method_id'] . '"  ' . $selected . '>' . $method['test_method_name'] . '</option>';
        }
    }
    $listData['testMethods'] = $testMethodOptions;


    //Get Test Result Units options
    $testResultUnitList = $genericTestsService->getTestResultUnit($testTypeId);
    $testResultUnitOptions = '<option value="">-- Select --</option>';
    if (!empty($testResultUnitList)) {
        foreach ($testResultUnitList as $reason) {
            $selected = '';
            if (!empty($_POST['testResultUnitId']) && $_POST['testResultUnitId'] == $reason['unit_id']) {
                $selected = "selected='selected'";
            }
            $testResultUnitOptions .= '<option value="' . $reason['unit_id'] . '"  ' . $selected . '>' . $reason['unit_name'] . '</option>';
        }
    }
    $listData['testResultUnits'] = $testResultUnitOptions;

    echo json_encode($listData);
}
