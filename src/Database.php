<?php
/**
 * کلاس اتصال و مدیریت دیتابیس
 */

class Database {
    private $conn;
    private $host;
    private $user;
    private $pass;
    private $db;
    private $charset;
    private $port;

    public function __construct() {
        $this->host = DB_HOST;
        $this->user = DB_USER;
        $this->pass = DB_PASS;
        $this->db = DB_NAME;
        $this->charset = DB_CHARSET;
        $this->port = DB_PORT;
        $this->connect();
    }

    private function connect() {
        try {
            $this->conn = new mysqli(
                $this->host,
                $this->user,
                $this->pass,
                $this->db,
                $this->port
            );

            if ($this->conn->connect_error) {
                throw new Exception('Database connection error: ' . $this->conn->connect_error);
            }

            $this->conn->set_charset($this->charset);
        } catch (Exception $e) {
            $this->log('Database Error: ' . $e->getMessage());
            die('Database connection failed');
        }
    }

    public function query($sql) {
        $result = $this->conn->query($sql);
        if ($this->conn->error) {
            $this->log('Query Error: ' . $this->conn->error . ' - SQL: ' . $sql);
            return false;
        }
        return $result;
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }

    public function insert($table, $data) {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $columnsList = implode(',', $columns);

        $sql = "INSERT INTO {$table} ({$columnsList}) VALUES ({$placeholders})";
        $stmt = $this->prepare($sql);

        if (!$stmt) {
            $this->log('Prepare Error: ' . $this->conn->error);
            return false;
        }

        $types = $this->getTypes($values);
        $stmt->bind_param($types, ...$values);

        if ($stmt->execute()) {
            $lastId = $this->conn->insert_id;
            $stmt->close();
            return $lastId;
        } else {
            $this->log('Execute Error: ' . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function update($table, $data, $where) {
        $setClause = [];
        $values = [];

        foreach ($data as $column => $value) {
            $setClause[] = "{$column} = ?";
            $values[] = $value;
        }

        $whereClause = [];
        $whereParts = [];
        foreach ($where as $column => $value) {
            $whereClause[] = "{$column} = ?";
            $values[] = $value;
        }

        $sql = "UPDATE {$table} SET " . implode(',', $setClause) . " WHERE " . implode(' AND ', $whereClause);
        $stmt = $this->prepare($sql);

        if (!$stmt) {
            $this->log('Prepare Error: ' . $this->conn->error);
            return false;
        }

        $types = $this->getTypes($values);
        $stmt->bind_param($types, ...$values);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $this->log('Execute Error: ' . $stmt->error);
            $stmt->close();
            return false;
        }
    }

    public function select($table, $columns = '*', $where = null, $limit = null) {
        $sql = "SELECT {$columns} FROM {$table}";
        $values = [];

        if ($where) {
            $whereClause = [];
            foreach ($where as $column => $value) {
                $whereClause[] = "{$column} = ?";
                $values[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        if (empty($values)) {
            $result = $this->query($sql);
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }

        $stmt = $this->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $types = $this->getTypes($values);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function selectOne($table, $columns = '*', $where = null) {
        $data = $this->select($table, $columns, $where, 1);
        return count($data) > 0 ? $data[0] : null;
    }

    public function delete($table, $where) {
        $whereClause = [];
        $values = [];

        foreach ($where as $column => $value) {
            $whereClause[] = "{$column} = ?";
            $values[] = $value;
        }

        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereClause);
        $stmt = $this->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $types = $this->getTypes($values);
        $stmt->bind_param($types, ...$values);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $stmt->close();
            return false;
        }
    }

    public function count($table, $where = null) {
        $sql = "SELECT COUNT(*) as count FROM {$table}";
        $values = [];

        if ($where) {
            $whereClause = [];
            foreach ($where as $column => $value) {
                $whereClause[] = "{$column} = ?";
                $values[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }

        if (empty($values)) {
            $result = $this->query($sql);
            $row = $result->fetch_assoc();
            return $row['count'];
        }

        $stmt = $this->prepare($sql);
        $types = $this->getTypes($values);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['count'];
    }

    private function getTypes($values) {
        $types = '';
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    private function log($message) {
        if (DEBUG) {
            error_log($message);
        }
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }

    public function __destruct() {
        $this->close();
    }
}
