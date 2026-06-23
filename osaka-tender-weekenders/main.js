/* =========================================================
   OSAKA TENDER WEEKENDERS — Event LP behavior
   - Hero canvas animation (波形 + 粒子, fixed to 両方)
   - FAQ accordion (single-open, first open by default)
   - Smooth anchor scroll
   - Image placeholder fallback (swap-ready)
   - Reservation form validation + submission
   ========================================================= */
(function () {
  'use strict';

  const prefersReducedMotion =
    window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------------------------------------------------
     Hero canvas animation
     --------------------------------------------------------- */
  function startHero() {
    const cv = document.getElementById('heroCanvas');
    if (!cv || prefersReducedMotion) return;
    const ctx = cv.getContext('2d');
    if (!ctx) return;

    let w = 0, h = 0, raf = 0;

    function resize() {
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      const r = cv.getBoundingClientRect();
      w = r.width; h = r.height;
      cv.width = w * dpr; cv.height = h * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    resize();
    window.addEventListener('resize', resize);

    const N = 48;
    const parts = [];
    for (let i = 0; i < N; i++) {
      parts.push({
        x: Math.random(), y: Math.random(),
        r: Math.random() * 2.4 + 0.8,
        s: Math.random() * 0.00009 + 0.00002,
        o: Math.random() * 0.45 + 0.15,
        ph: Math.random() * 6.2832
      });
    }

    let t = 0;
    function draw() {
      t += 1;
      ctx.clearRect(0, 0, w, h);

      // 波形 (wave)
      const cy = h * 0.62;
      for (let k = 0; k < 3; k++) {
        ctx.beginPath();
        const amp = 16 - k * 3.5, off = k * 34, sp = 0.018 + k * 0.004;
        for (let x = 0; x <= w; x += 6) {
          const env = Math.sin((x / w) * Math.PI);
          const y = cy + off + Math.sin(x * 0.013 + t * sp + k * 1.3) * amp * env;
          x === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        }
        ctx.strokeStyle = k % 2
          ? 'rgba(194,161,78,' + (0.16 - k * 0.03) + ')'
          : 'rgba(150,180,140,' + (0.14 - k * 0.025) + ')';
        ctx.lineWidth = 1.4;
        ctx.stroke();
      }

      // 粒子 (particles)
      for (const p of parts) {
        p.y -= p.s;
        if (p.y < -0.05) { p.y = 1.05; p.x = Math.random(); }
        const px = p.x * w + Math.sin(t * 0.005 + p.ph) * 14;
        const py = p.y * h;
        const o = p.o * (0.55 + 0.45 * Math.sin(t * 0.012 + p.ph));
        ctx.beginPath();
        ctx.fillStyle = 'rgba(228,212,162,' + Math.max(0, o) + ')';
        ctx.shadowColor = 'rgba(228,212,162,0.9)';
        ctx.shadowBlur = 11;
        ctx.arc(px, py, p.r, 0, 6.2832);
        ctx.fill();
      }
      ctx.shadowBlur = 0;

      raf = requestAnimationFrame(draw);
    }
    draw();

    // pause when tab hidden (battery friendly)
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        cancelAnimationFrame(raf);
      } else {
        raf = requestAnimationFrame(draw);
      }
    });
  }

  /* ---------------------------------------------------------
     FAQ accordion — single open, toggle to close
     --------------------------------------------------------- */
  function initFaq() {
    const list = document.getElementById('faqList');
    if (!list) return;
    const items = Array.prototype.slice.call(list.querySelectorAll('.faq__item'));

    items.forEach(function (item) {
      const btn = item.querySelector('.faq__q');
      btn.addEventListener('click', function () {
        const willOpen = !item.classList.contains('is-open');
        items.forEach(function (it) {
          it.classList.remove('is-open');
          it.querySelector('.faq__q').setAttribute('aria-expanded', 'false');
        });
        if (willOpen) {
          item.classList.add('is-open');
          btn.setAttribute('aria-expanded', 'true');
        }
      });
    });
  }

  /* ---------------------------------------------------------
     Sticky CTA — 少しスクロールしたら出現
     --------------------------------------------------------- */
  function initStickyCta() {
    const bar = document.querySelector('.sticky-cta');
    if (!bar) return;
    function update() {
      // ヒーロー高の約6割（上限600px）スクロールしたら表示
      const threshold = Math.min(window.innerHeight * 0.6, 600);
      bar.classList.toggle('is-visible', window.scrollY > threshold);
    }
    update();
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
  }

  /* ---------------------------------------------------------
     Smooth anchor scroll (#reserve など)
     --------------------------------------------------------- */
  function initSmoothScroll() {
    document.addEventListener('click', function (e) {
      const a = e.target.closest('a[href^="#"]');
      if (!a) return;
      const id = a.getAttribute('href');
      if (id === '#' || id.length < 2) return;
      const target = document.querySelector(id);
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({
        behavior: prefersReducedMotion ? 'auto' : 'smooth',
        block: 'start'
      });
    });
  }

  /* ---------------------------------------------------------
     Image placeholder fallback
     実画像が assets/ に無い場合、ラベル付きプレースホルダに差し替え。
     画像を配置すれば自動的にそのまま表示される。
     --------------------------------------------------------- */
  function initImageFallback() {
    const imgs = Array.prototype.slice.call(document.querySelectorAll('img.img-slot'));
    imgs.forEach(function (img) {
      function toPlaceholder() {
        if (img.dataset.phApplied) return;
        img.dataset.phApplied = '1';
        const ph = document.createElement('div');
        ph.className = img.className.replace('img-slot', 'img-ph');
        ph.setAttribute('role', 'img');
        ph.setAttribute('aria-label', img.alt || '画像');
        ph.textContent = img.alt || '画像';
        if (img.parentNode) img.parentNode.replaceChild(ph, img);
      }
      img.addEventListener('error', toPlaceholder);
      // already failed before listener attached
      if (img.complete && img.naturalWidth === 0) toPlaceholder();
    });
  }

  /* ---------------------------------------------------------
     Reservation form
     クライアント側バリデーション（即時フィードバック）。
     妥当ならフォームをそのまま PHP（form/confirm.php）へ POST し、
     確認画面 → 完了画面（サンキュー）へ遷移する。
     サーバー側でも validate_input() で再検証している。
     --------------------------------------------------------- */
  function initForm() {
    const form = document.getElementById('reserveForm');
    if (!form) return;
    const statusEl = document.getElementById('reserveStatus');

    const validators = {
      name: function (v) { return v.trim().length > 0; },
      kana: function (v) { return /^[぀-ゟ゠-ヿー\s　]+$/.test(v.trim()); },
      qty:  function (v) { return /^[1-4]$/.test(v); },
      email: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()); },
      tel:  function (v) { return /^[0-9\-+\s()]{10,}$/.test(v.trim()); }
    };

    function fieldOf(input) { return input.closest('.field'); }

    function validateField(input) {
      const fn = validators[input.name];
      const ok = fn ? fn(input.value) : input.value.trim().length > 0;
      const field = fieldOf(input);
      input.classList.toggle('is-invalid', !ok);
      if (field) field.classList.toggle('has-error', !ok);
      return ok;
    }

    // --- 入力値の保持（確認画面から戻っても消えないように sessionStorage に保存）---
    // ふりがな欄は autocomplete=off によりブラウザの戻る復元が効かないため、ここで補完する。
    const STORE_KEY = 'otw_reserve_v1';
    const STORE_FIELDS = ['name', 'kana', 'qty', 'email', 'tel'];

    function saveState() {
      try {
        const data = {};
        STORE_FIELDS.forEach(function (n) {
          const el = form.elements[n];
          if (el) data[n] = el.value;
        });
        sessionStorage.setItem(STORE_KEY, JSON.stringify(data));
      } catch (e) { /* sessionStorage 不可環境は無視 */ }
    }

    function restoreState() {
      try {
        const raw = sessionStorage.getItem(STORE_KEY);
        if (!raw) return;
        const data = JSON.parse(raw);
        STORE_FIELDS.forEach(function (n) {
          const el = form.elements[n];
          // ブラウザが既に復元した値は尊重し、空のものだけ補完する
          if (el && data[n] != null && el.value === '') el.value = data[n];
        });
      } catch (e) { /* noop */ }
    }

    restoreState();

    // live-clear errors as the user fixes them
    form.querySelectorAll('input, select').forEach(function (input) {
      input.addEventListener('input', function () {
        saveState();
        if (input.classList.contains('is-invalid')) validateField(input);
      });
      input.addEventListener('change', saveState);
      input.addEventListener('blur', function () { validateField(input); });
    });

    form.addEventListener('submit', function (e) {
      const fields = Array.prototype.slice.call(form.querySelectorAll('input, select'));
      let allOk = true;
      let firstBad = null;
      fields.forEach(function (input) {
        if (input.name === 'website') return; // skip honeypot
        const ok = validateField(input);
        if (!ok && !firstBad) firstBad = input;
        allOk = allOk && ok;
      });

      if (!allOk) {
        e.preventDefault();
        showStatus('入力内容をご確認ください。', false);
        if (firstBad) firstBad.focus();
        return;
      }
      saveState(); // 確認画面へ進む前に最新値を保存（戻った時に復元）
      // 妥当なら preventDefault せず、form/confirm.php へネイティブ送信
    });

    function showStatus(msg, ok) {
      if (!statusEl) return;
      statusEl.textContent = msg;
      statusEl.classList.add('is-shown');
      statusEl.classList.toggle('reserve__status--ok', ok);
      statusEl.classList.toggle('reserve__status--err', !ok);
    }
  }

  /* ---------------------------------------------------------
     init
     --------------------------------------------------------- */
  function init() {
    initImageFallback();
    initFaq();
    initStickyCta();
    initSmoothScroll();
    initForm();
    startHero();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
