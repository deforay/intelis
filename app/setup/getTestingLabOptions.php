<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$labNameList = $facilitiesService->getTestingLabs();


if (!empty($labNameList)) { ?>
    <option value="">
        <?php echo _translate("-- Choose Testing Lab --"); ?>
    </option>
    <?php foreach ($labNameList as $key => $value) { ?>
        <option value="<?= $key; ?>"><?php echo $value; ?></option>
    <?php } ?>
<?php
} else {
    echo 0;
}
