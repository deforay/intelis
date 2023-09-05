<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\MiscUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();
$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;


$db = $db->where('api_track_id', $id);
$result = $db->getOne('track_api_requests');
$zip = new ZipArchive();
$request = $response = "{}";
$folder = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api');

$request = MiscUtility::getJsonFromZip($folder . DIRECTORY_SEPARATOR . 'requests' . DIRECTORY_SEPARATOR . $result['transaction_id'] . '.json.zip', $result['transaction_id'] . '.json');
$response = MiscUtility::getJsonFromZip($folder . DIRECTORY_SEPARATOR . 'responses' . DIRECTORY_SEPARATOR . $result['transaction_id'] . '.json.zip', $result['transaction_id'] . '.json');

?>

<link rel="stylesheet" media="all" type="text/css" href="/assets/css/fonts.css" />
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/css/font-awesome.min.css">
<link rel="stylesheet" href="/assets/css/skins/_all-skins.min.css">

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="bs-example bs-example-tabs">
            <div class="tab">
                <ul id="myTab" class="nav nav-tabs" style="font-size:1.4em;">
                    <?php if (!empty($result['api_params'])) {
                        $result['request_data'] = $result['api_params'];
                        $result['response_data'] = [];
                    ?>
                        <li class="active request" data-name="vl" data-toggle="tab" onclick="openTab('request', 'response')"><a href="javascript:void(0);"><?php echo _translate("API PARAMS"); ?></a></li>
                    <?php } else { ?>
                        <li class="active request" data-name="vl" data-toggle="tab" onclick="openTab('request', 'response')"><a href="javascript:void(0);"><?php echo _translate("REQUEST"); ?></a></li>
                        <li class="response" data-name="vl" data-toggle="tab" onclick="openTab('response', 'request')"><a href="javascript:void(0);"><?php echo _translate("RESPONSE"); ?></a></li>
                    <?php } ?>
                </ul>
            </div>
            <div id="myTabContent" class="tab-content">
                <div class="tab-pane fade in active" id="request" style="min-height:300px;">
                    <pre><?= MiscUtility::prettyJson($request); ?></pre>
                </div>
                <div class="tab-pane fade in" id="response" style="min-height:300px;">
                    <pre><?= MiscUtility::prettyJson($response); ?></pre>
                </div>
            </div>
    </section>
</div>
<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
<script type="text/javascript" src="/assets/js/bootstrap.min.js"></script>
<script type="text/javascript" src="/assets/js/main.js"></script>
<script type="text/javascript" src="/assets/js/app.min.js"></script>
<script>
    function openTab(active, inactive) {
        $('#' + active).show();
        $('#' + inactive).hide();
        $('.' + active).addClass('active');
        $('.' + inactive).removeClass('active');
    }
</script>
