<?php

// includes/smart-date-format.php
// This file handles the smart date format detection and validation
use Carbon\Carbon;
use App\Utilities\SmartDateFormatDetector;

header('Content-Type: application/json');

try {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];

    switch ($action) {
        case 'smart_detect': // Updated to match JavaScript library
            // Main detection endpoint - handles both date samples AND format strings automatically
            $input = $_POST['input'] ?? ''; // Updated to match JavaScript parameter name
            if (empty($input)) {
                $response = ['success' => false, 'error' => 'No input provided'];
                break;
            }

            // Determine if input looks like a format string or a date sample
            $isFormatString = SmartDateFormatDetector::looksLikeFormat($input);

            if ($isFormatString) {
                // Handle format string input
                $suggestions = SmartDateFormatDetector::validateAndCorrectFormat($input);
                $response = [
                    'success' => true,
                    'suggestions' => $suggestions,
                    'input_type' => 'format'
                ];
            } else {
                // Handle date sample input
                $suggestions = SmartDateFormatDetector::getSuggestionsForUI($input);
                $response = [
                    'success' => true,
                    'suggestions' => $suggestions,
                    'regional_preference' => SmartDateFormatDetector::detectRegionalPreference($input),
                    'input_type' => 'sample'
                ];
            }
            break;

        case 'detect':
            // Legacy endpoint for backward compatibility
            $sampleDate = $_POST['sampleDate'] ?? '';
            if (empty($sampleDate)) {
                $response = ['success' => false, 'error' => 'No input provided'];
                break;
            }

            $suggestions = SmartDateFormatDetector::getSuggestionsForUI($sampleDate);
            $response = [
                'success' => true,
                'suggestions' => $suggestions,
                'regional_preference' => SmartDateFormatDetector::detectRegionalPreference($sampleDate),
                'input_type' => 'sample'
            ];
            break;

        case 'validate':
            $sampleDate = $_POST['sampleDate'] ?? '';
            $format = $_POST['format'] ?? '';

            if (empty($sampleDate) || empty($format)) {
                $response = ['success' => false, 'error' => 'Sample date and format required'];
                break;
            }

            $validation = SmartDateFormatDetector::validateFormat($sampleDate, $format);
            $response = [
                'success' => true,
                'valid' => $validation['valid'],
                'error' => $validation['error'],
                'parsed_date' => $validation['parsed_date']
            ];
            break;

        case 'test_format':
            // Test a format string with a known date
            $format = $_POST['format'] ?? '';

            if (empty($format)) {
                $response = ['success' => false, 'error' => 'No format provided'];
                break;
            }

            try {
                $testDate = Carbon::create(2025, 6, 19, 14, 30, 45);
                $formatted = $testDate->format($format);
                $parsed = Carbon::createFromFormat($format, $formatted);

                if ($parsed && $parsed->format($format) === $formatted) {
                    $response = [
                        'success' => true,
                        'valid' => true,
                        'example' => $formatted,
                        'description' => SmartDateFormatDetector::describeFormat($format),
                        'parsed_date' => $testDate->format('Y-m-d H:i:s')
                    ];
                } else {
                    $response = [
                        'success' => true,
                        'valid' => false,
                        'error' => 'Format produces inconsistent results'
                    ];
                }
            } catch (Exception $e) {
                $response = [
                    'success' => true,
                    'valid' => false,
                    'error' => $e->getMessage()
                ];
            }
            break;

        default:
            $response = ['success' => false, 'error' => 'Unknown action: ' . $action];
            break;
    }

} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ];
}

echo json_encode($response);
