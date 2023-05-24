<?php
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

echo $genericTestsService->getInterpretationResults($_POST['testType'], $_POST['result']);