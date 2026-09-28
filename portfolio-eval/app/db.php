<?php
function cfg(?string $key = null) {
    static $c;
    $c ??= require __DIR__ . '/config.php';
    if ($key === null) return $c;
    $v = $c;
    foreach (explode('.', $key) as $p) { $v = $v[$p] ?? null; }
    return $v;
}

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = cfg('db');
        try {
            $pdo = new PDO(
                "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}",
                $c['user'], $c['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES => false]
            );
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            exit('เชื่อมต่อฐานข้อมูลไม่ได้ กรุณาตรวจสอบ app/config.php และรัน setup.php ก่อน');
        }
    }
    return $pdo;
}
