<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$pdo = db(); $u = user(); $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $old = $_POST['old'] ?? ''; $new = $_POST['new'] ?? ''; $new2 = $_POST['new2'] ?? '';
    $st = $pdo->prepare("SELECT password_hash FROM users WHERE user_id=?"); $st->execute([$u['id']]);
    if (!password_verify($old, (string)$st->fetchColumn())) $errors[] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    if (strlen($new) < 8) $errors[] = 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 8 ตัวอักษร';
    if ($new !== $new2) $errors[] = 'การยืนยันรหัสผ่านใหม่ไม่ตรงกัน';
    if ($new === $old) $errors[] = 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสเดิม';
    if (!$errors) {
        $pdo->prepare("UPDATE users SET password_hash=?, must_change_password=0 WHERE user_id=?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        $_SESSION['user']['must_change'] = 0;
        flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
        redirect('index.php');
    }
}
$pageTitle = 'เปลี่ยนรหัสผ่าน';
include __DIR__ . '/../app/layout/header.php';
?>
<div class="row justify-content-center"><div class="col-md-6 col-lg-4">
  <div class="card-soft p-4">
    <h5 class="fw-bold mb-1"><i class="bi bi-key text-primary"></i> เปลี่ยนรหัสผ่าน</h5>
    <?php if ($u['must_change']): ?><div class="alert alert-warning py-2 mt-2 small">กรุณาตั้งรหัสผ่านใหม่ก่อนเริ่มใช้งานครั้งแรก</div><?php endif; ?>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2 small mb-2"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" class="mt-3"><?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">รหัสผ่านปัจจุบัน</label><input type="password" name="old" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">รหัสผ่านใหม่ <small class="text-muted">(อย่างน้อย 8 ตัวอักษร)</small></label><input type="password" name="new" class="form-control" minlength="8" required></div>
      <div class="mb-4"><label class="form-label">ยืนยันรหัสผ่านใหม่</label><input type="password" name="new2" class="form-control" minlength="8" required></div>
      <button class="btn btn-primary w-100">บันทึกรหัสผ่านใหม่</button>
    </form>
  </div>
</div></div>
<?php include __DIR__ . '/../app/layout/footer.php'; ?>
