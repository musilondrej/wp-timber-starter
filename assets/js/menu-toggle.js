const toggle = document.querySelector('.menu-toggle');

if (toggle) {
  const setOpen = (open) => toggle.setAttribute('aria-expanded', String(open));
  const isOpen = () => toggle.getAttribute('aria-expanded') === 'true';

  toggle.hidden = false;
  toggle.addEventListener('click', () => setOpen(!isOpen()));

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && isOpen()) {
      setOpen(false);
      toggle.focus();
    }
  });
}
