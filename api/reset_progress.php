<?php
// 設定タブの「ゲームの進行をリセット」から呼ばれる。
// ポイント・進化レベル・歴史を消して、最初の状態に戻す（課題・グループ・手紙は消さない）
require_once __DIR__ . '/../config/db_config.php';
require_fetch_post();

$pid = get_player_id($pdo);
$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM point_history WHERE player_id = :p")->execute([':p' => $pid]);
    $pdo->prepare("UPDATE user_status SET growth = 0, timer_pool = 0 WHERE id = :id")->execute([':id' => $pid]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out(['ok' => false, 'message' => 'リセットに失敗しました: ' . $e->getMessage()], 500);
}
json_out(['ok' => true, 'message' => 'ゲームの進行をリセットしました。']);
