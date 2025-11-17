<?php

namespace App\Utilities;

use DateTime;
use App\Utilities\DateUtility;
use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberParseException;

class ValidationUtility
{
    public static function validateMandatoryFields($fields): bool
    {
        foreach ($fields as $field) {
            if (in_array(trim((string) $field), ['', '0'], true)) {
                return false;
            }
        }
        return true;
    }
    public static function isValidNumber($input): bool
    {
        return is_numeric($input);
    }
    public static function isDateValid($input): bool
    {
        return DateUtility::isDateValid($input);
    }

    public static function hasFutureDates($dates): bool
    {
        return DateUtility::hasFutureDates($dates);
    }

    public static function isDateGreaterThan(?string $inputDate, ?string $comparisonDate): bool
    {
        return DateUtility::isDateGreaterThan($inputDate, $comparisonDate);
    }

    public static function isValidLength($input, $minLength = null, $maxLength = null): bool
    {
        $length = strlen((string) $input);
        if (!is_null($minLength) && $length < $minLength) {
            return false;
        }
        return !(!is_null($maxLength) && $length > $maxLength);
    }

    public static function isValidPhoneNumber(string $phoneNumberInput): bool
    {
        try {
            $phoneNumber = PhoneNumber::parse($phoneNumberInput);
            return $phoneNumber->isValidNumber();
        } catch (PhoneNumberParseException) {
            return false;
        }
    }


    public static function isValidEmail($email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isValidUrl($url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    public static function isAlpha($input): bool
    {
        return ctype_alpha((string) $input);
    }
    public static function isWithinRange($input, $min, $max): bool
    {
        return is_numeric($input) && $input >= $min && $input <= $max;
    }
    public static function matchesPattern($input, $pattern): bool
    {
        return preg_match($pattern, (string) $input) === 1;
    }
}
