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
    $category = $_GET['category'] ?? '';
    $status = $_GET['status'] ?? '';
    $query = $_GET['q'] ?? '';
    $cardNum = isset($_GET['num']) ? (int)$_GET['num'] : 0;

    $sql = "SELECT 
                f.id,
                f.num,
                f.word,
                f.pos,
                f.v1,
                f.v2,
                f.v3,
                f.meaning,
                f.category,
                f.reading,
                f.example,
                f.example_th,
                f.cloze,
                f.icon,
                f.synonyms,
                f.antonyms,
                f.memory_hook,
                COALESCE(cp.status, 'unseen') AS status,
                COALESCE(cp.review_count, 0) AS review_count,
                cp.last_reviewed_at
            FROM flashcards f
            LEFT JOIN card_progress cp ON f.num = cp.card_num AND cp.user_id = :user_id
            WHERE 1=1";

    $params = [':user_id' => $userId];

    if ($cardNum > 0) {
        $sql .= " AND f.num = :num";
        $params[':num'] = $cardNum;
    }

    if (!empty($category) && $category !== 'ทั้งหมด') {
        $sql .= " AND f.category = :category";
        $params[':category'] = $category;
    }

    if (!empty($status) && $status !== 'all') {
        if ($status === 'unseen') {
            $sql .= " AND (cp.status IS NULL OR cp.status = 'unseen')";
        } else {
            $sql .= " AND cp.status = :status";
            $params[':status'] = $status;
        }
    }

    if (!empty($query)) {
        $sql .= " AND (
            f.word LIKE :q OR 
            f.v1 LIKE :q OR 
            f.v2 LIKE :q OR 
            f.v3 LIKE :q OR 
            f.meaning LIKE :q OR 
            f.reading LIKE :q OR 
            f.example LIKE :q OR 
            f.example_th LIKE :q OR 
            f.synonyms LIKE :q
        )";
        $params[':q'] = "%{$query}%";
    }

    $sql .= " ORDER BY f.num ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cards = $stmt->fetchAll();

    // Type casting
    foreach ($cards as &$card) {
        $card['id'] = (int)$card['id'];
        $card['num'] = (int)$card['num'];
        $card['review_count'] = (int)$card['review_count'];

        if (($card['category'] ?? '') === 'คำกริยา (Verb)') {
            if (strpos($card['word'], '–') !== false || strpos($card['word'], '-') !== false) {
                $parts = array_map('trim', preg_split('/[–\-]/u', $card['word']));
                $card['word'] = $parts[0];
            }
        }
    }
    unset($card);

    echo json_encode(['success' => true, 'total' => count($cards), 'cards' => $cards], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? $_POST;
    $action = $input['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        $num = (int)($input['num'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM flashcards WHERE id = ?");
            $stmt->execute([$id]);
        } elseif ($num > 0) {
            $stmt = $pdo->prepare("DELETE FROM flashcards WHERE num = ?");
            $stmt->execute([$num]);
        }
        echo json_encode(['success' => true, 'message' => 'Card deleted successfully']);
        exit;
    }

    // Create or update card
    $num = (int)($input['num'] ?? 0);
    $word = trim($input['word'] ?? '');
    $pos = trim($input['pos'] ?? '');
    $v1 = trim($input['v1'] ?? '');
    $v2 = trim($input['v2'] ?? '');
    $v3 = trim($input['v3'] ?? '');
    $meaning = trim($input['meaning'] ?? '');
    $category = trim($input['category'] ?? 'คำนาม (Noun)');
    $reading = trim($input['reading'] ?? '');
    $example = trim($input['example'] ?? '');
    $example_th = trim($input['example_th'] ?? '');
    $cloze = trim($input['cloze'] ?? '');
    $icon = trim($input['icon'] ?? '📖');
    $synonyms = trim($input['synonyms'] ?? '');
    $antonyms = trim($input['antonyms'] ?? '');
    $memory_hook = trim($input['memory_hook'] ?? '');

    if ($category === 'คำกริยา (Verb)' && (strpos($word, '–') !== false || strpos($word, '-') !== false)) {
        $parts = array_map('trim', preg_split('/[–\-]/u', $word));
        $word = $parts[0];
    }

    if (empty($word) || empty($meaning)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'กรุณากรอกคำศัพท์และความหมาย']);
        exit;
    }

    if (empty($cloze) && !empty($example)) {
        $cloze = preg_replace('/\b' . preg_quote($word, '/') . '\b/i', '[ ______ ]', $example);
    }

    if ($num <= 0) {
        // Find next num
        $maxNum = (int)$pdo->query("SELECT MAX(num) FROM flashcards")->fetchColumn();
        $num = $maxNum + 1;
    }

    // Check if card with this num exists
    $exists = $pdo->prepare("SELECT id FROM flashcards WHERE num = ?");
    $exists->execute([$num]);
    $cardId = $exists->fetchColumn();

    if ($cardId) {
        $stmt = $pdo->prepare("UPDATE flashcards SET 
            word = :word, pos = :pos, v1 = :v1, v2 = :v2, v3 = :v3, meaning = :meaning, category = :category,
            reading = :reading, example = :example, example_th = :example_th,
            cloze = :cloze, icon = :icon, synonyms = :synonyms, antonyms = :antonyms,
            memory_hook = :memory_hook
            WHERE num = :num");
    } else {
        $stmt = $pdo->prepare("INSERT INTO flashcards 
            (num, word, pos, v1, v2, v3, meaning, category, reading, example, example_th, cloze, icon, synonyms, antonyms, memory_hook)
            VALUES 
            (:num, :word, :pos, :v1, :v2, :v3, :meaning, :category, :reading, :example, :example_th, :cloze, :icon, :synonyms, :antonyms, :memory_hook)");
    }

    $stmt->execute([
        ':num' => $num,
        ':word' => $word,
        ':pos' => $pos,
        ':v1' => !empty($v1) ? $v1 : null,
        ':v2' => !empty($v2) ? $v2 : null,
        ':v3' => !empty($v3) ? $v3 : null,
        ':meaning' => $meaning,
        ':category' => $category,
        ':reading' => $reading,
        ':example' => $example,
        ':example_th' => $example_th,
        ':cloze' => $cloze,
        ':icon' => $icon,
        ':synonyms' => $synonyms,
        ':antonyms' => $antonyms,
        ':memory_hook' => $memory_hook
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'บันทึกคำศัพท์ลงฐานข้อมูล MySQL สำเร็จ',
        'card' => [
            'num' => $num,
            'word' => $word,
            'v1' => $v1,
            'v2' => $v2,
            'v3' => $v3,
            'meaning' => $meaning,
            'category' => $category
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
