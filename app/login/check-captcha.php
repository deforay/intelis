<?php

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;

$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$_POST['challenge_field'] = htmlspecialchars((string) $_POST['challenge_field'], ENT_QUOTES);
if (isset($_POST['challenge_field']) && ($_POST['challenge_field'] !== '' && $_POST['challenge_field'] !== '0') && $_SESSION['captchaCode'] == $_POST['challenge_field']) {
    $_SESSION['captchaStatus'] = 'success';
} else {
    $_SESSION['captchaStatus'] = 'fail';
}

echo $_SESSION['captchaStatus'];
