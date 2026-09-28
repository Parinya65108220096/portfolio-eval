<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('ADMIN');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unlock') {
    csrf_verify();
    $eid = (int)($_POST['evaluation_id'] ?? 0);
    $q = $pdo->prepare("SELECT a.student_id, a.teacher_id FROM evaluations e JOIN assignments a ON a.assignment_id=e.assignment_id WHERE e.evaluation_id=? AND e.is_submitted=1");
    $q->execute([$eid]);
    if ($r = $q->fetch()) {
        $pdo->prepare("UPDATE evaluations SET is_submitted=0, submitted_at=NULL WHERE evaluation_id=?")->execute([$eid]);
        recalc_student($pdo, (int)$r['student_id']);
        audit('UNLOCK_EVALUATION', "evaluation_id=$eid student_id={$r['student_id']} teacher_id={$r['teacher_id']}");
        flash('success', 'ปลดล็อกผลประเมินแล้ว อาจารย์สามารถแก้ไขและส่งใหม่ได้ (บันทึกประวัติการปลดล็อกแล้ว)');
    }
    redirect('admin/results.php' . (!empty($_GET['branch']) ? '?branch=' . (int)$_GET['branch'] : ''));
}

$branches = $pdo->query("SELECT * FROM branches ORDER BY branch_id")->fetchAll();
$bf = (int)($_GET['branch'] ?? 0);
$sf = $_GET['status'] ?? '';

$sql = "SELECT s.*, b.branch_name FROM students s JOIN branches b ON b.branch_id=s.branch_id WHERE 1=1";
$par = [];
if ($bf) { $sql .= " AND s.branch_id=?"; $par[] = $bf; }
if (in_array($sf, ['PASS', 'FAIL', 'PENDING'], true)) { $sql .= " AND s.final_status=?"; $par[] = $sf; }
$sql .= " ORDER BY s.branch_id, s.student_code";
$q = $pdo->prepare($sql); $q->execute($par); $students = $q->fetchAll();

$ev = [];
$q = $pdo->query("SELECT a.student_id, a.slot, u.full_name teacher, e.*
    FROM assignments a JOIN users u ON u.user_id=a.teacher_id LEFT JOIN evaluations e ON e.assignment_id=a.assignment_id");
foreach ($q->fetchAll() as $r) $ev[$r['student_id']][$r['slot']] = $r;

$crit = criteria();
$pageTitle = 'ผลการประเมิน'; $nav = 'results';
include __DIR__ . '/../../app/layout/header.php';

function cell_eval(?array $r): string {
    if (!$r) return '<span class="text-muted">ยังไม่ได้มอบหมาย</span>';
    $name = '<div class="small text-muted">' . e($r['teacher']) . '</div>';
    if (!empty($r['is_submitted'])) return '<div class="fw-semibold">' . e(number_format((float)$r['total_score'], 2)) . '</div>' . $name;
    return '<span class="badge badge-soft bg-secondary-subtle text-secondary-emphasis">' . (!empty($r['evaluation_id']) ? 'กำลังประเมิน' : 'ยังไม่ประเมิน') . '</span>' . $name;
}
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div><h1 class="page-title">ผลการประเมิน</h1><p class="page-sub mb-0">เกณฑ์ผ่าน: คะแนนเฉลี่ยตั้งแต่ <?= (int)cfg('pass_threshold') ?> ขึ้นไป</p></div>
  <a class="btn btn-primary" href="<?= e(url('admin/export.php')) ?>"><i class="bi bi-file-earmark-excel"></i> ส่งออก Excel</a>
</div>
<div class="card-soft p-3">
  <form class="row g-2 mb-3" method="get">
    <div class="col-md-4"><select name="branch" class="form-select" onchange="this.form.submit()"><option value="0">ทุกสาขา</option>
      <?php foreach ($branches as $b): ?><option value="<?= (int)$b['branch_id'] ?>" <?= $bf == $b['branch_id'] ? 'selected' : '' ?>><?= e($b['branch_name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><select name="status" class="form-select" onchange="this.form.submit()"><option value="">ทุกสถานะ</option>
      <option value="PASS" <?= $sf === 'PASS' ? 'selected' : '' ?>>ผ่าน</option><option value="FAIL" <?= $sf === 'FAIL' ? 'selected' : '' ?>>ไม่ผ่าน</option><option value="PENDING" <?= $sf === 'PENDING' ? 'selected' : '' ?>>รอผล</option></select></div>
    <div class="col-md-5"><input id="q" class="form-control" placeholder="ค้นหาชื่อหรือรหัส..."></div>
  </form>
  <div class="table-responsive"><table class="table mb-0" id="tbl">
    <thead><tr><th>นักศึกษา</th><th>สาขา</th><th>คะแนนอาจารย์ท่านที่ 1</th><th>คะแนนอาจารย์ท่านที่ 2</th><th>คะแนนเฉลี่ย</th><th>สถานะ</th><th></th></tr></thead><tbody>
    <?php foreach ($students as $s): $sid = (int)$s['student_id']; $e1 = $ev[$sid][1] ?? null; $e2 = $ev[$sid][2] ?? null;
        $both = $e1 && $e2 && !empty($e1['is_submitted']) && !empty($e2['is_submitted']);
        $diff = $both ? abs($e1['total_score'] - $e2['total_score']) : 0; $flag = $both && $diff >= cfg('diff_flag'); ?>
      <tr data-text="<?= e(mb_strtolower($s['student_code'] . ' ' . $s['full_name'])) ?>">
        <td><div class="fw-medium"><?= e($s['full_name']) ?></div><div class="small text-muted"><?= e($s['student_code']) ?></div></td>
        <td class="small"><?= e($s['branch_name']) ?></td>
        <td><?= cell_eval($e1) ?></td><td><?= cell_eval($e2) ?></td>
        <td class="fw-bold"><?= $s['final_score'] !== null ? e(number_format((float)$s['final_score'], 2)) : '-' ?>
          <?php if ($flag): ?><i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="คะแนนอาจารย์ต่างกัน <?= e(number_format($diff, 2)) ?> คะแนน ควรตรวจสอบ"></i><?php endif; ?></td>
        <td><?= status_badge($s['final_status']) ?></td>
        <td class="text-end"><button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('d<?= $sid ?>').classList.toggle('d-none')"><i class="bi bi-chevron-expand"></i> รายละเอียด</button></td>
      </tr>
      <tr id="d<?= $sid ?>" class="d-none detail-row"><td colspan="7" class="bg-light">
        <div class="row g-3">
        <?php foreach ([1 => $e1, 2 => $e2] as $slot => $r): ?>
          <div class="col-lg-6"><div class="bg-white rounded-3 p-3 h-100 border">
            <?php if (!$r): ?><div class="text-muted">อาจารย์ท่านที่ <?= $slot ?>: ยังไม่ได้มอบหมาย</div>
            <?php elseif (empty($r['is_submitted'])): ?><div class="text-muted">อาจารย์ท่านที่ <?= $slot ?> (<?= e($r['teacher']) ?>): ยังไม่ส่งผลประเมิน</div>
            <?php else: ?>
              <div class="d-flex justify-content-between mb-2"><div class="fw-semibold">อาจารย์ท่านที่ <?= $slot ?>: <?= e($r['teacher']) ?></div>
                <form method="post" onsubmit="return confirm('ปลดล็อกผลประเมินของอาจารย์ท่านนี้? ผลสรุปของนักศึกษาจะกลับเป็น \"รอผล\" จนกว่าจะส่งใหม่')"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="unlock"><input type="hidden" name="evaluation_id" value="<?= (int)$r['evaluation_id'] ?>">
                  <button class="btn btn-sm btn-outline-warning"><i class="bi bi-unlock"></i> ปลดล็อก</button></form></div>
              <div class="small text-muted mb-2">ส่งเมื่อ <?= e(fmt_dt($r['submitted_at'])) ?> · รวม <?= e(number_format((float)$r['total_score'], 2)) ?>/100</div>
              <?php foreach ($crit as $k => $c): ?>
                <div class="mb-2"><div class="d-flex justify-content-between"><span><?= e($c['label']) ?></span><span class="fw-semibold"><?= (int)$r[$c['col'] . '_score'] ?>/5</span></div>
                  <?php if (trim((string)$r[$c['col'] . '_comment']) !== ''): ?><div class="small text-muted fst-italic"><?= nl2br(e($r[$c['col'] . '_comment'])) ?></div><?php endif; ?></div>
              <?php endforeach; ?>
              <?php if (trim((string)$r['overall_comment']) !== ''): ?><hr class="my-2"><div class="small"><b>ภาพรวม:</b> <?= nl2br(e($r['overall_comment'])) ?></div><?php endif; ?>
            <?php endif; ?>
          </div></div>
        <?php endforeach; ?>
        </div></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php if (!$students): ?><div class="text-center text-muted py-5">ไม่พบรายการ</div><?php endif; ?>
</div>
<?php
$extraJs = <<<'JS'
<script>
const q=document.getElementById('q');q.addEventListener('input',()=>{const t=q.value.toLowerCase().trim();
  document.querySelectorAll('#tbl tbody tr[data-text]').forEach(r=>{const hide=!r.dataset.text.includes(t);r.classList.toggle('d-none',hide);if(hide)r.nextElementSibling.classList.add('d-none');});});
</script>
JS;
include __DIR__ . '/../../app/layout/footer.php'; ?>
