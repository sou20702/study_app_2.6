<?php
require_once __DIR__ . '/db/db.php';
$client_id = get_client_id();

// 送信履歴（自分が流したボトル）と、何人が拾ったか
$stmt = $pdo->prepare("
    SELECT m.id, m.message, m.canvas_data, m.created_at,
           (SELECT COUNT(*) FROM bottle_mail_receipts r WHERE r.mail_id = m.id) AS pickup_count
    FROM bottle_mails m
    WHERE m.sender_token = :t
    ORDER BY m.id DESC
    LIMIT 50
");
$stmt->execute([':t' => $client_id]);
$sent_mails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 送ったボトルについた、リアクションの数（ボトルごと・種類ごと）
$react_counts = [];
if ($sent_mails) {
    $ids = implode(',', array_map('intval', array_column($sent_mails, 'id')));
    $rs = $pdo->query("SELECT mail_id, reaction, COUNT(*) AS c FROM bottle_mail_receipts
                       WHERE mail_id IN ($ids) AND reaction IS NOT NULL GROUP BY mail_id, reaction");
    foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $react_counts[(int)$row['mail_id']][$row['reaction']] = (int)$row['c'];
    }
}

// 受信履歴（自分が拾ったボトル）
$stmt = $pdo->prepare("
    SELECT m.id, m.message, m.canvas_data, r.received_at, r.reaction
    FROM bottle_mail_receipts r
    JOIN bottle_mails m ON m.id = r.mail_id
    WHERE r.receiver_token = :t
    ORDER BY r.id DESC
    LIMIT 50
");
$stmt->execute([':t' => $client_id]);
$received_mails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 送信履歴：このボトルについたリアクション（ナイス！ ×2 など）
function reaction_chips_html(array $counts): string {
    $out = '';
    foreach (MAIL_REACTIONS as $key => $label) {
        if (!empty($counts[$key])) {
            $out .= '<span class="react-chip"><span>' . h($label) . '</span><span class="react-n">×' . (int)$counts[$key] . '</span></span>';
        }
    }
    return $out === '' ? '' : '<div class="history-react"><span class="muted">リアクション</span> ' . $out . '</div>';
}
// 受信履歴：自分が送ったリアクション
function my_reaction_html($key): string {
    if (!is_string($key) || !isset(MAIL_REACTIONS[$key])) return '';
    return '<div class="history-react"><span class="muted">あなたのリアクション</span> <span class="react-chip"><span>' . h(MAIL_REACTIONS[$key]) . '</span></span></div>';
}

function render_mail_item(array $m, string $date, string $meta = '', string $extra_html = '', ?int $mail_id = null): void {
    echo '<div class="history-item"' . ($mail_id !== null ? ' data-mail-id="' . $mail_id . '"' : '') . '>';
    echo '<div class="history-date">' . h($date) . ($meta !== '' ? ' ／ ' . h($meta) : '') . '</div>';
    if ($m['message'] === '' || $m['message'] === null) {
        echo '<div class="history-msg"><span class="muted">（メッセージなし）</span></div>';
    } else {
        echo '<div class="history-msg" data-no-i18n>' . nl2br(h($m['message'])) . '</div>';
    }
    if (!empty($m['canvas_data']) && strpos($m['canvas_data'], 'data:image/') === 0) {
        echo '<img class="history-img" alt="お絵描き" src="' . h($m['canvas_data']) . '">';
    }
    echo $extra_html;
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ボトルメール</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/bottle_mail.css">
    <script src="js/i18n.js"></script>
</head>
<body>

    <?php require_once __DIR__ . '/header.php'; ?>

    <main class="wrap">

        <h1>ボトルメール</h1>

        <!-- ================= 送信 ================= -->
        <section class="panel">
            <h3>ボトルを流す</h3>
            <p class="note" style="margin-top:0;">メッセージを書いて海へ流そう。海を漂流して、だれかもとへたどり着くかもしれません。</p>

            <form id="mailForm">
                <div class="field">
                    <label for="message">メッセージ</label>
                    <textarea id="message" name="message" rows="5" maxlength="1000" placeholder="ここにメッセージを書いてください"></textarea>
                </div>

                <div class="field canvas-wrap">
                    <label for="mailCanvas">お絵描き</label>
                    <canvas id="mailCanvas" width="400" height="250"></canvas>
                </div>

                <div class="btn-row">
                    <button type="button" id="clearCanvas" class="btn btn-sub btn-lg">消す</button>
                    <button type="submit" id="sendBtn" class="btn btn-lg">海に流す</button>
                </div>
            </form>
        </section>

        <!-- ================= 受信 ================= -->
        <section class="panel">
            <h3>ボトルを拾う</h3>
            <button type="button" id="pickupBtn" class="btn btn-lg">ボトルを拾う</button>

            <div id="openedBox" class="history-item opened" hidden style="margin-top:14px;">
                <div class="section-label" style="margin-top:0;">メッセージ</div>
                <div class="history-msg" id="openedMsg"></div>
                <div id="openedImgWrap"></div>
                <div class="section-label">リアクション</div>
                <div class="btn-row" style="margin-top:0;">
                    <button type="button" class="btn btn-sub js-react" data-k="nice" data-r="ナイス！" aria-pressed="false">ナイス！</button>
                    <button type="button" class="btn btn-sub js-react" data-k="cheer" data-r="がんばれ！" aria-pressed="false">がんばれ！</button>
                    <button type="button" class="btn btn-sub js-react" data-k="like" data-r="いいね！" aria-pressed="false">いいね！</button>
                </div>
            </div>
        </section>

        <!-- ================= 履歴 ================= -->
        <section class="panel" id="history">
            <h3>送受信履歴</h3>

            <div class="view-tabs">
                <button type="button" class="active" data-target="panel-sent">送信（<span id="sentCount"><?= count($sent_mails) ?></span>）</button>
                <button type="button" data-target="panel-received">受信（<span id="recvCount"><?= count($received_mails) ?></span>）</button>
            </div>

            <div id="panel-sent" class="history-panel active">
                <p class="history-empty" id="sentEmpty" <?= empty($sent_mails) ? '' : 'hidden' ?>>まだボトルを流していません。</p>
                <div id="sentList">
                    <?php foreach ($sent_mails as $m): ?>
                        <?php render_mail_item($m, $m['created_at'], $m['pickup_count'] . '人が拾いました', reaction_chips_html($react_counts[(int)$m['id']] ?? [])); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="panel-received" class="history-panel">
                <p class="history-empty" id="recvEmpty" <?= empty($received_mails) ? '' : 'hidden' ?>>まだボトルを拾っていません。</p>
                <div id="recvList">
                    <?php foreach ($received_mails as $m): ?>
                        <?php render_mail_item($m, $m['received_at'], '', my_reaction_html($m['reaction']), (int)$m['id']); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

    </main>

    <script src="js/draw.js"></script>
    <script>
        (function () {
            const $ = (id) => document.getElementById(id);

            // 履歴カードを作る（表示は textContent で行い、本文は安全に扱う）
            function mailItem(m, date, meta) {
                const item = document.createElement('div');
                item.className = 'history-item';
                const d = document.createElement('div');
                d.className = 'history-date';
                d.textContent = date + (meta ? ' ／ ' + meta : '');
                const msg = document.createElement('div');
                msg.className = 'history-msg';
                if (m.message) {
                    msg.setAttribute('data-no-i18n', '');
                    m.message.split('\n').forEach(function (line, i) {
                        if (i) msg.appendChild(document.createElement('br'));
                        msg.appendChild(document.createTextNode(line));
                    });
                } else {
                    const s = document.createElement('span'); s.className = 'muted'; s.textContent = '（メッセージなし）';
                    msg.appendChild(s);
                }
                if (m.id) item.dataset.mailId = String(m.id);
                item.appendChild(d); item.appendChild(msg);
                if (m.canvas_data && m.canvas_data.indexOf('data:image/') === 0) {
                    const img = document.createElement('img');
                    img.className = 'history-img'; img.alt = 'お絵描き'; img.src = m.canvas_data;
                    item.appendChild(img);
                }
                return item;
            }
// 履歴カードに「あなたのリアクション」を表示（あれば置き換え）
            function setMyReaction(item, label) {
                let el = item.querySelector('.history-react');
                if (!el) { el = document.createElement('div'); el.className = 'history-react'; item.appendChild(el); }
                el.textContent = '';
                const cap = document.createElement('span'); cap.className = 'muted'; cap.textContent = 'あなたのリアクション';
                const chip = document.createElement('span'); chip.className = 'react-chip';
                const t = document.createElement('span'); t.textContent = label;
                chip.appendChild(t);
                el.appendChild(cap); el.appendChild(document.createTextNode(' ')); el.appendChild(chip);
            }
            // 選んでいるリアクションのボタンを目立たせる（key が null なら全部解除）
            function markReact(key) {
                document.querySelectorAll('.js-react').forEach(function (b) {
                    const on = b.dataset.k === key;
                    b.classList.toggle('selected', on);
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
            }
            // 絵が描かれているか（透明でない点が1つでもあるか）
            function canvasHasInk() {
                const c = $('mailCanvas');
                const d = c.getContext('2d').getImageData(0, 0, c.width, c.height).data;
                for (let i = 3; i < d.length; i += 4) { if (d[i] !== 0) return true; }
                return false;
            }
            let openedId = null;                  // いま開いているボトルの id（リアクションの送り先）
            const LABELS = { nice: 'ナイス！', cheer: 'がんばれ！', like: 'いいね！' };

            function bump(countId, emptyId, listId, item) {
                $(listId).prepend(item);
                $(countId).textContent = String(parseInt($(countId).textContent, 10) + 1);
                $(emptyId).hidden = true;
            }

            // ---------- 送信（画面を移動しない） ----------
            $('mailForm').addEventListener('submit', async function (e) {
                e.preventDefault();
                const btn = $('sendBtn');
                // メッセージも絵も無い「空のボトル」は流せない（どちらか一方があればOK）
                const hasText = $('message').value.trim() !== '';
                const hasInk = canvasHasInk();
                if (!hasText && !hasInk) { App.toast('メッセージを書くか、絵を描いてから流してください。', 'error'); return; }
                btn.disabled = true;
                const r = await App.post('db/save_mail.php', {
                    message: $('message').value,
                    drawing: hasInk ? $('mailCanvas').toDataURL() : ''      // 絵が無いときは、真っ白な画像を送らない
                });
                btn.disabled = false;
                if (!r.ok) { App.toast(r.message || '送信できませんでした', 'error'); return; }
                bump('sentCount', 'sentEmpty', 'sentList', mailItem(r.mail, r.mail.created_at, '0人が拾いました'));
                $('message').value = '';
                $('clearCanvas').click();
                if (window.Sound) Sound.enqueue('mail_send');      // メール送信音
                App.toast('ボトルメールを海へ流しました', 'success');
            });

            // ---------- 受信（画面を移動しない） ----------
            $('pickupBtn').addEventListener('click', async function () {
                const btn = $('pickupBtn');
                btn.disabled = true;
                const r = await App.post('db/pickup_mail.php', {});
                btn.disabled = false;
                if (!r.ok) { App.toast(r.message || '拾えませんでした', 'error'); return; }
                if (!r.mail) { App.toast(r.message || 'まだボトルが流れていません。'); return; }
                const m = r.mail;
                $('openedMsg').textContent = '';
                if (m.message) {
                    $('openedMsg').setAttribute('data-no-i18n', '');
                    m.message.split('\n').forEach(function (line, i) {
                        if (i) $('openedMsg').appendChild(document.createElement('br'));
                        $('openedMsg').appendChild(document.createTextNode(line));
                    });
                } else {
                    $('openedMsg').removeAttribute('data-no-i18n');
                    $('openedMsg').textContent = '（メッセージなし）';
                }
                $('openedImgWrap').textContent = '';
                if (m.canvas_data && m.canvas_data.indexOf('data:image/') === 0) {
                    const lab = document.createElement('div'); lab.className = 'section-label'; lab.textContent = 'お絵描き';
                    const img = document.createElement('img'); img.className = 'history-img'; img.alt = 'お絵描き'; img.src = m.canvas_data;
                    $('openedImgWrap').appendChild(lab); $('openedImgWrap').appendChild(img);
                }
                $('openedBox').hidden = false;
                openedId = m.id || null;
                markReact(m.reaction || null);
                const known = openedId ? document.querySelector('#recvList [data-mail-id="' + openedId + '"]') : null;
                if (!(m.already && known)) {                        // 以前に拾ったボトルは、履歴に重複して足さない
                    const item = mailItem(m, m.created_at, '');
                    if (m.reaction && LABELS[m.reaction]) setMyReaction(item, LABELS[m.reaction]);
                    bump('recvCount', 'recvEmpty', 'recvList', item);
                }
                if (window.Sound) Sound.enqueue('mail_open');      // メール開封音
                App.toast('ボトルを開封しました', 'success');
            });

            document.querySelectorAll('.js-react').forEach(function (b) {
                b.addEventListener('click', async function () {
                    if (!openedId) return;
                    document.querySelectorAll('.js-react').forEach(function (x) { x.disabled = true; });
                    const r = await App.post('db/react_mail.php', { mail_id: openedId, reaction: b.dataset.k });
                    document.querySelectorAll('.js-react').forEach(function (x) { x.disabled = false; });
                    if (!r.ok) { App.toast(r.message || 'リアクションを送れませんでした。', 'error'); return; }   // 保存できたときだけ「送りました」を出す
                    markReact(b.dataset.k);
                    const it = document.querySelector('#recvList [data-mail-id="' + openedId + '"]');
                    if (it) setMyReaction(it, b.dataset.r);
                    App.toast(b.dataset.r + ' を送りました', 'success');
                });
            });

            // 送信／受信タブ切り替え
            document.querySelectorAll('.view-tabs button').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.view-tabs button').forEach(function (b) { b.classList.remove('active'); });
                    document.querySelectorAll('.history-panel').forEach(function (p) { p.classList.remove('active'); });
                    btn.classList.add('active');
                    $(btn.dataset.target).classList.add('active');
                });
            });
        })();
    </script>
</body>
</html>
