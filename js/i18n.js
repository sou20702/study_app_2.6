// ==========================================================
// 言語の切り替え（日本語 / English）
//
// ・画面の文字は日本語で書かれていて、English のときだけ、表示するときに英語へ置き換えます。
//   （ページを作り直さなくても、新しい文言は下の EN に「日本語: 英語」の形で足すだけで対応できます）
// ・数字などが混ざる文言は、RULES（正規表現）で訳します。
// ・訳したくない場所（ユーザーが入力した文字など）には data-no-i18n 属性を付けます。
// ・選んだ言語はこのブラウザに保存されます（localStorage の study_lang）。
// ==========================================================
(function () {
  'use strict';

  const KEY = 'study_lang';
  function getLang() { try { return localStorage.getItem(KEY) === 'en' ? 'en' : 'ja'; } catch (e) { return 'ja'; } }
  function setLang(l) {
    try { if (l === 'en') localStorage.setItem(KEY, 'en'); else localStorage.removeItem(KEY); } catch (e) {}
    location.reload();
  }
  const lang = getLang();
  const JA_RE = /[\u3000-\u30ff\u4e00-\u9fff\uff00-\uffef]/;
  const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  // 日本語 → 英語（文言まるごと一致したときに置き換える）
  const EN = {
    "勉強大好きアプリ": "Study Lover App",
    "うみ": "Sea",
    "メインメニュー": "Main menu",
    "ホーム": "Home",
    "課題": "Tasks",
    "タイマー": "Timer",
    "歴史": "History",
    "手紙": "Letters",
    "設定": "Settings",
    "ポイントを獲得しました": "You earned points",
    "キャプション（何で得たポイントか）": "Caption (what the points were for)",
    "例：数学の復習をがんばった": "e.g. Worked hard reviewing math",
    "記録に追加": "Save to Log",
    "閉じる": "Close",
    "確認": "Confirm",
    "実行する": "Proceed",
    "やめる": "Cancel",
    "数学": "Math",
    "英語": "English",
    "物理": "Physics",
    "化学": "Chemistry",
    "情報": "Computer Science",
    "中央海域": "Central Sea",
    "サンゴ礁": "Coral Reef",
    "浅瀬": "Shallows",
    "深海エリア": "Deep Sea",
    "熱水噴出孔": "Hydrothermal Vents",
    "エラーが発生しました": "An error occurred",
    "データベースに接続できません": "Cannot connect to the database",
    "データベースの準備に失敗しました": "Failed to prepare the database",
    "エラー": "Error",
    "XAMPP の Apache と MySQL が起動しているか確認してから、もう一度お試しください。": "Check that Apache and MySQL are running in XAMPP, then try again.",
    "詳細:": "Details:",
    "再読み込み": "Reload",
    "前の画面へ": "Back",
    "不正なリクエストです。画面を再読み込みしてください。": "Invalid request. Please reload the page.",
    "通信に失敗しました。もう一度お試しください。": "Communication failed. Please try again.",
    "サーバーに接続できません。起動状態を確認してください。": "Cannot reach the server. Please check that it is running.",
    "キャプションを保存しました": "Caption saved.",
    "保存に失敗しました": "Failed to save.",
    "対象の記録が見つかりません。": "The record was not found.",
    "フェーズが正しくありません。": "The phase is invalid.",
    "時間が正しくありません。": "The time is invalid.",
    "ポイントを記録できませんでした": "Could not record the points.",
    "ホーム - 勉強大好きアプリ": "Home - Study Lover App",
    "進化レベル": "Evolution Level",
    "次の進化まで": "Until next evolution",
    "未完了の課題": "Open Tasks",
    "海の世界": "The Sea",
    "海の様子": "View of the sea",
    "海域マップ": "Sea area map",
    "まだ何もいません。勉強を重ねると生き物が増えていきます。": "Nothing lives here yet. More creatures appear as you keep studying.",
    "デモ表示。背景は": "Demo display. Put backgrounds in",
    "img/backgrounds/海域名_レベル.png": "img/backgrounds/area_level.png",
    "、生き物は": ", and creatures in",
    "img/creatures/海域名_番号.png": "img/creatures/area_number.png",
    "に画像を置くと、その絵に差し変わる（詳しくは README.md）。": "to replace the demo art with your own pictures (see README.md).",
    "新たな生物が誕生しました。": "A new creature has been born.",
    "時がたち、海域が変化しました。": "Time has passed, and the sea area has changed.",
    "新しい海域が解放されました。": "A new sea area has been unlocked.",
    "最近の歴史": "Recent History",
    "まだポイントを獲得していません。タイマーや課題でポイントをためましょう。": "No points earned yet. Earn points with the timer and your tasks.",
    "歴史をすべて見る": "View All History",
    "見に行く": "Go see",
    "まだ空っぽです": "Still empty",
    "歴史 - 勉強大好きアプリ": "History - Study Lover App",
    "ポイントを獲得するたびに記録されます。キャプションを追加して、取り組んだ内容を書き足してみよう。": "Every time you earn points, it is logged here. Add a caption to note what you worked on.",
    "総獲得ポイント": "Total Points",
    "進化した回数": "Evolutions",
    "回": "times",
    "記録件数": "Records",
    "件": "items",
    "まだポイントを獲得していません。タイマーを回すか、課題を完了するとここに記録されます。": "No points earned yet. Run the timer or complete a task and it will be logged here.",
    "ポイント": "points",
    "すべて": "All",
    "キャプションなし": "No caption",
    "キャプションを書く": "Add a caption",
    "編集": "Edit",
    "キャプション": "Caption",
    "保存": "Save",
    "新しい200件を表示しています。": "Showing the latest 200 items.",
    "ボトルメール": "Bottle Mail",
    "ボトルを流す": "Send a Bottle",
    "メッセージを書いて海へ流そう。海を漂流して、だれかもとへたどり着くかもしれません。": "Write a message and set it adrift. It might wash up with someone out there.",
    "メッセージ": "Message",
    "ここにメッセージを書いてください": "Write your message here",
    "お絵描き": "Drawing",
    "消す": "Clear",
    "海に流す": "Send to Sea",
    "ボトルを拾う": "Pick Up a Bottle",
    "リアクション": "Reaction",
    "あなたのリアクション": "Your reaction",
    "リアクションを送れませんでした。": "Could not send the reaction.",
    "このボトルはまだ開封していないので、リアクションを送れません。": "You haven't opened this bottle yet, so you can't send a reaction.",
    "メッセージを書くか、絵を描いてから流してください。": "Write a message or draw a picture before sending.",
    "削除する": "Delete",
    "退出する": "Leave",
    "解散する": "Disband",
    "ナイス！": "Nice!",
    "がんばれ！": "Keep going!",
    "いいね！": "Like!",
    "送受信履歴": "Sent & Received",
    "送信（": "Sent (",
    "受信（": "Received (",
    "）": ")",
    "まだボトルを流していません。": "You haven't sent any bottles yet.",
    "まだボトルを拾っていません。": "You haven't picked up any bottles yet.",
    "（メッセージなし）": "(No message)",
    "送信できませんでした": "Could not send.",
    "ボトルメールを海へ流しました": "Your bottle was sent out to sea.",
    "拾えませんでした": "Could not pick up a bottle.",
    "まだボトルが流れていません。": "No bottles have drifted in yet.",
    "ボトルを開封しました": "Bottle opened.",
    "メッセージは1000文字以内で入力してください。": "Messages must be 1000 characters or fewer.",
    "絵のデータが正しくありません。もう一度描いてください。": "The drawing data is invalid. Please draw it again.",
    "グループコードの生成に失敗しました。もう一度お試しください。": "Failed to generate a group code. Please try again.",
    "ユーザー名を入力してください。": "Please enter a user name.",
    "ユーザー名は20文字以内で入力してください。": "User names must be 20 characters or fewer.",
    "勉強グループ": "Study Group",
    "グループ名は30文字以内で入力してください。": "Group names must be 30 characters or fewer.",
    "すでにグループに参加しています。": "You are already in a group.",
    "グループコードを入力してください。": "Please enter a group code.",
    "そのグループコードのグループは見つかりません。": "No group found with that code.",
    "そのユーザー名はこのグループですでに使われています。別の名前で参加してください。": "That user name is already used in this group. Please join with a different name.",
    "参加に失敗しました。ユーザー名が重複している可能性があります。": "Failed to join. The user name may already be taken.",
    "メンバーを削除できるのはグループホストだけです。": "Only the group host can remove members.",
    "ホスト自身は削除できません。グループを解散してください。": "The host cannot be removed. Please disband the group instead.",
    "対象のメンバーが見つかりません。": "The member was not found.",
    "グループに参加していません。": "You are not in a group.",
    "グループを解散しました。": "The group was disbanded.",
    "グループから退出しました。": "You left the group.",
    "課題名は1〜255文字で入力してください。": "Task names must be 1–255 characters.",
    "科目が正しくありません。": "The subject is invalid.",
    "提出期限が正しくありません。": "The deadline is invalid.",
    "所要時間は1分以上で入力してください。": "The required time must be at least 1 minute.",
    "難易度が正しくありません。": "The difficulty is invalid.",
    "【成功】グループに新しい課題を登録しました！": "[Success] Added a new task to the group!",
    "【成功】新しい課題を登録しました！": "[Success] Added a new task!",
    "対象の課題が見つかりません。": "The task was not found.",
    "この課題を変更・削除できるのは、登録した本人かグループホストだけです。": "Only the person who added this task or the group host can edit or delete it.",
    "課題を削除しました。": "Task deleted.",
    "課題を更新しました。": "Task updated.",
    "この課題はすでに完了しているか、完了できない課題です。": "This task is already completed or cannot be completed.",
    "処理できませんでした。": "Could not process the request.",
    "完了できませんでした": "Could not complete the task.",
    "グループ共有": "Group Sharing",
    "グループを作成するか、グループコードで参加すると、課題をメンバーと共有できます。": "Create a group or join with a group code to share tasks with members.",
    "グループを作成": "Create a Group",
    "あなたのユーザー名（ホストになります）": "Your user name (you will be the host)",
    "例：たろう": "e.g. Taro",
    "グループ名（省略可）": "Group name (optional)",
    "例：3年A組 受験勉強": "e.g. Exam Prep Class 3A",
    "作成してコードを発行": "Create and get a code",
    "グループに参加": "Join a Group",
    "グループコード（合言葉）": "Group code (passphrase)",
    "例：AB3XK7": "e.g. AB3XK7",
    "あなたのユーザー名": "Your user name",
    "例：はなこ": "e.g. Hanako",
    "参加する": "Join",
    "ホスト": "Host",
    "メンバー": "Member",
    "あなたの名前:": "Your name:",
    "グループコード:": "Group code:",
    "コピー": "Copy",
    "コピーしました": "Copied",
    "コードをコピーしてください": "Please copy the code",
    "あなた": "You",
    "削除": "Delete",
    "グループを解散すると、グループの課題もすべて削除されます。よろしいですか？": "Disbanding the group also deletes all of its tasks. Are you sure?",
    "このグループから退出しますか？": "Leave this group?",
    "グループを解散する": "Disband the Group",
    "グループから退出する": "Leave the Group",
    "個人の課題": "Personal Tasks",
    "新規課題を登録": "Add a New Task",
    "課題名": "Task name",
    "例：数学プリント P.24": "e.g. Math worksheet p.24",
    "科目": "Subject",
    "提出期限": "Deadline",
    "所要時間 (分)": "Time required (min)",
    "例：30": "e.g. 30",
    "難易度": "Difficulty",
    "簡単": "Easy",
    "普通": "Normal",
    "難しい": "Hard",
    "登録先": "Add to",
    "個人": "Personal",
    "課題を登録する": "Add Task",
    "編集をやめる": "Cancel Editing",
    "課題を編集": "Edit Task",
    "変更を保存": "Save Changes",
    "グループの課題一覧（あなたの未完了分）": "Group Tasks (your open tasks)",
    "課題一覧": "Task List",
    "デフォルトでは「提出期限が近い順」が設定されています。期日が同じ場合は「算出スコアが高い順」に並びます。": "By default, tasks are sorted by the nearest deadline. Tasks with the same deadline are sorted by the highest calculated score.",
    "課題を達成すると、算出スコアと同量のスコアを獲得できます。": "Completing a task earns you score equal to its calculated score.",
    "（100スコア = 1ポイント）": "(100 score = 1 point)",
    "並び替え（オプション）": "Sort (option)",
    "優先順位順（デフォルト）": "By priority (default)",
    "算出スコア：高い順": "Calculated score: high to low",
    "算出スコア：低い順": "Calculated score: low to high",
    "所要時間：短い順": "Time required: short to long",
    "所要時間：長い順": "Time required: long to short",
    "難易度：低い順": "Difficulty: low to high",
    "難易度：高い順": "Difficulty: high to low",
    "優先順位": "Priority",
    "所要時間": "Time required",
    "算出スコア": "Calculated score",
    "登録者": "Added by",
    "完了状況": "Completion",
    "アクション": "Actions",
    "未完了の課題はありません": "No open tasks",
    "期限超過": "Overdue",
    "本日締切": "Due today",
    "締切間近": "Due soon",
    "（退会済み）": "(left the group)",
    "完了": "Done",
    "この課題を削除しますか？": "Delete this task?",
    "科目別の未完了課題": "Open Tasks by Subject",
    "課題 - 勉強大好きアプリ": "Tasks - Study Lover App",
    "勉強タイマー": "Study Timer",
    "記録": "Log",
    "今日": "Today",
    "分": "min",
    "リセット": "Reset",
    "スタート": "Start",
    "時間・通知の設定": "Time & notification settings",
    "終了後、自動で次を開始": "Automatically start the next phase after finishing",
    "通知を許可": "Allow notifications",
    "勉強と休憩を合わせて30分ごとに1ポイント（25分+5分で1ポイント）。3ポイントで進化レベルが1上がり、生き物が1匹増えます。": "You earn 1 point for every 30 minutes of study + break (25 + 5 min = 1 point). Every 3 points raises your evolution level by 1 and adds one creature.",
    "今日(分)": "Today (min)",
    "7日間(分)": "Last 7 days (min)",
    "今月(分)": "This month (min)",
    "連続(日)": "Streak (days)",
    "勉強時間の推移": "Study time trend",
    "7日": "7 days",
    "30日": "30 days",
    "日ごとの勉強時間": "Study time per day",
    "勉強時間": "Study time",
    "平均": "Average",
    "前の月": "Previous month",
    "次の月": "Next month",
    "勉強中": "Studying",
    "休憩中": "On break",
    "勉強": "Study",
    "休憩": "Break",
    "非対応のブラウザです": "Unsupported browser",
    "許可済み": "Allowed",
    "ブロック中（ブラウザ設定で解除）": "Blocked (change in browser settings)",
    "未許可": "Not allowed",
    "通知テスト": "Test notification",
    "この通知が出れば設定OKです": "If you see this, notifications are working.",
    "このブラウザは画面の消灯防止に非対応です": "This browser does not support keeping the screen awake.",
    "タイマー中は画面を消灯しません": "The screen stays on while the timer runs.",
    "消灯防止を準備中…": "Preparing to keep the screen on…",
    "一時停止": "Pause",
    "再開": "Resume",
    "休憩へ進む": "Go to break",
    "勉強へ進む": "Back to study",
    "予定の時間です。区切りがついたら「休憩へ進む」を押そう": "Time is up. When you reach a good stopping point, press \"Go to break\".",
    "休憩の予定時間です。「勉強へ進む」を押そう": "Break time is up. Press \"Back to study\".",
    "勉強の予定時間です": "Study time is up",
    "休憩の予定時間です": "Break time is up",
    "休憩おわり！勉強を再開しよう": "Break over! Back to studying.",
    "勉強おわり！": "Study finished!",
    "休憩おわり！": "Break over!",
    "小さな一歩も、積み重ねれば海になる。": "Even small steps become a sea when they pile up.",
    "今日の25分が、明日の自信になる。": "Today's 25 minutes become tomorrow's confidence.",
    "完璧より、続けることのほうが強い。": "Continuing is stronger than being perfect.",
    "日": "Sun",
    "月": "Mon",
    "火": "Tue",
    "水": "Wed",
    "木": "Thu",
    "金": "Fri",
    "土": "Sat",
    "設定 - 勉強大好きアプリ": "Settings - Study Lover App",
    "言語": "Language",
    "切り替えると、画面が自動で読み込み直されます。": "The page reloads automatically when you switch.",
    "サウンド": "Sound",
    "BGMなし": "No BGM",
    "ゲーム内BGM": "In-game BGM",
    "BGMの音源がまだ置かれていません（audio フォルダに bgm_1.mp3〜bgm_3.mp3 を置くと選べます）。": "No BGM files have been added yet (put bgm_1.mp3 to bgm_3.mp3 in the audio folder to choose them).",
    "（音源なし）": "(no audio file)",
    "ミュート": "Mute",
    "BGMと効果音をすべて消す": "Turn off all BGM and sound effects",
    "BGMの音量": "BGM volume",
    "効果音の音量": "Sound effects volume",
    "効果音を試す": "Test sound effect",
    "音量やミュートの設定は、このブラウザに保存されます。": "Volume and mute settings are saved in this browser.",
    "設定をもとに戻す": "Restore Settings",
    "言語・BGM・音量・ミュートを、最初の状態に戻します。": "Resets language, BGM, volume and mute to their defaults.",
    "設定を初期値に戻す": "Reset Settings to Defaults",
    "ゲームの進行をリセット": "Reset Game Progress",
    "ポイント・進化レベル・歴史を消して、最初の状態に戻します。課題・グループ・手紙は消えません。この操作は取り消せません。": "Deletes your points, evolution level and history and returns the game to its initial state. Tasks, groups and letters are kept. This cannot be undone.",
    "タイマーの学習記録（グラフ）も消す": "Also delete the timer study log (graphs)",
    "言語・BGM・音量・ミュートを、最初の状態に戻します。よろしいですか？": "Reset language, BGM, volume and mute to their defaults?",
    "もとに戻す": "Restore",
    "ポイント・進化レベル・歴史をすべて消します。この操作は取り消せません。よろしいですか？": "This deletes all points, evolution level and history. It cannot be undone. Continue?",
    "リセットする": "Reset",
    "リセットに失敗しました": "Reset failed",
    "ゲームの進行をリセットしました。": "Game progress has been reset.",
    "問い合わせ": "Contact",
    "不具合・要望・質問などを送れます。送った内容は開発チームが確認します。": "Send bug reports, requests or questions. The development team will read them.",
    "種類": "Type",
    "不具合の報告": "Bug report",
    "要望": "Request",
    "その他": "Other",
    "内容": "Details",
    "例：タイマーが終わっても音が鳴りません": "e.g. No sound plays when the timer ends",
    "返信用のメールアドレス（任意）": "Reply email address (optional)",
    "送信する": "Send",
    "メールで送る": "Send by Email",
    "送信した問い合わせ": "Your Inquiries",
    "まだ問い合わせを送っていません。": "You haven't sent any inquiries yet.",
    "問い合わせを送信しました。ありがとうございます。": "Your inquiry was sent. Thank you.",
    "問い合わせの内容を入力してください。": "Please enter the details of your inquiry.",
    "メールアドレスの形式が正しくありません。": "The email address format is invalid.",
    "短時間に送信しすぎです。しばらくしてからもう一度お試しください。": "Too many submissions in a short time. Please try again later.",
    "クレジット": "Credits",
    "このアプリで使っている音・画像・フォントの出どころです。": "Where the sounds, images and fonts used in this app come from.",
    "効果音": "Sound effects",
    "画像": "Images",
    "フォント・ライブラリ": "Fonts & libraries",
    "用途": "Used for",
    "曲名・素材名": "Title",
    "作者・提供元": "Author / source",
    "入手先": "Source",
    "利用条件・ライセンス": "License / terms",
    "（未入力）": "(not filled in yet)",
    "ゲーム内BGM（勉強中のBGMも兼ねる）": "In-game BGM (also used while studying)",
    "タイマー（予定の時間になったとき）": "Timer (when time is up)",
    "メール開封（ボトルを拾ったとき）": "Mail opened (when you pick up a bottle)",
    "メール送信（ボトルを流したとき）": "Mail sent (when you send a bottle)",
    "課題完了": "Task completed",
    "進化（新たな生物が誕生したとき）": "Evolution (when a new creature is born)",
    "ポイント獲得": "Points earned",
    "背景（海域ごとに5段階）": "Backgrounds (5 levels per sea area)",
    "生き物": "Creatures",
    "画面のフォント": "App font",
    "グラフの表示": "Chart display"
  };

  let tr = function (s) { return s; };            // 日本語のときは何もしない

  // 数字や名前が混ざる文言
  const RULES = [
    [/^キャプションは(\d+)文字以内で入力してください。$/, function (m) { return 'Captions must be ' + m[1] + ' characters or fewer.'; }],
    [/^問い合わせの内容は(\d+)文字以内で入力してください。$/, function (m) { return 'Inquiries must be ' + m[1] + ' characters or fewer.'; }],
    [/^グループの作成に失敗しました: (.*)$/, function (m) { return 'Failed to create the group: ' + m[1]; }],
    [/^グループを作成しました。グループコード「(.*)」をメンバーに共有してください。$/, function (m) { return 'Group created. Share the group code "' + m[1] + '" with your members.'; }],
    [/^「(.*)」に参加しました。$/, function (m) { return 'Joined "' + m[1] + '".'; }],
    [/^削除に失敗しました: (.*)$/, function (m) { return 'Failed to delete: ' + m[1]; }],
    [/^処理に失敗しました: (.*)$/, function (m) { return 'Operation failed: ' + m[1]; }],
    [/^エラーが発生しました: (.*)$/, function (m) { return 'An error occurred: ' + m[1]; }],
    [/^リセットに失敗しました: (.*)$/, function (m) { return 'Reset failed: ' + m[1]; }],
    [/^(.*) さんをグループから削除しました。$/, function (m) { return 'Removed ' + m[1] + ' from the group.'; }],
    [/^(.*) さんをグループから削除しますか？$/, function (m) { return 'Remove ' + m[1] + ' from the group?'; }],
    [/^課題「(.*)」を完了しました。\+(.+) ポイント$/, function (m) { return 'Completed "' + m[1] + '". +' + m[2] + ' points'; }],
    [/^\+([\d.]+) ポイント　(.*)$/, function (m) { return '+' + m[1] + ' pts　' + tr(m[2]); }],
    [/^\+([\d.]+) ポイント$/, function (m) { return '+' + m[1] + ' pts'; }],
    [/^合計 ([\d.]+) ポイント$/, function (m) { return 'Total ' + m[1] + ' points'; }],
    [/^ポモドーロ (\d+)分$/, function (m) { return 'Pomodoro ' + m[1] + ' min'; }],
    [/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})　(.+)$/, function (m) { return m[1] + '　' + m[2].split('　').map(function (p) { return tr(p); }).join('　'); }],
    [/^(.+) ／ (\d+)人が拾いました$/, function (m) { return m[1] + ' ／ Picked up by ' + m[2]; }],
    [/^勉強と休憩の (\d+) 分ぶん$/, function (m) { return m[1] + ' min of study and breaks'; }],
    [/^算出スコア (\d+) p → ([\d.]+) ポイントに換算$/, function (m) { return 'Score ' + m[1] + ' p → converted to ' + m[2] + ' pts'; }],
    [/^新たな生物が誕生しました（(\d+) 匹）$/, function (m) { return 'New creatures born (' + m[1] + ')'; }],
    [/^次の進化まで (\d+)%$/, function (m) { return m[1] + '% until the next evolution'; }],
    [/^次の進化まで（(.+)）$/, function (m) { return 'Until next evolution (' + tr(m[1]) + ')'; }],
    [/^(タイマー|課題)：(.*)$/, function (m) { return tr(m[1]) + ': ' + tr(m[2]); }],
    [/^(すべて|タイマー|課題)（(\d+)）$/, function (m) { return tr(m[1]) + ' (' + m[2] + ')'; }],
    [/^メンバー（(\d+)人）$/, function (m) { return 'Members (' + m[1] + ')'; }],
    [/^グループの課題（(.+)）$/, function (m) { return 'Group Tasks (' + m[1] + ')'; }],
    [/^グループ（(.+)）$/, function (m) { return 'Group (' + m[1] + ')'; }],
    [/^(\d+) \/ (\d+) 人$/, function (m) { return m[1] + ' / ' + m[2]; }],
    [/^レベル (\d+)$/, function (m) { return 'Level ' + m[1]; }],
    [/^(\d): (簡単|普通|難しい)$/, function (m) { return m[1] + ': ' + tr(m[2]); }],
    [/^(\d+)\s*分$/, function (m) { return m[1] + ' min'; }],
    [/^(\d+)分\/日$/, function (m) { return m[1] + ' min/day'; }],
    [/^\+(\S+) 超過$/, function (m) { return '+' + m[1] + ' over'; }],
    [/^（あと(\d+)分で1ポイント）$/, function (m) { return '(' + m[1] + ' min until the next point)'; }],
    [/^(\d+)分 おつかれさま！(?:（予定より(\d+)分超過）)?休憩しよう$/, function (m) { return 'Great work, ' + m[1] + ' min!' + (m[2] ? ' (' + m[2] + ' min over)' : '') + ' Take a break.'; }],
    [/^(\d+)年(\d+)月$/, function (m) { return MONTHS[parseInt(m[2], 10) - 1] + ' ' + m[1]; }],
    [/^生物 (\d+) \/ (\d+)　背景 Lv\.(\d+)$/, function (m) { return 'Creatures ' + m[1] + ' / ' + m[2] + '　Background Lv.' + m[3]; }],
    [/^未解放（進化レベル(\d+)で解放）$/, function (m) { return 'Locked (unlocks at evolution level ' + m[1] + ')'; }],
    [/^([▲▼◀▶]) (.+)$/, function (m) { return m[1] + ' ' + tr(m[2]); }],
    [/^(.+) を送りました$/, function (m) { return 'Sent "' + tr(m[1]) + '"'; }]
  ];

  function lookup(core) {
    if (Object.prototype.hasOwnProperty.call(EN, core)) return EN[core];
    for (let i = 0; i < RULES.length; i++) {
      const m = RULES[i][0].exec(core);
      if (m) return RULES[i][1](m);
    }
    return null;
  }
  // 1つの文言を訳す。見つからなければそのまま返す。複数の文がつながっているときは1文ずつ訳す
  function translate(s) {
    if (typeof s !== 'string' || !JA_RE.test(s)) return s;
    const lead = s.match(/^\s*/)[0], trail = s.match(/\s*$/)[0];
    const core = s.trim();
    let out = lookup(core);
    if (out === null) out = lookup(core.replace(/\s+/g, ' '));
    if (out === null) {                                            // 複数の文がつながっているとき：いちばん長く一致する並びから順に訳す
      const parts = core.match(/[^。！？]+[。！？]?\s*/g) || [];
      if (parts.length > 1) {
        let hit = false, i = 0;
        const done = [];
        while (i < parts.length) {
          let found = null, end = i + 1;
          for (let j = parts.length; j > i; j--) {
            const o = lookup(parts.slice(i, j).join('').trim().replace(/\s+/g, ' '));
            if (o !== null) { found = o; end = j; break; }
          }
          if (found !== null) hit = true;
          done.push(found !== null ? found : parts[i].trim());
          i = end;
        }
        if (hit) out = done.join(' ');
      }
    }
    return out === null ? s : lead + out + trail;
  }

  // ---------- 画面の書き換え（English のときだけ） ----------
  const ATTRS = ['placeholder', 'title', 'aria-label', 'alt'];
  const SKIP = { SCRIPT: 1, STYLE: 1, TEXTAREA: 1, NOSCRIPT: 1 };

  function inNoI18n(node) {
    const el = node && node.nodeType === 1 ? node : (node && node.parentElement);
    return !!(el && el.closest && el.closest('[data-no-i18n]'));
  }
  function doText(node) {
    if (inNoI18n(node)) return;
    const v = node.nodeValue, o = translate(v);
    if (o !== v) node.nodeValue = o;
  }
  function doAttrs(el) {
    for (let i = 0; i < ATTRS.length; i++) {
      const a = ATTRS[i];
      if (!el.hasAttribute(a)) continue;
      const v = el.getAttribute(a), o = translate(v);
      if (o !== v) el.setAttribute(a, o);
    }
  }
  function walk(node) {
    if (node.nodeType === 3) { doText(node); return; }
    if (node.nodeType !== 1 || node.hasAttribute('data-no-i18n')) return;
    doAttrs(node);                                              // placeholder などの属性は、textarea でも訳す
    if (SKIP[node.tagName]) return;                              // textarea の中身（ユーザーの入力）は訳さない
    for (let c = node.firstChild; c; c = c.nextSibling) walk(c);
  }

  function start() {
    document.documentElement.lang = 'en';
    document.documentElement.classList.add('i18n-wait');          // 訳し終わるまで日本語が一瞬見えないように隠す
    const reveal = function () { document.documentElement.classList.remove('i18n-wait'); };
    setTimeout(reveal, 1500);                                      // 何かあっても画面が隠れたままにならないように
    document.addEventListener('DOMContentLoaded', function () {
      try {
        document.title = translate(document.title);
        walk(document.documentElement);
        new MutationObserver(function (muts) {
          muts.forEach(function (m) {
            if (m.type === 'childList') {
              m.addedNodes.forEach(function (n) { if (!inNoI18n(m.target)) walk(n); });
            } else if (m.type === 'characterData') {
              doText(m.target);
            } else if (m.type === 'attributes') {
              doAttrs(m.target);
            }
          });
        }).observe(document.documentElement, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ATTRS });
      } finally {
        reveal();
      }
    });
    // confirm / alert / prompt の文言も訳す
    ['confirm', 'alert', 'prompt'].forEach(function (name) {
      const orig = window[name];
      window[name] = function (msg, def) { return orig.call(window, translate(msg), def); };
    });
  }

  if (lang === 'en') { tr = translate; start(); }

  window.I18N = {
    lang: lang,
    getLang: getLang,
    setLang: setLang,
    t: function (s) { return tr(s); },
    _entries: EN, _rules: RULES, _translate: translate     // テスト用
  };
})();
