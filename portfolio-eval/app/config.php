<?php
// ===== ตั้งค่าระบบ (แก้ไขไฟล์นี้ไฟล์เดียว) =====
return [
    'app_name' => 'ระบบประเมิน Portfolio',

    // ฐานข้อมูล MySQL / MariaDB
    'db' => [
        'host'    => '127.0.0.1',
        'name'    => 'portfolio_eval',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // ถ้าตั้ง Document Root ไปที่โฟลเดอร์ public ให้เว้นว่างไว้ ('')
    // ถ้าวางไว้ใน htdocs ของ XAMPP ให้ใส่เช่น '/portfolio-eval/public'
    'base_url' => '/portfolio-eval/public',

    // เกณฑ์ผ่าน และน้ำหนักคะแนน (รวมต้องเท่ากับ 100)
    'pass_threshold' => 80,
    'weights' => ['personal' => 20, 'activity' => 40, 'rec' => 10, 'english' => 30],

    // เตือน Admin เมื่อคะแนนอาจารย์ 2 ท่านต่างกันตั้งแต่ค่านี้ขึ้นไป
    'diff_flag' => 20,

    'max_upload_mb' => 20,
    'timezone' => 'Asia/Bangkok',
];
