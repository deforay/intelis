<?php
ob_start();
$imageData = "";
$general = new \Vlsm\Models\General();
$data = $_REQUEST['base64data'];
$image = explode('base64',$data);
$imageData = file_put_contents('uploads/screenshot/1.jpg',base64_decode($image[1]));
echo $imageData;
?>
