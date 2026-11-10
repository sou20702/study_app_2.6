<?php
require_once 'config/db_config.php';
session_start();
get_client_id(); // 端末IDのCookieを出力前に発行
$player_id = get_player_id($pdo); // 個人課題の持ち主

$subjects       = get_subjects();            // key => 表示名
$subject_labels = array_values($subjects);
$self           = 'task_reward.php';

$me = get_my_member($pdo);                   // 自分のグループ所属（なければ null）
$is_host = $me && (int)$me['host_member_id'] === (int)$me['id'];
$is_ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'; // 画面遷移なしで完了処理を行う通信
$ajax_result = null;

function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function go(string $view = 'personal'): void {
    header('Location: task_reward.php' . ($view === 'group' ? '?view=group' : ''));
    exit;
}

// ==========================================================
// POST処理（処理後はリダイレクトして二重送信を防ぐ）
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $post_view = (($_POST['view'] ?? '') === 'group' && $me) ? 'group' : 'personal';
    $next_view = $post_view;

    try {
        switch ($action) {
            // ---------- グループ ----------
            case 'create_group':
                [$ok, $msg] = create_group($pdo, (string)($_POST['user_name'] ?? ''), (string)($_POST['group_name'] ?? ''));
                flash($ok ? 'success' : 'error', $msg);
                $next_view = $ok ? 'group' : 'personal';
                break;

            case 'join_group':
                [$ok, $msg] = join_group($pdo, (string)($_POST['code'] ?? ''), (string)($_POST['user_name'] ?? ''));
                flash($ok ? 'success' : 'error', $msg);
                $next_view = $ok ? 'group' : 'personal';
                break;

            case 'kick_member':
                [$ok, $msg] = kick_member($pdo, $me, (int)($_POST['member_id'] ?? 0));
                flash($ok ? 'success' : 'error', $msg);
                $next_view = 'group';
                break;

            case 'leave_group':
                [$ok, $msg] = leave_group($pdo, $me);
                flash($ok ? 'success' : 'error', $msg);
                $next_view = $ok ? 'personal' : 'group';
                break;

            // ---------- 課題の追加 ----------
            case 'add_task':
                [$data, $err] = parse_task_input($_POST, $subject_labels);
                if ($err) {
                    flash('error', $err);
                    break;
                }
                $to_group = (($_POST['scope'] ?? '') === 'group') && $me;
                $stmt = $pdo->prepare("INSERT INTO tasks (title, subject, deadline, required_time, difficulty, is_completed, group_id, created_by, owner_id)
                                       VALUES (:title, :subject, :deadline, :required_time, :difficulty, 0, :group_id, :created_by, :owner_id)");
                $stmt->execute([
                    ':title' => $data['title'], ':subject' => $data['subject'], ':deadline' => $data['deadline'],
                    ':required_time' => $data['required_time'], ':difficulty' => $data['difficulty'],
                    ':group_id'   => $to_group ? $me['group_id'] : null,
                    ':created_by' => $me ? $me['id'] : null,
                    ':owner_id'   => $player_id,
                ]);
                flash('success', $to_group ? '【成功】グループに新しい課題を登録しました！' : '【成功】新しい課題を登録しました！');
                $next_view = $to_group ? 'group' : 'personal';
                break;

            // ---------- 課題の変更 ----------
            case 'update_task':
            case 'delete_task':
                $task_id = (int)($_POST['task_id'] ?? 0);
                $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id");
                $stmt->execute([':id' => $task_id]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$task) {
                    flash('error', '対象の課題が見つかりません。');
                    break;
                }
                if (!can_modify_task($task, $me, $player_id)) {
                    flash('error', 'この課題を変更・削除できるのは、登録した本人かグループホストだけです。');
                    break;
                }
                $next_view = $task['group_id'] === null ? 'personal' : 'group';

                if ($action === 'delete_task') {
                    $pdo->beginTransaction();
                    $pdo->prepare("DELETE FROM task_completions WHERE task_id = :id")->execute([':id' => $task_id]);
                    $pdo->prepare("DELETE FROM tasks WHERE id = :id")->execute([':id' => $task_id]);
                    $pdo->commit();
                    flash('success', '課題を削除しました。');
                } else {
                    // 既存課題が旧科目（例：理系）の場合は、その科目のままでも保存できる
                    $allowed = $subject_labels;
                    if (!in_array($task['subject'], $allowed, true)) {
                        $allowed[] = $task['subject'];
                    }
                    [$data, $err] = parse_task_input($_POST, $allowed);
                    if ($err) {
                        flash('error', $err);
                        break;
                    }
                    $stmt = $pdo->prepare("UPDATE tasks SET title = :title, subject = :subject, deadline = :deadline,
                                           required_time = :required_time, difficulty = :difficulty WHERE id = :id");
                    $stmt->execute([
                        ':title' => $data['title'], ':subject' => $data['subject'], ':deadline' => $data['deadline'],
                        ':required_time' => $data['required_time'], ':difficulty' => $data['difficulty'], ':id' => $task_id,
                    ]);
                    flash('success', '課題を更新しました。');
                }
                break;

            // ---------- 課題の完了（ポイント付与） ----------
            case 'complete_task':
                $task_id = (int)($_POST['task_id'] ?? 0);
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id");
                $stmt->execute([':id' => $task_id]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);
                $done = false;

                if ($task && $task['group_id'] === null && (int)$task['owner_id'] === $player_id) {
                    // 個人課題（自分のものだけ）
                    $upd = $pdo->prepare("UPDATE tasks SET is_completed = 1 WHERE id = :id AND is_completed = 0");
                    $upd->execute([':id' => $task_id]);
                    $done = $upd->rowCount() === 1;
                    $next_view = 'personal';
                } elseif ($task && $me && (int)$task['group_id'] === (int)$me['group_id']) {
                    // グループ課題：自分の分だけ完了にする
                    $ins = $pdo->prepare("INSERT IGNORE INTO task_completions (task_id, member_id) VALUES (:t, :m)");
                    $ins->execute([':t' => $task_id, ':m' => $me['id']]);
                    $done = $ins->rowCount() === 1;
                    $next_view = 'group';
                }

                if ($done) {
                    $award = award_task_completion($pdo, $task); // 算出スコアに応じたポイントを歴史に記録
                    $msg = "課題「{$task['title']}」を完了しました。+{$award['points_text']} ポイント";
                    flash('success', $msg);
                    $ajax_result = ['ok' => true, 'message' => $msg, 'award' => $award];
                } else {
                    $msg = 'この課題はすでに完了しているか、完了できない課題です。';
                    flash('error', $msg);
                    $ajax_result = ['ok' => false, 'message' => $msg];
                }
                $pdo->commit();
                break;
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'エラーが発生しました: ' . $e->getMessage());
        $ajax_result = ['ok' => false, 'message' => 'エラーが発生しました: ' . $e->getMessage()];
    }
    if ($is_ajax) {
        unset($_SESSION['flash']); // 画面遷移しないので、次の表示に持ち越さない
        json_out($ajax_result ?? ['ok' => false, 'message' => '処理できませんでした。']);
    }
    go($next_view);
}

// ==========================================================
// 表示用データ
// ==========================================================
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$view = (($_GET['view'] ?? '') === 'group' && $me) ? 'group' : 'personal';

$members = $me ? get_group_members($pdo, (int)$me['group_id']) : [];

if ($view === 'group') {
    $stmt = $pdo->prepare("
        SELECT t.*, cm.name AS creator_name,
               (SELECT COUNT(*) FROM task_completions c WHERE c.task_id = t.id) AS done_count
        FROM tasks t
        LEFT JOIN group_members cm ON cm.id = t.created_by
        WHERE t.group_id = :gid
          AND NOT EXISTS (SELECT 1 FROM task_completions c2 WHERE c2.task_id = t.id AND c2.member_id = :mid)
    ");
    $stmt->execute([':gid' => $me['group_id'], ':mid' => $me['id']]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE is_completed = 0 AND group_id IS NULL AND owner_id = :o");
    $stmt->execute([':o' => $player_id]);
}
$tasks = enrich_and_sort_tasks($stmt->fetchAll(PDO::FETCH_ASSOC));

// 登録フォームの初期値（編集は画面内で切り替える：js 側）
$f = ['title' => '', 'subject' => $subject_labels[0], 'deadline' => '', 'required_time' => '', 'difficulty' => 1];
$colspan = $view === 'group' ? 10 : 8;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>課題</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/tasks.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.js"></script>
    <script src="js/i18n.js"></script>
</head>
<body>

    <?php require_once __DIR__ . '/header.php'; ?>

    <main class="wrap">

    <h1>課題</h1>

    <?php if ($flash): ?>
        <div class="flash <?= h($flash['type']) ?>" role="status"><?= h($flash['msg']) ?></div>
    <?php endif; ?>

    <!-- ================= グループ ================= -->
    <section class="panel">
        <h3>グループ共有</h3>
        <?php if (!$me): ?>
            <p style="margin-top:0;">グループを作成するか、グループコードで参加すると、課題をメンバーと共有できます。</p>
            <div class="group-menu">
                <form method="POST" action="<?= $self ?>">
                    <input type="hidden" name="action" value="create_group">
                    <h4>グループを作成</h4>
                    <div class="field">
                        <label>あなたのユーザー名（ホストになります）</label>
                        <input type="text" name="user_name" maxlength="20" required placeholder="例：たろう">
                    </div>
                    <div class="field">
                        <label>グループ名（省略可）</label>
                        <input type="text" name="group_name" maxlength="30" placeholder="例：3年A組 受験勉強">
                    </div>
                    <button type="submit" class="btn">作成してコードを発行</button>
                </form>

                <form method="POST" action="<?= $self ?>">
                    <input type="hidden" name="action" value="join_group">
                    <h4>グループに参加</h4>
                    <div class="field">
                        <label>グループコード（合言葉）</label>
                        <input type="text" name="code" maxlength="12" required placeholder="例：AB3XK7" style="text-transform: uppercase;">
                    </div>
                    <div class="field">
                        <label>あなたのユーザー名</label>
                        <input type="text" name="user_name" maxlength="20" required placeholder="例：はなこ">
                    </div>
                    <button type="submit" class="btn">参加する</button>
                </form>
            </div>
        <?php else: ?>
            <p style="margin-top:0;">
                <strong><?= h($me['group_name']) ?></strong>
                <span class="badge <?= $is_host ? 'host' : '' ?>"><?= $is_host ? 'ホスト' : 'メンバー' ?></span>
                　あなたの名前: <strong><?= h($me['name']) ?></strong>
            </p>
            <p>
                グループコード:
                <span class="code-box">
                    <span class="code" id="groupCode"><?= h($me['code']) ?></span>
                    <button type="button" class="btn btn-sub" onclick="copyGroupCode(this)">コピー</button>
                </span>
            </p>

            <strong>メンバー（<?= count($members) ?>人）</strong>
            <ul class="member-list">
                <?php foreach ($members as $m): ?>
                    <?php $m_is_host = (int)$m['id'] === (int)$me['host_member_id']; ?>
                    <li>
                        <span>
                            <span data-no-i18n><?= h($m['name']) ?></span>
                            <?php if ($m_is_host): ?><span class="badge host">ホスト</span><?php endif; ?>
                            <?php if ((int)$m['id'] === (int)$me['id']): ?><span class="badge">あなた</span><?php endif; ?>
                        </span>
                        <?php if ($is_host && !$m_is_host): ?>
                            <form method="POST" action="<?= $self ?>" style="margin:0;"
                                  data-confirm="<?= h($m['name']) ?> さんをグループから削除しますか？" data-confirm-ok="削除する">
                                <input type="hidden" name="action" value="kick_member">
                                <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
                                <input type="hidden" name="view" value="group">
                                <button type="submit" class="btn btn-danger">削除</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form method="POST" action="<?= $self ?>" style="margin-top:14px;"
                  data-confirm="<?= $is_host ? 'グループを解散すると、グループの課題もすべて削除されます。よろしいですか？' : 'このグループから退出しますか？' ?>"
                  data-confirm-ok="<?= $is_host ? '解散する' : '退出する' ?>">
                <input type="hidden" name="action" value="leave_group">
                <button type="submit" class="btn btn-sub"><?= $is_host ? 'グループを解散する' : 'グループから退出する' ?></button>
            </form>
        <?php endif; ?>
    </section>

    <!-- ================= 表示切替 ================= -->
    <?php if ($me): ?>
        <div class="view-tabs">
            <a href="<?= $self ?>" class="<?= $view === 'personal' ? 'active' : '' ?>">個人の課題</a>
            <a href="<?= $self ?>?view=group" class="<?= $view === 'group' ? 'active' : '' ?>">グループの課題（<?= h($me['group_name']) ?>）</a>
        </div>
    <?php endif; ?>

    <!-- ================= 登録・編集フォーム（編集も画面内で切り替わる） ================= -->
    <section class="panel form-card" id="formCard">
        <h2 id="formTitle">新規課題を登録</h2>
        <form action="<?= $self ?>" method="POST" id="taskForm">
            <input type="hidden" name="action" id="fAction" value="add_task">
            <input type="hidden" name="view" value="<?= $view ?>">
            <input type="hidden" name="task_id" id="fTaskId" value="" disabled>

            <div class="field">
                <label for="fTitle">課題名</label>
                <input type="text" id="fTitle" name="title" required maxlength="255" placeholder="例：数学プリント P.24" value="<?= h($f['title']) ?>">
            </div>
            <div class="field">
                <label for="fSubject">科目</label>
                <select id="fSubject" name="subject" required>
                    <?php foreach ($subject_labels as $label): ?>
                        <option value="<?= h($label) ?>"><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="fDeadline">提出期限</label>
                <input type="date" id="fDeadline" name="deadline" required value="<?= h($f['deadline']) ?>">
            </div>
            <div class="field">
                <label for="fTime">所要時間 (分)</label>
                <input type="number" id="fTime" name="required_time" min="1" required placeholder="例：30" value="<?= h($f['required_time']) ?>">
            </div>
            <div class="field">
                <label>難易度</label>
                <div class="radio-row">
                    <?php foreach ([1 => '簡単', 2 => '普通', 3 => '難しい'] as $d => $dl): ?>
                        <label><input type="radio" name="difficulty" value="<?= $d ?>" <?= (int)$f['difficulty'] === $d ? 'checked' : '' ?>><?= $d ?>: <?= $dl ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($me): ?>
                <div class="field" id="scopeField">
                    <label>登録先</label>
                    <div class="radio-row">
                        <label><input type="radio" name="scope" value="personal" <?= $view === 'personal' ? 'checked' : '' ?>>個人</label>
                        <label><input type="radio" name="scope" value="group" <?= $view === 'group' ? 'checked' : '' ?>>グループ（<?= h($me['group_name']) ?>）</label>
                    </div>
                </div>
            <?php endif; ?>

            <div class="btn-row">
                <button type="submit" class="btn" id="fSubmit">課題を登録する</button>
                <button type="button" class="btn btn-sub" id="fCancel" hidden>編集をやめる</button>
            </div>
        </form>
    </section>

    <!-- ================= 課題一覧 ================= -->
    <h2><?= $view === 'group' ? 'グループの課題一覧（あなたの未完了分）' : '課題一覧' ?></h2>
    <p class="note">
        デフォルトでは「提出期限が近い順」が設定されています。期日が同じ場合は「算出スコアが高い順」に並びます。
    </p>
    <p class="note">
        課題を達成すると、算出スコアと同量のスコアを獲得できます。（100スコア = 1ポイント）
    </p>


    <div class="options-bar">
        <label for="sortSelect">並び替え（オプション）</label>
        <select id="sortSelect">
            <option value="rank">優先順位順（デフォルト）</option>
            <option value="score_desc">算出スコア：高い順</option>
            <option value="score_asc">算出スコア：低い順</option>
            <option value="time_asc">所要時間：短い順</option>
            <option value="time_desc">所要時間：長い順</option>
            <option value="diff_asc">難易度：低い順</option>
            <option value="diff_desc">難易度：高い順</option>
        </select>
    </div>

    <div class="table-scroll">
    <table class="tasks" id="taskTable">
        <thead>
            <tr>
                <th>優先順位</th>
                <th>課題名</th>
                <th>科目</th>
                <th>提出期限</th>
                <th>所要時間</th>
                <th>難易度</th>
                <th>算出スコア</th>
                <?php if ($view === 'group'): ?><th>登録者</th><th>完了状況</th><?php endif; ?>
                <th>アクション</th>
            </tr>
        </thead>
        <tbody>
            <tr id="emptyRow" <?= empty($tasks) ? '' : 'hidden' ?>>
                <td colspan="<?= $colspan ?>" class="muted" style="text-align:center;">未完了の課題はありません</td>
            </tr>
            <?php foreach ($tasks as $task): ?>
                <?php $can_mod = can_modify_task($task, $me, $player_id); ?>
                <tr data-id="<?= (int)$task['id'] ?>"
                    data-rank="<?= (int)$task['priority_rank'] ?>"
                    data-title="<?= h($task['title']) ?>"
                    data-subject="<?= h($task['subject']) ?>"
                    data-deadline="<?= h($task['deadline']) ?>"
                    data-time="<?= (int)$task['required_time'] ?>"
                    data-diff="<?= (int)$task['difficulty'] ?>"
                    data-score="<?= h($task['priority_score']) ?>">
                    <td>No.<?= (int)$task['priority_rank'] ?></td>
                    <td>
                        <span data-no-i18n><?= h($task['title']) ?></span>
                        <?php if ($task['days_left'] < 0): ?>
                            <span class="deadline-tag over">期限超過</span>
                        <?php elseif ($task['days_left'] === 0): ?>
                            <span class="deadline-tag over">本日締切</span>
                        <?php elseif ($task['days_left'] <= 3): ?>
                            <span class="deadline-tag">締切間近</span>
                        <?php endif; ?>
                    </td>
                    <td><?= h($task['subject']) ?></td>
                    <td><?= h($task['deadline']) ?></td>
                    <td><?= h($task['required_time']) ?> 分</td>
                    <td>レベル <?= h($task['difficulty']) ?></td>
                    <td class="score-cell"><?= h(format_points((float)$task['priority_score'])) ?> p</td>
                    <?php if ($view === 'group'): ?>
                        <td><?= $task['creator_name'] !== null ? '<span data-no-i18n>' . h($task['creator_name']) . '</span>' : '（退会済み）' ?></td>
                        <td><?= (int)$task['done_count'] ?> / <?= count($members) ?> 人</td>
                    <?php endif; ?>
                    <td>
                        <div class="actions">
                            <form action="<?= $self ?>" method="POST" class="js-complete">
                                <input type="hidden" name="action" value="complete_task">
                                <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                <input type="hidden" name="view" value="<?= $view ?>">
                                <button type="submit" class="btn">完了</button>
                            </form>
                            <?php if ($can_mod): ?>
                                <button type="button" class="btn btn-sub js-edit">編集</button>
                                <form action="<?= $self ?>" method="POST" data-confirm="この課題を削除しますか？" data-confirm-ok="削除する">
                                    <input type="hidden" name="action" value="delete_task">
                                    <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                    <input type="hidden" name="view" value="<?= $view ?>">
                                    <button type="submit" class="btn btn-danger">削除</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- ================= 科目別グラフ ================= -->
    <h3 style="margin-top:22px;">科目別の未完了課題</h3>
    <div class="chart-wrap"><canvas id="taskChart" height="200"></canvas></div>

    </main>

    <script>
        function copyGroupCode(btn) {
            const code = document.getElementById('groupCode').textContent;
            const done = () => { btn.textContent = 'コピーしました'; setTimeout(() => btn.textContent = 'コピー', 1500); };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(done, () => window.prompt('コードをコピーしてください', code));
            } else {
                window.prompt('コードをコピーしてください', code);
            }
        }

        (function () {
            const $ = (id) => document.getElementById(id);
            const tbody = $('taskTable').tBodies[0];
            const VIEW = <?= json_encode($view) ?>;
            const SUBJECTS = <?= json_encode($subject_labels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
            const rowsOf = () => Array.from(tbody.querySelectorAll('tr[data-id]'));

            // ---------- 並び順（オプション）。選んだ並びは次回も覚えておく ----------
            const STORE = 'taskSortV2';
            const sortSel = $('sortSelect');
            function applySort() {
                const v = sortSel.value;
                const [key, dir] = v === 'rank' ? [null, null] : v.split('_');
                const rows = rowsOf();
                rows.sort((a, b) => {
                    if (key) {
                        const diff = parseFloat(a.dataset[key]) - parseFloat(b.dataset[key]);
                        if (diff !== 0) return dir === 'asc' ? diff : -diff;
                    }
                    return parseInt(a.dataset.rank, 10) - parseInt(b.dataset.rank, 10);
                });
                rows.forEach(r => tbody.appendChild(r));
                try { v === 'rank' ? localStorage.removeItem(STORE) : localStorage.setItem(STORE, v); } catch (e) {}
            }
            try {
                const saved = localStorage.getItem(STORE);
                if (saved && Array.from(sortSel.options).some(o => o.value === saved)) sortSel.value = saved;
            } catch (e) {}
            sortSel.addEventListener('change', applySort);
            applySort();

            // ---------- 登録フォーム ⇔ 編集（ページを移動せずに切り替える） ----------
            const form = $('taskForm');
            function setForm(editing, d) {
                $('formTitle').textContent = editing ? '課題を編集' : '新規課題を登録';
                $('fAction').value = editing ? 'update_task' : 'add_task';
                $('fTaskId').disabled = !editing;
                $('fTaskId').value = editing ? d.id : '';
                $('fTitle').value = editing ? d.title : '';
                $('fSubject').value = editing ? d.subject : SUBJECTS[0];
                if (editing && $('fSubject').value !== d.subject) {        // 旧科目（例：理系）はそのまま選べるよう追加
                    const o = document.createElement('option'); o.value = o.textContent = d.subject; o.dataset.legacy = '1';
                    $('fSubject').appendChild(o); $('fSubject').value = d.subject;
                }
                if (!editing) $('fSubject').querySelectorAll('option[data-legacy]').forEach(o => o.remove());
                $('fDeadline').value = editing ? d.deadline : '';
                $('fTime').value = editing ? d.time : '';
                form.querySelectorAll('input[name=difficulty]').forEach(r => r.checked = (r.value === (editing ? String(d.diff) : '1')));
                $('fSubmit').textContent = editing ? '変更を保存' : '課題を登録する';
                $('fCancel').hidden = !editing;
                const scope = $('scopeField'); if (scope) scope.hidden = editing;
            }
            tbody.addEventListener('click', (e) => {
                const btn = e.target.closest('.js-edit');
                if (!btn) return;
                setForm(true, btn.closest('tr').dataset);
                $('formCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
                $('fTitle').focus();
            });
            $('fCancel').addEventListener('click', () => setForm(false));

            // ---------- 完了：画面を切り替えずにポイントを付与し、ポップアップを出す ----------
            tbody.addEventListener('submit', async (e) => {
                const f = e.target.closest('form.js-complete');
                if (!f) return;
                e.preventDefault();
                const btn = f.querySelector('button');
                btn.disabled = true;
                const r = await App.post('task_reward.php', {
                    action: 'complete_task',
                    task_id: f.querySelector('[name=task_id]').value,
                    view: VIEW
                });
                if (!r.ok) {
                    btn.disabled = false;
                    App.toast(r.message || '完了できませんでした', 'error');
                    return;
                }
                const tr = f.closest('tr');
                tr.classList.add('leaving');
                setTimeout(() => { tr.remove(); refresh(); }, 260);
                if (window.Sound) Sound.enqueue('task_done');      // 課題完了音（続けてポイント獲得音）
                App.showPoints(r.award);
            });

            // ---------- グラフ（科目別の未完了数）と「課題なし」表示 ----------
            let chart = null;
            function counts() {
                const c = {};
                rowsOf().forEach(r => { c[r.dataset.subject] = (c[r.dataset.subject] || 0) + 1; });
                return c;
            }
            function refresh() {
                $('emptyRow').hidden = rowsOf().length > 0;
                if (!chart) return;
                const c = counts();
                chart.data.labels = Object.keys(c).map(k => window.I18N ? I18N.t(k) : k);
                chart.data.datasets[0].data = Object.values(c);
                chart.update();
            }
            window.addEventListener('load', () => {
                if (typeof Chart === 'undefined') return;       // CDNに繋がらないときはグラフだけ省略
                Chart.defaults.font.family = "'DotGothic16', sans-serif";
                const c = counts();
                chart = new Chart($('taskChart').getContext('2d'), {
                    type: 'bar',
                    data: { labels: Object.keys(c).map(k => window.I18N ? I18N.t(k) : k), datasets: [{ data: Object.values(c), backgroundColor: '#2f6db5' }] },
                    options: {
                        indexAxis: 'y', responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            });
        })();
    </script>
</body>
</html>
