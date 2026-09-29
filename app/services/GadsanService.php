<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2).'/scripts/gadsan/Normalizer.php';

final class GadsanService
{
    public const CATALOGS = [
        'cargos'=>'Cargos', 'instituicoes'=>'Instituições', 'projetos'=>'Projetos',
        'lotacoes'=>'Lotações', 'municipios'=>'Municípios', 'vinculos'=>'Vínculos',
        'naturezas_contratacao'=>'Naturezas de contratação', 'turnos'=>'Turnos',
        'escolaridades'=>'Escolaridades', 'formacoes'=>'Formações',
    ];
    public const ALLOCATION = [
        'cargo_id'=>'cargos', 'instituicao_id'=>'instituicoes', 'projeto_id'=>'projetos',
        'lotacao_id'=>'lotacoes', 'vinculo_id'=>'vinculos', 'natureza_contratacao_id'=>'naturezas_contratacao', 'turno_id'=>'turnos',
    ];
    public const SITUATIONS = ['Nao informado','Completo','Incompleto','Cursando','Trancado'];
    public const LOCATION_TYPES = ['nao_classificada','unidade','setor','municipio'];
    public PDO $db;

    public function __construct(?PDO $db = null)
    {
        if ($db) { $this->db=$db; return; }
        require_once dirname(__DIR__,2).'/config/env.php';
        portal_load_env();
        $this->db=new PDO('mysql:host='.portal_env('DB_HOST','127.0.0.1').';port='.portal_env('DB_PORT','3306').';dbname='.portal_env('DB_NAME','portal_relatorios').';charset=utf8mb4',portal_env('DB_USER','dev'),portal_env('DB_PASSWORD',''),[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        ]);
        $this->db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_0900_as_ci");
        $this->db->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    }

    public function query(string $sql,array $args=[]): PDOStatement
    {
        $q=$this->db->prepare($sql); $q->execute($args); return $q;
    }

    private function atomic(callable $action): mixed
    {
        $outer=$this->db->inTransaction();
        if ($outer) $this->db->exec('SAVEPOINT gadsan_form'); else $this->db->beginTransaction();
        try {
            $result=$action();
            if ($outer) $this->db->exec('RELEASE SAVEPOINT gadsan_form'); else $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($outer) $this->db->exec('ROLLBACK TO SAVEPOINT gadsan_form'); elseif ($this->db->inTransaction()) $this->db->rollBack();
            if ($e instanceof PDOException && $e->getCode()==='23000') throw new DomainException('Já existe um registro com esse CPF, nome de catálogo ou código. Confira os dados antes de salvar.');
            throw $e;
        }
    }

    private function write(string $table,array $values,?int $id=null): int
    {
        if ($id) {
            $set=implode(',',array_map(fn($k)=>"`$k`=?",array_keys($values)));
            $this->query("UPDATE `$table` SET $set WHERE id=?",[...array_values($values),$id]);
            return $id;
        }
        $columns=implode('`,`',array_keys($values)); $placeholders=implode(',',array_fill(0,count($values),'?'));
        $this->query("INSERT INTO `$table` (`$columns`) VALUES ($placeholders)",array_values($values));
        return (int)$this->db->lastInsertId();
    }

    public static function text(mixed $value,int $limit,string $label): ?string
    {
        if (!is_scalar($value) && $value!==null) throw new DomainException("Valor inválido em $label.");
        $value=trim((string)$value);
        if (mb_strlen($value)>$limit) throw new DomainException("$label excede o limite de $limit caracteres.");
        return $value===''?null:$value;
    }

    private function date(mixed $value,string $label): ?string
    {
        $value=self::text($value,10,$label);
        if (!$value) return null;
        if (!preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D',$value)) throw new DomainException("Informe uma data válida em $label.");
        try { return \Gadsan\Normalizer::date($value); }
        catch (InvalidArgumentException) { throw new DomainException("Informe uma data válida em $label."); }
    }

    private function reference(string $table,mixed $id,?int $previous=null): ?int
    {
        if ($id===null || $id==='') return null;
        if (!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1) throw new DomainException('Seleção inválida de cadastro auxiliar.');
        $row=$this->query("SELECT id,ativo FROM `$table` WHERE id=?",[(int)$id])->fetch();
        if (!$row || (!$row['ativo'] && (int)$id!==$previous)) throw new DomainException('Um cadastro auxiliar não existe ou está inativo. Atualize as opções.');
        return (int)$id;
    }

    public function catalogs(bool $all=false): array
    {
        $result=[];
        foreach (self::CATALOGS as $table=>$label) $result[$table]=$this->query("SELECT * FROM `$table`".($all?'':' WHERE ativo=1').' ORDER BY nome,id')->fetchAll();
        return $result;
    }

    public function catalogSave(string $table,array $input): int
    {
        if (!isset(self::CATALOGS[$table])) throw new DomainException('Catálogo não reconhecido.');
        return $this->atomic(function() use($table,$input) {
            $id=filter_var($input['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null;
            if (($input['id']??'')!=='' && ($input['id']??null)!==null && $id===null) throw new DomainException('Identificador de cadastro auxiliar inválido.');
            if ($id && !$this->query("SELECT id FROM `$table` WHERE id=? FOR UPDATE",[$id])->fetch()) throw new DomainException('Cadastro não encontrado.');
            $name=self::text($input['nome']??null,$table==='municipios'?120:255,'Nome');
            if (!$name) throw new DomainException('Informe o nome do cadastro auxiliar.');
            $values=['nome'=>preg_replace('/\s+/u',' ',$name),'ativo'=>($input['ativo']??'1')==='1'?1:0];
            if ($table==='municipios') {
                $uf=strtoupper(self::text($input['uf']??null,2,'UF')??'');
                if (!in_array($uf,explode(' ','AC AL AP AM BA CE DF ES GO MA MT MS MG PA PB PR PE PI RJ RN RS RO RR SC SP SE TO'),true)) throw new DomainException('Selecione uma UF válida.');
                $ibge=self::text($input['codigo_ibge']??null,7,'Código IBGE');
                if ($ibge && !preg_match('/^\d{7}$/D',$ibge)) throw new DomainException('O código IBGE deve ter sete dígitos.');
                $values+=['uf'=>$uf,'codigo_ibge'=>$ibge];
            }
            if ($table==='lotacoes') {
                $type=$input['tipo']??'nao_classificada';
                if (!in_array($type,self::LOCATION_TYPES,true)) throw new DomainException('Tipo de lotação inválido.');
                $old=$id?$this->query('SELECT municipio_id FROM lotacoes WHERE id=?',[$id])->fetchColumn():null;
                $values+=['tipo'=>$type,'municipio_id'=>$this->reference('municipios',$input['municipio_id']??null,$old?(int)$old:null)];
            }
            return $this->write($table,$values,$id);
        });
    }

    public function person(int $id,bool $lock=false): array
    {
        $p=$this->query('SELECT id,nome,cpf,cpf_validado,rg,data_nascimento,email,telefone,endereco,tipo_sanguineo,ativo,updated_at FROM colaboradores WHERE id=?'.($lock?' FOR UPDATE':''),[$id])->fetch();
        if (!$p) throw new DomainException('Colaborador não encontrado.');
        $p['alocacoes']=$this->query('SELECT * FROM alocacoes_colaborador WHERE colaborador_id=? ORDER BY id'.($lock?' FOR UPDATE':''),[$id])->fetchAll();
        $p['escolaridades']=$this->query('SELECT escolaridade_id,situacao FROM colaborador_escolaridade WHERE colaborador_id=? ORDER BY escolaridade_id,situacao',[$id])->fetchAll();
        $p['formacoes']=array_column($this->query('SELECT formacao_id FROM colaborador_formacao WHERE colaborador_id=? ORDER BY formacao_id',[$id])->fetchAll(),'formacao_id');
        $p['version']=hash('sha256',json_encode($p));
        return $p;
    }

    public function savePerson(array $input,?int $id=null): int
    {
        if ($id!==null && $id<1) throw new DomainException('Identificador de colaborador inválido.');
        return $this->atomic(function() use($input,$id) {
            $old=$id?$this->person($id,true):null;
            if ($old && !hash_equals($old['version'],(string)($input['version']??''))) throw new DomainException('Este cadastro foi alterado em outra tela. Recarregue antes de editar novamente.');
            $p=[];
            foreach (['nome'=>255,'cpf'=>32,'rg'=>100,'email'=>320,'telefone'=>64,'endereco'=>65000,'tipo_sanguineo'=>3] as $key=>$limit) $p[$key]=self::text($input[$key]??null,$limit,$key);
            if (!$p['nome']) throw new DomainException('Informe o nome completo.');
            $p['cpf']=$p['cpf']===null?null:preg_replace('/\D/','',$p['cpf']);
            $p['cpf_validado']=$p['cpf']!==null&&\Gadsan\Normalizer::cpfValid($p['cpf'])?1:0;
            if ($p['cpf']!==null && !$p['cpf_validado'] && (!$old || $p['cpf']!==$old['cpf'])) throw new DomainException('CPF inválido. Corrija o documento ou deixe o campo vazio para preencher depois.');
            if ($p['cpf']!==null && ($p['cpf_validado'] || !$old || $p['cpf']!==$old['cpf']) && $this->query('SELECT id FROM colaboradores WHERE cpf=? AND id<>? LIMIT 1',[$p['cpf'],$id??0])->fetch()) throw new DomainException('Este CPF já está cadastrado. Localize o colaborador existente.');
            if ($p['email']) { $p['email']=mb_strtolower($p['email']); if (!filter_var($p['email'],FILTER_VALIDATE_EMAIL) && (!$old||$p['email']!==$old['email'])) throw new DomainException('Informe um e-mail válido.'); }
            if ($p['telefone']) {
                $p['telefone']=preg_replace('/\D/','',$p['telefone']); $n=strlen($p['telefone']);
                if ((!in_array($n,[8,9,10,11,12,13],true)||($n>11&&!str_starts_with($p['telefone'],'55'))) && (!$old||$p['telefone']!==$old['telefone'])) throw new DomainException('Informe um telefone válido; o DDD é opcional se ainda não for conhecido.');
            }
            if ($p['tipo_sanguineo'] && !in_array($p['tipo_sanguineo'],['A+','A-','B+','B-','AB+','AB-','O+','O-'],true)) throw new DomainException('Tipo sanguíneo inválido.');
            $p['data_nascimento']=$this->date($input['data_nascimento']??null,'nascimento');
            if ($p['data_nascimento']>date('Y-m-d') && (!$old||$p['data_nascimento']!==$old['data_nascimento'])) throw new DomainException('O nascimento não pode estar no futuro.');
            $p['ativo']=($input['ativo']??'1')==='1'?1:0;
            $personId=$this->write('colaboradores',$p,$id);
            $allocations=$input['alocacoes']??[]; $levels=$input['escolaridades']??[]; $formations=$input['formacoes']??[];
            foreach ([$allocations,$levels,$formations] as $rows) if (!is_array($rows)||count($rows)>50) throw new DomainException('São permitidos até 50 itens por seção.');
            $oldAlloc=array_column($old['alocacoes']??[],null,'id'); $used=[];
            foreach ($allocations as $row) {
                if (!is_array($row)) throw new DomainException('Alocação inválida.');
                $aid=filter_var($row['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:null;
                if ($aid && (!isset($oldAlloc[$aid])||isset($used[$aid]))) throw new DomainException('Alocação não pertence a este colaborador ou foi repetida.');
                if ($aid) $used[$aid]=true;
                $nonempty=array_filter($row,fn($v,$k)=>!in_array($k,['id','ativo'],true)&&$v!==''&&$v!==null,ARRAY_FILTER_USE_BOTH);
                if (!$aid && !$nonempty) continue;
                $a=['colaborador_id'=>$personId];
                foreach (self::ALLOCATION as $field=>$table) $a[$field]=$this->reference($table,$row[$field]??null,isset($oldAlloc[$aid][$field])?(int)$oldAlloc[$aid][$field]:null);
                $a['matricula']=self::text($row['matricula']??null,100,'Matrícula');
                $hours=$row['carga_horaria_semanal']??'';
                if ($hours!=='' && filter_var($hours,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>168]])===false) throw new DomainException('Carga semanal deve ser um inteiro entre 1 e 168.');
                $a['carga_horaria_semanal']=$hours===''?null:(int)$hours;
                foreach (['data_admissao','data_inicio','data_fim'] as $field) $a[$field]=$this->date($row[$field]??null,$field);
                if ($a['data_inicio']&&$a['data_fim']&&$a['data_fim']<$a['data_inicio']) throw new DomainException('O fim da alocação não pode ser anterior ao início.');
                if ($p['data_nascimento']&&$a['data_admissao']&&$a['data_admissao']<$p['data_nascimento'] && (!$old || $p['data_nascimento']!==$old['data_nascimento'] || !$aid || $a['data_admissao']!==$oldAlloc[$aid]['data_admissao'])) throw new DomainException('A admissão não pode ser anterior ao nascimento.');
                $a['ativo']=($row['ativo']??'1')==='1'?1:0;
                $this->write('alocacoes_colaborador',$a,$aid);
            }
            // Omitted existing allocations are preserved, never deleted with their provenance.
            $this->query('DELETE FROM colaborador_escolaridade WHERE colaborador_id=?',[$personId]);
            $seen=[];
            foreach ($levels as $row) {
                if (!is_array($row)) throw new DomainException('Escolaridade inválida.');
                $eid=$this->reference('escolaridades',$row['escolaridade_id']??null,in_array($row['escolaridade_id']??null,array_column($old['escolaridades']??[],'escolaridade_id'))?(int)$row['escolaridade_id']:null);
                if (!$eid) continue;
                $s=$row['situacao']??'Nao informado';
                if (!in_array($s,self::SITUATIONS,true)) throw new DomainException('Situação escolar inválida.');
                if (isset($seen[$eid.'|'.$s])) continue; $seen[$eid.'|'.$s]=true;
                $this->write('colaborador_escolaridade',['colaborador_id'=>$personId,'escolaridade_id'=>$eid,'situacao'=>$s]);
            }
            $this->query('DELETE FROM colaborador_formacao WHERE colaborador_id=?',[$personId]);
            foreach ($formations as $fid) if (!is_scalar($fid)) throw new DomainException('Formação inválida.');
            foreach (array_unique($formations) as $fid) {
                $fid=$this->reference('formacoes',$fid,in_array($fid,$old['formacoes']??[])?(int)$fid:null);
                if ($fid) $this->write('colaborador_formacao',['colaborador_id'=>$personId,'formacao_id'=>$fid]);
            }
            return $personId;
        });
    }

    public function listing(string $term,int $page): array
    {
        $term=mb_substr(trim($term),0,100); $where='';$args=[];
        if ($term!=='') { $where=' WHERE nome LIKE ?';$args[]='%'.strtr($term,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where.=" ESCAPE '!'"; }
        $total=(int)$this->query('SELECT COUNT(*) FROM colaboradores'.$where,$args)->fetchColumn();
        $page=max(1,min($page,max(1,(int)ceil($total/20))));$offset=($page-1)*20;
        $rows=$this->query("SELECT c.id,c.nome,c.ativo,(SELECT COUNT(*) FROM alocacoes_colaborador a WHERE a.colaborador_id=c.id AND a.ativo=1) AS alocacoes FROM colaboradores c".$where." ORDER BY c.nome,c.id LIMIT 20 OFFSET $offset",$args)->fetchAll();
        return compact('rows','total','page','term');
    }

    public function resolveIssue(int $id,bool $resolved): int
    {
        $row=$this->query('SELECT importacao_id FROM importacao_inconsistencias WHERE id=?',[$id])->fetch();
        if (!$row) throw new DomainException('Inconsistência não encontrada.');
        $this->query('UPDATE importacao_inconsistencias SET resolvido=? WHERE id=?',[(int)$resolved,$id]);
        return (int)$row['importacao_id'];
    }
}
