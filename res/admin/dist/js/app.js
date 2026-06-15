(function (window, document) {
  const ICONS = {
    success: 'bi bi-check-circle-fill',
    error: 'bi bi-x-octagon-fill',
    warning: 'bi bi-exclamation-triangle-fill',
    info: 'bi bi-info-circle-fill'
  };
  const TITLES = {
    success: 'Sucesso',
    error: 'Erro',
    warning: 'Aviso',
    info: 'Informação'
  };

  function getToastContainer() {
    let el = document.getElementById('portalToastContainer');
    if (!el) {
      el = document.createElement('div');
      el.id = 'portalToastContainer';
      el.className = 'portal-toast-container';
      document.body.appendChild(el);
    }
    return el;
  }

  function ensureButtonMarkup(button) {
    if (!button) return;
    if (!button.querySelector('.btn-text')) {
      const text = document.createElement('span');
      text.className = 'btn-text';
      text.innerHTML = button.innerHTML;
      button.innerHTML = '';
      button.appendChild(text);
    }
    if (!button.querySelector('.btn-spinner')) {
      const spinner = document.createElement('span');
      spinner.className = 'btn-spinner d-none';
      spinner.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
      button.appendChild(spinner);
    }
  }

  const PortalUI = {
    showToast: function (type, message, options) {
      type = (type || 'info').toLowerCase();
      if (!ICONS[type]) type = 'info';
      const settings = Object.assign({ duration: 3600, title: TITLES[type] }, options || {});
      const container = getToastContainer();
      const toast = document.createElement('div');
      toast.className = 'portal-toast portal-toast--' + type;
      toast.innerHTML = [
        '<div class="portal-toast__icon"><i class="' + ICONS[type] + '"></i></div>',
        '<div><div class="portal-toast__title">' + settings.title + '</div><div class="portal-toast__message">' + message + '</div></div>',
        '<button type="button" class="portal-toast__close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button>'
      ].join('');
      container.appendChild(toast);
      requestAnimationFrame(function () { toast.classList.add('show'); });
      const remove = function () {
        toast.classList.remove('show');
        setTimeout(function () { toast.remove(); }, 180);
      };
      toast.querySelector('.portal-toast__close').addEventListener('click', remove);
      if (settings.duration > 0) setTimeout(remove, settings.duration);
      return toast;
    },

    setLoading: function (button, state, text) {
      if (!button) return;
      ensureButtonMarkup(button);
      const spinner = button.querySelector('.btn-spinner');
      const label = button.querySelector('.btn-text');
      if (state) {
        button.classList.add('is-loading');
        button.disabled = true;
        button.dataset.originalText = label ? label.innerHTML : button.innerHTML;
        if (text && label) label.innerHTML = text;
        if (spinner) spinner.classList.remove('d-none');
      } else {
        button.classList.remove('is-loading');
        button.disabled = false;
        if (label && button.dataset.originalText) label.innerHTML = button.dataset.originalText;
        if (spinner) spinner.classList.add('d-none');
      }
    },

    showPageLoading: function (show) {
      const overlay = document.getElementById('portalLoadingOverlay');
      if (!overlay) return;
      overlay.classList.toggle('show', !!show);
      overlay.setAttribute('aria-hidden', show ? 'false' : 'true');
    },

    confirmAction: function (message, onConfirm, options) {
      const modalEl = document.getElementById('portalConfirmModal');
      const messageEl = document.getElementById('portalConfirmMessage');
      const okBtn = document.getElementById('portalConfirmOk');
      if (!modalEl || !window.bootstrap || !bootstrap.Modal) {
        if (window.confirm(message)) onConfirm && onConfirm();
        return;
      }
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      const settings = Object.assign({ okText: 'Confirmar' }, options || {});
      messageEl.textContent = message || 'Deseja continuar?';
      okBtn.textContent = settings.okText;
      const handler = function () {
        okBtn.removeEventListener('click', handler);
        modal.hide();
        onConfirm && onConfirm();
      };
      okBtn.addEventListener('click', handler, { once: true });
      modalEl.addEventListener('hidden.bs.modal', function cleanup() {
        okBtn.removeEventListener('click', handler);
        modalEl.removeEventListener('hidden.bs.modal', cleanup);
      });
      modal.show();
    },

    bindAutoLoading: function (scope) {
      (scope || document).querySelectorAll('form').forEach(function (form) {
        if (form.dataset.portalAutoLoadingBound === '1') return;
        form.dataset.portalAutoLoadingBound = '1';
        form.addEventListener('submit', function () {
          const btn = form.querySelector('button[type="submit"], input[type="submit"]');
          if (!btn) return;
          const loadingText = btn.dataset.loadingText || 'Processando...';
          PortalUI.setLoading(btn, true, loadingText);
        });
      });
    },

    bindConfirmActions: function (scope) {
      (scope || document).querySelectorAll('[data-confirm-message]').forEach(function (el) {
        if (el.dataset.portalConfirmBound === '1') return;
        el.dataset.portalConfirmBound = '1';
        el.addEventListener('click', function (event) {
          event.preventDefault();
          const href = el.getAttribute('href');
          const formSelector = el.getAttribute('data-confirm-form');
          PortalUI.confirmAction(el.getAttribute('data-confirm-message'), function () {
            if (formSelector) {
              const form = document.querySelector(formSelector);
              if (form) form.submit();
            } else if (href) {
              window.location.href = href;
            } else if (typeof el.click === 'function' && el.tagName !== 'A') {
              const clone = el.cloneNode(true);
              clone.removeAttribute('data-confirm-message');
              el.replaceWith(clone);
              clone.click();
            }
          }, { okText: el.getAttribute('data-confirm-ok') || 'Confirmar' });
        });
      });
    }
  };

  window.PortalUI = PortalUI;
})(window, document);
