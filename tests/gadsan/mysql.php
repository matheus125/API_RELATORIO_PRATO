<?php
// Integration tests against an explicitly isolated MySQL socket, never the portal .env.
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
ini_set('display_errors','0');ini_set('log_errors','0');
require dirname(__DIR__,2).'/vendor/autoload.php';
foreach(['Workbook','Normalizer','Importer'] as $class)require dirname(__DIR__,2).'/scripts/gadsan/'.$class.'.php';
$root=dirname(__DIR__,2);$checks=0;
function verify(bool $v):void {global $checks;if(!$v)throw new RuntimeException('Falha na verificação '.($checks+1));$checks++;}
function source(array $v=[],int $line=4):array{return ['aba'=>'Teste','linha'=>$line,'calendar'=>1900,'raw'=>array_replace(array_fill_keys(array_unique(array_values(Gadsan\Workbook::HEADERS)),null),['nome'=>'Pessoa sintética','cpf'=>'52998224725','instituicao'=>'Teste'],$v)];}
try{
    $db=new PDO('mysql:unix_socket=/tmp/gadsan-mysql-test/mysql.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $database='gadsan_validacao_'.date('Ymd_His');$db->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_ci");$db->exec("USE `$database`");
    $db->exec(file_get_contents($root.'/database/schema.sql'));$db->exec(file_get_contents($root.'/database/seed.sql'));$db->exec(file_get_contents($root.'/database/seed.sql'));
    $f=tempnam('/tmp','gadsan-mysql-fixture-');file_put_contents($f,'primeira carga sintética');
    $wb=['abas'=>[],'rows'=>[source(['matricula'=>'001','formacao'=>'Serviço Social / Enfermagem']),source(['cpf'=>'11111111111','nome'=>'Pessoa B'],5),source(['cpf'=>'11111111111','nome'=>'Pessoa C'],6),source(['nome'=>null],7)]];
    $r=(new Gadsan\Importer($db))->run($f,$wb);verify($r['total_importados']===3);verify((int)$db->query('SELECT COUNT(*) FROM importacao_origens')->fetchColumn()===4);verify((int)$db->query('SELECT COUNT(*) FROM colaborador_formacao')->fetchColumn()===2);
    $again=(new Gadsan\Importer($db))->run($f,$wb);verify($again['status']==='ja_importado');verify((int)$db->query('SELECT COUNT(*) FROM colaboradores')->fetchColumn()===3);
    file_put_contents($f,'segunda carga sintética');$r=(new Gadsan\Importer($db))->run($f,['abas'=>[],'rows'=>[source(['nome'=>'Nome divergente','cargo'=>'Cargo novo'])]]);verify($r['total_importados']===0);verify($db->query('SELECT nome FROM colaboradores WHERE id=1')->fetchColumn()==='Pessoa sintética');verify((int)$db->query('SELECT COUNT(*) FROM alocacoes_colaborador WHERE colaborador_id=1')->fetchColumn()===1);
    $before=(int)$db->query('SELECT COUNT(*) FROM colaboradores')->fetchColumn();file_put_contents($f,'falha sintética');$failed=false;
    try{(new Gadsan\Importer($db))->run($f,['abas'=>[],'rows'=>[source(['cpf'=>null,'nome'=>'Rollback']),source(['cpf'=>null,'nome'=>'Rollback dois','cargo'=>str_repeat('x',300)],5)]]);}catch(Throwable){$failed=true;}
    verify($failed);verify((int)$db->query('SELECT COUNT(*) FROM colaboradores')->fetchColumn()===$before);verify($db->query('SELECT status FROM importacoes ORDER BY id DESC LIMIT 1')->fetchColumn()==='falhou');
    $badFk=false;try{$db->exec('INSERT INTO colaborador_formacao (colaborador_id,formacao_id) VALUES (999999,999999)');}catch(PDOException){$badFk=true;}verify($badFk);
    $badCpf=false;try{$db->exec("INSERT INTO colaboradores (nome,cpf,cpf_validado) VALUES ('Teste','52998224725',1)");}catch(PDOException){$badCpf=true;}verify($badCpf);
    unlink($f);
    if(isset($argv[1])){
        $database.='_excel';$db->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_ci");$db->exec("USE `$database`");$db->exec(file_get_contents($root.'/database/schema.sql'));$db->exec(file_get_contents($root.'/database/seed.sql'));
        $file=$argv[1];$sha=hash_file('sha256',$file);$wb=Gadsan\Workbook::read($file);$r=(new Gadsan\Importer($db))->run($file,$wb);verify(hash_file('sha256',$file)===$sha);verify($r['total_linhas']===$r['total_importados']+$r['total_atualizados']+$r['total_ignorados']);verify((int)$db->query('SELECT COUNT(*) FROM importacao_origens')->fetchColumn()===$r['total_linhas']);verify((int)$db->query('SELECT COUNT(*) FROM importacao_inconsistencias')->fetchColumn()===$r['total_inconsistencias']);
        $again=(new Gadsan\Importer($db))->run($file,$wb);verify($again['status']==='ja_importado');
        $r['banco_validacao']=$database;$r['tabelas']=[];foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table)$r['tabelas'][$table]=(int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
        file_put_contents($root.'/docs/analise-gadsan.json',json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");echo json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
    }
    echo "OK: $checks verificações MySQL.\n";
}catch(Throwable $e){fwrite(STDERR,'Teste MySQL falhou: '.get_class($e).". Dados e detalhes SQL omitidos.\n");exit(1);}
