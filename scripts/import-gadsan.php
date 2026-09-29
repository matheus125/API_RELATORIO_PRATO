<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');
require dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__).'/config/env.php';
portal_load_env(dirname(__DIR__).'/.env');
require __DIR__.'/gadsan/Workbook.php';
require __DIR__.'/gadsan/Normalizer.php';
require __DIR__.'/gadsan/Importer.php';
$options=getopt('',['file:','apply','help']);
if(isset($options['help'])||!isset($options['file'])){echo "Uso: php scripts/import-gadsan.php --file=/caminho/arquivo.xlsx [--apply]\nSem --apply: simulação em memória, sem conexão com banco.\nCom --apply: DB_* do .env local, ou GADSAN_DSN/GADSAN_DB_USER/GADSAN_DB_PASSWORD explícitos.\n";exit(isset($options['help'])?0:1);}
try{
    $file=realpath($options['file']);if(!$file||!is_readable($file))throw new RuntimeException('Arquivo indisponível.');
    $hash=hash_file('sha256',$file);$workbook=Gadsan\Workbook::read($file);
    if(hash_file('sha256',$file)!==$hash)throw new RuntimeException('Arquivo alterado durante leitura.');
    $db=null;
    if(isset($options['apply'])){
        $dsn=getenv('GADSAN_DSN');
        if($dsn!==false&&$dsn!==''){
            if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Configure GADSAN_DSN MySQL.');
            $user=getenv('GADSAN_DB_USER');$password=getenv('GADSAN_DB_PASSWORD');
            if($user===false||$password===false)throw new RuntimeException('Informe credenciais GADSAN explícitas para o DSN alternativo.');
        }else{
            $dsn='mysql:host='.portal_env('DB_HOST','127.0.0.1').';port='.portal_env('DB_PORT','3306').';dbname='.portal_env('DB_NAME','portal_relatorios').';charset=utf8mb4';
            $user=portal_env('DB_USER','dev');$password=portal_env('DB_PASSWORD','');
        }
        $db=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
        $db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_0900_as_ci");$db->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    }
    echo json_encode((new Gadsan\Importer($db))->run($file,$workbook),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"Importação não concluída (".get_class($e)."). Verifique arquivo, estrutura, conexão e requisitos. Dados pessoais e detalhes SQL omitidos.\n");exit(1);}
