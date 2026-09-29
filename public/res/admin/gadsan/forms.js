(() => {
  'use strict';
  const module = document.querySelector('.gadsan-module');
  if (!module) return;
  const path = location.pathname;
  module.querySelectorAll('.gadsan-tabs a').forEach(link => {
    const href = link.getAttribute('href');
    const active = href === '/admin/gadsan' ? path === href || path.includes('/colaboradores/') : path.startsWith(href) || href.endsWith('/importacoes') && path.includes('/origens/');
    if (active) link.setAttribute('aria-current', 'page');
  });
  module.querySelector('[data-form-error]')?.focus();
  let index = Date.now();
  module.addEventListener('click', async event => {
    const add = event.target.closest('[data-add]');
    if (add) {
      const target = module.querySelector(`[data-repeat="${add.dataset.add}"]`);
      if (target.children.length >= 50) return;
      const template = document.querySelector(`#gadsan-${add.dataset.add}`);
      const wrapper = document.createElement('div');
      wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(++index));
      const row = wrapper.firstElementChild;
      target.append(row);
      row.querySelector('select,input:not([type="hidden"])')?.focus();
    }
    const remove = event.target.closest('[data-remove]');
    if (remove) remove.closest('[data-row]').remove();
    const refresh = event.target.closest('[data-refresh-options]');
    if (refresh) {
      const status = module.querySelector('[data-options-status]');
      refresh.disabled = true; status.textContent = 'Atualizando…';
      try {
        const response = await fetch('/admin/gadsan/opcoes', {credentials:'same-origin',headers:{Accept:'application/json'}});
        if (!response.ok) throw new Error();
        const catalogs = await response.json();
        const updateSelects = root => root.querySelectorAll('[data-catalog]').forEach(select => {
          const current = select.value;
          select.replaceChildren(new Option('Não informado',''));
          catalogs[select.dataset.catalog].forEach(row => {
            if (!row.ativo && String(row.id) !== current) return;
            select.add(new Option(row.nome + (row.uf ? ` / ${row.uf}` : '') + (!row.ativo ? ' (inativo)' : ''), String(row.id), false, String(row.id) === current));
          });
        });
        updateSelects(module);
        document.querySelectorAll('template[id^="gadsan-"]').forEach(t => updateSelects(t.content));
        const formations = module.querySelector('[data-formations]');
        const checked = new Set([...formations.querySelectorAll('input:checked')].map(i=>i.value));
        formations.replaceChildren();
        catalogs.formacoes.forEach(row => {
          if (!row.ativo && !checked.has(String(row.id))) return;
          const label = document.createElement('label'); label.className='form-check';
          const input = document.createElement('input'); Object.assign(input,{type:'checkbox',name:'formacoes[]',value:String(row.id),checked:checked.has(String(row.id)),className:'form-check-input'});
          const span = document.createElement('span'); span.className='form-check-label';span.textContent=row.nome+(!row.ativo?' (inativa)':'');
          label.append(input,span);formations.append(label);
        });
        status.textContent = 'Opções atualizadas. Seus dados foram mantidos.';
      } catch { status.textContent = 'Não foi possível atualizar. Verifique sua sessão e tente novamente.'; }
      finally { refresh.disabled=false; }
    }
  });
  module.querySelectorAll('[data-gadsan-form]').forEach(form => form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"],.card-footer button');
    if (button) { button.disabled=true; button.textContent='Salvando…'; }
  }));
})();
