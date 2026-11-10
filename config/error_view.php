<?php
// ==========================================
// エラー表示（別ページに飛ばさず、アプリの見た目のまま表示する）
//  - fetch 通信のとき：JSON を返す（画面側でトースト表示）
//  - 通常のページ：ヘッダー付きのエラー画面を表示（再読み込みボタン付き）
// ==========================================
if (!function_exists('app_fatal')) {
    function app_fatal(string $title, string $detail = '', int $code = 500): void {
        http_response_code($code);
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'message' => $title], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $dir = basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $base = in_array($dir, ['api', 'db'], true) ? '../' : '';
        $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $d = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>エラー</title><script src="' . $base . 'js/i18n.js"></script><link rel="stylesheet" href="' . $base . 'css/style.css"></head><body>'
           . '<header class="app-header"><div class="app-title">勉強大好きアプリ</div></header>'
           . '<main class="wrap"><section class="panel error-panel"><h2>' . $t . '</h2>'
           . '<p>XAMPP の Apache と MySQL が起動しているか確認してから、もう一度お試しください。</p>'
           . ($d !== '' ? '<p class="note">詳細: ' . $d . '</p>' : '')
           . '<button type="button" class="btn" onclick="location.reload()">再読み込み</button> '
           . '<button type="button" class="btn btn-sub" onclick="history.length>1?history.back():location.assign(\'' . $base . 'index.php\')">前の画面へ</button>'
           . '</section></main></body></html>';
        exit;
    }
}
