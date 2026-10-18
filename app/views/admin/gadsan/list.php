<div class="gadsan-page-heading">
  <div><h2>Colaboradores</h2><p><strong><?= (int)$listing['total'] ?></strong> cadastro(s) encontrado(s)</p></div>
  <?php if ($canEdit): ?><div class="gadsan-heading-actions"><a class="btn btn-primary" href="/admin/gadsan/colaboradores/novo"><i class="bi bi-plus-lg me-2"></i>Novo colaborador</a></div><?php endif ?>
</div>

<form method="get" action="/admin/gadsan" class="gadsan-search-card">
  <label for="busca" class="form-label"><i class="bi bi-search" aria-hidden="true"></i> Buscar colaborador</label>
  <div class="input-group">
    <span class="input-group-text"><i class="bi bi-person-search" aria-hidden="true"></i></span>
    <input class="form-control" name="busca" id="busca" maxlength="100" value="<?= ge($listing['term']) ?>" placeholder="Digite um nome para filtrar a lista" autocomplete="off">
    <button class="btn btn-primary" type="submit">Buscar</button>
    <?php if ($listing['term'] !== ''): ?><a class="btn btn-light" href="/admin/gadsan">Limpar</a><?php endif ?>
  </div>
</form>

<div class="gadsan-table-wrap">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Colaborador</th><th>Situação</th><th>Alocações ativas</th><th class="text-end"><span class="visually-hidden">Ações</span></th></tr></thead>
      <tbody>
      <?php foreach ($listing['rows'] as $row): ?>
        <?php $initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr(trim((string)$row['nome']), 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr(trim((string)$row['nome']), 0, 1)); ?>
        <tr>
          <td data-label="Colaborador"><div class="gadsan-table-primary"><span class="gadsan-avatar" aria-hidden="true"><?= ge($initial) ?></span><span><strong><?= ge($row['nome']) ?></strong><small>Cadastro #<?= (int)$row['id'] ?></small></span></div></td>
          <td data-label="Situação"><span class="gadsan-status <?= $row['ativo'] ? 'gadsan-status-active' : 'gadsan-status-inactive' ?>"><?= $row['ativo'] ? 'Ativo' : 'Inativo' ?></span></td>
          <td data-label="Alocações ativas"><span class="gadsan-count"><i class="bi bi-briefcase me-1" aria-hidden="true"></i><?= (int)$row['alocacoes'] ?></span></td>
          <td data-label="Ação" class="text-end"><a class="btn btn-sm btn-outline-primary" href="/admin/gadsan/colaboradores/<?= (int)$row['id'] ?>"><i class="bi bi-arrow-up-right me-1" aria-hidden="true"></i>Abrir cadastro</a></td>
        </tr>
      <?php endforeach ?>
      <?php if (!$listing['rows']): ?><tr><td colspan="4" class="gadsan-empty"><i class="bi bi-person-vcard" aria-hidden="true"></i><strong>Nenhum colaborador encontrado</strong><span class="d-block mt-1">Cadastre uma pessoa ou ajuste os termos da busca.</span></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<nav class="gadsan-pagination" aria-label="Paginação de colaboradores">
  <?php if ($listing['page'] > 1): ?><a href="?pagina=<?= $listing['page']-1 ?>&busca=<?= rawurlencode($listing['term']) ?>" class="btn btn-light"><i class="bi bi-chevron-left me-1"></i>Anterior</a><?php else: ?><span></span><?php endif ?>
  <span>Página <strong><?= (int)$listing['page'] ?></strong></span>
  <?php if ($listing['page']*20 < $listing['total']): ?><a href="?pagina=<?= $listing['page']+1 ?>&busca=<?= rawurlencode($listing['term']) ?>" class="btn btn-light">Próxima<i class="bi bi-chevron-right ms-1"></i></a><?php else: ?><span></span><?php endif ?>
</nav>
