<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;





/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$packageTable = "covid19_positive_confirmation_manifest";
try {
    if (isset($_POST['manifestCode']) && trim($_POST['manifestCode']) != "" && !empty($_POST['sampleCode'])) {
        $lastId = $_POST['manifestId'];
        $db->where('manifest_id', $lastId);
        $db->update($packageTable, array('manifest_status' => $_POST['manifestStatus']));

        if ($lastId > 0) {
            $value = array(
                'positive_test_manifest_id'   => null,
                'positive_test_manifest_code' => null
            );
            $db = $db->where('positive_test_manifest_code', $lastId);
            $db->update('form_covid19', $value);

            for ($j = 0; $j < count($_POST['sampleCode']); $j++) {
                $value = array(
                    'positive_test_manifest_id'   => $lastId,
                    'positive_test_manifest_code' => $_POST['manifestCode']
                );

                $db = $db->where('covid19_id', $_POST['sampleCode'][$j]);
                $db->update('form_covid19', $value);
            }
            $_SESSION['alertMsg'] = "Manifest details updated successfully";
            header("Location:/covid-19/results/covid-19-confirmation-manifest.php");
        } else {
            $_SESSION['alertMsg'] = "Something went wrong please try again later";
            header("Location:/covid-19/results/covid-19-add-confirmation-manifest.php");
        }
    } else {
        $_SESSION['alertMsg'] = "Please select the sample code to processed";
        header("Location:/covid-19/results/covid-19-edit-confirmation-manifest.php?id='" . base64_encode($_POST['manifestId']) . "'");
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
