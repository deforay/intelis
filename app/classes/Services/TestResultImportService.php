<?php

namespace App\Services;

use Exception;
use SAMPLE_STATUS;
use DateTimeImmutable;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MemoUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\TestResultsService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Service class that handles common import operations
 * Can be called from specific test result import scripts
 */
class TestResultImportService
{
    protected DatabaseService $db;
    protected TestResultsService $testResultsService;
    protected CommonService $general;
    protected array $postData;
    protected string $testType;

    public function __construct(string $testType = 'vl')
    {
        $this->testType = $testType;
        $this->db = ContainerRegistry::get(DatabaseService::class);
        $this->testResultsService = ContainerRegistry::get(TestResultsService::class);
        $this->general = ContainerRegistry::get(CommonService::class);

        $request = AppRegistry::get('request');
        $this->postData = _sanitizeInput($request->getParsedBody());
    }

    /**
     * Initialize import process - call this first in your procedural scripts
     */
    public function initializeImport(): void
    {
        $this->testResultsService->clearPreviousImportsByUser($_SESSION['userId'], $this->testType);
    }

    /**
     * Handle file upload and validation - returns file contents based on file type
     * @param array $allowedExtensions
     * @return string|array Returns string for text files, array for Excel files
     */
    public function handleFileUpload(array $allowedExtensions = ['txt', 'csv', 'xls', 'xlsx'], string $operation = 'parse')
    {
        if (
            !isset($_FILES['resultFile']) ||
            $_FILES['resultFile']['error'] !== UPLOAD_ERR_OK ||
            $_FILES['resultFile']['size'] <= 0
        ) {
            throw new SystemException('Please select a file to upload', 400);
        }

        $originalFileName = $_FILES['resultFile']['name'];
        $sanitizedFileName = preg_replace('/[^A-Za-z0-9.]/', '-', htmlspecialchars(basename($originalFileName)));
        $extension = MiscUtility::getFileExtension($sanitizedFileName);

        if (!in_array($extension, $allowedExtensions)) {
            throw new SystemException("Invalid file format. Allowed: " . implode(', ', $allowedExtensions), 400);
        }

        $fileName = ($this->postData['fileName'] ?? 'import') . "-" . MiscUtility::generateRandomString(12) . "." . $extension;
        $uploadPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results";

        if (!is_dir($uploadPath)) {
            MiscUtility::makeDirectory($uploadPath);
        }

        $resultFile = realpath($uploadPath) . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($_FILES['resultFile']['tmp_name'], $resultFile)) {
            throw new SystemException('Failed to move uploaded file', 500);
        }

        if ($operation == 'parse') {

            // Parse and return file contents based on extension
            if ($extension === 'txt') {
                // Text files - return raw content as string
                $contents = file_get_contents($resultFile);
                if ($contents === false) {
                    throw new SystemException('Failed to read text file contents');
                }
                return $contents;
            } elseif ($extension === 'csv') {
                // CSV files - return raw content as string (let scripts handle CSV parsing)
                $contents = file_get_contents($resultFile);
                if ($contents === false) {
                    throw new SystemException('Failed to read CSV file contents');
                }
                return $contents;
            } elseif ($extension === 'xls' || $extension === 'xlsx') {
                // Excel files - return parsed array
                try {
                    $spreadsheet = IOFactory::load($resultFile);
                    $worksheet = $spreadsheet->getActiveSheet();
                    return $worksheet->toArray(null, true, true, true);
                } catch (Exception $e) {
                    throw new SystemException('Failed to parse Excel file: ' . $e->getMessage());
                }
            } else {
                // Other file types - return raw content as fallback
                $contents = file_get_contents($resultFile);
                if ($contents === false) {
                    throw new SystemException('Failed to read file contents');
                }
                return $contents;
            }
        } else {
            // just return the file path
            return $resultFile;
        }
    }

    /**
     * Process and insert parsed data - call this after your parsing logic
     */
    public function insertParsedData(array $parsedData): void
    {
        foreach ($parsedData as $sampleCode => $sampleData) {
            $dbRecord = $this->prepareSampleRecord($sampleData, $sampleCode);
            if (!empty($dbRecord) && $this->shouldInsertRecord($dbRecord)) {
                $this->db->insert("temp_sample_import", $dbRecord);
            }
        }
    }


    /**
     * Prepare sample record for database insertion - returns the data array without inserting
     */
    public function prepareSampleRecord(array $sampleData, string $sampleCode): array
    {
        // Build base database record
        $data = [
            'module' => $this->testType,
            'lab_id' => base64_decode((string) $this->postData['labId']),
            'vl_test_platform' => $this->postData['vltestPlatform'] ?? null,
            'import_machine_name' => $this->postData['configMachineName'] ?? null,
            'result_reviewed_by' => $_SESSION['userId'],
            'sample_code' => $sampleData['sampleCode'] ?? '',
            'sample_type' => $sampleData['sampleType'] ?? 'S',
            'sample_tested_datetime' => $sampleData['testingDate'] ?? null,
            'result_status' => (string) SAMPLE_STATUS\PENDING_APPROVAL,
            'import_machine_file_name' => basename($sampleData['fileName'] ?? ''),
            'lab_tech_comments' => $sampleData['resultFlag'] ?? '',
            'lot_number' => $sampleData['lotNumber'] ?? null,
            'lot_expiration_date' => $sampleData['lotExpirationDate'] ?? null,
            'cv_number' => $sampleData['cvNumber'] ?? null,
            'sample_review_by' => $sampleData['reviewBy'] ?? null,
            'result_value_log' => $sampleData['logVal'] ?? null,
            'result_value_absolute' => $sampleData['absVal'] ?? null,
            'result_value_text' => $sampleData['txtVal'] ?? null,
            'result_value_absolute_decimal' => $sampleData['absDecimalVal'] ??  null,
            'result' => $sampleData['result'] ?? null
        ];

        // Check for existing sample
        $tableName = TestsService::getTestTableName($this->testType);
        $primaryKey = TestsService::getTestPrimaryKeyColumn($this->testType);
        $query = "SELECT facility_id, $primaryKey, result FROM $tableName
                        WHERE sample_code = ?";
        $existingResult = $this->db->rawQueryOne($query, [$sampleCode]);

        // Insert sample control if needed
        $this->insertSampleControl($data['sample_type']);

        // Set sample details based on existing record
        if (!empty($existingResult)) {
            $data['sample_details'] = !empty($existingResult['result']) ? 'Result already exists' : 'Existing Sample';
            $data['facility_id'] = $existingResult['facility_id'];
        } else {
            $data['sample_details'] = 'New Sample';
        }

        // Add import metadata
        $data['result_imported_datetime'] = DateUtility::getCurrentDateTime();
        $data['imported_by'] = $_SESSION['userId'];

        return $data;
    }

    /**
     * Check if record should be inserted based on business rules
     */
    protected function shouldInsertRecord(array $data): bool
    {
        return !empty($data['sample_code']) && ($data['sample_code'] !== $data['sample_type']);
    }

    /**
     * Handle successful import - call this at the end
     */
    public function handleSuccess(): void
    {
        $_SESSION['alertMsg'] = "Results imported successfully";

        $eventType = 'import';
        $action = $_SESSION['userName'] . ' imported test results';
        $resource = 'import-results-manually';
        $this->general->activityLog($eventType, $action, $resource);
    }

    /**
     * Handle import error
     */
    public function handleError(Exception $e): void
    {
        LoggerUtility::logError($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        $_SESSION['alertMsg'] = "Import failed: " . $e->getMessage();
    }

    /**
     * Redirect after import
     */
    public function redirect(): void
    {
        $type = $this->testType;
        $machine = $this->postData['machineName'] ?? '';
        $redirectUrl = "/import-result/imported-results.php?t={$type}&timestamp=" . time();
        if (!empty($machine)) {
            $redirectUrl .= "&machine={$machine}";
        }
        header("Location: {$redirectUrl}");
        exit;
    }

    // Utility methods that can be used in procedural scripts

    /**
     * Parse date helper
     */
    public function parseDate(string $dateString, ?string $format = null): ?string
    {
        if (empty($dateString)) return null;
        $format = $format ?: ($this->postData['dateFormat'] ?? 'd/m/Y H:i');

        return MemoUtility::remember(function () use ($dateString, $format) {

            try {
                $dateObject = DateTimeImmutable::createFromFormat("!$format", $dateString);
                if ($dateObject) {
                    return $dateObject->format('Y-m-d H:i:s');
                }
            } catch (Exception $e) {
                // Silent fail for date parsing
            }
            return null;
        });
    }

    public function abbottTestingDateFormatter($testDate, $testDateFormat, $interpretFormat = true): ?array
    {

        if (empty($testDate) || empty($testDateFormat)) {
            return null;
        }

        $testDateFormat = trim((string) $testDateFormat);

        if ($interpretFormat === true) {
            $find = ['am', 'pm', 'dd', 'mm', 'yyyy', 'yy'];
            $replace = ['', '', 'd', 'm', 'Y', 'y'];
            $testDateFormat = trim(str_ireplace($find, $replace, strtolower($testDateFormat)));
        }

        $testingDateFormat = substr_count((string) $testDate, ':') === 1 ? "$testDateFormat h:i" : "$testDateFormat H:i:s";

        if (stripos((string) $testDate, 'am') !== false || stripos((string) $testDate, 'pm') !== false) {
            $testingDateFormat .= ' A';
        }

        $timestamp = DateTimeImmutable::createFromFormat("!$testingDateFormat", $testDate);
        $testingDate = $timestamp ? $timestamp->format('Y-m-d H:i') : null;

        return [
            'testingDate' => $testingDate,
            'dateFormat' => $testDateFormat,
        ];
    }

    /**
     * Get user by name
     */
    public function getUserByName(string $username): ?int
    {
        if (empty($username)) return null;

        $usersService = ContainerRegistry::get(UsersService::class);
        return $usersService->getOrCreateUser($username);
    }

    // This function removes control characters from the strings in the CSV file.
    // https://en.wikipedia.org/wiki/Control_character#ASCII_control_characters
    // Also checks UTF-8 encoding and converts if needed
    public function removeCntrlCharsAndEncode($inputString, $encodeToUTF8 = true): string
    {
        return MemoUtility::remember(function () use ($inputString, $encodeToUTF8) {
            $inputString = preg_replace('/[[:cntrl:]]/', '', (string) $inputString);
            if ($encodeToUTF8 === true && mb_detect_encoding($inputString, 'UTF-8', true) === false) {
                $inputString = mb_convert_encoding($inputString, 'UTF-8');
            }
            return $inputString;
        });
    }

    /**
     * Get current date format from POST
     */
    public function getDateFormat(): string
    {
        return $this->postData['dateFormat'] ?? 'd/m/Y H:i';
    }

    protected function insertSampleControl(string $sampleType): void
    {
        if (empty($sampleType)) return;

        $query = "SELECT r_sample_control_name FROM r_sample_controls WHERE r_sample_control_name = ?";
        $exists = $this->db->rawQueryOne($query, [trim($sampleType)]);

        if (!$exists) {
            $this->db->insert("r_sample_controls", ['r_sample_control_name' => trim($sampleType)]);
        }
    }
}
