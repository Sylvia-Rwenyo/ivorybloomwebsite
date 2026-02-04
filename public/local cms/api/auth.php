<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CORS and headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Respond to OPTIONS preflight and exit
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Helper: output JSON and exit
function json_out($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// Return current user from session
if ($action === 'current' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_SESSION['user'])) {
        json_out(['success' => true, 'user' => $_SESSION['user']]);
    } else {
        json_out(['success' => false, 'message' => 'Not authenticated'], 200);
    }
}

// Logout
if ($action === 'logout') {
    session_unset();
    session_destroy();
    json_out(['success' => true, 'message' => 'Logged out']);
}

// Handle login
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'));
    if (empty($data->username) || empty($data->password)) {
        json_out(['success' => false, 'message' => 'Username and password are required'], 400);
    }

    $query = "SELECT id, username, email, password, full_name, role, is_active FROM users WHERE (username = :username OR email = :username) LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':username', $data->username);
    $stmt->execute();

    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ((int)$row['is_active'] !== 1) {
            json_out(['success' => false, 'message' => 'Account inactive'], 403);
        }

        if (password_verify($data->password, $row['password'])) {
            // Update last login
            try {
                $updateQuery = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bindParam(':id', $row['id']);
                $updateStmt->execute();
            } catch (Exception $e) {
                // non-fatal
            }

            // Log activity if table exists (best-effort)
            try {
                $logQuery = "INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (:user_id, 'login', 'User logged in successfully', :ip_address)";
                $logStmt = $conn->prepare($logQuery);
                $logStmt->bindParam(':user_id', $row['id']);
                $logStmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
                $logStmt->execute();
            } catch (Exception $e) {
                // ignore logging errors
            }

            // Store minimal user info in session
            $user = [
                'id' => $row['id'],
                'username' => $row['username'],
                'email' => $row['email'],
                'full_name' => $row['full_name'],
                'role' => $row['role']
            ];
            $_SESSION['user'] = $user;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_role'] = $row['role'];

            json_out(['success' => true, 'message' => 'Login successful', 'user' => $user]);
        } else {
            json_out(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
    } else {
        json_out(['success' => false, 'message' => 'Invalid credentials'], 401);
    }
}

// Create new user (admin action)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // allow admins only
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        json_out(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $data = json_decode(file_get_contents('php://input'));
    if (empty($data->username) || empty($data->password) || empty($data->email) || empty($data->full_name)) {
        json_out(['success' => false, 'message' => 'Missing required fields'], 400);
    }

    $hashed = password_hash($data->password, PASSWORD_DEFAULT);
    $role = isset($data->role) ? $data->role : 'editor';

    try {
        $insert = "INSERT INTO users (username, password, email, full_name, role) VALUES (:username, :password, :email, :full_name, :role)";
        $stmt = $conn->prepare($insert);
        $stmt->bindParam(':username', $data->username);
        $stmt->bindParam(':password', $hashed);
        $stmt->bindParam(':email', $data->email);
        $stmt->bindParam(':full_name', $data->full_name);
        $stmt->bindParam(':role', $role);
        $stmt->execute();
        json_out(['success' => true, 'message' => 'User created']);
    } catch (Exception $e) {
        json_out(['success' => false, 'message' => 'User creation failed: ' . $e->getMessage()], 500);
    }
}

// List users (admin only)
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        json_out(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $query = "SELECT id, username, email, full_name, role, last_login FROM users ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    json_out(['success' => true, 'users' => $users]);
}

// Update user (admin only)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        json_out(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id === 0) json_out(['success' => false, 'message' => 'Missing id'], 400);

    $data = json_decode(file_get_contents('php://input'));
    if (!$data) json_out(['success' => false, 'message' => 'Invalid input'], 400);

    // Build update set
    $fields = [];
    $params = [];
    if (isset($data->username) && trim($data->username) !== '') { 
        $fields[] = 'username = :username'; 
        $params[':username'] = trim($data->username); 
    }
    if (isset($data->email) && trim($data->email) !== '') { 
        $fields[] = 'email = :email'; 
        $params[':email'] = trim($data->email); 
    }
    if (isset($data->full_name) && trim($data->full_name) !== '') { 
        $fields[] = 'full_name = :full_name'; 
        $params[':full_name'] = trim($data->full_name); 
    }
    if (isset($data->role) && trim($data->role) !== '') { 
        $fields[] = 'role = :role'; 
        $params[':role'] = trim($data->role); 
    }
    if (isset($data->is_active)) { 
        $fields[] = 'is_active = :is_active'; 
        $params[':is_active'] = $data->is_active; 
    }
    if (isset($data->password) && trim($data->password) !== '') {
        $fields[] = 'password = :password';
        $params[':password'] = password_hash($data->password, PASSWORD_DEFAULT);
    }

    if (empty($fields)) {
        json_out(['success' => false, 'message' => 'No fields to update'], 400);
    }

    try {
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Update session if user updated their own account
        if ($id === $_SESSION['user']['id']) {
            if (isset($data->username)) $_SESSION['user']['username'] = $data->username;
            if (isset($data->email)) $_SESSION['user']['email'] = $data->email;
            if (isset($data->full_name)) $_SESSION['user']['full_name'] = $data->full_name;
            if (isset($data->role)) $_SESSION['user']['role'] = $data->role;
        }
        
        json_out(['success' => true, 'message' => 'User updated']);
    } catch (Exception $e) {
        json_out(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()], 500);
    }
}

// Delete user (admin only)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        json_out(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id === 0) json_out(['success' => false, 'message' => 'Missing id'], 400);
    if ($id === $_SESSION['user']['id']) json_out(['success' => false, 'message' => 'Cannot delete current user'], 400);

    try {
        $del = "DELETE FROM users WHERE id = :id";
        $stmt = $conn->prepare($del);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        json_out(['success' => true, 'message' => 'User deleted']);
    } catch (Exception $e) {
        json_out(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()], 500);
    }
}

// If we reached here, method/action not handled
json_out(['success' => false, 'message' => 'Action not supported or method not allowed'], 405);
?>