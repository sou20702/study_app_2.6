<?php
// 設定タブの「問い合わせ」から呼ばれる。内容を inquiries テーブルに保存する
require_once __DIR__ . '/../config/db_config.php';
require_fetch_post();

$category = (string)($_POST['category'] ?? 'other');
if (!in_array($category, ['bug', 'request', 'other'], true)) {
    $category = 'other';
}
$message = trim((string)($_POST['message'] ?? ''));
$email = trim((string)($_POST['reply_email'] ?? ''));

if ($message === '') {
    json_out(['ok' => false, 'message' => '問い合わせの内容を入力してください。'], 400);
}
if (mb_strlen($message) > INQUIRY_MAX_LENGTH) {
    json_out(['ok' => false, 'message' => '問い合わせの内容は' . INQUIRY_MAX_LENGTH . '文字以内で入力してください。'], 400);
}
if ($email !== '' && (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    json_out(['ok' => false, 'message' => 'メールアドレスの形式が正しくありません。'], 400);
}

$pid = get_player_id($pdo);

// 連続送信の防止（1時間に10件まで）
$chk = $pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE player_id = :p AND created_at > (NOW() - INTERVAL 1 HOUR)");
$chk->execute([':p' => $pid]);
if ((int)$chk->fetchColumn() >= 10) {
    json_out(['ok' => false, 'message' => '短時間に送信しすぎです。しばらくしてからもう一度お試しください。'], 429);
}

$stmt = $pdo->prepare("INSERT INTO inquiries (player_id, category, message, reply_email) VALUES (:p, :c, :m, :e)");
$stmt->execute([':p' => $pid, ':c' => $category, ':m' => $message, ':e' => $email]);

json_out([
    'ok'   => true,
    'item' => ['category' => $category, 'message' => $message, 'created_at' => date('Y-m-d H:i:s')],
]);
