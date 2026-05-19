<?php if(!class_exists('Rain\Tpl')){exit;}?><style>
  .consulta-page {
    padding: 18px 18px 30px;
  }

  .consulta-wrap {
    max-width: 1380px;
    margin: 0 auto;
  }

  .consulta-hero {
    background: linear-gradient(135deg, #ffffff 0%, #f5f9ff 100%);
    border: 1px solid #e7eef6;
    border-radius: 24px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, .06);
    padding: 26px;
    margin-bottom: 22px;
  }

  .consulta-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #e8f0ff;
    color: #2157c9;
    padding: 8px 14px;
    border-radius: 999px;
    font-weight: 700;
    font-size: .9rem;
    margin-bottom: 14px;
  }

  .consulta-title {
    margin: 0;
    font-size: clamp(1.9rem, 2.8vw, 2.8rem);
    font-weight: 800;
    color: #0f172a;
  }

  .consulta-subtitle {
    color: #64748b;
    margin: 12px 0 0;
    max-width: 900px;
  }

  .consulta-hero-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 18px;
  }

  .consulta-grid-top,
  .consulta-grid-main {
    display: grid;
    gap: 20px;
  }

  .consulta-grid-top {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    margin-bottom: 20px;
  }

  .consulta-grid-main {
    grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
  }

  .consulta-card {
    background: #fff;
    border: 1px solid #e7eef6;
    border-radius: 22px;
    box-shadow: 0 16px 38px rgba(15, 23, 42, .05);
    overflow: hidden;
  }

  .consulta-card-header {
    padding: 20px 22px;
    border-bottom: 1px solid #edf2f7;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .consulta-card-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
  }

  .consulta-card-subtitle {
    margin: 6px 0 0;
    color: #64748b;
    font-size: .95rem;
  }

  .consulta-card-body {
    padding: 22px;
  }

  .stat-card {
    padding: 18px;
    min-height: 126px;
    position: relative;
  }

  .stat-card:before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #2563eb, #60a5fa);
  }

  .stat-label {
    font-size: .9rem;
    color: #64748b;
    font-weight: 700;
    margin-bottom: 10px;
  }

  .stat-value {
    font-size: 2rem;
    color: #0f172a;
    font-weight: 800;
    line-height: 1;
    margin: 0 0 10px;
  }

  .stat-meta {
    color: #64748b;
    font-size: .88rem;
  }

  .filter-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
  }

  .consulta-card .form-label {
    font-weight: 700;
    color: #334155;
    margin-bottom: 7px;
  }

  .consulta-card .form-control,
  .consulta-card .form-select {
    min-height: 50px;
    border-radius: 16px;
    border: 1px solid #d7e0e8;
    box-shadow: none;
  }

  .consulta-card .form-control:focus,
  .consulta-card .form-select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 .22rem rgba(37, 99, 235, .12);
  }

  .bases-list {
    display: grid;
    gap: 12px;
    max-height: 320px;
    overflow: auto;
  }

  .base-item {
    border: 1px solid #e8edf4;
    border-radius: 18px;
    padding: 14px;
    background: #fbfdff;
  }

  .base-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
  }

  .base-title {
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    font-size: .98rem;
  }

  .base-sub {
    color: #64748b;
    font-size: .86rem;
    margin-top: 4px;
    word-break: break-word;
  }

  .badge-soft {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    padding: 6px 10px;
    font-size: .76rem;
    font-weight: 800;
  }

  .badge-soft.ok {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: #fff;
    box-shadow: 0 4px 10px rgba(34, 197, 94, 0.25);
  }

  .badge-soft.err {
    background: linear-gradient(135deg, #ef4444, #b91c1c);
    color: #fff;
    box-shadow: 0 4px 10px rgba(239, 68, 68, 0.25);
  }

  .badge-soft.off {
    background: #e5e7eb;
    color: #374151;
  }

  .badge-soft {
    font-size: 11px;
    padding: 6px 12px;
    border-radius: 999px;
    font-weight: 700;
    letter-spacing: .5px;
    text-transform: uppercase;
  }

  .table-wrap {
    overflow: auto;
  }

  .consulta-table {
    width: 100%;
    min-width: 1080px;
    border-collapse: separate;
    border-spacing: 0;
  }

  .consulta-table th,
  .consulta-table td {
    padding: 14px 12px;
    border-bottom: 1px solid #edf2f7;
    vertical-align: top;
  }

  .consulta-table thead th {
    position: sticky;
    top: 0;
    background: #f8fbff;
    color: #334155;
    font-size: .85rem;
    text-transform: uppercase;
    letter-spacing: .03em;
  }

  .name-cell {
    font-weight: 800;
    color: #0f172a;
  }

  .muted-line {
    color: #64748b;
    font-size: .85rem;
    margin-top: 4px;
  }

  .chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 5px 10px;
    font-size: .76rem;
    font-weight: 800;
  }

  .chip.titular {
    background: #e0ecff;
    color: #1d4ed8;
  }

  .chip.dependente {
    background: #f3e8ff;
    color: #7e22ce;
  }

  .result-toolbar {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 14px;
  }

  .result-info {
    color: #64748b;
    font-size: .92rem;
  }

  .pagination-wrap {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    flex-wrap: wrap;
    margin-top: 18px;
  }

  .pagination-wrap .btn {
    min-width: 42px;
    border-radius: 12px;
  }

  .sync-log {
    background: #0f172a;
    color: #e2e8f0;
    border-radius: 18px;
    padding: 16px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: .85rem;
    min-height: 120px;
    white-space: pre-wrap;
  }

  .empty-state {
    padding: 40px 24px;
    text-align: center;
    color: #64748b;
  }

  @media (max-width: 1100px) {
    .consulta-grid-top {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .consulta-grid-main {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 768px) {
    .consulta-page {
      padding: 14px 12px 24px;
    }

    .consulta-grid-top {
      grid-template-columns: 1fr;
    }

    .consulta-card-body,
    .consulta-card-header,
    .consulta-hero {
      padding: 18px;
    }
  }
</style>

<section class="consulta-page">
  <div class="consulta-wrap">
    <div class="consulta-hero">
      <div class="consulta-kicker"><i class="bi bi-diagram-3"></i> Multi-base de cadastros</div>
      <h1 class="consulta-title">Consulta unificada dos cadastrados</h1>
      <p class="consulta-subtitle">
        Esta área centraliza a consulta das bases externas cadastradas no portal. A sincronização preenche uma tabela
        local de leitura rápida,
        preservando a origem de cada registro para você filtrar por unidade, banco e tipo de cadastro.
      </p>
      <div class="consulta-hero-actions">
        <button class="btn btn-primary" id="btnSincronizarTudo"><i class="bi bi-arrow-repeat"></i> Sincronizar bases
          ativas</button>
        <button class="btn btn-outline-secondary" id="btnAtualizarPainel"><i class="bi bi-arrow-clockwise"></i>
          Atualizar painel</button>
      </div>
    </div>

    <div class="consulta-grid-top">
      <div class="consulta-card stat-card">
        <div class="stat-label">Registros unificados</div>
        <p class="stat-value" id="statTotalRegistros"><?php echo htmlspecialchars( $resumo["total_registros"], ENT_COMPAT, 'UTF-8', FALSE ); ?></p>
        <div class="stat-meta">Total de linhas disponíveis na consulta central.</div>
      </div>
      <div class="consulta-card stat-card">
        <div class="stat-label">Bases com dados</div>
        <p class="stat-value" id="statTotalBases"><?php echo htmlspecialchars( $resumo["total_bases"], ENT_COMPAT, 'UTF-8', FALSE ); ?></p>
        <div class="stat-meta">Número de bancos já refletidos na centralização.</div>
      </div>
      <div class="consulta-card stat-card">
        <div class="stat-label">Titulares</div>
        <p class="stat-value" id="statTotalTitulares"><?php echo htmlspecialchars( $resumo["total_titulares"], ENT_COMPAT, 'UTF-8', FALSE ); ?></p>
        <div class="stat-meta">Registros importados da tabela <code>tb_titular</code>.</div>
      </div>
      <div class="consulta-card stat-card">
        <div class="stat-label">Dependentes</div>
        <p class="stat-value" id="statTotalDependentes"><?php echo htmlspecialchars( $resumo["total_dependentes"], ENT_COMPAT, 'UTF-8', FALSE ); ?></p>
        <div class="stat-meta">Registros importados da tabela <code>tb_dependentes</code>.</div>
      </div>
    </div>

    <div class="consulta-grid-main">
      <div class="consulta-card">
        <div class="consulta-card-header">
          <div>
            <h3 class="consulta-card-title">Filtros e bases</h3>
            <p class="consulta-card-subtitle">Pesquise por nome, CPF ou RG e, se quiser, restrinja por tipo e unidade.
            </p>
          </div>
        </div>
        <div class="consulta-card-body">
          <form id="formConsulta" class="filter-grid">
            <div>
              <label class="form-label">Buscar por nome, CPF ou RG</label>
              <input type="text" class="form-control" name="busca" id="filtroBusca"
                placeholder="Ex.: Maria, 12345678900, RG...">
            </div>
            <div>
              <label class="form-label">Tipo de cadastro</label>
              <select class="form-select" name="tipo" id="filtroTipo">
                <option value="">Todos</option>
                <option value="TITULAR">Titular</option>
                <option value="DEPENDENTE">Dependente</option>
              </select>
            </div>
            <div>
              <label class="form-label">Banco de origem</label>
              <select class="form-select" name="origem_banco" id="filtroOrigemBanco">
                <option value="">Todos</option>
                <?php $counter1=-1;  if( isset($bases) && ( is_array($bases) || $bases instanceof Traversable ) && sizeof($bases) ) foreach( $bases as $key1 => $value1 ){ $counter1++; ?>

                <option value="<?php echo htmlspecialchars( $value1["nome_banco"], ENT_COMPAT, 'UTF-8', FALSE ); ?>"><?php echo htmlspecialchars( $value1["nome_banco"], ENT_COMPAT, 'UTF-8', FALSE ); ?></option>
                <?php } ?>

              </select>
            </div>
            <div>
              <label class="form-label">Unidade de origem</label>
              <select class="form-select" name="origem_unidade" id="filtroOrigemUnidade">
                <option value="">Todas</option>
                <?php $counter1=-1;  if( isset($bases) && ( is_array($bases) || $bases instanceof Traversable ) && sizeof($bases) ) foreach( $bases as $key1 => $value1 ){ $counter1++; ?>

                <option value="<?php echo htmlspecialchars( $value1["nome_unidade"], ENT_COMPAT, 'UTF-8', FALSE ); ?>"><?php echo htmlspecialchars( $value1["nome_unidade"], ENT_COMPAT, 'UTF-8', FALSE ); ?></option>
                <?php } ?>

              </select>
            </div>
            <div>
              <label class="form-label">Status do cadastro</label>
              <select class="form-select" name="somente_ativos" id="filtroSomenteAtivos">
                <option value="0">Todos</option>
                <option value="1">Somente ativos</option>
              </select>
            </div>
            <div class="d-grid gap-2">
              <label class="form-label d-none d-md-block">Ações</label>
              <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Consultar agora</button>
              <button class="btn btn-light border" type="button" id="btnLimparFiltros"><i class="bi bi-eraser"></i>
                Limpar filtros</button>
            </div>
          </form>

          <hr class="my-4">

          <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
            <div>
              <h4 class="consulta-card-title mb-1" style="font-size:1rem;">Bases cadastradas para integração</h4>
              <div class="consulta-card-subtitle">Selecione quais bases quer sincronizar manualmente.</div>
            </div>
          </div>

          <div class="bases-list" id="basesList">
            <?php $counter1=-1;  if( isset($bases) && ( is_array($bases) || $bases instanceof Traversable ) && sizeof($bases) ) foreach( $bases as $key1 => $value1 ){ $counter1++; ?>

            <label class="base-item">
              <div class="base-head">
                <div>
                  <div class="base-title">
                    <input class="form-check-input me-2" type="checkbox" name="base_ids[]" value="<?php echo htmlspecialchars( $value1["id"], ENT_COMPAT, 'UTF-8', FALSE ); ?>">
                    <?php echo htmlspecialchars( $value1["nome_unidade"], ENT_COMPAT, 'UTF-8', FALSE ); ?>

                  </div>
                  <div class="base-sub">Banco: <?php echo htmlspecialchars( $value1["nome_banco"], ENT_COMPAT, 'UTF-8', FALSE ); ?> • Host: <?php echo htmlspecialchars( $value1["host"], ENT_COMPAT, 'UTF-8', FALSE ); ?>:<?php echo htmlspecialchars( $value1["porta"], ENT_COMPAT, 'UTF-8', FALSE ); ?></div>
                  <div class="base-sub">Última sync: <?php echo htmlspecialchars( $value1["ultima_sincronizacao"], ENT_COMPAT, 'UTF-8', FALSE ); ?></div>
                </div>
                <?php if( $value1["ativo"] == 1 ){ ?>

                <span class="badge-soft <?php if(  $value1["ultimo_status"]=='ERRO' ){ ?>err<?php }else{ ?>ok<?php } ?>">
                  <?php echo htmlspecialchars( $value1["ultimo_status"], ENT_COMPAT, 'UTF-8', FALSE ); ?>

                </span>
                <?php }else{ ?>

                <span class="badge-soft off">INATIVA</span>
                <?php } ?>

              </div>
            </label>
            <?php } ?>

          </div>

          <div class="d-grid gap-2 mt-3">
            <button class="btn btn-outline-primary" type="button" id="btnSincronizarSelecionadas"><i
                class="bi bi-database-up"></i> Sincronizar bases selecionadas</button>
          </div>

          <div class="mt-4">
            <h4 class="consulta-card-title mb-2" style="font-size:1rem;">Retorno da sincronização</h4>
            <div class="sync-log" id="syncLog">Aguardando ação...</div>
          </div>
        </div>
      </div>

      <div class="consulta-card">
        <div class="consulta-card-header">
          <div>
            <h3 class="consulta-card-title">Resultados</h3>
            <p class="consulta-card-subtitle">Consulta central na tabela <code>tb_cadastros_unificados</code>.</p>
          </div>
        </div>
        <div class="consulta-card-body">
          <div class="result-toolbar">
            <div class="result-info" id="resultadoInfo">Use os filtros e clique em consultar.</div>
            <div>
              <select class="form-select" id="pageSizeSelect" style="min-width:120px;">
                <option value="10">10 / pág</option>
                <option value="25" selected>25 / pág</option>
                <option value="50">50 / pág</option>
                <option value="100">100 / pág</option>
              </select>
            </div>
          </div>

          <div class="table-wrap">
            <table class="consulta-table">
              <thead>
                <tr>
                  <th>Cadastro</th>
                  <th>Documento</th>
                  <th>Perfil</th>
                  <th>Origem</th>
                  <th>Status / vínculo</th>
                  <th>Datas</th>
                </tr>
              </thead>
              <tbody id="resultadoTabelaBody">
                <tr>
                  <td colspan="6">
                    <div class="empty-state">Nenhuma consulta executada ainda.</div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="pagination-wrap" id="paginationWrap"></div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
  (function () {
    const state = { page: 1, pageSize: 25, total: 0, pages: 1 };

    const els = {
      form: document.getElementById('formConsulta'),
      busca: document.getElementById('filtroBusca'),
      tipo: document.getElementById('filtroTipo'),
      origemBanco: document.getElementById('filtroOrigemBanco'),
      origemUnidade: document.getElementById('filtroOrigemUnidade'),
      somenteAtivos: document.getElementById('filtroSomenteAtivos'),
      pageSize: document.getElementById('pageSizeSelect'),
      resultadoBody: document.getElementById('resultadoTabelaBody'),
      resultadoInfo: document.getElementById('resultadoInfo'),
      pagination: document.getElementById('paginationWrap'),
      syncLog: document.getElementById('syncLog'),
      btnLimpar: document.getElementById('btnLimparFiltros'),
      btnSyncAll: document.getElementById('btnSincronizarTudo'),
      btnSyncSelected: document.getElementById('btnSincronizarSelecionadas'),
      btnRefresh: document.getElementById('btnAtualizarPainel'),
      statTotalRegistros: document.getElementById('statTotalRegistros'),
      statTotalBases: document.getElementById('statTotalBases'),
      statTotalTitulares: document.getElementById('statTotalTitulares'),
      statTotalDependentes: document.getElementById('statTotalDependentes')
    };

    function escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function formatDateBr(value) {
      if (!value) return '-';
      const date = new Date(String(value).replace(' ', 'T'));
      if (Number.isNaN(date.getTime())) return escapeHtml(value);
      return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: String(value).length > 10 ? 'short' : undefined }).format(date);
    }

    function currentFilters() {
      return {
        page: state.page,
        pageSize: state.pageSize,
        busca: els.busca.value.trim(),
        tipo: els.tipo.value,
        origem_banco: els.origemBanco.value,
        origem_unidade: els.origemUnidade.value,
        somente_ativos: els.somenteAtivos.value
      };
    }

    async function fetchJson(url, options) {
      const response = await fetch(url, options || {});
      const text = await response.text();
      let payload = {};
      try { payload = text ? JSON.parse(text) : {}; } catch (e) {
        throw new Error(text || 'Resposta inválida do servidor.');
      }
      if (!response.ok || payload.success === false) {
        const detalhes = [
          payload.message || 'Falha na operação.',
          payload.error ? 'Erro: ' + payload.error : '',
          payload.line ? 'Linha: ' + payload.line : '',
          payload.file ? 'Arquivo: ' + payload.file : ''
        ].filter(Boolean).join('\n');
        throw new Error(detalhes);
      }
      return payload;
    }

    function renderRows(items) {
      if (!Array.isArray(items) || items.length === 0) {
        els.resultadoBody.innerHTML = '<tr><td colspan="6"><div class="empty-state">Nenhum registro encontrado para os filtros informados.</div></td></tr>';
        return;
      }
      els.resultadoBody.innerHTML = items.map((item) => {
        const tipoClass = item.tipo_cadastro === 'DEPENDENTE' ? 'dependente' : 'titular';
        const statusVinculo = item.tipo_cadastro === 'DEPENDENTE' ? (item.parentesco || '-') : (item.status_cliente || '-');
        return `
          <tr>
            <td>
              <div class="name-cell">${escapeHtml(item.nome || '-')}</div>
              <div class="muted-line">ID origem: ${escapeHtml(item.origem_id || '-')}</div>
            </td>
            <td>
              <div><strong>CPF:</strong> ${escapeHtml(item.cpf || '-')}</div>
              <div class="muted-line"><strong>RG:</strong> ${escapeHtml(item.rg || '-')}</div>
            </td>
            <td>
              <span class="chip ${tipoClass}">${escapeHtml(item.tipo_cadastro || '-')}</span>
              <div class="muted-line">${escapeHtml(item.genero || '-')} • Idade: ${escapeHtml(item.idade ?? '-')}</div>
              <div class="muted-line">Nascimento: ${formatDateBr(item.data_nascimento)}</div>
            </td>
            <td>
              <div><strong>Unidade:</strong> ${escapeHtml(item.origem_unidade || '-')}</div>
              <div class="muted-line">Banco: ${escapeHtml(item.origem_banco || '-')}</div>
              <div class="muted-line">Tabela: ${escapeHtml(item.origem_tabela || '-')}</div>
            </td>
            <td>${escapeHtml(statusVinculo)}</td>
            <td>
              <div><strong>Cadastro:</strong> ${formatDateBr(item.registration_date)}</div>
              <div class="muted-line"><strong>Atualização:</strong> ${formatDateBr(item.registration_date_update)}</div>
              <div class="muted-line"><strong>Sync:</strong> ${formatDateBr(item.data_sync)}</div>
            </td>
          </tr>
        `;
      }).join('');
    }

    function renderPagination() {
      const pages = state.pages || 1;
      const page = state.page || 1;
      if (pages <= 1) {
        els.pagination.innerHTML = '';
        return;
      }
      let html = '';
      const start = Math.max(1, page - 2);
      const end = Math.min(pages, page + 2);
      html += `<button type="button" class="btn btn-light border" ${page <= 1 ? 'disabled' : ''} data-page="${page - 1}">‹</button>`;
      for (let i = start; i <= end; i++) {
        html += `<button type="button" class="btn ${i === page ? 'btn-primary' : 'btn-light border'}" data-page="${i}">${i}</button>`;
      }
      html += `<button type="button" class="btn btn-light border" ${page >= pages ? 'disabled' : ''} data-page="${page + 1}">›</button>`;
      els.pagination.innerHTML = html;
    }

    async function carregarResultados() {
      els.resultadoInfo.textContent = 'Consultando registros centralizados...';
      els.resultadoBody.innerHTML = '<tr><td colspan="6"><div class="empty-state">Carregando resultados...</div></td></tr>';
      const qs = new URLSearchParams(currentFilters()).toString();
      const payload = await fetchJson('/admin/api/consulta-unificada/lista?' + qs);
      const data = payload.data || {};
      state.total = data.total || 0;
      state.pages = data.pages || 1;
      renderRows(data.items || []);
      renderPagination();
      els.resultadoInfo.textContent = `${state.total} registro(s) encontrado(s) • página ${state.page} de ${state.pages}`;
    }

    async function carregarPainel() {
      const payload = await fetchJson('/admin/api/consulta-unificada/resumo');
      const resumo = (payload.data || {}).resumo || {};
      els.statTotalRegistros.textContent = resumo.total_registros || 0;
      els.statTotalBases.textContent = resumo.total_bases || 0;
      els.statTotalTitulares.textContent = resumo.total_titulares || 0;
      els.statTotalDependentes.textContent = resumo.total_dependentes || 0;
    }

    function baseIdsSelecionadas() {
      return Array.from(document.querySelectorAll('input[name="base_ids[]"]:checked')).map((el) => el.value);
    }

    async function sincronizar(baseIds) {
      const body = new URLSearchParams();
      (baseIds || []).forEach((id) => body.append('base_ids[]', id));
      els.syncLog.textContent = 'Sincronizando bases, aguarde...';
      const payload = await fetchJson('/admin/api/consulta-unificada/sincronizar', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: body.toString()
      });
      const data = payload.data || {};
      const detalhes = Array.isArray(data.detalhes) ? data.detalhes : [];
      const linhas = [
        `Bases processadas: ${data.bases_processadas || 0}`,
        `Sucesso: ${data.bases_sucesso || 0}`,
        `Erros: ${data.bases_erro || 0}`,
        `Registros upsert: ${data.registros_upsert || 0}`,
        '---'
      ];
      detalhes.forEach((item) => {
        linhas.push(`[${item.status}] ${item.nome_unidade} / ${item.nome_banco} => ${item.registros || 0} registro(s)`);
        if (item.mensagem) linhas.push(`  ${item.mensagem}`);
      });
      els.syncLog.textContent = linhas.join('\n');
      await carregarPainel();
      await carregarResultados();
    }

    els.form.addEventListener('submit', async function (e) {
      e.preventDefault();
      state.page = 1;
      try { await carregarResultados(); } catch (error) {
        els.resultadoBody.innerHTML = `<tr><td colspan="6"><div class="empty-state">${escapeHtml(error.message)}</div></td></tr>`;
        els.resultadoInfo.textContent = 'Falha na consulta.';
      }
    });
    els.pageSize.addEventListener('change', async function () {
      state.pageSize = parseInt(this.value, 10) || 25;
      state.page = 1;
      try { await carregarResultados(); } catch (error) { }
    });
    els.pagination.addEventListener('click', async function (e) {
      const btn = e.target.closest('button[data-page]');
      if (!btn || btn.disabled) return;
      state.page = parseInt(btn.getAttribute('data-page'), 10) || 1;
      try { await carregarResultados(); } catch (error) { }
    });
    els.btnLimpar.addEventListener('click', function () {
      els.form.reset();
      state.page = 1;
      state.pageSize = parseInt(els.pageSize.value, 10) || 25;
    });
    els.btnSyncAll.addEventListener('click', async function () {
      try { await sincronizar([]); } catch (error) { els.syncLog.textContent = error.message; }
    });
    els.btnSyncSelected.addEventListener('click', async function () {
      const ids = baseIdsSelecionadas();
      if (ids.length === 0) {
        els.syncLog.textContent = 'Selecione ao menos uma base antes de sincronizar.';
        return;
      }
      try { await sincronizar(ids); } catch (error) { els.syncLog.textContent = error.message; }
    });
    els.btnRefresh.addEventListener('click', async function () {
      try { await carregarPainel(); } catch (error) { els.syncLog.textContent = error.message; }
    });
    carregarResultados().catch(() => { });
  })();
</script>