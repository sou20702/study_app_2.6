<?php
require_once 'config/db_config.php';

// 世界の状態（ポイント・進化レベル・各海域の生き物と背景）
$state = get_world_state($pdo);

// 未完了の課題数（個人課題 ＋ 自分が所属するグループの未完了課題）
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE is_completed = 0 AND group_id IS NULL AND owner_id = :o");
$stmt->execute([':o' => get_player_id($pdo)]);
$task_count = (int)$stmt->fetchColumn();
$my_member = get_my_member($pdo);
if ($my_member) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE t.group_id = :g
        AND NOT EXISTS (SELECT 1 FROM task_completions c WHERE c.task_id = t.id AND c.member_id = :m)");
    $stmt->execute([':g' => $my_member['group_id'], ':m' => $my_member['id']]);
    $task_count += (int)$stmt->fetchColumn();
}

// 最近のポイント獲得（歴史タブの先頭3件）
$stmt = $pdo->prepare("SELECT source, title, points, caption, created_at FROM point_history
                       WHERE player_id = :p ORDER BY id DESC LIMIT 3");
$stmt->execute([':p' => get_player_id($pdo)]);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

$state_json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
$start_area = (string)($_GET['area'] ?? '');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ホーム - 勉強大好きアプリ</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/world.css">
    <script src="js/i18n.js"></script>
</head>
<body>
    <?php require_once __DIR__ . '/header.php'; ?>

    <main class="wrap">
        <div class="status-grid">
            <div class="stat">
                <div class="stat-label">総獲得ポイント</div>
                <div class="stat-num"><?= h(format_points($state['total_points'])) ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">進化レベル</div>
                <div class="stat-num"><?= (int)$state['total_stage'] ?><span class="stat-unit"> / <?= (int)$state['max_stage'] ?></span></div>
            </div>
            <div class="stat">
                <div class="stat-label">次の進化まで<?= $state['next_area'] !== null ? '（' . h($state['next_area']) . '）' : '' ?></div>
                <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$state['progress_pct'] ?>">
                    <div class="bar" style="width: <?= (int)$state['progress_pct'] ?>%"></div>
                </div>
                <div class="stat-unit"><?= (int)$state['progress_pct'] ?>%</div>
            </div>
            <a class="stat stat-link" href="task_reward.php">
                <div class="stat-label">未完了の課題</div>
                <div class="stat-num"><?= $task_count ?><span class="stat-unit"> 件</span></div>
            </a>
        </div>

        <section class="panel world-panel" aria-label="海の世界">
            <div class="world-head">
                <h2 id="areaName">中央海域</h2>
                <span id="areaStage" class="note"></span>
            </div>

            <div class="world-stage" id="worldStage">
                <canvas id="worldCanvas" width="800" height="400" role="img" aria-label="海の様子"></canvas>
                <div class="world-empty" id="worldEmpty" hidden>まだ何もいません。勉強を重ねると生き物が増えていきます。</div>
                <button type="button" class="world-arrow up"    data-dx="0"  data-dy="-1" hidden></button>
                <button type="button" class="world-arrow down"  data-dx="0"  data-dy="1"  hidden></button>
                <button type="button" class="world-arrow left"  data-dx="-1" data-dy="0"  hidden></button>
                <button type="button" class="world-arrow right" data-dx="1"  data-dy="0"  hidden></button>
            </div>

            <div class="world-foot">
                <div class="minimap" id="minimap" aria-label="海域マップ"></div>
                <p class="note">デモ表示。</p>
            </div>
        </section>

        <section class="panel">
            <h3>最近の歴史</h3>
            <?php if (empty($recent)): ?>
                <p class="muted">まだ何もありません。</p>
            <?php else: ?>
                <?php foreach ($recent as $r): ?>
                    <div class="history-item">
                        <div class="history-date"><?= h($r['created_at']) ?>　<?= $r['source'] === 'timer' ? 'タイマー' : '課題' ?></div>
                        <div class="history-msg">+<?= h(format_points((float)$r['points'])) ?> ポイント　<?= h($r['title']) ?></div>
                        <?php if ($r['caption'] !== ''): ?><div class="note"><?= h($r['caption']) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a class="btn btn-sub" href="history.php">歴史をすべて見る</a>
        </section>
    </main>

    <script>
        window.WORLD_STATE = <?= $state_json ?>;
        window.WORLD_START_AREA = <?= json_encode($start_area, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    </script>
    <script src="js/world.js"></script>
</body>
</html>
