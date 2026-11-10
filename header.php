<?php
// ==========================================
// 全ページ共通のヘッダー・ナビゲーション・トースト/ポップアップの置き場
// ==========================================
$current_page = basename($_SERVER['SCRIPT_NAME']);

function nav_link(string $href, string $label, string $current_page): void {
    $active = ($href === $current_page) ? ' active' : '';
    echo '<a href="' . $href . '" class="nav-btn' . $active . '"' . ($active ? ' aria-current="page"' : '') . '>' . $label . '</a>';
}
?>
<header class="app-header">
    <div class="app-title">勉強大好きアプリ <span class="tag">うみ</span></div>
    <nav class="app-nav" aria-label="メインメニュー">
        <?php nav_link('index.php', 'ホーム', $current_page); ?>
        <?php nav_link('task_reward.php', '課題', $current_page); ?>
        <?php nav_link('timer.php', 'タイマー', $current_page); ?>
        <?php nav_link('history.php', '歴史', $current_page); ?>
        <?php nav_link('bottle_mail.php', '手紙', $current_page); ?>
        <?php nav_link('settings.php', '設定', $current_page); ?>
    </nav>
</header>

<!-- エラー・お知らせはここに表示（alert を使わず、画面内で完結させる） -->
<div id="toastRoot" class="toast-root" aria-live="polite"></div>

<!-- ポイント獲得ポップアップ（js/app.js が中身を入れる） -->
<div id="pointModal" class="modal-back" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="pmTitle">
        <h2 id="pmTitle" class="modal-title">ポイントを獲得しました</h2>
        <div id="pmBody"></div>
        <label class="field" for="pmCaption">キャプション（何で得たポイントか）</label>
        <input type="text" id="pmCaption" maxlength="100" placeholder="例：数学の復習をがんばった">
        <div class="btn-row">
            <button type="button" class="btn" id="pmSave">記録に追加</button>
            <button type="button" class="btn btn-sub" id="pmClose">閉じる</button>
        </div>
    </div>
</div>
<!-- 確認ダイアログ（confirm() の代わりに、画面内で確認する） -->
<div id="confirmModal" class="modal-back" hidden>
    <div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="cfTitle">
        <h2 id="cfTitle" class="modal-title">確認</h2>
        <p id="cfMsg"></p>
        <div class="btn-row">
            <button type="button" class="btn btn-danger" id="cfOk">実行する</button>
            <button type="button" class="btn btn-sub" id="cfCancel">やめる</button>
        </div>
    </div>
</div>
<script src="js/sound.js" defer></script>
<script src="js/app.js" defer></script>
