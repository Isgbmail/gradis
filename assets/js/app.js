/**
 * Gradis — JS commun : panier (localStorage), favoris, recherche, ajout rapide, WhatsApp, toasts.
 * Le panier ne stocke que des CHOIX ; les prix sont toujours recalculés par le serveur (api/price.php).
 */
(function () {
  'use strict';
  const G = window.GRADIS || {};
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const money = v => {
    v = Number(v) || 0;
    const s = Number.isInteger(v) ? v.toLocaleString('fr-FR', { maximumFractionDigits: 0 }) : v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return s.replace(/ | /g, ' ') + ' DH';
  };
  const esc = s => String(s).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  const url = p => (G.base || '') + '/' + String(p).replace(/^\//, '');

  // ----------------------------------------------------------- stockage ---
  const KEY = 'gradis.cart.v1', FAV = 'gradis.fav.v1';
  const safe = {
    get(k, d) { try { const v = localStorage.getItem(k); return v ? JSON.parse(v) : d; } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* mode privé */ } },
  };
  // Repli mémoire si localStorage est indisponible
  let memCart = null;
  const Cart = {
    all() { return memCart || safe.get(KEY, []); },
    save(items) { memCart = items; safe.set(KEY, items); Cart.refresh(); },
    /** Empreinte : deux compositions identiques fusionnent, sinon lignes distinctes. */
    fingerprint(it) {
      const o = it.type === 'product' ? { p: it.product_id, s: it.size || '', c: it.color || '' } : { c: it.config };
      return JSON.stringify(o);
    },
    add(it) {
      const items = Cart.all();
      const fp = Cart.fingerprint(it);
      const found = items.find(x => Cart.fingerprint(x) === fp);
      const max = G.maxQty || 20;
      if (found) found.qty = Math.min(max, (found.qty || 1) + (it.qty || 1));
      else items.push(Object.assign({ id: 'l' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6), qty: 1 }, it));
      Cart.save(items);
    },
    update(id, patch) { Cart.save(Cart.all().map(x => x.id === id ? Object.assign({}, x, patch) : x)); },
    replace(id, it) {
      const items = Cart.all();
      const i = items.findIndex(x => x.id === id);
      if (i < 0) return Cart.add(it);
      items[i] = Object.assign({}, it, { id, qty: items[i].qty });
      Cart.save(items);
    },
    remove(id) { Cart.save(Cart.all().filter(x => x.id !== id)); },
    clear() { Cart.save([]); },
    count() { return Cart.all().reduce((n, x) => n + (x.qty || 1), 0); },
    refresh() {
      const n = Cart.count();
      $$('[data-cart-count]').forEach(el => { el.textContent = n; el.hidden = n === 0; });
    },
    /** Demande les prix au serveur. */
    async price(items) {
      const r = await fetch(url('api/price.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ items }) });
      if (!r.ok) throw new Error('Erreur serveur');
      return r.json();
    },
  };
  window.GradisCart = Cart;
  window.Gradis = { money, esc, url, safe, toast, waLink, Cart };

  // -------------------------------------------------------------- toasts ---
  function toast(msg, opts) {
    opts = opts || {};
    const box = $('#toasts');
    if (!box) return;
    const el = document.createElement('div');
    el.className = 'gr-toast' + (opts.error ? ' gr-toast--error' : '');
    el.setAttribute('role', opts.error ? 'alert' : 'status');
    el.innerHTML = `<span>${esc(msg)}</span>` + (opts.link ? `<a href="${esc(opts.link.href)}">${esc(opts.link.label)}</a>` : '');
    box.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 320); }, opts.ms || 3800);
  }
  const added = () => toast('Ajouté au panier', { link: { href: url('panier.php'), label: 'Voir le panier' } });

  function waLink(text) {
    const base = 'https://wa.me/' + (G.waNumber || '');
    return text ? base + '?text=' + encodeURIComponent(text) : base;
  }

  // ------------------------------------------------------------ recherche ---
  const searchForm = $('.gr-search');
  $$('[data-search-open]').forEach(b => b.addEventListener('click', () => {
    searchForm.hidden = false; $('input', searchForm).focus();
  }));
  $$('[data-search-close]').forEach(b => b.addEventListener('click', () => { searchForm.hidden = true; }));
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && searchForm && !searchForm.hidden) searchForm.hidden = true; });

  // -------------------------------------------------------------- favoris ---
  const Fav = {
    all() { return safe.get(FAV, []); },
    has(id) { return Fav.all().includes(+id); },
    toggle(id) { id = +id; const a = Fav.all(); const i = a.indexOf(id); i >= 0 ? a.splice(i, 1) : a.push(id); safe.set(FAV, a); return i < 0; },
  };
  window.GradisFav = Fav;
  function paintFavs() {
    $$('[data-fav]').forEach(b => {
      const on = Fav.has(b.dataset.fav);
      b.classList.toggle('is-fav', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      const i = $('i', b); if (i) i.className = 'bi bi-heart' + (on ? '-fill' : '');
    });
  }
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-fav]');
    if (!b) return;
    e.preventDefault();
    const on = Fav.toggle(b.dataset.fav);
    paintFavs();
    toast(on ? 'Ajouté à vos favoris' : 'Retiré de vos favoris');
    document.dispatchEvent(new CustomEvent('gradis:favs'));
  });

  // --------------------------------------------------------- ajout rapide ---
  const PRODUCTS = {};
  window.GRADIS_PRODUCTS = PRODUCTS;
  function chipRadios(box, name, list, mk) {
    box.innerHTML = list.map((o, i) => mk(o, i)).join('');
  }
  const qa = {
    modal: null, p: null,
    open(id) {
      const p = PRODUCTS[id];
      if (!p) return;
      qa.p = p;
      const el = $('#quickAdd');
      qa.modal = qa.modal || new bootstrap.Modal(el);
      $('[data-qa-name]', el).textContent = p.name;
      $('[data-qa-price]', el).textContent = money(p.price);
      const th = $('[data-qa-thumb]', el);
      th.className = 'gr-qa-thumb gr-line__img';
      th.innerHTML = GradisOutfit.svg(p.render);
      $('[data-qa-error]', el).hidden = true;
      $('[data-qa-qty] input', el).value = 1;
      const cw = $('[data-qa-colors-wrap]', el), sw = $('[data-qa-sizes-wrap]', el);
      cw.hidden = !p.colors.length;
      sw.hidden = !p.sizes.length;
      chipRadios($('[data-qa-colors]', el), 'qa-color', p.colors, (c, i) =>
        `<label class="gr-chip gr-chip--color"><input type="radio" name="qa-color" value="${esc(c.code)}" ${i === 0 ? 'checked' : ''}><span><i class="gr-dot" style="--c:${esc(c.hex)}"></i>${esc(c.label)}</span></label>`);
      chipRadios($('[data-qa-sizes]', el), 'qa-size', p.sizes, s =>
        `<label class="gr-chip"><input type="radio" name="qa-size" value="${esc(s.size)}" ${s.stock <= 0 ? 'disabled' : ''}><span>${esc(s.size)}</span></label>`);
      qa.modal.show();
    },
    confirm() {
      const el = $('#quickAdd'), p = qa.p;
      const err = $('[data-qa-error]', el);
      const size = ($('input[name=qa-size]:checked', el) || {}).value;
      const color = ($('input[name=qa-color]:checked', el) || {}).value;
      if (p.sizes.length && !size) { err.textContent = 'Choisissez une taille.'; err.hidden = false; return; }
      const qty = Math.max(1, Math.min(G.maxQty || 20, parseInt($('[data-qa-qty] input', el).value, 10) || 1));
      Cart.add({ type: 'product', product_id: p.id, size: size || null, color: color || null, qty });
      qa.modal.hide();
      added();
    },
  };
  $('#quickAdd [data-qa-confirm]')?.addEventListener('click', qa.confirm);
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-quickadd]');
    if (!b) return;
    const id = +b.dataset.quickadd;
    const p = PRODUCTS[id];
    if (!p) return;
    // Pas de choix nécessaire → ajout direct
    if (!p.sizes.length && p.colors.length <= 1) {
      Cart.add({ type: 'product', product_id: id, size: null, color: p.colors[0] ? p.colors[0].code : null, qty: 1 });
      added();
    } else qa.open(id);
  });
  // Boutons de quantité (modal + ailleurs)
  document.addEventListener('click', e => {
    const b = e.target.closest('.gr-qty [data-q]');
    if (!b) return;
    const inp = $('input', b.parentNode);
    const v = Math.max(1, Math.min(G.maxQty || 20, (parseInt(inp.value, 10) || 1) + (+b.dataset.q)));
    inp.value = v;
    inp.dispatchEvent(new Event('change', { bubbles: true }));
  });

  // ------------------------------------------------------ révélation scroll -
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(es => es.forEach(x => { if (x.isIntersecting) { x.target.classList.add('in'); io.unobserve(x.target); } }), { threshold: .12 });
    $$('.reveal').forEach(el => io.observe(el));
  } else $$('.reveal').forEach(el => el.classList.add('in'));

  // Registre des produits injecté par les pages (JSON dans <script type="application/json" id="gr-products">)
  const pj = $('#gr-products');
  if (pj) { try { JSON.parse(pj.textContent).forEach(p => { PRODUCTS[p.id] = p; }); } catch (e) { /* ignore */ } }

  Cart.refresh();
  paintFavs();
})();
