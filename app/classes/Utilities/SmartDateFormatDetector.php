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
     * Main entry point - auto-detect date format from sample data
     *
     * @param string $sampleDate Sample date string
     * @return array Array of possible formats with confidence scores
     */
    public static function detectFormat(string $sampleDate): array
    {
        $sampleDate = trim($sampleDate);
        if (empty($sampleDate)) {
            return [];
        }

        $suggestions = [];
        $seenFormats = []; // Track formats we've already found

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
        } catch (Throwable $e) {
            // Carbon couldn't parse it intelligently, continue with other methods
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

        // Clean and sort suggestions (now with fewer duplicates to process)
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
     *
     * @param string $sampleDate Sample date string
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
                'description' => self::generateDescription($format['format']),
                'example' => self::generateExample($format['format']),
                'test_success' => $format['test_success'] ?? true,
                'test_result' => $format['parsed_date'] ?? $format['test_result'] ?? null,
                'method' => $format['method'] ?? 'auto_detection'
            ];
        }

        return $suggestions;
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
        return array_filter($segments, function ($segment) {
            return !empty(trim($segment));
        });
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
                $possibilities[] = ($len === 2) ? 'm' : 'n';
            }

            // Day detection
            if ($num === $parsed->day) {
                $possibilities[] = ($len === 2) ? 'd' : 'j';
            }

            // Hour detection
            if ($num === $parsed->hour) {
                $possibilities[] = ($len === 2) ? 'H' : 'G';
            } elseif ($num === ($parsed->hour % 12 ?: 12)) {
                $possibilities[] = ($len === 2) ? 'h' : 'g';
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
        if (!empty($componentOptions['year']) && !empty($componentOptions['month']) && !empty($componentOptions['day'])) {
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
        if (empty($combination)) {
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
            } catch (Throwable $e) {
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
            } catch (Throwable $e) {
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
        $hasColon = strpos($dateString, ':') !== false;
        $hasSlash = strpos($dateString, '/') !== false;
        $hasDot = strpos($dateString, '.') !== false;
        $hasDash = strpos($dateString, '-') !== false;
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
        if (empty($separators)) {
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

            // Mixed patterns (some with, some without leading zeros)
            'n/d/Y',
            'd/n/Y',
            'm/j/Y',
            'j/m/Y',
        ];



        foreach ($separators as $sep) {
            foreach ($datePatterns as $pattern) {
                $formats[] = str_replace('-', $sep, $pattern);
            }
        }

        // Add time variations if time component detected
        if ($hasColon) {
            $timePatterns = ['H:i', 'H:i:s'];
            if ($hasAmPm) {
                $timePatterns = array_merge($timePatterns, ['g:i A', 'g:i:s A', 'h:i A', 'h:i:s A']);
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
        if (!strpos($dateString, ' ') && !$hasAlpha && ctype_digit(preg_replace('/[^\d]/', '', $dateString))) {
            $compactFormats = ['Ymd', 'YmdH', 'YmdHi', 'YmdHis', 'dmY', 'mdY'];
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
            $score -= 20;
        }

        if (strpos($format, 'Y') !== false) {
            $score += 10;
        }
        if (strpos($format, 'y') !== false) {
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

        if (strpos($format, 'Y') !== false) {
            $score += 15;
        }
        if (preg_match('/\b0\d/', $dateString) && strpos($format, 'd') !== false) {
            $score += 10;
        }
        if (preg_match('/\b0\d/', $dateString) && strpos($format, 'm') !== false) {
            $score += 10;
        }
        if (strpos($format, 'y') !== false) {
            $score -= 10;
        }
        if (strpos($format, 'M') !== false) {
            $score += 20;
        }
        if (strpos($format, 'A') !== false) {
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
        } catch (Throwable $e) {
            $score -= 30;
        }

        if (strpos($format, 'Y') !== false) {
            $score += 10;
        }
        if (strpos($format, 'y') !== false) {
            $score -= 5;
        }
        if (strpos($format, 'M') !== false) {
            $score += 15;
        }
        if (strpos($format, 'A') !== false) {
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

        for ($i = 0; $i < count($chars); $i++) {
            $char = $chars[$i];

            switch ($char) {
                case 'Y':
                    $result .= 'YYYY';
                    break;
                case 'y':
                    $result .= 'YY';
                    break;
                case 'm':
                    $result .= 'MM';
                    break;
                case 'n':
                    $result .= 'M';
                    break;
                case 'd':
                    $result .= 'DD';
                    break;
                case 'j':
                    $result .= 'D';
                    break;
                case 'H':
                    $result .= 'HH';
                    break;
                case 'G':
                    $result .= 'H';
                    break;
                case 'h':
                    $result .= 'hh';
                    break;
                case 'g':
                    $result .= 'h';
                    break;
                case 'i':
                    $result .= 'mm';
                    break;
                case 's':
                    $result .= 'ss';
                    break;
                case 'A':
                    $result .= 'AM/PM';
                    break;
                case 'a':
                    $result .= 'am/pm';
                    break;
                case 'M':
                    $result .= 'Mon';
                    break;
                case 'F':
                    $result .= 'Month';
                    break;
                default:
                    $result .= $char;
                    break;
            }
        }

        return $result;
    }

    /**
     * Generate a detailed description for UI display
     */
    private static function generateDescription(string $format): string
    {
        $parts = [];

        if (strpos($format, 'Y') !== false) {
            $parts[] = _translate('4-digit year', true);
        } elseif (strpos($format, 'y') !== false) {
            $parts[] = _translate('2-digit year', true);
        }

        if (strpos($format, 'M') !== false) {
            $parts[] = _translate('month name', true);
        } elseif (strpos($format, 'm') !== false) {
            $parts[] = _translate('month with leading zero', true);
        } elseif (strpos($format, 'n') !== false) {
            $parts[] = _translate('month without leading zero', true);
        }

        if (strpos($format, 'd') !== false) {
            $parts[] = _translate('day with leading zero', true);
        } elseif (strpos($format, 'j') !== false) {
            $parts[] = _translate('day without leading zero', true);
        }

        if (strpos($format, 'H') !== false) {
            $parts[] = _translate('24-hour format', true);
        } elseif (strpos($format, 'g') !== false || strpos($format, 'h') !== false) {
            $parts[] = _translate('12-hour format', true);
        }

        if (strpos($format, 'A') !== false) {
            $parts[] = _translate('with AM/PM', true);
        }

        if (strpos($format, 's') !== false) {
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
        } catch (Throwable $e) {
            return _translate('Example not available', true);
        }
    }

    /**
     * Clean and sort suggestions
     */
    private static function cleanAndSortSuggestions(array $suggestions): array
    {
        // Remove duplicates by format string
        $unique = [];
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

        // Convert back to indexed array
        $unique = array_values($formatMap);

        // Sort by confidence, then by method
        usort($unique, function ($a, $b) {
            $confidenceOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            $methodOrder = [
                'carbon_reverse_engineering' => 3,
                'component_analysis' => 2,
                'brute_force' => 1
            ];

            $confDiff = ($confidenceOrder[$b['confidence']] ?? 0) - ($confidenceOrder[$a['confidence']] ?? 0);
            if ($confDiff !== 0) {
                return $confDiff;
            }

            return ($methodOrder[$b['method']] ?? 0) - ($methodOrder[$a['method']] ?? 0);
        });

        return array_slice($unique, 0, 10);
    }


    /**
     * Quick utility method to get the best format suggestion
     */
    public static function getBestFormat(string $sampleDate): ?string
    {
        $suggestions = self::detectFormat($sampleDate);
        return !empty($suggestions) ? $suggestions[0]['format'] : null;
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
                if (preg_match('/^[mn][\/\.-][dj][\/\.-][Yy]/', $format)) {
                    return 'US';
                }

                // Check for European patterns (D/M/Y, D.M.Y, D-M-Y)
                if (preg_match('/^[dj][\/\.-][mn][\/\.-][Yy]/', $format)) {
                    return 'European';
                }

                // Check for ISO patterns (Y-M-D)
                if (preg_match('/^[Yy][\/\.-][mn][\/\.-][dj]/', $format)) {
                    return 'ISO';
                }
            }
        }

        return 'Unknown';
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
            if (preg_match('/^[mn][\/\.-][dj][\/\.-]/', $format)) {
                $usFormat = $suggestion;
                $hasAmbiguousFormats = true;
            } elseif (preg_match('/^[dj][\/\.-][mn][\/\.-]/', $format)) {
                $euFormat = $suggestion;
                $hasAmbiguousFormats = true;
            }

            // Add specific warnings
            if (strpos($format, 'y') !== false) {
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
        uasort($formatCounts, function ($a, $b) {
            if ($a['count'] !== $b['count']) {
                return $b['count'] - $a['count'];
            }

            $confidenceOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            return $confidenceOrder[$b['suggestion']['confidence']] - $confidenceOrder[$a['suggestion']['confidence']];
        });

        return array_map(function ($item) {
            return $item['suggestion'];
        }, array_slice($formatCounts, 0, 5, true));
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
}
