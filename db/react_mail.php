<?php
// 拾ったボトルにリアクションを保存して JSON を返す（bottle_mail.php から fetch で呼ばれる）
// 自分が拾ったボトルにだけ送れる。1通につき1つで、押し直すと上書きされる
require_once __DIR__ . '/db.php';
require_fetch_post();

$mail_id  = (int)($_POST['mail_id'] ?? 0);
$reaction = (string)($_POST['reaction'] ?? '');

if ($mail_id < 1 || !isset(MAIL_REACTIONS[$reaction])) {
    json_out(['ok' => false, 'message' => 'リアクションを送れませんでした。'], 400);
}

$client_id = get_client_id();
$chk = $pdo->prepare("SELECT id FROM bottle_mail_receipts WHERE mail_id = :m AND receiver_token = :t");
$chk->execute([':m' => $mail_id, ':t' => $client_id]);
if (!$chk->fetchColumn()) {
    json_out(['ok' => false, 'message' => 'このボトルはまだ開封していないので、リアクションを送れません。'], 403);
}

$upd = $pdo->prepare("UPDATE bottle_mail_receipts SET reaction = :r WHERE mail_id = :m AND receiver_token = :t");
$upd->execute([':r' => $reaction, ':m' => $mail_id, ':t' => $client_id]);

json_out(['ok' => true, 'reaction' => $reaction]);
