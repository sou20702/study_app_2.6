-- データベースの作成
CREATE DATABASE IF NOT EXISTS study_app CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE study_app;

-- ※ 以下のテーブルは、アプリ起動時に config/app_lib.php の ensure_schema() が
--    自動で作成・移行します（このSQLを流さなくても動作します）。
--    手動で初期構築したい場合のリファレンスとして残しています。

-- 課題管理（group_id が NULL なら個人課題、値があればグループ課題）
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    subject VARCHAR(50) NOT NULL,
    deadline DATE NOT NULL,
    required_time INT NOT NULL,
    difficulty INT NOT NULL,
    is_completed TINYINT(1) DEFAULT 0,
    group_id INT NULL DEFAULT NULL,
    created_by INT NULL DEFAULT NULL,
    owner_id INT NULL DEFAULT NULL  -- 個人課題の持ち主（players.id）
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- プレイヤー（端末ごと）。資材・教科レベル・建物・個人課題はこのIDに紐づく
CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token CHAR(32) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_player_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 成長状態（growth: 進化レベルのゲージ / timer_pool: ポイントに換算していないタイマーの分数）
CREATE TABLE IF NOT EXISTS user_status (
    id INT PRIMARY KEY,
    growth INT NOT NULL DEFAULT 0,
    timer_pool INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO user_status (id) VALUES (1);

-- 歴史（ポイント獲得ログ）
CREATE TABLE IF NOT EXISTS point_history (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- グループ
CREATE TABLE IF NOT EXISTS study_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(12) NOT NULL UNIQUE,   -- 自動生成のグループコード（合言葉）
    name VARCHAR(50) NOT NULL,
    host_member_id INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    token CHAR(32) NOT NULL,            -- 端末ID（Cookie）
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_group_name (group_id, name),
    UNIQUE KEY uq_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- グループ課題の完了状況（メンバーごと）
CREATE TABLE IF NOT EXISTS task_completions (
    task_id INT NOT NULL,
    member_id INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ボトルメール（sender_token = 送信者の端末ID）
CREATE TABLE IF NOT EXISTS bottle_mails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT,
    canvas_data LONGTEXT, -- お絵描きデータ保存用
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sender_token CHAR(32) NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ボトルメールの受信履歴
CREATE TABLE IF NOT EXISTS bottle_mail_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mail_id INT NOT NULL,
    receiver_token CHAR(32) NOT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reaction VARCHAR(20) NULL DEFAULT NULL, -- 受け取った人のリアクション（nice / cheer / like）
    UNIQUE KEY uq_mail_receiver (mail_id, receiver_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 問い合わせ（設定タブから送信）
CREATE TABLE IF NOT EXISTS inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    category VARCHAR(20) NOT NULL,        -- bug / request / other
    message TEXT NOT NULL,
    reply_email VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inquiry_player (player_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
