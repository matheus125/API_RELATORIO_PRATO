<?php require_once __DIR__.'/fields.php'; ?>
<div class="gadsan-page-heading"><div><h2><i class="bi bi-sliders2 me-2" aria-hidden="true"></i>Cadastros auxiliares</h2><p>Mantenha as opções usadas nas alocações sempre atualizadas.</p></div></div>

<div class="row g-4 align-items-start">
  <aside class="col-12 col-xl-3">
    <div class="card">
      <div class="card-header"><strong><i class="bi bi-grid-3x3-gap me-2" aria-hidden="true"></i>Catálogos</strong><span class="d-block mt-1 text-muted small">Escolha uma categoria para gerenciar.</span></div>
      <div class="list-group list-group-flush gadsan-catalog-nav">
        <?php foreach (GadsanService::CATALOGS as $key=>$label): ?><a class="list-group-item list-group-item-action d-flex align-items-center justify-content-between <?= $table===$key?'active':'' ?>" href="/admin/gadsan/auxiliares?tipo=<?= ge($key) ?>"><span><i class="bi bi-chevron-right me-2" aria-hidden="true"></i><?= ge($label) ?></span><span class="gadsan-badge"><?= count($catalogs[$key]) ?></span></a><?php endforeach ?>
      </div>
    </div>
  </aside>
  <div class="col-12 col-xl-9">
    <div class="gadsan-page-heading mb-3"><div><h2><?= ge(GadsanService::CATALOGS[$table]) ?></h2><p><?= count($catalogs[$table]) ?> opção(ões) cadastrada(s)</p></div></div>
    <?php if ($error): ?><div class="gadsan-alert" role="alert" data-form-error><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span><?= ge($error) ?></span></div><?php endif ?>
    <form method="post" action="/admin/gadsan/auxiliares/<?= ge($table) ?>" class="card mb-4" data-gadsan-form>
      <div class="card-header"><div class="gadsan-section-title"><span class="gadsan-section-icon"><i class="bi bi-pencil-square" aria-hidden="true"></i></span><div><h3><?= !empty($item['id'])?'Editar opção':'Adicionar opção' ?></h3><p>Use nomes claros para facilitar o preenchimento dos colaboradores.</p></div></div></div>
      <div class="card-body"><input type="hidden" name="_csrf" value="<?= ge($csrf) ?>"><input type="hidden" name="id" value="<?= ge($item['id']??'') ?>"><div class="row g-3">
        <?php
        gadsanInput('nome','Nome *',$item['nome']??null,'text','required maxlength="'.($table==='municipios'?120:255).'"');
        gadsanSelect('ativo','Situação',[['id'=>'1','nome'=>'Ativo'],['id'=>'0','nome'=>'Inativo']],$item['ativo']??'1');
        if ($table==='municipios') {
            gadsanSelect('uf','UF *',array_map(fn($v)=>['id'=>$v,'nome'=>$v],explode(' ','AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO')),$item['uf']??null);
            gadsanInput('codigo_ibge','Código IBGE',$item['codigo_ibge']??null,'text','maxlength="7" pattern="[0-9]{7}" inputmode="numeric"');
        }
        if ($table==='lotacoes') {
            gadsanSelect('tipo','Tipo de lotação',array_map(fn($v)=>['id'=>$v,'nome'=>['nao_classificada'=>'Não classificada','unidade'=>'Unidade','setor'=>'Setor','municipio'=>'Município'][$v]],GadsanService::LOCATION_TYPES),$item['tipo']??'nao_classificada');
            gadsanSelect('municipio_id','Município',$catalogs['municipios'],$item['municipio_id']??null);
        }
        ?>
      </div><p class="form-text mt-3 mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Inativar uma opção impede novos vínculos e preserva os cadastros existentes.</p></div>
      <div class="card-footer d-flex justify-content-end gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1"></i>Salvar opção</button><?php if (!empty($item['id'])): ?><a class="btn btn-light" href="?tipo=<?= ge($table) ?>">Cancelar edição</a><?php endif ?></div>
    </form>
    <div class="gadsan-table-wrap"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Opção</th><th>Situação</th><th class="text-end">Ação</th></tr></thead><tbody>
      <?php foreach ($catalogs[$table] as $row): ?><tr><td data-label="Opção"><strong><?= ge($row['nome']) ?></strong><?php if (isset($row['uf'])): ?><small class="d-block text-muted"><?= ge($row['uf']) ?></small><?php endif ?><?php if ($table==='lotacoes'): ?><small class="d-block text-muted"><?= ge(str_replace('_',' ',$row['tipo'])) ?></small><?php endif ?></td><td data-label="Situação"><span class="gadsan-status <?= $row['ativo']?'gadsan-status-active':'gadsan-status-inactive' ?>"><?= $row['ativo']?'Ativo':'Inativo' ?></span></td><td data-label="Ação" class="text-end"><a class="btn btn-sm btn-outline-primary" href="?tipo=<?= ge($table) ?>&editar=<?= (int)$row['id'] ?>"><i class="bi bi-pencil me-1"></i>Editar</a></td></tr><?php endforeach ?>
      <?php if (!$catalogs[$table]): ?><tr><td colspan="3" class="gadsan-empty"><i class="bi bi-inbox" aria-hidden="true"></i><strong>Nenhuma opção cadastrada</strong><span class="d-block mt-1">Use o formulário acima para começar.</span></td></tr><?php endif ?>
    </tbody></table></div></div>
  </div>
</div>
