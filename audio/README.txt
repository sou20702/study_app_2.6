BGM・効果音を置く場所です。下のファイル名で mp3 を置くだけで鳴ります。
置かれていない音は、何も鳴らさずに進みます（エラーにはなりません）。

【効果音】
  se_timer.mp3       タイマー（予定の時間になったとき）
  se_mail_open.mp3   メール開封（ボトルを拾ったとき）
  se_mail_send.mp3   メール送信（ボトルを流したとき）
  se_task_done.mp3   課題完了
  se_stage_up.mp3    絵柄更新
  se_point.mp3       ポイント獲得

【BGM】（ゲーム内BGMは3種類。勉強中＝タイマーのBGMも兼ねます）
  bgm_1.mp3          BGM 1（bgm_game.mp3 という名前でも、BGM 1 として鳴ります）
  bgm_2.mp3          BGM 2
  bgm_3.mp3          BGM 3

・設定タブで、BGMの選択・ミュート・音量の変更ができます（音源が置かれていないBGMは選べません）。
・BGMの名前（初期は「BGM 1」など）は config/credits.php の title に書くと、設定タブの選択肢に反映されます。
・音源の出どころ（作者・サイト・URL・利用条件）も config/credits.php に書くと、設定タブの「クレジット」に表示されます。
・ファイル名や拡張子を変えたいときは js/sound.js の SE_FILES / BGM_TRACKS を書き換えます。
