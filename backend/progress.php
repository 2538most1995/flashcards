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
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 1;

    // Get all ratings for this user
    $stmt = $pdo->prepare("SELECT card_num, status, review_count, last_reviewed_at FROM card_progress WHERE user_id = ?");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $mastery = [];
    $masteredCount = 0;
    $reviewCount = 0;
    $learningCount = 0;

    foreach ($rows as $row) {
        $num = (int)$row['card_num'];
        $st = $row['status'];
        $mastery[$num] = $st;

        if ($st === 'mastered') $masteredCount++;
        elseif ($st === 'review') $reviewCount++;
        elseif ($st === 'learning') $learningCount++;
    }

    $totalCards = (int)$pdo->query("SELECT COUNT(*) FROM flashcards")->fetchColumn();
    $unseenCount = max(0, $totalCards - ($masteredCount + $reviewCount + $learningCount));
    $percent = $totalCards > 0 ? round(($masteredCount / $totalCards) * 100) : 0;

    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'mastery' => $mastery,
        'stats' => [
            'mastered' => $masteredCount,
            'review' => $reviewCount,
            'learning' => $learningCount,
            'unseen' => $unseenCount,
            'total' => $totalCards,
            'percent_mastered' => $percent
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? $_POST;
    $action = $input['action'] ?? 'rate';
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : 1;

    if ($action === 'reset') {
        $stmt = $pdo->prepare("DELETE FROM card_progress WHERE user_id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'message' => 'รีเซ็ตข้อมูลความจำเรียบร้อยแล้ว']);
        exit;
    }

    $cardNum = (int)($input['card_num'] ?? 0);
    $status = trim($input['status'] ?? '');

    $validStatuses = ['unseen', 'learning', 'review', 'mastered'];
    if ($cardNum <= 0 || !in_array($status, $validStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ถูกต้อง']);
        exit;
    }

    // Upsert into card_progress in MySQL
    $sql = "INSERT INTO card_progress (user_id, card_num, status, review_count, last_reviewed_at)
            VALUES (:user_id, :card_num, :status, 1, NOW())
            ON DUPLICATE KEY UPDATE 
                status = :status_up,
                review_count = review_count + 1,
                last_reviewed_at = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':card_num' => $cardNum,
        ':status' => $status,
        ':status_up' => $status
    ]);

    // Return updated user stats
    $countStmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM card_progress WHERE user_id = ? GROUP BY status");
    $countStmt->execute([$userId]);
    $counts = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $mastered = (int)($counts['mastered'] ?? 0);
    $review = (int)($counts['review'] ?? 0);
    $learning = (int)($counts['learning'] ?? 0);
    $totalCards = (int)$pdo->query("SELECT COUNT(*) FROM flashcards")->fetchColumn();
    $unseen = max(0, $totalCards - ($mastered + $review + $learning));
    $percent = $totalCards > 0 ? round(($mastered / $totalCards) * 100) : 0;

    echo json_encode([
        'success' => true,
        'card_num' => $cardNum,
        'status' => $status,
        'stats' => [
            'mastered' => $mastered,
            'review' => $review,
            'learning' => $learning,
            'unseen' => $unseen,
            'total' => $totalCards,
            'percent_mastered' => $percent
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
