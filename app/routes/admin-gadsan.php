<?php
use Hcode\Model\Funcionarios;
use Hcode\Model\Permissions;
use Hcode\PageAdmin;
use Hcode\Audit\AuditLogger;

require_once APP_DIR.'/services/GadsanService.php';

function gadsanGuard(string $permission, bool $post=false): void
{
    Funcionarios::verifyLogin();
    Permissions::syncDefinitions();
    Funcionarios::refreshPermissions();
    Funcionarios::checkPermission($permission);
    header('Cache-Control: no-store, private');
    header('Referrer-Policy: same-origin');
    if (!isset($_SESSION['gadsan_csrf'])) $_SESSION['gadsan_csrf']=bin2hex(random_bytes(32));
    if ($post && (!is_string($_POST['_csrf']??null)||!hash_equals($_SESSION['gadsan_csrf'],$_POST['_csrf']))) {
        \Slim\Slim::getInstance()->halt(403,'Sessão de formulário inválida. Recarregue a página e tente novamente.');
    }
}
function ge(mixed $value): string { return htmlspecialchars(is_scalar($value)?(string)$value:'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function gadsanRender(string $view,array $data=[]): void
{
    $csrf=$_SESSION['gadsan_csrf'];
    $flash=$_SESSION['gadsan_flash']??null; unset($_SESSION['gadsan_flash']);
    $canEdit=Funcionarios::hasPermission('GADSAN_EDIT');
    $canAux=Funcionarios::hasPermission('GADSAN_AUX');
    $canImport=Funcionarios::hasPermission('GADSAN_IMPORT');
    $canView=Funcionarios::hasPermission('GADSAN_VIEW');
    extract($data,EXTR_SKIP);
    $adminLayout=new PageAdmin();
    require APP_DIR.'/views/admin/gadsan/layout-top.php';
    require APP_DIR.'/views/admin/gadsan/'.$view.'.php';
    echo '</div></main>';
    echo '<script src="/res/admin/gadsan/masks.js" defer></script>';
    echo '<script src="/res/admin/gadsan/forms.js" defer></script>';
    unset($adminLayout);
}
function gadsanAudit(string $action,string $entity,int $id): void
{
    AuditLogger::log(['categoria'=>'CHANGE','acao'=>$action,'modulo'=>'GADSAN','entidade'=>$entity,'registro_id'=>$id,'descricao'=>'Operação no cadastro GADSAN.']);
}
function gadsanRedirect(string $path,string $message): void
{
    $_SESSION['gadsan_flash']=$message;
    \Slim\Slim::getInstance()->redirect($path,303);
}
function gadsanFailure(Throwable $error): string
{
    if ($error instanceof \Slim\Exception\Stop) throw $error;
    \Slim\Slim::getInstance()->response()->status($error instanceof DomainException?422:500);
    return $error instanceof DomainException?$error->getMessage():'Não foi possível concluir a operação. Os dados não foram salvos. Tente novamente.';
}

$app->get('/admin/gadsan',function() {
    gadsanGuard('GADSAN_VIEW');
    $s=new GadsanService();
    $listing=$s->listing(is_string($_GET['busca']??null)?$_GET['busca']:'',max(1,(int)($_GET['pagina']??1)));
    gadsanRender('list',['listing'=>$listing]);
});

$app->get('/admin/gadsan/colaboradores/novo',function() {
    gadsanGuard('GADSAN_EDIT');
    gadsanRender('person',['person'=>[],'catalogs'=>(new GadsanService())->catalogs(true),'error'=>null]);
});
$app->post('/admin/gadsan/colaboradores/novo',function() {
    gadsanGuard('GADSAN_EDIT',true); $s=new GadsanService();
    try {
        if (($_POST['_complete']??'')!=='1') throw new DomainException('O formulário chegou incompleto. Recarregue a página antes de salvar.');
        $id=$s->savePerson($_POST);
        gadsanAudit('CREATE','colaboradores',$id);
        gadsanRedirect('/admin/gadsan/colaboradores/'.$id,'Colaborador cadastrado com sucesso.');
    } catch (Throwable $e) {
        gadsanRender('person',['person'=>$_POST,'catalogs'=>$s->catalogs(true),'error'=>gadsanFailure($e)]);
    }
});
$app->get('/admin/gadsan/colaboradores/:id',function($id) {
    gadsanGuard('GADSAN_VIEW'); $s=new GadsanService();
    try { $person=$s->person((int)$id); }
    catch (DomainException $e) { \Slim\Slim::getInstance()->halt(404,'Colaborador não encontrado.'); }
    gadsanRender('person',['person'=>$person,'catalogs'=>$s->catalogs(true),'error'=>null]);
});
$app->post('/admin/gadsan/colaboradores/:id',function($id) {
    gadsanGuard('GADSAN_EDIT',true); $s=new GadsanService();
    try {
        if (($_POST['_complete']??'')!=='1') throw new DomainException('O formulário chegou incompleto. Recarregue a página antes de salvar.');
        $s->savePerson($_POST,(int)$id); gadsanAudit('UPDATE','colaboradores',(int)$id);
        gadsanRedirect('/admin/gadsan/colaboradores/'.(int)$id,'Alterações salvas com sucesso.');
    } catch (Throwable $e) {
        gadsanRender('person',['person'=>array_merge($_POST,['id'=>(int)$id]),'catalogs'=>$s->catalogs(true),'error'=>gadsanFailure($e)]);
    }
});

$app->get('/admin/gadsan/auxiliares',function() {
    gadsanGuard('GADSAN_AUX'); $table=$_GET['tipo']??'cargos';
    if (!is_string($table)||!isset(GadsanService::CATALOGS[$table])) \Slim\Slim::getInstance()->halt(404,'Catálogo não encontrado.');
    $catalogs=(new GadsanService())->catalogs(true); $item=[];
    foreach ($catalogs[$table] as $row) if ((int)$row['id']===(int)($_GET['editar']??0)) $item=$row;
    gadsanRender('catalog',['table'=>$table,'catalogs'=>$catalogs,'item'=>$item,'error'=>null]);
});
$app->post('/admin/gadsan/auxiliares/:table',function($table) {
    gadsanGuard('GADSAN_AUX',true); $s=new GadsanService();
    if (!isset(GadsanService::CATALOGS[$table])) \Slim\Slim::getInstance()->halt(404,'Catálogo não encontrado.');
    try {
        $id=$s->catalogSave($table,$_POST);gadsanAudit('SAVE',$table,$id);
        gadsanRedirect('/admin/gadsan/auxiliares?tipo='.$table,'Cadastro auxiliar salvo.');
    } catch (Throwable $e) { gadsanRender('catalog',['table'=>$table,'catalogs'=>$s->catalogs(true),'item'=>$_POST,'error'=>gadsanFailure($e)]); }
});
$app->get('/admin/gadsan/opcoes',function() {
    gadsanGuard('GADSAN_EDIT');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode((new GadsanService())->catalogs(true),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
});

$app->get('/admin/gadsan/importacoes',function() {
    gadsanGuard('GADSAN_IMPORT'); $s=new GadsanService();
    gadsanRender('imports',['imports'=>$s->query('SELECT * FROM importacoes ORDER BY id DESC LIMIT 100')->fetchAll()]);
});
$app->get('/admin/gadsan/importacoes/:id',function($id) {
    gadsanGuard('GADSAN_IMPORT');$s=new GadsanService();
    $import=$s->query('SELECT * FROM importacoes WHERE id=?',[(int)$id])->fetch();
    if (!$import) \Slim\Slim::getInstance()->halt(404,'Importação não encontrada.');
    $total=(int)$s->query('SELECT COUNT(*) FROM importacao_origens WHERE importacao_id=?',[(int)$id])->fetchColumn();
    $page=max(1,min((int)($_GET['pagina']??1),max(1,(int)ceil($total/25))));$offset=($page-1)*25;
    $origins=$s->query("SELECT id,aba,linha,colaborador_id,resultado FROM importacao_origens WHERE importacao_id=? ORDER BY id LIMIT 25 OFFSET $offset",[(int)$id])->fetchAll();
    $pending=$s->query('SELECT tipo_inconsistencia, COUNT(*) AS total, SUM(resolvido=0) AS pendentes FROM importacao_inconsistencias WHERE importacao_id=? GROUP BY tipo_inconsistencia',[(int)$id])->fetchAll();
    gadsanRender('import',['import'=>$import,'origins'=>$origins,'pending'=>$pending,'page'=>$page,'total'=>$total]);
});
$app->get('/admin/gadsan/origens/:id',function($id) {
    gadsanGuard('GADSAN_IMPORT');$s=new GadsanService();
    $origin=$s->query('SELECT * FROM importacao_origens WHERE id=?',[(int)$id])->fetch();
    if (!$origin) \Slim\Slim::getInstance()->halt(404,'Origem não encontrada.');
    $issues=$s->query('SELECT * FROM importacao_inconsistencias WHERE origem_id=? ORDER BY id',[(int)$id])->fetchAll();
    gadsanRender('origin',['origin'=>$origin,'issues'=>$issues]);
});
$app->post('/admin/gadsan/inconsistencias/:id',function($id) {
    gadsanGuard('GADSAN_IMPORT',true); $s=new GadsanService();
    $row=$s->query('SELECT origem_id FROM importacao_inconsistencias WHERE id=?',[(int)$id])->fetch();
    if (!$row) \Slim\Slim::getInstance()->halt(404,'Inconsistência não encontrada.');
    $importId=$s->resolveIssue((int)$id,($_POST['resolvido']??'0')==='1');
    gadsanAudit('REVIEW','importacao_inconsistencias',(int)$id);
    gadsanRedirect($row['origem_id']?'/admin/gadsan/origens/'.$row['origem_id']:'/admin/gadsan/importacoes/'.$importId,'Revisão atualizada. O valor original foi preservado.');
});

$app->post('/admin/gadsan/importacoes/enviar',function() {
    gadsanGuard('GADSAN_IMPORT',true); $s=new GadsanService();$dir=null;$file=null;
    try {
        if (($_POST['confirmar']??'')!=='1') throw new DomainException('Confirme o envio da planilha.');
        $upload=$_FILES['arquivo']??[];
        if (($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new DomainException('Não foi possível receber o arquivo. Confira o limite de upload do servidor.');
        if (!is_string($upload['name']??null)||!is_string($upload['tmp_name']??null)||!is_uploaded_file($upload['tmp_name'])||($upload['size']??0)>10*1024*1024) throw new DomainException('Envie um arquivo Excel de até 10 MB.');
        $name=basename(str_replace('\\','/',$upload['name']));
        if (mb_strlen($name)>200||strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='xlsx'||preg_match('/[\x00-\x1f]/',$name)) throw new DomainException('Envie um arquivo com extensão .xlsx e nome válido.');
        $zip=new ZipArchive();
        if ($zip->open($upload['tmp_name'])!==true) throw new DomainException('O arquivo não é uma planilha XLSX válida.');
        $size=0;for($i=0;$i<$zip->numFiles;$i++)$size+=(int)$zip->statIndex($i)['size'];
        $valid=$zip->locateName('xl/workbook.xml')!==false;$zip->close();
        if (!$valid||$size>64*1024*1024) throw new DomainException('Estrutura XLSX inválida ou arquivo descompactado acima de 64 MB.');
        $dir=sys_get_temp_dir().'/gadsan-upload-'.bin2hex(random_bytes(12));
        if (!mkdir($dir,0700)) throw new RuntimeException('Não foi possível preparar a importação.');
        $file=$dir.'/'.$name;
        if (!move_uploaded_file($upload['tmp_name'],$file)) throw new RuntimeException('Falha no recebimento.');
        chmod($file,0600);
        require_once ROOT_DIR.'/scripts/gadsan/Workbook.php';
        require_once ROOT_DIR.'/scripts/gadsan/Importer.php';
        $workbook=\Gadsan\Workbook::read($file);
        if (count($workbook['rows'])>10000) throw new DomainException('O limite por carga é de 10.000 linhas de dados.');
        $report=(new \Gadsan\Importer($s->db))->run($file,$workbook);
        $id=(int)$report['importacao_id'];gadsanAudit('IMPORT','importacoes',$id);
    } catch (Throwable $e) {
        $error=gadsanFailure($e);
        gadsanRender('imports',['imports'=>$s->query('SELECT * FROM importacoes ORDER BY id DESC LIMIT 100')->fetchAll(),'error'=>$error]);
        return;
    } finally { if ($file&&is_file($file)) unlink($file);if ($dir&&is_dir($dir)) rmdir($dir); }
    gadsanRedirect('/admin/gadsan/importacoes/'.$id,isset($report['status'])&&$report['status']==='ja_importado'?'Este arquivo já foi importado. Nenhum registro foi duplicado.':'Importação concluída. Confira as linhas e as pendências.');
});
