<?php
// Database helper and auto-migration
function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // Load config: prioritize config.php, fallback to config.example.php or env
    $configFile = __DIR__ . '/config.php';
    $exampleFile = __DIR__ . '/config.example.php';
    
    if (file_exists($configFile)) {
        $config = require $configFile;
    } elseif (file_exists($exampleFile)) {
        $config = require $exampleFile;
    } else {
        $config = [];
    }

    $host = $config['host'] ?? (getenv('DB_HOST') ?: '127.0.0.1');
    $port = (int)($config['port'] ?? (getenv('DB_PORT') ?: 3306));
    $dbName = $config['database'] ?? (getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'flashcards_db'));
    $user = $config['username'] ?? (getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: 'root'));
    $pass = $config['password'] ?? (getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: ''));
    $charset = $config['charset'] ?? 'utf8mb4';
    $socket = $config['socket'] ?? (getenv('DB_SOCKET') ?: '');

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    // Attempt 1: Direct connection to target database (ideal for production / shared hosting)
    $connected = false;
    $dsnWithDb = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
    
    try {
        $pdo = new PDO($dsnWithDb, $user, $pass, $options);
        $connected = true;
    } catch (PDOException $eDirect) {
        // Direct connection failed, attempt socket if available
        if (!empty($socket) && file_exists($socket)) {
            try {
                $dsnSocketDb = "mysql:unix_socket={$socket};dbname={$dbName};charset={$charset}";
                $pdo = new PDO($dsnSocketDb, $user, $pass, $options);
                $connected = true;
            } catch (PDOException $eSock) {}
        }
    }

    // Attempt 2: If target database does not exist yet (local dev environment), try to create it
    if (!$connected) {
        $dsnNoDb = "mysql:host={$host};port={$port};charset={$charset}";
        $tempPdo = null;

        try {
            $tempPdo = new PDO($dsnNoDb, $user, $pass, $options);
        } catch (PDOException $e) {
            if (!empty($socket) && file_exists($socket)) {
                $dsnSocket = "mysql:unix_socket={$socket};charset={$charset}";
                try {
                    $tempPdo = new PDO($dsnSocket, $user, $pass, $options);
                } catch (PDOException $e2) {}
            }
        }

        if ($tempPdo) {
            try {
                $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (Exception $eCreateDb) {
                // If user has no permission to create database on production, ignore and proceed
            }
        }

        // Connect again after creation attempt
        try {
            $pdo = new PDO($dsnWithDb, $user, $pass, $options);
        } catch (PDOException $eFinal) {
            if (!empty($socket) && file_exists($socket)) {
                $dsnSocketDb = "mysql:unix_socket={$socket};dbname={$dbName};charset={$charset}";
                $pdo = new PDO($dsnSocketDb, $user, $pass, $options);
            } else {
                throw $eFinal;
            }
        }
    }

    // Ensure tables exist and data seeded without overwriting existing data
    initSchemaAndData($pdo);

    return $pdo;
}

function initSchemaAndData(PDO $pdo) {
    // 1. Flashcards table (IF NOT EXISTS prevents altering existing table)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `flashcards` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `num` INT NOT NULL UNIQUE,
        `word` VARCHAR(150) NOT NULL,
        `pos` VARCHAR(50) DEFAULT '',
        `v1` VARCHAR(100) DEFAULT NULL,
        `v2` VARCHAR(100) DEFAULT NULL,
        `v3` VARCHAR(100) DEFAULT NULL,
        `meaning` TEXT NOT NULL,
        `category` VARCHAR(100) NOT NULL,
        `reading` VARCHAR(150) DEFAULT '',
        `example` TEXT DEFAULT NULL,
        `example_th` TEXT DEFAULT NULL,
        `cloze` TEXT DEFAULT NULL,
        `icon` VARCHAR(50) DEFAULT '📖',
        `synonyms` TEXT DEFAULT NULL,
        `antonyms` TEXT DEFAULT NULL,
        `memory_hook` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_cat` (`category`),
        INDEX `idx_word` (`word`),
        INDEX `idx_num` (`num`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Ensure v1, v2, v3 columns exist if table was created previously without them
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM `flashcards` LIKE 'v1'")->fetch();
        if (!$colCheck) {
            $pdo->exec("ALTER TABLE `flashcards` ADD COLUMN `v1` VARCHAR(100) DEFAULT NULL AFTER `pos`");
            $pdo->exec("ALTER TABLE `flashcards` ADD COLUMN `v2` VARCHAR(100) DEFAULT NULL AFTER `v1`");
            $pdo->exec("ALTER TABLE `flashcards` ADD COLUMN `v3` VARCHAR(100) DEFAULT NULL AFTER `v2`");
        }
    } catch (Exception $eCol) {}

    // 2. Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `display_name` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 3. Card mastery progress table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `card_progress` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `card_num` INT NOT NULL,
        `status` ENUM('unseen', 'learning', 'review', 'mastered') NOT NULL DEFAULT 'unseen',
        `review_count` INT NOT NULL DEFAULT 0,
        `last_reviewed_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_user_card` (`user_id`, `card_num`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Ensure default user exists if table is empty
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount == 0) {
        $ins = $pdo->prepare("INSERT IGNORE INTO users (id, username, display_name) VALUES (1, ?, ?)");
        $ins->execute(['student1', 'ผู้สอบครูผู้ช่วย 2569']);
    }

    // Check if flashcards are populated - ONLY seed if table is completely empty
    $count = $pdo->query("SELECT COUNT(*) FROM flashcards")->fetchColumn();
    if ($count == 0) {
        $jsonPath = __DIR__ . '/../data/kru_whan_500_flashcards.json';
        if (file_exists($jsonPath)) {
            $cards = json_decode(file_get_contents($jsonPath), true);
            if (is_array($cards) && !empty($cards)) {
                $insert = $pdo->prepare("INSERT IGNORE INTO flashcards 
                    (num, word, pos, v1, v2, v3, meaning, category, reading, example, example_th, cloze, icon, synonyms, antonyms, memory_hook)
                    VALUES 
                    (:num, :word, :pos, :v1, :v2, :v3, :meaning, :category, :reading, :example, :example_th, :cloze, :icon, :synonyms, :antonyms, :memory_hook)
                ");

                $pdo->beginTransaction();
                foreach ($cards as $c) {
                    $insert->execute([
                        ':num' => $c['num'] ?? 0,
                        ':word' => $c['word'] ?? '',
                        ':pos' => $c['pos'] ?? '',
                        ':v1' => $c['v1'] ?? null,
                        ':v2' => $c['v2'] ?? null,
                        ':v3' => $c['v3'] ?? null,
                        ':meaning' => $c['meaning'] ?? '',
                        ':category' => $c['category'] ?? '',
                        ':reading' => $c['reading'] ?? '',
                        ':example' => $c['example'] ?? '',
                        ':example_th' => $c['example_th'] ?? '',
                        ':cloze' => $c['cloze'] ?? '',
                        ':icon' => $c['icon'] ?? '📖',
                        ':synonyms' => $c['synonyms'] ?? '',
                        ':antonyms' => $c['antonyms'] ?? '',
                        ':memory_hook' => $c['memory_hook'] ?? '',
                    ]);
                }
                $pdo->commit();
            }
        }
    }
}
