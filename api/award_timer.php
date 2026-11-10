<?php
// タイマーの1フェーズ（勉強/休憩）が完了したときに timer.js から呼ばれる
require_once __DIR__ . '/../config/db_config.php';
require_fetch_post();

try {
    $res = award_timer_phase($pdo, (string)($_POST['phase'] ?? ''), (int)($_POST['minutes'] ?? 0));
    json_out(['ok' => true] + $res);
} catch (InvalidArgumentException $e) {
    json_out(['ok' => false, 'message' => $e->getMessage()], 400);
}
