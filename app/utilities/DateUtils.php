<?php

namespace Vlsm\Utilities;

use Exception;
use DateTimeImmutable;

class DateUtils
{
    public function __construct()
    {
    }

    // Function to get the verify if date is in Y-m-d or specified format
    public function verifyDateFormat($date, $format = 'Y-m-d', $strict = true)
    {
        $dateTime = DateTimeImmutable::createFromFormat($format, $date);
        if ($strict) {
            $errors = DateTimeImmutable::getLastErrors();
            if (!empty($errors['warning_count']) || !empty($errors['error_count'])) {
                return false;
            }
        }
        return $dateTime !== false;
    }

    // Function to get the verify if date is valid or not
    public function verifyIfDateValid($date): bool
    {
        $dateTime = new DateTimeImmutable($date);

        $errors = DateTimeImmutable::getLastErrors();
        if (!empty($errors['warning_count']) || !empty($errors['error_count'])) {
            return false;
        } else {
            return true;
        }
    }

    // Returns the given date in d-M-Y format (with or without time depending on the $includeTime parameter)
    public function humanReadableDateFormat($date, $includeTime = false)
    {
        $date = trim($date);
        if (false === $this->verifyIfDateValid($date)) {
            return null;
        } else {
            $dateObj = new DateTimeImmutable($date);
            $newDate = $dateObj->format("d-M-Y");

            if ($includeTime === true) {
                $newDate .= " " . $dateObj->format("H:i:s");
            }

            return $newDate;
        }
    }

    // Returns current date time
    public static function getCurrentDateTime($returnFormat = 'Y-m-d H:i:s')
    {
        $date = new DateTimeImmutable(date('Y-m-d H:i:s'));
        return $date->format($returnFormat);
    }

    // Returns the given date in Y-m-d format
    public function isoDateFormat($date)
    {
        $date = trim($date);
        if (false === $this->verifyIfDateValid($date)) {
            return null;
        } else {
            $dateObj = new DateTimeImmutable($date);
            return $dateObj->format("Y-m-d");
        }
    }

    // returns age array in year, months, days
    public function ageInYearMonthDays($dateOfBirth)
    {
        $bday = new \DateTimeImmutable($dateOfBirth);
        $today = new \DateTimeImmutable();
        $diff = $today->diff($bday);
        // printf(' Your age : %d years, %d month, %d days', $diff->y, $diff->m, $diff->d);
        return array("year" => $diff->y, "months" => $diff->m, "days" => $diff->d);
    }
}
