<h2 class="h4 mb-3">Importações e revisão</h2>
<p class="text-muted">Acompanhe os arquivos recebidos, a origem dos cadastros e as informações que precisam de conferência.</p>
<?php if (!empty($error)): ?><div class="alert alert-danger" role="alert"><?= ge($error) ?></div><?php endif ?>
<form method="post" action="/admin/gadsan/importacoes/enviar" enctype="multipart/form-data" class="card mb-4" data-gadsan-form>
<div class="card-header"><h3 class="h5 mb-0">Importar planilha GADSAN</h3></div><div class="card-body">
<input type="hidden" name="_csrf" value="<?= ge($csrf) ?>">
<label for="arquivo" class="form-label">Arquivo Excel (.xlsx, até 10 MB)</label><input class="form-control" id="arquivo" type="file" name="arquivo" accept=".xlsx" required>
<p class="form-text mb-3">O arquivo será validado. Dados divergentes ficam para revisão e não substituem automaticamente um cadastro existente.</p>
<label class="form-check"><input type="checkbox" class="form-check-input" name="confirmar" value="1" required><span class="form-check-label">Conferi o arquivo e desejo importar seus dados.</span></label>
</div><div class="card-footer"><button class="btn btn-primary">Validar e importar</button></div></form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Arquivo</th><th>Status</th><th>Linhas</th><th>Novos</th><th>Alertas</th><th>Ação</th></tr></thead><tbody>
<?php foreach ($imports as $row): ?><tr><td><?= ge($row['nome_arquivo']) ?><small class="d-block text-muted"><?= ge($row['data_importacao']) ?></small></td><td><?= ge($row['status']) ?></td><td><?= (int)$row['total_linhas'] ?></td><td><?= (int)$row['total_importados'] ?></td><td><?= (int)$row['total_inconsistencias'] ?></td><td><a class="btn btn-sm btn-outline-primary" href="/admin/gadsan/importacoes/<?= (int)$row['id'] ?>">Conferir</a></td></tr><?php endforeach ?>
<?php if (!$imports): ?><tr><td colspan="6" class="text-center text-muted p-5">Nenhuma importação registrada neste banco.</td></tr><?php endif ?>
</tbody></table></div></div><p class="form-text">Exibindo as 100 cargas mais recentes.</p>
