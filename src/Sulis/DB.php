<?php

declare(strict_types=1);

namespace Sulis;

use Exception;
use ReflectionClass;

class DB
{
    public string $last_query;
    public int $num_rows;
    public int $insert_id;
    public int $affected_rows;
    public bool $is_cached = false;
    public bool $stats_enabled = false;
    public bool $show_sql = false;
    public string $key_prefix = '';

    protected string $table;
    protected string $where;
    protected string $joins;
    protected string $order;
    protected string $groups;
    protected string $having;
    protected string $distinct;
    protected int $limit;
    protected int $offset;
    protected string $sql;

    protected $db;
    protected string $db_type;
    protected $cache;
    protected string $cache_type;
    protected array $stats;
    protected float $query_time;
    protected object $class;

    protected static array $db_types = ['pdo', 'mysqli', 'mysql', 'pgsql', 'sqlite', 'sqlite3'];
    protected static array $cache_types = ['memcached', 'memcache', 'xcache'];

    public function __construct()
    {
        //
    }

    public function build(string $sql, string $input): string
    {
        return (strlen($input) > 0) ? ($sql . ' ' . $input) : $sql;
    }

    public function parseConnection(string $connection): array
    {
        $url = parse_url($connection);

        if (empty($url)) {
            throw new Exception('Invalid connection string.');
        }

        $cfg = [];

        $cfg['type'] = isset($url['scheme']) ? $url['scheme'] : $url['path'];
        $cfg['hostname'] = isset($url['host']) ? $url['host'] : null;
        $cfg['database'] = isset($url['path']) ? substr($url['path'], 1) : null;
        $cfg['username'] = isset($url['user']) ? $url['user'] : null;
        $cfg['password'] = isset($url['pass']) ? $url['pass'] : null;
        $cfg['port'] = isset($url['port']) ? $url['port'] : null;

        return $cfg;
    }

    public function getStats(): array
    {
        $this->stats['total_time'] = 0;
        $this->stats['num_queries'] = 0;
        $this->stats['num_rows'] = 0;
        $this->stats['num_changes'] = 0;

        if (isset($this->stats['queries'])) {
            foreach ($this->stats['queries'] as $query) {
                $this->stats['total_time'] += $query['time'];
                $this->stats['num_queries'] += 1;
                $this->stats['num_rows'] += $query['rows'];
                $this->stats['num_changes'] += $query['changes'];
            }
        }

        $totsl_queries = (float) (($this->stats['num_queries'] > 0) ? $this->stats['num_queries'] : 1);
        $this->stats['avg_query_time'] = $this->stats['total_time'] / $totsl_queries;

        return $this->stats;
    }

    public function checkTable(): void
    {
        if (! $this->table) {
            throw new Exception('Table is not defined.');
        }
    }

    public function checkClass(): void
    {
        if (! $this->class) {
            throw new Exception('Class is not defined.');
        }
    }

    public function reset(): void
    {
        $this->where = '';
        $this->joins = '';
        $this->order = '';
        $this->groups = '';
        $this->having = '';
        $this->distinct = '';
        $this->limit = '';
        $this->offset = '';
        $this->sql = '';
    }

    protected function parseCondition(string $field, ?string $value = null, string $join = '', bool $escape = true): string
    {
        if (is_string($field)) {
            if ($value === null) {
                return $join . ' ' . trim($field);
            }

            $operator = '';

            if (strpos($field, ' ') !== false) {
                list($field, $operator) = explode(' ', $field);
            }

            if (! empty($operator)) {
                switch ($operator) {
                    case '%':  $condition = ' LIKE ';     break;
                    case '!%': $condition = ' NOT LIKE '; break;
                    case '@':  $condition = ' IN ';       break;
                    case '!@': $condition = ' NOT IN ';   break;
                    default:   $condition = $operator;
                }
            } else {
                $condition = '=';
            }

            $join = empty($join) ? (($field[0] === '|') ? ' OR' : ' AND') : $join;

            if (is_array($value)) {
                $condition = (strpos($operator, '@') === false) ? ' IN ' : $condition;
                $value = '(' . implode(',', array_map([$this, 'quote'], $value)) . ')';
            } else {
                $value = ($escape && ! is_numeric($value)) ? $this->quote($value) : $value;
            }

            return $join . ' ' . str_replace('|', '', $field) . $condition . $value;
        } elseif (is_array($field)) {
            $str = '';

            foreach ($field as $key => $value) {
                $str .= $this->parseCondition($key, $value, $join, $escape);
                $join = '';
            }

            return $str;
        } else {
            throw new Exception('Invalid where condition.');
        }
    }

    public function from(string $table, bool $reset = true): self
    {
        $this->table = $table;

        if ($reset) {
            $this->reset();
        }

        return $this;
    }

    public function join(string $table, array $fields, string $type = 'INNER'): self
    {
        if (! in_array($type, ['INNER', 'LEFT OUTER', 'RIGHT OUTER', 'FULL OUTER'])) {
            throw new Exception('Invalid join type.');
        }

        $this->joins .= ' ' . $type . ' JOIN ' . $table . $this->parseCondition($fields, null, ' ON', false);
        return $this;
    }

    public function leftJoin(string $table, array $fields): string
    {
        return $this->join($table, $fields, 'LEFT OUTER');
    }

    public function rightJoin(string $table, array $fields): string
    {
        return $this->join($table, $fields, 'RIGHT OUTER');
    }

    public function fullJoin(string $table, array $fields): string
    {
        return $this->join($table, $fields, 'FULL OUTER');
    }

    public function where(string $field, ?string $value = null): self
    {
        $join = empty($this->where) ? 'WHERE' : '';
        $this->where .= $this->parseCondition($field, $value, $join);
        return $this;
    }

    public function sortAsc(string $field): string
    {
        return $this->orderBy($field, 'ASC');
    }

    public function sortDesc(string $field): string
    {
        return $this->orderBy($field, 'DESC');
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $join = (empty($this->order)) ? 'ORDER BY' : ',';

        if (is_array($field)) {
            foreach ($field as $key => $value) {
                $field[$key] = $value . ' ' . $direction;
            }
        } else {
            $field .= ' ' . $direction;
        }

        $fields = is_array($field) ? implode(', ', $field) : $field;
        $this->order .= $join . ' ' . $fields;

        return $this;
    }

    public function groupBy(string $field): self
    {
        $join = empty($this->order) ? 'GROUP BY' : ',';
        $fields = is_array($field) ? implode(',', $field) : $field;

        $this->groups .= $join . ' ' . $fields;
        return $this;
    }

    public function having(string $field, $value = null): self
    {
        $join = empty($this->having) ? 'HAVING' : '';
        $this->having .= $this->parseCondition($field, $value, $join);

        return $this;
    }

    public function limit(int $limit, ?int $offset = null): self
    {
        if ($limit !== null) {
            $this->limit = 'LIMIT ' . $limit;
        }

        if ($offset !== null) {
            $this->offset($offset);
        }

        return $this;
    }

    public function offset(int $offset, ?int $limit = null): self
    {
        if ($offset !== null) {
            $this->offset = 'OFFSET ' . $offset;
        }

        if ($limit !== null) {
            $this->limit($limit);
        }

        return $this;
    }

    public function distinct(bool $value = true): self
    {
        $this->distinct = $value ? 'DISTINCT' : '';
        return $this;
    }

    public function between(string $field, string $value1, string $value2): void
    {
        $this->where(sprintf('%s BETWEEN %s AND %s', $field, $this->quote($value1), $this->quote($value2)));
    }

    public function select(string $fields = '*', ?int $limit = null, ?int $offset = null): self
    {
        $this->checkTable();

        $fields = is_array($fields) ? implode(',', $fields) : $fields;
        $this->limit($limit, $offset);

        $this->sql([
            'SELECT',
            $this->distinct,
            $fields,
            'FROM',
            $this->table,
            $this->joins,
            $this->where,
            $this->groups,
            $this->having,
            $this->order,
            $this->limit,
            $this->offset
        ]);

        return $this;
    }

    public function insert(array $data): self
    {
        $this->checkTable();

        if (empty($data)) {
            return $this;
        }

        $keys = implode(',', array_keys($data));
        $values = implode(',', array_values(array_map([$this, 'quote'], $data)));

        $this->sql([
            'INSERT INTO',
            $this->table,
            '(' . $keys . ')',
            'VALUES',
            '(' . $values . ')'
        ]);

        return $this;
    }

    public function update(?array $data): self
    {
        $this->checkTable();

        if (empty($data)) {
            return $this;
        }

        $values = [];

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $values[] = is_numeric($key) ? $value : $key . '=' . $this->quote($value);
            }
        } else {
            $values[] = (string) $data;
        }

        $this->sql([
            'UPDATE',
            $this->table,
            'SET',
            implode(',', $values),
            $this->where
        ]);

        return $this;
    }

    public function delete(?string $where = null): self
    {
        $this->checkTable();

        if ($where !== null) {
            $this->where($where);
        }

        $this->sql([
            'DELETE FROM',
            $this->table,
            $this->where
        ]);

        return $this;
    }

    public function sql(?string $sql = null): self
    {
        if ($sql !== null) {
            $this->sql = trim(is_array($sql) ? array_reduce($sql, [$this, 'build']) : $sql);
            return $this;
        }

        return $this->sql;
    }

    public function setDb($db): void
    {
        $this->db = null;

        if (is_string($db)) {
            $this->setDb($this->parseConnection($db));
        } elseif (is_array($db)) {
            switch ($db['type']) {
                case 'mysqli':
                    $this->db = new \mysqli($db['hostname'], $db['username'], $db['password'], $db['database']);

                    if ($this->db->connect_error) {
                        throw new Exception('Connection error: ' . $this->db->connect_error);
                    }
                    break;

                case 'mysql':
                    $this->db = mysql_connect($db['hostname'], $db['username'], $db['password']);

                    if (! $this->db) {
                        throw new Exception('Connection error: ' . mysql_error());
                    }

                    mysql_select_db($db['database'], $this->db);
                    break;

                case 'pgsql':
                    $str = sprintf(
                        'host=%s dbname=%s user=%s password=%s',
                        $db['hostname'],
                        $db['database'],
                        $db['username'],
                        $db['password']
                    );

                    $this->db = pg_connect($str);
                    break;

                case 'sqlite':
                    $this->db = sqlite_open($db['database'], 0666, $error);

                    if (! $this->db) {
                        throw new Exception('Connection error: ' . $error);
                    }
                    break;

                case 'sqlite3':
                    $this->db = new \SQLite3($db['database']);
                    break;

                case 'pdomysql':
                    $dsn = sprintf(
                        'mysql:host=%s;port=%d;dbname=%s',
                        $db['hostname'],
                        isset($db['port']) ? $db['port'] : 3306,
                        $db['database']
                    );

                    $this->db = new \PDO($dsn, $db['username'], $db['password']);
                    $db['type'] = 'pdo';
                    break;

                case 'pdopgsql':
                    $dsn = sprintf(
                        'pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s',
                        $db['hostname'],
                        isset($db['port']) ? $db['port'] : 5432,
                        $db['database'],
                        $db['username'],
                        $db['password']
                    );

                    $this->db = new \PDO($dsn);
                    $db['type'] = 'pdo';
                    break;

                case 'pdosqlite':
                    $this->db = new \PDO('sqlite:/' . $db['database']);
                    $db['type'] = 'pdo';
                    break;
            }

            if (! $this->db) {
                throw new Exception('Undefined database.');
            }

            $this->db_type = $db['type'];
        } else {
            $type = $this->getDbType($db);

            if (! in_array($type, self::$db_types)) {
                throw new Exception('Invalid database type.');
            }

            $this->db = $db;
            $this->db_type = $type;
        }
    }

    public function getDb()
    {
        return $this->db;
    }

    public function getDbType($db): string
    {
        if (is_object($db)) {
            return strtolower(get_class($db));
        } elseif (is_resource($db)) {
            switch (get_resource_type($db)) {
                case 'mysql link':      return 'mysql';
                case 'sqlite database': return 'sqlite';
                case 'pgsql link':      return 'pgsql';
            }
        }

        return null;
    }

    public function execute(?string $key = null, int $expire = 0)
    {
        if (! $this->db) {
            throw new Exception('Database is not defined.');
        }

        if ($key !== null) {
            $result = $this->fetch($key);

            if ($this->is_cached) {
                return $result;
            }
        }

        $result = null;

        $this->is_cached = false;
        $this->num_rows = 0;
        $this->affected_rows = 0;
        $this->insert_id = -1;
        $this->last_query = $this->sql;

        if ($this->stats_enabled) {
            if (empty($this->stats)) {
                $this->stats = ['queries' => []];
            }

            $this->query_time = microtime(true);
        }

        if (! empty($this->sql)) {
            $error = null;

            switch ($this->db_type) {
                case 'pdo':
                    try {
                        $result = $this->db->prepare($this->sql);

                        if (! $result) {
                            $error = $this->db->errorInfo();
                        } else {
                            $result->execute();
                            $this->num_rows = $result->rowCount();
                            $this->affected_rows = $result->rowCount();
                            $this->insert_id = $this->db->lastInsertId();
                        }
                    } catch (\PDOException $ex) {
                        $error = $ex->getMessage();
                    }
                    break;

                case 'mysqli':
                    $result = $this->db->query($this->sql);

                    if (! $result) {
                        $error = $this->db->error;
                    } else {
                        if (is_object($result)) {
                            $this->num_rows = $result->num_rows;
                        } else {
                            $this->affected_rows = $this->db->affected_rows;
                        }

                        $this->insert_id = $this->db->insert_id;
                    }
                    break;

                case 'mysql':
                    $result = mysql_query($this->sql, $this->db);

                    if (! $result) {
                        $error = mysql_error();
                    } else {
                        if (! is_bool($result)) {
                            $this->num_rows = mysql_num_rows($result);
                        } else {
                            $this->affected_rows = mysql_affected_rows($this->db);
                        }

                        $this->insert_id = mysql_insert_id($this->db);
                    }
                    break;

                case 'pgsql':
                    $result = pg_query($this->db, $this->sql);

                    if (! $result) {
                        $error = pg_last_error($this->db);
                    } else {
                        $this->num_rows = pg_num_rows($result);
                        $this->affected_rows = pg_affected_rows($result);
                        $this->insert_id = pg_last_oid($result);
                    }
                    break;

                case 'sqlite':
                    $result = sqlite_query($this->db, $this->sql, SQLITE_ASSOC, $error);

                    if ($result !== false) {
                        $this->num_rows = sqlite_num_rows($result);
                        $this->affected_rows = sqlite_changes($this->db);
                        $this->insert_id = sqlite_last_insert_rowid($this->db);
                    }
                    break;

                case 'sqlite3':
                    $result = $this->db->query($this->sql);

                    if ($result === false) {
                        $error = $this->db->lastErrorMsg();
                    } else {
                        $this->num_rows = 0;
                        $this->affected_rows = $result ? $this->db->changes() : 0;
                        $this->insert_id = $this->db->lastInsertRowId();
                    }
                    break;
            }

            if ($error !== null) {
                if ($this->show_sql) {
                    $error .= "\nSQL: " . $this->sql;
                }

                throw new Exception('Database error: ' . $error);
            }
        }

        if ($this->stats_enabled) {
            $time = microtime(true) - $this->query_time;
            $this->stats['queries'][] = [
                'query' => $this->sql,
                'time' => $time,
                'rows' => (int) $this->num_rows,
                'changes' => (int) $this->affected_rows,
            ];
        }

        return $result;
    }

    public function many(?string $key = null, int $expire = 0)
    {
        if (empty($this->sql)) {
            $this->select();
        }

        $data = [];
        $result = $this->execute($key, $expire);

        if ($this->is_cached) {
            $data = $result;

            if ($this->stats_enabled) {
                $this->stats['cached'][$this->key_prefix.$key] = $this->sql;
            }
        } else {
            switch ($this->db_type) {
                case 'pdo':
                    $data = $result->fetchAll(\PDO::FETCH_ASSOC);
                    $this->num_rows = count($data);
                    break;

                case 'mysqli':
                    if (function_exists('mysqli_fetch_all')) {
                        $data = $result->fetch_all(MYSQLI_ASSOC);
                    } else {
                        while ($row = $result->fetch_assoc()) {
                            $data[] = $row;
                        }
                    }

                    $result->close();
                    break;

                case 'mysql':
                    while ($row = mysql_fetch_assoc($result)) {
                        $data[] = $row;
                    }

                    mysql_free_result($result);
                    break;

                case 'pgsql':
                    $data = pg_fetch_all($result);
                    pg_free_result($result);
                    break;

                case 'sqlite':
                    $data = sqlite_fetch_all($result, SQLITE_ASSOC);
                    break;

                case 'sqlite3':
                    if ($result) {
                        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                            $data[] = $row;
                        }

                        $result->finalize();
                        $this->num_rows = count($data);
                    }
                    break;
            }
        }

        if (! $this->is_cached && $key !== null) {
            $this->store($key, $data, $expire);
        }

        return $data;
    }

    public function one(?string $key = null, int $expire = 0)
    {
        if (empty($this->sql)) {
            $this->limit(1)->select();
        }

        $data = $this->many($key, $expire);
        $row = empty($data) ? [] : $data[0];

        return $row;
    }

    public function value(string $name, ?string $key = null, int $expire = 0): string
    {
        $row = $this->one($key, $expire);
        $value = empty($row) ? null : $row[$name];

        return $value;
    }

    public function min(string $field, ?string $key = null, int $expire = 0): string
    {
        $this->select('MIN(' . $field . ') min_value');
        return $this->value('min_value', $key, $expire);
    }

    public function max(string $field, ?string $key = null, int $expire = 0): string
    {
        $this->select('MAX(' . $field . ') max_value');
        return $this->value('max_value', $key, $expire);
    }

    public function sum(string $field, ?string $key = null, int $expire = 0): string
    {
        $this->select('SUM(' . $field . ') sum_value');
        return $this->value('sum_value', $key, $expire);
    }

    public function avg(string $field, ?string $key = null, int $expire = 0): string
    {
        $this->select('AVG(' . $field . ') avg_value');
        return $this->value('avg_value', $key, $expire);
    }

    public function count(string $field = '*', ?string $key = null, int $expire = 0): string
    {
        $this->select('COUNT(' . $field . ') num_rows');
        return $this->value('num_rows', $key, $expire);
    }

    public function quote(string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_string($value)) {
            if ($this->db !== null) {
                switch ($this->db_type) {
                    case 'pdo':     return $this->db->quote($value);
                    case 'mysqli':  return "'" . $this->db->real_escape_string($value) . "'";
                    case 'mysql':   return "'" . mysql_real_escape_string($value, $this->db) . "'";
                    case 'pgsql':   return "'" . pg_escape_string($this->db, $value) . "'";
                    case 'sqlite':  return "'" . sqlite_escape_string($value) . "'";
                    case 'sqlite3': return "'" . $this->db->escapeString($value) . "'";
                }
            }

            $value = str_replace(
                ['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
                ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'],
                $value
            );

            return "'$value'";
        }

        return $value;
    }

    public function setCache($cache): void
    {
        $this->cache = null;

        if (is_string($cache)) {
            if ($cache[0] === '.' || $cache[0] === '/') {
                $this->cache = $cache;
                $this->cache_type = 'file';
            } else {
                $this->setCache($this->parseConnection($cache));
            }
        } elseif (is_array($cache)) {
            switch ($cache['type']) {
                case 'memcache':
                    $this->cache = new \Memcache();
                    $this->cache->connect($cache['hostname'], $cache['port']);
                    break;

                case 'memcached':
                    $this->cache = new \Memcached();
                    $this->cache->addServer($cache['hostname'], $cache['port']);
                    break;

                default:
                    $this->cache = $cache['type'];
            }

            $this->cache_type = $cache['type'];
        } elseif (is_object($cache)) {
            $type = strtolower(get_class($cache));

            if (! in_array($type, self::$cache_types)) {
                throw new Exception('Invalid cache type.');
            }

            $this->cache = $cache;
            $this->cache_type = $type;
        }
    }

    public function getCache()
    {
        return $this->cache;
    }

    public function store(string $key, $value, int $expire = 0): void
    {
        $key = $this->key_prefix . $key;

        switch ($this->cache_type) {
            case 'memcached':
                $this->cache->set($key, $value, $expire);
                break;

            case 'memcache':
                $this->cache->set($key, $value, 0, $expire);
                break;

            case 'apc':
                apc_store($key, $value, $expire);
                break;

            case 'xcache':
                xcache_set($key, $value, $expire);
                break;

            case 'file':
                $file = $this->cache . '/' . md5($key);
                $data = ['value' => $value, 'expire' => ($expire > 0) ? (time() + $expire) : 0];
                file_put_contents($file, serialize($data));
                break;

            default:
                $this->cache[$key] = $value;
        }
    }

    public function fetch(string $key)
    {
        $key = $this->key_prefix . $key;

        switch ($this->cache_type) {
            case 'memcached':
                $value = $this->cache->get($key);
                $this->is_cached = $this->cache->getResultCode() === \Memcached::RES_SUCCESS;
                return $value;

            case 'memcache':
                $value = $this->cache->get($key);
                $this->is_cached = $value !== false;
                return $value;

            case 'apc':
                return apc_fetch($key, $this->is_cached);

            case 'xcache':
                $this->is_cached = xcache_isset($key);
                return xcache_get($key);

            case 'file':
                $file = $this->cache . '/' . md5($key);

                if ($this->is_cached = is_file($file)) {
                    $data = unserialize(file_get_contents($file));

                    if ((int) $data['expire'] === 0 || time() < $data['expire']) {
                        return $data['value'];
                    } else {
                        $this->is_cached = false;
                    }
                }
                break;

            default:
                return $this->cache[$key];
        }

        return null;
    }

    public function clear(string $key): bool
    {
        $key = $this->key_prefix . $key;

        switch ($this->cache_type) {
            case 'memcached': return $this->cache->delete($key);
            case 'memcache':  return $this->cache->delete($key);
            case 'apc':       return apc_delete($key);
            case 'xcache':    return xcache_unset($key);

            case 'file':
            if (is_file($file = $this->cache . '/' . md5($key))) {
                return unlink($file);
            }

                return false;

            default:
                if (isset($this->cache[$key])) {
                    unset($this->cache[$key]);
                    return true;
                }

                return false;
        }
    }

    public function flush(): void
    {
        switch ($this->cache_type) {
            case 'memcached':
                $this->cache->flush();
                break;

            case 'memcache':
                $this->cache->flush();
                break;

            case 'apc':
                apc_clear_cache();
                break;

            case 'xcache':
                xcache_clear_cache();
                break;

            case 'file':
                if ($handle = opendir($this->cache)) {
                    while (false !== ($file = readdir($handle))) {
                        if ($file !== '.' && $file !== '..') {
                            unlink($this->cache . '/' . $file);
                        }
                    }

                    closedir($handle);
                }
                break;

            default:
                $this->cache = [];
                break;
        }
    }

    public function using(object $class): self
    {
        if (is_string($class)) {
            $this->class = $class;
        } elseif (is_object($class)) {
            $this->class = get_class($class);
        }

        $this->reset();
        return $this;
    }

    public function load($object, array $data)
    {
        foreach ($data as $key => $value) {
            if (property_exists($object, $key)) {
                $object->$key = $value;
            }
        }

        return $object;
    }

    public function find($value = null, $key = null)
    {
        $this->checkClass();

        $properties = $this->getProperties();
        $this->from($properties->table, false);

        if ($value !== null) {
            if (is_int($value) && property_exists($properties, 'id_field')) {
                $this->where($properties->id_field, $value);
            } elseif (is_string($value) && property_exists($properties, 'name_field')) {
                $this->where($properties->name_field, $value);
            } elseif (is_array($value)) {
                $this->where($value);
            }
        }

        if (empty($this->sql)) {
            $this->select();
        }

        $data = $this->many($key);
        $objects = [];

        foreach ($data as $row) {
            $objects[] = $this->load(new $this->class, $row);
        }

        return (count($objects) === 1) ? $objects[0] : $objects;
    }

    public function save($object, array $fields = null)
    {
        $this->using($object);

        $properties = $this->getProperties();
        $this->from($properties->table);

        $data = get_object_vars($object);
        $id = $object->{$properties->id_field};

        unset($data[$properties->id_field]);

        if ($id === null) {
            $this->insert($data)->execute();
            $object->{$properties->id_field} = $this->insert_id;
        } else {
            if ($fields !== null) {
                $keys = array_flip($fields);
                $data = array_intersect_key($data, $keys);
            }

            $this->where($properties->id_field, $id)->update($data)->execute();
        }

        return $this->class;
    }

    public function remove($object): void
    {
        $this->using($object);

        $properties = $this->getProperties();
        $this->from($properties->table);

        $id = $object->{$properties->id_field};

        if ($id !== null) {
            $this->where($properties->id_field, $id)->delete()->execute();
        }
    }

    public function getProperties(): array
    {
        static $properties = [];

        if (! $this->class) {
            return [];
        }

        if (! isset($properties[$this->class])) {
            $defaults = ['table' => null, 'id_field' => null, 'name_field' => null];
            $reflector = new ReflectionClass($this->class);
            $config = $reflector->getStaticProperties();
            $properties[$this->class] = (object) array_merge($defaults, $config);
        }

        return $properties[$this->class];
    }
}
