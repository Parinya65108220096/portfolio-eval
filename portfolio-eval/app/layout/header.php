<?php
$u = user();
$nav = $nav ?? '';
$items = [];
if ($u && $u['role'] === 'ADMIN') {
    $items = [
        'dashboard'   => ['admin/dashboard.php',   'bi-speedometer2',  'ภาพรวม'],
        'users'       => ['admin/users.php',       'bi-people',        'ผู้ใช้งาน'],
        'students'    => ['admin/students.php',    'bi-mortarboard',   'นักศึกษา & Portfolio'],
        'assignments' => ['admin/assignments.php', 'bi-diagram-3',     'มอบหมายผู้ประเมิน'],
        'results'     => ['admin/results.php',     'bi-clipboard-data','ผลการประเมิน'],
        'rubric'      => ['admin/rubric.php',      'bi-list-check',    'เกณฑ์ Rubric'],
    ];
} elseif ($u) {
    $items = ['dashboard' => ['teacher/dashboard.php', 'bi-card-checklist', 'รายการประเมินของฉัน']];
}
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($pageTitle ?? '') . ' | ' . cfg('app_name')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<?php if ($u): ?>
<nav class="navbar navbar-expand-lg app-nav sticky-top">
  <div class="container-fluid px-lg-4">
    <a class="navbar-brand fw-semibold" href="<?= e(url('index.php')) ?>"><i class="bi bi-journal-check"></i> <?= e(cfg('app_name')) ?></a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nv"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nv">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
        <?php foreach ($items as $k => [$href, $icon, $label]): ?>
          <li class="nav-item"><a class="nav-link <?= $nav === $k ? 'active' : '' ?>" href="<?= e(url($href)) ?>"><i class="bi <?= $icon ?>"></i> <?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="dropdown">
        <a class="btn btn-sm btn-light dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <?= e($u['name']) ?></a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><span class="dropdown-item-text small text-muted"><?= $u['role'] === 'ADMIN' ? 'ผู้ดูแลระบบ' : 'อาจารย์ผู้ประเมิน' ?></span></li>
          <li><a class="dropdown-item" href="<?= e(url('change_password.php')) ?>"><i class="bi bi-key"></i> เปลี่ยนรหัสผ่าน</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= e(url('logout.php')) ?>"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>
<?php endif; ?>
<main class="<?= e($mainClass ?? 'container-xxl py-4 px-lg-4') ?>">
<?php foreach (flashes() as [$type, $msg]): ?>
  <div class="alert alert-<?= e($type) ?> alert-dismissible fade show shadow-sm" role="alert">
    <?= e($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endforeach; ?>
