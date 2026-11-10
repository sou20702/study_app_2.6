<?php
// データベース接続設定の共通ルール
date_default_timezone_set('Asia/Tokyo'); // 提出期限までの日数を日本時間で計算するため
require_once __DIR__ . '/error_view.php';
require_once __DIR__ . '/game_config.php';

// 想定外の例外も、アプリ内のエラー画面で表示する
set_exception_handler(function (Throwable $e) {
    app_fatal('エラーが発生しました', $e->getMessage());
});

$host = 'localhost';
$dbname = 'study_app'; // 全員このDB名で作成する
$user = 'root';        // XAMPPのデフォルト
$pass = '';            // XAMPPのデフォルト

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    app_fatal('データベースに接続できません', $e->getMessage());
}

// 共通ライブラリ（スキーマ自動更新・グループ・課題など）と、成長（ポイント・進化レベル）の処理
require_once __DIR__ . '/app_lib.php';
require_once __DIR__ . '/growth_lib.php';
try {
    ensure_schema($pdo);
} catch (Throwable $e) {
    app_fatal('データベースの準備に失敗しました', $e->getMessage());
}
get_client_id(); // 端末ID(Cookie)を、ページ出力より前に必ず発行しておく
