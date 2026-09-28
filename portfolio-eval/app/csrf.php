<?php
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_ok($t): bool { return is_string($t) && hash_equals(csrf_token(), $t); }
function csrf_verify(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_ok($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('หน้าเว็บหมดอายุ กรุณากลับไปโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}
