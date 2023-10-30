<?php

namespace App\Utilities;

use DateTime;
use App\Utilities\DateUtility;
use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberParseException;

class ValidationUtility
{
    public static function validateMandatoryFields($fields)
    {
        foreach ($fields as $field) {
            if (empty(trim($field))) {
                return false;
            }
        }
        return true;
    }
    public static function isValidNumber($input)
    {
        return is_numeric($input);
    }
    public static function isDateValid($input)
    {
        return DateUtility::isDateValid($input);
    }

    public static function hasFutureDates($dates): bool
    {
        return DateUtility::hasFutureDates($dates);
    }

    public static function isDateGreaterThan($inputDate, $comparisonDate)
    {
        return DateUtility::isDateGreaterThan($inputDate, $comparisonDate);
    }

    public static function isValidLength($input, $minLength = null, $maxLength = null)
    {
        $length = strlen($input);
        if (!is_null($minLength) && $length < $minLength) {
            return false;
        }
        if (!is_null($maxLength) && $length > $maxLength) {
            return false;
        }
        return true;
    }

    public static function isValidPhoneNumber($phoneNumberInput)
    {
        try {
            $phoneNumber = PhoneNumber::parse($phoneNumberInput);
            return $phoneNumber->isValidNumber();
        } catch (PhoneNumberParseException $e) {
            return false;
        }
    }


    public static function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isValidUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    public static function isAlpha($input)
    {
        return ctype_alpha($input);
    }
    public static function isWithinRange($input, $min, $max)
    {
        return is_numeric($input) && $input >= $min && $input <= $max;
    }
    public static function matchesPattern($input, $pattern)
    {
        return preg_match($pattern, $input) === 1;
    }
}
