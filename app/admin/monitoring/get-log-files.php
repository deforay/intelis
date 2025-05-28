<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;

$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$logType = $_GET['log_type'] ?? 'application';
$linesPerPage = 20;
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($logType === 'php_error') {
    $file = ini_get('error_log');
} else {
    $date = isset($_GET['date']) ? DateUtility::isoDateFormat($_GET['date']) : date('Y-m-d');
    $file = ROOT_PATH . '/logs/' . $date . '-logfile.log';
}

function getMostRecentLogFile($logDirectory)
{
    $files = glob($logDirectory . '/*.log');
    if (!$files) {
        return null;
    }

    usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    return $files[0];
}

function parseSearchTerms($searchString)
{
    $terms = [];
    preg_match_all('/"([^"]+)"|\'([^\']+)\'|\+(\S+)|\b(\S+)\b/', $searchString, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        if (!empty($match[1])) {
            $terms[] = ['type' => 'phrase', 'value' => $match[1]];
        } elseif (!empty($match[2])) {
            $terms[] = ['type' => 'phrase', 'value' => $match[2]];
        } elseif (!empty($match[3])) {
            $terms[] = ['type' => 'exact', 'value' => $match[3]];
        } elseif (!empty($match[4])) {
            $terms[] = ['type' => 'partial', 'value' => $match[4]];
        }
    }

    return array_filter($terms, function($term) {
        return strlen($term['value']) > 0;
    });
}

function lineContainsAllSearchTerms($line, $search)
{
    if (empty($search)) {
        return true;
    }

    $terms = parseSearchTerms(trim($search));

    if (empty($terms)) {
        return true;
    }

    foreach ($terms as $term) {
        $found = false;

        switch ($term['type']) {
            case 'exact':
                $pattern = '/\b' . preg_quote($term['value'], '/') . '\b/i';
                $found = preg_match($pattern, $line);
                break;

            case 'phrase':
                $found = stripos($line, $term['value']) !== false;
                break;

            case 'partial':
            default:
                $found = stripos($line, $term['value']) !== false;
                break;
        }

        if (!$found) {
            return false;
        }
    }

    return true;
}

function detectLogLevel($line)
{
    $line = strtolower($line);
    if (strpos($line, 'error') !== false || strpos($line, 'exception') !== false || strpos($line, 'fatal') !== false) {
        return 'error';
    } elseif (strpos($line, 'warn') !== false) {
        return 'warning';
    } elseif (strpos($line, 'info') !== false) {
        return 'info';
    } elseif (strpos($line, 'debug') !== false) {
        return 'debug';
    }
    return 'info';
}

function formatApplicationLogEntry($entry)
{
    $entry = preg_replace('/\\\\n#(\d+)/', '<br/><span style="color:#e83e8c;font-weight:bold;">#$1</span>', $entry);
    $entry = preg_replace('/\n#(\d+)/', '<br/><span style="color:#e83e8c;font-weight:bold;">#$1</span>', $entry);
    $entry = preg_replace('/\\n#(\d+)/', '<br/><span style="color:#e83e8c;font-weight:bold;">#$1</span>', $entry);

    $entry = str_replace('\n#',  '<br/><span style="color:#e83e8c;font-weight:bold;">#', $entry);

    $entry = preg_replace_callback(
        '/(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2})?)/i',
        function ($matches) {
            $isoTimestamp = $matches[1];

            try {
                $humanReadable = DateUtility::humanReadableDateFormat($isoTimestamp, includeTime: true, withSeconds: true);
                return '<strong title="' . htmlspecialchars($isoTimestamp) . '">' . $humanReadable . '</strong>';
            } catch (Exception $e) {
                return '<strong>' . $isoTimestamp . '</strong>';
            }
        },
        $entry
    );

    $patterns = [
        '/(exception|error|fatal|warning|deprecated)/i' => '<span style="color:#dc3545;font-weight:bold;">$1</span>',
        '/(\w+\.php):(\d+)/i' => '<span style="color:#17a2b8;">$1</span>:<span style="color:#fd7e14;font-weight:bold;">$2</span>',
        '/(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|JOIN|GROUP BY|ORDER BY|HAVING)(?=\s)/i' => '<span style="color:#6610f2;font-weight:bold;">$1</span>'
    ];

    foreach ($patterns as $pattern => $replacement) {
        $entry = preg_replace($pattern, $replacement, $entry);
    }

    return $entry;
}

function processPHPErrorLog($entries)
{
    $processedEntries = [];
    $currentEntry = '';
    $inStackTrace = false;

    foreach ($entries as $line) {
        if (preg_match('/^\[\d{2}-\w{3}-\d{4}/', $line)) {
            if (!empty($currentEntry)) {
                $processedEntries[] = $currentEntry;
                $currentEntry = '';
            }
            $inStackTrace = false;
            $currentEntry = $line;

            if (
                stripos($line, 'Fatal error') !== false ||
                stripos($line, 'Uncaught') !== false ||
                stripos($line, 'Exception') !== false
            ) {
                $inStackTrace = true;
            }
        }
        else {
            if (preg_match('/^#\d+/', $line)) {
                $currentEntry .= "\n" . $line;
                $inStackTrace = true;
            }
            else if ($inStackTrace || trim($line) !== '') {
                $currentEntry .= "\n" . $line;
            }
        }
    }

    if (!empty($currentEntry)) {
        $processedEntries[] = $currentEntry;
    }

    return $processedEntries;
}

function formatPhpErrorLogEntry($entry)
{
    $html = nl2br(htmlspecialchars($entry));

    $html = preg_replace('/\[(\d{2}-\w{3}-\d{4}\s\d{2}:\d{2}:\d{2}\s\w+)\]/', '<strong>[$1]</strong>', $html);
    $html = preg_replace('/(#\d+)/', '<span style="color:#e83e8c;font-weight:bold;">$1</span>', $html);
    $html = preg_replace('/(PHP (?:Fatal error|Warning|Notice|Deprecated):)/', '<span style="color:#dc3545;font-weight:bold;">$1</span>', $html);
    $html = preg_replace('/(thrown in)/', '<span style="color:#dc3545;">$1</span>', $html);
    $html = preg_replace('/in (\/[\w\/\.\-]+\.php)/', 'in <span style="color:#17a2b8;">$1</span>', $html);
    $html = preg_replace('/(on line |:)(\d+)/', '$1<span style="color:#fd7e14;font-weight:bold;">$2</span>', $html);

    return $html;
}

function createLogLine($content, $lineNumber, $logLevel)
{
    return '<div class="logLine log-' . $logLevel . '" data-linenumber="' . $lineNumber . '" data-level="' . $logLevel . '" onclick="copyToClipboard(this.innerHTML, ' . $lineNumber . ')">
        <span class="lineNumber">' . $lineNumber . '</span>' . $content . '</div>';
}

$actualLogDate = '';

if (file_exists($file)) {
    if ($logType === 'php_error') {
        $fileContent = file($file, FILE_IGNORE_NEW_LINES);
        $logEntries = processPHPErrorLog($fileContent);
        $logEntries = array_reverse($logEntries);

        if (!empty($searchTerm)) {
            $logEntries = array_filter($logEntries, function ($entry) use ($searchTerm) {
                return lineContainsAllSearchTerms($entry, $searchTerm);
            });
            $logEntries = array_values($logEntries);
        }

        $logEntries = array_slice($logEntries, $start, $linesPerPage);

        echo "<div class='log-header'>" . _translate("Viewing PHP Error Log") . "</div>";

        if (empty($logEntries)) {
            echo "<div class='logLine'>No more logs.</div>";
            exit();
        }

        foreach ($logEntries as $index => $entry) {
            $lineNumber = $start + $index + 1;
            $logLevel = 'error';
            $formattedEntry = formatPhpErrorLogEntry($entry);
            echo createLogLine($formattedEntry, $lineNumber, $logLevel);
        }

        if (count($logEntries) < $linesPerPage) {
            echo "<div class='logLine'>No more logs.</div>";
        }
    } else {
        $fileHandle = fopen($file, 'r');
        if ($fileHandle) {
            $fileSize = filesize($file);

            if ($fileSize > 10 * 1024 * 1024) {
                $matchingEntries = [];
                $lineCount = 0;
                $matchCount = 0;

                $lines = [];
                $pos = -2;
                $currentLine = '';
                $lastChar = '';

                while (abs($pos) < $fileSize) {
                    fseek($fileHandle, $pos, SEEK_END);
                    $char = fgetc($fileHandle);

                    if ($char === PHP_EOL && $lastChar === PHP_EOL) {
                        if (!empty($currentLine)) {
                            $lines[] = $currentLine;
                            $lineCount++;
                            $currentLine = '';

                            if ($lineCount - 1 >= $start && count($lines) >= $linesPerPage) {
                                break;
                            }
                        }
                    } elseif ($char === PHP_EOL) {
                        $currentLine = $char . $currentLine;
                    } else {
                        $currentLine = $char . $currentLine;
                    }

                    $lastChar = $char;
                    $pos--;

                    if (abs($pos) >= $fileSize) {
                        break;
                    }
                }

                if (!empty($currentLine)) {
                    $lines[] = $currentLine;
                }

                if (!empty($searchTerm)) {
                    $lines = array_filter($lines, function ($line) use ($searchTerm) {
                        return lineContainsAllSearchTerms($line, $searchTerm);
                    });
                    $lines = array_values($lines);
                }

                $logEntries = array_slice($lines, 0, $linesPerPage);
            } else {
                $fileContent = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $fileContent = array_reverse($fileContent);

                if (!empty($searchTerm)) {
                    $fileContent = array_filter($fileContent, function ($line) use ($searchTerm) {
                        return lineContainsAllSearchTerms($line, $searchTerm);
                    });
                    $fileContent = array_values($fileContent);
                }

                $logEntries = array_slice($fileContent, $start, $linesPerPage);
            }

            fclose($fileHandle);

            echo "<div class='log-header'>" . _translate("Viewing System Log for Date") . " - " . $_GET['date'] . "</div>";

            $actualLogDate = $_GET['date'] ?? date('d-M-Y');

            if (empty($logEntries)) {
                echo "<div class='logLine'>No more logs.</div>";
                exit();
            }

            foreach ($logEntries as $index => $entry) {
                $lineNumber = $start + $index + 1;
                $logLevel = detectLogLevel($entry);
                $entry = htmlspecialchars($entry);

                $lines = preg_split('/\\\\n|\\n|\n/', $entry);
                $formattedEntry = '';

                foreach ($lines as $i => $line) {
                    if (preg_match('/^#(\d+)/', $line, $matches)) {
                        $line = '<span style="color:#e83e8c;font-weight:bold;">#' . $matches[1] . '</span>' . substr($line, strlen($matches[0]));
                        $formattedEntry .= ($i > 0 ? '<br/>' : '') . $line;
                    } else {
                        $formattedEntry .= ($i > 0 ? '<br/>' : '') . $line;
                    }
                }

                $formattedEntry = formatApplicationLogEntry($formattedEntry);
                echo createLogLine($formattedEntry, $lineNumber, $logLevel);
            }

            if (count($logEntries) < $linesPerPage) {
                echo "<div class='logLine'>No more logs.</div>";
            }
        } else {
            echo "<div class='error'>" . _translate("Error opening log file.") . "</div>";
        }
    }
} else {
    if ($logType === 'application') {
        $recentFile = getMostRecentLogFile(ROOT_PATH . '/logs');

        if ($recentFile) {
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', basename($recentFile), $matches)) {
                $isoDate = $matches[1];
                $dateObj = new DateTime($isoDate);
                $actualLogDate = $dateObj->format('d-M-Y');
            }

            $fileContent = file($recentFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $fileContent = array_reverse($fileContent);

            if (!empty($searchTerm)) {
                $fileContent = array_filter($fileContent, function ($line) use ($searchTerm) {
                    return lineContainsAllSearchTerms($line, $searchTerm);
                });
                $fileContent = array_values($fileContent);
            }

            $logEntries = array_slice($fileContent, $start, $linesPerPage);

            echo "<div class='log-header'>" . _translate("No data found for the selected date") . " - " . ($_GET['date'] ?? date('d-M-Y')) . "<br>" .
                _translate("Showing the most recent log file") . " : " . basename($recentFile) . "</div>";

            if (empty($logEntries)) {
                echo "<div class='logLine'>No logs found.</div>";
                exit();
            }

            foreach ($logEntries as $index => $entry) {
                $lineNumber = $start + $index + 1;
                $logLevel = detectLogLevel($entry);
                $entry = htmlspecialchars($entry);

                $lines = preg_split('/\\\\n|\\n|\n/', $entry);
                $formattedEntry = '';

                foreach ($lines as $i => $line) {
                    if (preg_match('/^#(\d+)/', $line, $matches)) {
                        $line = '<span style="color:#e83e8c;font-weight:bold;">#' . $matches[1] . '</span>' . substr($line, strlen($matches[0]));
                        $formattedEntry .= ($i > 0 ? '<br/>' : '') . $line;
                    } else {
                        $formattedEntry .= ($i > 0 ? '<br/>' : '') . $line;
                    }
                }

                $formattedEntry = formatApplicationLogEntry($formattedEntry);
                echo createLogLine($formattedEntry, $lineNumber, $logLevel);
            }

            if (count($logEntries) < $linesPerPage) {
                echo "<div class='logLine'>No more logs.</div>";
            }
        } else {
            echo '<div class="logLine">No log files found.</div>';
        }
    } else {
        echo '<div class="logLine">No PHP error log found.</div>';
    }
}

if ($start === 0) {
    echo "<input type='hidden' id='actualLogDate' value='{$actualLogDate}'>";
}
