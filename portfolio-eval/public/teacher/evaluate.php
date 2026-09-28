<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('TEACHER');
$pdo = db(); $u = user();
$aid = (int)($_GET['id'] ?? 0);

// ต้องเป็น assignment ของอาจารย์ที่ login อยู่เท่านั้น
$st = $pdo->prepare("SELECT a.assignment_id, s.student_id, s.student_code, s.full_name, s.file_type, s.file_path, b.branch_name
    FROM assignments a JOIN students s ON s.student_id = a.student_id JOIN branches b ON b.branch_id = s.branch_id
    WHERE a.assignment_id = ? AND a.teacher_id = ?");
$st->execute([$aid, $u['id']]);
$a = $st->fetch();
if (!$a) { http_response_code(404); exit('ไม่พบรายการประเมิน หรือท่านไม่ได้รับมอบหมายรายการนี้'); }

$st = $pdo->prepare("SELECT * FROM evaluations WHERE assignment_id = ?");
$st->execute([$aid]);
$ev = $st->fetch() ?: [];
$readonly = !empty($ev['is_submitted']);

$rubric = [];
foreach ($pdo->query("SELECT criterion_key, score, description FROM rubric") as $r) $rubric[$r['criterion_key']][$r['score']] = $r['description'];

$crit = criteria(); $weights = cfg('weights');
$isLink = $a['file_type'] === 'LINK';
$viewSrc = $isLink ? embed_url($a['file_path']) : url('file.php?sid=' . $a['student_id']);
$hasFile = $a['file_path'] !== '';

$pageTitle = 'ประเมิน ' . $a['full_name']; $nav = 'dashboard';
$mainClass = 'container-fluid py-3 px-lg-4';
include __DIR__ . '/../../app/layout/header.php';
?>
<div class="eval-head d-flex flex-wrap align-items-center gap-2 mb-3">
  <a href="<?= e(url('teacher/dashboard.php')) ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i> กลับ</a>
  <div class="ms-1"><div class="fw-semibold"><?= e($a['full_name']) ?> <span class="text-muted fw-normal">(<?= e($a['student_code']) ?>)</span></div>
    <div class="small text-muted"><?= e($a['branch_name']) ?></div></div>
  <div class="ms-auto save-status" id="saveStatus">
    <?php if ($readonly): ?><span class="text-success"><i class="bi bi-lock-fill"></i> ส่งผลแล้วเมื่อ <?= e(fmt_dt($ev['submitted_at'])) ?> (แก้ไขไม่ได้)</span>
    <?php elseif ($ev): ?>บันทึกร่างล่าสุด <?= e(fmt_dt($ev['updated_at'])) ?><?php else: ?>ยังไม่ได้บันทึก<?php endif; ?></div>
</div>

<div class="pane-tabs">
  <button class="btn btn-primary" data-pane="paneDoc"><i class="bi bi-file-earmark-text"></i> เอกสาร</button>
  <button class="btn btn-outline-primary" data-pane="paneForm"><i class="bi bi-ui-radios"></i> ให้คะแนน</button>
</div>

<div class="row g-3">
  <div class="col-lg-7 pane active" id="paneDoc">
    <div class="viewer">
      <?php if (!$hasFile): ?>
        <div class="viewer-empty"><i class="bi bi-file-earmark-x fs-1"></i><p class="mt-2">ยังไม่มีการแนบ Portfolio ของนักศึกษาคนนี้<br>กรุณาแจ้งผู้ดูแลระบบ</p></div>
      <?php else: ?>
        <iframe src="<?= e($viewSrc) ?>" title="Portfolio" allow="autoplay"></iframe>
      <?php endif; ?>
    </div>
    <?php if ($hasFile): ?>
    <div class="mt-2 text-end"><a class="btn btn-sm btn-outline-primary <?= $isLink ? 'fw-semibold' : '' ?>" target="_blank" rel="noopener"
        href="<?= e($isLink ? $a['file_path'] : $viewSrc) ?>"><i class="bi bi-box-arrow-up-right"></i> เปิดในแท็บใหม่</a>
      <?php if ($isLink): ?><span class="small text-muted d-block d-md-inline ms-md-2">หากเอกสารไม่แสดงด้านบน ให้กดเปิดในแท็บใหม่</span><?php endif; ?></div>
    <?php endif; ?>
  </div>

  <div class="col-lg-5 pane" id="paneForm">
    <form id="evalForm" class="form-pane" autocomplete="off" onsubmit="return false">
      <?php $n = 0; foreach ($crit as $k => $c): $n++; $val = $ev[$c['col'] . '_score'] ?? null; ?>
      <div class="crit-card" id="card_<?= $k ?>" data-key="<?= $k ?>">
        <div class="d-flex align-items-start justify-content-between">
          <div><div class="crit-title"><?= $n ?>. <?= e($c['label']) ?></div><div class="small text-muted"><i class="bi <?= $c['icon'] ?>"></i> <?= e($c['hint']) ?></div></div>
          <span class="weight-badge"><?= (int)$weights[$k] ?>%</span>
        </div>
        <div class="score-pill <?= $readonly ? 'readonly' : '' ?>">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <input type="radio" name="<?= $k ?>" id="<?= $k ?>_<?= $i ?>" value="<?= $i ?>" <?= (int)$val === $i ? 'checked' : '' ?> <?= $readonly ? 'disabled' : '' ?>>
            <label for="<?= $k ?>_<?= $i ?>"><?= $i ?></label>
          <?php endfor; ?>
        </div>
        <div class="rubric-box mb-2" id="rubric_<?= $k ?>"><?= $val ? e($rubric[$k][$val] ?? '') : '' ?></div>
        <textarea name="c_<?= $k ?>" class="form-control form-control-sm" rows="2" maxlength="2000" placeholder="เหตุผลประกอบการให้คะแนน (ไม่บังคับ)" <?= $readonly ? 'disabled' : '' ?>><?= e($ev[$c['col'] . '_comment'] ?? '') ?></textarea>
      </div>
      <?php endforeach; ?>
      <div class="crit-card">
        <div class="crit-title mb-2"><i class="bi bi-chat-left-text"></i> ข้อเสนอแนะภาพรวม</div>
        <textarea name="c_overall" class="form-control" rows="3" maxlength="3000" placeholder="ข้อเสนอแนะเพิ่มเติม (ไม่บังคับ)" <?= $readonly ? 'disabled' : '' ?>><?= e($ev['overall_comment'] ?? '') ?></textarea>
      </div>
    </form>
  </div>
</div>

<div class="live-bar">
  <div class="row align-items-center g-3">
    <div class="col-md-4"><div class="small text-muted mb-1" id="progressText">ตอบแล้ว 0/4</div>
      <div class="progress"><div class="progress-bar" id="progressBar" style="width:0%"></div></div></div>
    <div class="col-6 col-md-3"><div class="small text-muted">คะแนนของท่าน</div>
      <div><span class="live-score" id="liveScore">0.00</span> <span class="text-muted">/ 100</span></div></div>
    <div class="col-6 col-md-5 text-end">
      <?php if (!$readonly): ?>
        <button class="btn btn-outline-secondary" id="btnDraft"><i class="bi bi-save"></i> บันทึกร่าง</button>
        <button class="btn btn-primary" id="btnSubmit" disabled><i class="bi bi-send-check"></i> ส่งผลประเมิน</button>
      <?php else: ?>
        <a class="btn btn-primary" href="<?= e(url('teacher/dashboard.php')) ?>">กลับรายการ</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal ยืนยันส่งผล -->
<div class="modal fade" id="confirmModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px">
  <div class="modal-header border-0 pb-0"><h5 class="modal-title"><i class="bi bi-send-check text-primary"></i> ยืนยันการส่งผลประเมิน</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div id="summary"></div>
    <div class="alert alert-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> เมื่อส่งผลแล้ว <b>จะแก้ไขไม่ได้</b> (หากส่งผิดพลาดให้แจ้งผู้ดูแลระบบเพื่อปลดล็อก)</div></div>
  <div class="modal-footer border-0"><button class="btn btn-light" data-bs-dismiss="modal">ตรวจสอบอีกครั้ง</button><button class="btn btn-primary" id="btnConfirm">ยืนยันส่งผล</button></div>
</div></div></div>

<?php
$extraJs = '<script>
const CSRF=' . json_encode(csrf_token()) . ',ASSIGNMENT_ID=' . $aid . ',READONLY=' . ($readonly ? 'true' : 'false') . ',
WEIGHTS=' . json_encode($weights) . ',RUBRIC=' . json_encode($rubric, JSON_UNESCAPED_UNICODE) . ',
LABELS=' . json_encode(array_map(fn($c) => $c['label'], $crit), JSON_UNESCAPED_UNICODE) . ',SAVE_URL=' . json_encode(url('teacher/save.php')) . ',
DASH_URL=' . json_encode(url('teacher/dashboard.php?done=1')) . ';
</script><script src="' . e(url('assets/js/evaluate.js')) . '"></script>';
include __DIR__ . '/../../app/layout/footer.php'; ?>
