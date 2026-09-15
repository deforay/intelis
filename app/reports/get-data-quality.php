<?php

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
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
            echo JsonUtility::encodeUtf8Json([
                'limit' => DataQualityReportUtility::SAMPLE_LIMIT,
                'samples' => iterator_to_array(DataQualityReportUtility::samples($testType, $filters, $db, $general), false),
            ]);
            break;

        case 'export':
            $labels = array_map(fn($c) => $c['label'], DataQualityReportUtility::checks($testType));
            $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Data-Quality-' . $testType . '-' . date('d-M-Y-H-i-s') . '.xlsx';

            $writer = new Writer();
            $writer->openToFile($fileName);
            $writer->addRow(Row::fromValues([
                _translate('Sample ID'),
                _translate('Patient ID'),
                _translate('Sample Collection Date'),
                _translate('Request Created On'),
                _translate('Facility'),
                _translate('Status'),
                _translate('Missing Fields'),
            ]));
            foreach (DataQualityReportUtility::samples($testType, $filters, $db, $general, null) as $sample) {
                $writer->addRow(Row::fromValues([
                    $sample['sampleCode'],
                    $sample['patientId'],
                    DateUtility::humanReadableDateFormat($sample['collected']),
                    DateUtility::humanReadableDateFormat($sample['requested']),
                    $sample['facility'],
                    $sample['status'],
                    implode(', ', array_map(fn($k) => $labels[$k], $sample['missing'])),
                ]));
            }
            $writer->close();
            echo JsonUtility::encodeUtf8Json(['download' => _downloadToken($fileName)]);
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
