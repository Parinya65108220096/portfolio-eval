<?php
// คะแนนรวมถ่วงน้ำหนัก (เต็ม 100) จากคะแนน 1-5 รายหัวข้อ; คืน null ถ้ากรอกไม่ครบ
function calc_total(array $scores): ?float {
    $total = 0.0;
    foreach (cfg('weights') as $key => $weight) {
        $s = (int)($scores[$key] ?? 0);
        if ($s < 1 || $s > 5) return null;
        $total += ($s / 5) * $weight;
    }
    return round($total, 2);
}

// สรุปผลนักศึกษา: ต้องส่งครบ 2 ท่านจึงจะสรุป
function recalc_student(PDO $pdo, int $studentId): void {
    $st = $pdo->prepare("SELECT e.total_score FROM evaluations e
        JOIN assignments a ON a.assignment_id = e.assignment_id
        WHERE a.student_id = ? AND e.is_submitted = 1");
    $st->execute([$studentId]);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);
    if (count($rows) < 2) {
        $pdo->prepare("UPDATE students SET final_score = NULL, final_status = 'PENDING' WHERE student_id = ?")
            ->execute([$studentId]);
        return;
    }
    $final  = round(array_sum($rows) / 2, 2);
    $status = $final >= cfg('pass_threshold') ? 'PASS' : 'FAIL';
    $pdo->prepare("UPDATE students SET final_score = ?, final_status = ? WHERE student_id = ?")
        ->execute([$final, $status, $studentId]);
}
