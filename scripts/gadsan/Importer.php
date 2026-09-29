<?php
declare(strict_types=1);
namespace Gadsan;
use PDO;

final class Importer
{
    public const VERSION='1.0.0';
    private array $people=[];
    private array $assignments=[];
    private array $cpfSeen=[];
    private array $matSeen=[];
    private array $lookups=[];
    private int $next=1;
    private int $importId=0;
    private array $report=[];
    private const PERSONAL=['nome','cpf','rg','data_nascimento','email','telefone','endereco','tipo_sanguineo'];
    private const AUX=['cargo'=>'cargos','instituicao'=>'instituicoes','projeto'=>'projetos','lotacao'=>'lotacoes','vinculo'=>'vinculos','turno'=>'turnos','natureza_contratacao'=>'naturezas_contratacao'];
    public function __construct(private ?PDO $db=null){}
    private function query(string $sql,array $args=[]): \PDOStatement { $q=$this->db->prepare($sql);$q->execute($args);return $q; }
    private function insert(string $table,array $data): int
    {
        $keys=array_keys($data);$this->query('INSERT INTO '.$table.' (`'.implode('`,`',$keys).'`) VALUES ('.implode(',',array_fill(0,count($keys),'?')).')',array_values($data));return (int)$this->db->lastInsertId();
    }
    private function issue(array &$row,string $field,string $type,string $description): void
    {
        $row['issues'][]=['campo'=>$field,'valor_original'=>$row['raw'][$field]??null,'tipo_inconsistencia'=>$type,'descricao'=>$description];
    }
    private function aux(string $table,?string $name): ?int
    {
        if($name===null)return null;
        $key=mb_strtolower($name);
        if(isset($this->lookups[$table][$key]))return $this->lookups[$table][$key];
        if(!$this->db)return $this->lookups[$table][$key]=count($this->lookups[$table]??[])+1;
        $id=$this->query('SELECT id FROM '.$table.' WHERE nome=?',[$name])->fetchColumn();
        return $this->lookups[$table][$key]=$id?(int)$id:$this->insert($table,['nome'=>$name]);
    }
    private function loadExisting(): void
    {
        if(!$this->db)return;
        foreach($this->query('SELECT * FROM colaboradores')->fetchAll(PDO::FETCH_ASSOC) as $p){$id=(int)$p['id'];$this->people[$id]=$p;$this->next=max($this->next,$id+1);if($p['cpf'])$this->cpfSeen[$p['cpf']][]=$id;}
        foreach($this->query('SELECT * FROM alocacoes_colaborador')->fetchAll(PDO::FETCH_ASSOC) as $a){$this->assignments[(int)$a['colaborador_id']][]=$a;if($a['matricula'])$this->matSeen[$a['matricula']][]=['id'=>(int)$a['colaborador_id'],'instituicao_id'=>$a['instituicao_id']];}
    }
    public function run(string $file,array $workbook): array
    {
        $hash=hash_file('sha256',$file);$name=basename($file);$locked=false;
        $this->report=['modo'=>$this->db?'importacao':'simulacao_banco_vazio','arquivo'=>$name,'sha256'=>$hash,'abas'=>$workbook['abas'],'total_linhas'=>count($workbook['rows']),'total_importados'=>0,'total_atualizados'=>0,'total_ignorados'=>0,'total_inconsistencias'=>0,'inconsistencias'=>[],'inconsistencias_por_aba'=>[],'campos_ausentes'=>[]];
        try {
            if($this->db){
                $version=(string)$this->query('SELECT VERSION()')->fetchColumn();if(str_contains($version,'MariaDB')||version_compare($version,'8.0.16','<'))throw new \RuntimeException('Requer MySQL 8.0.16 ou superior.');
                $lock='gadsan:'.substr(hash('sha256',(string)$this->query('SELECT DATABASE()')->fetchColumn()),0,50);
                if((int)$this->query('SELECT GET_LOCK(?, 0)',[$lock])->fetchColumn()!==1)throw new \RuntimeException('Outra importação está em execução.');$locked=true;
                $previous=$this->query('SELECT * FROM importacoes WHERE sha256=? AND versao_importador=?',[$hash,self::VERSION])->fetch(PDO::FETCH_ASSOC);
                if($previous&&$previous['status']==='concluida'){return ['modo'=>'importacao','status'=>'ja_importado','importacao_id'=>(int)$previous['id'],'novos_importados'=>0];}
                if($previous){$this->importId=(int)$previous['id'];$this->query("UPDATE importacoes SET status='em_andamento', data_importacao=CURRENT_TIMESTAMP WHERE id=?",[$this->importId]);}
                else $this->importId=$this->insert('importacoes',['nome_arquivo'=>$name,'sha256'=>$hash,'versao_importador'=>self::VERSION,'total_linhas'=>count($workbook['rows'])]);
                $this->db->beginTransaction();$this->loadExisting();
            }
            foreach($workbook['rows'] as $source){
                $beforeImported=$this->report['total_importados'];
                $row=Normalizer::normalize($source);$d=$row['data'];
                foreach($row['raw'] as $field=>$value)if(Normalizer::text($value)===null)$this->report['campos_ausentes'][$field]=($this->report['campos_ausentes'][$field]??0)+1;$id=null;$allocationId=null;$result='ignorado';
                if($d['cpf']!==null && isset($this->cpfSeen[$d['cpf']]))$this->issue($row,'cpf','cpf_duplicado','CPF já observado; somente CPF válido permite associação automática.');
                if($d['matricula']!==null&&isset($this->matSeen[$d['matricula']]))$this->issue($row,'matricula','matricula_duplicada','Matrícula repetida; instituição e dados pessoais devem ser compatíveis.');
                $institution=$this->aux('instituicoes',$d['instituicao']);
                if($d['cpf']!==null&&Normalizer::cpfValid($d['cpf'])){
                    $ids=array_values(array_unique($this->cpfSeen[$d['cpf']]??[]));
                    $ids=array_values(array_filter($ids,fn($i)=>!empty($this->people[$i]['cpf_validado'])));
                    if(count($ids)===1)$id=$ids[0];
                }
                if($id===null&&$institution!==null&&$d['matricula']!==null){
                    $candidates=[];$checked=[];
                    foreach($this->matSeen[$d['matricula']]??[] as $match){if(isset($checked[$match['id']]))continue;$checked[$match['id']]=true;$p=$this->people[$match['id']];if($match['instituicao_id']!=$institution)continue;
                        if($p['cpf']&&$d['cpf']&&$p['cpf']!==$d['cpf']){$this->issue($row,'matricula','identificadores_conflitantes','Matrícula coincide, mas CPFs divergem; pessoas mantidas separadas.');continue;}
                        if(Workbook::key($p['nome'])===Workbook::key($d['nome'])&&$p['data_nascimento']!==null&&$p['data_nascimento']===$d['data_nascimento']&&(!$d['cpf']||$p['cpf']===$d['cpf']))$candidates[]=$match['id'];
                    }
                    $candidates=array_values(array_unique($candidates));if(count($candidates)===1)$id=$candidates[0];
                }
                foreach($this->people as $pid=>$p)if($pid!==$id&&$d['nome']!==null&&Workbook::key($p['nome'])===Workbook::key($d['nome'])){$this->issue($row,'nome','colaborador_possivelmente_duplicado','Nome coincide com outro registro; nenhuma fusão por nome foi realizada.');break;}
                // Oversized identifiers are preserved in original JSON, never silently truncated.
                foreach(['nome'=>255,'cpf'=>32,'rg'=>100,'email'=>320,'telefone'=>64,'matricula'=>100] as $field=>$limit)if($d[$field]!==null&&mb_strlen($d[$field])>$limit){$this->issue($row,$field,'limite_campo_excedido','Valor excede o limite do campo; preservado somente na origem.');$d[$field]=null;}
                if($d['nome']!==null){
                    $conflict=false;
                    if($id!==null){
                        foreach(self::PERSONAL as $field){$old=$this->people[$id][$field]??null;$new=$d[$field];if($new!==null&&$old!==$new){$conflict=true;$this->issue($row,$field,'conflito_cadastro','Valor recebido difere do cadastro; nenhuma sobrescrita nem preenchimento automático.');}}
                        $result='existente';
                    }else{
                        $p=array_intersect_key($d,array_flip(self::PERSONAL));$p['cpf_validado']=$d['cpf']!==null&&Normalizer::cpfValid($d['cpf'])?1:0;
                        $id=$this->db?$this->insert('colaboradores',$p):$this->next++;$this->people[$id]=$p;$this->report['total_importados']++;$result='importado';
                    }
                    $a=['colaborador_id'=>$id];foreach(self::AUX as $field=>$table)$a[$field.'_id']=$this->aux($table,$d[$field]??null);
                    foreach(['matricula','carga_horaria_semanal','data_admissao'] as $field)$a[$field]=$d[$field];
                    $existing=$this->assignments[$id]??[];$same=null;
                    foreach($existing as $previous){$equal=true;foreach($a as $k=>$v)if(($previous[$k]??null)!=$v){$equal=false;break;}if($equal){$same=$previous;break;}}
                    if($same)$allocationId=$same['id'];
                    elseif($existing){$conflict=true;$this->issue($row,'alocacao','alocacao_divergente','Alocação difere da cadastrada; origem preservada para confirmar mudança ou simultaneidade.');}
                    elseif(!$conflict){$allocationId=$this->db?$this->insert('alocacoes_colaborador',$a):$id;$this->assignments[$id][]= $a+['id'=>$allocationId];}
                    if($result==='importado'){
                        foreach($d['formacoes'] as $f){$fid=$this->aux('formacoes',$f);if($this->db)$this->query('INSERT IGNORE INTO colaborador_formacao (colaborador_id,formacao_id) VALUES (?,?)',[$id,$fid]);}
                        if($d['escolaridade']!==null){$eid=$this->aux('escolaridades',$d['escolaridade']);if($this->db)$this->insert('colaborador_escolaridade',['colaborador_id'=>$id,'escolaridade_id'=>$eid,'situacao'=>$d['situacao_escolaridade']??'Nao informado']);}
                    }else{
                        if($d['formacoes']||$d['escolaridade'])$this->issue($row,'formacao','educacao_existente_revisar','Registro existente: informação educacional preservada na origem para conferência, sem alterar cadastro.');
                        $this->report['total_ignorados']++;
                    }
                    if($conflict)$result='pendente_revisao';
                    if($d['cpf'])$this->cpfSeen[$d['cpf']][]=$id;
                    if($d['matricula'])$this->matSeen[$d['matricula']][]=['id'=>$id,'instituicao_id'=>$institution];
                }else $this->report['total_ignorados']++;
                $sheetResult=$this->report['total_importados']>$beforeImported?'importados':'ignorados';
                if(isset($this->report['abas'][$row['aba']]))$this->report['abas'][$row['aba']][$sheetResult]=($this->report['abas'][$row['aba']][$sheetResult]??0)+1;
                $originId=null;
                if($this->db)$originId=$this->insert('importacao_origens',['importacao_id'=>$this->importId,'colaborador_id'=>$id,'alocacao_id'=>$allocationId,'arquivo'=>$name,'aba'=>$row['aba'],'linha'=>$row['linha'],'dados_originais'=>json_encode($row['raw'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'resultado'=>$result]);
                foreach($row['issues'] as $issue){$type=$issue['tipo_inconsistencia'];$this->report['inconsistencias_por_aba'][$row['aba']][$type]=($this->report['inconsistencias_por_aba'][$row['aba']][$type]??0)+1;$this->report['inconsistencias'][$type]=($this->report['inconsistencias'][$type]??0)+1;$this->report['total_inconsistencias']++;if($this->db){$issue['valor_original']=Normalizer::text($issue['valor_original']);$this->insert('importacao_inconsistencias',['importacao_id'=>$this->importId,'origem_id'=>$originId,'arquivo'=>$name,'aba'=>$row['aba'],'linha'=>$row['linha']]+$issue);}}
            }
            if(hash_file('sha256',$file)!==$hash)throw new \RuntimeException('Arquivo mudou durante a importação.');
            ksort($this->report['inconsistencias']);
            if($this->db){$this->query("UPDATE importacoes SET total_importados=?,total_atualizados=?,total_ignorados=?,total_inconsistencias=?,status='concluida' WHERE id=?",[$this->report['total_importados'],0,$this->report['total_ignorados'],$this->report['total_inconsistencias'],$this->importId]);$this->db->commit();$this->report['importacao_id']=$this->importId;}
            return $this->report;
        }catch(\Throwable $e){if($this->db&&$this->db->inTransaction())$this->db->rollBack();if($this->db&&$this->importId)$this->query("UPDATE importacoes SET status='falhou' WHERE id=?",[$this->importId]);throw $e;}
        finally{if($locked)$this->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
}
