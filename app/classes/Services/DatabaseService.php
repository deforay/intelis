<?php

namespace App\Services;

use MysqliDb;
use Override;
use Exception;
use Generator;
use Throwable;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use PhpMyAdmin\SqlParser\Parser;
use App\Exceptions\SystemException;
use PhpMyAdmin\SqlParser\Components\Limit;
use PhpMyAdmin\SqlParser\Components\Expression;

final class DatabaseService extends MysqliDb
{

    private bool $isTransactionActive = false;
    private $useSavepoints = false;
    private string $sessionCollation = 'utf8mb4_unicode_ci';
    private string $sessionCharset = 'utf8mb4';
    private int $countQueryMaxExecutionMs = 10000;
    private array $parserStatementCache = [];

    public function __construct($host = null, $username = null, $password = null, $db = null, $port = null, $charset = 'utf8mb4')
    {
        // allow array config
        if (is_array($host)) {
            $cfg = $host;
            $host = $cfg['host'] ?? null;
            $username = $cfg['username'] ?? null;
            $password = $cfg['password'] ?? null;
            $db = $cfg['db'] ?? null;
            $port = $cfg['port'] ?? null;
            $charset = $cfg['charset'] ?? 'utf8mb4';
        }

        // persistent connection
        if ($host && is_string($host) && !str_starts_with($host, 'p:')) {
            $host = "p:$host";
        }

        parent::__construct($host, $username, $password, $db, $port, $charset);

        $this->sessionCharset = $charset ?: 'utf8mb4';

        // Ensure charset on the mysqli handle
        mysqli_set_charset($this->mysqli(), $this->sessionCharset);

        // Prefer the current database's default collation
        $rowDb = $this->rawQueryOne("SHOW VARIABLES LIKE 'collation_database'");
        $collation = $rowDb['Value'] ?? null;

        // Fallback to server default if needed
        if (!$collation) {
            $rowSrv = $this->rawQueryOne("SHOW VARIABLES LIKE 'collation_server'");
            $collation = $rowSrv['Value'] ?? null;
        }

        // Final fallback for very old installs
        $this->sessionCollation = $collation ?: 'utf8mb4_unicode_ci';

        $this->applySessionSettings();
    }

    public function getMySQLVersion(): string
    {
        return $this->mysqli()->server_info; // human-readable, e.g. "8.0.37-0ubuntu0.22.04.3"
    }

    public function getMySQLVersionId(): int
    {
        return $this->mysqli()->server_version; // e.g. 80037
    }

    public function isMySQL8OrHigher(): bool
    {
        return $this->getMySQLVersionId() >= 80000;
    }

    public function setCountQueryMaxExecutionTime(?int $milliseconds): void
    {
        if ($milliseconds === null || $milliseconds < 0) {
            $this->countQueryMaxExecutionMs = 0;
            return;
        }
        $this->countQueryMaxExecutionMs = $milliseconds;
    }


    /**
     * Destructor.
     * Automatically commits the transaction if it's still active.
     */
    public function __destruct()
    {
        $this->commitTransaction();
    }

    public function isConnected($connectionName = null): bool
    {
        if ($connectionName === null) {
            $connectionName = $this->defConnectionName ?? 'default';
        }

        try {
            $this->connect($connectionName);
            return true;
        } catch (Throwable $e) {
            LoggerUtility::logError($e->getMessage());
            return false;
        }
    }

    public function isTransactionActive(): bool
    {
        return $this->isTransactionActive;
    }


    /**
     * Execute a query and return a generator to fetch results row by row.
     *
     * @param string $query SQL query string
     * @param array|null $bindParams Parameters to bind to the query
     * @return Generator
     */
    public function rawQueryGenerator(?string $query, $bindParams = null)
    {
        if ($query === null || $query === '' || $query === '0' || $query === '') {
            return yield from [];
        }
        $this->_query = $query;
        $stmt = $this->_prepareQuery();

        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $this->mysqli()->error);
        }

        // parameter binding
        if (is_array($bindParams) && $bindParams !== []) {
            $types = '';
            $values = [];

            foreach ($bindParams as $val) {
                $types .= $this->_determineType($val);
                $values[] = $val;
            }

            // Use reference binding
            $bindReferences = array_merge([&$types], $this->createReferences($values));
            call_user_func_array($stmt->bind_param(...), $bindReferences);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false) { // Only false indicates failure
            $stmt->close();
            $this->reset();
            LoggerUtility::logError('DB Result Error: ' . $this->mysqli()->error);
            throw new Exception("Failed to get result: " . $this->mysqli()->error);
        }

        // Fetch results row by row
        while ($row = $result->fetch_assoc()) {
            yield $row;
        }

        // These should always be called, even for empty result sets
        $result->free();
        $stmt->close();
        $this->reset();
    }

    /**
     * Create references for bind_param
     *
     * @param array $values
     * @return array
     */
    private function createReferences(array $values): array
    {
        $references = [];
        foreach (array_keys($values) as $key) {
            $references[$key] = &$values[$key];
        }
        return $references;
    }

    private function applySessionSettings(): void
    {
        try {
            mysqli_set_charset($this->mysqli(), $this->sessionCharset);
        } catch (Throwable $e) {
            LoggerUtility::logWarning('Failed to set mysqli charset: ' . $e->getMessage());
        }

        $charset = $this->sessionCharset ?: 'utf8mb4';
        $collation = $this->sessionCollation ?: 'utf8mb4_unicode_ci';

        try {
            $this->rawQuery("SET NAMES {$charset} COLLATE {$collation}");
            $this->rawQuery("SET collation_connection = '{$collation}'");
        } catch (Throwable $e) {
            LoggerUtility::logWarning('Failed to apply session collation settings: ' . $e->getMessage());
        }
    }

    public function ensureConnection(): void
    {
        $needsReconnect = false;

        try {
            $needsReconnect = !$this->ping();
        } catch (Throwable $e) {
            $needsReconnect = true;
            LoggerUtility::logWarning('Database ping failed: ' . $e->getMessage());
        }

        if ($needsReconnect) {
            $this->reconnect();
        }
    }

    private function reconnect(): void
    {
        try {
            $this->disconnectAll();
        } catch (Throwable $e) {
            LoggerUtility::logWarning('Failed to disconnect database connection cleanly: ' . $e->getMessage());
        }

        $connectionName = $this->defConnectionName ?? 'default';

        try {
            $this->connect($connectionName);
            $this->isTransactionActive = false;
            $this->useSavepoints = false;
            $this->applySessionSettings();
        } catch (Throwable $e) {
            LoggerUtility::logError('Database reconnect attempt failed: ' . $e->getMessage());
            throw new SystemException('Unable to reconnect to the database', 500, $e);
        }
    }

    /**
     * Set the transaction isolation level to READ COMMITTED.
     */
    private function setTransactionIsolationLevel($level = 'READ COMMITTED'): void
    {
        $validLevels = ['READ UNCOMMITTED', 'READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'];
        if (!in_array($level, $validLevels)) {
            $level = 'READ COMMITTED';
        }

        $this->rawQuery("SET TRANSACTION ISOLATION LEVEL $level;");
    }


    /**
     * Begin a new transaction if not already started, with read-only optimization.
     */
    public function beginReadOnlyTransaction($level = 'READ COMMITTED'): void
    {
        if (!$this->isTransactionActive) {
            $this->setTransactionIsolationLevel($level);
            $this->startTransaction();
            $this->isTransactionActive = true;
        }
    }

    /**
     * Begin a new transaction.
     * Optionally use savepoints if supported and requested.
     *
     * @param bool $useSavepoints Whether to use savepoints within the transaction.
     */
    public function beginTransaction($useSavepoints = false): void
    {
        if (!$this->isTransactionActive) {
            $this->startTransaction();
            $this->isTransactionActive = true;
            // Enable savepoints only if MySQL 8 or higher and requested.
            $this->useSavepoints = $this->isMySQL8OrHigher() ? $useSavepoints : false;
        }
    }


    public function commitTransaction(): void
    {
        if ($this->isTransactionActive) {
            $this->commit();
            $this->isTransactionActive = false;
        }
    }


    /**
     * Roll back the current transaction.
     * * @param string|null $toSavepoint The savepoint to rollback to, or null to rollback the entire transaction.
     */
    public function rollbackTransaction($toSavepoint = null): void
    {
        if ($this->isTransactionActive) {
            if ($toSavepoint && $this->useSavepoints) {
                $this->rollbackToSavepoint($toSavepoint);
            } else {
                $this->rollback();
            }
            $this->isTransactionActive = false;
        }
    }

    public function createSavepoint($savepointName): void
    {
        $this->rawQuery("SAVEPOINT `$savepointName`;");
    }

    public function rollbackToSavepoint($savepointName): void
    {
        $this->rawQuery("ROLLBACK TO SAVEPOINT `$savepointName`;");
    }

    public function releaseSavepoint($savepointName): void
    {
        $this->rawQuery("RELEASE SAVEPOINT `$savepointName`;");
    }


    /**
     * Dynamically fetch primary key columns for a table.
     *
     * @param string $tableName The name of the table.
     * @return array Array of primary key column names.
     */
    public function getPrimaryKeys($tableName): array
    {
        $sql = "SHOW KEYS FROM `$tableName` WHERE Key_name = 'PRIMARY'";
        $result = $this->mysqli()->query($sql);
        $primaryKeys = [];
        while ($row = $result->fetch_assoc()) {
            $primaryKeys[] = $row['Column_name'];
        }
        return $primaryKeys;
    }


    /**
     * Insert on duplicate key update (upsert) a row into a table.
     *
     * @param string $tableName The name of the table to operate on.
     * @param array  $tableData Associative array of data to insert (column => value).
     * @param array  $updateColumns Array of columns to be updated on duplicate key, excluding primary key components.
     * @param array|string  $primaryKeys String or Array of primary key column names.
     * @return bool Returns true on success or false on failure.
     */
    public function upsert($tableName, array $tableData, array $updateColumns = [], $primaryKeys = []): bool
    {
        $this->reset();
        $keys = array_keys($tableData);
        $placeholders = array_fill(0, count($tableData), '?');
        $values = array_values($tableData);

        $primaryKeys = $primaryKeys ?: $this->getPrimaryKeys($tableName);
        $primaryKeys = is_array($primaryKeys) ? $primaryKeys : [$primaryKeys];

        $sql = "INSERT INTO `$tableName` (`" . implode('`, `', $keys) . "`) VALUES (" . implode(', ', $placeholders) . ")";

        if ($updateColumns === []) {
            $updateColumns = array_diff($keys, $primaryKeys);  // Default to using all data keys except primary keys
        }

        $updateParts = [];
        $updateValues = [];
        foreach ($updateColumns as $key => $column) {
            if (is_numeric($key)) {
                // Indexed array, use VALUES() to refer to the value attempted to insert
                if (in_array($column, $keys) && !in_array($column, $primaryKeys)) {
                    $updateParts[] = "`$column` = VALUES(`$column`)";
                }
            } elseif (!in_array($key, $primaryKeys)) {
                // Associative array, direct assignment from updateColumns
                $updateParts[] = "`$key` = ?";
                $updateValues[] = $column;
                // Assuming column is the value to update
            }
        }

        if ($updateParts !== []) {
            $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
        }

        $stmt = $this->mysqli()->prepare($sql);
        if (!$stmt) {
            LoggerUtility::logError("Unable to prepare statement: " . $this->mysqli()->error . ':' . $this->mysqli()->errno);
        }

        $allValues = array_merge($values, $updateValues);
        $types = str_repeat('s', count($allValues));
        $stmt->bind_param($types, ...$allValues);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $error = $stmt->error;
            $stmt->close();
            LoggerUtility::logError("Failed to execute upsert: $error");
            return false;
        }
    }

    private const int COUNT_CACHE_TTL = 30;

    public function getDataAndCount(string $sql, ?array $params = null, ?int $limit = null, ?int $offset = null, bool $returnGenerator = true): array
    {
        try {
            $trimmed = $this->sanitizeSqlForSelect($sql);

            if (!preg_match('/\A(SELECT|WITH)\b/i', $trimmed)) {
                throw new SystemException('Only SELECT statements are supported in getDataAndCount');
            }

            $limitOffsetSet = isset($limit) && isset($offset);
            [$querySql, $statementForParsing, $appliedLimitToQuery] = $this->prepareQuerySql(
                $sql,
                $limit,
                $offset,
                $limitOffsetSet,
                $returnGenerator
            );

            $queryResult = $returnGenerator
                ? $this->rawQueryGenerator($querySql, $params)
                : $this->rawQuery($querySql, $params);

            $count = $this->resolveCount(
                $sql,
                $params,
                $statementForParsing,
                $limit,
                $offset,
                $returnGenerator,
                $queryResult,
                $appliedLimitToQuery,
                $limitOffsetSet
            );

            return [$queryResult, max((int) $count, 0)];
        } catch (Throwable $e) {
            throw new SystemException('Query Execution Failed. SQL: ' . substr($sql, 0, 500) . ' | Error: ' . $e->getMessage(), 500, $e);
        }
    }

    private function sanitizeSqlForSelect(string $sql): string
    {
        $trimmed = preg_replace('/(?s)\/\*.*?\*\/|--.*?(?=\n|$)|#.*/', '', $sql);
        $trimmed = ltrim((string) $trimmed);
        return ltrim($trimmed, '(');
    }

    /**
     * @return array{0:string,1:?object,2:bool}
     */
    private function prepareQuerySql(
        string $sql,
        ?int $limit,
        ?int $offset,
        bool $limitOffsetSet,
        bool $returnGenerator
    ): array {
        $statementForParsing = null;
        $appliedLimitToQuery = false;
        $querySql = $sql;

        if ($limitOffsetSet || $returnGenerator) {
            $statementForParsing = $this->getParsedStatement($sql);
        }

        if ($limitOffsetSet && $statementForParsing !== null) {
            $statementForQuery = clone $statementForParsing;
            $hasLimit = isset($statementForQuery->limit) &&
                $statementForQuery->limit !== null &&
                !empty($statementForQuery->limit);

            if (!$hasLimit) {
                $statementForQuery->limit = new Limit($limit, $offset);
                $querySql = $statementForQuery->build();
                $appliedLimitToQuery = true;
            }
        }

        return [$querySql, $statementForParsing, $appliedLimitToQuery];
    }

    private function getParsedStatement(string $sql): ?object
    {
        $cacheKey = hash('sha256', $sql);

        if (array_key_exists($cacheKey, $this->parserStatementCache)) {
            return $this->parserStatementCache[$cacheKey];
        }

        try {
            $parser = new Parser($sql);
            $statementForParsing = $parser->statements[0] ?? null;
        } catch (Throwable $parseException) {
            LoggerUtility::log('warning', 'Failed to parse SQL for data/count batching: ' . $parseException->getMessage());
            $statementForParsing = null;
        }

        $this->parserStatementCache[$cacheKey] = $statementForParsing;

        return $statementForParsing;
    }

    private function resolveCount(
        string $sql,
        ?array $params,
        ?object $statementForParsing,
        ?int $limit,
        ?int $offset,
        bool $returnGenerator,
        $queryResult,
        bool $appliedLimitToQuery,
        bool $limitOffsetSet
    ): int {
        $count = 0;
        $countResolved = false;

        if ($limitOffsetSet && $appliedLimitToQuery && $returnGenerator === false && is_array($queryResult)) {
            $fetchedRows = count($queryResult);

            if ($fetchedRows < (int) $limit) {
                $count = (int) $offset + $fetchedRows;
                $countResolved = true;
            }
        }

        if ($countResolved) {
            return $count;
        }

        $countQuerySessionKey = hash('sha256', $sql . json_encode($params));
        $now = time();

        if (
            CommonService::isSessionActive() &&
            isset($_SESSION['queryCounters'][$countQuerySessionKey]['count']) &&
            isset($_SESSION['queryCounters'][$countQuerySessionKey]['timestamp']) &&
            ($now - (int) $_SESSION['queryCounters'][$countQuerySessionKey]['timestamp']) < self::COUNT_CACHE_TTL
        ) {
            return (int) $_SESSION['queryCounters'][$countQuerySessionKey]['count'];
        }

        $originalIsolationLevel = $this->getSessionIsolationLevel();
        $downgradedIsolation = false;

        if ($originalIsolationLevel !== null && $originalIsolationLevel !== 'READ COMMITTED') {
            $downgradedIsolation = $this->setSessionIsolationLevelSafe('READ COMMITTED');
        }

        $useMaxExecutionHint = $this->countQueryMaxExecutionMs > 0;
        $updateCachedCount = function (int $value) use (&$countResolved, $countQuerySessionKey, $now): void {
            $countResolved = true;
            if (CommonService::isSessionActive()) {
                if (!isset($_SESSION['queryCounters']) || !is_array($_SESSION['queryCounters'])) {
                    $_SESSION['queryCounters'] = [];
                }
                $_SESSION['queryCounters'][$countQuerySessionKey] = [
                    'count' => $value,
                    'timestamp' => $now
                ];
            }
        };

        try {
            $countSql = $this->buildCountSql($sql, $statementForParsing, $useMaxExecutionHint);
            $countResult = $this->rawQueryOne($countSql, $params);
            $count = (int) ($countResult['totalCount'] ?? 0);
            $updateCachedCount($count);
        } catch (Throwable $countException) {
            $message = $countException->getMessage();
            $maxExecutionExceeded = stripos($message, 'maximum statement execution time exceeded') !== false;

            if ($useMaxExecutionHint && $maxExecutionExceeded) {
                try {
                    $countSql = $this->buildCountSql($sql, $statementForParsing, false);
                    $countResult = $this->rawQueryOne($countSql, $params);
                    $count = (int) ($countResult['totalCount'] ?? 0);
                    $updateCachedCount($count);
                    LoggerUtility::log('warning', 'Count query exceeded MAX_EXECUTION_TIME hint; retried without limit.');
                } catch (Throwable $retryException) {
                    $countException = $retryException;
                }
            }

            if (!$countResolved) {
                if (
                    CommonService::isSessionActive() &&
                    isset($_SESSION['queryCounters'][$countQuerySessionKey]['count'])
                ) {
                    $count = (int) $_SESSION['queryCounters'][$countQuerySessionKey]['count'];
                    LoggerUtility::log('warning', 'Count query timed out, using cached value: ' . $countException->getMessage());
                    $countResolved = true;
                } else {
                    LoggerUtility::logError('Count query failed with no cache: ' . $countException->getMessage());
                }
            }
        } finally {
            if ($downgradedIsolation && $originalIsolationLevel !== null) {
                $this->setSessionIsolationLevelSafe($originalIsolationLevel);
            }
        }

        return $count;
    }

    private function buildCountSql(string $sql, ?object $statementForParsing = null, bool $useMaxExecutionHint = true): string
    {
        $includeHint = $useMaxExecutionHint && $this->countQueryMaxExecutionMs > 0;
        $countExpression = $includeHint
            ? sprintf('/*+ MAX_EXECUTION_TIME(%d) */ COUNT(*) AS totalCount', $this->countQueryMaxExecutionMs)
            : 'COUNT(*) AS totalCount';

        try {
            if ($statementForParsing === null) {
                $parser = new Parser($sql);
                $statementForParsing = $parser->statements[0] ?? null;
            }

            if ($statementForParsing !== null) {
                $originalStatement = clone $statementForParsing;
                $statementForCount = clone $originalStatement;
                $statementForCount->limit = null;
                $statementForCount->order = null;

                if (!empty($originalStatement->group)) {
                    $innerSql = $statementForCount->build();
                    return sprintf('SELECT %s FROM (%s) AS subquery', $countExpression, $innerSql);
                }

                $statementForCount->expr = [new Expression($countExpression)];
                return $statementForCount->build();
            }
        } catch (Throwable $parseException) {
            LoggerUtility::log('warning', 'Unable to rebuild count SQL using parser: ' . $parseException->getMessage());
        }

        $innerSql = rtrim($sql, ";\t\n\r\0\x0B ");
        return sprintf('SELECT %s FROM (%s) AS subquery', $countExpression, $innerSql);
    }

    private function getSessionIsolationLevel(): ?string
    {
        $probes = [
            'SELECT @@session.transaction_isolation AS isolation',
            'SELECT @@session.tx_isolation AS isolation'
        ];
        $errors = [];

        foreach ($probes as $probeSql) {
            try {
                $result = $this->rawQueryOne($probeSql);
                if (!empty($result['isolation'])) {
                    return $this->normalizeIsolationLevel($result['isolation']);
                }
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            LoggerUtility::log('warning', 'Isolation level probe failed: ' . implode(' | ', $errors));
        }

        return null;
    }

    private function setSessionIsolationLevelSafe(string $level): bool
    {
        $normalizedLevel = $this->normalizeIsolationLevel($level);

        if ($normalizedLevel === null) {
            return false;
        }

        try {
            $this->rawQuery("SET SESSION TRANSACTION ISOLATION LEVEL $normalizedLevel");
            return true;
        } catch (Throwable $e) {
            LoggerUtility::log('warning', 'Failed to set isolation level: ' . $e->getMessage());
            return false;
        }
    }

    private function normalizeIsolationLevel(?string $level): ?string
    {
        if ($level === null) {
            return null;
        }

        $normalized = strtoupper(trim($level));
        $normalized = str_replace('-', ' ', $normalized);

        $validLevels = [
            'READ UNCOMMITTED',
            'READ COMMITTED',
            'REPEATABLE READ',
            'SERIALIZABLE'
        ];

        return in_array($normalized, $validLevels, true) ? $normalized : null;
    }


    #[Override]
    public function reset(): void
    {
        parent::reset();
    }


    /**
     * Insert multiple rows into a table in a single query with configurable insert options.
     *
     * @param string $tableName The name of the table to insert into.
     * @param array $data An array of associative arrays representing the rows to insert.
     * @param string $insertType The type of insert operation: 'ignore' for INSERT IGNORE, 'upsert' for INSERT ON DUPLICATE KEY UPDATE, and 'insert' for standard INSERT.
     * @param array $updateColumns Columns to update in case of a duplicate key (only used for 'upsert').
     * @return bool Returns true on success or false on failure.
     */
    public function insertMultipleRows(string $tableName, array $data, string $insertType = 'insert', array $updateColumns = []): bool
    {
        if ($data === []) {
            return false;
        }

        $keys = array_keys($data[0]);
        $columns = implode('`, `', $keys);
        $values = [];
        $placeholders = array_fill(0, count($keys), '?');
        $placeholderString = '(' . implode(', ', $placeholders) . ')';

        foreach ($data as $row) {
            $values = array_merge($values, array_values($row));
        }

        $placeholdersString = implode(', ', array_fill(0, count($data), $placeholderString));

        $sql = '';
        if ($insertType === 'ignore') {
            $sql = "INSERT IGNORE INTO `$tableName` (`$columns`) VALUES $placeholdersString";
        } elseif ($insertType === 'upsert') {
            $updatePart = implode(', ', array_map(fn($col): string => "`$col` = VALUES(`$col`)", $updateColumns));
            $sql = "INSERT INTO `$tableName` (`$columns`) VALUES $placeholdersString ON DUPLICATE KEY UPDATE $updatePart";
        } else {
            $sql = "INSERT INTO `$tableName` (`$columns`) VALUES $placeholdersString";
        }

        // Log the SQL string for testing purposes
        //LoggerUtility::log('info', "Generated SQL: $sql");

        $stmt = $this->mysqli()->prepare($sql);
        if (!$stmt) {
            LoggerUtility::logError("Unable to prepare statement: " . $this->mysqli()->error);
            return false;
        }

        $types = $this->determineTypes($values);
        $stmt->bind_param($types, ...$values);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $error = $stmt->error;
            $stmt->close();
            LoggerUtility::logError("Failed to execute insertMultipleRows: $error");
            return false;
        }
    }

    /**
     * Determine the types of the values for bind_param.
     *
     * @param array $values The values to determine types for.
     * @return string The types string.
     */
    private function determineTypes(array $values): string
    {
        $types = '';
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 'b'; // 'b' for blob and other types
            }
        }
        return $types;
    }

    public function getTableFieldsAsArray(string $tableName, array $unwantedColumns = []): array
    {
        $tableFieldsAsArray = [];
        if ($tableName !== '' && $tableName !== '0' && $tableName !== '') {
            try {

                $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = ? AND table_name= ?";
                $allColResult = $this->rawQuery($allColumns, [SYSTEM_CONFIG['database']['db'], $tableName]);
                $columnNames = array_column($allColResult, 'COLUMN_NAME');

                // Create an array with all column names set to null
                $tableFieldsAsArray = array_fill_keys($columnNames, null);
                if ($unwantedColumns !== []) {
                    $tableFieldsAsArray = MiscUtility::excludeKeys($tableFieldsAsArray, $unwantedColumns);
                }
            } catch (Throwable $e) {
                throw new SystemException($e->getMessage(), 500, $e);
            }
        }

        return $tableFieldsAsArray;
    }
}
