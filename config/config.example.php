<?php
/**
 * แม่แบบการตั้งค่าฐานข้อมูล (Configuration Template)
 * ----------------------------------------------------
 * บน Server จริง: ให้คัดลอกไฟล์นี้เป็น config.php แล้วแก้ไขค่าตาม Server จริงของท่าน
 * หรือใช้ Environment Variables (DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT)
 */
return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('DB_PORT') ?: 3306),
    'socket' => getenv('DB_SOCKET') ?: '',
    'database' => getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'flashcards_db'),
    'username' => getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: 'root'),
    'password' => getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: ''),
    'charset' => 'utf8mb4'
];
