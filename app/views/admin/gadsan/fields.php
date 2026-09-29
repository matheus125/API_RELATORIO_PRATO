<?php
function gadsanInput(string $name,string $label,mixed $value=null,string $type='text',string $extra=''): void {
    $id='f_'.preg_replace('/[^a-zA-Z0-9_]/','_',$name);
    echo '<div class="col-12 col-md-6 col-xl-4"><label class="form-label" for="'.ge($id).'">'.ge($label).'</label><input class="form-control" id="'.ge($id).'" name="'.ge($name).'" type="'.ge($type).'" value="'.ge($value).'" '.$extra.'></div>';
}
function gadsanSelect(string $name,string $label,array $options,mixed $selected=null,?string $catalog=null): void {
    $id='f_'.preg_replace('/[^a-zA-Z0-9_]/','_',$name);
    echo '<div class="col-12 col-md-6 col-xl-4"><label class="form-label" for="'.ge($id).'">'.ge($label).'</label><select class="form-select" name="'.ge($name).'" id="'.ge($id).'"'.($catalog?' data-catalog="'.ge($catalog).'"':'').'>';
    if (!preg_match('/(?:ativo|situacao|tipo)\]?$/',$name)) echo '<option value="">Não informado</option>';
    foreach ($options as $option) {
        $match=(string)$selected===(string)$option['id'];
        if (isset($option['ativo'])&&!$option['ativo']&&!$match) continue;
        echo '<option value="'.ge($option['id']).'"'.($match?' selected':'').'>'.ge($option['nome']).(isset($option['uf'])?' / '.ge($option['uf']):'').(isset($option['ativo'])&&!$option['ativo']?' (inativo)':'').'</option>';
    }
    echo '</select></div>';
}
function gadsanAllocation(array $row,string $key,array $catalogs): void {
    $base='alocacoes['.$key.']';
    echo '<fieldset class="gadsan-repeat" data-row><legend>Alocação profissional</legend><input type="hidden" name="'.ge($base).'[id]" value="'.ge($row['id']??'').'"><div class="row g-3">';
    $labels=['cargo_id'=>'Cargo','instituicao_id'=>'Instituição','projeto_id'=>'Projeto','lotacao_id'=>'Lotação','vinculo_id'=>'Vínculo','natureza_contratacao_id'=>'Natureza de contratação','turno_id'=>'Turno'];
    foreach (GadsanService::ALLOCATION as $field=>$table) gadsanSelect($base.'['.$field.']',$labels[$field],$catalogs[$table],$row[$field]??null,$table);
    gadsanInput($base.'[matricula]','Matrícula',$row['matricula']??null,'text','maxlength="100" spellcheck="false" placeholder="Informe como consta no registro"');
    gadsanInput($base.'[carga_horaria_semanal]','Horas por semana',$row['carga_horaria_semanal']??null,'number','min="1" max="168" step="1"');
    foreach (['data_admissao'=>'Data de admissão','data_inicio'=>'Início nesta alocação','data_fim'=>'Fim nesta alocação'] as $field=>$label) gadsanInput($base.'['.$field.']',$label,$row[$field]??null,'date');
    gadsanSelect($base.'[ativo]','Situação da alocação',[['id'=>'1','nome'=>'Ativa'],['id'=>'0','nome'=>'Inativa']],$row['ativo']??'1');
    echo '</div><div class="mt-3">';
    if (empty($row['id'])) echo '<button type="button" class="btn btn-sm btn-outline-danger" data-remove>Remover esta alocação</button>';
    else echo '<small class="text-muted">Alocação #'.(int)$row['id'].'. Para encerrar, informe o fim e marque como inativa.</small>';
    echo '</div></fieldset>';
}
function gadsanEducation(array $row,string $key,array $catalogs): void {
    echo '<fieldset class="gadsan-repeat" data-row><legend>Nível de escolaridade</legend><div class="row g-3">';
    gadsanSelect('escolaridades['.$key.'][escolaridade_id]','Escolaridade',$catalogs['escolaridades'],$row['escolaridade_id']??null,'escolaridades');
    $options=array_map(fn($v)=>['id'=>$v,'nome'=>$v==='Nao informado'?'Não informada':$v],GadsanService::SITUATIONS);
    gadsanSelect('escolaridades['.$key.'][situacao]','Situação',$options,$row['situacao']??'Nao informado');
    echo '</div><button type="button" class="btn btn-sm btn-outline-danger mt-3" data-remove>Remover escolaridade</button></fieldset>';
}
