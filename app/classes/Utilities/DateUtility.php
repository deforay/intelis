<?php

namespace App\Utilities;

use Exception;
use Throwable;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use App\Exceptions\SystemException;

final class DateUtility
{
    public static function isDateFormatValid($date, string $format = 'Y-m-d', $strict = true): bool
    {
        $date = trim((string) $date);

        if ($date === '' || $date === '0' || 'undefined' === $date || 'null' === $date) {
            return false;
        }

        $carbonDate = self::parseDate($date, [$format], ignoreTime: false);

        return $carbonDate && (!$strict || $carbonDate->format($format) === $date);
    }

    public static function getDateTime(?string $date, string $format = 'Y-m-d H:i:s', ?string $inputFormat = null): ?string
    {
        if (null === $date) {
            return null;
        }

        $inputFormat = ($inputFormat === '' || $inputFormat === '0') ? null : $inputFormat;
        $normalizedDate = self::normalizeDateString(trim($date));

        // Not a date (in the expected format): there is nothing to convert
        $isDate = $inputFormat === null
            ? self::isDateValid($normalizedDate)
            : self::isDateFormatValid($normalizedDate, $inputFormat, true);
        if (!$isDate) {
            return null;
        }

        try {
            if ($inputFormat !== null) {
                $carbonDate = Carbon::createFromFormat($inputFormat, $normalizedDate);
            } elseif (ctype_digit($normalizedDate)) {
                // isDateValid only lets 10+ digit numbers through: Unix timestamps
                $carbonDate = Carbon::createFromTimestamp((int) $normalizedDate);
            } else {
                $carbonDate = Carbon::parse($normalizedDate);
            }
            return $carbonDate instanceof Carbon ? $carbonDate->format($format) : null;
        } catch (Throwable $e) {
            LoggerUtility::logError("DateUtility::getDateTime: Error processing date '$date': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Returns the current timestamp in Unix format.
     *
     * @return int The current Unix timestamp.
     */
    public static function getCurrentTimestamp(): int
    {
        return Carbon::now()->timestamp;
    }

    public static function daysAgo(int $days, string $format = 'Y-m-d'): string
    {
        return Carbon::now()->subDays($days)->format($format);
    }

    /**
     * Add days to a given date.
     *
     * @param string $date The base date
     * @param int $days Number of days to add
     * @param string $format Output format (default: 'Y-m-d H:i:s')
     * @return string The resulting date
     */
    public static function addDays(string $date, int $days, string $format = 'Y-m-d H:i:s'): string
    {
        return Carbon::parse($date)->addDays($days)->format($format);
    }

    /**
     * Subtract days from a given date.
     *
     * @param string $date The base date
     * @param int $days Number of days to subtract
     * @param string $format Output format (default: 'Y-m-d H:i:s')
     * @return string The resulting date
     */
    public static function subDays(string $date, int $days, string $format = 'Y-m-d H:i:s'): string
    {
        return Carbon::parse($date)->subDays($days)->format($format);
    }

    /**
     * Whether a value holds a date. "No" is an answer, not an error, so
     * nothing is logged: a listing or an export asks this for every date cell,
     * and logging each blank or junk value used to fill the error log.
     */
    public static function isDateValid(mixed $date): bool
    {
        $date = trim((string) $date);

        if (
            $date === '' || $date === '0'
            || in_array($date, ['undefined', 'null'], true)
            || preg_match('/[_*]|--/', $date)
            // MySQL's zero date: parses as 30-Nov--0001, and is never a real date
            || str_starts_with($date, '0000-00-00')
        ) {
            return false;
        }

        if (ctype_digit($date)) {
            // A Unix timestamp (10+ digits) between 1970 and 2100; shorter
            // numbers are ages, counts or codes, not dates
            return strlen($date) >= 10 && (int) $date <= 4102444800;
        }

        return self::parseDate($date, ignoreTime: true) instanceof Carbon;
    }


    /**
     * The date in the user's display format, or null when it is not a date.
     *
     * Not memoized: formatting takes microseconds, while the cross-request
     * memo it used to go through wrote a cache file for every distinct date
     * shown (an export of 20,000 rows wrote tens of thousands) and, keyed
     * without the user's date format, could show one user's format to another.
     */
    public static function humanReadableDateFormat(
        $date,
        $includeTime = false,
        ?string $format = null,
        $withSeconds = false
    ): ?string {
        if (!self::isDateValid($date)) {
            return null;
        }

        $format ??= $_SESSION['phpDateFormat'] ?? 'd-M-Y';

        // Append the time unless the format already carries it
        if ($includeTime && !preg_match('/[HhGgis]/', $format)) {
            $format .= $withSeconds ? ' H:i:s' : ' H:i';
        }

        return Carbon::parse(self::normalizeDateString((string) $date))->format($format);
    }


    public static function getCurrentDateTime(string $format = 'Y-m-d H:i:s'): string
    {
        return Carbon::now()->format($format);
    }

    /** The date as Y-m-d (or Y-m-d H:i:s), or null when it is not a date. Not memoized, see above. */
    public static function isoDateFormat($date, $includeTime = false): ?string
    {
        if (!self::isDateValid($date)) {
            return null;
        }

        $format = ($includeTime !== true) ? "Y-m-d" : "Y-m-d H:i:s";
        return Carbon::parse(self::normalizeDateString((string) $date))->format($format);
    }

    /**
     * Completed whole years lived since a date of birth, or null when the date
     * cannot yield one.
     *
     * Result PDFs used to divide a timestamp difference by 365 days and round
     * the quotient. That rounded a patient of 41 years 7 months up to 42, drifted
     * by a day every leap year, and turned a date of birth in the future into a
     * negative age.
     */
    public static function completedYears($dateOfBirth): ?int
    {
        if (!self::isDateValid($dateOfBirth)) {
            return null;
        }

        $dob = Carbon::parse(self::normalizeDateString((string) $dateOfBirth))->startOfDay();
        $now = Carbon::now()->startOfDay();

        // Someone not yet born has no age
        if ($dob->greaterThan($now)) {
            return null;
        }

        return (int) $dob->diffInYears($now, true);
    }

    /** @return array{year: int, months: int, days: int}|null */
    public static function ageInYearMonthDays($dateOfBirth): ?array
    {
        if (!self::isDateValid($dateOfBirth)) {
            return null;
        }

        $dob = Carbon::parse(self::normalizeDateString((string) $dateOfBirth));

        // A date of birth in the future has no age. DateInterval components
        // are unsigned, so without this a future DOB reads as a real age.
        if ($dob->greaterThan(Carbon::now())) {
            return null;
        }

        $diff = Carbon::now()->diff($dob);
        return [
            "year" => $diff->y,
            "months" => $diff->m,
            "days" => $diff->d
        ];
    }

    public static function dateDiff($dateString1, $dateString2, $format = null): ?string
    {
        if (!self::isDateValid($dateString1) || !self::isDateValid($dateString2)) {
            return null;
        }

        $interval = Carbon::parse(self::normalizeDateString((string) $dateString1))
            ->diff(Carbon::parse(self::normalizeDateString((string) $dateString2)));
        return $format === null ? $interval->format('%a days') : $interval->format($format);
    }


    public static function hasFutureDates($dates, ?array $formats = null): bool
    {
        $now = Carbon::now();
        $dates = is_array($dates) ? $dates : [$dates];

        foreach ($dates as $dateStr) {
            if (!empty($dateStr)) {
                $date = self::parseDate($dateStr, $formats, ignoreTime: true);
                if ($date && $date->greaterThan($now)) {
                    return true;
                }
            }
        }

        return false;
    }
    private static function parseDate(string $dateStr, ?array $formats = null, $ignoreTime = false): ?Carbon
    {
        $dateStr = self::normalizeDateString($dateStr);

        if ($ignoreTime === true) {
            $dateStr = explode(' ', $dateStr)[0]; // Extract only the date part
        }
        // Unparseable input is the expected "not a date" answer here, so it
        // returns null without logging (see isDateValid)
        foreach ($formats ?? [] as $format) {
            try {
                return Carbon::createFromFormat($format, $dateStr);
            } catch (Throwable) {
                continue;
            }
        }
        try {
            return Carbon::parse($dateStr);
        } catch (Throwable) {
            return null;
        }
    }
    private static function normalizeDateString(string $dateStr): string
    {
        $replacements = [
            '/\bjanv\.?\b/iu' => 'jan',
            '/\bfévr\.?\b/iu' => 'feb',
            '/\bfevr\.?\b/iu' => 'feb',
            '/\bmars\b/iu' => 'mar',
            '/\bavr\.?\b/iu' => 'apr',
            '/\bmai\b/iu' => 'may',
            '/\bjuin\b/iu' => 'jun',
            '/\bjuil\.?\b/iu' => 'jul',
            '/\baoût\b/iu' => 'aug',
            '/\baout\b/iu' => 'aug',
            '/\bsept\.?\b/iu' => 'sep',
            '/\boct\.?\b/iu' => 'oct',
            '/\bnov\.?\b/iu' => 'nov',
            '/\bdéc\.?\b/iu' => 'dec',
            '/\bdec\.?\b/iu' => 'dec',
        ];

        $normalized = $dateStr;
        foreach ($replacements as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized);
        }

        return $normalized;
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
            $parsedInputDate = $inputDate ? Carbon::parse(self::normalizeDateString($inputDate)) : null;
            $parsedComparisonDate = $comparisonDate ? Carbon::parse(self::normalizeDateString($comparisonDate)) : null;

            // Check if either date is null after attempting to parse
            if (!$parsedInputDate || !$parsedComparisonDate) {
                // Optionally, you can log these errors or handle them as needed
                return false;
            }

            return $parsedInputDate->gt($parsedComparisonDate);
        } catch (Throwable) {
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
        $carbonDate = Carbon::parse(self::normalizeDateString($datetime));
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

    /**
     * A date-range filter as the two datetimes that bound the days it names,
     * ready to compare a datetime column against directly.
     *
     * Reports used to write DATE(col) BETWEEN start AND end. Wrapping the
     * column in a function puts every index on it out of reach, so the filter
     * read the whole table to answer a question the index could have answered.
     * The span is the same either way: midnight on the first day through
     * 23:59:59 on the last. The form_* date columns are plain datetimes with
     * no fractional seconds, so that second endpoint is exact.
     *
     * A range naming only one day bounds that single day rather than
     * collapsing to an empty end and matching nothing.
     *
     * @return array{0: string, 1: string} ['', ''] when nothing was picked.
     */
    public static function dayRange(?string $dateRange, string $seperator = "to"): array
    {
        [$start, $end] = self::convertDateRange($dateRange, $seperator, includeTime: true);

        if ($start === '' && $end === '') {
            return ['', ''];
        }
        if ($start === '') {
            $start = Carbon::parse($end)->startOfDay()->format('Y-m-d H:i:s');
        }
        if ($end === '') {
            $end = Carbon::parse($start)->endOfDay()->format('Y-m-d H:i:s');
        }

        return [$start, $end];
    }

    public static function convertDateRange(?string $dateRange, $seperator = "to", bool $includeTime = false): array
    {
        if ($dateRange === null || $dateRange === '' || $dateRange === '0') {
            return ['', ''];
        }

        $dates = explode($seperator, $dateRange ?? '');
        $dates = array_map('trim', $dates);

        $startDate = '';
        $endDate = '';

        if (!empty($dates[0])) {
            try {
                $start = Carbon::parse(self::normalizeDateString($dates[0]));
                if ($includeTime) {
                    $startDate = preg_match('/\d{2}:\d{2}/', $dates[0])
                        ? $start->format('Y-m-d H:i:s')
                        : $start->startOfDay()->format('Y-m-d H:i:s');
                } else {
                    $startDate = $start->format('Y-m-d');
                }
            } catch (Exception $e) {
                LoggerUtility::logError("Failed to parse start date: " . $dates[0] . " - " . $e->getMessage());
            }
        }

        if (!empty($dates[1])) {
            try {
                $end = Carbon::parse(self::normalizeDateString($dates[1]));
                if ($includeTime) {
                    $endDate = preg_match('/\d{2}:\d{2}/', $dates[1])
                        ? $end->format('Y-m-d H:i:s')
                        : $end->endOfDay()->format('Y-m-d H:i:s'); // end of day instead of next day start
                } else {
                    $endDate = $end->format('Y-m-d');
                }
            } catch (Exception $e) {
                LoggerUtility::logError("Failed to parse end date: " . $dates[1] . " - " . $e->getMessage());
            }
        }

        return [$startDate, $endDate];
    }

    /**
     * Returns the date that is a certain number of months before the current date.
     *
     * @param int $months The number of months to subtract.
     * @return string The date in 'Y-m-d' format.
     */
    public static function getDateBeforeMonths(int $months): string
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
        return array_filter($dates, fn($date): bool => self::isDateValid($date));
    }

    /**
     * Returns the earliest date among a variable number of given dates.
     *
     * @param string ...$dates A variable number of date strings.
     * @return string|null The earliest date in 'Y-m-d H:i:s' format, or null if all dates are invalid or no dates are provided.
     */
    public static function getLowestDate(...$dates): ?string
    {
        return self::extremeDate($dates, latest: false);
    }
    /**
     * Returns the latest date among a variable number of given dates.
     *
     * @param string ...$dates A variable number of date strings.
     * @return string|null The latest date in 'Y-m-d H:i:s' format, or null if all dates are invalid or no dates are provided.
     */
    public static function getHighestDate(...$dates): ?string
    {
        return self::extremeDate($dates, latest: true);
    }

    /** The earliest or latest of the valid dates, as Y-m-d H:i:s; null when none is valid. */
    private static function extremeDate(array $dates, bool $latest): ?string
    {
        $found = null;
        foreach (self::filterValidDates($dates) as $date) {
            $carbonDate = Carbon::parse(self::normalizeDateString((string) $date));
            if ($found === null || ($latest ? $carbonDate->gt($found) : $carbonDate->lt($found))) {
                $found = $carbonDate;
            }
        }
        return $found?->format('Y-m-d H:i:s');
    }

    /**
     * Calculates the age of a patient from their date of birth, age in years, or age in months.
     *
     * @param array $result Array containing patient's date of birth ('patient_dob'),
     *                      age in years ('patient_age_in_years'), or age in months ('patient_age_in_months').
     * @return string The calculated age as a string, with years or months specified as appropriate.
     */
    public static function calculatePatientAge($result): string
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

        // Check for valid DOB and calculate completed years since birth
        if (!empty($result['patient_dob']) && $result['patient_dob'] !== '0000-00-00' && self::isDateFormatValid($result['patient_dob'])) {
            $dob = Carbon::createFromFormat('Y-m-d', $result['patient_dob'])->startOfDay();
            $now = Carbon::now()->startOfDay();

            // A date of birth in the future cannot produce an age, so fall through
            if ($dob->lessThanOrEqualTo($now)) {
                // Carbon 3 returns a signed float here, so ask for the absolute
                // difference and truncate it to completed whole units
                $years = (int) $dob->diffInYears($now, true);
                if ($years > 0) {
                    return $years . ' ' . ($years > 1 ? _translate('years') : _translate('year'));
                }

                // Under a year old, report months or days rather than "0 year"
                $months = (int) $dob->diffInMonths($now, true);
                if ($months > 0) {
                    return $months . ' ' . ($months > 1 ? _translate('months') : _translate('month'));
                }

                $days = (int) $dob->diffInDays($now, true);
                return $days . ' ' . ($days === 1 ? _translate('day') : _translate('days'));
            }
        }

        // Convert age in months to appropriate format
        if (isset($result['patient_age_in_months']) && is_numeric($result['patient_age_in_months']) && $result['patient_age_in_months'] > 0) {
            $months = (int)$result['patient_age_in_months'];
            return $months . ' ' . ($months > 1 ? _translate('months') : _translate('month'));
        }

        // Default case if none of the above conditions are met
        return _translate('Unknown');
    }

    public static function getCurrentYear(): int
    {
        return (int) Carbon::now()->format('Y');
    }

    public static function getYearMinus(int $years): int
    {
        return self::getCurrentYear() - $years;
    }

    /**
     * Returns the start/end for the last N months ending "now".
     * If $includeTime=false, start is at startOfDay and end is at endOfDay.
     *
     * @return array{0:string,1:string} [$start, $end] formatted.
     */
    public static function lastMonthsRange(
        int $months,
        bool $includeTime = false,
        string $format = 'Y-m-d H:i:s'
    ): array {
        $now   = Carbon::now();
        $start = (clone $now)->subMonths($months);

        if ($includeTime === false) {
            $start = $start->startOfDay();
            $end   = (clone $now)->endOfDay();
            $format = $format === 'Y-m-d H:i:s' ? 'Y-m-d' : $format;
        } else {
            $end = $now; // precise "now"
        }

        return [$start->format($format), $end->format($format)];
    }

    /**
     * Human label for last N months, e.g., "22-Apr-2025 to 22-Oct-2025".
     * Uses $_SESSION['phpDateFormat'] if set; otherwise 'd-M-Y'.
     */
    public static function lastMonthsLabel(int $months, ?string $format = null): string
    {
        $fmt = $format ?? ($_SESSION['phpDateFormat'] ?? 'd-M-Y');
        [$start, $end] = self::lastMonthsRange($months, false, $fmt);
        return "$start to $end";
    }
}
