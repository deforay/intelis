<?php

use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Utilities\ClinicReportUtility;
use App\Utilities\DataQualityReportUtility;

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$filters = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$testType = (string) ($filters['testType'] ?? '');

try {
    // AJAX requests bypass the access control layer: a test type is readable by
    // whoever can open its clinic report.
    if (!ClinicReportUtility::canView($testType)) {
        throw new SystemException(_translate('You do not have permission to perform this action.'), 403);
    }

    switch ($filters['action'] ?? '') {
        case 'summary':
            $summary = DataQualityReportUtility::summary($testType, $filters, $db, $general);
            $summary['period'] = DateUtility::humanReadableDateFormat($summary['startDate'])
                . ' - ' . DateUtility::humanReadableDateFormat($summary['endDate']);
            echo JsonUtility::encodeUtf8Json($summary);
            break;

        case 'samples':
            $samples = iterator_to_array(DataQualityReportUtility::samples($testType, $filters, $db, $general), false);
            // The export of this listing (export-data-quality.php) sizes its
            // job from this: a count below the limit is exact, one at the
            // limit only says "at least", so it is not kept.
            $_SESSION[DataQualityReportUtility::LISTING_SESSION_KEY] = [
                'key' => DataQualityReportUtility::listingKey($filters),
                'count' => count($samples) < DataQualityReportUtility::SAMPLE_LIMIT ? count($samples) : null,
            ];
            echo JsonUtility::encodeUtf8Json([
                'limit' => DataQualityReportUtility::SAMPLE_LIMIT,
                'samples' => $samples,
            ]);
            break;

        default:
            throw new SystemException('Invalid data quality action');
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
    ]);
    echo JsonUtility::encodeUtf8Json(['error' => _translate('Unable to load the data quality report right now')]);
}
