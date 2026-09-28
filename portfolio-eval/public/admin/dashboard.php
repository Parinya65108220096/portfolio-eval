<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('ADMIN');
$pdo = db();

$stu = $pdo->query("SELECT b.branch_id, b.branch_name, COUNT(s.student_id) total,
    COALESCE(SUM(s.final_status='PASS'),0) pass, COALESCE(SUM(s.final_status='FAIL'),0) fail,
    COALESCE(SUM(s.final_status='PENDING'),0) pending
    FROM branches b LEFT JOIN students s ON s.branch_id = b.branch_id GROUP BY b.branch_id, b.branch_name ORDER BY b.branch_id")->fetchAll();
$ev = [];
foreach ($pdo->query("SELECT s.branch_id, COUNT(a.assignment_id) tot, COALESCE(SUM(e.is_submitted=1),0) done
    FROM assignments a JOIN students s ON s.student_id = a.student_id
    LEFT JOIN evaluations e ON e.assignment_id = a.assignment_id GROUP BY s.branch_id") as $r) $ev[$r['branch_id']] = $r;
$unassigned = (int)$pdo->query("SELECT COUNT(*) FROM students s WHERE (SELECT COUNT(*) FROM assignments a WHERE a.student_id=s.student_id) < 2")->fetchColumn();
$nofile = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE file_path=''")->fetchColumn();
$teachers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='TEACHER' AND is_active=1")->fetchColumn();
$flags = 0;
foreach ($pdo->query("SELECT student_id, MAX(total_score)-MIN(total_score) d FROM evaluations e JOIN assignments a ON a.assignment_id=e.assignment_id WHERE e.is_submitted=1 GROUP BY student_id HAVING COUNT(*)=2") as $r)
    if ($r['d'] >= cfg('diff_flag')) $flags++;

$pageTitle = 'ภาพรวม'; $nav = 'dashboard';
include __DIR__ . '/../../app/layout/header.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
  <div><h1 class="page-title">ภาพรวมระบบ</h1><p class="page-sub mb-0">ความคืบหน้าการประเมิน Portfolio แยกตามสาขา</p></div>
  <a class="btn btn-primary" href="<?= e(url('admin/export.php')) ?>"><i class="bi bi-file-earmark-excel"></i> ส่งออก Excel</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="card-soft stat-card"><div class="stat-num"><?= $teachers ?></div><div class="stat-label">อาจารย์ผู้ประเมิน (ใช้งานอยู่)</div></div></div>
  <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= e(url('admin/assignments.php')) ?>"><div class="card-soft stat-card"><div class="stat-num" style="color:#D97706"><?= $unassigned ?></div><div class="stat-label">นักศึกษาที่ยังมอบหมายไม่ครบ 2 ท่าน</div></div></a></div>
  <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= e(url('admin/students.php')) ?>"><div class="card-soft stat-card"><div class="stat-num" style="color:#D97706"><?= $nofile ?></div><div class="stat-label">นักศึกษาที่ยังไม่แนบ Portfolio</div></div></a></div>
  <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= e(url('admin/results.php')) ?>"><div class="card-soft stat-card"><div class="stat-num text-danger"><?= $flags ?></div><div class="stat-label">คะแนนอาจารย์ต่างกัน ≥ <?= (int)cfg('diff_flag') ?> คะแนน</div></div></a></div>
</div>

<div class="row g-3">
<?php foreach ($stu as $b):
    $e = $ev[$b['branch_id']] ?? ['tot' => 0, 'done' => 0];
    $pct = $e['tot'] ? round($e['done'] / $e['tot'] * 100) : 0; ?>
  <div class="col-lg-6"><div class="card-soft p-4 h-100">
    <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="fw-bold mb-0"><i class="bi bi-mortarboard text-primary"></i> <?= e($b['branch_name']) ?></h5>
      <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/results.php?branch=' . $b['branch_id'])) ?>">ดูผล</a></div>
    <div class="row text-center g-2 mb-3">
      <div class="col-3"><div class="stat-num"><?= (int)$b['total'] ?></div><div class="stat-label">นักศึกษา</div></div>
      <div class="col-3"><div class="stat-num text-success"><?= (int)$b['pass'] ?></div><div class="stat-label">ผ่าน</div></div>
      <div class="col-3"><div class="stat-num text-danger"><?= (int)$b['fail'] ?></div><div class="stat-label">ไม่ผ่าน</div></div>
      <div class="col-3"><div class="stat-num text-secondary"><?= (int)$b['pending'] ?></div><div class="stat-label">รอผล</div></div>
    </div>
    <div class="d-flex justify-content-between small text-muted mb-1"><span>ส่งผลประเมินแล้ว <?= (int)$e['done'] ?>/<?= (int)$e['tot'] ?> ชุด</span><span><?= $pct ?>%</span></div>
    <div class="progress"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
  </div></div>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/../../app/layout/footer.php'; ?>
