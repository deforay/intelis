<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['captchaStatus'] = 'fail';

$_POST['challenge_field'] = filter_var($_POST['challenge_field'], FILTER_SANITIZE_STRING);

if (!empty($_POST['challenge_field']) && $_SESSION['captchaCode'] == $_POST['challenge_field']) {
    $_SESSION['captchaStatus'] = 'success';
    echo "success";
} else {
    $_SESSION['captchaStatus'] = 'fail';
    echo "fail";
}
