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

function portalRequireAdmin(bool $json = false): void
{
    if (Funcionarios::checkLogin('ADMIN')) {
        return;
    }

    if ($json) {
        http_response_code(403);
        portalDashboardJson([
            'success' => false,
            'message' => 'Acesso restrito ao perfil administrador.'
        ]);
    }

    header('Location: /acesso-negado');
    exit;
}

function portalIntelligentAnalysisService()
{
    require_once APP_DIR . '/services/IntelligentAnalysisService.php';
    return new IntelligentAnalysisService(new Sql());
}

function portalUnidadesAtualizadasService()
{
    require_once APP_DIR . '/services/UnidadesAtualizadasService.php';
    return new UnidadesAtualizadasService(new Sql());
}

function portalUpdateMonitoringService()
{
    require_once APP_DIR . '/services/UpdateMonitoringService.php';
    return new UpdateMonitoringService(new Sql());
}

function portalUpdatePublishService()
{
    require_once APP_DIR . '/services/CentralUpdatePublishService.php';
    $config = require APP_DIR . '/config/update-publish.php';
    return new CentralUpdatePublishService($config);
}

function portalUpdateMonitoringFallback(string $message = ''): array
{
    return [
        'summary' => [
            'total' => 0,
            'consultaram' => 0,
            'baixaram' => 0,
            'instalaram' => 0,
            'atualizadas' => 0,
            'pendentes' => 0,
            'erro' => 0,
            'offline' => 0,
            'versoes_antigas' => 0,
            'atualizando' => 0,
            'taxa_sucesso' => 0,
            'ultima_publicada' => '-',
        ],
        'latest' => [],
        'by_version' => [],
        'errors' => [],
        'threshold_minutes' => 60,
        'error' => $message,
    ];
}

function portalLogOperationalError(string $message): void
{
    $dir = defined('LOG_DIR') ? LOG_DIR : (ROOT_DIR . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($dir . DIRECTORY_SEPARATOR . 'operational-monitoring.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

function portalReadJsonPayload(): array
{
    $raw = (string)file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    return $_POST ?: [];
}

function portalValidateUpdateMonitorToken(array $server): void
{
    $expected = '';
    if (function_exists('portal_env')) {
        $expected = trim((string)portal_env('UPDATE_MONITOR_TOKEN', ''));
    }

    if ($expected === '') {
        return;
    }

    $authorization = (string)($server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $received = '';
    if (preg_match('/Bearer\s+(.+)/i', $authorization, $match)) {
        $received = trim($match[1]);
    } elseif (isset($server['HTTP_X_UPDATE_MONITOR_TOKEN'])) {
        $received = trim((string)$server['HTTP_X_UPDATE_MONITOR_TOKEN']);
    }

    if ($received === '' || !hash_equals($expected, $received)) {
        http_response_code(401);
        portalDashboardJson([
            'success' => false,
            'message' => 'Token de monitoramento invalido.'
        ]);
    }
}

function portalPdfLog(string $message): void
{
    $dir = defined('LOG_DIR') ? LOG_DIR : (ROOT_DIR . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    @file_put_contents($dir . DIRECTORY_SEPARATOR . 'pdf-download-lote.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

function portalPdfColumns(Sql $sql): array
{
    static $columns = null;
    if ($columns !== null) {
        return $columns;
    }

    $columns = [];
    try {
        foreach ($sql->select('SHOW COLUMNS FROM tb_relatorios_pdf') as $row) {
            if (!empty($row['Field'])) {
                $columns[] = (string)$row['Field'];
            }
        }
    } catch (Throwable $e) {
        portalPdfLog('Falha ao ler colunas de tb_relatorios_pdf: ' . $e->getMessage());
    }

    return $columns;
}

function portalPdfBuildHistoricoFilter(Sql $sql, array $input): array
{
    $columns = portalPdfColumns($sql);
    $where = [];
    $params = [];

    $data = isset($input['data']) ? trim((string)$input['data']) : '';
    $status = isset($input['status']) ? trim((string)$input['status']) : '';
    $busca = isset($input['busca']) ? trim((string)$input['busca']) : (isset($input['q']) ? trim((string)$input['q']) : '');

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

    if ($busca !== '') {
        $searchable = [
            'nome_arquivo',
            'caminho_remoto',
            'url_publica',
            'responsavel',
            'cpf_responsavel',
            'mensagem_erro',
            'nome_unidade',
            'unidade',
            'unidade_nome',
            'nome_banco',
            'municipio',
            'cidade'
        ];
        $existing = array_values(array_filter($searchable, function ($column) use ($columns) {
            return in_array($column, $columns, true);
        }));

        $terms = [];
        $params[':busca'] = '%' . strtolower($busca) . '%';
        $params[':busca_slug'] = '%' . strtolower(str_replace([' ', '-'], '_', $busca)) . '%';

        foreach ($existing as $column) {
            $safeColumn = '`' . str_replace('`', '``', $column) . '`';
            $terms[] = 'LOWER(COALESCE(' . $safeColumn . ', "")) LIKE :busca';
            $terms[] = 'LOWER(REPLACE(REPLACE(COALESCE(' . $safeColumn . ', ""), " ", "_"), "-", "_")) LIKE :busca_slug';
        }

        if (!empty($terms)) {
            $where[] = '(' . implode(' OR ', $terms) . ')';
        }
    }

    return [
        'where' => $where,
        'whereSql' => count($where) ? 'WHERE ' . implode(' AND ', $where) : '',
        'params' => $params,
    ];
}

function portalPdfSelectColumns(Sql $sql): array
{
    $columns = portalPdfColumns($sql);
    $wanted = [
        'id',
        'data_relatorio',
        'nome_arquivo',
        'url_publica',
        'caminho_remoto',
        'status_upload',
        'mensagem_erro',
        'responsavel',
        'cpf_responsavel',
        'data_geracao',
        'data_upload',
        'nome_unidade',
        'unidade',
        'unidade_nome',
        'nome_banco'
    ];

    $select = [];
    foreach ($wanted as $column) {
        if (in_array($column, $columns, true)) {
            $select[] = '`' . str_replace('`', '``', $column) . '`';
        }
    }

    return $select ?: ['*'];
}

function portalPdfResolvePath(array $row): ?string
{
    $inputs = [];
    foreach (['caminho_remoto', 'nome_arquivo', 'url_publica'] as $key) {
        if (!empty($row[$key])) {
            $value = (string)$row[$key];
            if ($key === 'url_publica') {
                $path = parse_url($value, PHP_URL_PATH);
                if ($path) {
                    $inputs[] = $path;
                }
            } else {
                $inputs[] = $value;
            }
        }
    }

    $baseDirs = [
        ROOT_DIR,
        PUBLIC_DIR,
        STORAGE_DIR,
        PUBLIC_DIR . DIRECTORY_SEPARATOR . 'relatorios',
        PUBLIC_DIR . DIRECTORY_SEPARATOR . 'prato' . DIRECTORY_SEPARATOR . 'relatorios',
        STORAGE_DIR . DIRECTORY_SEPARATOR . 'relatorios',
        STORAGE_DIR . DIRECTORY_SEPARATOR . 'relatorios_pdf',
    ];

    $allowedRoots = array_filter(array_map('realpath', [ROOT_DIR, PUBLIC_DIR, STORAGE_DIR]));
    $candidates = [];

    foreach ($inputs as $input) {
        $input = str_replace('\\', '/', trim((string)$input));
        if ($input === '') {
            continue;
        }

        $trimmed = ltrim($input, '/');
        $basename = basename($trimmed);
        if ($basename === '' || strtolower(pathinfo($basename, PATHINFO_EXTENSION)) !== 'pdf') {
            continue;
        }

        foreach ($baseDirs as $base) {
            $candidates[] = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $trimmed);
            $candidates[] = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $basename;
        }
    }

    foreach (array_unique($candidates) as $candidate) {
        $real = realpath($candidate);
        if ($real === false || !is_file($real)) {
            continue;
        }

        foreach ($allowedRoots as $root) {
            $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
            $normalized = str_replace('\\', '/', $real);
            if (strpos($normalized, $root) === 0) {
                return $real;
            }
        }
    }

    return null;
}

function portalPdfDownloadRemote(array $row, string $tempDir): ?string
{
    $url = trim((string)($row['url_publica'] ?? ''));
    if ($url === '') {
        return null;
    }

    $parts = parse_url($url);
    if (
        empty($parts['scheme']) ||
        empty($parts['host']) ||
        !in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true)
    ) {
        return null;
    }

    $path = isset($parts['path']) ? (string)$parts['path'] : '';
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
        return null;
    }

    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0775, true);
    }

    $target = rtrim($tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pdf_' . (int)($row['id'] ?? 0) . '_' . sha1($url) . '.pdf';
    $fp = @fopen($target, 'wb');
    if (!$fp) {
        return null;
    }

    $ok = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'API_RELATORIO_PRATO-ZipPDF/1.0',
        ]);
        $result = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        $ok = (bool)$result && $http >= 200 && $http < 300;
        if (!$ok) {
            portalPdfLog('Falha ao baixar PDF publico | id=' . (int)($row['id'] ?? 0) . ' | http=' . $http . ' | erro=' . $error . ' | url=' . $url);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'header' => "User-Agent: API_RELATORIO_PRATO-ZipPDF/1.0\r\n",
            ],
        ]);
        $in = @fopen($url, 'rb', false, $context);
        if ($in) {
            stream_copy_to_stream($in, $fp);
            fclose($in);
            $ok = true;
        }
    }

    fclose($fp);

    if (!$ok || !is_file($target) || filesize($target) <= 0) {
        @unlink($target);
        return null;
    }

    $head = (string)@file_get_contents($target, false, null, 0, 4);
    if ($head !== '%PDF') {
        portalPdfLog('Arquivo publico baixado nao parece PDF | id=' . (int)($row['id'] ?? 0) . ' | url=' . $url);
        @unlink($target);
        return null;
    }

    return $target;
}

function portalPdfZipName(string $busca = ''): string
{
    $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($busca));
    $slug = trim((string)$slug, '_-');
    return 'relatorios_pdf_unidade_' . ($slug !== '' ? $slug . '_' : '') . date('Ymd_His') . '.zip';
}

$app->post('/api/updates/status', function () {
    try {
        portalValidateUpdateMonitorToken($_SERVER);
        portalDashboardJson(portalUpdateMonitoringService()->receiveStatus(portalReadJsonPayload(), $_SERVER));
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        portalDashboardJson([
            'success' => false,
            'message' => $e->getMessage(),
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        portalDashboardJson([
            'success' => false,
            'message' => $e->getMessage(),
        ]);
    }
});

$app->get('/', function () {
    header('Location: /admin/login');
    exit;
});

$app->get('/admin', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');

    $stats = [
        'total_funcionarios' => 0,
        'usuarios_ativos' => 0,
        'logs_hoje' => 0,
        'acessos_negados_hoje' => 0,
        'unidades_atualizadas' => 0,
        'ultimo_login' => null
    ];
    $dashboardDbError = '';

    try {
        $sql = new Sql();
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

        $stats['unidades_atualizadas'] = portalUnidadesAtualizadasService()->getDashboardResumo()['atualizadas'] ?? 0;
    } catch (Throwable $e) {
        $dashboardDbError = 'Banco central indisponivel no momento. Os indicadores foram carregados em modo reduzido.';
        portalLogOperationalError('Falha ao carregar indicadores do dashboard: ' . $e->getMessage());
    }

    $recentLogs = [];
    try {
        if (!isset($sql) || !$sql instanceof Sql) {
            $sql = new Sql();
        }
        $recentLogs = $sql->select("
            SELECT nome_funcionario, acao, ip, created_at
            FROM tb_userlogs
            ORDER BY created_at DESC
            LIMIT 8
        ");
    } catch (Throwable $e) {
        $recentLogs = [];
        if ($dashboardDbError === '') {
            $dashboardDbError = 'Banco central indisponivel no momento. Os logs recentes nao foram carregados.';
        }
        portalLogOperationalError('Falha ao carregar logs recentes do dashboard: ' . $e->getMessage());
    }

    $page = new PageAdmin();
    $page->setTpl('index', [
        'stats' => $stats,
        'dashboardChartData' => json_encode([
            $stats['total_funcionarios'],
            $stats['usuarios_ativos'],
            $stats['unidades_atualizadas'],
            $stats['logs_hoje'],
            $stats['acessos_negados_hoje']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'recentLogs' => $recentLogs,
        'dashboardDbError' => $dashboardDbError,
        'usuario' => Funcionarios::getFromSession()->getValues()
    ]);
});

$app->get('/admin/analise-inteligente', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');

    $page = new PageAdmin();
    $page->setTpl('analise-inteligente');
});

$app->get('/admin/unidades-atualizadas', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');

    $filtros = [
        'unidade' => $_GET['unidade'] ?? '',
        'tipo_unidade' => $_GET['tipo_unidade'] ?? '',
        'data_atualizacao' => $_GET['data_atualizacao'] ?? '',
        'status' => $_GET['status'] ?? '',
    ];

    $resultado = portalUnidadesAtualizadasService()->pesquisar($filtros);

    $page = new PageAdmin();
    $page->setTpl('unidades-atualizadas', [
        'filtros' => $filtros,
        'unidades' => $resultado['items'],
        'resumo' => $resultado['resumo'],
    ]);
});

$app->get('/admin/monitoramento-atualizacoes', function () {
    Funcionarios::checkPermission('UPDATE_MONITORING_VIEW');

    $page = new PageAdmin();
    $dashboard = portalUpdateMonitoringFallback();
    $units = [];
    $logs = [];
    $filterOptions = [
        'municipios' => [],
        'tipos' => [],
        'sistemas' => [],
        'versoes_instaladas' => [],
        'versoes_disponiveis' => [],
    ];

    try {
        $service = portalUpdateMonitoringService();
        $dashboard = $service->dashboard();
        $units = $service->units($_GET);
        $logs = $service->logs($_GET);
        $filterOptions = $service->filterOptions();
    } catch (Throwable $e) {
        portalLogOperationalError('Falha ao carregar monitoramento de atualizacoes: ' . $e->getMessage());
        $dashboard = portalUpdateMonitoringFallback('Banco central indisponivel no momento. Tente novamente em alguns minutos.');
    }

    $page->setTpl('monitoramento-atualizacoes', [
        'dashboard' => $dashboard,
        'dashboardJson' => json_encode($dashboard, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
        'unitsJson' => json_encode($units, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
        'logsJson' => json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
        'filterOptionsJson' => json_encode($filterOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
    ]);
});

$app->get('/admin/publicar-atualizacao', function () {
    Funcionarios::checkPermission('UPDATE_PUBLISH_MANAGE');

    $service = portalUpdatePublishService();
    $page = new PageAdmin();
    $page->setTpl('publicar-atualizacao', [
        'currentVersion' => $service->currentVersion(),
    ]);
});

$app->get('/admin/api/update-publish/preview', function () {
    try {
        Funcionarios::checkPermission('UPDATE_PUBLISH_MANAGE');
        portalDashboardJson([
            'success' => true,
            'data' => portalUpdatePublishService()->preview(),
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        portalDashboardJson(['success' => false, 'message' => $e->getMessage()]);
    }
});

$app->post('/admin/api/update-publish/test-connection', function () {
    try {
        Funcionarios::checkPermission('UPDATE_PUBLISH_MANAGE');
        portalDashboardJson(portalUpdatePublishService()->testConnection());
    } catch (Throwable $e) {
        http_response_code(500);
        portalDashboardJson(['success' => false, 'message' => $e->getMessage()]);
    }
});

$app->post('/admin/api/update-publish/publish', function () {
    try {
        Funcionarios::checkPermission('UPDATE_PUBLISH_MANAGE');
        portalDashboardJson(portalUpdatePublishService()->publish($_POST['version'] ?? '', $_POST['changelog'] ?? ''));
    } catch (Throwable $e) {
        http_response_code(500);
        portalDashboardJson(['success' => false, 'message' => $e->getMessage()]);
    }
});

$app->get('/admin/api/updates/monitoring', function () {
    try {
        Funcionarios::checkPermission('UPDATE_MONITORING_VIEW');
        $service = portalUpdateMonitoringService();
        portalDashboardJson([
            'success' => true,
            'dashboard' => $service->dashboard(),
            'units' => $service->units($_GET),
            'logs' => $service->logs($_GET),
            'filters' => $service->filterOptions(),
        ]);
    } catch (Throwable $e) {
        portalLogOperationalError('Falha API monitoramento de atualizacoes: ' . $e->getMessage());
        http_response_code(503);
        portalDashboardJson([
            'success' => false,
            'message' => 'Banco central indisponivel no momento.',
            'dashboard' => portalUpdateMonitoringFallback('Banco central indisponivel no momento.'),
            'units' => [],
            'logs' => [],
        ]);
    }
});

$app->get('/admin/api/updates/unit/:unidade_id', function ($unidadeId) {
    try {
        Funcionarios::checkPermission('DASHBOARD_VIEW');
        portalDashboardJson([
            'success' => true,
            'data' => portalUpdateMonitoringService()->unitDetail($unidadeId, $_GET['sistema'] ?? ''),
        ]);
    } catch (Throwable $e) {
        portalLogOperationalError('Falha detalhe unidade atualizacao: ' . $e->getMessage());
        http_response_code(503);
        portalDashboardJson(['success' => false, 'message' => 'Nao foi possivel carregar os detalhes da unidade.']);
    }
});

$app->get('/admin/api/updates/version/:versao', function ($versao) {
    try {
        Funcionarios::checkPermission('DASHBOARD_VIEW');
        portalDashboardJson([
            'success' => true,
            'data' => portalUpdateMonitoringService()->versionDetail($versao),
        ]);
    } catch (Throwable $e) {
        portalLogOperationalError('Falha detalhe versao atualizacao: ' . $e->getMessage());
        http_response_code(503);
        portalDashboardJson(['success' => false, 'message' => 'Nao foi possivel carregar os detalhes da versao.']);
    }
});

$app->post('/admin/api/updates/config', function () {
    try {
        Funcionarios::checkPermission('DASHBOARD_VIEW');
        portalUpdateMonitoringService()->updateConfig($_POST['offline_threshold_minutes'] ?? 60);
        portalDashboardJson(['success' => true, 'message' => 'Configuracao atualizada.']);
    } catch (Throwable $e) {
        portalLogOperationalError('Falha ao salvar configuracao de monitoramento: ' . $e->getMessage());
        http_response_code(503);
        portalDashboardJson(['success' => false, 'message' => 'Banco central indisponivel no momento.']);
    }
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
        if (isset($_GET['debug_planilha']) && (int)$_GET['debug_planilha'] === 1) {
            $exportador->setDebugPlanilha(true);
        }

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
        if ($page < 1) $page = 1;
        if ($pageSize < 1) $pageSize = 20;
        if ($pageSize > 100) $pageSize = 100;

        $offset = ($page - 1) * $pageSize;
        $filter = portalPdfBuildHistoricoFilter($sql, $_GET);
        $whereSql = $filter['whereSql'];
        $params = $filter['params'];
        $select = portalPdfSelectColumns($sql);

        $totalResult = $sql->select("\n            SELECT COUNT(*) AS total\n            FROM tb_relatorios_pdf\n            {$whereSql}\n        ", $params);

        $total = isset($totalResult[0]['total']) ? (int)$totalResult[0]['total'] : 0;

        $items = $sql->select("\n            SELECT\n                " . implode(",\n                ", $select) . "\n            FROM tb_relatorios_pdf\n            {$whereSql}\n            ORDER BY id DESC\n            LIMIT {$offset}, {$pageSize}\n        ", $params);

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

$app->post('/admin/relatorio/pdf/download-lote', function () {
    Funcionarios::checkPermission('DASHBOARD_VIEW');

    try {
        if (!class_exists('ZipArchive')) {
            throw new Exception('A extensão ZipArchive não está habilitada neste servidor.');
        }

        $payload = portalReadJsonPayload();
        $ids = [];
        if (isset($payload['ids']) && is_array($payload['ids'])) {
            foreach ($payload['ids'] as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }
        $ids = array_values($ids);

        $sql = new Sql();
        $select = portalPdfSelectColumns($sql);
        $params = [];
        $whereSql = '';

        if (!empty($ids)) {
            $placeholders = [];
            foreach ($ids as $idx => $id) {
                $key = ':id' . $idx;
                $placeholders[] = $key;
                $params[$key] = $id;
            }
            $whereSql = 'WHERE id IN (' . implode(',', $placeholders) . ')';
        } else {
            $filters = isset($payload['filters']) && is_array($payload['filters']) ? $payload['filters'] : [];
            $filter = portalPdfBuildHistoricoFilter($sql, $filters);
            $whereSql = $filter['whereSql'];
            $params = $filter['params'];
        }

        $rows = $sql->select("\n            SELECT\n                " . implode(",\n                ", $select) . "\n            FROM tb_relatorios_pdf\n            {$whereSql}\n            ORDER BY id DESC\n        ", $params);

        if (empty($rows)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Nenhum PDF foi encontrado para os critérios informados.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $zipDir = STORAGE_DIR . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($zipDir)) {
            @mkdir($zipDir, 0775, true);
        }

        $busca = '';
        if (isset($payload['filters']['busca'])) {
            $busca = (string)$payload['filters']['busca'];
        }
        $zipName = portalPdfZipName($busca);
        $zipPath = $zipDir . DIRECTORY_SEPARATOR . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Não foi possível criar o arquivo ZIP temporário.');
        }

        $added = 0;
        $missing = [];
        $usedNames = [];
        $downloadedTempFiles = [];

        foreach ($rows as $row) {
            $path = portalPdfResolvePath($row);
            $displayName = basename((string)($row['nome_arquivo'] ?? ($row['caminho_remoto'] ?? 'relatorio.pdf')));
            if ($displayName === '' || strtolower(pathinfo($displayName, PATHINFO_EXTENSION)) !== 'pdf') {
                $displayName = 'relatorio_' . (int)($row['id'] ?? 0) . '.pdf';
            }

            if (!$path) {
                $path = portalPdfDownloadRemote($row, $zipDir);
                if ($path) {
                    $downloadedTempFiles[] = $path;
                } else {
                    $missing[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'arquivo' => $displayName,
                        'caminho' => (string)($row['caminho_remoto'] ?? ''),
                    ];
                    portalPdfLog('PDF ausente | id=' . (int)($row['id'] ?? 0) . ' | arquivo=' . $displayName . ' | caminho=' . (string)($row['caminho_remoto'] ?? '') . ' | url=' . (string)($row['url_publica'] ?? ''));
                    continue;
                }
            }

            $entryName = $displayName;
            if (isset($usedNames[$entryName])) {
                $entryName = pathinfo($displayName, PATHINFO_FILENAME) . '_' . (int)($row['id'] ?? 0) . '.pdf';
            }
            $usedNames[$entryName] = true;

            if ($zip->addFile($path, $entryName)) {
                $added++;
            }
        }

        if (!empty($missing)) {
            $zip->addFromString('PDFs_nao_encontrados.txt', implode(PHP_EOL, array_map(function ($item) {
                return 'ID #' . $item['id'] . ' | ' . $item['arquivo'] . ' | ' . $item['caminho'];
            }, $missing)) . PHP_EOL);
        }

        $zip->close();
        foreach ($downloadedTempFiles as $tempFile) {
            @unlink($tempFile);
        }

        if ($added <= 0) {
            @unlink($zipPath);
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Nenhum arquivo PDF existente foi encontrado no servidor para gerar o ZIP.',
                'missing' => $missing,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($zipName) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('X-PDF-Zip-Added: ' . $added);
        header('X-PDF-Zip-Missing: ' . count($missing));
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    } catch (Throwable $e) {
        portalPdfLog('Erro ao gerar ZIP: ' . $e->getMessage());
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
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
