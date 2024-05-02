<?php

namespace App\Services;

use MysqliDb;
use Generator;
use Throwable;
use App\Utilities\LoggerUtility;

final class DatabaseService extends MysqliDb
{

    private $isTransactionActive = false;

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
     * Optionally execute as an unbuffered query.
     *
     * @param string $query SQL query string
     * @param array|null $bindParams Parameters to bind to the query
     * @param bool $unbuffered Whether to execute as an unbuffered query
     * @return Generator
     */
    public function rawQueryGenerator(string $query, $bindParams = null, bool $unbuffered = false)
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

        if (!$unbuffered) {
            $stmt->store_result();
        }

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
     * Begin a new transaction if not already started.
     */
    public function beginTransaction(): void
    {
        if (!$this->isTransactionActive) {
            $this->startTransaction();
            $this->isTransactionActive = true;
        }
    }

    /**
     * Commit the current transaction.
     */
    public function commitTransaction(): void
    {
        if ($this->isTransactionActive) {
            $this->commit();
            $this->isTransactionActive = false;
        }
    }

    /**
     * Roll back the current transaction.
     */
    public function rollbackTransaction(): void
    {
        if ($this->isTransactionActive) {
            $this->rollback();
            $this->isTransactionActive = false;
        }
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
        $keys = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');
        $values = array_values($data);

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



    public function reset(): void
    {
        parent::reset();
    }
}
