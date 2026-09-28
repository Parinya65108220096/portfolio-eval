<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('ADMIN');
$pdo = db();
$crit = criteria();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $st = $pdo->prepare("INSERT INTO rubric (criterion_key, score, description) VALUES (?,?,?) ON DUPLICATE KEY UPDATE description=VALUES(description)");
    foreach (array_keys($crit) as $k) for ($i = 1; $i <= 5; $i++) {
        $t = trim($_POST["r_{$k}_{$i}"] ?? '');
        if ($t !== '') $st->execute([$k, $i, mb_substr($t, 0, 2000)]);
    }
    audit('UPDATE_RUBRIC');
    flash('success', 'บันทึกเกณฑ์ Rubric เรียบร้อย (มีผลกับหน้าประเมินทันที)');
    redirect('admin/rubric.php');
}
$r = [];
foreach ($pdo->query("SELECT criterion_key, score, description FROM rubric") as $x) $r[$x['criterion_key']][$x['score']] = $x['description'];

$pageTitle = 'เกณฑ์ Rubric'; $nav = 'rubric';
include __DIR__ . '/../../app/layout/header.php';
?>
<h1 class="page-title">เกณฑ์ Rubric (5 ระดับ)</h1>
<p class="page-sub">ข้อความนี้จะแสดงให้อาจารย์เห็นทันทีเมื่อเลือกคะแนนในหน้าประเมิน — กรอกตามเอกสาร Rubric ปี 2570</p>
<form method="post"><?= csrf_field() ?>
  <?php foreach ($crit as $k => $c): ?>
  <div class="card-soft p-4 mb-3">
    <h5 class="fw-bold mb-3"><i class="bi <?= $c['icon'] ?> text-primary"></i> <?= e($c['label']) ?> <span class="weight-badge ms-1"><?= (int)cfg('weights')[$k] ?>%</span></h5>
    <?php for ($i = 5; $i >= 1; $i--): ?>
      <div class="row g-2 mb-2 align-items-start"><div class="col-auto"><span class="badge text-bg-primary" style="width:2.2rem;padding:.6rem 0"><?= $i ?></span></div>
        <div class="col"><textarea name="r_<?= $k ?>_<?= $i ?>" class="form-control form-control-sm" rows="2"><?= e($r[$k][$i] ?? '') ?></textarea></div></div>
    <?php endfor; ?>
  </div>
  <?php endforeach; ?>
  <div class="text-end"><button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึกเกณฑ์ทั้งหมด</button></div>
</form>
<?php include __DIR__ . '/../../app/layout/footer.php'; ?>
