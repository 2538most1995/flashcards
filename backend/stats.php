<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 1;

// Overall counts
$totalCards = (int)$pdo->query("SELECT COUNT(*) FROM flashcards")->fetchColumn();

// Progress breakdown by status
$stmt = $pdo->prepare("SELECT 
    status, COUNT(*) as cnt 
    FROM card_progress 
    WHERE user_id = ? 
    GROUP BY status");
$stmt->execute([$userId]);
$counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$mastered = (int)($counts['mastered'] ?? 0);
$review = (int)($counts['review'] ?? 0);
$learning = (int)($counts['learning'] ?? 0);
$unseen = max(0, $totalCards - ($mastered + $review + $learning));
$percent = $totalCards > 0 ? round(($mastered / $totalCards) * 100, 1) : 0;

// Category breakdown
$catSql = "SELECT 
    f.category,
    COUNT(f.id) as total,
    SUM(CASE WHEN cp.status = 'mastered' THEN 1 ELSE 0 END) as mastered,
    SUM(CASE WHEN cp.status = 'review' THEN 1 ELSE 0 END) as review,
    SUM(CASE WHEN cp.status = 'learning' THEN 1 ELSE 0 END) as learning,
    SUM(CASE WHEN cp.status IS NULL OR cp.status = 'unseen' THEN 1 ELSE 0 END) as unseen
FROM flashcards f
LEFT JOIN card_progress cp ON f.num = cp.card_num AND cp.user_id = :user_id
GROUP BY f.category
ORDER BY f.category ASC";

$catStmt = $pdo->prepare($catSql);
$catStmt->execute([':user_id' => $userId]);
$categories = $catStmt->fetchAll();

foreach ($categories as &$c) {
    $c['total'] = (int)$c['total'];
    $c['mastered'] = (int)$c['mastered'];
    $c['review'] = (int)$c['review'];
    $c['learning'] = (int)$c['learning'];
    $c['unseen'] = (int)$c['unseen'];
    $c['percent'] = $c['total'] > 0 ? round(($c['mastered'] / $c['total']) * 100) : 0;
}

echo json_encode([
    'success' => true,
    'user_id' => $userId,
    'overview' => [
        'total' => $totalCards,
        'mastered' => $mastered,
        'review' => $review,
        'learning' => $learning,
        'unseen' => $unseen,
        'percent_mastered' => $percent
    ],
    'categories' => $categories
], JSON_UNESCAPED_UNICODE);
