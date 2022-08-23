<?php
$id = base64_decode($_GET['id']);
$db = $db->where('api_track_id', $id);
$result = $db->getOne('track_api_requests');
$result['request_data'] = (isset($result['request_data']) && !empty($result['request_data'])) ? $result['request_data'] : '';
$result['response_data'] = (isset($result['response_data']) && !empty($result['response_data'])) ? json_decode($result['response_data']) : '';
?>
<script src="/assets/js/bootstrap.min.js"></script>
<link rel="stylesheet" media="all" type="text/css" href="/assets/css/fonts.css" />
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/css/font-awesome.min.6.1.1.css">
<link rel="stylesheet" href="/assets/css/skins/_all-skins.min.css">
<script type="text/javascript" src="/assets/js/jquery.min.js"></script>
<script type="text/javascript" src="/assets/js/jquery-ui.min.js"></script>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="bs-example bs-example-tabs">
            <div class="tab">
                <ul id="myTab" class="nav nav-tabs" style="font-size:1.4em;">
                    <li class="active request" data-name="vl" data-toggle="tab" onclick="openTab('request', 'response')"><a href="javascript:void(0);"><?php echo _("REQUEST"); ?></a></li>
                    <li class="response" data-name="vl" data-toggle="tab" onclick="openTab('response', 'request')"><a href="javascript:void(0);"><?php echo _("RESPONSE"); ?></a></li>
                </ul>
            </div>
            <div id="myTabContent" class="tab-content">
                <div class="tab-pane fade in active" id="request">
                    <pre><?php print_r($result['request_data']); ?></pre>
                </div>
                <div class="tab-pane fade in" id="response">
                    <pre><?php print_r($result['response_data']); ?></pre>
                </div>
            </div>
    </section>
</div>
<script src="/assets/js/main.js"></script>
<script src="/assets/js/app.min.js"></script>
<script src="/assets/js/bootstrap.min.js"></script>
<script>
    function openTab(active, inactive) {
        $('#' + active).show();
        $('#' + inactive).hide();
        $('.' + active).addClass('active');
        $('.' + inactive).removeClass('active');
    }
</script>