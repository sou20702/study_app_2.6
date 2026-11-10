<?php
// ==========================================
// 成長（ポイント・進化レベル）の処理
//  - タイマー：勉強＋休憩で30分ごとに1ポイント → 3ポイントで進化レベルが1上がる
//  - 課題　　：算出スコアがそのままp → 100pで進化レベルが1上がる（100p＝1ポイントとして歴史に記録）
//  - 進化レベルが1上がるごとに、どこかの海域に生き物が1匹増える（最大50匹）
//  - 生き物が2匹増えるごとに、その海域の背景が次のレベルへ（Lv1〜Lv5。Lv5のあとも生き物は10匹まで増える）
//  数値は config/game_config.php で変更できます
// ==========================================

// 0.46 → "0.46" / 1.00 → "1"
function format_points(float $p): string {
    return rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.');
}

// 進化レベルの上限（＝生き物の合計数。5海域×10匹＝50）
function world_max_level(): int {
    return count(get_world_areas()) * CREATURES_PER_AREA;
}

// 背景のレベル（1〜5）。生き物が2匹増えるごとに次のレベルへ。5になったあとも生き物は増える
function bg_level(int $creatures): int {
    return min(BG_LEVELS, intdiv(max(0, $creatures), CREATURES_PER_BG_LEVEL) + 1);
}

// 進化レベルが 1,2,3,… と上がるたびに、どの海域へ生き物が増えるかの予定表（0番目＝進化レベル1のとき）。
//  ・解放されていない海域には増えない（解放：進化レベルが unlock_after を超えたとき）
//  ・解放された海域は、そのレベルアップですぐ1匹目が増える
//  ・そのあとは、いちばん長く増えていない海域から順に回して、変化がばらけるようにする
//  ・1つの海域は CREATURES_PER_AREA 匹で打ち止め
function world_schedule(): array {
    static $schedule = null;
    if ($schedule !== null) {
        return $schedule;
    }
    $areas = get_world_areas();
    $keys = array_keys($areas);
    $counts = array_fill_keys($keys, 0);
    $last = array_fill_keys($keys, -1);       // 最後に増えた進化レベル（まだ増えていなければ -1）
    $schedule = [];
    $max = world_max_level();
    for ($k = 1; $k <= $max; $k++) {
        $best = null;
        foreach ($keys as $key) {
            if ($k <= $areas[$key]['unlock_after'] || $counts[$key] >= CREATURES_PER_AREA) {
                continue;
            }
            if ($best === null || $last[$key] < $last[$best]) {
                $best = $key;                  // 同じなら、先に並んでいる海域を選ぶ
            }
        }
        if ($best === null) {
            break;
        }
        $schedule[] = $best;
        $counts[$best]++;
        $last[$best] = $k;
    }
    return $schedule;
}

// 進化レベルから、各海域の生き物の数を求める
function area_counts(int $level): array {
    $counts = array_fill_keys(array_keys(get_world_areas()), 0);
    foreach (array_slice(world_schedule(), 0, max(0, $level)) as $key) {
        $counts[$key]++;
    }
    return $counts;
}

// k回目（0始まり）の進化レベルアップで、どの海域に何匹目の生き物が増えたか
function stage_up_info(int $k): array {
    $areas = get_world_areas();
    $schedule = world_schedule();
    $key = $schedule[$k];
    $before = array_count_values(array_slice($schedule, 0, $k));
    $n = ($before[$key] ?? 0) + 1;             // この海域の n 匹目
    return [
        'area'         => $key,
        'name'         => $areas[$key]['name'],
        'creature'     => $n,
        'bg_level'     => bg_level($n),
        'bg_up'        => bg_level($n) > bg_level($n - 1),   // 背景が次のレベルへ進んだか
        'unlocked_now' => $n === 1 && $areas[$key]['unlock_after'] > 0,   // この海域が解放されたか
    ];
}

function get_growth_value(PDO $pdo): int {
    $stmt = $pdo->prepare("SELECT growth FROM user_status WHERE id = :id");
    $stmt->execute([':id' => get_player_id($pdo)]);
    return (int)$stmt->fetchColumn();
}

function get_total_points(PDO $pdo): float {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(points), 0) FROM point_history WHERE player_id = :p");
    $stmt->execute([':p' => get_player_id($pdo)]);
    return (float)$stmt->fetchColumn();
}

// 次の進化レベルアップまでの進み具合（0〜100）
function stage_progress_pct(int $growth): int {
    if (intdiv($growth, GROWTH_PER_STAGE) >= world_max_level()) {
        return 100;
    }
    return (int)floor(($growth % GROWTH_PER_STAGE) / GROWTH_PER_STAGE * 100);
}

// ホームタブ用：世界の状態（api/world_state.php でも同じ形を返す）
function get_world_state(PDO $pdo): array {
    $growth = get_growth_value($pdo);
    $max = world_max_level();
    $level = min(intdiv($growth, GROWTH_PER_STAGE), $max);   // 進化レベル（＝生き物の合計数）
    $counts = area_counts($level);
    $areas = [];
    foreach (get_world_areas() as $key => $a) {
        $areas[$key] = [
            'name'         => $a['name'],
            'x'            => $a['x'],
            'y'            => $a['y'],
            'unlocked'     => $a['unlock_after'] === 0 || $level > $a['unlock_after'],
            'unlock_level' => $a['unlock_after'] + 1,         // この進化レベルになると解放
            'creatures'    => $counts[$key],
            'max_creatures' => CREATURES_PER_AREA,
            'bg_level'     => bg_level($counts[$key]),
            'bg_max'       => BG_LEVELS,
        ];
    }
    return [
        'total_points' => get_total_points($pdo),
        'total_stage'  => $level,
        'max_stage'    => $max,
        'progress_pct' => stage_progress_pct($growth),
        'next_area'    => $level < $max ? stage_up_info($level)['name'] : null,
        'areas'        => $areas,
    ];
}

// ポイント獲得を歴史に記録し、成長値を加算する。
// $e: source(timer|task), title, subject, amount, points, growth, task_id
// 呼び出し側がトランザクション中ならそれに参加し、そうでなければ自分で開始する。
function award_growth(PDO $pdo, array $e): array {
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $pid = get_player_id($pdo);
        $sel = $pdo->prepare("SELECT growth FROM user_status WHERE id = :id FOR UPDATE");
        $sel->execute([':id' => $pid]);
        $before = (int)$sel->fetchColumn();
        $add = max(0, (int)$e['growth']);
        $after = $before + $add;
        $sb = intdiv($before, GROWTH_PER_STAGE);
        $sa = intdiv($after, GROWTH_PER_STAGE);

        $pdo->prepare("UPDATE user_status SET growth = :g WHERE id = :id")
            ->execute([':g' => $after, ':id' => $pid]);

        $pdo->prepare("INSERT INTO point_history
                (player_id, source, task_id, title, subject, amount, points, growth, stage_before, stage_after)
                VALUES (:p, :src, :tid, :title, :subj, :amount, :points, :growth, :sb, :sa)")
            ->execute([
                ':p' => $pid, ':src' => $e['source'], ':tid' => $e['task_id'] ?? null,
                ':title' => mb_substr((string)$e['title'], 0, 255), ':subj' => $e['subject'] ?? null,
                ':amount' => (int)$e['amount'], ':points' => round((float)$e['points'], 2),
                ':growth' => $add, ':sb' => $sb, ':sa' => $sa,
            ]);
        $history_id = (int)$pdo->lastInsertId();
        if ($own) {
            $pdo->commit();
        }
    } catch (Throwable $ex) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }

    $cap = world_max_level();
    $ups = [];
    for ($k = $sb; $k < min($sa, $cap); $k++) {
        $ups[] = stage_up_info($k);
    }
    return [
        'awarded'      => true,
        'history_id'   => $history_id,
        'source'       => $e['source'],
        'title'        => (string)$e['title'],
        'points'       => (float)$e['points'],
        'points_text'  => format_points((float)$e['points']),
        'total_points' => format_points(get_total_points($pdo)),
        'stage_ups'    => $ups,
        'progress_pct' => stage_progress_pct($after),
    ];
}

// タイマーの1フェーズ（勉強 or 休憩）が完了したときに呼ぶ。
// 経過分数をためておき、30分ぶんたまるごとに1ポイント（端数は次回に繰り越し）
function award_timer_phase(PDO $pdo, string $phase, int $minutes): array {
    if (!in_array($phase, ['study', 'break'], true)) {
        throw new InvalidArgumentException('フェーズが正しくありません。');
    }
    $max = $phase === 'study' ? 180 : 60;
    if ($minutes < 1) {
        throw new InvalidArgumentException('時間が正しくありません。');
    }
    $minutes = min($minutes, $max);      // 超過したまま長く放置した場合は、上限（勉強180分・休憩60分）までを数える
    $pdo->beginTransaction();
    try {
        $pid = get_player_id($pdo);
        $sel = $pdo->prepare("SELECT timer_pool FROM user_status WHERE id = :id FOR UPDATE");
        $sel->execute([':id' => $pid]);
        $pool = (int)$sel->fetchColumn() + $minutes;
        $pts = intdiv($pool, TIMER_MIN_PER_POINT);
        $pool -= $pts * TIMER_MIN_PER_POINT;
        $pdo->prepare("UPDATE user_status SET timer_pool = :p WHERE id = :id")
            ->execute([':p' => $pool, ':id' => $pid]);

        if ($pts === 0) {
            $pdo->commit();
            return ['awarded' => false, 'minutes_to_next' => TIMER_MIN_PER_POINT - $pool];
        }
        $res = award_growth($pdo, [
            'source' => 'timer', 'task_id' => null, 'subject' => null,
            'title'  => 'ポモドーロ ' . ($pts * TIMER_MIN_PER_POINT) . '分',
            'amount' => $pts * TIMER_MIN_PER_POINT,
            'points' => $pts,
            'growth' => $pts * TIMER_POINT_GROWTH,
        ]);
        $pdo->commit();
        return $res;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

// 課題の完了時に呼ぶ（呼び出し側のトランザクション内で使う）。ポイントは算出スコアから決まる。
function award_task_completion(PDO $pdo, array $task): array {
    $score = enrich_and_sort_tasks([$task])[0]['priority_score'];
    $p = max(1, (int)round($score));
    return award_growth($pdo, [
        'source' => 'task', 'task_id' => (int)$task['id'],
        'title'  => (string)$task['title'], 'subject' => $task['subject'],
        'amount' => $p,
        'points' => $p / TASK_SCORE_PER_POINT,
        'growth' => $p * TASK_SCORE_GROWTH,
    ]);
}
