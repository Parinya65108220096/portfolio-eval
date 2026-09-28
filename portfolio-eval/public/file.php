<?php
// ส่งไฟล์ PDF Portfolio เฉพาะผู้มีสิทธิ์ (Admin หรืออาจารย์ที่ได้รับมอบหมายนักศึกษาคนนั้น)
require __DIR__ . '/../app/bootstrap.php';
$u = user();
if (!$u) { http_response_code(403); exit('กรุณาเข้าสู่ระบบ'); }
$sid = (int)($_GET['sid'] ?? 0);
$pdo = db();
if ($u['role'] !== 'ADMIN') {
    $chk = $pdo->prepare("SELECT 1 FROM assignments WHERE student_id=? AND teacher_id=?");
    $chk->execute([$sid, $u['id']]);
    if (!$chk->fetchColumn()) { http_response_code(403); exit('ไม่มีสิทธิ์เปิดไฟล์นี้'); }
}
$st = $pdo->prepare("SELECT file_path FROM students WHERE student_id=? AND file_type='PDF'");
$st->execute([$sid]);
$name = basename((string)$st->fetchColumn());
$path = portfolio_dir() . $name;
if ($name === '' || !preg_match('/^[a-f0-9]{32}\.pdf$/', $name) || !is_file($path)) { http_response_code(404); exit('ไม่พบไฟล์'); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="portfolio.pdf"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0');
readfile($path);
