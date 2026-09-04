/* MODAL-CATALOG — Interacción del catálogo de laboratorio AHT
   Tabs, búsqueda, apertura/cierre, toggles de slots.
   Trigger: ?modal_catalog=1 (debug, no visible para jugadores).
   Sin dependencias del motor del juego. */

(function () {
  'use strict';

  var overlay = document.getElementById('catalogOverlay');
  var closeBtn = document.getElementById('catalogClose');
  var tabsWrap = document.getElementById('catalogTabs');
  var searchSlot = document.getElementById('catalogSearch');
  var footerSlot = document.getElementById('catalogFooter');
  var searchInput = overlay ? overlay.querySelector('.aht-catalog-search-input') : null;
  var cancelBtn = document.getElementById('catalogBtnCancel');
  var confirmBtn = document.getElementById('catalogBtnConfirm');

  /* Lab controls */
  var labPanel = document.getElementById('catalogLabPanel');
  var toggleTabs = document.getElementById('catLabToggleTabs');
  var toggleSearch = document.getElementById('catLabToggleSearch');
  var toggleFooter = document.getElementById('catLabToggleFooter');

  if (!overlay) return;

  /* --- Abrir / Cerrar --- */
  function openCatalog() {
    overlay.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    if (searchInput) searchInput.focus();
  }

  function closeCatalog() {
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
  }

  /* Expone apertura global para testing desde consola */
  window.ahtCatalogOpen = openCatalog;
  window.ahtCatalogClose = closeCatalog;

  if (closeBtn) closeBtn.addEventListener('click', closeCatalog);
  if (cancelBtn) cancelBtn.addEventListener('click', closeCatalog);
  if (confirmBtn) confirmBtn.addEventListener('click', function () {
    closeCatalog();
  });

  /* Cierre con click en overlay (fuera del frame) */
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closeCatalog();
  });

  /* Cierre con Escape */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
      closeCatalog();
    }
  });

  /* --- Tabs --- */
  if (tabsWrap) {
    tabsWrap.addEventListener('click', function (e) {
      var tab = e.target.closest('.aht-catalog-tab');
      if (!tab) return;

      var target = tab.getAttribute('data-cat-tab');
      if (!target) return;

      /* Activar tab */
      tabsWrap.querySelectorAll('.aht-catalog-tab').forEach(function (t) {
        t.classList.remove('is-active');
        t.setAttribute('aria-selected', 'false');
      });
      tab.classList.add('is-active');
      tab.setAttribute('aria-selected', 'true');

      /* Mostrar vista */
      var body = document.getElementById('catalogBody');
      if (body) {
        body.querySelectorAll('[data-cat-view]').forEach(function (v) {
          v.hidden = v.getAttribute('data-cat-view') !== target;
        });
      }
    });
  }

  /* --- Búsqueda simple --- */
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      var q = this.value.toLowerCase().trim();
      var body = document.getElementById('catalogBody');
      if (!body) return;

      var items = body.querySelectorAll('.cat-grid-item, .cat-rel-card, .cat-list-row, .cat-shelf-item');
      items.forEach(function (item) {
        var text = item.textContent.toLowerCase();
        item.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
      });
    });
  }

  /* --- Lab toggles (solo visibles con ?modal_catalog=1) --- */
  function applyToggle(el, slot, checked) {
    if (!slot) return;
    slot.hidden = !checked;
    if (el) el.checked = checked;
  }

  if (toggleTabs) {
    toggleTabs.addEventListener('change', function () {
      applyToggle(toggleTabs, tabsWrap, this.checked);
    });
  }
  if (toggleSearch) {
    toggleSearch.addEventListener('change', function () {
      applyToggle(toggleSearch, searchSlot, this.checked);
    });
  }
  if (toggleFooter) {
    toggleFooter.addEventListener('change', function () {
      applyToggle(toggleFooter, footerSlot, this.checked);
    });
  }

  /* --- Trigger: ?modal_catalog=1 --- */
  var params = new URLSearchParams(window.location.search);
  if (params.get('modal_catalog') === '1') {
    /* Mostrar panel de laboratorio */
    if (labPanel) labPanel.hidden = false;

    /* Auto-abrir el catálogo tras un tick para que el DOM esté listo */
    setTimeout(openCatalog, 80);
  }
})();
