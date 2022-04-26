<?php

if (!isset($_SESSION['userId'])) {
    header("Location:/login.php");
}

if (!isset($_GET['f']) || !is_file(base64_decode($_GET['f']))) {
    $redirect = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
    header("Location:" . $redirect);
}

$file = base64_decode($_GET['f']);

$disposition = (isset($_GET['d']) && $_GET['d'] = 'a') ? 'attachment' : 'inline';

$mime = mime_content_type($file);

header('Content-Description: File Transfer');
header('Content-Type: ' . (($mime !== false) ? $mime : 'application/octet-stream'));
header('Content-Disposition: ' . $disposition . '; filename=' . basename($file));
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($file));
ob_clean();
flush();
readfile($file);