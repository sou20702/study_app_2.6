// ==========================================================
// BGM・効果音（※音の「枠」だけを用意してあります）
//
// audio/ フォルダに下のファイル名で音源を置くだけで鳴ります。
// 置かれていない音は、何も鳴らさずに進みます（エラーにはなりません）。
//
//   効果音   audio/se_timer.mp3       タイマー（予定の時間になったとき）
//            audio/se_mail_open.mp3   メール開封（ボトルを拾ったとき）
//            audio/se_mail_send.mp3   メール送信（ボトルを流したとき）
//            audio/se_task_done.mp3   課題完了
//            audio/se_stage_up.mp3    進化（新たな生物の誕生）
//            audio/se_point.mp3       ポイント獲得
//   BGM      audio/bgm_1.mp3 / bgm_2.mp3 / bgm_3.mp3   ゲーム内BGM（3種類）。勉強中（タイマー）のBGMも兼ねます
//            （bgm_game.mp3 という名前でも、BGM 1 として鳴ります）
//
// 設定（ミュート・音量・BGMの選択）は設定タブで変更でき、このブラウザに保存されます。
// BGMの名前は、config/credits.php の title に書くと、設定タブの選択肢に反映されます。
// 形式は mp3 がおすすめです（拡張子を変えるときは、下の SE_FILES / BGM_TRACKS を書き換えてください）。
// ==========================================================
(function () {
  'use strict';

  const SE_FILES = {
    timer:     'audio/se_timer.mp3',
    mail_open: 'audio/se_mail_open.mp3',
    mail_send: 'audio/se_mail_send.mp3',
    task_done: 'audio/se_task_done.mp3',
    stage_up:  'audio/se_stage_up.mp3',
    point:     'audio/se_point.mp3'
  };
  const BGM_TRACKS = [                              // name は初期の名前（変えたいときは config/credits.php の title に書く）
    { id: 'bgm1', name: 'BGM 1', file: 'audio/bgm_1.mp3', alt: 'audio/bgm_game.mp3' },
    { id: 'bgm2', name: 'BGM 2', file: 'audio/bgm_2.mp3' },
    { id: 'bgm3', name: 'BGM 3', file: 'audio/bgm_3.mp3' }
  ];
  const DEFAULTS = { muted: false, bgmVol: 0.4, seVol: 0.8, track: 'off' };    // track: 'off' でBGMなし（初期値はBGMなし。設定タブで選ぶと鳴る）
  const KEY = 'study_sound', POS_KEY = 'study_bgm_pos';

  // ---------- 設定の保存 ----------
  function clamp01(v, d) { v = parseFloat(v); return isFinite(v) ? Math.min(1, Math.max(0, v)) : d; }
  function load() {
    let s = {};
    try { s = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) {}
    return {
      muted: !!s.muted,
      bgmVol: clamp01(s.bgmVol, DEFAULTS.bgmVol),
      seVol: clamp01(s.seVol, DEFAULTS.seVol),
      track: typeof s.track === 'string' ? (s.track === 'game' ? 'bgm1' : s.track) : DEFAULTS.track   // 古い設定名 game は bgm1 として扱う
    };
  }
  let settings = load();
  function save() { try { localStorage.setItem(KEY, JSON.stringify(settings)); } catch (e) {} }

  // ---------- BGM ----------
  let bgm = null, bgmId = null, waiting = false, posTimer = null;

  function trackById(id) { return BGM_TRACKS.find(function (t) { return t.id === id; }) || null; }
  function savePos() {
    if (!bgm || !bgmId) return;
    try { sessionStorage.setItem(POS_KEY, JSON.stringify({ id: bgmId, t: bgm.currentTime })); } catch (e) {}
  }
  function stopBgm() {
    if (posTimer) { clearInterval(posTimer); posTimer = null; }
    if (bgm) { savePos(); bgm.pause(); }
  }
  function waitForGesture() {                       // ブラウザは、操作するまで自動再生を許可しないため、最初の操作で再生を始める
    if (waiting) return;
    waiting = true;
    const go = function () {
      waiting = false;
      ['pointerdown', 'keydown', 'touchstart'].forEach(function (ev) { document.removeEventListener(ev, go, true); });
      tryPlay();
    };
    ['pointerdown', 'keydown', 'touchstart'].forEach(function (ev) { document.addEventListener(ev, go, true); });
  }
  function tryPlay() {
    if (!bgm || settings.muted) return;
    const p = bgm.play();
    if (p && p.catch) p.catch(function () { waitForGesture(); });
    if (!posTimer) posTimer = setInterval(savePos, 2000);   // 画面を移動しても続きから流れるように、位置を覚えておく
  }
  function applyBgm() {
    if (settings.muted || settings.track === 'off') { stopBgm(); return; }
    const tr = trackById(settings.track);
    if (!tr) { stopBgm(); return; }
    if (!bgm || bgmId !== tr.id) {
      stopBgm();
      const a = new Audio(tr.file);
      a.loop = true;
      a.preload = 'auto';
      bgm = a; bgmId = tr.id;
      a.addEventListener('error', function () {
        if (bgm !== a) return;
        if (tr.alt && !a._alt) { a._alt = true; a.src = tr.alt; tryPlay(); return; }   // 別名のファイルがあれば、そちらを試す
        bgm = null; bgmId = null;                                                       // ファイルが無ければ、何も鳴らさない
      });
      a.addEventListener('loadedmetadata', function () {
        try {
          const saved = JSON.parse(sessionStorage.getItem(POS_KEY) || 'null');
          if (saved && saved.id === tr.id && saved.t > 0 && saved.t < a.duration) a.currentTime = saved.t;
        } catch (e) {}
      }, { once: true });
    }
    bgm.volume = settings.bgmVol;
    tryPlay();
  }

  // ---------- 効果音（順番に1つずつ鳴らす。重なって聞こえにくくならないように） ----------
  const queue = [];
  let playing = false;

  function playOne(item) {
    return new Promise(function (resolve) {
      if (settings.muted || settings.seVol <= 0) { resolve(); return; }
      const file = SE_FILES[item.name];
      if (!file) { resolve(); return; }
      const a = new Audio(file);
      a.volume = settings.seVol;
      let done = false, tm = null;
      const fin = function () { if (done) return; done = true; clearTimeout(tm); resolve(); };
      tm = setTimeout(fin, 8000);
      a.addEventListener('ended', fin);
      a.addEventListener('error', function () {          // 音源が置かれていないとき：fallback があればそれを鳴らす
        if (!done && item.fallback) { try { item.fallback(settings.seVol); } catch (e) {} }
        fin();
      });
      const p = a.play();
      if (p && p.catch) p.catch(fin);
    });
  }
  function next() {
    const item = queue.shift();
    if (!item) { playing = false; return; }
    playing = true;
    playOne(item).then(next);
  }
  function enqueue(name, fallback) {
    queue.push({ name: name, fallback: fallback });
    if (!playing) next();
  }

  // ---------- 設定タブから使う ----------
  function set(partial) {
    settings = Object.assign({}, settings, partial);
    settings.bgmVol = clamp01(settings.bgmVol, DEFAULTS.bgmVol);
    settings.seVol = clamp01(settings.seVol, DEFAULTS.seVol);
    save();
    applyBgm();
  }
  function probe(file) {                              // 音源ファイルが置かれているか調べる
    return new Promise(function (resolve) {
      const a = new Audio();
      let done = false;
      const fin = function (v) { if (done) return; done = true; resolve(v); };
      a.preload = 'metadata';
      a.addEventListener('loadedmetadata', function () { fin(true); });
      a.addEventListener('error', function () { fin(false); });
      setTimeout(function () { fin(false); }, 5000);
      a.src = file;
    });
  }
  // 3つのBGMの枠について、音源ファイルが置かれているかを調べる
  function trackStatus() {
    return Promise.all(BGM_TRACKS.map(function (t) {
      return probe(t.file)
        .then(function (ok) { return ok || !t.alt ? ok : probe(t.alt); })
        .then(function (ok) { return { id: t.id, name: t.name, available: ok }; });
    }));
  }

  window.addEventListener('pagehide', savePos);
  document.addEventListener('DOMContentLoaded', applyBgm);

  window.Sound = {
    enqueue: enqueue,
    play: enqueue,
    set: set,
    reset: function () { settings = Object.assign({}, DEFAULTS); save(); applyBgm(); },
    getSettings: function () { return Object.assign({}, settings); },
    defaults: Object.assign({}, DEFAULTS),
    tracks: BGM_TRACKS,
    trackStatus: trackStatus
  };
})();
