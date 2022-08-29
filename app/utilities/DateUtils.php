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
    public function verifyDateFormat($date, $format = 'Y-m-d', $strict = true): bool
    {
        $date = trim($date);

        if (empty($date) || 'undefined' === $date || 'null' === $date) {
            return false;
        } else {
            try {
                $dateTime = DateTimeImmutable::createFromFormat($format, $date);
                if ($strict) {
                    $errors = DateTimeImmutable::getLastErrors();
                    if (!empty($errors['warning_count']) || !empty($errors['error_count'])) {
                        //error_log("Invalid date format ($format) :: $date");
                        return false;
                    }
                }
                return $dateTime !== false;
            } catch (Exception $e) {
                //error_log("Invalid date format ($format) :: $date :: " . $e->getMessage());
                return false;
            }
        }
    }

    // Function to get the verify if date is valid or not
    public function verifyIfDateValid($date): bool
    {
        $date = trim($date);

        if (empty($date) || 'undefined' === $date || 'null' === $date) {
            return false;
        } else {
            try {
                $dateTime = new DateTimeImmutable($date);
                $errors = DateTimeImmutable::getLastErrors();
                if (!empty($errors['warning_count']) || !empty($errors['error_count'])) {
                    //error_log("Invalid date :: $date");
                    return false;
                } else {
                    return true;
                }
            } catch (Exception $e) {
                //error_log("Invalid date :: $date :: " . $e->getMessage());
                return false;
            }
        }
    }

    // Returns the given date in d-M-Y format 
    // (with or without time depending on the $includeTime parameter)
    public function humanReadableDateFormat($date, $includeTime = false, $format = "d-M-Y")
    {
        $date = trim($date);
        if (false === $this->verifyIfDateValid($date)) {
            return null;
        } else {

            if ($includeTime === true) {
                $format = $format . " H:i:s";
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
    public function isoDateFormat($date, $includeTime = false)
    {
        $date = trim($date);
        if (false === $this->verifyIfDateValid($date)) {
            return null;
        } else {
            $format = "Y-m-d";
            if ($includeTime === true) {
                $format = $format . " H:i:s";
            }
            return (new DateTimeImmutable($date))->format($format);            
        }
    }

    // returns age array in year, months, days
    public function ageInYearMonthDays($dateOfBirth)
    {
        $bday = new DateTimeImmutable($dateOfBirth);
        $today = new DateTimeImmutable();
        $diff = $today->diff($bday);
        // printf(' Your age : %d years, %d month, %d days', $diff->y, $diff->m, $diff->d);
        return array("year" => $diff->y, "months" => $diff->m, "days" => $diff->d);
    }
}
