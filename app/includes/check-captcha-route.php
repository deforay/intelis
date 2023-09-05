<?php

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$_POST['challenge_field'] = htmlspecialchars($_POST['challenge_field'], ENT_QUOTES);
if (!empty($_POST['challenge_field']) && $_SESSION['captchaCode'] == $_POST['challenge_field']) {
    $_SESSION['captchaStatus'] = 'success';
} else {
    $_SESSION['captchaStatus'] = 'fail';
}

echo $_SESSION['captchaStatus'];
