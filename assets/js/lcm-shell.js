(function () {
  const menuButton = document.getElementById('lcmMenuButton');
  const closeButton = document.getElementById('lcmMenuClose');
  const backdrop = document.getElementById('lcmMenuBackdrop');
  const frame = document.getElementById('lcmFrame');
  const nav = document.getElementById('lcmNav');
  const homeButton = document.getElementById('lcmHomeButton');
  const brandHome = document.getElementById('lcmBrandHome');
  const pageTitle = document.getElementById('lcmPageTitle');
  const pageSubtitle = document.getElementById('lcmPageSubtitle');

  const apps = [
    {
      section: 'Principal',
      title: 'Inicio',
      description: 'Panel general',
      src: 'homepage.php',
      icon: '⌂',
      assigned: true,
      home: true
    },
    {
      section: 'Producción',
      title: 'Control de técnicos',
      description: 'Instalaciones por técnico',
      src: 'tma_instalaciones/control_tecnicos.php',
      icon: '▦',
      assigned: true
    }
  ];

  function setMenu(open) {
    document.body.classList.toggle('lcm-menu-open', open);
    const menu = document.getElementById('lcmMenu');
    if (menu) menu.setAttribute('aria-hidden', open ? 'false' : 'true');
  }

  function escapeAttr(value) {
    return String(value).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
  }

  function openApp(item) {
    document.querySelectorAll('.lcm-nav-item').forEach(node => node.classList.remove('active'));
    item.classList.add('active');
    frame.src = item.dataset.src;
    frame.title = item.dataset.title;
    pageTitle.textContent = item.dataset.title;
    pageSubtitle.textContent = item.dataset.subtitle;
    setMenu(false);
  }

  function openHome() {
    const homeItem = document.querySelector('.lcm-nav-item[data-home="true"]');
    if (homeItem) openApp(homeItem);
  }

  function openAppBySrc(src, title, subtitle) {
    const item = document.querySelector(`.lcm-nav-item[data-src="${CSS.escape(src)}"]`);
    if (item) {
      openApp(item);
      return;
    }
    frame.src = src;
    frame.title = title || 'Aplicación';
    pageTitle.textContent = title || 'Aplicación';
    pageSubtitle.textContent = subtitle || '';
    setMenu(false);
  }

  function renderMenu() {
    const assignedApps = apps.filter(app => app.assigned);
    if (!assignedApps.length) {
      nav.innerHTML = '<div class="lcm-nav-section">Sin accesos</div>';
      return;
    }

    const sections = [...new Set(assignedApps.map(app => app.section))];
    nav.innerHTML = sections.map(section => {
      const items = assignedApps
        .filter(app => app.section === section)
        .map(app => `
          <button class="lcm-nav-item${app.home ? ' active' : ''}" type="button" data-title="${escapeAttr(app.title)}" data-subtitle="${escapeAttr(app.description)}" data-src="${escapeAttr(app.src)}" data-home="${app.home ? 'true' : 'false'}">
            <span class="lcm-nav-icon">${app.icon}</span>
            <span class="lcm-nav-text"><strong>${escapeAttr(app.title)}</strong><span>${escapeAttr(app.description)}</span></span>
          </button>
        `).join('');
      return `<div class="lcm-nav-section">${escapeAttr(section)}</div>${items}`;
    }).join('');

    document.querySelectorAll('.lcm-nav-item').forEach(item => {
      item.addEventListener('click', () => openApp(item));
    });
  }

  menuButton?.addEventListener('click', () => setMenu(!document.body.classList.contains('lcm-menu-open')));
  closeButton?.addEventListener('click', () => setMenu(false));
  backdrop?.addEventListener('click', () => setMenu(false));
  homeButton?.addEventListener('click', openHome);
  brandHome?.addEventListener('click', event => {
    event.preventDefault();
    openHome();
  });
  window.addEventListener('message', event => {
    if (event.data?.type === 'open-sidebar' || event.data?.type === 'open-menu') setMenu(true);
    if (event.data?.type === 'open-app' && event.data.src) {
      openAppBySrc(event.data.src, event.data.title, event.data.subtitle);
    }
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') setMenu(false);
  });

  renderMenu();
})();
