<?php
require_once __DIR__.'/fields.php';
$editing=!empty($person['id']);
$allocations=is_array($person['alocacoes']??null)?array_filter($person['alocacoes'],'is_array'):[[]];
$levels=is_array($person['escolaridades']??null)?array_filter($person['escolaridades'],'is_array'):[[]];
$selectedFormations=is_array($person['formacoes']??null)?$person['formacoes']:[];
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"><div><h2 class="h4 mb-1"><?= $editing?'Cadastro do colaborador':'Novo colaborador' ?></h2><p class="text-muted mb-0">Somente o nome é obrigatório. Complete os demais dados quando estiverem disponíveis.</p></div><a href="/admin/gadsan" class="btn btn-light">Voltar à lista</a></div>
<?php if ($error): ?><div class="alert alert-danger" role="alert" tabindex="-1" data-form-error><?= ge($error) ?></div><?php endif ?>
<?php if ($editing && !empty($person['cpf']) && empty($person['cpf_validado'])): ?><div class="alert alert-warning">O CPF deste cadastro precisa de revisão. O valor importado foi preservado.</div><?php endif ?>
<form method="post" action="/admin/gadsan/colaboradores/<?= $editing?(int)$person['id']:'novo' ?>" data-gadsan-form autocomplete="off">
<input type="hidden" name="_csrf" value="<?= ge($csrf) ?>"><input type="hidden" name="version" value="<?= ge($person['version']??'') ?>">
<fieldset <?= !$canEdit?'disabled':'' ?> class="gadsan-form-body">
<section class="card mb-4"><div class="card-header"><h3 class="h5 mb-0">1. Dados pessoais</h3></div><div class="card-body"><div class="row g-3">
<?php
gadsanInput('nome','Nome completo *',$person['nome']??null,'text','required maxlength="255" autocomplete="name"');
gadsanInput('cpf','CPF',$person['cpf']??null,'text','maxlength="32" inputmode="numeric" data-gadsan-mask="cpf" placeholder="000.000.000-00" aria-describedby="gadsan-documentos-ajuda"');
gadsanInput('rg','RG',$person['rg']??null,'text','maxlength="100" autocapitalize="characters" spellcheck="false" aria-describedby="gadsan-documentos-ajuda"');
gadsanInput('data_nascimento','Data de nascimento',$person['data_nascimento']??null,'date');
gadsanInput('email','E-mail',$person['email']??null,'text','maxlength="320" inputmode="email" autocomplete="email" autocapitalize="none" spellcheck="false" data-gadsan-email placeholder="nome@exemplo.com"');
gadsanInput('telefone','Telefone',$person['telefone']??null,'tel','maxlength="64" inputmode="tel" autocomplete="tel" data-gadsan-mask="phone" placeholder="(92) 99999-9999" aria-describedby="gadsan-contato-ajuda"');
gadsanSelect('tipo_sanguineo','Tipo sanguíneo',array_map(fn($v)=>['id'=>$v,'nome'=>$v],['A+','A-','B+','B-','AB+','AB-','O+','O-']),$person['tipo_sanguineo']??null);
gadsanSelect('ativo','Situação do cadastro',[['id'=>'1','nome'=>'Ativo'],['id'=>'0','nome'=>'Inativo']],$person['ativo']??'1');
?>
<div class="col-12"><p class="form-text mb-1" id="gadsan-contato-ajuda">Telefone: informe o DDD quando conhecido. Números locais e com +55 também são aceitos.</p><p class="form-text mb-0" id="gadsan-documentos-ajuda">O CPF recebe pontuação automática. No RG, mantenha letras e números conforme o documento.</p></div>
<div class="col-12"><label for="endereco" class="form-label">Endereço de residência</label><textarea class="form-control" id="endereco" name="endereco" rows="2" maxlength="65000"><?= ge($person['endereco']??'') ?></textarea></div>
</div></div></section>
<section class="card mb-4"><div class="card-header"><h3 class="h5 mb-0">2. Alocações profissionais</h3></div><div class="card-body">
<p class="text-muted">Adicione uma alocação para cada combinação de cargo, instituição, projeto e lotação. Informe apenas os períodos confirmados.</p>
<?php if ($canAux): ?><div class="gadsan-options-help mb-3"><a href="/admin/gadsan/auxiliares" target="_blank" rel="noopener">Cadastrar cargo, projeto ou outra opção <i class="bi bi-box-arrow-up-right"></i></a><button type="button" class="btn btn-sm btn-outline-secondary" data-refresh-options>Atualizar opções</button><span role="status" data-options-status></span></div><?php endif ?>
<div data-repeat="allocations"><?php foreach (array_values($allocations) as $i=>$a) gadsanAllocation($a,(string)$i,$catalogs); ?></div>
<button type="button" class="btn btn-outline-primary" data-add="allocations">+ Adicionar alocação</button>
</div></section>
<section class="card mb-4"><div class="card-header"><h3 class="h5 mb-0">3. Escolaridade e formação</h3></div><div class="card-body">
<p class="text-muted">Escolaridade é o nível de ensino. Formação identifica os cursos realizados.</p>
<div data-repeat="education"><?php foreach (array_values($levels) as $i=>$level) gadsanEducation($level,(string)$i,$catalogs); ?></div>
<button type="button" class="btn btn-outline-primary mb-4" data-add="education">+ Adicionar escolaridade</button>
<fieldset><legend class="h6">Formações acadêmicas</legend><div class="gadsan-checks" data-formations>
<?php foreach ($catalogs['formacoes'] as $f): if (!$f['ativo']&&!in_array($f['id'],$selectedFormations)) continue; ?>
<label class="form-check"><input class="form-check-input" type="checkbox" name="formacoes[]" value="<?= (int)$f['id'] ?>" <?= in_array($f['id'],$selectedFormations)?'checked':'' ?>><span class="form-check-label"><?= ge($f['nome']) ?><?= !$f['ativo']?' (inativa)':'' ?></span></label>
<?php endforeach ?>
<?php if (!$catalogs['formacoes']): ?><p class="text-muted">Ainda não há formações. Use os cadastros auxiliares para adicionar cursos e clique em “Atualizar opções”.</p><?php endif ?>
</div></fieldset>
</div></section>
<?php if ($canEdit): ?><div class="gadsan-savebar"><span class="text-muted">Confira as informações antes de salvar.</span><div class="d-flex gap-2"><a href="/admin/gadsan" class="btn btn-light">Cancelar</a><button class="btn btn-primary" type="submit">Salvar colaborador</button></div></div><?php endif ?>
<input type="hidden" name="_complete" value="1"></fieldset></form>
<template id="gadsan-allocations"><?php gadsanAllocation([],'__INDEX__',$catalogs); ?></template>
<template id="gadsan-education"><?php gadsanEducation([],'__INDEX__',$catalogs); ?></template>
