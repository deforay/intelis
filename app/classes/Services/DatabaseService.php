<?php

namespace App\Services;

use MysqliDb;
use Generator;
use Throwable;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use PhpMyAdmin\SqlParser\Parser;
use App\Exceptions\SystemException;
use PhpMyAdmin\SqlParser\Components\Limit;
use PhpMyAdmin\SqlParser\Components\Expression;

final class DatabaseService extends MysqliDb
{

    private $isTransactionActive = false;
    private $useSavepoints = false;

    public function __construct($host = null, $username = null, $password = null, $db = null, $port = null, $charset = 'utf8mb4')
    {
        // Handle the case where an array of config is passed instead of individual parameters
        if (is_array($host)) {
            $config = $host;
            $host = $config['host'] ?? null;
            $username = $config['username'] ?? null;
            $password = $config['password'] ?? null;
            $db = $config['db'] ?? null;
            $port = $config['port'] ?? null;
            $charset = $config['charset'] ?? 'utf8mb4';
        }

        // Now add 'p:' prefix to the hostname for persistent connections
        if ($host && is_string($host) && strpos($host, 'p:') !== 0) {
            $host = "p:$host";
        }

        parent::__construct($host, $username, $password, $db, $port, $charset);
    }

    public function isMySQL8OrHigher(): bool
    {
        $version = $this->mysqli()->server_version;
        return $version >= 80000; // MySQL versions are expressed in the form of main_version * 10000 + minor_version * 100 + sub_version for example 8.0.21 is 80021
    }


    /**
     * Destructor.
     * Automatically commits the transaction if it's still active.
     */
    public function __destruct()
    {
        $this->commitTransaction();
    }

    public function isConnected($connectionName = null)
    {
        if ($connectionName === null) {
            $connectionName = $this->defConnectionName ?? 'default';
        }

        try {
            $this->connect($connectionName);
            return true;
        } catch (Throwable $e) {
            LoggerUtility::log('error', $e->getMessage());
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
        if(empty($query) || $query === '') {
            return yield from [];
        }
        $this->_query = $query;
        $stmt = $this->_prepareQuery();

        if (!$stmt) {
            throw new \Exception("Failed to prepare statement: " . $this->mysqli()->error);
        }

        // parameter binding
        if (is_array($bindParams) && !empty($bindParams)) {
            $types = '';
            $values = [];

            foreach ($bindParams as $val) {
                $types .= $this->_determineType($val);
                $values[] = $val;
            }

            // Use reference binding
            $bindReferences = array_merge([&$types], $this->createReferences($values));
            call_user_func_array([$stmt, 'bind_param'], $bindReferences);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false) { // Only false indicates failure
            $stmt->close();
            $this->reset();
            LoggerUtility::log('error', 'DB Result Error: ' . $this->mysqli()->error);
            throw new \Exception("Failed to get result: " . $this->mysqli()->error);
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
        foreach ($values as $key => $value) {
            $references[$key] = &$values[$key];
        }
        return $references;
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
    public function getPrimaryKeys($tableName)
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
    public function upsert($tableName, array $tableData, array $updateColumns = [], $primaryKeys = [])
    {
        $this->reset();
        $keys = array_keys($tableData);
        $placeholders = array_fill(0, count($tableData), '?');
        $values = array_values($tableData);

        $primaryKeys = $primaryKeys ?: $this->getPrimaryKeys($tableName);
        $primaryKeys = is_array($primaryKeys) ? $primaryKeys : [$primaryKeys];

        $sql = "INSERT INTO `$tableName` (`" . implode('`, `', $keys) . "`) VALUES (" . implode(', ', $placeholders) . ")";

        if (empty($updateColumns)) {
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
            } else {
                // Associative array, direct assignment from updateColumns
                if (!in_array($key, $primaryKeys)) {
                    $updateParts[] = "`$key` = ?";
                    $updateValues[] = $column;  // Assuming column is the value to update
                }
            }
        }

        if (!empty($updateParts)) {
            $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
        }

        $stmt = $this->mysqli()->prepare($sql);
        if (!$stmt) {
            LoggerUtility::log('error', "Unable to prepare statement: " . $this->mysqli()->error . ':' . $this->mysqli()->errno);
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
            LoggerUtility::log('error', "Failed to execute upsert: $error");
            return false;
        }
    }

    public function getRequestAndCount(string $sql, ?array $params = null, ?int $limit = null, ?int $offset = null, bool $returnGenerator = true): array
    {
        try {
            $limitOffsetSet = isset($limit) && isset($offset);

            // Apply limit/offset directly to the SQL query if needed
            $querySql = $sql;
            if ($limitOffsetSet) {
                // Parse the query and add limit/offset
                $parser = new Parser($sql);
                $statementForQuery = clone $parser->statements[0];

                // Apply limit if needed
                if (!isset($statementForQuery->limit) || empty($statementForQuery->limit)) {
                    $statementForQuery->limit = new Limit($limit, $offset);
                }

                $querySql = $statementForQuery->build();
            }

            // Execute the main query
            if ($returnGenerator === true) {
                $queryResult = $this->rawQueryGenerator($querySql, $params);
            } else {
                $queryResult = $this->rawQuery($querySql, $params);
            }

            // Get count if needed
            $count = 0;
            if ($limitOffsetSet || $returnGenerator) {
                // Try to get from session first (fastest)
                $countQuerySessionKey = hash('sha256', $sql . json_encode($params));

                if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['queryCounters'][$countQuerySessionKey])) {
                    $count = $_SESSION['queryCounters'][$countQuerySessionKey];
                } else {
                    // Generate the count SQL
                    $parser = new Parser($sql);
                    $originalStatement = clone $parser->statements[0];
                    $statementForCount = clone $originalStatement;
                    $statementForCount->limit = null;
                    $statementForCount->order = null;

                    $countSql = '';
                    if (!empty($originalStatement->group)) {
                        // Group By exists — need subquery
                        $innerSql = $statementForCount->build();
                        $countSql = "SELECT COUNT(*) AS totalCount FROM ($innerSql) AS subquery";
                    } else {
                        // No Group By — simpler
                        $statementForCount->expr = [new Expression('COUNT(*) AS totalCount')];
                        $countSql = $statementForCount->build();
                    }

                    // Execute count query
                    $countResult = $this->rawQueryOne($countSql, $params);
                    $count = (int)($countResult['totalCount'] ?? 0);

                    if (session_status() === PHP_SESSION_ACTIVE) {
                        // Cache in session
                        $_SESSION['queryCounters'][$countQuerySessionKey] = $count;
                    }
                }
            } else {
                $count = count($queryResult);
            }

            return [$queryResult, max((int)$count, 0)];
        } catch (Throwable $e) {
            throw new SystemException('Query Execution Failed. SQL: ' . substr($sql, 0, 500) . ' | Error: ' . $e->getMessage(), 500, $e);
        }
    }


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
        if (empty($data)) {
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
            $updatePart = implode(', ', array_map(fn($col) => "`$col` = VALUES(`$col`)", $updateColumns));
            $sql = "INSERT INTO `$tableName` (`$columns`) VALUES $placeholdersString ON DUPLICATE KEY UPDATE $updatePart";
        } else {
            $sql = "INSERT INTO `$tableName` (`$columns`) VALUES $placeholdersString";
        }

        // Log the SQL string for testing purposes
        //LoggerUtility::log('info', "Generated SQL: $sql");

        $stmt = $this->mysqli()->prepare($sql);
        if (!$stmt) {
            LoggerUtility::log('error', "Unable to prepare statement: " . $this->mysqli()->error);
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
            LoggerUtility::log('error', "Failed to execute insertMultipleRows: $error");
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
        if (!empty($tableName) && $tableName != '') {
            try {

                $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = ? AND table_name= ?";
                $allColResult = $this->rawQuery($allColumns, [SYSTEM_CONFIG['database']['db'], $tableName]);
                $columnNames = array_column($allColResult, 'COLUMN_NAME');

                // Create an array with all column names set to null
                $tableFieldsAsArray = array_fill_keys($columnNames, null);
                if (!empty($unwantedColumns)) {
                    $tableFieldsAsArray = MiscUtility::excludeKeys($tableFieldsAsArray, $unwantedColumns);
                }
            } catch (Throwable $e) {
                throw new SystemException($e->getMessage(), 500, $e);
            }
        }

        return $tableFieldsAsArray;
    }

    /**
     * Load data from a CSV file into the specified table using LOAD DATA INFILE.
     *
     * @param string $tableName Name of the table to load data into.
     * @param string $filePath Full path to the CSV file.
     * @param string $delimiter Delimiter used in the CSV file (default: ',').
     * @param string $enclosure Enclosure used in the CSV file (default: '"').
     * @param string $lineTerminator Line terminator used in the CSV file (default: '\n').
     * @param array $excludeColumns Columns to exclude from update in ON DUPLICATE KEY UPDATE.
     * @return bool Returns true on success or false on failure.
     */
    public function loadDataInfile(
        string $tableName,
        string $filePath,
        string $delimiter = ',',
        string $enclosure = '"',
        string $lineTerminator = '\n',
        array $updateColumns = [],
        array $excludeColumns = []
    ): bool {
        try {
            // Fetch columns dynamically
            $columns = $this->getTableFieldsAsArray($tableName);
            $columnNames = array_keys($columns);

            // Exclude specified columns from update clause
            $updateColumns = !empty($updateColumns) ? $updateColumns : array_diff($columnNames, $excludeColumns);

            // Build the LOAD DATA INFILE query
            $columnList = implode(', ', $columnNames);
            $updateList = implode(', ', array_map(fn($col) => "`$col` = VALUES(`$col`)", $updateColumns));

            $query = "LOAD DATA INFILE ?
            INTO TABLE `$tableName`
            FIELDS TERMINATED BY '$delimiter'
            ENCLOSED BY '$enclosure'
            LINES TERMINATED BY '$lineTerminator'
            IGNORE 1 LINES
            ($columnList)
            ON DUPLICATE KEY UPDATE $updateList;";

            // Execute the query
            $this->rawQuery($query, [$filePath]);
            return true;
        } catch (Throwable $e) {
            LoggerUtility::log('error', "Failed to load data infile: " . $e->getMessage());
            return false;
        }
    }
}
