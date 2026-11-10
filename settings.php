<?php
require_once 'config/db_config.php';

$pid = get_player_id($pdo);

// クレジット（config/credits.php）。BGMの title は、BGM選択の名前にも使う
$credits = require __DIR__ . '/config/credits.php';
$bgm_names = [];
foreach ($credits['BGM'] ?? [] as $c) {
    if (!empty($c['id']) && trim((string)($c['title'] ?? '')) !== '') {
        $bgm_names[$c['id']] = (string)$c['title'];
    }
}
function credit_value(string $v): string {
    return $v === '' ? '<span class="muted">（未入力）</span>' : '<span data-no-i18n>' . h($v) . '</span>';
}
$category_labels = ['bug' => '不具合の報告', 'request' => '要望', 'other' => 'その他'];

// 自分が送った問い合わせ（新しい順に20件）
$stmt = $pdo->prepare("SELECT category, message, created_at FROM inquiries WHERE player_id = :p ORDER BY id DESC LIMIT 20");
$stmt->execute([':p' => $pid]);
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>設定 - 勉強大好きアプリ</title>
    <script src="js/i18n.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/settings.css">
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <main class="wrap">
        <h1>設定</h1>

        <!-- ================= 言語 ================= -->
        <section class="panel" id="secLang">
            <h2>言語</h2>
            <div class="radio-row" role="radiogroup" aria-label="言語">
                <label data-no-i18n><input type="radio" name="lang" value="ja"> 日本語</label>
                <label data-no-i18n><input type="radio" name="lang" value="en"> English</label>
            </div>
            <p class="note">切り替えると、画面が自動で読み込み直されます。</p>
        </section>

        <!-- ================= サウンド ================= -->
        <section class="panel" id="secSound">
            <h2>サウンド</h2>

            <div class="setting-row">
                <label for="bgmTrack">BGM</label>
                <select id="bgmTrack">
                    <option value="off">BGMなし</option>
                </select>
            </div>
            <p class="note" id="bgmNone" hidden>BGMの音源がまだ置かれていません（audio フォルダに bgm_1.mp3〜bgm_3.mp3 を置くと選べます）。</p>

            <div class="setting-row">
                <label for="muteBox">ミュート</label>
                <label class="check"><input type="checkbox" id="muteBox"> BGMと効果音をすべて消す</label>
            </div>

            <div class="setting-row">
                <label for="bgmVol">BGMの音量</label>
                <div class="range-line"><input type="range" id="bgmVol" min="0" max="100" step="1"><span class="range-val" id="bgmVolVal"></span></div>
            </div>

            <div class="setting-row">
                <label for="seVol">効果音の音量</label>
                <div class="range-line"><input type="range" id="seVol" min="0" max="100" step="1"><span class="range-val" id="seVolVal"></span></div>
            </div>

            <div class="btn-row">
                <button type="button" class="btn btn-sub" id="seTest">効果音を試す</button>
            </div>
            <p class="note">音量やミュートの設定は、このブラウザに保存されます。</p>
        </section>

        <!-- ================= リセット ================= -->
        <section class="panel" id="secReset">
            <h2>リセット</h2>

            <div class="reset-block">
                <h3>設定をもとに戻す</h3>
                <p class="note">言語・BGM・音量・ミュートを、最初の状態に戻します。</p>
                <button type="button" class="btn btn-sub" id="resetSettings">設定を初期値に戻す</button>
            </div>

            <div class="reset-block">
                <h3>ゲームの進行をリセット</h3>
                <p class="note">ポイント・進化レベル・歴史を消して、最初の状態に戻します。課題・グループ・手紙は消えません。この操作は取り消せません。</p>
                <label class="check"><input type="checkbox" id="resetTimerLog"> タイマーの学習記録（グラフ）も消す</label>
                <div class="btn-row">
                    <button type="button" class="btn btn-danger" id="resetProgress">ゲームの進行をリセット</button>
                </div>
            </div>
        </section>

        <!-- ================= 問い合わせ ================= -->
        <section class="panel" id="secContact">
            <h2>問い合わせ</h2>
            <p class="note" style="margin-top:0;">不具合・要望・質問などを送れます。送った内容は開発チームが確認します。</p>

            <form id="inquiryForm">
                <div class="field">
                    <label for="inqCategory">種類</label>
                    <select id="inqCategory" name="category">
                        <?php foreach ($category_labels as $key => $label): ?>
                            <option value="<?= h($key) ?>"><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="inqMessage">内容</label>
                    <textarea id="inqMessage" name="message" rows="5" maxlength="<?= INQUIRY_MAX_LENGTH ?>" required placeholder="例：タイマーが終わっても音が鳴りません"></textarea>
                </div>
                <div class="field">
                    <label for="inqEmail">返信用のメールアドレス（任意）</label>
                    <input type="text" id="inqEmail" name="reply_email" maxlength="255" placeholder="example@mail.com">
                </div>
                <div class="btn-row">
                    <button type="submit" class="btn" id="inqSend">送信する</button>
                    <?php if (CONTACT_EMAIL !== ''): ?>
                        <a class="btn btn-sub" href="mailto:<?= h(CONTACT_EMAIL) ?>">メールで送る</a>
                    <?php endif; ?>
                </div>
            </form>

            <h3 style="margin-top:22px;">送信した問い合わせ</h3>
            <p class="history-empty" id="inqEmpty" <?= empty($inquiries) ? '' : 'hidden' ?>>まだ問い合わせを送っていません。</p>
            <div id="inqList">
                <?php foreach ($inquiries as $q): ?>
                    <div class="history-item">
                        <div class="history-date"><?= h($q['created_at']) ?>　<?= h($category_labels[$q['category']] ?? 'その他') ?></div>
                        <div class="history-msg" data-no-i18n><?= nl2br(h($q['message'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ================= クレジット ================= -->
        <section class="panel" id="credits">
            <h2>クレジット</h2>
            <p class="note" style="margin-top:0;">このアプリで使っている音・画像・フォントの出どころです。</p>

            <?php foreach ($credits as $group => $items): ?>
                <h3 class="credit-group"><?= h($group) ?></h3>
                <?php foreach ($items as $c): ?>
                    <?php
                        $url = (string)($c['url'] ?? '');
                        $url_ok = preg_match('#^https?://#i', $url) === 1;
                        $site = (string)($c['site'] ?? '');
                    ?>
                    <div class="credit-item">
                        <dl>
                            <dt>用途</dt><dd><?= h($c['use'] ?? '') ?></dd>
                            <dt>曲名・素材名</dt><dd><?= credit_value((string)($c['title'] ?? '')) ?></dd>
                            <dt>作者・提供元</dt><dd><?= credit_value((string)($c['author'] ?? '')) ?></dd>
                            <dt>入手先</dt>
                            <dd>
                                <?php if ($url_ok): ?>
                                    <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer"><span data-no-i18n><?= h($site !== '' ? $site : $url) ?></span></a>
                                <?php else: ?>
                                    <?= credit_value($site) ?>
                                <?php endif; ?>
                            </dd>
                            <dt>利用条件・ライセンス</dt><dd><?= credit_value((string)($c['license'] ?? '')) ?></dd>
                        </dl>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </section>
    </main>

    <script>window.BGM_NAMES = <?= json_encode($bgm_names, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
    <script src="js/settings.js"></script>
</body>
</html>
