<?php

namespace App\Services;

use Gettext\Loader\MoLoader;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;

final class SystemService
{
    protected CommonService $commonService;
    private string $defaultLocale = 'en_US';
    private static ?\PDO $systemAlertSqlite = null;
    private const SYSTEM_ALERT_SQLITE_FILE = CACHE_PATH . '/system_alerts.sqlite';


    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }

    // Application Bootstrap
    public function bootstrap(): SystemService
    {
        $this->setLocale();
        $this->setDateTimeZone();

        return $this;
    }

    // Setup Translation
    public function setLocale($locale = null, $domain = "messages"): void
    {
        // Determine the locale to use
        $_SESSION['APP_LOCALE'] = $locale ?? $_SESSION['userLocale'] ?? $_SESSION['APP_LOCALE'] ?? $this->commonService->getGlobalConfig('app_locale') ?? $this->defaultLocale;



        // Construct the path to the .mo file
        $moFilePath = sprintf(
            '%s%slocales%s%s%sLC_MESSAGES%s%s.mo',
            APPLICATION_PATH,
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            $_SESSION['APP_LOCALE'],
            DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            $domain
        );

        // Initialize translations to null
        $_SESSION['translations'] = null;

        // Load translations if the locale is not the default and the .mo file exists
        if ($_SESSION['APP_LOCALE'] !== $this->defaultLocale && file_exists($moFilePath)) {
            $loader = new MoLoader();
            $translations = $loader->loadFile($moFilePath);

            // Store translations in the session
            $_SESSION['translations'] = $translations;
        }
    }

    public static function translate(?string $text)
    {
        if (empty($text) || empty($_SESSION['translations']) || empty($_SESSION['translations']->find(null, $text))) {
            return $text;
        } else {
            return $_SESSION['translations']->find(null, $text)->getTranslation();
        }
    }

    public function getDateFormat($category = null, $inputFormat = null)
    {
        $dateFormat = $inputFormat ?? $this->commonService->getGlobalConfig('gui_date_format') ?? 'd-M-Y';

        $dateFormatArray = ['phpDateFormat' => $dateFormat];

        if ($dateFormat == 'd-m-Y') {
            $dateFormatArray['jsDateFieldFormat'] = 'dd-mm-yy';
            $dateFormatArray['dayjsDateFieldFormat'] = 'DD-MM-YYYY';
            $dateFormatArray['jsDateRangeFormat'] = 'DD-MM-YYYY';
            $dateFormatArray['jsDateFormatMask'] = '99-99-9999';
            $dateFormatArray['mysqlDateFormat'] = '%d-%m-%Y';
        } else {
            $dateFormatArray['jsDateFieldFormat'] = 'dd-M-yy';
            $dateFormatArray['dayjsDateFieldFormat'] = 'DD-MMM-YYYY';
            $dateFormatArray['jsDateRangeFormat'] = 'DD-MMM-YYYY';
            $dateFormatArray['jsDateFormatMask'] = '99-aaa-9999';
            $dateFormatArray['mysqlDateFormat'] = '%d-%b-%Y';
        }

        if (empty($category)) {
            // Return all date formats
            return $dateFormatArray;
        } elseif ($category == 'php') {
            return $dateFormatArray['phpDateFormat'] ?? 'd-m-Y';
        } elseif ($category == 'js') {
            return $dateFormatArray['jsDateFieldFormat'] ?? 'dd-mm-yy';
        } elseif ($category == 'dayjs') {
            return $dateFormatArray['dayjsDateFieldFormat'] ?? 'DD-MM-YYYY';
        } elseif ($category == 'jsDateRange') {
            return $dateFormatArray['jsDateRangeFormat'] ?? 'DD-MM-YYYY';
        } elseif ($category == 'jsMask') {
            return $dateFormatArray['jsDateFormatMask'] ?? '99-99-9999';
        } elseif ($category == 'mysql') {
            return $dateFormatArray['mysqlDateFormat'] ?? '%d-%b-%Y';
        } else {
            return null;
        }
    }



    public function setGlobalDateFormat($inputFormat = null)
    {
        $dateFormatArray = $this->getDateFormat(null, $inputFormat);
        foreach ($dateFormatArray as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }

    // Setup Timezone
    public function setDateTimeZone(): void
    {
        $this->setGlobalDateFormat();

        $_SESSION['APP_TIMEZONE'] = $_SESSION['APP_TIMEZONE'] ?? $this->getTimezone();
        date_default_timezone_set($_SESSION['APP_TIMEZONE']);
    }

    public function getTimezone(): string
    {
        return  $this->commonService->getGlobalConfig('default_time_zone') ?? 'UTC';
    }

    // Setup debugging
    public function debug($debugMode = false): SystemService
    {
        if ($debugMode) {
            error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
            ini_set('display_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
        }
        return $this;
    }


    public static function getActiveModules(bool $onlyTests = false): array
    {
        $activeModules = [];

        if ($onlyTests === false) {
            $activeModules = ['admin', 'dashboard', 'common'];
        }
        return array_merge($activeModules, array_keys(array_filter(SYSTEM_CONFIG['modules'])));
    }

    public function getServerSettings(): array
    {
        return [
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => ini_get('error_reporting'),
        ];
    }
    public function checkFolderPermissions(): array
    {
        // Define folder paths
        $folders = [
            'CACHE_PATH' => CACHE_PATH,
            'UPLOAD_PATH' => UPLOAD_PATH,
            'TEMP_PATH' => TEMP_PATH,
            'LOGS_PATH' => ROOT_PATH . DIRECTORY_SEPARATOR . 'logs'
        ];

        $folderPermissions = [];

        foreach ($folders as $folderName => $folderPath) {
            $folderPermissions[$folderName] = [
                'exists' => is_dir($folderPath),
                'readable' => is_readable($folderPath),
                'writable' => is_writable($folderPath)
            ];
        }

        return $folderPermissions;
    }

    public static function isAlertStoreAvailable(bool $log = false, int $logEverySec = 600): bool
    {
        static $lastLogAt = 0;

        try {
            // Try to init/connect; will also auto-create file/schema.
            self::systemAlertSqlite();
            return true;
        } catch (\Throwable $e) {
            if ($log) {
                $now = time();
                if ($now - $lastLogAt >= $logEverySec) {
                    LoggerUtility::log('warning', 'Alerts store unavailable: ' . $e->getMessage(), [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                    $lastLogAt = $now;
                }
            }
            return false;
        }
    }

    private static function systemAlertSqlite(): \PDO
    {
        if (self::$systemAlertSqlite instanceof \PDO) {
            return self::$systemAlertSqlite;
        }

        if (!is_dir(CACHE_PATH)) {
            @mkdir(CACHE_PATH, 0775, true);
        }
        if (!is_writable(CACHE_PATH)) {
            LoggerUtility::logError('CACHE_PATH not writable', ['path' => CACHE_PATH]);
            throw new \RuntimeException('CACHE_PATH not writable: ' . CACHE_PATH);
        }

        // Ensure driver present (clearer error than PDO)
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            LoggerUtility::logError('pdo_sqlite driver not available');
            throw new \RuntimeException('pdo_sqlite driver not available');
        }

        $dbFile = self::SYSTEM_ALERT_SQLITE_FILE;
        try {
            $pdo = new \PDO('sqlite:' . $dbFile, null, null, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT            => 3,
            ]);

            // If we just created it, set perms so web user can RW
            if (!file_exists($dbFile)) {
                // touching first will create via PDO anyway; still set perms
                @chmod($dbFile, 0664);
            }

            $pdo->exec("PRAGMA journal_mode = WAL");
            $pdo->exec("PRAGMA synchronous = NORMAL");
            $pdo->exec("PRAGMA busy_timeout = 3000");

            $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_alerts (
              id         INTEGER PRIMARY KEY AUTOINCREMENT,
              level      TEXT NOT NULL CHECK(level IN ('info','warn','error','critical')),
              type       TEXT NOT NULL,
              message    TEXT NOT NULL,
              meta       TEXT NULL,
              audience   TEXT NOT NULL DEFAULT 'admin' CHECK(audience IN ('auth','admin')),
              created_at TEXT NOT NULL DEFAULT (datetime('now'))
            );
        ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_system_alerts_audience_id ON system_alerts(audience, id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_system_alerts_type_created ON system_alerts(type, created_at)");

            self::$systemAlertSqlite = $pdo;
            return $pdo;
        } catch (\Throwable $e) {
            \App\Utilities\LoggerUtility::logError('SQLite init failed: ' . $e->getMessage(), [
                'path' => $dbFile,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }


    public static function insertSystemAlert(
        string $level,
        string $type,
        string $message,
        ?array $meta = null,
        string $audience = 'admin'
    ): ?int {
        try {
            $pdo = self::systemAlertSqlite(); // ensures DB + schema
            $stmt = $pdo->prepare(
                'INSERT INTO system_alerts (level, type, message, meta, audience)
             VALUES (:level, :type, :message, :meta, :audience)'
            );
            $stmt->execute([
                ':level'    => $level,
                ':type'     => $type,
                ':message'  => $message,
                ':meta'     => $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES) : null,
                ':audience' => $audience,
            ]);
            return (int)$pdo->lastInsertId();
        } catch (\Throwable $e) {
            // If SQLite cannot be used, we return null.
            return null;
        }
    }

    public static function systemAlertSqliteMaxId(): int
    {
        $pdo = self::systemAlertSqlite();
        return (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM system_alerts")->fetchColumn();
    }


    /** Read since id with audience filter (for SSE) */
    public static function systemAlertSqliteReadSinceId(int $lastId, array $audiences): array
    {
        $pdo = self::systemAlertSqlite();
        $marks = implode(',', array_fill(0, count($audiences), '?'));
        $sql = "SELECT id, level, type, message, meta, created_at, audience
                FROM system_alerts
                    WHERE id > ?
                    AND audience IN ($marks)
                    ORDER BY id ASC";
        $stmt = $pdo->prepare($sql);
        $params = array_merge([$lastId], $audiences);
        $stmt->execute($params);

        $rows = [];
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $rows[] = [
                'id'      => (int)$r['id'],
                'level'   => $r['level'],
                'type'    => $r['type'],
                'message' => $r['message'],
                'meta'    => isset($r['meta']) ? json_decode($r['meta'], true) : null,
                'ts'      => $r['created_at'],
                'audience' => $r['audience'],
            ];
        }
        return $rows;
    }
}

