<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'syndicate_platform';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                )
            );
        } catch(PDOException $exception) {
            // Show error in development
            if (defined('APP_DEBUG') && APP_DEBUG) {
                die("Connection error: " . $exception->getMessage());
            } else {
                error_log("Database connection error: " . $exception->getMessage());
                die("Database connection failed. Please check your configuration.");
            }
        }
        return $this->conn;
    }
}
?>