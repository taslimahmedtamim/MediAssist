<?php
/**
 * MediAssist+ Database Configuration
 * PDO Database Connection Class
 * 
 * SETUP: Copy this file to database.php and fill in your credentials
 */

class Database {
    private $host = "localhost";
    private $db_name = "mediassist";
    private $username = "your_username";
    private $password = "your_password";
    private $conn;

    /**
     * Get database connection
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            return null;
        }

        return $this->conn;
    }
}
