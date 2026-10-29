/**
 * Gradis — configurateur de tenue.
 * L'état ne contient que des CHOIX. Le prix affiché est une estimation instantanée calculée
 * avec les tarifs de la base ; le serveur recalcule et valide tout à l'ajout/commande.
 */
(function () {
  'use strict';
  const C = window.GR_CONF, PRE = window.GR_PRESET || {};
  const { Cart, toast, money, esc, waLink } = window.Gradis;
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const O = C.options, L = C.limits;
  const tierCodes = Object.keys(C.tiers);
  if (!tierCodes.length) { $('#conf').innerHTML = '<div class="gr-empty"><i class="bi bi-tools"></i>Le configurateur est momentanément indisponible. Écrivez-nous sur WhatsApp pour composer votre tenue.</div>'; return; }

  const GROUPS = ['robe_color', 'scarf_color', 'trim_color', 'cap_color', 'cap_style', 'size'];
  const year = new Date().getFullYear();
  const SUGGEST = { scarf: ['Prénom NOM', 'Promotion ' + year, 'Master ' + year], cap: ['Promotion ' + year, String(year), 'Diplômé(e)'] };

  const firstValid = (grp, tier) => (O[grp] || []).find(o => o.tiers.includes(tier));
  const optOf = (grp, code) => (O[grp] || []).find(o => o.code === code);
  const okFor = (o, tier) => !!o && o.tiers.includes(tier);

  // --------------------------------------------------------------- état ---
  const state = {
    tier: tierCodes.includes(PRE.tier) ? PRE.tier : (tierCodes.includes('premium') ? 'premium' : tierCodes[0]),
    parts: Object.assign({ robe: true, scarf: true, cap: true }, PRE.parts || {}),
    size: null, robe_color: 'noir', scarf_color: 'bleu-marine', trim_color: 'blanc', cap_color: null, cap_style: 'standard',
    accessories: [], qty: 1,
    custom: {
      scarf: { text: '', zone: 'both', font: 'classique', color: 'or', align: 'center', size: Math.min(L.size_max, Math.max(L.size_min, 16)) },
      cap: { text: '', zone: 'top', font: 'classique', color: 'or', align: 'center', size: Math.min(L.size_max, Math.max(L.size_min, 14)) },
    },
  };
  ['robe_color', 'scarf_color', 'trim_color', 'cap_color'].forEach(k => { if (PRE[k] && optOf(k, PRE[k])) state[k] = PRE[k]; });
  if (PRE.cap_color === undefined) state.cap_color = null;
  const editId = new URLSearchParams(location.search).get('edit');
  let editing = null;

  // Édition d'une ligne du panier
  if (editId) {
    editing = Cart.all().find(x => x.id === editId && x.type === 'custom');
    if (editing) {
      const c = editing.config;
      state.tier = tierCodes.includes(c.tier) ? c.tier : state.tier;
      state.parts = Object.assign({ robe: false, scarf: false, cap: false }, c.parts);
      GROUPS.forEach(g => { if (c[g] !== undefined) state[g] = c[g]; });
      state.accessories = (c.accessories || []).slice();
      ['scarf', 'cap'].forEach(z => { if (c.custom && c.custom[z]) Object.assign(state.custom[z], c.custom[z]); });
      state.qty = editing.qty || 1;
    }
  }

  // ------------------------------------------------- construction des UI ---
  const T = () => C.tiers[state.tier];
  const chip = (type, name, value, inner, cls) => `<label class="gr-chip ${cls || ''}"><input type="${type}" name="${name}" value="${esc(value)}"><span>${inner}</span></label>`;
  const dot = hex => `<i class="gr-dot" style="--c:${esc(hex)}"></i>`;

  // Gamme
  $('#tierPick').innerHTML = tierCodes.map(c => {
    const t = C.tiers[c];
    return `<label><input type="radio" name="tier" value="${esc(c)}"><span class="box"><strong>${esc(t.name)}</strong><small>${esc(t.description || '')}</small>
      <small class="mt-1 text-dark fw-semibold">Toge ${money(t.price_robe)} · Écharpe ${money(t.price_scarf)} · Toque ${money(t.price_cap)}</small></span></label>`;
  }).join('');
  // Pièces
  $('#partsPick').innerHTML = [['robe', 'Toge'], ['scarf', 'Écharpe'], ['cap', 'Toque']].map(([k, l]) =>
    `<label class="gr-chip"><input type="checkbox" name="part" value="${k}"><span>${l}</span></label>`).join('');

  // Groupes d'options
  GROUPS.forEach(g => {
    const box = $(`[data-chips="${g}"]`);
    if (!box) return;
    let list = O[g] || [];
    let html = '';
    if (g === 'cap_color') html += chip('radio', g, '', 'Assortie à la toge', 'gr-chip--same');
    html += list.map(o => chip('radio', g, o.code, o.hex ? dot(o.hex) + esc(o.label) : esc(o.label), o.hex ? 'gr-chip--color' : '')).join('');
    box.innerHTML = html || '<span class="text-muted small">Aucune option disponible.</span>';
  });
  // Texte : polices, couleurs de fil, suggestions
  ['scarf', 'cap'].forEach(z => {
    $(`[data-fonts="${z}"]`).innerHTML = (O.font || []).map(o => `<label class="gr-chip gr-fontchip"><input type="radio" name="font_${z}" value="${esc(o.code)}"><span style="font-family:${esc(o.font || 'inherit')}">${esc(o.label)}</span></label>`).join('');
    $(`[data-tcolors="${z}"]`).innerHTML = (O.text_color || []).map(o => chip('radio', 'tcolor_' + z, o.code, dot(o.hex) + esc(o.label), 'gr-chip--color')).join('');
    $(`[data-suggest="${z}"]`).innerHTML = SUGGEST[z].map(s => `<button type="button" class="btn btn-sm btn-outline-secondary py-0" data-fill="${z}" data-v="${esc(s)}">${esc(s)}</button>`).join('');
    const r = $(`#sz_${z}`); r.min = L.size_min; r.max = L.size_max; r.step = 1;
    $(`#txt_${z}`).maxLength = z === 'scarf' ? L.max_scarf : L.max_cap;
  });
  // Accessoires
  if (C.accessories.length) {
    $('#stAcc').hidden = false;
    $('#accList').innerHTML = C.accessories.map(a => `<label class="gr-accessory" data-acc="${a.id}"><input class="form-check-input mt-0" type="checkbox" value="${a.id}" name="acc">
      <span class="gr-accwrap"></span><span class="flex-grow-1"><strong class="d-block">${esc(a.name)}</strong><small class="text-muted">${esc(a.description || '')}</small><small class="d-block gr-accmsg text-warning-emphasis"></small></span>
      <span class="fw-semibold text-nowrap">+ ${money(a.price)}</span></label>`).join('');
    $$('[data-acc]').forEach(l => { const a = C.accessories.find(x => x.id == l.dataset.acc); $('.gr-accwrap', l).innerHTML = GradisOutfit.svg({ icon: a.icon }); });
  }

  // ------------------------------------------------------- calcul du prix ---
  function activeText(z) {
    const c = state.custom[z];
    if (!state.parts[z === 'scarf' ? 'scarf' : 'cap']) return null;
    if (!T()['text_' + z]) return null;
    const txt = (c.text || '').trim();
    return txt ? c : null;
  }
  function price() {
    const t = T(), lines = [];
    let unit = 0;
    const add = (label, amount) => { if (amount !== 0 || label) { lines.push({ label, amount }); } unit += amount; };
    if (state.parts.robe) { add(`Toge ${t.name}`, t.price_robe); }
    if (state.parts.scarf) { add(`Écharpe ${t.name}`, t.price_scarf); }
    if (state.parts.cap) { add(`Toque ${t.name}`, t.price_cap); }
    let extras = 0;
    const ex = (grp, code) => { const o = optOf(grp, code); if (o && okFor(o, state.tier)) extras += o.extra; };
    if (state.parts.robe) { ex('robe_color', state.robe_color); ex('size', state.size); }
    if (state.parts.scarf) ex('scarf_color', state.scarf_color);
    ex('trim_color', state.trim_color);
    if (state.parts.cap) { ex('cap_color', effCap()); ex('cap_style', state.cap_style); }
    if (extras) { lines.push({ label: 'Options', amount: extras }); unit += extras; }
    let acc = 0;
    state.accessories.forEach(id => { const a = C.accessories.find(x => x.id === id); if (a) acc += a.price; });
    if (acc) { lines.push({ label: 'Accessoires', amount: acc }); unit += acc; }
    let txt = 0;
    ['scarf', 'cap'].forEach(z => {
      const c = activeText(z);
      if (c) txt += L['price_text_' + z] + ((optOf('font', c.font) || {}).extra || 0) + ((optOf('text_color', c.color) || {}).extra || 0);
    });
    if (txt) { lines.push({ label: 'Personnalisation texte', amount: txt }); unit += txt; }
    return { lines, unit };
  }
  const effCap = () => state.cap_color || (state.parts.robe ? state.robe_color : (firstValid('cap_color', state.tier) || {}).code || 'noir');

  // ----------------------------------------------------- cohérence / gamme ---
  function enforce(notify) {
    const t = state.tier, changed = [];
    const fix = (grp, key, allowNull) => {
      const cur = state[key];
      if (allowNull && (cur === null || cur === '')) return;
      if (cur !== null && okFor(optOf(grp, cur), t)) return;
      const nv = firstValid(grp, t);
      if (cur !== null && cur !== undefined) changed.push(`« ${(optOf(grp, cur) || {}).label || cur} » n'est pas disponible en gamme ${C.tiers[t].name}`);
      state[key] = nv ? nv.code : null;
    };
    fix('robe_color', 'robe_color'); fix('scarf_color', 'scarf_color'); fix('trim_color', 'trim_color'); fix('cap_color', 'cap_color', true); fix('cap_style', 'cap_style');
    if (state.size && !okFor(optOf('size', state.size), t)) { state.size = null; changed.push('Votre taille doit être choisie à nouveau'); }
    // Accessoires incompatibles
    state.accessories = state.accessories.filter(id => {
      const a = C.accessories.find(x => x.id === id);
      const ok = a && (!a.tiers.length || a.tiers.includes(t));
      if (!ok && a) changed.push(`« ${a.name} » n'est pas compatible avec la gamme ${C.tiers[t].name}`);
      return ok;
    });
    ['scarf', 'cap'].forEach(z => {
      const c = state.custom[z];
      if (!okFor(optOf('font', c.font), t)) { const f = firstValid('font', t); if (f) { c.font = f.code; } }
      if (!okFor(optOf('text_color', c.color), t)) { const f = firstValid('text_color', t); if (f) { c.color = f.code; } }
    });
    if (notify && changed.length) toast(changed[0] + ' — choix remplacé.' + (changed.length > 1 ? ` (+${changed.length - 1})` : ''));
  }

  // ------------------------------------------------------- synchro de l'UI ---
  function sync() {
    const t = T();
    // Gamme & pièces
    $$('input[name=tier]').forEach(i => { i.checked = i.value === state.tier; });
    $('#tierMat').textContent = t.materials || '';
    $$('input[name=part]').forEach(i => { i.checked = !!state.parts[i.value]; });
    // visibilité des étapes
    $$('[data-needs]').forEach(el => { el.hidden = !state.parts[el.dataset.needs]; });
    // groupes
    GROUPS.forEach(g => {
      const box = $(`[data-chips="${g}"]`); if (!box) return;
      const note = $(`[data-note="${g}"]`);
      const cur = g === 'cap_color' ? (state.cap_color === null ? '' : state.cap_color) : state[g];
      const off = [];
      $$('input', box).forEach(i => {
        const o = optOf(g, i.value);
        const same = g === 'cap_color' && i.value === '';
        const ok = same ? state.parts.robe : okFor(o, state.tier);
        i.disabled = !ok;
        i.checked = (cur === null ? '' : cur) === i.value && ok;
        i.parentNode.title = ok ? '' : (same ? 'Nécessite la toge dans la composition' : `Non disponible en gamme ${t.name}`);
        if (!ok && o) off.push(o.label + (o.extra ? '' : ''));
      });
      if (g === 'cap_color' && cur === '' && !state.parts.robe) { state.cap_color = (firstValid('cap_color', state.tier) || {}).code || null; }
      if (note && ['robe_color', 'scarf_color', 'trim_color', 'cap_color', 'cap_style'].includes(g)) {
        const extras = (O[g] || []).filter(o => o.extra > 0 && okFor(o, state.tier)).map(o => `${o.label} (+ ${money(o.extra)})`);
        const base = note.dataset.base || (note.dataset.base = note.textContent);
        note.innerHTML = esc(base) + (extras.length ? ` ${esc(extras.join(' · '))}.` : '') +
          (off.length ? ` <span class="text-warning-emphasis"><i class="bi bi-lock"></i> Non disponible en gamme ${esc(t.name)} : ${esc(off.join(', '))}. Passez à une gamme supérieure pour en profiter.</span>` : '');
      }
    });
    // taille : étiquette cliquable WhatsApp
    $('#sizeHelp').href = waLink(`${GRADIS.waGreeting}\nJe voudrais un conseil de taille pour une tenue de graduation.`);
    // Accessoires
    $$('[data-acc]').forEach(l => {
      const a = C.accessories.find(x => x.id == l.dataset.acc);
      const inp = $('input', l), msg = $('.gr-accmsg', l);
      const compat = !a.tiers.length || a.tiers.includes(state.tier);
      const ok = compat;
      inp.disabled = !ok; inp.checked = state.accessories.includes(a.id) && ok;
      l.classList.toggle('is-disabled', !ok);
      msg.textContent = !compat ? `Non compatible avec la gamme ${t.name}.` : '';
    });
    // Personnalisation texte
    ['scarf', 'cap'].forEach(z => {
      const c = state.custom[z], allowed = !!t['text_' + z];
      const zone = $(`[data-zone="${z}"]`), lock = $(`[data-lock="${z}"]`);
      zone.setAttribute('aria-disabled', allowed ? 'false' : 'true');
      $$('input,button', zone).forEach(el => { el.disabled = !allowed; });
      lock.hidden = allowed;
      if (!allowed) lock.innerHTML = `<i class="bi bi-lock"></i> La gamme ${esc(t.name)} ne permet pas de personnaliser ${z === 'scarf' ? "l'écharpe" : 'la toque'}. Choisissez Premium ou Prestige pour ajouter votre texte.`;
      const ti = $(`#txt_${z}`);
      if (document.activeElement !== ti) ti.value = c.text;
      $(`#cnt_${z}`).textContent = `(${c.text.length}/${ti.maxLength})`;
      $(`#sz_${z}`).value = c.size; $(`#szv_${z}`).textContent = c.size;
      $$(`[data-fonts="${z}"] input`).forEach(i => { const ok = okFor(optOf('font', i.value), state.tier) && allowed; i.disabled = !ok; i.checked = i.value === c.font; i.parentNode.title = okFor(optOf('font', i.value), state.tier) ? '' : `Non disponible en gamme ${t.name}`; });
      $$(`[data-tcolors="${z}"] input`).forEach(i => { const ok = okFor(optOf('text_color', i.value), state.tier) && allowed; i.disabled = !ok; i.checked = i.value === c.color; i.parentNode.title = okFor(optOf('text_color', i.value), state.tier) ? '' : `Non disponible en gamme ${t.name}`; });
      $$(`[data-aligns="${z}"] input`).forEach(i => { i.checked = i.value === c.align; });
      if (z === 'scarf') $$('input[name=zone_scarf]').forEach(i => { i.checked = i.value === c.zone; });
      $(`#price_${z}`).textContent = allowed ? `Broderie : + ${money(L['price_text_' + z])} (si un texte est saisi). ${z === 'scarf' ? L.max_scarf : L.max_cap} caractères maximum.` : '';
    });
    paint();
  }

  // ---------------------------------------------------- aperçu / totaux -----
  function renderCfg() {
    const custom = {};
    ['scarf', 'cap'].forEach(z => { const c = activeText(z); if (c) custom[z] = Object.assign({}, c, { text: c.text.trim() }); });
    return { parts: Object.assign({}, state.parts), tier: state.tier, robe: state.robe_color, scarf: state.scarf_color, trim: state.trim_color,
      cap: effCap(), cap_style: state.cap_style, custom };
  }
  function paint() {
    $('#stage').innerHTML = GradisOutfit.svg(renderCfg());
    const names = [state.parts.robe && 'Toge', state.parts.scarf && 'Écharpe', state.parts.cap && 'Toque'].filter(Boolean);
    $('#stageTag').textContent = names.length === 3 ? 'Tenue complète' : names.join(' + ') || 'Aperçu';
    const p = price(), q = state.qty;
    $('#breakdown').innerHTML = p.lines.map(l => `<div class="gr-total__row"><span>${esc(l.label)}</span><span>${money(l.amount)}</span></div>`).join('') +
      (q > 1 ? `<div class="gr-total__row"><span>Prix unitaire</span><span>${money(p.unit)}</span></div>` : '');
    const tot = money(p.unit * q);
    $('#total').textContent = tot; $('#totalM').textContent = tot;
    $('#cQty').value = q;
    $('#waBtn').href = waLink(waMessage(p));
    $('#askBtn').href = waLink(`${GRADIS.waGreeting}\nJ'ai une question sur ma composition de tenue :\n${summaryLines().join('\n')}`);
  }

  // ------------------------------------------------------- résumé / WhatsApp ---
  function summaryLines() {
    const t = T(), L2 = [`Gamme : ${t.name}`];
    const lab = (g, c) => (optOf(g, c) || {}).label || c;
    const parts = [state.parts.robe && 'toge', state.parts.scarf && 'écharpe', state.parts.cap && 'toque'].filter(Boolean);
    L2.push('Pièces : ' + parts.join(', '));
    if (state.parts.robe) { L2.push('Toge : ' + lab('robe_color', state.robe_color)); L2.push('Taille : ' + (state.size || 'à préciser')); }
    if (state.parts.scarf) L2.push('Écharpe : ' + lab('scarf_color', state.scarf_color));
    L2.push(`${C.label_trim} : ${lab('trim_color', state.trim_color)}`);
    if (state.parts.cap) { L2.push('Toque : ' + lab('cap_color', effCap())); if (state.cap_style !== 'standard') L2.push('Gland : ' + lab('cap_style', state.cap_style)); }
    state.accessories.forEach(id => { const a = C.accessories.find(x => x.id === id); if (a) L2.push('Accessoire : ' + a.name); });
    ['scarf', 'cap'].forEach(z => {
      const c = activeText(z);
      if (c) L2.push(`Texte ${z === 'scarf' ? 'écharpe' : 'toque'} : « ${c.text.trim()} » (${lab('font', c.font)}, ${lab('text_color', c.color)}, ${c.size}px, ${c.align})`);
    });
    return L2;
  }
  function waMessage(p) {
    p = p || price();
    return [GRADIS.waGreeting, 'Je souhaite commander une tenue personnalisée :', '', ...summaryLines(), '', `Quantité : ${state.qty}`, `Prix estimé : ${money(p.unit * state.qty)}`].join('\n');
  }

  // ------------------------------------------------------------ événements ---
  const root = $('#conf');
  const bump = () => { enforce(false); sync(); };
  root.addEventListener('change', e => {
    const i = e.target; if (!(i instanceof HTMLInputElement)) return;
    const n = i.name;
    if (n === 'tier') { state.tier = i.value; enforce(true); sync(); return; }
    if (n === 'part') {
      const next = Object.assign({}, state.parts, { [i.value]: i.checked });
      if (!next.robe && !next.scarf && !next.cap) { i.checked = true; toast('Gardez au moins une pièce dans votre composition.', { error: true }); return; }
      state.parts = next; bump(); return;
    }
    if (n === 'cap_color') { state.cap_color = i.value === '' ? null : i.value; sync(); return; }
    if (GROUPS.includes(n)) { state[n] = i.value; sync(); return; }
    if (n === 'acc') { const id = +i.value; state.accessories = i.checked ? [...new Set([...state.accessories, id])] : state.accessories.filter(x => x !== id); sync(); return; }
    const m = n.match(/^(font|tcolor|align|zone)_(scarf|cap)$/);
    if (m) { const k = { font: 'font', tcolor: 'color', align: 'align', zone: 'zone' }[m[1]]; state.custom[m[2]][k] = i.value; sync(); }
  });
  root.addEventListener('input', e => {
    const t = e.target;
    if (t.dataset.text) { state.custom[t.dataset.text].text = t.value.replace(/[\r\n\t]+/g, ' '); sync(); }
    if (t.dataset.size) { state.custom[t.dataset.size].size = +t.value; sync(); }
  });
  root.addEventListener('click', e => {
    const f = e.target.closest('[data-fill]');
    if (f) { const z = f.dataset.fill; state.custom[z].text = f.dataset.v.slice(0, z === 'scarf' ? L.max_scarf : L.max_cap); sync(); }
  });
  $('#cQty').addEventListener('change', e => { state.qty = Math.max(1, Math.min(L.max_qty, parseInt(e.target.value, 10) || 1)); paint(); });

  // ------------------------------------------------------ ajout au panier ---
  function buildConfig() {
    const cfg = { tier: state.tier, parts: Object.assign({}, state.parts), trim_color: state.trim_color, accessories: state.accessories.slice(), custom: {} };
    if (state.parts.robe) { cfg.robe_color = state.robe_color; cfg.size = state.size; }
    if (state.parts.scarf) cfg.scarf_color = state.scarf_color;
    if (state.parts.cap) { cfg.cap_color = state.cap_color; cfg.cap_style = state.cap_style; }
    ['scarf', 'cap'].forEach(z => { const c = activeText(z); if (c) cfg.custom[z] = Object.assign({}, c, { text: c.text.trim() }); });
    return cfg;
  }
  function validate() {
    if (state.parts.robe && !state.size) return { msg: 'Choisissez votre taille pour la toge.', el: $('[data-chips="size"]') };
    return null;
  }
  function add() {
    const err = $('#confError'); err.hidden = true;
    const v = validate();
    if (v) { err.textContent = v.msg; err.hidden = false; v.el.scrollIntoView({ behavior: 'smooth', block: 'center' }); toast(v.msg, { error: true }); return; }
    const item = { type: 'custom', config: buildConfig(), qty: state.qty };
    if (editing) { Cart.replace(editing.id, item); Cart.update(editing.id, { qty: state.qty }); location.href = Gradis.url('panier.php'); return; }
    Cart.add(item);
    toast('Composition ajoutée au panier', { link: { href: Gradis.url('panier.php'), label: 'Voir le panier' }, ms: 5000 });
  }
  $('#addBtn').addEventListener('click', add);
  $('#addBtnM').addEventListener('click', add);
  if (editing) { $('#addBtn span').textContent = 'Mettre à jour la composition'; $('#addBtnM span').textContent = 'Mettre à jour'; }
  if (PRE.product_name) { $('#stPreset').hidden = false; $('#presetNote').innerHTML = `<i class="bi bi-info-circle"></i> Point de départ : « ${esc(PRE.product_name)} ». Modifiez librement chaque détail.`; }
  if (editing) { $('#stPreset').hidden = false; $('#presetNote').innerHTML = '<i class="bi bi-pencil"></i> Vous modifiez une composition de votre panier.'; }

  root.removeAttribute('data-loading');
  enforce(false);
  sync();
})();
