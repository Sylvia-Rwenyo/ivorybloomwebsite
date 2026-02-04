<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();
session_start();

// session user id (if logged in)
$sessionUserId = isset($_SESSION['user']) ? $_SESSION['user']['id'] : null;

// ===========================
// Fetch all testimonials
// ===========================
function getTestimonials($conn) {
    $query = "SELECT t.*, u.username AS created_by_username 
              FROM testimonials t
              LEFT JOIN users u ON t.created_by = u.id
              ORDER BY t.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===========================
// Create new testimonial
// ===========================
function createTestimonial($conn, $data) {
    try {
        $conn->beginTransaction();

        $author_name = isset($data->author_name) ? trim($data->author_name) : null;
        $testimonial_text = isset($data->testimonial_text) ? trim($data->testimonial_text) : null;
        $created_by = isset($data->created_by) ? $data->created_by : null;
        $is_active = isset($data->is_active) ? intval($data->is_active) : 1;

        if (empty($author_name) || empty($testimonial_text)) {
            throw new Exception('author_name and testimonial_text are required');
        }

        $query = "INSERT INTO testimonials 
                    (author_name, testimonial_text,  is_active, created_by, created_at, updated_at)
                  VALUES 
                    (:author_name, :testimonial_text, :is_active, :created_by, NOW(), NOW())";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(":author_name", $author_name);
        $stmt->bindParam(":testimonial_text", $testimonial_text);
        $stmt->bindParam(":is_active", $is_active, PDO::PARAM_INT);
        $stmt->bindParam(":created_by", $created_by);

        $stmt->execute();
        $testimonialId = $conn->lastInsertId();

        // Log activity
        $logQuery = "INSERT INTO activity_log (user_id, action, table_name, record_id, details) 
                     VALUES (:user_id, 'create', 'testimonials', :record_id, :details)";
        $logStmt = $conn->prepare($logQuery);
        $details = "Created testimonial from: " . $author_name;
        $logStmt->bindParam(":user_id", $created_by);
        $logStmt->bindParam(":record_id", $testimonialId);
        $logStmt->bindParam(":details", $details);
        $logStmt->execute();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

// ===========================
// Update testimonial
// ===========================
function updateTestimonial($conn, $id, $data) {
    try {
        $conn->beginTransaction();

        $author_name = isset($data->author_name) ? trim($data->author_name) : null;
        $testimonial_text = isset($data->testimonial_text) ? trim($data->testimonial_text) : null;
        $is_active = isset($data->is_active) ? intval($data->is_active) : 1;
        $created_by = isset($data->created_by) ? $data->created_by : null;

        $query = "UPDATE testimonials 
                  SET author_name = :author_name,
                      testimonial_text = :testimonial_text,
                      is_active = :is_active,
                      updated_at = NOW()
                  WHERE id = :id";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(":author_name", $author_name);
        $stmt->bindParam(":testimonial_text", $testimonial_text);
        $stmt->bindParam(":is_active", $is_active, PDO::PARAM_INT);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $result = $stmt->execute();

        // Log activity
        if ($result) {
            $logQuery = "INSERT INTO activity_log (user_id, action, table_name, record_id, details)
                         VALUES (:user_id, 'update', 'testimonials', :record_id, :details)";
            $logStmt = $conn->prepare($logQuery);
            $details = "Updated testimonial from: " . $author_name;
            $logStmt->bindParam(":user_id", $created_by);
            $logStmt->bindParam(":record_id", $id);
            $logStmt->bindParam(":details", $details);
            $logStmt->execute();
        }

        $conn->commit();
        return $result;
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

// ===========================
// Delete testimonial
// ===========================
function deleteTestimonial($conn, $id, $userId) {
    try {
        $conn->beginTransaction();

        $query = "DELETE FROM testimonials WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $result = $stmt->execute();

        // Log activity
        if ($result) {
            $logQuery = "INSERT INTO activity_log (user_id, action, table_name, record_id, details)
                         VALUES (:user_id, 'delete', 'testimonials', :record_id, :details)";
            $logStmt = $conn->prepare($logQuery);
            $details = "Deleted testimonial with ID: " . $id;
            $logStmt->bindParam(":user_id", $userId);
            $logStmt->bindParam(":record_id", $id);
            $logStmt->bindParam(":details", $details);
            $logStmt->execute();
        }

        $conn->commit();
        return $result;
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

// ===========================
// Handle requests
// ===========================
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $id = $_GET['id'];
                $stmt = $conn->prepare("SELECT * FROM testimonials WHERE id = :id");
                $stmt->bindParam(":id", $id);
                $stmt->execute();
                $testimonial = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($testimonial) {
                    echo json_encode(["success" => true, "testimonial" => $testimonial]);
                } else {
                    http_response_code(404);
                    echo json_encode(["success" => false, "message" => "Testimonial not found"]);
                }
            } else {
                $testimonials = getTestimonials($conn);
                echo json_encode(["success" => true, "testimonials" => $testimonials]);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"));
            if (empty($data->created_by)) $data->created_by = $GLOBALS['sessionUserId'];
            if (createTestimonial($conn, $data)) {
                echo json_encode(["success" => true, "message" => "Testimonial created successfully"]);
            }
            break;

        case 'PUT':
            $data = json_decode(file_get_contents("php://input"));
            if (empty($data->created_by)) $data->created_by = $GLOBALS['sessionUserId'];
            $id = isset($_GET['id']) ? $_GET['id'] : die();

            if (updateTestimonial($conn, $id, $data)) {
                echo json_encode(["success" => true, "message" => "Testimonial updated successfully"]);
            }
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? $_GET['id'] : die();
            $userId = isset($_GET['user_id']) ? $_GET['user_id'] : $GLOBALS['sessionUserId'];

            if ($userId && deleteTestimonial($conn, $id, $userId)) {
                echo json_encode(["success" => true, "message" => "Testimonial deleted successfully"]);
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "User ID is required for deletion"]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Method not allowed"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "An error occurred: " . $e->getMessage()
    ]);
}
?>
