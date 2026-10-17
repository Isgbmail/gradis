/**
 * Gradis — rendu SVG de la tenue (toge, écharpe, toque, accessoires).
 * Utilisé par le configurateur, les cartes produit, le panier, la commande et l'admin.
 * La palette (code → hex) vient de la base de données via window.GRADIS_PALETTE.
 */
(function () {
  'use strict';
  let uid = 0;
  const pal = () => window.GRADIS_PALETTE || { colors: {}, fonts: {} };

  function hexOf(group, code, fallback) {
    const c = pal().colors;
    if (c[group] && c[group][code]) return c[group][code];
    for (const g of ['robe_color', 'scarf_color', 'trim_color', 'cap_color', 'text_color']) {
      if (c[g] && c[g][code]) return c[g][code];
    }
    return fallback || '#222222';
  }
  function rgb(h) {
    h = h.replace('#', '');
    if (h.length === 3) h = h.split('').map(x => x + x).join('');
    return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
  }
  function shade(hex, amt) { // amt -1 (noir) … +1 (blanc)
    const [r, g, b] = rgb(hex);
    const t = amt < 0 ? 0 : 255, p = Math.abs(amt);
    const f = v => Math.round((t - v) * p + v);
    return '#' + [f(r), f(g), f(b)].map(v => v.toString(16).padStart(2, '0')).join('');
  }
  function lum(hex) { const [r, g, b] = rgb(hex); return (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255; }
  const esc = s => String(s).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  const mirror = g => `<g transform="matrix(-1 0 0 1 400 0)">${g}</g>`;

  function partsOf(cfg) {
    if (cfg.parts) return { robe: !!cfg.parts.robe, scarf: !!cfg.parts.scarf, cap: !!cfg.parts.cap };
    switch (cfg.view) {
      case 'robe': return { robe: true, scarf: false, cap: false };
      case 'scarf': return { robe: false, scarf: true, cap: false };
      case 'cap': return { robe: false, scarf: false, cap: true };
      default: return { robe: true, scarf: true, cap: true };
    }
  }

  // ---------------------------------------------------------- accessoires --
  function accessorySvg(icon) {
    const gold = '#D4AF37', goldD = '#A98A22', navy = '#0B132B';
    const shapes = {
      tube: `<g transform="rotate(-28 100 100)"><rect x="48" y="78" width="104" height="44" rx="10" fill="${navy}"/>
        <rect x="48" y="78" width="104" height="44" rx="10" fill="url(#shine)"/>
        <rect x="64" y="78" width="9" height="44" fill="${gold}"/><rect x="127" y="78" width="9" height="44" fill="${gold}"/>
        <ellipse cx="48" cy="100" rx="7" ry="22" fill="#1b2748"/><ellipse cx="152" cy="100" rx="7" ry="22" fill="#1b2748"/></g>`,
      box: `<rect x="48" y="84" width="104" height="70" rx="6" fill="${navy}"/><rect x="42" y="70" width="116" height="22" rx="5" fill="#16213f"/>
        <rect x="94" y="70" width="12" height="84" fill="${gold}"/><path d="M100 70 C80 42 58 56 76 70 Z M100 70 C120 42 142 56 124 70 Z" fill="${gold}" stroke="${goldD}" stroke-width="1.5"/>`,
      charm: `<path d="M100 28 V54" stroke="${goldD}" stroke-width="3" fill="none"/><circle cx="100" cy="28" r="9" fill="none" stroke="${gold}" stroke-width="4"/>
        <circle cx="100" cy="100" r="40" fill="${gold}" stroke="${goldD}" stroke-width="3"/><circle cx="100" cy="100" r="31" fill="none" stroke="${goldD}" stroke-width="1.5"/>
        <path d="M100 80 L105 94 L120 95 L108 104 L112 119 L100 110 L88 119 L92 104 L80 95 L95 94 Z" fill="${goldD}"/>`,
      bag: `<path d="M62 52 H138 L148 160 H52 Z" fill="${navy}"/><path d="M62 52 H138 L148 160 H52 Z" fill="url(#shine)"/>
        <rect x="88" y="40" width="24" height="14" rx="7" fill="none" stroke="${gold}" stroke-width="4"/><rect x="95" y="64" width="10" height="70" rx="5" fill="${gold}" opacity=".9"/>`,
    };
    return `<svg class="gr-outfit" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Accessoire">
      <defs><linearGradient id="shine" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".18"/><stop offset=".5" stop-color="#fff" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".25"/></linearGradient></defs>
      <ellipse cx="100" cy="172" rx="52" ry="7" fill="rgba(0,0,0,.14)"/>${shapes[icon] || shapes.bag}</svg>`;
  }

  // ----------------------------------------------------------------- texte --
  function fontOf(code) { return pal().fonts[code] || "'Cormorant Garamond', serif"; }

  function scarfText(cz, side) {
    if (!cz || !cz.text) return '';
    if (!(cz.zone === 'both' || cz.zone === side)) return '';
    const size = Math.max(8, Math.min(30, +cz.size || 14));
    const cx = side === 'left' ? 177 : 223;
    const x = cx + size * 0.32;
    const pos = { left: [434, 'start'], center: [322, 'middle'], right: [208, 'end'] }[cz.align] || [322, 'middle'];
    const color = hexOf('text_color', cz.color, '#D4AF37');
    return `<text transform="translate(${x} ${pos[0]}) rotate(-90)" text-anchor="${pos[1]}" font-family="${esc(fontOf(cz.font))}" font-size="${size}" fill="${color}" style="letter-spacing:.04em">${esc(cz.text)}</text>`;
  }

  function capText(cz, clipId) {
    if (!cz || !cz.text) return '';
    const size = Math.max(8, Math.min(30, +cz.size || 14));
    const color = hexOf('text_color', cz.color, '#D4AF37');
    const ax = { left: [-62, 'start'], center: [0, 'middle'], right: [62, 'end'] }[cz.align] || [0, 'middle'];
    return `<g clip-path="url(#${clipId})"><text transform="translate(${200 + ax[0]} 62) scale(1 .5)" text-anchor="${ax[1]}" font-family="${esc(fontOf(cz.font))}" font-size="${size * 1.05}" fill="${color}">${esc(cz.text)}</text></g>`;
  }

  // ------------------------------------------------------------- principal --
  function outfit(cfg, opts) {
    cfg = cfg || {};
    if (cfg.view === 'accessory' || cfg.icon) return accessorySvg(cfg.icon || 'bag');
    const id = 'o' + (++uid);
    const parts = partsOf(cfg);
    const robe = hexOf('robe_color', cfg.robe, '#1A1A1C');
    const scarf = hexOf('scarf_color', cfg.scarf, '#1C1C1C');
    const trim = hexOf('trim_color', cfg.trim, '#D4AF37');
    const capC = hexOf('cap_color', cfg.cap || cfg.robe, '#1A1A1C');
    const custom = cfg.custom || {};
    const style = cfg.cap_style || 'standard';
    const rStroke = lum(robe) > 0.6 ? 'rgba(0,0,0,.22)' : 'rgba(255,255,255,.10)';
    const sStroke = lum(scarf) > 0.6 ? 'rgba(0,0,0,.2)' : 'rgba(255,255,255,.12)';
    const skin = '#E2C9B2', hair = '#2A201B';
    let s = '';

    // Défs : ombrages
    s += `<defs>
      <linearGradient id="${id}g" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#000" stop-opacity=".32"/><stop offset=".28" stop-color="#fff" stop-opacity=".07"/><stop offset=".5" stop-color="#000" stop-opacity=".05"/><stop offset=".72" stop-color="#fff" stop-opacity=".05"/><stop offset="1" stop-color="#000" stop-opacity=".34"/></linearGradient>
      <linearGradient id="${id}v" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".08"/><stop offset="1" stop-color="#000" stop-opacity=".22"/></linearGradient>
      <clipPath id="${id}c"><polygon points="96,58 200,30 304,58 200,86"/></clipPath>
    </defs>`;
    s += `<ellipse cx="200" cy="509" rx="150" ry="9" fill="rgba(0,0,0,.18)"/>`;

    // --- Corps : toge ou haut neutre
    if (parts.robe) {
      const body = 'M118,184 C118,163 152,156 200,156 C248,156 282,163 282,184 L326,506 L74,506 Z';
      const sleeve = 'M124,178 C96,196 74,262 60,412 C70,432 106,438 121,424 C124,340 136,262 150,206 Z';
      s += `<path d="${body}" fill="${robe}" stroke="${rStroke}" stroke-width="1.2"/>`;
      s += `<path d="${body}" fill="url(#${id}g)"/><path d="${body}" fill="url(#${id}v)"/>`;
      s += `<path d="M168,200 C160,300 150,400 136,500 M232,200 C240,300 250,400 264,500 M200,240 L200,506" stroke="rgba(0,0,0,.16)" stroke-width="2" fill="none"/>`;
      s += `<path d="M74,506 L326,506" stroke="${trim}" stroke-width="3.5"/>`;
      const sl = `<path d="${sleeve}" fill="${robe}" stroke="${rStroke}" stroke-width="1.2"/><path d="${sleeve}" fill="url(#${id}g)"/>
        <path d="M60,412 C70,432 106,438 121,424" stroke="${trim}" stroke-width="3.5" fill="none"/>
        <path d="M96,230 C84,290 76,350 70,400" stroke="rgba(0,0,0,.16)" stroke-width="2" fill="none"/>`;
      s += sl + mirror(sl);
    } else {
      const shirt = '#CFCAC0';
      const body = 'M126,192 C126,168 158,160 200,160 C242,160 274,168 274,192 L282,506 L118,506 Z';
      s += `<path d="${body}" fill="${shirt}" stroke="rgba(0,0,0,.15)" stroke-width="1.2"/><path d="${body}" fill="url(#${id}g)"/>`;
      s += `<path d="M164,160 L200,200 L236,160" stroke="rgba(0,0,0,.18)" stroke-width="2" fill="none"/>`;
    }

    // --- Cou, tête
    s += `<rect x="186" y="140" width="28" height="30" fill="${skin}"/><path d="M186,150 Q200,162 214,150 V140 H186 Z" fill="rgba(0,0,0,.08)"/>`;
    s += `<ellipse cx="200" cy="112" rx="32" ry="38" fill="${skin}"/>`;
    s += `<path d="M167,112 C165,76 235,76 233,112 C230,96 218,88 200,88 C182,88 170,96 167,112 Z" fill="${hair}"/>`;
    // col en V
    s += `<path d="M170,158 L200,230 L230,158 Z" fill="${parts.robe ? '#EFEAE0' : '#B9B4AA'}"/>`;
    if (parts.robe) s += `<path d="M162,160 L200,236 L238,160" stroke="${trim}" stroke-width="3.2" fill="none" stroke-linejoin="round"/>`;

    // --- Écharpe (deux bandes)
    if (parts.scarf) {
      const band = 'M156,160 C172,153 190,158 200,170 L198,470 L154,474 Z';
      const grp = `<path d="${band}" fill="${scarf}" stroke="${sStroke}" stroke-width="1"/><path d="${band}" fill="url(#${id}g)" opacity=".9"/>
        <path d="M156,160 L154,474 M200,170 L198,470" stroke="${trim}" stroke-width="3" fill="none"/>
        <path d="M154,474 L198,470" stroke="${trim}" stroke-width="4.5"/>
        <path d="M155,448 L199,446" stroke="${trim}" stroke-width="2.5"/>
        <path d="${[0, 1, 2, 3, 4, 5, 6, 7].map(i => `M${157 + i * 5.2},${474 - i * .5} v15`).join(' ')}" stroke="${shade(scarf, -.25)}" stroke-width="2.4"/>`;
      s += grp + mirror(grp);
      s += scarfText(custom.scarf, 'left') + scarfText(custom.scarf, 'right');
    }

    // --- Toque
    if (parts.cap) {
      const cap = capC, edge = shade(cap, -.42), top = shade(cap, lum(cap) < .2 ? .06 : -.02);
      const L = style === 'long' ? 70 : 48;
      s += `<path d="M164,66 L164,90 Q200,104 236,90 L236,66 Z" fill="${shade(cap, -.12)}" stroke="${rStroke}" stroke-width="1"/>`;
      s += `<polygon points="96,58 200,86 304,58 304,64 200,92 96,64" fill="${edge}"/>`;
      s += `<polygon points="96,58 200,30 304,58 200,86" fill="${top}" stroke="${lum(cap) > .6 ? 'rgba(0,0,0,.25)' : 'rgba(255,255,255,.14)'}" stroke-width="1"/>`;
      s += capText(custom.cap, id + 'c');
      s += `<circle cx="200" cy="58" r="4.4" fill="${trim}" stroke="rgba(0,0,0,.25)" stroke-width=".8"/>`;
      s += `<path d="M200,58 Q252,66 290,62" stroke="${trim}" stroke-width="2.4" fill="none"/>`;
      s += `<circle cx="290" cy="62" r="3.6" fill="${trim}"/>`;
      s += `<path d="M285.5,66 L294.5,66 L297,${66 + L} L283,${66 + L} Z" fill="${trim}"/>`;
      s += `<path d="${[285.5, 288, 290, 292, 294.5].map((x, i) => `M${x},68 L${283 + i * 3.5},${66 + L}`).join(' ')}" stroke="${shade(trim, -.3)}" stroke-width=".9"/>`;
      if (style === 'breloque') {
        s += `<circle cx="290" cy="${66 + L + 11}" r="8" fill="${trim}" stroke="${shade(trim, -.3)}" stroke-width="1.5"/><path d="M290,${66 + L + 6} l1.6,3.4 3.6,.4 -2.7,2.4 .8,3.6 -3.3,-1.9 -3.3,1.9 .8,-3.6 -2.7,-2.4 3.6,-.4 Z" fill="${shade(trim, -.35)}"/>`;
      }
    }

    // Cadrage
    let vb = '30 8 340 506';
    if (parts.cap && !parts.robe && !parts.scarf) vb = '70 6 260 220';
    else if (parts.scarf && !parts.robe && !parts.cap) vb = '70 110 260 410';
    return `<svg class="gr-outfit" viewBox="${vb}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Aperçu de la tenue">${s}</svg>`;
  }

  function mount(el, cfg) { el.innerHTML = outfit(cfg); }
  function hydrate(root) {
    (root || document).querySelectorAll('[data-outfit]').forEach(el => {
      try { mount(el, JSON.parse(el.getAttribute('data-outfit'))); } catch (e) { /* ignore */ }
    });
  }
  window.GradisOutfit = { svg: outfit, mount, hydrate, hexOf };
  document.addEventListener('DOMContentLoaded', () => hydrate());
})();
