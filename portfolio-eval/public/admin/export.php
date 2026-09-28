<?php
require __DIR__ . '/../../app/bootstrap.php';
require_login('ADMIN');
require __DIR__ . '/../../app/xlsx.php';
if (!class_exists('ZipArchive')) exit('เซิร์ฟเวอร์ยังไม่เปิดใช้ PHP extension "zip" (เปิดใน php.ini: extension=zip)');
$pdo = db();
$crit = criteria();

$ev = [];
$q = $pdo->query("SELECT a.student_id, a.slot, u.full_name teacher, e.*
    FROM assignments a JOIN users u ON u.user_id=a.teacher_id
    LEFT JOIN evaluations e ON e.assignment_id=a.assignment_id AND e.is_submitted=1");
foreach ($q->fetchAll() as $r) $ev[$r['student_id']][$r['slot']] = $r;

// รวมคะแนนรายหัวข้อ + ความเห็นเป็นข้อความเดียวต่ออาจารย์ 1 ท่าน
$note = function (?array $r) use ($crit): string {
    if (!$r || empty($r['is_submitted'])) return '';
    $out = [];
    foreach ($crit as $k => $c) {
        $line = $c['label'] . ': ' . (int)$r[$c['col'] . '_score'] . '/5';
        if (trim((string)$r[$c['col'] . '_comment']) !== '') $line .= ' — ' . trim($r[$c['col'] . '_comment']);
        $out[] = $line;
    }
    if (trim((string)$r['overall_comment']) !== '') $out[] = 'ภาพรวม: ' . trim($r['overall_comment']);
    return implode("\n", $out);
};

$thr = (int)cfg('pass_threshold');
$book = new SimpleXlsx();
foreach ($pdo->query("SELECT * FROM branches ORDER BY branch_id")->fetchAll() as $b) {
    $rows = [['รหัส', 'ชื่อ-สกุล', 'อาจารย์ท่านที่ 1', 'คะแนน (1)', 'อาจารย์ท่านที่ 2', 'คะแนน (2)', 'คะแนนเฉลี่ย', 'ผลการประเมิน', 'ผลต่างคะแนน', 'รายละเอียด/ความเห็น อาจารย์ท่านที่ 1', 'รายละเอียด/ความเห็น อาจารย์ท่านที่ 2']];
    $st = $pdo->prepare("SELECT student_id, student_code, full_name FROM students WHERE branch_id=? ORDER BY student_code");
    $st->execute([$b['branch_id']]);
    foreach ($st->fetchAll() as $s) {
        $r = count($rows) + 1; $e1 = $ev[$s['student_id']][1] ?? null; $e2 = $ev[$s['student_id']][2] ?? null;
        $s1 = $e1 && !empty($e1['is_submitted']) ? (float)$e1['total_score'] : '';
        $s2 = $e2 && !empty($e2['is_submitted']) ? (float)$e2['total_score'] : '';
        $rows[] = [
            $s['student_code'], $s['full_name'],
            $e1['teacher'] ?? '', $s1, $e2['teacher'] ?? '', $s2,
            ['f' => "IF(AND(ISNUMBER(D$r),ISNUMBER(F$r)),AVERAGE(D$r,F$r),\"\")"],
            ['f' => "IF(G$r=\"\",\"รอผล\",IF(G$r>=$thr,\"ผ่าน\",\"ไม่ผ่าน\"))"],
            ['f' => "IF(G$r=\"\",\"\",ABS(D$r-F$r))"],
            $note($e1), $note($e2),
        ];
    }
    $book->addSheet($b['branch_name'], $rows, [14, 28, 26, 11, 26, 11, 13, 15, 13, 60, 60]);
}
$book->output('ผลประเมิน_Portfolio_' . date('Ymd') . '.xlsx');
