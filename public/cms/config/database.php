<?php
// Database Configuration
// Place in: cms/config/database.php

class Database {
    private $host = "localhost";
    private $db_name = "saqqaekn_ivorybloomdb";
    private $username = "ivorybloomadminsaqqaekn_ivorybloomadmin";
    private $password = "Y[L+80_!1RUaYW]w";
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
            error_log("Connection error: " . $e->getMessage());
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed'
            ]));
        }
        
        return $this->conn;
    }
}

?>