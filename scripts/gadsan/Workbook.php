<?php
declare(strict_types=1);
namespace Gadsan;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class Workbook
{
    public const HEADERS = ['cargo'=>'cargo','nome'=>'nome','lotacao'=>'lotacao','projeto'=>'projeto','instituicao'=>'instituicao','contato'=>'telefone','nivel'=>'escolaridade','escolaridade'=>'escolaridade','formacao'=>'formacao','data de nascimento'=>'data_nascimento','rg'=>'rg','cpf'=>'cpf','data admissao'=>'data_admissao','data de admissao'=>'data_admissao','e-mail'=>'email','endereco de residencia'=>'endereco','turno'=>'turno','carga horaria/ semana'=>'carga_horaria_semanal','matricula'=>'matricula','tipo sanguineo'=>'tipo_sanguineo','vinculo'=>'vinculo','ligacao'=>'vinculo'];
    public static function key(mixed $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string)$value));
        return mb_strtolower(transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $value));
    }
    public static function read(string $file): array
    {
        $reader=IOFactory::createReaderForFile($file);
        $book=$reader->load($file); $rows=[]; $stats=[];
        foreach($book->getAllSheets() as $sheet) {
            foreach($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                if(preg_match('/^([A-Z]+)[0-9]+$/',$coordinate,$m) && strlen($m[1])>1 && $sheet->getCell($coordinate)->getValue()!==null)throw new \RuntimeException('Conteúdo além da área suportada; rever mapeamento.');
            }
            $title=$sheet->getTitle(); $map=[]; $stats[$title]=['registros'=>0,'linhas_ignoradas'=>0,'cabecalhos'=>[]];
            foreach($sheet->rangeToArray('A1:Z'.$sheet->getHighestDataRow(),null,false,false,true) as $line=>$cells) {
                $candidate=[];
                foreach($cells as $col=>$value) { $k=self::key($value); if(isset(self::HEADERS[$k]))$candidate[$col]=self::HEADERS[$k]; }
                if(in_array('nome',$candidate,true)&&in_array('cpf',$candidate,true)&&count($candidate)>=8) {
                    $map=$candidate;$stats[$title]['cabecalhos'][]=$line;$stats[$title]['linhas_ignoradas']++;continue;
                }
                if(!$map){$stats[$title]['linhas_ignoradas']++;continue;}
                $raw=array_fill_keys(array_unique(array_values(self::HEADERS)),null);foreach($map as $col=>$field)$raw[$field]=$cells[$col];
                $nonempty=array_filter($raw,fn($v)=>$v!==null&&trim((string)$v)!=='');
                if(!$nonempty || (count($nonempty)===1 && preg_match('/lista de colaboradores|gerência|gerencia/iu',(string)reset($nonempty)))) {
                    $stats[$title]['linhas_ignoradas']++;continue;
                }
                foreach($cells as $col=>$v)if(!isset($map[$col])&&$col!=='A'&&$v!==null&&trim((string)$v)!=='')throw new \RuntimeException('Coluna não mapeada na planilha.');
                $rows[]=['aba'=>$title,'linha'=>$line,'raw'=>$raw,'calendar'=>$book->getExcelCalendar()];$stats[$title]['registros']++;
            }
            if(!$map)throw new \RuntimeException('Aba sem cabeçalho reconhecido.');
        }
        $book->disconnectWorksheets();return ['rows'=>$rows,'abas'=>$stats];
    }
}
