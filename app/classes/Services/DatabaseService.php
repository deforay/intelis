<?php

namespace App\Services;

use MysqliDb;
use Generator;
use Throwable;
use App\Utilities\LoggerUtility;
use PhpMyAdmin\SqlParser\Parser;
use App\Exceptions\SystemException;
use PhpMyAdmin\SqlParser\Components\Limit;
use PhpMyAdmin\SqlParser\Components\Expression;

final class DatabaseService extends MysqliDb
{

    private $isTransactionActive = false;
    private $useSavepoints = false;

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


    /**
     * Execute a query and return a generator to fetch results row by row.
     *
     * @param string $query SQL query string
     * @param array|null $bindParams Parameters to bind to the query
     * @return Generator
     */
    public function rawQueryGenerator(string $query, $bindParams = null)
    {
        $params = ['']; // Create the empty 0 index
        $this->_query = $query;
        $stmt = $this->_prepareQuery();

        if (is_array($bindParams)) {
            foreach ($bindParams as $val) {
                $params[0] .= $this->_determineType($val);
                $params[] = $val;
            }
            $stmt->bind_param(...$this->refValues($params));
        }

        $stmt->execute();

        // Initialize $row as an empty array
        $row = [];
        $parameters = [];

        $meta = $stmt->result_metadata();
        while ($field = $meta->fetch_field()) {
            $parameters[] = &$row[$field->name];
        }

        call_user_func_array([$stmt, 'bind_result'], $parameters);

        while ($stmt->fetch()) {
            yield $row;
        }

        $stmt->close();
        $this->reset();
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

    /**
     * Commit the current transaction or to a savepoint.
     * @param string|null $toSavepoint The savepoint to commit to, or null to commit the entire transaction.
     */
    public function commitTransaction($toSavepoint = null): void
    {
        if ($this->isTransactionActive) {
            if ($toSavepoint && $this->useSavepoints) {
                $this->releaseSavepoint($toSavepoint);
            } else {
                $this->commit();
                $this->isTransactionActive = false;
            }
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
     * @param array  $data Associative array of data to insert (column => value).
     * @param array  $updateColumns Array of columns to be updated on duplicate key, excluding primary key components.
     * @param array|string  $primaryKeys String or Array of primary key column names.
     * @return bool Returns true on success or false on failure.
     */

    public function upsert($tableName, array $data, array $updateColumns = [], $primaryKeys = [])
    {
        $this->reset();

        if (empty($data)) {
            return false;
        }

        // Get primary keys, either provided or fetched from the database
        $primaryKeys = $primaryKeys ?: $this->getPrimaryKeys($tableName);
        $primaryKeys = is_array($primaryKeys) ? $primaryKeys : [$primaryKeys];

        // Determine columns to update on duplicate key
        if (empty($updateColumns)) {
            $updateColumns = array_diff(array_keys($data), $primaryKeys);
        }

        // Prepare data for updating
        $updateData = [];
        foreach ($updateColumns as $column) {
            if (array_key_exists($column, $data)) {
                $updateData[$column] = $data[$column];
            }
        }

        // Use parent class methods
        $this->onDuplicate($updateData);
        return $this->insert($tableName, $data);
    }

    public function getQueryResultAndCount(string $sql, ?array $params = null, ?int $limit = null, ?int $offset = null, bool $returnGenerator = true): array
    {
        try {

            $parser = new Parser($sql);

            // Retrieve the first statement
            $statement = $parser->statements[0];

            $limitOffsetSet = isset($limit) && isset($offset);

            if ((!isset($statement->limit) || empty($statement->limit)) && $limitOffsetSet) {
                $statement->limit = new Limit($limit, $offset);
            }

            $sql = $statement->build();

            // Execute the main query
            if ($returnGenerator === true) {
                $queryResult = $this->rawQueryGenerator($sql, $params);
            } else {
                $queryResult = $this->rawQuery($sql, $params);
            }


            $count = 0;
            // Execute the count query if necessary
            if ($limitOffsetSet || $returnGenerator) {

                $statement->limit = null;
                $statement->order = null;

                if (stripos($sql, 'GROUP BY') !== false) {
                    $sql = $statement->build();
                    $countSql = "SELECT COUNT(*) as totalCount FROM ($sql) as subquery";
                } else {
                    // Replacing all SELECT columns with a new COUNT expression
                    $statement->expr = [new Expression('COUNT(*) as totalCount')];
                    $countSql = $statement->build();
                }

                // Generate a unique session key for the count query
                $countQuerySessionKey = hash('sha256', $countSql);
                $count = $_SESSION['queryCounters'][$countQuerySessionKey] ?? ($_SESSION['queryCounters'][$countQuerySessionKey] = (int)$this->rawQueryOne($countSql)['totalCount']);
            } else {
                $count = count($queryResult);
            }

            return [$queryResult, max((int)$count, 0)];
        } catch (Throwable $e) {
            throw new SystemException($e->getMessage(), 500, $e);
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
}
