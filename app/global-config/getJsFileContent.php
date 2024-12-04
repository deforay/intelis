<?php

$jsFormat = $_POST['formatType'];


if($jsFormat == 'zebra-printer'){
    $path = 'public/uploads'. DIRECTORY_SEPARATOR . 'barcode-formats' . DIRECTORY_SEPARATOR . 'zebra-format.js';
}
elseif($jsFormat == 'dymo-labelwriter-450'){
    $path = 'public/uploads'. DIRECTORY_SEPARATOR . 'barcode-formats' . DIRECTORY_SEPARATOR . 'dymo-format.js';
}
$content = file_get_contents($path);
if (preg_match('/`([^`]+)`/', $content, $m)) {
    echo $m[1];   
}
else{
    echo false;
}
