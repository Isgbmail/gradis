/** Gradis — validation du formulaire de commande et envoi à api/order.php (le serveur revalide tout). */
(function () {
  'use strict';
  const { Cart, money, esc, url } = window.Gradis;
  const $ = s => document.querySelector(s);
  const form = $('#coForm');

  const rules = {
    last_name: v => v.trim().length >= 2 || 'Indiquez votre nom.',
    first_name: v => v.trim().length >= 2 || 'Indiquez votre prénom.',
    email: v => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()) || 'Adresse e-mail invalide.',
    phone: v => { const d = v.replace(/\D/g, ''); return (d.length >= 9 && d.length <= 15 && /^[+\d\s.\-()]+$/.test(v.trim())) || 'Numéro invalide (ex. 06 12 34 56 78).'; },
    city: v => v.trim().length >= 2 || 'Indiquez votre ville.',
    address: v => v.trim().length >= 8 || 'Indiquez votre adresse complète de livraison.',
  };
  function setErr(name, msg) {
    const el = form.elements[name]; if (!el) return;
    el.classList.toggle('is-invalid', !!msg);
    const fb = el.parentNode.querySelector('.invalid-feedback'); if (fb) fb.textContent = msg || '';
  }
  function validate() {
    let first = null;
    Object.keys(rules).forEach(k => {
      const r = rules[k](form.elements[k].value);
      setErr(k, r === true ? '' : r);
      if (r !== true && !first) first = form.elements[k];
    });
    return first;
  }
  Object.keys(rules).forEach(k => form.elements[k].addEventListener('blur', () => { const r = rules[k](form.elements[k].value); setErr(k, r === true ? '' : r); }));

  async function init() {
    const items = Cart.all();
    $('#coLoading').hidden = true;
    if (!items.length) { $('#coEmpty').hidden = false; return; }
    let res;
    try { res = await Cart.price(items); } catch (e) { $('#coEmpty').hidden = false; $('#coEmpty').querySelector('h2').textContent = 'Erreur de chargement. Rechargez la page.'; return; }
    if (res.errors.length || !res.lines.length) { location.href = url('panier.php'); return; }
    form.hidden = false;
    $('#coLines').innerHTML = res.lines.map(l => `<div class="d-flex gap-3 py-2 border-bottom"><div class="gr-line__img" style="width:56px">${l.image ? `<img src="${esc(url('uploads/' + l.image))}" alt="" style="width:100%;height:100%;object-fit:cover">` : GradisOutfit.svg(l.render)}</div>
      <div class="flex-grow-1 small"><strong class="d-block">${esc(l.title)}</strong><span class="text-muted">${l.qty} × ${money(l.unit_price)}</span>
      <ul class="gr-line__details mb-0">${l.details.map(d => `<li>${esc(d)}</li>`).join('')}</ul></div><div class="fw-semibold small text-nowrap">${money(l.line_total)}</div></div>`).join('');
    $('#cSub').textContent = money(res.subtotal);
    $('#cShip').textContent = res.shipping > 0 ? money(res.shipping) : 'Offerte';
    $('#cTotal').textContent = money(res.total);
  }

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const err = $('#coError'); err.hidden = true;
    const bad = validate();
    if (bad) { bad.focus(); bad.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
    const pm = (form.querySelector('input[name=payment_method]:checked') || {}).value;
    if (!pm) { $('#err_payment_method').textContent = 'Choisissez un mode de commande.'; return; }
    const btn = $('#coSubmit'); btn.disabled = true; btn.innerHTML = '<span class="gr-spinner" style="width:18px;height:18px"></span> Envoi en cours…';
    const f = form.elements;
    const body = {
      csrf: window.GRADIS.csrf, items: Cart.all(), payment_method: pm, note: f.note.value,
      customer: { first_name: f.first_name.value, last_name: f.last_name.value, email: f.email.value, phone: f.phone.value, city: f.city.value, address: f.address.value },
    };
    try {
      const r = await fetch(url('api/order.php'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
      const d = await r.json().catch(() => ({ ok: false, error: 'Réponse invalide du serveur.' }));
      if (d.ok) { Cart.clear(); location.href = d.redirect; return; }
      if (d.fields) Object.keys(d.fields).forEach(k => k === 'payment_method' ? ($('#err_payment_method').textContent = d.fields[k]) : setErr(k, d.fields[k]));
      err.textContent = d.error || 'Une erreur est survenue.'; err.hidden = false;
      err.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } catch (x) {
      err.textContent = "Connexion impossible. Vérifiez votre réseau puis réessayez — votre panier est conservé."; err.hidden = false;
    }
    btn.disabled = false; btn.innerHTML = '<span class="label">Confirmer la commande</span>';
  });
  init();
})();
