<?php if(!class_exists('Rain\Tpl')){exit;}?><style>
    :root {
        --bg: #f4f7fb;
        --surface: #ffffff;
        --surface-2: #f8fafc;
        --text: #17212f;
        --muted: #6b7280;
        --line: #e5e7eb;
        --primary: #1f3b57;
        --primary-2: #284d73;
        --success: #198754;
        --danger: #dc3545;
        --warning: #f59e0b;
        --info: #0ea5e9;
        --shadow: 0 14px 40px rgba(15, 23, 42, .08);
        --radius: 20px;
        --radius-sm: 14px;
    }

    * {
        box-sizing: border-box
    }

    body {
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        background: linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
        color: var(--text)
    }

    .dash-page {
        min-height: 100vh;
        padding: 22px
    }

    .dash-shell {
        max-width: 1500px;
        margin: 0 auto
    }

    .hero {
        position: relative;
        overflow: hidden;
        margin-bottom: 18px;
        border-radius: 28px;
        padding: 28px 30px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-2) 100%);
        color: #fff;
        box-shadow: var(--shadow)
    }

    .hero:before,
    .hero:after {
        content: "";
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, .08)
    }

    .hero:before {
        width: 260px;
        height: 260px;
        right: -80px;
        top: -90px
    }

    .hero:after {
        width: 180px;
        height: 180px;
        right: 160px;
        bottom: -70px
    }

    .hero-head {
        position: relative;
        z-index: 1;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap
    }

    .hero-title {
        margin: 0;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: .2px
    }

    .hero-sub {
        margin: 8px 0 0;
        color: rgba(255, 255, 255, .82);
        font-size: 14px
    }

    .hero-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap
    }

    .btn {
        border: 0;
        border-radius: 14px;
        padding: 12px 16px;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        transition: .18s ease;
        box-shadow: 0 8px 18px rgba(0, 0, 0, .08)
    }

    .btn:hover {
        transform: translateY(-1px)
    }

    .btn:disabled {
        opacity: .55;
        cursor: not-allowed;
        transform: none
    }

    .btn-primary {
        background: #fff;
        color: var(--primary)
    }

    .btn-ghost {
        background: rgba(255, 255, 255, .12);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .18)
    }

    .btn-soft {
        background: var(--surface);
        color: var(--primary);
        border: 1px solid var(--line);
        box-shadow: none
    }

    .cards {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 18px
    }

    .card {
        background: var(--surface);
        border: 1px solid rgba(15, 23, 42, .05);
        border-radius: var(--radius);
        box-shadow: var(--shadow)
    }

    .stat {
        padding: 18px;
        position: relative;
        overflow: hidden
    }

    .stat:after {
        content: "";
        position: absolute;
        right: -20px;
        bottom: -20px;
        width: 92px;
        height: 92px;
        border-radius: 50%;
        background: rgba(31, 59, 87, .06)
    }

    .stat-label {
        font-size: 12px;
        color: var(--muted);
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .6px;
        margin-bottom: 8px
    }

    .stat-value {
        font-size: 32px;
        line-height: 1;
        font-weight: 900;
        margin-bottom: 6px
    }

    .stat-foot {
        font-size: 12px;
        color: var(--muted)
    }

    .layout {
        display: grid;
        grid-template-columns: 1.5fr 1fr;
        gap: 16px;
        margin-bottom: 18px
    }

    .panel {
        padding: 16px
    }

    .panel-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 14px
    }

    .panel-title {
        margin: 0;
        font-size: 19px;
        font-weight: 800
    }

    .panel-sub {
        margin: 3px 0 0;
        color: var(--muted);
        font-size: 13px
    }

    .grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px
    }

    .chart-box {
        padding: 8px 8px 0
    }

    .chart-wrap {
        height: 290px;
        background: linear-gradient(180deg, #fbfdff 0%, #f7fafc 100%);
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 12px;
        position: relative
    }

    .chart-wrap canvas {
        width: 100% !important;
        height: 100% !important
    }

    .ranking-list,
    .alerts-list {
        display: flex;
        flex-direction: column;
        gap: 10px
    }

    .rank-item,
    .alert-item {
        border: 1px solid var(--line);
        background: var(--surface-2);
        border-radius: 14px;
        padding: 12px 14px;
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: center
    }

    .rank-left {
        min-width: 0
    }

    .rank-name {
        font-weight: 800;
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis
    }

    .rank-meta,
    .alert-text {
        font-size: 12px;
        color: var(--muted)
    }

    .pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap
    }

    .pill-success {
        background: rgba(25, 135, 84, .12);
        color: #157347
    }

    .pill-danger {
        background: rgba(220, 53, 69, .12);
        color: #b02a37
    }

    .pill-warning {
        background: rgba(245, 158, 11, .14);
        color: #a16207
    }

    .pill-info {
        background: rgba(14, 165, 233, .12);
        color: #0369a1
    }

    .filters.card {
        margin-bottom: 18px
    }

    .filter-grid {
        display: grid;
        grid-template-columns: 1.2fr .9fr .9fr .9fr auto auto auto;
        gap: 12px;
        align-items: end
    }

    .field label {
        display: block;
        font-size: 12px;
        font-weight: 800;
        color: var(--muted);
        margin-bottom: 7px
    }

    .input,
    .select {
        width: 100%;
        height: 46px;
        border: 1px solid var(--line);
        border-radius: 14px;
        background: #fff;
        padding: 0 14px;
        outline: none;
        transition: .18s ease;
        color: var(--text)
    }

    .input:focus,
    .select:focus {
        border-color: rgba(31, 59, 87, .38);
        box-shadow: 0 0 0 4px rgba(31, 59, 87, .08)
    }

    .search-wrap {
        position: relative
    }

    .search-icon {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 14px
    }

    .search-wrap .input {
        padding-left: 38px
    }

    .table-wrap {
        overflow: auto;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: #fff
    }

    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        min-width: 1250px
    }

    .table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8fafc;
        border-bottom: 1px solid var(--line);
        color: #475569;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .5px;
        text-align: left;
        padding: 14px;
        white-space: nowrap
    }

    .table tbody td {
        padding: 14px;
        border-bottom: 1px solid #edf2f7;
        font-size: 13px;
        vertical-align: middle
    }

    .table tbody tr:hover {
        background: #fbfdff
    }

    .table tbody tr.row-error {
        background: #fff6f6
    }

    .file-stack {
        display: flex;
        flex-direction: column;
        gap: 4px
    }

    .file-name {
        font-weight: 800;
        word-break: break-word
    }

    .file-meta {
        color: var(--muted);
        font-size: 12px
    }

    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px
    }

    .action-btn {
        appearance: none;
        border: 1px solid var(--line);
        background: #fff;
        color: var(--text);
        text-decoration: none;
        cursor: pointer;
        border-radius: 12px;
        padding: 9px 12px;
        font-size: 12px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        gap: 6px
    }

    .action-btn:hover {
        border-color: rgba(31, 59, 87, .22);
        color: var(--primary);
        text-decoration: none
    }

    .action-btn.loading {
        opacity: .7;
        pointer-events: none
    }

    .footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 14px
    }

    .summary-line {
        font-size: 13px;
        color: var(--muted)
    }

    .pagination {
        display: flex;
        gap: 8px;
        flex-wrap: wrap
    }

    .page-btn {
        min-width: 40px;
        height: 40px;
        padding: 0 12px;
        border-radius: 12px;
        border: 1px solid var(--line);
        background: #fff;
        color: var(--text);
        font-size: 13px;
        font-weight: 800;
        cursor: pointer
    }

    .page-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff
    }

    .page-btn:disabled {
        opacity: .45;
        cursor: not-allowed
    }

    .empty {
        padding: 44px 18px;
        text-align: center;
        color: var(--muted)
    }

    .empty-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: #f1f5f9;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin-bottom: 12px
    }

    .toast-wrap {
        position: fixed;
        top: 18px;
        right: 18px;
        z-index: 4000;
        display: flex;
        flex-direction: column;
        gap: 10px
    }

    .toast {
        min-width: 280px;
        max-width: 420px;
        background: #fff;
        border: 1px solid var(--line);
        border-left: 4px solid var(--info);
        border-radius: 16px;
        box-shadow: 0 18px 36px rgba(15, 23, 42, .12);
        padding: 12px 14px;
        font-size: 13px;
        color: var(--text)
    }

    .toast.success {
        border-left-color: var(--success)
    }

    .toast.error {
        border-left-color: var(--danger)
    }

    .toast.warning {
        border-left-color: var(--warning)
    }

    .modal {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .52);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 3000;
        padding: 18px
    }

    .modal.show {
        display: flex
    }

    .modal-card {
        width: min(820px, 100%);
        max-height: 90vh;
        overflow: auto;
        background: #fff;
        border-radius: 22px;
        box-shadow: 0 24px 60px rgba(15, 23, 42, .25)
    }

    .modal-head {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #fff;
        padding: 18px 20px;
        border-bottom: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px
    }

    .modal-title {
        margin: 0;
        font-size: 18px;
        font-weight: 800
    }

    .modal-body {
        padding: 18px 20px 20px
    }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 14px
    }

    .detail-item {
        border: 1px solid var(--line);
        background: var(--surface-2);
        border-radius: 14px;
        padding: 12px 14px
    }

    .detail-label {
        display: block;
        font-size: 11px;
        color: var(--muted);
        text-transform: uppercase;
        font-weight: 800;
        margin-bottom: 6px
    }

    .detail-value {
        font-size: 14px;
        font-weight: 700;
        word-break: break-word
    }

    .error-box {
        border: 1px solid rgba(220, 53, 69, .18);
        background: rgba(220, 53, 69, .06);
        border-radius: 14px;
        padding: 14px;
        color: #7f1d1d;
        font-size: 13px;
        white-space: pre-wrap
    }

    @media (max-width:1280px) {
        .cards {
            grid-template-columns: repeat(3, minmax(0, 1fr))
        }

        .layout {
            grid-template-columns: 1fr
        }

        .filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }
    }

    @media (max-width:768px) {
        .dash-page {
            padding: 14px
        }

        .hero {
            padding: 20px;
            border-radius: 22px
        }

        .hero-title {
            font-size: 24px
        }

        .cards {
            grid-template-columns: 1fr
        }

        .filter-grid,
        .grid-2,
        .detail-grid {
            grid-template-columns: 1fr
        }
    }
</style>

<div class="dash-page">
    <div class="dash-shell">
        <div class="hero">
            <div class="hero-head">
                <div>
                    <h1 class="hero-title">Central de Relatórios</h1>
                    <p class="hero-sub">Consulta completa dos relatórios em PDF com indicadores, gráficos interativos,
                        auditoria e ações rápidas em uma única tela.</p>
                </div>
                <div class="hero-actions">
                    <button class="btn btn-ghost" type="button" onclick="PDFDASH.limparFiltros()">Limpar
                        filtros</button>
                    <button class="btn btn-ghost" type="button" onclick="PDFDASH.reenviarFalhas()">Reenviar
                        pendências</button>
                    <button class="btn btn-primary" type="button" onclick="PDFDASH.carregar(1)">Atualizar</button>
                </div>
            </div>
        </div>

        <div class="cards">
            <div class="card stat">
                <div class="stat-label">Total listado</div>
                <div class="stat-value" id="statTotal">0</div>
                <div class="stat-foot">Quantidade retornada pela busca atual</div>
            </div>
            <div class="card stat">
                <div class="stat-label">Uploads com sucesso</div>
                <div class="stat-value" id="statSucesso">0</div>
                <div class="stat-foot">Relatórios enviados com sucesso</div>
            </div>
            <div class="card stat">
                <div class="stat-label">Falhas</div>
                <div class="stat-value" id="statErro">0</div>
                <div class="stat-foot">Registros com erro de envio</div>
            </div>
            <div class="card stat">
                <div class="stat-label">Sem link público</div>
                <div class="stat-value" id="statRemoto">0</div>
                <div class="stat-foot">Relatórios sem URL pública ou com falha de publicação</div>
            </div>
            <div class="card stat">
                <div class="stat-label">Taxa de publicação</div>
                <div class="stat-value" id="statPercent">0%</div>
                <div class="stat-foot">Percentual de relatórios publicados na busca atual</div>
            </div>
        </div>

        <div class="layout">
            <div class="card panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">Visão analítica dos relatórios</h2>
                        <p class="panel-sub">Acompanhe o volume diário e a distribuição dos status dos relatórios.</p>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="chart-box">
                        <div class="chart-wrap">
                            <canvas id="chartPorDia"></canvas>
                        </div>
                    </div>
                    <div class="chart-box">
                        <div class="chart-wrap">
                            <canvas id="chartStatus"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">Insights automáticos</h2>
                        <p class="panel-sub">Leitura rápida do comportamento da base de relatórios.</p>
                    </div>
                </div>

                <div style="margin-bottom:14px">
                    <span id="insightPill" class="pill pill-info">● Aguardando dados</span>
                </div>

                <div class="alerts-list" id="alertsList">
                    <div class="alert-item">
                        <div class="alert-text">Carregando alertas do dashboard...</div>
                    </div>
                </div>

                <div style="height:16px"></div>

                <div class="panel-head" style="margin-bottom:10px">
                    <div>
                        <h2 class="panel-title" style="font-size:17px">Ranking de responsáveis</h2>
                        <p class="panel-sub">Responsáveis com mais relatórios na busca atual.</p>
                    </div>
                </div>

                <div class="ranking-list" id="rankingList">
                    <div class="rank-item">
                        <div class="rank-left">
                            <div class="rank-name">Carregando ranking...</div>
                            <div class="rank-meta">Aguarde</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card filters">
            <div class="panel" style="padding-bottom:18px">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">Filtros e ações</h2>
                        <p class="panel-sub">Pesquise, filtre, exporte CSV e acompanhe o histórico documental com
                            rapidez.</p>
                    </div>
                </div>

                <div class="filter-grid">
                    <div class="field">
                        <label for="busca">Busca rápida</label>
                        <div class="search-wrap">
                            <span class="search-icon">🔎</span>
                            <input id="busca" class="input" type="text"
                                placeholder="Arquivo, responsável, CPF, erro...">
                        </div>
                    </div>

                    <div class="field">
                        <label for="data">Data do relatório</label>
                        <input id="data" class="input" type="date">
                    </div>

                    <div class="field">
                        <label for="status">Status do upload</label>
                        <select id="status" class="select">
                            <option value="">Todos</option>
                            <option value="SUCESSO">SUCESSO</option>
                            <option value="ERRO">ERRO</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="pageSize">Itens por página</label>
                        <select id="pageSize" class="select">
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>

                    <button class="btn btn-soft" type="button" onclick="PDFDASH.carregar(1)">Filtrar</button>
                    <button class="btn btn-soft" type="button" onclick="PDFDASH.exportarCSV()">Exportar CSV</button>
                    <button class="btn btn-soft" type="button" onclick="window.print()">Imprimir tela</button>
                </div>
            </div>
        </div>

        <div class="card panel">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">Histórico detalhado dos relatórios</h2>
                    <p class="panel-sub">Tabela completa para consulta, auditoria, detalhes e reenvio para nuvem.</p>
                </div>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:90px">ID</th>
                            <th>Arquivo</th>
                            <th style="width:130px">Data relatório</th>
                            <th style="width:130px">Upload</th>
                            <th style="width:150px">Nuvem</th>
                            <th style="width:180px">Responsável</th>
                            <th style="width:190px">Gerado em</th>
                            <th style="width:235px">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaBody">
                        <tr>
                            <td colspan="8">
                                <div class="empty">
                                    <div class="empty-icon">📄</div>
                                    <div>Carregando histórico...</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="footer">
                <div class="summary-line" id="resumo">Nenhum dado carregado.</div>
                <div class="pagination" id="paginacao"></div>
            </div>
        </div>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<div class="modal" id="modal">
    <div class="modal-card">
        <div class="modal-head">
            <h3 class="modal-title">Detalhes do histórico</h3>
            <button class="btn btn-soft" type="button" onclick="PDFDASH.fecharModal()">Fechar</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    (function () {
        const state = {
            page: 1,
            pages: 1,
            total: 0,
            pageSize: 20,
            items: [],
            filteredItems: [],
            reenviando: {},
            autoRefreshMs: 30000
        };

        const els = {
            busca: document.getElementById('busca'),
            data: document.getElementById('data'),
            status: document.getElementById('status'),
            pageSize: document.getElementById('pageSize'),
            tabelaBody: document.getElementById('tabelaBody'),
            paginacao: document.getElementById('paginacao'),
            resumo: document.getElementById('resumo'),
            statTotal: document.getElementById('statTotal'),
            statSucesso: document.getElementById('statSucesso'),
            statErro: document.getElementById('statErro'),
            statRemoto: document.getElementById('statRemoto'),
            statPercent: document.getElementById('statPercent'),
            rankingList: document.getElementById('rankingList'),
            alertsList: document.getElementById('alertsList'),
            insightPill: document.getElementById('insightPill'),
            modal: document.getElementById('modal'),
            modalBody: document.getElementById('modalBody'),
            toastWrap: document.getElementById('toastWrap'),
            chartPorDia: document.getElementById('chartPorDia'),
            chartStatus: document.getElementById('chartStatus')
        };

        function escapeHtml(str) {
            return String(str == null ? '' : str)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function normalizeStatus(status) {
            return String(status || '').toUpperCase();
        }

        function hasRemote(item) {
            return !!(item.url_publica && String(item.url_publica).trim() !== '');
        }

        function formatDate(value) {
            if (!value) return '-';
            const text = String(value).trim();
            if (/^\d{4}-\d{2}-\d{2}$/.test(text)) {
                const [y, m, d] = text.split('-');
                return `${d}/${m}/${y}`;
            }
            const normalized = text.replace(' ', 'T');
            const dt = new Date(normalized);
            if (isNaN(dt.getTime())) return text;
            return dt.toLocaleString('pt-BR');
        }

        function onlyDate(value) {
            if (!value) return '';
            const text = String(value).trim();
            if (/^\d{4}-\d{2}-\d{2}$/.test(text)) return text;
            const normalized = text.replace(' ', 'T');
            const dt = new Date(normalized);
            if (isNaN(dt.getTime())) return '';
            const y = dt.getFullYear();
            const m = String(dt.getMonth() + 1).padStart(2, '0');
            const d = String(dt.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        function statusBadge(status) {
            const s = normalizeStatus(status);
            if (s === 'SUCESSO') return '<span class="pill pill-success">● SUCESSO</span>';
            if (s === 'ERRO') return '<span class="pill pill-danger">● ERRO</span>';
            return '<span class="pill pill-warning">● N/D</span>';
        }

        function remoteStatus(item) {
            const uploadOk = normalizeStatus(item.status_upload) === 'SUCESSO';
            const remoteOk = hasRemote(item);

            if (uploadOk && remoteOk) return { label: 'Publicado', cls: 'pill-success' };
            if (!uploadOk && remoteOk) return { label: 'Link público disponível', cls: 'pill-info' };
            if (!remoteOk && uploadOk) return { label: 'Sem link público', cls: 'pill-warning' };
            return { label: 'Falha na publicação', cls: 'pill-danger' };
        }

        function remoteBadge(item) {
            const rs = remoteStatus(item);
            return `<span class="pill ${rs.cls}">● ${escapeHtml(rs.label)}</span>`;
        }

        function toast(msg, type = 'success') {
            const div = document.createElement('div');
            div.className = `toast ${type}`;
            div.textContent = msg;
            els.toastWrap.appendChild(div);
            setTimeout(() => div.remove(), 4200);
        }

        function montarQuery(page) {
            const params = new URLSearchParams();
            params.set('page', page || 1);
            params.set('pageSize', els.pageSize.value || 20);
            if (els.data.value) params.set('data', els.data.value);
            if (els.status.value) params.set('status', els.status.value);
            return params.toString();
        }

        function aplicarBuscaClientSide(items) {
            const termo = (els.busca.value || '').trim().toLowerCase();
            if (!termo) return items.slice();

            return items.filter(item => {
                const bag = [
                    item.id,
                    item.nome_arquivo,
                    item.url_publica,
                    item.caminho_remoto,
                    item.status_upload,
                    item.mensagem_erro,
                    item.responsavel,
                    item.cpf_responsavel,
                    item.data_relatorio,
                    item.data_geracao,
                    item.data_upload
                ].join(' ').toLowerCase();

                return bag.includes(termo);
            });
        }

        function computeMetrics(items) {
            const total = items.length;
            const sucesso = items.filter(x => normalizeStatus(x.status_upload) === 'SUCESSO').length;
            const erro = items.filter(x => normalizeStatus(x.status_upload) === 'ERRO').length;
            const remotoPendente = items.filter(x => !hasRemote(x)).length;
            const percent = total ? Math.round((sucesso / total) * 100) : 0;
            return { total, sucesso, erro, remotoPendente, percent };
        }

        function renderStats() {
            const m = computeMetrics(state.filteredItems);

            els.statTotal.textContent = state.total || 0;
            els.statSucesso.textContent = m.sucesso;
            els.statErro.textContent = m.erro;
            els.statRemoto.textContent = m.remotoPendente;
            els.statPercent.textContent = m.percent + '%';

            const inicio = state.total ? (((state.page - 1) * state.pageSize) + 1) : 0;
            const fim = state.total ? Math.min(state.page * state.pageSize, state.total) : 0;
            els.resumo.textContent = state.total
                ? `Exibindo ${inicio} até ${fim} de ${state.total} registro(s).`
                : 'Nenhum registro retornado para os filtros informados.';
        }

        function renderTable() {
            const items = state.filteredItems;

            if (!items.length) {
                els.tabelaBody.innerHTML = `
                <tr>
                    <td colspan="8">
                        <div class="empty">
                            <div class="empty-icon">📂</div>
                            <div><strong>Nenhum relatório encontrado</strong></div>
                            <div style="margin-top:6px;">Tente outro filtro ou gere um novo relatório.</div>
                        </div>
                    </td>
                </tr>
            `;
                return;
            }

            els.tabelaBody.innerHTML = items.map(item => {
                const reenviando = !!state.reenviando[item.id];
                const isErro = normalizeStatus(item.status_upload) === 'ERRO';
                const podeReenviar = !!item.data_relatorio;
                return `
                <tr class="${isErro ? 'row-error' : ''}">
                    <td><strong>#${escapeHtml(item.id)}</strong></td>
                    <td>
                        <div class="file-stack">
                            <div class="file-name">${escapeHtml(item.nome_arquivo || '-')}</div>
                            <div class="file-meta">${escapeHtml(item.caminho_remoto || 'Sem caminho remoto')}</div>
                        </div>
                    </td>
                    <td>${formatDate(item.data_relatorio)}</td>
                    <td>${statusBadge(item.status_upload)}</td>
                    <td>
                        <div class="file-stack">
                            <div>${remoteBadge(item)}</div>
                            <div class="file-meta">${hasRemote(item) ? 'Relatório publicado' : 'Ainda sem link público salvo'}</div>
                        </div>
                    </td>
                    <td>
                        <div class="file-stack">
                            <div class="file-name">${escapeHtml(item.responsavel || 'Não informado')}</div>
                            <div class="file-meta">${escapeHtml(item.cpf_responsavel || 'CPF não informado')}</div>
                        </div>
                    </td>
                    <td>${formatDate(item.data_geracao)}</td>
                    <td>
                        <div class="actions">
                            ${item.url_publica ? `<a class="action-btn" href="${escapeHtml(item.url_publica)}" target="_blank" rel="noopener">📄 Abrir</a>` : ''}
                            <button class="action-btn" type="button" onclick="PDFDASH.verDetalhes(${Number(item.id)})">🔍 Detalhes</button>
                            ${(normalizeStatus(item.status_upload) === 'ERRO' || !hasRemote(item)) ? `<button class="action-btn ${reenviando ? 'loading' : ''}" type="button" ${(!podeReenviar || reenviando) ? 'disabled' : ''} onclick="PDFDASH.reenviar(${Number(item.id)})">${reenviando ? 'Reenviando...' : '☁️ Republicar'}</button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
            }).join('');
        }

        function renderPagination() {
            const pages = Math.max(1, Number(state.pages || 1));
            const current = Math.max(1, Number(state.page || 1));
            let start = Math.max(1, current - 2);
            let end = Math.min(pages, current + 2);

            if ((end - start) < 4) {
                if (start === 1) end = Math.min(pages, start + 4);
                else if (end === pages) start = Math.max(1, pages - 4);
            }

            const buttons = [];
            buttons.push(`<button class="page-btn" ${current <= 1 ? 'disabled' : ''} onclick="PDFDASH.carregar(${current - 1})">‹</button>`);
            for (let i = start; i <= end; i++) {
                buttons.push(`<button class="page-btn ${i === current ? 'active' : ''}" onclick="PDFDASH.carregar(${i})">${i}</button>`);
            }
            buttons.push(`<button class="page-btn" ${current >= pages ? 'disabled' : ''} onclick="PDFDASH.carregar(${current + 1})">›</button>`);
            els.paginacao.innerHTML = buttons.join('');
        }

        function renderRanking() {
            const map = new Map();

            state.filteredItems.forEach(item => {
                const key = (item.responsavel || 'Não informado').trim() || 'Não informado';
                const current = map.get(key) || { nome: key, total: 0, erros: 0, cpf: item.cpf_responsavel || '' };
                current.total += 1;
                if (normalizeStatus(item.status_upload) === 'ERRO') current.erros += 1;
                map.set(key, current);
            });

            const ranking = Array.from(map.values())
                .sort((a, b) => b.total - a.total)
                .slice(0, 5);

            if (!ranking.length) {
                els.rankingList.innerHTML = `<div class="rank-item"><div class="rank-left"><div class="rank-name">Sem dados</div><div class="rank-meta">Nenhum responsável encontrado.</div></div></div>`;
                return;
            }

            els.rankingList.innerHTML = ranking.map((item, idx) => `
            <div class="rank-item">
                <div class="rank-left">
                    <div class="rank-name">${idx + 1}. ${escapeHtml(item.nome)}</div>
                    <div class="rank-meta">${escapeHtml(item.cpf || 'CPF não informado')} • ${item.erros} falha(s)</div>
                </div>
                <span class="pill ${item.erros ? 'pill-warning' : 'pill-success'}">${item.total} relatório(s)</span>
            </div>
        `).join('');
        }

        function renderAlerts() {
            const items = state.filteredItems;
            const m = computeMetrics(items);
            const alerts = [];

            if (!items.length) {
                alerts.push({ type: 'info', text: 'Sem dados suficientes para gerar alertas.' });
            } else {
                if (m.erro > 0) alerts.push({ type: 'warning', text: `Existem ${m.erro} relatório(s) com falha de upload nesta busca.` });
                if (m.remotoPendente > 0) alerts.push({ type: 'warning', text: `Há ${m.remotoPendente} item(ns) sem URL pública salva.` });
                if (m.percent === 100) alerts.push({ type: 'success', text: 'Todos os relatórios filtrados estão publicados com sucesso.' });
                if (m.percent > 0 && m.percent < 80) alerts.push({ type: 'danger', text: 'A taxa de publicação está abaixo de 80% na busca atual.' });

                const groupedByDate = {};
                items.forEach(item => {
                    const d = onlyDate(item.data_relatorio || item.data_geracao);
                    if (!d) return;
                    groupedByDate[d] = (groupedByDate[d] || 0) + 1;
                });
                const days = Object.keys(groupedByDate).sort();
                if (days.length >= 2) {
                    const last = groupedByDate[days[days.length - 1]];
                    const prev = groupedByDate[days[days.length - 2]];
                    if (prev > 0 && last < prev) {
                        alerts.push({ type: 'info', text: `Houve queda no volume de relatórios no último dia listado (${last} contra ${prev}).` });
                    }
                }
            }

            els.alertsList.innerHTML = alerts.map(a => `
            <div class="alert-item">
                <span class="pill ${a.type === 'success' ? 'pill-success' : a.type === 'danger' ? 'pill-danger' : a.type === 'warning' ? 'pill-warning' : 'pill-info'}">● ${a.type.toUpperCase()}</span>
                <div class="alert-text">${escapeHtml(a.text)}</div>
            </div>
        `).join('');

            if (!items.length) {
                els.insightPill.className = 'pill pill-info';
                els.insightPill.textContent = '● Sem dados para análise';
            } else if (m.percent === 100) {
                els.insightPill.className = 'pill pill-success';
                els.insightPill.textContent = '● Publicação estável';
            } else if (m.percent >= 80) {
                els.insightPill.className = 'pill pill-warning';
                els.insightPill.textContent = '● Atenção moderada';
            } else {
                els.insightPill.className = 'pill pill-danger';
                els.insightPill.textContent = '● Ação recomendada';
            }
        }


        let chartPorDiaInstance = null;
        let chartStatusInstance = null;

        function destroyChart(chartRef) {
            if (chartRef && typeof chartRef.destroy === 'function') {
                chartRef.destroy();
            }
        }

        function buildCommonChartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 700,
                    easing: 'easeOutQuart'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(23, 33, 47, 0.95)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#64748b',
                            font: {
                                size: 11,
                                weight: '600'
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#e5e7eb'
                        },
                        ticks: {
                            color: '#94a3b8',
                            precision: 0,
                            font: {
                                size: 11,
                                weight: '600'
                            }
                        },
                        border: {
                            display: false
                        }
                    }
                }
            };
        }

        function drawStatusChart() {
            if (!els.chartStatus || typeof Chart === 'undefined') return;

            const m = computeMetrics(state.filteredItems);
            const labels = ['Sucesso', 'Erro', 'Sem link público'];
            const values = [m.sucesso, m.erro, m.remotoPendente];

            destroyChart(chartStatusInstance);

            chartStatusInstance = new Chart(els.chartStatus.getContext('2d'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Quantidade',
                        data: values,
                        backgroundColor: ['#1f77d0', '#dc3545', '#f59e0b'],
                        borderRadius: 10,
                        borderSkipped: false,
                        maxBarThickness: 56
                    }]
                },
                options: {
                    ...buildCommonChartOptions(),
                    plugins: {
                        ...buildCommonChartOptions().plugins,
                        title: {
                            display: true,
                            text: 'Distribuição de status',
                            align: 'start',
                            color: '#17212f',
                            font: {
                                size: 18,
                                weight: '700'
                            },
                            padding: {
                                bottom: 16
                            }
                        }
                    }
                }
            });
        }

        function drawDailyChart() {
            if (!els.chartPorDia || typeof Chart === 'undefined') return;

            const grouped = {};
            state.filteredItems.forEach(item => {
                const d = onlyDate(item.data_relatorio || item.data_geracao);
                if (!d) return;
                grouped[d] = (grouped[d] || 0) + 1;
            });

            const sortedKeys = Object.keys(grouped).sort().slice(-7);
            const labels = sortedKeys.map(d => formatDate(d));
            const values = sortedKeys.map(d => grouped[d]);

            destroyChart(chartPorDiaInstance);

            chartPorDiaInstance = new Chart(els.chartPorDia.getContext('2d'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Relatórios',
                        data: values,
                        backgroundColor: '#2c5c88',
                        borderRadius: 10,
                        borderSkipped: false,
                        maxBarThickness: 62
                    }]
                },
                options: {
                    ...buildCommonChartOptions(),
                    plugins: {
                        ...buildCommonChartOptions().plugins,
                        title: {
                            display: true,
                            text: 'Relatórios por dia',
                            align: 'start',
                            color: '#17212f',
                            font: {
                                size: 18,
                                weight: '700'
                            },
                            padding: {
                                bottom: 16
                            }
                        }
                    }
                }
            });
        }

        function renderAnalytics() {
            renderStats();
            renderRanking();
            renderAlerts();
            drawDailyChart();
            drawStatusChart();
        }

        function obterItem(id) {
            return state.items.find(item => Number(item.id) === Number(id)) || null;
        }

        function abrirModal(html) {
            els.modalBody.innerHTML = html;
            els.modal.classList.add('show');
        }

        function fecharModal() {
            els.modal.classList.remove('show');
        }

        function verDetalhes(id) {
            const item = obterItem(id);
            if (!item) return;

            const rs = remoteStatus(item);

            abrirModal(`
            <div class="detail-grid">
                <div class="detail-item"><span class="detail-label">ID</span><div class="detail-value">#${escapeHtml(item.id)}</div></div>
                <div class="detail-item"><span class="detail-label">Status do upload</span><div class="detail-value">${statusBadge(item.status_upload)}</div></div>
                <div class="detail-item"><span class="detail-label">Status da nuvem</span><div class="detail-value"><span class="pill ${rs.cls}">● ${escapeHtml(rs.label)}</span></div></div>
                <div class="detail-item"><span class="detail-label">Data do relatório</span><div class="detail-value">${formatDate(item.data_relatorio)}</div></div>
                <div class="detail-item"><span class="detail-label">Arquivo</span><div class="detail-value">${escapeHtml(item.nome_arquivo || '-')}</div></div>
                <div class="detail-item"><span class="detail-label">Responsável</span><div class="detail-value">${escapeHtml(item.responsavel || 'Não informado')}</div></div>
                <div class="detail-item"><span class="detail-label">CPF do responsável</span><div class="detail-value">${escapeHtml(item.cpf_responsavel || 'Não informado')}</div></div>
                <div class="detail-item"><span class="detail-label">Gerado em</span><div class="detail-value">${formatDate(item.data_geracao)}</div></div>
                <div class="detail-item"><span class="detail-label">Upload em</span><div class="detail-value">${formatDate(item.data_upload)}</div></div>
                <div class="detail-item" style="grid-column:1 / -1"><span class="detail-label">Caminho remoto</span><div class="detail-value">${escapeHtml(item.caminho_remoto || 'Não informado')}</div></div>
                <div class="detail-item" style="grid-column:1 / -1"><span class="detail-label">URL pública</span><div class="detail-value">${item.url_publica ? `<a href="${escapeHtml(item.url_publica)}" target="_blank" rel="noopener">${escapeHtml(item.url_publica)}</a>` : 'Não disponível'}</div></div>
            </div>
            ${item.mensagem_erro ? `<div class="error-box"><strong>Mensagem de erro:</strong>\n${escapeHtml(item.mensagem_erro)}</div>` : ''}
        `);
        }

        async function reenviar(id) {
            const item = obterItem(id);
            if (!item || !item.data_relatorio) {
                toast('Não foi possível identificar a data do relatório para reenviar.', 'warning');
                return;
            }

            if (!confirm(`Deseja reenviar o relatório da data ${formatDate(item.data_relatorio)} para a nuvem?`)) {
                return;
            }

            state.reenviando[id] = true;
            renderTable();

            try {
                const url = `/admin/api/relatorio/pdf?data=${encodeURIComponent(item.data_relatorio)}&upload=1`;
                const resp = await fetch(url, { credentials: 'same-origin' });
                const data = await resp.json();

                if (!resp.ok || !data.success) {
                    throw new Error(data.message || data.error || 'Falha ao reenviar o relatório.');
                }

                toast('Relatório reenviado com sucesso para a nuvem.', 'success');
                await carregar(state.page);
            } catch (error) {
                toast(error.message || 'Falha ao reenviar para a nuvem.', 'error');
            } finally {
                delete state.reenviando[id];
                renderTable();
            }
        }

        async function reenviarFalhas() {
            const falhas = state.filteredItems.filter(item => normalizeStatus(item.status_upload) === 'ERRO' || !hasRemote(item));
            if (!falhas.length) {
                toast('Nenhuma falha encontrada para reenviar.', 'warning');
                return;
            }

            if (!confirm(`Deseja reenviar ${falhas.length} item(ns) com falha ou sem link público?`)) {
                return;
            }

            for (const item of falhas) {
                if (item.data_relatorio) {
                    try {
                        const url = `/admin/api/relatorio/pdf?data=${encodeURIComponent(item.data_relatorio)}&upload=1`;
                        const resp = await fetch(url, { credentials: 'same-origin' });
                        const data = await resp.json();
                        if (!resp.ok || !data.success) throw new Error(data.message || data.error || 'Falha no reenvio em massa.');
                    } catch (err) {
                        console.error(err);
                    }
                }
            }

            toast('Processo de reenvio das pendências finalizado.', 'success');
            await carregar(state.page);
        }

        function exportarCSV() {
            const rows = state.filteredItems;
            if (!rows.length) {
                toast('Não há dados para exportar.', 'warning');
                return;
            }

            const headers = ['id', 'nome_arquivo', 'data_relatorio', 'status_upload', 'url_publica', 'caminho_remoto', 'responsavel', 'cpf_responsavel', 'data_geracao', 'data_upload', 'mensagem_erro'];
            const csv = [
                headers.join(';'),
                ...rows.map(item => headers.map(key => `"${String(item[key] ?? '').replaceAll('"', '""')}"`).join(';'))
            ].join('\n');

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            const hoje = new Date();
            const nome = `historico_pdf_${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-${String(hoje.getDate()).padStart(2, '0')}.csv`;
            a.href = url;
            a.download = nome;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }

        function limparFiltros() {
            els.busca.value = '';
            els.data.value = '';
            els.status.value = '';
            els.pageSize.value = '20';
            carregar(1);
        }

        async function carregar(page = 1) {
            state.page = page;
            state.pageSize = Number(els.pageSize.value || 20);

            els.tabelaBody.innerHTML = `
            <tr>
                <td colspan="8">
                    <div class="empty">
                        <div class="empty-icon">⏳</div>
                        <div>Carregando histórico...</div>
                    </div>
                </td>
            </tr>
        `;

            try {
                const resp = await fetch('/admin/api/relatorio/pdf/historico?' + montarQuery(page), {
                    credentials: 'same-origin'
                });

                const data = await resp.json();

                if (!resp.ok || !data.success) {
                    throw new Error(data.message || data.error || 'Falha ao carregar o histórico.');
                }

                state.page = Number(data.page || 1);
                state.pages = Number(data.pages || 1);
                state.total = Number(data.total || 0);
                state.items = Array.isArray(data.items) ? data.items : [];
                state.filteredItems = aplicarBuscaClientSide(state.items);

                renderTable();
                renderPagination();
                renderAnalytics();
            } catch (error) {
                els.tabelaBody.innerHTML = `
                <tr>
                    <td colspan="8">
                        <div class="empty">
                            <div class="empty-icon">⚠️</div>
                            <div><strong>Não foi possível carregar o histórico.</strong></div>
                            <div style="margin-top:6px;">${escapeHtml(error.message || 'Erro inesperado.')}</div>
                        </div>
                    </td>
                </tr>
            `;
                els.resumo.textContent = 'Falha ao carregar dados.';
                toast(error.message || 'Falha ao carregar histórico.', 'error');
            }
        }

        els.busca.addEventListener('input', function () {
            state.filteredItems = aplicarBuscaClientSide(state.items);
            renderTable();
            renderAnalytics();
        });

        els.pageSize.addEventListener('change', function () {
            carregar(1);
        });

        els.modal.addEventListener('click', function (e) {
            if (e.target === els.modal) fecharModal();
        });

        window.PDFDASH = {
            carregar,
            limparFiltros,
            reenviar,
            reenviarFalhas,
            verDetalhes,
            fecharModal,
            exportarCSV
        };

        carregar(1);
        setInterval(() => {
            carregar(state.page);
        }, state.autoRefreshMs);
    })();
</script>