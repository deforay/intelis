<?php
$id = base64_decode($_GET['id']);
$db = $db->where('api_track_id', $id);
$result = $db->getOne('track_api_requests');
echo "<pre>";
print_r($result['api_params']);
