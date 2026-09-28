<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('TEACHER');
$pdo = db(); $u = user();

$st = $pdo->prepare("SELECT a.assignment_id, s.student_id, s.student_code, s.full_name, b.branch_name,
        e.evaluation_id, e.is_submitted, e.total_score, e.submitted_at, e.updated_at
    FROM assignments a
    JOIN students s ON s.student_id = a.student_id
    JOIN branches b ON b.branch_id = s.branch_id
    LEFT JOIN evaluations e ON e.assignment_id = a.assignment_id
    WHERE a.teacher_id = ?
    ORDER BY (e.is_submitted = 1), s.student_code");
$st->execute([$u['id']]);
$rows = $st->fetchAll();

$total = count($rows); $done = 0; $prog = 0;
foreach ($rows as &$r) {
    if ($r['is_submitted']) { $r['state'] = 'done'; $done++; }
    elseif ($r['evaluation_id']) { $r['state'] = 'draft'; $prog++; }
    else $r['state'] = 'new';
}
unset($r);
$pageTitle = 'รายการประเมินของฉัน'; $nav = 'dashboard';
include __DIR__ . '/../../app/layout/header.php';
?>
<h1 class="page-title">สวัสดี <?= e($u['name']) ?></h1>
<p class="page-sub">รายชื่อนักศึกษาที่ท่านได้รับมอบหมายให้ประเมิน Portfolio</p>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="card-soft stat-card"><div class="stat-num"><?= $total ?></div><div class="stat-label">ทั้งหมด</div></div></div>
  <div class="col-6 col-md-3"><div class="card-soft stat-card"><div class="stat-num text-success"><?= $done ?></div><div class="stat-label">ประเมินแล้ว</div></div></div>
  <div class="col-6 col-md-3"><div class="card-soft stat-card"><div class="stat-num" style="color:#D97706"><?= $prog ?></div><div class="stat-label">กำลังดำเนินการ</div></div></div>
  <div class="col-6 col-md-3"><div class="card-soft stat-card"><div class="stat-num text-secondary"><?= $total - $done - $prog ?></div><div class="stat-label">ยังไม่ได้ประเมิน</div></div></div>
</div>

<div class="card-soft p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-6"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
      <input id="q" class="form-control" placeholder="ค้นหาชื่อหรือรหัสนักศึกษา..."></div></div>
    <div class="col-md-6"><div class="btn-group w-100" id="flt">
      <button class="btn btn-outline-primary active" data-f="all">ทั้งหมด</button>
      <button class="btn btn-outline-primary" data-f="new">ยังไม่ได้ประเมิน</button>
      <button class="btn btn-outline-primary" data-f="draft">กำลังดำเนินการ</button>
      <button class="btn btn-outline-primary" data-f="done">ประเมินแล้ว</button></div></div>
  </div>
  <?php if (!$rows): ?>
    <div class="text-center text-muted py-5"><i class="bi bi-inbox fs-1"></i><p class="mt-2">ยังไม่มีนักศึกษาที่ได้รับมอบหมาย</p></div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-hover mb-0" id="tbl">
    <thead><tr><th>รหัส</th><th>ชื่อ-สกุล</th><th>สาขา</th><th>สถานะ</th><th>คะแนนของท่าน</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr data-state="<?= $r['state'] ?>" data-text="<?= e(mb_strtolower($r['student_code'] . ' ' . $r['full_name'])) ?>">
        <td><?= e($r['student_code']) ?></td>
        <td class="fw-medium"><?= e($r['full_name']) ?></td>
        <td><?= e($r['branch_name']) ?></td>
        <td><?php if ($r['state'] === 'done'): ?><span class="badge badge-soft bg-success-subtle text-success-emphasis"><i class="bi bi-check2-circle"></i> ประเมินแล้ว</span>
            <?php elseif ($r['state'] === 'draft'): ?><span class="badge badge-soft bg-warning-subtle text-warning-emphasis"><i class="bi bi-pencil"></i> กำลังดำเนินการ</span>
            <?php else: ?><span class="badge badge-soft bg-secondary-subtle text-secondary-emphasis">ยังไม่ได้ประเมิน</span><?php endif; ?></td>
        <td><?= $r['state'] === 'done' ? e(number_format((float)$r['total_score'], 2)) . ' / 100' : '<span class="text-muted">-</span>' ?></td>
        <td class="text-end"><a class="btn btn-sm <?= $r['state'] === 'done' ? 'btn-outline-primary' : 'btn-primary' ?>" href="<?= e(url('teacher/evaluate.php?id=' . $r['assignment_id'])) ?>">
          <?= $r['state'] === 'done' ? '<i class="bi bi-eye"></i> ดูผล' : ($r['state'] === 'draft' ? '<i class="bi bi-pencil-square"></i> ประเมินต่อ' : '<i class="bi bi-play-fill"></i> เริ่มประเมิน') ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <div id="empty" class="text-center text-muted py-4 d-none">ไม่พบรายการที่ค้นหา</div>
  <?php endif; ?>
</div>
<?php
$extraJs = <<<'JS'
<script>
const q=document.getElementById('q'),rows=[...document.querySelectorAll('#tbl tbody tr')];let f='all';
function apply(){const t=(q.value||'').toLowerCase().trim();let n=0;
  rows.forEach(r=>{const ok=(f==='all'||r.dataset.state===f)&&r.dataset.text.includes(t);r.classList.toggle('d-none',!ok);if(ok)n++;});
  document.getElementById('empty')?.classList.toggle('d-none',n>0);}
q?.addEventListener('input',apply);
document.querySelectorAll('#flt button').forEach(b=>b.onclick=()=>{document.querySelectorAll('#flt button').forEach(x=>x.classList.remove('active'));b.classList.add('active');f=b.dataset.f;apply();});
</script>
JS;
include __DIR__ . '/../../app/layout/footer.php'; ?>
