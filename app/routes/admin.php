<?php

use \Hcode\PageAdmin;
use \Hcode\Model\Funcionarios;
use \Hcode\DB\Sql;
use Hcode\Model\Permissions;

function portalDashboardFilters(): array
{
    return [
        'periodo' => $_GET['periodo'] ?? null,
        'inicio' => $_GET['inicio'] ?? null,
        'fim' => $_GET['fim'] ?? null,
        'unidade_id' => $_GET['unidade_id'] ?? null,
    ];
}

function portalDashboardJson($data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function portalIntelligentAnalysisService()
{
    require_once APP_DIR . '/services/IntelligentAnalysisService.php';
    return new IntelligentAnalysisService(new Sql());
}

$app->get('/', function () {
    header('Location: /admin/login');
    exit;
});

$app->get('/admin', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');

    $sql = new Sql();

    $stats = [
        'total_funcionarios' => 0,
        'usuarios_ativos' => 0,
        'logs_hoje' => 0,
        'acessos_negados_hoje' => 0,
        'ultimo_login' => null
    ];

    try {
        $rows = $sql->select("SELECT COUNT(*) AS total FROM tb_funcionario WHERE ativo = 1");
        $stats['total_funcionarios'] = (int)($rows[0]['total'] ?? 0);

        $rows = $sql->select("SELECT COUNT(*) AS total FROM tb_usuario WHERE ativo = 1");
        $stats['usuarios_ativos'] = (int)($rows[0]['total'] ?? 0);

        $rows = $sql->select("SELECT COUNT(*) AS total FROM tb_userlogs WHERE DATE(created_at) = CURDATE()");
        $stats['logs_hoje'] = (int)($rows[0]['total'] ?? 0);

        $rows = $sql->select("SELECT COUNT(*) AS total FROM tb_access_denied WHERE DATE(created_at) = CURDATE()");
        $stats['acessos_negados_hoje'] = (int)($rows[0]['total'] ?? 0);

        $rows = $sql->select("
            SELECT created_at
            FROM tb_userlogs
            WHERE acao = 'LOGIN'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stats['ultimo_login'] = $rows[0]['created_at'] ?? null;
    } catch (Throwable $e) {
        // mantém dashboard funcional mesmo sem alguma tabela adicional
    }

    $recentLogs = [];
    try {
        $recentLogs = $sql->select("
            SELECT nome_funcionario, acao, ip, created_at
            FROM tb_userlogs
            ORDER BY created_at DESC
            LIMIT 8
        ");
    } catch (Throwable $e) {
        $recentLogs = [];
    }

    $page = new PageAdmin();
    $page->setTpl('index', [
        'stats' => $stats,
        'recentLogs' => $recentLogs,
        'usuario' => Funcionarios::getFromSession()->getValues()
    ]);
});

$app->get('/admin/analise-inteligente', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');

    $page = new PageAdmin();
    $page->setTpl('analise-inteligente');
});

$app->get('/admin/api/dashboard/resumo', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');
    portalDashboardJson(portalIntelligentAnalysisService()->getResumo(portalDashboardFilters()));
});

$app->get('/admin/api/dashboard/comparativo-semanal', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');
    portalDashboardJson(portalIntelligentAnalysisService()->getComparativo('semanal', portalDashboardFilters()));
});

$app->get('/admin/api/dashboard/comparativo-mensal', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');
    portalDashboardJson(portalIntelligentAnalysisService()->getComparativo('mensal', portalDashboardFilters()));
});

$app->get('/admin/api/dashboard/comparativo-anual', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');
    portalDashboardJson(portalIntelligentAnalysisService()->getComparativo('anual', portalDashboardFilters()));
});

$app->get('/admin/api/dashboard/ranking-unidades', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');
    portalDashboardJson(portalIntelligentAnalysisService()->getRankingUnidades(portalDashboardFilters()));
});

$app->get('/admin/api/dashboard/alertas', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');
    portalDashboardJson(portalIntelligentAnalysisService()->getAlertas(portalDashboardFilters()));
});

$app->get('/admin/api/dashboard/analise-inteligente', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');
    portalDashboardJson(portalIntelligentAnalysisService()->getAnaliseInteligente(portalDashboardFilters()));
});

$app->get('/admin/api/dashboard/unidades', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');
    portalDashboardJson(portalIntelligentAnalysisService()->getUnidades());
});

$app->get('/admin/login', function () {
    $page = new PageAdmin([
        'header' => false,
        'footer' => false
    ]);

    $page->setTpl('login', [
        'error' => Funcionarios::getError(),
        'attempts' => Funcionarios::getLoginAttempts()
    ]);
});

$app->post('/admin/login', function () {
    try {
        if (!isset($_POST['cpf']) || trim($_POST['cpf']) === '') {
            throw new Exception('Informe o CPF.');
        }

        if (!isset($_POST['senha']) || trim($_POST['senha']) === '') {
            throw new Exception('Informe a senha.');
        }

        if (Funcionarios::getLoginAttempts() >= 5) {
            throw new Exception('Muitas tentativas inválidas. Aguarde um momento antes de tentar novamente.');
        }

        $cpf = preg_replace('/\D+/', '', $_POST['cpf']);
        $funcionario = Funcionarios::login($cpf, $_POST['senha']);
        $_SESSION[Funcionarios::SESSION] = $funcionario->getValues();

        Funcionarios::clearError();
        Funcionarios::clearLoginAttempts();
        Funcionarios::registerAccess($funcionario, 'LOGIN');

        header('Location: /admin');
        exit;
    } catch (Exception $e) {
        Funcionarios::addLoginAttempt();
        $tentativas = Funcionarios::getLoginAttempts();
        $msg = $e->getMessage();

        if ($tentativas < 5 && $msg !== 'Muitas tentativas inválidas. Aguarde um momento antes de tentar novamente.') {
            $msg .= ' Tentativa ' . $tentativas . ' de 5.';
        }

        Funcionarios::setError($msg);
        header('Location: /admin/login');
        exit;
    }
});

$app->get('/admin/logout', function () {
    $funcionario = Funcionarios::getFromSession();

    if ($funcionario && $funcionario->getid_usuario() > 0) {
        Funcionarios::registerAccess($funcionario, 'LOGOUT');
    }

    Funcionarios::logout();
    header('Location: /admin/login');
    exit;
});

$app->get('/acesso-negado', function () {
    Funcionarios::verifyLogin();

    $func = Funcionarios::getFromSession();
    if ($func && $func->getid_usuario() > 0) {
        Funcionarios::registerAccess($func, 'ACESSO_NEGADO');
    }

    $page = new PageAdmin();
    $page->setTpl('acesso-negado');
});

$app->get('/admin/seguranca/permissoes', function () {
    Funcionarios::checkPermission('ACL_PROFILES_MANAGE');

    $page = new PageAdmin();
    $all = Permissions::listAll();

    $page->setTpl('seguranca-permissoes', [
        'permissions' => $all,
        'admin_permissions' => Permissions::listByProfile('ADMIN'),
        'supervisor_permissions' => Permissions::listByProfile('SUPERVISOR'),
        'assessor_permissions' => Permissions::listByProfile('ASSESSOR')
    ]);
});

$app->post('/admin/seguranca/permissoes', function () {
    Funcionarios::checkPermission('ACL_PROFILES_MANAGE');

    foreach (['ADMIN', 'SUPERVISOR', 'ASSESSOR'] as $perfil) {
        Permissions::saveProfilePermissions($perfil, $_POST['permissions'][$perfil] ?? []);
    }

    Permissions::clearSessionCache();
    Funcionarios::refreshPermissions();

    Funcionarios::audit(
        'ACL_PERMISSION_UPDATE',
        'SEGURANCA',
        null,
        'Permissões por perfil foram atualizadas'
    );

    header('Location: /admin/seguranca/permissoes');
    exit;
});

$app->get('/admin/seguranca/acessos-negados', function () {
    Funcionarios::checkPermission('ACL_DENIED_VIEW');

    $sql = new Sql();
    $rows = $sql->select("SELECT * FROM tb_access_denied ORDER BY created_at DESC LIMIT 300");

    $page = new PageAdmin();
    $page->setTpl('seguranca-acessos-negados', ['rows' => $rows]);
});

$app->get('/admin/usuarios/seguranca', function () {
    Funcionarios::checkPermission('USUARIOS_SECURITY_MANAGE');

    $page = new PageAdmin();
    $page->setTpl('usuarios-seguranca', ['usuarios' => Funcionarios::listAllSecurity()]);
});

$app->post('/admin/usuarios/:id_usuario/status', function ($id_usuario) {
    Funcionarios::checkPermission('USUARIOS_SECURITY_MANAGE');
    Funcionarios::setUserActive((int)$id_usuario, (int)($_POST['ativo'] ?? 0));
    header('Location: /admin/usuarios/seguranca');
    exit;
});

$app->post('/admin/funcionarios/:id_pessoa/status-funcionario', function ($id_pessoa) {
    Funcionarios::checkPermission('USUARIOS_SECURITY_MANAGE');
    Funcionarios::setFuncionarioActive((int)$id_pessoa, (int)($_POST['ativo'] ?? 0));
    header('Location: /admin/usuarios/seguranca');
    exit;
});

$app->get('/admin/seguranca/auditoria', function () {
    Funcionarios::checkPermission('AUDITORIA_VIEW');

    $sql = new Sql();
    $logs = $sql->select("
        SELECT *
        FROM tb_userlogs
        ORDER BY created_at DESC
        LIMIT 300
    ");

    $page = new PageAdmin();
    $page->setTpl('seguranca-auditoria', [
        'logs' => $logs
    ]);
});



$app->get('/admin/relatorio/planilha', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');

    require_once APP_DIR . '/helpers/RelatorioPlanilhaDashboard.php';

    $sql = new Sql();
    $dashboard = new RelatorioPlanilhaDashboard($sql);

    $page = new PageAdmin();
    $page->setTpl('relatorio-planilha', [
        'filtros' => [
            'ano' => isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y'),
            'mes' => isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n'),
            'somente_fechados' => isset($_GET['somente_fechados']) ? (int)$_GET['somente_fechados'] : 1,
        ],
        'can_exportar' => $dashboard->canExport(),
    ]);
});

$app->get('/admin/api/relatorio/planilha/painel', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');
    header('Content-Type: application/json; charset=utf-8');

    try {
        require_once APP_DIR . '/helpers/RelatorioPlanilhaDashboard.php';

        $ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
        $mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
        $somenteFechados = isset($_GET['somente_fechados']) ? ((int)$_GET['somente_fechados'] === 1) : true;

        if ($ano < 2000 || $ano > 2100) {
            throw new Exception('Ano inválido.');
        }

        if ($mes < 1 || $mes > 12) {
            throw new Exception('Mês inválido.');
        }

        $sql = new Sql();
        $dashboard = new RelatorioPlanilhaDashboard($sql);
        $painel = $dashboard->getPainel($ano, $mes, $somenteFechados);

        echo json_encode([
            'success' => true,
            'data' => $painel,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível carregar o painel da planilha.',
            'error' => $e->getMessage(),
        ]);
    }
    exit;
});

$app->get('/admin/api/relatorio/planilha/arquivo', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');

    require_once APP_DIR . '/helpers/RelatorioPlanilhaDashboard.php';

    $sql = new Sql();
    $dashboard = new RelatorioPlanilhaDashboard($sql);

    if (!$dashboard->canExport()) {
        http_response_code(403);
        echo 'Você não possui permissão para baixar planilhas.';
        exit;
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $file = isset($_GET['file']) ? trim((string)$_GET['file']) : '';

    $located = $dashboard->localizarArquivoHistorico($id > 0 ? $id : null, $file !== '' ? $file : null);
    if (!$located || empty($located['path']) || !file_exists($located['path'])) {
        http_response_code(404);
        echo 'Arquivo da planilha não encontrado.';
        exit;
    }

    $path = $located['path'];
    $name = $located['name'] ?? basename($path);

    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . basename($name) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});

$app->get('/admin/api/relatorio/planilha/exportar', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');

    $isAjax = (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && stripos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );

    try {
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new Exception('A biblioteca PhpSpreadsheet não está instalada. Rode: composer update phpoffice/phpspreadsheet');
        }

        require_once APP_DIR . '/helpers/RelatorioPlanilhaMensalExporter.php';
        require_once APP_DIR . '/helpers/RelatorioPlanilhaDashboard.php';

        $ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
        $mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
        $somenteFechados = isset($_GET['somente_fechados']) ? ((int)$_GET['somente_fechados'] === 1) : true;

        if ($ano < 2000 || $ano > 2100) {
            throw new Exception('Ano inválido.');
        }

        if ($mes < 1 || $mes > 12) {
            throw new Exception('Mês inválido.');
        }

        $templatePath = ROOT_DIR . '/public/res/admin/excel/resultados_alcancados_2026_modelo.xlsx';
        if (!file_exists($templatePath)) {
            throw new Exception('Modelo da planilha não encontrado em: ' . $templatePath);
        }

        $sql = new Sql();
        $dashboard = new RelatorioPlanilhaDashboard($sql);

        if (!$dashboard->canExport()) {
            throw new Exception('Você não possui permissão para gerar e baixar planilhas.');
        }

        $exportador = new RelatorioPlanilhaMensalExporter($sql);

        $dirSaida = ROOT_DIR . '/storage/relatorios_planilha';
        if (!is_dir($dirSaida)) {
            @mkdir($dirSaida, 0775, true);
        }

        $nomeArquivo = sprintf('relatorio_mensal_%04d_%02d_%s.xlsx', $ano, $mes, date('Ymd_His'));
        $outputPath = $dirSaida . '/' . $nomeArquivo;

        $caminhoFinal = $exportador->exportar($templatePath, $outputPath, $ano, $mes, $somenteFechados);

        if (!file_exists($caminhoFinal)) {
            throw new Exception('A planilha foi processada, mas o arquivo final não foi encontrado.');
        }

        $usuario = Funcionarios::getFromSession();
        $dashboard->registrarGeracao([
            'data_relatorio' => sprintf('%04d-%02d-01', $ano, $mes),
            'nome_arquivo' => basename($caminhoFinal),
            'url_publica' => null,
            'caminho_remoto' => basename($caminhoFinal),
            'status_upload' => 'GERADO_PLANILHA',
            'mensagem_erro' => null,
            'responsavel' => method_exists($usuario, 'getnome_funcionario') ? $usuario->getnome_funcionario() : null,
            'cpf_responsavel' => method_exists($usuario, 'getcpf') ? $usuario->getcpf() : null,
            'data_geracao' => date('Y-m-d H:i:s'),
            'data_upload' => date('Y-m-d H:i:s'),
        ]);

        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($caminhoFinal) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($caminhoFinal));
        readfile($caminhoFinal);
        exit;
    } catch (Exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
            exit;
        }

        http_response_code(500);
        echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><title>Erro ao gerar planilha</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#f7f7f7;padding:30px;color:#333}.box{max-width:860px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;box-shadow:0 8px 24px rgba(0,0,0,.08)}code{background:#f1f1f1;padding:2px 6px;border-radius:6px}</style>';
        echo '</head><body><div class="box">';
        echo '<h2>Erro ao gerar planilha</h2>';
        echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p><a href="/admin/relatorio/planilha">Voltar</a></p>';
        echo '</div></body></html>';
        exit;
    }
});


$app->get('/admin/relatorio/pdf/historico', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');

    $page = new PageAdmin();
    $page->setTpl('relatorio-pdf-historico');
});

$app->get('/admin/api/relatorio/pdf/historico', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');

    header('Content-Type: application/json; charset=utf-8');

    try {
        $sql = new Sql();

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 20;
        $data = isset($_GET['data']) ? trim($_GET['data']) : '';
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';

        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;
        if ($pageSize > 100) $pageSize = 100;

        $offset = ($page - 1) * $pageSize;

        $where = array();
        $params = array();

        if ($data !== '') {
            if (strpos($data, '/') !== false) {
                $dt = DateTime::createFromFormat('d/m/Y', $data);
                if ($dt instanceof DateTime) {
                    $data = $dt->format('Y-m-d');
                }
            }

            $where[] = 'DATE(data_relatorio) = :data';
            $params[':data'] = $data;
        }

        if ($status !== '') {
            $where[] = 'status_upload = :status';
            $params[':status'] = strtoupper($status);
        }

        $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $totalResult = $sql->select("\n            SELECT COUNT(*) AS total\n            FROM tb_relatorios_pdf\n            {$whereSql}\n        ", $params);

        $total = isset($totalResult[0]['total']) ? (int)$totalResult[0]['total'] : 0;

        $items = $sql->select("\n            SELECT\n                id,\n                data_relatorio,\n                nome_arquivo,\n                url_publica,\n                caminho_remoto,\n                status_upload,\n                mensagem_erro,\n                responsavel,\n                cpf_responsavel,\n                data_geracao,\n                data_upload\n            FROM tb_relatorios_pdf\n            {$whereSql}\n            ORDER BY id DESC\n            LIMIT {$offset}, {$pageSize}\n        ", $params);

        echo json_encode(array(
            'success' => true,
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'pages' => $pageSize > 0 ? (int)ceil($total / $pageSize) : 1,
            'items' => $items
        ));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array(
            'success' => false,
            'message' => 'Erro ao carregar histórico de relatórios PDF.',
            'error' => $e->getMessage()
        ));
    }
    exit;
});

$app->get('/admin/consulta-unificada', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');

    require_once APP_DIR . '/services/CadastroUnificadoService.php';

    $sql = new Sql();
    $service = new CadastroUnificadoService($sql);

    $page = new PageAdmin();
    $page->setTpl('consulta-unificada', [
        'resumo' => $service->getResumo(),
        'bases' => $service->listBases(false),
    ]);
});

$app->get('/admin/api/consulta-unificada/resumo', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');
    header('Content-Type: application/json; charset=utf-8');

    try {
        require_once APP_DIR . '/services/CadastroUnificadoService.php';
        $service = new CadastroUnificadoService(new Sql());

        echo json_encode([
            'success' => true,
            'data' => [
                'resumo' => $service->getResumo(),
                'bases' => $service->listBases(false),
            ],
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível carregar o painel da consulta unificada.',
            'error' => $e->getMessage(),
        ]);
    }
    exit;
});

$app->get('/admin/api/consulta-unificada/lista', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');
    header('Content-Type: application/json; charset=utf-8');

    try {
        require_once APP_DIR . '/services/CadastroUnificadoService.php';
        $service = new CadastroUnificadoService(new Sql());

        $resultado = $service->pesquisar([
            'page' => $_GET['page'] ?? 1,
            'pageSize' => $_GET['pageSize'] ?? 25,
            'busca' => $_GET['busca'] ?? '',
            'tipo' => $_GET['tipo'] ?? '',
            'origem_banco' => $_GET['origem_banco'] ?? '',
            'origem_unidade' => $_GET['origem_unidade'] ?? '',
            'somente_ativos' => $_GET['somente_ativos'] ?? 0,
        ]);

        echo json_encode([
            'success' => true,
            'data' => $resultado,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível consultar os cadastros unificados.',
            'error' => $e->getMessage(),
        ]);
    }
    exit;
});

$app->post('/admin/api/consulta-unificada/sincronizar', function () {
    header('Content-Type: application/json; charset=utf-8');

    try {
        Funcionarios::checkPermission('DASHBOARD_VIEW');

        $serviceFile = APP_DIR . '/services/CadastroUnificadoService.php';
        if (!file_exists($serviceFile)) {
            throw new Exception('Arquivo não encontrado: ' . $serviceFile);
        }

        require_once $serviceFile;

        if (!class_exists('CadastroUnificadoService')) {
            throw new Exception('Classe CadastroUnificadoService não encontrada.');
        }

        $baseIds = [];
        if (isset($_POST['base_ids'])) {
            $baseIds = is_array($_POST['base_ids'])
                ? $_POST['base_ids']
                : explode(',', (string) $_POST['base_ids']);
        }

        $baseIds = array_values(array_filter(array_map(function ($id) {
            $id = trim((string)$id);
            return $id !== '' ? (int)$id : null;
        }, $baseIds), function ($id) {
            return !is_null($id) && $id > 0;
        }));

        $service = new CadastroUnificadoService(new Sql());
        $resultado = $service->sincronizar($baseIds, ['tb_titular', 'tb_dependentes']);

        echo json_encode([
            'success' => true,
            'base_ids_recebidos' => $baseIds,
            'data' => $resultado,
        ]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível sincronizar as bases cadastradas.',
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ]);
    }

    exit;
});
