/** Gradis — filtres, recherche instantanée et tri du catalogue (côté navigateur, sur les données déjà chargées). */
(function () {
  'use strict';
  const grid = document.getElementById('grid');
  if (!grid) return;
  const $ = id => document.getElementById(id);
  const cards = Array.from(grid.querySelectorAll('.gr-pcol'));
  const radio = name => (document.querySelector(`input[name=${name}]:checked`) || {}).value || '';
  const norm = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

  function apply() {
    const q = norm($('fSearch').value.trim());
    const cat = radio('cat'), tier = radio('tier'), color = radio('color'), size = radio('size');
    let min = Math.max(0, parseFloat($('fMin').value)), max = Math.max(0, parseFloat($('fMax').value));
    if (!isNaN(min) && !isNaN(max) && min > max) [min, max] = [max, min]; // bornes inversées : on les remet dans l'ordre
    let n = 0;
    cards.forEach(c => {
      const d = c.dataset, price = parseFloat(d.price);
      const ok = (!q || norm(d.name).includes(q))
        && (!cat || d.cat === cat) && (!tier || d.tier === tier)
        && (!color || d.colors.split(' ').includes(color))
        && (!size || d.sizes.split(' ').includes(size))
        && (isNaN(min) || price >= min) && (isNaN(max) || price <= max);
      c.hidden = !ok;
      if (ok) n++;
    });
    $('fCount').textContent = n + ' produit' + (n > 1 ? 's' : '');
    $('noResult').hidden = n !== 0;
    const u = new URL(location.href);
    [['q', $('fSearch').value.trim()], ['cat', cat], ['tier', tier]].forEach(([k, v]) => v ? u.searchParams.set(k, v) : u.searchParams.delete(k));
    history.replaceState(null, '', u);
  }

  function sort(mode) {
    const by = {
      'price-asc': (a, b) => a.dataset.price - b.dataset.price,
      'price-desc': (a, b) => b.dataset.price - a.dataset.price,
      'name': (a, b) => a.dataset.name.localeCompare(b.dataset.name, 'fr'),
      'order': (a, b) => a.dataset.order - b.dataset.order,
    }[mode];
    cards.slice().sort(by).forEach(c => grid.appendChild(c));
    $('fSort').value = $('fSortM').value = mode;
  }

  let t;
  $('fSearch').addEventListener('input', () => { clearTimeout(t); t = setTimeout(apply, 80); });
  document.querySelectorAll('#filters input').forEach(i => i.addEventListener('change', apply));
  ['fMin', 'fMax'].forEach(id => $(id).addEventListener('input', apply));
  $('fSort').addEventListener('change', e => sort(e.target.value));
  $('fSortM').addEventListener('change', e => sort(e.target.value));
  function reset() {
    $('fSearch').value = ''; $('fMin').value = ''; $('fMax').value = '';
    document.querySelectorAll('#filters input[type=radio][value=""]').forEach(r => { r.checked = true; });
    sort('order'); apply();
  }
  $('fReset').addEventListener('click', reset);
  $('fReset2').addEventListener('click', reset);
  apply();
})();
