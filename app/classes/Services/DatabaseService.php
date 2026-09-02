<?php

namespace App\Services;

use Override;
use Exception;
use App\Services\Database\MysqliDb;
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

    /**
     * Transaction nesting depth, keyed by connection name.
     *
     * A depth rather than a flag because a request save opens a transaction and then
     * calls a service that opens one of its own -- insertSample() in every module does
     * exactly this. With a flag, the inner scope's commit ended the outer scope's
     * transaction, so the rest of a batch ran unprotected and the outer rollback
     * silently undid nothing. Only depth 0 talks to the server.
     *
     * @var array<string, int>
     */
    private array $transactionDepth = [];

    /**
     * Set when a nested scope rolled back with no savepoint to unwind to, so the
     * outermost commit must refuse rather than persist half an operation.
     *
     * @var array<string, bool>
     */
    private array $rollbackOnly = [];

    /**
     * Savepoint names pushed by nested scopes, keyed by connection name.
     *
     * @var array<string, list<string|null>>
     */
    private array $savepointStack = [];

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

    /**
     * Escapes a value destined for a LIKE pattern. escape() alone leaves % and _
     * live, so user input could silently widen the match to everything.
     */
    public function escapeLike(mixed $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], (string) $this->escape((string) $value));
    }

    /**
     * Renders an id list for an IN () clause from an array or a CSV string,
     * casting every element to int. Non-numeric elements are dropped; an empty
     * result yields "0" so the IN () stays valid SQL and matches nothing.
     */
    public function inIntList(mixed $values, string $emptyFallback = '0'): string
    {
        if (is_string($values)) {
            $values = explode(',', $values);
        } elseif (!is_array($values)) {
            $values = [$values];
        }
        $ints = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if (is_numeric($value)) {
                $ints[] = (int) $value;
            }
        }
        return $ints === [] ? $emptyFallback : implode(',', $ints);
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
        return ($this->transactionDepth[$this->currentConnection()] ?? 0) > 0;
    }

    /**
     * The connection transaction state is tracked against.
     *
     * MysqliDb keeps several connections on one object -- bin/interface.php drives the
     * analyzer database alongside the application's -- and holds defConnectionName
     * steady for as long as a transaction is in progress. Keying the depth by name
     * keeps one connection's nesting from being counted against another's.
     */
    private function currentConnection(): string
    {
        return $this->defConnectionName ?: 'default';
    }

    /**
     * Replace only true placeholders (outside of quoted strings) for debug output.
     * Prevents notices when SQL contains literal '?' (e.g., in REGEXP patterns).
     */
    protected function replacePlaceHolders($str, $vals)
    {
        if (!is_array($vals) || count($vals) <= 1) {
            return $str;
        }

        $i = 1;
        $len = strlen($str);
        $out = '';
        $inSingle = false;
        $inDouble = false;

        for ($pos = 0; $pos < $len; $pos++) {
            $ch = $str[$pos];

            if ($inSingle) {
                if ($ch === "'") {
                    // Handle doubled single quote inside string literal
                    if ($pos + 1 < $len && $str[$pos + 1] === "'") {
                        $out .= "''";
                        $pos++;
                        continue;
                    }
                    $inSingle = false;
                } elseif ($ch === '\\' && $pos + 1 < $len) {
                    // Preserve escaped character in string literal
                    $out .= $ch . $str[$pos + 1];
                    $pos++;
                    continue;
                }
                $out .= $ch;
                continue;
            }

            if ($inDouble) {
                if ($ch === '"') {
                    // Handle doubled double quote inside string literal
                    if ($pos + 1 < $len && $str[$pos + 1] === '"') {
                        $out .= '""';
                        $pos++;
                        continue;
                    }
                    $inDouble = false;
                } elseif ($ch === '\\' && $pos + 1 < $len) {
                    $out .= $ch . $str[$pos + 1];
                    $pos++;
                    continue;
                }
                $out .= $ch;
                continue;
            }

            if ($ch === "'") {
                $inSingle = true;
                $out .= $ch;
                continue;
            }

            if ($ch === '"') {
                $inDouble = true;
                $out .= $ch;
                continue;
            }

            if ($ch === '?' && isset($vals[$i])) {
                $val = $vals[$i++];
                if (is_object($val)) {
                    $val = '[object]';
                }
                if ($val === null) {
                    $val = 'NULL';
                }
                $out .= "'" . $val . "'";
                continue;
            }

            $out .= $ch;
        }

        return $out;
    }


    /**
     * Normalize an empty bind-param array to null before delegating to the
     * parent. MysqliDb::rawQuery() calls bind_param('') for an empty array,
     * which is a hard fatal on PHP 8.1+ ("Argument #1 ($types) must not be
     * empty") and silently killed every unparameterized rawQuery (e.g. the
     * audit_log drain). Passing null makes the parent skip binding entirely.
     * Also covers rawQueryOne()/rawQueryValue(), which funnel through here.
     *
     * Guarded by resetStateOnFailure() for the same reason as the builder methods. This
     * one binds from its own local array, so it contributes nothing of its own to leak;
     * what it does do is clear whatever was already on the handle, and only when it
     * succeeds -- so a raw query failing between a where() and the write that where() was
     * meant for still leaves that clause behind for the write to inherit.
     */
    #[Override]
    public function rawQuery($query, $bindParams = null)
    {
        if (is_array($bindParams) && $bindParams === []) {
            $bindParams = null;
        }
        return $this->resetStateOnFailure(fn() => parent::rawQuery($query, $bindParams));
    }

    /**
     * The parent runs every raw query through a regex that rewrites the token after
     * `from|into|update|join|describe` into `` `<prefix><token>` ``. Its only purpose
     * is to inject a table prefix, but this application never sets one, so with an
     * empty prefix the regex does nothing but wrap that token in backticks -- which is
     * a no-op for a plain table name and outright corruption for anything that is not
     * a table. `ON UPDATE CURRENT_TIMESTAMP` becomes `ON UPDATE ``CURRENT_TIMESTAMP```,
     * a syntax error that surfaces only through this class and never via the mysql
     * client, which is how a valid-looking migration silently failed to create its
     * table.
     *
     * When a prefix is configured the parent runs verbatim, so prefixed installs are
     * unchanged. When it is empty the query is returned exactly as written. Dropping
     * the regex is safe here because no table in the schema is a reserved word, so
     * none depends on being auto-backticked; already-backticked references in a query
     * are left exactly as written.
     *
     * The parent's newline stripping goes with it. It existed only to flatten the
     * query for that single-line regex, and flattening turns a `--` comment into one
     * that swallows every line after it: the VL fetch-results SELECT carried two such
     * lines and reached MySQL truncated at the comment, failing every call with a
     * syntax error. MySQL parses multi-line SQL fine, so there is nothing to gain by
     * rewriting it.
     */
    #[Override]
    public function rawAddPrefix($query)
    {
        if (self::$prefix !== '') {
            return parent::rawAddPrefix($query);
        }

        return (string) $query;
    }

    /**
     * Execute a query and return a generator to fetch results row by row.
     *
     * The cleanup sits in a finally so it also covers the two paths that used to skip it:
     * a throw from execute(), and a caller that breaks out of the loop and abandons the
     * generator, which reaches it when the generator is destroyed. Both otherwise leave
     * the statement open and whatever state the caller had accumulated on the handle
     * behind -- see resetStateOnFailure() for what that state then does to the next query.
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

        try {
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
                LoggerUtility::logError('DB Result Error: ' . $this->mysqli()->error);
                throw new Exception("Failed to get result: " . $this->mysqli()->error);
            }

            // Fetch results row by row
            while ($row = $result->fetch_assoc()) {
                yield $row;
            }

            $result->free();
        } finally {
            // Always reached, including for empty result sets
            $stmt->close();
            $this->reset();
        }
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

        $this->applySessionTimeZone();
    }

    /**
     * Point the connection at the same clock PHP is using.
     *
     * The application already chooses its timezone deliberately:
     * SystemService::setDateTimeZone() reads global_config.default_time_zone and
     * calls date_default_timezone_set(), so every datetime the app writes is in
     * the timezone of the programme it serves. Nothing ever told the database
     * that, so MySQL kept answering NOW() in the server's own timezone — which
     * is wherever the machine happens to be hosted, and need not be anywhere
     * near the lab.
     *
     * Rows stayed self-consistent, because PHP wrote every column. What was
     * wrong was any query mixing the two: DATEDIFF(NOW(), request_created_datetime)
     * and its relatives, of which this codebase has around thirty. On an STS
     * hosted in Asia/Kolkata serving a programme in Africa/Kinshasa, that is a
     * four-and-a-half hour skew — enough to put a day-count out by one for a
     * fifth of every day, silently, and only in the direction of looking older
     * than it is.
     *
     * A numeric offset rather than a name, because named zones require the
     * mysql.time_zone tables to have been populated and on most installs they
     * have not been. The offset is recomputed on every connect and reconnect, so
     * a daylight-saving change is picked up on the next connection rather than
     * being baked in.
     *
     * Public because connect time is too early on its own. bootstrap.php opens
     * the connection before SystemService::setDateTimeZone() runs, so the offset
     * captured here is PHP's default rather than the application's — UTC, on a
     * machine that has not been told otherwise. SystemService calls this again
     * once it knows the answer.
     *
     * Never fatal: a connection that cannot set this is still a usable
     * connection, and it behaves exactly as it did before this existed.
     */
    public function applySessionTimeZone(): void
    {
        try {
            $offset = (new \DateTimeImmutable('now'))->format('P');
            $this->rawQuery("SET time_zone = ?", [$offset]);
        } catch (Throwable $e) {
            LoggerUtility::logWarning('Failed to align database session time zone: ' . $e->getMessage());
        }
    }

    /**
     * Liveness check for the active connection.
     *
     * Overrides MysqliDb::ping(), which calls mysqli::ping() — deprecated in
     * PHP 8.4 and slated for removal. Instead we run a trivial query on the
     * live handle: a dead connection ("server has gone away") throws, which the
     * caller treats as "reconnect". Returns true only when the round-trip works.
     */
    public function ping(): bool
    {
        try {
            $result = $this->mysqli()->query('SELECT 1');
            if ($result instanceof \mysqli_result) {
                $result->free();
            }
            return $result !== false;
        } catch (Throwable $e) {
            return false;
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
            // The server-side transaction did not survive the reconnect, so neither
            // does any scope that was counted against it.
            $this->transactionDepth = [];
            $this->rollbackOnly = [];
            $this->savepointStack = [];
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
        // The isolation level applies to the transaction as a whole, so only the
        // outermost scope gets to choose it.
        if (($this->transactionDepth[$this->currentConnection()] ?? 0) === 0) {
            $this->setTransactionIsolationLevel($level);
        }

        $this->beginTransaction();
    }

    /**
     * Begin a transaction, or enter a nested scope within the one already open.
     *
     * Nesting is real here: a request save opens a transaction and then calls a
     * service that opens another. Only the outermost scope starts and ends the
     * server-side transaction; an inner scope is bookkeeping, plus a savepoint when
     * savepoints were asked for, so it can unwind its own work without discarding
     * work the caller knows about and it does not.
     *
     * @param bool $useSavepoints Accepted for compatibility and ignored -- a nested
     *                             scope always takes a savepoint now.
     */
    public function beginTransaction($useSavepoints = false): void
    {
        $connection = $this->currentConnection();
        $depth = $this->transactionDepth[$connection] ?? 0;

        if ($depth === 0) {
            $this->startTransaction();
            $this->rollbackOnly[$connection] = false;
            $this->savepointStack[$connection] = [];
        } else {
            // Always, not on request: a nested scope that rolls back has to be able to
            // undo its own work and nothing else, and the callers that need it are the
            // ones least likely to know they are nested. An API batch calling
            // insertSample() per record, or an interop receiver calling getSampleCode()
            // inside its own transaction, both get per-record recovery for free.
            $savepoint = 'nested_' . $depth;
            try {
                $this->createSavepoint($savepoint);
            } catch (Throwable $e) {
                // Degrade rather than pretend. Without a savepoint this scope cannot
                // roll back on its own, and rollbackTransaction() will say so by
                // marking the whole transaction instead.
                LoggerUtility::logWarning(
                    'Could not create savepoint for nested transaction scope: ' . $e->getMessage()
                );
                $savepoint = null;
            }
            $this->savepointStack[$connection][] = $savepoint;
        }

        $this->transactionDepth[$connection] = $depth + 1;
    }


    /**
     * Commit the current scope.
     *
     * A nested commit is not a commit -- it closes that scope and nothing more. Only
     * the outermost one reaches the server, which is what lets a service commit its
     * own insert without ending the batch its caller is still building.
     */
    public function commitTransaction(): void
    {
        $connection = $this->currentConnection();
        $depth = $this->transactionDepth[$connection] ?? 0;

        // Nothing open. Unchanged, and depended on: several call sites commit
        // unconditionally on a path that may already have resolved.
        if ($depth === 0) {
            return;
        }

        if ($depth > 1) {
            $savepoint = array_pop($this->savepointStack[$connection]);
            if ($savepoint !== null) {
                try {
                    $this->releaseSavepoint($savepoint);
                } catch (Throwable $e) {
                    // Releasing only frees the marker; the work stays in the
                    // transaction either way. Not worth failing an operation over.
                    LoggerUtility::logWarning('Could not release savepoint: ' . $e->getMessage());
                }
            }
            $this->transactionDepth[$connection] = $depth - 1;
            return;
        }

        $this->transactionDepth[$connection] = 0;
        $this->savepointStack[$connection] = [];

        if ($this->rollbackOnly[$connection] ?? false) {
            // An inner scope failed and had no savepoint to undo just its own work.
            // Committing here would persist half an operation and report success.
            $this->rollbackOnly[$connection] = false;
            $this->rollback();
            throw new SystemException(
                'Transaction was marked rollback-only by a nested operation and has been rolled back',
                500
            );
        }

        $this->commit();
    }


    /**
     * Roll back the current scope.
     *
     * @param string|null $toSavepoint The savepoint to rollback to, or null to rollback the entire transaction.
     */
    public function rollbackTransaction($toSavepoint = null): void
    {
        $connection = $this->currentConnection();
        $depth = $this->transactionDepth[$connection] ?? 0;

        // A no-op once the transaction has been resolved. Several call sites roll back
        // unconditionally as a backstop and rely on this.
        if ($depth === 0) {
            return;
        }

        if ($depth > 1) {
            $savepoint = array_pop($this->savepointStack[$connection]);
            if ($savepoint !== null) {
                $this->rollbackToSavepoint($savepoint);
            } else {
                // Nothing to unwind to, so this scope cannot undo only its own work.
                // Mark the transaction so the outermost commit refuses it.
                $this->rollbackOnly[$connection] = true;
            }
            $this->transactionDepth[$connection] = $depth - 1;
            return;
        }

        // An explicit partial rollback leaves the transaction open, as SQL says it
        // does. The caller asked to undo part of its work, not to end it.
        if ($toSavepoint !== null) {
            $this->rollbackToSavepoint($toSavepoint);
            return;
        }

        $this->transactionDepth[$connection] = 0;
        $this->savepointStack[$connection] = [];
        $this->rollbackOnly[$connection] = false;
        $this->rollback();
    }

    /**
     * Run a savepoint statement.
     *
     * Not through rawQuery(): that prepares, and MySQL rejects SAVEPOINT and its two
     * companions in the prepared statement protocol ("This command is not supported
     * in the prepared statement protocol yet"), so these three used to fatal the
     * moment anything called them.
     *
     * A savepoint name is an identifier and cannot be bound, so it is validated
     * rather than escaped. Names are generated internally today, but these are
     * public methods and the check is what keeps that from mattering.
     */
    private function savepointStatement(string $keyword, string $savepointName): void
    {
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $savepointName) !== 1) {
            throw new SystemException("Invalid savepoint name: $savepointName", 500);
        }

        $this->mysqli()->query("$keyword `$savepointName`");
    }

    public function createSavepoint($savepointName): void
    {
        $this->savepointStatement('SAVEPOINT', (string) $savepointName);
    }

    public function rollbackToSavepoint($savepointName): void
    {
        $this->savepointStatement('ROLLBACK TO SAVEPOINT', (string) $savepointName);
    }

    public function releaseSavepoint($savepointName): void
    {
        $this->savepointStatement('RELEASE SAVEPOINT', (string) $savepointName);
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
     * MysqliDb assembles a query out of state accumulated on the handle -- where(),
     * join(), orderBy() and the bind-param list -- and clears it in reset(), which
     * every terminal method calls only AFTER $stmt->execute() has returned. mysqli
     * runs in exception mode on PHP 8.1+, so a statement that fails (a broken trigger
     * on the table, a constraint violation, a dropped connection) unwinds straight
     * past that call and leaves the state sitting on the handle.
     *
     * The next query on the same handle then inherits it. The stale WHERE is appended
     * and bound a second time while its original values are still at the head of the
     * bind list, so the query ends up with fewer placeholders than bound variables and
     * bind_param() rejects it: "The number of variables must match the number of
     * parameters in the prepared statement" -- reported against a statement that is
     * itself well formed, in a helper that never touched the table that actually
     * failed.
     *
     * The silent case is worse. When the counts happen to line up, the leaked values
     * shift the whole SET list along and the write lands with every column holding its
     * neighbour's value, under a WHERE that is no longer the caller's. That is how an
     * UPDATE facility_details, failing on a pre-5.5.3 audit trigger left on a table
     * outside AuditTriggerService::trackedTables(), corrupted the form_vl UPDATE that
     * followed it in the same request.
     *
     * So the state is cleared on the failure path as well. Subquery objects are exempt:
     * they hold their built query and bind params for the outer query to consume and
     * never execute anything themselves.
     */
    private function resetStateOnFailure(callable $query): mixed
    {
        try {
            return $query();
        } catch (Throwable $e) {
            if (!$this->isSubQuery) {
                // The parent copies these off the statement after execute(), which is
                // exactly the line we did not reach, so callers inspecting
                // getLastError()/getLastErrno() in their catch block would otherwise
                // see the previous statement's result. reset() leaves them alone.
                if ($e instanceof \mysqli_sql_exception) {
                    $this->_stmtError = $e->getMessage();
                    $this->_stmtErrno = (int) $e->getCode();
                }
                $this->reset();
            }
            throw $e;
        }
    }

    /**
     * getOne(), getValue(), has() and paginate() all funnel through get(), so guarding
     * it here covers them too.
     */
    #[Override]
    public function get($tableName, $numRows = null, $columns = '*')
    {
        return $this->resetStateOnFailure(fn() => parent::get($tableName, $numRows, $columns));
    }

    #[Override]
    public function insert($tableName, $insertData)
    {
        return $this->resetStateOnFailure(fn() => parent::insert($tableName, $insertData));
    }

    #[Override]
    public function replace($tableName, $insertData)
    {
        return $this->resetStateOnFailure(fn() => parent::replace($tableName, $insertData));
    }

    #[Override]
    public function update($tableName, $tableData, $numRows = null)
    {
        return $this->resetStateOnFailure(fn() => parent::update($tableName, $tableData, $numRows));
    }

    #[Override]
    public function delete($tableName, $numRows = null)
    {
        return $this->resetStateOnFailure(fn() => parent::delete($tableName, $numRows));
    }

    /**
     * query() takes the SQL verbatim but still runs it through _buildQuery(), so a
     * where() or join() chained before it is appended and bound like anywhere else --
     * it leaks exactly as get() does.
     */
    #[Override]
    public function query($query, $numRows = null)
    {
        return $this->resetStateOnFailure(fn() => parent::query($query, $numRows));
    }


    /**
     * The insert() guard covers the individual rows, but not the transaction the parent
     * opens around the loop when one is not already running: it rolls that back when a
     * row returns false and not when a row throws, which leaves the connection inside an
     * open transaction with autocommit off. Only a transaction this call started is
     * rolled back here -- an outer one belongs to the caller.
     */
    #[Override]
    public function insertMulti($tableName, array $multiInsertData, ?array $dataKeys = null)
    {
        $outerTransaction = $this->_transaction_in_progress;

        try {
            return $this->resetStateOnFailure(fn() => parent::insertMulti($tableName, $multiInsertData, $dataKeys));
        } catch (Throwable $e) {
            if (!$outerTransaction && $this->_transaction_in_progress) {
                $this->rollback();
            }
            throw $e;
        }
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
