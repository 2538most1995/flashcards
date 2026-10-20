<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT u.id, u.username, u.display_name, u.created_at,
        COUNT(cp.id) as total_reviewed,
        SUM(CASE WHEN cp.status = 'mastered' THEN 1 ELSE 0 END) as mastered_count
        FROM users u
        LEFT JOIN card_progress cp ON u.id = cp.user_id
        GROUP BY u.id, u.username, u.display_name, u.created_at
        ORDER BY u.id ASC");
    $users = $stmt->fetchAll();

    foreach ($users as &$u) {
        $u['id'] = (int)$u['id'];
        $u['total_reviewed'] = (int)($u['total_reviewed'] ?? 0);
        $u['mastered_count'] = (int)($u['mastered_count'] ?? 0);
    }

    echo json_encode(['success' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? $_POST;

    $username = trim($input['username'] ?? '');
    $displayName = trim($input['display_name'] ?? '');

    if (empty($username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'กรุณาระบุชื่อผู้ใช้งาน']);
        exit;
    }

    if (empty($displayName)) {
        $displayName = $username;
    }

    // Check existing
    $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $chk->execute([$username]);
    if ($chk->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว']);
        exit;
    }

    $ins = $pdo->prepare("INSERT INTO users (username, display_name) VALUES (?, ?)");
    $ins->execute([$username, $displayName]);
    $newId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $newId,
            'username' => $username,
            'display_name' => $displayName
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
