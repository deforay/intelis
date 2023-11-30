<?php

namespace App\Services;

use MysqliDb;

class DatabaseService extends MysqliDb
{

    /**
     * Execute a query and return a generator to fetch results row by row.
     *
     * @param string $query SQL query string
     * @param array|null $bindParams Parameters to bind to the query
     * @return \Generator
     */
    public function rawQueryGenerator(string $query, $bindParams = null)
    {
        $params = ['']; // Create the empty 0 index
        $this->_query = $query;
        $stmt = $this->_prepareQuery();

        if (is_array($bindParams)) {
            foreach ($bindParams as $prop => $val) {
                $params[0] .= $this->_determineType($val);
                $params[] = $bindParams[$prop];
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
}
