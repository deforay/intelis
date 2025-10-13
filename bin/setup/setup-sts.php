<?php

//bin/setup-sts.php

use App\Services\CommonService;
use App\Services\ConfigService;
use App\Utilities\LoggerUtility;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;

require_once __DIR__ . "/../../bootstrap.php";

ini_set('memory_limit', -1);
set_time_limit(0);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var ConfigService $configService */
$configService = ContainerRegistry::get(ConfigService::class);

$cliMode = php_sapi_name() === 'cli';
$isLIS = $general->isLISInstance();

if (!$isLIS || !$cliMode) {
    echo "❗ This script is only for LIS instances and must be run from the command line." . PHP_EOL;
    exit(0);
}

// Clear the file cache
(ContainerRegistry::get(FileCacheUtility::class))->clear();

/**
 * Function to read user input from command line.
 * Returns null on EOF/non-interactive.
 */
function readUserInput($prompt = '')
{
    echo $prompt;
    $h = fopen('php://stdin', 'r');
    if ($h === false) {
        return null;
    }
    $line = fgets($h);
    fclose($h);
    if ($line === false) {
        // EOF or not a TTY
        return null;
    }
    return trim($line);
}

/**
 * Function to validate URL format
 */
function isValidUrl($url)
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Function to normalize URL with smart STS validation
 */
function normalizeUrl($url, $labId = null)
{
    $url = trim($url);

    // If URL already has a protocol, test it as-is first
    if (preg_match('/^https?:\/\//', $url)) {
        $testUrl = rtrim($url, '/');
        if (CommonService::validateStsUrl($testUrl, $labId)) {
            return $testUrl;
        }
        // If the provided protocol doesn't work, we'll still try the opposite
        $domain = preg_replace('/^https?:\/\//', '', $url);
    } else {
        $domain = $url;
    }

    // Try HTTPS first
    $httpsUrl = 'https://' . rtrim($domain, '/');
    if (CommonService::validateStsUrl($httpsUrl, $labId)) {
        return $httpsUrl;
    }

    // Fallback to HTTP
    $httpUrl = 'http://' . rtrim($domain, '/');
    if (CommonService::validateStsUrl($httpUrl, $labId)) {
        return $httpUrl;
    }

    // Neither worked, return HTTPS version anyway (let later validation handle the error)
    return $httpsUrl;
}

// Parse CLI arguments
$options = getopt('k', ['key:']);
$apiKey = $options['key'] ?? null;

// Interactive mode - handle STS and Lab setup
echo "=== STS Configuration Setup ===" . PHP_EOL . PHP_EOL;


// Step 1: Handle STS URL
$currentRemoteURL = rtrim($general->getRemoteURL(), '/');
$urlWasEmpty = empty($currentRemoteURL);
$urlChanged = false;

// Get current lab ID for URL validation
$currentLabId = $general->getSystemConfig('sc_testing_lab_id');

// If no current URL, allow skipping with Enter or on EOF
if (empty($currentRemoteURL)) {
    echo "No STS URL is currently configured." . PHP_EOL;
    echo "Press Enter to skip (you can set it later from Admin > System Config)." . PHP_EOL;

    $attempts = 0;
    $newRemoteURL = ''; // default to “skipped”

    do {
        $attempts++;
        $userInput = readUserInput("STS URL: ");

        // Non-interactive / EOF or user pressed Enter -> skip
        if ($userInput === null || $userInput === '') {
            echo "Skipping STS URL setup for now." . PHP_EOL;
            $newRemoteURL = '';
            break;
        }

        $newRemoteURL = normalizeUrl($userInput, $currentLabId);

        if (!isValidUrl($newRemoteURL)) {
            echo "Unable to create a valid URL. Please try again or press Enter to skip." . PHP_EOL;
            continue;
        }

        if (!CommonService::validateStsUrl($newRemoteURL, $currentLabId)) {
            echo "Cannot connect to STS at this URL. Try again or press Enter to skip." . PHP_EOL;
            continue;
        }

        echo "Using: " . $newRemoteURL . PHP_EOL;
        $urlChanged = true;
        break;
    } while ($attempts < 5);
} else {
    echo "Current STS URL: " . $currentRemoteURL . PHP_EOL . PHP_EOL;

    $confirmUrl = strtolower((string)readUserInput("Is this STS URL correct? (y/n) [y]: "));
    if ($confirmUrl === '' || $confirmUrl === null) {
        $confirmUrl = 'y';
    }

    if ($confirmUrl !== 'y' && $confirmUrl !== 'yes') {
        echo PHP_EOL . "Press Enter to skip (you can set it later)." . PHP_EOL;

        $attempts = 0;
        do {
            $attempts++;
            $userInput = readUserInput("STS URL: ");

            if ($userInput === null || $userInput === '') {
                echo "Skipping STS URL change; keeping the previous value." . PHP_EOL;
                $newRemoteURL = $currentRemoteURL;
                break;
            }

            $newRemoteURL = normalizeUrl($userInput, $currentLabId);

            if (!isValidUrl($newRemoteURL)) {
                echo "Unable to create a valid URL. Try again or press Enter to skip." . PHP_EOL;
                continue;
            }

            if (!CommonService::validateStsUrl($newRemoteURL, $currentLabId)) {
                echo "Cannot connect to STS at this URL. Try again or press Enter to skip." . PHP_EOL;
                continue;
            }

            echo "Using: " . $newRemoteURL . PHP_EOL;
            $urlChanged = ($newRemoteURL !== $currentRemoteURL);
            break;
        } while ($attempts < 5);
    } else {
        $newRemoteURL = $currentRemoteURL;
    }
}

// Step 2: only update if we actually changed AND not skipped
if ($urlChanged && $newRemoteURL !== '') {
    echo PHP_EOL . "Updating STS URL in configuration..." . PHP_EOL;
    try {
        $configService->updateConfig(['remoteURL' => $newRemoteURL]);
        echo "✅ STS URL updated successfully to: " . $newRemoteURL . PHP_EOL;
    } catch (Exception $e) {
        LoggerUtility::logError("Error updating STS URL: " . $e->getMessage(), [
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString(),
        ]);
        echo "❌ Error updating STS URL. Please check logs for details." . PHP_EOL;
        exit(1);
    }
}

// Step 3: Only refresh metadata if we have a non-empty URL and it changed/was newly set
if ($newRemoteURL !== '' && ($urlWasEmpty || $urlChanged)) {
    $reason = $urlWasEmpty ? "STS URL was freshly set" : "STS URL was changed";

    echo PHP_EOL;
    echo "=== Refreshing Database Metadata ===" . PHP_EOL;
    echo "Running metadata refresh script (" . $reason . ")..." . PHP_EOL;

    $metadataScriptPath = APPLICATION_PATH . "/tasks/remote/sts-metadata-receiver.php";

    if (!file_exists($metadataScriptPath)) {
        echo "❌ Metadata script not found at: " . $metadataScriptPath . PHP_EOL;
        echo "Please run manually: php app/tasks/remote/sts-metadata-receiver.php -ft" . PHP_EOL;
        echo "Or alternatively: ./intelis reset-metadata" . PHP_EOL;
        exit(1);
    } else {
        $metadataCommand = "php " . escapeshellarg($metadataScriptPath) . " -ft";
        echo "Executing: " . $metadataCommand . PHP_EOL;
        echo PHP_EOL;

        $output = [];
        $returnCode = 0;
        exec($metadataCommand . " 2>&1", $output, $returnCode);

        foreach ($output as $line) {
            echo $line . PHP_EOL;
        }

        if ($returnCode === 0) {
            echo PHP_EOL;
            echo "✅ Metadata refresh completed successfully." . PHP_EOL;
        } else {
            echo PHP_EOL;
            echo "❌ Metadata refresh failed with return code: " . $returnCode . PHP_EOL;
            echo "Please run manually: php app/tasks/remote/sts-metadata-receiver.php -ft" . PHP_EOL;
            exit(1);
        }
    }
} else {
    echo PHP_EOL . "Skipping metadata refresh (no STS URL set/changed)." . PHP_EOL;
}

// Step 4: Handle Lab Configuration
echo PHP_EOL;
echo "=== Lab Configuration ===" . PHP_EOL;

$currentLabId = $general->getSystemConfig('sc_testing_lab_id');

if (empty($currentLabId)) {
    echo "No lab is currently configured." . PHP_EOL;
    $needLabSelection = true;
} else {
    $labDetails = $db->rawQueryOne(
        "SELECT facility_name FROM facility_details WHERE facility_id = ? AND facility_type = 2 AND status = 'active'",
        [$currentLabId]
    );

    if ($labDetails) {
        echo "Current InteLIS Lab ID: " . $currentLabId . PHP_EOL;
        echo "Lab Name: " . $labDetails['facility_name'] . PHP_EOL;
        echo PHP_EOL;

        $confirmLab = readUserInput("Is this the correct lab? (y/n) [y]: ");
        $confirmLab = strtolower(trim($confirmLab));

        if (empty($confirmLab)) {
            $confirmLab = 'y';
        }

        $needLabSelection = ($confirmLab !== 'y' && $confirmLab !== 'yes');
    } else {
        echo "Current lab ID (" . $currentLabId . ") not found in active facilities." . PHP_EOL;
        $needLabSelection = true;
    }
}

if ($needLabSelection) {
    echo PHP_EOL;
    echo "=== Lab Selection ===" . PHP_EOL;

    $testingLabs = $db->rawQuery("SELECT facility_id, facility_name FROM facility_details 
                                    WHERE facility_type = 2 AND status = 'active' 
                                    ORDER BY facility_name");

    if (empty($testingLabs)) {
        echo "❌ No active testing labs found. Please ensure facilities are properly configured." . PHP_EOL;
        exit(1);
    }

    echo "Found " . count($testingLabs) . " available labs." . PHP_EOL;
    echo PHP_EOL;
    echo "Choose selection method:" . PHP_EOL;
    echo "1. Search by name" . PHP_EOL;
    echo "2. Browse all labs" . PHP_EOL;
    echo "3. Enter facility ID directly" . PHP_EOL;
    echo PHP_EOL;

    $method = readUserInput("Select method (1-3) [1]: ");
    $method = trim($method);
    if (empty($method)) $method = '1';

    $selectedLab = null;

    if ($method === '1') {
        // Search by name
        do {
            echo PHP_EOL;
            $searchTerm = readUserInput("Enter lab name (or part of name) to search: ");
            $searchTerm = trim($searchTerm);

            if (empty($searchTerm)) {
                echo "Search term cannot be empty." . PHP_EOL;
                continue;
            }

            $filteredLabs = array_filter($testingLabs, function ($lab) use ($searchTerm) {
                return stripos($lab['facility_name'], $searchTerm) !== false;
            });

            if (empty($filteredLabs)) {
                echo "No labs found matching '" . $searchTerm . "'. Try a different search term." . PHP_EOL;
                continue;
            }

            echo PHP_EOL;
            echo "Found " . count($filteredLabs) . " matching lab(s):" . PHP_EOL;
            $filteredLabs = array_values($filteredLabs); // Reindex

            foreach ($filteredLabs as $index => $lab) {
                echo ($index + 1) . ". [ID: " . $lab['facility_id'] . "] " . $lab['facility_name'] . PHP_EOL;
            }

            echo PHP_EOL;

            if (count($filteredLabs) === 1) {
                $confirm = readUserInput("Select this lab? (y/n) [y]: ");
                if (empty($confirm) || strtolower($confirm) === 'y') {
                    $selectedLab = $filteredLabs[0];
                    break;
                }
            } else {
                $selection = readUserInput("Select lab number (1-" . count($filteredLabs) . ") or 's' to search again: ");
                $selection = trim($selection);

                if (strtolower($selection) === 's') {
                    continue; // Search again
                }

                if (is_numeric($selection)) {
                    $selectedIndex = (int)$selection - 1;
                    if ($selectedIndex >= 0 && $selectedIndex < count($filteredLabs)) {
                        $selectedLab = $filteredLabs[$selectedIndex];
                        break;
                    }
                }
                echo "Invalid selection. Please try again." . PHP_EOL;
            }
        } while (true);
    } elseif ($method === '2') {
        // Browse all labs (paginated)
        $pageSize = 20;
        $totalLabs = count($testingLabs);
        $currentPage = 0;

        do {
            $startIndex = $currentPage * $pageSize;
            $endIndex = min($startIndex + $pageSize, $totalLabs);

            echo PHP_EOL;
            echo "=== Labs " . ($startIndex + 1) . "-" . $endIndex . " of " . $totalLabs . " ===" . PHP_EOL;

            for ($i = $startIndex; $i < $endIndex; $i++) {
                $lab = $testingLabs[$i];
                echo ($i + 1) . ". [ID: " . $lab['facility_id'] . "] " . $lab['facility_name'] . PHP_EOL;
            }

            echo PHP_EOL;
            echo "Commands: [number] = select lab, [n] = next page, [p] = previous page, [s] = search, [q] = quit" . PHP_EOL;

            $input = readUserInput("Enter choice: ");
            $input = trim(strtolower($input));

            if ($input === 'n' && $endIndex < $totalLabs) {
                $currentPage++;
            } elseif ($input === 'p' && $currentPage > 0) {
                $currentPage--;
            } elseif ($input === 's') {
                $method = '1'; // Switch to search mode
                break;
            } elseif ($input === 'q') {
                echo "Lab selection cancelled." . PHP_EOL;
                exit(0);
            } elseif (is_numeric($input)) {
                $selectedIndex = (int)$input - 1;
                if ($selectedIndex >= 0 && $selectedIndex < $totalLabs) {
                    $selectedLab = $testingLabs[$selectedIndex];
                    break;
                } else {
                    echo "❗ Invalid lab number. Please enter a number between 1 and " . $totalLabs . "." . PHP_EOL;
                }
            } else {
                echo "❗ Invalid command." . PHP_EOL;
            }
        } while (true);
    } elseif ($method === '3') {
        // Enter facility ID directly
        do {
            echo PHP_EOL;
            $facilityId = readUserInput("Enter facility ID: ");
            $facilityId = trim($facilityId);

            if (empty($facilityId)) {
                echo "Facility ID cannot be empty." . PHP_EOL;
                continue;
            }

            // Find lab by ID
            $foundLab = null;
            foreach ($testingLabs as $lab) {
                if ($lab['facility_id'] == $facilityId) {
                    $foundLab = $lab;
                    break;
                }
            }

            if ($foundLab) {
                echo "Found: [ID: " . $foundLab['facility_id'] . "] " . $foundLab['facility_name'] . PHP_EOL;
                $confirm = readUserInput("Select this lab? (y/n) [y]: ");
                if (empty($confirm) || strtolower($confirm) === 'y') {
                    $selectedLab = $foundLab;
                    break;
                }
            } else {
                echo "❗ Facility ID '" . $facilityId . "' not found in active labs." . PHP_EOL;
                $retry = readUserInput("Try again? (y/n) [y]: ");
                if (!empty($retry) && strtolower($retry) === 'n') {
                    break;
                }
            }
        } while (true);
    }

    if ($selectedLab === null) {
        echo "❗ No lab selected. Exiting." . PHP_EOL;
        exit(0);
    }

    echo PHP_EOL;
    echo "Updating lab configuration..." . PHP_EOL;
    echo "ℹ️ Selected Lab: [InteLIS Lab ID: " . $selectedLab['facility_id'] . "] " . $selectedLab['facility_name'] . PHP_EOL;

    try {
        $data = ['value' => $selectedLab['facility_id']];
        $db->where('name', 'sc_testing_lab_id');
        $result = $db->update('system_config', $data);

        if ($result) {
            echo "✅ Lab ID updated successfully." . PHP_EOL;
        } else {
            echo "❌ Failed to update lab ID in system configuration." . PHP_EOL;
            exit(1);
        }
    } catch (Exception $e) {
        LoggerUtility::logError(
            "Error updating lab ID: " . $e->getMessage(),
            [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]
        );
        echo "❌ Error updating lab ID. Please check logs for details." . PHP_EOL;
        exit(1);
    }
} else {
    // Use existing lab ID and get details for display
    $selectedLab = $db->rawQueryOne(
        "SELECT facility_id, facility_name FROM facility_details WHERE facility_id = ? AND facility_type = 2 AND status = 'active'",
        [$currentLabId]
    );
}

echo PHP_EOL;
echo "✅ Configuration setup complete!" . PHP_EOL;
echo PHP_EOL;

// Step 5: Call the original token generation script
$remoteURL = isset($newRemoteURL) ? $newRemoteURL : $currentRemoteURL;

if (isset($selectedLab)) {
    $labId = $selectedLab['facility_id'];
    $labName = $selectedLab['facility_name'];
} else {
    $labId = $currentLabId;
    $labName = isset($labDetails) ? $labDetails['facility_name'] : 'Unknown';
}

// echo "=== Proceeding with Token Generation ===" . PHP_EOL;
// echo "Using STS URL: " . $remoteURL . PHP_EOL;
// echo "InteLIS Lab ID: " . $labId . PHP_EOL;
// echo "Lab Name: " . $labName . PHP_EOL;
// echo PHP_EOL;

// $tokenScriptPath = BIN_PATH . "/token.php";
// $tokenCommand = "php " . escapeshellarg($tokenScriptPath);

// // Add API key if provided
// if (!empty($apiKey)) {
//     $tokenCommand .= " --key " . escapeshellarg($apiKey);
// }

// echo "Executing: " . $tokenCommand . PHP_EOL;
// echo PHP_EOL;

// // Execute the token script and capture output
// $output = [];
// $returnCode = 0;
// exec("$tokenCommand 2>&1", $output, $returnCode);

// // Display the output from token script
// foreach ($output as $line) {
//     echo $line . PHP_EOL;
// }

// if ($returnCode === 0) {
//     echo PHP_EOL;
//     echo "✅ STS Setup and Token Generation Complete" . PHP_EOL;
// } else {
//     echo PHP_EOL;
//     echo "❌ Token generation failed with return code: " . $returnCode . PHP_EOL;
//     exit($returnCode);
// }


// Clear the file cache again -- just in case
(ContainerRegistry::get(FileCacheUtility::class))->clear();
