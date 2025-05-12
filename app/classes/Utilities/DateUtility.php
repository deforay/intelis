<?php

namespace App\Utilities;

use Throwable;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use App\Utilities\MemoUtility;
use App\Exceptions\SystemException;

final class DateUtility
{
    public static function isDateFormatValid($date, $format = 'Y-m-d', $strict = true): bool
    {
        $date = trim((string) $date);

        if (empty($date) || 'undefined' === $date || 'null' === $date) {
            return false;
        }

        $carbonDate = self::parseDate($date, [$format]);

        return $carbonDate && (!$strict || $carbonDate->format($format) === $date);
    }

    public static function getDateTime(?string $date, string $format = 'Y-m-d H:i:s'): ?string
    {
        if (!self::isDateValid($date)) {
            return null;
        }

        return Carbon::parse($date)->format($format);
    }

    public static function daysAgo(int $days, string $format = 'Y-m-d'): string
    {
        return Carbon::now()->subDays($days)->format($format);
    }



    public static function isDateValid($date): bool
    {
        $date = trim((string) $date);

        // Immediately return false if date is blank or contains any placeholder characters
        // like underscores, asterisks, or multiple consecutive hyphens
        if (empty($date) || 'undefined' === $date || 'null' === $date || preg_match('/[_*]|--/', $date)) {
            return false;
        }

        return self::parseDate($date) !== null;
    }

    public static function humanReadableDateFormat($date, $includeTime = false, $format = null, $withSeconds = false)
    {
        return MemoUtility::remember(function () use ($date, $includeTime, $format, $withSeconds) {

            if (!self::isDateValid($date)) {
                return null;
            }

            $format ??= $_SESSION['phpDateFormat'] ?? 'd-M-Y';

            // Check if the format already includes time components
            $hasTimeComponent = preg_match('/[HhGgis]/', $format);

            // If the format doesn't have a time component and $includeTime is true, append the appropriate time format
            if ($includeTime && !$hasTimeComponent) {
                $format .= $withSeconds ? ' H:i:s' : ' H:i';
            }

            return Carbon::parse($date)->format($format);
        });
    }


    public static function getCurrentDateTime($format = 'Y-m-d H:i:s')
    {
        return Carbon::now()->format($format);
    }

    public static function isoDateFormat($date, $includeTime = false)
    {
        return MemoUtility::remember(function () use ($date, $includeTime) {
            if (!self::isDateValid($date)) {
                return null;
            }

            $format = ($includeTime !== true) ? "Y-m-d" : "Y-m-d H:i:s";
            return Carbon::parse($date)->format($format);
        });
    }

    public static function ageInYearMonthDays($dateOfBirth)
    {
        return MemoUtility::remember(function () use ($dateOfBirth) {

            if (!self::isDateValid($dateOfBirth)) {
                return null;
            }

            $diff = Carbon::now()->diff(Carbon::parse($dateOfBirth));
            return [
                "year" => $diff->y,
                "months" => $diff->m,
                "days" => $diff->d
            ];
        });
    }

    public static function dateDiff($dateString1, $dateString2, $format = null)
    {
        if (!self::isDateValid($dateString1) || !self::isDateValid($dateString2)) {
            return null;
        }

        $interval = Carbon::parse($dateString1)->diff(Carbon::parse($dateString2));
        return $format === null ? $interval->format('%a days') : $interval->format($format);
    }


    public static function hasFutureDates($dates, ?array $formats = null): bool
    {
        $now = Carbon::now();
        $dates = is_array($dates) ? $dates : [$dates];

        foreach ($dates as $dateStr) {
            if (!empty($dateStr)) {
                $date = self::parseDate($dateStr, $formats);
                if ($date && $date->greaterThan($now)) {
                    return true;
                }
            }
        }

        return false;
    }
    private static function parseDate(string $dateStr, ?array $formats = null, $ignoreTime = true): ?Carbon
    {

        if ($ignoreTime === true) {
            $dateStr = explode(' ', $dateStr)[0]; // Extract only the date part
        }
        if ($formats) {
            foreach ($formats as $format) {
                try {
                    return Carbon::createFromFormat($format, $dateStr);
                } catch (Throwable $e) {
                    LoggerUtility::logError(
                        "Invalid or unparseable date $dateStr : " . $e->getMessage(),
                        [
                            'line' => $e->getLine(),
                            'file' => $e->getFile(),
                            'trace' => $e->getTraceAsString()
                        ]
                    );
                    continue;
                }
            }
        }
        try {
            return Carbon::parse($dateStr);
        } catch (Throwable $e) {
            LoggerUtility::logError(
                "Invalid or unparseable date $dateStr : " . $e->getMessage(),
                [
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString()
                ]
            );
        }

        return null;
    }

    /**
     * Checks if one date is greater than another.
     *
     * @param string|null $inputDate The date to compare from.
     * @param string|null $comparisonDate The date to compare against.
     * @return bool Returns true if $inputDate is greater than $comparisonDate, otherwise false.
     *              Returns false if any date is null or invalid.
     */
    public static function isDateGreaterThan(?string $inputDate, ?string $comparisonDate): bool
    {
        try {
            // Validate and parse dates
            $parsedInputDate = $inputDate ? Carbon::parse($inputDate) : null;
            $parsedComparisonDate = $comparisonDate ? Carbon::parse($comparisonDate) : null;

            // Check if either date is null after attempting to parse
            if (!$parsedInputDate || !$parsedComparisonDate) {
                // Optionally, you can log these errors or handle them as needed
                return false;
            }

            return $parsedInputDate->gt($parsedComparisonDate);
        } catch (Throwable $e) {
            // Handle or log the error appropriately
            // This catches cases where Carbon could not parse the date strings
            return false;
        }
    }
    /**
     * Compares a given datetime against a modified datetime by a specified interval.
     *
     * @param string $datetime The base datetime for the comparison.
     * @param string $operator The comparison operator ('>' or '<').
     * @param string $interval A string describing the interval (e.g., '10 days', '3 months', '-5 years', '2 hours').
     * @return bool Returns true if the comparison is true, false otherwise.
     */
    public static function compareDateWithInterval(string $datetime, string $operator, string $interval): bool
    {
        $carbonDate = Carbon::parse($datetime);
        $modifiedDate = clone $carbonDate;

        // Check if interval is negative
        if (str_starts_with($interval, '-')) {
            // Subtract interval: remove the '-' and subtract
            $modifiedDate->sub(CarbonInterval::createFromDateString(ltrim($interval, '-')));
        } else {
            // Add interval
            $modifiedDate->add(CarbonInterval::createFromDateString($interval));
        }

        // Perform the comparison based on the operator
        return match ($operator) {
            '>' => $carbonDate->greaterThan($modifiedDate),
            '<' => $carbonDate->lessThan($modifiedDate),
            default => throw new SystemException("Invalid comparison operator: $operator. Use '>' or '<'."),
        };
    }

    public static function convertDateRange(?string $dateRange, $seperator = "to"): array
    {
        return MemoUtility::remember(function () use ($dateRange, $seperator) {
            if (empty($dateRange)) {
                return ['', ''];
            }

            $dates = explode($seperator, $dateRange ?? '');
            $dates = array_map('trim', $dates);

            $startDate = !empty($dates[0]) ? self::isoDateFormat($dates[0]) : '';
            $endDate = !empty($dates[1]) ? self::isoDateFormat($dates[1]) : '';

            return [$startDate, $endDate];
        });
    }

    /**
     * Returns the date that is a certain number of months before the current date.
     *
     * @param int $months The number of months to subtract.
     * @return string The date in 'Y-m-d' format.
     */
    public static function getDateBeforeMonths(int $months)
    {
        return Carbon::now()->subMonths($months)->format('Y-m-d');
    }


    /**
     * Filters and returns only valid dates from an array of date strings.
     *
     * @param array $dates An array of date strings.
     * @return array An array containing only valid date strings.
     */
    private static function filterValidDates(array $dates): array
    {
        return array_filter($dates, function ($date) {
            return self::isDateValid($date);
        });
    }

    /**
     * Returns the earliest date among a variable number of given dates.
     *
     * @param string ...$dates A variable number of date strings.
     * @return string|null The earliest date in 'Y-m-d H:i:s' format, or null if all dates are invalid or no dates are provided.
     */
    public static function getLowestDate(...$dates)
    {
        // Filter out invalid dates
        $validDates = self::filterValidDates($dates);

        // If there are no valid dates, return null
        if (empty($validDates)) {
            return null;
        }

        $earliestDate = null;

        foreach ($validDates as $date) {
            $carbonDate = Carbon::parse($date);

            if (is_null($earliestDate) || $carbonDate->lt($earliestDate)) {
                $earliestDate = $carbonDate;
            }
        }

        return $earliestDate->format('Y-m-d H:i:s');
    }
    /**
     * Returns the latest date among a variable number of given dates.
     *
     * @param string ...$dates A variable number of date strings.
     * @return string|null The latest date in 'Y-m-d H:i:s' format, or null if all dates are invalid or no dates are provided.
     */
    public static function getHighestDate(...$dates)
    {
        // Filter out invalid dates
        $validDates = self::filterValidDates($dates);

        // If there are no valid dates, return null
        if (empty($validDates)) {
            return null;
        }

        $latestDate = null;

        foreach ($validDates as $date) {
            $carbonDate = Carbon::parse($date);

            if (is_null($latestDate) || $carbonDate->gt($latestDate)) {
                $latestDate = $carbonDate;
            }
        }

        return $latestDate->format('Y-m-d H:i:s');
    }

    /**
     * Calculates the age of a patient from their date of birth, age in years, or age in months.
     *
     * @param array $result Array containing patient's date of birth ('patient_dob'),
     *                      age in years ('patient_age_in_years'), or age in months ('patient_age_in_months').
     * @return string The calculated age as a string, with years or months specified as appropriate.
     */
    public static function calculatePatientAge($result)
    {
        if (!isset($result['patient_dob']) && !isset($result['patient_age_in_years']) && !isset($result['patient_age']) && !isset($result['patient_age_in_months'])) {
            return _translate('Unknown');
        }


        // Directly use age in years if provided and valid, considering both possible keys
        $ageInYearsKey = isset($result['patient_age_in_years']) ? 'patient_age_in_years' : 'patient_age';
        if (isset($result[$ageInYearsKey]) && is_numeric($result[$ageInYearsKey]) && $result[$ageInYearsKey] > 0) {
            $age = (int)$result[$ageInYearsKey];
            return $age . ' ' . ($age > 1 ? _translate('years') : _translate('year'));
        }

        // Check for valid DOB and calculate age in years
        if (!empty($result['patient_dob']) && $result['patient_dob'] !== '0000-00-00' && self::isDateFormatValid($result['patient_dob'])) {
            $dob = Carbon::createFromFormat('Y-m-d', $result['patient_dob']);
            $age = Carbon::now()->diffInYears($dob);
            return $age . ' ' . ($age > 1 ? _translate('years') : _translate('year'));
        }

        // Convert age in months to appropriate format
        if (isset($result['patient_age_in_months']) && is_numeric($result['patient_age_in_months']) && $result['patient_age_in_months'] > 0) {
            $months = (int)$result['patient_age_in_months'];
            return $months . ' ' . ($months > 1 ? _translate('months') : _translate('month'));
        }

        // Default case if none of the above conditions are met
        return _translate('Unknown');
    }
}
