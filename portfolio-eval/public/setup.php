<?php
// ตัวติดตั้งครั้งแรก: สร้างฐานข้อมูล/ตาราง และบัญชี Admin แรก  (ใช้เสร็จแล้วให้ลบไฟล์นี้ทิ้ง)
require __DIR__ . '/../app/db.php';
date_default_timezone_set(cfg('timezone'));
$c = cfg('db');
$msg = ''; $ok = false; $locked = false;

try {
    $pdo = new PDO("mysql:host={$c['host']};charset={$c['charset']}", $c['user'], $c['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $dbName = preg_replace('/[^A-Za-z0-9_]/', '', $c['name']);
    $exists = $pdo->query("SHOW DATABASES LIKE '$dbName'")->fetchColumn();
    if ($exists) {
        $pdo->exec("USE `$dbName`");
        try { $locked = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0; } catch (Throwable $e) {}
    }
} catch (Throwable $e) {
    $msg = 'เชื่อมต่อ MySQL ไม่ได้: ' . $e->getMessage() . ' — ตรวจสอบค่า db ใน app/config.php';
    $pdo = null;
}

if ($pdo && !$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid = trim($_POST['staff_id'] ?? ''); $name = trim($_POST['full_name'] ?? ''); $pw = $_POST['password'] ?? '';
    if ($sid === '' || $name === '' || strlen($pw) < 8) {
        $msg = 'กรุณากรอกข้อมูลให้ครบ และรหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
    } else {
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");
            $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
            foreach (preg_split('/;\s*\n/', $sql) as $stmt) { if (trim($stmt) !== '') $pdo->exec($stmt); }

            // ข้อความ Rubric เริ่มต้น (ให้แก้ตามเอกสาร Rubric ปี 2570 ที่เมนู "เกณฑ์ Rubric")
            $levels = [5 => 'ดีเยี่ยม', 4 => 'ดี', 3 => 'พอใช้', 2 => 'ต้องปรับปรุง', 1 => 'ไม่เป็นไปตามเกณฑ์'];
            $labels = ['personal' => 'Personal Statement', 'activity' => 'กิจกรรมและผลงาน', 'rec' => 'หนังสือรับรอง', 'english' => 'ภาษาอังกฤษ'];
            $ins = $pdo->prepare("INSERT IGNORE INTO rubric (criterion_key, score, description) VALUES (?,?,?)");
            foreach ($labels as $k => $l) foreach ($levels as $s => $t)
                $ins->execute([$k, $s, "ระดับ $s ($t): [ตัวอย่างข้อความ] กรุณาแก้ไขเกณฑ์ของหัวข้อ \"$l\" ตามเอกสาร Rubric ปี 2570"]);

            $pdo->prepare("INSERT INTO users (staff_id, password_hash, full_name, role, must_change_password) VALUES (?,?,?,'ADMIN',0)")
                ->execute([$sid, password_hash($pw, PASSWORD_DEFAULT), $name]);
            $ok = true;
        } catch (Throwable $e) { $msg = 'เกิดข้อผิดพลาด: ' . $e->getMessage(); }
    }
}
?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ติดตั้งระบบ</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{font-family:'Sarabun',sans-serif;background:#F4F7FB}.card{border:0;border-radius:16px;box-shadow:0 4px 18px rgba(15,23,42,.08)}</style></head>
<body><div class="container py-5" style="max-width:520px"><div class="card p-4">
<h4 class="mb-3">🛠️ ติดตั้งระบบประเมิน Portfolio</h4>
<?php if ($ok): ?>
  <div class="alert alert-success">ติดตั้งสำเร็จ! สร้างตารางและบัญชี Admin เรียบร้อย</div>
  <p class="mb-2"><b>สำคัญ:</b> ให้ลบไฟล์ <code>public/setup.php</code> ทิ้งทันทีเพื่อความปลอดภัย</p>
  <a class="btn btn-primary w-100" href="<?= htmlspecialchars(rtrim((string)cfg('base_url'), '/') . '/login.php') ?>">ไปหน้าเข้าสู่ระบบ</a>
<?php elseif ($locked): ?>
  <div class="alert alert-warning">ระบบถูกติดตั้งแล้ว (มีผู้ใช้อยู่ในฐานข้อมูล) กรุณาลบไฟล์ <code>public/setup.php</code> ออก</div>
<?php else: ?>
  <?php if ($msg): ?><div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($pdo): ?>
  <p class="text-muted">ฐานข้อมูล: <code><?= htmlspecialchars($c['name']) ?></code> (จะถูกสร้างให้ถ้ายังไม่มี) — สร้างบัญชี Admin คนแรก</p>
  <form method="post">
    <div class="mb-3"><label class="form-label">รหัสบุคลากร (Staff ID)</label><input name="staff_id" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">ชื่อ-สกุล</label><input name="full_name" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)</label><input type="password" name="password" class="form-control" minlength="8" required></div>
    <button class="btn btn-primary w-100">ติดตั้งระบบ</button>
  </form>
  <?php else: ?><div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php endif; ?>
</div></div></body></html>
