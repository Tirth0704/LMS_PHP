(function () {
  const toggles = document.querySelectorAll('[data-confirm]');
  toggles.forEach((toggle) => {
    toggle.addEventListener('click', (event) => {
      const message = toggle.getAttribute('data-confirm') || 'Are you sure?';
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
})();
