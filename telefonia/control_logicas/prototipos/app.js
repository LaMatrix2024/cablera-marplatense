(() => {
    'use strict';

    const buttons = [...document.querySelectorAll('[data-theme-button]')];
    const savedTheme = localStorage.getItem('lcm-visual-prototype');
    const requestedTheme = new URLSearchParams(window.location.search).get('tema');

    const applyTheme = (theme) => {
        document.documentElement.dataset.theme = theme;
        buttons.forEach((button) => {
            const active = button.dataset.themeButton === theme;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', String(active));
        });
        localStorage.setItem('lcm-visual-prototype', theme);
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => applyTheme(button.dataset.themeButton));
    });

    if (buttons.some((button) => button.dataset.themeButton === requestedTheme)) {
        applyTheme(requestedTheme);
    } else if (buttons.some((button) => button.dataset.themeButton === savedTheme)) {
        applyTheme(savedTheme);
    } else {
        applyTheme('bruma');
    }
})();
