<?php

namespace App\Services;

use Throwable;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use Exception;
use SAMPLE_STATUS;
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
    protected string $currentFileName;

    public function __construct(protected string $testType = 'vl')
    {
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
        $userId = $_SESSION['userId'] ?? null;
        if (!empty($userId)) {
            $this->db->where('imported_by', $userId);
            if ($this->testType !== '' && $this->testType !== '0') {
                $this->db->where('module', $this->testType);
            }
            $this->db->delete('temp_sample_import');
        }
    }

    /**
     * Handle file upload and validation - returns file contents based on file type
     * @param array $allowedExtensions
     * @return string|array Returns string for text files, array for Excel files
     */
    public function handleFileUpload(array $allowedExtensions = ['txt', 'csv', 'xls', 'xlsx'], string $operation = 'parse'): string|array
    {
        if (
            !isset($_FILES['resultFile']) ||
            $_FILES['resultFile']['error'] !== UPLOAD_ERR_OK ||
            $_FILES['resultFile']['size'] <= 0
        ) {
            throw new SystemException('Please select a file to upload', 400);
        }

        $originalFileName = $_FILES['resultFile']['name'];
        $sanitizedFileName = preg_replace('/[^A-Za-z0-9.]/', '-', htmlspecialchars(basename((string) $originalFileName)));
        $extension = MiscUtility::getFileExtension($sanitizedFileName);

        if (!in_array($extension, $allowedExtensions)) {
            throw new SystemException("Invalid file format. Allowed: " . implode(', ', $allowedExtensions), 400);
        }

        $fileName = ($this->postData['fileName'] ?? 'import') . "-" . MiscUtility::generateRandomString(12) . "." . $extension;
        $uploadPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results";

        if (!is_dir($uploadPath)) {
            MiscUtility::makeDirectory($uploadPath);
        }

        $this->currentFileName = realpath($uploadPath) . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($_FILES['resultFile']['tmp_name'], $this->currentFileName)) {
            throw new SystemException('Failed to move uploaded file', 500);
        }

        if ($operation === 'parse') {

            // Parse and return file contents based on extension
            if ($extension === 'txt') {
                // Text files - return raw content as string
                $contents = file_get_contents($this->currentFileName);
                if ($contents === false) {
                    throw new SystemException('Failed to read text file contents');
                }
                return $contents;
            } elseif ($extension === 'csv') {
                // CSV files - return raw content as string (let scripts handle CSV parsing)
                $contents = file_get_contents($this->currentFileName);
                if ($contents === false) {
                    throw new SystemException('Failed to read CSV file contents');
                }
                return $contents;
            } elseif ($extension === 'xls' || $extension === 'xlsx') {
                // Excel files - return parsed array
                try {
                    $spreadsheet = IOFactory::load($this->currentFileName);
                    $worksheet = $spreadsheet->getActiveSheet();
                    return $worksheet->toArray(null, true, true, true);
                } catch (Exception $e) {
                    throw new SystemException('Failed to parse Excel file: ' . $e->getMessage());
                }
            } else {
                // Other file types - return raw content as fallback
                $contents = file_get_contents($this->currentFileName);
                if ($contents === false) {
                    throw new SystemException('Failed to read file contents');
                }
                return $contents;
            }
        } else {
            // just return the file path
            return $this->currentFileName;
        }
    }

    /**
     * Process and insert parsed data - call this after your parsing logic
     */
    public function insertParsedData(array $parsedData): void
    {
        foreach ($parsedData as $sampleCode => $sampleData) {
            $dbRecord = $this->prepareSampleRecord($sampleData, $sampleCode);
            if ($dbRecord !== [] && $this->shouldInsertRecord($dbRecord)) {
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
            'import_machine_name' => base64_decode((string) $this->postData['machineName']),
            'result_reviewed_by' => $_SESSION['userId'],
            'sample_code' => $sampleData['sampleCode'] ?? '',
            'sample_type' => $sampleData['sampleType'] ?? 'S',
            'sample_tested_datetime' => $sampleData['testingDate'] ?? null,
            'result_status' => (string) PENDING_APPROVAL,
            'import_machine_file_name' => $this->currentFileName ?? '',
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
        $primaryKey = TestsService::getPrimaryColumn($this->testType);
        $query = "SELECT facility_id, $primaryKey, result FROM $tableName
                        WHERE sample_code = ?";
        $existingResult = $this->db->rawQueryOne($query, [$sampleCode]);

        // Insert sample control if needed
        $this->insertSampleControl($data['sample_type']);

        // Set sample details based on existing record
        if (!empty($existingResult)) {
            $data['sample_details'] = empty($existingResult['result']) ? 'Existing Sample' : 'Result already exists';
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
    public function handleError(Throwable $e): void
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

    /**
     * Parse date helper
     */
    public function parseDate(string $dateString, ?string $inputFormat = null): ?string
    {
        if ($dateString === '' || $dateString === '0') {
            return null;
        }

        return MemoUtility::remember(function () use ($dateString, $inputFormat): ?string {
            $fullFormat = $inputFormat ?? $this->postData['dateFormat'] ?? 'n/d/Y g:i:s A';

            // Check if input contains time (look for colon pattern)
            $isDateOnly = !preg_match('/\d+:\d+/', trim($dateString));

            if ($isDateOnly) {
                // Extract just the date part from the format (everything before first time character)
                $dateFormat = preg_replace('/\s*[gGhHiIsaA:].+$/', '', $fullFormat);
                $actualFormat = trim($dateFormat);
            } else {
                // Use the full format, but adjust if AM/PM is missing from input
                $hasAmPm = preg_match('/\b(am|pm|AM|PM)\b/', trim($dateString));

                if (!$hasAmPm && str_contains($fullFormat, 'A')) {
                    // Remove AM/PM from format and convert to 24-hour format
                    $actualFormat = str_replace(['g', 'h', ' A', 'A'], ['G', 'H', '', ''], $fullFormat);
                } else {
                    $actualFormat = $fullFormat;
                }
            }

            return DateUtility::getDateTime($dateString, 'Y-m-d H:i:s', $actualFormat);
        });
    }

    public function abbottDateFormatter($testDateFromInstrument, $inputFormat, $interpretFormat = true): ?array
    {

        if (empty($testDateFromInstrument) || empty($inputFormat)) {
            return null;
        }

        return MemoUtility::remember(function () use ($testDateFromInstrument, $inputFormat, $interpretFormat): array {

            $inputFormat = trim((string) $inputFormat);

            if ($interpretFormat === true) {
                $find = ['am', 'pm', 'dd', 'mm', 'yyyy', 'yy'];
                $replace = ['', '', 'd', 'm', 'Y', 'y'];
                $inputFormat = trim(str_ireplace($find, $replace, strtolower($inputFormat)));
            }

            $inputFormat = substr_count((string) $testDateFromInstrument, ':') === 1 ? "$inputFormat h:i" : "$inputFormat H:i:s";

            if (stripos((string) $testDateFromInstrument, 'am') !== false || stripos((string) $testDateFromInstrument, 'pm') !== false) {
                $inputFormat .= ' A';
            }

            error_log($inputFormat);

            $testingDate = DateUtility::getDateTime($testDateFromInstrument, 'Y-m-d H:i', "!$inputFormat");

            return [
                'testingDate' => $testingDate,
                'dateFormat' => $inputFormat,
            ];
        });
    }

    /**
     * Get user by name
     */
    public function getUserByName(string $username): ?int
    {
        if ($username === '' || $username === '0') {
            return null;
        }

        $usersService = ContainerRegistry::get(UsersService::class);
        return $usersService->getOrCreateUser($username);
    }

    // This function removes control characters from the strings in the CSV file.
    // https://en.wikipedia.org/wiki/Control_character#ASCII_control_characters
    // Also checks UTF-8 encoding and converts if needed
    public function removeControlCharsAndEncode($inputString, $encodeToUTF8 = true): string
    {
        return MemoUtility::remember(function () use ($inputString, $encodeToUTF8): string|object|int|float|bool|array|null {
            $inputString = preg_replace('/[[:cntrl:]]/', '', (string) $inputString);
            if ($encodeToUTF8 === true && mb_detect_encoding((string) $inputString, 'UTF-8', true) === false) {
                $inputString = MiscUtility::toUtf8($inputString);
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
        if ($sampleType === '' || $sampleType === '0') {
            return;
        }

        $query = "SELECT r_sample_control_name FROM r_sample_controls WHERE r_sample_control_name = ?";
        $exists = $this->db->rawQueryOne($query, [trim($sampleType)]);

        if (!$exists) {
            $this->db->insert("r_sample_controls", ['r_sample_control_name' => trim($sampleType)]);
        }
    }
}
