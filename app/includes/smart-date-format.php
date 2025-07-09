<?php

// includes/smart-date-format.php

use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Utilities\SmartDateFormatDetector;

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new SystemException('Invalid Request Method', 405);
    }
    // Sanitized values from request object
    /** @var Laminas\Diactoros\ServerRequest $request */
    $request = AppRegistry::get('request');
    $_POST = _sanitizeInput($request->getParsedBody());


    $sampleDate = $_POST['sampleDate'] ?? '';
    $action = $_POST['action'] ?? 'detect'; // detect, validate, or best

    if (empty($sampleDate)) {
        echo json_encode([
            'success' => false,
            'error' => 'Sample date is required'
        ]);
        exit;
    }

    switch ($action) {
        case 'validate':
            // Validate a specific format
            $format = $_POST['format'] ?? '';
            if (empty($format)) {
                throw new SystemException('Format is required for validation');
            }

            $result = SmartDateFormatDetector::validateFormat($sampleDate, $format);
            echo json_encode([
                'success' => $result['valid'],
                'action' => 'validate',
                'valid' => $result['valid'],
                'parsed_date' => $result['parsed_date'],
                'error' => $result['error']
            ]);
            break;

        case 'best':
            // Get just the best format quickly
            $bestFormat = SmartDateFormatDetector::getBestFormat($sampleDate);
            $regionalPreference = SmartDateFormatDetector::detectRegionalPreference($sampleDate);

            if ($bestFormat) {
                echo json_encode([
                    'success' => true,
                    'action' => 'best',
                    'format' => $bestFormat,
                    'regional_preference' => $regionalPreference,
                    'description' => SmartDateFormatDetector::describeFormat($bestFormat)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'action' => 'best',
                    'error' => 'Could not detect date format'
                ]);
            }
            break;

        case 'detect':
        default:
            // Full detection with all suggestions (default)
            $suggestions = SmartDateFormatDetector::getSuggestionsWithWarnings($sampleDate);
            $regionalPreference = SmartDateFormatDetector::detectRegionalPreference($sampleDate);

            echo json_encode([
                'success' => true,
                'action' => 'detect',
                'suggestions' => $suggestions,
                'regional_preference' => $regionalPreference,
                'sample_date' => $sampleDate,
                'total_found' => count($suggestions),
                'best_format' => !empty($suggestions) ? $suggestions[0]['format'] : null
            ]);
            break;
    }
} catch (SystemException $e) {
    // Log the exception details internally if needed, but do not expose to the client
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
        'trace' => $e->getTraceAsString(),
    ]);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing your request.',
        'action' => $_POST['action'] ?? 'detect'
    ]);
}
