<?php
declare(strict_types=1);
namespace Gadsan;
use PhpOffice\PhpSpreadsheet\Shared\Date;

final class Normalizer
{
    public static function text(mixed $v): ?string
    {
        if($v===null)return null;
        $v=preg_replace('/\s+/u',' ',trim((string)$v));
        return $v===''?null:$v;
    }
    public static function cpfValid(string $cpf): bool
    {
        if(!preg_match('/^\d{11}$/D',$cpf)||preg_match('/^(\d)\1{10}$/D',$cpf))return false;
        for($t=9;$t<11;$t++){ $sum=0;for($i=0;$i<$t;$i++)$sum+=(int)$cpf[$i]*($t+1-$i);$digit=(10*$sum)%11;if($digit===10)$digit=0;if((int)$cpf[$t]!==$digit)return false; }
        return true;
    }
    public static function date(mixed $v, int $calendar=1900): ?string
    {
        if(self::text($v)===null)return null;
        if(is_int($v)||is_float($v)) {
            if($v<1||$v>100000||floor($v)!=$v||($calendar===1900 && (int)$v===60))throw new \InvalidArgumentException();
            Date::setExcelCalendar($calendar);return Date::excelToDateTimeObject($v,new \DateTimeZone('UTC'))->format('Y-m-d');
        }
        if(!preg_match('/^(?:[0-9]{1,2}\/[0-9]{1,2}\/[1-9][0-9]{3}|[0-9]{1,2}-[0-9]{1,2}-[1-9][0-9]{3}|[1-9][0-9]{3}-[0-9]{1,2}-[0-9]{1,2})$/D',trim((string)$v)))throw new \InvalidArgumentException();
        foreach(['!d/m/Y','!d-m-Y','!Y-m-d'] as $f){$d=\DateTimeImmutable::createFromFormat($f,trim((string)$v));$e=\DateTimeImmutable::getLastErrors();if($d&&($e===false||(!$e['warning_count']&&!$e['error_count'])))return $d->format('Y-m-d');}
        throw new \InvalidArgumentException();
    }
    public static function normalize(array $row): array
    {
        $raw=$row['raw'];$data=[];$issues=[];
        $issue=function(string $field,string $type,string $description)use(&$issues,$raw){$issues[]=['campo'=>$field,'valor_original'=>$raw[$field]??null,'tipo_inconsistencia'=>$type,'descricao'=>$description];};
        foreach($raw as $field=>$value){$v=self::text($value);if($v!==null&&in_array(Workbook::key($v),['-','nao se aplica','n/a'],true))$v=null;$data[$field]=$v;}
        if(empty($data['nome']))$issue('nome','campo_obrigatorio_ausente','Nome ausente; linha preservada para revisão, sem criar pessoa.');
        foreach(['cpf','telefone'] as $field)if($data[$field]!==null){$data[$field]=preg_replace('/\D/','',$data[$field]);if($field==='cpf'&&!self::cpfValid($data[$field]))$issue($field,'cpf_invalido','Quantidade de dígitos ou verificadores inválidos; pessoa preservada.');if($field==='telefone'){if(!in_array(strlen($data[$field]),[8,9,10,11,12,13],true)||(strlen($data[$field])>11&&!str_starts_with($data[$field],'55'))||preg_match('/^(\d)\1+$/D',$data[$field]))$issue($field,'telefone_invalido','Formato não reconhecido; não foi acrescentado DDD.');elseif(strlen($data[$field])<=9)$issue($field,'telefone_sem_ddd','Telefone local sem DDD; confirmar manualmente.');}if($data[$field]==='')$data[$field]=null;}
        foreach(['cpf','rg','matricula'] as $field)if(isset($raw[$field])&&(is_int($raw[$field])||is_float($raw[$field])))$issue($field,'identificador_numerico_excel','Excel armazenou o identificador como número; zeros iniciais podem ter sido perdidos. Nenhum zero foi inventado.');
        foreach(['data_nascimento','data_admissao'] as $field){try{$data[$field]=$data[$field]===null?null:self::date($raw[$field],$row['calendar']);}catch(\InvalidArgumentException){$data[$field]=null;$issue($field,'data_invalida','Data impossível ou formato não reconhecido; consultar valor original.');}if($data[$field]!==null&&($data[$field]>date('Y-m-d')||$data[$field]<'1900-01-01'||($field==='data_nascimento'&&$data[$field]>date('Y-m-d',strtotime('-14 years')))))$issue($field,'data_suspeita','Data fora do intervalo esperado; mantida sem correção.');}
        if($data['data_nascimento']&&$data['data_admissao']&&$data['data_admissao']<$data['data_nascimento'])$issue('data_admissao','data_suspeita','Admissão anterior ao nascimento.');
        if($data['email']!==null){$data['email']=mb_strtolower(trim($data['email']));if(!filter_var($data['email'],FILTER_VALIDATE_EMAIL))$issue('email','email_invalido','Formato de e-mail inválido.');elseif(in_array(explode('@',$data['email'])[1],['homail.com','outlok.com','hptmail.co','hotmai.com','gmai.com','hotmal.com'],true))$issue('email','email_suspeito','Domínio possivelmente incorreto; nenhuma substituição realizada.');}
        $hours=$data['carga_horaria_semanal'];$data['carga_horaria_semanal']=null;
        if($hours!==null){if(preg_match('/^(\d{1,3})\s*(h|horas?)?$/iu',$hours,$m)&&(int)$m[1]>0&&(int)$m[1]<=168)$data['carga_horaria_semanal']=(int)$m[1];else $issue('carga_horaria_semanal','carga_horaria_invalida','Carga deve ser um número inteiro entre 1 e 168.');}
        if($data['tipo_sanguineo']!==null){$v=strtoupper(str_replace(' ','',$data['tipo_sanguineo']));if(preg_match('/^(A|B|AB|O)[+-]$/D',$v))$data['tipo_sanguineo']=$v;else{$data['tipo_sanguineo']=null;$issue('tipo_sanguineo','tipo_sanguineo_invalido','Grupo sanguíneo não reconhecido.');}}
        if($data['turno']!==null){$turnos=['matutino'=>'Matutino','vespertino'=>'Vespertino','integral'=>'Integral'];$data['turno']=$turnos[Workbook::key($data['turno'])]??$data['turno'];}
        $data['natureza_contratacao']=null;
        foreach(['instituicao','projeto'] as $field)if(Workbook::key($data[$field])==='comissionado'){$data['natureza_contratacao']='Comissionado';$data[$field]=null;$issue($field,'classificacao_revisar','Comissionado classificado como natureza de contratação; instituição/projeto não inferido.');}
        if(Workbook::key($data['vinculo']??null)==='aadesam'){$issue('vinculo','classificacao_revisar','Valor de instituição no campo vínculo; vínculo deixado pendente.');$data['vinculo']=null;}
        $level=Workbook::key($data['escolaridade']);$data['situacao_escolaridade']=null;
        if($level!=='') {
            if(preg_match('/incompleto|cursando|trancad/',$level,$m))$data['situacao_escolaridade']=str_starts_with($m[0],'trancad')?'Trancado':(str_contains($level,'cursando')?'Cursando':'Incompleto');elseif(str_contains($level,'completo'))$data['situacao_escolaridade']='Completo';
            $base=preg_replace('/\b(ensino|completo|incompleto|cursando)\b/','',$level);$base=trim(preg_replace('/\s+/',' ',$base));
            $levels=['medio'=>'Médio','2° grau'=>'Médio','superior'=>'Superior','tecnico'=>'Técnico','fundamental'=>'Fundamental'];
            if(isset($levels[$base]))$data['escolaridade']=$levels[$base];else{$data['escolaridade']=null;$data['situacao_escolaridade']=null;$issue('escolaridade','escolaridade_ambigua','Nível misturado com outra informação ou não reconhecido; requer revisão.');}
        }
        if(Workbook::key($data['lotacao'])==='a disposicao'){$data['lotacao']=null;$issue('lotacao','lotacao_ambigua','À disposição não identifica uma unidade; lotação pendente.');}
        elseif($data['lotacao']!==null&&str_contains($data['lotacao'],'/'))$issue('lotacao','lotacao_ambigua','Lotação composta; expressão preservada sem inferir múltiplas unidades.');
        $data['formacoes']=[];
        if($data['formacao']!==null){$f=$data['formacao'];if(in_array(Workbook::key($f),['professor','jornalista','biologa','assistente social','nutricionista','esteticista','ass.social'],true)){$issue('formacao','formacao_ocupacao','Termo de ocupação no campo formação; confirmar curso antes de cadastrar.');}elseif(preg_match('/\*|\]|\be\b|especialista|incompleto|cursando|pedagodia|eologia/iu',$f)){$issue('formacao','formacao_ambigua','Formação com anotação, composição ou grafia suspeita; preservada apenas na origem até revisão.');}else{foreach(preg_split('/\s*\/\s*/',$f) as $part)$data['formacoes'][]=$part;}}
        foreach(['cargo','lotacao','projeto','formacao','vinculo'] as $field)if($data[$field]!==null&&preg_match('/allimenta|viiver|surperior|\*|\]| - JULIANA/iu',$data[$field]))$issue($field,'valor_suspeito','Grafia ou anotação não padronizada; não foi unificada automaticamente.');
        return $row+['data'=>$data,'issues'=>$issues];
    }
}
