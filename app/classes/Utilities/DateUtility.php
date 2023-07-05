<?php

namespace App\Utilities;

use Exception;
use DateTimeImmutable;

class DateUtility
{
    public function __construct()
    {
    }

    // Function to get the verify if date is in Y-m-d or specified format
    public static function isDateFormatValid($date, $format = 'Y-m-d', $strict = true): bool
    {
        $date = trim($date);

        if (empty($date) || 'undefined' === $date || 'null' === $date) {
            $response = false;
        } else {
            try {
                $dateTime = DateTimeImmutable::createFromFormat($format, $date);
                if ($strict) {
                    $errors = DateTimeImmutable::getLastErrors();
                    if (
                        empty($dateTime)
                        || !empty($errors['warning_count'])
                        || !empty($errors['error_count'])
                    ) {
                        $response = false;
                    }
                }
                $response = $dateTime !== false;
            } catch (Exception $e) {
                $response = false;
            }
        }
        return $response;
    }

    // Function to verify if date is valid or not
    public static function isDateValid($date): bool
    {
        $date = trim($date);

        if (empty($date) || 'undefined' === $date || 'null' === $date) {
            $response = false;
        } else {
            try {
                $dateTime = new DateTimeImmutable($date);
                $errors = DateTimeImmutable::getLastErrors();
                if (
                    !empty($errors['warning_count'])
                    || !empty($errors['error_count'])
                ) {
                    $response = false;
                } else {
                    $response = true;
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
                $response = false;
            }
        }

        return $response;
    }

    // Returns the given date in d-M-Y format
    // (with or without time depending on the $includeTime parameter)
    public static function humanReadableDateFormat($date, $includeTime = false, $format = "d-M-Y")
    {
        if (false === self::isDateValid($date)) {
            return null;
        } else {

            if ($includeTime === true) {
                $format = $format . " H:i";
            }

            return (new DateTimeImmutable($date))->format($format);
        }
    }

    // Returns current date time in Y-m-d H:i:s format or any specified format
    public static function getCurrentDateTime($format = 'Y-m-d H:i:s')
    {
        return (new DateTimeImmutable())->format($format);
    }

    // Returns the given date in Y-m-d format
    public static function isoDateFormat($date, $includeTime = false)
    {
        return once(function () use ($date, $includeTime) {
            if (false === self::isDateValid($date)) {
                return null;
            } else {
                $format = "Y-m-d";
                if ($includeTime === true) {
                    $format = $format . " H:i:s";
                }
                return (new DateTimeImmutable($date))->format($format);
            }
        });
    }

    // returns age array in year, months, days
    public static function ageInYearMonthDays($dateOfBirth)
    {
        if (false === self::isDateValid($dateOfBirth)) {
            return null;
        }
        $bday = new DateTimeImmutable($dateOfBirth);
        $today = new DateTimeImmutable();
        $diff = $today->diff($bday);
        return [
            "year" => $diff->y,
            "months" => $diff->m,
            "days" => $diff->d
        ];
    }

    public static function dateDiff($dateString1, $dateString2, $format = null)
    {
        if (false === self::isDateValid($dateString1) || false === self::isDateValid($dateString2)) {
            return null;
        }
        $datetime1 = new DateTimeImmutable($dateString1);
        $datetime2 = new DateTimeImmutable($dateString2);
        $interval = $datetime1->diff($datetime2);
        if ($format === null) {
            return $interval->format('%a days');
        } else {
            return $interval->format($format);
        }
    }


    /**
     * Checks if the given date string or date array contains any future dates.
     *
     * @param string|array $dates The date string or date array to check.
     * @return bool Returns true if any date is in the future, false otherwise.
     */
    public static function hasFutureDates($dates): bool
    {
        $now = new DateTimeImmutable();
        $dates = is_array($dates) ? $dates : [$dates];

        foreach ($dates as $dateStr) {
            if (!empty($dateStr) && $dateStr != "") {
                $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
                if ($date > $now) {
                    return true;
                }
            }
        }

        return false;
    }
}
