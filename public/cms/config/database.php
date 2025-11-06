<?php
// Database Configuration

class Database {
    private $host = "localhost";
    private $db_name = "saqqaekn_ivorybloomdb";
    private $username = "saqqaekn_ivorybloomadmin";
    private $password = "dBVkU{+~rpzen[oO";
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
        } catch(PDOException $e) {
            // Log to PHP error log
            error_log("Database Connection Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            // Return detailed error in JSON (shows in browser console)
            header('Content-Type: application/json');
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'host' => $this->host,
                'database' => $this->db_name,
                'trace' => $e->getTraceAsString()
            ]));
        }
        
        return $this->conn;
    }
}

?>