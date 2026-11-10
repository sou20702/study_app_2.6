// ==========================================
// 全ページ共通：トースト表示・API呼び出し・ポイント獲得ポップアップ
// ==========================================
(function () {
  'use strict';

  const $ = (id) => document.getElementById(id);

  // ---------- トースト（alert の代わり。画面内で完結） ----------
  function toast(msg, type) {
    const root = $('toastRoot');
    if (!root) return;
    const el = document.createElement('div');
    el.className = 'toast ' + (type || 'info');
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.textContent = msg;
    root.appendChild(el);
    setTimeout(() => el.remove(), type === 'error' ? 6000 : 3500);
  }

  // ---------- fetch で POST（失敗時はトーストで知らせる） ----------
  async function post(url, data) {
    const body = new URLSearchParams(data || {});
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
        body: body,
        credentials: 'same-origin'
      });
      let json = null;
      try { json = await res.json(); } catch (e) {}
      if (!json) return { ok: false, message: '通信に失敗しました。もう一度お試しください。' };
      return json;
    } catch (e) {
      return { ok: false, message: 'サーバーに接続できません。起動状態を確認してください。' };
    }
  }

  // ---------- キャプション保存 ----------
  async function saveCaption(id, caption) {
    const r = await post('api/history_caption.php', { id: id, caption: caption });
    if (r.ok) toast('キャプションを保存しました', 'success');
    else toast(r.message || '保存に失敗しました', 'error');
    return r;
  }

  // ---------- ポイント獲得ポップアップ ----------
  let currentId = null;
  let lastFocus = null;

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function showPoints(res) {
    const modal = $('pointModal');
    if (!modal || !res || !res.awarded) return;
    currentId = res.history_id;
    lastFocus = document.activeElement;
    const src = res.source === 'timer' ? 'タイマー' : '課題';
    let html = '<p class="pm-points">+' + esc(res.points_text) + ' ポイント</p>' +
               '<p class="pm-sub">' + esc(src) + '：' + esc(res.title) + '</p>' +
               '<p class="pm-sub">合計 ' + esc(res.total_points) + ' ポイント</p>';
    if (res.stage_ups && res.stage_ups.length) {
      res.stage_ups.forEach(function (u) {
        html += '<div class="pm-up">' +
                '<p>新たな生物が誕生しました。</p>' +
                (u.bg_up ? '<p>時がたち、海域が変化しました。</p>' : '') +
                (u.unlocked_now ? '<p>新しい海域が解放されました。</p>' : '') +
                '<p class="pm-area">' + esc(u.name) + '</p>' +
                '</div>';
      });
    } else {
      html += '<p class="pm-sub">次の進化まで ' + res.progress_pct + '%</p>';
    }
    $('pmBody').innerHTML = html;
    $('pmCaption').value = '';
    modal.hidden = false;
    $('pmCaption').focus();
    if (window.Sound) {                                   // ポイント獲得音 →（進化レベルが上がったら）進化の音
      Sound.enqueue('point');
      if (res.stage_ups && res.stage_ups.length) Sound.enqueue('stage_up');
    }
  }

  function closeModal() {
    const modal = $('pointModal');
    if (!modal) return;
    modal.hidden = true;
    currentId = null;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  document.addEventListener('DOMContentLoaded', function () {
    const modal = $('pointModal');
    if (!modal) return;
    $('pmClose').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) closeModal();
    });
    $('pmSave').addEventListener('click', async function () {
      const text = $('pmCaption').value.trim();
      if (!currentId) return;
      if (text === '') { closeModal(); return; }   // 空ならそのまま閉じる
      const r = await saveCaption(currentId, text);
      if (r.ok) closeModal();
    });
    $('pmCaption').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); $('pmSave').click(); }
    });
  });

  // ---------- 確認ダイアログ（画面内。OKなら true を返す） ----------
  function confirmDialog(message, okLabel) {
    return new Promise(function (resolve) {
      const modal = $('confirmModal');
      if (!modal) { resolve(window.confirm(message)); return; }
      const prev = document.activeElement;
      $('cfMsg').textContent = message;
      $('cfOk').textContent = okLabel || '実行する';
      modal.hidden = false;
      $('cfCancel').focus();
      const done = function (v) {
        modal.hidden = true;
        $('cfOk').removeEventListener('click', ok);
        $('cfCancel').removeEventListener('click', no);
        modal.removeEventListener('click', back);
        document.removeEventListener('keydown', key);
        if (prev && prev.focus) prev.focus();
        resolve(v);
      };
      const ok = function () { done(true); };
      const no = function () { done(false); };
      const back = function (e) { if (e.target === modal) done(false); };
      const key = function (e) { if (e.key === 'Escape') done(false); };
      $('cfOk').addEventListener('click', ok);
      $('cfCancel').addEventListener('click', no);
      modal.addEventListener('click', back);
      document.addEventListener('keydown', key);
    });
  }

  // data-confirm="確認の文" を持つフォームは、送信の前に画面内ダイアログで確認する（data-confirm-ok でOKボタンの文言）
  document.addEventListener('submit', async function (e) {
    const f = e.target;
    const msg = f && f.getAttribute ? f.getAttribute('data-confirm') : null;
    if (!msg) return;
    e.preventDefault();
    const ok = await confirmDialog(msg, f.getAttribute('data-confirm-ok') || undefined);
    if (ok) f.submit();                  // submit() は submit イベントを再発火しないので、二重に確認されない（隠し項目 action などはそのまま送られる）
  }, true);

  window.App = { toast: toast, post: post, saveCaption: saveCaption, showPoints: showPoints, confirm: confirmDialog };
})();
