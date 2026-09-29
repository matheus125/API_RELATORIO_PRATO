<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
  <div><h2 class="h4 mb-1">Colaboradores</h2><p class="text-muted mb-0"><?= (int)$listing['total'] ?> cadastro(s) encontrado(s)</p></div>
  <?php if ($canEdit): ?><a class="btn btn-primary" href="/admin/gadsan/colaboradores/novo"><i class="bi bi-plus-lg me-2"></i>Novo colaborador</a><?php endif ?>
</div>
<form method="get" action="/admin/gadsan" class="gadsan-search mb-4">
  <label for="busca" class="form-label">Buscar por nome</label>
  <div class="input-group"><input class="form-control" name="busca" id="busca" maxlength="100" value="<?= ge($listing['term']) ?>" placeholder="Digite o nome do colaborador"><button class="btn btn-outline-primary">Buscar</button><a class="btn btn-light" href="/admin/gadsan">Limpar</a></div>
</form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
<thead><tr><th>Colaborador</th><th>Situação</th><th>Alocações ativas</th><th><span class="visually-hidden">Ações</span></th></tr></thead>
<tbody><?php foreach ($listing['rows'] as $row): ?><tr><td><strong><?= ge($row['nome']) ?></strong><small class="d-block text-muted">Cadastro #<?= (int)$row['id'] ?></small></td><td><span class="badge <?= $row['ativo']?'text-bg-success':'text-bg-secondary' ?>"><?= $row['ativo']?'Ativo':'Inativo' ?></span></td><td><?= (int)$row['alocacoes'] ?></td><td><a class="btn btn-sm btn-outline-primary" href="/admin/gadsan/colaboradores/<?= (int)$row['id'] ?>">Abrir cadastro</a></td></tr><?php endforeach ?>
<?php if (!$listing['rows']): ?><tr><td colspan="4" class="text-center p-5"><i class="bi bi-person-vcard fs-1 text-muted d-block mb-3"></i><h3 class="h5">Nenhum colaborador encontrado</h3><p class="text-muted mb-0">Cadastre a primeira pessoa ou ajuste a busca.</p></td></tr><?php endif ?>
</tbody></table></div></div>
<nav class="d-flex justify-content-between mt-3" aria-label="Paginação">
<?php if ($listing['page']>1): ?><a href="?pagina=<?= $listing['page']-1 ?>&busca=<?= rawurlencode($listing['term']) ?>" class="btn btn-light">Anterior</a><?php else: ?><span></span><?php endif ?>
<span class="text-muted">Página <?= $listing['page'] ?></span>
<?php if ($listing['page']*20<$listing['total']): ?><a href="?pagina=<?= $listing['page']+1 ?>&busca=<?= rawurlencode($listing['term']) ?>" class="btn btn-light">Próxima</a><?php else: ?><span></span><?php endif ?>
</nav>
