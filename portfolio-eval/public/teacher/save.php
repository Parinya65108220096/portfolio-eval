<?php
require __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$u = user();
if (!$u || $u['role'] !== 'TEACHER') { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'กรุณาเข้าสู่ระบบใหม่']); exit; }
$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!csrf_ok($in['csrf'] ?? null)) { http_response_code(419); echo json_encode(['ok' => false, 'msg' => 'หน้าเว็บหมดอายุ กรุณารีเฟรช']); exit; }

$pdo = db();
$aid = (int)($in['assignment_id'] ?? 0);

// 1) assignment ต้องเป็นของอาจารย์คนนี้
$st = $pdo->prepare("SELECT student_id FROM assignments WHERE assignment_id = ? AND teacher_id = ?");
$st->execute([$aid, $u['id']]);
$studentId = $st->fetchColumn();
if (!$studentId) { http_response_code(403); echo json_encode(['ok' => false, 'msg' => 'ไม่มีสิทธิ์']); exit; }

// 2) ส่งแล้วห้ามแก้
$chk = $pdo->prepare("SELECT is_submitted FROM evaluations WHERE assignment_id = ?");
$chk->execute([$aid]);
if ((int)$chk->fetchColumn() === 1) { echo json_encode(['ok' => false, 'msg' => 'ส่งผลแล้ว แก้ไขไม่ได้']); exit; }

// 3) เตรียมข้อมูล (เซิร์ฟเวอร์คำนวณคะแนนเอง)
$s = [];
foreach (array_keys(cfg('weights')) as $k) {
    $v = (int)($in['scores'][$k] ?? 0);
    $s[$k] = ($v >= 1 && $v <= 5) ? $v : null;
}
$submit = !empty($in['submit']);
$total = calc_total($s);
if ($submit && $total === null) { echo json_encode(['ok' => false, 'msg' => 'กรุณาให้คะแนนให้ครบทุกหัวข้อ']); exit; }
$c = $in['comments'] ?? [];
$cm = fn($k) => mb_substr(trim((string)($c[$k] ?? '')), 0, 3000);

$sql = "INSERT INTO evaluations
 (assignment_id, personal_statement_score, personal_statement_comment, activity_score, activity_comment,
  recommendation_score, recommendation_comment, english_score, english_comment, total_score, overall_comment,
  is_submitted, submitted_at)
 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
 ON DUPLICATE KEY UPDATE
  personal_statement_score=VALUES(personal_statement_score), personal_statement_comment=VALUES(personal_statement_comment),
  activity_score=VALUES(activity_score), activity_comment=VALUES(activity_comment),
  recommendation_score=VALUES(recommendation_score), recommendation_comment=VALUES(recommendation_comment),
  english_score=VALUES(english_score), english_comment=VALUES(english_comment),
  total_score=VALUES(total_score), overall_comment=VALUES(overall_comment),
  is_submitted=VALUES(is_submitted), submitted_at=VALUES(submitted_at)";
$pdo->prepare($sql)->execute([
    $aid, $s['personal'], $cm('personal'), $s['activity'], $cm('activity'),
    $s['rec'], $cm('rec'), $s['english'], $cm('english'), $total, $cm('overall'),
    $submit ? 1 : 0, $submit ? date('Y-m-d H:i:s') : null,
]);

if ($submit) recalc_student($pdo, (int)$studentId);
echo json_encode(['ok' => true, 'total' => $total, 'saved_at' => date('H:i:s')]);
