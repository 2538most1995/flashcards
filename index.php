<?php
require_once __DIR__ . '/config/database.php';

$pdo = null;
$dbError = null;
$users = [];
$totalCards = 0;
$initialUser = null;
$initialCards = [];
$initialMastery = [];
$initialStats = [
    'mastered' => 0,
    'review' => 0,
    'learning' => 0,
    'unseen' => 0,
    'total' => 0,
    'percent_mastered' => 0
];

try {
    $pdo = getDbConnection();
    
    // Fetch users
    $stmtUsers = $pdo->query("SELECT id, username, display_name FROM users ORDER BY id ASC");
    $users = $stmtUsers->fetchAll();
    if (!empty($users)) {
        $initialUser = $users[0];
    }

    $userId = $initialUser ? (int)$initialUser['id'] : 1;

    // Fetch initial cards with v1, v2, v3
    $stmtCards = $pdo->prepare("SELECT 
            f.id, f.num, f.word, f.pos, f.v1, f.v2, f.v3, f.meaning, f.category, f.reading,
            f.example, f.example_th, f.cloze, f.icon, f.synonyms, f.antonyms, f.memory_hook,
            COALESCE(cp.status, 'unseen') AS status,
            COALESCE(cp.review_count, 0) AS review_count
        FROM flashcards f
        LEFT JOIN card_progress cp ON f.num = cp.card_num AND cp.user_id = :user_id
        ORDER BY f.num ASC");
    $stmtCards->execute([':user_id' => $userId]);
    $initialCards = $stmtCards->fetchAll();
    $totalCards = count($initialCards);

    // Self-healing fallback for verbs in PHP
    foreach ($initialCards as &$card) {
        if (($card['category'] ?? '') === 'คำกริยา (Verb)') {
            if (empty($card['v1']) || empty($card['v2']) || empty($card['v3'])) {
                if (strpos($card['word'], '–') !== false || strpos($card['word'], '-') !== false) {
                    $parts = array_map('trim', preg_split('/[–\-]/u', $card['word']));
                    if (count($parts) >= 3) {
                        $card['v1'] = $parts[0];
                        $card['v2'] = $parts[1];
                        $card['v3'] = $parts[2];
                    }
                }
            }
            if (!empty($card['v1']) && !empty($card['v2']) && !empty($card['v3']) && (strpos($card['word'], '–') === false && strpos($card['word'], '-') === false)) {
                $card['word'] = "{$card['v1']} – {$card['v2']} – {$card['v3']}";
            }
        }
    }
    unset($card);

    // Fetch mastery
    $stmtProg = $pdo->prepare("SELECT card_num, status FROM card_progress WHERE user_id = ?");
    $stmtProg->execute([$userId]);
    while ($row = $stmtProg->fetch()) {
        $initialMastery[(int)$row['card_num']] = $row['status'];
    }

    $mCount = 0; $rCount = 0; $lCount = 0;
    foreach ($initialMastery as $st) {
        if ($st === 'mastered') $mCount++;
        elseif ($st === 'review') $rCount++;
        elseif ($st === 'learning') $lCount++;
    }
    $initialStats['mastered'] = $mCount;
    $initialStats['review'] = $rCount;
    $initialStats['learning'] = $lCount;
    $initialStats['total'] = $totalCards;
    $initialStats['unseen'] = max(0, $totalCards - ($mCount + $rCount + $lCount));
    $initialStats['percent_mastered'] = $totalCards > 0 ? round(($mCount / $totalCards) * 100) : 0;

} catch (Exception $e) {
    $dbError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=5.0">
  
  <!-- Apple iOS & Mobile Web App Meta Tags -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="500 Flashcards">
  <meta name="theme-color" content="#2563eb">
  <meta name="format-detection" content="telephone=no">

  <title>500 คำศัพท์ครูผู้ช่วย 2569 (MySQL Edition) - Flashcards</title>
  <script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --sat: env(safe-area-inset-top, 0px);
      --sab: env(safe-area-inset-bottom, 0px);
      --sal: env(safe-area-inset-left, 0px);
      --sar: env(safe-area-inset-right, 0px);
    }
    body {
      font-family: 'Sarabun', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      min-height: 100dvh;
      -webkit-tap-highlight-color: transparent;
      padding-top: max(0.5rem, env(safe-area-inset-top));
      padding-bottom: max(0.75rem, env(safe-area-inset-bottom));
      padding-left: max(0.5rem, env(safe-area-inset-left));
      padding-right: max(0.5rem, env(safe-area-inset-right));
    }
    .touch-target {
      min-height: 44px;
      min-width: 44px;
    }
    .touch-manipulate {
      touch-action: manipulation;
    }
    .perspective-1000 {
      perspective: 1200px;
    }
    .transform-style-3d {
      transform-style: preserve-3d;
    }
    .backface-hidden {
      backface-visibility: hidden;
      -webkit-backface-visibility: hidden;
    }
    .rotate-y-180 {
      transform: rotateY(180deg);
    }
    .flip-card-inner {
      transition: transform 0.38s cubic-bezier(0.4, 0.2, 0.2, 1);
      will-change: transform;
    }
    .flipped {
      transform: rotateY(180deg);
    }
    .flip-card-inner:not(.flipped) #card-front {
      pointer-events: auto;
      z-index: 10;
    }
    .flip-card-inner:not(.flipped) #card-back {
      pointer-events: none;
      z-index: 1;
    }
    .flip-card-inner.flipped #card-front {
      pointer-events: none;
      z-index: 1;
    }
    .flip-card-inner.flipped #card-back {
      pointer-events: auto;
      z-index: 10;
    }
    .scrollbar-none::-webkit-scrollbar {
      display: none;
    }
    .scrollbar-none {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
    .fluid-word {
      font-size: clamp(1.4rem, 4.5vw + 0.2rem, 2.3rem);
      line-height: 1.2;
    }
    .fluid-meaning {
      font-size: clamp(1.15rem, 3.8vw, 1.5rem);
    }
    .fluid-icon {
      font-size: clamp(2.5rem, 7vw, 4rem);
    }
    .ios-scroll {
      -webkit-overflow-scrolling: touch;
      overscroll-behavior-y: contain;
    }
  </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased transition-colors duration-200">

  <!-- Main Container -->
  <div class="max-w-4xl mx-auto space-y-3 sm:space-y-4 px-1 sm:px-3">

    <!-- Header Banner -->
    <div class="bg-white border border-slate-200 rounded-2xl p-3 sm:p-5 shadow-sm space-y-3">
      <!-- Top Row: Brand & Status -->
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2.5 min-w-0">
          <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-blue-600/10 text-blue-600 flex items-center justify-center text-xl sm:text-2xl font-bold shadow-inner flex-shrink-0">
            🧠
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-1.5 flex-wrap">
              <h1 class="text-base sm:text-xl md:text-2xl font-bold tracking-tight text-slate-900 truncate">
                500 คำศัพท์ครูผู้ช่วย 2569
              </h1>
              <span class="text-[10px] sm:text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-300 flex items-center gap-1 flex-shrink-0">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> MySQL
              </span>
            </div>
            <p class="text-[11px] sm:text-xs text-slate-500 truncate hidden sm:block">
              Active Recall • บันทึกคะแนนและคำศัพท์ลง MySQL (รวมกริยา 3 ช่อง V.1, V.2, V.3)
            </p>
          </div>
        </div>

        <!-- User Selector -->
        <div class="flex items-center gap-1 bg-slate-100 border border-slate-200 rounded-xl px-2 py-1 text-xs flex-shrink-0">
          <span class="text-slate-500 hidden sm:inline">👤 ผู้เรียน:</span>
          <span class="text-slate-500 sm:hidden">👤</span>
          <select id="user-selector" onchange="switchUser(this.value)" class="bg-transparent font-medium text-slate-700 focus:outline-none cursor-pointer max-w-[110px] sm:max-w-[150px] truncate text-xs">
            <?php foreach ($users as $u): ?>
              <option value="<?= (int)$u['id'] ?>" <?= ($initialUser && $initialUser['id'] == $u['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['display_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button onclick="openNewUserModal()" class="ml-0.5 text-blue-600 hover:text-blue-800 font-bold px-1" title="เพิ่มผู้เรียนใหม่">+</button>
        </div>
      </div>

      <!-- Action Toolbar -->
      <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto pb-0.5 scrollbar-none text-xs">
        <button onclick="openCardModal()" class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-medium transition flex items-center gap-1 shadow-sm whitespace-nowrap active:scale-95 touch-manipulate" title="เพิ่มคำศัพท์ใหม่">
          <span>➕</span> <span class="hidden sm:inline">เพิ่มคำศัพท์</span><span class="sm:hidden">เพิ่ม</span>
        </button>

        <button id="btn-shuffle" onclick="toggleShuffle()" class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-medium transition flex items-center gap-1 shadow-sm whitespace-nowrap active:scale-95 touch-manipulate" title="สุ่มลำดับคำศัพท์">
          <span>🔀</span> <span id="shuffle-label">สุ่มคำ</span>
        </button>

        <button onclick="openStatsModal()" class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-medium transition flex items-center gap-1 shadow-sm whitespace-nowrap active:scale-95 touch-manipulate" title="ดูสถิติแยกตามหมวด">
          <span>📊</span> สถิติ
        </button>

        <button onclick="resetProgress()" class="px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 font-medium transition flex items-center gap-1 whitespace-nowrap active:scale-95 touch-manipulate ml-auto" title="ล้างคะแนนความจำของผู้เรียนคนนี้ใน MySQL">
          <span>↺</span> <span class="hidden sm:inline">รีเซ็ตคะแนน</span><span class="sm:hidden">รีเซ็ต</span>
        </button>
      </div>
    </div>

    <?php if ($dbError): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-3 py-2.5 rounded-2xl text-xs sm:text-sm">
      ⚠️ <b>เชื่อมต่อ MySQL ไม่สำเร็จ:</b> <?= htmlspecialchars($dbError) ?><br>
      <span class="text-[11px]">กรุณาตรวจสอบว่า MAMP MySQL Server (Port 8889) กำลังทำงานอยู่</span>
    </div>
    <?php endif; ?>

    <!-- Category Tabs & Search Bar -->
    <div class="bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 shadow-sm space-y-2.5">
      <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center justify-between">
        <div class="relative flex-1">
          <input type="text" id="search-input" oninput="handleSearch(this.value)" placeholder="🔍 ค้นหาคำศัพท์, กริยา 3 ช่อง, คำแปล, คำอ่าน หรือประโยค..." 
                 class="w-full pl-8 sm:pl-9 pr-3 py-2 text-xs sm:text-sm rounded-xl border border-slate-300 bg-slate-50 text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
          <span class="absolute left-2.5 sm:left-3 top-2.5 text-xs text-slate-400">🔍</span>
        </div>
        <div class="flex items-center gap-1.5 sm:gap-2">
          <select id="status-filter" onchange="handleStatusFilter(this.value)" class="text-xs py-2 px-2.5 rounded-xl border border-slate-300 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer flex-1 sm:flex-initial">
            <option value="all">แสดงทั้งหมด (<span id="opt-total"><?= $totalCards ?></span> คำ)</option>
            <option value="learning">🔴 ยังจำไม่ได้</option>
            <option value="review">🟡 พอจำได้</option>
            <option value="mastered">🟢 จำได้แม่น</option>
            <option value="unseen">⚪ ยังไม่ได้เปิด</option>
          </select>
          <div class="text-xs font-semibold text-slate-600 whitespace-nowrap bg-slate-100 px-2.5 py-2 rounded-xl flex-shrink-0">
            <span id="filtered-count"><?= $totalCards ?></span> คำ
          </div>
        </div>
      </div>

      <!-- Categories Pills -->
      <div class="flex items-center gap-1 sm:gap-1.5 overflow-x-auto pb-1 text-xs scrollbar-none" id="category-pills">
      </div>
    </div>

    <!-- Progress Tracker Bar (Synced with MySQL) -->
    <div class="bg-white border border-slate-200 rounded-2xl p-2.5 sm:p-4 shadow-sm">
      <div class="flex justify-between items-center text-[11px] sm:text-xs mb-1.5">
        <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
          <span class="flex items-center gap-1 font-medium text-emerald-600">
            <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span> จำแม่น: <b id="stat-mastered"><?= $initialStats['mastered'] ?></b>
          </span>
          <span class="flex items-center gap-1 font-medium text-amber-600">
            <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span> พอจำได้: <b id="stat-review"><?= $initialStats['review'] ?></b>
          </span>
          <span class="flex items-center gap-1 font-medium text-rose-600">
            <span class="w-2 h-2 rounded-full bg-rose-500 inline-block"></span> ยังจำไม่ได้: <b id="stat-learning"><?= $initialStats['learning'] ?></b>
          </span>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-slate-400 text-[10px] sm:text-[11px] hidden sm:inline" id="sync-status">💾 บันทึกใน MySQL</span>
          <span class="font-bold text-blue-600 text-xs" id="stat-percent"><?= $initialStats['percent_mastered'] ?>% จำแม่น</span>
        </div>
      </div>
      <div class="w-full h-2 sm:h-2.5 bg-slate-100 rounded-full overflow-hidden flex">
        <div id="bar-mastered" class="bg-emerald-500 h-full transition-all duration-300" style="width: <?= $initialStats['percent_mastered'] ?>%"></div>
        <div id="bar-review" class="bg-amber-400 h-full transition-all duration-300" style="width: <?= $totalCards > 0 ? ($initialStats['review'] / $totalCards) * 100 : 0 ?>%"></div>
        <div id="bar-learning" class="bg-rose-500 h-full transition-all duration-300" style="width: <?= $totalCards > 0 ? ($initialStats['learning'] / $totalCards) * 100 : 0 ?>%"></div>
      </div>
    </div>

    <!-- Flashcard Display Area (Touch-friendly 3D Card) -->
    <div class="perspective-1000 relative">
      <div id="card-container" onclick="flipCard(event)" 
           class="flip-card-inner transform-style-3d relative w-full min-h-[400px] sm:min-h-[440px] md:min-h-[470px] cursor-pointer select-none touch-manipulate">

        <!-- FRONT SIDE -->
        <div id="card-front" class="backface-hidden absolute inset-0 w-full h-full bg-white border-2 border-blue-200 rounded-2xl sm:rounded-3xl p-4 sm:p-7 shadow-md flex flex-col justify-between">
          <div>
            <!-- Top Card Header -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
              <div class="flex items-center gap-1.5 sm:gap-2">
                <span id="front-cat-badge" class="px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full text-[11px] sm:text-xs font-semibold bg-blue-100 text-blue-700">
                  คำนาม Noun
                </span>
                <span id="front-index" class="text-[11px] sm:text-xs font-mono text-slate-400">#1 / <?= $totalCards ?></span>
              </div>
              <div id="front-status-pill" class="text-[11px] sm:text-xs px-2 sm:px-2.5 py-0.5 rounded-full border border-slate-200 text-slate-500">
                ยังไม่ได้เปิด
              </div>
            </div>

            <!-- Center Target Word & Verb Forms -->
            <div class="my-3 sm:my-6 text-center">
              <div class="fluid-icon mb-1.5" id="front-icon">🔑</div>
              <div class="flex items-center justify-center gap-2 flex-wrap">
                <h2 class="fluid-word font-extrabold tracking-tight text-slate-900" id="front-word">
                  access
                </h2>
                <button onclick="speakWord(event)" id="speaker-btn" class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center transition border border-blue-200 touch-target active:scale-90 flex-shrink-0" title="ฟังเสียงอ่าน (หากเป็นกริยาจะอ่านครบ 3 คำ)">
                  <span class="text-base sm:text-lg">🔊</span>
                </button>
              </div>

              <!-- Dedicated 3 Verb Forms Container (Shows only for verbs) -->
              <div id="front-verb-box" class="mt-2.5 inline-flex items-center justify-center gap-1.5 sm:gap-2.5 flex-wrap bg-blue-50/90 border border-blue-200 rounded-xl px-3 py-1.5 shadow-sm text-xs sm:text-sm hidden">
                <span class="inline-flex items-center gap-1 font-bold text-blue-900 cursor-pointer hover:bg-blue-100/80 px-2 py-0.5 rounded-lg transition" onclick="speakSingleWord(event, 'v1')" title="คลิกเพื่อฟังเสียง V.1">
                  <span class="text-[10px] bg-blue-200/90 text-blue-800 font-bold px-1.5 py-0.5 rounded font-mono">V.1</span> <b id="front-v1">accept</b>
                </span>
                <span class="text-blue-300 font-bold">➔</span>
                <span class="inline-flex items-center gap-1 font-bold text-blue-900 cursor-pointer hover:bg-blue-100/80 px-2 py-0.5 rounded-lg transition" onclick="speakSingleWord(event, 'v2')" title="คลิกเพื่อฟังเสียง V.2">
                  <span class="text-[10px] bg-blue-200/90 text-blue-800 font-bold px-1.5 py-0.5 rounded font-mono">V.2</span> <b id="front-v2">accepted</b>
                </span>
                <span class="text-blue-300 font-bold">➔</span>
                <span class="inline-flex items-center gap-1 font-bold text-blue-900 cursor-pointer hover:bg-blue-100/80 px-2 py-0.5 rounded-lg transition" onclick="speakSingleWord(event, 'v3')" title="คลิกเพื่อฟังเสียง V.3">
                  <span class="text-[10px] bg-blue-200/90 text-blue-800 font-bold px-1.5 py-0.5 rounded font-mono">V.3</span> <b id="front-v3">accepted</b>
                </span>
              </div>

              <p class="text-xs sm:text-sm text-slate-500 mt-1" id="front-reading">
                คำอ่าน: 'แอ็คเซิซ
              </p>
            </div>

            <!-- Active Recall Context Cloze -->
            <div class="bg-blue-50/70 border border-blue-100 rounded-xl sm:rounded-2xl p-3 sm:p-4 text-center">
              <span class="text-[10px] sm:text-[11px] font-semibold text-blue-700 uppercase tracking-wider block mb-1">
                Context Cloze Sentence (ทดสอบบริบทก่อนดูเฉลย)
              </span>
              <p class="text-xs sm:text-sm md:text-base font-medium text-slate-800 leading-relaxed" id="front-cloze">
                The staff has full <span class="px-2 py-0.5 bg-amber-200/90 font-bold text-amber-900 rounded border border-amber-300">[ ______ ]</span> to records.
              </p>
            </div>
          </div>

          <!-- Bottom Mobile Swipe & Tap Prompt -->
          <div class="text-center pt-2.5 border-t border-slate-100 text-[11px] sm:text-xs text-slate-400 flex items-center justify-center gap-1.5 flex-wrap">
            <span>👆 แตะการ์ดเพื่อดูเฉลย</span>
            <span class="hidden sm:inline">• กด <kbd class="px-1 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200 font-mono text-[10px]">Space</kbd></span>
            <span>• 👈👉 ปัดซ้าย/ขวาเพื่อเปลี่ยนคำ</span>
          </div>
        </div>

        <!-- BACK SIDE -->
        <div id="card-back" class="rotate-y-180 backface-hidden absolute inset-0 w-full h-full bg-white border-2 border-emerald-300 rounded-2xl sm:rounded-3xl p-4 sm:p-7 shadow-md flex flex-col justify-between overflow-y-auto ios-scroll">
          <div>
            <!-- Top Back Header -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
              <div class="flex items-center gap-1.5 sm:gap-2">
                <span id="back-cat-badge" class="px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full text-[11px] sm:text-xs font-semibold bg-emerald-100 text-emerald-800">
                  คำนาม Noun
                </span>
                <span id="back-index" class="text-[11px] sm:text-xs font-mono text-slate-400">#1 / <?= $totalCards ?></span>
              </div>
              <div class="flex items-center gap-1 sm:gap-2">
                <button onclick="editCurrentCard(event)" class="text-xs px-2 py-1 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-600 flex items-center gap-1 touch-target active:scale-95" title="แก้ไขคำศัพท์นี้ใน MySQL">
                  <span>✏️</span> <span class="hidden sm:inline">แก้ไข</span>
                </button>
                <button onclick="flipCard(event)" class="text-xs font-medium text-slate-500 hover:text-slate-800 px-2.5 py-1 rounded-lg border border-slate-200 hover:bg-slate-100 flex items-center gap-1 touch-target active:scale-95">
                  <span>↩</span> ด้านหน้า
                </button>
              </div>
            </div>

            <!-- Word & Thai Meaning -->
            <div class="text-center my-2 sm:my-3">
              <div class="flex items-center justify-center gap-2 flex-wrap">
                <span id="back-word" class="fluid-word font-bold text-slate-900">access</span>
                <button onclick="speakWord(event)" id="speaker-btn-back" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-emerald-50 text-emerald-600 hover:bg-emerald-100 flex items-center justify-center transition border border-emerald-200 touch-target active:scale-90 flex-shrink-0" title="ฟังเสียงอ่าน">
                  <span class="text-sm sm:text-base">🔊</span>
                </button>
              </div>

              <!-- Dedicated 3 Verb Forms Diagram on Back (Shows only for verbs) -->
              <div id="back-verb-box" class="mt-2 inline-flex items-center justify-center gap-2 sm:gap-3 flex-wrap bg-emerald-50 border border-emerald-200 rounded-xl px-3.5 py-2 shadow-sm text-xs sm:text-sm hidden">
                <div class="text-center px-1.5 py-0.5 rounded-lg hover:bg-emerald-100/60 cursor-pointer transition" onclick="speakSingleWord(event, 'v1')" title="ฟังเสียง V.1">
                  <span class="text-[10px] font-bold text-emerald-600 uppercase block">V.1 รูปเดิม</span>
                  <span class="font-extrabold text-emerald-950 text-sm sm:text-base" id="back-v1">accept</span>
                </div>
                <span class="text-emerald-400 font-bold">➔</span>
                <div class="text-center px-1.5 py-0.5 rounded-lg hover:bg-emerald-100/60 cursor-pointer transition" onclick="speakSingleWord(event, 'v2')" title="ฟังเสียง V.2">
                  <span class="text-[10px] font-bold text-emerald-600 uppercase block">V.2 อดีต (Past)</span>
                  <span class="font-extrabold text-emerald-950 text-sm sm:text-base" id="back-v2">accepted</span>
                </div>
                <span class="text-emerald-400 font-bold">➔</span>
                <div class="text-center px-1.5 py-0.5 rounded-lg hover:bg-emerald-100/60 cursor-pointer transition" onclick="speakSingleWord(event, 'v3')" title="ฟังเสียง V.3">
                  <span class="text-[10px] font-bold text-emerald-600 uppercase block">V.3 Participle</span>
                  <span class="font-extrabold text-emerald-950 text-sm sm:text-base" id="back-v3">accepted</span>
                </div>
              </div>

              <div id="back-reading" class="text-xs text-slate-500 mt-0.5">('แอ็คเซิซ)</div>
              <div class="mt-2 inline-block">
                <span id="back-meaning" class="fluid-meaning font-extrabold text-emerald-800 bg-emerald-50 px-3 py-1 sm:px-4 sm:py-1 rounded-xl border border-emerald-200 inline-block">
                  การเข้าถึง
                </span>
              </div>
            </div>

            <!-- Full Sentence & Translation -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5 sm:p-3.5 space-y-1 text-xs sm:text-sm">
              <div class="text-slate-800 font-medium" id="back-example">
                The staff has full <b>access</b> to records.
              </div>
              <div class="text-slate-500 text-[11px] sm:text-xs" id="back-example-th">
                แปล: เจ้าหน้าที่สามารถเข้าถึงบันทึกทั้งหมดได้
              </div>
            </div>

            <!-- Synonyms & Antonyms -->
            <div id="back-syn-container" class="mt-2.5 grid grid-cols-1 sm:grid-cols-2 gap-1.5 sm:gap-2 text-[11px] sm:text-xs">
              <div class="bg-blue-50/70 border border-blue-100 rounded-lg p-2 sm:p-2.5">
                <span class="font-bold text-blue-700">📚 คำพ้อง (Synonyms):</span>
                <p id="back-synonyms" class="text-slate-700 mt-0.5">entry, admission, admittance</p>
              </div>
              <div class="bg-rose-50/70 border border-rose-100 rounded-lg p-2 sm:p-2.5">
                <span class="font-bold text-rose-700">⚡ คำตรงข้าม (Antonyms):</span>
                <p id="back-antonyms" class="text-slate-700 mt-0.5">restriction, exclusion</p>
              </div>
            </div>

            <!-- Memory Hook / Root / Prefix / Suffix / Exam Tip -->
            <div class="mt-2.5 bg-amber-50 border-l-4 border-amber-500 rounded-r-xl p-2.5 sm:p-3 text-[11px] sm:text-xs text-amber-950">
              <div class="font-bold flex items-center gap-1.5 mb-0.5 text-amber-800">
                <span>🧠</span> เทคนิคจำ & จุดตายข้อสอบครูผู้ช่วย:
              </div>
              <div id="back-hook" class="leading-relaxed text-slate-700">
                สำนวนข้อสอบ: <b>have access to + N.</b>
              </div>
            </div>
          </div>

          <!-- Bottom Evaluation Controls (Leitner System -> syncs to MySQL) -->
          <div class="pt-3 border-t border-slate-100">
            <div class="text-[11px] sm:text-xs text-center font-medium text-slate-500 mb-1.5">
              ประเมินระดับความจำ (บันทึกลง MySQL):
            </div>
            <div class="grid grid-cols-3 gap-1.5 sm:gap-2">
              <button onclick="rateCard('learning', event)" class="min-h-[46px] py-2 px-2 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 text-xs font-bold transition flex items-center justify-center gap-1 shadow-sm active:scale-95 touch-manipulate">
                <span>🔴</span> <span class="hidden xs:inline">ยังจำไม่ได้</span><span class="xs:hidden">ไม่จำ</span> <kbd class="hidden sm:inline opacity-60 font-mono text-[10px] ml-0.5">(1)</kbd>
              </button>
              <button onclick="rateCard('review', event)" class="min-h-[46px] py-2 px-2 rounded-xl bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-700 text-xs font-bold transition flex items-center justify-center gap-1 shadow-sm active:scale-95 touch-manipulate">
                <span>🟡</span> พอจำได้ <kbd class="hidden sm:inline opacity-60 font-mono text-[10px] ml-0.5">(2)</kbd>
              </button>
              <button onclick="rateCard('mastered', event)" class="min-h-[46px] py-2 px-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 text-xs font-bold transition flex items-center justify-center gap-1 shadow-sm active:scale-95 touch-manipulate">
                <span>🟢</span> จำได้แม่น <kbd class="hidden sm:inline opacity-60 font-mono text-[10px] ml-0.5">(3)</kbd>
              </button>
            </div>
          </div>

        </div>

      </div>
    </div>

    <!-- Bottom Navigation Bar -->
    <div class="bg-white border border-slate-200 rounded-2xl p-2.5 sm:p-4 shadow-sm flex items-center justify-between gap-2">
      <button onclick="prevCard(event)" class="min-h-[44px] px-3.5 sm:px-4 py-2 rounded-xl border border-slate-300 bg-slate-50 text-xs sm:text-sm font-semibold hover:bg-slate-100 transition flex items-center gap-1 shadow-sm active:scale-95 touch-manipulate">
        <span>←</span> <span>ก่อนหน้า</span>
      </button>

      <!-- Center Jump & Flip -->
      <div class="flex items-center gap-1.5 sm:gap-2">
        <button onclick="flipCard(event)" class="min-h-[44px] px-3.5 sm:px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs sm:text-sm font-semibold shadow-sm transition flex items-center gap-1 active:scale-95 touch-manipulate">
          <span>🔄</span> <span>พลิกการ์ด</span>
        </button>
        <div class="flex items-center gap-1 text-[11px] sm:text-xs text-slate-500">
          <span class="hidden sm:inline">#</span>
          <input type="number" id="jump-input" min="1" max="<?= $totalCards ?>" class="w-12 sm:w-14 px-1.5 py-1 text-center rounded-lg border border-slate-300 bg-white text-xs" onkeydown="if(event.key==='Enter') jumpToCard(this.value)">
        </div>
      </div>

      <button onclick="nextCard(event)" class="min-h-[44px] px-3.5 sm:px-4 py-2 rounded-xl border border-slate-300 bg-slate-50 text-xs sm:text-sm font-semibold hover:bg-slate-100 transition flex items-center gap-1 shadow-sm active:scale-95 touch-manipulate">
        <span>ถัดไป</span> <span>→</span>
      </button>
    </div>

    <!-- Mobile Touch / Keyboard Shortcuts Help -->
    <div class="text-center text-[11px] sm:text-xs text-slate-400 pt-1 pb-3">
      <span class="hidden sm:inline">คีย์ลัด: <kbd class="px-1.5 py-0.5 rounded bg-slate-200 text-slate-700 font-mono">Space</kbd> พลิก • 
      <kbd class="px-1.5 py-0.5 rounded bg-slate-200 text-slate-700 font-mono">←</kbd>/<kbd class="px-1.5 py-0.5 rounded bg-slate-200 text-slate-700 font-mono">→</kbd> เปลี่ยนคำ • 
      <kbd class="px-1.5 py-0.5 rounded bg-slate-200 text-slate-700 font-mono">1/2/3</kbd> บันทึก MySQL • 
      <kbd class="px-1.5 py-0.5 rounded bg-slate-200 text-slate-700 font-mono">S</kbd> ออกเสียง</span>
      <span class="sm:hidden">📱 <b>การใช้งาน:</b> ปัดซ้าย/ขวาเพื่อเปลี่ยนคำ • แตะตัวการ์ดเพื่อพลิก • แตะปุ่มลำโพงเพื่อฟังเสียงกริยา 3 ช่อง</span>
    </div>

  </div>

  <!-- MODAL: Add / Edit Flashcard (Including V.1, V.2, V.3) -->
  <div id="card-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90dvh] overflow-y-auto ios-scroll p-4 sm:p-5 shadow-2xl border border-slate-200 space-y-3.5">
      <div class="flex items-center justify-between border-b pb-2.5">
        <h3 class="text-sm sm:text-base font-bold text-slate-900" id="modal-title">➕ เพิ่มคำศัพท์ใหม่ลงใน MySQL</h3>
        <button onclick="closeCardModal()" class="w-8 h-8 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center text-lg touch-manipulate">✕</button>
      </div>
      <form id="card-form" onsubmit="saveCardForm(event)" class="space-y-3 text-xs">
        <input type="hidden" id="form-num" value="0">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <div>
            <label class="block font-medium text-slate-700 mb-1">คำศัพท์ภาษาอังกฤษ *</label>
            <input type="text" id="form-word" required class="w-full p-2 border border-slate-300 rounded-lg text-sm font-semibold">
          </div>
          <div>
            <label class="block font-medium text-slate-700 mb-1">หมวดหมู่ (Category) *</label>
            <select id="form-category" onchange="toggleVerbInputs(this.value)" class="w-full p-2 border border-slate-300 rounded-lg text-xs">
              <option value="คำนาม (Noun)">คำนาม (Noun)</option>
              <option value="คำกริยา (Verb)">คำกริยา (Verb)</option>
              <option value="คำคุณศัพท์ (Adjective)">คำคุณศัพท์ (Adjective)</option>
              <option value="คำกริยาวิเศษณ์ (Adverb)">คำกริยาวิเศษณ์ (Adverb)</option>
              <option value="คำบุพบท (Preposition)">คำบุพบท (Preposition)</option>
              <option value="คำสันธาน (Conjunction)">คำสันธาน (Conjunction)</option>
              <option value="คำกำกับนาม (Determiner)">คำกำกับนาม (Determiner)</option>
            </select>
          </div>
        </div>

        <!-- 3 Forms Section for Verbs -->
        <div id="form-verb-section" class="bg-blue-50/70 border border-blue-200 rounded-xl p-2.5 space-y-2">
          <span class="font-bold text-blue-800 text-[11px] block">📌 กริยา 3 ช่อง (สำหรับหมวดคำกริยา):</span>
          <div class="grid grid-cols-3 gap-2">
            <div>
              <label class="block text-[10px] text-slate-600 mb-0.5">V.1 รูปเดิม</label>
              <input type="text" id="form-v1" placeholder="เช่น accept" class="w-full p-1.5 border border-slate-300 rounded-lg font-medium text-xs">
            </div>
            <div>
              <label class="block text-[10px] text-slate-600 mb-0.5">V.2 อดีต (Past)</label>
              <input type="text" id="form-v2" placeholder="เช่น accepted" class="w-full p-1.5 border border-slate-300 rounded-lg font-medium text-xs">
            </div>
            <div>
              <label class="block text-[10px] text-slate-600 mb-0.5">V.3 Participle</label>
              <input type="text" id="form-v3" placeholder="เช่น accepted" class="w-full p-1.5 border border-slate-300 rounded-lg font-medium text-xs">
            </div>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-2">
          <div>
            <label class="block font-medium text-slate-700 mb-1">ชนิดคำ (POS)</label>
            <input type="text" id="form-pos" placeholder="เช่น v., n." class="w-full p-2 border border-slate-300 rounded-lg">
          </div>
          <div>
            <label class="block font-medium text-slate-700 mb-1">คำอ่านไทย</label>
            <input type="text" id="form-reading" placeholder="เช่น 'แอ็คเซิซ" class="w-full p-2 border border-slate-300 rounded-lg">
          </div>
          <div>
            <label class="block font-medium text-slate-700 mb-1">Emoji</label>
            <input type="text" id="form-icon" placeholder="เช่น 🔑" class="w-full p-2 border border-slate-300 rounded-lg text-center">
          </div>
        </div>

        <div>
          <label class="block font-medium text-slate-700 mb-1">คำแปลภาษาไทย *</label>
          <input type="text" id="form-meaning" required class="w-full p-2 border border-slate-300 rounded-lg text-sm">
        </div>

        <div>
          <label class="block font-medium text-slate-700 mb-1">ประโยคตัวอย่างภาษาอังกฤษ</label>
          <input type="text" id="form-example" class="w-full p-2 border border-slate-300 rounded-lg">
        </div>

        <div>
          <label class="block font-medium text-slate-700 mb-1">คำแปลประโยคภาษาไทย</label>
          <input type="text" id="form-example-th" class="w-full p-2 border border-slate-300 rounded-lg">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <div>
            <label class="block font-medium text-slate-700 mb-1">คำพ้อง (Synonyms)</label>
            <input type="text" id="form-synonyms" placeholder="เช่น entry, admission" class="w-full p-2 border border-slate-300 rounded-lg">
          </div>
          <div>
            <label class="block font-medium text-slate-700 mb-1">คำตรงข้าม (Antonyms)</label>
            <input type="text" id="form-antonyms" placeholder="เช่น restriction" class="w-full p-2 border border-slate-300 rounded-lg">
          </div>
        </div>

        <div>
          <label class="block font-medium text-slate-700 mb-1">เทคนิคจำ / จุดตายข้อสอบครูผู้ช่วย</label>
          <textarea id="form-hook" rows="2" class="w-full p-2 border border-slate-300 rounded-lg"></textarea>
        </div>

        <div class="flex justify-between items-center pt-2.5 border-t">
          <button type="button" id="btn-delete-card" onclick="deleteCurrentCard()" class="px-3 py-1.5 text-xs rounded-lg text-red-600 hover:bg-red-50 hidden touch-manipulate">
            🗑️ ลบคำศัพท์
          </button>
          <div class="flex gap-2 ml-auto">
            <button type="button" onclick="closeCardModal()" class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 touch-manipulate">
              ยกเลิก
            </button>
            <button type="submit" class="px-4 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold touch-manipulate">
              บันทึกลง MySQL
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: Add New Learner Profile -->
  <div id="user-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-3 hidden">
    <div class="bg-white rounded-2xl max-w-sm w-full p-4 sm:p-5 shadow-2xl border border-slate-200 space-y-3.5 text-xs">
      <div class="flex items-center justify-between border-b pb-2.5">
        <h3 class="text-sm font-bold text-slate-900">👤 เพิ่มโปรไฟล์ผู้เรียนใหม่</h3>
        <button onclick="closeNewUserModal()" class="w-8 h-8 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center text-base touch-manipulate">✕</button>
      </div>
      <form onsubmit="saveNewUser(event)" class="space-y-3">
        <div>
          <label class="block font-medium text-slate-700 mb-1">รหัสผู้ใช้ / Username *</label>
          <input type="text" id="new-username" required placeholder="เช่น student2" class="w-full p-2 border border-slate-300 rounded-lg">
        </div>
        <div>
          <label class="block font-medium text-slate-700 mb-1">ชื่อผู้เรียน / ชื่อที่แสดง *</label>
          <input type="text" id="new-display-name" required placeholder="เช่น ครูสมชาย" class="w-full p-2 border border-slate-300 rounded-lg">
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t">
          <button type="button" onclick="closeNewUserModal()" class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 touch-manipulate">
            ยกเลิก
          </button>
          <button type="submit" class="px-4 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold touch-manipulate">
            สร้างโปรไฟล์
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: Statistics Overview -->
  <div id="stats-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-4 hidden">
    <div class="bg-white rounded-2xl max-w-lg w-full p-4 sm:p-5 shadow-2xl border border-slate-200 space-y-3.5 text-xs max-h-[85dvh] overflow-y-auto ios-scroll">
      <div class="flex items-center justify-between border-b pb-2.5">
        <h3 class="text-sm font-bold text-slate-900">📊 สถิติความจำแยกตามหมวดหมู่ (MySQL)</h3>
        <button onclick="closeStatsModal()" class="w-8 h-8 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center text-base touch-manipulate">✕</button>
      </div>
      <div id="stats-category-list" class="space-y-2">
        <div class="text-center py-4 text-slate-400">กำลังโหลดข้อมูลสถิติ...</div>
      </div>
      <div class="pt-2.5 border-t text-right">
        <button onclick="closeStatsModal()" class="px-4 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium touch-manipulate">
          ปิดหน้าต่าง
        </button>
      </div>
    </div>
  </div>

  <!-- Embedded initial cards and mastery data from MySQL -->
  <script>
    let rawCards = <?= json_encode($initialCards, JSON_UNESCAPED_UNICODE) ?>;
    let mastery = <?= json_encode($initialMastery, JSON_UNESCAPED_UNICODE) ?>;
    let currentUserId = <?= (int)$userId ?>;

    let cards = [...rawCards];
    let currentIndex = 0;
    let isFlipped = false;
    let isShuffled = false;
    let activeCategory = "ทั้งหมด";
    let activeStatus = "all";
    let searchQuery = "";
    let currentAudio = null;

    // Touch Swipe Detection for iPhone & iPad
    let touchStartX = 0;
    let touchStartY = 0;
    let touchEndX = 0;
    let touchEndY = 0;
    let isTouchSwiping = false;

    const categories = [
      "ทั้งหมด",
      "คำนาม (Noun)",
      "คำกริยา (Verb)",
      "คำคุณศัพท์ (Adjective)",
      "คำกริยาวิเศษณ์ (Adverb)",
      "คำบุพบท (Preposition)",
      "คำสันธาน (Conjunction)",
      "คำกำกับนาม (Determiner)"
    ];

    const verb3FormsMap = {"101": ["accept", "accepted", "accepted"], "102": ["achieve", "achieved", "achieved"], "103": ["acknowledge", "acknowledged", "acknowledged"], "104": ["acquire", "acquired", "acquired"], "105": ["act", "acted", "acted"], "106": ["adjust", "adjusted", "adjusted"], "107": ["administer", "administered", "administered"], "108": ["advertise", "advertised", "advertised"], "109": ["advise", "advised", "advised"], "110": ["allocate", "allocated", "allocated"], "111": ["analyze", "analyzed", "analyzed"], "112": ["answer", "answered", "answered"], "113": ["apply", "applied", "applied"], "114": ["appoint", "appointed", "appointed"], "115": ["approve", "approved", "approved"], "116": ["arrange", "arranged", "arranged"], "117": ["assist", "assisted", "assisted"], "118": ["assume", "assumed", "assumed"], "119": ["attend", "attended", "attended"], "120": ["authorize", "authorized", "authorized"], "121": ["avoid", "avoided", "avoided"], "122": ["balance", "balanced", "balanced"], "123": ["behave", "behaved", "behaved"], "124": ["believe", "believed", "believed"], "125": ["build", "built", "built"], "126": ["calculate", "calculated", "calculated"], "127": ["cancel", "canceled", "canceled"], "128": ["clarify", "clarified", "clarified"], "129": ["collaborate", "collaborated", "collaborated"], "130": ["collect", "collected", "collected"], "131": ["communicate", "communicated", "communicated"], "132": ["compare", "compared", "compared"], "133": ["complete", "completed", "completed"], "134": ["confirm", "confirmed", "confirmed"], "135": ["connect", "connected", "connected"], "136": ["consider", "considered", "considered"], "137": ["construct", "constructed", "constructed"], "138": ["contact", "contacted", "contacted"], "139": ["contribute", "contributed", "contributed"], "140": ["control", "controlled", "controlled"], "141": ["cooperate", "cooperated", "cooperated"], "142": ["correct", "corrected", "corrected"], "143": ["create", "created", "created"], "144": ["decide", "decided", "decided"], "145": ["decline", "declined", "declined"], "146": ["deliver", "delivered", "delivered"], "147": ["demonstrate", "demonstrated", "demonstrated"], "148": ["depend", "depended", "depended"], "149": ["describe", "described", "described"], "150": ["design", "designed", "designed"], "151": ["develop", "developed", "developed"], "152": ["discuss", "discussed", "discussed"], "153": ["distribute", "distributed", "distributed"], "154": ["edit", "edited", "edited"], "155": ["emphasize", "emphasized", "emphasized"], "156": ["enable", "enabled", "enabled"], "157": ["encourage", "encouraged", "encouraged"], "158": ["engage", "engaged", "engaged"], "159": ["ensure", "ensured", "ensured"], "160": ["enter", "entered", "entered"], "161": ["establish", "established", "established"], "162": ["estimate", "estimated", "estimated"], "163": ["evaluate", "evaluated", "evaluated"], "164": ["exceed", "exceeded", "exceeded"], "165": ["exclude", "excluded", "excluded"], "166": ["excuse", "excused", "excused"], "167": ["expand", "expanded", "expanded"], "168": ["expect", "expected", "expected"], "169": ["explain", "explained", "explained"], "170": ["express", "expressed", "expressed"], "171": ["file", "filed", "filed"], "172": ["fill", "filled", "filled"], "173": ["focus", "focused", "focused"], "174": ["follow", "followed", "followed"], "175": ["form", "formed", "formed"], "176": ["gain", "gained", "gained"], "177": ["gather", "gathered", "gathered"], "178": ["generate", "generated", "generated"], "179": ["handle", "handled", "handled"], "180": ["identify", "identified", "identified"], "181": ["implement", "implemented", "implemented"], "182": ["improve", "improved", "improved"], "183": ["include", "included", "included"], "184": ["inform", "informed", "informed"], "185": ["install", "installed", "installed"], "186": ["instruct", "instructed", "instructed"], "187": ["intend", "intended", "intended"], "188": ["interview", "interviewed", "interviewed"], "189": ["introduce", "introduced", "introduced"], "190": ["investigate", "investigated", "investigated"], "191": ["invite", "invited", "invited"], "192": ["join", "joined", "joined"], "193": ["justify", "justified", "justified"], "194": ["lead", "led", "led"], "195": ["limit", "limited", "limited"], "196": ["manage", "managed", "managed"], "197": ["measure", "measured", "measured"], "198": ["monitor", "monitored", "monitored"], "199": ["notify", "notified", "notified"], "200": ["observe", "observed", "observed"]};

    function getCardVerbForms(c) {
      if (!c) return { v1: "", v2: "", v3: "", hasForms: false };
      
      let v1 = (c.v1 || "").trim();
      let v2 = (c.v2 || "").trim();
      let v3 = (c.v3 || "").trim();

      if (v1 && v2 && v3) {
        return { v1: v1, v2: v2, v3: v3, hasForms: true };
      }

      if (c.word && (c.word.includes("–") || c.word.includes("-"))) {
        const parts = c.word.split(/[–\-]/).map(function(s) { return s.trim(); }).filter(Boolean);
        if (parts.length >= 3) {
          return { v1: parts[0], v2: parts[1], v3: parts[2], hasForms: true };
        }
      }

      const numKey = String(c.num || "");
      if (numKey && verb3FormsMap[numKey]) {
        const mv = verb3FormsMap[numKey];
        return { v1: mv[0], v2: mv[1], v3: mv[2], hasForms: true };
      }

      if (c.category === "คำกริยา (Verb)" || (c.pos && c.pos.toLowerCase().includes("v"))) {
        const cleanWord = (c.word || "").replace(/[^a-zA-Z]/g, '').toLowerCase();
        for (const k in verb3FormsMap) {
          if (verb3FormsMap[k][0].toLowerCase() === cleanWord) {
            const mv = verb3FormsMap[k];
            return { v1: mv[0], v2: mv[1], v3: mv[2], hasForms: true };
          }
        }
      }

      return { v1: "", v2: "", v3: "", hasForms: false };
    }

    function init() {
      renderCategoryPills();
      filterCards();
      updateStatsUI();
      renderCurrentCard();
      setupTouchGestures();
      unlockAudioOnUserGesture();

      // Keyboard navigation
      document.addEventListener("keydown", function(e) {
        if (e.target.tagName === "INPUT" || e.target.tagName === "SELECT" || e.target.tagName === "TEXTAREA") return;
        if (e.code === "Space" || e.key === "Enter") {
          e.preventDefault();
          flipCard();
        } else if (e.code === "ArrowRight" || e.key === "j" || e.key === "l") {
          nextCard();
        } else if (e.code === "ArrowLeft" || e.key === "h") {
          prevCard();
        } else if (e.key === "1") {
          rateCard("learning");
        } else if (e.key === "2") {
          rateCard("review");
        } else if (e.key === "3") {
          rateCard("mastered");
        } else if (e.key.toLowerCase() === "s") {
          speakWord();
        }
      });

      // Warm up voices
      if ("speechSynthesis" in window) {
        window.speechSynthesis.onvoiceschanged = function() {
          try { window.speechSynthesis.getVoices(); } catch(e) {}
        };
      }
    }

    // Touch Swipe Setup for iPhone & iPad
    function setupTouchGestures() {
      const cardContainer = document.getElementById("card-container");
      if (!cardContainer) return;

      cardContainer.addEventListener("touchstart", function(e) {
        if (e.touches.length !== 1) return;
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        touchEndX = touchStartX;
        touchEndY = touchStartY;
        isTouchSwiping = false;
      }, { passive: true });

      cardContainer.addEventListener("touchmove", function(e) {
        if (e.touches.length !== 1) return;
        touchEndX = e.touches[0].clientX;
        touchEndY = e.touches[0].clientY;
        const diffX = touchEndX - touchStartX;
        const diffY = touchEndY - touchStartY;

        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 25) {
          isTouchSwiping = true;
        }
      }, { passive: true });

      cardContainer.addEventListener("touchend", function(e) {
        const diffX = touchEndX - touchStartX;
        const diffY = touchEndY - touchStartY;
        const absX = Math.abs(diffX);
        const absY = Math.abs(diffY);

        if (absX > 45 && absX > absY * 1.3) {
          if (diffX < 0) {
            nextCard();
          } else {
            prevCard();
          }
          setTimeout(function() {
            isTouchSwiping = false;
          }, 100);
        } else {
          isTouchSwiping = false;
        }

        touchStartX = 0;
        touchStartY = 0;
        touchEndX = 0;
        touchEndY = 0;
      }, { passive: true });
    }

    // iOS Audio Unlock
    function unlockAudioOnUserGesture() {
      const unlock = function() {
        if ("speechSynthesis" in window) {
          try {
            window.speechSynthesis.getVoices();
          } catch(e) {}
        }
        document.removeEventListener("touchstart", unlock);
        document.removeEventListener("click", unlock);
      };
      document.addEventListener("touchstart", unlock, { once: true, passive: true });
      document.addEventListener("click", unlock, { once: true, passive: true });
    }

    function renderCategoryPills() {
      const container = document.getElementById("category-pills");
      if (!container) return;
      container.innerHTML = categories.map(function(cat) {
        const isActive = cat === activeCategory;
        const count = cat === "ทั้งหมด" ? rawCards.length : rawCards.filter(function(c) { return c.category === cat; }).length;
        const shortName = cat.split(" ")[0];
        const activeClass = "bg-blue-600 text-white shadow-sm";
        const inactiveClass = "bg-slate-100 text-slate-600 hover:bg-slate-200";
        return '<button onclick="selectCategory(\'' + cat + '\')" class="px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-full font-medium transition whitespace-nowrap active:scale-95 touch-manipulate ' + (isActive ? activeClass : inactiveClass) + '">' +
               shortName + ' <span class="opacity-70 text-[10px] sm:text-[11px]">(' + count + ')</span></button>';
      }).join("");
    }

    function selectCategory(cat) {
      activeCategory = cat;
      renderCategoryPills();
      filterCards();
    }

    function handleSearch(q) {
      searchQuery = (q || "").trim().toLowerCase();
      filterCards();
    }

    function handleStatusFilter(status) {
      activeStatus = status;
      filterCards();
    }

    function filterCards() {
      let list = [...rawCards];

      if (activeCategory !== "ทั้งหมด") {
        list = list.filter(function(c) { return c.category === activeCategory; });
      }

      if (activeStatus !== "all") {
        list = list.filter(function(c) {
          const st = mastery[c.num] || "unseen";
          return st === activeStatus;
        });
      }

      if (searchQuery) {
        list = list.filter(function(c) {
          const vf = getCardVerbForms(c);
          return (c.word && c.word.toLowerCase().includes(searchQuery)) ||
                 (vf.v1 && vf.v1.toLowerCase().includes(searchQuery)) ||
                 (vf.v2 && vf.v2.toLowerCase().includes(searchQuery)) ||
                 (vf.v3 && vf.v3.toLowerCase().includes(searchQuery)) ||
                 (c.meaning && c.meaning.toLowerCase().includes(searchQuery)) ||
                 (c.reading && c.reading.toLowerCase().includes(searchQuery)) ||
                 (c.example && c.example.toLowerCase().includes(searchQuery)) ||
                 (c.example_th && c.example_th.toLowerCase().includes(searchQuery));
        });
      }

      if (isShuffled) {
        list.sort(function() { return Math.random() - 0.5; });
      }

      cards = list;
      currentIndex = 0;
      isFlipped = false;
      const container = document.getElementById("card-container");
      if (container) container.classList.remove("flipped");
      const countEl = document.getElementById("filtered-count");
      if (countEl) countEl.innerText = cards.length;
      renderCurrentCard();
    }

    function renderCurrentCard() {
      if (cards.length === 0) {
        document.getElementById("front-word").innerText = "ไม่พบคำศัพท์";
        document.getElementById("front-reading").innerText = "ลองเปลี่ยนตัวกรอง หรือล้างการค้นหา";
        document.getElementById("front-cloze").innerHTML = "—";
        document.getElementById("front-index").innerText = "0 / 0";
        document.getElementById("front-icon").innerText = "🔍";
        document.getElementById("front-verb-box").classList.add("hidden");
        document.getElementById("back-verb-box").classList.add("hidden");
        return;
      }

      if (currentIndex >= cards.length) currentIndex = 0;
      if (currentIndex < 0) currentIndex = cards.length - 1;

      const c = cards[currentIndex];
      const st = mastery[c.num] || "unseen";
      const vForms = getCardVerbForms(c);
      const isVerbWithThreeForms = vForms.hasForms;
      const v1 = vForms.v1;
      const v2 = vForms.v2;
      const v3 = vForms.v3;

      // Front elements
      document.getElementById("front-cat-badge").innerText = c.category;
      document.getElementById("front-index").innerText = "#" + c.num + " / " + rawCards.length + " (" + (currentIndex + 1) + "/" + cards.length + ")";
      document.getElementById("front-icon").innerText = c.icon || "📖";
      
      // Front Word & Verb 3 Forms
      const frontWordEl = document.getElementById("front-word");
      const frontVerbBox = document.getElementById("front-verb-box");
      const backVerbBox = document.getElementById("back-verb-box");

      if (isVerbWithThreeForms) {
        frontWordEl.innerHTML = `<span class="text-blue-700">${v1}</span> <span class="text-slate-300 font-normal">–</span> <span class="text-slate-800">${v2}</span> <span class="text-slate-300 font-normal">–</span> <span class="text-slate-800">${v3}</span>`;
        frontVerbBox.classList.remove("hidden");
        document.getElementById("front-v1").innerText = v1;
        document.getElementById("front-v2").innerText = v2;
        document.getElementById("front-v3").innerText = v3;

        backVerbBox.classList.remove("hidden");
        document.getElementById("back-v1").innerText = v1;
        document.getElementById("back-v2").innerText = v2;
        document.getElementById("back-v3").innerText = v3;
      } else {
        frontWordEl.innerText = c.word;
        frontVerbBox.classList.add("hidden");
        backVerbBox.classList.add("hidden");
      }

      document.getElementById("front-reading").innerText = (c.pos ? c.pos + " " : "") + "คำอ่าน: " + (c.reading || "—");

      // Cloze highlight
      const clozeHtml = (c.cloze || c.example || "")
        .replace(/\[\s*______\s*\]/g, '<span class="px-2 py-0.5 bg-amber-200/90 font-bold text-amber-900 rounded border border-amber-300">[ ______ ]</span>')
        .replace(/\[Both\]/g, '<span class="px-2 py-0.5 bg-amber-200 font-bold text-amber-900 rounded">[Both]</span>')
        .replace(/\[and\]/g, '<span class="px-2 py-0.5 bg-amber-200 font-bold text-amber-900 rounded">[and]</span>')
        .replace(/\[Either\]/g, '<span class="px-2 py-0.5 bg-amber-200 font-bold text-amber-900 rounded">[Either]</span>')
        .replace(/\[or\]/g, '<span class="px-2 py-0.5 bg-amber-200 font-bold text-amber-900 rounded">[or]</span>')
        .replace(/\[Neither\]/g, '<span class="px-2 py-0.5 bg-amber-200 font-bold text-amber-900 rounded">[Neither]</span>')
        .replace(/\[nor\]/g, '<span class="px-2 py-0.5 bg-amber-200 font-bold text-amber-900 rounded">[nor]</span>');
      document.getElementById("front-cloze").innerHTML = clozeHtml || "—";

      // Status pill
      const statusPill = document.getElementById("front-status-pill");
      if (st === "mastered") {
        statusPill.innerHTML = "🟢 จำได้แม่น";
        statusPill.className = "text-[11px] sm:text-xs px-2 sm:px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-medium";
      } else if (st === "review") {
        statusPill.innerHTML = "🟡 พอจำได้";
        statusPill.className = "text-[11px] sm:text-xs px-2 sm:px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-medium";
      } else if (st === "learning") {
        statusPill.innerHTML = "🔴 ยังจำไม่ได้";
        statusPill.className = "text-[11px] sm:text-xs px-2 sm:px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-800 font-medium";
      } else {
        statusPill.innerHTML = "⚪ ยังไม่ได้เปิด";
        statusPill.className = "text-[11px] sm:text-xs px-2 sm:px-2.5 py-0.5 rounded-full border border-slate-200 text-slate-500";
      }

      // Back elements
      document.getElementById("back-cat-badge").innerText = c.category;
      document.getElementById("back-index").innerText = "#" + c.num + " / " + rawCards.length;
      
      const backWordEl = document.getElementById("back-word");
      if (isVerbWithThreeForms) {
        backWordEl.innerHTML = `<span class="text-emerald-700">${v1}</span> <span class="text-slate-300 font-normal">–</span> <span class="text-slate-800">${v2}</span> <span class="text-slate-300 font-normal">–</span> <span class="text-slate-800">${v3}</span>`;
      } else {
        backWordEl.innerText = c.word;
      }

      document.getElementById("back-reading").innerText = "(" + (c.pos ? c.pos + " " : "") + (c.reading || "") + ")";
      document.getElementById("back-meaning").innerText = c.meaning;
      document.getElementById("back-example").innerHTML = "<b>ประโยคเต็ม:</b> " + highlightWord(c.example, c.word, v1);
      document.getElementById("back-example-th").innerHTML = "<b>แปล:</b> " + (c.example_th || "—");

      // Synonyms & Antonyms
      const synContainer = document.getElementById("back-syn-container");
      if (!c.synonyms && !c.antonyms) {
        if (synContainer) synContainer.style.display = "none";
      } else {
        if (synContainer) synContainer.style.display = "grid";
        document.getElementById("back-synonyms").innerText = c.synonyms || "—";
        document.getElementById("back-antonyms").innerText = c.antonyms || "—";
      }

      // Memory hook
      document.getElementById("back-hook").innerHTML = c.memory_hook || "ทบทวนคำศัพท์นี้และนำไปใช้ในข้อสอบ!";

      // Jump input sync
      const jumpEl = document.getElementById("jump-input");
      if (jumpEl) jumpEl.value = c.num;
    }

    function highlightWord(example, word, baseWord) {
      if (!example) return "";
      let target = (baseWord || word || "").replace(/xx/, '').replace(/[…\.]/g, '').trim();
      if (target.includes("–") || target.includes("-")) {
        target = target.split(/[–\-]/)[0].trim();
      }
      target = target.replace(/[e]$/, '').trim();
      try {
        const regex = new RegExp("(\\b" + target + "\\w*\\b)", "gi");
        return example.replace(regex, '<span class="text-blue-600 font-bold underline">$1</span>');
      } catch (e) {
        return example;
      }
    }

    function flipCard(event) {
      if (isTouchSwiping) {
        isTouchSwiping = false;
        return;
      }
      if (event) {
        if (event.target.closest("button") || event.target.closest("input") || event.target.closest("select") || event.target.closest("textarea")) {
          return;
        }
      }
      isFlipped = !isFlipped;
      const el = document.getElementById("card-container");
      if (isFlipped) {
        el.classList.add("flipped");
      } else {
        el.classList.remove("flipped");
      }
    }

    function nextCard(event) {
      if (event) event.stopPropagation();
      if (cards.length === 0) return;
      isFlipped = false;
      document.getElementById("card-container").classList.remove("flipped");
      currentIndex = (currentIndex + 1) % cards.length;
      renderCurrentCard();
    }

    function prevCard(event) {
      if (event) event.stopPropagation();
      if (cards.length === 0) return;
      isFlipped = false;
      document.getElementById("card-container").classList.remove("flipped");
      currentIndex = (currentIndex - 1 + cards.length) % cards.length;
      renderCurrentCard();
    }

    function jumpToCard(val) {
      const num = parseInt(val, 10);
      if (isNaN(num)) return;
      const idx = cards.findIndex(function(c) { return c.num === num; });
      if (idx !== -1) {
        currentIndex = idx;
        isFlipped = false;
        document.getElementById("card-container").classList.remove("flipped");
        renderCurrentCard();
      } else {
        alert("ไม่พบคำศัพท์หมายเลข " + num + " ในตัวกรองปัจจุบัน");
      }
    }

    function toggleShuffle() {
      isShuffled = !isShuffled;
      const btn = document.getElementById("btn-shuffle");
      const label = document.getElementById("shuffle-label");
      if (isShuffled) {
        btn.classList.add("bg-blue-100", "text-blue-700", "border-blue-300");
        label.innerText = "กำลังสุ่ม";
      } else {
        btn.classList.remove("bg-blue-100", "text-blue-700", "border-blue-300");
        label.innerText = "สุ่มคำ";
      }
      filterCards();
    }

    // Rate card and save directly to MySQL
    function rateCard(status, event) {
      if (event) {
        event.stopPropagation();
        event.preventDefault();
      }
      if (cards.length === 0) return;
      const c = cards[currentIndex];
      mastery[c.num] = status;

      updateStatsUI();
      renderCurrentCard();

      const syncEl = document.getElementById("sync-status");
      if (syncEl) syncEl.innerHTML = "⏳ ซิงค์...";

      fetch("backend/progress.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          user_id: currentUserId,
          card_num: c.num,
          status: status
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.stats) {
          if (syncEl) syncEl.innerHTML = "💾 บันทึกใน MySQL แล้ว";
        }
      })
      .catch(err => {
        console.error("Save to MySQL error:", err);
        if (syncEl) syncEl.innerHTML = "⚠️ ซิงค์ขัดข้อง";
      });

      setTimeout(function() {
        nextCard();
      }, 230);
    }

    function updateStatsUI() {
      let mastered = 0, review = 0, learning = 0;
      for (const num in mastery) {
        if (mastery[num] === "mastered") mastered++;
        else if (mastery[num] === "review") review++;
        else if (mastery[num] === "learning") learning++;
      }

      document.getElementById("stat-mastered").innerText = mastered;
      document.getElementById("stat-review").innerText = review;
      document.getElementById("stat-learning").innerText = learning;

      const total = rawCards.length;
      const pct = total > 0 ? Math.round((mastered / total) * 100) : 0;
      document.getElementById("stat-percent").innerText = pct + "% จำแม่น";

      if (total > 0) {
        document.getElementById("bar-mastered").style.width = ((mastered / total) * 100) + "%";
        document.getElementById("bar-review").style.width = ((review / total) * 100) + "%";
        document.getElementById("bar-learning").style.width = ((learning / total) * 100) + "%";
      }
    }

    function resetProgress() {
      if (confirm("ต้องการรีเซ็ตผลการท่องจำทั้งหมดของผู้เรียนคนนี้ใน MySQL หรือไม่?")) {
        fetch("backend/progress.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            user_id: currentUserId,
            action: "reset"
          })
        })
        .then(res => res.json())
        .then(data => {
          mastery = {};
          updateStatsUI();
          filterCards();
          alert("รีเซ็ตคะแนนใน MySQL เรียบร้อยแล้ว");
        })
        .catch(err => {
          console.error("Reset error:", err);
          alert("เกิดข้อผิดพลาดในการรีเซ็ต");
        });
      }
    }

    function switchUser(userId) {
      currentUserId = parseInt(userId, 10);
      const syncEl = document.getElementById("sync-status");
      if (syncEl) syncEl.innerHTML = "⏳ โหลดข้อมูล...";

      fetch("backend/progress.php?user_id=" + currentUserId)
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          mastery = data.mastery || {};
          updateStatsUI();
          filterCards();
          if (syncEl) syncEl.innerHTML = "💾 เชื่อมต่อ MySQL";
        }
      })
      .catch(err => {
        console.error("Load user progress error:", err);
      });
    }

    // Audio Engine: Supports reading 3 verb forms in sequence or single word
    function speakWord(event) {
      if (event) {
        event.stopPropagation();
        event.preventDefault();
      }
      if (cards.length === 0) return;
      const c = cards[currentIndex];

      const btns = [document.getElementById("speaker-btn"), document.getElementById("speaker-btn-back")];
      btns.forEach(function(btn) {
        if (btn) {
          btn.classList.add("scale-125", "text-blue-500", "ring-2", "ring-blue-400");
          setTimeout(function() {
            btn.classList.remove("scale-125", "text-blue-500", "ring-2", "ring-blue-400");
          }, 600);
        }
      });

      // If card has 3 verb forms (v1, v2, v3), read all 3 forms in sequence
      const vForms = getCardVerbForms(c);
      if (vForms.hasForms) {
        speakThreeWords(vForms.v1, vForms.v2, vForms.v3);
        return;
      }

      // Normal single word
      let wordToSpeak = (c.word || "").replace(/xx/, '').replace(/[…\.]/g, '').trim();
      if (wordToSpeak.includes("–") || wordToSpeak.includes("-")) {
        wordToSpeak = wordToSpeak.split(/[–\-]/)[0].trim();
      }
      playSingleAudio(wordToSpeak);
    }

    // Read 3 verb forms sequentially with natural spacing and distinct articulation
    function speakThreeWords(w1, w2, w3) {
      const clean1 = (w1 || "").replace(/[^a-zA-Z\s]/g, '').trim();
      const clean2 = (w2 || "").replace(/[^a-zA-Z\s]/g, '').trim();
      const clean3 = (w3 || "").replace(/[^a-zA-Z\s]/g, '').trim();

      if (typeof window !== "undefined" && ("speechSynthesis" in window)) {
        try {
          window.speechSynthesis.cancel();
          const SpeechClass = window.SpeechSynthesisUtterance || window.webkitSpeechSynthesisUtterance;
          if (SpeechClass) {
            // Using period boundaries creates natural, distinct pauses for V.1 -> V.2 -> V.3
            const phrase = clean1 + ". " + clean2 + ". " + clean3 + ".";
            const u = new SpeechClass(phrase);
            u.lang = "en-US";
            u.rate = 0.78;

            const voices = window.speechSynthesis.getVoices();
            if (voices && voices.length > 0) {
              const enVoice = voices.find(function(v) {
                return (v.lang === "en-US" || v.lang.startsWith("en")) &&
                       (v.name.includes("Google") || v.name.includes("Samantha") || v.name.includes("Siri") || v.name.includes("Daniel") || v.default);
              });
              if (enVoice) u.voice = enVoice;
            }

            window.speechSynthesis.speak(u);
            return;
          }
        } catch (e) {
          console.warn("TTS speakThreeWords error:", e);
        }
      }

      playSingleAudio(clean1);
    }

    // Pronounce a single specific form when tapped
    function speakSingleWord(event, formKey) {
      if (event) {
        event.stopPropagation();
        event.preventDefault();
      }
      if (cards.length === 0) return;
      const c = cards[currentIndex];
      const vForms = getCardVerbForms(c);
      let word = vForms[formKey] || c[formKey] || c.word;
      if (word && (word.includes("–") || word.includes("-"))) {
        word = word.split(/[–\-]/)[0].trim();
      }

      if (event && event.currentTarget) {
        const el = event.currentTarget;
        el.classList.add("ring-2", "ring-blue-400", "scale-105");
        setTimeout(function() {
          el.classList.remove("ring-2", "ring-blue-400", "scale-105");
        }, 350);
      }

      playSingleAudio(word);
    }

    function playSingleAudio(wordToSpeak) {
      const audioUrl = "https://dict.youdao.com/dictvoice?audio=" + encodeURIComponent(wordToSpeak) + "&type=2";
      if (typeof Audio !== "undefined") {
        try {
          if (currentAudio) {
            currentAudio.pause();
            currentAudio.currentTime = 0;
          }
          currentAudio = new Audio(audioUrl);
          const playPromise = currentAudio.play();
          if (playPromise !== undefined) {
            playPromise.catch(function(err) {
              fallbackSpeechSynthesis(wordToSpeak);
            });
          }
          return;
        } catch (e) {}
      }
      fallbackSpeechSynthesis(wordToSpeak);
    }

    function fallbackSpeechSynthesis(text) {
      if (typeof window === "undefined" || !("speechSynthesis" in window)) return;
      const SpeechClass = window.SpeechSynthesisUtterance || window.webkitSpeechSynthesisUtterance;
      if (!SpeechClass) return;

      try {
        window.speechSynthesis.cancel();
        setTimeout(function() {
          const u = new SpeechClass(text);
          u.lang = "en-US";
          u.rate = 0.85;
          try {
            const voices = window.speechSynthesis.getVoices();
            const enVoice = voices.find(function(v) {
              return (v.lang === "en-US" || v.lang.startsWith("en")) &&
                     (v.name.includes("Google") || v.name.includes("Samantha") || v.name.includes("Siri") || v.default);
            });
            if (enVoice) u.voice = enVoice;
          } catch (e) {}
          window.speechSynthesis.speak(u);
        }, 25);
      } catch (e) {}
    }

    function toggleVerbInputs(cat) {
      const verbSec = document.getElementById("form-verb-section");
      if (cat === "คำกริยา (Verb)") {
        verbSec.classList.remove("hidden");
      } else {
        verbSec.classList.add("hidden");
      }
    }

    // Modal Add / Edit Card
    function openCardModal(cardToEdit = null) {
      const modal = document.getElementById("card-modal");
      const title = document.getElementById("modal-title");
      const delBtn = document.getElementById("btn-delete-card");

      if (cardToEdit) {
        title.innerText = "✏️ แก้ไขคำศัพท์ #" + cardToEdit.num + " ใน MySQL";
        delBtn.classList.remove("hidden");
        const vForms = getCardVerbForms(cardToEdit);
        document.getElementById("form-num").value = cardToEdit.num;
        document.getElementById("form-word").value = cardToEdit.word || "";
        document.getElementById("form-pos").value = cardToEdit.pos || "";
        document.getElementById("form-v1").value = vForms.v1 || cardToEdit.v1 || "";
        document.getElementById("form-v2").value = vForms.v2 || cardToEdit.v2 || "";
        document.getElementById("form-v3").value = vForms.v3 || cardToEdit.v3 || "";
        document.getElementById("form-meaning").value = cardToEdit.meaning || "";
        document.getElementById("form-category").value = cardToEdit.category || "คำนาม (Noun)";
        document.getElementById("form-reading").value = cardToEdit.reading || "";
        document.getElementById("form-icon").value = cardToEdit.icon || "📖";
        document.getElementById("form-example").value = cardToEdit.example || "";
        document.getElementById("form-example-th").value = cardToEdit.example_th || "";
        document.getElementById("form-synonyms").value = cardToEdit.synonyms || "";
        document.getElementById("form-antonyms").value = cardToEdit.antonyms || "";
        document.getElementById("form-hook").value = cardToEdit.memory_hook || "";
        toggleVerbInputs(cardToEdit.category);
      } else {
        title.innerText = "➕ เพิ่มคำศัพท์ใหม่ลงใน MySQL";
        delBtn.classList.add("hidden");
        document.getElementById("card-form").reset();
        document.getElementById("form-num").value = "0";
        toggleVerbInputs("คำนาม (Noun)");
      }

      modal.classList.remove("hidden");
    }

    function editCurrentCard(event) {
      if (event) event.stopPropagation();
      if (cards.length === 0) return;
      openCardModal(cards[currentIndex]);
    }

    function closeCardModal() {
      document.getElementById("card-modal").classList.add("hidden");
    }

    function saveCardForm(event) {
      event.preventDefault();
      const num = parseInt(document.getElementById("form-num").value, 10);
      const payload = {
        action: "save",
        num: num,
        word: document.getElementById("form-word").value.trim(),
        pos: document.getElementById("form-pos").value.trim(),
        v1: document.getElementById("form-v1").value.trim(),
        v2: document.getElementById("form-v2").value.trim(),
        v3: document.getElementById("form-v3").value.trim(),
        meaning: document.getElementById("form-meaning").value.trim(),
        category: document.getElementById("form-category").value,
        reading: document.getElementById("form-reading").value.trim(),
        icon: document.getElementById("form-icon").value.trim() || "📖",
        example: document.getElementById("form-example").value.trim(),
        example_th: document.getElementById("form-example-th").value.trim(),
        synonyms: document.getElementById("form-synonyms").value.trim(),
        antonyms: document.getElementById("form-antonyms").value.trim(),
        memory_hook: document.getElementById("form-hook").value.trim()
      };

      fetch("backend/cards.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert("บันทึกลงฐานข้อมูล MySQL สำเร็จ!");
          closeCardModal();
          reloadCardsFromBackend();
        } else {
          alert("เกิดข้อผิดพลาด: " + (data.error || "ไม่สามารถบันทึกได้"));
        }
      })
      .catch(err => {
        alert("ข้อผิดพลาดเครือข่าย: " + err.message);
      });
    }

    function deleteCurrentCard() {
      const num = parseInt(document.getElementById("form-num").value, 10);
      if (!num || num <= 0) return;
      if (!confirm("คุณแน่ใจว่าต้องการลบคำศัพท์หมายเลข #" + num + " ออกจาก MySQL?")) return;

      fetch("backend/cards.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "delete", num: num })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert("ลบคำศัพท์เรียบร้อยแล้ว");
          closeCardModal();
          reloadCardsFromBackend();
        }
      });
    }

    function reloadCardsFromBackend() {
      fetch("backend/cards.php?user_id=" + currentUserId)
      .then(res => res.json())
      .then(data => {
        if (data.success && data.cards) {
          rawCards = data.cards;
          renderCategoryPills();
          filterCards();
          updateStatsUI();
        }
      });
    }

    // Modal New User
    function openNewUserModal() {
      document.getElementById("user-modal").classList.remove("hidden");
    }

    function closeNewUserModal() {
      document.getElementById("user-modal").classList.add("hidden");
    }

    function saveNewUser(event) {
      event.preventDefault();
      const username = document.getElementById("new-username").value.trim();
      const displayName = document.getElementById("new-display-name").value.trim();

      fetch("backend/users.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username: username, display_name: displayName })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.user) {
          const sel = document.getElementById("user-selector");
          const opt = document.createElement("option");
          opt.value = data.user.id;
          opt.innerText = data.user.display_name;
          opt.selected = true;
          sel.appendChild(opt);
          closeNewUserModal();
          switchUser(data.user.id);
        } else {
          alert(data.error || "ไม่สามารถสร้างผู้เรียนได้");
        }
      });
    }

    // Modal Stats
    function openStatsModal() {
      const modal = document.getElementById("stats-modal");
      modal.classList.remove("hidden");

      fetch("backend/stats.php?user_id=" + currentUserId)
      .then(res => res.json())
      .then(data => {
        if (data.success && data.categories) {
          const list = document.getElementById("stats-category-list");
          list.innerHTML = data.categories.map(c => `
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5 sm:p-3">
              <div class="flex justify-between items-center font-bold text-slate-800 mb-1">
                <span class="text-xs sm:text-sm">${c.category}</span>
                <span class="text-emerald-600 font-semibold text-xs">${c.percent}% (${c.mastered}/${c.total})</span>
              </div>
              <div class="w-full h-1.5 sm:h-2 bg-slate-200 rounded-full overflow-hidden flex mb-1.5">
                <div class="bg-emerald-500 h-full" style="width: ${(c.mastered/c.total)*100}%"></div>
                <div class="bg-amber-400 h-full" style="width: ${(c.review/c.total)*100}%"></div>
                <div class="bg-rose-500 h-full" style="width: ${(c.learning/c.total)*100}%"></div>
              </div>
              <div class="flex justify-between text-[10px] sm:text-[11px] text-slate-500">
                <span class="text-emerald-700">🟢 จำแม่น: ${c.mastered}</span>
                <span class="text-amber-700">🟡 พอจำได้: ${c.review}</span>
                <span class="text-rose-700">🔴 ยังไม่จำ: ${c.learning}</span>
                <span class="text-slate-400">⚪ ยังไม่เปิด: ${c.unseen}</span>
              </div>
            </div>
          `).join("");
        }
      });
    }

    function closeStatsModal() {
      document.getElementById("stats-modal").classList.add("hidden");
    }

    // Expose global window bindings
    window.flipCard = flipCard;
    window.nextCard = nextCard;
    window.prevCard = prevCard;
    window.jumpToCard = jumpToCard;
    window.toggleShuffle = toggleShuffle;
    window.rateCard = rateCard;
    window.selectCategory = selectCategory;
    window.handleSearch = handleSearch;
    window.handleStatusFilter = handleStatusFilter;
    window.resetProgress = resetProgress;
    window.speakWord = speakWord;
    window.speakSingleWord = speakSingleWord;
    window.switchUser = switchUser;
    window.openCardModal = openCardModal;
    window.editCurrentCard = editCurrentCard;
    window.closeCardModal = closeCardModal;
    window.saveCardForm = saveCardForm;
    window.deleteCurrentCard = deleteCurrentCard;
    window.openNewUserModal = openNewUserModal;
    window.closeNewUserModal = closeNewUserModal;
    window.saveNewUser = saveNewUser;
    window.openStatsModal = openStatsModal;
    window.closeStatsModal = closeStatsModal;
    window.toggleVerbInputs = toggleVerbInputs;

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", init);
    } else {
      init();
    }
  </script>
</body>
</html>
