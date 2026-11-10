<?php
// ボトルメールを保存して JSON を返す（bottle_mail.php から fetch で呼ばれる。画面は移動しない）
require_once __DIR__ . '/db.php';
require_fetch_post();

$message = trim((string)($_POST['message'] ?? ''));
$drawing = (string)($_POST['drawing'] ?? '');

if (mb_strlen($message) > 1000) {
    json_out(['ok' => false, 'message' => 'メッセージは1000文字以内で入力してください。'], 400);
}
if ($drawing !== '' && (strpos($drawing, 'data:image/png;base64,') !== 0 || strlen($drawing) > 2000000)) {
    json_out(['ok' => false, 'message' => '絵のデータが正しくありません。もう一度描いてください。'], 400);
}

// メッセージも絵も無い「空のボトル」は流せない（どちらか一方があればOK）
if ($message === '' && $drawing === '') {
    json_out(['ok' => false, 'message' => 'メッセージを書くか、絵を描いてから流してください。'], 400);
}

$stmt = $pdo->prepare("INSERT INTO bottle_mails (message, canvas_data, sender_token) VALUES (:message, :drawing, :sender)");
$stmt->execute([':message' => $message, ':drawing' => $drawing, ':sender' => get_client_id()]);

json_out([
    'ok'   => true,
    'mail' => ['message' => $message, 'canvas_data' => $drawing, 'created_at' => date('Y-m-d H:i:s')],
]);
