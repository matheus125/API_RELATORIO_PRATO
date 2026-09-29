<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__,2).'/vendor/autoload.php';
foreach(['Workbook','Normalizer','Importer'] as $class)require dirname(__DIR__,2).'/scripts/gadsan/'.$class.'.php';
use Gadsan\Normalizer as N;
use Gadsan\Workbook as W;
use Gadsan\Importer as I;
$count=0;
function check(bool $ok,string $label): void {global $count;if(!$ok)throw new RuntimeException($label);$count++;}
function row(array $values=[],int $line=4): array {return ['aba'=>'Teste','linha'=>$line,'calendar'=>1900,'raw'=>array_replace(array_fill_keys(array_unique(array_values(W::HEADERS)),null),['nome'=>'Pessoa sintética','cpf'=>'529.982.247-25','instituicao'=>'Teste'],$values)];}
check(N::cpfValid('52998224725'),'CPF com verificadores válidos');
foreach(['11111111111','123','52998224724','00000000000'] as $cpf)check(!N::cpfValid($cpf),'CPF inválido');
check(N::date('19/07/2001')==='2001-07-19','Data texto');
check(N::date(36526)==='2000-01-01','Data serial 1900');
check(N::date(35064,1904)==='2000-01-01','Data serial 1904');
foreach(['35/03/1985','29/02/2001','01/01/25',60] as $value){$thrown=false;try{N::date($value);}catch(InvalidArgumentException){$thrown=true;}check($thrown,'Data impossível');}
$r=N::normalize(row(['telefone'=>'98451-8200','email'=>'  TESTE@homail.com ','turno'=>'matutino','carga_horaria_semanal'=>'40 horas','formacao'=>'Serviço Social / Enfermagem']));
check($r['data']['telefone']==='984518200','Não inventar DDD');check($r['data']['email']==='teste@homail.com','Não corrigir domínio');check($r['data']['carga_horaria_semanal']===40,'Carga numérica');check(count($r['data']['formacoes'])===2,'Formação N:N');check($r['data']['turno']==='Matutino','Turno canônico');
$tmp=tempnam(sys_get_temp_dir(),'gadsan-test-');file_put_contents($tmp,'fixture sintética');
$wb=['abas'=>[],'rows'=>[row(),row(['nome'=>'Outro nome'],5),row(['cpf'=>'11111111111'],6),row(['cpf'=>'11111111111'],7),row(['nome'=>null],8)]];
$r=(new I)->run($tmp,$wb);check($r['total_importados']===3,'CPF válido associa, inválido não funde');check($r['total_ignorados']===2,'Nome ausente e existente');check($r['inconsistencias']['cpf_duplicado']===3,'Duplicidade CPF válido/inválido');check($r['inconsistencias']['conflito_cadastro']===1,'Não sobrescrever nome');
$wb['rows']=[row(['matricula'=>'001','data_nascimento'=>'01/01/1980']),row(['matricula'=>'001','cpf'=>null,'data_nascimento'=>'01/01/1980'],5),row(['matricula'=>'001','cpf'=>'11111111111','data_nascimento'=>'01/01/1980'],6)];
$r=(new I)->run($tmp,$wb);check($r['total_importados']===2,'Matrícula exige compatibilidade');check($r['inconsistencias']['identificadores_conflitantes']===1,'CPFs divergentes não fundem');
$b=new PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$b->getActiveSheet();$s->fromArray(['Título'],null,'B1');$headers=['Cargo','Nome','CPF','Contato','Nível','Formação','Data de Nascimento','Data Admissão','E-mail','Endereço de Residência','Turno','Carga Horária/ semana','Matrícula','Tipo Sanguíneo','Instituição'];$s->fromArray($headers,null,'B3');$s->fromArray(['Assessor','Teste','52998224725'],null,'B4');$s->fromArray($headers,null,'B6');$s->fromArray(['Assessor','Teste 2'],null,'B7');$s->getStyle('XFB100')->getFont()->setBold(true);$xlsx=$tmp.'.xlsx';(new PhpOffice\PhpSpreadsheet\Writer\Xlsx($b))->save($xlsx);$parsed=W::read($xlsx);check(count($parsed['rows'])===2,'Ignorar títulos, cabeçalhos, vazios, formatação distante');check($parsed['rows'][1]['linha']===7,'Preservar linha Excel');unlink($xlsx);unlink($tmp);
echo "OK: $count verificações sintéticas.\n";
