/** Gradis — fiche produit : aperçu selon la couleur, ajout au panier, message WhatsApp. */
(function () {
  'use strict';
  const P = window.GR_PRODUCT, f = document.getElementById('buyForm');
  if (!P || !f) return;
  const { Cart, toast, waLink } = window.Gradis;
  const val = n => (f.querySelector(`input[name=${n}]:checked`) || {}).value || null;
  const wrap = document.getElementById('pvOutfit');
  const err = document.getElementById('buyError');

  function render() {
    const color = val('color');
    const r = Object.assign({}, P.render);
    if (color) {
      if (['outfit', 'robe'].includes(P.kind)) { r.robe = color; r.cap = color; }
      else if (P.kind === 'scarf') r.scarf = color;
      else if (P.kind === 'cap') r.cap = color;
    }
    if (wrap) GradisOutfit.mount(wrap, r);
  }
  function waMessage() {
    const lines = [GRADIS.waGreeting, 'Je souhaite commander :', `• ${Math.max(1, Math.min(GRADIS.maxQty, parseInt(document.getElementById('qty').value, 10) || 1))} × ${P.name} (${P.tier_name}) — ${Gradis.money(P.price)}`];
    const c = P.colors.find(x => x.code === val('color')); if (c) lines.push('   Couleur : ' + c.label);
    if (val('size')) lines.push('   Taille : ' + val('size'));
    lines.push(location.href);
    return lines.join('\n');
  }
  function sync() { render(); document.getElementById('waOrder').href = waLink(waMessage()); }

  f.addEventListener('change', sync);
  document.getElementById('qty').addEventListener('input', sync);
  document.querySelectorAll('.gr-thumbs button').forEach(b => b.addEventListener('click', () => {
    document.getElementById('galleryMain').src = b.dataset.img;
    document.querySelectorAll('.gr-thumbs button').forEach(x => x.classList.toggle('active', x === b));
  }));
  f.addEventListener('submit', e => {
    e.preventDefault();
    err.hidden = true;
    if (P.sizes.length && !val('size')) { err.textContent = 'Veuillez choisir une taille.'; err.hidden = false; return; }
    const qty = Math.max(1, Math.min(GRADIS.maxQty, parseInt(document.getElementById('qty').value, 10) || 1));
    Cart.add({ type: 'product', product_id: P.id, size: val('size'), color: val('color'), qty });
    toast('Ajouté au panier', { link: { href: Gradis.url('panier.php'), label: 'Voir le panier' } });
  });
  sync();
})();
