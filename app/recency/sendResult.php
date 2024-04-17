<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use GuzzleHttp\Client;

try {




    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);


    $vlTestResultQuery = "SELECT remote_sample_code,
                            result,sample_tested_datetime,
                            recency_vl,recency_sync
                            FROM form_vl
                            WHERE recency_vl ='yes'
                            AND recency_sync = '0'
                            AND result != ''
                            AND result is NOT NULL";
    $vlTestResult = $db->query($vlTestResultQuery);
    $client = new Client();

    $domain = rtrim((string) SYSTEM_CONFIG['recency']['url'], "/");
    $urlCart = $domain . '/api/vl-test-result';

    foreach ($vlTestResult as $result) {
        if (isset($result['result']) && $result['result'] != "") {
            $resultCart = $client->post($urlCart, [
                'form_params' => [
                    'sampleId' => $result['remote_sample_code'],
                    'result' => $result['result'],
                    'sampleTestedDatetime' => $result['sample_tested_datetime'],
                    'recencyVl' => $result['recency_vl']
                ]
            ]);
            $responseCart = $resultCart->getBody()->getContents();
            $response = json_decode($responseCart);
            if (isset($response->status) && $response->status == "success") {
                $data['recency_sync'] = '1';
                $db->update('form_vl', $data);
                $db->where('remote_sample_code', $result['remote_sample_code']);
            }
        }
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
}
