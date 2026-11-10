<?php
// ボトルを1通拾って JSON を返す（bottle_mail.php から fetch で呼ばれる。画面は移動しない）
require_once __DIR__ . '/db.php';
require_fetch_post();

$client_id = get_client_id();

// 自分が流したボトルは拾わない。まだ拾っていないボトルを優先してランダムに1通選ぶ
$sql = "
SELECT m.*
FROM bottle_mails m
WHERE m.sender_token IS NULL OR m.sender_token <> :me
ORDER BY
    EXISTS (SELECT 1 FROM bottle_mail_receipts r
            WHERE r.mail_id = m.id AND r.receiver_token = :me2) ASC,
    RAND()
LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':me' => $client_id, ':me2' => $client_id]);
$mail = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mail) {
    json_out(['ok' => true, 'mail' => null, 'message' => 'まだボトルが流れていません。']);
}

// 受信履歴に記録（同じボトルは1回だけ）。すでに拾っていたボトルなら、前に送ったリアクションも返す
$prev = $pdo->prepare("SELECT reaction FROM bottle_mail_receipts WHERE mail_id = :id AND receiver_token = :t");
$prev->execute([':id' => $mail['id'], ':t' => $client_id]);
$prev_row = $prev->fetch(PDO::FETCH_ASSOC);
$already = $prev_row !== false;
$reaction = ($already && isset(MAIL_REACTIONS[(string)$prev_row['reaction']])) ? (string)$prev_row['reaction'] : null;

$log = $pdo->prepare("INSERT IGNORE INTO bottle_mail_receipts (mail_id, receiver_token) VALUES (:id, :t)");
$log->execute([':id' => $mail['id'], ':t' => $client_id]);

json_out([
    'ok'   => true,
    'mail' => [
        'id'          => (int)$mail['id'],
        'already'     => $already,       // 以前にも拾ったボトルか（履歴に重複して足さないため）
        'reaction'    => $reaction,      // 自分がこのボトルに送ったリアクション（無ければ null）
        'message'     => (string)$mail['message'],
        'canvas_data' => (string)$mail['canvas_data'],
        'created_at'  => date('Y-m-d H:i:s'),   // 受信した日時
    ],
]);
