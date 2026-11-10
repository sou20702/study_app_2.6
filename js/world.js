// ==========================================================
// ホームタブの世界描画（※仮のデモ版。背景と生き物の画像を置くと差し替わります）
//
// 【描画担当の方へ】
//  ■ 背景（100×50px）… 海域ごとに5枚（Lv1〜Lv5）、合計25枚
//      img/backgrounds/{海域キー}_{レベル}.png      例：center_1.png … center_5.png
//      海域キー：center（中央海域） shallow（浅瀬） coral（サンゴ礁） vent（熱水噴出孔） deep（深海エリア）
//      レベルは Lv1 が最初の背景で、生き物が2匹増えるごとに次のレベルへ（0匹→Lv1、2匹→Lv2、4匹→Lv3、6匹→Lv4、8匹→Lv5）。
//      背景の画像が無いレベルは、下の仮の背景（水のグラデーション・海底）が表示されます。
//      泡（ぽこぽこ上がっていく粒）は、背景の画像の上にもそのまま表示されます。
//  ■ 生き物 … 海域ごとに10匹、合計50匹
//      img/creatures/{海域キー}_{番号}.png          例：coral_3.png（番号1〜10。番号の小さい順に誕生します）
//      img/creatures/{名前}.png                     例：fish.png（種類ごとの使い回し。1匹ごとの画像が無い枠だけ使われます）
//        名前：fish coral jelly crab turtle whale angler seaweed starfish tube
//      背景が透明のPNGはそのまま重なり、白い背景のスキャンは白い部分が自動で消えます（乗算合成）。
//      大きさは CREATURE_W（種類ごと）と SLOT_W（1匹ごと）で調整できます。
//  ■ 描画そのものを作り直す場合は draw() / drawProceduralBg() を差し替えてください。
//    世界の状態は window.WORLD_STATE（api/world_state.php と同じ形）で渡されます。
//
// 移動（上下左右の海域）・ミニマップ・矢印ボタンはこのファイルが担当しています。
// 海域は、進化レベルが上がるにつれて解放されます（解放条件は config/game_config.php）。
// ==========================================================
(function () {
  'use strict';

  const STATE = window.WORLD_STATE;
  if (!STATE || !STATE.areas) return;

  const W = 100, H = 50, FLOOR_H = 6, FLOOR_TOP = H - FLOOR_H;     // 論理サイズ（背景画像と同じ 100×50）
  const SCALE = 8;                                                  // 画面用キャンバスは8倍（ドット絵を整数倍できっちり拡大）
  const canvas = document.getElementById('worldCanvas');
  canvas.width = W * SCALE; canvas.height = H * SCALE;
  const ctx = canvas.getContext('2d');
  const bgc = document.createElement('canvas');                     // 仮の背景
  bgc.width = W; bgc.height = H;
  const bgctx = bgc.getContext('2d');
  const fx = document.createElement('canvas');                      // 泡＋ドット絵の生き物（透明な重ね用）
  fx.width = W; fx.height = H;
  const fxctx = fx.getContext('2d');
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---------- 仮の絵（ドット絵）の素材 ----------
  const SPRITES = {
    fish:     { pal: { o: '#f2a65a', d: '#c97a2e', e: '#16314f' }, px: ['...oo....', '.oooooo.o', 'ooeooooo.', '.oooooo.o', '...dd....'] },
    coral:    { pal: { p: '#e0788a' }, px: ['p..p..p', 'p..p..p', 'pp.pp.p', '.ppppp.', '..ppp..', '..ppp..', '..ppp..'] },
    seaweed:  { pal: { g: '#3f9b6b' }, px: ['.g.', 'g..', '.g.', '..g', '.g.', 'g..', '.g.', '.g.'] },
    starfish: { pal: { y: '#f0c75e' }, px: ['...y...', '...y...', 'yyyyyyy', '.yyyyy.', '.yy.yy.', '.y...y.'] },
    jelly:    { pal: { j: '#c9a7e8', w: '#ffffff' }, px: ['.jjjjj.', 'jjjjjjj', 'jjwjwjj', 'jjjjjjj', '.j.j.j.', '.j.j.j.', '..j.j..', '..j.j..'] },
    crab:     { pal: { r: '#d9604a', e: '#16314f' }, px: ['r.......r', 'rr.....rr', '.rrrrrrr.', 'rrerrrerr', '.rrrrrrr.', 'r.r...r.r'] },
    turtle:   { pal: { g: '#4f9d6a', h: '#8fc48a', b: '#8fc48a' }, px: ['....ggg....', '..ggggggg..', 'hhggggggggb', '..ggggggg..', '..b.b.b.b..'] },
    whale:    { pal: { w: '#5a7fa8', l: '#a9c4e0', e: '#16314f' }, px: ['...wwwwww.........', '.wwwwwwwwww....w.w', 'wwewwwwwwwwww.www.', 'wllllllllllwwwww..', '.lllllllllllww....', '..llllllllll......'] },
    angler:   { pal: { a: '#6b4f9e', y: '#ffe27a', e: '#ffffff', t: '#ffffff' }, px: ['yy.........', '..y..aaa...', '...aaaaaaa.', '..aeaaaaaaa', '..attaaaaa.', '...aaaaaa.a', '....aaaa..a'] },
    tube:     { pal: { r: '#e0525a', w: '#f1f1f1' }, px: ['r.r', 'rwr', '.w.', '.w.', 'ww.', '.w.', '.ww', '.w.'] }
  };
  const DWELLERS = { coral: 1, seaweed: 1, starfish: 1, crab: 1, tube: 1 };   // 海底に置く生き物

  // 海域ごとの（仮の）背景の色と、生き物の並び。items の n 番目が、その海域の n 番目に誕生する生き物（最大10匹）
  const THEME = {
    center:  { top: '#3f86c9', bot: '#1f5a96', floor: '#c2b280', floor2: '#a89968', items: ['seaweed', 'fish', 'fish', 'starfish', 'fish', 'turtle', 'jelly', 'seaweed', 'fish', 'whale'] },
    coral:   { top: '#4a9bd0', bot: '#2a6fa8', floor: '#d9c7a0', floor2: '#bba97f', items: ['coral', 'fish', 'coral', 'fish', 'coral', 'crab', 'fish', 'coral', 'turtle', 'fish'] },
    shallow: { top: '#7cc1e6', bot: '#4a9bd0', floor: '#e6d7a8', floor2: '#cdbd8a', items: ['seaweed', 'fish', 'fish', 'starfish', 'crab', 'fish', 'turtle', 'fish', 'seaweed', 'jelly'] },
    deep:    { top: '#16305c', bot: '#0a1a38', floor: '#3a4a63', floor2: '#2c3a52', items: ['jelly', 'angler', 'jelly', 'jelly', 'angler', 'whale', 'jelly', 'angler', 'jelly', 'whale'] },
    vent:    { top: '#12263f', bot: '#0a1626', floor: '#4a3a3a', floor2: '#372b2b', items: ['tube', 'crab', 'tube', 'tube', 'crab', 'angler', 'tube', 'tube', 'crab', 'tube'] }
  };
  const FALLBACK_THEME = THEME.center;

  // ---------- 小さな道具 ----------
  function hash(s) { let h = 2166136261; for (let i = 0; i < s.length; i++) { h ^= s.charCodeAt(i); h = Math.imul(h, 16777619); } return h >>> 0; }
  function rng(seed) { let a = seed; return function () { a |= 0; a = a + 0x6D2B79F5 | 0; let t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
  function rgb(hex) { const n = parseInt(hex.slice(1), 16); return [n >> 16 & 255, n >> 8 & 255, n & 255]; }
  function mix(a, b, t) { const x = rgb(a), y = rgb(b); return 'rgb(' + x.map((v, i) => Math.round(v + (y[i] - v) * t)).join(',') + ')'; }

  function drawSprite(sp, x, y, flip) {
    const rows = sp.px, w = rows[0].length;
    for (let r = 0; r < rows.length; r++) {
      for (let c = 0; c < w; c++) {
        const ch = rows[r][c];
        if (ch === '.') continue;
        fxctx.fillStyle = sp.pal[ch] || '#fff';
        fxctx.fillRect(Math.round(x) + (flip ? w - 1 - c : c), Math.round(y) + r, 1, 1);
      }
    }
  }

  // 海域ごとの生き物の配置（海域キーから固定で決まるので、開くたびに同じ位置になる）
  const layouts = {};
  function layoutOf(key) {
    if (layouts[key]) return layouts[key];
    const theme = THEME[key] || FALLBACK_THEME, rand = rng(hash(key)), list = [];
    theme.items.forEach(function (name, i) {
      const sp = SPRITES[name], w = sp.px[0].length, h = sp.px.length, dweller = !!DWELLERS[name];
      const slot = (i * 7) % 10;                                   // 0〜9 を重複なく使って横に散らす
      const x = 3 + slot * ((W - 20) / 10) + rand() * 4;
      const range = Math.max(3, FLOOR_TOP - h - 10);
      const y = dweller ? FLOOR_TOP - h + 2 : 6 + Math.floor(rand() * range);
      list.push({ id: key + '_' + (i + 1), name: name, sp: sp, x: x, y: y, dweller: dweller, flip: rand() < 0.5, phase: rand() * 6 });
    });
    return layouts[key] = list;
  }

  // ---------- 画像の読み込み（無ければ null のまま。画像が届いたら描き直す） ----------
  const cache = {};
  function getImage(url) {
    if (!(url in cache)) {
      cache[url] = null;
      const el = new Image();
      el.onload = function () { cache[url] = el; draw(performance.now()); };
      el.onerror = function () { cache[url] = false; };
      el.src = url;
    }
    return cache[url] || null;
  }

  // ---------- 生き物の画像（img/creatures/ に置くと、ドット絵の代わりに使う） ----------
  const CREATURE_W = { fish: 12.5, coral: 11, jelly: 9.5, crab: 11, turtle: 17, whale: 31, angler: 15.5, seaweed: 7, starfish: 9.5, tube: 7 };  // 論理座標（100×50）での幅
  const SLOT_W = {};   // 1匹ごとに大きさを変えたいときだけ書く。例：{ deep_6: 36, coral_3: 8 }
  const cimgs = {};
  function hasTransparency(el) {
    try {
      const c = document.createElement('canvas'); c.width = c.height = 32;
      const g = c.getContext('2d'); g.drawImage(el, 0, 0, 32, 32);
      const d = g.getImageData(0, 0, 32, 32).data;
      for (let i = 3; i < d.length; i += 4) if (d[i] < 250) return true;
      return false;                                                 // 透明な部分がない＝白い紙のままのスキャンとみなす
    } catch (e) { return true; }                                    // 判定できないときは透過PNGとして扱う
  }
  function creatureImage(id) {
    if (!(id in cimgs)) {
      cimgs[id] = null;
      const el = new Image();
      el.onload = function () { cimgs[id] = { el: el, alpha: hasTransparency(el) }; draw(performance.now()); };
      el.onerror = function () { cimgs[id] = false; draw(performance.now()); };   // 無いと分かったら、種類ごとの画像を探しに行く
      el.src = 'img/creatures/' + id + '.png';
    }
    return cimgs[id] || null;
  }
  // 1匹ごとの画像（海域キー_番号.png）を優先し、なければ種類ごとの画像（名前.png）を使う
  function creatureFor(it) {
    const own = creatureImage(it.id);
    if (own) return own;
    if (cimgs[it.id] === false) return creatureImage(it.name);
    return null;
  }
  function drawCreatureImages(list) {
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    list.forEach(function (o) {
      const c = o.img, w = SLOT_W[o.it.id] || CREATURE_W[o.it.name] || 11, h = w * c.el.naturalHeight / c.el.naturalWidth;
      const x = Math.max(0, Math.min(o.it.x + o.dx, W - w));
      const y = o.it.dweller ? FLOOR_TOP + 1 - h : Math.max(1, Math.min(o.it.y + o.dy, FLOOR_TOP - h - 1));
      ctx.save();
      ctx.globalCompositeOperation = c.alpha ? 'source-over' : 'multiply';   // 白い背景は乗算で消える
      ctx.translate((x + (o.it.flip ? w : 0)) * SCALE, y * SCALE);
      ctx.scale(o.it.flip ? -SCALE : SCALE, SCALE);
      ctx.drawImage(c.el, 0, 0, w, h);
      ctx.restore();
    });
  }

  // ---------- 仮の背景（背景の画像が無いときに表示） ----------
  function drawProceduralBg(key, lv) {
    const theme = THEME[key] || FALLBACK_THEME;
    for (let y = 0; y < H; y += 2) {                                // 水のグラデーション（2pxごとの段）
      bgctx.fillStyle = mix(theme.top, theme.bot, y / H);
      bgctx.fillRect(0, y, W, 2);
    }
    const r = rng(hash(key + 'floor'));                             // 海底
    bgctx.fillStyle = theme.floor;
    bgctx.fillRect(0, FLOOR_TOP, W, FLOOR_H);
    bgctx.fillStyle = theme.floor2;
    for (let i = 0; i < 18; i++) bgctx.fillRect(Math.floor(r() * W), FLOOR_TOP + 1 + Math.floor(r() * (FLOOR_H - 2)), 2, 1);
    for (let i = 0; i < (lv - 1) * 2; i++) {                        // 背景のレベルが上がるほど、海底に岩が増える（仮の変化）
      const rx = Math.floor(r() * (W - 6));
      bgctx.fillRect(rx, FLOOR_TOP - 2, 4, 2);
      bgctx.fillRect(rx + 1, FLOOR_TOP - 3, 2, 1);
    }
  }

  // ---------- あわ（ぽこぽこ上がっていく粒。背景の画像の上にも表示） ----------
  function drawBubbles(key, t) {
    fxctx.fillStyle = 'rgba(255,255,255,0.35)';
    for (let k = 0; k < 6; k++) {
      const bx = 8 + k * 16 + (hash(key + k) % 6);
      const by = H - ((reduceMotion ? 0 : t / 75) + k * 19 + 8) % H;
      fxctx.fillRect(bx, Math.floor(by), 2, 2);
    }
  }

  // ---------- 表示の更新 ----------
  const keys = Object.keys(STATE.areas);
  let current = STATE.areas[window.WORLD_START_AREA] && STATE.areas[window.WORLD_START_AREA].unlocked ? window.WORLD_START_AREA : 'center';
  if (!STATE.areas[current]) current = keys[0];

  // 描画の順番：背景 → あわ → 生き物（ドット絵）→ 生き物（画像）
  function draw(t) {
    const a = STATE.areas[current];
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.globalCompositeOperation = 'source-over';
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    fxctx.clearRect(0, 0, W, H);
    drawBubbles(current, t || 0);
    const items = layoutOf(current), overlay = [];
    for (let i = 0; i < Math.min(a.creatures, items.length); i++) {   // 生き物は、誕生した順（番号順）に表示
      const it = items[i];
      let dx = 0, dy = 0;
      if (!it.dweller && !reduceMotion) {
        dx = Math.sin((t || 0) / 1600 + it.phase) * 2.5;
        dy = Math.sin((t || 0) / 900 + it.phase) * 1.2;
      }
      const cimg = creatureFor(it);
      if (cimg) overlay.push({ img: cimg, it: it, dx: dx, dy: dy });   // 画像があれば後で重ねる
      else drawSprite(it.sp, it.x + dx, it.y + dy, it.flip);
    }

    const bgimg = getImage('img/backgrounds/' + current + '_' + a.bg_level + '.png');
    if (bgimg) {
      ctx.imageSmoothingEnabled = bgimg.naturalWidth > W * 2;         // 100×50 のドット絵はくっきり拡大。大きい画像は滑らかに
      ctx.drawImage(bgimg, 0, 0, canvas.width, canvas.height);
    } else {
      bgctx.clearRect(0, 0, W, H);
      drawProceduralBg(current, a.bg_level);
      ctx.imageSmoothingEnabled = false;
      ctx.drawImage(bgc, 0, 0, canvas.width, canvas.height);
    }
    ctx.imageSmoothingEnabled = false;
    ctx.drawImage(fx, 0, 0, canvas.width, canvas.height);
    drawCreatureImages(overlay);
  }

  function areaAt(x, y) {
    return keys.find(function (k) { return STATE.areas[k].x === x && STATE.areas[k].y === y; }) || null;
  }

  const ARROW_MARK = { up: '▲ ', down: '▼ ', left: '◀ ', right: '▶ ' };
  function updateUi() {
    const a = STATE.areas[current];
    document.getElementById('areaName').textContent = a.name;
    document.getElementById('areaStage').textContent = '生物 ' + a.creatures + ' / ' + a.max_creatures + '　背景 Lv.' + a.bg_level;
    document.getElementById('worldEmpty').hidden = a.creatures > 0;
    document.querySelectorAll('.world-arrow').forEach(function (btn) {
      const target = areaAt(a.x + (+btn.dataset.dx), a.y + (+btn.dataset.dy));
      btn.hidden = !target;
      if (!target) return;
      const dir = btn.classList.contains('up') ? 'up' : btn.classList.contains('down') ? 'down' : btn.classList.contains('left') ? 'left' : 'right';
      const t = STATE.areas[target];
      btn.disabled = !t.unlocked;                                    // 未解放の海域は、行けない（解放の条件だけ表示）
      btn.textContent = ARROW_MARK[dir] + (t.unlocked ? t.name : '未解放（進化レベル' + t.unlock_level + 'で解放）');
      btn.dataset.target = t.unlocked ? target : '';
    });
    document.querySelectorAll('#minimap button').forEach(function (b) {
      b.classList.toggle('here', b.dataset.key === current);
    });
    draw(performance.now());
  }

  function go(key) {
    if (!key || key === current || !STATE.areas[key] || !STATE.areas[key].unlocked) return;
    current = key;
    if (reduceMotion) { updateUi(); return; }
    canvas.classList.add('swap');
    setTimeout(function () { updateUi(); canvas.classList.remove('swap'); }, 120);
  }

  // ミニマップ（海域の位置関係。未解放の海域は薄く表示）
  (function buildMinimap() {
    const mm = document.getElementById('minimap');
    const xs = keys.map(function (k) { return STATE.areas[k].x; }), ys = keys.map(function (k) { return STATE.areas[k].y; });
    const minX = Math.min.apply(null, xs), maxX = Math.max.apply(null, xs), minY = Math.min.apply(null, ys), maxY = Math.max.apply(null, ys);
    mm.style.gridTemplateColumns = 'repeat(' + (maxX - minX + 1) + ', 22px)';
    for (let y = minY; y <= maxY; y++) {
      for (let x = minX; x <= maxX; x++) {
        const k = areaAt(x, y);
        const el = document.createElement(k ? 'button' : 'span');
        if (k) {
          const a = STATE.areas[k];
          el.type = 'button';
          el.dataset.key = k;
          el.title = a.unlocked ? a.name : '未解放（進化レベル' + a.unlock_level + 'で解放）';
          el.setAttribute('aria-label', el.title);
          if (!a.unlocked) { el.classList.add('locked'); el.disabled = true; }
          el.addEventListener('click', function () { go(k); });
        } else {
          el.className = 'gap';
        }
        mm.appendChild(el);
      }
    }
  })();

  document.querySelectorAll('.world-arrow').forEach(function (btn) {
    btn.addEventListener('click', function () { go(btn.dataset.target); });
  });

  // キーボードの矢印キーでも移動できる
  document.addEventListener('keydown', function (e) {
    const tag = (e.target && e.target.tagName) || '';
    if (/INPUT|TEXTAREA|SELECT/.test(tag)) return;
    const d = { ArrowUp: [0, -1], ArrowDown: [0, 1], ArrowLeft: [-1, 0], ArrowRight: [1, 0] }[e.key];
    if (!d) return;
    const a = STATE.areas[current];
    go(areaAt(a.x + d[0], a.y + d[1]));
  });

  // スマホ：画面をスワイプして移動（左へスワイプ → 右の海域）
  (function () {
    const stage = document.getElementById('worldStage');
    let sx = 0, sy = 0;
    stage.addEventListener('pointerdown', function (e) { sx = e.clientX; sy = e.clientY; });
    stage.addEventListener('pointerup', function (e) {
      const dx = e.clientX - sx, dy = e.clientY - sy;
      if (Math.max(Math.abs(dx), Math.abs(dy)) < 50) return;
      const a = STATE.areas[current];
      if (Math.abs(dx) > Math.abs(dy)) go(areaAt(a.x + (dx < 0 ? 1 : -1), a.y));
      else go(areaAt(a.x, a.y + (dy < 0 ? 1 : -1)));
    });
  })();

  updateUi();
  if (!reduceMotion) setInterval(function () { if (!document.hidden) draw(performance.now()); }, 140);
})();
