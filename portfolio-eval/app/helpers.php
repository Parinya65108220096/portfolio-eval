<?php
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function url(string $p = ''): string { return rtrim((string)cfg('base_url'), '/') . '/' . ltrim($p, '/'); }
function redirect(string $p): never { header('Location: ' . url($p)); exit; }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function criteria(): array {
    return [
        'personal' => ['label' => 'Personal Statement',        'hint' => 'ไม่เกิน 2 หน้า A4',                 'col' => 'personal_statement', 'icon' => 'bi-person-lines-fill'],
        'activity' => ['label' => 'กิจกรรมและผลงาน',            'hint' => 'อย่างน้อย 3 กิจกรรม',               'col' => 'activity',           'icon' => 'bi-trophy'],
        'rec'      => ['label' => 'หนังสือรับรอง',              'hint' => 'จากผู้บริหาร/อาจารย์ที่ปรึกษา',      'col' => 'recommendation',     'icon' => 'bi-envelope-paper'],
        'english'  => ['label' => 'มาตรฐานความรู้ภาษาอังกฤษ',   'hint' => 'ผลสอบไม่เกิน 2 ปี',                 'col' => 'english',            'icon' => 'bi-translate'],
    ];
}

function fmt_dt(?string $d): string {
    if (!$d) return '-';
    $t = strtotime($d);
    return date('d/m/', $t) . (date('Y', $t) + 543) . date(' H:i', $t);
}

function status_badge(string $s): string {
    return match ($s) {
        'PASS' => '<span class="badge badge-soft bg-success-subtle text-success-emphasis"><i class="bi bi-check-circle-fill"></i> ผ่าน</span>',
        'FAIL' => '<span class="badge badge-soft bg-danger-subtle text-danger-emphasis"><i class="bi bi-x-circle-fill"></i> ไม่ผ่าน</span>',
        default => '<span class="badge badge-soft bg-secondary-subtle text-secondary-emphasis"><i class="bi bi-hourglass-split"></i> รอผล</span>',
    };
}

// แปลงลิงก์ Google Drive ให้ฝังได้
function embed_url(string $link): string {
    if (preg_match('~drive\.google\.com/file/d/([^/?#]+)~', $link, $m)) {
        return 'https://drive.google.com/file/d/' . $m[1] . '/preview';
    }
    return $link;
}

function valid_link(string $u): bool {
    return (bool)filter_var($u, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $u);
}

function portfolio_dir(): string { return dirname(__DIR__) . '/storage/portfolios/'; }

// บันทึกไฟล์ PDF ที่อัปโหลด คืนชื่อไฟล์ใหม่ หรือ throw เมื่อไม่ผ่าน
function store_pdf(array $f): string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ (ตรวจสอบขนาดไฟล์ หรือค่า upload_max_filesize ใน php.ini)');
    }
    if ($f['size'] > cfg('max_upload_mb') * 1048576) {
        throw new RuntimeException('ไฟล์ใหญ่เกิน ' . cfg('max_upload_mb') . ' MB');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if ($mime !== 'application/pdf') throw new RuntimeException('รองรับเฉพาะไฟล์ PDF');
    $name = bin2hex(random_bytes(16)) . '.pdf';
    if (!move_uploaded_file($f['tmp_name'], portfolio_dir() . $name)) {
        throw new RuntimeException('บันทึกไฟล์ไม่ได้ ตรวจสอบสิทธิ์เขียนของโฟลเดอร์ storage/portfolios');
    }
    return $name;
}

function delete_pdf(?string $name): void {
    if ($name && preg_match('/^[a-f0-9]{32}\.pdf$/', $name)) @unlink(portfolio_dir() . $name);
}

function audit(string $action, string $detail = ''): void {
    db()->prepare("INSERT INTO audit_log (user_id, action, detail) VALUES (?,?,?)")
        ->execute([user()['id'] ?? null, $action, $detail]);
}
