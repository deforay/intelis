<?php

use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberParseException;

$data = [
    'isValid' => false
];

if (isset($_POST['phoneNumber'])) {
    $phoneNumberInput = $_POST['phoneNumber'];

    try {
        $phoneNumber = PhoneNumber::parse($phoneNumberInput);

        // This ensures the number is both possible and valid according to the library's data
        if ($phoneNumber->isValidNumber()) {
            $data['isValid'] = true;
        }

    } catch (PhoneNumberParseException $e) {
        // Invalid number format, you can log this error if needed
    }
}

echo json_encode($data);