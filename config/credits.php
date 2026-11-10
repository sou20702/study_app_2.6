<?php
// ==========================================================
// クレジット（音源・素材の出どころ）
//
// ここに書いた内容が、設定タブの「クレジット」にそのまま表示されます。
// 空欄のところは「（未入力）」と表示されます。あとから書き足すだけでOKです。
//
//   use      … 使っている場所（変えなくてOK）
//   title    … 曲名・素材名
//   author   … 作者・提供元
//   site     … 入手したサイトの名前
//   url      … 入手先のURL（https:// から。あるとリンクになります）
//   license  … 利用条件・ライセンス（例：クレジット表記が必要、商用利用OK など）
//
// ★ BGM の title は、設定タブの「BGM」の選択肢の名前にも使われます（空欄のときは「BGM 1」などの名前）。
// ==========================================================
return [
    'BGM' => [
        ['id' => 'bgm1', 'use' => 'ゲーム内BGM（勉強中のBGMも兼ねる）', 'title' => 'BGM 1', 'author' => 'MATSU', 'site' => 'OpenTracks', 'url' => 'https://opentracks.com/bgm/detail/1877', 'license' => 'OpenTracks 音源利用ライセンス'],
        ['id' => 'bgm2', 'use' => 'ゲーム内BGM（勉強中のBGMも兼ねる）', 'title' => 'BGM 2', 'author' => 'MATSU', 'site' => 'OpenTracks', 'url' => 'https://opentracks.com/bgm/detail/12568', 'license' => 'OpenTracks 音源利用ライセンス'],
        ['id' => 'bgm3', 'use' => 'ゲーム内BGM（勉強中のBGMも兼ねる）', 'title' => 'BGM 3', 'author' => 'PeriTune®', 'site' => 'PeriTune', 'url' => 'https://peritune.com/blog/2022/07/05/deep_sea/', 'license' => 'PeriTune 音楽素材利用規約（要クレジット表記 / 商用利用可）'],
    ],
    '効果音' => [
        ['use' => 'タイマー（予定の時間になったとき）',     'title' => '（不明）', 'author' => '効果音ラボ', 'site' => '効果音ラボ', 'url' => 'https://soundeffect-lab.info/', 'license' => '効果音ラボ利用規約（商用利用可・クレジット表記不要 / 再配布・ポン出しアプリ化禁止'],
        ['use' => 'メール開封（ボトルを拾ったとき）',       'title' => '（不明）', 'author' => '（不明）', 'site' => 'Pixabay', 'url' => 'https://pixabay.com/', 'license' => 'Pixabay Content License（商用利用可・クレジット表記不要 / 単体再配布禁止'],
        ['use' => 'メール送信（ボトルを流したとき）',       'title' => '（不明）', 'author' => '（不明）', 'site' => 'Pixabay', 'url' => 'https://pixabay.com/', 'license' => 'Pixabay Content License（商用利用可・クレジット表記不要 / 単体再配布禁止'],
        ['use' => '課題完了',                               'title' => '（不明）', 'author' => '効果音ラボ', 'site' => '効果音ラボ', 'url' => 'https://soundeffect-lab.info/', 'license' => '効果音ラボ利用規約（商用利用可・クレジット表記不要 / 再配布・ポン出しアプリ化禁止'],
        ['use' => '進化（新たな生物が誕生したとき）',       'title' => '（不明）', 'author' => '効果音ラボ', 'site' => '効果音ラボ', 'url' => 'https://soundeffect-lab.info/', 'license' => '効果音ラボ利用規約（商用利用可・クレジット表記不要 / 再配布・ポン出しアプリ化禁止'],
        ['use' => 'ポイント獲得',                           'title' => '（不明）', 'author' => '効果音ラボ', 'site' => '効果音ラボ', 'url' => 'https://soundeffect-lab.info/', 'license' => '効果音ラボ利用規約（商用利用可・クレジット表記不要 / 再配布・ポン出しアプリ化禁止'],
    ],
    '画像' => [
        ['use' => '背景（海域ごとに5段階）', 'title' => 'backgrounds', 'author' => '大字幹太', 'site' => 'N/A', 'url' => 'N/A', 'license' => 'N/A'],
        ['use' => '生き物',                 'title' => 'creatures', 'author' => '浦井煌志郎、大字幹太', 'site' => 'N/A', 'url' => 'N/A', 'license' => '一部生成：Gemini'],
    ],
    'フォント・ライブラリ' => [
        ['use' => '画面のフォント', 'title' => 'DotGothic16', 'author' => 'Fontworks Inc.（The DotGothic16 Project Authors）', 'site' => 'GitHub', 'url' => 'https://github.com/fontworks-fonts/DotGothic16/', 'license' => 'SIL Open Font License 1.1'],
        ['use' => 'グラフの表示',   'title' => 'Chart.js',    'author' => 'Chart.js Contributors', 'site' => 'Chart.js', 'url' => 'https://www.chartjs.org/', 'license' => 'MIT License'],
    ],
];
