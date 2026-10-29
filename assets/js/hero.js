/* Gradis — carrousel du bandeau d'accueil (fondu, défilement auto, pause, clavier, balayage tactile) */
(function () {
  const root = document.querySelector('[data-hero]');
  const header = document.querySelector('.gr-header');
  if (header) {
    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 24);
    onScroll(); window.addEventListener('scroll', onScroll, { passive: true });
  }
  if (!root) return;
  const slides = [...root.querySelectorAll('[data-slide]')];
  const figs = [...root.querySelectorAll('[data-fig]')];
  const dots = [...root.querySelectorAll('[data-hero-dot]')];
  const cap = root.querySelector('[data-hero-cap]');
  const pauseBtn = root.querySelector('[data-hero-pause]');
  const captions = (window.GRADIS_HERO_CAPTIONS || []);
  if (slides.length < 2) return;

    const DELAY = 5000;
  let i = 0, timer = null, userPaused = false, hovering = false;

  function go(n) {
    const prev = i;
    i = (n + slides.length) % slides.length;
    slides.forEach((s, k) => { s.classList.toggle('is-prev', k === prev && k !== i); s.classList.toggle('is-active', k === i); });
    figs.forEach((f, k) => f.classList.toggle('is-active', k === i));
    dots.forEach((d, k) => { d.classList.toggle('is-active', k === i); k === i ? d.setAttribute('aria-current', 'true') : d.removeAttribute('aria-current'); });
    if (cap && captions[i] !== undefined) cap.textContent = captions[i];
  }
  function stop() { clearInterval(timer); timer = null; }
  function start() { stop(); if (!userPaused && !hovering && !document.hidden) timer = setInterval(() => go(i + 1), DELAY); }
  function setPaused(p) {
    userPaused = p; start();
    if (pauseBtn) {
      pauseBtn.innerHTML = '<i class="bi bi-' + (p ? 'play-fill' : 'pause-fill') + '"></i>';
      pauseBtn.setAttribute('aria-label', p ? 'Reprendre le défilement' : 'Mettre en pause le défilement');
    }
  }

  root.querySelector('[data-hero-next]')?.addEventListener('click', () => { go(i + 1); start(); });
  root.querySelector('[data-hero-prev]')?.addEventListener('click', () => { go(i - 1); start(); });
  dots.forEach(d => d.addEventListener('click', () => { go(+d.dataset.heroDot); start(); }));
  pauseBtn?.addEventListener('click', () => setPaused(!userPaused));
  document.addEventListener('visibilitychange', start);
  root.addEventListener('keydown', e => { if (e.key === 'ArrowRight') { go(i + 1); start(); } if (e.key === 'ArrowLeft') { go(i - 1); start(); } });

  let x0 = null;
  root.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; }, { passive: true });
  root.addEventListener('touchend', e => {
    if (x0 === null) return;
    const dx = e.changedTouches[0].clientX - x0; x0 = null;
    if (Math.abs(dx) > 45) { go(i + (dx < 0 ? 1 : -1)); start(); }
  }, { passive: true });

  start();
})();
