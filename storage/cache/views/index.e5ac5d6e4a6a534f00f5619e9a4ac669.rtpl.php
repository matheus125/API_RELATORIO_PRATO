<?php if(!class_exists('Rain\Tpl')){exit;}?><main class="app-main">
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="portal-page-header">
        <div class="portal-kicker"><i class="bi bi-bar-chart-line"></i> Painel institucional</div>
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
          <div>
            <h1 class="portal-page-title">Portal de Relatórios</h1>
            <p class="portal-page-subtitle">Acompanhe rapidamente o desempenho operacional, acessos, auditoria e indicadores principais do ambiente administrativo do Governo do Estado do Amazonas.</p>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <?php if( canAccess('FUNCIONARIOS_VIEW') ){ ?><a href="/admin/funcionarios" class="btn btn-light text-primary"><i class="bi bi-people me-1"></i> Funcionários</a><?php } ?>

            <?php if( canAccess('DASHBOARD_VIEW') ){ ?><a href="/admin/relatorio/pdf/historico" class="btn btn-outline-light"><i class="bi bi-file-earmark-pdf me-1"></i> PDFs</a><?php } ?>

          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
          <div class="small-box text-bg-primary shadow-sm rounded-4">
            <div class="inner">
              <h3><?php echo htmlspecialchars( $stats["total_funcionarios"], ENT_COMPAT, 'UTF-8', FALSE ); ?></h3>
              <p>Funcionários ativos</p>
            </div>
            <i class="small-box-icon bi bi-people"></i>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="small-box text-bg-success shadow-sm rounded-4">
            <div class="inner">
              <h3><?php echo htmlspecialchars( $stats["usuarios_ativos"], ENT_COMPAT, 'UTF-8', FALSE ); ?></h3>
              <p>Usuários com login ativo</p>
            </div>
            <i class="small-box-icon bi bi-person-check"></i>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="small-box text-bg-warning shadow-sm rounded-4">
            <div class="inner">
              <h3><?php echo htmlspecialchars( $stats["logs_hoje"], ENT_COMPAT, 'UTF-8', FALSE ); ?></h3>
              <p>Eventos registrados hoje</p>
            </div>
            <i class="small-box-icon bi bi-journal-text"></i>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="small-box text-bg-danger shadow-sm rounded-4">
            <div class="inner">
              <h3><?php echo htmlspecialchars( $stats["acessos_negados_hoje"], ENT_COMPAT, 'UTF-8', FALSE ); ?></h3>
              <p>Acessos negados hoje</p>
            </div>
            <i class="small-box-icon bi bi-shield-x"></i>
          </div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-xl-8">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
              <h3 class="card-title mb-0">Movimento operacional</h3>
              <span class="badge badge-soft-primary rounded-pill px-3 py-2">Resumo visual do painel</span>
            </div>
            <div class="card-body">
              <div style="position:relative; height:320px;">
                <canvas id="dashboardResumoChart"></canvas>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-4">
          <div class="card h-100">
            <div class="card-header"><h3 class="card-title mb-0">Usuário logado</h3></div>
            <div class="card-body">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width:58px;height:58px;font-size:1.4rem;"><i class="bi bi-person-circle"></i></div>
                <div>
                  <div class="fw-bold fs-5"><?php echo htmlspecialchars( $usuario["nome_funcionario"], ENT_COMPAT, 'UTF-8', FALSE ); ?></div>
                  <div class="text-muted">Perfil <?php echo htmlspecialchars( $usuario["perfil"], ENT_COMPAT, 'UTF-8', FALSE ); ?></div>
                </div>
              </div>
              <div class="row g-3">
                <div class="col-12"><div class="p-3 rounded-4 border bg-light-subtle"><div class="text-muted small">CPF</div><div class="fw-semibold"><?php echo htmlspecialchars( $usuario["cpf"], ENT_COMPAT, 'UTF-8', FALSE ); ?></div></div></div>
                <div class="col-12"><div class="p-3 rounded-4 border bg-light-subtle"><div class="text-muted small">Último login</div><div class="fw-semibold"><?php if( $stats["ultimo_login"] ){ ?><?php echo htmlspecialchars( $stats["ultimo_login"], ENT_COMPAT, 'UTF-8', FALSE ); ?><?php }else{ ?>Ainda sem registros<?php } ?></div></div></div>
              </div>
              <div class="d-grid gap-2 mt-4">
                <?php if( canAccess('FUNCIONARIOS_VIEW') ){ ?><a href="/admin/funcionarios" class="btn btn-primary"><i class="bi bi-people me-1"></i> Abrir funcionários</a><?php } ?>

                <?php if( canAccess('ACL_PROFILES_MANAGE') ){ ?><a href="/admin/seguranca/permissoes" class="btn btn-outline-primary"><i class="bi bi-shield-lock me-1"></i> Ajustar permissões</a><?php } ?>

                <?php if( canAccess('AUDITORIA_VIEW') ){ ?><a href="/admin/seguranca/auditoria" class="btn btn-outline-secondary"><i class="bi bi-clock-history me-1"></i> Ver auditoria</a><?php } ?>

              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-xl-8">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
              <h3 class="card-title mb-0">Últimos registros de auditoria</h3>
              <span class="text-muted small">Monitoramento recente do sistema</span>
            </div>
            <div class="card-body p-0 table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Funcionário</th>
                    <th>Ação</th>
                    <th>IP</th>
                    <th>Data</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if( count($recentLogs) > 0 ){ ?>

                    <?php $counter1=-1;  if( isset($recentLogs) && ( is_array($recentLogs) || $recentLogs instanceof Traversable ) && sizeof($recentLogs) ) foreach( $recentLogs as $key1 => $value1 ){ $counter1++; ?>

                    <tr>
                      <td><div class="fw-semibold"><?php echo htmlspecialchars( $value1["nome_funcionario"], ENT_COMPAT, 'UTF-8', FALSE ); ?></div></td>
                      <td><span class="badge badge-soft-primary rounded-pill px-3 py-2"><?php echo htmlspecialchars( $value1["acao"], ENT_COMPAT, 'UTF-8', FALSE ); ?></span></td>
                      <td><?php echo htmlspecialchars( $value1["ip"], ENT_COMPAT, 'UTF-8', FALSE ); ?></td>
                      <td><?php echo htmlspecialchars( $value1["created_at"], ENT_COMPAT, 'UTF-8', FALSE ); ?></td>
                    </tr>
                    <?php } ?>

                  <?php }else{ ?>

                    <tr>
                      <td colspan="4" class="text-center text-muted py-4">Nenhum log encontrado.</td>
                    </tr>
                  <?php } ?>

                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-xl-4">
          <div class="card mb-4">
            <div class="card-header"><h3 class="card-title mb-0">Indicadores rápidos</h3></div>
            <div class="card-body">
              <div class="d-grid gap-3">
                <div class="p-3 rounded-4 border"><div class="text-muted small mb-1">Status operacional</div><div class="fw-bold fs-5 text-success">Sistema online</div></div>
                <div class="p-3 rounded-4 border"><div class="text-muted small mb-1">Base institucional</div><div class="fw-bold">Governo do Estado do Amazonas</div></div>
                <div class="p-3 rounded-4 border"><div class="text-muted small mb-1">Módulo principal</div><div class="fw-bold">Relatórios e segurança</div></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('dashboardResumoChart');
    if (!canvas || typeof Chart === 'undefined') return;
    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: ['Funcionários', 'Usuários ativos', 'Logs hoje', 'Negados hoje'],
        datasets: [{
          label: 'Quantidade',
          data: ["<?php echo htmlspecialchars( $stats["total_funcionarios"] ?? 0, ENT_COMPAT, 'UTF-8', FALSE ); ?>", "<?php echo htmlspecialchars( $stats["usuarios_ativos"] ?? 0, ENT_COMPAT, 'UTF-8', FALSE ); ?>", "<?php echo htmlspecialchars( $stats["logs_hoje"] ?? 0, ENT_COMPAT, 'UTF-8', FALSE ); ?>", "<?php echo htmlspecialchars( $stats["acessos_negados_hoje"] ?? 0, ENT_COMPAT, 'UTF-8', FALSE ); ?>"],
          borderRadius: 14,
          maxBarThickness: 56
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 } },
          x: { grid: { display: false } }
        }
      }
    });
  });
</script>
