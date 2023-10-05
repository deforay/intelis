<?php

namespace App\Utilities;

use Exception;
use Carbon\Carbon;

class DateUtility
{
    public static function isDateFormatValid($date, $format = 'Y-m-d', $strict = true): bool
    {
        $date = trim($date);

        if (empty($date) || 'undefined' === $date || 'null' === $date) {
            return false;
        }

        try {
            $carbonDate = Carbon::createFromFormat($format, $date);
            return $strict ? $carbonDate->format($format) === $date : true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function isDateValid($date): bool
    {
        $date = trim($date);

        if (empty($date) || 'undefined' === $date || 'null' === $date) {
            return false;
        }

        try {
            new Carbon($date);
            return true;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public static function humanReadableDateFormat($date, $includeTime = false, $format = null)
    {
        if (!self::isDateValid($date)) {
            return null;
        }

        $format = $format ??  $_SESSION['phpDateFormat'] ?? 'd-M-Y';

        $format = $includeTime ? $format . " H:i" : $format;
        return Carbon::parse($date)->format($format);
    }

    public static function getCurrentDateTime($format = 'Y-m-d H:i:s')
    {
        return Carbon::now()->format($format);
    }

    public static function isoDateFormat($date, $includeTime = false)
    {
        if (!self::isDateValid($date)) {
            return null;
        }

        $format = $includeTime ? "Y-m-d H:i:s" : "Y-m-d";
        return Carbon::parse($date)->format($format);
    }

    public static function ageInYearMonthDays($dateOfBirth)
    {
        if (!self::isDateValid($dateOfBirth)) {
            return null;
        }

        $diff = Carbon::now()->diff(Carbon::parse($dateOfBirth));
        return [
            "year" => $diff->y,
            "months" => $diff->m,
            "days" => $diff->d
        ];
    }

    public static function dateDiff($dateString1, $dateString2, $format = null)
    {
        if (!self::isDateValid($dateString1) || !self::isDateValid($dateString2)) {
            return null;
        }

        $interval = Carbon::parse($dateString1)->diff(Carbon::parse($dateString2));
        return $format === null ? $interval->format('%a days') : $interval->format($format);
    }

    public static function hasFutureDates($dates): bool
    {
        $now = Carbon::now();
        $dates = is_array($dates) ? $dates : [$dates];

        foreach ($dates as $dateStr) {
            if (!empty($dateStr) && $dateStr != "") {
                $date = Carbon::createFromFormat('Y-m-d', $dateStr);
                if ($date->greaterThan($now)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function convertDateRange(?string $dateRange): array
    {
        if (empty($dateRange)) {
            return ['', ''];
        }

        $dates = explode("to", $dateRange ?? '');
        $dates = array_map('trim', $dates);

        $startDate = !empty($dates[0]) ? self::isoDateFormat($dates[0]) : '';
        $endDate = !empty($dates[1]) ? self::isoDateFormat($dates[1]) : '';

        return [$startDate, $endDate];
    }
}
