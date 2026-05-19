<?php if(!class_exists('Rain\Tpl')){exit;}?><footer class="app-footer">
  <strong>© 2024–2026 Governo do Estado do Amazonas</strong>
  <span class="ms-2 d-block d-md-inline">Plataforma desenvolvida por MS Tecnologia</span>
</footer>
</div>


<div class="portal-toast-container" id="portalToastContainer" aria-live="polite" aria-atomic="true"></div>
<div class="portal-loading-overlay" id="portalLoadingOverlay" aria-hidden="true">
  <div class="portal-loading-card">
    <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
    <div class="fw-bold text-dark">Processando</div>
    <div class="text-muted small">Aguarde enquanto concluímos a operação.</div>
  </div>
</div>
<div class="modal fade" id="portalConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius:22px;overflow:hidden;">
      <div class="modal-header bg-light border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-shield-check me-2 text-primary"></i>Confirmar ação</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body pt-0">
        <p class="text-muted mb-0" id="portalConfirmMessage">Deseja continuar?</p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="portalConfirmOk">Confirmar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.10.1/browser/overlayscrollbars.browser.es6.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/res/admin/dist/js/adminlte.js"></script>
<script src="/res/admin/dist/js/app.js"></script>
<script>
  const currentYearEl = document.getElementById('current-year');
  if (currentYearEl) currentYearEl.textContent = new Date().getFullYear();
  document.addEventListener('DOMContentLoaded', function () {
    const sidebarWrapper = document.querySelector('.sidebar-wrapper');
    if (sidebarWrapper && typeof OverlayScrollbarsGlobal?.OverlayScrollbars !== 'undefined') {
      OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
        scrollbars: { theme: 'os-theme-light', autoHide: 'leave', clickScroll: true }
      });
    }

    const currentPath = window.location.pathname.replace(/\/$/, '') || '/admin';
    document.querySelectorAll('.sidebar-menu .nav-link').forEach(function (link) {
      const href = (link.getAttribute('href') || '').replace(/\/$/, '');
      if (!href) return;
      if (currentPath === href || (href !== '/admin' && currentPath.startsWith(href))) {
        link.classList.add('active');
      }
    });

    document.querySelectorAll('table').forEach(function (table) {
      if (table.closest('.table-responsive')) return;
      const wrapper = document.createElement('div');
      wrapper.className = 'table-responsive';
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    });

    if (window.PortalUI) {
      window.PortalUI.bindAutoLoading();
      window.PortalUI.bindConfirmActions();
      const flash = document.body.dataset.flashMessage;
      const flashType = document.body.dataset.flashType || 'info';
      if (flash) {
        window.PortalUI.showToast(flashType, flash);
      }
    }
  });
</script>
</body>
</html>
