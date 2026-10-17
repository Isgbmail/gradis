/** Gradis — page panier : affichage des lignes tarifées par le serveur, quantités, suppression, WhatsApp. */
(function () {
  'use strict';
  const { Cart, money, esc, url, toast, waLink } = window.Gradis;
  const $ = s => document.querySelector(s);
  let last = null;

  function thumb(l) {
    if (l.image) return `<img src="${esc(url('uploads/' + l.image))}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:6px">`;
    return GradisOutfit.svg(l.render);
  }

  function lineHtml(l, err) {
    const isCustom = l.kind === 'custom';
    const edit = isCustom ? `<a class="small" href="${esc(url('configurateur.php?edit=' + l.id))}"><i class="bi bi-pencil"></i> Modifier la personnalisation</a>`
      : (l.slug ? `<a class="small" href="${esc(url('produit.php?slug=' + l.slug))}">Voir le produit</a>` : '');
    return `<div class="gr-line" data-line="${esc(l.id)}">
      <div class="gr-line__img">${thumb(l)}</div>
      <div>
        <div class="gr-line__title">${esc(l.title)}</div>
        <ul class="gr-line__details">${l.details.map(d => `<li>${esc(d)}</li>`).join('')}</ul>
        <div class="d-flex flex-wrap align-items-center gap-3">
          <div class="gr-qty"><button type="button" data-act="dec" aria-label="Diminuer la quantité">−</button><input type="number" min="1" max="${window.GRADIS.maxQty}" value="${l.qty}" aria-label="Quantité" data-act="qty"><button type="button" data-act="inc" aria-label="Augmenter la quantité">+</button></div>
          ${edit}
          <button type="button" class="btn btn-link text-danger p-0 small" data-act="rm"><i class="bi bi-trash"></i> Supprimer</button>
        </div>
      </div>
      <div class="gr-line__total">${money(l.line_total)}<div class="small fw-normal text-muted">${money(l.unit_price)} / unité</div></div>
    </div>`;
  }
  function errHtml(e) {
    return `<div class="gr-line" data-line="${esc(e.id)}"><div class="gr-line__img"><i class="bi bi-exclamation-triangle m-auto text-warning fs-3"></i></div>
      <div><div class="gr-line__title">Article indisponible</div><div class="gr-line__err">${esc(e.message)}</div>
      <div class="mt-2 d-flex gap-3"><a class="small" href="${esc(url('configurateur.php?edit=' + e.id))}">Modifier</a><button type="button" class="btn btn-link text-danger p-0 small" data-act="rm"><i class="bi bi-trash"></i> Retirer</button></div></div><div></div></div>`;
  }

  async function load() {
    const items = Cart.all();
    $('#cartLoading').hidden = false; $('#cartEmpty').hidden = true; $('#cartBody').hidden = true; $('#cartError').hidden = true;
    if (!items.length) { $('#cartLoading').hidden = true; $('#cartEmpty').hidden = false; return; }
    let res;
    try { res = await Cart.price(items); } catch (e) {
      $('#cartLoading').hidden = true; $('#cartError').hidden = false;
      $('#cartError').innerHTML = 'Impossible de charger votre panier pour le moment. <button class="btn btn-link p-0 align-baseline" id="retry">Réessayer</button>';
      $('#retry').onclick = load; return;
    }
    last = res;
    $('#cartLoading').hidden = true;
    const byId = {}; res.lines.forEach(l => { byId[l.id] = l; });
    const errs = {}; res.errors.forEach(e => { errs[e.id] = e; });
    $('#cartLines').innerHTML = items.map(it => byId[it.id] ? lineHtml(byId[it.id]) : (errs[it.id] ? errHtml(errs[it.id]) : '')).join('');
    $('#cartBody').hidden = false;
    $('#sSub').textContent = money(res.subtotal);
    $('#sShip').textContent = res.shipping > 0 ? money(res.shipping) : (res.subtotal > 0 ? 'Offerte' : '—');
    $('#sTotal').textContent = money(res.total);
    const rest = res.free_from - res.subtotal;
    $('#sFree').textContent = res.free_from > 0 && rest > 0 && res.lines.length ? `Plus que ${money(rest)} pour la livraison offerte.` : '';
    const blocked = res.errors.length > 0 || !res.lines.length;
    const go = $('#goCheckout');
    go.classList.toggle('disabled', blocked); go.setAttribute('aria-disabled', blocked ? 'true' : 'false');
    $('#waCart').href = waLink(waMessage(res));
  }

  function waMessage(res) {
    const L = [window.GRADIS.waGreeting, 'Je souhaite commander :'];
    res.lines.forEach(l => {
      L.push('', `• ${l.qty} × ${l.title} — ${money(l.line_total)}`);
      l.details.forEach(d => L.push('   ' + d));
    });
    L.push('', `Sous-total : ${money(res.subtotal)}`, `Livraison : ${res.shipping > 0 ? money(res.shipping) : 'offerte'}`, `Total estimé : ${money(res.total)}`);
    return L.join('\n');
  }

  const box = $('#cartLines');
  let t;
  box.addEventListener('click', e => {
    const b = e.target.closest('[data-act]'); if (!b) return;
    const id = b.closest('[data-line]').dataset.line;
    const it = Cart.all().find(x => x.id === id); if (!it) return;
    const max = window.GRADIS.maxQty;
    if (b.dataset.act === 'rm') { Cart.remove(id); toast('Article retiré du panier'); load(); }
    if (b.dataset.act === 'inc') { Cart.update(id, { qty: Math.min(max, it.qty + 1) }); load(); }
    if (b.dataset.act === 'dec') { Cart.update(id, { qty: Math.max(1, it.qty - 1) }); load(); }
  });
  box.addEventListener('change', e => {
    if (e.target.dataset.act !== 'qty') return;
    const id = e.target.closest('[data-line]').dataset.line;
    Cart.update(id, { qty: Math.max(1, Math.min(window.GRADIS.maxQty, parseInt(e.target.value, 10) || 1)) });
    clearTimeout(t); t = setTimeout(load, 150);
  });
  $('#goCheckout').addEventListener('click', e => { if (e.currentTarget.classList.contains('disabled')) { e.preventDefault(); toast('Corrigez ou retirez les articles indisponibles avant de commander.', { error: true }); } });
  load();
})();
