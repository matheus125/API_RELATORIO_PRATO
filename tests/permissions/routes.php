<?php

declare(strict_types=1);

// Isolated route/menu checks: no application environment or database is loaded.
namespace Hcode\Model {
    class Funcionarios
    {
        public static array $grants = [];
        public static array $checks = [];
        public static int $stopAfter = 1;
        public static bool $loggedIn = true;

        public static function checkLogin(): bool { return self::$loggedIn; }
        public static function hasPermission(string $key): bool { return in_array($key, self::$grants, true); }
        public static function checkPermission(string $key): void
        {
            self::$checks[] = $key;
            $allowed = self::hasPermission($key);
            if (!$allowed || count(self::$checks) >= self::$stopAfter) {
                echo json_encode(['allowed' => $allowed, 'checks' => self::$checks], JSON_THROW_ON_ERROR);
                exit;
            }
        }
    }
}

namespace {
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }

    use Hcode\Model\Funcionarios;

    $root = dirname(__DIR__, 2);
    if (($argv[1] ?? '') === '--route') {
        $input = json_decode(base64_decode($argv[2], true), true, 512, JSON_THROW_ON_ERROR);
        Funcionarios::$grants = $input['grants'];
        Funcionarios::$stopAfter = count($input['expected']);
        $app = new class {
            public array $routes = [];
            public function get(string $path, callable $handler): void { $this->routes['GET ' . $path] = $handler; }
            public function post(string $path, callable $handler): void { $this->routes['POST ' . $path] = $handler; }
        };
        require $root . '/app/routes/admin.php';
        $handler = $app->routes[$input['route']] ?? null;
        if (!$handler) throw new RuntimeException('Missing route: ' . $input['route']);
        // These test paths have at most one required route parameter.
        $handler('fixture');
        throw new RuntimeException('A route reached its data service without authorization.');
    }

    $checks = 0;
    $failures = [];
    function verify(bool $ok, string $label): void
    {
        global $checks, $failures;
        ++$checks;
        if (!$ok) $failures[] = $label;
    }

    function checkRoute(string $route, array $expected, array $grants): array
    {
        $input = base64_encode(json_encode(compact('route', 'expected', 'grants'), JSON_THROW_ON_ERROR));
        $process = proc_open([PHP_BINARY, __FILE__, '--route', $input], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start isolated route check.');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException($route . ': ' . $error);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    $routes = [
        'GET /admin' => ['DASHBOARD_VIEW'],
        'GET /admin/analise-inteligente' => ['ANALISE_INTELIGENTE_VIEW'],
        'GET /admin/unidades-atualizadas' => ['UNIDADES_ATUALIZADAS_VIEW'],
        'GET /admin/monitoramento-atualizacoes' => ['UPDATE_MONITORING_VIEW'],
        'GET /admin/api/updates/monitoring' => ['UPDATE_MONITORING_VIEW'],
        'GET /admin/api/updates/unit/:unidade_id' => ['UPDATE_MONITORING_VIEW'],
        'GET /admin/api/updates/version/:versao' => ['UPDATE_MONITORING_VIEW'],
        'POST /admin/api/updates/config' => ['UPDATE_MONITORING_VIEW', 'UPDATE_MONITORING_MANAGE'],
        'GET /admin/relatorio/planilha' => ['PLANILHA_VIEW'],
        'GET /admin/api/relatorio/planilha/painel' => ['PLANILHA_VIEW'],
        'GET /admin/api/relatorio/planilha/arquivo' => ['PLANILHA_VIEW', 'PLANILHA_EXPORT'],
        'GET /admin/api/relatorio/planilha/exportar' => ['PLANILHA_VIEW', 'PLANILHA_EXPORT'],
        'GET /admin/relatorio/pdf/historico' => ['PDF_HISTORICO_VIEW'],
        'GET /admin/api/relatorio/pdf/historico' => ['PDF_HISTORICO_VIEW'],
        'POST /admin/relatorio/pdf/download-lote' => ['PDF_HISTORICO_VIEW'],
        'GET /admin/consulta-unificada' => ['CONSULTA_UNIFICADA_VIEW'],
        'GET /admin/api/consulta-unificada/resumo' => ['CONSULTA_UNIFICADA_VIEW'],
        'GET /admin/api/consulta-unificada/lista' => ['CONSULTA_UNIFICADA_VIEW'],
        'GET /admin/api/consulta-unificada/download' => ['CONSULTA_UNIFICADA_VIEW'],
        'POST /admin/api/consulta-unificada/sincronizar' => ['CONSULTA_UNIFICADA_VIEW', 'CONSULTA_UNIFICADA_SYNC'],
    ];
    foreach (['resumo', 'comparativo-semanal', 'comparativo-mensal', 'comparativo-anual', 'ranking-unidades', 'alertas', 'analise-inteligente', 'unidades'] as $endpoint) {
        $routes['GET /admin/api/dashboard/' . $endpoint] = ['ANALISE_INTELIGENTE_VIEW'];
    }
    foreach ($routes as $route => $expected) {
        $result = checkRoute($route, $expected, $expected);
        verify($result === ['allowed' => true, 'checks' => $expected], $route . ': independent authorization');
        foreach ($expected as $index => $missing) {
            $result = checkRoute($route, $expected, array_values(array_diff($expected, [$missing])));
            verify($result === ['allowed' => false, 'checks' => array_slice($expected, 0, $index + 1)], $route . ': denies missing ' . $missing);
        }
    }

    require $root . '/vendor/autoload.php';
    require $root . '/app/helpers/RelatorioPlanilhaDashboard.php';
    $dashboard = (new ReflectionClass(RelatorioPlanilhaDashboard::class))->newInstanceWithoutConstructor();
    foreach ([[], ['DASHBOARD_VIEW'], ['PLANILHA_VIEW'], ['PLANILHA_EXPORT'], ['PLANILHA_VIEW', 'PLANILHA_EXPORT']] as $grants) {
        Funcionarios::$grants = $grants;
        verify($dashboard->canExport() === (count($grants) === 2), 'canExport requires both grants: ' . implode(',', $grants));
    }
    Funcionarios::$loggedIn = false;
    verify(!$dashboard->canExport(), 'canExport denies expired login');
    Funcionarios::$loggedIn = true;

    function canAccess(string $key): bool { return Funcionarios::hasPermission($key); }
    function canAnyAccess(array $keys): bool { return count(array_intersect($keys, Funcionarios::$grants)) > 0; }
    function getUserName(): string { return 'Permission fixture'; }
    function currentUserPerfil(): string { return 'CONSULTA'; }
    $cache = sys_get_temp_dir() . '/portal-acl-route-template-' . getmypid() . '/';
    mkdir($cache, 0700, true);
    Rain\Tpl::configure(['tpl_dir' => $root . '/app/views/admin/', 'cache_dir' => $cache, 'tpl_ext' => 'html']);
    function render(string $name, array $grants): string
    {
        Funcionarios::$grants = $grants;
        $tpl = new Rain\Tpl();
        foreach ([
            'total' => 0, 'notificacoes' => [], 'dashboardDbError' => '', 'stats' => array_fill_keys(['total_funcionarios', 'usuarios_ativos', 'unidades_atualizadas', 'logs_hoje', 'acessos_negados_hoje', 'ultimo_login'], 0),
            'recentLogs' => [], 'usuario' => ['nome_funcionario' => 'Fixture', 'perfil' => 'CONSULTA', 'cpf' => ''], 'dashboardChartData' => '[]', 'bases' => [],
            'resumo' => array_fill_keys(['total_registros', 'total_bases', 'total_titulares', 'total_dependentes', 'ultima_sync'], 0),
            'dashboardJson' => '{}', 'unitsJson' => '[]', 'logsJson' => '[]', 'filterOptionsJson' => '{}',
        ] as $key => $value) $tpl->assign($key, $value);
        return $tpl->draw($name, true);
    }
    $links = [
        'ANALISE_INTELIGENTE_VIEW' => '/admin/analise-inteligente',
        'PDF_HISTORICO_VIEW' => '/admin/relatorio/pdf/historico',
        'PLANILHA_VIEW' => '/admin/relatorio/planilha',
        'UNIDADES_ATUALIZADAS_VIEW' => '/admin/unidades-atualizadas',
        'CONSULTA_UNIFICADA_VIEW' => '/admin/consulta-unificada',
    ];
    foreach ($links as $grant => $path) {
        $html = render('header', [$grant]);
        foreach ($links as $otherGrant => $otherPath) {
            verify(str_contains($html, 'href="' . $otherPath . '"') === ($grant === $otherGrant), 'menu ' . $grant . ': ' . $otherGrant);
        }
    }
    foreach ([[], ['DASHBOARD_VIEW']] as $grants) {
        $html = render('header', $grants);
        foreach ($links as $path) verify(!str_contains($html, 'href="' . $path . '"'), 'dashboard does not grant report menu ' . $path);
    }
    foreach ([false, true] as $enabled) {
        $grants = $enabled ? ['PDF_HISTORICO_VIEW', 'UNIDADES_ATUALIZADAS_VIEW'] : ['DASHBOARD_VIEW'];
        $html = render('index', $grants);
        foreach (['/admin/relatorio/pdf/historico', '/admin/unidades-atualizadas'] as $path) verify(str_contains($html, 'href="' . $path . '"') === $enabled, 'dashboard shortcut ' . $path);
        $html = render('consulta-unificada', $enabled ? ['CONSULTA_UNIFICADA_VIEW', 'CONSULTA_UNIFICADA_SYNC'] : ['CONSULTA_UNIFICADA_VIEW', 'DASHBOARD_VIEW']);
        verify(str_contains($html, 'id="btnSincronizarTudo"') === $enabled, 'sync all visibility');
        verify(str_contains($html, 'id="btnSincronizarSelecionadas"') === $enabled, 'sync selected visibility');
        $html = render('monitoramento-atualizacoes', $enabled ? ['UPDATE_MONITORING_VIEW', 'UPDATE_MONITORING_MANAGE'] : ['UPDATE_MONITORING_VIEW', 'DASHBOARD_VIEW']);
        verify(str_contains($html, 'id="offlineConfigForm"') === $enabled, 'monitoring configuration visibility');
    }
    foreach (glob($cache . '*') ?: [] as $file) unlink($file);
    rmdir($cache);
    if ($failures) {
        fwrite(STDERR, implode("\n", $failures) . "\n");
        exit(1);
    }
    echo $checks . " route, menu and export checks passed.\n";
}
