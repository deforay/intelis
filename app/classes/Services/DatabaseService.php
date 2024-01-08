<?php

namespace App\Services;

use App\Exceptions\SystemException;
use MysqliDb;
use Generator;

class DatabaseService extends MysqliDb
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
                array_push($params, $val);
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
}
