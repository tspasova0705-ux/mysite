(() => {
  // reveal on scroll
  const io = new IntersectionObserver(es => es.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
  }), { threshold: .05 });
  document.querySelectorAll('.reveal').forEach((el, i) => { el.style.transitionDelay = (i % 4) * 60 + 'ms'; io.observe(el); });

  // module accordion
  document.querySelectorAll('.mod-head').forEach(h => h.addEventListener('click', () => {
    const m = h.closest('.mod');
    m.classList.toggle('open');
    h.setAttribute('aria-expanded', m.classList.contains('open'));
  }));

  // confirm dangerous actions
  document.addEventListener('submit', e => {
    const msg = e.target.dataset.confirm || e.submitter?.dataset.confirm;
    if (msg && !confirm(msg)) e.preventDefault();
  });

  // admin review: "mark all as done"
  document.querySelectorAll('[data-mark-all]').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll(`input[type=radio][value="${b.dataset.markAll}"]`).forEach(r => r.checked = true);
  }));
})();
