<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['captchaStatus'] = 'fail';

$_POST['challenge_field'] = htmlspecialchars($_POST['challenge_field'], ENT_QUOTES);
if (!empty($_POST['challenge_field']) && $_SESSION['captchaCode'] == $_POST['challenge_field']) {
    $_SESSION['captchaStatus'] = 'success';
} else {
    $_SESSION['captchaStatus'] = 'fail';
}

echo $_SESSION['captchaStatus'];
