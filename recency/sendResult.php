<?php
try {
    ob_start();
    #require_once('../startup.php');  


    $general = new \Vlsm\Models\General();
    // Define path to guzzle directory
    /** Zend_Application */
    // require_once APPLICATION_PATH.'/includes/Zend/Application.php';
    require APPLICATION_PATH . '/vendor/guzzle/autoload.php';

    $vlTestResultQuery = "SELECT remote_sample_code,result,sample_tested_datetime,recency_vl,recency_sync from vl_request_form WHERE recency_vl ='yes' AND recency_sync = '0' AND result != '' and result is NOT NULL";
    $vlTestResult = $db->query($vlTestResultQuery);
    $client = new \GuzzleHttp\Client();

    $domain = rtrim($systemConfig['recency']['url'], "/");
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
                $db->update('vl_request_form', $data);
                $db = $db->where('remote_sample_code', $result['remote_sample_code']);
            }
        }
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
