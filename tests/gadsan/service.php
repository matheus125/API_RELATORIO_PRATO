<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
ini_set('display_errors','0');ini_set('log_errors','0');
require dirname(__DIR__,2).'/vendor/autoload.php';
require dirname(__DIR__,2).'/app/services/GadsanService.php';
$checks=0;
function verify(bool $ok): void { global $checks; if (!$ok) throw new RuntimeException('Verificação '.($checks+1).' falhou.'); $checks++; }
function rejected(callable $fn): void { $caught=false;try{$fn();}catch(DomainException){$caught=true;}verify($caught); }
$s=new GadsanService();$s->db->beginTransaction();
try {
    $baseline=(int)$s->query('SELECT COUNT(*) FROM colaboradores')->fetchColumn();
    rejected(fn()=>$s->savePerson(['nome'=>'Identificador inválido'],0));
    rejected(fn()=>$s->catalogSave('cargos',['nome'=>'Identificador inválido','id'=>'abc']));
    $tag='Teste GADSAN '.bin2hex(random_bytes(5));
    $ids=[];
    foreach(GadsanService::CATALOGS as $table=>$label){
        $extra=$table==='municipios'?['uf'=>'AM']:[];
        if($table==='lotacoes')$extra=['tipo'=>'unidade'];
        $ids[$table]=$s->catalogSave($table,['nome'=>$tag.' '.$table]+$extra);
    }
    verify(count($ids)===10);
    rejected(fn()=>$s->catalogSave('municipios',['nome'=>$tag.' UF','uf'=>'XX']));
    rejected(fn()=>$s->catalogSave('colaboradores',['nome'=>'Não permitido']));
    $input=['nome'=>$tag,'email'=>'  TESTE@EXAMPLE.COM  ','telefone'=>'98451-8200','data_nascimento'=>'1990-02-01','formacoes'=>[$ids['formacoes']], 'escolaridades'=>[['escolaridade_id'=>$ids['escolaridades'],'situacao'=>'Completo']], 'alocacoes'=>[['cargo_id'=>$ids['cargos'],'projeto_id'=>$ids['projetos'],'carga_horaria_semanal'=>'40','matricula'=>'00042']]];
    $id=$s->savePerson($input);$p=$s->person($id);
    verify($p['email']==='teste@example.com');verify($p['telefone']==='984518200');verify(count($p['alocacoes'])===1);verify($p['alocacoes'][0]['matricula']==='00042');verify(count($p['escolaridades'])===1);verify(count($p['formacoes'])===1);
    $invalid=$input;$invalid['alocacoes'][0]['cargo_id']='999999999';
    rejected(fn()=>$s->savePerson($invalid));verify((int)$s->query('SELECT COUNT(*) FROM colaboradores')->fetchColumn()===$baseline+1);
    $invalid=$input;$invalid['cpf']='11111111111';rejected(fn()=>$s->savePerson($invalid));
    $invalid=$input;$invalid['data_nascimento']='1990-02-31';rejected(fn()=>$s->savePerson($invalid));
    $invalid=$input;$invalid['alocacoes'][0]['carga_horaria_semanal']='40h';rejected(fn()=>$s->savePerson($invalid));
    $invalid=$input;$invalid['alocacoes'][0]+=['data_inicio'=>'2025-01-01','data_fim'=>'2024-12-31'];rejected(fn()=>$s->savePerson($invalid));
    $p['nome']=$tag.' alterado';$s->savePerson($p,$id);
    rejected(fn()=>$s->savePerson($p,$id)); // stale version
    $fresh=$s->person($id);$fresh['alocacoes'][]=['cargo_id'=>$ids['cargos'],'carga_horaria_semanal'=>'20'];$s->savePerson($fresh,$id);
    verify(count($s->person($id)['alocacoes'])===2);
    $foreign=$s->savePerson(['nome'=>$tag.' Outra pessoa']);$malicious=$s->person($foreign);$malicious['alocacoes']=$s->person($id)['alocacoes'];rejected(fn()=>$s->savePerson($malicious,$foreign));
    $s->catalogSave('cargos',['id'=>$ids['cargos'],'nome'=>$tag.' cargos','ativo'=>'0']);
    rejected(fn()=>$s->savePerson($input));
    $fresh=$s->person($id);$s->savePerson($fresh,$id);verify(count($s->person($id)['alocacoes'])===2); // retaining inactive FK is allowed
    $fresh=$s->person($id);$fresh['alocacoes']=[];$s->savePerson($fresh,$id);verify(count($s->person($id)['alocacoes'])===2); // no implicit deletion
    $fresh=$s->person($id);$fresh['ativo']='0';$s->savePerson($fresh,$id);verify((int)$s->person($id)['ativo']===0);
    $cpf='52998224725';if(!$s->query('SELECT id FROM colaboradores WHERE cpf=?',[$cpf])->fetch()){$s->savePerson(['nome'=>$tag.' CPF','cpf'=>$cpf]);rejected(fn()=>$s->savePerson(['nome'=>$tag.' Repetição','cpf'=>$cpf]));}
    $s->db->rollBack();verify((int)$s->query('SELECT COUNT(*) FROM colaboradores')->fetchColumn()===$baseline);
    echo "OK: $checks verificações do cadastro, com rollback dos dados sintéticos.\n";
} catch(Throwable $e) {
    if($s->db->inTransaction())$s->db->rollBack();
    fwrite(STDERR,'Falha no teste de cadastro: '.get_class($e).' após '.$checks.' verificações. Valores pessoais omitidos.'.PHP_EOL);exit(1);
}
