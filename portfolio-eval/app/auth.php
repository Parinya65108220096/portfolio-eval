<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('PEVAL');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => !empty($_SERVER['HTTPS']),
    ]);
}

function user(): ?array { return $_SESSION['user'] ?? null; }

// คืนข้อความ error หรือ null เมื่อสำเร็จ
function attempt_login(string $staffId, string $password): ?string {
    $f = $_SESSION['fail'] ?? ['n' => 0, 't' => 0];
    if ($f['n'] >= 5 && time() - $f['t'] < 300) return 'ลองเข้าสู่ระบบผิดหลายครั้ง กรุณารอ 5 นาทีแล้วลองใหม่';

    $st = db()->prepare("SELECT * FROM users WHERE staff_id = ? AND is_active = 1");
    $st->execute([trim($staffId)]);
    $u = $st->fetch();
    if (!$u || !password_verify($password, $u['password_hash'])) {
        $_SESSION['fail'] = ['n' => ($f['n'] >= 5 ? 0 : $f['n']) + 1, 't' => time()];
        return 'รหัสบุคลากรหรือรหัสผ่านไม่ถูกต้อง';
    }
    session_regenerate_id(true);
    unset($_SESSION['fail']);
    $_SESSION['user'] = [
        'id' => (int)$u['user_id'], 'name' => $u['full_name'], 'role' => $u['role'],
        'branch_id' => $u['branch_id'], 'must_change' => (int)$u['must_change_password'],
    ];
    return null;
}

function require_login(?string $role = null): void {
    $u = user();
    if (!$u) redirect('login.php');
    // ตรวจสถานะล่าสุดจากฐานข้อมูลทุกครั้ง (กรณี Admin ปิดบัญชี)
    $st = db()->prepare("SELECT is_active, must_change_password FROM users WHERE user_id = ?");
    $st->execute([$u['id']]);
    $row = $st->fetch();
    if (!$row || !$row['is_active']) { $_SESSION = []; redirect('login.php'); }
    $_SESSION['user']['must_change'] = (int)$row['must_change_password'];
    if ($_SESSION['user']['must_change'] && basename($_SERVER['SCRIPT_NAME']) !== 'change_password.php') {
        redirect('change_password.php');
    }
    if ($role && $u['role'] !== $role) { http_response_code(403); exit('ไม่มีสิทธิ์เข้าถึงหน้านี้'); }
}
