<?php

namespace App\Utilities;

use Carbon\Carbon;
use Throwable;

/**
 * Smart Date Format Auto-Detector
 * Uses Carbon's parsing intelligence + reverse engineering to detect ANY date format
 * No predefined patterns needed - handles unlimited combinations automatically
 */
final class SmartDateFormatDetector
{

    /**
     * Public method to check if input looks like a PHP date format
     * (Making the private method public for API access)
     *
     * @param string $input Input string
     * @return bool True if looks like a format
     */
    public static function looksLikeFormat(string $input): bool
    {
        return self::looksLikeDateFormat($input);
    }

    /**
     * Validate and correct format strings for API endpoint
     *
     * @param string $format Format string to validate/correct
     * @return array Array of suggestions including corrections
     */
    public static function validateAndCorrectFormat(string $format): array
    {
        return self::handleFormatString($format);
    }

    /**
     * Main entry point - auto-detect date format from sample data OR validate format string
     * This method now intelligently determines if input is a date sample or format string
     *
     * @param string $sampleDate Sample date string OR PHP date format string
     * @return array Array of possible formats with confidence scores
     */
    public static function detectFormat(string $sampleDate): array
    {
        $sampleDate = trim($sampleDate);
        if ($sampleDate === '' || $sampleDate === '0') {
            return [];
        }

        // First check if this looks like a PHP date format string
        if (self::looksLikeDateFormat($sampleDate)) {
            return self::handleFormatString($sampleDate);
        }

        // Otherwise, treat as date sample and use existing detection logic
        return self::detectFromDateSample($sampleDate);
    }
    /**
     * Check if input string looks like a PHP date format
     *
     * @param string $input Input string
     * @return bool True if looks like a format
     */
    private static function looksLikeDateFormat(string $input): bool
    {
        // PHP date format characters
        $formatChars = [
            // Day
            'd',
            'j',
            'D',
            'l',
            'N',
            'S',
            'w',
            'z',
            // Week
            'W',
            // Month
            'F',
            'm',
            'M',
            'n',
            't',
            // Year
            'L',
            'o',
            'Y',
            'y',
            // Time
            'a',
            'A',
            'B',
            'g',
            'G',
            'h',
            'H',
            'i',
            's',
            'u',
            'v',
            // Timezone
            'e',
            'I',
            'O',
            'P',
            'T',
            'Z',
            // Full Date/Time
            'c',
            'r',
            'U'
        ];

        // Count how many format characters vs total characters
        $formatCharCount = 0;
        $totalChars = strlen($input);

        for ($i = 0; $i < $totalChars; $i++) {
            if (in_array($input[$i], $formatChars)) {
                $formatCharCount++;
            }
        }

        // If more than 25% are format characters, likely a format string
        $formatRatio = $formatCharCount / $totalChars;

        // Additional checks for common format patterns
        $hasCommonFormatPatterns = (
            preg_match('/[YymdnjHGhgis]/', $input) && // Has date/time chars
            preg_match('/[\/\-\.\s:]/', $input)       // Has separators
        );

        $hasSequentialFormats = preg_match('/[YymdnjHGhgis][\/\-\.\s:][YymdnjHGhgis]/', $input);

        // More specific patterns that are definitely formats
        $definitelyFormat = (
            preg_match('/^[YymdnjHGhgis\/\-\.\s:AaMFl]+$/', $input) && // Only format chars and separators
            $formatRatio > 0.4 // High ratio of format characters
        );

        return $definitelyFormat || ($formatRatio > 0.25 && $hasCommonFormatPatterns) || $hasSequentialFormats;
    }

    /**
     * Handle when user enters a PHP date format string
     *
     * @param string $format PHP date format string
     * @return array Suggestions array
     */
    private static function handleFormatString(string $format): array
    {
        $suggestions = [];

        // Test if it's a valid format by trying to format a known date
        try {
            $testDate = Carbon::create(2025, 6, 19, 14, 30, 45);
            $formatted = $testDate->format($format);

            // Try to parse it back
            $parsed = Carbon::createFromFormat($format, $formatted);

            if ($parsed && $parsed->format($format) === $formatted) {
                // Valid format - add as primary suggestion
                $suggestions[] = [
                    'format' => $format,
                    'name' => '✏️ ' . self::describeFormat($format) . ' (Your Format)',
                    'confidence' => 'high',
                    'description' => self::generateDescription($format) . ' - Format you entered',
                    'example' => $formatted,
                    'method' => 'user_format_validation',
                    'test_success' => true,
                    'parsed_date' => $testDate->format('Y-m-d H:i:s'),
                    'is_user_format' => true
                ];

                // Add similar/alternative formats
                $alternatives = self::generateAlternativeFormats($format);
                foreach ($alternatives as $altFormat) {
                    try {
                        $altFormatted = $testDate->format($altFormat);
                        $altParsed = Carbon::createFromFormat($altFormat, $altFormatted);

                        if ($altParsed && $altParsed->format($altFormat) === $altFormatted) {
                            $suggestions[] = [
                                'format' => $altFormat,
                                'name' => self::describeFormat($altFormat) . ' (Alternative)',
                                'confidence' => 'medium',
                                'description' => self::generateDescription($altFormat) . ' - Similar to your format',
                                'example' => $altFormatted,
                                'method' => 'format_alternative',
                                'test_success' => true,
                                'parsed_date' => $testDate->format('Y-m-d H:i:s')
                            ];
                        }
                    } catch (Throwable) {
                        continue;
                    }
                }
            }
        } catch (Throwable $e) {
            // Invalid format - suggest corrections
            $suggestions[] = [
                'format' => $format,
                'name' => '❌ Invalid Format',
                'confidence' => 'low',
                'description' => 'This format contains errors: ' . $e->getMessage(),
                'example' => 'Error: ' . $e->getMessage(),
                'method' => 'format_validation_error',
                'test_success' => false,
                'error' => $e->getMessage()
            ];

            // Add corrected suggestions
            $corrections = self::suggestFormatCorrections($format);
            foreach ($corrections as $correction) {
                $suggestions[] = [
                    'format' => $correction['format'],
                    'name' => '🔧 ' . self::describeFormat($correction['format']) . ' (Corrected)',
                    'confidence' => 'medium',
                    'description' => $correction['description'],
                    'example' => self::generateExample($correction['format']),
                    'method' => 'format_correction',
                    'test_success' => true,
                    'parsed_date' => Carbon::create(2025, 6, 19, 14, 30, 45)->format('Y-m-d H:i:s'),
                    'correction_details' => $correction['changes']
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Original detection logic for date samples
     *
     * @param string $sampleDate Sample date string
     * @return array Array of possible formats with confidence scores
     */
    private static function detectFromDateSample(string $sampleDate): array
    {
        $suggestions = [];
        $seenFormats = [];

        // Method 1: Try Carbon's intelligent parsing first
        try {
            $carbonDate = Carbon::parse($sampleDate);
            if ($carbonDate) {
                $reverseEngineered = self::reverseEngineerFormat($sampleDate, $carbonDate);
                foreach ($reverseEngineered as $suggestion) {
                    $format = $suggestion['format'];
                    if (!isset($seenFormats[$format])) {
                        $suggestions[] = $suggestion;
                        $seenFormats[$format] = true;
                    }
                }
            }
        } catch (Throwable) {
            // Carbon couldn't parse it intelligently, continue with other methods
        }

        // Method 1.5: Single-digit specific detection
        $singleDigitFormats = self::generateSingleDigitFormats($sampleDate);
        foreach ($singleDigitFormats as $format) {
            if (!isset($seenFormats[$format]) && self::testFormatExactly($sampleDate, $format)) {
                try {
                    $parsedDate = Carbon::createFromFormat($format, $sampleDate);
                    $suggestions[] = [
                        'format' => $format,
                        'name' => self::describeFormat($format),
                        'confidence' => 'high',
                        'method' => 'single_digit_detection',
                        'test_success' => true,
                        'parsed_date' => $parsedDate->format('Y-m-d H:i:s')
                    ];
                    $seenFormats[$format] = true;
                } catch (Throwable) {
                    continue;
                }
            }
        }

        // Method 2: Component-based analysis
        $componentBased = self::analyzeComponents($sampleDate);
        foreach ($componentBased as $suggestion) {
            $format = $suggestion['format'];
            if (!isset($seenFormats[$format])) {
                $suggestions[] = $suggestion;
                $seenFormats[$format] = true;
            }
        }

        // Method 3: Brute force with dynamic format generation
        $bruteForceResults = self::bruteForceFormatDetection($sampleDate);
        foreach ($bruteForceResults as $suggestion) {
            $format = $suggestion['format'];
            if (!isset($seenFormats[$format])) {
                $suggestions[] = $suggestion;
                $seenFormats[$format] = true;
            }
        }

        return self::cleanAndSortSuggestions($suggestions);
    }

    /**
     * Validate a sample date against a specific format
     *
     * @param string $sampleDate Sample date string
     * @param string $format PHP date format
     * @return array Validation result
     */
    public static function validateFormat(string $sampleDate, string $format): array
    {
        try {
            $parsed = Carbon::createFromFormat($format, $sampleDate);

            if ($parsed === false) {
                return [
                    'valid' => false,
                    'error' => 'Failed to parse date with the given format',
                    'parsed_date' => null
                ];
            }

            // Check if re-formatting produces the exact original
            $reformatted = $parsed->format($format);
            if ($reformatted !== $sampleDate) {
                return [
                    'valid' => false,
                    'error' => "Format mismatch: Expected '{$sampleDate}', got '{$reformatted}'",
                    'parsed_date' => $parsed->format('Y-m-d H:i:s')
                ];
            }

            return [
                'valid' => true,
                'parsed_date' => $parsed->format('Y-m-d H:i:s'),
                'error' => null
            ];
        } catch (Throwable $e) {
            return [
                'valid' => false,
                'error' => 'Parse error: ' . $e->getMessage(),
                'parsed_date' => null
            ];
        }
    }

    /**
     * Get suggested formats in a user-friendly format for display
     * This method now handles both date samples and format strings transparently
     *
     * @param string $sampleDate Sample date string OR PHP date format string
     * @return array Formatted suggestions for UI display
     */
    public static function getSuggestionsForUI(string $sampleDate): array
    {
        $detectedFormats = self::detectFormat($sampleDate);

        $suggestions = [];
        foreach ($detectedFormats as $format) {
            $suggestions[] = [
                'format' => $format['format'],
                'name' => $format['name'] ?? self::describeFormat($format['format']),
                'confidence' => $format['confidence'],
                'description' => $format['description'] ?? self::generateDescription($format['format']),
                'example' => $format['example'] ?? self::generateExample($format['format']),
                'test_success' => $format['test_success'] ?? true,
                'test_result' => $format['parsed_date'] ?? $format['test_result'] ?? null,
                'method' => $format['method'] ?? 'auto_detection',
                'is_user_format' => $format['is_user_format'] ?? false,
                'error' => $format['error'] ?? null,
                'correction_details' => $format['correction_details'] ?? null
            ];
        }

        return $suggestions;
    }

    /**
     * Generate specific method to handle single-digit dates
     */
    private static function generateSingleDigitFormats(string $dateString): array
    {
        $formats = [];

        // Check if we have single digits in typical positions
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2}):(\d{2})\s+(AM|PM)$/i', $dateString, $matches)) {
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
            $hour = $matches[4];
            $minute = $matches[5];
            $second = $matches[6];
            $ampm = $matches[7];

            // Generate appropriate format based on actual lengths
            $monthFormat = strlen($month) === 1 ? 'n' : 'm';
            $dayFormat = strlen($day) === 1 ? 'j' : 'd';
            $hourFormat = strlen($hour) === 1 ? 'g' : 'h';

            // US format: month/day/year
            $formats[] = "$monthFormat/$dayFormat/Y $hourFormat:i:s A";

            // European format: day/month/year
            $formats[] = "$dayFormat/$monthFormat/Y $hourFormat:i:s A";
        }

        // Check for other common single-digit patterns
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateString, $matches)) {
            $first = $matches[1];
            $second = $matches[2];

            $firstFormat = strlen($first) === 1 ? 'n' : 'm';
            $secondFormat = strlen($second) === 1 ? 'j' : 'd';

            $formats[] = "$firstFormat/$secondFormat/Y";
            $formats[] = "$secondFormat/$firstFormat/Y";
        }

        // Check for dash-separated dates
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $dateString, $matches)) {
            $first = $matches[1];
            $second = $matches[2];

            $firstFormat = strlen($first) === 1 ? 'n' : 'm';
            $secondFormat = strlen($second) === 1 ? 'j' : 'd';

            $formats[] = "$firstFormat-$secondFormat-Y";
            $formats[] = "$secondFormat-$firstFormat-Y";
        }

        return $formats;
    }

    /**
     * Generate alternative formats based on input format
     *
     * @param string $format Original format
     * @return array Alternative formats
     */
    private static function generateAlternativeFormats(string $format): array
    {
        $alternatives = [];

        // Common substitutions
        $substitutions = [
            // Year variations
            'Y' => 'y',   // 4-digit to 2-digit year
            'y' => 'Y',   // 2-digit to 4-digit year

            // Month variations
            'm' => 'n',   // Zero-padded to no padding
            'n' => 'm',   // No padding to zero-padded
            'M' => 'm',   // Text month to numeric
            'F' => 'm',   // Full month to numeric

            // Day variations
            'd' => 'j',   // Zero-padded to no padding
            'j' => 'd',   // No padding to zero-padded

            // Hour variations
            'H' => 'G',   // 24-hour padded to unpadded
            'G' => 'H',   // 24-hour unpadded to padded
            'h' => 'g',   // 12-hour padded to unpadded
            'g' => 'h',   // 12-hour unpadded to padded
            'H' => 'h',   // 24-hour to 12-hour
            'h' => 'H',   // 12-hour to 24-hour
        ];

        // Generate single-character substitutions
        foreach ($substitutions as $from => $to) {
            if (str_contains($format, $from)) {
                $altFormat = str_replace($from, $to, $format);
                if ($altFormat !== $format) {
                    $alternatives[] = $altFormat;
                }
            }
        }

        // If 12-hour format without AM/PM, add AM/PM
        if (preg_match('/[hg]/', $format) && !preg_match('/[aA]/', $format)) {
            $alternatives[] = $format . ' A';
        }

        // If 24-hour format with AM/PM, remove AM/PM and convert to 12-hour
        if (preg_match('/[HG].*[aA]/', $format)) {
            $alt = preg_replace('/\s*[aA]/', '', $format);
            $alt = str_replace(['H', 'G'], ['h', 'g'], $alt);
            $alternatives[] = $alt;
        }

        return array_unique($alternatives);
    }

    /**
     * Suggest corrections for invalid format strings
     *
     * @param string $format Invalid format
     * @return array Suggested corrections
     */
    private static function suggestFormatCorrections(string $format): array
    {
        $corrections = [];

        // Common mistakes and their fixes
        $commonMistakes = [
            // Wrong case or duplicates
            'mm' => 'm',     // Double m
            'dd' => 'd',     // Double d
            'yy' => 'y',     // Double y (if not YYYY)
            'yyyy' => 'Y',   // Lowercase yyyy
            'YYYY' => 'Y',   // Uppercase YYYY
            'MM' => 'M',     // Double M for month name
            'DD' => 'D',     // Double D for day name
            'hh' => 'h',     // Double h
            'HH' => 'H',     // Double H
            'ss' => 's',     // Double s
            'ii' => 'i',     // Double i (minutes)

            // Common format confusions
            'MM/DD/YYYY' => 'm/d/Y',
            'DD/MM/YYYY' => 'd/m/Y',
            'YYYY-MM-DD' => 'Y-m-d',
            'DD.MM.YYYY' => 'd.m.Y',
            'mm/dd/yyyy' => 'm/d/Y',
            'dd/mm/yyyy' => 'd/m/Y',

            // Time format mistakes
            '24' => 'H',     // Literal 24
            '12' => 'h',     // Literal 12
            'am/pm' => 'A',  // Literal am/pm
            'AM/PM' => 'A',  // Literal AM/PM
            'ampm' => 'A',   // No separator
            'AMPM' => 'A',   // No separator
        ];

        $correctedFormat = $format;
        $changesMade = [];

        foreach ($commonMistakes as $mistake => $correction) {
            if (str_contains($correctedFormat, $mistake)) {
                $correctedFormat = str_replace($mistake, $correction, $correctedFormat);
                $changesMade[] = "Changed '$mistake' to '$correction'";
            }
        }

        // Test if correction is valid
        if ($changesMade !== []) {
            try {
                $testDate = Carbon::create(2025, 6, 19, 14, 30, 45);
                $testDate->format($correctedFormat);

                $corrections[] = [
                    'format' => $correctedFormat,
                    'changes' => $changesMade,
                    'description' => _translate('Corrected common format mistakes:', true) . ' ' .  implode(', ', $changesMade)
                ];
            } catch (Throwable) {
                // Correction still invalid, continue to suggest common patterns
            }
        }

        // Suggest common patterns if format is very wrong or no corrections worked
        if (strlen($format) > 5 && ($corrections === [] || $changesMade === [])) {
            $corrections[] = [
                'format' => 'd/m/Y H:i',
                'changes' => [_translate('Suggested common European format', true)],
                'description' => _translate('European date with time (Day/Month/Year Hour:Minute)', true)
            ];

            $corrections[] = [
                'format' => 'm/d/Y H:i',
                'changes' => [_translate('Suggested common US format', true)],
                'description' => _translate('US date with time (Month/Day/Year Hour:Minute)', true)
            ];

            $corrections[] = [
                'format' => 'Y-m-d H:i:s',
                'changes' => [_translate('Suggested ISO format', true)],
                'description' => _translate('ISO 8601 format (Year-Month-Day Hour:Minute:Second)', true)
            ];
        }

        return $corrections;
    }

    /**
     * Reverse engineer format from Carbon's intelligent parsing
     */
    private static function reverseEngineerFormat(string $original, Carbon $parsed): array
    {
        $suggestions = [];

        // Analyze the structure of the original string
        $structure = self::analyzeStringStructure($original);

        // Generate possible formats based on the structure and parsed result
        $possibleFormats = self::buildFormatsFromStructure($structure, $parsed);

        foreach ($possibleFormats as $format) {
            if (self::testFormatExactly($original, $format)) {
                $suggestions[] = [
                    'format' => $format,
                    'name' => self::describeFormat($format),
                    'confidence' => self::calculateFormatConfidence($original, $format, $parsed),
                    'method' => 'carbon_reverse_engineering',
                    'test_success' => true,
                    'parsed_date' => $parsed->format('Y-m-d H:i:s')
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Analyze string structure (separators, number patterns, text patterns)
     */
    private static function analyzeStringStructure(string $dateString): array
    {
        return [
            'separators' => self::extractSeparators($dateString),
            'numbers' => self::extractNumbers($dateString),
            'text_parts' => self::extractTextParts($dateString),
            'length' => strlen($dateString),
            'has_time' => self::hasTimeComponent($dateString),
            'has_ampm' => preg_match('/\b(AM|PM|am|pm)\b/', $dateString),
            'patterns' => self::identifyPatterns($dateString),
            'segments' => self::segmentString($dateString)
        ];
    }

    /**
     * Extract all separators from the string
     */
    private static function extractSeparators(string $str): array
    {
        preg_match_all('/[^\w\s]/', $str, $matches);
        return array_values(array_unique($matches[0]));
    }

    /**
     * Extract all numeric parts
     */
    private static function extractNumbers(string $str): array
    {
        preg_match_all('/\d+/', $str, $matches);
        return $matches[0];
    }

    /**
     * Extract text parts (month names, AM/PM, etc.)
     */
    private static function extractTextParts(string $str): array
    {
        preg_match_all('/[A-Za-z]+/', $str, $matches);
        return $matches[0];
    }

    /**
     * Check if string has time component
     */
    private static function hasTimeComponent(string $str): bool
    {
        return preg_match('/\d{1,2}:\d{2}/', $str);
    }

    /**
     * Identify patterns in the string
     */
    private static function identifyPatterns(string $str): array
    {
        $patterns = [];

        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}/', $str)) {
            $patterns[] = 'iso_date';
        }
        if (preg_match('/^\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4}/', $str)) {
            $patterns[] = 'separated_date';
        }
        if (preg_match('/^\d{8,14}$/', $str)) {
            $patterns[] = 'compact_datetime';
        }
        if (preg_match('/[A-Za-z]{3,9}/', $str)) {
            $patterns[] = 'text_month';
        }

        return $patterns;
    }

    /**
     * Segment string into meaningful parts
     */
    private static function segmentString(string $str): array
    {
        $segments = preg_split('/[^\w]/', $str);
        return array_filter($segments, fn($segment): bool => !in_array(trim((string) $segment), ['', '0'], true));
    }

    /**
     * Build possible formats from analyzed structure
     */
    private static function buildFormatsFromStructure(array $structure, Carbon $parsed): array
    {
        $formats = [];
        $segments = $structure['segments'];
        $separators = $structure['separators'];

        if (count($segments) < 3) {
            return $formats; // Need at least 3 parts for a date
        }

        // Determine what each segment could represent
        $segmentPossibilities = [];
        foreach ($segments as $index => $segment) {
            $segmentPossibilities[$index] = self::determinePossibleMeanings($segment, $parsed);
        }

        // Generate format combinations
        return self::generateFormatCombinations($segmentPossibilities, $separators);
    }

    /**
     * Determine what a segment could represent (year, month, day, etc.)
     */
    private static function determinePossibleMeanings(string $segment, Carbon $parsed): array
    {
        $possibilities = [];

        if (is_numeric($segment)) {
            $num = (int)$segment;
            $len = strlen($segment);

            // Year detection
            if ($len === 4 && $num === $parsed->year) {
                $possibilities[] = 'Y';
            } elseif ($len === 2 && $num === ($parsed->year % 100)) {
                $possibilities[] = 'y';
            }

            // Month detection
            if ($num === $parsed->month) {
                if ($len === 2 || ($len === 1 && $num >= 10)) {
                    $possibilities[] = 'm';
                }
                $possibilities[] = 'n';
            }

            // Day detection
            if ($num === $parsed->day) {
                if ($len === 2 || ($len === 1 && $num >= 10)) {
                    $possibilities[] = 'd';
                }
                $possibilities[] = 'j';
            }

            // Hour detection
            if ($num === $parsed->hour) {
                $possibilities[] = ($len === 2) ? 'H' : 'G';
            }

            $hour12 = $parsed->hour % 12 ?: 12;
            if ($num === $hour12) {
                if ($len === 2 || $num >= 10) {
                    $possibilities[] = 'h';
                }
                $possibilities[] = 'g';
            }

            // Minute detection
            if ($num === $parsed->minute) {
                $possibilities[] = 'i';
            }

            // Second detection
            if ($num === $parsed->second) {
                $possibilities[] = 's';
            }
        } else {
            // Text segment
            $lower = strtolower($segment);

            // AM/PM detection
            if (in_array($lower, ['am', 'pm'])) {
                $possibilities[] = 'A';
            }

            // Month name detection
            $monthNames = [
                'jan' => 1,
                'feb' => 2,
                'mar' => 3,
                'apr' => 4,
                'may' => 5,
                'jun' => 6,
                'jul' => 7,
                'aug' => 8,
                'sep' => 9,
                'oct' => 10,
                'nov' => 11,
                'dec' => 12
            ];

            $monthKey = substr($lower, 0, 3);
            if (isset($monthNames[$monthKey]) && $monthNames[$monthKey] === $parsed->month) {
                $possibilities[] = 'M';
            }
        }

        return $possibilities;
    }

    /**
     * Generate format combinations from segment possibilities
     */
    private static function generateFormatCombinations(array $segmentPossibilities, array $separators): array
    {
        $formats = [];
        $combinations = self::getAllCombinations($segmentPossibilities);

        foreach ($combinations as $combination) {
            $format = self::buildFormatString($combination, $separators);
            if ($format) {
                $formats[] = $format;
            }
        }

        return array_unique($formats);
    }

    /**
     * Get all valid combinations of segment meanings
     */
    private static function getAllCombinations(array $segmentPossibilities): array
    {
        $combinations = [];
        $requiredComponents = ['year' => ['Y', 'y'], 'month' => ['m', 'n', 'M'], 'day' => ['d', 'j']];

        // Find segments that could be each required component
        $componentOptions = [];
        foreach ($requiredComponents as $component => $tokens) {
            $componentOptions[$component] = [];
            foreach ($segmentPossibilities as $segmentIndex => $possibilities) {
                foreach ($possibilities as $possibility) {
                    if (in_array($possibility, $tokens)) {
                        $componentOptions[$component][] = ['segment' => $segmentIndex, 'token' => $possibility];
                    }
                }
            }
        }

        // Generate valid combinations (ensuring no segment is used twice)
        if (isset($componentOptions['year']) && $componentOptions['year'] !== [] && (isset($componentOptions['month']) && $componentOptions['month'] !== []) && (isset($componentOptions['day']) && $componentOptions['day'] !== [])) {
            foreach ($componentOptions['year'] as $yearOption) {
                foreach ($componentOptions['month'] as $monthOption) {
                    foreach ($componentOptions['day'] as $dayOption) {
                        $usedSegments = [$yearOption['segment'], $monthOption['segment'], $dayOption['segment']];
                        if (count(array_unique($usedSegments)) === 3) {
                            $combination = [];
                            $combination[$yearOption['segment']] = $yearOption['token'];
                            $combination[$monthOption['segment']] = $monthOption['token'];
                            $combination[$dayOption['segment']] = $dayOption['token'];

                            // Add time components if available
                            foreach ($segmentPossibilities as $segmentIndex => $possibilities) {
                                if (!in_array($segmentIndex, $usedSegments)) {
                                    foreach ($possibilities as $possibility) {
                                        if (in_array($possibility, ['H', 'G', 'h', 'g', 'i', 's', 'A'])) {
                                            $combination[$segmentIndex] = $possibility;
                                            break;
                                        }
                                    }
                                }
                            }

                            $combinations[] = $combination;
                        }
                    }
                }
            }
        }

        return $combinations;
    }

    /**
     * Build format string from combination and separators
     */
    private static function buildFormatString(array $combination, array $separators): ?string
    {
        if ($combination === []) {
            return null;
        }

        ksort($combination);
        $formatParts = [];
        $segmentIndexes = array_keys($combination);

        foreach ($segmentIndexes as $i => $segmentIndex) {
            $formatParts[] = $combination[$segmentIndex];

            // Add separator between segments (if not the last one)
            if ($i < count($segmentIndexes) - 1) {
                $separatorIndex = min($i, count($separators) - 1);
                if (isset($separators[$separatorIndex])) {
                    $formatParts[] = $separators[$separatorIndex];
                }
            }
        }

        return implode('', $formatParts);
    }

    /**
     * Component-based analysis - break down the string and analyze each part
     */
    private static function analyzeComponents(string $dateString): array
    {
        $suggestions = [];
        $parts = preg_split('/[\s\/:.-]+/', $dateString);
        $parts = array_filter($parts);

        if (count($parts) < 3) {
            return [];
        }

        // Analyze each part to determine what it could be
        $partAnalysis = [];
        foreach ($parts as $index => $part) {
            $partAnalysis[$index] = self::analyzePart($part);
        }

        // Generate format combinations based on part analysis
        $formatCombinations = self::generateCombinationsFromParts($partAnalysis, $dateString);

        foreach ($formatCombinations as $format) {
            try {
                $testDate = Carbon::createFromFormat($format, $dateString);
                if ($testDate && self::isReasonableDate($testDate)) {
                    $suggestions[] = [
                        'format' => $format,
                        'name' => self::describeFormat($format),
                        'confidence' => self::calculateConfidence($dateString, $format),
                        'method' => 'component_analysis',
                        'test_success' => true,
                        'parsed_date' => $testDate->format('Y-m-d H:i:s')
                    ];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $suggestions;
    }

    /**
     * Analyze individual part to determine possible meanings
     */
    private static function analyzePart(string $part): array
    {
        $possibilities = [];

        if (is_numeric($part)) {
            $num = (int)$part;
            $len = strlen($part);

            // Year possibilities
            if ($len === 4 && $num >= 1900 && $num <= 2100) {
                $possibilities[] = ['type' => 'year', 'format' => 'Y'];
            } elseif ($len === 2 && $num >= 0 && $num <= 99) {
                $possibilities[] = ['type' => 'year', 'format' => 'y'];
            }

            // Month possibilities
            if ($num >= 1 && $num <= 12) {
                $possibilities[] = ['type' => 'month', 'format' => $len === 2 ? 'm' : 'n'];
            }

            // Day possibilities
            if ($num >= 1 && $num <= 31) {
                $possibilities[] = ['type' => 'day', 'format' => $len === 2 ? 'd' : 'j'];
            }

            // Hour possibilities
            if ($num >= 0 && $num <= 23) {
                $possibilities[] = ['type' => 'hour', 'format' => $len === 2 ? 'H' : 'G'];
            }
            if ($num >= 1 && $num <= 12) {
                $possibilities[] = ['type' => 'hour12', 'format' => $len === 2 ? 'h' : 'g'];
            }

            // Minute/Second possibilities
            if ($num >= 0 && $num <= 59) {
                $possibilities[] = ['type' => 'minute', 'format' => 'i'];
                $possibilities[] = ['type' => 'second', 'format' => 's'];
            }
        } else {
            // Text part
            $lower = strtolower($part);

            // AM/PM
            if (in_array($lower, ['am', 'pm'])) {
                $possibilities[] = ['type' => 'ampm', 'format' => 'A'];
            }

            // Month names
            $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
            if (in_array(substr($lower, 0, 3), $months)) {
                $possibilities[] = ['type' => 'month', 'format' => 'M'];
            }
        }

        return $possibilities;
    }

    /**
     * Generate format combinations from part analysis
     */
    private static function generateCombinationsFromParts(array $partAnalysis, string $originalString): array
    {
        $formats = [];
        $assignments = self::findBestPartAssignments($partAnalysis);

        foreach ($assignments as $assignment) {
            $format = self::buildFormatFromAssignment($assignment, $originalString);
            if ($format) {
                $formats[] = $format;
            }
        }

        return array_unique($formats);
    }

    /**
     * Find the best assignments of parts to date components
     */
    private static function findBestPartAssignments(array $partAnalysis): array
    {
        $assignments = [];

        if (count($partAnalysis) >= 3) {
            // Generate common assignment patterns
            $assignments[] = [0 => 'day', 1 => 'month', 2 => 'year'];
            $assignments[] = [0 => 'month', 1 => 'day', 2 => 'year'];
            $assignments[] = [0 => 'year', 1 => 'month', 2 => 'day'];
        }

        return $assignments;
    }

    /**
     * Build format string from part assignment
     */
    private static function buildFormatFromAssignment(array $assignment, string $originalString): ?string
    {
        preg_match_all('/[^\w]/', $originalString, $separators);
        $seps = $separators[0];

        $formatParts = [];
        $sepIndex = 0;

        foreach ($assignment as $partIndex => $componentType) {
            switch ($componentType) {
                case 'year':
                    $formatParts[] = 'Y';
                    break;
                case 'month':
                    $formatParts[] = 'm';
                    break;
                case 'day':
                    $formatParts[] = 'd';
                    break;
                case 'hour':
                    $formatParts[] = 'H';
                    break;
                case 'minute':
                    $formatParts[] = 'i';
                    break;
                case 'second':
                    $formatParts[] = 's';
                    break;
            }

            if ($partIndex < count($assignment) - 1 && isset($seps[$sepIndex])) {
                $formatParts[] = $seps[$sepIndex];
                $sepIndex++;
            }
        }

        return implode('', $formatParts);
    }

    /**
     * Brute force format detection by trying many combinations
     */
    private static function bruteForceFormatDetection(string $sampleDate): array
    {
        $suggestions = [];
        $formats = self::generateDynamicFormats($sampleDate);

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $sampleDate);
                if ($parsed && $parsed->format($format) === $sampleDate) {
                    $suggestions[] = [
                        'format' => $format,
                        'name' => self::describeFormat($format),
                        'confidence' => self::calculateBruteForceConfidence($sampleDate, $format),
                        'method' => 'brute_force',
                        'test_success' => true,
                        'parsed_date' => $parsed->format('Y-m-d H:i:s')
                    ];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $suggestions;
    }

    /**
     * Generate dynamic formats based on string characteristics
     */
    private static function generateDynamicFormats(string $dateString): array
    {
        $formats = [];

        // Analyze string characteristics
        $hasColon = str_contains($dateString, ':');
        $hasSlash = str_contains($dateString, '/');
        $hasDot = str_contains($dateString, '.');
        $hasDash = str_contains($dateString, '-');
        $hasAmPm = preg_match('/\b(AM|PM|am|pm)\b/', $dateString);
        $hasAlpha = preg_match('/[A-Za-z]/', $dateString);

        // Determine likely separators
        $separators = [];
        if ($hasSlash) {
            $separators[] = '/';
        }
        if ($hasDot) {
            $separators[] = '.';
        }
        if ($hasDash) {
            $separators[] = '-';
        }
        if ($separators === []) {
            $separators[] = '/';
        }

        // Basic date format variations
        $datePatterns = [
            'd/m/y',
            'd/m/Y',
            'm/d/y',
            'm/d/Y',
            'y/m/d',
            'Y/m/d',
            'd-m-y',
            'd-m-Y',
            'm-d-y',
            'm-d-Y',
            'y-m-d',
            'Y-m-d',
            'd.m.y',
            'd.m.Y',
            'm.d.y',
            'm.d.Y',
            'n/j/y',
            'n/j/Y',
            'j/n/y',
            'j/n/Y',
            'n-j-y',
            'n-j-Y',
            'j-n-y',
            'j-n-Y',
            'n.j.y',
            'n.j.Y',
            'j.n.y',
            'j.n.Y',
            'n/d/Y',
            'd/n/Y',
            'm/j/Y',
            'j/m/Y',
            'Ymd',
            'dmY',
            'dmy',
            'mYd',
            'Y/m',
            'Y-m',
            'Ym',
            'my'
        ];

        foreach ($separators as $sep) {
            foreach ($datePatterns as $pattern) {
                $formats[] = str_replace('-', $sep, $pattern);
            }
        }

        // Add time variations if time component detected
        if ($hasColon) {
            $timePatterns = [
                'H:i',
                'H:i:s'
            ];
            if ($hasAmPm) {
                $timePatterns = array_merge($timePatterns, [
                    'g:i A',
                    'g:i:s A',
                    'h:i A',
                    'h:i:s A'
                ]);
            }

            $dateTimeFormats = [];
            foreach ($formats as $dateFormat) {
                foreach ($timePatterns as $timePattern) {
                    $dateTimeFormats[] = "$dateFormat $timePattern";
                }
            }
            $formats = array_merge($formats, $dateTimeFormats);
        }

        // Add month name variations
        if ($hasAlpha && !$hasAmPm) {
            $monthFormats = [];
            foreach ($separators as $sep) {
                $monthFormats[] = "M{$sep}d{$sep}Y";
                $monthFormats[] = "d{$sep}M{$sep}Y";
                $monthFormats[] = "Y{$sep}M{$sep}d";
            }
            $formats = array_merge($formats, $monthFormats);
        }

        // Add compact formats for numeric-only strings
        if (!strpos($dateString, ' ') && !$hasAlpha && ctype_digit((string) preg_replace('/[^\d]/', '', $dateString))) {
            $compactFormats = [
                'Ymd',
                'YmdH',
                'YmdHi',
                'YmdHis',
                'dmY',
                'mdY'
            ];
            $formats = array_merge($formats, $compactFormats);
        }

        return array_unique($formats);
    }

    /**
     * Test if format exactly matches the original string
     */
    private static function testFormatExactly(string $original, string $format): bool
    {
        try {
            $parsed = Carbon::createFromFormat($format, $original);
            return $parsed && $parsed->format($format) === $original;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Check if parsed date is reasonable
     */
    private static function isReasonableDate(Carbon $date): bool
    {
        $year = $date->year;
        return $year >= 1900 && $year <= 2100;
    }

    /**
     * Calculate confidence score for a format
     */
    private static function calculateConfidence(string $dateString, string $format): string
    {
        $score = 50;

        try {
            $parsed = Carbon::createFromFormat($format, $dateString);
            if ($parsed && $parsed->format($format) === $dateString) {
                $score += 30;
            }
        } catch (Throwable) {
            $score -= 20;
        }

        if (str_contains($format, 'Y')) {
            $score += 10;
        }
        if (str_contains($format, 'y')) {
            $score -= 5;
        }

        if ($score >= 80) {
            return 'high';
        }
        if ($score >= 60) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Calculate confidence for brute force detection
     */
    private static function calculateBruteForceConfidence(string $dateString, string $format): string
    {
        $score = 50;

        if (str_contains($format, 'Y')) {
            $score += 15;
        }
        if (preg_match('/\b0\d/', $dateString) && str_contains($format, 'd')) {
            $score += 10;
        }
        if (preg_match('/\b0\d/', $dateString) && str_contains($format, 'm')) {
            $score += 10;
        }
        if (str_contains($format, 'y')) {
            $score -= 10;
        }
        if (str_contains($format, 'M')) {
            $score += 20;
        }
        if (str_contains($format, 'A')) {
            $score += 10;
        }

        if ($score >= 75) {
            return 'high';
        }
        if ($score >= 55) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Calculate confidence for reverse engineered formats
     */
    private static function calculateFormatConfidence(string $original, string $format, Carbon $parsed): string
    {
        $score = 70;

        try {
            if ($parsed->format($format) === $original) {
                $score += 20;
            }
        } catch (Throwable) {
            $score -= 30;
        }

        if (str_contains($format, 'Y')) {
            $score += 10;
        }
        if (str_contains($format, 'y')) {
            $score -= 5;
        }
        if (str_contains($format, 'M')) {
            $score += 15;
        }
        if (str_contains($format, 'A')) {
            $score += 5;
        }

        if ($score >= 80) {
            return 'high';
        }
        if ($score >= 60) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Generate human-readable description for format
     */
    public static function describeFormat(string $format): string
    {
        // Split format into individual characters and process each
        $chars = str_split($format);
        $result = '';
        $counter = count($chars);

        for ($i = 0; $i < $counter; $i++) {
            $char = $chars[$i];

            match ($char) {
                'Y' => $result .= 'YYYY',
                'y' => $result .= 'YY',
                'm' => $result .= 'MM',
                'n' => $result .= 'M',
                'd' => $result .= 'DD',
                'j' => $result .= 'D',
                'H' => $result .= 'HH',
                'G' => $result .= 'H',
                'h' => $result .= 'hh',
                'g' => $result .= 'h',
                'i' => $result .= 'mm',
                's' => $result .= 'ss',
                'A' => $result .= 'AM/PM',
                'a' => $result .= 'am/pm',
                'M' => $result .= 'Mon',
                'F' => $result .= 'Month',
                default => $result .= $char,
            };
        }

        return $result;
    }

    /**
     * Generate a detailed description for UI display
     */
    private static function generateDescription(string $format): string
    {
        $parts = [];

        if (str_contains($format, 'Y')) {
            $parts[] = _translate('4-digit year', true);
        } elseif (str_contains($format, 'y')) {
            $parts[] = _translate('2-digit year', true);
        }

        if (str_contains($format, 'M')) {
            $parts[] = _translate('month name', true);
        } elseif (str_contains($format, 'm')) {
            $parts[] = _translate('month with leading zero', true);
        } elseif (str_contains($format, 'n')) {
            $parts[] = _translate('month without leading zero', true);
        }

        if (str_contains($format, 'd')) {
            $parts[] = _translate('day with leading zero', true);
        } elseif (str_contains($format, 'j')) {
            $parts[] = _translate('day without leading zero', true);
        }

        if (str_contains($format, 'H')) {
            $parts[] = _translate('24-hour format', true);
        } elseif (str_contains($format, 'g') || str_contains($format, 'h')) {
            $parts[] = _translate('12-hour format', true);
        }

        if (str_contains($format, 'A')) {
            $parts[] = _translate('with AM/PM', true);
        }

        if (str_contains($format, 's')) {
            $parts[] = _translate('with seconds', true);
        }

        return implode(', ', $parts);
    }

    /**
     * Generate an example of what this format would produce
     */
    private static function generateExample(string $format): string
    {
        try {
            $exampleDate = Carbon::create(2025, 6, 19, 14, 30, 45);
            return $exampleDate->format($format);
        } catch (Throwable) {
            return _translate('Example not available', true);
        }
    }

    /**
     * Clean and sort suggestions
     */
    private static function cleanAndSortSuggestions(array $suggestions): array
    {
        // Remove duplicates by format string
        $formatMap = [];

        foreach ($suggestions as $suggestion) {
            $format = $suggestion['format'];

            if (!isset($formatMap[$format])) {
                // First occurrence of this format
                $formatMap[$format] = $suggestion;
            } else {
                // Compare with existing and keep the better one
                $existing = $formatMap[$format];

                // Priority: high > medium > low confidence
                $confidenceOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
                $newScore = $confidenceOrder[$suggestion['confidence']] ?? 0;
                $existingScore = $confidenceOrder[$existing['confidence']] ?? 0;

                if ($newScore > $existingScore) {
                    $formatMap[$format] = $suggestion;
                } elseif ($newScore === $existingScore) {
                    // Same confidence, prefer better method
                    $methodOrder = [
                        'single_digit_detection' => 4,
                        'carbon_reverse_engineering' => 3,
                        'component_analysis' => 2,
                        'brute_force' => 1
                    ];
                    $newMethodScore = $methodOrder[$suggestion['method']] ?? 0;
                    $existingMethodScore = $methodOrder[$existing['method']] ?? 0;

                    if ($newMethodScore > $existingMethodScore) {
                        $formatMap[$format] = $suggestion;
                    }
                }
            }
        }

        // Convert back to indexed array and ensure uniqueness
        $unique = array_values($formatMap);

        // Sort by confidence, then by method priority
        usort($unique, function (array $a, array $b): int {
            $confidenceOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            $methodOrder = [
                'single_digit_detection' => 4,
                'carbon_reverse_engineering' => 3,
                'component_analysis' => 2,
                'brute_force' => 1
            ];

            // First sort by confidence
            $confDiff = ($confidenceOrder[$b['confidence']] ?? 0) - ($confidenceOrder[$a['confidence']] ?? 0);
            if ($confDiff !== 0) {
                return $confDiff;
            }

            // Then by method priority
            $methodDiff = ($methodOrder[$b['method']] ?? 0) - ($methodOrder[$a['method']] ?? 0);
            if ($methodDiff !== 0) {
                return $methodDiff;
            }

            // Finally by format string for consistent ordering
            return strcmp((string) $a['format'], (string) $b['format']);
        });

        // Final deduplication check - remove any remaining duplicates by format
        $finalUnique = [];
        $seenFormats = [];

        foreach ($unique as $suggestion) {
            $format = $suggestion['format'];
            if (!isset($seenFormats[$format])) {
                $finalUnique[] = $suggestion;
                $seenFormats[$format] = true;
            }
        }

        return array_slice($finalUnique, 0, 10);
    }

    /**
     * Quick utility method to get the best format suggestion
     */
    public static function getBestFormat(string $sampleDate): ?string
    {
        $suggestions = self::detectFormat($sampleDate);
        return $suggestions === [] ? null : $suggestions[0]['format'];
    }

    /**
     * Utility method to check if a date string is likely to be in a specific regional format
     */
    public static function detectRegionalPreference(string $sampleDate): string
    {
        $suggestions = self::detectFormat($sampleDate);

        foreach ($suggestions as $suggestion) {
            if ($suggestion['confidence'] === 'high') {
                $format = $suggestion['format'];

                // Check for US patterns (M/D/Y, M.D.Y, M-D-Y)
                if (preg_match('/^[mn][\/\.-][dj][\/\.-][Yy]/', (string) $format)) {
                    return _translate('US', true);
                }

                // Check for European patterns (D/M/Y, D.M.Y, D-M-Y)
                if (preg_match('/^[dj][\/\.-][mn][\/\.-][Yy]/', (string) $format)) {
                    return _translate('European', true);
                }

                // Check for ISO patterns (Y-M-D)
                if (preg_match('/^[Yy][\/\.-][mn][\/\.-][dj]/', (string) $format)) {
                    return _translate('ISO', true);
                }
            }
        }

        return _translate('Unknown', true);
    }

    /**
     * Get format suggestions with ambiguity warnings
     */
    public static function getSuggestionsWithWarnings(string $sampleDate): array
    {
        $suggestions = self::getSuggestionsForUI($sampleDate);

        // Add ambiguity warnings
        $hasAmbiguousFormats = false;
        $usFormat = null;
        $euFormat = null;

        foreach ($suggestions as &$suggestion) {
            $format = $suggestion['format'];

            // Check for day/month ambiguity
            if (preg_match('/^[mn][\/\.-][dj][\/\.-]/', (string) $format)) {
                $usFormat = $suggestion;
                $hasAmbiguousFormats = true;
            } elseif (preg_match('/^[dj][\/\.-][mn][\/\.-]/', (string) $format)) {
                $euFormat = $suggestion;
                $hasAmbiguousFormats = true;
            }

            // Add specific warnings
            if (str_contains((string) $format, 'y')) {
                $suggestion['warning'] = _translate('2-digit years can be ambiguous (e.g., 25 = 2025 or 1925?)', true);
            }

            if ($hasAmbiguousFormats && ($usFormat && $euFormat)) {
                if ($suggestion === $usFormat) {
                    $suggestion['warning'] = _translate('Could be confused with European format (Day/Month/Year)', true);
                } elseif ($suggestion === $euFormat) {
                    $suggestion['warning'] = _translate('Could be confused with US format (Month/Day/Year)', true);
                }
            }
        }

        return $suggestions;
    }

    /**
     * Test multiple sample dates to improve detection accuracy
     */
    public static function detectFromMultipleSamples(array $sampleDates): array
    {
        $formatCounts = [];

        foreach ($sampleDates as $sampleDate) {
            $suggestions = self::detectFormat($sampleDate);
            foreach ($suggestions as $suggestion) {
                $format = $suggestion['format'];
                if (!isset($formatCounts[$format])) {
                    $formatCounts[$format] = ['count' => 0, 'suggestion' => $suggestion];
                }
                $formatCounts[$format]['count']++;

                // Boost confidence if format works for multiple samples
                if ($formatCounts[$format]['count'] > 1) {
                    $formatCounts[$format]['suggestion']['confidence'] = 'high';
                    $formatCounts[$format]['suggestion']['name'] .= ' (verified with multiple samples)';
                }
            }
        }

        // Sort by count and confidence
        uasort($formatCounts, function (array $a, array $b): int {
            if ($a['count'] !== $b['count']) {
                return $b['count'] - $a['count'];
            }

            $confidenceOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            return $confidenceOrder[$b['suggestion']['confidence']] - $confidenceOrder[$a['suggestion']['confidence']];
        });

        return array_map(fn($item) => $item['suggestion'], array_slice($formatCounts, 0, 5, true));
    }

    /**
     * Smart auto-detection of date formats - delegates to SmartDateFormatDetector
     *
     * @param string $sampleDate Sample date string from user
     * @return array Array of possible formats with confidence levels
     */
    public static function smartDetectDateFormat(string $sampleDate): array
    {
        return SmartDateFormatDetector::getSuggestionsForUI($sampleDate);
    }

    /**
     * Get the best format suggestion quickly
     *
     * @param string $sampleDate Sample date string
     * @return string|null Best format or null if none found
     */
    public static function getBestDateFormat(string $sampleDate): ?string
    {
        return SmartDateFormatDetector::getBestFormat($sampleDate);
    }

    /**
     * Validate a date format against sample data
     *
     * @param string $sampleDate Sample date string
     * @param string $format PHP date format
     * @return array Validation result
     */
    public static function validateDateFormat(string $sampleDate, string $format): array
    {
        return SmartDateFormatDetector::validateFormat($sampleDate, $format);
    }

    /**
     * Enhanced date parsing using detected format
     *
     * @param string $dateString Date string to parse
     * @param string $detectedFormat Format detected by smart detector
     * @param string $outputFormat Desired output format
     * @return string|null Formatted date or null on failure
     */
    public static function parseWithDetectedFormat(
        string $dateString,
        string $detectedFormat,
        string $outputFormat = 'Y-m-d H:i:s'
    ): ?string {
        try {
            $carbonDate = Carbon::createFromFormat($detectedFormat, $dateString);
            if ($carbonDate === false) {
                return null;
            }

            return $carbonDate->format($outputFormat);
        } catch (Throwable $e) {
            LoggerUtility::logError(
                "Failed to parse date '$dateString' with format '$detectedFormat': " . $e->getMessage(),
                [
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            );
            return null;
        }
    }

    /**
     * Debug method to test specific formats
     */
    public static function debugDetection(string $sampleDate): array
    {
        $testFormats = [
            'n/j/Y g:i:s A',
            'n/j/Y h:i:s A',
            'm/d/Y g:i:s A',
            'm/d/Y h:i:s A',
            'd/m/Y g:i:s A',
            'd/m/Y h:i:s A'
        ];

        $results = [];
        foreach ($testFormats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $sampleDate);
                $reformatted = $parsed instanceof Carbon ? $parsed->format($format) : 'FAILED';
                $matches = ($reformatted === $sampleDate);

                $results[] = [
                    'format' => $format,
                    'parsed' => $parsed instanceof Carbon ? $parsed->format('Y-m-d H:i:s') : 'FAILED',
                    'reformatted' => $reformatted,
                    'exact_match' => $matches
                ];
            } catch (Throwable $e) {
                $results[] = [
                    'format' => $format,
                    'parsed' => 'ERROR',
                    'reformatted' => 'ERROR: ' . $e->getMessage(),
                    'exact_match' => false
                ];
            }
        }

        return $results;
    }
}
