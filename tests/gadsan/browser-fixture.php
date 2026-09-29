<?php
// CLI-only fixtures for the isolated browser test server, never a public login route.
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__,2).'/vendor/autoload.php';
require dirname(__DIR__,2).'/app/services/GadsanService.php';
if(($argv[1]??'')==='cleanup') {
$r=json_decode(file_get_contents('/tmp/gadsan-ui-records.json'),true);$s=new GadsanService();$s->db->beginTransaction();
try{
 foreach($r['imports'] as $id){
  $name=$s->query('SELECT nome_arquivo FROM importacoes WHERE id=?',[$id])->fetchColumn();
  if($name!=='gadsan-ui-fixture.xlsx')throw new RuntimeException('Carga não é de teste.');
  foreach($s->query('SELECT colaborador_id FROM importacao_origens WHERE importacao_id=? AND colaborador_id IS NOT NULL',[$id])->fetchAll() as $origin)$r['people'][]=$origin['colaborador_id'];
  $s->query('DELETE FROM importacao_inconsistencias WHERE importacao_id=?',[$id]);$s->query('DELETE FROM importacao_origens WHERE importacao_id=?',[$id]);$s->query('DELETE FROM importacoes WHERE id=?',[$id]);
 }
 foreach(array_unique($r['people']) as $id){
  $name=$s->query('SELECT nome FROM colaboradores WHERE id=?',[$id])->fetchColumn();if($name===false)continue;
  if(!str_starts_with($name,'Validação UI ')&&!str_starts_with($name,'Validação de upload '))throw new RuntimeException('Pessoa não é de teste.');
  foreach(['colaborador_formacao','colaborador_escolaridade','alocacoes_colaborador'] as $table)$s->query("DELETE FROM $table WHERE colaborador_id=?",[$id]);
  $s->query('DELETE FROM colaboradores WHERE id=?',[$id]);
 }
 foreach($r['catalogs'] as $row){if($row['table']!=='cargos')throw new RuntimeException('Catálogo não esperado.');$name=$s->query('SELECT nome FROM cargos WHERE id=?',[$row['id']])->fetchColumn();if($name!==false&&!str_starts_with($name,'Validação UI '))throw new RuntimeException('Cargo não é de teste.');$s->query('DELETE FROM cargos WHERE id=?',[$row['id']]);}
 $s->db->commit();file_put_contents('/tmp/gadsan-ui-records.json',json_encode(['people'=>[],'catalogs'=>[],'imports'=>[]]));echo "Registros sintéticos da interface removidos.\n";
}catch(Throwable $e){$s->db->rollBack();throw $e;}

exit;
}
if(($argv[1]??'')!=='prepare'){fwrite(STDERR,"Uso: php tests/gadsan/browser-fixture.php prepare|cleanup\n");exit(1);}
$dir='/tmp/gadsan-ui-sessions';if(!is_dir($dir))mkdir($dir,0700);
$s=new GadsanService();
$id=$s->query("SELECT id_usuario FROM tb_usuario WHERE perfil='ADMIN' ORDER BY id_usuario LIMIT 1")->fetchColumn();
if(!$id)throw new RuntimeException('Administrador indisponível para validação.');
session_save_path($dir);
$sessions=[];
foreach(['ADMIN','CONSULTA'] as $profile){
 session_id(bin2hex(random_bytes(20)));session_start();
 $_SESSION=['User'=>['id_usuario'=>(int)$id,'perfil'=>$profile,'nome_funcionario'=>'Validação da interface','inadmin'=>1]];
 $sessions[$profile]=session_id();session_write_close();
}
file_put_contents('/tmp/gadsan-ui-sessions.json',json_encode($sessions));chmod('/tmp/gadsan-ui-sessions.json',0600);
echo "Sessões de teste isoladas preparadas.\n";

$b=new PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$b->getActiveSheet();$s->setTitle('GADSAN');
$s->fromArray(['Cargo','Nome','Lotação','Projeto','Instituição','Contato','Nível','Formação','Data de Nascimento','RG','CPF','Data Admissão','E-mail','Endereço de Residência','Turno','Carga Horária/ semana','Matrícula','Tipo Sanguíneo','Vínculo'],null,'B3');
$s->setCellValue('C4','Validação de upload '.bin2hex(random_bytes(8)));$s->setCellValue('L4','11111111111');$s->setCellValue('Q5','40h');
(new PhpOffice\PhpSpreadsheet\Writer\Xlsx($b))->save('/tmp/gadsan-ui-fixture.xlsx');
