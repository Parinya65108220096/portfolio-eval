<?php
require __DIR__ . '/../app/bootstrap.php';
if (user()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $error = attempt_login($_POST['staff_id'] ?? '', $_POST['password'] ?? '');
    if (!$error) redirect('index.php');
}
$pageTitle = 'เข้าสู่ระบบ'; $bodyClass = 'p-0';
?><!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>เข้าสู่ระบบ | <?= e(cfg('app_name')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head><body>
<div class="login-wrap"><div class="login-card">
  <div class="login-icon"><i class="bi bi-journal-check"></i></div>
  <h4 class="text-center fw-bold mb-1"><?= e(cfg('app_name')) ?></h4>
  <p class="text-center text-muted mb-4">เข้าสู่ระบบด้วยรหัสบุคลากร</p>
  <?php if ($error): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle"></i> <?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">รหัสบุคลากร (Staff ID)</label>
      <div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
        <input name="staff_id" class="form-control form-control-lg" value="<?= e($_POST['staff_id'] ?? '') ?>" required autofocus></div>
    </div>
    <div class="mb-4">
      <label class="form-label">รหัสผ่าน</label>
      <div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
        <input type="password" name="password" id="pw" class="form-control form-control-lg" required>
        <button class="btn btn-outline-secondary" type="button" onclick="pw.type=pw.type==='password'?'text':'password'"><i class="bi bi-eye"></i></button></div>
    </div>
    <button class="btn btn-primary btn-lg w-100">เข้าสู่ระบบ</button>
  </form>
  <p class="text-center text-muted small mt-4 mb-0">หากลืมรหัสผ่าน กรุณาติดต่อผู้ดูแลระบบ</p>
</div></div>
</body></html>
