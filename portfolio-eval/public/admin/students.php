<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('ADMIN');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    $newFile = null;
    try {
        if ($act === 'add' || $act === 'edit') {
            $code = trim($_POST['student_code'] ?? ''); $name = trim($_POST['full_name'] ?? '');
            $branch = (int)($_POST['branch_id'] ?? 0);
            $type = ($_POST['file_type'] ?? 'PDF') === 'LINK' ? 'LINK' : 'PDF';
            $link = trim($_POST['link'] ?? '');
            if ($code === '' || $name === '' || !$branch) throw new RuntimeException('กรุณากรอกรหัส ชื่อ และเลือกสาขา');
            $hasUpload = isset($_FILES['pdf']) && $_FILES['pdf']['error'] !== UPLOAD_ERR_NO_FILE;

            $old = ['file_type' => 'PDF', 'file_path' => ''];
            if ($act === 'edit') {
                $q = $pdo->prepare("SELECT file_type, file_path, branch_id FROM students WHERE student_id=?");
                $q->execute([(int)$_POST['student_id']]); $old = $q->fetch() ?: $old;
            }
            if ($type === 'LINK') {
                if (!valid_link($link)) throw new RuntimeException('กรุณาใส่ลิงก์ที่ขึ้นต้นด้วย http:// หรือ https://');
                $path = $link;
            } elseif ($hasUpload) {
                $newFile = store_pdf($_FILES['pdf']); $path = $newFile;
            } else {
                $path = $old['file_type'] === 'PDF' ? $old['file_path'] : '';   // ไม่อัปโหลดใหม่ = คงไฟล์เดิม
            }
            if ($act === 'add') {
                $pdo->prepare("INSERT INTO students (student_code, full_name, branch_id, file_type, file_path) VALUES (?,?,?,?,?)")
                    ->execute([$code, $name, $branch, $type, $path]);
                flash('success', "เพิ่มนักศึกษา $code เรียบร้อย");
            } else {
                $id = (int)$_POST['student_id'];
                if ($old['branch_id'] != $branch) {   // ย้ายสาขา: ล้างการมอบหมายเดิมที่ยังไม่มีผลส่ง
                    $sub = $pdo->prepare("SELECT COUNT(*) FROM assignments a JOIN evaluations e ON e.assignment_id=a.assignment_id WHERE a.student_id=? AND e.is_submitted=1");
                    $sub->execute([$id]);
                    if ($sub->fetchColumn()) throw new RuntimeException('นักศึกษานี้มีผลประเมินที่ส่งแล้ว ไม่สามารถย้ายสาขาได้');
                    $pdo->prepare("DELETE FROM assignments WHERE student_id=?")->execute([$id]);
                }
                $pdo->prepare("UPDATE students SET student_code=?, full_name=?, branch_id=?, file_type=?, file_path=? WHERE student_id=?")
                    ->execute([$code, $name, $branch, $type, $path, $id]);
                if ($old['file_type'] === 'PDF' && $old['file_path'] !== $path) delete_pdf($old['file_path']);
                flash('success', 'บันทึกข้อมูลนักศึกษาเรียบร้อย');
            }
        } elseif ($act === 'delete') {
            $id = (int)$_POST['student_id'];
            $q = $pdo->prepare("SELECT file_type, file_path FROM students WHERE student_id=?"); $q->execute([$id]); $old = $q->fetch();
            $pdo->prepare("DELETE FROM students WHERE student_id=?")->execute([$id]);
            if ($old && $old['file_type'] === 'PDF') delete_pdf($old['file_path']);
            audit('DELETE_STUDENT', "student_id=$id");
            flash('success', 'ลบนักศึกษาและผลประเมินที่เกี่ยวข้องเรียบร้อย');
        } elseif ($act === 'import') {
            $f = $_FILES['csv'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('กรุณาเลือกไฟล์ CSV');
            $bmap = []; foreach ($pdo->query("SELECT branch_id, branch_code, branch_name FROM branches") as $b) { $bmap[strtolower($b['branch_code'])] = $b['branch_id']; $bmap[$b['branch_name']] = $b['branch_id']; }
            $h = fopen($f['tmp_name'], 'r'); $ok = 0; $skip = []; $ln = 0;
            $ins = $pdo->prepare("INSERT INTO students (student_code, full_name, branch_id, file_type, file_path) VALUES (?,?,?,?,?)");
            while (($row = fgetcsv($h)) !== false) {
                $ln++; if ($ln === 1) $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
                if ($ln === 1 && strtolower(trim($row[0])) === 'student_code') continue;
                if (count($row) < 3 || trim($row[0]) === '') continue;
                [$code, $name, $bc] = [trim($row[0]), trim($row[1]), trim($row[2])]; $lk = trim($row[3] ?? '');
                $bid = $bmap[strtolower($bc)] ?? $bmap[$bc] ?? null;
                if (!$bid || $name === '') { $skip[] = "แถว $ln (สาขา/ชื่อไม่ถูกต้อง)"; continue; }
                $type = valid_link($lk) ? 'LINK' : 'PDF';
                try { $ins->execute([$code, $name, $bid, $type, $type === 'LINK' ? $lk : '']); $ok++; }
                catch (PDOException $e) { $skip[] = "แถว $ln ($code ซ้ำ)"; }
            }
            fclose($h);
            flash($ok ? 'success' : 'warning', "นำเข้าสำเร็จ $ok รายการ" . ($skip ? ' | ข้าม: ' . implode(', ', array_slice($skip, 0, 8)) : ''));
        }
    } catch (PDOException $e) {
        if ($newFile) delete_pdf($newFile);
        flash('danger', $e->getCode() === '23000' ? 'รหัสนักศึกษานี้มีอยู่ในระบบแล้ว' : 'เกิดข้อผิดพลาดของฐานข้อมูล');
    } catch (RuntimeException $e) { flash('danger', $e->getMessage()); }
    redirect('admin/students.php' . (isset($_GET['branch']) ? '?branch=' . (int)$_GET['branch'] : ''));
}

$branches = $pdo->query("SELECT * FROM branches ORDER BY branch_id")->fetchAll();
$bf = (int)($_GET['branch'] ?? 0);
$sql = "SELECT s.*, b.branch_name,
    (SELECT COUNT(*) FROM assignments a WHERE a.student_id = s.student_id) n_assign
    FROM students s JOIN branches b ON b.branch_id = s.branch_id" . ($bf ? " WHERE s.branch_id = " . $bf : '') . " ORDER BY s.branch_id, s.student_code";
$students = $pdo->query($sql)->fetchAll();
$pageTitle = 'นักศึกษา & Portfolio'; $nav = 'students';
include __DIR__ . '/../../app/layout/header.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div><h1 class="page-title">นักศึกษา &amp; Portfolio</h1><p class="page-sub mb-0">เพิ่มนักศึกษา แนบไฟล์ PDF หรือลิงก์ Portfolio แยกตามสาขา</p></div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-filetype-csv"></i> นำเข้า CSV</button>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#stuModal" onclick="openStu()"><i class="bi bi-plus-lg"></i> เพิ่มนักศึกษา</button>
  </div>
</div>
<div class="card-soft p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-5"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input id="q" class="form-control" placeholder="ค้นหาชื่อหรือรหัส..."></div></div>
    <div class="col-md-7"><div class="btn-group">
      <a class="btn btn-outline-primary <?= !$bf ? 'active' : '' ?>" href="?">ทุกสาขา</a>
      <?php foreach ($branches as $b): ?><a class="btn btn-outline-primary <?= $bf == $b['branch_id'] ? 'active' : '' ?>" href="?branch=<?= (int)$b['branch_id'] ?>"><?= e($b['branch_name']) ?></a><?php endforeach; ?></div></div>
  </div>
  <div class="table-responsive"><table class="table table-hover mb-0" id="tbl">
    <thead><tr><th>รหัส</th><th>ชื่อ-สกุล</th><th>สาขา</th><th>Portfolio</th><th>ผู้ประเมิน</th><th></th></tr></thead><tbody>
    <?php foreach ($students as $s): ?>
      <tr data-text="<?= e(mb_strtolower($s['student_code'] . ' ' . $s['full_name'])) ?>">
        <td><?= e($s['student_code']) ?></td><td class="fw-medium"><?= e($s['full_name']) ?></td><td><?= e($s['branch_name']) ?></td>
        <td><?php if ($s['file_path'] === ''): ?><span class="badge badge-soft bg-warning-subtle text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> ยังไม่แนบไฟล์</span>
          <?php elseif ($s['file_type'] === 'LINK'): ?><a target="_blank" rel="noopener" href="<?= e($s['file_path']) ?>"><i class="bi bi-link-45deg"></i> ลิงก์ภายนอก</a>
          <?php else: ?><a target="_blank" href="<?= e(url('file.php?sid=' . $s['student_id'])) ?>"><i class="bi bi-file-earmark-pdf text-danger"></i> เปิด PDF</a><?php endif; ?></td>
        <td><?= (int)$s['n_assign'] >= 2 ? '<span class="text-success"><i class="bi bi-check-circle"></i> 2/2</span>' : '<span class="text-warning"><i class="bi bi-dash-circle"></i> ' . (int)$s['n_assign'] . '/2</span>' ?></td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#stuModal"
            onclick='openStu(<?= json_encode(["id" => $s["student_id"], "code" => $s["student_code"], "name" => $s["full_name"], "branch" => $s["branch_id"], "type" => $s["file_type"], "link" => $s["file_type"] === "LINK" ? $s["file_path"] : "", "has" => $s["file_type"] === "PDF" && $s["file_path"] !== ""], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline" onsubmit="return confirm('ลบนักศึกษา <?= e($s['full_name']) ?> พร้อมผลประเมินทั้งหมด? การลบกู้คืนไม่ได้')"><?= csrf_field() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="student_id" value="<?= (int)$s['student_id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td>
      </tr>
    <?php endforeach; ?></tbody></table></div>
  <?php if (!$students): ?><div class="text-center text-muted py-5"><i class="bi bi-inbox fs-1"></i><p class="mt-2 mb-0">ยังไม่มีนักศึกษา กด "เพิ่มนักศึกษา" หรือ "นำเข้า CSV"</p></div><?php endif; ?>
</div>

<div class="modal fade" id="stuModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" enctype="multipart/form-data" class="modal-content" style="border-radius:16px">
  <?= csrf_field() ?><input type="hidden" name="action" id="s_action" value="add"><input type="hidden" name="student_id" id="s_id">
  <div class="modal-header border-0"><h5 class="modal-title" id="s_title">เพิ่มนักศึกษา</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-3 mb-3"><div class="col-5"><label class="form-label">รหัสนักศึกษา/เลขที่สมัคร</label><input name="student_code" id="s_code" class="form-control" required></div>
      <div class="col-7"><label class="form-label">ชื่อ-สกุล</label><input name="full_name" id="s_name" class="form-control" required></div></div>
    <div class="mb-3"><label class="form-label">สาขาที่สมัคร</label><select name="branch_id" id="s_branch" class="form-select"><?php foreach ($branches as $b): ?><option value="<?= (int)$b['branch_id'] ?>"><?= e($b['branch_name']) ?></option><?php endforeach; ?></select></div>
    <div class="mb-2"><label class="form-label d-block">ประเภท Portfolio</label>
      <div class="btn-group w-100"><input type="radio" class="btn-check" name="file_type" id="ft_pdf" value="PDF" checked><label class="btn btn-outline-primary" for="ft_pdf"><i class="bi bi-file-earmark-pdf"></i> ไฟล์ PDF</label>
        <input type="radio" class="btn-check" name="file_type" id="ft_link" value="LINK"><label class="btn btn-outline-primary" for="ft_link"><i class="bi bi-link-45deg"></i> ลิงก์ภายนอก</label></div></div>
    <div id="w_pdf"><input type="file" name="pdf" accept="application/pdf" class="form-control"><div class="form-text" id="s_pdfhint">ไม่เกิน <?= (int)cfg('max_upload_mb') ?> MB (แนบภายหลังได้)</div></div>
    <div id="w_link" class="d-none"><input type="url" name="link" id="s_link" class="form-control" placeholder="https://drive.google.com/file/d/..."><div class="form-text">ลิงก์ Google Drive ต้องตั้งสิทธิ์ให้ผู้มีลิงก์เปิดดูได้</div></div>
  </div>
  <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary">บันทึก</button></div>
</form></div></div>

<div class="modal fade" id="importModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" enctype="multipart/form-data" class="modal-content" style="border-radius:16px">
  <?= csrf_field() ?><input type="hidden" name="action" value="import">
  <div class="modal-header border-0"><h5 class="modal-title">นำเข้านักศึกษาจาก CSV</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><p class="small">ไฟล์ CSV (UTF-8) คอลัมน์: <code>student_code, full_name, branch_code, link</code><br>
    <code>branch_code</code> = <?= e(implode(' หรือ ', array_map(fn($b) => $b['branch_code'], $branches))) ?> (หรือชื่อสาขาเต็ม) &nbsp;|&nbsp; <code>link</code> ไม่ใส่ก็ได้ (แนบ PDF ภายหลัง)</p>
    <input type="file" name="csv" accept=".csv,text/csv" class="form-control" required></div>
  <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary">นำเข้า</button></div>
</form></div></div>
<?php
$extraJs = <<<'JS'
<script>
const q=document.getElementById('q');q.addEventListener('input',()=>{const t=q.value.toLowerCase().trim();document.querySelectorAll('#tbl tbody tr').forEach(r=>r.classList.toggle('d-none',!r.dataset.text.includes(t)));});
function syncType(){const l=ft_link.checked;w_pdf.classList.toggle('d-none',l);w_link.classList.toggle('d-none',!l);}
ft_pdf.onchange=ft_link.onchange=syncType;
function openStu(d){const add=!d;s_action.value=add?'add':'edit';s_title.textContent=add?'เพิ่มนักศึกษา':'แก้ไขนักศึกษา';
  s_id.value=d?d.id:'';s_code.value=d?d.code:'';s_name.value=d?d.name:'';if(d)s_branch.value=d.branch;
  (d&&d.type==='LINK'?ft_link:ft_pdf).checked=true;s_link.value=d?d.link:'';
  s_pdfhint.textContent=(d&&d.has)?'มีไฟล์แนบอยู่แล้ว — เลือกไฟล์ใหม่เฉพาะเมื่อต้องการแทนที่':'ไม่เกิน <?= (int)cfg('max_upload_mb') ?> MB (แนบภายหลังได้)';syncType();}
</script>
JS;
include __DIR__ . '/../../app/layout/footer.php'; ?>
