<?php
// ==========================================
// 共通ライブラリ
//  - 教科の定義
//  - DBスキーマの自動作成・移行（ensure_schema）
//  - 端末ID（Cookie）・プレイヤーID・グループ機能・課題の優先度計算
//  ※ ポイント・進化レベルの処理は config/growth_lib.php にあります
// ==========================================

// ボトルメールのリアクション（保存する値 => 表示する文言）
const MAIL_REACTIONS = ['nice' => 'ナイス！', 'cheer' => 'がんばれ！', 'like' => 'いいね！'];

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// JSON を返して終了（fetch 用）
function json_out(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// fetch でのPOSTだけを受け付ける（他サイトからの勝手な送信を防ぐ）
function require_fetch_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
        json_out(['ok' => false, 'message' => '不正なリクエストです。画面を再読み込みしてください。'], 400);
    }
}

// 教科の定義：キー => 表示名
function get_subjects(): array {
    return [
        'math'        => '数学',
        'english'     => '英語',
        'physics'     => '物理',
        'chemistry'   => '化学',
        'information' => '情報',
    ];
}

// ------------------------------------------
// スキーマ（初回・旧バージョンからの移行を自動で行う）
// 旧バージョンの表（buildings / self_study_units など）は削除せずそのまま残します
// ------------------------------------------
function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE :c");
    $stmt->execute([':c' => $column]);
    return $stmt->fetch() !== false;
}

function ensure_schema(PDO $pdo): void {
    $opt = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // 課題
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        subject VARCHAR(50) NOT NULL,
        deadline DATE NOT NULL,
        required_time INT NOT NULL,
        difficulty INT NOT NULL,
        is_completed TINYINT(1) DEFAULT 0
    ) $opt");
    if (!column_exists($pdo, 'tasks', 'group_id')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN group_id INT NULL DEFAULT NULL");
    }
    if (!column_exists($pdo, 'tasks', 'created_by')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN created_by INT NULL DEFAULT NULL");
    }

    // プレイヤー（端末ごと）
    $pdo->exec("CREATE TABLE IF NOT EXISTS players (
        id INT AUTO_INCREMENT PRIMARY KEY,
        token CHAR(32) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_player_token (token)
    ) $opt");
    if (!column_exists($pdo, 'tasks', 'owner_id')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN owner_id INT NULL DEFAULT NULL");
        // 旧データ（全員共有だった個人課題）は、最初にアクセスした端末＝プレイヤー1が引き継ぐ
        $pdo->exec("UPDATE tasks SET owner_id = 1 WHERE group_id IS NULL");
    }

    // 成長状態（growth: 進化レベルのゲージ / timer_pool: ポイントに換算されていないタイマーの分数）
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_status (
        id INT PRIMARY KEY,
        growth INT NOT NULL DEFAULT 0,
        timer_pool INT NOT NULL DEFAULT 0
    ) $opt");
    if (!column_exists($pdo, 'user_status', 'growth')) {
        $pdo->exec("ALTER TABLE user_status ADD COLUMN growth INT NOT NULL DEFAULT 0");
    }
    if (!column_exists($pdo, 'user_status', 'timer_pool')) {
        $pdo->exec("ALTER TABLE user_status ADD COLUMN timer_pool INT NOT NULL DEFAULT 0");
    }
    $pdo->exec("INSERT IGNORE INTO user_status (id) VALUES (1)");

    // 歴史（ポイント獲得ログ）
    $pdo->exec("CREATE TABLE IF NOT EXISTS point_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id INT NOT NULL,
        source VARCHAR(10) NOT NULL,          -- timer / task
        task_id INT NULL DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        subject VARCHAR(50) NULL DEFAULT NULL,
        amount INT NOT NULL DEFAULT 0,        -- タイマー: 換算した分数 / 課題: 算出スコア(p)
        points DECIMAL(8,2) NOT NULL DEFAULT 0,
        growth INT NOT NULL DEFAULT 0,
        stage_before INT NOT NULL DEFAULT 0,
        stage_after INT NOT NULL DEFAULT 0,
        caption VARCHAR(255) NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_history_player (player_id, id)
    ) $opt");

    // 問い合わせ（設定タブから送信。phpMyAdmin の inquiries テーブルで確認できる）
    $pdo->exec("CREATE TABLE IF NOT EXISTS inquiries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id INT NOT NULL,
        category VARCHAR(20) NOT NULL,        -- bug / request / other
        message TEXT NOT NULL,
        reply_email VARCHAR(255) NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_inquiry_player (player_id, id)
    ) $opt");

    // グループ
    $pdo->exec("CREATE TABLE IF NOT EXISTS study_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(12) NOT NULL UNIQUE,
        name VARCHAR(50) NOT NULL,
        host_member_id INT NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $opt");
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        name VARCHAR(50) NOT NULL,
        token CHAR(32) NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_group_name (group_id, name),
        UNIQUE KEY uq_token (token)
    ) $opt");
    // グループ課題の完了状況はメンバーごとに管理
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_completions (
        task_id INT NOT NULL,
        member_id INT NOT NULL,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (task_id, member_id)
    ) $opt");

    // ボトルメール（送受信履歴用）
    $pdo->exec("CREATE TABLE IF NOT EXISTS bottle_mails (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message TEXT,
        canvas_data LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $opt");
    if (!column_exists($pdo, 'bottle_mails', 'sender_token')) {
        $pdo->exec("ALTER TABLE bottle_mails ADD COLUMN sender_token CHAR(32) NULL DEFAULT NULL");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS bottle_mail_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mail_id INT NOT NULL,
        receiver_token CHAR(32) NOT NULL,
        received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_mail_receiver (mail_id, receiver_token)
    ) $opt");
    // 受け取った人が送ったリアクション（nice / cheer / like）。1通につき1人1つ（押し直すと上書き）
    if (!column_exists($pdo, 'bottle_mail_receipts', 'reaction')) {
        $pdo->exec("ALTER TABLE bottle_mail_receipts ADD COLUMN reaction VARCHAR(20) NULL DEFAULT NULL");
    }
}

// ------------------------------------------
// 端末ID（Cookie）。グループのメンバー識別・ボトルメール履歴に使用
// ※ページ出力より前に呼ぶこと
// ------------------------------------------
function get_client_id(): string {
    static $id = null;
    if ($id !== null) {
        return $id;
    }
    if (!empty($_COOKIE['study_client']) && preg_match('/^[a-f0-9]{32}$/', $_COOKIE['study_client'])) {
        return $id = $_COOKIE['study_client'];
    }
    $id = bin2hex(random_bytes(16));
    setcookie('study_client', $id, [
        'expires'  => time() + 60 * 60 * 24 * 365 * 5,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['study_client'] = $id;
    return $id;
}

// ------------------------------------------
// プレイヤーID（端末IDに紐づく）。成長状態・歴史・個人課題の持ち主
// 旧バージョンは全員が user_id=1 を共有していたため、最初に来た端末が id=1（既存データ）を引き継ぐ
// ------------------------------------------
function get_player_id(PDO $pdo): int {
    static $cache = [];
    $token = get_client_id();
    if (isset($cache[$token])) {
        return $cache[$token];
    }
    $sel = $pdo->prepare("SELECT id FROM players WHERE token = :t");
    $sel->execute([':t' => $token]);
    $id = $sel->fetchColumn();

    if ($id === false) {
        $is_first = (int)$pdo->query("SELECT COUNT(*) FROM players")->fetchColumn() === 0;
        if ($is_first) {
            $pdo->prepare("INSERT IGNORE INTO players (id, token) VALUES (1, :t)")->execute([':t' => $token]);
            $sel->execute([':t' => $token]);
            $id = $sel->fetchColumn();
        }
        if ($id === false) {
            $pdo->prepare("INSERT IGNORE INTO players (token) VALUES (:t)")->execute([':t' => $token]);
            $sel->execute([':t' => $token]);
            $id = $sel->fetchColumn();
        }
        $id = (int)$id;
    }
    // この端末用の成長状態の初期行（既にあれば何もしない）
    $pdo->prepare("INSERT IGNORE INTO user_status (id) VALUES (:id)")->execute([':id' => (int)$id]);
    return $cache[$token] = (int)$id;
}

// ------------------------------------------
// グループ
// ------------------------------------------
function normalize_user_name(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', $s));
}

function get_my_member(PDO $pdo): ?array {
    $stmt = $pdo->prepare("
        SELECT m.id, m.group_id, m.name, g.code, g.name AS group_name, g.host_member_id
        FROM group_members m
        JOIN study_groups g ON g.id = m.group_id
        WHERE m.token = :t
    ");
    $stmt->execute([':t' => get_client_id()]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function get_group_members(PDO $pdo, int $group_id): array {
    $stmt = $pdo->prepare("SELECT id, name, joined_at FROM group_members WHERE group_id = :g ORDER BY id ASC");
    $stmt->execute([':g' => $group_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generate_group_code(PDO $pdo): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 紛らわしい文字(I,O,0,1)は除外
    $check = $pdo->prepare("SELECT COUNT(*) FROM study_groups WHERE code = :c");
    for ($i = 0; $i < 20; $i++) {
        $code = '';
        for ($j = 0; $j < 6; $j++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $check->execute([':c' => $code]);
        if ((int)$check->fetchColumn() === 0) {
            return $code;
        }
    }
    throw new RuntimeException('グループコードの生成に失敗しました。もう一度お試しください。');
}

function validate_user_name(string $name): ?string {
    if ($name === '') {
        return 'ユーザー名を入力してください。';
    }
    if (mb_strlen($name) > 20) {
        return 'ユーザー名は20文字以内で入力してください。';
    }
    return null;
}

// 戻り値: [成功フラグ, メッセージ]
function create_group(PDO $pdo, string $user_name, string $group_name): array {
    $user_name = normalize_user_name($user_name);
    $group_name = normalize_user_name($group_name);
    if ($err = validate_user_name($user_name)) {
        return [false, $err];
    }
    if ($group_name === '') {
        $group_name = '勉強グループ';
    }
    if (mb_strlen($group_name) > 30) {
        return [false, 'グループ名は30文字以内で入力してください。'];
    }
    if (get_my_member($pdo)) {
        return [false, 'すでにグループに参加しています。'];
    }
    try {
        $pdo->beginTransaction();
        $code = generate_group_code($pdo);
        $pdo->prepare("INSERT INTO study_groups (code, name) VALUES (:c, :n)")
            ->execute([':c' => $code, ':n' => $group_name]);
        $group_id = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO group_members (group_id, name, token) VALUES (:g, :n, :t)")
            ->execute([':g' => $group_id, ':n' => $user_name, ':t' => get_client_id()]);
        $member_id = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE study_groups SET host_member_id = :m WHERE id = :g")
            ->execute([':m' => $member_id, ':g' => $group_id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [false, 'グループの作成に失敗しました: ' . $e->getMessage()];
    }
    return [true, "グループを作成しました。グループコード「{$code}」をメンバーに共有してください。"];
}

function join_group(PDO $pdo, string $code, string $user_name): array {
    $code = strtoupper(trim($code));
    $user_name = normalize_user_name($user_name);
    if ($code === '') {
        return [false, 'グループコードを入力してください。'];
    }
    if ($err = validate_user_name($user_name)) {
        return [false, $err];
    }
    if (get_my_member($pdo)) {
        return [false, 'すでにグループに参加しています。'];
    }
    $stmt = $pdo->prepare("SELECT id, name FROM study_groups WHERE code = :c");
    $stmt->execute([':c' => $code]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$group) {
        return [false, 'そのグループコードのグループは見つかりません。'];
    }
    $dup = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = :g AND name = :n");
    $dup->execute([':g' => $group['id'], ':n' => $user_name]);
    if ((int)$dup->fetchColumn() > 0) {
        return [false, 'そのユーザー名はこのグループですでに使われています。別の名前で参加してください。'];
    }
    try {
        $pdo->prepare("INSERT INTO group_members (group_id, name, token) VALUES (:g, :n, :t)")
            ->execute([':g' => $group['id'], ':n' => $user_name, ':t' => get_client_id()]);
    } catch (PDOException $e) {
        return [false, '参加に失敗しました。ユーザー名が重複している可能性があります。'];
    }
    return [true, "「{$group['name']}」に参加しました。"];
}

function remove_member_rows(PDO $pdo, int $member_id): void {
    $pdo->prepare("DELETE FROM task_completions WHERE member_id = :m")->execute([':m' => $member_id]);
    $pdo->prepare("DELETE FROM group_members WHERE id = :m")->execute([':m' => $member_id]);
}

// ホストによるメンバー削除
function kick_member(PDO $pdo, ?array $me, int $target_id): array {
    if (!$me || (int)$me['host_member_id'] !== (int)$me['id']) {
        return [false, 'メンバーを削除できるのはグループホストだけです。'];
    }
    if ($target_id === (int)$me['id']) {
        return [false, 'ホスト自身は削除できません。グループを解散してください。'];
    }
    $stmt = $pdo->prepare("SELECT name FROM group_members WHERE id = :id AND group_id = :g");
    $stmt->execute([':id' => $target_id, ':g' => $me['group_id']]);
    $name = $stmt->fetchColumn();
    if ($name === false) {
        return [false, '対象のメンバーが見つかりません。'];
    }
    try {
        $pdo->beginTransaction();
        remove_member_rows($pdo, $target_id);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [false, '削除に失敗しました: ' . $e->getMessage()];
    }
    return [true, "{$name} さんをグループから削除しました。"];
}

// 退出（ホストの場合はグループ解散：グループ課題も削除）
function leave_group(PDO $pdo, ?array $me): array {
    if (!$me) {
        return [false, 'グループに参加していません。'];
    }
    try {
        $pdo->beginTransaction();
        if ((int)$me['host_member_id'] === (int)$me['id']) {
            $g = (int)$me['group_id'];
            $pdo->prepare("DELETE FROM task_completions WHERE task_id IN (SELECT id FROM tasks WHERE group_id = :g)")
                ->execute([':g' => $g]);
            $pdo->prepare("DELETE FROM tasks WHERE group_id = :g")->execute([':g' => $g]);
            $pdo->prepare("DELETE FROM group_members WHERE group_id = :g")->execute([':g' => $g]);
            $pdo->prepare("DELETE FROM study_groups WHERE id = :g")->execute([':g' => $g]);
            $msg = 'グループを解散しました。';
        } else {
            remove_member_rows($pdo, (int)$me['id']);
            $msg = 'グループから退出しました。';
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [false, '処理に失敗しました: ' . $e->getMessage()];
    }
    return [true, $msg];
}

// ------------------------------------------
// 課題
// ------------------------------------------
// 優先度スコアを付与して降順に並べる
function enrich_and_sort_tasks(array $tasks): array {
    $today = new DateTime('today');
    foreach ($tasks as &$task) {
        $days_left = (int)$today->diff(new DateTime($task['deadline']))->format('%r%a');
        if ($days_left <= 0)      { $deadline_score = 100; }
        elseif ($days_left === 1) { $deadline_score = 50; }
        elseif ($days_left === 2) { $deadline_score = 30; }
        elseif ($days_left <= 3)  { $deadline_score = 20; }
        elseif ($days_left <= 7)  { $deadline_score = 10; }
        else                      { $deadline_score = 0; }

        $task['priority_score'] = round($deadline_score
            + (int)$task['difficulty'] * 10
            + (int)$task['required_time'] * 0.1, 1);
        $task['days_left'] = $days_left;
    }
    unset($task);
    // 優先順位：①提出期限が近い順（期限超過が最優先）→ ②同じ期限なら算出スコアが高い順 → ③ID順
    usort($tasks, function ($a, $b) {
        return [$a['deadline'], $b['priority_score'], (int)$a['id']]
           <=> [$b['deadline'], $a['priority_score'], (int)$b['id']];
    });
    foreach ($tasks as $i => &$task) {
        $task['priority_rank'] = $i + 1; // 画面での並び替え後も変わらない「優先順位」
    }
    unset($task);
    return $tasks;
}

// 課題フォーム入力の検証。戻り値: [データ|null, エラー|null]
function parse_task_input(array $in, array $allowed_subjects): array {
    $title = trim((string)($in['title'] ?? ''));
    $subject = (string)($in['subject'] ?? '');
    $deadline = (string)($in['deadline'] ?? '');
    $required_time = (int)($in['required_time'] ?? 0);
    $difficulty = (int)($in['difficulty'] ?? 0);

    if ($title === '' || mb_strlen($title) > 255) {
        return [null, '課題名は1〜255文字で入力してください。'];
    }
    if (!in_array($subject, $allowed_subjects, true)) {
        return [null, '科目が正しくありません。'];
    }
    $d = DateTime::createFromFormat('Y-m-d', $deadline);
    if (!$d || $d->format('Y-m-d') !== $deadline) {
        return [null, '提出期限が正しくありません。'];
    }
    if ($required_time < 1) {
        return [null, '所要時間は1分以上で入力してください。'];
    }
    if ($difficulty < 1 || $difficulty > 3) {
        return [null, '難易度が正しくありません。'];
    }
    return [[
        'title' => $title, 'subject' => $subject, 'deadline' => $deadline,
        'required_time' => $required_time, 'difficulty' => $difficulty,
    ], null];
}

// グループ課題の編集・削除は「登録者本人 or ホスト」。個人課題は持ち主のみ
function can_modify_task(array $task, ?array $me, ?int $player_id = null): bool {
    if ($task['group_id'] === null) {
        return $player_id !== null && (int)($task['owner_id'] ?? 0) === $player_id; // 個人課題は持ち主のみ
    }
    if (!$me || (int)$task['group_id'] !== (int)$me['group_id']) {
        return false;
    }
    return (int)$task['created_by'] === (int)$me['id']
        || (int)$me['host_member_id'] === (int)$me['id'];
}
