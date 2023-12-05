<?php

namespace App\Services;

use MysqliDb;
use Generator;

class DatabaseService extends MysqliDb
{

    private $isTransactionActive = false;

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
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            yield $row;
        }

        $stmt->close();
        $this->reset();
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
