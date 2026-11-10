<?php
// ==========================================
// ゲームの数値設定（バランス調整はここだけで行えます）
// ==========================================
// 「成長値」は進化レベルを上げる内部ゲージです。進化レベルが1上がる = 成長値300 = 生き物が1匹増える。
const GROWTH_PER_STAGE     = 300;  // 進化レベルを1上げるのに必要な成長値
const TIMER_MIN_PER_POINT  = 30;   // タイマー：勉強＋休憩で30分（25+5）ごとに1ポイント
const TIMER_POINT_GROWTH   = 100;  // タイマー：1ポイント＝成長値100（3ポイントで進化レベルが1上がる）
const TASK_SCORE_PER_POINT = 100;  // 課題：算出スコア100p＝1ポイント（歴史への記録用の換算）
const TASK_SCORE_GROWTH    = 3;    // 課題：算出スコア1p＝成長値3（100pで進化レベルが1上がる）
const CREATURES_PER_AREA   = 10;   // 1つの海域に現れる生き物の数（5海域で合計50匹）
const BG_LEVELS            = 5;    // 背景のレベル数（Lv1〜Lv5。海域ごとに5枚、合計25枚）
const CREATURES_PER_BG_LEVEL = 2;  // 生き物が2匹増えるごとに、背景が次のレベルへ（0匹→Lv1、2匹→Lv2、4匹→Lv3、6匹→Lv4、8匹→Lv5）
const CAPTION_MAX_LENGTH   = 100;  // 歴史キャプションの最大文字数
const INQUIRY_MAX_LENGTH   = 1000; // 問い合わせ内容の最大文字数
const CONTACT_EMAIL        = '';   // 問い合わせ先のメールアドレス（設定すると、設定タブにメールで送るリンクも表示される）

// 海域の定義。x,y はホームタブでの位置（中央が 0,0 / 上が y=-1 / 左が x=-1）。
// unlock_after：進化レベルがこの値を「超える」と解放される（0 は最初から解放）。
// 配列の順番は、解放される順番にそろえてください。
// 解放後は、進化レベルが1上がるごとに、解放ずみの海域へ順番にばらけて生き物が増えます（growth_lib.php の world_schedule）。
function get_world_areas(): array {
    return [
        'center'  => ['name' => '中央海域',   'x' => 0,  'y' => 0,  'unlock_after' => 0],
        'shallow' => ['name' => '浅瀬',       'x' => 1,  'y' => 0,  'unlock_after' => 5],
        'coral'   => ['name' => 'サンゴ礁',   'x' => -1, 'y' => 0,  'unlock_after' => 10],
        'vent'    => ['name' => '熱水噴出孔', 'x' => 0,  'y' => 1,  'unlock_after' => 15],
        'deep'    => ['name' => '深海エリア', 'x' => 0,  'y' => -1, 'unlock_after' => 20],
    ];
}
