<?php
// ====================================================
// Ivory Bloom CMS - Events API
// FULL CRUD + MULTI-FILE (IMAGES + VIDEOS)
// XAMPP SAFE: No GD dependency
// DEBUG: Errors logged + JSON response
// ====================================================

ini_set('display_errors', 0);  // Set to 1 only for debugging
ini_set('log_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../config/database.php';
$database = new Database();
$conn = $database->getConnection();
session_start();

$sessionUserId = $_SESSION['user']['id'] ?? null;

$uploadDir          = '../../assets/uploads/';
$allowedImageTypes  = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
$allowedVideoTypes  = ['video/mp4','video/webm','video/ogg','video/quicktime'];
$maxFileSize        = 50 * 1024 * 1024; // 50 MB

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

/* ------------------------------------------------------------------
   UTILS
------------------------------------------------------------------- */
function sanitizeFileName(string $filename): string {
    return preg_replace('/_+/', '_', preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename));
}

function generateUniqueFileName(string $originalName): string {
    $ext  = pathinfo($originalName, PATHINFO_EXTENSION);
    $base = sanitizeFileName(pathinfo($originalName, PATHINFO_FILENAME));
    return $base . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
}

function handleFileUpload(array $file): array {
    global $uploadDir, $allowedImageTypes, $allowedVideoTypes, $maxFileSize;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Upload error code: " . $file['error']);
    }
    if ($file['size'] > $maxFileSize) {
        throw new Exception("File too large: " . $file['size']);
    }

    $type = mime_content_type($file['tmp_name']) ?: $file['type'];

    $allAllowed = array_merge($allowedImageTypes, $allowedVideoTypes);
    if (!in_array($type, $allAllowed)) {
        // Fallback to extension-based MIME type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extToMime = [
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'mp4'   => 'video/mp4',
            'webm'  => 'video/webm',
            'ogg'   => 'video/ogg',
            'ogv'   => 'video/ogg',
            'mov'   => 'video/quicktime',
        ];
        if (isset($extToMime[$ext])) {
            $type = $extToMime[$ext];
        } else {
            throw new Exception("Invalid file type: $type / ext: $ext");
        }
    }

    $isImage = in_array($type, $allowedImageTypes);
    $isVideo = in_array($type, $allowedVideoTypes);

    if (!$isImage && !$isVideo) {
        throw new Exception("Invalid file type: $type");
    }

    $name = generateUniqueFileName($file['name']);
    $path = $uploadDir . $name;

    if (!rename($file['tmp_name'], $path)) {
        throw new Exception("Failed to move uploaded file");
    }

    return [
        'file_path' => '/assets/uploads/' . $name,
        'file_type' => $isVideo ? 'video' : 'image',
        'name'      => $name
    ];
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

/* ------------------------------------------------------------------
   CRUD HELPERS
------------------------------------------------------------------- */
function getEvents(PDO $conn, ?int $id = null): array {
    $sql = "SELECT e.*,
              GROUP_CONCAT(DISTINCT es.service_name) AS services,
              GROUP_CONCAT(DISTINCT CONCAT(em.id,'|',em.file_path,'|',em.file_type,'|',COALESCE(em.alt_text,'')) SEPARATOR ';;') AS media
            FROM events e
            LEFT JOIN event_services es ON e.id = es.event_id
            LEFT JOIN event_media em   ON e.id = em.event_id
            WHERE e.is_active = 1
            " . ($id ? " AND e.id = ?" : "") . "
            GROUP BY e.id
            ORDER BY e.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($id ? [$id] : []);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$e) {
        $e['services'] = $e['services'] ? explode(',', $e['services']) : [];
        $media = [];
        if ($e['media']) {
            foreach (explode(';;', $e['media']) as $m) {
                [$mid, $p, $t, $a] = explode('|', $m . '|||', 4);
                $media[] = [
                    'id'        => (int)$mid,
                    'file_path' => $p,
                    'file_type' => $t,
                    'alt_text'  => $a
                ];
            }
        }
        $e['media'] = $e['images'] = $media;
        $e['media_count']   = count($media);
        $e['service_count'] = count($e['services']);
    }
    return $rows;
}

function createEvent(PDO $conn, array $data, array $files): int {
    $conn->beginTransaction();
    try {
        $slug = strtolower(preg_replace('/[^a-z0-9-]+/', '-', trim($data['name'])));
        $stmt = $conn->prepare("INSERT INTO events
            (name,slug,description,seo_title,seo_description,seo_keywords,created_by,is_active,created_at)
            VALUES (?,?,?,?,?,?,?,1,NOW())");
        $stmt->execute([
            $data['name'], $slug,
            $data['description'] ?? null,
            $data['seo_title'] ?? null,
            $data['seo_description'] ?? null,
            $data['seo_keywords'] ?? null,
            $data['created_by']
        ]);
        $id = (int)$conn->lastInsertId();

        if (!empty($data['services'])) {
            $svc = $conn->prepare("INSERT INTO event_services (event_id,service_name) VALUES (?,?)");
            foreach ($data['services'] as $s) if (trim($s)) $svc->execute([$id, trim($s)]);
        }

        if ($files) {
            $media = $conn->prepare("INSERT INTO event_media (event_id,file_path,file_type,alt_text) VALUES (?,?,?,?)");
            foreach ($files as $f) {
                $media->execute([$id, $f['file_path'], $f['file_type'], $f['name']]);
            }
        }

        $conn->commit();
        return $id;
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function updateEvent(PDO $conn, int $id, array $data, array $newFiles, array $removeMediaIds): void {
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("UPDATE events SET
            name=?, description=?, seo_title=?, seo_description=?, seo_keywords=?, is_active=?
            WHERE id=?");
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['seo_title'] ?? null,
            $data['seo_description'] ?? null,
            $data['seo_keywords'] ?? null,
            $data['is_active'] ?? 1,
            $id
        ]);

        $conn->prepare("DELETE FROM event_services WHERE event_id=?")->execute([$id]);
        if (!empty($data['services'])) {
            $svc = $conn->prepare("INSERT INTO event_services (event_id,service_name) VALUES (?,?)");
            foreach ($data['services'] as $s) if (trim($s)) $svc->execute([$id, trim($s)]);
        }

        if ($removeMediaIds) {
            $placeholders = str_repeat('?,', count($removeMediaIds) - 1) . '?';
            $stmt = $conn->prepare("SELECT file_path FROM event_media WHERE id IN ($placeholders)");
            $stmt->execute($removeMediaIds);
            $paths = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $del = $conn->prepare("DELETE FROM event_media WHERE id = ?");
            foreach ($removeMediaIds as $mid) $del->execute([$mid]);

            foreach ($paths as $p) {
                $full = '../../' . ltrim($p, '/');
                if (file_exists($full)) @unlink($full);
            }
        }

        if ($newFiles) {
            $ins = $conn->prepare("INSERT INTO event_media (event_id,file_path,file_type,alt_text) VALUES (?,?,?,?)");
            foreach ($newFiles as $f) {
                $ins->execute([$id, $f['file_path'], $f['file_type'], $f['name']]);
            }
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function deleteEvent(PDO $conn, int $id): void {
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("SELECT file_path FROM event_media WHERE event_id=?");
        $stmt->execute([$id]);
        $paths = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $conn->prepare("DELETE FROM events WHERE id=?")->execute([$id]);

        foreach ($paths as $p) {
            $full = '../../' . ltrim($p, '/');
            if (file_exists($full)) @unlink($full);
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

/* ------------------------------------------------------------------
   FILE PARSING
------------------------------------------------------------------- */
function parseUploadedFiles(): array {
    $uploaded = [];
    if (empty($_FILES['files']['name']) || !is_array($_FILES['files']['name'])) return $uploaded;

    for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $file = [
            'name'     => $_FILES['files']['name'][$i],
            'type'     => $_FILES['files']['type'][$i],
            'tmp_name' => $_FILES['files']['tmp_name'][$i],
            'error'    => $_FILES['files']['error'][$i],
            'size'     => $_FILES['files']['size'][$i]
        ];
        $uploaded[] = handleFileUpload($file);
    }
    return $uploaded;
}

function parsePutMultipart(): array {
    $data = $newFiles = $removeIds = [];

    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === false) {
        parse_str(file_get_contents('php://input'), $data);
        return [$data, $newFiles, $removeIds];
    }

    $boundary = '';
    if (preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $m)) {
        $boundary = trim($m[1], '"');
    }
    if (!$boundary) throw new Exception('Missing multipart boundary');

    $raw = file_get_contents('php://input');
    $parts = preg_split('/-{2,}' . preg_quote($boundary, '/') . '(?:--)?/', $raw);
    array_pop($parts);

    foreach ($parts as $part) {
        if (trim($part) === '') continue;
        [$headers, $body] = explode("\r\n\r\n", $part, 2);
        $body = substr($body, 0, -2);

        $name = $filename = '';
        if (preg_match('/name="([^"]+)"/', $headers, $m)) $name = $m[1];
        if (preg_match('/filename="([^"]+)"/', $headers, $m)) $filename = $m[1];

        if ($filename) {
            $tmp = tempnam(sys_get_temp_dir(), 'put_');
            file_put_contents($tmp, $body);
            $file = [
                'name'     => $filename,
                'type'     => mime_content_type($tmp) ?: 'application/octet-stream',
                'tmp_name' => $tmp,
                'error'    => 0,
                'size'     => filesize($tmp)
            ];
            $newFiles[] = handleFileUpload($file);
            @unlink($tmp);
        } else {
            $data[$name] = $body;
            if ($name === 'remove_media') {
                $removeIds[] = (int)$body;
            }
        }
    }

    if (empty($removeIds) && !empty($data['remove_media'])) {
        $removeIds = array_map('intval', json_decode($data['remove_media'], true) ?: []);
    }
    return [$data, $newFiles, $removeIds];
}

/* ------------------------------------------------------------------
   ROUTER
------------------------------------------------------------------- */
$method = $_SERVER['REQUEST_METHOD'];

try {
    if (in_array($method, ['POST', 'PUT', 'DELETE']) && !$sessionUserId) {
        jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    switch ($method) {
        case 'GET':
            $id = $_GET['id'] ?? null;
            $events = getEvents($conn, $id ? (int)$id : null);
            if ($id) {
                $event = $events[0] ?? null;
                jsonResponse($event ? ['success' => true, 'event' => $event] : ['success' => false, 'message' => 'Not found'], $event ? 200 : 404);
            } else {
                jsonResponse(['success' => true, 'events' => $events]);
            }
            break;

        case 'POST':
            $uploaded = parseUploadedFiles();

            $post = $_POST;
            $name = trim($post['name'] ?? '');
            if ($name === '') throw new Exception('Event name is required');

            // SAFE JSON DECODE
            $servicesJson = $post['services'] ?? '[]';
            $services = is_string($servicesJson) ? (json_decode($servicesJson, true) ?: []) : [];
            $services = array_map('trim', array_filter((array)$services, 'strlen'));

            $data = [
                'name'           => $name,
                'description'    => $post['description'] ?? '',
                'seo_title'      => $post['seo_title'] ?? '',
                'seo_description'=> $post['seo_description'] ?? '',
                'seo_keywords'   => $post['seo_keywords'] ?? '',
                'is_active'      => $post['is_active'] ?? '1',
                'services'       => $services,
                'created_by'     => $sessionUserId
            ];

            $newId = createEvent($conn, $data, $uploaded);
            jsonResponse(['success' => true, 'message' => 'Event created', 'event_id' => $newId], 201);
            break;

        case 'PUT':
            parse_str($_SERVER['QUERY_STRING'], $q);
            $id = $q['id'] ?? null;
            if (!$id) throw new Exception('Event ID required');

            [$rawData, $newFiles, $removeIds] = parsePutMultipart();

            $name = trim($rawData['name'] ?? '');
            if ($name === '') throw new Exception('Event name required');

            $servicesJson = $rawData['services'] ?? '[]';
            $services = is_string($servicesJson) ? (json_decode($servicesJson, true) ?: []) : [];
            $services = array_map('trim', array_filter((array)$services, 'strlen'));

            $data = [
                'name'            => $name,
                'description'     => $rawData['description'] ?? '',
                'seo_title'       => $rawData['seo_title'] ?? '',
                'seo_description' => $rawData['seo_description'] ?? '',
                'seo_keywords'    => $rawData['seo_keywords'] ?? '',
                'is_active'       => $rawData['is_active'] ?? '1',
                'services'        => $services
            ];

            updateEvent($conn, (int)$id, $data, $newFiles, $removeIds);
            jsonResponse(['success' => true, 'message' => 'Event updated']);
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) throw new Exception('Event ID required');
            deleteEvent($conn, (int)$id);
            jsonResponse(['success' => true, 'message' => 'Event deleted']);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    jsonResponse(['success' => false, 'message' => 'Database error'], 500);
}
?>