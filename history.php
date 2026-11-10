<?php
require_once 'config/db_config.php';

$pid = get_player_id($pdo);
$stmt = $pdo->prepare("SELECT id, source, title, subject, amount, points, stage_before, stage_after, caption, created_at
                       FROM point_history WHERE player_id = :p ORDER BY id DESC LIMIT 200");
$stmt->execute([':p' => $pid]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_points = get_total_points($pdo);
$state = get_world_state($pdo);
$timer_count = count(array_filter($entries, fn($e) => $e['source'] === 'timer'));
$task_count  = count($entries) - $timer_count;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>歴史 - 勉強大好きアプリ</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/history.css">
    <script src="js/i18n.js"></script>
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <main class="wrap">
        <h1>歴史</h1>
        <p class="note">ポイントを獲得するたびに記録されます。キャプションを追加して、取り組んだ内容を書き足してみよう。</p>

        <div class="history-summary">
            <div class="stat"><div class="stat-label">総獲得ポイント</div><div class="stat-num"><?= h(format_points($total_points)) ?></div></div>
            <div class="stat"><div class="stat-label">進化した回数</div><div class="stat-num"><?= (int)$state['total_stage'] ?><span class="stat-unit"> 回</span></div></div>
            <div class="stat"><div class="stat-label">記録件数</div><div class="stat-num"><?= count($entries) ?><span class="stat-unit"> 件</span></div></div>
        </div>

        <div class="view-tabs" id="filterTabs">
            <button type="button" class="active" data-filter="all">すべて（<?= count($entries) ?>）</button>
            <button type="button" data-filter="timer">タイマー（<?= $timer_count ?>）</button>
            <button type="button" data-filter="task">課題（<?= $task_count ?>）</button>
        </div>

        <section id="historyList">
            <?php if (empty($entries)): ?>
                <div class="panel"><p class="muted" style="margin:0;">まだポイントを獲得していません。タイマーを回すか、課題を完了するとここに記録されます。</p></div>
            <?php endif; ?>

            <?php foreach ($entries as $e): ?>
                <?php $is_timer = $e['source'] === 'timer'; ?>
                <article class="history-item" data-source="<?= h($e['source']) ?>" data-id="<?= (int)$e['id'] ?>">
                    <div class="history-date">
                        <?= h($e['created_at']) ?>　<?= $is_timer ? 'タイマー' : '課題' ?><?= $e['subject'] ? '　' . h($e['subject']) : '' ?>
                    </div>
                    <div class="history-main">
                        <span class="hp">+<?= h(format_points((float)$e['points'])) ?> ポイント</span>
                        <span class="ht"<?= $is_timer ? '' : ' data-no-i18n' ?>><?= h($e['title']) ?></span>
                    </div>
                    <div class="note">
                        <?php if ($is_timer): ?>
                            勉強と休憩の <?= (int)$e['amount'] ?> 分ぶん
                        <?php else: ?>
                            算出スコア <?= (int)$e['amount'] ?> p → <?= h(format_points((float)$e['points'])) ?> ポイントに換算
                        <?php endif; ?>
                    </div>
                    <?php if ((int)$e['stage_after'] > (int)$e['stage_before']): ?>
                        <div class="stage-up">新たな生物が誕生しました（<?= (int)$e['stage_after'] - (int)$e['stage_before'] ?> 匹）</div>
                    <?php endif; ?>

                    <div class="caption-view">
                        <span class="caption-text<?= $e['caption'] === '' ? ' muted' : '' ?>"<?= $e['caption'] === '' ? '' : ' data-no-i18n' ?>><?= $e['caption'] === '' ? 'キャプションなし' : h($e['caption']) ?></span>
                        <button type="button" class="btn btn-sub js-cap-edit"><?= $e['caption'] === '' ? 'キャプションを書く' : '編集' ?></button>
                    </div>
                    <div class="caption-edit" hidden>
                        <input type="text" maxlength="<?= CAPTION_MAX_LENGTH ?>" value="<?= h($e['caption']) ?>" placeholder="例：数学の復習をがんばった" aria-label="キャプション">
                        <button type="button" class="btn js-cap-save">保存</button>
                        <button type="button" class="btn btn-sub js-cap-cancel">やめる</button>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
        <?php if (count($entries) >= 200): ?><p class="note">新しい200件を表示しています。</p><?php endif; ?>
    </main>

    <script>
        (function () {
            const list = document.getElementById('historyList');

            // 絞り込み（すべて／タイマー／課題）
            document.getElementById('filterTabs').addEventListener('click', (e) => {
                const b = e.target.closest('button');
                if (!b) return;
                document.querySelectorAll('#filterTabs button').forEach(x => x.classList.toggle('active', x === b));
                list.querySelectorAll('.history-item').forEach(it => {
                    it.hidden = b.dataset.filter !== 'all' && it.dataset.source !== b.dataset.filter;
                });
            });

            // キャプションの編集（ページを移動せず、その場で保存）
            function setEditing(item, on) {
                item.querySelector('.caption-view').hidden = on;
                item.querySelector('.caption-edit').hidden = !on;
                if (on) { const i = item.querySelector('.caption-edit input'); i.focus(); i.setSelectionRange(i.value.length, i.value.length); }
            }
            async function save(item) {
                const input = item.querySelector('.caption-edit input');
                const text = input.value.trim();
                const r = await App.saveCaption(item.dataset.id, text);
                if (!r.ok) return;
                const span = item.querySelector('.caption-text');
                span.textContent = text === '' ? 'キャプションなし' : text;
                span.classList.toggle('muted', text === '');
                item.querySelector('.js-cap-edit').textContent = text === '' ? 'キャプションを書く' : '編集';
                setEditing(item, false);
            }
            list.addEventListener('click', (e) => {
                const item = e.target.closest('.history-item');
                if (!item) return;
                if (e.target.closest('.js-cap-edit')) setEditing(item, true);
                else if (e.target.closest('.js-cap-cancel')) { item.querySelector('.caption-edit input').value = item.querySelector('.caption-text').classList.contains('muted') ? '' : item.querySelector('.caption-text').textContent; setEditing(item, false); }
                else if (e.target.closest('.js-cap-save')) save(item);
            });
            list.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter' || !e.target.matches('.caption-edit input')) return;
                e.preventDefault();
                save(e.target.closest('.history-item'));
            });
        })();
    </script>
</body>
</html>
