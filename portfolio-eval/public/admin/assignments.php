<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('ADMIN');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $sid = (int)($_POST['student_id'] ?? 0);
        $q = $pdo->prepare("SELECT branch_id FROM students WHERE student_id=?"); $q->execute([$sid]);
        $branch = $q->fetchColumn();
        if (!$branch) throw new RuntimeException('ไม่พบนักศึกษา');

        $want = [1 => (int)($_POST['t1'] ?? 0), 2 => (int)($_POST['t2'] ?? 0)];
        if ($want[1] && $want[1] === $want[2]) throw new RuntimeException('ต้องเลือกอาจารย์คนละท่านสำหรับ 2 ตำแหน่ง');

        $cur = [];
        $q = $pdo->prepare("SELECT a.assignment_id, a.slot, a.teacher_id, COALESCE(e.is_submitted,0) sub
            FROM assignments a LEFT JOIN evaluations e ON e.assignment_id=a.assignment_id WHERE a.student_id=?");
        $q->execute([$sid]);
        foreach ($q->fetchAll() as $r) $cur[$r['slot']] = $r;

        $pdo->beginTransaction();
        // ลบก่อนเพื่อไม่ให้ชนกับ UNIQUE (กรณีสลับอาจารย์ระหว่างช่อง)
        foreach ([1, 2] as $slot) {
            $c = $cur[$slot] ?? null;
            if ($c && !$c['sub'] && (int)$c['teacher_id'] !== $want[$slot]) {
                $pdo->prepare("DELETE FROM assignments WHERE assignment_id=?")->execute([$c['assignment_id']]);
                unset($cur[$slot]);
            }
        }
        foreach ([1, 2] as $slot) {
            if (isset($cur[$slot]) || !$want[$slot]) continue;
            $t = $pdo->prepare("SELECT 1 FROM users WHERE user_id=? AND role='TEACHER' AND is_active=1 AND branch_id=?");
            $t->execute([$want[$slot], $branch]);
            if (!$t->fetchColumn()) throw new RuntimeException('อาจารย์ที่เลือกต้องอยู่สาขาเดียวกับนักศึกษา');
            $pdo->prepare("INSERT INTO assignments (student_id, teacher_id, slot) VALUES (?,?,?)")->execute([$sid, $want[$slot], $slot]);
        }
        $pdo->commit();
        recalc_student($pdo, $sid);
        flash('success', 'บันทึกการมอบหมายเรียบร้อย');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('danger', 'บันทึกไม่สำเร็จ: อาจารย์ท่านนี้ถูกมอบหมายให้นักศึกษาคนนี้แล้ว');
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('danger', $e->getMessage());
    }
    redirect('admin/assignments.php' . (!empty($_GET['branch']) ? '?branch=' . (int)$_GET['branch'] : ''));
}

$branches = $pdo->query("SELECT * FROM branches ORDER BY branch_id")->fetchAll();
$bf = (int)($_GET['branch'] ?? ($branches[0]['branch_id'] ?? 0));

$teachers = [];
$q = $pdo->prepare("SELECT user_id, full_name, staff_id FROM users WHERE role='TEACHER' AND is_active=1 AND branch_id=? ORDER BY full_name");
$q->execute([$bf]); $teachers = $q->fetchAll();

$load = [];  // จำนวนนักศึกษาต่ออาจารย์
foreach ($pdo->query("SELECT teacher_id, COUNT(*) n FROM assignments GROUP BY teacher_id") as $r) $load[$r['teacher_id']] = $r['n'];

$q = $pdo->prepare("SELECT student_id, student_code, full_name, file_path FROM students WHERE branch_id=? ORDER BY student_code");
$q->execute([$bf]); $students = $q->fetchAll();

$asg = [];
$q = $pdo->prepare("SELECT a.student_id, a.slot, a.teacher_id, COALESCE(e.is_submitted,0) sub
    FROM assignments a JOIN students s ON s.student_id=a.student_id LEFT JOIN evaluations e ON e.assignment_id=a.assignment_id WHERE s.branch_id=?");
$q->execute([$bf]);
foreach ($q->fetchAll() as $r) $asg[$r['student_id']][$r['slot']] = $r;

$pageTitle = 'มอบหมายผู้ประเมิน'; $nav = 'assignments';
include __DIR__ . '/../../app/layout/header.php';
?>
<h1 class="page-title">มอบหมายผู้ประเมิน</h1>
<p class="page-sub">เลือกอาจารย์ 2 ท่านต่อนักศึกษา 1 คน (เลือกได้เฉพาะอาจารย์ในสาขาเดียวกัน)</p>
<div class="btn-group mb-3"><?php foreach ($branches as $b): ?><a class="btn btn-outline-primary <?= $bf == $b['branch_id'] ? 'active' : '' ?>" href="?branch=<?= (int)$b['branch_id'] ?>"><?= e($b['branch_name']) ?></a><?php endforeach; ?></div>

<?php if (count($teachers) < 2): ?>
  <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> สาขานี้มีอาจารย์ผู้ประเมินไม่ถึง 2 ท่าน กรุณาเพิ่มบัญชีที่เมนู <a href="<?= e(url('admin/users.php')) ?>">ผู้ใช้งาน</a> ก่อน</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-9"><div class="card-soft p-3"><div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr><th>นักศึกษา</th><th style="min-width:200px">อาจารย์ท่านที่ 1</th><th style="min-width:200px">อาจารย์ท่านที่ 2</th><th></th></tr></thead><tbody>
    <?php foreach ($students as $s): $sid = (int)$s['student_id']; $a1 = $asg[$sid][1] ?? null; $a2 = $asg[$sid][2] ?? null; ?>
      <tr>
        <td><form id="f<?= $sid ?>" method="post"><?= csrf_field() ?><input type="hidden" name="student_id" value="<?= $sid ?>"></form>
          <div class="fw-medium"><?= e($s['full_name']) ?></div><div class="small text-muted"><?= e($s['student_code']) ?></div></td>
        <?php foreach ([1 => $a1, 2 => $a2] as $slot => $a): ?>
        <td><select form="f<?= $sid ?>" name="t<?= $slot ?>" class="form-select form-select-sm" <?= $a && $a['sub'] ? 'disabled' : '' ?>>
            <option value="0">— ยังไม่เลือก —</option>
            <?php foreach ($teachers as $t): ?><option value="<?= (int)$t['user_id'] ?>" <?= $a && $a['teacher_id'] == $t['user_id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?></option><?php endforeach; ?>
          </select><?php if ($a && $a['sub']): ?><div class="small text-success mt-1"><i class="bi bi-lock-fill"></i> ส่งผลแล้ว (เปลี่ยนไม่ได้)</div><?php endif; ?></td>
        <?php endforeach; ?>
        <td class="text-end"><button form="f<?= $sid ?>" class="btn btn-sm btn-primary"><i class="bi bi-save"></i> บันทึก</button></td>
      </tr>
    <?php endforeach; ?></tbody></table></div>
    <?php if (!$students): ?><div class="text-center text-muted py-5">ยังไม่มีนักศึกษาในสาขานี้</div><?php endif; ?></div></div>
  <div class="col-lg-3"><div class="card-soft p-3"><h6 class="fw-bold"><i class="bi bi-bar-chart"></i> ภาระงานของอาจารย์</h6>
    <?php foreach ($teachers as $t): ?><div class="d-flex justify-content-between py-1 border-bottom small"><span><?= e($t['full_name']) ?></span><span class="fw-semibold"><?= (int)($load[$t['user_id']] ?? 0) ?> คน</span></div><?php endforeach; ?>
    <?php if (!$teachers): ?><div class="text-muted small">ยังไม่มีอาจารย์ในสาขานี้</div><?php endif; ?></div></div>
</div>
<?php include __DIR__ . '/../../app/layout/footer.php'; ?>
