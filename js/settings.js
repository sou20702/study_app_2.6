// ==========================================================
// 設定タブ：言語・サウンド・リセット・問い合わせ
// ==========================================================
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  // ---------- 言語（切り替えると画面を読み込み直す） ----------
  const lang = window.I18N ? I18N.getLang() : 'ja';
  document.querySelectorAll('input[name=lang]').forEach(function (r) {
    r.checked = r.value === lang;
    r.addEventListener('change', function () { if (r.checked && window.I18N) I18N.setLang(r.value); });
  });

  // ---------- サウンド ----------
  function tone(vol) {                                   // 音源が置かれていなくても、音量が確かめられるように短い音を鳴らす
    try {
      const ac = new (window.AudioContext || window.webkitAudioContext)();
      const o = ac.createOscillator(), g = ac.createGain();
      o.frequency.value = 660; o.connect(g); g.connect(ac.destination);
      g.gain.setValueAtTime(0.25 * (vol == null ? 0.8 : vol), ac.currentTime);
      o.start(); o.stop(ac.currentTime + 0.25);
    } catch (e) {}
  }
  function showVol() {
    $('bgmVolVal').textContent = $('bgmVol').value + '%';
    $('seVolVal').textContent = $('seVol').value + '%';
  }
  function syncControls() {
    const s = Sound.getSettings();
    $('muteBox').checked = s.muted;
    $('bgmVol').value = Math.round(s.bgmVol * 100);
    $('seVol').value = Math.round(s.seVol * 100);
    showVol();
  }
  if (window.Sound) {
    syncControls();
    const tt = function (s) { return window.I18N ? I18N.t(s) : s; };
    Sound.trackStatus().then(function (list) {            // 3つのBGMの枠をすべて表示（音源がまだ無いものは選べない）
      const sel = $('bgmTrack');
      list.forEach(function (t) {
        const o = document.createElement('option');
        const name = (window.BGM_NAMES && BGM_NAMES[t.id]) || t.name;
        o.value = t.id;
        o.setAttribute('data-no-i18n', '');                // 曲名は訳さない
        o.textContent = name + (t.available ? '' : '　' + tt('（音源なし）'));
        o.disabled = !t.available;
        sel.appendChild(o);
      });
      $('bgmNone').hidden = list.some(function (t) { return t.available; });
      const cur = Sound.getSettings().track;
      sel.value = list.some(function (t) { return t.id === cur && t.available; }) ? cur : 'off';
    });
    $('bgmTrack').addEventListener('change', function () { Sound.set({ track: this.value }); });
    $('muteBox').addEventListener('change', function () { Sound.set({ muted: this.checked }); });
    $('bgmVol').addEventListener('input', function () { showVol(); Sound.set({ bgmVol: this.value / 100 }); });
    $('seVol').addEventListener('input', function () { showVol(); Sound.set({ seVol: this.value / 100 }); });
    $('seVol').addEventListener('change', function () { Sound.enqueue('point', tone); });
    $('seTest').addEventListener('click', function () { Sound.enqueue('point', tone); });
  }

  // ---------- リセット ----------
  $('resetSettings').addEventListener('click', async function () {
    const ok = await App.confirm('言語・BGM・音量・ミュートを、最初の状態に戻します。よろしいですか？', 'もとに戻す');
    if (!ok) return;
    if (window.Sound) Sound.reset();
    try { localStorage.removeItem('study_lang'); } catch (e) {}
    location.reload();
  });
  $('resetProgress').addEventListener('click', async function () {
    const ok = await App.confirm('ポイント・進化レベル・歴史をすべて消します。この操作は取り消せません。よろしいですか？', 'リセットする');
    if (!ok) return;
    const r = await App.post('api/reset_progress.php', {});
    if (!r.ok) { App.toast(r.message || 'リセットに失敗しました', 'error'); return; }
    if ($('resetTimerLog').checked) { try { localStorage.removeItem('log'); } catch (e) {} }
    App.toast(r.message || 'ゲームの進行をリセットしました。', 'success');
  });

  // ---------- 問い合わせ ----------
  const CATS = { bug: '不具合の報告', request: '要望', other: 'その他' };
  $('inquiryForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = $('inqSend');
    btn.disabled = true;
    const r = await App.post('api/inquiry.php', {
      category: $('inqCategory').value,
      message: $('inqMessage').value,
      reply_email: $('inqEmail').value
    });
    btn.disabled = false;
    if (!r.ok) { App.toast(r.message || '送信できませんでした', 'error'); return; }
    const item = document.createElement('div');
    item.className = 'history-item';
    const d = document.createElement('div');
    d.className = 'history-date';
    d.textContent = r.item.created_at + '　' + (CATS[r.item.category] || CATS.other);
    const m = document.createElement('div');
    m.className = 'history-msg';
    m.setAttribute('data-no-i18n', '');
    r.item.message.split('\n').forEach(function (line, i) {
      if (i) m.appendChild(document.createElement('br'));
      m.appendChild(document.createTextNode(line));
    });
    item.appendChild(d); item.appendChild(m);
    $('inqList').prepend(item);
    $('inqEmpty').hidden = true;
    $('inqMessage').value = '';
    App.toast('問い合わせを送信しました。ありがとうございます。', 'success');
  });
});
