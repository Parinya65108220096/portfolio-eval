<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('ADMIN');
$pdo = db(); $me = user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['action'] ?? '';
    try {
        if ($act === 'add' || $act === 'edit') {
            $sid = trim($_POST['staff_id'] ?? ''); $name = trim($_POST['full_name'] ?? '');
            $role = ($_POST['role'] ?? '') === 'ADMIN' ? 'ADMIN' : 'TEACHER';
            $branch = (int)($_POST['branch_id'] ?? 0) ?: null;
            if ($name === '') throw new RuntimeException('กรุณากรอกชื่อ-สกุล');
            if ($role === 'TEACHER' && !$branch) throw new RuntimeException('อาจารย์ผู้ประเมินต้องระบุสาขา');
            if ($role === 'ADMIN') $branch = null;
            if ($act === 'add') {
                $pw = $_POST['password'] ?? '';
                if ($sid === '' || strlen($pw) < 6) throw new RuntimeException('กรุณากรอกรหัสบุคลากร และรหัสผ่านเริ่มต้นอย่างน้อย 6 ตัวอักษร');
                $pdo->prepare("INSERT INTO users (staff_id, password_hash, full_name, role, branch_id, must_change_password) VALUES (?,?,?,?,?,1)")
                    ->execute([$sid, password_hash($pw, PASSWORD_DEFAULT), $name, $role, $branch]);
                flash('success', "เพิ่มบัญชี $sid เรียบร้อย (ผู้ใช้จะถูกบังคับเปลี่ยนรหัสผ่านเมื่อเข้าครั้งแรก)");
            } else {
                $id = (int)$_POST['user_id'];
                if ($id === $me['id']) $role = 'ADMIN';  // กันแก้บทบาทตัวเอง
                $pdo->prepare("UPDATE users SET full_name=?, role=?, branch_id=? WHERE user_id=?")->execute([$name, $role, $branch, $id]);
                flash('success', 'บันทึกข้อมูลเรียบร้อย');
            }
        } elseif ($act === 'reset') {
            $pw = $_POST['password'] ?? ''; $id = (int)$_POST['user_id'];
            if (strlen($pw) < 6) throw new RuntimeException('รหัสผ่านใหม่ต้องยาวอย่างน้อย 6 ตัวอักษร');
            $pdo->prepare("UPDATE users SET password_hash=?, must_change_password=1 WHERE user_id=?")->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
            audit('RESET_PASSWORD', "user_id=$id");
            flash('success', 'รีเซ็ตรหัสผ่านเรียบร้อย (ผู้ใช้ต้องเปลี่ยนรหัสผ่านเมื่อเข้าครั้งถัดไป)');
        } elseif ($act === 'toggle') {
            $id = (int)$_POST['user_id'];
            if ($id === $me['id']) throw new RuntimeException('ไม่สามารถปิดบัญชีของตัวเองได้');
            $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE user_id=?")->execute([$id]);
            flash('success', 'เปลี่ยนสถานะบัญชีเรียบร้อย');
        }
    } catch (PDOException $e) {
        flash('danger', $e->getCode() === '23000' ? 'รหัสบุคลากรนี้มีอยู่ในระบบแล้ว' : 'เกิดข้อผิดพลาดของฐานข้อมูล');
    } catch (RuntimeException $e) { flash('danger', $e->getMessage()); }
    redirect('admin/users.php');
}

$users = $pdo->query("SELECT u.*, b.branch_name,
    (SELECT COUNT(*) FROM assignments a WHERE a.teacher_id = u.user_id) n_assign
    FROM users u LEFT JOIN branches b ON b.branch_id = u.branch_id ORDER BY u.role, u.staff_id")->fetchAll();
$branches = $pdo->query("SELECT * FROM branches ORDER BY branch_id")->fetchAll();
$pageTitle = 'ผู้ใช้งาน'; $nav = 'users';
include __DIR__ . '/../../app/layout/header.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
  <div><h1 class="page-title">ผู้ใช้งาน</h1><p class="page-sub mb-0">สร้างบัญชีอาจารย์ด้วยรหัสบุคลากร และรีเซ็ตรหัสผ่าน</p></div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openUser()"><i class="bi bi-person-plus"></i> เพิ่มผู้ใช้</button>
</div>
<div class="card-soft p-3"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>รหัสบุคลากร</th><th>ชื่อ-สกุล</th><th>บทบาท</th><th>สาขา</th><th>นศ.ที่รับผิดชอบ</th><th>สถานะ</th><th></th></tr></thead><tbody>
  <?php foreach ($users as $x): ?>
    <tr class="<?= $x['is_active'] ? '' : 'text-muted' ?>">
      <td class="fw-medium"><?= e($x['staff_id']) ?></td><td><?= e($x['full_name']) ?></td>
      <td><?= $x['role'] === 'ADMIN' ? '<span class="badge badge-soft bg-primary-subtle text-primary-emphasis">Admin</span>' : '<span class="badge badge-soft bg-info-subtle text-info-emphasis">อาจารย์</span>' ?></td>
      <td><?= e($x['branch_name'] ?? '-') ?></td>
      <td><?= $x['role'] === 'TEACHER' ? (int)$x['n_assign'] : '-' ?></td>
      <td><?= $x['is_active'] ? '<span class="text-success"><i class="bi bi-circle-fill small"></i> ใช้งาน</span>' : '<span><i class="bi bi-circle small"></i> ปิดใช้งาน</span>' ?>
          <?= $x['must_change_password'] ? '<span class="badge bg-warning-subtle text-warning-emphasis ms-1">รอเปลี่ยนรหัส</span>' : '' ?></td>
      <td class="text-end text-nowrap">
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#userModal"
          onclick='openUser(<?= json_encode(["id" => $x["user_id"], "staff" => $x["staff_id"], "name" => $x["full_name"], "role" => $x["role"], "branch" => $x["branch_id"], "self" => $x["user_id"] == $me["id"]], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'><i class="bi bi-pencil"></i></button>
        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetModal"
          onclick='openReset(<?= (int)$x["user_id"] ?>, <?= json_encode($x["full_name"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)' title="รีเซ็ตรหัสผ่าน"><i class="bi bi-key"></i></button>
        <?php if ($x['user_id'] != $me['id']): ?>
        <form method="post" class="d-inline" onsubmit="return confirm('<?= $x['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>บัญชีนี้?')"><?= csrf_field() ?>
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int)$x['user_id'] ?>">
          <button class="btn btn-sm btn-outline-<?= $x['is_active'] ? 'danger' : 'success' ?>" title="<?= $x['is_active'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?>"><i class="bi bi-<?= $x['is_active'] ? 'slash-circle' : 'check-circle' ?>"></i></button></form>
        <?php endif; ?></td>
    </tr>
  <?php endforeach; ?></tbody></table></div></div>

<div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content" style="border-radius:16px">
  <?= csrf_field() ?><input type="hidden" name="action" id="u_action" value="add"><input type="hidden" name="user_id" id="u_id">
  <div class="modal-header border-0"><h5 class="modal-title" id="u_title">เพิ่มผู้ใช้</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label">รหัสบุคลากร (Staff ID)</label><input name="staff_id" id="u_staff" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">ชื่อ-สกุล</label><input name="full_name" id="u_name" class="form-control" required></div>
    <div class="row g-3 mb-3"><div class="col-6"><label class="form-label">บทบาท</label><select name="role" id="u_role" class="form-select"><option value="TEACHER">อาจารย์ผู้ประเมิน</option><option value="ADMIN">Admin</option></select></div>
      <div class="col-6" id="u_branch_wrap"><label class="form-label">สาขา</label><select name="branch_id" id="u_branch" class="form-select"><?php foreach ($branches as $b): ?><option value="<?= (int)$b['branch_id'] ?>"><?= e($b['branch_name']) ?></option><?php endforeach; ?></select></div></div>
    <div class="mb-1" id="u_pw_wrap"><label class="form-label">รหัสผ่านเริ่มต้น</label>
      <div class="input-group"><input name="password" id="u_pw" class="form-control" minlength="6"><button type="button" class="btn btn-outline-secondary" onclick="u_pw.value=gen()">สุ่ม</button></div>
      <div class="form-text">ผู้ใช้จะถูกบังคับเปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบครั้งแรก</div></div>
  </div>
  <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary">บันทึก</button></div>
</form></div></div>

<div class="modal fade" id="resetModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content" style="border-radius:16px">
  <?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="user_id" id="r_id">
  <div class="modal-header border-0"><h5 class="modal-title">รีเซ็ตรหัสผ่าน</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><p>ตั้งรหัสผ่านใหม่ให้ <b id="r_name"></b></p>
    <div class="input-group"><input name="password" id="r_pw" class="form-control" minlength="6" required><button type="button" class="btn btn-outline-secondary" onclick="r_pw.value=gen()">สุ่ม</button></div>
    <div class="form-text">จดรหัสนี้แจ้งผู้ใช้ ระบบจะบังคับให้เปลี่ยนรหัสเมื่อเข้าใช้ครั้งถัดไป</div></div>
  <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-warning">รีเซ็ตรหัสผ่าน</button></div>
</form></div></div>
<?php
$extraJs = <<<'JS'
<script>
function gen(){const c='ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';let s='';const a=new Uint32Array(8);crypto.getRandomValues(a);a.forEach(v=>s+=c[v%c.length]);return s;}
function syncRole(){u_branch_wrap.style.display=u_role.value==='ADMIN'?'none':'';}
u_role.addEventListener('change',syncRole);
function openUser(d){const add=!d;u_action.value=add?'add':'edit';u_title.textContent=add?'เพิ่มผู้ใช้':'แก้ไขผู้ใช้';
  u_id.value=d?d.id:'';u_staff.value=d?d.staff:'';u_staff.readOnly=!add;u_name.value=d?d.name:'';u_role.value=d?d.role:'TEACHER';u_role.disabled=!!(d&&d.self);
  if(d&&d.branch)u_branch.value=d.branch;u_pw_wrap.style.display=add?'':'none';u_pw.required=add;u_pw.value=add?gen():'';syncRole();}
function openReset(id,name){r_id.value=id;r_name.textContent=name;r_pw.value=gen();}
document.querySelector('#userModal form').addEventListener('submit',()=>{u_role.disabled=false;});
</script>
JS;
include __DIR__ . '/../../app/layout/footer.php'; ?>
