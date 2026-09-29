<?php require_once __DIR__.'/fields.php'; ?>
<div class="row g-4"><aside class="col-12 col-lg-3"><div class="list-group">
<?php foreach (GadsanService::CATALOGS as $key=>$label): ?><a class="list-group-item list-group-item-action d-flex justify-content-between <?= $table===$key?'active':'' ?>" href="/admin/gadsan/auxiliares?tipo=<?= ge($key) ?>"><?= ge($label) ?><span class="badge rounded-pill text-bg-light"><?= count($catalogs[$key]) ?></span></a><?php endforeach ?>
</div></aside><div class="col-12 col-lg-9">
<h2 class="h4 mb-3"><?= ge(GadsanService::CATALOGS[$table]) ?></h2>
<?php if ($error): ?><div class="alert alert-danger" role="alert" data-form-error tabindex="-1"><?= ge($error) ?></div><?php endif ?>
<form method="post" action="/admin/gadsan/auxiliares/<?= ge($table) ?>" class="card mb-4" data-gadsan-form>
<div class="card-header"><h3 class="h5 mb-0"><?= !empty($item['id'])?'Editar opção':'Adicionar opção' ?></h3></div>
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
</div><p class="form-text mt-3 mb-0">Inativar uma opção impede novos vínculos com ela e preserva os cadastros existentes.</p></div>
<div class="card-footer d-flex gap-2"><button class="btn btn-primary">Salvar opção</button><?php if (!empty($item['id'])): ?><a class="btn btn-light" href="?tipo=<?= ge($table) ?>">Cancelar edição</a><?php endif ?></div></form>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nome</th><th>Situação</th><th>Ação</th></tr></thead><tbody>
<?php foreach ($catalogs[$table] as $row): ?><tr><td><?= ge($row['nome']) ?><?= isset($row['uf'])?' / '.ge($row['uf']):'' ?><?php if ($table==='lotacoes'): ?><small class="d-block text-muted"><?= ge(str_replace('_',' ',$row['tipo'])) ?></small><?php endif ?></td><td><?= $row['ativo']?'Ativo':'Inativo' ?></td><td><a class="btn btn-sm btn-outline-primary" href="?tipo=<?= ge($table) ?>&editar=<?= (int)$row['id'] ?>">Editar</a></td></tr><?php endforeach ?>
<?php if (!$catalogs[$table]): ?><tr><td colspan="3" class="text-muted text-center py-5">Nenhuma opção cadastrada. Use o formulário acima para começar.</td></tr><?php endif ?>
</tbody></table></div></div></div></div>
