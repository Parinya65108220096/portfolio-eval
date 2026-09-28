<?php
require __DIR__ . '/../app/bootstrap.php';
$u = user();
if (!$u) redirect('login.php');
redirect($u['role'] === 'ADMIN' ? 'admin/dashboard.php' : 'teacher/dashboard.php');
