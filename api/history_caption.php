<?php
// 歴史の項目にキャプション（何で得たポイントか）を保存する
require_once __DIR__ . '/../config/db_config.php';
require_fetch_post();

$id = (int)($_POST['id'] ?? 0);
$caption = trim((string)($_POST['caption'] ?? ''));
if (mb_strlen($caption) > CAPTION_MAX_LENGTH) {
    json_out(['ok' => false, 'message' => 'キャプションは' . CAPTION_MAX_LENGTH . '文字以内で入力してください。'], 400);
}
$stmt = $pdo->prepare("UPDATE point_history SET caption = :c WHERE id = :id AND player_id = :p");
$stmt->execute([':c' => $caption, ':id' => $id, ':p' => get_player_id($pdo)]);
if ($stmt->rowCount() === 0) {
    // 内容が同じで更新されなかった場合と、対象がない場合を区別する
    $chk = $pdo->prepare("SELECT COUNT(*) FROM point_history WHERE id = :id AND player_id = :p");
    $chk->execute([':id' => $id, ':p' => get_player_id($pdo)]);
    if ((int)$chk->fetchColumn() === 0) {
        json_out(['ok' => false, 'message' => '対象の記録が見つかりません。'], 404);
    }
}
json_out(['ok' => true, 'caption' => $caption]);
