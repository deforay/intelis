<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberParseException;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$data = ['isValid' => false];

if (isset($_POST['phoneNumber'])) {
    $phoneNumberInput = $_POST['phoneNumber'];
    $strictCheck = isset($_POST['strictCheck']) && $_POST['strictCheck'] === 'yes';

    try {
        $phoneNumber = PhoneNumber::parse($phoneNumberInput);

        $data['isValid'] = $strictCheck ? $phoneNumber->isValidNumber() : $phoneNumber->isPossibleNumber();
    } catch (PhoneNumberParseException) {
        $data['isValid'] = false;
    }
}

echo json_encode($data);
