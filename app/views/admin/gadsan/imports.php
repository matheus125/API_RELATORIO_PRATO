<div class="gadsan-page-heading"><div><h2><i class="bi bi-file-earmark-arrow-up me-2" aria-hidden="true"></i>Importações e revisão</h2><p>Acompanhe arquivos recebidos, origens dos cadastros e pendências de conferência.</p></div></div>

<?php if (!empty($error)): ?><div class="gadsan-alert" role="alert" data-form-error><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span><?= ge($error) ?></span></div><?php endif ?>
<form method="post" action="/admin/gadsan/importacoes/enviar" enctype="multipart/form-data" class="card mb-4" data-gadsan-form>
  <div class="card-header"><div class="gadsan-section-title"><span class="gadsan-section-icon"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i></span><div><h3>Importar planilha GADSAN</h3><p>Envie um arquivo XLSX com até 10 MB e 10.000 linhas.</p></div></div></div>
  <div class="card-body">
    <input type="hidden" name="_csrf" value="<?= ge($csrf) ?>">
    <label for="arquivo" class="gadsan-dropzone w-100" style="cursor:pointer"><span class="gadsan-dropzone-icon"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i></span><span><strong data-file-name>Selecione sua planilha Excel</strong><p>Os dados serão validados antes de entrar no cadastro. Pendências ficam disponíveis para revisão.</p></span><input class="visually-hidden" id="arquivo" type="file" name="arquivo" accept=".xlsx" required></label>
    <div class="form-check mt-3"><input type="checkbox" class="form-check-input" id="confirmar" name="confirmar" value="1" required><label class="form-check-label" for="confirmar">Conferi o arquivo e desejo iniciar a importação.</label></div>
  </div>
  <div class="card-footer d-flex justify-content-end"><button class="btn btn-primary" type="submit"><i class="bi bi-shield-check me-1"></i>Validar e importar</button></div>
</form>

<div class="gadsan-table-wrap"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Arquivo</th><th>Status</th><th>Linhas</th><th>Novos</th><th>Alertas</th><th class="text-end">Ação</th></tr></thead><tbody>
<?php foreach ($imports as $row): ?><tr><td data-label="Arquivo"><strong><?= ge($row['nome_arquivo']) ?></strong><small class="d-block text-muted"><?= ge($row['data_importacao']) ?></small></td><td data-label="Status"><span class="gadsan-badge <?= in_array($row['status'],['concluida','ja_importado'],true)?'gadsan-badge-success':($row['status']==='falhou'?'gadsan-badge-warning':'') ?>"><?= ge(str_replace('_',' ',$row['status'])) ?></span></td><td data-label="Linhas"><strong><?= (int)$row['total_linhas'] ?></strong></td><td data-label="Novos"><strong><?= (int)$row['total_importados'] ?></strong></td><td data-label="Alertas"><strong><?= (int)$row['total_inconsistencias'] ?></strong></td><td data-label="Ação" class="text-end"><a class="btn btn-sm btn-outline-primary" href="/admin/gadsan/importacoes/<?= (int)$row['id'] ?>"><i class="bi bi-eye me-1"></i>Conferir</a></td></tr><?php endforeach ?>
<?php if (!$imports): ?><tr><td colspan="6" class="gadsan-empty"><i class="bi bi-inbox" aria-hidden="true"></i><strong>Nenhuma importação registrada</strong><span class="d-block mt-1">Envie a primeira planilha para começar.</span></td></tr><?php endif ?>
</tbody></table></div></div><p class="form-text mt-3"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Exibindo as 100 cargas mais recentes.</p>
