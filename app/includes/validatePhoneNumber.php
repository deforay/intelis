<?php

use App\Registries\AppRegistry;
use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberParseException;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$data = ['isValid' => false];

if (isset($_POST['phoneNumber'])) {
    $phoneNumberInput = $_POST['phoneNumber'];
    $strictCheck = isset($_POST['strictCheck']) && $_POST['strictCheck'] === 'yes';

    try {
        $phoneNumber = PhoneNumber::parse($phoneNumberInput);

        if ($strictCheck) {
            $data['isValid'] = $phoneNumber->isValidNumber();
        } else {
            // a more lenient and faster check than `isValidNumber()`
            $data['isValid'] = $phoneNumber->isPossibleNumber();
        }
    } catch (PhoneNumberParseException $e) {
        $data['isValid'] = false;
    }
}

echo json_encode($data);
